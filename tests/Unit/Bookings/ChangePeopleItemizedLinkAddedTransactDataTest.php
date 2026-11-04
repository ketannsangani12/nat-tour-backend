<?php

namespace Tests\Unit\Bookings;

use App\Models\Admin;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression (real-world report, booking #KEN-41966): an UNPAID booking created by Split Booking
 * has only an itemized remaining_payment link. Adding a pax via Change People appended a
 * change_people link item with an EMPTY added_transact_data, so the booking page couldn't show
 * the added pax as "Unpaid" (and later removal couldn't match the pending item). It must carry
 * the transact row the pax landed on, same as the main Payment Link path.
 */
class ChangePeopleItemizedLinkAddedTransactDataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Mail::fake();
        $this->actingAs(Admin::first() ?? Admin::factory()->create(), 'admin');
    }

    /** @test */
    public function pax_added_to_an_unpaid_itemized_link_booking_records_its_transact_row(): void
    {
        $builder = (new CreateABooking())
            ->withTourists(1)->withRate(55.00)->withPermitFee(15.00)->withoutTransaction()
            ->withTransactOverrides(['nat_fees_percentage' => 9.82])
            ->create();
        $bookingId = $builder->bookingId;
        $groupId   = (string) $bookingId;
        DB::table('tour_bookings_master')->where('id', $bookingId)->update(['group_id' => $groupId]);

        // The Split Booking's own pending, itemized link (no lump-sum link at all).
        $linkId = DB::table('tour_booking_payment_links')->insertGetId([
            'order_id' => $groupId, 'section' => 'remaining_payment', 'amount' => 80.49, 'status' => 'Unpaid',
            'reason_of_payment' => 'Payment for your booking', 'email' => 'test@example.com', 'phone_number' => '5555551234',
            'token' => Str::random(64), 'override_status' => 'Yes', 'expired_at' => now()->addDays(30),
            'created_by' => 1, 'created_by_user_type' => 'Admin', 'created_at' => now(),
        ]);
        DB::table('tour_booking_payment_link_items')->insert([
            'payment_link_id' => $linkId, 'type' => 'change_people', 'reference_id' => null, 'amount' => 80.49, 'status' => 'Unpaid',
            'details' => json_encode(['tour_booking_id' => $bookingId, 'tourists' => 1, 'ticket_cost' => 73.30, 'platform_fees' => 7.19, 'added_transact_data' => []]),
            'created_by' => 1, 'created_by_user_type' => 'Admin', 'updated_by' => 1, 'updated_by_user_type' => 'Admin',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $request = new Request();
        $request->merge([
            'bookingID' => $bookingId, 'slot_time' => $builder->slotTimeId,
            'tourists' => [2], 'rate_group' => [$builder->rateId], 'permit_fee' => [0], 'total_tourist' => 2,
            'discount_percentage' => 0, 'discount_fixed_money' => 0, 'payment_method' => 'Pay Later',
            'infantorchilds' => 0, 'payment_amount' => 73.30, 'refund_amount' => 0, 'validation_check' => 'false',
        ]);

        try {
            app(TourBookingRepository::class)->changePeopleBooking($request);

            $items = DB::table('tour_booking_payment_link_items as i')
                ->join('tour_booking_payment_links as l', 'l.id', '=', 'i.payment_link_id')
                ->where('l.order_id', $groupId)->where('i.type', 'change_people')->where('i.status', 'Unpaid')
                ->orderByDesc('i.id')->pluck('i.details');
            $this->assertGreaterThanOrEqual(2, $items->count(), 'The added pax must be appended to the itemized link');

            $details = json_decode($items->first(), true);
            $this->assertNotEmpty($details['added_transact_data'], 'added_transact_data must not be empty');
            $transactId = (int) DB::table('tour_bookings_transact')->where('tour_booking_id', $bookingId)
                ->where('tour_package_rate_id', $builder->rateId)->where('deleted_by', 0)->value('id');
            $this->assertSame($transactId, (int) $details['added_transact_data'][0]['transact_id']);
            $this->assertSame(1, (int) $details['added_transact_data'][0]['added_count']);
        } finally {
            DB::table('tour_booking_payment_link_items')->whereIn('payment_link_id',
                DB::table('tour_booking_payment_links')->where('order_id', $groupId)->pluck('id'))->delete();
            DB::table('tour_booking_payment_links')->where('order_id', $groupId)->delete();
            $builder->cleanup();
        }
    }
}
