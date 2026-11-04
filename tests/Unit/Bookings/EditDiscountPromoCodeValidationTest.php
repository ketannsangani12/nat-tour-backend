<?php

namespace Tests\Unit\Bookings;

use App\Exceptions\Core\GeneralCoreException;
use App\Repositories\TourBooking\TourBookingRepository as TourBookingRepositoryV1;
use App\Repositories\TourBooking\V3\TourBookingRepository as TourBookingRepositoryV3;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: editDiscount()'s "Promo Code" case (both TourBookingRepository and its V3
 * counterpart) only ever checked that a promotion with the given code existed at all --
 * "Invalid Promocode" was the ONLY possible rejection. None of the promotion's own configured
 * limits were ever enforced here, even though the checkout-time coupon flow
 * (API\V2\TourBookingController::applyCoupon() / NetworkService::applyCoupon()) already
 * enforces max_allowed_usages for new bookings:
 *
 *   - max_allowed_usages: confirmed live -- a code created with max_allowed_usages=1, applied
 *     once via Edit Discount on one booking, was accepted again on a second, unrelated booking.
 *   - min_allowed_tourists: never enforced anywhere in the codebase at all (not even in the
 *     checkout-time flow) -- infants must NOT count toward it, since
 *     tour_bookings_master.tourists already excludes them by convention.
 *   - status / start_date-end_date: never enforced in this specific flow, though the checkout
 *     flow already scopes both.
 *   - the resulting discount amount exceeding the booking's own total: the sibling
 *     'Fixed Money'/'Percentage' cases in this exact switch already guard against this;
 *     'Promo Code' was the one case that didn't.
 *
 * Also caught along the way: the V1 repository's editDiscount() fetched $booking via a
 * restrictive select() missing tour_promotion_id/tourists/tour_slot_id -- tour_promotion_id
 * read as null, so the pre-existing "old promotion" lookup a few lines below silently never
 * ran (null != 0 is false in PHP), and the two new checks here would have false-tripped
 * against a null tourists/tour_slot_id. Fixed by adding the missing columns to that select().
 *
 * Runs against both TourBookingRepository (V1 API / affiliate & staff routes) and its V3
 * counterpart (admin web / V3 API) -- the bug and the fix are duplicated across both.
 */
class EditDiscountPromoCodeValidationTest extends TestCase
{
    protected function repositories(): array
    {
        return [
            'V1' => app(TourBookingRepositoryV1::class),
            'V3' => app(TourBookingRepositoryV3::class),
        ];
    }

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

    private function editDiscountRequest(int $bookingId, string $code): Request
    {
        $request = new Request();
        $request->merge([
            'bookingID' => $bookingId,
            'edit_discount_type' => 'Promo Code',
            'edit_discount_promo_code' => $code,
            'payment_method' => 'Card',
        ]);
        return $request;
    }

    public function test_rejects_promo_code_once_max_allowed_usages_is_reached(): void
    {
        foreach ($this->repositories() as $label => $repo) {
            $bookingA = (new CreateABooking())->withPaymentMethod('Card')->withRate(50.00)->create();
            $bookingB = (new CreateABooking())->withPaymentMethod('Card')->withRate(50.00)->create();
            $promoId = $this->makePromotion(['max_allowed_usages' => 1]);
            $code = DB::table('tour_promotions')->find($promoId)->code;

            try {
                // Simulate booking A having already consumed this promotion's one allowed use.
                DB::table('tour_bookings_master')->where('id', $bookingA->bookingId)->update(['tour_promotion_id' => $promoId]);

                $thrown = null;
                try {
                    $repo->editDiscount($this->editDiscountRequest($bookingB->bookingId, $code));
                } catch (GeneralCoreException $e) {
                    $thrown = $e;
                }

                $this->assertNotNull($thrown, "[$label] a second booking must not be able to use an exhausted promo code");
                $this->assertStringContainsString('maximum allowed usage', $thrown->message(), "[$label]");
            } finally {
                DB::table('tour_promotions')->where('id', $promoId)->delete();
                $bookingA->cleanup();
                $bookingB->cleanup();
            }
        }
    }

    public function test_rejects_promo_code_below_min_allowed_tourists_and_infants_dont_count(): void
    {
        foreach ($this->repositories() as $label => $repo) {
            // 1 real tourist + 5 infants -- infants must NOT count toward the minimum.
            $booking = (new CreateABooking())->withPaymentMethod('Card')->withRate(50.00)->withTourists(1)->withInfants(5)->create();
            $promoId = $this->makePromotion(['min_allowed_tourists' => 3]);
            $code = DB::table('tour_promotions')->find($promoId)->code;

            try {
                $thrown = null;
                try {
                    $repo->editDiscount($this->editDiscountRequest($booking->bookingId, $code));
                } catch (GeneralCoreException $e) {
                    $thrown = $e;
                }

                $this->assertNotNull($thrown, "[$label] a booking with only 1 tourist (5 infants aside) must not satisfy a min of 3");
                $this->assertStringContainsString('requires at least 3 tourist', $thrown->message(), "[$label]");
            } finally {
                DB::table('tour_promotions')->where('id', $promoId)->delete();
                $booking->cleanup();
            }
        }
    }

    public function test_rejects_promo_code_outside_its_date_range(): void
    {
        foreach ($this->repositories() as $label => $repo) {
            $booking = (new CreateABooking())->withPaymentMethod('Card')->withRate(50.00)
                ->withDate(now()->addMonths(2)->toDateString())
                ->create();
            // Window closed a month ago -- doesn't cover the booking's tour date.
            $promoId = $this->makePromotion([
                'start_date' => now()->subMonths(2)->toDateString(),
                'end_date' => now()->subMonth()->toDateString(),
            ]);
            $code = DB::table('tour_promotions')->find($promoId)->code;

            try {
                $thrown = null;
                try {
                    $repo->editDiscount($this->editDiscountRequest($booking->bookingId, $code));
                } catch (GeneralCoreException $e) {
                    $thrown = $e;
                }

                $this->assertNotNull($thrown, "[$label] a promo code whose window does not cover the tour date must be rejected");
                $this->assertStringContainsString('Invalid Promocode', $thrown->message(), "[$label]");
            } finally {
                DB::table('tour_promotions')->where('id', $promoId)->delete();
                $booking->cleanup();
            }
        }
    }

    public function test_rejects_promo_code_whose_discount_exceeds_the_booking_total(): void
    {
        foreach ($this->repositories() as $label => $repo) {
            $booking = (new CreateABooking())->withPaymentMethod('Card')->withRate(50.00)->create();
            // $50 booking, $999 fixed-money discount -- must not be allowed to go negative.
            $promoId = $this->makePromotion(['discount_value' => 999.00, 'discount_value_type' => 'Fixed Money']);
            $code = DB::table('tour_promotions')->find($promoId)->code;

            try {
                $thrown = null;
                try {
                    $repo->editDiscount($this->editDiscountRequest($booking->bookingId, $code));
                } catch (GeneralCoreException $e) {
                    $thrown = $e;
                }

                $this->assertNotNull($thrown, "[$label] a promo discount larger than the booking total must be rejected");
                $this->assertStringContainsString('cannot be greater than the total booking amount', $thrown->message(), "[$label]");
            } finally {
                DB::table('tour_promotions')->where('id', $promoId)->delete();
                $booking->cleanup();
            }
        }
    }

    public function test_allows_a_valid_promo_code_within_all_limits(): void
    {
        foreach ($this->repositories() as $label => $repo) {
            $booking = (new CreateABooking())->withPaymentMethod('Card')->withRate(50.00)->withTourists(2)->create();
            $promoId = $this->makePromotion(['min_allowed_tourists' => 1, 'max_allowed_usages' => 5, 'discount_value' => 5.00]);
            $code = DB::table('tour_promotions')->find($promoId)->code;

            try {
                $response = $repo->editDiscount($this->editDiscountRequest($booking->bookingId, $code));

                $data = json_decode($response->getContent(), true);
                $this->assertArrayHasKey('discountHistory', $data, "[$label] a promo code within every configured limit must still be accepted");
                $this->assertEquals($promoId, $data['discountHistory']['new_tour_promotion_id'], "[$label]");
            } finally {
                DB::table('tour_promotions')->where('id', $promoId)->delete();
                $booking->cleanup();
            }
        }
    }
}
