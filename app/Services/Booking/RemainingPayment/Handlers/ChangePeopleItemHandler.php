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

class ChangePeopleItemHandler extends BaseItemHandler
{
    public static function isProcessable(TourBookingPaymentLinkItem $item): bool
    {
        if ($item->reference_id !== null) {
            $historyRow = DB::table('tour_booking_history')->find($item->reference_id);
            return $historyRow && (int) $historyRow->is_processed === 0;
        }
        return true;
    }

    public static function extractFees(TourBookingPaymentLinkItem $item): float
    {
        if ($item->reference_id !== null) {
            $historyRow = DB::table('tour_booking_history')->find($item->reference_id);
            return (float) ($historyRow->platform_fees ?? 0);
        }
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

        // This handler is reached both from the customer-facing Payment Link completion flow
        // (no admin/staff session at all — Auth::id() is null) AND from an admin using the
        // "Make Payment" option directly (a real authenticated session). Use the real
        // admin/staff identity when one is genuinely logged in; otherwise attribute to the
        // Customer via the booking's own email.
        $resolvedOperationBy = Auth::id() ?? BookingService::customerIdByBookingEmail($bookingId);
        $resolvedOperationByUserType = Auth::id() ? userType() : 'Customer';
        $createdByUserName = Auth::id() ? BookingService::createdUserInfo() : 'AppUser: (Customer)';
        $bookedByStaffOverride = ['booked_by_staff' => $resolvedOperationBy, 'booked_by_staff_type' => $resolvedOperationByUserType];
        $paymentIntentId   = $paymentIntent->id ?? null;
        $applicationFeeId  = $paymentIntent->charges->data[0]->application_fee ?? null;
        // Ground truth: $paymentIntent is the real, already-retrieved Stripe object for this
        // remaining-payment-link charge, so its actual settlement mode is read directly off it.
        $mode = PaymentModeInference::fromStripeObject($paymentIntent);

        if ($item->reference_id !== null) {
            // ── Legacy path: pending history record was created when the link was sent ──
            $historyRow = DB::table('tour_booking_history')->find($item->reference_id);

            if (!$historyRow || $historyRow->is_processed == 1) {
                return;
            }

            $fees   = $historyRow->platform_fees;
            $amount = $historyRow->ticket_cost + $fees;

            // Use the shared transaction if provided (multi-item link), else create one.
            $transactionId = $transactionId ?? TransactionService::storePaymentInTransaction(
                $bookingId,
                $amount,
                $fees,
                $paymentMethod,
                0,
                $paymentIntentId,
                $applicationFeeId,
                $bookedByStaffOverride,
                mode: $mode
            );

            $updatedDetails = json_encode([
                'booking_id'           => $bookingId,
                'amount'               => $amount,
                'payment_type'         => ucwords($paymentMethod),
                'payment_method'       => 'Full Payment',
                'card_number'          => $cardLast4,
                'card_type'            => $cardType,
                'created_by_with_name' => $createdByUserName,
            ], JSON_NUMERIC_CHECK);

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

            $historyId = $item->reference_id;
        } else {
            // ── New path: no pending history record; details are on the item ──
            $details = is_array($item->details) ? $item->details : (json_decode($item->details ?? '[]', true) ?? []);

            $fees   = (float)($details['platform_fees'] ?? 0);
            $amount = (float)($details['ticket_cost'] ?? 0) + $fees;

            // Use the shared transaction if provided (multi-item link), else create one.
            $transactionId = $transactionId ?? TransactionService::storePaymentInTransaction(
                $bookingId,
                $amount,
                $fees,
                $paymentMethod,
                0,
                $paymentIntentId,
                $applicationFeeId,
                $bookedByStaffOverride,
                mode: $mode
            );

            $updatedDetails = json_encode([
                'booking_id'           => $bookingId,
                'amount'               => $amount,
                'payment_type'         => ucwords($paymentMethod),
                'payment_method'       => 'Full Payment',
                'card_number'          => $cardLast4,
                'card_type'            => $cardType,
                // Always the finalize-time label — never $details['created_by_with_name'], which
                // is whatever was recorded when the pending item was first created (a different
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
                'charge_type'            => 'change_people',
                'is_processed'           => 1,
                'details'                => $updatedDetails,
                'operation_at'           => mysqlDT(),
                'operation_by'           => $resolvedOperationBy,
                'operation_by_user_type' => $resolvedOperationByUserType,
                'created_at'             => Carbon::now(),
                'updated_at'             => Carbon::now(),
            ]);
        }

        // total/booking_total (the ticket-cost portion) were already written in full when the
        // Change People payment link was sent — that part is correct to show as owed immediately,
        // same as a Pay Later booking's total, regardless of payment status. service_commission
        // (the FEE portion) is deliberately NOT written at that point anymore (see
        // changePeopleBooking()'s own comment on why 'Payment Link' was removed from its eager
        // service_commission update) — it's recorded here instead, now that the fee has actually
        // been collected via this real PaymentIntent. Only items flagged 'fee_deferred' work this
        // way; an item whose booking already carries its fee (e.g. created by Split Booking) must
        // not add it a second time.
        $itemDetails = is_array($item->details) ? $item->details : (json_decode($item->details ?? '[]', true) ?? []);
        if ($fees > 0 && !empty($itemDetails['fee_deferred'])) {
            DB::table('tour_bookings_master')
                ->where('id', $bookingId)
                ->update(['service_commission' => DB::raw('service_commission + ' . $fees)]);
        }

        DetectPayoutAdjustment::dispatch($bookingId, $historyId, 'Payment');

        BoardService::boardBookingUpdate($bookingId);
    }

    public static function getLabel(): string
    {
        return 'Change People';
    }
}
