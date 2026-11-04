<?php

namespace Tests\Unit\Bookings;

use Tests\TestCase;
use Tests\Fixture\CreateABooking;
use App\Models\Admin;
use App\Models\TourBookingPaymentLink;
use App\Models\TourBookingPaymentLinkItem;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Unit tests for TourBookingRepository::refundCustomCharge() — the "Don't Refund" path for a
 * custom charge that's still Open (unpaid via payment link, never collected from the customer).
 *
 * CMD: php artisan test --filter=RefundCustomChargeUnpaidLinkTest
 */
class RefundCustomChargeUnpaidLinkTest extends TestCase
{
    protected TourBookingRepository $repo;
    protected static array $builders = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->repo = app(TourBookingRepository::class);
        Queue::fake();
    }

    protected function authAsAdmin(): Admin
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    /**
     * Reproduces the reported bug: an Open (unpaid, sent via payment link) custom charge, when
     * "Don't Refund" is submitted from the Refund modal, had the pending history row deleted and
     * NO replacement history row written — silently erasing the charge from tour_booking_history
     * entirely, with no audit trail of the "Don't Refund" decision.
     *
     * @test
     * CMD: php artisan test --filter=refundCustomCharge_writesZeroAmountHistoryRowForOpenChargeDontRefund
     */
    public function refundCustomCharge_writesZeroAmountHistoryRowForOpenChargeDontRefund(): void
    {
        $this->authAsAdmin();

        $builder = (new CreateABooking())->withTourists(1)->create();
        static::$builders[] = $builder;

        $groupId = (string) $builder->bookingId;
        DB::table('tour_bookings_master')->where('id', $builder->bookingId)->update(['group_id' => $groupId]);

        $chargeId = DB::table('tour_booking_custom_charges')->insertGetId([
            'tour_booking_id' => $builder->bookingId,
            'amount'          => 10.00,
            'fees'            => 0.98,
            'taxable'         => 1,
            'description'     => 'Test custom charge',
            'status'          => 'Open',
            'operation_by'    => 1,
            'created_at'      => Carbon::now(),
            'updated_at'      => Carbon::now(),
        ]);

        $link = TourBookingPaymentLink::create([
            'order_id'             => $groupId,
            'section'              => 'remaining_payment',
            'amount'               => 10.98,
            'reason_of_payment'    => 'Test remaining payment',
            'email'                => 'test@example.com',
            'phone_number'         => '5555551234',
            'token'                => \Illuminate\Support\Str::random(64),
            'status'               => 'Unpaid',
            'override_status'      => 'Yes',
            'expired_at'           => Carbon::now()->addDays(30),
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
        ]);

        $item = TourBookingPaymentLinkItem::create([
            'payment_link_id'      => $link->id,
            'type'                 => 'custom_charge',
            'reference_id'         => $chargeId,
            'amount'               => 10.98,
            'status'               => 'Unpaid',
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'updated_by'           => 1,
            'updated_by_user_type' => 'Admin',
        ]);

        // The pending (unprocessed) history row storeCustomChargePendingLinkInHistory() wrote
        // when the payment link was originally sent.
        DB::table('tour_booking_history')->insert([
            'tour_booking_id'        => $builder->bookingId,
            'ticket_cost'            => 10.00,
            'platform_fees'          => 0.98,
            'payment_type'           => 'Pay Later',
            'payment_method'         => 'Payment Link',
            'operation'              => 'Payment',
            'charge_type'            => 'custom_charge',
            'is_processed'           => 0,
            'details'                => json_encode(['operation' => 'pending']),
            'operation_at'           => Carbon::now(),
            'operation_by'           => 1,
            'operation_by_user_type' => 'Admin',
            'created_at'             => Carbon::now(),
        ]);

        $request = new Request();
        $request->merge([
            'custom_charge_ids' => json_encode([$chargeId]),
            'refund_type'       => 'dont_refund',
            'payment_method'    => 'Pay Later',
        ]);

        $response = $this->repo->refundCustomCharge($request, $builder->bookingId);
        $this->assertEquals(200, $response->getStatusCode());

        $chargeRow = DB::table('tour_booking_custom_charges')->find($chargeId);
        $this->assertEquals('Cancelled', $chargeRow->status);

        $this->assertEquals('Cancelled', TourBookingPaymentLinkItem::find($item->id)->status);

        // The pending row must be gone (it no longer reflects a still-open charge)...
        $pendingRow = DB::table('tour_booking_history')
            ->where('tour_booking_id', $builder->bookingId)
            ->where('charge_type', 'custom_charge')
            ->where('is_processed', 0)
            ->first();
        $this->assertNull($pendingRow, 'The stale pending entry must be removed');

        // ...but a "Don't Refund" record must exist in its place, with a $0 amount since this
        // charge was never actually collected from the customer.
        $refundRow = DB::table('tour_booking_history')
            ->where('tour_booking_id', $builder->bookingId)
            ->where('charge_type', 'custom_charge')
            ->where('operation', 'Refund')
            ->first();
        $this->assertNotNull($refundRow, 'A history row for the "Don\'t Refund" decision must be written, not silently dropped');
        $this->assertEquals(0, (float) $refundRow->ticket_cost);
        $this->assertEquals(0, (float) $refundRow->platform_fees);
        $this->assertEquals("Don't Refund", $refundRow->payment_method);

        DB::table('tour_booking_custom_charges')->where('id', $chargeId)->delete();
        DB::table('tour_booking_history')->where('tour_booking_id', $builder->bookingId)->delete();
        TourBookingPaymentLinkItem::where('payment_link_id', $link->id)->delete();
        $link->delete();
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
