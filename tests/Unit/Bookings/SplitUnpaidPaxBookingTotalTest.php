<?php

namespace Tests\Unit\Bookings;

use App\Events\BookingNew;
use App\Models\Admin;
use App\Models\TourBooking;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression (real-world report): splitting an unpaid pax (still pending on a remaining_payment
 * link, never actually charged) off to a new booking decremented BOTH `total` and `booking_total`
 * on the old booking's tour_bookings_master row. Only `total` (the payable-today figure) should
 * shrink — `booking_total` is the gross-lifetime total and, per splitUnpaidPaxBooking()'s own
 * docblock ("this never touches booking_total on the old booking"), must stay untouched here since
 * nothing was ever actually collected for the pax being split off. The code had drifted out of sync
 * with its own documented contract.
 */
class SplitUnpaidPaxBookingTotalTest extends TestCase
{
    /** @test */
    public function splitting_an_unpaid_pax_does_not_decrement_the_old_bookings_booking_total(): void
    {
        Event::fake([BookingNew::class]);
        Queue::fake();

        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $builder = (new CreateABooking())
            ->withTourists(3)
            ->withRate(50.00)
            ->withPaymentMethod('Card')
            ->create();

        $before = TourBooking::find($builder->bookingId);
        $originalBookingTotal = (float) $before->booking_total;
        $originalTotal = (float) $before->total;

        $request = new Request();
        $request->merge([
            'bookingID'                 => $builder->bookingId,
            'check'                     => ['0.0' => $builder->rateId],
            'permitF'                   => [],
            'split_is_all_unpaid'       => '1',
            'split_pending_item_ids'    => '',
            'split_unpaid_check_counts' => '',
            'tourits_numbers'           => '',
        ]);

        try {
            $repo = app(TourBookingRepository::class);
            $result = $repo->splitPeopleBooking($request);
            $newBookingId = (int) $result['bookingId'];

            $after = TourBooking::find($builder->bookingId);
            $this->assertEquals($originalBookingTotal, (float) $after->booking_total,
                'booking_total must stay at its original gross value — this pax was never actually charged');
            $this->assertLessThan($originalTotal, (float) $after->total,
                'total must still shrink to reflect only the remaining, payable-today pax');

            DB::table('tour_booking_payment_link_items')
                ->whereIn('payment_link_id', function ($q) use ($newBookingId) {
                    $q->select('id')->from('tour_booking_payment_links')
                        ->where('order_id', TourBooking::find($newBookingId)->group_id)
                        ->where('section', 'remaining_payment');
                })->delete();
            DB::table('tour_booking_payment_links')
                ->where('order_id', TourBooking::find($newBookingId)->group_id)
                ->where('section', 'remaining_payment')->delete();
            DB::table('tour_bookings_transact')->where('tour_booking_id', $newBookingId)->delete();
            DB::table('tour_booking_history')->where('tour_booking_id', $newBookingId)->delete();
            DB::table('tour_bookings_master')->where('id', $newBookingId)->delete();
        } finally {
            $builder->cleanup();
        }
    }
}
