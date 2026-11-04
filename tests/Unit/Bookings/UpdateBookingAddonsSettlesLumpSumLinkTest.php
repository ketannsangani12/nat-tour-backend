<?php

namespace Tests\Unit\Bookings;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\AddonField;
use App\Models\AddonForm;
use App\Repositories\TourBooking\TourBookingAddonRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: after StripeService::stripeCreatePaymentIntentForAddonEditCharge() folds a
 * coexisting lump-sum payment link's amount into the Card PaymentIntent (see
 * AddonEditChargeCardFoldsLumpSumLinkTest for that half of the fix), updateBookingAddons() must
 * settle that lump-sum portion too: retire the link AND record a real Completed `transactions`
 * row for its own amount -- separate from the addon's own TourBookingAddonTransaction row --
 * so the booking's own $trans-driven Payment Total/badge picks it up. Without this, the customer
 * was correctly charged in full by Stripe, but the booking page kept showing the original amount
 * as still owed and the link as still "Unpaid" (payable again via its original emailed link).
 *
 * CMD: php artisan test --filter=UpdateBookingAddonsSettlesLumpSumLinkTest
 */
class UpdateBookingAddonsSettlesLumpSumLinkTest extends TestCase
{
    protected static array $builders = [];

    public function test_a_card_charge_that_covers_a_lump_sum_link_settles_it_with_a_real_transaction(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();
        static::$builders[] = $builder;

        $groupId = (string) $builder->bookingId;
        DB::table('tour_bookings_master')->where('id', $builder->bookingId)->update(['group_id' => $groupId]);
        DB::table('tour_bookings_transact')->where('tour_booking_id', $builder->bookingId)->where('deleted_by', 0)
            ->update(['nat_fees_percentage' => 10.00]);

        $packageId = (int) DB::table('tour_bookings_master')->where('id', $builder->bookingId)->value('tour_package_id');
        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name' => 'Settle Lump Sum Form ' . uniqid(),
            'status' => 'Active',
        ]);
        $fieldDef = AddonField::create([
            'form_id' => $form->id,
            'label' => 'Photography Package',
            'type' => 'checkbox',
            'unit_type' => 'one_time',
            'additional_fee' => true,
            'price' => 20.00,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);

        $lumpSumLinkId = DB::table('tour_booking_payment_links')->insertGetId([
            'order_id'             => $groupId,
            'section'              => null,
            'amount'               => 146.60,
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
            $pi = 'pi_settle_lump_sum_' . uniqid();
            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->method('retrievePayment')->willReturn((object) [
                'id' => $pi,
                'charges' => (object) ['data' => [(object) ['application_fee' => 'fee_test_123', 'transfer' => null]]],
            ]);
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            $repo = app(TourBookingAddonRepository::class);
            $repo->updateBookingAddons((object) [
                'booking_id' => $builder->bookingId,
                'form_id' => $form->id,
                'added' => [
                    [
                        'field_id' => $fieldDef->id, 'option_id' => null, 'option_label' => null,
                        'value' => null, 'unit_type' => 'one_time', 'unit' => 1,
                        'rule_id' => null, 'applied_rule_snapshot' => null,
                    ],
                ],
                'updated' => [],
                'deleted' => [],
                'financial' => [
                    'action' => 'charge',
                    'payment_method' => 'Card',
                    'payment_intent_id' => $pi,
                    'net_amount' => 20.00,
                    'to_charge' => [
                        [
                            'field_id' => $fieldDef->id, 'label' => 'Photography Package',
                            'charge_amount' => 20.00, 'field_price' => 20.00, 'option_price' => 0,
                            'field_additional_fee' => true, 'option_additional_fee' => false,
                            'reason' => 'added',
                        ],
                    ],
                    'to_refund' => [],
                ],
            ]);

            $this->assertEquals(
                'Paid',
                DB::table('tour_booking_payment_links')->find($lumpSumLinkId)->status,
                'The lump-sum link must be retired once its amount has been settled via this same PI'
            );

            // The addon's own portion is recorded separately via storeAddonsPaymentInTransaction()
            // with charge_type='addons' (processAddonFinancialTransactions() -- unrelated to this
            // fix). $trans itself is always looked up with whereNull('charge_type') (see e.g.
            // TourBookingRepository.php:5724/11862), so the base-booking settlement row this fix
            // adds must be queried the same way to distinguish it from that addon-only row --
            // both legitimately share the same transaction_number (the one combined PI).
            $settlementTxn = DB::table('transactions')
                ->where('tour_booking_id', $builder->bookingId)
                ->where('transaction_number', $pi)
                ->where('status', 'Completed')
                ->whereNull('charge_type')
                ->first();
            $this->assertNotNull($settlementTxn, 'A real Completed base-booking transaction must exist for the lump-sum portion');
            $this->assertEqualsWithDelta(146.60, (float) $settlementTxn->amount, 0.01);
            $this->assertEquals('Card', $settlementTxn->via);
            $this->assertEquals('fee_test_123', $settlementTxn->application_fee_id);
            // Backed out from the fee-inclusive 146.60 at the booking's own 10% rate:
            // 146.60 - (146.60 / 1.10) = 13.33 -- never a hardcoded 0.
            $this->assertEqualsWithDelta(13.33, (float) $settlementTxn->fee_amount, 0.01);

            // A "Payment" History & Notes entry must exist for the tour amount too, not just the
            // add-ons' own entry.
            $historyRow = DB::table('tour_booking_history')
                ->where('tour_booking_id', $builder->bookingId)
                ->where('operation', 'Payment')
                ->orderByDesc('id')
                ->first();
            $this->assertNotNull($historyRow, 'A Payment history entry must exist for the settled tour amount');
            $historyDetails = json_decode($historyRow->details, true);
            $this->assertEqualsWithDelta(146.60, (float) $historyDetails['amount'], 0.01);
            $this->assertEqualsWithDelta(13.33, (float) $historyDetails['fees'], 0.01);
            // The "Payment" history partial (_payment.blade.php) reads ticket_cost/platform_fees
            // as their OWN row columns, not from the details JSON -- must not be left at their 0
            // column defaults the way CardBookingService::run() would populate them for a fresh
            // booking's own Card payment.
            $this->assertEqualsWithDelta(133.27, (float) $historyRow->ticket_cost, 0.01);
            $this->assertEqualsWithDelta(13.33, (float) $historyRow->platform_fees, 0.01);
            $this->assertEquals('Card', $historyRow->payment_type);
            $this->assertEquals(1, $historyRow->is_processed);

            // The "Booking Fees:" summary line reads tour_bookings_master.service_commission
            // separately from everything else settled above -- must be incremented too, or it
            // keeps showing only the add-ons' own fee. total/booking_total must take the
            // fee-INCLUSIVE tour amount (146.60, matching the lump-sum link's own amount), not
            // the fee-exclusive value left over from booking creation.
            $masterRow = DB::table('tour_bookings_master')->where('id', $builder->bookingId)->first();
            $this->assertEqualsWithDelta(13.33, (float) $masterRow->service_commission, 0.01);
            $this->assertEqualsWithDelta(146.60, (float) $masterRow->total, 0.01);
            $this->assertEqualsWithDelta(146.60, (float) $masterRow->booking_total, 0.01);

            // The addon's own $22 (20 + 10% fee) must still be recorded, untouched, as its own
            // charge_type='addons' row -- confirming no double-counting/overwriting happened.
            $addonTxn = DB::table('transactions')
                ->where('tour_booking_id', $builder->bookingId)
                ->where('charge_type', 'addons')
                ->where('status', 'Completed')
                ->first();
            $this->assertNotNull($addonTxn);
            $this->assertEqualsWithDelta(22.00, (float) $addonTxn->amount, 0.01);

            // $trans itself (whereNull('charge_type')) must now resolve to the settlement row.
            $trans = DB::table('transactions')
                ->where('tour_booking_id', $builder->bookingId)
                ->where('status', 'Completed')
                ->whereNull('charge_type')
                ->first();
            $this->assertNotNull($trans, '$trans must no longer be null for this booking');
            $this->assertEqualsWithDelta(146.60, (float) $trans->amount, 0.01);
        } finally {
            DB::table('tour_booking_payment_links')->where('id', $lumpSumLinkId)->delete();
            DB::table('transactions')->where('tour_booking_id', $builder->bookingId)->delete();
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
