<?php

namespace Tests\Unit\Bookings;

use App\Events\BookingNew;
use App\Models\Admin;
use App\Models\TourBooking;
use App\Models\TourBookingPaymentLink;
use App\Models\TourBookingPaymentLinkItem;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use App\Services\Booking\RemainingPayment\Handlers\ChangePeopleItemHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use App\Services\Booking\PaymentLinkService;
use Stripe\StripeObject;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * ChangePeopleItemHandler::finalize() records the item's fee on service_commission at payment
 * time. A booking created by splitting an unpaid pax off must therefore NOT already carry that
 * fee in service_commission before the link is paid — otherwise paying the link counts it twice.
 */
class SplitUnpaidPaxFeeNotDoubleCountedOnPaymentTest extends TestCase
{
    /** @test */
    public function paying_the_split_off_bookings_link_counts_its_fee_once(): void
    {
        Event::fake([BookingNew::class]);
        Queue::fake();

        $this->actingAs(Admin::first() ?? Admin::factory()->create(), 'admin');

        $builder = (new CreateABooking())
            ->withTourists(3)
            ->withRate(50.00)
            ->withTransactOverrides(['nat_fees_percentage' => 9.82])
            ->withPaymentMethod('Card')
            ->create();

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

        $newBookingId = null;
        try {
            $result = app(TourBookingRepository::class)->splitPeopleBooking($request);
            $newBookingId = (int) $result['bookingId'];
            $newBooking = TourBooking::find($newBookingId);

            $item = TourBookingPaymentLinkItem::whereIn('payment_link_id', function ($q) use ($newBooking) {
                $q->select('id')->from('tour_booking_payment_links')
                    ->where('order_id', $newBooking->group_id)
                    ->where('section', 'remaining_payment');
            })->where('type', 'change_people')->first();
            $this->assertNotNull($item, 'The split must leave a pending change_people item on the new booking');

            $fee = (float) ($item->details['platform_fees'] ?? 0);
            $this->assertGreaterThan(0, $fee, 'Fixture must produce a fee for this test to mean anything');

            $paymentIntent = StripeObject::constructFrom([
                'id'      => 'pi_test_' . uniqid(),
                'status'  => 'succeeded',
                'charges' => ['data' => [[
                    'application_fee'        => null,
                    'payment_method_details' => ['card' => ['last4' => '4242', 'brand' => 'visa']],
                ]]],
            ]);
            ChangePeopleItemHandler::finalize($item, $newBookingId, $paymentIntent, 'Card');

            $this->assertEqualsWithDelta(
                $fee,
                (float) TourBooking::find($newBookingId)->service_commission,
                0.001,
                'The fee must be on service_commission exactly once after the link is paid'
            );
        } finally {
            if ($newBookingId) {
                $groupId = TourBooking::find($newBookingId)?->group_id;
                $linkIds = DB::table('tour_booking_payment_links')->where('order_id', $groupId)->pluck('id');
                DB::table('tour_booking_payment_link_items')->whereIn('payment_link_id', $linkIds)->delete();
                DB::table('tour_booking_payment_links')->where('order_id', $groupId)->delete();
                DB::table('transactions')->where('tour_booking_id', $newBookingId)->delete();
                DB::table('tour_booking_history')->where('tour_booking_id', $newBookingId)->delete();
                DB::table('tour_bookings_transact')->where('tour_booking_id', $newBookingId)->delete();
                DB::table('tour_bookings_master')->where('id', $newBookingId)->delete();
            }
            $builder->cleanup();
        }
    }
    /** @test */
    public function cancelling_a_fee_deferred_item_does_not_subtract_a_fee_that_was_never_added(): void
    {
        Queue::fake();
        $builder = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();
        $groupId = (string) $builder->bookingId;
        DB::table('tour_bookings_master')->where('id', $builder->bookingId)->update(['group_id' => $groupId, 'service_commission' => 5.00]);

        $link = TourBookingPaymentLink::create([
            'order_id' => $groupId, 'section' => 'remaining_payment', 'amount' => 80.50,
            'reason_of_payment' => 'Additional payment for your booking', 'email' => 'test@example.com',
            'phone_number' => '5555551234', 'token' => Str::random(64), 'status' => 'Unpaid',
            'override_status' => 'Yes', 'expired_at' => now()->addDays(30), 'created_by' => 1, 'created_by_user_type' => 'Admin',
        ]);
        $item = TourBookingPaymentLinkItem::create([
            'payment_link_id' => $link->id, 'type' => 'change_people', 'reference_id' => null, 'amount' => 80.50,
            'status' => 'Unpaid',
            'details' => json_encode([
                'tour_booking_id' => $builder->bookingId, 'ticket_cost' => 73.30, 'platform_fees' => 7.20,
                'fee_deferred' => true, 'added_transact_data' => [],
            ]),
            'created_by' => 1, 'created_by_user_type' => 'Admin', 'updated_by' => 1, 'updated_by_user_type' => 'Admin',
        ]);

        try {
            PaymentLinkService::cancelPendingChangePeopleItems((string) $item->id, null);

            $this->assertEqualsWithDelta(
                5.00,
                (float) DB::table('tour_bookings_master')->where('id', $builder->bookingId)->value('service_commission'),
                0.001,
                'The deferred fee was never on service_commission, so cancelling must leave it alone'
            );
        } finally {
            TourBookingPaymentLinkItem::where('payment_link_id', $link->id)->delete();
            $link->delete();
            $builder->cleanup();
        }
    }
}
