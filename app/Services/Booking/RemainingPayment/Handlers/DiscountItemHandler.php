<?php

namespace App\Services\Booking\RemainingPayment\Handlers;

use App\Jobs\DetectPayoutAdjustment;
use App\Models\TourBookingPaymentLinkItem;
use App\Services\Booking\BookingService;
use App\Services\Booking\TransactionService;
use App\Services\Payments\PaymentModeInference;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DiscountItemHandler extends BaseItemHandler
{
    public static function isProcessable(TourBookingPaymentLinkItem $item): bool
    {
        $historyRow = DB::table('tour_booking_history')->find($item->reference_id);
        return $historyRow && (int) $historyRow->is_processed === 0;
    }

    public static function extractFees(TourBookingPaymentLinkItem $item): float
    {
        $historyRow = DB::table('tour_booking_history')->find($item->reference_id);
        return (float) ($historyRow->platform_fees ?? 0);
    }

    public static function finalize(
        TourBookingPaymentLinkItem $item,
        int|string $bookingId,
        object $paymentIntent,
        string $paymentMethod = 'Card',
        ?int $transactionId = null
    ): void {
        $historyRow = DB::table('tour_booking_history')
            ->where('id', $item->reference_id)
            ->where('is_processed', 0)
            ->first();

        if (!$historyRow) {
            return;
        }

        $cardLast4 = $cardType = null;
        $cardInfo  = $paymentIntent->charges->data[0]->payment_method_details ?? [];
        if (isset($cardInfo['card_present']['last4'])) {
            $cardLast4 = $cardInfo['card_present']['last4'] ?? '';
            $cardType  = $cardInfo['card_present']['brand'] ?? '';
        } elseif (isset($cardInfo['card']['last4'])) {
            $cardLast4 = $cardInfo['card']['last4'] ?? '';
            $cardType  = $cardInfo['card']['brand'] ?? '';
        }

        $paymentIntentId  = $paymentIntent->id ?? null;
        $applicationFeeId = $paymentIntent->charges->data[0]->application_fee ?? null;
        $fees   = (float) $historyRow->platform_fees;
        $amount = (float) $historyRow->ticket_cost + $fees;

        // This handler is reached both from the customer-facing Payment Link completion flow
        // (no admin/staff session at all — Auth::id() is null) AND from an admin using the
        // "Make Payment" option directly (a real authenticated session). Use the real
        // admin/staff identity when one is genuinely logged in; otherwise attribute to the
        // Customer via the booking's own email. Force the same resolution through to the
        // transaction row too via the booked_by_staff override.
        $resolvedOperationBy = Auth::id() ?? BookingService::customerIdByBookingEmail($bookingId);
        $resolvedOperationByUserType = Auth::id() ? userType() : 'Customer';
        // from_edit_discount=1 tells TransactionService::storePaymentInTransaction() to tag this
        // transaction's charge_type='discount', matching the direct Card/Cash/Invoiced edit-discount
        // path (TourBookingRepository.php's applyDiscount()) — without it, BookingService::
        // calculateDiscountpayment() (which filters charge_type='discount') silently undercounts
        // discount payments collected via a finalized Payment Link.
        $bookedByStaffOverride = ['booked_by_staff' => $resolvedOperationBy, 'booked_by_staff_type' => $resolvedOperationByUserType, 'from_edit_discount' => 1];

        // Use the shared transaction if provided (multi-item link), else create one.
        // Ground truth: $paymentIntent is the real, already-retrieved Stripe object for this
        // remaining-payment-link charge, so its actual settlement mode is read directly off it.
        $transactionId = $transactionId ?? TransactionService::storePaymentInTransaction(
            $bookingId,
            $amount,
            $fees,
            $paymentMethod,
            0,
            $paymentIntentId,
            $applicationFeeId,
            $bookedByStaffOverride,
            mode: PaymentModeInference::fromStripeObject($paymentIntent)
        );

        // Update payment_type, card_number, and payment_method in the details JSON.
        $details = json_decode($historyRow->details ?? '{}', true) ?: [];
        $details['payment_type']   = ucwords($paymentMethod);
        $details['card_number']    = $cardLast4;
        $details['payment_method'] = 'Full Payment';
        $updatedDetails = json_encode($details, JSON_NUMERIC_CHECK);

        DB::table('tour_booking_history')
            ->where('id', $item->reference_id)
            ->update([
                'transaction_id'         => $transactionId,
                'card_last_4_digit'      => $cardLast4,
                'payment_type'           => $paymentMethod,
                'payment_method'         => 'Full Payment',
                'is_processed'           => 1,
                'details'                => $updatedDetails,
                'operation_at'           => mysqlDT(),
                'operation_by'           => $resolvedOperationBy,
                'operation_by_user_type' => $resolvedOperationByUserType,
                'updated_at'             => Carbon::now(),
            ]);

        DetectPayoutAdjustment::dispatch($bookingId, $item->reference_id, 'Payment');
    }

    public static function getLabel(): string
    {
        return 'Discount';
    }
}
