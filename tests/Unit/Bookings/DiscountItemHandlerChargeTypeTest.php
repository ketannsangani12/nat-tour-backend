<?php

namespace Tests\Unit\Bookings;

use Tests\TestCase;
use Tests\Fixture\CreateABooking;
use App\Models\TourBookingPaymentLink;
use App\Models\TourBookingPaymentLinkItem;
use App\Services\Booking\BookingService;
use App\Services\Booking\RemainingPayment\Handlers\DiscountItemHandler;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * Regression: a discount payment collected via a finalized Payment Link item got
 * transactions.charge_type = NULL instead of 'discount' — DiscountItemHandler::finalize()
 * built its TransactionService::storePaymentInTransaction() $data array from
 * $bookedByStaffOverride alone, which never carried the 'from_edit_discount' key that
 * direct Card/Cash/Invoiced discount payments set (TourBookingRepository::applyDiscount()).
 * BookingService::calculateDiscountpayment() filters transactions.charge_type='discount', so
 * it silently undercounted any discount payment collected this way.
 *
 * Fix: DiscountItemHandler::finalize() now includes 'from_edit_discount' => 1 in that array.
 *
 * CMD: php artisan test --filter=DiscountItemHandlerChargeTypeTest
 */
class DiscountItemHandlerChargeTypeTest extends TestCase
{
    protected static array $builders = [];

    public function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function fakePaymentIntent(int $amountCents): object
    {
        return (object) [
            'id'      => 'pi_test_' . uniqid(),
            'amount'  => $amountCents,
            'charges' => (object) [
                'data' => [
                    (object) [
                        'application_fee'        => 'fee_test_' . uniqid(),
                        'payment_method_details' => [
                            'card' => ['last4' => '4242', 'brand' => 'visa'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_finalized_discount_payment_link_item_is_tagged_discount_and_counted(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->create();
        static::$builders[] = $builder;

        $groupId = (string) $builder->bookingId;
        DB::table('tour_bookings_master')->where('id', $builder->bookingId)->update(['group_id' => $groupId]);

        // The pending discount history row, exactly as editDiscountPaymentLink() creates it:
        // charge_type='discount', is_processed=0, amount embedded in details.amount (what
        // calculateDiscountpayment() sums).
        $historyId = DB::table('tour_booking_history')->insertGetId([
            'tour_booking_id'        => $builder->bookingId,
            'tourists'               => 1,
            'ticket_cost'            => 18.20,
            'platform_fees'          => 1.80,
            'operation'              => 'Payment',
            'charge_type'            => 'discount',
            'is_processed'           => 0,
            'payment_type'           => 'Payment Link',
            'payment_method'         => 'Full Payment',
            'details'                => json_encode(['amount' => 20.00]),
            'operation_at'           => Carbon::now(),
            'operation_by'           => 1,
            'operation_by_user_type' => 'Admin',
            'created_at'             => Carbon::now(),
            'updated_at'             => Carbon::now(),
        ]);

        $link = TourBookingPaymentLink::create([
            'order_id'             => $groupId,
            'section'              => 'remaining_payment',
            'amount'               => 20.00,
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
            'type'                 => 'discount',
            'reference_id'         => $historyId,
            'amount'               => 20.00,
            'status'               => 'Unpaid',
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'updated_by'           => 1,
            'updated_by_user_type' => 'Admin',
        ]);

        try {
            DiscountItemHandler::finalize($item, $builder->bookingId, $this->fakePaymentIntent(2000));

            $transaction = DB::table('transactions')
                ->where('tour_booking_id', $builder->bookingId)
                ->where('charge_type', 'discount')
                ->first();
            $this->assertNotNull($transaction, 'A transaction row tagged charge_type=discount must exist');
            $this->assertEquals('Completed', $transaction->status);
            $this->assertEquals(20.00, (float) $transaction->amount);

            $this->assertEqualsWithDelta(
                20.00,
                (float) BookingService::calculateDiscountpayment($builder->bookingId),
                0.001
            );

            $historyRow = DB::table('tour_booking_history')->find($historyId);
            $this->assertEquals(1, (int) $historyRow->is_processed);
        } finally {
            DB::table('transactions')->where('tour_booking_id', $builder->bookingId)->delete();
            DB::table('tour_booking_history')->where('id', $historyId)->delete();
            TourBookingPaymentLinkItem::where('payment_link_id', $link->id)->delete();
            $link->delete();
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
