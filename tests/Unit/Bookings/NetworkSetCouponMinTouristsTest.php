<?php

namespace Tests\Unit\Bookings;

use App\Services\Network\NetworkService;
use Exception;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: NetworkService::setCoupon() (the V4 Network/Partner white-label API's coupon
 * endpoint) -- the sibling to API\V2\TourBookingController::applyCoupon() -- was already
 * correct on status='Active', the date range, and max_allowed_usages, but never checked
 * min_allowed_tourists (didn't even select it), same gap as the customer-facing widget.
 *
 * Fixed the same way as applyCoupon(): an OPTIONAL $tourists parameter, threaded through
 * NetworkController::setCoupon() -> NetworkRepository::setCoupon() -> NetworkService::setCoupon(),
 * checked only when a caller actually supplies it (fail-open when null) since partner
 * integrations don't send this today and defaulting to 0 would have silently rejected every
 * promo with a configured minimum for every existing partner.
 *
 * Note: setCoupon() calls tenancy()->initialize()/->end() internally (it's meant to be called
 * from a central-context controller, unlike the tenant-context tests elsewhere in this suite)
 * -- tenancy()->initialize() is idempotent for the same tenant already active (see
 * vendor/stancl/tenancy/src/Tenancy.php), but ->end() (reached only on the SUCCESS path, since
 * the rejection paths throw before reaching it) really does tear tenancy down, so each test
 * re-initializes it before asserting/cleaning up.
 */
class NetworkSetCouponMinTouristsTest extends TestCase
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

    public function test_allows_coupon_when_tourists_is_not_supplied_at_all(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->withRate(50.00)->create();
        $promoId = $this->makePromotion(['min_allowed_tourists' => 3, 'tour_package_id' => $booking->packageId]);
        $code = DB::table('tour_promotions')->find($promoId)->code;

        try {
            $result = app(NetworkService::class)->setCoupon(
                tenantId: 'dixies',
                packageId: $booking->packageId,
                coupon: $code,
                requestDate: now()->toDateString(),
            );
            tenancy()->initialize('dixies');

            $this->assertEquals($promoId, $result['coupon']['id'], 'A partner that never sends tourists must not be blocked by min_allowed_tourists');
        } finally {
            tenancy()->initialize('dixies');
            DB::table('tour_promotions')->where('id', $promoId)->delete();
            $booking->cleanup();
        }
    }

    public function test_rejects_coupon_when_tourists_is_supplied_below_minimum(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->withRate(50.00)->create();
        $promoId = $this->makePromotion(['min_allowed_tourists' => 3, 'tour_package_id' => $booking->packageId]);
        $code = DB::table('tour_promotions')->find($promoId)->code;

        try {
            $thrown = null;
            try {
                app(NetworkService::class)->setCoupon(
                    tenantId: 'dixies',
                    packageId: $booking->packageId,
                    coupon: $code,
                    requestDate: now()->toDateString(),
                    tourists: 1,
                );
            } catch (Exception $e) {
                $thrown = $e;
            }
            tenancy()->initialize('dixies');

            $this->assertNotNull($thrown, 'A partner that opts in with tourists=1 must be rejected against a min of 3');
            $this->assertStringContainsString('at least 3 tourist', $thrown->getMessage());
        } finally {
            tenancy()->initialize('dixies');
            DB::table('tour_promotions')->where('id', $promoId)->delete();
            $booking->cleanup();
        }
    }

    public function test_allows_coupon_when_tourists_supplied_meets_minimum(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->withRate(50.00)->create();
        $promoId = $this->makePromotion(['min_allowed_tourists' => 3, 'tour_package_id' => $booking->packageId]);
        $code = DB::table('tour_promotions')->find($promoId)->code;

        try {
            $result = app(NetworkService::class)->setCoupon(
                tenantId: 'dixies',
                packageId: $booking->packageId,
                coupon: $code,
                requestDate: now()->toDateString(),
                tourists: 3,
            );
            tenancy()->initialize('dixies');

            $this->assertEquals($promoId, $result['coupon']['id']);
        } finally {
            tenancy()->initialize('dixies');
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

            $thrown = null;
            try {
                app(NetworkService::class)->setCoupon(
                    tenantId: 'dixies',
                    packageId: $bookingB->packageId,
                    coupon: $code,
                    requestDate: now()->toDateString(),
                );
            } catch (Exception $e) {
                $thrown = $e;
            }
            tenancy()->initialize('dixies');

            $this->assertNotNull($thrown, 'Existing max_allowed_usages enforcement must still work after adding min_allowed_tourists');
        } finally {
            tenancy()->initialize('dixies');
            DB::table('tour_promotions')->where('id', $promoId)->delete();
            $bookingA->cleanup();
            $bookingB->cleanup();
        }
    }
}
