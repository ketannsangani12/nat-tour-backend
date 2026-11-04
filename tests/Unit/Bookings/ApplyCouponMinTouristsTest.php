<?php

namespace Tests\Unit\Bookings;

use App\Http\Controllers\API\V2\TourBookingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: API\V2\TourBookingController::applyCoupon() -- the customer-facing public
 * booking widget's coupon endpoint (GET /api/v2/apply-coupon/{packageId}/{timeSlotId}/{code})
 * -- already correctly checked status='Active', the date range, and max_allowed_usages, but
 * never checked min_allowed_tourists (didn't even select it).
 *
 * Fixed by an OPTIONAL 'tourists' query param, checked only when the caller actually sends it.
 * This is deliberately fail-open when absent (not defaulted to 0) because the customer-facing
 * widget that calls this endpoint lives in a separate frontend repository this session can't
 * verify or update -- defaulting to 0 would have silently rejected every promo with a
 * configured minimum for every existing caller until that separate frontend is updated to send
 * a real count. The check only activates for callers that opt in by sending the param.
 */
class ApplyCouponMinTouristsTest extends TestCase
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

    private function applyCoupon(int $packageId, int $timeSlotId, string $code, array $query = []): \Illuminate\Http\JsonResponse
    {
        $request = Request::create('/api/v2/apply-coupon/' . $packageId . '/' . $timeSlotId . '/' . $code, 'GET', $query);
        return app(TourBookingController::class)->applyCoupon($request, $packageId, $timeSlotId, $code);
    }

    public function test_allows_coupon_when_tourists_param_is_not_sent_at_all(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->withRate(50.00)->create();
        $promoId = $this->makePromotion(['min_allowed_tourists' => 3, 'tour_package_id' => $booking->packageId]);
        $code = DB::table('tour_promotions')->find($promoId)->code;

        try {
            // No 'tourists' query param at all -- must NOT be rejected (fail-open for callers
            // that haven't been updated to send it).
            $response = $this->applyCoupon($booking->packageId, $booking->slotTimeId, $code);

            $data = json_decode($response->getContent(), true);
            $this->assertTrue($data['success'], 'A caller that never sends tourists must not be blocked by min_allowed_tourists');
        } finally {
            DB::table('tour_promotions')->where('id', $promoId)->delete();
            $booking->cleanup();
        }
    }

    public function test_rejects_coupon_when_tourists_param_is_sent_below_minimum(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->withRate(50.00)->create();
        $promoId = $this->makePromotion(['min_allowed_tourists' => 3, 'tour_package_id' => $booking->packageId]);
        $code = DB::table('tour_promotions')->find($promoId)->code;

        try {
            $response = $this->applyCoupon($booking->packageId, $booking->slotTimeId, $code, ['tourists' => 1]);

            $data = json_decode($response->getContent(), true);
            $this->assertFalse($data['success'], 'A caller that opts in with tourists=1 must be rejected against a min of 3');
            $this->assertStringContainsString('at least 3 tourist', $data['message']);
        } finally {
            DB::table('tour_promotions')->where('id', $promoId)->delete();
            $booking->cleanup();
        }
    }

    public function test_allows_coupon_when_tourists_param_meets_minimum(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->withRate(50.00)->create();
        $promoId = $this->makePromotion(['min_allowed_tourists' => 3, 'tour_package_id' => $booking->packageId]);
        $code = DB::table('tour_promotions')->find($promoId)->code;

        try {
            $response = $this->applyCoupon($booking->packageId, $booking->slotTimeId, $code, ['tourists' => 3]);

            $data = json_decode($response->getContent(), true);
            $this->assertTrue($data['success']);
            $this->assertEquals($promoId, $data['data']['id']);
        } finally {
            DB::table('tour_promotions')->where('id', $promoId)->delete();
            $booking->cleanup();
        }
    }

    public function test_still_rejects_coupon_once_max_allowed_usages_is_reached(): void
    {
        $bookingA = (new CreateABooking())->withPaymentMethod('Card')->withRate(50.00)->create();
        $bookingB = (new CreateABooking())->withPaymentMethod('Card')->withRate(50.00)->create();
        $promoId = $this->makePromotion(['max_allowed_usages' => 1, 'tour_package_id' => $bookingB->packageId]);
        $code = DB::table('tour_promotions')->find($promoId)->code;

        try {
            DB::table('tour_bookings_master')->where('id', $bookingA->bookingId)->update(['tour_promotion_id' => $promoId]);

            $response = $this->applyCoupon($bookingB->packageId, $bookingB->slotTimeId, $code);

            $data = json_decode($response->getContent(), true);
            $this->assertFalse($data['success'], 'Existing max_allowed_usages enforcement must still work after adding min_allowed_tourists');
        } finally {
            DB::table('tour_promotions')->where('id', $promoId)->delete();
            $bookingA->cleanup();
            $bookingB->cleanup();
        }
    }
}
