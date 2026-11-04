<?php

namespace App\Services;

use App\Models\AutoPayout;
use App\Models\PayoutAdjustment;
use App\Models\PendingPayout;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds the full (unpaginated) export payload for a payout.
 *
 * Auto payouts and pending payouts store their line items in different tables with
 * different foreign keys but an otherwise identical shape, so the row-building lives
 * in one place here rather than being copied into each controller.
 */
class PayoutExportService
{
    /**
     * Export payload for a payout that has already been executed.
     */
    public function buildForAutoPayout(AutoPayout $payout): array
    {
        return [
            'payout' => [
                'payout_reference' => $payout->payout_reference,
                'source' => 'auto_payout',
                'status' => $payout->status,
                'total_amount' => $payout->total_amount,
                'platform_fee' => $payout->platform_fee,
                'net_amount' => $payout->net_amount,
                'booking_count' => $payout->booking_count,
                'period_display' => $payout->periodDisplay(),
                'created_at' => optional($payout->created_at)->toDateTimeString(),
                'processed_date' => $payout->processed_date,
            ],
            'items' => $this->fetchItems('auto_payout_items', 'payout_id', $payout),
            // Adjustments folded into this payout when it executed.
            'applied_adjustments' => PayoutAdjustment::where('applied_in_payout_id', $payout->id)
                ->select('booking_id', 'type', 'gross_amount', 'platform_fee', 'net_amount', 'payout_reference', 'reason')
                ->get(),
        ];
    }

    /**
     * Export payload for a payout that has not executed yet (awaiting or approved).
     */
    public function buildForPendingPayout(PendingPayout $payout): array
    {
        return [
            'payout' => [
                'payout_reference' => $payout->payout_reference,
                'source' => 'pending_payout',
                'status' => $payout->status,
                'total_amount' => $payout->total_amount,
                'platform_fee' => $payout->platform_fee,
                'net_amount' => $payout->net_amount,
                'booking_count' => $payout->booking_count,
                'period_display' => $payout->periodDisplay(),
                'created_at' => optional($payout->created_at)->toDateTimeString(),
                // A pending payout has not been paid out, so there is no processed date.
                'processed_date' => null,
            ],
            'items' => $this->fetchItems('pending_payout_items', 'pending_payout_id', $payout),
            // Nothing is "applied" until execution -- report the adjustments that were
            // snapshotted into this payout's net_amount instead, so the CSV explains the
            // gap between the item rows and the payout total.
            'applied_adjustments' => $this->snapshottedAdjustments($payout),
        ];
    }

    /**
     * Line items for a payout, enriched with booking, tour, refund and adjustment context.
     */
    private function fetchItems(string $itemsTable, string $foreignKey, AutoPayout|PendingPayout $payout): Collection
    {
        $items = DB::table($itemsTable . ' as pi')
            ->join('tour_bookings_master as tbm', 'tbm.id', '=', 'pi.booking_id')
            // Left joins: a booking whose slot or package was removed must still appear in
            // the export. An inner join dropped those rows silently, so the exported lines
            // no longer added up to the payout total.
            ->leftJoin('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->leftJoin('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
            ->where('pi.' . $foreignKey, $payout->id)
            ->select(
                'pi.booking_id',
                'tbm.name as customer_name',
                'tp.name as tour_package_name',
                DB::raw("DATE_FORMAT(tbm.created_at, '%Y-%m-%d %H:%i') as booking_created_at"),
                DB::raw("DATE_FORMAT(tst.date, '%Y-%m-%d') as tour_date"),
                DB::raw("TIME_FORMAT(tst.time_from, '%H:%i') as tour_time"),
                'tbm.tourists as tourist_count',
                'tbm.total as booking_total',
                'tbm.status as booking_status',
                'pi.total_amount',
                'pi.platform_fee',
                'pi.net_amount'
            )
            ->orderBy('pi.booking_id')
            ->get();

        $bookingIds = $items->pluck('booking_id')->unique()->values()->all();

        $refunds = $this->refundTotals($bookingIds);
        $adjustedBookingIds = $this->adjustedBookingIds($payout, $bookingIds);

        return $items->map(function ($item) use ($refunds, $adjustedBookingIds) {
            $refund = $refunds[$item->booking_id] ?? null;
            $item->refund_total = $refund ? round((float) $refund->refund_total, 2) : 0;
            $item->refund_count = $refund ? (int) $refund->refund_count : 0;
            $item->has_adjustment = in_array($item->booking_id, $adjustedBookingIds);
            return $item;
        });
    }

    /**
     * Card refund totals keyed by booking id.
     *
     * Scoped to the payout's bookings. This was previously a derived table that grouped
     * the entire transactions table before joining, which scanned every transaction row
     * on every export.
     */
    private function refundTotals(array $bookingIds): array
    {
        if (empty($bookingIds)) {
            return [];
        }

        return DB::table('transactions')
            ->whereIn('tour_booking_id', $bookingIds)
            ->where('status', 'Refund')
            ->where('via', 'Card')
            ->groupBy('tour_booking_id')
            ->select(
                'tour_booking_id',
                DB::raw('SUM(amount) as refund_total'),
                DB::raw('COUNT(*) as refund_count')
            )
            ->get()
            ->keyBy('tour_booking_id')
            ->all();
    }

    /**
     * Booking ids in this payout that carry an adjustment.
     *
     * An executed payout matches on foreign key or reference, because adjustments
     * folded in at approval time hold the parent pending payout's reference rather
     * than the executed row's. A pending payout has nothing to key a foreign key
     * off yet, so it can only match on its own reference.
     */
    private function adjustedBookingIds(AutoPayout|PendingPayout $payout, array $bookingIds): array
    {
        if (empty($bookingIds)) {
            return [];
        }

        if ($payout instanceof AutoPayout) {
            $query = PayoutAdjustment::forExecutedPayout($payout);
        } elseif (!empty($payout->payout_reference)) {
            $query = PayoutAdjustment::forPayoutReference($payout->payout_reference);
        } else {
            return [];
        }

        return $query
            ->whereIn('booking_id', $bookingIds)
            ->pluck('booking_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Adjustments snapshotted into a pending payout's net amount at calculation time.
     */
    private function snapshottedAdjustments(PendingPayout $payout): Collection
    {
        $adjustmentIds = data_get($payout->calculation_data, 'adjustment_ids', []);

        if (empty($adjustmentIds) || !is_array($adjustmentIds)) {
            return collect();
        }

        return PayoutAdjustment::whereIn('id', $adjustmentIds)
            ->select('booking_id', 'type', 'gross_amount', 'platform_fee', 'net_amount', 'payout_reference', 'reason')
            ->get();
    }
}
