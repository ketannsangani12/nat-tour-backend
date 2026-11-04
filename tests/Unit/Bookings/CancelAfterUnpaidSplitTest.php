<?php

namespace Tests\Unit\Bookings;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\Admin;
use App\Models\TourBookingHistory;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression for LC-2147 (booking 272112, tenant_kens): a Pay Later booking had pax split off
 * into child bookings BEFORE it was paid. ExtendBookingPayLaterService writes a Refund history row
 * (details->from = 'Extend Booking', details->payment_type = '') carrying the moved pax's subtotal
 * for each split, although no money moved. The card payment taken afterwards was already sized for
 * the pax left on the booking, so when the cancel services summed every Refund row into $ref
 * (the amount "already refunded"), they subtracted the moved pax's cost a second time. The 100%
 * weather cancel in production refunded only the 18.76 platform fee and kept the 267.76 ticket cost.
 *
 * Paid splits (Card/Cash/Invoiced) must still count: their moved pax's share stays on the parent's
 * charge but belongs to the child booking.
 */
class CancelAfterUnpaidSplitTest extends TestCase
{
    private const RATE = 66.94;

    protected function authAsAdmin(): Admin
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    public function test_weather_100_percent_cancel_ignores_unpaid_split_rows(): void
    {
        // Booking 272112 after the split: 4 pax = 267.76 ticket + 18.76 fee, split rows 2 x 133.88.
        $captured = $this->runCancelAfterUnpaidSplit('100_refund', 4, 18.76, [133.88, 133.88]);

        $this->assertNotNull($captured['params'], 'Expected the cancellation to call refundPayment()');
        $this->assertEquals(
            28652,
            $captured['params']['amount'],
            'A 100% cancel must refund the whole charge (267.76 ticket + 18.76 fee = 286.52). The two '
            . 'unpaid split rows (2 x 133.88) predate the payment and are not refunds of it; counting them '
            . 'leaves only the fee (18.76) or nothing.'
        );
        $this->assertEquals(286.52, round((float) $captured['cancelledAmount'], 2));
    }

    public function test_ticket_refund_cancel_ignores_unpaid_split_rows(): void
    {
        $captured = $this->runCancelAfterUnpaidSplit('full_refund', 4, 18.76, [133.88, 133.88]);

        $this->assertNotNull($captured['params'], 'Expected the cancellation to call refundPayment()');
        $this->assertEquals(
            26776,
            $captured['params']['amount'],
            'A Ticket Cost cancel must refund the full ticket cost of the 4 paid pax (267.76), not zero.'
        );
    }

    /**
     * Booking 272091: 2 pax paid (133.88 + 9.38 = 143.26) after splits of 267.76 and 133.88. The old
     * code computed 143.26 - 401.64 = -258.38 and the cancel failed at Stripe for every refund type.
     */
    public function test_weather_cancel_when_split_rows_exceed_the_payment(): void
    {
        $captured = $this->runCancelAfterUnpaidSplit('100_refund', 2, 9.38, [267.76, 133.88]);

        $this->assertNotNull($captured['params'], 'Expected the cancellation to call refundPayment()');
        $this->assertEquals(14326, $captured['params']['amount']);
        $this->assertEquals(143.26, round((float) $captured['cancelledAmount'], 2));
    }

    public function test_ticket_refund_cancel_when_split_rows_exceed_the_payment(): void
    {
        $captured = $this->runCancelAfterUnpaidSplit('full_refund', 2, 9.38, [267.76, 133.88]);

        $this->assertNotNull($captured['params'], 'Expected the cancellation to call refundPayment()');
        $this->assertEquals(13388, $captured['params']['amount']);
    }

    public function test_unpaid_split_detection_only_matches_pay_later_split_rows(): void
    {
        $row = fn (array $details) => (object) ['details' => json_encode($details)];

        $this->assertTrue(TourBookingHistory::isUnpaidSplitReallocation(
            $row(['amount' => 133.88, 'payment_type' => '', 'from' => 'Extend Booking'])
        ));

        // Paid splits move money that is still sitting on the parent's charge; they must count.
        foreach (['Card', 'Cash', 'Invoiced'] as $via) {
            $this->assertFalse(TourBookingHistory::isUnpaidSplitReallocation(
                $row(['amount' => 133.88, 'payment_type' => $via, 'from' => 'Extend Booking'])
            ), "$via split rows must still count as refunded");
        }

        // An ordinary refund, a row with no payment_type at all, and an empty details blob.
        $this->assertFalse(TourBookingHistory::isUnpaidSplitReallocation($row(['amount' => 50, 'payment_type' => ''])));
        $this->assertFalse(TourBookingHistory::isUnpaidSplitReallocation($row(['amount' => 50, 'from' => 'Extend Booking'])));
        $this->assertFalse(TourBookingHistory::isUnpaidSplitReallocation((object) ['details' => null]));
    }

    /**
     * Rebuild a booking that was split while Pay Later (one unpaid split row per amount in
     * $splitAmounts, plus a 0.00 pax removal) and then paid by card for the pax left on it. Cancel
     * it with the given refund option and return what was sent to the gateway plus the Cancelled
     * history amount.
     */
    private function runCancelAfterUnpaidSplit(string $refundType, int $tourists, float $platformFee, array $splitAmounts): array
    {
        $this->authAsAdmin();

        $booking = (new CreateABooking())
            ->withTourists($tourists)
            ->withRate(self::RATE)
            ->withPaymentMethod('Card')
            ->create();
        $bookingId = $booking->bookingId;

        try {
            DB::table('tour_booking_history')
                ->where('transaction_id', $booking->transactionId)
                ->where('operation', 'Payment')
                ->update(['platform_fees' => $platformFee]);

            // The split and removal rows were written before the payment.
            $before = now()->subDay();
            foreach ($splitAmounts as $amount) {
                $this->insertRefundRow($bookingId, $tourists, $before, [
                    'amount' => $amount,
                    'created_by_with_name' => 'test',
                    'refund_type' => 'Full Refund minus Fees',
                    'payment_type' => '',
                    'deleted' => 'Adult person 2',
                    'fees' => 0,
                    'partner_fees' => 0,
                    'refund_partner_fees_keep_us' => 0,
                    'from' => 'Extend Booking',
                ]);
            }
            $this->insertRefundRow($bookingId, $tourists, $before, [
                'amount' => 0,
                'created_by_with_name' => 'test',
                'refund_type' => 'Full Refund minus Fees',
                'payment_type' => '',
                'deleted' => 'Adult person 2',
            ]);

            $captured = ['params' => null];
            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->method('refundPayment')
                ->willReturnCallback(function (array $params, string $mode) use (&$captured) {
                    $captured['params'] = $params;
                    return (object) ['id' => 're_test_' . uniqid()];
                });
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            $request = new Request();
            $request->merge([
                'cancel_booking' => 1,
                'refund_type' => $refundType,
                'comment' => 'cancel after unpaid split test',
                'api' => 1,
            ]);

            app(TourBookingRepository::class)->cancel($request, $bookingId);

            $cancelledRow = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('operation', 'Cancelled')
                ->latest('id')
                ->first();
            $captured['cancelledAmount'] = json_decode($cancelledRow->details, true)['amount'] ?? null;

            return $captured;
        } finally {
            $booking->cleanup();
        }
    }

    private function insertRefundRow(int $bookingId, int $tourists, $operationAt, array $details): void
    {
        DB::table('tour_booking_history')->insert([
            'tour_booking_id' => $bookingId,
            'tourists' => $tourists,
            'operation' => 'Refund',
            'ticket_cost' => $details['amount'],
            'platform_fees' => 0,
            'discount' => 0,
            'permit_fees' => 0,
            'processing_fees' => 0,
            'payment_type' => '',
            'payment_method' => 'Full Refund minus Fees',
            'charge_type' => null,
            'details' => json_encode($details),
            'operation_at' => $operationAt,
            'operation_by' => 1,
            'operation_by_user_type' => 'Admin',
            'created_at' => $operationAt,
        ]);
    }
}
