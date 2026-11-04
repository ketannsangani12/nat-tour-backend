<?php

namespace Tests\Unit\Bookings;

use App\Services\Booking\AddonBookingService;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * `AddonBookingService::fanOutDisplayOnceAddons()` is the whole implementation of the
 * "display once with other add-ons" feature: a price_per_pax field flagged `display_once`
 * is answered ONCE by the customer (rendered in the general add-ons section, not duplicated
 * per traveler), but must still be charged/stored exactly like an ordinary price_per_pax
 * field — one `tour_booking_addon_fields`/`tour_booking_addon_transactions` row per traveler.
 *
 * This is pure data-shaping (no DB writes), run on the raw `pax_addons_data`/`addons_data`
 * JSON strings BEFORE AddonBookingService::insertPricePerPaxBookingAddons()/insertBookingAddons()
 * ever see them — those two methods, and the per-rate amount-calculation loops in
 * TourBookingRepository, are never touched and never need to know this feature exists.
 */
class DisplayOnceAddonsFanOutTest extends TestCase
{
    private function pricePerPaxField(int $fieldId, float $price): array
    {
        return [
            'field_id' => $fieldId,
            'field_label' => 'Please pick me up',
            'type' => 'radio',
            'unit_type' => 'price_per_pax',
            'value' => 'checked',
            'option_price' => $price,
            'option_price_enabled' => 1,
        ];
    }

    private function oneTimeField(int $fieldId, float $price): array
    {
        return [
            'field_id' => $fieldId,
            'field_label' => 'Trip Protection',
            'type' => 'checkbox',
            'unit_type' => 'one_time',
            'value' => 'checked',
            'field_price' => $price,
            'field_price_enabled' => 1,
        ];
    }

    public function test_passthrough_when_addons_data_has_no_display_once_field(): void
    {
        $addonsData = json_encode(['form_id' => 12, 'fields' => ['addons' => [
            ['50' => $this->oneTimeField(50, 10.00)],
        ]]]);
        $paxAddonsData = json_encode(['form_id' => 12, 'fields' => []]);

        $result = AddonBookingService::fanOutDisplayOnceAddons($paxAddonsData, $addonsData, [101 => 3]);

        $this->assertSame($paxAddonsData, $result['pax_addons_data'], 'No price_per_pax field present — pax_addons_data must be untouched.');
        $this->assertSame($addonsData, $result['addons_data'], 'No price_per_pax field present — addons_data must be untouched.');
    }

    public function test_passthrough_when_addons_data_is_null_or_empty(): void
    {
        $result = AddonBookingService::fanOutDisplayOnceAddons('{"form_id":12,"fields":{}}', null, [101 => 2]);
        $this->assertNull($result['addons_data']);
        $this->assertSame('{"form_id":12,"fields":{}}', $result['pax_addons_data']);

        $result = AddonBookingService::fanOutDisplayOnceAddons(null, '', [101 => 2]);
        $this->assertSame('', $result['addons_data']);
        $this->assertNull($result['pax_addons_data']);
    }

    public function test_malformed_json_returns_original_payloads_without_throwing(): void
    {
        $result = AddonBookingService::fanOutDisplayOnceAddons('not json', '{also not json', [101 => 2]);

        $this->assertSame('not json', $result['pax_addons_data']);
        $this->assertSame('{also not json', $result['addons_data']);
    }

    /**
     * The exact scenario this feature exists for: a form with ONLY a display-once field, no
     * genuine per-pax fields at all — the per-pax modal never opens client-side, so
     * pax_addons_data is empty/absent entirely, yet the charge must still land once per traveler.
     */
    public function test_expands_display_once_field_across_all_travelers_when_pax_addons_data_is_empty(): void
    {
        $addonsData = json_encode(['form_id' => 12, 'fields' => ['addons' => [
            ['45' => $this->pricePerPaxField(45, 20.00)],
        ]]]);

        $result = AddonBookingService::fanOutDisplayOnceAddons(null, $addonsData, [101 => 3]);

        $pax = json_decode($result['pax_addons_data'], true);
        $this->assertSame(12, $pax['form_id']);
        $this->assertCount(3, $pax['fields'][101]['addons'], 'One entry per traveler on rate 101.');
        foreach ($pax['fields'][101]['addons'] as $entry) {
            $this->assertArrayHasKey('45', $entry, 'Every traveler entry must carry the display-once field.');
            $this->assertEquals(20.00, $entry['45']['option_price']);
        }

        $general = json_decode($result['addons_data'], true);
        $this->assertEmpty(
            array_filter($general['fields']['addons'][0], fn($f) => is_array($f) && ($f['unit_type'] ?? null) === 'price_per_pax'),
            'The display-once field must be removed from the general bucket — insertBookingAddons() must not also create a single pax_id=null row for it.'
        );
    }

    /**
     * A rate group that ALSO has a genuine price_per_pax field already populated by the per-pax
     * modal: the display-once field must be merged into the SAME existing traveler entries, not
     * appended as extra travelers.
     *
     * uids end in "-1"/"-2" (1-based), matching the REAL per-pax modal's own convention
     * (createPaxDetail()'s `index` is 1-based) — the trailing "-N" is what fanOutDisplayOnceAddons()
     * (and insertPricePerPaxBookingAddons() downstream) parses as each entry's true ordinal.
     */
    public function test_merges_display_once_field_into_existing_pax_entries_without_adding_extra_travelers(): void
    {
        $addonsData = json_encode(['form_id' => 12, 'fields' => ['addons' => [
            ['45' => $this->pricePerPaxField(45, 20.00)],
        ]]]);
        $paxAddonsData = json_encode(['form_id' => 12, 'fields' => [101 => ['addons' => [
            ['uid' => 'slot-1-1', '60' => ['field_id' => 60, 'unit_type' => 'price_per_pax', 'value' => 'checked', 'field_price' => 5, 'field_price_enabled' => 1]],
            ['uid' => 'slot-1-2', '60' => ['field_id' => 60, 'unit_type' => 'price_per_pax', 'value' => 'unchecked', 'field_price' => 5, 'field_price_enabled' => 0]],
        ]]]]);

        $result = AddonBookingService::fanOutDisplayOnceAddons($paxAddonsData, $addonsData, [101 => 2]);

        $pax = json_decode($result['pax_addons_data'], true);
        $this->assertCount(2, $pax['fields'][101]['addons'], 'Must not add extra traveler entries beyond the real headcount.');
        foreach ($pax['fields'][101]['addons'] as $entry) {
            $this->assertArrayHasKey('60', $entry, 'The genuine per-pax field must be left untouched.');
            $this->assertArrayHasKey('45', $entry, 'The display-once field must be merged into every existing traveler entry.');
        }
    }

    /**
     * Regression: the exact reported bug. Only SOME travelers (the last 2 of 4) already have a
     * genuine per-pax selection when a display-once field is fanned out. Padding must fill in
     * ONLY the ordinals travelers #1/#2 actually occupy — not duplicate travelers #3/#4's own
     * slots while leaving #1/#2 without any entry at all.
     */
    public function test_merges_display_once_field_when_only_later_travelers_have_a_selection(): void
    {
        $addonsData = json_encode(['form_id' => 12, 'fields' => ['addons' => [
            ['45' => $this->pricePerPaxField(45, 30.00)],
        ]]]);
        // Only travelers #3 and #4 (1-based uid suffix) picked the genuine per-pax field —
        // collectPaxAddonSelections() never emits entries for #1/#2 at all.
        $paxAddonsData = json_encode(['form_id' => 12, 'fields' => [101 => ['addons' => [
            ['uid' => 'slot-1-3', '60' => ['field_id' => 60, 'unit_type' => 'price_per_pax', 'value' => 'yes', 'field_price' => 15, 'field_price_enabled' => 1]],
            ['uid' => 'slot-1-4', '60' => ['field_id' => 60, 'unit_type' => 'price_per_pax', 'value' => 'yes', 'field_price' => 15, 'field_price_enabled' => 1]],
        ]]]]);

        $result = AddonBookingService::fanOutDisplayOnceAddons($paxAddonsData, $addonsData, [101 => 4]);

        $pax = json_decode($result['pax_addons_data'], true);
        $addons = $pax['fields'][101]['addons'];
        $this->assertCount(4, $addons, 'Exactly one entry per physical traveler, no duplicates.');

        // Every entry must carry the display-once field regardless of ordinal.
        foreach ($addons as $entry) {
            $this->assertArrayHasKey('45', $entry, 'The display-once field must reach every traveler.');
        }

        // The genuine per-pax field must stay attached to travelers #3/#4 ONLY (uids ending -3/-4),
        // never to the synthesized placeholders for #1/#2.
        $withGenuineField = collect($addons)->filter(fn ($e) => array_key_exists('60', $e))->values();
        $this->assertCount(2, $withGenuineField, 'Only the 2 travelers who actually picked it keep the genuine field.');
        $uidsWithGenuineField = $withGenuineField->pluck('uid')->sort()->values()->all();
        $this->assertEquals(['slot-1-3', 'slot-1-4'], $uidsWithGenuineField);

        // The 2 synthesized placeholders must be for ordinals 0 and 1 (travelers #1/#2) — i.e.
        // "display-once-101-0" and "display-once-101-1" — not colliding with ordinals 2/3 already
        // claimed by travelers #3/#4.
        $placeholderUids = collect($addons)->pluck('uid')->filter(fn ($u) => str_starts_with($u, 'display-once-'))->sort()->values()->all();
        $this->assertEquals(['display-once-101-0', 'display-once-101-1'], $placeholderUids);
    }

    public function test_handles_multiple_rate_groups_with_different_tourist_counts(): void
    {
        $addonsData = json_encode(['form_id' => 12, 'fields' => ['addons' => [
            ['45' => $this->pricePerPaxField(45, 20.00)],
        ]]]);

        $result = AddonBookingService::fanOutDisplayOnceAddons(null, $addonsData, [101 => 2, 202 => 1]);

        $pax = json_decode($result['pax_addons_data'], true);
        $this->assertCount(2, $pax['fields'][101]['addons']);
        $this->assertCount(1, $pax['fields'][202]['addons']);
    }

    public function test_zero_tourist_rate_groups_are_skipped(): void
    {
        $addonsData = json_encode(['form_id' => 12, 'fields' => ['addons' => [
            ['45' => $this->pricePerPaxField(45, 20.00)],
        ]]]);

        $result = AddonBookingService::fanOutDisplayOnceAddons(null, $addonsData, [101 => 0]);

        $pax = json_decode($result['pax_addons_data'], true);
        $this->assertArrayNotHasKey(101, $pax['fields']);
    }

    /**
     * End-to-end through the SAME unmodified storage path a genuine (non-flagged) price_per_pax
     * field uses: feed fanOutDisplayOnceAddons()'s own output straight into
     * insertPricePerPaxBookingAddons() against a real booking, and confirm it lands as one
     * tour_booking_addon_fields row + one tour_booking_addon_transactions row per traveler —
     * i.e. storage is byte-for-byte identical to a real price_per_pax field, only the answer
     * was collected once instead of once per traveler.
     */
    public function test_expanded_payload_stores_one_row_per_traveler_via_the_real_insert_path(): void
    {
        $booking = (new CreateABooking())->withTourists(3)->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;
        $rateId = $booking->rateId;

        try {
            $addonsData = json_encode(['form_id' => 999, 'fields' => ['addons' => [
                ['77' => $this->pricePerPaxField(77, 20.00)],
            ]]]);

            $expanded = AddonBookingService::fanOutDisplayOnceAddons(null, $addonsData, [$rateId => 3]);
            $paxAddons = json_decode($expanded['pax_addons_data'], true);

            AddonBookingService::insertPricePerPaxBookingAddons($paxAddons, $bookingId);

            $fieldRows = DB::table('tour_booking_addon_fields')
                ->where('tour_booking_id', $bookingId)
                ->where('field_id', 77)
                ->get();
            $this->assertCount(3, $fieldRows, 'One field row per traveler, exactly like a genuine price_per_pax field.');
            $this->assertEqualsCanonicalizing([0, 1, 2], $fieldRows->pluck('person_ordinal')->map(fn($v) => (int) $v)->all());
            $this->assertTrue($fieldRows->every(fn($r) => (int) $r->unit === 1), 'price_per_pax rows are always 1 unit, per person.');
            $this->assertTrue($fieldRows->every(fn($r) => !empty($r->pax_index)), 'Every row must have its own pax_index.');
            $this->assertCount(1, $fieldRows->pluck('pax_id')->unique(), 'All 3 belong to the same rate line (BookingTransact row).');
        } finally {
            DB::table('tour_booking_addon_transactions')->where('tour_booking_id', $bookingId)->delete();
            DB::table('tour_booking_addon_fields')->where('tour_booking_id', $bookingId)->delete();
            $booking->cleanup();
        }
    }

    /**
     * Regression, end-to-end through the real storage path: 4 travelers, a genuine per-pax
     * checkbox ("Photography Package") selected ONLY for travelers #3/#4, plus a display-once
     * field ("Sweater") that fans out to all 4. Reproduces the exact reported bug — before the
     * fix, travelers #1/#2 ended up with BOTH fields (stolen from #3/#4) while #3/#4 kept only
     * the fanned-out Sweater.
     */
    public function test_sparse_genuine_selection_with_display_once_field_lands_on_correct_travelers(): void
    {
        $booking = (new CreateABooking())->withTourists(4)->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;
        $rateId = $booking->rateId;

        try {
            $addonsData = json_encode(['form_id' => 999, 'fields' => ['addons' => [
                ['77' => $this->pricePerPaxField(77, 30.00)], // Sweater — display-once
            ]]]);
            // Only travelers #3/#4 (1-based uid suffix) checked Photography Package.
            $paxAddonsData = json_encode(['form_id' => 999, 'fields' => [$rateId => ['addons' => [
                ['uid' => "slot-{$rateId}-3", '88' => $this->pricePerPaxField(88, 15.00)],
                ['uid' => "slot-{$rateId}-4", '88' => $this->pricePerPaxField(88, 15.00)],
            ]]]]);

            $expanded = AddonBookingService::fanOutDisplayOnceAddons($paxAddonsData, $addonsData, [$rateId => 4]);
            $paxAddons = json_decode($expanded['pax_addons_data'], true);

            AddonBookingService::insertPricePerPaxBookingAddons($paxAddons, $bookingId);

            $photoRows = DB::table('tour_booking_addon_fields')
                ->where('tour_booking_id', $bookingId)->where('field_id', 88)->get();
            $sweaterRows = DB::table('tour_booking_addon_fields')
                ->where('tour_booking_id', $bookingId)->where('field_id', 77)->get();

            $this->assertCount(2, $photoRows, 'Photography Package must only ever land on 2 travelers.');
            $this->assertEqualsCanonicalizing([2, 3], $photoRows->pluck('person_ordinal')->map(fn ($v) => (int) $v)->all(), 'Photography Package must land on travelers #3/#4 (0-based ordinal 2/3), never #1/#2.');

            $this->assertCount(4, $sweaterRows, 'Sweater must land on all 4 travelers.');
            $this->assertEqualsCanonicalizing([0, 1, 2, 3], $sweaterRows->pluck('person_ordinal')->map(fn ($v) => (int) $v)->all());
        } finally {
            DB::table('tour_booking_addon_transactions')->where('tour_booking_id', $bookingId)->delete();
            DB::table('tour_booking_addon_fields')->where('tour_booking_id', $bookingId)->delete();
            $booking->cleanup();
        }
    }
}
