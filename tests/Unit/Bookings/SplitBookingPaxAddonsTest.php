<?php

namespace Tests\Unit\Bookings;

use App\Constants\Constants;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingAddonTransaction;
use App\Repositories\TourBooking\TourBookingAddonRepository;
use App\Services\Booking\AddonBookingService;
use App\Services\Booking\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Split Booking now moves a price-per-pax add-on's MONEY along with its field row: an Add-ons
 * Refund entry on the old booking and an Add-ons Payment entry on the new booking, mirroring how
 * the tour amount's own refund-on-old/payment-on-new bookkeeping pair already works for a split.
 * Previously AddonBookingService::splitBookingPaxAddons() only reassigned the field row with no
 * ledger entries at all — the old booking silently kept the charge, the new booking showed the
 * add-on with nothing paid for it.
 */
class SplitBookingPaxAddonsTest extends TestCase
{
    protected function createOldAndNewBookings(int $tourists = 1): array
    {
        $oldBooking = (new CreateABooking())->withTourists($tourists)->withPaymentMethod('Card')->create();
        $newBooking = (new CreateABooking())
            ->usePackage($oldBooking->packageId)
            ->useRate($oldBooking->rateId)
            ->useSlotTime($oldBooking->slotTimeId)
            ->useCustomer($oldBooking->customerId)
            ->withPaymentMethod('Card')
            ->create();

        return [$oldBooking, $newBooking];
    }

    /**
     * A real Card PI always has a matching `transactions` row in production (written by
     * TransactionService at charge time) — Payout Simplification's refund-mode lookup depends on
     * that row existing, so a synthetic addon PI used only in `tour_booking_addon_transactions`
     * needs one too. Rides along with the booking's own cleanup (deletes by tour_booking_id).
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

    public function test_money_follows_the_pax_to_the_new_booking(): void
    {
        [$oldBooking, $newBooking] = $this->createOldAndNewBookings();

        $field = TourBookingAddonField::create([
            'tour_booking_id' => $oldBooking->bookingId,
            'form_id' => 1,
            'field_id' => 1,
            'pax_id' => $oldBooking->transactId,
            'field_label' => 'Souvenir T-Shirt',
            'option_id' => 1,
            'option_label' => 'Medium',
            'pax_index' => 'split-test-a',
            'unit_type' => 'price_per_pax',
            'unit' => 1,
        ]);

        try {
            TourBookingAddonTransaction::create([
                'tour_booking_id' => $oldBooking->bookingId, 'tour_booking_addon_id' => $field->id,
                'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
                'transaction_id' => 'pi_split_a', 'application_fee_id' => 'fee_split_a',
                'field_price' => 0, 'field_fee' => 0,
                'option_price' => 20.00, 'option_fee' => 1.96,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);
            // The original booking-time addons payment ledger row — real bookings always have
            // this; addons_gross_* is sourced from it, so omitting it would understate gross.
            DB::table('transactions')->insert([
                'tour_booking_id' => $oldBooking->bookingId, 'amount' => 21.96, 'fee_amount' => 1.96,
                'transaction_number' => 'pi_split_a', 'application_fee_id' => 'fee_split_a',
                'charge_type' => 'addons', 'status' => 'Completed', 'via' => 'Card',
                'payment_type' => 0, 'created_by' => 1, 'created_by_user_type' => 'Admin',
                'created_at' => now(),
            ]);

            AddonBookingService::splitBookingPaxAddons($oldBooking->bookingId, $newBooking->bookingId, [$field->id]);

            // Field row moved to the new booking / new pax.
            $this->assertDatabaseHas('tour_booking_addon_fields', [
                'id' => $field->id,
                'tour_booking_id' => $newBooking->bookingId,
                'pax_id' => $newBooking->transactId,
            ]);

            // Old booking: Add-ons Refund entry (bookkeeping only, no real Stripe refund).
            $this->assertDatabaseHas('tour_booking_addon_transactions', [
                'tour_booking_id' => $oldBooking->bookingId,
                'tour_booking_addon_id' => $field->id,
                'operation' => 'refund',
                'status' => 'refunded',
                'option_price' => 20.00,
                'option_fee' => 1.96,
            ]);
            $this->assertDatabaseHas('transactions', [
                'tour_booking_id' => $oldBooking->bookingId,
                'charge_type' => 'addons',
                'status' => 'Refund',
                'amount' => 21.96,
                'fee_amount' => 1.96,
                'application_fee_id' => 'fee_split_a',
            ]);
            $this->assertDatabaseHas('tour_booking_history', [
                'tour_booking_id' => $oldBooking->bookingId,
                'charge_type' => 'addons',
                'operation' => 'Refund',
            ]);

            // New booking: Add-ons Payment entry, reusing the same PaymentIntent id as the charge.
            $this->assertDatabaseHas('tour_booking_addon_transactions', [
                'tour_booking_id' => $newBooking->bookingId,
                'tour_booking_addon_id' => $field->id,
                'operation' => 'charge',
                'status' => 'paid',
                'transaction_id' => 'pi_split_a',
                'option_price' => 20.00,
                'option_fee' => 1.96,
            ]);
            $this->assertDatabaseHas('transactions', [
                'tour_booking_id' => $newBooking->bookingId,
                'charge_type' => 'addons',
                'status' => 'Completed',
                'amount' => 21.96,
                'fee_amount' => 1.96,
                // Regression: application_fee_id must carry through even though split never
                // calls Stripe (no real PaymentIntent object to read it off of) — it was
                // previously silently dropped to null on the new booking's payment row.
                'application_fee_id' => 'fee_split_a',
            ]);
            $this->assertDatabaseHas('tour_booking_history', [
                'tour_booking_id' => $newBooking->bookingId,
                'charge_type' => 'addons',
                'operation' => 'Payment',
            ]);

            // Convention check: old booking nets to 0, new booking shows the moved amount — the
            // same positive-refund-row convention BookingService::fetchAddonsWithPrice() and
            // DeleteBookingAddonBreakdownService already net charge-minus-refund against.
            $oldTotals = BookingService::fetchAddonsWithPrice($oldBooking->bookingId);
            $newTotals = BookingService::fetchAddonsWithPrice($newBooking->bookingId);
            $this->assertEquals(0.0, round($oldTotals['addons_final_total'], 2));
            $this->assertEquals(21.96, round($newTotals['addons_final_total'], 2));

            // Gross intentionally stays at its original, never-decremented value on the OLD
            // booking even after a split (matching booking_total's own "gross forever" behavior
            // on the tour side, which can't safely be changed — see the cancellation/refund
            // services that key off booking_total staying at its original value). The NEW
            // booking's own fresh 'Completed' row also counts as gross for its own total — the
            // two bookings' gross figures are allowed to overlap for the split-moved amount,
            // same as booking_total already does.
            $this->assertEquals(21.96, round($oldTotals['addons_gross_total'], 2));
            $this->assertEquals(21.96, round($newTotals['addons_gross_total'], 2));
        } finally {
            TourBookingAddonTransaction::withTrashed()->where('tour_booking_addon_id', $field->id)->forceDelete();
            TourBookingAddonField::withTrashed()->where('id', $field->id)->forceDelete();
            DB::table('transactions')
                ->whereIn('tour_booking_id', [$oldBooking->bookingId, $newBooking->bookingId])
                ->where('charge_type', 'addons')
                ->delete();
            $newBooking->cleanup();
            $oldBooking->cleanup();
        }
    }

    /**
     * Regression guard mirroring DeletePaxAddonsExplicitFieldIdsTest's multi-PI coverage: two
     * fields charged on DIFFERENT PaymentIntents must each get their own settlement amount on
     * both sides, not one PI's total bleeding onto the other's row.
     */
    public function test_multiple_payment_intents_each_get_their_own_settlement(): void
    {
        [$oldBooking, $newBooking] = $this->createOldAndNewBookings();

        $fieldA = TourBookingAddonField::create([
            'tour_booking_id' => $oldBooking->bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => $oldBooking->transactId, 'field_label' => 'Souvenir T-Shirt',
            'option_id' => 1, 'option_label' => 'Medium', 'pax_index' => 'split-multi-a',
            'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        $fieldB = TourBookingAddonField::create([
            'tour_booking_id' => $oldBooking->bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => $oldBooking->transactId, 'field_label' => 'Souvenir T-Shirt',
            'option_id' => 2, 'option_label' => 'Large', 'pax_index' => 'split-multi-b',
            'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        try {
            $this->stubCardTransactionRow($oldBooking->bookingId, 'pi_multi_a', 21.96);
            $this->stubCardTransactionRow($oldBooking->bookingId, 'pi_multi_b', 24.16);

            TourBookingAddonTransaction::create([
                'tour_booking_id' => $oldBooking->bookingId, 'tour_booking_addon_id' => $fieldA->id,
                'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
                'transaction_id' => 'pi_multi_a', 'field_price' => 0, 'field_fee' => 0,
                'option_price' => 20.00, 'option_fee' => 1.96,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);
            TourBookingAddonTransaction::create([
                'tour_booking_id' => $oldBooking->bookingId, 'tour_booking_addon_id' => $fieldB->id,
                'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
                'transaction_id' => 'pi_multi_b', 'field_price' => 0, 'field_fee' => 0,
                'option_price' => 22.00, 'option_fee' => 2.16,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);

            AddonBookingService::splitBookingPaxAddons($oldBooking->bookingId, $newBooking->bookingId, [$fieldA->id, $fieldB->id]);

            $this->assertDatabaseHas('transactions', [
                'tour_booking_id' => $oldBooking->bookingId, 'charge_type' => 'addons', 'status' => 'Refund',
                'transaction_number' => 'pi_multi_a', 'amount' => 21.96, 'fee_amount' => 1.96,
            ]);
            $this->assertDatabaseHas('transactions', [
                'tour_booking_id' => $oldBooking->bookingId, 'charge_type' => 'addons', 'status' => 'Refund',
                'transaction_number' => 'pi_multi_b', 'amount' => 24.16, 'fee_amount' => 2.16,
            ]);
            $this->assertDatabaseHas('transactions', [
                'tour_booking_id' => $newBooking->bookingId, 'charge_type' => 'addons', 'status' => 'Completed',
                'transaction_number' => 'pi_multi_a', 'amount' => 21.96, 'fee_amount' => 1.96,
            ]);
            $this->assertDatabaseHas('transactions', [
                'tour_booking_id' => $newBooking->bookingId, 'charge_type' => 'addons', 'status' => 'Completed',
                'transaction_number' => 'pi_multi_b', 'amount' => 24.16, 'fee_amount' => 2.16,
            ]);
            // Neither PI's row may carry the OTHER PI's combined total.
            $this->assertDatabaseMissing('transactions', [
                'tour_booking_id' => $oldBooking->bookingId, 'transaction_number' => 'pi_multi_a', 'amount' => 46.12,
            ]);
            $this->assertDatabaseMissing('transactions', [
                'tour_booking_id' => $newBooking->bookingId, 'transaction_number' => 'pi_multi_a', 'amount' => 46.12,
            ]);
        } finally {
            TourBookingAddonTransaction::withTrashed()->whereIn('tour_booking_addon_id', [$fieldA->id, $fieldB->id])->forceDelete();
            TourBookingAddonField::withTrashed()->whereIn('id', [$fieldA->id, $fieldB->id])->forceDelete();
            DB::table('transactions')
                ->whereIn('tour_booking_id', [$oldBooking->bookingId, $newBooking->bookingId])
                ->where('charge_type', 'addons')
                ->delete();
            $newBooking->cleanup();
            $oldBooking->cleanup();
        }
    }

    /**
     * Regression guard mirroring refundAddonsByBookingId()'s own "one settlement action, one
     * entry" rule: Cash/Invoiced/Wire add-on charges have no real PaymentIntent, so grouping by
     * transaction_id alone (falling back to a per-field key) put every field in its own group —
     * splitting N such fields together wrote N separate refund/payment history entries instead of
     * one combined entry each, even though they were all settled the same way at once.
     */
    public function test_non_card_fields_settle_as_one_combined_entry(): void
    {
        [$oldBooking, $newBooking] = $this->createOldAndNewBookings();

        $fieldA = TourBookingAddonField::create([
            'tour_booking_id' => $oldBooking->bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => $oldBooking->transactId, 'field_label' => 'Photography Package',
            'pax_index' => 'split-invoiced-a', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        $fieldB = TourBookingAddonField::create([
            'tour_booking_id' => $oldBooking->bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => $oldBooking->transactId, 'field_label' => 'Photography Package',
            'pax_index' => 'split-invoiced-b', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        try {
            foreach ([$fieldA, $fieldB] as $field) {
                TourBookingAddonTransaction::create([
                    'tour_booking_id' => $oldBooking->bookingId, 'tour_booking_addon_id' => $field->id,
                    'payment_method' => 'invoiced', 'status' => 'paid', 'operation' => 'charge',
                    'transaction_id' => null, 'field_price' => 15.00, 'field_fee' => 0,
                    'option_price' => 0, 'option_fee' => 0,
                    'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
                ]);
            }

            AddonBookingService::splitBookingPaxAddons($oldBooking->bookingId, $newBooking->bookingId, [$fieldA->id, $fieldB->id]);

            // One combined Refund ledger/history entry on the old booking totalling both fields —
            // not two $15 entries.
            $this->assertEquals(1, DB::table('transactions')
                ->where('tour_booking_id', $oldBooking->bookingId)
                ->where('charge_type', 'addons')->where('status', 'Refund')->count());
            $this->assertDatabaseHas('transactions', [
                'tour_booking_id' => $oldBooking->bookingId, 'charge_type' => 'addons',
                'status' => 'Refund', 'amount' => 30.00,
            ]);
            $this->assertEquals(1, DB::table('tour_booking_history')
                ->where('tour_booking_id', $oldBooking->bookingId)
                ->where('charge_type', 'addons')->where('operation', 'Refund')->count());

            // One combined Payment ledger/history entry on the new booking totalling both fields.
            $this->assertEquals(1, DB::table('transactions')
                ->where('tour_booking_id', $newBooking->bookingId)
                ->where('charge_type', 'addons')->where('status', 'Completed')->count());
            $this->assertDatabaseHas('transactions', [
                'tour_booking_id' => $newBooking->bookingId, 'charge_type' => 'addons',
                'status' => 'Completed', 'amount' => 30.00,
            ]);
            $this->assertEquals(1, DB::table('tour_booking_history')
                ->where('tour_booking_id', $newBooking->bookingId)
                ->where('charge_type', 'addons')->where('operation', 'Payment')->count());
        } finally {
            TourBookingAddonTransaction::withTrashed()->whereIn('tour_booking_addon_id', [$fieldA->id, $fieldB->id])->forceDelete();
            TourBookingAddonField::withTrashed()->whereIn('id', [$fieldA->id, $fieldB->id])->forceDelete();
            DB::table('transactions')
                ->whereIn('tour_booking_id', [$oldBooking->bookingId, $newBooking->bookingId])
                ->where('charge_type', 'addons')
                ->delete();
            $newBooking->cleanup();
            $oldBooking->cleanup();
        }
    }

    public function test_empty_scope_is_a_no_op(): void
    {
        [$oldBooking, $newBooking] = $this->createOldAndNewBookings();

        try {
            AddonBookingService::splitBookingPaxAddons($oldBooking->bookingId, $newBooking->bookingId, []);

            $this->assertDatabaseMissing('tour_booking_addon_transactions', ['tour_booking_id' => $newBooking->bookingId]);
            $this->assertDatabaseMissing('transactions', ['tour_booking_id' => $newBooking->bookingId, 'charge_type' => 'addons']);
        } finally {
            $newBooking->cleanup();
            $oldBooking->cleanup();
        }
    }

    /**
     * resolveSplitAddonFieldIds() must translate a 0-based position within $checkarrays (the same
     * convention "check[]"'s data-pax-index checkboxes use) into the real field ids for THAT
     * specific pax, in the same creation-order-derived position numbering
     * DeleteBookingAddonBreakdownService::buildPaxIndexPositionFromSurvivingItems() establishes.
     */
    public function test_resolve_split_addon_field_ids_matches_check_array_position(): void
    {
        $booking = (new CreateABooking())->withTourists(2)->withPaymentMethod('Card')->create();

        $fieldFirstPax = TourBookingAddonField::create([
            'tour_booking_id' => $booking->bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => $booking->transactId, 'field_label' => 'Souvenir T-Shirt',
            'pax_index' => 'resolve-test-pax-0', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        $fieldSecondPax = TourBookingAddonField::create([
            'tour_booking_id' => $booking->bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => $booking->transactId, 'field_label' => 'Souvenir T-Shirt',
            'pax_index' => 'resolve-test-pax-1', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        try {
            $resolved = AddonBookingService::resolveSplitAddonFieldIds($booking->bookingId, [
                $booking->rateId => [1],
            ]);

            $this->assertEquals([$fieldSecondPax->id], $resolved);

            $resolvedBoth = AddonBookingService::resolveSplitAddonFieldIds($booking->bookingId, [
                $booking->rateId => [0, 1],
            ]);
            sort($resolvedBoth);
            $expectedBoth = [$fieldFirstPax->id, $fieldSecondPax->id];
            sort($expectedBoth);
            $this->assertEquals($expectedBoth, $resolvedBoth);
        } finally {
            TourBookingAddonField::withTrashed()->whereIn('id', [$fieldFirstPax->id, $fieldSecondPax->id])->forceDelete();
            $booking->cleanup();
        }
    }

    /**
     * Regression: when only SOME pax in a rate group have add-ons (e.g. add-ons purchased for only
     * the last 4 of 8 travelers), resolveSplitAddonFieldIds() must still map a checkbox position to
     * the CORRECT physical pax via person_ordinal — not to "the Nth pax_index that happens to have
     * an add-on row", which silently shifts every position once earlier pax have none at all.
     */
    public function test_resolve_split_addon_field_ids_handles_sparse_addon_ownership(): void
    {
        $booking = (new CreateABooking())->withTourists(8)->withPaymentMethod('Card')->create();

        // Only pax #5, #6, #7, #8 (0-based person_ordinal 4, 5, 6, 7) have an add-on.
        $fields = [];
        foreach ([4, 5, 6, 7] as $ordinal) {
            $fields[$ordinal] = TourBookingAddonField::create([
                'tour_booking_id' => $booking->bookingId, 'form_id' => 1, 'field_id' => 1,
                'pax_id' => $booking->transactId, 'field_label' => 'Photography Package',
                'pax_index' => "sparse-test-pax-{$ordinal}", 'person_ordinal' => $ordinal,
                'unit_type' => 'price_per_pax', 'unit' => 1,
            ]);
        }

        try {
            // Split off positions 0, 1, 2 (no add-ons) and 4 (pax #5's add-on).
            $resolved = AddonBookingService::resolveSplitAddonFieldIds($booking->bookingId, [
                $booking->rateId => [0, 1, 2, 4],
            ]);

            $this->assertEquals([$fields[4]->id], $resolved);

            // Position 5 (pax #6) must resolve to pax #6's field alone.
            $resolvedSecond = AddonBookingService::resolveSplitAddonFieldIds($booking->bookingId, [
                $booking->rateId => [5],
            ]);
            $this->assertEquals([$fields[5]->id], $resolvedSecond);
        } finally {
            TourBookingAddonField::withTrashed()->whereIn('id', collect($fields)->pluck('id'))->forceDelete();
            $booking->cleanup();
        }
    }

    public function test_resolve_split_addon_field_ids_returns_empty_for_addon_less_booking(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();

        try {
            $resolved = AddonBookingService::resolveSplitAddonFieldIds($booking->bookingId, [
                $booking->rateId => [0],
            ]);

            $this->assertEquals([], $resolved);
        } finally {
            $booking->cleanup();
        }
    }

    /**
     * Regression: once a field's tour_booking_id can change lifetime (via a split), any lookup
     * keyed purely on tour_booking_addon_id (not also tour_booking_id) sums the field's pre-split
     * charge row alongside its post-split one — the exact bug that showed a moved $22 shirt as
     * $44 in the "Passenger Add-ons" modal on the new booking.
     */
    public function test_get_booking_pax_addons_does_not_double_count_moved_field_after_split(): void
    {
        [$oldBooking, $newBooking] = $this->createOldAndNewBookings();

        $field = TourBookingAddonField::create([
            'tour_booking_id' => $oldBooking->bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => $oldBooking->transactId, 'field_label' => 'Souvenir T-Shirt',
            'option_id' => 1, 'option_label' => 'Large', 'pax_index' => 'double-count-test-a',
            'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        try {
            $this->stubCardTransactionRow($oldBooking->bookingId, 'pi_double_count', 24.16);

            TourBookingAddonTransaction::create([
                'tour_booking_id' => $oldBooking->bookingId, 'tour_booking_addon_id' => $field->id,
                'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
                'transaction_id' => 'pi_double_count', 'field_price' => 0, 'field_fee' => 0,
                'option_price' => 22.00, 'option_fee' => 2.16,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);

            AddonBookingService::splitBookingPaxAddons($oldBooking->bookingId, $newBooking->bookingId, [$field->id]);

            $repo = app(TourBookingAddonRepository::class);
            $request = new Request();
            $request->merge(['booking_id' => $newBooking->bookingId]);
            $response = json_decode($repo->getBookingPaxAddons($request)->getContent(), true);

            $paxEntry = collect($response[0]['pax'])->firstWhere('pax_index', 'double-count-test-a');
            $this->assertNotNull($paxEntry, 'Moved field must still appear under the new booking');
            $this->assertEquals(22.00, $paxEntry['charged_price']);
            $this->assertEquals(2.16, $paxEntry['charged_fee']);
        } finally {
            TourBookingAddonTransaction::withTrashed()->where('tour_booking_addon_id', $field->id)->forceDelete();
            TourBookingAddonField::withTrashed()->where('id', $field->id)->forceDelete();
            DB::table('transactions')
                ->whereIn('tour_booking_id', [$oldBooking->bookingId, $newBooking->bookingId])
                ->where('charge_type', 'addons')
                ->delete();
            $newBooking->cleanup();
            $oldBooking->cleanup();
        }
    }

    /**
     * Regression: refundAddonsByBookingId() (called on booking cancellation, real Stripe refund)
     * must only refund the CURRENT booking's own charge row for a moved field — not the pre-split
     * charge row still tagged to the old booking, which would double the actual refund amount.
     */
    public function test_refund_addons_by_booking_id_does_not_double_refund_moved_field(): void
    {
        [$oldBooking, $newBooking] = $this->createOldAndNewBookings();

        $field = TourBookingAddonField::create([
            'tour_booking_id' => $oldBooking->bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => $oldBooking->transactId, 'field_label' => 'Souvenir T-Shirt',
            'option_id' => 1, 'option_label' => 'Large', 'pax_index' => 'refund-double-test-a',
            'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        try {
            $this->stubCardTransactionRow($oldBooking->bookingId, 'pi_refund_double', 24.16);

            TourBookingAddonTransaction::create([
                'tour_booking_id' => $oldBooking->bookingId, 'tour_booking_addon_id' => $field->id,
                'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
                'transaction_id' => 'pi_refund_double', 'field_price' => 0, 'field_fee' => 0,
                'option_price' => 22.00, 'option_fee' => 2.16,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);

            AddonBookingService::splitBookingPaxAddons($oldBooking->bookingId, $newBooking->bookingId, [$field->id]);

            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->expects($this->once())
                ->method('refundPayment')
                ->with($this->callback(function ($params) {
                    // Must refund only ONE shirt ($22 + $2.16 = $24.16), not both ($48.32).
                    return $params['payment_intent'] === 'pi_refund_double'
                        && $params['amount'] === 2416;
                }))
                ->willReturn((object) ['id' => 're_test']);
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            $repo = app(TourBookingAddonRepository::class);
            $repo->refundAddonsByBookingId($newBooking->bookingId, Constants::ADDONS_FULL_REFUND);
        } finally {
            TourBookingAddonTransaction::withTrashed()->where('tour_booking_addon_id', $field->id)->forceDelete();
            TourBookingAddonField::withTrashed()->where('id', $field->id)->forceDelete();
            DB::table('transactions')
                ->whereIn('tour_booking_id', [$oldBooking->bookingId, $newBooking->bookingId])
                ->where('charge_type', 'addons')
                ->delete();
            DB::table('tour_booking_history')
                ->whereIn('tour_booking_id', [$oldBooking->bookingId, $newBooking->bookingId])
                ->where('charge_type', 'addons')
                ->delete();
            $newBooking->cleanup();
            $oldBooking->cleanup();
        }
    }

    /**
     * Regression: refundAddonsByBookingId() (called on booking cancellation) must not crash when
     * a paid addon charge's TourBookingAddonField has since been soft-deleted (e.g. by an earlier
     * pax removal) — addonField is a SoftDeletes-scoped belongsTo, so it comes back null for that
     * row, and reading ->tour_booking_id off it threw "Attempt to read property on null" and
     * aborted the whole cancellation even though the tour-side cancellation had already committed.
     */
    public function test_refund_addons_by_booking_id_survives_a_soft_deleted_addon_field(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();

        $field = TourBookingAddonField::create([
            'tour_booking_id' => $booking->bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => $booking->transactId, 'field_label' => 'Photography Package',
            'pax_index' => 'orphaned-field-test', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        try {
            $this->stubCardTransactionRow($booking->bookingId, 'pi_orphaned_field', 16.47);

            TourBookingAddonTransaction::create([
                'tour_booking_id' => $booking->bookingId, 'tour_booking_addon_id' => $field->id,
                'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
                'transaction_id' => 'pi_orphaned_field', 'field_price' => 15.00, 'field_fee' => 1.47,
                'option_price' => 0, 'option_fee' => 0,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);

            // Orphan the transaction: the field it points at is gone, but the charge row survives —
            // exactly what a pax removal that soft-deletes the field without touching its already
            // 'paid' transaction leaves behind.
            $field->delete();

            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->expects($this->once())
                ->method('refundPayment')
                ->with($this->callback(fn ($params) => $params['payment_intent'] === 'pi_orphaned_field'))
                ->willReturn((object) ['id' => 're_test']);
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            $repo = app(TourBookingAddonRepository::class);
            // Must not throw — this call used to fatal with "Attempt to read property
            // tour_booking_id on null".
            $repo->refundAddonsByBookingId($booking->bookingId, Constants::ADDONS_FULL_REFUND);

            $this->assertDatabaseHas('tour_booking_addon_transactions', [
                'tour_booking_addon_id' => $field->id,
                'status' => 'refunded',
                'field_price' => 15.00,
                'field_fee' => 1.47,
            ]);
        } finally {
            TourBookingAddonTransaction::withTrashed()->where('tour_booking_addon_id', $field->id)->forceDelete();
            TourBookingAddonField::withTrashed()->where('id', $field->id)->forceDelete();
            DB::table('transactions')->where('tour_booking_id', $booking->bookingId)->where('charge_type', 'addons')->delete();
            DB::table('tour_booking_history')->where('tour_booking_id', $booking->bookingId)->where('charge_type', 'addons')->delete();
            $booking->cleanup();
        }
    }

    /**
     * Regression: computeNetAddonTransactions() (backs getTourBookingAddonsSubmission()) must
     * also stay scoped to the requesting booking for the same reason as the two tests above.
     */
    public function test_compute_net_addon_transactions_does_not_double_count_moved_field(): void
    {
        [$oldBooking, $newBooking] = $this->createOldAndNewBookings();

        $field = TourBookingAddonField::create([
            'tour_booking_id' => $oldBooking->bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => $oldBooking->transactId, 'field_label' => 'Souvenir T-Shirt',
            'option_id' => 1, 'option_label' => 'Large', 'pax_index' => 'compute-net-test-a',
            'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        try {
            $this->stubCardTransactionRow($oldBooking->bookingId, 'pi_compute_net', 24.16);

            TourBookingAddonTransaction::create([
                'tour_booking_id' => $oldBooking->bookingId, 'tour_booking_addon_id' => $field->id,
                'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
                'transaction_id' => 'pi_compute_net', 'field_price' => 0, 'field_fee' => 0,
                'option_price' => 22.00, 'option_fee' => 2.16,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);

            AddonBookingService::splitBookingPaxAddons($oldBooking->bookingId, $newBooking->bookingId, [$field->id]);

            $repo = app(TourBookingAddonRepository::class);
            $method = new ReflectionMethod($repo, 'computeNetAddonTransactions');
            $method->setAccessible(true);
            $net = $method->invoke($repo, [$field->id], $newBooking->bookingId);

            $this->assertArrayHasKey($field->id, $net);
            $this->assertEquals(22.00, $net[$field->id]['option_price']);
            $this->assertEquals(2.16, $net[$field->id]['option_fee']);
        } finally {
            TourBookingAddonTransaction::withTrashed()->where('tour_booking_addon_id', $field->id)->forceDelete();
            TourBookingAddonField::withTrashed()->where('id', $field->id)->forceDelete();
            DB::table('transactions')
                ->whereIn('tour_booking_id', [$oldBooking->bookingId, $newBooking->bookingId])
                ->where('charge_type', 'addons')
                ->delete();
            $newBooking->cleanup();
            $oldBooking->cleanup();
        }
    }

    /**
     * BookingService::fetchSplitReallocatedTourFee() reconstructs the gross (never-decremented)
     * tour fee for display — "Booking Fees" combines this with addons_gross_fee so both halves
     * use the same gross-forever convention as booking_total, instead of mixing a decremented
     * service_commission with a gross addons figure. Scoped via is_reallocated=1, set ONLY on the
     * tour-side transactions ExtendBooking*Service writes during a split.
     */
    public function test_fetch_split_reallocated_tour_fee_sums_only_reallocated_refund_rows(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();

        try {
            // A split-caused tour refund (is_reallocated=1) — must be counted.
            DB::table('transactions')->insert([
                'tour_booking_id' => $booking->bookingId, 'amount' => 14.40, 'fee_amount' => 14.40,
                'transaction_number' => 'pi_reallocated_test', 'charge_type' => null,
                'status' => 'Refund', 'via' => 'Card', 'payment_type' => 0, 'is_reallocated' => 1,
                'created_by' => 1, 'created_by_user_type' => 'Admin', 'created_at' => now(),
            ]);
            // An ordinary (non-split) tour refund on the same booking — must NOT be counted.
            DB::table('transactions')->insert([
                'tour_booking_id' => $booking->bookingId, 'amount' => 5.00, 'fee_amount' => 5.00,
                'transaction_number' => '', 'charge_type' => null,
                'status' => 'Refund', 'via' => 'Card', 'payment_type' => 0, 'is_reallocated' => 0,
                'created_by' => 1, 'created_by_user_type' => 'Admin', 'created_at' => now(),
            ]);

            $reallocatedFee = BookingService::fetchSplitReallocatedTourFee($booking->bookingId);

            $this->assertEquals(14.40, $reallocatedFee);
        } finally {
            DB::table('transactions')
                ->where('tour_booking_id', $booking->bookingId)
                ->whereNull('charge_type')
                ->where('status', 'Refund')
                ->delete();
            $booking->cleanup();
        }
    }

    public function test_fetch_split_reallocated_tour_fee_is_zero_for_a_booking_split_never_touched(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();

        try {
            $this->assertEquals(0.0, BookingService::fetchSplitReallocatedTourFee($booking->bookingId));
        } finally {
            $booking->cleanup();
        }
    }
}
