<?php

namespace Tests\Unit\Bookings;

use Tests\TestCase;
use Tests\Fixture\CreateABooking;
use App\Services\Booking\PaymentLinkService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * Regression: several call sites (Custom Charge add/refund, Discount's Pay-Later branch,
 * Switch Package's Pay-Later service) used to look up "any Unpaid link for this order" with
 * no `section` filter — so if an itemized (section='remaining_payment') link coexisted with
 * the lump-sum balance they actually meant to find, they'd hand the itemized link to
 * PaymentLinkService::paymentLinkUpdate(), which always fully expires+reissues as a fresh
 * flat lump-sum link — silently destroying the itemized link's item-level detail.
 *
 * Fix: PaymentLinkService::findLumpSumLink() — one shared helper, explicitly excluding
 * section='remaining_payment', used at every one of those call sites.
 *
 * CMD: php artisan test --filter=FindLumpSumLinkExcludesItemizedTest
 */
class FindLumpSumLinkExcludesItemizedTest extends TestCase
{
    protected static array $builders = [];

    public function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function insertLink(string $groupId, ?string $section, float $amount): int
    {
        return DB::table('tour_booking_payment_links')->insertGetId([
            'order_id'             => $groupId,
            'section'              => $section,
            'amount'               => $amount,
            'reason_of_payment'    => 'Test',
            'email'                => 'test@example.com',
            'phone_number'         => '5555551234',
            'token'                => Str::random(64),
            'status'               => 'Unpaid',
            'override_status'      => 'Yes',
            'expired_at'           => Carbon::now()->addDays(30),
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => Carbon::now(),
            'updated_at'           => Carbon::now(),
        ]);
    }

    public function test_returns_the_lump_sum_link_when_only_a_lump_sum_link_exists(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();
        static::$builders[] = $builder;
        $groupId = (string) $builder->bookingId;

        $lumpSumId = $this->insertLink($groupId, null, 100.00);

        try {
            $found = PaymentLinkService::findLumpSumLink($groupId);
            $this->assertNotNull($found);
            $this->assertEquals($lumpSumId, $found->id);
        } finally {
            DB::table('tour_booking_payment_links')->where('order_id', $groupId)->delete();
        }
    }

    public function test_returns_null_when_only_an_itemized_link_exists(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();
        static::$builders[] = $builder;
        $groupId = (string) $builder->bookingId;

        $this->insertLink($groupId, 'remaining_payment', 50.00);

        try {
            $found = PaymentLinkService::findLumpSumLink($groupId);
            $this->assertNull($found, 'An itemized link must never be returned as the lump-sum link');
        } finally {
            DB::table('tour_booking_payment_links')->where('order_id', $groupId)->delete();
        }
    }

    public function test_returns_only_the_lump_sum_link_when_both_coexist(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();
        static::$builders[] = $builder;
        $groupId = (string) $builder->bookingId;

        $itemizedId = $this->insertLink($groupId, 'remaining_payment', 50.00);
        $lumpSumId  = $this->insertLink($groupId, null, 100.00);

        try {
            $found = PaymentLinkService::findLumpSumLink($groupId);
            $this->assertNotNull($found);
            $this->assertEquals($lumpSumId, $found->id, 'Must find the lump-sum link, not the coexisting itemized one');
            $this->assertNotEquals($itemizedId, $found->id);
        } finally {
            DB::table('tour_booking_payment_links')->where('order_id', $groupId)->delete();
        }
    }

    public static function tearDownAfterClass(): void
    {
        foreach (static::$builders as $builder) {
            $builder->cleanup();
        }
        static::$builders = [];
        parent::tearDownAfterClass();
    }
}
