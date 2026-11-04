<?php

namespace Tests\Unit\Helpers;

use Tests\TestCase;

/**
 * Regression: every add-ons refund history row is written by the single shared
 * HistoryService::storeAddonsRefundInHistory() (bulk pax add-ons, single add-on delete, Change
 * People's addon-only settlement, "Don't Refund" leftovers — all of it), which stores the admin's
 * note under $details->description (see its $request->description param). Every OTHER refund/
 * cancel writer (TicketRefundService, WeatherRefundService, PartialRefundCancelService, etc.)
 * stores it under $details->note instead — and resolveRefundData() (app/helpers.php), which feeds
 * the "Note: ..." box in history-entries/_refund.blade.php, only ever read ->note. An add-ons
 * refund's note was genuinely saved to tour_booking_history but silently never rendered.
 */
class ResolveRefundDataNoteTest extends TestCase
{
    private function makeHist(?string $chargeType, array $details): object
    {
        $details['amount'] ??= 0;
        $details['created_by_with_name'] ??= 'Test Admin: (Admin)'; // avoids a DB lookup in resolveHistCreatedBy()

        return (object) [
            'card_last_4_digit' => null,
            'details' => (object) $details,
            'charge_type' => $chargeType,
            'ticket_cost' => 0,
            'platform_fees' => 0,
            'payment_type' => 'Cash',
            'operation_by_user_type' => 'Admin',
            'username' => '',
        ];
    }

    public function test_addons_refund_note_falls_back_to_description(): void
    {
        $hist = $this->makeHist('addons', ['description' => 'Customer requested no refund, kept as courtesy']);

        $d = resolveRefundData($hist);

        $this->assertSame('Customer requested no refund, kept as courtesy', $d['note']);
    }

    public function test_addons_refund_prefers_a_real_note_over_description_when_both_exist(): void
    {
        $hist = $this->makeHist('addons', ['note' => 'The real note', 'description' => 'Should be ignored']);

        $d = resolveRefundData($hist);

        $this->assertSame('The real note', $d['note']);
    }

    public function test_addons_refund_with_neither_field_has_no_note(): void
    {
        $hist = $this->makeHist('addons', []);

        $d = resolveRefundData($hist);

        $this->assertSame('', $d['note']);
    }

    /**
     * A tour-side refund's own $details->note must keep working exactly as before — this fix
     * only ever ADDS a fallback, never changes the primary source.
     */
    public function test_tour_side_refund_note_is_unaffected(): void
    {
        $hist = $this->makeHist(null, ['note' => 'Weather cancellation']);

        $d = resolveRefundData($hist);

        $this->assertSame('Weather cancellation', $d['note']);
    }

    /**
     * CustomChargeBookingService stores ITS OWN unrelated $details->description (the custom
     * charge's own description, e.g. "Late checkout fee") — the addons-only fallback must never
     * leak that in as if it were an admin's refund note on a non-addons row.
     */
    public function test_custom_charge_description_is_not_shown_as_a_note(): void
    {
        $hist = $this->makeHist('custom_charge', ['description' => 'Late checkout fee']);

        $d = resolveRefundData($hist);

        $this->assertSame('', $d['note']);
    }
}
