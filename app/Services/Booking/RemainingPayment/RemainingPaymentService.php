<?php

namespace App\Services\Booking\RemainingPayment;

use App\Models\TourBookingPaymentLink;
use App\Models\TourBookingPaymentLinkItem;
use App\Services\Booking\BookingService;
use App\Services\Booking\RemainingPayment\Handlers\AddonItemHandler;
use App\Services\Booking\RemainingPayment\Handlers\ChangePeopleItemHandler;
use App\Services\Booking\RemainingPayment\Handlers\CustomChargeItemHandler;
use App\Services\Booking\RemainingPayment\Handlers\DiscountItemHandler;
use App\Services\Booking\RemainingPayment\Handlers\SwitchPackageItemHandler;
use Illuminate\Support\Facades\DB;

class RemainingPaymentService
{
    /**
     * Item-type to handler class map.
     * Add new item types here as the feature expands.
     */
    private static array $handlers = [
        'custom_charge'  => CustomChargeItemHandler::class,
        'change_people'  => ChangePeopleItemHandler::class,
        'addon'          => AddonItemHandler::class,
        'discount'       => DiscountItemHandler::class,
        'switch_package' => SwitchPackageItemHandler::class,
    ];

    /**
     * Finalize all items on a remaining_payment link after payment confirms.
     * Idempotent — skips items whose underlying record is already Paid.
     * Called from PaymentLinkService for both webhook and redirect paths.
     */
    public static function finalizeItems(
        TourBookingPaymentLink $paymentLink,
        object $paymentIntent,
        int|string $bookingId,
        string $paymentMethod = 'Card'
    ): void {
        if ($paymentLink->status === 'Paid') {
            return;
        }

        // Only process items that are still Unpaid — Cancelled/Expired items are skipped.
        $items = TourBookingPaymentLinkItem::where('payment_link_id', $paymentLink->id)
            ->where('status', 'Unpaid')
            ->get();

        if ($items->isEmpty()) {
            $paymentLink->status = 'Paid';
            $paymentLink->save();
            return;
        }

        // Each handler creates its own transaction for its item amount.
        // Skip items already settled outside this payment link (different Stripe charge).
        foreach ($items as $item) {
            $handlerClass = self::$handlers[$item->type] ?? null;
            if (!$handlerClass || !$handlerClass::isProcessable($item)) {
                continue;
            }
            $handlerClass::finalize($item, $bookingId, $paymentIntent, $paymentMethod);
            $item->status     = 'Paid';
            $item->updated_at = now();
            $item->save();
        }

        $paymentLink->status = 'Paid';
        $paymentLink->save();
    }

    /**
     * Returns the pending remaining_payment link and its items for a given booking.
     * Returns null if no pending link exists.
     */
    public static function getPendingForBooking(int|string $bookingId): ?array
    {
        $groupId = DB::table('tour_bookings_master')->where('id', $bookingId)->value('group_id');
        if (!$groupId) {
            return null;
        }

        $link = TourBookingPaymentLink::where('order_id', $groupId)
            ->where('status', 'Unpaid')
            ->where('section', 'remaining_payment')
            ->first();

        if (!$link) {
            return null;
        }

        $items = TourBookingPaymentLinkItem::where('payment_link_id', $link->id)
            ->where('status', 'Unpaid')
            ->orderBy('id')
            ->get()
            ->map(function ($item) {
                $ref = match ($item->type) {
                    'change_people' => $item->reference_id
                        ? DB::table('tour_booking_history')
                            ->where('id', $item->reference_id)
                            ->first(['operation_by', 'operation_by_user_type'])
                        : null,
                    'custom_charge' => $item->reference_id
                        ? DB::table('tour_booking_custom_charges')
                            ->where('id', $item->reference_id)
                            ->first(['operation_by', 'operation_by_user_type'])
                        : null,
                    default => null,
                };

                $createdBy = null;
                if ($ref && $ref->operation_by) {
                    $createdBy = BookingService::createdUserInfo(false, null, (int) $ref->operation_by, $ref->operation_by_user_type);
                }
                // Fall back to details['created_by_with_name'] for items where the
                // reference_id lookup didn't resolve (e.g. addon, discount, or
                // change_people items created with reference_id = null).
                if (!$createdBy) {
                    $det = is_array($item->details)
                        ? $item->details
                        : (json_decode($item->details ?? '[]', true) ?? []);
                    $createdBy = $det['created_by_with_name'] ?? null;
                }

                return [
                    'id'         => $item->id,
                    'type'       => $item->type,
                    'label'      => isset(self::$handlers[$item->type])
                        ? (self::$handlers[$item->type])::getLabel()
                        : ucwords(str_replace('_', ' ', $item->type)),
                    'amount'     => (float) $item->amount,
                    'created_at' => $item->created_at?->format('M j, Y @ g:i A'),
                    'created_by' => $createdBy,
                ];
            });

        // Also include Open (Unpaid) custom charges that were created outside the payment
        // link flow (e.g. editBooking / Pay Later paths) and are therefore not yet linked
        // to any payment link item.
        $linkedCustomChargeIds = TourBookingPaymentLinkItem::where('payment_link_id', $link->id)
            ->where('type', 'custom_charge')
            ->where('status', 'Unpaid')
            ->pluck('reference_id')
            ->filter()
            ->values()
            ->toArray();

        $groupBookingIds = DB::table('tour_bookings_master')
            ->where('group_id', $groupId)
            ->pluck('id');

        $openCustomCharges = DB::table('tour_booking_custom_charges')
            ->whereIn('tour_booking_id', $groupBookingIds)
            ->where('status', 'Open')
            ->when(!empty($linkedCustomChargeIds), fn($q) => $q->whereNotIn('id', $linkedCustomChargeIds))
            ->get();

        foreach ($openCustomCharges as $charge) {
            $createdBy = null;
            if (!empty($charge->operation_by)) {
                $createdBy = BookingService::createdUserInfo(false, null, (int) $charge->operation_by, $charge->operation_by_user_type ?? 'Admin');
            }
            $items->push([
                'id'         => null,
                'type'       => 'custom_charge',
                'label'      => 'Custom Charge',
                'amount'     => round((float) $charge->amount + (float) $charge->fees, 2),
                'created_at' => $charge->created_at
                    ? \Carbon\Carbon::parse($charge->created_at)->format('M j, Y @ g:i A')
                    : null,
                'created_by' => $createdBy,
            ]);
        }

        $total = $items->sum('amount');

        return [
            'link'  => $link,
            'items' => $items,
            'total' => $total,
        ];
    }
}
