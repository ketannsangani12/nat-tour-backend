<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGatewayInterface;
use Carbon\Carbon;
use Exception;
use Stripe\Account;
use Stripe\Charge;
use Stripe\Checkout\Session;
use Stripe\Collection;
use Stripe\Customer;
use Stripe\Dispute;
use Stripe\File;
use Stripe\Invoice;
use Stripe\InvoiceItem;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Stripe\Refund;
use Stripe\SearchResult;
use Stripe\StripeClient;
use Stripe\Terminal\ConnectionToken;
use Stripe\Terminal\Location;
use Stripe\Terminal\Reader;
use Stripe\Token;
use Stripe\Transfer;
use Stripe\TransferReversal;
use Stripe\Exception\IdempotencyException;

class StripePaymentGateway implements PaymentGatewayInterface
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = initStripeClient();
    }

    public function createPayment(array $params, array $opts = []): PaymentIntent
    {
        $params = PlatformChargeStatementDescriptor::normalize($params, $opts);
        $params = PlatformFeeMetadata::applyToPaymentIntentParams($params);

        $requestOptions = [
            'idempotency_key' => Carbon::now()->timestamp . '-' . rand(1000, 9999),
            ...$opts,
        ];
        try {
            return $this->stripe->paymentIntents->create($params, $requestOptions);
        } catch (IdempotencyException $e) {
            throw new Exception('A payment is already being processed for this booking. Please try again shortly.');
        }
    }

    public function updatePayment(string $id, array $params): PaymentIntent
    {
        return $this->stripe->paymentIntents->update($id, PlatformFeeMetadata::applyToPaymentIntentParams($params));
    }

    public function updateConnectedPayment(string $paymentId, array $params, string $connectedAccountId): Charge
    {
        return $this->stripe->charges->update($paymentId, $params, ['stripe_account' => $connectedAccountId]);
    }

    public function refundPayment(array $params, string $mode): Refund
    {
        // A zero or negative amount always means the caller's refund calculation went wrong (e.g.
        // LC-2147, where unpaid split rows were counted as prior refunds). Stripe rejects it anyway;
        // fail here with a message that says what happened. No 'amount' key = full refund, allowed.
        if (array_key_exists('amount', $params) && (float) $params['amount'] <= 0) {
            throw new Exception(sprintf(
                'Refund not sent: the calculated refund amount for %s is %s. Please contact support.',
                $params['payment_intent'] ?? $params['charge'] ?? 'this payment',
                number_format((float) $params['amount'] / 100, 2)
            ));
        }

        return $this->stripe->refunds->create($this->buildRefundParams($params, $mode));
    }

    public function buildRefundParams(array $params, string $mode): array
    {
        if ($mode === PaymentModeResolver::PLATFORM_HELD) {
            // The original charge carried no transfer_data/application_fee_amount, so Stripe has
            // nothing to reverse/refund on the charge itself for these two keys — sending either would
            // error. Only ever removes them; never adds either key if the caller's own business logic
            // already decided not to include it for a destination-mode refund.
            unset($params['reverse_transfer'], $params['refund_application_fee']);
        }

        return $params;
    }

    public function retrievePayment(string $id, array $options = []): PaymentIntent
    {
        return $this->stripe->paymentIntents->retrieve($id, $options);
    }

    public function confirmPayment(string $id, array $params = []): PaymentIntent
    {
        return $this->stripe->paymentIntents->confirm($id, $params);
    }

    public function capturePayment(string $id, array $params = []): PaymentIntent
    {
        return $this->stripe->paymentIntents->capture($id, $params);
    }

    public function cancelPayment(string $id, array $params = []): PaymentIntent
    {
        return $this->stripe->paymentIntents->cancel($id, $params);
    }

    public function searchPayments(array $params): SearchResult
    {
        return $this->stripe->paymentIntents->search($params);
    }

    public function createCheckoutSession(array $params): Session
    {
        return $this->stripe->checkout->sessions->create(PlatformFeeMetadata::applyToCheckoutSessionParams($params));
    }

    public function expireCheckoutSession(string $sessionId): mixed
    {
        return $this->stripe->checkout->sessions->expire($sessionId);
    }

    public function buildConnectedPaymentData(string $destinationId, int $feeAmountCents, ?string $statementDescriptor = null, string $mode = PaymentModeResolver::DESTINATION): array
    {
        if ($mode === PaymentModeResolver::PLATFORM_HELD) {
            // Payout Simplification (2027+ bookings): no transfer_data/application_fee_amount at all —
            // the full charge lands on the platform's own Stripe balance.
            //
            // on_behalf_of still requires the connected account to have card_payments active, even
            // with no transfer_data present — confirmed empirically 2026-09-19 by actually completing
            // a charge (off_session + confirm, reaching real charge creation, not just an unconfirmed
            // PaymentIntent or one stalled at a 3DS requires_action) against a live Canadian test
            // account with only the `transfers` capability: Stripe rejects it outright with "You
            // cannot create a charge with the `on_behalf_of` parameter set to a connected account
            // with `transfers` but without the `card_payments` capability enabled." An earlier
            // verification pass wrongly concluded this combination was safe because it never actually
            // reached charge creation. So on_behalf_of is gated on the SAME connectedAccountHasCardPayments()
            // check the destination-mode branch below already uses.
            //
            // For an account that lacks it (most Canadian accounts on this platform today), fall back
            // to a full `statement_descriptor` override instead of the suffix: unlike on_behalf_of,
            // this is a property of the CHARGING (platform) account, not the connected account, so it
            // needs no capability at all — confirmed empirically to work and to actually render as the
            // operator's own name (Charge::calculated_statement_descriptor), not the platform's.
            $data = [];

            if ($destinationId !== '' && $this->connectedAccountHasCardPayments($destinationId)) {
                $data['on_behalf_of'] = $destinationId;
                if ($statementDescriptor !== null) {
                    $data['statement_descriptor_suffix'] = $statementDescriptor;
                }
            } elseif ($statementDescriptor !== null) {
                $data['statement_descriptor'] = $statementDescriptor;
            }

            // NAT's fee is not on the charge any more; keep it on the PaymentIntent instead so the
            // code completing the payment can record it (see PlatformFeeMetadata).
            $data[PlatformFeeMetadata::MARKER] = $feeAmountCents;

            return $data;
        }

        $data = [
            'application_fee_amount' => $feeAmountCents,
            'transfer_data'          => ['destination' => $destinationId],
        ];

        // on_behalf_of requires card_payments capability; omit it for accounts that only have transfers (e.g. Canada)
        if ($this->connectedAccountHasCardPayments($destinationId)) {
            $data['on_behalf_of'] = $destinationId;
        }

        if ($statementDescriptor !== null) {
            $data['statement_descriptor_suffix'] = $statementDescriptor;
        }

        return $data;
    }

    private function connectedAccountHasCardPayments(string $accountId): bool
    {
        static $cache = [];
        if (!array_key_exists($accountId, $cache)) {
            $account = $this->stripe->accounts->retrieve($accountId);
            $cache[$accountId] = ($account->capabilities->card_payments ?? null) === 'active';
        }
        return $cache[$accountId];
    }

    // ── Customer management ──────────────────────────────────────────────────

    public function createCustomer(array $params): Customer
    {
        return $this->stripe->customers->create($params);
    }

    public function listCustomers(array $params = []): Collection
    {
        return $this->stripe->customers->all($params);
    }

    public function retrieveCustomer(string $id, array $params = []): Customer
    {
        return $this->stripe->customers->retrieve($id, $params);
    }

    public function searchCustomers(array $params): SearchResult
    {
        return $this->stripe->customers->search($params);
    }

    // ── Payment method management ────────────────────────────────────────────

    public function listPaymentMethods(array $params): Collection
    {
        return $this->stripe->paymentMethods->all($params);
    }

    public function retrievePaymentMethod(string $id): PaymentMethod
    {
        return $this->stripe->paymentMethods->retrieve($id);
    }

    public function updatePaymentMethod(string $id, array $params): PaymentMethod
    {
        return $this->stripe->paymentMethods->update($id, $params);
    }

    public function detachPaymentMethod(string $id): PaymentMethod
    {
        return $this->stripe->paymentMethods->detach($id);
    }

    // ── Transfers ────────────────────────────────────────────────────────────

    public function createTransfer(array $params): Transfer
    {
        return $this->stripe->transfers->create($params);
    }

    public function reverseTransfer(string $transferId, array $params = []): TransferReversal
    {
        return $this->stripe->transfers->createReversal($transferId, $params);
    }

    // ── Charges ──────────────────────────────────────────────────────────────

    public function retrieveCharge(string $id, array $options = []): Charge
    {
        return $this->stripe->charges->retrieve($id, $options);
    }

    // ── Refunds ──────────────────────────────────────────────────────────────

    public function listRefunds(array $params = []): Collection
    {
        return $this->stripe->refunds->all($params);
    }

    // ── Disputes ─────────────────────────────────────────────────────────────

    public function updateDispute(string $id, array $params): Dispute
    {
        return $this->stripe->disputes->update($id, $params);
    }

    public function retrieveDispute(string $id): Dispute
    {
        return $this->stripe->disputes->retrieve($id);
    }

    public function createFile(array $params): File
    {
        return $this->stripe->files->create($params);
    }

    // ── Invoices ──────────────────────────────────────────────────────────────

    public function retrieveInvoice(string $id, array $params = [], array $opts = []): Invoice
    {
        return $this->stripe->invoices->retrieve($id, $params, $opts);
    }

    public function finalizeInvoice(string $id, array $params = [], array $opts = []): Invoice
    {
        return $this->stripe->invoices->finalizeInvoice($id, $params, $opts);
    }

    public function createInvoice(array $params, array $opts = []): Invoice
    {
        return $this->stripe->invoices->create($params, $opts);
    }

    public function updateInvoice(string $id, array $params, array $opts = []): Invoice
    {
        return $this->stripe->invoices->update($id, $params, $opts);
    }

    public function voidInvoice(string $id, array $params = [], array $opts = []): Invoice
    {
        return $this->stripe->invoices->voidInvoice($id, $params, $opts);
    }

    public function deleteInvoice(string $id, array $params = [], array $opts = []): Invoice
    {
        return $this->stripe->invoices->delete($id, $params, $opts);
    }

    public function searchInvoices(array $params, array $opts = []): SearchResult
    {
        return $this->stripe->invoices->search($params, $opts);
    }

    // ── Invoice items ─────────────────────────────────────────────────────────

    public function createInvoiceItem(array $params, array $opts = []): InvoiceItem
    {
        return $this->stripe->invoiceItems->create($params, $opts);
    }

    public function updateInvoiceItem(string $id, array $params, array $opts = []): InvoiceItem
    {
        return $this->stripe->invoiceItems->update($id, $params, $opts);
    }

    public function retrieveInvoiceItem(string $id, array $opts = []): InvoiceItem
    {
        return $this->stripe->invoiceItems->retrieve($id, [], $opts);
    }

    public function deleteInvoiceItem(string $id, array $opts = []): InvoiceItem
    {
        return $this->stripe->invoiceItems->delete($id, [], $opts);
    }

    // ── Connected-account customers ───────────────────────────────────────────

    public function createConnectedCustomer(array $params, array $opts = []): Customer
    {
        return $this->stripe->customers->create($params, $opts);
    }

    public function retrieveConnectedCustomer(string $id, array $params = [], array $opts = []): Customer
    {
        return $this->stripe->customers->retrieve($id, $params, $opts);
    }

    public function updateConnectedCustomer(string $id, array $params, array $opts = []): Customer
    {
        return $this->stripe->customers->update($id, $params, $opts);
    }

    // ── Stripe Terminal ───────────────────────────────────────────────────────

    public function createConnectionToken(array $params = [], array $opts = []): ConnectionToken
    {
        return $this->stripe->terminal->connectionTokens->create($params, $opts);
    }

    public function retrieveTerminalLocation(string $id, array $opts = []): Location
    {
        return $this->stripe->terminal->locations->retrieve($id, [], $opts);
    }

    public function createTerminalReader(array $params, array $opts = []): Reader
    {
        return $this->stripe->terminal->readers->create($params, $opts);
    }

    public function retrieveTerminalReader(string $id, array $opts = []): Reader
    {
        return $this->stripe->terminal->readers->retrieve($id, [], $opts);
    }

    public function deleteTerminalReader(string $id, array $opts = []): Reader
    {
        return $this->stripe->terminal->readers->delete($id, [], $opts);
    }

    public function cancelTerminalReaderAction(string $readerId, array $params = [], array $opts = []): Reader
    {
        return $this->stripe->terminal->readers->cancelAction($readerId, $params, $opts);
    }

    // ── Stripe Accounts ───────────────────────────────────────────────────────

    public function createAccount(array $params): Account
    {
        return $this->stripe->accounts->create($params);
    }

    public function retrieveAccount(string $id, array $opts = []): Account
    {
        return $this->stripe->accounts->retrieve($id, [], $opts);
    }

    public function updateAccount(string $id, array $params, array $opts = []): Account
    {
        return $this->stripe->accounts->update($id, $params, $opts);
    }

    public function createAccountExternalAccount(string $accountId, array $params): mixed
    {
        return $this->stripe->accounts->createExternalAccount($accountId, $params);
    }

    // ── Tokens ────────────────────────────────────────────────────────────────

    public function createToken(array $params): Token
    {
        return $this->stripe->tokens->create($params);
    }
}
