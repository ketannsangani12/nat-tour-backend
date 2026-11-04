<?php

namespace Tests\Unit\Bookings;

use App\Models\TourBookingAddonTransaction;
use App\Models\TourBookingPaymentLink;
use App\Models\TourBookingPaymentLinkItem;
use App\Services\Booking\DeleteBookingAddonBreakdownService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

class DeleteBookingAddonBreakdownServiceTest extends TestCase
{
    /**
     * Regression for the "Update Booking" modal silently unable to refund an add-on that was
     * removed then re-added for the same still-present pax: the re-add gets its own new
     * tour_booking_addon_fields row (new pax_index), charged on its OWN PaymentIntent with NO
     * accompanying tour-side charge. That PI never appears in $paymentTransactions (tour-side
     * only), so its item must be surfaced separately or it's invisible/unselectable in the modal
     * — silently leaving that money unrefunded whenever the pax is later removed.
     */
    public function test_addon_only_payment_intent_with_no_tour_side_row_is_surfaced(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;
        $paxId = 700099;
        $fieldIds = [700001, 700002];

        try {
            DB::table('tour_booking_addon_fields')->insert([
                ['id' => 700001, 'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1, 'pax_id' => $paxId, 'field_label' => 'Souvenir T-Shirt', 'option_id' => 1, 'option_label' => 'M', 'pax_index' => 'test-orig-0', 'unit_type' => 'price_per_pax', 'unit' => 1, 'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
                ['id' => 700002, 'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1, 'pax_id' => $paxId, 'field_label' => 'Souvenir T-Shirt', 'option_id' => 1, 'option_label' => 'M', 'pax_index' => 'test-readd-0', 'unit_type' => 'price_per_pax', 'unit' => 1, 'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
            ]);

            // Original field, fully removed-then-refunded (positive-convention refund, matching
            // TourBookingAddonRepository's direct addon-edit refund path).
            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => 700001, 'payment_method' => 'card',
                'status' => 'paid', 'operation' => 'charge', 'transaction_id' => 'pi_test_original',
                'field_price' => 0, 'field_fee' => 0, 'option_price' => 22.00, 'option_fee' => 2.16,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);
            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => 700001, 'payment_method' => 'card',
                'status' => 'refunded', 'operation' => 'refund', 'transaction_id' => 'pi_test_original',
                'field_price' => 0, 'field_fee' => 0, 'option_price' => 22.00, 'option_fee' => 2.16,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);

            // Re-added field — brand new db_id, charged on a PI that has NO tour-side transaction
            // row at all (only this addons-only PI exists for it).
            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => 700002, 'payment_method' => 'card',
                'status' => 'paid', 'operation' => 'charge', 'transaction_id' => 'pi_test_addon_only',
                'field_price' => 0, 'field_fee' => 0, 'option_price' => 22.00, 'option_fee' => 2.16,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);

            // $paymentTransactions mirrors booking()'s tour-side-only set — deliberately does NOT
            // include 'pi_test_addon_only', matching the real scenario exactly.
            $paymentTransactions = collect([(object) ['transaction_number' => 'pi_test_tour_side']]);

            $addonOnlyEntries = DeleteBookingAddonBreakdownService::attach($paymentTransactions, $bookingId, true);

            $this->assertArrayHasKey('pi_test_addon_only', $addonOnlyEntries, 'The addon-only PI must be surfaced separately since it has no tour-side row to attach to');
            $items = $addonOnlyEntries['pi_test_addon_only'];
            $this->assertCount(1, $items);
            $this->assertEquals(700002, $items[0]['id']);
            $this->assertEquals(22.00, $items[0]['price']);
            $this->assertEquals(2.16, $items[0]['fee']);

            // The fully-refunded original item must NOT reappear anywhere (positive-convention
            // refund correctly netted to $0, not doubled).
            $this->assertArrayNotHasKey('pi_test_original', $addonOnlyEntries);
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', $fieldIds)->forceDelete();
            DB::table('tour_booking_addon_fields')->whereIn('id', $fieldIds)->delete();
            $booking->cleanup();
        }
    }

    /**
     * Regression for the "Update Booking" modal's Tour Transaction badge understating a card's
     * true balance: when a booking's ticket and its priced add-ons are charged on the SAME
     * PaymentIntent (e.g. add-ons picked at initial booking time, billed in one Stripe charge),
     * attach() must still expose the add-on portion via addon_amount so the modal can fold it
     * into that card's badge instead of only showing the tour-side amount.
     */
    public function test_addon_amount_is_attached_for_a_payment_intent_that_also_charged_the_tour(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;
        $paxId = 700299;
        $fieldIds = [700201, 700202];

        try {
            DB::table('tour_booking_addon_fields')->insert([
                ['id' => 700201, 'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1, 'pax_id' => $paxId, 'field_label' => 'Photography Package', 'pax_index' => 'test-shared-0', 'unit_type' => 'price_per_pax', 'unit' => 1, 'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
                ['id' => 700202, 'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1, 'pax_id' => $paxId, 'field_label' => 'Photography Package', 'pax_index' => 'test-shared-1', 'unit_type' => 'price_per_pax', 'unit' => 1, 'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
            ]);

            // Two pax' worth of add-ons, both charged on the SAME PaymentIntent that also charged
            // the tour amount (the $paymentTransactions row below stands in for that tour charge).
            foreach ($fieldIds as $fieldId) {
                TourBookingAddonTransaction::create([
                    'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $fieldId, 'payment_method' => 'card',
                    'status' => 'paid', 'operation' => 'charge', 'transaction_id' => 'pi_test_combined',
                    'field_price' => 15.00, 'field_fee' => 1.47, 'option_price' => 0, 'option_fee' => 0,
                    'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
                ]);
            }

            $paymentTransactions = collect([(object) ['transaction_number' => 'pi_test_combined']]);

            DeleteBookingAddonBreakdownService::attach($paymentTransactions, $bookingId, true);

            // 2 x ($15.00 + $1.47) = $32.94, still outstanding (nothing refunded yet).
            $this->assertEquals(32.94, $paymentTransactions->first()->addon_amount);
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', $fieldIds)->forceDelete();
            DB::table('tour_booking_addon_fields')->whereIn('id', $fieldIds)->delete();
            $booking->cleanup();
        }
    }

    /**
     * Regression: removing a pax via "Ticket Refund" (fees kept) soft-deletes their add-on field
     * row, but ChangePeopleAddonService deliberately leaves the refund row's fee at $0 so the fee
     * stays "available" for a later upgrade to a 100% refund — netting that row to (price=0,
     * fee>0), not (0, 0). Before filtering out soft-deleted field rows, that phantom fee-only
     * remnant still cleared the HAVING filter and consumed a pax_position slot ahead of a
     * still-active pax's own untouched add-on on the same PaymentIntent, so the modal showed the
     * still-active pax's item at only the phantom's fee amount (e.g. $1.47 instead of $16.47),
     * under the wrong shifted position.
     */
    public function test_removed_pax_kept_fee_remnant_does_not_shadow_a_still_active_pax_addon(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;
        $paxId = 700199;
        $fieldIds = [700101, 700102];

        try {
            DB::table('tour_booking_addon_fields')->insert([
                ['id' => 700101, 'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1, 'pax_id' => $paxId, 'field_label' => 'Photography Package', 'pax_index' => 'test-removed-0', 'unit_type' => 'price_per_pax', 'unit' => 1, 'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin', 'created_at' => now(), 'updated_at' => now(), 'deleted_at' => now()],
                ['id' => 700102, 'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1, 'pax_id' => $paxId, 'field_label' => 'Photography Package', 'pax_index' => 'test-active-0', 'unit_type' => 'price_per_pax', 'unit' => 1, 'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin', 'created_at' => now(), 'updated_at' => now(), 'deleted_at' => null],
            ]);

            // Already-removed pax: charged $15 + $1.47 fee, then "Ticket Refund" (fees kept)
            // settled — the refund row matches price exactly but deliberately leaves fee at $0,
            // netting to (price=0, fee=1.47), not (0, 0).
            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => 700101, 'payment_method' => 'card',
                'status' => 'paid', 'operation' => 'charge', 'transaction_id' => 'pi_test_shared',
                'field_price' => 15.00, 'field_fee' => 1.47, 'option_price' => 0, 'option_fee' => 0,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);
            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => 700101, 'payment_method' => 'card',
                'status' => 'refunded', 'operation' => 'refund', 'transaction_id' => 'pi_test_shared',
                'field_price' => 15.00, 'field_fee' => 0, 'option_price' => 0, 'option_fee' => 0,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);

            // Still-active pax's own, never-touched item on the SAME PaymentIntent.
            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => 700102, 'payment_method' => 'card',
                'status' => 'paid', 'operation' => 'charge', 'transaction_id' => 'pi_test_shared',
                'field_price' => 15.00, 'field_fee' => 1.47, 'option_price' => 0, 'option_fee' => 0,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);

            $paymentTransactions = collect([(object) ['transaction_number' => 'pi_test_shared']]);

            DeleteBookingAddonBreakdownService::attach($paymentTransactions, $bookingId, true);

            $items = $paymentTransactions->first()->addon_items;

            $this->assertCount(1, $items, 'The soft-deleted, kept-fee-only remnant must not appear alongside the still-active pax\'s own item');
            $this->assertEquals(700102, $items[0]['id']);
            $this->assertEquals(15.00, $items[0]['price']);
            $this->assertEquals(1.47, $items[0]['fee']);
            $this->assertEquals(0, $items[0]['pax_index'], 'The still-active pax must occupy position 0, not be shifted down by the removed pax\'s remnant');
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', $fieldIds)->forceDelete();
            DB::table('tour_booking_addon_fields')->whereIn('id', $fieldIds)->delete();
            $booking->cleanup();
        }
    }

    /**
     * Regression: a pax whose ONLY priced add-on was never paid (sent via a "Pay Later" payment
     * link, never completed) had that field silently orphaned by the "Update Booking" delete
     * modal — attach() only ever surfaces PAID add-ons, so the field id never reached
     * #delete_booking_addon_scope_ids -> $explicitFieldIds, and deletePaxAddons() no-ops entirely
     * on an empty/non-matching explicit field id list. attachPending() must surface it instead,
     * so its id flows into the same deletion scope paid items already use.
     *
     * @test
     * CMD: php artisan test --filter=attachPending_surfacesFieldCoveredByUnpaidAddonLinkItem
     */
    public function test_attachPending_surfacesFieldCoveredByUnpaidAddonLinkItem(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;
        $groupId = (string) $bookingId;
        DB::table('tour_bookings_master')->where('id', $bookingId)->update(['group_id' => $groupId]);

        $paxId = 700399;
        $fieldId = 700301;

        DB::table('tour_booking_addon_fields')->insert([
            'id' => $fieldId, 'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => $paxId, 'field_label' => 'Photography Package', 'pax_index' => 'test-pending-0',
            'person_ordinal' => 0, 'unit_type' => 'price_per_pax', 'unit' => 1,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $link = TourBookingPaymentLink::create([
            'order_id' => $groupId, 'section' => 'remaining_payment', 'amount' => 15.00,
            'reason_of_payment' => 'Payment for add-ons on your booking', 'email' => 'test@example.com',
            'phone_number' => '5555551234', 'token' => \Illuminate\Support\Str::random(64),
            'status' => 'Unpaid', 'override_status' => 'Yes', 'expired_at' => Carbon::now()->addDays(30),
            'created_by' => 1, 'created_by_user_type' => 'Admin',
        ]);
        $item = TourBookingPaymentLinkItem::create([
            'payment_link_id' => $link->id, 'type' => 'addon', 'reference_id' => null, 'amount' => 15.00,
            'status' => 'Unpaid',
            'details' => json_encode([
                'booking_id' => $bookingId,
                'financial' => ['to_charge' => [['old_db_id' => $fieldId, 'field_id' => 1, 'pax_index' => 'test-pending-0']], 'to_refund' => []],
                'field_to_db' => ['1_test-pending-0' => $fieldId],
            ]),
            'created_by' => 1, 'created_by_user_type' => 'Admin', 'updated_by' => 1, 'updated_by_user_type' => 'Admin',
        ]);

        try {
            $items = DeleteBookingAddonBreakdownService::attachPending($bookingId);

            $this->assertCount(1, $items);
            $this->assertEquals($fieldId, $items[0]['id']);
            $this->assertEquals($paxId, $items[0]['pax_id']);
            $this->assertTrue($items[0]['pending']);
            $this->assertEquals(0.0, $items[0]['price']);
            $this->assertEquals(0.0, $items[0]['fee']);
        } finally {
            TourBookingPaymentLinkItem::where('payment_link_id', $link->id)->delete();
            DB::table('tour_booking_payment_links')->where('id', $link->id)->delete();
            DB::table('tour_booking_addon_fields')->where('id', $fieldId)->delete();
            $booking->cleanup();
        }
    }

    /**
     * A field already covered by attach()'s paid-side listing must never also appear here —
     * otherwise it would be double-counted / double-scoped in the delete modal.
     *
     * @test
     * CMD: php artisan test --filter=attachPending_excludesFieldsAlreadyPaid
     */
    public function test_attachPending_excludesFieldsAlreadyPaid(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;
        $paxId = 700499;
        $fieldId = 700401;

        DB::table('tour_booking_addon_fields')->insert([
            'id' => $fieldId, 'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => $paxId, 'field_label' => 'Photography Package', 'pax_index' => 'test-paid-0',
            'person_ordinal' => 0, 'unit_type' => 'price_per_pax', 'unit' => 1,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $fieldId, 'payment_method' => 'card',
            'status' => 'paid', 'operation' => 'charge', 'transaction_id' => 'pi_test_pending_excl',
            'field_price' => 15.00, 'field_fee' => 1.47, 'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);

        try {
            $items = DeleteBookingAddonBreakdownService::attachPending($bookingId);

            $this->assertEmpty($items, 'A field with a real paid transaction must never be listed as pending');
        } finally {
            TourBookingAddonTransaction::where('tour_booking_addon_id', $fieldId)->forceDelete();
            DB::table('tour_booking_addon_fields')->where('id', $fieldId)->delete();
            $booking->cleanup();
        }
    }

    /**
     * A field with no transaction AND no pending payment-link item at all is some other
     * in-progress state, not a genuine "will be cancelled" pending add-on — must not be surfaced.
     *
     * @test
     * CMD: php artisan test --filter=attachPending_excludesFieldsWithNoPendingLinkAtAll
     */
    public function test_attachPending_excludesFieldsWithNoPendingLinkAtAll(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;
        $paxId = 700599;
        $fieldId = 700501;

        DB::table('tour_booking_addon_fields')->insert([
            'id' => $fieldId, 'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => $paxId, 'field_label' => 'Photography Package', 'pax_index' => 'test-orphan-0',
            'person_ordinal' => 0, 'unit_type' => 'price_per_pax', 'unit' => 1,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        try {
            $items = DeleteBookingAddonBreakdownService::attachPending($bookingId);

            $this->assertEmpty($items, 'A field with no transaction and no pending link must not be surfaced');
        } finally {
            DB::table('tour_booking_addon_fields')->where('id', $fieldId)->delete();
            $booking->cleanup();
        }
    }
}
