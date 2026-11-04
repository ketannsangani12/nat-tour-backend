<?php

namespace Tests\Unit\Bookings;

use App\Models\AddonField;
use App\Models\AddonForm;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingPaymentLink;
use App\Models\TourBookingPaymentLinkItem;
use App\Services\Booking\PaymentLinkService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: PaymentLinkService::cancelPendingAddonLinkItemsForBooking() is called whenever a
 * pax is deleted from a booking (deletePeopleChangePeople()), to clean up any pending addon
 * payment-link item that belonged to the just-deleted pax. It used to cancel EVERY type='addon'
 * item on the booking's Unpaid remaining_payment link unconditionally — so deleting one pax wiped
 * out a completely unrelated, still-current pax's own pending add-on link too (and, once nothing
 * was left, the "Pending Payment Link" banner disappeared from the booking page entirely).
 *
 * CMD: php artisan test --filter=CancelPendingAddonLinkItemsForBookingScopingTest
 */
class CancelPendingAddonLinkItemsForBookingScopingTest extends TestCase
{
    public function test_only_cancels_the_item_whose_addon_field_no_longer_exists(): void
    {
        $booking = (new CreateABooking())
            ->withPaymentMethod('Card')
            ->create();
        $bookingId = $booking->bookingId;

        $packageId = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tour_package_id');
        $groupId = (string) $bookingId;
        DB::table('tour_bookings_master')->where('id', $bookingId)->update(['group_id' => $groupId]);

        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name' => 'Cancel Scoping Form ' . uniqid(),
            'status' => 'Active',
        ]);

        $fieldDef = AddonField::create([
            'form_id' => $form->id,
            'label' => 'Photography Package',
            'type' => 'checkbox',
            'unit_type' => 'price_per_pax',
            'additional_fee' => true,
            'price' => 15.00,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);

        // Pax A's add-on field — still current (that pax was NOT deleted).
        $stillPresentField = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $fieldDef->id,
            'pax_id' => 1, 'field_label' => 'Photography Package',
            'pax_index' => 'pax-A', 'person_ordinal' => 0, 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        $link = TourBookingPaymentLink::create([
            'order_id'             => $groupId,
            'section'              => 'remaining_payment',
            'amount'               => 30.00,
            'reason_of_payment'    => 'Payment for add-ons on your booking',
            'email'                => 'test@example.com',
            'phone_number'         => '5555551234',
            'token'                => \Illuminate\Support\Str::random(64),
            'status'               => 'Unpaid',
            'override_status'      => 'Yes',
            'expired_at'           => Carbon::now()->addDays(30),
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
        ]);

        // Item covering pax A's still-current field — must survive.
        $survivingItem = TourBookingPaymentLinkItem::create([
            'payment_link_id'      => $link->id,
            'type'                 => 'addon',
            'reference_id'         => null,
            'amount'               => 15.00,
            'status'               => 'Unpaid',
            'details'              => json_encode([
                'booking_id' => $bookingId,
                'financial'  => [
                    'to_charge' => [
                        ['old_db_id' => $stillPresentField->id, 'field_id' => $fieldDef->id, 'pax_index' => 'pax-A'],
                    ],
                    'to_refund' => [],
                ],
                'field_to_db' => [$fieldDef->id . '_pax-A' => $stillPresentField->id],
            ]),
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'updated_by'           => 1,
            'updated_by_user_type' => 'Admin',
        ]);

        // Item covering pax B's field — pax B was the one just deleted, so by the time
        // cancelPendingAddonLinkItemsForBooking() runs, removePaxAddons()/deletePaxAddons()
        // has already removed that field row entirely. Simulate that: reference a field id
        // that simply does not exist.
        $deletedPaxFieldId = TourBookingAddonField::max('id') + 999999;
        $orphanedItem = TourBookingPaymentLinkItem::create([
            'payment_link_id'      => $link->id,
            'type'                 => 'addon',
            'reference_id'         => null,
            'amount'               => 15.00,
            'status'               => 'Unpaid',
            'details'              => json_encode([
                'booking_id' => $bookingId,
                'financial'  => [
                    'to_charge' => [
                        ['old_db_id' => $deletedPaxFieldId, 'field_id' => $fieldDef->id, 'pax_index' => 'pax-B'],
                    ],
                    'to_refund' => [],
                ],
                'field_to_db' => [$fieldDef->id . '_pax-B' => $deletedPaxFieldId],
            ]),
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'updated_by'           => 1,
            'updated_by_user_type' => 'Admin',
        ]);

        try {
            PaymentLinkService::cancelPendingAddonLinkItemsForBooking($bookingId);

            $this->assertEquals(
                'Unpaid',
                TourBookingPaymentLinkItem::find($survivingItem->id)->status,
                'The still-current pax\'s own pending addon item must NOT be cancelled by an unrelated pax deletion'
            );
            $this->assertEquals(
                'Cancelled',
                TourBookingPaymentLinkItem::find($orphanedItem->id)->status,
                'The deleted pax\'s own pending addon item must be cancelled'
            );
        } finally {
            TourBookingPaymentLinkItem::where('payment_link_id', $link->id)->delete();
            DB::table('tour_booking_payment_links')->where('id', $link->id)->delete();
            TourBookingAddonField::withTrashed()->where('tour_booking_id', $bookingId)->forceDelete();
            DB::table('addon_fields')->where('id', $fieldDef->id)->delete();
            DB::table('addon_forms')->where('id', $form->id)->delete();
            $booking->cleanup();
        }
    }

    /**
     * Regression (real-world case: booking 253941, dixies tenant): two children were added
     * together via the same payment-link resend, each with their own priced add-on, bundled into
     * ONE addon-type payment-link item (to_charge has two entries, one per child). Deleting just
     * ONE of those two children left the WHOLE item — and its full, both-children amount —
     * completely untouched, since "at least one referenced field still exists" was true. The
     * customer's pending payment link kept demanding the deleted child's add-on money forever.
     *
     * @test
     * CMD: php artisan test --filter=cancelPendingAddonLinkItemsForBooking_partiallyReducesItemBundlingTwoDeletedAndSurvivingFields
     */
    public function test_cancelPendingAddonLinkItemsForBooking_partiallyReducesItemBundlingTwoDeletedAndSurvivingFields(): void
    {
        $booking = (new CreateABooking())
            ->withPaymentMethod('Card')
            ->create();
        $bookingId = $booking->bookingId;

        $groupId = (string) $bookingId;
        DB::table('tour_bookings_master')->where('id', $bookingId)->update(['group_id' => $groupId]);

        $form = AddonForm::create([
            'tour_package_id' => (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tour_package_id'),
            'name' => 'Partial Reduce Form ' . uniqid(),
            'status' => 'Active',
        ]);
        $sandwichDef = AddonField::create([
            'form_id' => $form->id, 'label' => 'Sandwich', 'type' => 'checkbox', 'unit_type' => 'price_per_pax',
            'additional_fee' => true, 'price' => 10.00, 'status' => 'Active', 'visibility' => 'both', 'sort_order' => 0,
        ]);
        $photoDef = AddonField::create([
            'form_id' => $form->id, 'label' => 'Photography Package', 'type' => 'checkbox', 'unit_type' => 'price_per_pax',
            'additional_fee' => true, 'price' => 15.00, 'status' => 'Active', 'visibility' => 'both', 'sort_order' => 1,
        ]);

        // Child #1 (ordinal 0) — this one gets deleted below. Child #2 (ordinal 1) survives.
        $child1Field = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $sandwichDef->id,
            'pax_id' => 1, 'field_label' => 'Sandwich', 'option_label' => 'Yes',
            'pax_index' => 'pax-child-0', 'person_ordinal' => 0, 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        $child2Field = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $photoDef->id,
            'pax_id' => 1, 'field_label' => 'Photography Package',
            'pax_index' => 'pax-child-1', 'person_ordinal' => 1, 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        $link = TourBookingPaymentLink::create([
            'order_id' => $groupId, 'section' => 'remaining_payment', 'amount' => 27.46,
            'reason_of_payment' => 'Payment for added guests to your booking', 'email' => 'test@example.com',
            'phone_number' => '5555551234', 'token' => \Illuminate\Support\Str::random(64),
            'status' => 'Unpaid', 'override_status' => 'Yes', 'expired_at' => Carbon::now()->addDays(30),
            'created_by' => 1, 'created_by_user_type' => 'Admin',
        ]);

        // Shaped exactly like the real booking 253941 item #292: two to_charge entries, one per
        // child, old_db_id null (brand new fields), resolved via field_to_db.
        $item = TourBookingPaymentLinkItem::create([
            'payment_link_id' => $link->id,
            'type' => 'addon',
            'reference_id' => null,
            'amount' => 27.46,
            'status' => 'Unpaid',
            'details' => json_encode([
                'booking_id' => $bookingId,
                'financial' => [
                    'action' => 'charge',
                    'to_charge' => [
                        [
                            'label' => 'Sandwich (Yes)', 'field_id' => $sandwichDef->id, 'old_db_id' => null,
                            'pax_index' => 'pax-child-0', 'field_price' => 0, 'option_price' => 10,
                            'field_fee' => 0, 'option_fee' => 0.98, 'charge_amount' => 10,
                        ],
                        [
                            'label' => 'Photography Package', 'field_id' => $photoDef->id, 'old_db_id' => null,
                            'pax_index' => 'pax-child-1', 'field_price' => 15, 'option_price' => 0,
                            'field_fee' => 1.47, 'option_fee' => 0, 'charge_amount' => 15,
                        ],
                    ],
                    'to_refund' => [],
                    'net_amount' => 25,
                ],
                'field_to_db' => [
                    $sandwichDef->id . '_pax-child-0' => $child1Field->id,
                    $photoDef->id . '_pax-child-1' => $child2Field->id,
                ],
                'platform_fees' => 2.46,
            ]),
            'created_by' => 1, 'created_by_user_type' => 'Admin', 'updated_by' => 1, 'updated_by_user_type' => 'Admin',
        ]);

        try {
            // Simulate child #1 having just been deleted (removePaxAddons() already soft-deletes
            // the field row before this method ever runs).
            $child1Field->delete();

            PaymentLinkService::cancelPendingAddonLinkItemsForBooking($bookingId);

            $item->refresh();
            $this->assertEquals('Unpaid', $item->status, 'The item must survive — child #2\'s field is still present');
            $this->assertEquals(16.47, (float) $item->amount, 'Only child #2\'s $15 + $1.47 fee must remain — child #1\'s $10 + $0.98 must be dropped, not left in the total');

            $details = is_array($item->details) ? $item->details : json_decode($item->details, true);
            $this->assertCount(1, $details['financial']['to_charge'], 'Only child #2\'s entry must remain in to_charge');
            $this->assertEquals('pax-child-1', $details['financial']['to_charge'][0]['pax_index']);
            $this->assertEquals(15.0, (float) $details['financial']['net_amount']);
            $this->assertEquals(1.47, (float) $details['platform_fees']);
            $this->assertArrayNotHasKey($sandwichDef->id . '_pax-child-0', $details['field_to_db']);
            $this->assertArrayHasKey($photoDef->id . '_pax-child-1', $details['field_to_db']);

            $this->assertEquals(16.47, (float) TourBookingPaymentLink::find($link->id)->amount, 'The parent link total must also drop to match');
        } finally {
            TourBookingPaymentLinkItem::where('payment_link_id', $link->id)->delete();
            DB::table('tour_booking_payment_links')->where('id', $link->id)->delete();
            TourBookingAddonField::withTrashed()->where('tour_booking_id', $bookingId)->forceDelete();
            DB::table('addon_fields')->whereIn('id', [$sandwichDef->id, $photoDef->id])->delete();
            DB::table('addon_forms')->where('id', $form->id)->delete();
            $booking->cleanup();
        }
    }
}
