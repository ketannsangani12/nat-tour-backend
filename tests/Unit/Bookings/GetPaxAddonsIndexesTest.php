<?php

namespace Tests\Unit\Bookings;

use App\Models\TourBookingAddonField;
use App\Repositories\TourBooking\TourBookingAddonRepository;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

class GetPaxAddonsIndexesTest extends TestCase
{
    /**
     * Regression: 2 pax, only the 2ND one has an add-on (the 1st was never given one — no
     * pax was ever removed). The old count-based implementation returned range(0, n-1) —
     * "however many pax have add-ons" — which for n=1 is [0], marking the WRONG (1st) pax's
     * icon as "has add-ons" on the booking page and mis-scoping the "Add Passenger Add-ons"
     * modal's pre-fill to the wrong slot. It must return [1], the add-on's OWN real slot.
     */
    public function test_only_second_pax_has_addon(): void
    {
        $booking = (new CreateABooking())->withTourists(2)->withPaymentMethod('Card')->create();
        $fieldId = 800101;

        try {
            TourBookingAddonField::create([
                'id' => $fieldId, 'tour_booking_id' => $booking->bookingId, 'form_id' => 1, 'field_id' => 1,
                'field_label' => 'Photography Package', 'pax_id' => $booking->transactId,
                'pax_index' => 'test-second-pax', 'person_ordinal' => 1, 'unit_type' => 'price_per_pax', 'unit' => 1,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);

            $indexes = (new TourBookingAddonRepository())->getPaxAddonsIndexes($booking->bookingId, $booking->transactId, 2);

            $this->assertEquals([1], $indexes, 'Only slot 1 (the real pax with the add-on) should be marked, not slot 0');
        } finally {
            TourBookingAddonField::withTrashed()->where('id', $fieldId)->forceDelete();
            $booking->cleanup();
        }
    }

    /**
     * Regression guard: the original "removal" scenario this function's count-based approach
     * was written to protect. 2 pax originally, ordinal 0's addon-having pax is fully removed
     * (headcount now 1) — the surviving ordinal-1 pax's add-on must still be found, in the
     * only slot that remains, not silently dropped just because its own ordinal (1) no
     * longer fits within the shrunk headcount (1).
     */
    public function test_surviving_higher_ordinal_pax_still_found_after_removal(): void
    {
        $booking = (new CreateABooking())->withTourists(1)->withPaymentMethod('Card')->create();
        $fieldId = 800102;

        try {
            TourBookingAddonField::create([
                'id' => $fieldId, 'tour_booking_id' => $booking->bookingId, 'form_id' => 1, 'field_id' => 1,
                'field_label' => 'Photography Package', 'pax_id' => $booking->transactId,
                'pax_index' => 'test-surviving-pax', 'person_ordinal' => 1, 'unit_type' => 'price_per_pax', 'unit' => 1,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);

            $indexes = (new TourBookingAddonRepository())->getPaxAddonsIndexes($booking->bookingId, $booking->transactId, 1);

            $this->assertEquals([0], $indexes, 'The surviving pax must be found in the only remaining slot');
        } finally {
            TourBookingAddonField::withTrashed()->where('id', $fieldId)->forceDelete();
            $booking->cleanup();
        }
    }
}
