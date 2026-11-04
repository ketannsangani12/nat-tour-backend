<?php

namespace Tests\Unit\Bookings;

use App\Models\AddonField;
use App\Models\AddonFieldOption;
use App\Models\AddonForm;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingPaymentLink;
use App\Models\TourBookingPaymentLinkItem;
use App\Services\Booking\PaymentLinkService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression (found live, tenant `kens`, booking 348700): the fix in
 * [[project_change_people_addon_bundled_remaining_link_fix]] (Round 2) that consolidates a
 * never-paid addon upgrade into ONE ledger entry only works if the pending link's stale 'addon'
 * item is dropped/cancelled BEFORE RemainingPaymentService::finalizeItems() runs (it settles every
 * still-Unpaid item on the link) -- not after. The first version of that fix called
 * PaymentLinkService::syncPendingAddonLinkItemsForUpdatedFields() AFTER
 * TourBookingRepository::createBookingCard()/createPaymentIntent() (which call finalizeItems()
 * internally), so the stale $21.96 "Standard" item was already finalized as its OWN separate paid
 * entry by the time the drop call ran -- producing two ledger entries again ($21.96 old + $27.45
 * new = $49.41 recorded, even though only $27.45 was meant to be recorded once the old line was
 * superseded). Fixed by moving the sync/drop call to run BEFORE createBookingCard()/
 * createPaymentIntent(), mirroring TourBookingAddonRepository::syncPendingAddonLinkForEditedFields()'s
 * ordering for the single-passenger addon-edit flow exactly (sync/drop, THEN finalize).
 *
 * This test proves syncPendingAddonLinkItemsForUpdatedFields() itself correctly cancels the
 * superseded item given the REAL production `details` JSON shape (captured verbatim from tenant
 * `kens` booking 348698's link item 59) -- the mechanism the fix now calls before finalizeItems()
 * ever gets a chance to settle the stale item as a second, separate entry.
 * RemainingPaymentServiceTest::finalizeItems_doesNotTouchCancelledItems already proves finalizeItems()
 * skips a Cancelled item, so the two tests together cover the full ordering contract without
 * needing to also exercise AddonItemHandler::finalize()'s own Stripe-retrieval internals here.
 */
class ChangePeopleAddonSyncBeforeFinalizeOrderingTest extends TestCase
{
    public function test_sync_cancels_the_superseded_addon_item_using_the_real_production_details_shape(): void
    {
        $booking = (new CreateABooking())->withTourists(1)->withPaymentMethod('Card')->create();
        $groupId = (string) $booking->bookingId;
        DB::table('tour_bookings_master')->where('id', $booking->bookingId)->update(['group_id' => $groupId]);

        $form = AddonForm::create(['tour_package_id' => $booking->packageId, 'name' => 'Ordering Fix Form ' . uniqid(), 'status' => 'Active']);
        $field = AddonField::create([
            'form_id' => $form->id, 'label' => 'Photography Package', 'type' => 'dropdown', 'unit_type' => 'price_per_pax',
            'additional_fee' => true, 'price' => 15.00, 'status' => 'Active', 'visibility' => 'both', 'sort_order' => 0,
        ]);
        $standard = AddonFieldOption::create(['field_id' => $field->id, 'label' => 'Standard', 'value' => 'standard', 'additional_fee' => true, 'price' => 5.00, 'sort_order' => 0]);
        AddonFieldOption::create(['field_id' => $field->id, 'label' => 'Premium', 'value' => 'premium', 'additional_fee' => true, 'price' => 10.00, 'sort_order' => 1]);

        $paxIndex = 'test-ordering-0';
        $row = TourBookingAddonField::create([
            'tour_booking_id' => $booking->bookingId, 'form_id' => $form->id, 'field_id' => $field->id,
            'field_label' => 'Photography Package', 'option_id' => $standard->id, 'option_label' => 'Standard', 'value' => 'standard',
            'pax_id' => $booking->transactId, 'pax_index' => $paxIndex, 'person_ordinal' => 0,
            'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        $link = TourBookingPaymentLink::create([
            'order_id' => $groupId, 'section' => 'remaining_payment', 'amount' => 102.46,
            'status' => 'Unpaid', 'override_status' => 'Yes', 'token' => Str::random(64),
            'expired_at' => Carbon::now()->addDays(30), 'created_by' => 1, 'created_by_user_type' => 'Admin',
        ]);
        $ticketItem = TourBookingPaymentLinkItem::create([
            'payment_link_id' => $link->id, 'type' => 'change_people', 'amount' => 80.50, 'status' => 'Unpaid',
            'details' => ['tourists' => 1, 'ticket_cost' => 73.3, 'platform_fees' => 7.2, 'tour_booking_id' => $booking->bookingId],
            'created_by' => 1, 'created_by_user_type' => 'Admin',
        ]);
        // Real production shape (tenant `kens`, booking 348698, link item 59) -- to_charge is NOT
        // empty; it carries the original charge line the field was created with.
        $addonItem = TourBookingPaymentLinkItem::create([
            'payment_link_id' => $link->id, 'type' => 'addon', 'amount' => 21.96, 'status' => 'Unpaid',
            'details' => [
                'booking_id' => $booking->bookingId, 'form_id' => (string) $form->id, 'platform_fees' => 1.96,
                'field_to_db' => [$field->id . '_' . $paxIndex => $row->id],
                'financial' => [
                    'action' => 'charge', 'net_amount' => 20, 'to_refund' => [],
                    'to_charge' => [[
                        'label' => 'Photography Package (Standard)', 'field_id' => $field->id, 'field_fee' => 1.47,
                        'old_db_id' => null, 'pax_index' => $paxIndex, 'option_fee' => 0.49,
                        'field_price' => 15, 'option_price' => 5, 'charge_amount' => 20,
                        'field_additional_fee' => true, 'option_additional_fee' => true,
                    ]],
                ],
            ],
            'created_by' => 1, 'created_by_user_type' => 'Admin',
        ]);

        try {
            // The fixed ordering: this now runs BEFORE createBookingCard()/createPaymentIntent()
            // (which internally calls finalizeItems()) in TourBookingRepository::changePeopleBooking().
            PaymentLinkService::syncPendingAddonLinkItemsForUpdatedFields(
                $booking->bookingId,
                [$row->id],
                [$row->id],
                false
            );

            $this->assertEquals(
                'Cancelled',
                TourBookingPaymentLinkItem::find($addonItem->id)->status,
                'The stale addon item must be cancelled before finalizeItems() ever gets a chance to settle it as a second, separate entry.'
            );
            $this->assertEquals(
                'Unpaid',
                TourBookingPaymentLinkItem::find($ticketItem->id)->status,
                'Only the superseded field\'s own item is touched -- the unrelated ticket item is left for finalizeItems() to settle normally.'
            );
            $this->assertEquals(
                'Unpaid',
                TourBookingPaymentLink::find($link->id)->status,
                'The link itself stays Unpaid -- finalizeItems() (not sync) is what marks it Paid, once the ticket item is settled.'
            );
        } finally {
            TourBookingPaymentLinkItem::where('payment_link_id', $link->id)->delete();
            TourBookingPaymentLink::where('id', $link->id)->delete();
            DB::table('tour_booking_addon_transactions')->where('tour_booking_addon_id', $row->id)->delete();
            TourBookingAddonField::withTrashed()->where('id', $row->id)->forceDelete();
            DB::table('addon_field_options')->where('field_id', $field->id)->delete();
            DB::table('addon_fields')->where('id', $field->id)->delete();
            DB::table('addon_forms')->where('id', $form->id)->delete();
            $booking->cleanup();
        }
    }
}
