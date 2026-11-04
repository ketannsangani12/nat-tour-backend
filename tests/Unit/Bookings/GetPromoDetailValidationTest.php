<?php

namespace Tests\Unit\Bookings;

use App\Http\Controllers\Common\TourBookingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: Common\TourBookingController::getPromoDetail() (the "New Booking" and Edit
 * Discount preview endpoint behind /admin/.../get-promo-detail, called from
 * tour-booking-add-edit.js, tour-booking-group-rates.js, and tour-booking-info.blade.php)
 * already checked status='Active' and the date range, but:
 *
 *   - selected max_allowed_usages and never checked it against real usage at all -- a promo
 *     could be previewed/applied here on unlimited bookings even after its allowed uses were
 *     already exhausted.
 *   - never checked min_allowed_tourists (not even selected), and had no way to -- none of its
 *     3 frontend call sites sent a tourist count or booking id.
 *
 * Fixed by: enforcing max_allowed_usages via the same TourBooking::where('tour_promotion_id',
 * $id)->count() check used elsewhere; enforcing min_allowed_tourists against either a new
 * 'tourists' param (New Booking flow, sent from the already-tracked, already infant-excluded
 * totalTourists JS variable) or a new 'bookingId' param (Edit Discount preview on an EXISTING
 * booking, deriving the same infant-excluded count from tour_bookings_master.tourists).
 */
class GetPromoDetailValidationTest extends TestCase
{
    private function makePromotion(array $overrides = []): int
    {
        return DB::table('tour_promotions')->insertGetId(array_merge([
            'tour_operator_id' => DB::table('tour_operators')->value('id') ?? 1,
            'tour_package_id' => DB::table('tour_packages')->where('status', 'Active')->value('id'),
            'code' => 'PROMO' . uniqid(),
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'max_allowed_usages' => 0,
            'min_allowed_tourists' => 0,
            'discount_value' => 5.00,
            'discount_value_type' => 'Fixed Money',
            'status' => 'Active',
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
        ], $overrides));
    }

    private function getPromoDetail(array $params): \Illuminate\Http\JsonResponse
    {
        $request = new Request();
        $request->merge($params);
        return app(TourBookingController::class)->getPromoDetail($request);
    }

    public function test_rejects_promo_code_once_max_allowed_usages_is_reached(): void
    {
        $bookingA = (new CreateABooking())->withPaymentMethod('Card')->withRate(50.00)->create();
        $bookingB = (new CreateABooking())->withPaymentMethod('Card')->withRate(50.00)->create();
        $promoId = $this->makePromotion(['max_allowed_usages' => 1, 'tour_package_id' => $bookingB->packageId]);
        $code = DB::table('tour_promotions')->find($promoId)->code;

        try {
            DB::table('tour_bookings_master')->where('id', $bookingA->bookingId)->update(['tour_promotion_id' => $promoId]);

            $response = $this->getPromoDetail([
                'promoCode' => $code,
                'tourPackageId' => $bookingB->packageId,
                'tourSlotId' => $bookingB->slotTimeId,
                'tourists' => 2,
            ]);

            $data = json_decode($response->getContent(), true);
            $this->assertArrayHasKey('errors', $data, 'An exhausted promo code must be rejected here too');
            $this->assertStringContainsString('maximum allowed usage', $data['errors']['common'][0]);
        } finally {
            DB::table('tour_promotions')->where('id', $promoId)->delete();
            $bookingA->cleanup();
            $bookingB->cleanup();
        }
    }

    public function test_rejects_promo_code_below_min_allowed_tourists_new_booking_flow(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->withRate(50.00)->create();
        $promoId = $this->makePromotion(['min_allowed_tourists' => 3, 'tour_package_id' => $booking->packageId]);
        $code = DB::table('tour_promotions')->find($promoId)->code;

        try {
            // New Booking flow -- no bookingId yet, only the in-progress tourist count.
            $response = $this->getPromoDetail([
                'promoCode' => $code,
                'tourPackageId' => $booking->packageId,
                'tourSlotId' => $booking->slotTimeId,
                'tourists' => 1,
            ]);

            $data = json_decode($response->getContent(), true);
            $this->assertArrayHasKey('errors', $data, 'A new booking with only 1 tourist must not satisfy a min of 3');
            $this->assertStringContainsString('requires at least 3 tourist', $data['errors']['common'][0]);
        } finally {
            DB::table('tour_promotions')->where('id', $promoId)->delete();
            $booking->cleanup();
        }
    }

    public function test_rejects_promo_code_below_min_allowed_tourists_existing_booking_flow_and_infants_dont_count(): void
    {
        // 1 real tourist + 5 infants -- infants must NOT count toward the minimum.
        $booking = (new CreateABooking())->withPaymentMethod('Card')->withRate(50.00)->withTourists(1)->withInfants(5)->create();
        $promoId = $this->makePromotion(['min_allowed_tourists' => 3, 'tour_package_id' => $booking->packageId]);
        $code = DB::table('tour_promotions')->find($promoId)->code;

        try {
            // Edit Discount preview on an EXISTING booking -- bookingId supplied, no 'tourists' param.
            $response = $this->getPromoDetail([
                'promoCode' => $code,
                'tourPackageId' => $booking->packageId,
                'tourSlotId' => $booking->slotTimeId,
                'bookingId' => $booking->bookingId,
            ]);

            $data = json_decode($response->getContent(), true);
            $this->assertArrayHasKey('errors', $data);
            $this->assertStringContainsString('requires at least 3 tourist', $data['errors']['common'][0]);
        } finally {
            DB::table('tour_promotions')->where('id', $promoId)->delete();
            $booking->cleanup();
        }
    }

    public function test_allows_a_valid_promo_code_within_all_limits(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->withRate(50.00)->create();
        $promoId = $this->makePromotion(['min_allowed_tourists' => 1, 'max_allowed_usages' => 5, 'tour_package_id' => $booking->packageId]);
        $code = DB::table('tour_promotions')->find($promoId)->code;

        try {
            $response = $this->getPromoDetail([
                'promoCode' => $code,
                'tourPackageId' => $booking->packageId,
                'tourSlotId' => $booking->slotTimeId,
                'tourists' => 2,
            ]);

            $data = json_decode($response->getContent(), true);
            $this->assertArrayNotHasKey('errors', $data, 'A promo code within every configured limit must still be accepted');
            $this->assertEquals($promoId, $data['id']);
        } finally {
            DB::table('tour_promotions')->where('id', $promoId)->delete();
            $booking->cleanup();
        }
    }
}
