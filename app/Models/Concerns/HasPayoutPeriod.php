<?php

namespace App\Models\Concerns;

/**
 * Formats the booking-collection window stored on a payout.
 *
 * Shared by AutoPayout and PendingPayout so the window is rendered identically
 * wherever it appears (detail modal, export, notifications).
 */
trait HasPayoutPeriod
{
    /**
     * Human-readable collection window, or a clear "not recorded" for payouts made
     * before the window was persisted.
     *
     * No timezone conversion is applied. PayoutApprovalService writes these columns as
     * wall-clock time in the tour operator's timezone, so re-projecting them into any
     * other zone would shift the window away from the values the payout actually used.
     * The zone is stated in the label instead.
     */
    public function periodDisplay(): string
    {
        if (empty($this->period_start) || empty($this->period_end)) {
            return 'Not recorded';
        }

        $format = 'M j, Y g:i A';

        return $this->period_start->format($format)
            . ' - ' . $this->period_end->format($format)
            . ' (' . $this->payoutTimezoneLabel() . ')';
    }

    private function payoutTimezoneLabel(): string
    {
        try {
            $timezone = tourOperator()->timezone ?? null;
        } catch (\Throwable $e) {
            $timezone = null;
        }

        return $timezone ?: config('app.timezone');
    }
}
