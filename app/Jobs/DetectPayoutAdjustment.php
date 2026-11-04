<?php

namespace App\Jobs;

use App\Models\AutoPayoutRule;
use App\Services\PayoutAdjustmentService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DetectPayoutAdjustment implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $backoff = [10, 30, 60];

    public function __construct(
        private int $bookingId,
        private int $historyId,
        private string $operation
    ) {}

    public function handle(PayoutAdjustmentService $adjustmentService): void
    {
        $rule = AutoPayoutRule::where('enabled', true)->first();
        if (!$rule) {
            return;
        }

        $booking = DB::table('tour_bookings_master as tbm')
            ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->where('tbm.id', $this->bookingId)
            ->select('tbm.id', 'tbm.payout_made', DB::raw("TIMESTAMP(tst.date, tst.time_from) as tour_datetime"))
            ->first();

        if (!$booking || !$booking->tour_datetime) {
            return;
        }

        $bufferDays = $rule->buffer_days ?? 0;
        $tourDate = Carbon::parse($booking->tour_datetime);
        $bufferEnd = now()->subDays($bufferDays);

        // Booking tour date is still within the buffer window -- normal payout will handle it
        if ($tourDate->greaterThanOrEqualTo($bufferEnd)) {
            return;
        }

        if ($booking->payout_made) {
            // Booking was already paid out -- create adjustment against original payout
            $adjustment = $adjustmentService->detectAndCreateAdjustmentForBooking($this->bookingId, $this->historyId);
            if ($adjustment) {
                Log::info("DetectPayoutAdjustment: Created post-payout adjustment #{$adjustment->id} for booking #{$this->bookingId}");
            }
            return;
        }

        // Booking was never paid out -- check if it's orphaned (tour date before cycle start)
        $lastExecuted = $rule->last_executed_at
            ? Carbon::parse($rule->last_executed_at)
            : now()->subMonth();
        $cycleStart = $lastExecuted->copy()->subDays($bufferDays);

        if ($tourDate->lessThan($cycleStart)) {
            // Orphaned: tour date is before the current cycle window, will never be picked up
            $adjustment = $adjustmentService->createOrphanedBookingAdjustment($this->bookingId, $this->historyId);
            if ($adjustment) {
                Log::info("DetectPayoutAdjustment: Created orphaned booking adjustment #{$adjustment->id} for booking #{$this->bookingId}");
            }
        }

        // If tour date >= cycleStart, the booking is within the current cycle window
        // and the normal payout calculation will include it -- no adjustment needed.
    }
}
