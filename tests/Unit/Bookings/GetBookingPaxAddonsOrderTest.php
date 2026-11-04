<?php

namespace Tests\Unit\Bookings;

use App\Models\AddonField;
use App\Models\AddonForm;
use App\Models\TourBookingAddonField;
use App\Repositories\TourBooking\TourBookingAddonRepository;
use Illuminate\Http\Request;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * TourBookingAddonRepository::getBookingPaxAddons() backs the "Passenger Add-ons" modal. Its
 * $paxList entries are built by iterating $addonRows->groupBy('pax_index') — and $addonRows is
 * fetched ordered by `af.sort_order` (the ADD-ON FIELD's own display order), not by traveler.
 * groupBy() keeps first-appearance order, so whichever traveler happens to carry the
 * lowest-sort_order field lands FIRST in the response, regardless of their real traveler position.
 *
 * Reproduces the exact bug: 4 travelers, "Photography Package" (lower sort_order) selected only
 * for travelers #3/#4, "Sweater" (higher sort_order, display-once) fanned out to all 4. Before the
 * fix, the response ordered pax as [#3, #4, #1, #2] — the "Passenger Add-ons" modal (which shows
 * each block with no traveler number of its own, see renderPaxAddonsModal()) then rendered this as
 * "block 1 has both fields, block 2 has both fields, block 3/4 have only Sweater" — visually
 * indistinguishable from Photography Package having moved onto travelers #1/#2.
 */
class GetBookingPaxAddonsOrderTest extends TestCase
{
    public function test_pax_list_is_ordered_by_person_ordinal_not_by_field_sort_order(): void
    {
        $booking = (new CreateABooking())->withTourists(4)->withPaymentMethod('Card')->create();

        // addon_fields.form_id is a real FK to addon_forms — needs a genuine row, not a bare
        // literal id (which only happened to exist by coincidence in a pre-seeded local DB).
        $form = AddonForm::create([
            'tour_package_id' => $booking->packageId,
            'name' => 'Test Addon Form ' . uniqid(),
            'status' => 'Active',
        ]);

        $photoField = AddonField::create([
            'form_id' => $form->id, 'label' => 'Photography Package', 'type' => 'checkbox',
            'unit_type' => 'price_per_pax', 'additional_fee' => true, 'price' => 15.00,
            'status' => 'Active', 'visibility' => 'both', 'sort_order' => 1,
        ]);
        $sweaterField = AddonField::create([
            'form_id' => $form->id, 'label' => 'Sweater', 'type' => 'dropdown',
            'unit_type' => 'price_per_pax', 'additional_fee' => true, 'price' => 30.00,
            'status' => 'Active', 'visibility' => 'both', 'sort_order' => 2,
        ]);

        // A real traveler's fields all share ONE pax_index (insertPricePerPaxBookingAddons()
        // generates a single pax_index per traveler entry, reused for every field that
        // traveler answers) — so each ordinal gets its own shared pax_index here too.
        $fieldIds = [];
        // Sweater: all 4 travelers (0-based ordinal 0, 1, 2, 3).
        foreach ([0, 1, 2, 3] as $ordinal) {
            $fieldIds[] = TourBookingAddonField::create([
                'tour_booking_id' => $booking->bookingId, 'form_id' => 1, 'field_id' => $sweaterField->id,
                'field_label' => 'Sweater', 'option_label' => 'X-Large', 'pax_id' => $booking->transactId,
                'pax_index' => "order-test-pax-{$ordinal}", 'person_ordinal' => $ordinal,
                'unit_type' => 'price_per_pax', 'unit' => 1,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ])->id;
        }
        // Photography Package: only travelers #3/#4 (0-based ordinal 2, 3) — same pax_index as
        // their own Sweater row above, since it's the same physical traveler.
        foreach ([2, 3] as $ordinal) {
            $fieldIds[] = TourBookingAddonField::create([
                'tour_booking_id' => $booking->bookingId, 'form_id' => 1, 'field_id' => $photoField->id,
                'field_label' => 'Photography Package', 'pax_id' => $booking->transactId,
                'pax_index' => "order-test-pax-{$ordinal}", 'person_ordinal' => $ordinal,
                'unit_type' => 'price_per_pax', 'unit' => 1,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ])->id;
        }

        try {
            $repo = new TourBookingAddonRepository();
            $request = new Request();
            $request->merge(['booking_id' => $booking->bookingId, 'rate_groups' => [$booking->rateId]]);

            $response = json_decode($repo->getBookingPaxAddons($request)->getContent(), true);

            $ordinals = array_column($response[0]['pax'], 'person_ordinal');
            $this->assertEquals([0, 1, 2, 3], $ordinals, 'Pax list must be ordered by real traveler position (person_ordinal), not by which field sorts first.');

            // Sanity: traveler #1/#2 (ordinal 0/1) have only 1 field each (Sweater); #3/#4
            // (ordinal 2/3) have 2 each (Photography + Sweater).
            foreach ($response[0]['pax'] as $pax) {
                $expectedCount = in_array($pax['person_ordinal'], [2, 3], true) ? 2 : 1;
                $this->assertCount($expectedCount, $pax['tree'], "Traveler ordinal {$pax['person_ordinal']} has the wrong number of add-on fields.");
            }
        } finally {
            TourBookingAddonField::withTrashed()->whereIn('id', $fieldIds)->forceDelete();
            AddonField::withTrashed()->whereIn('id', [$photoField->id, $sweaterField->id])->forceDelete();
            AddonForm::withTrashed()->where('id', $form->id)->forceDelete();
            $booking->cleanup();
        }
    }
}
