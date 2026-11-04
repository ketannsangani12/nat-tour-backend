<?php

namespace Tests\Unit\Bookings;

use App\Models\AddonField;
use App\Models\AddonForm;
use App\Models\TourBooking;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingPaymentLink;
use App\Models\TourBookingPaymentLinkItem;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression (real-world report): on a CARD-paid booking, adding an add-on via the "Change People"
 * modal (paid via a "Payment Link" deferred to a remaining_payment 'addon' item) correctly created
 * a pending item — but TOGGLING THAT SAME ADD-ON BACK OFF moments later left the pending item
 * completely untouched, still showing the old (now wrong, too-high) amount. Root cause:
 * upsertChangePeopleAddonPaymentLink() only ever handled a net ADDITION (addon_delta.net_price >
 * 0) — a net removal/downgrade (net_price <= 0) hit its early return and did nothing at all,
 * regardless of whether an existing pending item covered the now-removed field.
 *
 * Fixed by reduceChangePeopleAddonPaymentLink(): on a net removal, it locates the pending item(s)
 * (matched via db_id, same convention as PaymentLinkService's sibling helpers) and shrinks —
 * or, when nothing is left owed, cancels — them.
 */
class ChangePeopleAddonRemovalShrinksPendingLinkTest extends TestCase
{
    private function reduceViaReflection(array $refundItems, TourBooking $tourBooking): void
    {
        $repo = app(TourBookingRepository::class);
        $method = new ReflectionMethod($repo, 'reduceChangePeopleAddonPaymentLink');
        $method->setAccessible(true);
        $method->invoke($repo, $refundItems, $tourBooking);
    }

    /** @test */
    public function removing_the_only_pending_addon_cancels_the_item_and_the_link(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;
        DB::table('tour_bookings_master')->where('id', $bookingId)->update(['group_id' => $bookingId]);

        $form = AddonForm::create(['tour_package_id' => $booking->packageId, 'name' => 'x' . uniqid(), 'status' => 'Active']);
        $field = AddonField::create([
            'form_id' => $form->id, 'label' => 'Sandwich Option', 'type' => 'dropdown',
            'unit_type' => 'price_per_pax', 'additional_fee' => true, 'price' => 10.00,
            'status' => 'Active', 'visibility' => 'both', 'sort_order' => 0,
        ]);
        $addonField = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $field->id,
            'pax_id' => $booking->transactId, 'field_label' => 'Sandwich Option',
            'pax_index' => 'cp-addon-toggle-0', 'person_ordinal' => 0,
            'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        $link = TourBookingPaymentLink::create([
            'order_id' => $bookingId, 'section' => 'remaining_payment', 'amount' => 10.98,
            'reason_of_payment' => 'Payment for add-ons on your booking',
            'email' => 'test@example.com', 'phone_number' => '5555551234',
            'token' => Str::random(64), 'status' => 'Unpaid', 'override_status' => 'Yes',
            'expired_at' => now()->addDays(30),
            'created_by' => 1, 'created_by_user_type' => 'Admin',
        ]);
        $item = TourBookingPaymentLinkItem::create([
            'payment_link_id' => $link->id, 'type' => 'addon', 'reference_id' => null,
            'amount' => 10.98, 'status' => 'Unpaid',
            'details' => [
                'financial' => [
                    'to_charge' => [[
                        'field_id' => $field->id, 'old_db_id' => null, 'pax_index' => 'cp-addon-toggle-0',
                        'charge_amount' => 10.00,
                    ]],
                    'to_refund' => [], 'fee_amount' => 0.98, 'net_amount' => 10.00,
                ],
                'field_to_db' => [$field->id . '_cp-addon-toggle-0' => $addonField->id],
                'platform_fees' => 0.98,
                'booking_id' => $bookingId,
            ],
            'created_by' => 1, 'created_by_user_type' => 'Admin',
            'updated_by' => 1, 'updated_by_user_type' => 'Admin',
        ]);

        try {
            $this->reduceViaReflection(
                [['db_id' => $addonField->id, 'field_id' => $field->id, 'pax_index' => 'cp-addon-toggle-0', 'price' => 10.00]],
                TourBooking::find($bookingId)
            );

            $this->assertEquals('Cancelled', TourBookingPaymentLinkItem::find($item->id)->status, 'The item must be cancelled — nothing is left owed once its only field is removed');
            $this->assertEquals('Cancelled', TourBookingPaymentLink::find($link->id)->status, 'The link itself must auto-cancel once its only item nets to $0');
        } finally {
            DB::table('tour_booking_payment_link_items')->where('payment_link_id', $link->id)->delete();
            DB::table('tour_booking_payment_links')->where('id', $link->id)->delete();
            TourBookingAddonField::withTrashed()->where('id', $addonField->id)->forceDelete();
            DB::table('addon_fields')->where('id', $field->id)->delete();
            DB::table('addon_forms')->where('id', $form->id)->delete();
            $booking->cleanup();
        }
    }

    /** @test */
    public function removing_one_of_two_bundled_addons_partially_shrinks_the_item(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;
        DB::table('tour_bookings_master')->where('id', $bookingId)->update(['group_id' => $bookingId]);

        $form = AddonForm::create(['tour_package_id' => $booking->packageId, 'name' => 'x' . uniqid(), 'status' => 'Active']);
        $field = AddonField::create([
            'form_id' => $form->id, 'label' => 'Photography Package', 'type' => 'checkbox',
            'unit_type' => 'price_per_pax', 'additional_fee' => true, 'price' => 15.00,
            'status' => 'Active', 'visibility' => 'both', 'sort_order' => 0,
        ]);
        $fieldA = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $field->id,
            'pax_id' => $booking->transactId, 'field_label' => 'Photography Package',
            'pax_index' => 'cp-bundle-0', 'person_ordinal' => 0, 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        $fieldB = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $field->id,
            'pax_id' => $booking->transactId, 'field_label' => 'Photography Package',
            'pax_index' => 'cp-bundle-1', 'person_ordinal' => 1, 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        $link = TourBookingPaymentLink::create([
            'order_id' => $bookingId, 'section' => 'remaining_payment', 'amount' => 32.94,
            'reason_of_payment' => 'Payment for add-ons on your booking',
            'email' => 'test@example.com', 'phone_number' => '5555551234',
            'token' => Str::random(64), 'status' => 'Unpaid', 'override_status' => 'Yes',
            'expired_at' => now()->addDays(30),
            'created_by' => 1, 'created_by_user_type' => 'Admin',
        ]);
        $item = TourBookingPaymentLinkItem::create([
            'payment_link_id' => $link->id, 'type' => 'addon', 'reference_id' => null,
            'amount' => 32.94, 'status' => 'Unpaid',
            'details' => [
                'financial' => [
                    'to_charge' => [
                        ['field_id' => $field->id, 'old_db_id' => null, 'pax_index' => 'cp-bundle-0', 'charge_amount' => 15.00],
                        ['field_id' => $field->id, 'old_db_id' => null, 'pax_index' => 'cp-bundle-1', 'charge_amount' => 15.00],
                    ],
                    'to_refund' => [], 'fee_amount' => 2.94, 'net_amount' => 30.00,
                ],
                'field_to_db' => [
                    $field->id . '_cp-bundle-0' => $fieldA->id,
                    $field->id . '_cp-bundle-1' => $fieldB->id,
                ],
                'platform_fees' => 2.94,
                'booking_id' => $bookingId,
            ],
            'created_by' => 1, 'created_by_user_type' => 'Admin',
            'updated_by' => 1, 'updated_by_user_type' => 'Admin',
        ]);

        try {
            $this->reduceViaReflection(
                [['db_id' => $fieldB->id, 'field_id' => $field->id, 'pax_index' => 'cp-bundle-1', 'price' => 15.00]],
                TourBooking::find($bookingId)
            );

            $refreshed = TourBookingPaymentLinkItem::find($item->id);
            $this->assertEquals('Unpaid', $refreshed->status, 'Pax A\'s own add-on stays behind — the item must NOT be cancelled outright');
            $this->assertEqualsWithDelta(16.47, (float) $refreshed->amount, 0.01, 'Only pax B\'s $15 + its fee share is removed');

            $details = is_array($refreshed->details) ? $refreshed->details : json_decode($refreshed->details, true);
            $this->assertCount(1, $details['financial']['to_charge']);
            $this->assertEquals('cp-bundle-0', $details['financial']['to_charge'][0]['pax_index']);
        } finally {
            DB::table('tour_booking_payment_link_items')->where('payment_link_id', $link->id)->delete();
            DB::table('tour_booking_payment_links')->where('id', $link->id)->delete();
            TourBookingAddonField::withTrashed()->whereIn('id', [$fieldA->id, $fieldB->id])->forceDelete();
            DB::table('addon_fields')->where('id', $field->id)->delete();
            DB::table('addon_forms')->where('id', $form->id)->delete();
            $booking->cleanup();
        }
    }
}
