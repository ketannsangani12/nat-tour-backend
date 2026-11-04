<?php

namespace App\Contracts\Payments;

interface PaymentGatewayInterface
{
    /**
     * Create a new payment.
     *
     * @param  array  $params
     * @param  array  $opts   e.g. ['stripe_account' => 'acct_xxx'] for connected-account charges
     * @return mixed
     */
    public function createPayment(array $params, array $opts = []): mixed;

    /**
     * Update an existing payment.
     *
     * @param  string  $id
     * @param  array   $params
     * @return mixed
     */
    public function updatePayment(string $id, array $params): mixed;

    /**
     * Update a payment on a connected/destination account.
     *
     * @param  string  $paymentId
     * @param  array   $params
     * @param  string  $connectedAccountId
     * @return mixed
     */
    public function updateConnectedPayment(string $paymentId, array $params, string $connectedAccountId): mixed;

    /**
     * Refund a payment.
     *
     * @param  array   $params  Refund parameters (payment_intent, amount, etc.)
     * @param  string  $mode    One of App\Services\Payments\PaymentModeResolver::DESTINATION /
     *                          PLATFORM_HELD — the settlement mode the ORIGINAL charge actually used
     *                          (read from the persisted Transaction::payment_mode, never re-derived
     *                          from the booking's current tour date). Deliberately required, not
     *                          defaulted: a forgotten mode here would silently send reverse_transfer
     *                          against a charge that has nothing to reverse.
     * @return mixed
     */
    public function refundPayment(array $params, string $mode): mixed;

    /**
     * Strip refund parameters that only make sense for a destination charge (reverse_transfer,
     * refund_application_fee) when the original charge was platform-held — it never carried
     * transfer_data, so Stripe has nothing for those two keys to act on. Never ADDS either key; only
     * ever removes them, and only for PLATFORM_HELD. Called internally by refundPayment(); exposed on
     * the interface so callers that need to inspect the final params (e.g. for a captured-params
     * assertion in a test) can call it directly too.
     *
     * @param  array   $params
     * @param  string  $mode
     * @return array
     */
    public function buildRefundParams(array $params, string $mode): array;

    /**
     * Retrieve a payment by ID.
     *
     * @param  string  $id
     * @param  array   $options  e.g. ['expand' => ['charges.data.transfer']]
     * @return mixed
     */
    public function retrievePayment(string $id, array $options = []): mixed;

    /**
     * Confirm a payment.
     *
     * @param  string  $id
     * @param  array   $params
     * @return mixed
     */
    public function confirmPayment(string $id, array $params = []): mixed;

    /**
     * Capture an authorised payment.
     *
     * @param  string  $id
     * @param  array   $params
     * @return mixed
     */
    public function capturePayment(string $id, array $params = []): mixed;

    /**
     * Cancel a payment that has not yet been captured/succeeded.
     *
     * @param  string  $id
     * @param  array   $params
     * @return mixed
     */
    public function cancelPayment(string $id, array $params = []): mixed;

    /**
     * Search payments using a query string.
     *
     * @param  array  $params  e.g. ['query' => "metadata['booking_id']:'123'"]
     * @return mixed
     */
    public function searchPayments(array $params): mixed;

    /**
     * Create a hosted checkout session.
     *
     * @param  array  $params
     * @return mixed
     */
    public function createCheckoutSession(array $params): mixed;

    /**
     * Expire an open checkout session so it can no longer be used.
     *
     * @param  string  $sessionId
     * @return mixed
     */
    public function expireCheckoutSession(string $sessionId): mixed;

    /**
     * Build the connected payment data for a PaymentIntent/Checkout Session.
     *
     * @param  string       $destinationId
     * @param  int          $feeAmountCents
     * @param  string|null  $statementDescriptor
     * @param  string       $mode  One of App\Services\Payments\PaymentModeResolver::DESTINATION /
     *                             PLATFORM_HELD. Defaults to DESTINATION, reproducing today's exact
     *                             behavior — a safe default, unlike the equivalent parameter on
     *                             refundPayment()/buildRefundParams() below.
     * @return array
     */
    public function buildConnectedPaymentData(string $destinationId, int $feeAmountCents, ?string $statementDescriptor = null, string $mode = 'destination'): array;

    // ── Customer management ──────────────────────────────────────────────────

    /**
     * Create a customer.
     *
     * @param  array  $params  e.g. ['email' => ..., 'name' => ..., 'phone' => ...]
     * @return mixed
     */
    public function createCustomer(array $params): mixed;

    /**
     * List customers, optionally filtered (e.g. by email).
     *
     * @param  array  $params  e.g. ['email' => ..., 'limit' => 1]
     * @return mixed
     */
    public function listCustomers(array $params = []): mixed;

    /**
     * Retrieve a single customer by ID.
     *
     * @param  string  $id
     * @param  array   $params
     * @return mixed
     */
    public function retrieveCustomer(string $id, array $params = []): mixed;

    /**
     * Search customers using a query string.
     *
     * @param  array  $params  e.g. ['query' => "email:'...'", 'limit' => 1]
     * @return mixed
     */
    public function searchCustomers(array $params): mixed;

    // ── Payment method management ────────────────────────────────────────────

    /**
     * List payment methods for a customer.
     *
     * @param  array  $params  e.g. ['customer' => ..., 'type' => 'card']
     * @return mixed
     */
    public function listPaymentMethods(array $params): mixed;

    /**
     * Retrieve a single payment method.
     *
     * @param  string  $id
     * @return mixed
     */
    public function retrievePaymentMethod(string $id): mixed;

    /**
     * Update metadata or other fields on a payment method.
     *
     * @param  string  $id
     * @param  array   $params
     * @return mixed
     */
    public function updatePaymentMethod(string $id, array $params): mixed;

    /**
     * Detach a payment method from its customer.
     *
     * @param  string  $id
     * @return mixed
     */
    public function detachPaymentMethod(string $id): mixed;

    // ── Transfers ────────────────────────────────────────────────────────────

    /**
     * Create a transfer to a connected account.
     *
     * @param  array  $params  e.g. ['amount' => ..., 'currency' => ..., 'destination' => ...]
     * @return mixed
     */
    public function createTransfer(array $params): mixed;

    /**
     * Reverse (partially or fully) an existing transfer.
     *
     * @param  string  $transferId
     * @param  array   $params  e.g. ['amount' => ..., 'description' => ...]
     * @return mixed
     */
    public function reverseTransfer(string $transferId, array $params = []): mixed;

    // ── Charges ──────────────────────────────────────────────────────────────

    /**
     * Retrieve a charge by ID.
     *
     * @param  string  $id
     * @param  array   $options  e.g. ['expand' => ['transfer']]
     * @return mixed
     */
    public function retrieveCharge(string $id, array $options = []): mixed;

    // ── Refunds ──────────────────────────────────────────────────────────────

    /**
     * List refunds.
     *
     * @param  array  $params
     * @return mixed
     */
    public function listRefunds(array $params = []): mixed;

    // ── Disputes ─────────────────────────────────────────────────────────────

    /**
     * Update a dispute (e.g. submit evidence).
     *
     * @param  string  $id
     * @param  array   $params  e.g. ['evidence' => [...], 'submit' => true]
     * @return mixed
     */
    public function updateDispute(string $id, array $params): mixed;

    /**
     * Retrieve a dispute by ID.
     *
     * @param  string  $id
     * @return mixed
     */
    public function retrieveDispute(string $id): mixed;

    /**
     * Upload a file to Stripe (e.g. dispute evidence).
     *
     * @param  array  $params  e.g. ['file' => resource, 'purpose' => 'dispute_evidence']
     * @return mixed
     */
    public function createFile(array $params): mixed;

    // ── Invoices ──────────────────────────────────────────────────────────────

    /**
     * Retrieve a Stripe invoice.
     *
     * @param  string  $id
     * @param  array   $params
     * @param  array   $opts   e.g. ['stripe_account' => 'acct_xxx']
     * @return mixed
     */
    public function retrieveInvoice(string $id, array $params = [], array $opts = []): mixed;

    /**
     * Finalize a draft invoice so it can be sent.
     *
     * @param  string  $id
     * @param  array   $params
     * @param  array   $opts   e.g. ['stripe_account' => 'acct_xxx']
     * @return mixed
     */
    public function finalizeInvoice(string $id, array $params = [], array $opts = []): mixed;

    /**
     * Create a new invoice.
     *
     * @param  array  $params
     * @param  array  $opts   e.g. ['stripe_account' => 'acct_xxx']
     * @return mixed
     */
    public function createInvoice(array $params, array $opts = []): mixed;

    /**
     * Update an existing invoice.
     *
     * @param  string  $id
     * @param  array   $params
     * @param  array   $opts   e.g. ['stripe_account' => 'acct_xxx']
     * @return mixed
     */
    public function updateInvoice(string $id, array $params, array $opts = []): mixed;

    /**
     * Void a finalized invoice.
     *
     * @param  string  $id
     * @param  array   $params
     * @param  array   $opts   e.g. ['stripe_account' => 'acct_xxx']
     * @return mixed
     */
    public function voidInvoice(string $id, array $params = [], array $opts = []): mixed;

    /**
     * Delete a draft invoice.
     *
     * @param  string  $id
     * @param  array   $params
     * @param  array   $opts   e.g. ['stripe_account' => 'acct_xxx']
     * @return mixed
     */
    public function deleteInvoice(string $id, array $params = [], array $opts = []): mixed;

    /**
     * Search invoices using a query string.
     *
     * @param  array  $params  e.g. ['query' => "metadata['customer_id']:'123'"]
     * @param  array  $opts    e.g. ['stripe_account' => 'acct_xxx']
     * @return mixed
     */
    public function searchInvoices(array $params, array $opts = []): mixed;

    // ── Invoice items ─────────────────────────────────────────────────────────

    /**
     * Create an invoice item.
     *
     * @param  array  $params
     * @param  array  $opts   e.g. ['stripe_account' => 'acct_xxx']
     * @return mixed
     */
    public function createInvoiceItem(array $params, array $opts = []): mixed;

    /**
     * Update an invoice item.
     *
     * @param  string  $id
     * @param  array   $params
     * @param  array   $opts   e.g. ['stripe_account' => 'acct_xxx']
     * @return mixed
     */
    public function updateInvoiceItem(string $id, array $params, array $opts = []): mixed;

    /**
     * Retrieve an invoice item.
     *
     * @param  string  $id
     * @param  array   $opts   e.g. ['stripe_account' => 'acct_xxx']
     * @return mixed
     */
    public function retrieveInvoiceItem(string $id, array $opts = []): mixed;

    /**
     * Delete an invoice item.
     *
     * @param  string  $id
     * @param  array   $opts   e.g. ['stripe_account' => 'acct_xxx']
     * @return mixed
     */
    public function deleteInvoiceItem(string $id, array $opts = []): mixed;

    // ── Connected-account customers ───────────────────────────────────────────

    /**
     * Create a customer on a connected account.
     *
     * @param  array  $params
     * @param  array  $opts   e.g. ['stripe_account' => 'acct_xxx']
     * @return mixed
     */
    public function createConnectedCustomer(array $params, array $opts = []): mixed;

    /**
     * Retrieve a customer from a connected account.
     *
     * @param  string  $id
     * @param  array   $params
     * @param  array   $opts   e.g. ['stripe_account' => 'acct_xxx']
     * @return mixed
     */
    public function retrieveConnectedCustomer(string $id, array $params = [], array $opts = []): mixed;

    /**
     * Update a customer on a connected account.
     *
     * @param  string  $id
     * @param  array   $params
     * @param  array   $opts   e.g. ['stripe_account' => 'acct_xxx']
     * @return mixed
     */
    public function updateConnectedCustomer(string $id, array $params, array $opts = []): mixed;

    // ── Stripe Terminal ───────────────────────────────────────────────────────

    /**
     * Create a Terminal connection token.
     *
     * @param  array  $params
     * @param  array  $opts   e.g. ['stripe_account' => 'acct_xxx']
     * @return mixed
     */
    public function createConnectionToken(array $params = [], array $opts = []): mixed;

    /**
     * Retrieve a Terminal location.
     *
     * @param  string  $id
     * @param  array   $opts   e.g. ['stripe_account' => 'acct_xxx']
     * @return mixed
     */
    public function retrieveTerminalLocation(string $id, array $opts = []): mixed;

    /**
     * Create (pair) a Terminal reader.
     *
     * @param  array  $params  e.g. ['registration_code' => ..., 'location' => ..., 'label' => ...]
     * @param  array  $opts    e.g. ['stripe_account' => 'acct_xxx']
     * @return mixed
     */
    public function createTerminalReader(array $params, array $opts = []): mixed;

    /**
     * Retrieve a Terminal reader.
     *
     * @param  string  $id
     * @param  array   $opts   e.g. ['stripe_account' => 'acct_xxx']
     * @return mixed
     */
    public function retrieveTerminalReader(string $id, array $opts = []): mixed;

    /**
     * Delete (unpair) a Terminal reader.
     *
     * @param  string  $id
     * @param  array   $opts   e.g. ['stripe_account' => 'acct_xxx']
     * @return mixed
     */
    public function deleteTerminalReader(string $id, array $opts = []): mixed;

    /**
     * Cancel the current action on a Terminal reader.
     *
     * @param  string  $readerId
     * @param  array   $params
     * @param  array   $opts    e.g. ['stripe_account' => 'acct_xxx']
     * @return mixed
     */
    public function cancelTerminalReaderAction(string $readerId, array $params = [], array $opts = []): mixed;

    // ── Stripe Accounts ───────────────────────────────────────────────────────

    /**
     * Create a connected Stripe account.
     *
     * @param  array  $params  e.g. ['type' => 'express', 'country' => 'US', ...]
     * @return mixed
     */
    public function createAccount(array $params): mixed;

    /**
     * Retrieve a Stripe account by ID.
     *
     * @param  string  $id
     * @param  array   $opts   e.g. ['stripe_account' => 'acct_xxx']
     * @return mixed
     */
    public function retrieveAccount(string $id, array $opts = []): mixed;

    /**
     * Update a Stripe account.
     *
     * @param  string  $id
     * @param  array   $params
     * @param  array   $opts   e.g. ['stripe_account' => 'acct_xxx']
     * @return mixed
     */
    public function updateAccount(string $id, array $params, array $opts = []): mixed;

    /**
     * Create an external account (bank account or card) on a connected account.
     *
     * @param  string  $accountId
     * @param  array   $params    e.g. ['external_account' => 'btok_xxx']
     * @return mixed
     */
    public function createAccountExternalAccount(string $accountId, array $params): mixed;

    // ── Tokens ────────────────────────────────────────────────────────────────

    /**
     * Create a Stripe token (e.g. bank account token).
     *
     * @param  array  $params
     * @return mixed
     */
    public function createToken(array $params): mixed;
}
