<?php

namespace App\Services\Booking\RemainingPayment;

use Illuminate\Support\Facades\DB;

class UnpaidPaxService
{
    /**
     * Returns [transact_id => unpaid_count] for pax added via Change People
     * whose remaining-payment link is still Unpaid, for the given booking IDs.
     *
     * These pax must be excluded from tourist counts, permit-fee totals,
     * state-tax totals, and all reporting that reflects only collected payment.
     */
    public static function getUnpaidPaxByTransactId(array $bookingIds): array
    {
        if (empty($bookingIds)) {
            return [];
        }

        $map = [];

        $items = DB::table('tour_booking_payment_link_items as pli')
            ->join('tour_booking_payment_links as pl', 'pl.id', '=', 'pli.payment_link_id')
            ->join('tour_bookings_master as tbm', 'tbm.group_id', '=', 'pl.order_id')
            ->whereIn('tbm.id', $bookingIds)
            ->where('pl.status', 'Unpaid')
            ->where('pl.section', 'remaining_payment')
            ->where('pli.type', 'change_people')
            ->select('pli.details')
            ->get();

        foreach ($items as $item) {
            $details = is_array($item->details)
                ? $item->details
                : (json_decode($item->details ?? '[]', true) ?? []);

            foreach ($details['added_transact_data'] ?? [] as $td) {
                $tid   = (int)($td['transact_id'] ?? 0);
                $count = (int)($td['added_count']  ?? 0);
                if ($tid > 0 && $count > 0) {
                    $map[$tid] = ($map[$tid] ?? 0) + $count;
                }
            }
        }

        return $map;
    }
}
