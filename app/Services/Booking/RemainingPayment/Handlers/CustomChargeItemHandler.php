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

class CustomChargeItemHandler extends BaseItemHandler
{
    public static function isProcessable(TourBookingPaymentLinkItem $item): bool
    {
        $chargeRow = DB::table('tour_booking_custom_charges')->find($item->reference_id);
        return $chargeRow && $chargeRow->status !== 'Paid';
    }

    public static function extractFees(TourBookingPaymentLinkItem $item): float
    {
        $chargeRow = DB::table('tour_booking_custom_charges')->find($item->reference_id);
        return (float) ($chargeRow->fees ?? 0);
    }

    public static function finalize(
        TourBookingPaymentLinkItem $item,
        int|string $bookingId,
        object $paymentIntent,
        string $paymentMethod = 'Card',
        ?int $transactionId = null
    ): void {
        $chargeRow = DB::table('tour_booking_custom_charges')->find($item->reference_id);

        if (!$chargeRow || $chargeRow->status === 'Paid') {
            return;
        }

        // This handler is reached both from the customer-facing Payment Link completion flow
        // (no admin/staff session at all — Auth::id() is null) AND from an admin using the
        // "Make Payment" option directly (a real authenticated session). Use the real
        // admin/staff identity when one is genuinely logged in; otherwise attribute to the
        // Customer via the booking's own email. Force the same resolution through to the
        // transaction row too via the booked_by_staff override.
        $resolvedOperationBy = Auth::id() ?? BookingService::customerIdByBookingEmail($bookingId);
        $resolvedOperationByUserType = Auth::id() ? userType() : 'Customer';
        $bookedByStaffOverride = ['booked_by_staff' => $resolvedOperationBy, 'booked_by_staff_type' => $resolvedOperationByUserType];

        // Use the shared transaction if provided (multi-item link), else create one.
        // Ground truth: $paymentIntent is the real, already-retrieved Stripe object for this
        // remaining-payment-link charge, so its actual settlement mode is read directly off it.
        $transactionId = $transactionId ?? TransactionService::storeCustomChargeInTransaction(
            $bookingId,
            $chargeRow->amount,
            $chargeRow->fees,
            $paymentMethod,
            $paymentIntent,
            $bookedByStaffOverride,
            mode: PaymentModeInference::fromStripeObject($paymentIntent)
        );

        DB::table('tour_booking_custom_charges')
            ->where('id', $item->reference_id)
            ->update([
                'transaction_id' => $paymentIntent->id,
                'payment_method' => $paymentMethod,
                'status'         => 'Paid',
                'updated_at'     => now(),
            ]);

        // Extract card info from the payment intent
        $cardLast4 = $cardType = null;
        $cardInfo  = $paymentIntent->charges->data[0]->payment_method_details ?? [];
        if (isset($cardInfo['card_present']['last4'])) {
            $cardLast4 = $cardInfo['card_present']['last4'] ?? '';
            $cardType  = $cardInfo['card_present']['brand'] ?? '';
        } elseif (isset($cardInfo['card']['last4'])) {
            $cardLast4 = $cardInfo['card']['last4'] ?? '';
            $cardType  = $cardInfo['card']['brand'] ?? '';
        }

        $createdByUserName = Auth::id() ? BookingService::createdUserInfo() : 'AppUser: (Customer)';
        $amount = $chargeRow->amount + $chargeRow->fees;

        $chargeDetail = [
            'amount'  => $chargeRow->amount,
            'fees'    => $chargeRow->fees,
            'taxable' => $chargeRow->taxable,
            'comment' => $chargeRow->description,
        ];

        $updatedDetails = json_encode([
            'booking_id'           => $bookingId,
            'operation'            => 'added',
            'custom_charge'        => $chargeDetail,
            'payment_type'         => ucwords($paymentMethod),
            'payment_method'       => 'Full Payment',
            'card_number'          => $cardLast4,
            'card_type'            => $cardType,
            'amount'               => $amount,
            'fees'                 => roundout($chargeRow->fees, 2),
            'created_by_with_name' => $createdByUserName,
        ], JSON_NUMERIC_CHECK);

        // Update the pending "Payment Link / Pay Later" history entry in place.
        // This avoids creating a duplicate entry — the existing row is promoted
        // to a completed Full Payment record.
        $historyId = DB::table('tour_booking_history')
            ->where('tour_booking_id', $bookingId)
            ->where('charge_type', 'custom_charge')
            ->where('payment_method', 'Payment Link')
            ->where('is_processed', 0)
            ->value('id');

        if ($historyId) {
            DB::table('tour_booking_history')
                ->where('id', $historyId)
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
        } else {
            // No pending entry found — create a fresh completed entry as fallback
            $historyId = DB::table('tour_booking_history')
                ->insertGetId([
                    'tour_booking_id'        => $bookingId,
                    'transaction_id'         => $transactionId,
                    'ticket_cost'            => $chargeRow->amount,
                    'platform_fees'          => roundout($chargeRow->fees, 2),
                    'card_last_4_digit'      => $cardLast4,
                    'payment_type'           => $paymentMethod,
                    'payment_method'         => 'Full Payment',
                    'operation'              => 'Payment',
                    'details'                => $updatedDetails,
                    'is_processed'           => 1,
                    'charge_type'            => 'custom_charge',
                    'operation_at'           => mysqlDT(),
                    'operation_by'           => $resolvedOperationBy,
                    'operation_by_user_type' => $resolvedOperationByUserType,
                    'updated_at'             => Carbon::now(),
                    'created_at'             => Carbon::now(),
                ]);
        }

        DetectPayoutAdjustment::dispatch($bookingId, $historyId, 'Payment');
    }

    public static function getLabel(): string
    {
        return 'Custom Charge';
    }
}
