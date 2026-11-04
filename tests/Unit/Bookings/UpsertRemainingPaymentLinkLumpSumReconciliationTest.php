<?php

namespace Tests\Unit\Bookings;

use Tests\TestCase;
use Tests\Fixture\CreateABooking;
use App\Models\Admin;
use App\Models\TourBooking;
use App\Models\TourBookingPaymentLink;
use App\Services\Booking\PaymentLinkService;
use App\Utils\BookingUtils;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * Regression: PaymentLinkService::upsertRemainingPaymentLink() is the shared mechanism behind
 * add-ons, change-people, custom-charge, split-pax and switch-package payment-link updates (9
 * call sites). It only ever looked for an EXISTING ITEMIZED link (section='remaining_payment')
 * to append to. When a booking's only pending balance was still the original LUMP-SUM link
 * (section=NULL, created at booking creation), that lookup came back empty, so it fell through to
 * its "create a brand new link" branch -- creating a SECOND, parallel Unpaid link for just the
 * new item while leaving the old lump-sum link untouched and still Unpaid. The customer would
 * then owe on two disjoint links instead of one correct combined total.
 *
 * Fix: when no itemized link exists, upsertRemainingPaymentLink() now looks for a coexisting
 * lump-sum Unpaid link and folds the new item's amount into it via the same
 * PaymentLinkService::paymentLinkUpdate('Add-ons') recompute-and-replace mechanism that
 * TourBookingAddonRepository::syncPayLaterAddonsPaymentLink() already relies on for this exact
 * scenario -- expiring the old link and creating one correctly-combined replacement.
 *
 * CMD: php artisan test --filter=UpsertRemainingPaymentLinkLumpSumReconciliationTest
 */
class UpsertRemainingPaymentLinkLumpSumReconciliationTest extends TestCase
{
    protected static array $builders = [];

    public function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    protected function authAsAdmin(): Admin
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    public function test_a_coexisting_lump_sum_link_is_folded_in_instead_of_leaving_two_active_links(): void
    {
        $this->authAsAdmin();

        $builder = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();
        static::$builders[] = $builder;

        $groupId = (string) $builder->bookingId;
        DB::table('tour_bookings_master')->where('id', $builder->bookingId)->update(['group_id' => $groupId]);

        // The original lump-sum link, exactly as storeTourBookingPaymentLink() creates it at
        // booking-creation time: section=NULL, no itemized rows.
        $originalAmount = 100.00;
        $lumpSumLinkId = DB::table('tour_booking_payment_links')->insertGetId([
            'order_id'             => $groupId,
            'section'              => null,
            'amount'               => $originalAmount,
            'reason_of_payment'    => 'Booking payment',
            'email'                => 'test@example.com',
            'phone_number'         => '5555551234',
            'token'                => Str::random(64),
            'status'               => 'Unpaid',
            'override_status'      => 'Yes',
            'expired_at'           => Carbon::now()->addDays(30),
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
        ]);

        try {
            $tourBooking = TourBooking::find($builder->bookingId);
            $info        = BookingUtils::getBookingDetails($builder->bookingId);

            PaymentLinkService::upsertRemainingPaymentLink(
                $groupId,
                [
                    'type'         => 'addon',
                    'reference_id' => null,
                    'amount'       => 40.00,
                    'reason'       => 'Payment for add-ons on your booking',
                    'details'      => [],
                ],
                $tourBooking,
                Carbon::now()->addDays(5),
                $info
            );

            // The old lump-sum link must be retired, not left dangling as a second live link.
            $this->assertEquals('Expired', DB::table('tour_booking_payment_links')->find($lumpSumLinkId)->status);

            // Exactly ONE Unpaid link must exist for the order -- never two.
            $unpaidLinks = TourBookingPaymentLink::where('order_id', $groupId)->where('status', 'Unpaid')->get();
            $this->assertCount(1, $unpaidLinks, 'The customer must owe on exactly one active link, not two disjoint ones');

            // And it must be correctly combined: old pending + new item ($100.00 + $40.00).
            $this->assertEqualsWithDelta(140.00, (float) $unpaidLinks->first()->amount, 0.001);

            // No itemized rows should have been created for this fold-in (it went through the
            // lump-sum replace path, not the itemized append path).
            $this->assertEquals(
                0,
                DB::table('tour_booking_payment_link_items')->where('payment_link_id', $unpaidLinks->first()->id)->count()
            );
        } finally {
            DB::table('tour_booking_payment_links')->where('order_id', $groupId)->delete();
            DB::table('tour_booking_history')->where('tour_booking_id', $builder->bookingId)->delete();
        }
    }

    public function test_an_existing_itemized_link_still_takes_priority_and_is_appended_to_normally(): void
    {
        $this->authAsAdmin();

        $builder = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();
        static::$builders[] = $builder;

        $groupId = (string) $builder->bookingId;
        DB::table('tour_bookings_master')->where('id', $builder->bookingId)->update(['group_id' => $groupId]);

        $itemizedLinkId = DB::table('tour_booking_payment_links')->insertGetId([
            'order_id'             => $groupId,
            'section'              => 'remaining_payment',
            'amount'               => 25.00,
            'reason_of_payment'    => 'Additional payment for your booking',
            'email'                => 'test@example.com',
            'phone_number'         => '5555551234',
            'token'                => Str::random(64),
            'status'               => 'Unpaid',
            'override_status'      => 'Yes',
            'expired_at'           => Carbon::now()->addDays(30),
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
        ]);
        DB::table('tour_booking_payment_link_items')->insert([
            'payment_link_id' => $itemizedLinkId,
            'type'            => 'custom_charge',
            'reference_id'    => null,
            'amount'          => 25.00,
            'status'          => 'Unpaid',
            'created_by'      => 1,
            'created_at'      => Carbon::now(),
            'updated_at'      => Carbon::now(),
        ]);

        try {
            $tourBooking = TourBooking::find($builder->bookingId);
            $info        = BookingUtils::getBookingDetails($builder->bookingId);

            PaymentLinkService::upsertRemainingPaymentLink(
                $groupId,
                [
                    'type'         => 'addon',
                    'reference_id' => null,
                    'amount'       => 15.00,
                    'reason'       => 'Payment for add-ons on your booking',
                    'details'      => [],
                ],
                $tourBooking,
                Carbon::now()->addDays(5),
                $info
            );

            // Same link, same id -- appended to, not replaced.
            $this->assertEquals('Unpaid', DB::table('tour_booking_payment_links')->find($itemizedLinkId)->status);
            $unpaidLinks = TourBookingPaymentLink::where('order_id', $groupId)->where('status', 'Unpaid')->get();
            $this->assertCount(1, $unpaidLinks);
            $this->assertEquals($itemizedLinkId, $unpaidLinks->first()->id);
            $this->assertEqualsWithDelta(40.00, (float) $unpaidLinks->first()->amount, 0.001);
        } finally {
            DB::table('tour_booking_payment_links')->where('order_id', $groupId)->delete();
            DB::table('tour_booking_history')->where('tour_booking_id', $builder->bookingId)->delete();
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
