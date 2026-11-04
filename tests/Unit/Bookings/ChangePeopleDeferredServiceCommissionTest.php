<?php

namespace Tests\Unit\Bookings;

use App\Models\Admin;
use App\Models\TourBookingPaymentLink;
use App\Models\TourBookingPaymentLinkItem;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use App\Services\Booking\RemainingPayment\Handlers\ChangePeopleItemHandler;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression (real repro: tenant `kens`, booking #348705): tour_bookings_master.service_commission
 * was bumped the instant a Change People pax was added with "Payment Link" as the method --
 * before the customer ever paid -- because changePeopleBooking()'s real-settlement branch listed
 * 'Payment Link' alongside 'Card'/cash-fee-Cash in its eager service_commission increment. Add-ons
 * and Custom Charge never do this (their fees live in separate, live-recomputed tables, never in
 * this stored running column) -- Change People was the inconsistent outlier.
 *
 * Fix, two parts:
 * 1. 'Payment Link' removed from that eager-write condition (TourBookingRepository.php)  --
 *    ticket cost (total/booking_total) still increments immediately (correct: owed regardless of
 *    payment status, same as a genuine Pay Later addition), only the FEE portion is deferred.
 * 2. ChangePeopleItemHandler::finalize() (reached once the item/link is ACTUALLY paid) now writes
 *    that fee into service_commission at that point instead -- otherwise the itemized
 *    (remaining_payment) shape would lose the fee from this column forever.
 * The lump-sum shape (this repro's actual situation -- no payment-link items at all) needs no
 * compensating write: PaymentLinkService::bookingUpdate() already recomputes service_commission
 * fresh from live transact rows once that link is actually paid.
 *
 * CMD: php artisan test --filter=ChangePeopleDeferredServiceCommissionTest
 */
class ChangePeopleDeferredServiceCommissionTest extends TestCase
{
    protected TourBookingRepository $repo;

    public function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->repo = app(TourBookingRepository::class);
    }

    protected function authAsAdmin(): Admin
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    protected function baseRequestData(int $bookingId, CreateABooking $builder, int $newTouristCount): array
    {
        return [
            'bookingID' => $bookingId,
            'slot_time' => $builder->slotTimeId,
            'tourists' => [$newTouristCount],
            'rate_group' => [$builder->rateId],
            'permit_fee' => [0],
            'total_tourist' => $newTouristCount,
            'discount_percentage' => 0,
            'discount_fixed_money' => 0,
            'payment_method' => 'Pay Later',
            'infantorchilds' => 0,
            'payment_amount' => 0,
            'refund_amount' => 0,
        ];
    }

    /**
     * Reproduces booking #348705 exactly: a booking whose only pending balance is the original
     * lump-sum Payment Link (section=NULL), adding a pax with an explicit "Payment Link" choice.
     */
    public function test_adding_a_pax_via_payment_link_does_not_bump_service_commission_before_collection(): void
    {
        $this->authAsAdmin();

        $builder = (new CreateABooking())
            ->withTourists(2)
            ->withRate(55.00)
            ->withPermitFee(15.00)
            ->withoutTransaction()
            // Non-zero fee percentage is essential here -- without it $serviceCommissionForPersist
            // is always 0 regardless of this fix, and every assertion below would pass trivially.
            ->withTransactOverrides(['nat_fees_percentage' => 9.82])
            ->create();

        $groupId = (string) $builder->bookingId;
        DB::table('tour_bookings_master')->where('id', $builder->bookingId)->update(['group_id' => $groupId]);
        $linkId = DB::table('tour_booking_payment_links')->insertGetId([
            'order_id'             => $groupId,
            'section'              => null,
            'amount'               => 161.00,
            'status'               => 'Unpaid',
            'override_status'      => 'Yes',
            'expired_at'           => now()->addDays(30),
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => now(),
        ]);

        $before = DB::table('tour_bookings_master')->where('id', $builder->bookingId)->first();
        // Confirmed via direct trace of $totalForPersist/$subttForPersist in this exact fixture
        // (2->3 pax, rate 55, permit 15): the real ticket-only delta EditBookingService computes
        // is 85.00, not a naive rate+permit_fee=70.00 -- the formula has nuances (per-pax vs
        // group-rate handling, etc.) outside this fix's scope. What matters for this test is
        // only that $after->total increases by exactly this ticket-only figure, not by this PLUS
        // the separately-tracked $serviceCommission (8.35 in this fixture) -- i.e. total must
        // never silently include a not-yet-collected Payment Link fee.
        $ticketCostForOnePax = 85.00;

        $request = new Request();
        $request->merge(array_merge(
            $this->baseRequestData($builder->bookingId, $builder, $before->tourists + 1),
            [
                'payment_method'    => 'Payment Link',
                'validation_check'  => 'false',
                'payment_amount'    => 73.30,
                'refund_amount'     => 0,
            ]
        ));

        try {
            $this->repo->changePeopleBooking($request);

            $after = DB::table('tour_bookings_master')->where('id', $builder->bookingId)->first();

            $this->assertEquals($before->tourists + 1, (int) $after->tourists, 'The pax must actually be added');

            // Ticket cost must still be owed immediately (exactly the new pax's ticket cost, no
            // fee folded in) -- this is the user's own repro: a booking whose established baseline
            // is fee-free total/booking_total (created via Payment Link) must stay fee-free after
            // a Payment-Link pax addition too, not silently pick up a partial fee mid-edit.
            $this->assertEqualsWithDelta(
                (float) $before->total + $ticketCostForOnePax,
                (float) $after->total,
                0.01,
                'total must increase by exactly the new pax\'s ticket cost -- no fee baked in before it is actually collected'
            );
            $this->assertEqualsWithDelta(
                (float) $before->booking_total + $ticketCostForOnePax,
                (float) $after->booking_total,
                0.01
            );
            $this->assertEqualsWithDelta(
                (float) $before->service_commission,
                (float) $after->service_commission,
                0.001,
                'service_commission must NOT be bumped for a not-yet-paid Payment Link addition'
            );
        } finally {
            DB::table('tour_booking_payment_links')->where('id', $linkId)->delete();
            DB::table('tour_booking_history')->where('tour_booking_id', $builder->bookingId)->delete();
            $builder->cleanup();
        }
    }

    /**
     * Part 2: once a Change People item on the ITEMIZED (remaining_payment) shape is actually
     * finalized (paid), the fee must now be recorded into service_commission -- it no longer gets
     * written eagerly at submission time for Payment Link.
     */
    public function test_finalizing_an_itemized_change_people_item_now_records_the_fee(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->create();

        $groupId = (string) $builder->bookingId;
        DB::table('tour_bookings_master')->where('id', $builder->bookingId)->update(['group_id' => $groupId]);

        $before = (float) DB::table('tour_bookings_master')->where('id', $builder->bookingId)->value('service_commission');

        $link = TourBookingPaymentLink::create([
            'order_id'             => $groupId,
            'section'              => 'remaining_payment',
            'amount'               => 80.50,
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
        $item = TourBookingPaymentLinkItem::create([
            'payment_link_id'      => $link->id,
            'type'                 => 'change_people',
            'reference_id'         => null,
            'amount'               => 80.50,
            'status'               => 'Unpaid',
            'details'              => json_encode([
                'tour_booking_id'     => $builder->bookingId,
                'ticket_cost'         => 73.30,
                'platform_fees'       => 7.20,
                'fee_deferred'        => true,
                'added_transact_data' => [],
            ]),
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'updated_by'           => 1,
            'updated_by_user_type' => 'Admin',
        ]);

        $pi = (object) [
            'id'      => 'pi_test_' . uniqid(),
            'charges' => (object) ['data' => [(object) ['application_fee' => null, 'payment_method_details' => ['card' => ['last4' => '4242', 'brand' => 'visa']]]]],
        ];

        try {
            ChangePeopleItemHandler::finalize($item, $builder->bookingId, $pi, 'Card');

            $after = (float) DB::table('tour_bookings_master')->where('id', $builder->bookingId)->value('service_commission');
            $this->assertEqualsWithDelta($before + 7.20, $after, 0.001, 'service_commission must now be incremented at finalize (actual payment) time');
        } finally {
            DB::table('transactions')->where('tour_booking_id', $builder->bookingId)->delete();
            DB::table('tour_booking_history')->where('tour_booking_id', $builder->bookingId)->where('charge_type', 'change_people')->delete();
            TourBookingPaymentLinkItem::where('payment_link_id', $link->id)->delete();
            $link->delete();
            $builder->cleanup();
        }
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
    }
}
