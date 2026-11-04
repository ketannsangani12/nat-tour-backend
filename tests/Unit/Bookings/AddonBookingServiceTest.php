<?php

namespace Tests\Unit\Bookings;

use App\Models\AddonField;
use App\Models\AddonForm;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingAddonTransaction;
use App\Services\Booking\AddonBookingService;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression for booking 65517: add-ons added BEFORE payment (via
 * TourBookingAddonRepository::chargeAddonFields() with payment_method='pay later') get an
 * 'open' placeholder tour_booking_addon_transactions row. When the admin later makes a
 * manual "Make Payment" (Cash/Card/Invoiced), AddonBookingService::createAddonTransactionsFromFields()
 * is supposed to settle those — but its old whereNotExists() filter excluded ANY field with
 * an existing transaction row, 'open' or not, so it silently found nothing to do and the
 * add-ons stayed unpaid/untraceable forever despite the booking showing "Paid in Full".
 */
class AddonBookingServiceTest extends TestCase
{
    protected int $formId;
    protected static array $createdFormIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $packageId = (int) (DB::table('tour_packages')->where('status', 'Active')->value('id')
            ?? (new CreateABooking())->create()->packageId);

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
        if (!empty(static::$createdFormIds)) {
            $fieldIds = DB::table('addon_fields')->whereIn('form_id', static::$createdFormIds)->pluck('id');
            DB::table('addon_field_options')->whereIn('field_id', $fieldIds)->delete();
            DB::table('addon_fields')->whereIn('form_id', static::$createdFormIds)->delete();
            DB::table('addon_forms')->whereIn('id', static::$createdFormIds)->delete();
        }
        parent::tearDownAfterClass();
    }

    protected function makeOpenAddonField(int $bookingId, string $label, float $price, string $unitType = 'one_time', int $unit = 0): TourBookingAddonField
    {
        $addonField = AddonField::create([
            'form_id' => $this->formId,
            'label' => $label,
            'type' => 'text',
            'unit_type' => $unitType,
            'additional_fee' => true,
            'price' => $price,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);

        $qty = $unitType === 'price_per_unit' ? max(1, $unit) : 1;

        $field = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId,
            'form_id' => $this->formId,
            'field_id' => $addonField->id,
            'field_label' => $label,
            'option_id' => null,
            'value' => $unitType === 'price_per_unit' ? (string) $unit : 'checked',
            'pax_id' => null,
            'pax_index' => null,
            'unit_type' => $unitType,
            'unit' => $unit,
            'operation_at' => now(),
            'operation_by' => 0,
            'operation_by_user_type' => 'Admin',
        ]);

        // Mirrors chargeAddonFields()'s 'pay later' path: status='open', no fee yet
        // (fee applicability isn't known until a real payment method is chosen).
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId,
            'tour_booking_addon_id' => $field->id,
            'payment_method' => 'pay later',
            'status' => 'open',
            'operation' => 'charge',
            'transaction_id' => null,
            'field_price' => $price * $qty,
            'field_fee' => 0,
            'option_price' => 0,
            'option_fee' => 0,
            'operation_at' => now(),
            'operation_by' => 0,
            'operation_by_user_type' => 'Admin',
        ]);

        return $field;
    }

    /**
     * Reproduces booking 65517 exactly: 3 add-ons added pay-later (one_time $25, one_time $5,
     * price_per_unit $6 total for 2 units), then a Cash "Make Payment" happens. All 3 open
     * rows must flip to paid — none may be left behind, and a transactions + history row
     * must be written covering their combined total.
     */
    public function test_open_addon_transactions_are_settled_by_a_later_cash_payment(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Cash')->create();
        $bookingId = $booking->bookingId;

        $photoField = $this->makeOpenAddonField($bookingId, 'Professional Photography Package', 25.00);
        $languageField = $this->makeOpenAddonField($bookingId, 'Guided Language Preference', 5.00);
        $waterField = $this->makeOpenAddonField($bookingId, 'Extra Water Bottles', 3.00, 'price_per_unit', 2);

        try {
            AddonBookingService::createAddonTransactionsFromFields($bookingId, null, 'cash', false);

            foreach ([$photoField->id, $languageField->id, $waterField->id] as $fieldId) {
                $row = TourBookingAddonTransaction::where('tour_booking_addon_id', $fieldId)
                    ->where('operation', 'charge')
                    ->first();
                $this->assertNotNull($row, "Addon field {$fieldId} must still have its charge row");
                $this->assertEquals('paid', $row->status, "Addon field {$fieldId} must be settled, not left 'open'");
                $this->assertEquals('cash', $row->payment_method);
            }

            $this->assertEquals(25.00, (float) TourBookingAddonTransaction::where('tour_booking_addon_id', $photoField->id)->value('field_price'));
            $this->assertEquals(6.00, (float) TourBookingAddonTransaction::where('tour_booking_addon_id', $waterField->id)->value('field_price'), 'Water bottles: $3 x 2 units, already qty-multiplied at add-time');

            $this->assertDatabaseHas('transactions', [
                'tour_booking_id' => $bookingId,
                'charge_type' => 'addons',
                'amount' => 36.00,
            ]);

            $historyRow = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('charge_type', 'addons')
                ->orderByDesc('id')
                ->first();
            $this->assertNotNull($historyRow, 'A history entry must exist for the settled add-ons');
            $details = json_decode($historyRow->details, true);
            $this->assertCount(3, $details['addons']);
        } finally {
            DB::table('tour_booking_addon_transactions')->where('tour_booking_id', $bookingId)->delete();
            DB::table('tour_booking_addon_fields')->where('tour_booking_id', $bookingId)->delete();
            $booking->cleanup();
        }
    }

    /** Fee must be recomputed (not left at 0) when the resolved payment method applies fees. */
    public function test_open_addon_transactions_get_the_correct_fee_when_fees_apply(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')
            ->withTransactOverrides(['nat_fees_percentage' => 9.82])
            ->create();
        $bookingId = $booking->bookingId;

        $field = $this->makeOpenAddonField($bookingId, 'Souvenir T-Shirt', 20.00);

        try {
            $paymentIntent = (object) [
                'id' => 'pi_test_65517_settle',
                'charges' => (object) ['data' => [(object) ['application_fee' => null]]],
            ];

            AddonBookingService::createAddonTransactionsFromFields($bookingId, $paymentIntent, 'card', true);

            $row = TourBookingAddonTransaction::where('tour_booking_addon_id', $field->id)
                ->where('operation', 'charge')->first();
            $this->assertEquals('paid', $row->status);
            $this->assertEquals('pi_test_65517_settle', $row->transaction_id);
            $this->assertEqualsWithDelta(1.96, (float) $row->field_fee, 0.01, '$20 * 9.82% nat fee');
        } finally {
            DB::table('tour_booking_addon_transactions')->where('tour_booking_id', $bookingId)->delete();
            DB::table('tour_booking_addon_fields')->where('tour_booking_id', $bookingId)->delete();
            $booking->cleanup();
        }
    }

    /**
     * Rounding policy (2026-08-10, explicit user decision): a price_per_unit field's fee is
     * rounded PER UNIT then multiplied by qty — NOT rounded once on the qty-multiplied total.
     * $3/unit x 2 units @ 9.82% is the exact case where the two conventions disagree:
     * round($3 x 9.82%) x 2 = $0.58 (current policy) vs round($6 x 9.82%) = $0.59 (prior policy,
     * reverted). This is a real-money regression guard — do not "fix" this test to expect $0.59
     * without re-confirming the policy decision (see AddonBookingService::calculateFieldAmounts()
     * for the full rationale) with whoever owns that decision.
     */
    public function test_open_addon_transaction_fee_is_rounded_per_unit_then_multiplied(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')
            ->withTransactOverrides(['nat_fees_percentage' => 9.82])
            ->create();
        $bookingId = $booking->bookingId;

        $field = $this->makeOpenAddonField($bookingId, 'Extra Water Bottles', 3.00, 'price_per_unit', 2);

        try {
            $paymentIntent = (object) [
                'id' => 'pi_test_rounding_policy',
                'charges' => (object) ['data' => [(object) ['application_fee' => null]]],
            ];

            AddonBookingService::createAddonTransactionsFromFields($bookingId, $paymentIntent, 'card', true);

            $row = TourBookingAddonTransaction::where('tour_booking_addon_id', $field->id)
                ->where('operation', 'charge')->first();
            $this->assertEquals(6.00, (float) $row->field_price, '$3 x 2 units');
            $this->assertEquals(0.58, (float) $row->field_fee, 'round($3 x 9.82%) x 2 = $0.58, not round($6 x 9.82%) = $0.59');
        } finally {
            DB::table('tour_booking_addon_transactions')->where('tour_booking_id', $bookingId)->delete();
            DB::table('tour_booking_addon_fields')->where('tour_booking_id', $bookingId)->delete();
            $booking->cleanup();
        }
    }

    /**
     * Same policy, exercised via calculateFieldAmounts() directly (the function that
     * determines the actual Stripe PaymentIntent amount for a new booking) — must agree with
     * the settlement path above, or the ledger disagrees with what was actually charged.
     */
    public function test_calculate_field_amounts_rounds_price_per_unit_fee_per_unit(): void
    {
        $fieldData = [
            'unit_type' => 'price_per_unit',
            'value' => 2,
            'field_price' => 3.00,
            'field_price_enabled' => 1,
        ];

        $amounts = AddonBookingService::calculateFieldAmounts($fieldData, 'Card', 0, 9.82);

        $this->assertEquals(6.00, $amounts['price']);
        $this->assertEquals(0.58, $amounts['fee']);
    }

    /**
     * Regression for a real Stripe undercharge (booking #69822, dixies): a priced CHILD field
     * (e.g. "Camera Rental Type", $40 DSLR option) nested under a parent field with NO price of
     * its own (e.g. "Equipment Rental Needed?") must still be counted. Every caller of this method
     * used to gate the ENTIRE call on the PARENT's own price flags, so this function's own
     * recursion into children never even got a chance to run for an unpriced parent.
     */
    public function test_calculate_field_amounts_includes_priced_child_under_unpriced_parent(): void
    {
        $fieldData = [
            'field_price_enabled' => 0,
            'option_price_enabled' => 0,
            'field_price' => 0,
            'option_price' => 0,
            'children' => [
                [
                    2538 => [
                        'field_price_enabled' => 0,
                        'option_price_enabled' => 1,
                        'field_price' => 0,
                        'option_price' => 40,
                    ],
                ],
            ],
        ];

        $amounts = AddonBookingService::calculateFieldAmounts($fieldData, 'Card', 0, 9.82);

        $this->assertEquals(40.00, $amounts['price']);
        $this->assertEquals(3.93, $amounts['fee']);
    }

    /**
     * The flip side of the fix above, and the risk explicitly raised when reviewing it: since
     * every caller now always calls calculateFieldAmounts() (no more outer price-flag gate), this
     * method itself must be the one place that still refuses to count a field's price when pricing
     * is disabled for it — otherwise a field with pricing turned OFF but a stale/leftover
     * `field_price`/`option_price` value (components.js always serializes the field's configured
     * price regardless of the additional_fee toggle: `wrapper.dataset.fee = node.price || 0`)
     * would get silently OVERcounted instead of correctly ignored.
     */
    public function test_calculate_field_amounts_ignores_stale_price_when_disabled(): void
    {
        $fieldData = [
            'field_price_enabled' => 0,
            'option_price_enabled' => 0,
            'field_price' => 25.00, // stale — pricing is OFF for this field
            'option_price' => 15.00, // stale — pricing is OFF for this option too
        ];

        $amounts = AddonBookingService::calculateFieldAmounts($fieldData, 'Card', 0, 9.82);

        $this->assertEquals(0.0, $amounts['price']);
        $this->assertEquals(0.0, $amounts['fee']);
    }

    /**
     * Three-level nesting: an unpriced parent with an unpriced child, which itself has a priced
     * grandchild. Confirms the recursion is unconditional at EVERY level, not just the first —
     * this exact case was broken one level deeper than the reported bug, since the recursive call
     * inside calculateFieldAmounts() used to gate on the immediate child's own price flags too.
     */
    public function test_calculate_field_amounts_includes_priced_grandchild_under_two_unpriced_ancestors(): void
    {
        $fieldData = [
            'field_price_enabled' => 0,
            'option_price_enabled' => 0,
            'field_price' => 0,
            'option_price' => 0,
            'children' => [
                [
                    2 => [
                        'field_price_enabled' => 0,
                        'option_price_enabled' => 0,
                        'field_price' => 0,
                        'option_price' => 0,
                        'children' => [
                            [
                                3 => [
                                    'field_price_enabled' => 1,
                                    'option_price_enabled' => 0,
                                    'field_price' => 15.00,
                                    'option_price' => 0,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $amounts = AddonBookingService::calculateFieldAmounts($fieldData, 'Card', 0, 9.82);

        $this->assertEquals(15.00, $amounts['price']);
        $this->assertEquals(1.47, $amounts['fee']);
    }

    /** Fields with NO transaction row at all (the original, pre-existing scenario) still work. */
    public function test_fields_with_no_existing_transaction_still_get_a_fresh_paid_row(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Cash')->create();
        $bookingId = $booking->bookingId;

        $addonField = AddonField::create([
            'form_id' => $this->formId,
            'label' => 'Fresh Field',
            'type' => 'text',
            'unit_type' => 'one_time',
            'additional_fee' => true,
            'price' => 10.00,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);
        $field = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId,
            'form_id' => $this->formId,
            'field_id' => $addonField->id,
            'field_label' => 'Fresh Field',
            'value' => 'checked',
            'unit_type' => 'one_time',
            'unit' => 0,
            'operation_at' => now(),
            'operation_by' => 0,
            'operation_by_user_type' => 'Admin',
        ]);

        try {
            $this->assertEquals(0, TourBookingAddonTransaction::where('tour_booking_addon_id', $field->id)->count());

            AddonBookingService::createAddonTransactionsFromFields($bookingId, null, 'cash', false);

            $row = TourBookingAddonTransaction::where('tour_booking_addon_id', $field->id)->first();
            $this->assertNotNull($row);
            $this->assertEquals('paid', $row->status);
            $this->assertEquals(10.00, (float) $row->field_price);
        } finally {
            DB::table('tour_booking_addon_transactions')->where('tour_booking_id', $bookingId)->delete();
            DB::table('tour_booking_addon_fields')->where('tour_booking_id', $bookingId)->delete();
            $booking->cleanup();
        }
    }

    /**
     * Regression: a price_per_pax dropdown field (e.g. "Sweater" with Small/Medium/Large
     * options) selected at NEW booking creation gets its option_label stored correctly on
     * tour_booking_addon_fields — insertAddonFieldRecursive() already writes it faithfully from
     * the submitted payload — but insertBookingAddonTransactions() -> buildAddonTransaction()
     * built the resulting "Add-ons" Payment history line item's name from ONLY
     * $addonField->field_label, never $addonField->option_label. History & Notes (and anything
     * else reading that entry) showed a bare "Sweater" with no indication which size was
     * actually selected and charged — the size was stored, just never surfaced anywhere.
     * ChangePeopleAddonService::buildAddonLabel() already documents and applies the correct
     * "Field Label (Option Label)" convention for the identical situation on the Change People
     * side, and this same file already gets it right in createAddonTransactionsFromFields()
     * (its later "Make a Payment" settlement path) — buildAddonTransaction() alone never adopted it.
     */
    public function test_history_label_includes_selected_option_for_a_price_per_pax_dropdown(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Cash')->create();
        $bookingId = $booking->bookingId;

        $sweaterField = AddonField::create([
            'form_id' => $this->formId,
            'label' => 'Sweater',
            'type' => 'dropdown',
            'unit_type' => 'price_per_pax',
            'additional_fee' => false,
            'price' => 0,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);

        $addonField = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId,
            'form_id' => $this->formId,
            'field_id' => $sweaterField->id,
            'field_label' => 'Sweater',
            'option_id' => 501,
            'option_label' => 'Medium',
            'value' => 'medium',
            'pax_id' => $booking->transactId,
            'pax_index' => 'p-0',
            'person_ordinal' => 0,
            'unit_type' => 'price_per_pax',
            'unit' => 1,
            'operation_at' => now(),
            'operation_by' => 0,
            'operation_by_user_type' => 'Admin',
        ]);

        // Shape produced by insertPricePerPaxBookingAddons()/expandDisplayOnceAddons() and
        // consumed by insertBookingAddonTransactions() itself — one addons array entry per
        // traveler (array index IS the person_ordinal buildAddonTransaction() looks up by).
        $paxAddonsData = json_encode([
            'form_id' => $this->formId,
            'fields' => [
                $booking->rateId => [
                    'addons' => [
                        [
                            'uid' => 'p-0',
                            (string) $sweaterField->id => [
                                'field_id' => $sweaterField->id,
                                'option_id' => 501,
                                'option_label' => 'Medium',
                                'value' => 'medium',
                                'unit_type' => 'price_per_pax',
                                'field_price_enabled' => 0,
                                'option_price_enabled' => 1,
                                'field_price' => 0,
                                'option_price' => 15.00,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        try {
            AddonBookingService::insertBookingAddonTransactions(
                $bookingId, null, 'Cash', $paxAddonsData, null, 'paid'
            );

            $chargeRow = TourBookingAddonTransaction::where('tour_booking_addon_id', $addonField->id)
                ->where('operation', 'charge')
                ->first();
            $this->assertNotNull($chargeRow, 'Sanity check: the transaction row must actually be created');
            $this->assertEquals(15.00, (float) $chargeRow->option_price);

            $historyRow = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('operation', 'Payment')
                ->orderByDesc('id')
                ->first();
            $this->assertNotNull($historyRow, 'The addition must leave a "Payment"/Add-ons entry in History & Notes');
            $this->assertStringContainsString('Medium', $historyRow->details, 'History must show which Sweater size was selected, not just a bare "Sweater"');
            $this->assertStringContainsString('Sweater (Medium)', $historyRow->details);
        } finally {
            DB::table('tour_booking_addon_transactions')->where('tour_booking_id', $bookingId)->delete();
            DB::table('tour_booking_addon_fields')->where('id', $addonField->id)->delete();
            DB::table('tour_booking_history')->where('tour_booking_id', $bookingId)->where('operation', 'Payment')->delete();
            $sweaterField->delete();
            $booking->cleanup();
        }
    }
}
