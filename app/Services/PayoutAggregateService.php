<?php

namespace App\Services;

use App\Models\AutoPayout;
use App\Models\PendingPayout;
use App\Services\Payments\PaymentModeResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds the period roll-up that goes out with the weekly payout email:
 * bookings, tourists and amount per category, the bookings still unpaid, and the
 * Stripe balance the deposit will land on top of.
 *
 * Categories are derived, not configured: one row per tour package that actually has
 * bookings in the payout, plus a single Affiliates row. A new package therefore shows
 * up on its own instead of silently disappearing into an "other" bucket.
 *
 * Affiliate-originated bookings are pulled OUT of their package rows and counted only
 * in the Affiliates row, so the rows sum to the total exactly once.
 */
class PayoutAggregateService
{
    private const AFFILIATE_CATEGORY = 'Affiliates';

    public function buildForAutoPayout(AutoPayout $payout): array
    {
        return $this->build(
            'auto_payout_items',
            'payout_id',
            $payout->id,
            $payout->period_start,
            $payout->period_end,
            $payout->periodDisplay()
        );
    }

    public function buildForPendingPayout(PendingPayout $payout): array
    {
        return $this->build(
            'pending_payout_items',
            'pending_payout_id',
            $payout->id,
            $payout->period_start,
            $payout->period_end,
            $payout->periodDisplay()
        );
    }

    private function build(string $itemsTable, string $foreignKey, int $payoutId, $periodStart, $periodEnd, string $periodDisplay): array
    {
        $rows = $this->categoryRows($itemsTable, $foreignKey, $payoutId);
        $bookingIds = $this->payoutBookingIds($itemsTable, $foreignKey, $payoutId);

        return [
            'period_display' => $periodDisplay,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'tour_dates' => $this->tourDateRange($itemsTable, $foreignKey, $payoutId),
            'rows' => $rows,
            'total' => $this->totalRow($rows),
            'unpaid' => $this->unpaidBookings($periodStart, $periodEnd, $bookingIds),
        ];
    }

    /**
     * Earliest and latest tour date among the bookings this payout actually paid for.
     *
     * This is NOT the collection window. The window is a cutoff applied to
     * TIMESTAMP(date, time_from), so it is normally wider at both ends than the tours
     * that fell inside it -- the window says what was eligible, this says what was
     * bought. Bookings with no slot are ignored rather than counted as a null date.
     *
     * Cheap enough to keep in the same request: it is one MIN/MAX over the payout's own
     * item rows, not a scan of the window like unpaidBookings().
     */
    private function tourDateRange(string $itemsTable, string $foreignKey, int $payoutId): array
    {
        $range = DB::table($itemsTable . ' as pi')
            ->join('tour_bookings_master as tbm', 'tbm.id', '=', 'pi.booking_id')
            ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->where('pi.' . $foreignKey, $payoutId)
            ->select(DB::raw('MIN(tst.date) as first_date'), DB::raw('MAX(tst.date) as last_date'))
            ->first();

        $first = $range->first_date ?? null;
        $last = $range->last_date ?? null;

        return [
            'first' => $first,
            'last' => $last,
            // Formatted here, not in the browser: these are date-only values, and
            // new Date('2026-08-02') parses as UTC, which renders as Aug 1 in Phoenix.
            'display' => $this->tourDateRangeDisplay($first, $last),
        ];
    }

    private function tourDateRangeDisplay(?string $first, ?string $last): string
    {
        if (empty($first) || empty($last)) {
            return 'No tour dates recorded';
        }

        $format = 'M j, Y';
        $firstLabel = Carbon::parse($first)->format($format);
        $lastLabel = Carbon::parse($last)->format($format);

        return $firstLabel === $lastLabel ? $firstLabel : $firstLabel . ' - ' . $lastLabel;
    }

    /**
     * One row per package (affiliate bookings excluded) plus a combined Affiliates row,
     * ordered by amount so the largest category reads first.
     */
    private function categoryRows(string $itemsTable, string $foreignKey, int $payoutId): array
    {
        $grouped = DB::table($itemsTable . ' as pi')
            ->join('tour_bookings_master as tbm', 'tbm.id', '=', 'pi.booking_id')
            ->leftJoin('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
            ->where('pi.' . $foreignKey, $payoutId)
            ->groupBy('category')
            ->select(
                DB::raw("CASE WHEN tbm.created_by_user_type = 'Affiliate' THEN '" . self::AFFILIATE_CATEGORY . "' ELSE COALESCE(tp.name, 'Unassigned package') END as category"),
                // One item row per booking, so these do not double count.
                DB::raw('COUNT(DISTINCT pi.booking_id) as bookings'),
                DB::raw('COALESCE(SUM(tbm.tourists), 0) as tourists'),
                DB::raw('COALESCE(SUM(pi.total_amount), 0) as amount'),
                DB::raw('COALESCE(SUM(pi.net_amount), 0) as net_amount')
            )
            ->get();

        return $grouped
            ->map(fn ($row) => [
                'category' => $row->category,
                'bookings' => (int) $row->bookings,
                'tourists' => (int) $row->tourists,
                'amount' => round((float) $row->amount, 2),
                'net_amount' => round((float) $row->net_amount, 2),
            ])
            ->sortByDesc('amount')
            ->values()
            ->all();
    }

    private function totalRow(array $rows): array
    {
        return [
            'category' => 'Total',
            'bookings' => array_sum(array_column($rows, 'bookings')),
            'tourists' => array_sum(array_column($rows, 'tourists')),
            'amount' => round(array_sum(array_column($rows, 'amount')), 2),
            'net_amount' => round(array_sum(array_column($rows, 'net_amount')), 2),
        ];
    }

    private function payoutBookingIds(string $itemsTable, string $foreignKey, int $payoutId): array
    {
        return DB::table($itemsTable)
            ->where($foreignKey, $payoutId)
            ->pluck('booking_id')
            ->all();
    }

    /**
     * Bookings whose tour falls inside the same window but which this payout did not
     * pay -- partial payments, bookings added after calculation, or anything the rule
     * filtered out. Answers "what is left over" without a second definition of the
     * eligibility rules: it mirrors PayoutApprovalService::calculateAccountPayoutAmount.
     *
     * Returns nulls when the payout predates the stored window, because without the
     * window there is nothing to compare against.
     */
    private function unpaidBookings($periodStart, $periodEnd, array $paidBookingIds): array
    {
        if (empty($periodStart) || empty($periodEnd)) {
            return ['recorded' => false, 'bookings' => 0, 'tourists' => 0, 'amount' => 0];
        }

        $query = DB::table('tour_bookings_master as tbm')
            ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->join('transactions as tra', 'tra.tour_booking_id', '=', 'tbm.id')
            ->where('tra.status', 'Completed')
            ->where('tra.via', 'Card')
            // Payout Simplification (2027+): mirror PayoutApprovalService's payment_mode
            // filter exactly (see this method's docblock) — a platform_held transaction was
            // never destined for a Stripe transfer to this account, so it must not be
            // reported here as money the operator is still waiting on.
            ->where('tra.payment_mode', PaymentModeResolver::DESTINATION)
            ->where('tra.request_from', '!=', 'Insert')
            ->where('tbm.comments', 'not like', '#%')
            ->where('tbm.payout_made', 0)
            ->whereBetween(DB::raw('TIMESTAMP(tst.date, tst.time_from)'), [$periodStart, $periodEnd]);

        if (!empty($paidBookingIds)) {
            $query->whereNotIn('tbm.id', $paidBookingIds);
        }

        // Collapse to one row per booking first: a booking with several card
        // transactions would otherwise be counted once per transaction.
        $perBooking = $query
            ->groupBy('tbm.id', 'tbm.tourists')
            ->select('tbm.id', 'tbm.tourists', DB::raw('SUM(tra.amount) as collected'))
            ->get();

        return [
            'recorded' => true,
            'bookings' => $perBooking->count(),
            'tourists' => (int) $perBooking->sum('tourists'),
            'amount' => round((float) $perBooking->sum('collected'), 2),
        ];
    }
}
