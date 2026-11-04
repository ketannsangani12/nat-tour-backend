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
 * Regression: TourBookingAddonRepository::updatePaxAddons() -- the "Add Passenger Add-ons" /
 * per-guest ("Guest(s) #1 [+]") popup's own entry point, a SEPARATE method from
 * updateBookingAddons() that duplicates the same combined-PI finalisation logic rather than
 * sharing it -- had the same lump-sum-link gap fixed there (see
 * UpdateBookingAddonsSettlesLumpSumLinkTest) but was never itself patched. A live repro on
 * tenant kens booking #348680 confirmed it: Stripe correctly charged the combined total (the
 * StripeService fix), but this method only finalized an ITEMIZED pending link -- for a booking
 * whose only pending balance is the original lump-sum link, it silently did nothing, leaving
 * that link "Unpaid" for syncPayLaterAddonsPaymentLink() to independently (and incorrectly,
 * since it doesn't know this add-on was already paid via Card) regenerate afterward, and no
 * transactions row was ever created for the original portion.
 *
 * CMD: php artisan test --filter=UpdatePaxAddonsSettlesLumpSumLinkTest
 */
class UpdatePaxAddonsSettlesLumpSumLinkTest extends TestCase
{
    protected static array $builders = [];

    public function test_a_card_charge_via_the_pax_addons_popup_settles_a_coexisting_lump_sum_link(): void
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
            'name' => 'Settle Lump Sum Pax Form ' . uniqid(),
            'status' => 'Active',
        ]);
        $fieldDef = AddonField::create([
            'form_id' => $form->id,
            'label' => 'Photography Package',
            'type' => 'checkbox',
            'unit_type' => 'price_per_pax',
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
            $pi = 'pi_settle_lump_sum_pax_' . uniqid();
            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->method('retrievePayment')->willReturn((object) [
                'id' => $pi,
                'charges' => (object) ['data' => [(object) ['application_fee' => 'fee_test_pax_123', 'transfer' => null]]],
            ]);
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            $repo = app(TourBookingAddonRepository::class);
            $repo->updatePaxAddons((object) [
                'booking_id' => $builder->bookingId,
                'form_id' => $form->id,
                'pax' => [
                    [
                        'pax_id' => $builder->transactId,
                        'pax_index' => 'settle-lump-sum-adult-1',
                        'person_ordinal' => 0,
                        'added' => [
                            [
                                'field_id' => $fieldDef->id, 'option_id' => null, 'option_label' => null,
                                'value' => null, 'unit_type' => 'price_per_pax', 'unit' => 1,
                                'rule_id' => null, 'applied_rule_snapshot' => null,
                            ],
                        ],
                        'updated' => [],
                        'deleted' => [],
                    ],
                ],
                'financial' => [
                    'action' => 'charge',
                    'payment_method' => 'Card',
                    'payment_intent_id' => $pi,
                    'net_amount' => 20.00,
                    'total_charge' => 20.00,
                    'total_refund' => 0,
                    'to_charge' => [
                        [
                            'field_id' => $fieldDef->id, 'pax_index' => 'settle-lump-sum-adult-1',
                            'label' => 'Photography Package', 'option_id' => null, 'unit' => 1,
                            'new_price' => 20.00, 'old_db_id' => null, 'old_paid' => 0,
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

            $settlementTxn = DB::table('transactions')
                ->where('tour_booking_id', $builder->bookingId)
                ->where('transaction_number', $pi)
                ->where('status', 'Completed')
                ->whereNull('charge_type')
                ->first();
            $this->assertNotNull($settlementTxn, 'A real Completed base-booking transaction must exist for the lump-sum portion');
            $this->assertEqualsWithDelta(146.60, (float) $settlementTxn->amount, 0.01);
            $this->assertEquals('Card', $settlementTxn->via);
            $this->assertEquals('fee_test_pax_123', $settlementTxn->application_fee_id);
            // Backed out from the fee-inclusive 146.60 at the booking's own 10% rate:
            // 146.60 - (146.60 / 1.10) = 13.33 -- never a hardcoded 0.
            $this->assertEqualsWithDelta(13.33, (float) $settlementTxn->fee_amount, 0.01);

            $historyRow = DB::table('tour_booking_history')
                ->where('tour_booking_id', $builder->bookingId)
                ->where('operation', 'Payment')
                ->orderByDesc('id')
                ->first();
            $this->assertNotNull($historyRow, 'A Payment history entry must exist for the settled tour amount');
            $historyDetails = json_decode($historyRow->details, true);
            $this->assertEqualsWithDelta(146.60, (float) $historyDetails['amount'], 0.01);
            $this->assertEqualsWithDelta(13.33, (float) $historyDetails['fees'], 0.01);
            $this->assertEqualsWithDelta(133.27, (float) $historyRow->ticket_cost, 0.01);
            $this->assertEqualsWithDelta(13.33, (float) $historyRow->platform_fees, 0.01);
            $this->assertEquals('Card', $historyRow->payment_type);
            $this->assertEquals(1, $historyRow->is_processed);

            $masterRow = DB::table('tour_bookings_master')->where('id', $builder->bookingId)->first();
            $this->assertEqualsWithDelta(13.33, (float) $masterRow->service_commission, 0.01);
            $this->assertEqualsWithDelta(146.60, (float) $masterRow->total, 0.01);
            $this->assertEqualsWithDelta(146.60, (float) $masterRow->booking_total, 0.01);

            // $trans itself (whereNull('charge_type')) must now resolve to the settlement row --
            // not left null the way it was in the live repro.
            $trans = DB::table('transactions')
                ->where('tour_booking_id', $builder->bookingId)
                ->where('status', 'Completed')
                ->whereNull('charge_type')
                ->first();
            $this->assertNotNull($trans, '$trans must no longer be null for this booking');
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
