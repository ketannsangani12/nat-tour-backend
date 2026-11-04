<?php

namespace Tests\Unit\Bookings;

use App\Models\AddonField;
use App\Models\AddonFieldOption;
use App\Models\AddonForm;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingAddonTransaction;
use App\Repositories\TourBooking\TourBookingAddonRepository;
use App\Services\Booking\DeleteBookingAddonBreakdownService;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression for two related bugs found via booking 64957:
 *
 * 1. Reassigning a priced add-on selection from one pax to another (e.g. moving a T-Shirt from
 *    "Adult 2" to "Adult 3") nets to $0 financial impact, so the JS takes the "no financial
 *    action" path straight to TourBookingAddonRepository::savePaxAddons() — which only called
 *    upsertBookingAddonFields() and created NEITHER a tour_booking_addon_transactions row NOR a
 *    tour_booking_history entry. The underlying data (who has which add-on) genuinely changed,
 *    so it must leave an audit trail even though no money moved.
 *
 * 2. A naive fix (stamping a $0 "paid" marker on the new field) made the "Passenger Add-ons"
 *    preview modal's badge show correctly, but left the REAL charged money stuck on the old
 *    (now soft-deleted) field's PaymentIntent — so when the pax that now holds the add-on is
 *    later deleted, there was nothing to refund: DeleteBookingAddonBreakdownService nets the old
 *    field to $0 (its $0 marker had nothing to offset) and the new field was never tied to the
 *    real PI, so it never surfaced as a refundable item either. The fix must actually move the
 *    real charge from the old field to the new one (same PaymentIntent id on both sides, netting
 *    to zero impact on that PI's available balance) so the item becomes genuinely refundable
 *    under whichever pax now holds it.
 */
class SavePaxAddonsHistoryTest extends TestCase
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

    public function test_zero_net_reassignment_transfers_the_real_charge_and_stays_refundable(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        $shirtField = AddonField::create([
            'form_id' => $this->formId,
            'label' => 'Souvenir T-Shirt',
            'type' => 'dropdown',
            'unit_type' => 'price_per_pax',
            'additional_fee' => true,
            'price' => null,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);

        // The existing field on "Adult 2" that's about to be removed (already charged/paid).
        $existingField = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId,
            'form_id' => $this->formId,
            'field_id' => $shirtField->id,
            'pax_id' => 700500,
            'field_label' => 'Souvenir T-Shirt',
            'option_id' => 1,
            'option_label' => 'Medium',
            'pax_index' => 'swap-test-adult-2',
            'unit_type' => 'price_per_pax',
            'unit' => 1,
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $existingField->id,
            'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => 'pi_swap_test', 'field_price' => 0, 'field_fee' => 0,
            'option_price' => 20.00, 'option_fee' => 1.96,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);

        try {
            $repo = app(TourBookingAddonRepository::class);
            $result = $repo->savePaxAddons((object) [
                'booking_id' => $bookingId,
                'form_id' => $this->formId,
                'description' => null,
                'pax' => [
                    // "Adult 2" loses the T-Shirt.
                    [
                        'pax_id' => 700500,
                        'pax_index' => 'swap-test-adult-2',
                        'person_ordinal' => 1,
                        'added' => [],
                        'updated' => [],
                        'deleted' => [['db_id' => $existingField->id, 'field_id' => $shirtField->id]],
                    ],
                    // "Adult 3" gains the IDENTICAL selection (same field_id + option_id) — net
                    // $0, so this whole request goes through the no-financial savePaxAddons()
                    // path, and this specific pair must be recognized as a true reassignment.
                    [
                        'pax_id' => 700501,
                        'pax_index' => 'swap-test-adult-3',
                        'person_ordinal' => 1,
                        'added' => [[
                            'field_id' => $shirtField->id,
                            'option_id' => 1,
                            'option_label' => 'Medium',
                            'value' => 'medium',
                            'unit_type' => 'price_per_pax',
                            'unit' => 1,
                            'rule_id' => null,
                            'applied_rule_snapshot' => null,
                            'paid_price' => 0,
                            'paid_fee' => 0,
                        ]],
                        'updated' => [],
                        'deleted' => [],
                    ],
                ],
            ]);

            $this->assertTrue($result['success']);
            $this->assertSoftDeleted('tour_booking_addon_fields', ['id' => $existingField->id]);
            $newField = TourBookingAddonField::where('tour_booking_id', $bookingId)
                ->where('pax_id', 700501)->first();
            $this->assertNotNull($newField);

            // The original charge transaction must be REPOINTED onto the new field (not
            // duplicated as a refund+charge pair) — this moves its entire transaction history in
            // one step, so the money is now found under whichever pax actually holds the add-on,
            // while the original PaymentIntent id stays intact for a real refund later.
            $this->assertDatabaseHas('tour_booking_addon_transactions', [
                'tour_booking_addon_id' => $newField->id,
                'status' => 'paid',
                'operation' => 'charge',
                'transaction_id' => 'pi_swap_test',
                'option_price' => 20.00,
                'option_fee' => 1.96,
            ]);
            $this->assertDatabaseMissing('tour_booking_addon_transactions', [
                'tour_booking_addon_id' => $existingField->id,
            ]);

            $historyRow = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('operation', 'Refund')
                ->orderByDesc('id')
                ->first();
            $this->assertNotNull($historyRow, 'A history entry must exist for a zero-net add-on reassignment');
            $this->assertEquals(0.0, (float) $historyRow->ticket_cost, 'No customer-facing refund/charge happened — must not inflate the Refund Amount summary');
            $this->assertEquals(0.0, (float) $historyRow->platform_fees);

            $details = json_decode($historyRow->details, true);
            $this->assertEquals('Souvenir T-Shirt — Medium', $details['addons'][0]['name']);
            $this->assertEquals(20.00, $details['addons'][0]['price']);
            $this->assertEquals('Reassigned', $details['addons'][0]['status']);
            $this->assertEquals('Souvenir T-Shirt — Medium', $details['added_addons'][0]['name']);
            $this->assertEquals('Reassigned', $details['added_addons'][0]['status']);

            // End-to-end: the same query the "Update Booking" delete-pax modal uses must now
            // surface this item under the NEW pax_id (700501, "Adult 3"), not the old one — this
            // is what makes it actually selectable/refundable when that pax is later deleted.
            $ref = new \ReflectionMethod(DeleteBookingAddonBreakdownService::class, 'buildAddonLineItemsByTransactionNumber');
            $ref->setAccessible(true);
            $itemsByTx = $ref->invoke(null, $bookingId);
            $items = $itemsByTx->get('pi_swap_test') ?? collect();
            $this->assertCount(1, $items, 'Exactly one line item must survive — the old field must net to zero and drop out');
            $this->assertEquals($newField->pax_id, $items[0]['pax_id']);
            $this->assertEquals(20.00, $items[0]['price']);
            $this->assertEquals(1.96, $items[0]['fee']);
        } finally {
            $newFieldId = TourBookingAddonField::where('tour_booking_id', $bookingId)->where('pax_id', 700501)->value('id');
            TourBookingAddonTransaction::where('tour_booking_addon_id', $existingField->id)->forceDelete();
            if ($newFieldId) {
                TourBookingAddonTransaction::where('tour_booking_addon_id', $newFieldId)->forceDelete();
            }
            TourBookingAddonField::withTrashed()->where('tour_booking_id', $bookingId)->forceDelete();
            $booking->cleanup();
        }
    }

    /**
     * Regression for booking 65175: reassigning to a DIFFERENT option that happens to cost the
     * same (e.g. Adult's XL T-Shirt removed, Child's Large T-Shirt added, both $22) must still
     * be recognized as a real transfer — an exact (field_id, option_id)-only match left the
     * charge stranded on the old field because "XL" and "Large" are different option ids.
     */
    public function test_reassignment_to_a_different_option_with_matching_price_still_transfers(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        $shirtField = AddonField::create([
            'form_id' => $this->formId,
            'label' => 'Souvenir T-Shirt',
            'type' => 'dropdown',
            'unit_type' => 'price_per_pax',
            'additional_fee' => true,
            'price' => null,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);
        $xlOption = AddonFieldOption::create([
            'field_id' => $shirtField->id, 'label' => 'XL', 'value' => 'xl',
            'additional_fee' => true, 'price' => 22.00, 'sort_order' => 0,
        ]);
        $largeOption = AddonFieldOption::create([
            'field_id' => $shirtField->id, 'label' => 'Large', 'value' => 'large',
            'additional_fee' => true, 'price' => 22.00, 'sort_order' => 1,
        ]);

        $existingField = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId,
            'form_id' => $this->formId,
            'field_id' => $shirtField->id,
            'pax_id' => 700502,
            'field_label' => 'Souvenir T-Shirt',
            'option_id' => $xlOption->id,
            'option_label' => 'XL',
            'pax_index' => 'diff-option-test-adult',
            'unit_type' => 'price_per_pax',
            'unit' => 1,
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $existingField->id,
            'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => 'pi_diff_option_test', 'field_price' => 0, 'field_fee' => 0,
            'option_price' => 22.00, 'option_fee' => 2.16,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);

        try {
            $repo = app(TourBookingAddonRepository::class);
            $repo->savePaxAddons((object) [
                'booking_id' => $bookingId,
                'form_id' => $this->formId,
                'description' => null,
                'pax' => [
                    [
                        'pax_id' => 700502,
                        'pax_index' => 'diff-option-test-adult',
                        'person_ordinal' => 1,
                        'added' => [],
                        'updated' => [],
                        'deleted' => [['db_id' => $existingField->id, 'field_id' => $shirtField->id]],
                    ],
                    [
                        'pax_id' => 700503,
                        'pax_index' => 'diff-option-test-child',
                        'person_ordinal' => 1,
                        'added' => [[
                            'field_id' => $shirtField->id,
                            'option_id' => $largeOption->id,
                            'option_label' => 'Large',
                            'value' => 'large',
                            'unit_type' => 'price_per_pax',
                            'unit' => 1,
                            'rule_id' => null,
                            'applied_rule_snapshot' => null,
                            'paid_price' => 0,
                            'paid_fee' => 0,
                        ]],
                        'updated' => [],
                        'deleted' => [],
                    ],
                ],
            ]);

            $newField = TourBookingAddonField::where('tour_booking_id', $bookingId)
                ->where('pax_id', 700503)->first();
            $this->assertNotNull($newField);
            $this->assertEquals('Large', $newField->option_label);

            // The $22 charge must have moved onto the Child's new (Large) field, even though
            // its option_id differs from the Adult's original (XL) field.
            $this->assertDatabaseHas('tour_booking_addon_transactions', [
                'tour_booking_addon_id' => $newField->id,
                'status' => 'paid',
                'operation' => 'charge',
                'transaction_id' => 'pi_diff_option_test',
                'option_price' => 22.00,
                'option_fee' => 2.16,
            ]);
            $this->assertDatabaseMissing('tour_booking_addon_transactions', [
                'tour_booking_addon_id' => $existingField->id,
            ]);
        } finally {
            $newFieldId = TourBookingAddonField::where('tour_booking_id', $bookingId)->where('pax_id', 700503)->value('id');
            TourBookingAddonTransaction::where('tour_booking_addon_id', $existingField->id)->forceDelete();
            if ($newFieldId) {
                TourBookingAddonTransaction::where('tour_booking_addon_id', $newFieldId)->forceDelete();
            }
            TourBookingAddonField::withTrashed()->where('tour_booking_id', $bookingId)->forceDelete();
            DB::table('addon_field_options')->whereIn('id', [$xlOption->id, $largeOption->id])->delete();
            $booking->cleanup();
        }
    }

    /**
     * Regression: unlike the two tests above (which reassign between DISTINCT pax_ids), a real
     * rate group shares ONE pax_id across every person in it (e.g. "Adult x3" is a single
     * tour_bookings_transact row; person_ordinal alone tells them apart). Reassigning an add-on
     * from the 2nd pax to the 3rd — same pax_id, different person_ordinal — must stamp the
     * NEW field with the 3rd pax's own ordinal (2), not silently inherit the 2nd pax's (1) just
     * because upsertBookingAddonFields()'s "reuse a recently soft-deleted row's ordinal" lookup
     * only scoped by pax_id, not by which specific person it belonged to.
     */
    public function test_reassignment_within_shared_rate_group_keeps_each_pax_own_ordinal(): void
    {
        $booking = (new CreateABooking())->withTourists(3)->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;
        $rateGroupPaxId = $booking->transactId;

        $photoField = AddonField::create([
            'form_id' => $this->formId,
            'label' => 'Photography Package',
            'type' => 'checkbox',
            'unit_type' => 'price_per_pax',
            'additional_fee' => true,
            'price' => 15.00,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);

        // The 2nd pax (person_ordinal=1) already has the add-on, paid.
        $existingField = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId,
            'form_id' => $this->formId,
            'field_id' => $photoField->id,
            'pax_id' => $rateGroupPaxId,
            'field_label' => 'Photography Package',
            'pax_index' => 'shared-group-adult-2',
            'person_ordinal' => 1,
            'unit_type' => 'price_per_pax',
            'unit' => 1,
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $existingField->id,
            'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => 'pi_shared_group_test', 'field_price' => 15.00, 'field_fee' => 1.47,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);

        try {
            $repo = app(TourBookingAddonRepository::class);
            $repo->savePaxAddons((object) [
                'booking_id' => $bookingId,
                'form_id' => $this->formId,
                'description' => null,
                'pax' => [
                    // 2nd pax loses the add-on.
                    [
                        'pax_id' => $rateGroupPaxId,
                        'pax_index' => 'shared-group-adult-2',
                        'person_ordinal' => 1,
                        'added' => [],
                        'updated' => [],
                        'deleted' => [['db_id' => $existingField->id, 'field_id' => $photoField->id]],
                    ],
                    // 3rd pax (brand new pax_index, SAME shared pax_id) gains it.
                    [
                        'pax_id' => $rateGroupPaxId,
                        'pax_index' => 'shared-group-adult-3',
                        'person_ordinal' => 2,
                        'added' => [[
                            'field_id' => $photoField->id,
                            'option_id' => null,
                            'option_label' => null,
                            'value' => 'checked',
                            'unit_type' => 'price_per_pax',
                            'unit' => 1,
                            'rule_id' => null,
                            'applied_rule_snapshot' => null,
                            'paid_price' => 0,
                            'paid_fee' => 0,
                        ]],
                        'updated' => [],
                        'deleted' => [],
                    ],
                ],
            ]);

            $newField = TourBookingAddonField::where('tour_booking_id', $bookingId)
                ->where('pax_index', 'shared-group-adult-3')->first();

            $this->assertNotNull($newField);
            $this->assertEquals(2, $newField->person_ordinal, 'The 3rd pax\'s own new field must carry ITS OWN ordinal (2), not the 2nd pax\'s (1)');
        } finally {
            $newFieldId = TourBookingAddonField::where('tour_booking_id', $bookingId)->where('pax_index', 'shared-group-adult-3')->value('id');
            TourBookingAddonTransaction::where('tour_booking_addon_id', $existingField->id)->forceDelete();
            if ($newFieldId) {
                TourBookingAddonTransaction::where('tour_booking_addon_id', $newFieldId)->forceDelete();
            }
            TourBookingAddonField::withTrashed()->where('tour_booking_id', $bookingId)->forceDelete();
            $booking->cleanup();
        }
    }

    /**
     * Regression: a Pay Later booking's add-ons are charged with transaction status 'open'
     * (see chargeAddonFields()) — they never become 'paid', since nothing is actually collected
     * until the booking itself is invoiced. A plain removal (no matching addition) previously
     * looked up the deleted field's "last charge" filtered to status='paid' only, which NEVER
     * matches a Pay Later item — so the "Don't Refund" history entry silently showed $0.00 for
     * both price and fee, and fell back to a hardcoded 'card' payment method even though the
     * booking was never paid by card at all. Payment method must show blank, not "Pay Later" —
     * nothing has actually been paid BY any method while the charge sits at status='open'.
     */
    public function test_pay_later_plain_removal_shows_real_price_and_correct_payment_method(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Pay Later')->create();
        $bookingId = $booking->bookingId;

        $photoField = AddonField::create([
            'form_id' => $this->formId,
            'label' => 'Photography Package',
            'type' => 'checkbox',
            'unit_type' => 'price_per_pax',
            'additional_fee' => true,
            'price' => 15.00,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);

        $existingField = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId,
            'form_id' => $this->formId,
            'field_id' => $photoField->id,
            'pax_id' => $booking->transactId,
            'field_label' => 'Photography Package',
            'pax_index' => 'pay-later-removal-test',
            'person_ordinal' => 0,
            'unit_type' => 'price_per_pax',
            'unit' => 1,
        ]);
        // Matches chargeAddonFields()'s Pay Later branch exactly: status 'open', no PaymentIntent.
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $existingField->id,
            'payment_method' => 'paylater', 'status' => 'open', 'operation' => 'charge',
            'transaction_id' => null, 'field_price' => 15.00, 'field_fee' => 0,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);

        try {
            $repo = app(TourBookingAddonRepository::class);
            $repo->savePaxAddons((object) [
                'booking_id' => $bookingId,
                'form_id' => $this->formId,
                'description' => null,
                'pax' => [
                    [
                        'pax_id' => $booking->transactId,
                        'pax_index' => 'pay-later-removal-test',
                        'person_ordinal' => 0,
                        'added' => [],
                        'updated' => [],
                        'deleted' => [['db_id' => $existingField->id, 'field_id' => $photoField->id]],
                    ],
                ],
            ]);

            $this->assertSoftDeleted('tour_booking_addon_fields', ['id' => $existingField->id]);

            $historyRow = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('operation', 'Refund')
                ->orderByDesc('id')
                ->first();
            $this->assertNotNull($historyRow, 'A history entry must exist for the removal');

            $details = json_decode($historyRow->details, true);
            $this->assertEquals('Photography Package', $details['addons'][0]['name']);
            $this->assertEquals(15.00, $details['addons'][0]['price'], 'The real add-on price must show, not $0.00');
            $this->assertEquals('', $details['payment_type'], 'Pay Later is not a real payment method — must be blank, not "Pay Later" or a hardcoded "Card" fallback');
        } finally {
            TourBookingAddonTransaction::where('tour_booking_addon_id', $existingField->id)->forceDelete();
            TourBookingAddonField::withTrashed()->where('tour_booking_id', $bookingId)->forceDelete();
            $booking->cleanup();
        }
    }

    /**
     * Regression: a Pay Later booking's add-ons selected at BOOKING CREATION time (checkout) get
     * NO tour_booking_addon_transactions row at all — not even 'open' — since
     * AddonBookingService::insertPricePerPaxBookingAddons() only writes the field row; a
     * transaction row is only ever added later, either by chargeAddonFields() (add-on added
     * AFTER creation) or by createAddonTransactionsFromFields() once the booking is actually
     * paid. So the previous test's fix (widening status to include 'open') isn't enough on its
     * own — $lastCharge is genuinely null here, and the code must fall back to the field's own
     * configured addon_fields.price instead of $0.00, and the booking's own payment method
     * instead of a hardcoded 'card' default.
     */
    public function test_pay_later_removal_with_no_transaction_row_at_all_shows_real_price(): void
    {
        // withoutTransaction() matches a GENUINELY never-paid Pay Later booking exactly: zero
        // rows in `transactions` at all (nothing has ever been charged), not just a missing
        // add-on transaction row. This is what actually exposed the payment-method fallback bug
        // — resolveBookingPaymentMethodLabel() must default to '' (not 'Card', and not "Pay
        // Later" either, since that isn't a real payment method) when it can't find ANY
        // transaction row to read `via` from.
        $booking = (new CreateABooking())->withPaymentMethod('Pay Later')->withoutTransaction()->create();
        $bookingId = $booking->bookingId;

        $photoField = AddonField::create([
            'form_id' => $this->formId,
            'label' => 'Photography Package',
            'type' => 'checkbox',
            'unit_type' => 'price_per_pax',
            'additional_fee' => true,
            'price' => 15.00,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);

        // Matches what AddonBookingService::insertPricePerPaxBookingAddons() actually leaves
        // behind at booking-creation time for a true Pay Later booking: the field row exists,
        // but NO tour_booking_addon_transactions row was ever created for it.
        $existingField = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId,
            'form_id' => $this->formId,
            'field_id' => $photoField->id,
            'pax_id' => $booking->transactId,
            'field_label' => 'Photography Package',
            'pax_index' => 'pay-later-no-txn-test',
            'person_ordinal' => 0,
            'unit_type' => 'price_per_pax',
            'unit' => 1,
        ]);

        try {
            $repo = app(TourBookingAddonRepository::class);
            $repo->savePaxAddons((object) [
                'booking_id' => $bookingId,
                'form_id' => $this->formId,
                'description' => null,
                'pax' => [
                    [
                        'pax_id' => $booking->transactId,
                        'pax_index' => 'pay-later-no-txn-test',
                        'person_ordinal' => 0,
                        'added' => [],
                        'updated' => [],
                        'deleted' => [['db_id' => $existingField->id, 'field_id' => $photoField->id]],
                    ],
                ],
            ]);

            $this->assertSoftDeleted('tour_booking_addon_fields', ['id' => $existingField->id]);

            $historyRow = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('operation', 'Refund')
                ->orderByDesc('id')
                ->first();
            $this->assertNotNull($historyRow, 'A history entry must exist for the removal');

            $details = json_decode($historyRow->details, true);
            $this->assertEquals('Photography Package', $details['addons'][0]['name']);
            $this->assertEquals(15.00, $details['addons'][0]['price'], 'Must fall back to the field\'s own configured price, not $0.00');
            $this->assertEquals('', $details['payment_type'], 'Pay Later is not a real payment method — must be blank, not "Pay Later" or a hardcoded "Card" fallback');
        } finally {
            TourBookingAddonField::withTrashed()->where('tour_booking_id', $bookingId)->forceDelete();
            $booking->cleanup();
        }
    }

    /**
     * Regression: a Pay Later booking's add-on picked at booking creation (checkout) has NO
     * tour_booking_addon_transactions row at all (see the previous test) — so when that pax's
     * selection is deselected in the SAME request that adds the IDENTICAL field/option to a
     * different pax, findReassignmentMatch()'s exact field_id+option_id loop matched and spliced
     * the deleted entry out of $deletedPool before checking lastCharge, so savePaxAddons()'s
     * `if ($match && $lastCharge)` correctly refused to log it as "Reassigned" (nothing was ever
     * charged to move) — but by then the entry was already gone from $deletedPool and never
     * reached the trailing "plain removal" loop either, so the deletion silently vanished from
     * the history audit trail: the history entry had an "added" pill but NO "removed" pill at
     * all for a real deselection.
     */
    public function test_pay_later_deselect_alongside_unrelated_addition_still_logs_the_removal(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Pay Later')->withoutTransaction()->create();
        $bookingId = $booking->bookingId;

        $photoField = AddonField::create([
            'form_id' => $this->formId,
            'label' => 'Photography Package',
            'type' => 'checkbox',
            'unit_type' => 'price_per_pax',
            'additional_fee' => true,
            'price' => 15.00,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);

        // "Adult 2"'s selection from booking creation — no transaction row at all, matching
        // AddonBookingService::insertPricePerPaxBookingAddons()'s Pay Later behavior.
        $existingField = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId,
            'form_id' => $this->formId,
            'field_id' => $photoField->id,
            'pax_id' => 800600,
            'field_label' => 'Photography Package',
            'pax_index' => 'deselect-test-adult-2',
            'person_ordinal' => 1,
            'unit_type' => 'price_per_pax',
            'unit' => 1,
        ]);

        try {
            $repo = app(TourBookingAddonRepository::class);
            $repo->savePaxAddons((object) [
                'booking_id' => $bookingId,
                'form_id' => $this->formId,
                'description' => null,
                'pax' => [
                    // "Adult 2" deselects the Photography Package.
                    [
                        'pax_id' => 800600,
                        'pax_index' => 'deselect-test-adult-2',
                        'person_ordinal' => 1,
                        'added' => [],
                        'updated' => [],
                        'deleted' => [['db_id' => $existingField->id, 'field_id' => $photoField->id]],
                    ],
                    // A brand-new "Adult (Ages 8 & Up)" pax (added moments earlier via Change
                    // People) separately gains the IDENTICAL field/option — same field_id, no
                    // option — which is what triggers findReassignmentMatch()'s exact-match loop,
                    // even though this pax's addition has nothing to do with Adult 2's removal.
                    [
                        'pax_id' => 800601,
                        'pax_index' => 'deselect-test-new-pax',
                        'person_ordinal' => 1,
                        'added' => [[
                            'field_id' => $photoField->id,
                            'option_id' => null,
                            'option_label' => null,
                            'value' => 'checked',
                            'unit_type' => 'price_per_pax',
                            'unit' => 1,
                            'rule_id' => null,
                            'applied_rule_snapshot' => null,
                            'paid_price' => 0,
                            'paid_fee' => 0,
                        ]],
                        'updated' => [],
                        'deleted' => [],
                    ],
                ],
            ]);

            $this->assertSoftDeleted('tour_booking_addon_fields', ['id' => $existingField->id]);

            $historyRow = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('operation', 'Refund')
                ->orderByDesc('id')
                ->first();
            $this->assertNotNull($historyRow, 'A history entry must exist for the deselect + unrelated addition');

            $details = json_decode($historyRow->details, true);
            $this->assertArrayHasKey('addons', $details, 'The deselected item must still appear as a "removed" line — it must not silently vanish just because an unrelated pax added the identical field');
            $this->assertNotEmpty($details['addons'], 'The deselected item must still appear as a "removed" line — it must not silently vanish just because an unrelated pax added the identical field');
            $this->assertEquals('Photography Package', $details['addons'][0]['name']);
            $this->assertEquals(15.00, $details['addons'][0]['price'], 'Must fall back to the field\'s own configured price, not $0.00');
            $this->assertEquals("Don't Refund", $details['addons'][0]['status'], 'Never actually charged, so it is a plain removal, not a "Reassigned" pair');

            $this->assertArrayHasKey('added_addons', $details);
            $this->assertEquals('Photography Package', $details['added_addons'][0]['name']);
        } finally {
            $newFieldId = TourBookingAddonField::where('tour_booking_id', $bookingId)->where('pax_id', 800601)->value('id');
            if ($newFieldId) {
                TourBookingAddonTransaction::where('tour_booking_addon_id', $newFieldId)->forceDelete();
            }
            TourBookingAddonField::withTrashed()->where('tour_booking_id', $bookingId)->forceDelete();
            $booking->cleanup();
        }
    }
}
