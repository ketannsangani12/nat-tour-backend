<?php

namespace Tests\Unit\Bookings;

use App\Constants\Constants;
use App\Models\TourBookingAddonField;
use App\Repositories\TourBooking\TourBookingAddonRepository;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression for: deleting a pax from the classic "Edit Booking" delete flow (Pay Later bookings
 * especially — see editBooking()'s $checkarrays[$key] fallback) never removed that pax's own
 * add-on fields, because tour_booking_addon_fields.pax_index is an OPAQUE string set at booking
 * creation (see 2025_09_29_171159_create_tour_booking_addon_fields_table.php), not the simple
 * "0"/"1" position the admin's .ads_Checkbox rows carry. resolveFieldIdsByPaxPositions() must map
 * "the Nth person" (0-based, by creation order — same convention getIndividualPaxAddons() uses)
 * to that person's REAL pax_index value(s) before any field lookup, regardless of what the
 * opaque string actually contains.
 */
class ResolveFieldIdsByPaxPositionsTest extends TestCase
{
    public function test_resolves_the_second_persons_fields_by_position_despite_opaque_pax_index(): void
    {
        $booking = (new CreateABooking())->withoutTransaction()->create();
        $bookingId = $booking->bookingId;
        $paxId = 900100;

        // Person 0 ("first added") — two fields, non-numeric opaque pax_index.
        $personAField1 = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => $paxId, 'field_label' => 'Photography Package',
            'pax_index' => 'row-uuid-aaa', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        $personAField2 = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 2,
            'pax_id' => $paxId, 'field_label' => 'Sweater', 'option_label' => 'Small',
            'pax_index' => 'row-uuid-aaa', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        // Person 1 ("second added") — the one actually being removed.
        $personBField1 = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => $paxId, 'field_label' => 'Photography Package',
            'pax_index' => 'row-uuid-bbb', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        $personBField2 = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 2,
            'pax_id' => $paxId, 'field_label' => 'Sweater', 'option_label' => 'Medium',
            'pax_index' => 'row-uuid-bbb', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        try {
            $repo = app(TourBookingAddonRepository::class);

            // Admin checked ONLY the 2nd checkbox (0-based position 1) for deletion.
            $fieldIds = $repo->resolveFieldIdsByPaxPositions($paxId, [1]);

            sort($fieldIds);
            $expected = [$personBField1->id, $personBField2->id];
            sort($expected);
            $this->assertEquals($expected, $fieldIds, 'Must resolve to exactly person B\'s fields, not person A\'s');

            // And feeding that straight into deletePaxAddons() actually removes only those rows —
            // the end-to-end path editBooking()'s fallback exercises.
            $repo->deletePaxAddons($bookingId, '[]', Constants::ADDONS_DONT_REFUND, null, null, $fieldIds);

            $this->assertSoftDeleted('tour_booking_addon_fields', ['id' => $personBField1->id]);
            $this->assertSoftDeleted('tour_booking_addon_fields', ['id' => $personBField2->id]);
            $this->assertDatabaseHas('tour_booking_addon_fields', ['id' => $personAField1->id, 'deleted_at' => null]);
            $this->assertDatabaseHas('tour_booking_addon_fields', ['id' => $personAField2->id, 'deleted_at' => null]);
        } finally {
            TourBookingAddonField::withTrashed()
                ->whereIn('id', [$personAField1->id, $personAField2->id, $personBField1->id, $personBField2->id])
                ->forceDelete();
            $booking->cleanup();
        }
    }

    public function test_out_of_range_position_resolves_to_empty(): void
    {
        $booking = (new CreateABooking())->withoutTransaction()->create();
        $bookingId = $booking->bookingId;
        $paxId = 900200;

        $field = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => $paxId, 'field_label' => 'Photography Package',
            'pax_index' => 'only-person', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        try {
            $repo = app(TourBookingAddonRepository::class);

            $this->assertEquals([], $repo->resolveFieldIdsByPaxPositions($paxId, [5]));
            $this->assertEquals([], $repo->resolveFieldIdsByPaxPositions($paxId, []));
        } finally {
            TourBookingAddonField::withTrashed()->whereIn('id', [$field->id])->forceDelete();
            $booking->cleanup();
        }
    }
}
