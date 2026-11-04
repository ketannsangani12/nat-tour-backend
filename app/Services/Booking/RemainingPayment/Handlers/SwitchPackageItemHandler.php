<?php

namespace App\Services\Booking\RemainingPayment\Handlers;

use App\Jobs\DetectPayoutAdjustment;
use App\Models\TourBookingPaymentLinkItem;
use App\Services\Booking\BookingService;
use App\Services\Booking\TransactionService;
use App\Services\Board\BoardService;
use App\Services\Payments\PaymentModeInference;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SwitchPackageItemHandler extends BaseItemHandler
{
    public static function isProcessable(TourBookingPaymentLinkItem $item): bool
    {
        // switch_package items are always created without a reference_id and are
        // always processable (no prior pending history row to check).
        return true;
    }

    public static function extractFees(TourBookingPaymentLinkItem $item): float
    {
        $details = is_array($item->details) ? $item->details : (json_decode($item->details ?? '[]', true) ?? []);
        return (float) ($details['platform_fees'] ?? 0);
    }

    public static function finalize(
        TourBookingPaymentLinkItem $item,
        int|string $bookingId,
        object $paymentIntent,
        string $paymentMethod = 'Card',
        ?int $transactionId = null
    ): void {
        $cardLast4 = $cardType = null;
        $cardInfo  = $paymentIntent->charges->data[0]->payment_method_details ?? [];
        if (isset($cardInfo['card_present']['last4'])) {
            $cardLast4 = $cardInfo['card_present']['last4'] ?? '';
            $cardType  = $cardInfo['card_present']['brand'] ?? '';
        } elseif (isset($cardInfo['card']['last4'])) {
            $cardLast4 = $cardInfo['card']['last4'] ?? '';
            $cardType  = $cardInfo['card']['brand'] ?? '';
        }

        $details           = is_array($item->details) ? $item->details : (json_decode($item->details ?? '[]', true) ?? []);
        $fees              = (float) ($details['platform_fees'] ?? 0);
        $amount            = (float) ($details['ticket_cost'] ?? 0) + $fees;
        // This handler is reached both from the customer-facing Payment Link completion flow
        // (no admin/staff session at all — Auth::id() is null) AND from an admin using the
        // "Make Payment" option directly (a real authenticated session). Use the real
        // admin/staff identity when one is genuinely logged in; otherwise attribute to the
        // Customer via the booking's own email. Force the same resolution through to the
        // transaction row too via the booked_by_staff override.
        $resolvedOperationBy = Auth::id() ?? BookingService::customerIdByBookingEmail($bookingId);
        $resolvedOperationByUserType = Auth::id() ? userType() : 'Customer';
        $bookedByStaffOverride = ['booked_by_staff' => $resolvedOperationBy, 'booked_by_staff_type' => $resolvedOperationByUserType];
        $createdByUserName = Auth::id() ? BookingService::createdUserInfo() : 'AppUser: (Customer)';
        $paymentIntentId   = $paymentIntent->id ?? null;
        $applicationFeeId  = $paymentIntent->charges->data[0]->application_fee ?? null;

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

        $updatedDetails = json_encode([
            'booking_id'           => $bookingId,
            'amount'               => $amount,
            'payment_type'         => ucwords($paymentMethod),
            'payment_method'       => 'Full Payment',
            'card_number'          => $cardLast4,
            'card_type'            => $cardType,
            // Always the finalize-time label — never $details['created_by_with_name'], which is
            // whatever was recorded when the pending item was first created (a different
            // moment/actor from whoever is completing the payment now).
            'created_by_with_name' => $createdByUserName,
        ], JSON_NUMERIC_CHECK);

        $historyId = DB::table('tour_booking_history')->insertGetId([
            'tour_booking_id'        => $bookingId,
            'tourists'               => $details['tourists'] ?? 0,
            'ticket_cost'            => $details['ticket_cost'] ?? 0,
            'platform_fees'          => $fees,
            'transaction_id'         => $transactionId,
            'card_last_4_digit'      => $cardLast4,
            'payment_type'           => $paymentMethod,
            'payment_method'         => 'Full Payment',
            'operation'              => 'Payment',
            'charge_type'            => 'switch_package',
            'is_processed'           => 1,
            'details'                => $updatedDetails,
            'operation_at'           => mysqlDT(),
            'operation_by'           => $resolvedOperationBy,
            'operation_by_user_type' => $resolvedOperationByUserType,
            'created_at'             => Carbon::now(),
            'updated_at'             => Carbon::now(),
        ]);

        // tour_bookings_master (total, booking_total, service_commission) was already
        // incremented by SwitchTourPackagePaymentLinkService when the link was created,
        // so no further update is needed here.

        DetectPayoutAdjustment::dispatch($bookingId, $historyId, 'Payment');

        BoardService::boardBookingUpdate($bookingId);
    }

    public static function getLabel(): string
    {
        return 'Switch Package';
    }
}
