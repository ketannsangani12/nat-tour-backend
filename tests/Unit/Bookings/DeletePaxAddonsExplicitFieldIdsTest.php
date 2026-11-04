<?php

namespace Tests\Unit\Bookings;

use App\Constants\Constants;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\AddonField;
use App\Models\AddonForm;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingAddonTransaction;
use App\Repositories\TourBooking\TourBookingAddonRepository;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression for removing the "Passenger Add-ons" selection modal from the direct "Edit Booking"
 * delete flow: the "Update Booking" modal now submits explicit tour_booking_addon_fields ids
 * (delete_booking_addon_scope_ids) instead of routing through that modal to resolve opaque
 * pax_index strings. deletePaxAddons()'s new $explicitFieldIds param must scope both the refund
 * AND the hard-delete to exactly those fields, leaving any other field on the same booking (e.g.
 * a different person's add-on) completely untouched.
 */
class DeletePaxAddonsExplicitFieldIdsTest extends TestCase
{
    protected static array $createdFormIds = [];

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

    public function test_explicit_field_ids_refund_and_delete_only_the_named_fields(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        $fieldA = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId,
            'form_id' => 1,
            'field_id' => 1,
            'pax_id' => 700200,
            'field_label' => 'Souvenir T-Shirt',
            'option_id' => 1,
            'option_label' => 'Medium',
            'pax_index' => 'explicit-test-a',
            'unit_type' => 'price_per_pax',
            'unit' => 1,
        ]);
        $fieldB = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId,
            'form_id' => 1,
            'field_id' => 1,
            'pax_id' => 700200,
            'field_label' => 'Souvenir T-Shirt',
            'option_id' => 2,
            'option_label' => 'Small',
            'pax_index' => 'explicit-test-b',
            'unit_type' => 'price_per_pax',
            'unit' => 1,
        ]);

        try {
            $this->stubCardTransactionRow($bookingId, 'pi_explicit_a', 21.96);
            $this->stubCardTransactionRow($bookingId, 'pi_explicit_b', 21.96);

            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $fieldA->id,
                'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
                'transaction_id' => 'pi_explicit_a', 'field_price' => 0, 'field_fee' => 0,
                'option_price' => 20.00, 'option_fee' => 1.96,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);
            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $fieldB->id,
                'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
                'transaction_id' => 'pi_explicit_b', 'field_price' => 0, 'field_fee' => 0,
                'option_price' => 20.00, 'option_fee' => 1.96,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);

            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->expects($this->once())
                ->method('refundPayment')
                ->with($this->callback(function ($params) {
                    return $params['payment_intent'] === 'pi_explicit_a'
                        && $params['amount'] === 2196;
                }))
                ->willReturn((object) ['id' => 're_test_explicit_a']);
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            $repo = app(TourBookingAddonRepository::class);
            $repo->deletePaxAddons($bookingId, '[]', Constants::ADDONS_FULL_REFUND, null, null, [$fieldA->id]);

            $this->assertSoftDeleted('tour_booking_addon_fields', ['id' => $fieldA->id]);
            $this->assertDatabaseHas('tour_booking_addon_fields', [
                'id' => $fieldB->id,
                'deleted_at' => null,
            ]);
            $this->assertDatabaseHas('tour_booking_addon_transactions', [
                'tour_booking_addon_id' => $fieldA->id,
                'operation' => 'refund',
                'status' => 'refunded',
            ]);
            $this->assertDatabaseMissing('tour_booking_addon_transactions', [
                'tour_booking_addon_id' => $fieldB->id,
                'operation' => 'refund',
            ]);
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', [$fieldA->id, $fieldB->id])->forceDelete();
            TourBookingAddonField::withTrashed()->whereIn('id', [$fieldA->id, $fieldB->id])->forceDelete();
            $booking->cleanup();
        }
    }

    /**
     * Regression for booking 64670: refunding two fields charged on DIFFERENT PaymentIntents in
     * one deletePaxAddons() call must write a SEPARATE `transactions` row per PI, each with ONLY
     * that PI's own amount/fee/transaction_number — not the combined total of both PIs stamped
     * onto both rows (which is what a shared $totalRefundPrice/$lastTxn produced previously).
     */
    public function test_multiple_payment_intents_each_get_their_own_refund_amount(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        $fieldA = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1, 'pax_id' => 700300,
            'field_label' => 'Souvenir T-Shirt', 'option_id' => 1, 'option_label' => 'Medium',
            'pax_index' => 'multi-pi-test-a', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        $fieldB = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1, 'pax_id' => 700301,
            'field_label' => 'Souvenir T-Shirt', 'option_id' => 2, 'option_label' => 'Large',
            'pax_index' => 'multi-pi-test-b', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        try {
            $this->stubCardTransactionRow($bookingId, 'pi_multi_a', 21.96);
            $this->stubCardTransactionRow($bookingId, 'pi_multi_b', 24.16);

            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $fieldA->id,
                'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
                'transaction_id' => 'pi_multi_a', 'field_price' => 0, 'field_fee' => 0,
                'option_price' => 20.00, 'option_fee' => 1.96,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);
            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $fieldB->id,
                'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
                'transaction_id' => 'pi_multi_b', 'field_price' => 0, 'field_fee' => 0,
                'option_price' => 22.00, 'option_fee' => 2.16,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);

            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->expects($this->exactly(2))->method('refundPayment')->willReturn((object) ['id' => 're_test']);
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            $repo = app(TourBookingAddonRepository::class);
            $repo->deletePaxAddons($bookingId, '[]', Constants::ADDONS_FULL_REFUND, null, null, [$fieldA->id, $fieldB->id]);

            $this->assertDatabaseHas('transactions', [
                'tour_booking_id' => $bookingId,
                'transaction_number' => 'pi_multi_a',
                'amount' => 21.96,
                'fee_amount' => 1.96,
                'charge_type' => 'addons',
                'status' => 'Refund',
            ]);
            $this->assertDatabaseHas('transactions', [
                'tour_booking_id' => $bookingId,
                'transaction_number' => 'pi_multi_b',
                'amount' => 24.16,
                'fee_amount' => 2.16,
                'charge_type' => 'addons',
                'status' => 'Refund',
            ]);
            // Neither row may carry the OTHER PI's combined total — the exact bug being regression-tested.
            $this->assertDatabaseMissing('transactions', [
                'tour_booking_id' => $bookingId,
                'transaction_number' => 'pi_multi_a',
                'amount' => 46.12,
            ]);
            $this->assertDatabaseMissing('transactions', [
                'tour_booking_id' => $bookingId,
                'transaction_number' => 'pi_multi_b',
                'amount' => 46.12,
            ]);
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', [$fieldA->id, $fieldB->id])->forceDelete();
            TourBookingAddonField::withTrashed()->whereIn('id', [$fieldA->id, $fieldB->id])->forceDelete();
            DB::table('transactions')->where('tour_booking_id', $bookingId)->where('charge_type', 'addons')->delete();
            $booking->cleanup();
        }
    }

    public function test_empty_explicit_field_ids_is_a_no_op(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        try {
            $repo = app(TourBookingAddonRepository::class);
            $result = $repo->deletePaxAddons($bookingId, '[]', Constants::ADDONS_FULL_REFUND, null, null, []);

            $this->assertTrue($result);
        } finally {
            $booking->cleanup();
        }
    }

    /**
     * Regression for booking 64903: removing an add-on with refund type "Don't Refund" (the "+"
     * icon standalone delete-addon flow) hard-deletes the field but previously created ZERO
     * audit trail — no tour_booking_addon_transactions row (expected, no money moves) AND no
     * tour_booking_history entry either (not expected — every removal should leave a trace).
     * The history entry must show $0 refunded (so it never inflates the "Refund Amount" summary,
     * which sums real refunded amounts only) while still naming the removed item for context.
     */
    public function test_dont_refund_still_creates_a_history_entry(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        $field = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1, 'pax_id' => 700400,
            'field_label' => 'Souvenir T-Shirt', 'option_id' => 1, 'option_label' => 'Large',
            'pax_index' => 'dont-refund-test', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        try {
            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $field->id,
                'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
                'transaction_id' => 'pi_dont_refund', 'field_price' => 0, 'field_fee' => 0,
                'option_price' => 22.00, 'option_fee' => 2.16,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);

            $repo = app(TourBookingAddonRepository::class);
            $repo->deletePaxAddons($bookingId, json_encode(['dont-refund-test']), Constants::ADDONS_DONT_REFUND);

            $this->assertSoftDeleted('tour_booking_addon_fields', ['id' => $field->id]);
            // A same-amount 'refunded' settlement row MUST now exist — no money actually moved to
            // the customer (see the history assertions below, still $0), but without this row the
            // original 'paid' charge stays unmatched and a LATER cancel-booking refund sweep has no
            // way to know this exact charge was already finally decided ("kept, not refunded") and
            // pays it out anyway. Matches the convention ChangePeopleAddonService::
            // settleAddonTransactions() already uses for its own 'dont_refund' branch.
            $settlementRow = TourBookingAddonTransaction::where('tour_booking_addon_id', $field->id)
                ->where('operation', 'refund')
                ->first();
            $this->assertNotNull($settlementRow, 'A settlement row must be recorded so this charge nets to $0 and is never refunded again');
            $this->assertEquals(0.0, (float) $settlementRow->field_price);
            $this->assertEquals(22.00, (float) $settlementRow->option_price);
            $this->assertEquals(2.16, (float) $settlementRow->option_fee);
            // But a history entry MUST exist, with $0 ticket_cost/platform_fees (doesn't move the
            // "Refund Amount" summary) while naming the removed item in its details JSON.
            $historyRow = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('operation', 'Refund')
                ->orderByDesc('id')
                ->first();
            $this->assertNotNull($historyRow, 'A history entry must be created even when refund type is Don\'t Refund');
            $this->assertEquals(0.0, (float) $historyRow->ticket_cost);
            $this->assertEquals(0.0, (float) $historyRow->platform_fees);
            $details = json_decode($historyRow->details, true);
            // "Field — Option" — the fixture's option_label ('Large') must not be silently
            // dropped, matching the ChangePeopleAddonService/AddonBookingService convention for
            // the identical situation elsewhere.
            $this->assertEquals('Souvenir T-Shirt — Large', $details['addons'][0]['name']);
            // Matches ChangePeopleAddonService/processAddonFinancialTransactions()'s existing wording
            // for a kept-not-refunded item, not an invented label.
            $this->assertEquals("Don't Refund", $details['addons'][0]['status']);
            $this->assertEquals(22.00, $details['addons'][0]['price']);
            // The raw Constants::ADDONS_DONT_REFUND slug is stored — app/helpers.php's
            // resolveRefundData() translates it to "Don't Refund" for display at render time.
            $this->assertEquals('dont_refund', $details['refund_type']);
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', [$field->id])->forceDelete();
            TourBookingAddonField::withTrashed()->whereIn('id', [$field->id])->forceDelete();
            $booking->cleanup();
        }
    }

    /**
     * Regression: same "Don't Refund still creates a history entry" scenario as above, but for a
     * Pay Later booking, whose add-on charge is stored with status 'open' (never 'paid' — nothing
     * is actually collected until the booking itself is invoiced). The "not refunded fields"
     * history lookup filtered to status='paid' only, so a Pay Later field's removal here NEVER
     * found its own charge row — showing $0.00 price/fee and falling back to a hardcoded 'card'
     * payment method in the history entry, on a booking that was never paid by card at all.
     * Payment method must show blank, not "Pay Later" — nothing has actually been paid BY any
     * method while the charge sits at status='open', so "Pay Later" isn't a real payment method
     * to display as one.
     */
    public function test_pay_later_dont_refund_shows_real_price_and_correct_payment_method(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Pay Later')->create();
        $bookingId = $booking->bookingId;

        $field = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1, 'pax_id' => 700401,
            'field_label' => 'Souvenir T-Shirt', 'option_id' => 1, 'option_label' => 'Large',
            'pax_index' => 'pay-later-dont-refund-test', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        try {
            // Matches chargeAddonFields()'s Pay Later branch exactly: status 'open', no PaymentIntent.
            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $field->id,
                'payment_method' => 'paylater', 'status' => 'open', 'operation' => 'charge',
                'transaction_id' => null, 'field_price' => 0, 'field_fee' => 0,
                'option_price' => 22.00, 'option_fee' => 0,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);

            $repo = app(TourBookingAddonRepository::class);
            $repo->deletePaxAddons($bookingId, json_encode(['pay-later-dont-refund-test']), Constants::ADDONS_DONT_REFUND);

            $this->assertSoftDeleted('tour_booking_addon_fields', ['id' => $field->id]);
            $this->assertDatabaseMissing('tour_booking_addon_transactions', [
                'tour_booking_addon_id' => $field->id,
                'operation' => 'refund',
            ]);

            $historyRow = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('operation', 'Refund')
                ->orderByDesc('id')
                ->first();
            $this->assertNotNull($historyRow, 'A history entry must be created even when refund type is Don\'t Refund');
            $details = json_decode($historyRow->details, true);
            // "Field — Option" — the fixture's option_label ('Large') must not be silently dropped.
            $this->assertEquals('Souvenir T-Shirt — Large', $details['addons'][0]['name']);
            $this->assertEquals(22.00, $details['addons'][0]['price'], 'The real add-on price must show, not $0.00');
            $this->assertEquals('', $details['payment_type'], 'Pay Later is not a real payment method — must be blank, not "Pay Later" or a hardcoded "Card" fallback');
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', [$field->id])->forceDelete();
            TourBookingAddonField::withTrashed()->whereIn('id', [$field->id])->forceDelete();
            $booking->cleanup();
        }
    }

    /**
     * Regression: matches what actually happens for a Pay Later booking's add-ons selected at
     * BOOKING CREATION time (checkout) — AddonBookingService::insertPricePerPaxBookingAddons()
     * only writes the tour_booking_addon_fields row; NO tour_booking_addon_transactions row is
     * created at all (not even 'open') until either chargeAddonFields() (add-on added later) or
     * a real "Make Payment" settles it. So the previous test's fix (widening status to include
     * 'open') isn't enough on its own here — $lastCharge is genuinely null, and the "not
     * refunded" history entry must fall back to the field's own configured addon_fields.price
     * and the booking's real payment method (from its `transactions` row), not $0.00/'card'.
     */
    public function test_pay_later_removal_with_no_transaction_row_at_all_shows_real_price(): void
    {
        // withoutTransaction() matches a GENUINELY never-paid Pay Later booking exactly: zero
        // rows in `transactions` at all — resolveBookingPaymentMethodLabel() must default to ''
        // (not 'Card', and not "Pay Later" either, since that isn't a real payment method) when
        // there's no transaction row to read `via` from at all.
        $booking = (new CreateABooking())->withPaymentMethod('Pay Later')->withoutTransaction()->create();
        $bookingId = $booking->bookingId;

        // addon_fields.form_id is FK'd to addon_forms — a real row is required (unlike the other
        // tests in this file, which only ever create tour_booking_addon_fields rows, a different,
        // unconstrained table, so their plain 'form_id' => 1 placeholder is fine).
        $addonForm = AddonForm::create([
            'tour_package_id' => $booking->packageId,
            'name' => 'Test Addon Form ' . uniqid(),
            'status' => 'Active',
        ]);
        static::$createdFormIds[] = $addonForm->id;

        $addonField = AddonField::create([
            'form_id' => $addonForm->id,
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
        // behind at booking-creation time: the field row exists, no transaction row at all.
        $field = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $addonForm->id, 'field_id' => $addonField->id, 'pax_id' => 700402,
            'field_label' => 'Photography Package',
            'pax_index' => 'pay-later-no-txn-delete-test', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        try {
            $repo = app(TourBookingAddonRepository::class);
            $repo->deletePaxAddons($bookingId, json_encode(['pay-later-no-txn-delete-test']), Constants::ADDONS_DONT_REFUND);

            $this->assertSoftDeleted('tour_booking_addon_fields', ['id' => $field->id]);

            $historyRow = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('operation', 'Refund')
                ->orderByDesc('id')
                ->first();
            $this->assertNotNull($historyRow, 'A history entry must be created even when refund type is Don\'t Refund');
            $details = json_decode($historyRow->details, true);
            $this->assertEquals('Photography Package', $details['addons'][0]['name']);
            $this->assertEquals(15.00, $details['addons'][0]['price'], 'Must fall back to the field\'s own configured price, not $0.00');
            $this->assertEquals('', $details['payment_type'], 'Pay Later is not a real payment method — must be blank, not "Pay Later" or a hardcoded "Card" fallback');
        } finally {
            TourBookingAddonField::withTrashed()->whereIn('id', [$field->id])->forceDelete();
            $booking->cleanup();
        }
    }

    /**
     * Regression: paymentMethodLabel()'s "is this really Pay Later" check compared the raw
     * ledger value against the literal string 'paylater' (no space) — chargeAddonFields()'s own
     * convention. But processAddonFinancialTransactions() (used by the "Add Passenger Add-ons"
     * popup's actual charge path) stores strtolower($financial['payment_method']), and the
     * frontend sends the booking's real "Pay Later" string, space included — so THAT convention's
     * ledger rows are stored as 'pay later' (with a space). The un-normalized comparison only
     * matched one of the two conventions; the other fell through to ucwords('pay later') and
     * displayed "Pay Later" as if it were a real payment method when this add-on was later
     * deleted via the "Passenger Add-ons" popup.
     */
    public function test_pay_later_removal_of_an_addon_charged_via_the_space_variant_shows_blank_payment_method(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Pay Later')->create();
        $bookingId = $booking->bookingId;

        $field = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1, 'pax_id' => 700403,
            'field_label' => 'Photography Package',
            'pax_index' => 'pay-later-space-variant-delete-test', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        try {
            // Matches processAddonFinancialTransactions()'s Pay Later charge branch exactly:
            // strtolower('Pay Later') === 'pay later', WITH the space.
            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $field->id,
                'payment_method' => 'pay later', 'status' => 'open', 'operation' => 'charge',
                'transaction_id' => null, 'field_price' => 15.00, 'field_fee' => 0,
                'option_price' => 0, 'option_fee' => 0,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);

            $repo = app(TourBookingAddonRepository::class);
            $repo->deletePaxAddons($bookingId, json_encode(['pay-later-space-variant-delete-test']), Constants::ADDONS_DONT_REFUND);

            $this->assertSoftDeleted('tour_booking_addon_fields', ['id' => $field->id]);

            $historyRow = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('operation', 'Refund')
                ->orderByDesc('id')
                ->first();
            $this->assertNotNull($historyRow, 'A history entry must be created even when refund type is Don\'t Refund');
            $details = json_decode($historyRow->details, true);
            $this->assertEquals(15.00, $details['addons'][0]['price'], 'The real add-on price must show, not $0.00');
            $this->assertEquals('', $details['payment_type'], 'Pay Later is not a real payment method — must be blank, not "Pay Later"');
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', [$field->id])->forceDelete();
            TourBookingAddonField::withTrashed()->whereIn('id', [$field->id])->forceDelete();
            $booking->cleanup();
        }
    }

    /**
     * Regression for booking KEN-348634: a non-priced price_per_pax field (a plain Yes/No or text
     * question with no price/fee attached, no Stripe money ever moved for it) landed in the "not
     * refunded" branch same as any priced field kept-but-deselected, and got its own $0.00/"Don't
     * Refund" tour_booking_history entry with nothing real to show — pure noise, unlike the
     * one_time/price_per_unit flow's recordZeroNetAddonHistory(), which silently skips creating any
     * history entry when nothing priced changed. The field must still be hard-deleted; only the
     * empty history entry must disappear.
     */
    public function test_dont_refund_of_a_non_priced_field_creates_no_history_entry(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        $addonForm = AddonForm::create([
            'tour_package_id' => $booking->packageId,
            'name' => 'Test Non-Priced Addon Form ' . uniqid(),
            'status' => 'Active',
        ]);
        static::$createdFormIds[] = $addonForm->id;

        $addonField = AddonField::create([
            'form_id' => $addonForm->id,
            'label' => 'Would you like to include a campfire breakfast and dinner?',
            'type' => 'radio',
            'unit_type' => 'price_per_pax',
            'additional_fee' => false,
            'price' => 0,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);

        // No tour_booking_addon_transactions row at all — matches a genuinely non-priced field
        // exactly like a never-charged Pay Later field (see the test above): nothing was ever
        // chargeable for it, so resolveConfiguredAddonPrice() is the only source of its price/fee,
        // and correctly resolves to 0/0 here since additional_fee is false.
        $field = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $addonForm->id, 'field_id' => $addonField->id, 'pax_id' => 700404,
            'field_label' => 'Would you like to include a campfire breakfast and dinner?',
            'option_label' => 'Yes',
            'pax_index' => 'non-priced-dont-refund-test', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        try {
            $historyCountBefore = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('operation', 'Refund')
                ->count();

            $repo = app(TourBookingAddonRepository::class);
            $repo->deletePaxAddons($bookingId, json_encode(['non-priced-dont-refund-test']), Constants::ADDONS_DONT_REFUND);

            $this->assertSoftDeleted('tour_booking_addon_fields', ['id' => $field->id]);

            $historyCountAfter = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('operation', 'Refund')
                ->count();
            $this->assertEquals(
                $historyCountBefore,
                $historyCountAfter,
                'Deleting a non-priced field must create NO history entry at all — nothing real to show'
            );
        } finally {
            TourBookingAddonField::withTrashed()->whereIn('id', [$field->id])->forceDelete();
            $booking->cleanup();
        }
    }
}
