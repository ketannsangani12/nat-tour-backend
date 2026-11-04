<?php

namespace Tests\Unit\Bookings;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\AddonField;
use App\Models\AddonFieldOption;
use App\Models\AddonForm;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingAddonTransaction;
use App\Services\Booking\ChangePeopleAddonService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

class ChangePeopleAddonServiceTest extends TestCase
{
    protected int $tourBookingId = 999001;
    protected int $formId;
    protected static array $createdFormIds = [];
    // Only ever populated by the setUp() fallback below when no Active package exists at all —
    // that fallback used to create a full booking (via CreateABooking()) with NO cleanup path,
    // leaking a booking/transact/rate/slot row into the tenant DB every time it fired.
    protected static ?CreateABooking $fallbackPackageBuilder = null;

    protected function setUp(): void
    {
        parent::setUp();

        $activePackageId = DB::table('tour_packages')->where('status', 'Active')->value('id');
        if ($activePackageId) {
            $packageId = (int) $activePackageId;
        } else {
            static::$fallbackPackageBuilder ??= (new CreateABooking())->create();
            $packageId = static::$fallbackPackageBuilder->packageId;
        }

        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name' => 'Test Addon Form ' . uniqid(),
            'status' => 'Active',
        ]);
        $this->formId = $form->id;
        static::$createdFormIds[] = $form->id;
    }

    public static function tearDownAfterClass(): void
    {
        DB::table('tour_booking_addon_fields')->where('tour_booking_id', 999001)->delete();
        if (!empty(static::$createdFormIds)) {
            $fieldIds = DB::table('addon_fields')->whereIn('form_id', static::$createdFormIds)->pluck('id');
            DB::table('addon_field_options')->whereIn('field_id', $fieldIds)->delete();
            DB::table('addon_fields')->whereIn('form_id', static::$createdFormIds)->delete();
            DB::table('addon_forms')->whereIn('id', static::$createdFormIds)->delete();
        }
        static::$fallbackPackageBuilder?->cleanup();
        static::$fallbackPackageBuilder = null;
        parent::tearDownAfterClass();
    }

    protected function makeField(float $price, bool $additionalFee, string $unitType = 'price_per_pax'): AddonField
    {
        return AddonField::create([
            'form_id' => $this->formId,
            'label' => 'Field ' . uniqid(),
            'type' => 'dropdown',
            'unit_type' => $unitType,
            'additional_fee' => $additionalFee,
            'price' => $price,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);
    }

    /**
     * A real Card PI always has a matching `transactions` row in production (written by
     * TransactionService at charge time) — Payout Simplification's refund-mode lookup depends on
     * that row existing, so a synthetic addon PI needs one too. Rides along with the booking's
     * own cleanup (deletes by tour_booking_id).
     */
    protected function stubCardTransactionRow(int $bookingId, string $piId, float $amount): void
    {
        DB::table('transactions')->insert([
            'tour_booking_id' => $bookingId,
            'amount' => $amount,
            'transaction_number' => $piId,
            'via' => 'Card',
            'payment_type' => 2,
            'response_init' => '{}',
            'response_end' => '{}',
            'response_end_datetime' => now(),
            'status' => 'Completed',
            'charge_type' => 'addons',
            'created_by' => 0,
            'created_by_user_type' => 'Admin',
            'created_at' => now(),
        ]);
    }

    protected function makeOption(int $fieldId, float $price, bool $additionalFee): AddonFieldOption
    {
        return AddonFieldOption::create([
            'field_id' => $fieldId,
            'label' => 'Option ' . uniqid(),
            'value' => 'opt_' . uniqid(),
            'additional_fee' => $additionalFee,
            'price' => $price,
            'sort_order' => 0,
        ]);
    }

    /**
     * Regression for booking #63992: added one child with a $20 option-priced add-on at a
     * 9.82% commission rate. The Stripe PaymentIntent was created for $100.50 instead of the
     * correct $102.46 — a $1.96 shortfall that is EXACTLY this add-on's commission fee
     * ($20 * 9.82% = $1.964, rounds to $1.96). Root cause: the "people only added" fast path in
     * changePeopleBooking()/stripeCreatePaymentIntentForChangePeople() re-uses a precomputed
     * $serviceCommissionTotal instead of recomputing commission on the combined (tour + add-on)
     * total, so the add-on's own fee never made it into $serviceCommissionTotal. This test locks
     * down that calculatePricedAddonDelta() itself returns the correct fee — the surrounding
     * repository fix (folding net_fee into $serviceCommissionTotal) is verified by code review
     * since changePeopleBooking() has no existing test harness to safely exercise end-to-end.
     */
    public function test_addon_fee_matches_booking_63992_real_world_numbers(): void
    {
        $field = $this->makeField(0, false);
        $option = $this->makeOption($field->id, 20.00, true);

        $delta = ChangePeopleAddonService::calculatePricedAddonDelta(
            [['field_id' => $field->id, 'option_id' => $option->id, 'pax_index' => 'p-0']],
            [],
            [],
            9.82,
            true
        );

        $this->assertEquals(20.00, $delta['net_price']);
        $this->assertEquals(1.96, $delta['net_fee']);
    }

    /**
     * The client-side dropdown decorates its visible option text with the price for shopping
     * purposes (e.g. "Small (+$20.00)") and sends that decorated string as option_label — the
     * stored/history label must use the clean server-truth label instead ("Small"), matching the
     * one_time/price_per_unit add-ons convention.
     */
    public function test_label_uses_clean_server_truth_option_label_not_client_decorated_text(): void
    {
        $field = $this->makeField(0, false);
        $option = $this->makeOption($field->id, 20.00, true);
        $option->update(['label' => 'Small']);

        $delta = ChangePeopleAddonService::calculatePricedAddonDelta(
            [[
                'field_id' => $field->id,
                'option_id' => $option->id,
                'option_label' => 'Small (+$20.00)', // decorated client text — must be ignored
                'pax_index' => 'p-0',
            ]],
            [],
            [],
            10.0,
            true
        );

        $this->assertStringNotContainsString('+$20.00', $delta['chargeItems'][0]['label']);
        $this->assertStringContainsString('Small', $delta['chargeItems'][0]['label']);
    }

    /** Field-only pricing: option carries no price. */
    public function test_field_only_priced_addon_is_charged(): void
    {
        $field = $this->makeField(25.00, true);

        $delta = ChangePeopleAddonService::calculatePricedAddonDelta(
            [['field_id' => $field->id, 'option_id' => null, 'pax_index' => 'p-0']],
            [],
            [],
            10.0,
            true
        );

        $this->assertEquals(25.00, $delta['net_price']);
        $this->assertEquals(2.50, $delta['net_fee']);
        $this->assertCount(1, $delta['chargeItems']);
        $this->assertEquals(25.00, $delta['chargeItems'][0]['field_price']);
        $this->assertEquals(0.0, $delta['chargeItems'][0]['option_price']);
    }

    /** Option-only pricing: field itself carries no price. */
    public function test_option_only_priced_addon_is_charged(): void
    {
        $field = $this->makeField(0, false);
        $option = $this->makeOption($field->id, 20.00, true);

        $delta = ChangePeopleAddonService::calculatePricedAddonDelta(
            [['field_id' => $field->id, 'option_id' => $option->id, 'pax_index' => 'p-0']],
            [],
            [],
            10.0,
            true
        );

        $this->assertEquals(20.00, $delta['net_price']);
        $this->assertCount(1, $delta['chargeItems']);
        $this->assertEquals(0.0, $delta['chargeItems'][0]['field_price']);
        $this->assertEquals(20.00, $delta['chargeItems'][0]['option_price']);
    }

    /** Rare-but-supported case: both field AND option carry their own price. */
    public function test_field_and_option_both_priced_are_summed(): void
    {
        $field = $this->makeField(5.00, true);
        $option = $this->makeOption($field->id, 20.00, true);

        $delta = ChangePeopleAddonService::calculatePricedAddonDelta(
            [['field_id' => $field->id, 'option_id' => $option->id, 'pax_index' => 'p-0']],
            [],
            [],
            10.0,
            true
        );

        $this->assertEquals(25.00, $delta['net_price']);
        $this->assertEquals(2.50, $delta['net_fee']);
    }

    /** Non-priced add-ons must be skipped entirely — no charge item, no fee. */
    public function test_non_priced_addon_is_skipped_entirely(): void
    {
        $field = $this->makeField(0, false);

        $delta = ChangePeopleAddonService::calculatePricedAddonDelta(
            [['field_id' => $field->id, 'option_id' => null, 'pax_index' => 'p-0']],
            [],
            [],
            10.0,
            true
        );

        $this->assertEquals(0.0, $delta['net_price']);
        $this->assertEmpty($delta['chargeItems']);
        $this->assertEmpty($delta['refundItems']);
    }

    /** No commission fee should be computed when the payment method doesn't apply fees (e.g. plain Cash). */
    public function test_no_fee_when_fees_not_applicable(): void
    {
        $field = $this->makeField(25.00, true);

        $delta = ChangePeopleAddonService::calculatePricedAddonDelta(
            [['field_id' => $field->id, 'option_id' => null, 'pax_index' => 'p-0']],
            [],
            [],
            10.0,
            false
        );

        $this->assertEquals(25.00, $delta['net_price']);
        $this->assertEquals(0.0, $delta['net_fee']);
    }

    /** Removing an existing priced pax add-on nets a refund, not a charge. */
    public function test_removed_priced_addon_is_refunded(): void
    {
        $field = $this->makeField(30.00, true);
        $existing = TourBookingAddonField::create([
            'tour_booking_id' => $this->tourBookingId,
            'form_id' => $this->formId,
            'field_id' => $field->id,
            'field_label' => $field->label,
            'option_id' => null,
            'pax_index' => 'p-0',
            'unit_type' => 'price_per_pax',
            'unit' => 1,
            'operation_at' => now(),
            'operation_by' => 0,
            'operation_by_user_type' => 'Admin',
        ]);

        $delta = ChangePeopleAddonService::calculatePricedAddonDelta(
            [],
            [],
            [['db_id' => $existing->id]],
            10.0,
            true
        );

        $this->assertEquals(-30.00, $delta['net_price']);
        $this->assertEquals(-3.00, $delta['net_fee']);
        $this->assertCount(1, $delta['refundItems']);
        $this->assertEquals(30.00, $delta['refundItems'][0]['price']);
    }

    /**
     * Updating an existing pax's option selection should net to the PRICE DIFFERENCE,
     * not the full new price — e.g. adult changes option B ($20) -> option C ($22) nets +$2.
     */
    public function test_updated_addon_option_change_nets_the_difference(): void
    {
        $field = $this->makeField(0, false);
        $optionB = $this->makeOption($field->id, 20.00, true);
        $optionC = $this->makeOption($field->id, 22.00, true);

        $existing = TourBookingAddonField::create([
            'tour_booking_id' => $this->tourBookingId,
            'form_id' => $this->formId,
            'field_id' => $field->id,
            'field_label' => $field->label,
            'option_id' => $optionB->id,
            'option_label' => $optionB->label,
            'pax_index' => 'p-1',
            'unit_type' => 'price_per_pax',
            'unit' => 1,
            'operation_at' => now(),
            'operation_by' => 0,
            'operation_by_user_type' => 'Admin',
        ]);

        $delta = ChangePeopleAddonService::calculatePricedAddonDelta(
            [],
            [['db_id' => $existing->id, 'field_id' => $field->id, 'option_id' => $optionC->id, 'pax_index' => 'p-1']],
            [],
            10.0,
            true
        );

        $this->assertEquals(2.00, $delta['net_price']);
        $this->assertCount(1, $delta['chargeItems']);
        $this->assertEquals(2.00, $delta['chargeItems'][0]['price']);
        $this->assertEmpty($delta['refundItems']);
        // Upgrade must show "before → after", matching tour-booking-addons.js's
        // _buildAddonChangeLabel() convention used by the Passenger Add-ons popup — not just
        // the new option, which silently drops what it changed FROM.
        $this->assertEquals("{$field->label}: {$optionB->label} → {$optionC->label}", $delta['chargeItems'][0]['label']);
    }

    /**
     * Regression: downgrading a priced option (e.g. Sweater X-Large -> Large) via Change People
     * must record the same "Field: Old → New" history label the Passenger Add-ons popup already
     * records for the identical edit — previously this branch built the label from the NEW
     * option alone (e.g. "Sweater (Large)"), silently dropping the "changed FROM" half.
     */
    public function test_updated_addon_option_downgrade_label_shows_before_and_after(): void
    {
        $field = $this->makeField(0, false);
        $optionXLarge = $this->makeOption($field->id, 30.00, true);
        $optionLarge = $this->makeOption($field->id, 20.00, true);

        $existing = TourBookingAddonField::create([
            'tour_booking_id' => $this->tourBookingId,
            'form_id' => $this->formId,
            'field_id' => $field->id,
            'field_label' => $field->label,
            'option_id' => $optionXLarge->id,
            'option_label' => $optionXLarge->label,
            'pax_index' => 'p-1',
            'unit_type' => 'price_per_pax',
            'unit' => 1,
            'operation_at' => now(),
            'operation_by' => 0,
            'operation_by_user_type' => 'Admin',
        ]);

        $delta = ChangePeopleAddonService::calculatePricedAddonDelta(
            [],
            [['db_id' => $existing->id, 'field_id' => $field->id, 'option_id' => $optionLarge->id, 'pax_index' => 'p-1']],
            [],
            0.0,
            false
        );

        $this->assertCount(1, $delta['refundItems']);
        $this->assertEquals(
            "{$field->label}: {$optionXLarge->label} → {$optionLarge->label}",
            $delta['refundItems'][0]['label']
        );
    }

    /**
     * Two different people in the same rate group picking different priced options
     * (adult 1 -> $20, adult 2 -> $22) must be priced independently, not averaged/merged.
     */
    public function test_distinct_pax_priced_independently(): void
    {
        $field = $this->makeField(0, false);
        $optionB = $this->makeOption($field->id, 20.00, true);
        $optionC = $this->makeOption($field->id, 22.00, true);

        $delta = ChangePeopleAddonService::calculatePricedAddonDelta(
            [
                ['field_id' => $field->id, 'option_id' => $optionB->id, 'pax_index' => 'p-0'],
                ['field_id' => $field->id, 'option_id' => $optionC->id, 'pax_index' => 'p-1'],
            ],
            [],
            [],
            10.0,
            true
        );

        $this->assertEquals(42.00, $delta['net_price']);
        $this->assertCount(2, $delta['chargeItems']);
        $prices = collect($delta['chargeItems'])->pluck('price')->sort()->values()->all();
        $this->assertEquals([20.00, 22.00], $prices);
    }

    /** Add + remove in the same submit nets to a single combined amount. */
    public function test_add_and_remove_combined_nets_correctly(): void
    {
        $addedField = $this->makeField(20.00, true);
        $removedField = $this->makeField(35.00, true);
        $existingRemoved = TourBookingAddonField::create([
            'tour_booking_id' => $this->tourBookingId,
            'form_id' => $this->formId,
            'field_id' => $removedField->id,
            'field_label' => $removedField->label,
            'pax_index' => 'p-2',
            'unit_type' => 'price_per_pax',
            'unit' => 1,
            'operation_at' => now(),
            'operation_by' => 0,
            'operation_by_user_type' => 'Admin',
        ]);

        $delta = ChangePeopleAddonService::calculatePricedAddonDelta(
            [['field_id' => $addedField->id, 'option_id' => null, 'pax_index' => 'p-3']],
            [],
            [['db_id' => $existingRemoved->id]],
            10.0,
            true
        );

        $this->assertEquals(-15.00, $delta['net_price']); // 20 charged - 35 refunded
        $this->assertCount(1, $delta['chargeItems']);
        $this->assertCount(1, $delta['refundItems']);
    }

    /**
     * Regression for the "Update Booking" refund routing bug: removing an adult AND a child
     * whose priced add-ons were originally charged on TWO DIFFERENT PaymentIntents must refund
     * each add-on from its OWN original PaymentIntent — never from whichever single PaymentIntent
     * the admin happened to radio-select for the tour-side refund.
     */
    public function test_addon_refund_routes_to_each_items_own_origin_payment_intent(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        try {
        $adultField = $this->makeField(20.00, true);
        $childField = $this->makeField(15.00, true);

        $adultAddon = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId,
            'form_id' => $this->formId,
            'field_id' => $adultField->id,
            'field_label' => $adultField->label,
            'option_id' => null,
            'pax_index' => 'p-0',
            'unit_type' => 'price_per_pax',
            'unit' => 1,
            'operation_at' => now(),
            'operation_by' => 0,
            'operation_by_user_type' => 'Admin',
        ]);
        $childAddon = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId,
            'form_id' => $this->formId,
            'field_id' => $childField->id,
            'field_label' => $childField->label,
            'option_id' => null,
            'pax_index' => 'p-1',
            'unit_type' => 'price_per_pax',
            'unit' => 1,
            'operation_at' => now(),
            'operation_by' => 0,
            'operation_by_user_type' => 'Admin',
        ]);

        $adultPi = 'pi_test_adult_original';
        $childPi = 'pi_test_child_original';
        $adminSelectedPi = 'pi_test_admin_selected';

        $this->stubCardTransactionRow($bookingId, $adultPi, 22.00);
        $this->stubCardTransactionRow($bookingId, $childPi, 16.50);

        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId,
            'tour_booking_addon_id' => $adultAddon->id,
            'operation_group_id' => (string) Str::uuid(),
            'payment_method' => 'card',
            'status' => 'paid',
            'operation' => 'charge',
            'transaction_id' => $adultPi,
            'field_price' => 20.00,
            'field_fee' => 2.00,
            'option_price' => 0,
            'option_fee' => 0,
            'operation_at' => now(),
            'operation_by' => 0,
            'operation_by_user_type' => 'Admin',
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId,
            'tour_booking_addon_id' => $childAddon->id,
            'operation_group_id' => (string) Str::uuid(),
            'payment_method' => 'card',
            'status' => 'paid',
            'operation' => 'charge',
            'transaction_id' => $childPi,
            'field_price' => 15.00,
            'field_fee' => 1.50,
            'option_price' => 0,
            'option_fee' => 0,
            'operation_at' => now(),
            'operation_by' => 0,
            'operation_by_user_type' => 'Admin',
        ]);

        $refundedPaymentIntents = [];
        $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
        $fakeGateway->method('retrievePayment')->willReturnCallback(function ($id) {
            return (object) [
                'charges' => (object) [
                    'data' => [
                        (object) ['amount_captured' => 100000, 'amount_refunded' => 0, 'application_fee' => null],
                    ],
                ],
            ];
        });
        $fakeGateway->expects($this->exactly(2))
            ->method('refundPayment')
            ->willReturnCallback(function ($params) use (&$refundedPaymentIntents) {
                $refundedPaymentIntents[$params['payment_intent']] = $params['amount'];
                return (object) ['id' => 're_test_' . $params['payment_intent']];
            });
        $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

        ChangePeopleAddonService::settleFromDelta(
            [
                'chargeItems' => [],
                'refundItems' => [
                    ['db_id' => $adultAddon->id, 'label' => 'Adult shirt', 'price' => 20.00, 'fee' => 2.00, 'field_price' => 20.00, 'field_fee' => 2.00, 'option_price' => 0, 'option_fee' => 0],
                    ['db_id' => $childAddon->id, 'label' => 'Child shirt', 'price' => 15.00, 'fee' => 1.50, 'field_price' => 15.00, 'field_fee' => 1.50, 'option_price' => 0, 'option_fee' => 0],
                ],
            ],
            [
                'action' => 'refund',
                'payment_method' => 'card',
                'transaction_id' => $adminSelectedPi,
                'refund_type' => 'full_refund',
                'execute_stripe_refund' => true,
            ],
            $bookingId
        );

        $this->assertArrayHasKey($adultPi, $refundedPaymentIntents, 'Adult add-on must be refunded from its OWN original PaymentIntent');
        $this->assertArrayHasKey($childPi, $refundedPaymentIntents, 'Child add-on must be refunded from its OWN original PaymentIntent');
        $this->assertArrayNotHasKey($adminSelectedPi, $refundedPaymentIntents, 'Must NOT refund from the admin-selected PaymentIntent when it never charged these add-ons');
        $this->assertEquals(2200, $refundedPaymentIntents[$adultPi]); // $20 + $2 fee, in cents
        $this->assertEquals(1650, $refundedPaymentIntents[$childPi]); // $15 + $1.50 fee, in cents

        $refundRows = TourBookingAddonTransaction::where('tour_booking_id', $bookingId)
            ->where('operation', 'refund')
            ->get();
        $this->assertEquals($adultPi, $refundRows->firstWhere('tour_booking_addon_id', $adultAddon->id)->transaction_id);
        $this->assertEquals($childPi, $refundRows->firstWhere('tour_booking_addon_id', $childAddon->id)->transaction_id);

        // Regression: refund rows must be stored POSITIVE (status='refunded'/operation='refund'
        // already say this is a refund) — not negative, which is what settleAddonTransactions()
        // wrote before this was standardized to match the rest of the codebase's convention.
        $adultRefundRow = $refundRows->firstWhere('tour_booking_addon_id', $adultAddon->id);
        $childRefundRow = $refundRows->firstWhere('tour_booking_addon_id', $childAddon->id);
        $this->assertEquals(20.00, (float) $adultRefundRow->field_price);
        $this->assertEquals(2.00, (float) $adultRefundRow->field_fee);
        $this->assertEquals(15.00, (float) $childRefundRow->field_price);
        $this->assertEquals(1.50, (float) $childRefundRow->field_fee);
        } finally {
            DB::table('tour_booking_addon_transactions')->where('tour_booking_id', $bookingId)->delete();
            DB::table('tour_booking_addon_fields')->where('tour_booking_id', $bookingId)->delete();
            $booking->cleanup();
        }
    }

    /**
     * Regression for booking 65205: in the SAME bulk pax-addon edit, one pax's option was
     * downgraded ($22 -> $20, a price/fee DELTA refund) while a different, previously
     * addon-less pax had a new $20 add-on added. The combined net is a CHARGE (+$18 price,
     * ~+$1.76 fee), so this never goes through the admin-facing refund-type modal — the
     * financial payload never carries an explicit refund_type, and settleAddonTransactions()
     * defaulted to 'ticket_refund', which zeroed the downgrade's fee (option_fee stored as
     * 0.00 instead of the correct ~$0.20 delta). A downgrade's field_fee/option_fee is already
     * the correct delta computed by calculatePricedAddonDelta() — there is no admin "keep vs.
     * return the fee" choice being made for a mere value change, so it must always be stored,
     * regardless of $refundType (which only governs genuine full-item removals).
     */
    public function test_option_downgrade_fee_delta_is_stored_even_without_explicit_refund_type(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        $downgradeField = $this->makeField(0, false);
        $optionOld = $this->makeOption($downgradeField->id, 22.00, true);
        $optionNew = $this->makeOption($downgradeField->id, 20.00, true);

        $newAddonField = $this->makeField(0, false);
        $newAddonOption = $this->makeOption($newAddonField->id, 20.00, true);

        $downgradedAddon = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId,
            'form_id' => $this->formId,
            'field_id' => $downgradeField->id,
            'field_label' => $downgradeField->label,
            'option_id' => $optionOld->id,
            'option_label' => $optionOld->label,
            'pax_index' => 'p-0',
            'unit_type' => 'price_per_pax',
            'unit' => 1,
            'operation_at' => now(),
            'operation_by' => 0,
            'operation_by_user_type' => 'Admin',
        ]);
        $originalPi = 'pi_test_65205_original';
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId,
            'tour_booking_addon_id' => $downgradedAddon->id,
            'operation_group_id' => (string) Str::uuid(),
            'payment_method' => 'card',
            'status' => 'paid',
            'operation' => 'charge',
            'transaction_id' => $originalPi,
            'field_price' => 0,
            'field_fee' => 0,
            'option_price' => 22.00,
            'option_fee' => 2.16,
            'operation_at' => now(),
            'operation_by' => 0,
            'operation_by_user_type' => 'Admin',
        ]);

        $newDbId = 999501; // simulates the newly-created addon field row for the new pax's addon

        $delta = ChangePeopleAddonService::calculatePricedAddonDelta(
            [['field_id' => $newAddonField->id, 'option_id' => $newAddonOption->id, 'pax_index' => 'p-1']],
            [['db_id' => $downgradedAddon->id, 'field_id' => $downgradeField->id, 'option_id' => $optionNew->id, 'pax_index' => 'p-0']],
            [],
            9.82,
            true
        );

        $this->assertEquals(18.00, $delta['net_price']);
        $this->assertEquals(1.76, $delta['net_fee']);
        $this->assertCount(1, $delta['chargeItems']);
        $this->assertCount(1, $delta['refundItems']);
        $this->assertEqualsWithDelta(0.20, $delta['refundItems'][0]['option_fee'], 0.001, 'calculatePricedAddonDelta() must already compute the correct fee delta ($2.16 - $1.96)');

        $newPi = 'pi_test_65205_new';
        $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
        $fakeGateway->method('retrievePayment')->willReturn((object) [
            'id' => $newPi,
            'charges' => (object) ['data' => [(object) ['application_fee' => null, 'transfer' => null]]],
        ]);
        $fakeGateway->expects($this->never())->method('refundPayment');
        $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

        try {
            ChangePeopleAddonService::settleFromDelta(
                $delta,
                [
                    'action' => 'charge',
                    'payment_method' => 'card',
                    'transaction_id' => $newPi,
                    // No 'refund_type' key — matches the real bulk-edit payload, which never
                    // sends one when the net action is 'charge'. Backend defaults to 'ticket_refund'.
                ],
                $bookingId,
                [$newAddonField->id . '_p-1' => $newDbId]
            );

            $refundRow = TourBookingAddonTransaction::where('tour_booking_id', $bookingId)
                ->where('tour_booking_addon_id', $downgradedAddon->id)
                ->where('operation', 'refund')
                ->first();
            $this->assertNotNull($refundRow, 'Downgrade must still produce a refund row for the price delta');
            $this->assertEquals(2.00, (float) $refundRow->option_price);
            $this->assertEquals(0.20, (float) $refundRow->option_fee, 'option_fee must store the real fee delta, not 0');

            // The downgrade's $2.20 (price+fee) was never actually refunded via Stripe here (see
            // the never() expectation on refundPayment above) — it simply stays untouched on its
            // own original PI, genuinely still available to fund part of the new item. So the new
            // item's $21.96 is now correctly split across TWO charge rows: $2.20 drawn from that
            // untouched original PI, the remaining $19.76 from the brand-new PI — instead of the
            // old behavior of blindly tying the item's FULL value to only the new PI (which a
            // later 100% cancel refund would then try to over-refund from that same new PI).
            $chargeRows = TourBookingAddonTransaction::where('tour_booking_id', $bookingId)
                ->where('tour_booking_addon_id', $newDbId)
                ->where('operation', 'charge')
                ->get();
            $this->assertCount(2, $chargeRows);
            $this->assertEqualsWithDelta(20.00, (float) $chargeRows->sum('option_price'), 0.01);
            $this->assertEqualsWithDelta(1.96, (float) $chargeRows->sum('option_fee'), 0.01);

            $originalPiRow = $chargeRows->firstWhere('transaction_id', $originalPi);
            $this->assertNotNull($originalPiRow, 'Part of the new item must draw from the downgrade\'s own untouched original PI');
            $this->assertEqualsWithDelta(2.00, (float) $originalPiRow->option_price, 0.01);
            $this->assertEqualsWithDelta(0.20, (float) $originalPiRow->option_fee, 0.01);

            $newPiRow = $chargeRows->firstWhere('transaction_id', $newPi);
            $this->assertNotNull($newPiRow, 'The rest of the new item must draw from the brand-new PI actually confirmed for the net amount');
            $this->assertEqualsWithDelta(18.00, (float) $newPiRow->option_price, 0.01);
            $this->assertEqualsWithDelta(1.76, (float) $newPiRow->option_fee, 0.01);
        } finally {
            DB::table('tour_booking_addon_transactions')->where('tour_booking_id', $bookingId)->delete();
            DB::table('tour_booking_addon_fields')->where('tour_booking_id', $bookingId)->delete();
            DB::table('addon_field_options')->whereIn('id', [$optionOld->id, $optionNew->id, $newAddonOption->id])->delete();
            $booking->cleanup();
        }
    }

    /**
     * Regression: a Card booking where adding a NEW pax's identically-priced add-on alongside
     * a genuine full removal of an EXISTING pax's add-on (a "reassignment", not a downgrade)
     * combines into a net CHARGE — same shape as the previous test, but for a plain 'removed'
     * item rather than an 'updated'/downgrade one. changePeopleBooking()'s "charge" branches
     * (Card and Cash/Invoiced) never show the "Update Booking" refund-type modal for this kind
     * of edit — that modal is deletePeopleChangePeople()'s alone, for actual pax deletion — so
     * they must pass 'refund_type' => 'full_refund' explicitly, or settleAddonTransactions()
     * defaults to 'ticket_refund' and silently "keeps" the removed item's fee even though no
     * money beyond the tour-side charge was ever actually collected for it (the add-on's own
     * price/fee net to $0 against the paired addition on the SAME PaymentIntent). This locks
     * down that 'full_refund' actually refunds the fee too, achieving that true net-$0.
     */
    public function test_full_removal_paired_with_unrelated_addition_refunds_the_fee_with_full_refund(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        $photoField = $this->makeField(15.00, true);

        $removedAddon = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId,
            'form_id' => $this->formId,
            'field_id' => $photoField->id,
            'field_label' => $photoField->label,
            'option_id' => null,
            'pax_index' => 'reassign-existing-adult',
            'unit_type' => 'price_per_pax',
            'unit' => 1,
            'operation_at' => now(),
            'operation_by' => 0,
            'operation_by_user_type' => 'Admin',
        ]);
        $originalPi = 'pi_test_reassign_original';
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId,
            'tour_booking_addon_id' => $removedAddon->id,
            'operation_group_id' => (string) Str::uuid(),
            'payment_method' => 'card',
            'status' => 'paid',
            'operation' => 'charge',
            'transaction_id' => $originalPi,
            'field_price' => 15.00,
            'field_fee' => 1.47,
            'option_price' => 0,
            'option_fee' => 0,
            'operation_at' => now(),
            'operation_by' => 0,
            'operation_by_user_type' => 'Admin',
        ]);

        $newDbId = 999502; // simulates the new pax's freshly-created addon field row

        $delta = ChangePeopleAddonService::calculatePricedAddonDelta(
            [['field_id' => $photoField->id, 'option_id' => null, 'pax_index' => 'reassign-new-adult']],
            [],
            [['db_id' => $removedAddon->id]],
            9.8,
            true
        );

        $this->assertEquals(0.0, $delta['net_price'], 'Same field/price added and removed must net to $0 price');
        $this->assertEqualsWithDelta(0.0, $delta['net_fee'], 0.001, 'Same field/price added and removed must net to $0 fee');
        $this->assertCount(1, $delta['chargeItems']);
        $this->assertCount(1, $delta['refundItems']);

        $newPi = 'pi_test_reassign_new';
        $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
        $fakeGateway->method('retrievePayment')->willReturn((object) [
            'id' => $newPi,
            'charges' => (object) ['data' => [(object) ['application_fee' => null, 'transfer' => null]]],
        ]);
        // action='charge' never sets execute_stripe_refund, so no real Stripe refund call is
        // expected here either way — this is purely about what gets BOOKED into the ledger.
        $fakeGateway->expects($this->never())->method('refundPayment');
        $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

        try {
            ChangePeopleAddonService::settleFromDelta(
                $delta,
                [
                    'action' => 'charge',
                    'payment_method' => 'card',
                    'transaction_id' => $newPi,
                    'refund_type' => 'full_refund',
                ],
                $bookingId,
                [$photoField->id . '_reassign-new-adult' => $newDbId]
            );

            $refundRow = TourBookingAddonTransaction::where('tour_booking_id', $bookingId)
                ->where('tour_booking_addon_id', $removedAddon->id)
                ->where('operation', 'refund')
                ->first();
            $this->assertNotNull($refundRow, 'The removed item must still produce a refund row');
            $this->assertEquals(15.00, (float) $refundRow->field_price);
            $this->assertEquals(1.47, (float) $refundRow->field_fee, 'full_refund must refund the fee too, not keep it like ticket_refund would');

            $chargeRow = TourBookingAddonTransaction::where('tour_booking_id', $bookingId)
                ->where('tour_booking_addon_id', $newDbId)
                ->where('operation', 'charge')
                ->first();
            $this->assertNotNull($chargeRow);
            $this->assertEquals(15.00, (float) $chargeRow->field_price);
            $this->assertEquals(1.47, (float) $chargeRow->field_fee);

            // The whole reassignment must net to true $0 (charge price+fee == refund price+fee) —
            // exactly what the real Stripe PaymentIntent actually moved for the add-on side.
            $this->assertEquals(
                (float) $chargeRow->field_price + (float) $chargeRow->field_fee,
                (float) $refundRow->field_price + (float) $refundRow->field_fee
            );
        } finally {
            DB::table('tour_booking_addon_transactions')->where('tour_booking_id', $bookingId)->delete();
            DB::table('tour_booking_addon_fields')->where('tour_booking_id', $bookingId)->delete();
            $booking->cleanup();
        }
    }

    /**
     * Regression (booking #253461): changePeopleBooking()'s "delete-only" branch (no pax added,
     * at least one pax deleted) calls processAddons() to write a brand-new add-on's field row for
     * a SURVIVING pax (not the one being deleted) — e.g. deleting one Adult while also adding a
     * Photography Package to a surviving Children pax — then hands the same priced delta straight
     * to deletePeopleChangePeople(), which only ever settles refundItems, scoped per rate group to
     * the pax actually being deleted. A chargeItem for a different, non-deleted pax was never
     * looked at anywhere in that function, so the addition silently never produced a
     * TourBookingAddonTransaction charge row or an "Add-ons" Payment history entry — effectively
     * given away for free with zero audit trail, even though its tour_booking_addon_fields row DID
     * get written by processAddons(). Fixed by settling processAddons()'s own returned chargeItems
     * (via the SAME field_db_id_map it returns) with an explicit settleFromDelta(action=>'charge')
     * call in that branch, right after processAddons() runs and before deletePeopleChangePeople()
     * is ever called. This locks down that exact settlement, the same way
     * test_full_removal_paired_with_unrelated_addition_refunds_the_fee_with_full_refund() above
     * locks down the parallel Card/"charge" branch's own settleFromDelta() call.
     */
    public function test_addon_added_for_surviving_pax_is_charged_when_paired_with_a_pax_deletion(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Cash')->create();
        $bookingId = $booking->bookingId;

        try {
            $photoField = $this->makeField(15.00, true);

            // Simulates addPaxAddons()'s own return shape for a brand-new field row on an
            // EXISTING (surviving) pax — the "field_id_pax_index" => new tour_booking_addon_fields.id
            // map processAddons() bubbles up as 'field_db_id_map'.
            $newDbId = 999601;

            $delta = ChangePeopleAddonService::calculatePricedAddonDelta(
                [['field_id' => $photoField->id, 'option_id' => null, 'pax_index' => 'existing-child']],
                [],
                [],
                9.8,
                false // Cash without cash-fees enabled — matches the reported booking
            );

            $this->assertCount(1, $delta['chargeItems'], 'A brand-new addon selection must produce exactly one chargeItem');
            $this->assertEmpty($delta['refundItems'], 'This is a pure addition — nothing is being refunded here');

            // Mirrors the fix's own settleFromDelta() call exactly: action=>'charge', the
            // booking's real payment method, the existing Completed transaction's own
            // transaction_number, and the field_db_id_map processAddons() just returned.
            ChangePeopleAddonService::settleFromDelta(
                ['chargeItems' => $delta['chargeItems'], 'refundItems' => []],
                [
                    'action' => 'charge',
                    'payment_method' => 'Cash',
                    'transaction_id' => 'TEST-existing-cash-txn',
                ],
                $bookingId,
                [$photoField->id . '_existing-child' => $newDbId]
            );

            $chargeRow = TourBookingAddonTransaction::where('tour_booking_id', $bookingId)
                ->where('tour_booking_addon_id', $newDbId)
                ->where('operation', 'charge')
                ->first();

            $this->assertNotNull($chargeRow, 'The surviving pax\'s new add-on must produce a TourBookingAddonTransaction charge row');
            $this->assertEquals(15.00, (float) $chargeRow->field_price);
            $this->assertEquals('paid', $chargeRow->status, 'A Cash booking with a real completed transaction must settle as paid, not left open like true Pay Later');

            $historyRow = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('operation', 'Payment')
                ->orderByDesc('id')
                ->first();
            $this->assertNotNull($historyRow, 'The addition must leave a "Payment"/Add-ons entry in History & Notes, not be silently invisible');
            $this->assertStringContainsString($photoField->label, $historyRow->details);
        } finally {
            DB::table('tour_booking_addon_transactions')->where('tour_booking_id', $bookingId)->delete();
            DB::table('tour_booking_history')->where('tour_booking_id', $bookingId)->where('operation', 'Payment')->delete();
            $booking->cleanup();
        }
    }

    /**
     * Regression: EditBookingService::deletePaxFromGroupBooking() (Group-rate "Change People"
     * pax removal) never refunded a removed pax's priced add-on at all — processAddons() upstream
     * (V3\TourBookingRepository's "delete-only" branch) already hard-deletes the field row and
     * computes the correct $addonRefundDelta, but that delta was only ever passed to the
     * non-group sibling, deletePeopleChangePeople(). A "Weather / 100%" refund on a Group-rate
     * pax removal therefore kept the add-on's money charged with zero refund and zero audit
     * trail, even with the add-on explicitly checked in the "Delete Booking" sub-modal.
     *
     * This exercises the new shared entry point, refundRemovedPaxAddonsForRateGroup(), that
     * both the group and non-group flows can now call — matching a real Cash Group-rate booking
     * (single tour_bookings_transact row, `pax_id` on the add-on field = that row's id).
     */
    public function test_refund_removed_pax_addons_for_rate_group_refunds_matching_pax_items(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Cash')->create();
        $bookingId = $booking->bookingId;
        $rateGroupPaxId = $booking->transactId;

        try {
            $photoField = $this->makeField(15.00, true);

            $removedAddon = TourBookingAddonField::create([
                'tour_booking_id' => $bookingId,
                'form_id' => $this->formId,
                'field_id' => $photoField->id,
                'field_label' => $photoField->label,
                'option_id' => null,
                'pax_id' => $rateGroupPaxId,
                'pax_index' => 'group-3',
                'unit_type' => 'price_per_pax',
                'unit' => 1,
                'operation_at' => now(),
                'operation_by' => 0,
                'operation_by_user_type' => 'Admin',
            ]);

            // A field belonging to a DIFFERENT rate group's pax_id must never be swept up —
            // proves the pax_id filter, not just "any removed item", actually gates this.
            $otherPaxField = $this->makeField(30.00, true);
            $otherPaxAddon = TourBookingAddonField::create([
                'tour_booking_id' => $bookingId,
                'form_id' => $this->formId,
                'field_id' => $otherPaxField->id,
                'field_label' => $otherPaxField->label,
                'option_id' => null,
                'pax_id' => $rateGroupPaxId + 999,
                'pax_index' => 'other-group',
                'unit_type' => 'price_per_pax',
                'unit' => 1,
                'operation_at' => now(),
                'operation_by' => 0,
                'operation_by_user_type' => 'Admin',
            ]);

            $removedPi = 'pi_test_group_delete_removed';
            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId,
                'tour_booking_addon_id' => $removedAddon->id,
                'payment_method' => 'cash',
                'status' => 'paid',
                'operation' => 'charge',
                'transaction_id' => $removedPi,
                'field_price' => 15.00,
                'field_fee' => 1.47,
                'option_price' => 0,
                'option_fee' => 0,
                'operation_at' => now(),
                'operation_by' => 0,
                'operation_by_user_type' => 'Admin',
            ]);
            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId,
                'tour_booking_addon_id' => $otherPaxAddon->id,
                'payment_method' => 'cash',
                'status' => 'paid',
                'operation' => 'charge',
                'transaction_id' => 'pi_test_group_delete_other',
                'field_price' => 30.00,
                'field_fee' => 2.94,
                'option_price' => 0,
                'option_fee' => 0,
                'operation_at' => now(),
                'operation_by' => 0,
                'operation_by_user_type' => 'Admin',
            ]);

            $delta = ChangePeopleAddonService::calculatePricedAddonDelta(
                [],
                [],
                [
                    ['db_id' => $removedAddon->id],
                    ['db_id' => $otherPaxAddon->id],
                ],
                9.8,
                true
            );
            $this->assertCount(2, $delta['refundItems'], 'Sanity: the delta itself must include both removed fields');

            // A Cash-paid add-on refunds through the ledger only — no real Stripe call, matching
            // settleAddonTransactions()'s own `$paymentMethod === 'card'` gate on the actual
            // gateway refund.
            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->expects($this->never())->method('refundPayment');
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            $transaction = \App\Models\Transaction::find($booking->transactionId);

            ChangePeopleAddonService::refundRemovedPaxAddonsForRateGroup(
                $delta,
                $rateGroupPaxId,
                $transaction,
                'Cash',
                ['refund_Type' => '100_refund'],
                (object) ['id' => $bookingId],
                $bookingId
            );

            $refundedRow = TourBookingAddonTransaction::where('tour_booking_addon_id', $removedAddon->id)
                ->where('operation', 'refund')
                ->first();
            $this->assertNotNull($refundedRow, 'The removed pax\'s own rate group must be refunded');
            $this->assertEquals(15.00, (float) $refundedRow->field_price);
            $this->assertEquals(1.47, (float) $refundedRow->field_fee);

            $otherPaxRefundRow = TourBookingAddonTransaction::where('tour_booking_addon_id', $otherPaxAddon->id)
                ->where('operation', 'refund')
                ->first();
            $this->assertNull($otherPaxRefundRow, 'A different rate group\'s add-on must never be touched');
        } finally {
            DB::table('tour_booking_addon_transactions')->where('tour_booking_id', $bookingId)->delete();
            DB::table('tour_booking_addon_fields')->where('tour_booking_id', $bookingId)->delete();
            DB::table('tour_booking_history')->where('tour_booking_id', $bookingId)->delete();
            $booking->cleanup();
        }
    }

    /**
     * True Pay Later (no Transaction row was ever completed for this booking): must never
     * fabricate a Stripe refund or a ledger row for money that was never actually collected —
     * mirrors the exact bypass deletePeopleChangePeople() already uses on its own add-on-removal
     * side, per its own long comment.
     */
    public function test_refund_removed_pax_addons_for_rate_group_pay_later_writes_dont_refund_audit_entry(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Pay Later')->create();
        $bookingId = $booking->bookingId;
        $rateGroupPaxId = $booking->transactId;

        try {
            $photoField = $this->makeField(15.00, true);
            $removedAddon = TourBookingAddonField::create([
                'tour_booking_id' => $bookingId,
                'form_id' => $this->formId,
                'field_id' => $photoField->id,
                'field_label' => $photoField->label,
                'option_id' => null,
                'pax_id' => $rateGroupPaxId,
                'pax_index' => 'group-3',
                'unit_type' => 'price_per_pax',
                'unit' => 1,
                'operation_at' => now(),
                'operation_by' => 0,
                'operation_by_user_type' => 'Admin',
            ]);

            $delta = ChangePeopleAddonService::calculatePricedAddonDelta(
                [],
                [],
                [['db_id' => $removedAddon->id]],
                0,
                false
            );

            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->expects($this->never())->method('refundPayment');
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            ChangePeopleAddonService::refundRemovedPaxAddonsForRateGroup(
                $delta,
                $rateGroupPaxId,
                null, // no Completed transaction — true Pay Later
                '',
                ['refund_Type' => '100_refund'],
                (object) ['id' => $bookingId],
                $bookingId
            );

            $historyRow = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('operation', 'Refund')
                ->orderByDesc('id')
                ->first();
            $this->assertNotNull($historyRow, 'Removal must still leave an audit trail even with nothing to actually refund');
            $this->assertStringContainsString("Don't Refund", $historyRow->details);
        } finally {
            DB::table('tour_booking_addon_fields')->where('tour_booking_id', $bookingId)->delete();
            DB::table('tour_booking_history')->where('tour_booking_id', $bookingId)->delete();
            $booking->cleanup();
        }
    }

    /** Calls the protected static resolvePersonOrdinal() directly via reflection. */
    protected function invokeResolvePersonOrdinal(array $addon)
    {
        $method = new ReflectionMethod(ChangePeopleAddonService::class, 'resolvePersonOrdinal');
        $method->setAccessible(true);

        return $method->invoke(null, $addon);
    }

    public function test_resolve_person_ordinal_keeps_a_legitimate_zero_for_the_first_traveler(): void
    {
        // Regression case for a real production report bug: person_ordinal is
        // documented as the traveler's 0-based position, so the first
        // traveler's real value is 0. The previous !empty() check treated 0 as
        // "not set" and fell through to the rate_for branch (or null), silently
        // turning the first traveler's addon selection into an un-labeled row
        // ("Yes" instead of "Yes (Traveler 1)") wherever a Change People
        // operation replayed it.
        $this->assertSame('0', $this->invokeResolvePersonOrdinal([
            'person_ordinal' => 0,
            'rate_for' => 'Adult',
            'pax_index' => 0,
        ]), 'A real ordinal of 0 must be kept, not discarded in favor of the rate_for fallback');

        $this->assertSame('2', $this->invokeResolvePersonOrdinal([
            'person_ordinal' => '2',
        ]), 'A non-zero ordinal must still pass through unchanged');

        $this->assertSame('Adult 1', $this->invokeResolvePersonOrdinal([
            'rate_for' => 'Adult',
            'pax_index' => 0,
        ]), 'With no person_ordinal at all, the rate_for/pax_index fallback must still apply');

        $this->assertNull($this->invokeResolvePersonOrdinal([]), 'With neither person_ordinal nor rate_for, the result must be null');
    }
}
