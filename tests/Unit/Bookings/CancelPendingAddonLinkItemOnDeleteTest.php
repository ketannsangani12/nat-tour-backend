<?php

namespace Tests\Unit\Bookings;

use App\Models\AddonField;
use App\Models\AddonForm;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingPaymentLink;
use App\Models\TourBookingPaymentLinkItem;
use App\Repositories\TourBooking\TourBookingAddonRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: deleting an addon field via the "Edit Passenger Add-ons" flow (or the plain
 * "Booking Add-ons" editor) before its own Pay-Later payment link was ever paid must cancel
 * that field's pending TourBookingPaymentLinkItem — otherwise the customer keeps being asked
 * to pay for an add-on that no longer exists on the booking, and every view that surfaces the
 * link's total (the booking-detail "Pending Payment Link" banner, the payment link email/page)
 * keeps showing it as still owed.
 *
 * CMD: php artisan test --filter=CancelPendingAddonLinkItemOnDeleteTest
 */
class CancelPendingAddonLinkItemOnDeleteTest extends TestCase
{
    public function test_deleting_a_field_cancels_its_pending_addon_link_item(): void
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
            'name' => 'Cancel Pending Addon Link Item Form ' . uniqid(),
            'status' => 'Active',
        ]);

        $photoFieldDef = AddonField::create([
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

        // The never-paid field being removed.
        $photoField = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $photoFieldDef->id,
            'pax_id' => 1, 'field_label' => 'Photography Package',
            'pax_index' => 'pax-1', 'person_ordinal' => 0, 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        $link = TourBookingPaymentLink::create([
            'order_id'             => $groupId,
            'section'              => 'remaining_payment',
            'amount'               => 15.00,
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

        $item = TourBookingPaymentLinkItem::create([
            'payment_link_id'      => $link->id,
            'type'                 => 'addon',
            'reference_id'         => null,
            'amount'               => 15.00,
            'status'               => 'Unpaid',
            'details'              => json_encode([
                'booking_id' => $bookingId,
                'financial'  => [
                    'to_charge' => [
                        ['old_db_id' => $photoField->id, 'field_id' => $photoFieldDef->id, 'pax_index' => 'pax-1'],
                    ],
                    'to_refund' => [],
                ],
                'field_to_db' => [$photoFieldDef->id . '_pax-1' => $photoField->id],
            ]),
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'updated_by'           => 1,
            'updated_by_user_type' => 'Admin',
        ]);

        try {
            $repo = app(TourBookingAddonRepository::class);
            $repo->updateBookingAddons((object) [
                'booking_id' => $bookingId,
                'form_id'    => $form->id,
                'added'      => [],
                'updated'    => [],
                'deleted'    => [
                    ['db_id' => $photoField->id, 'field_id' => $photoFieldDef->id],
                ],
                'financial'  => [
                    'action'    => 'none',
                    'to_charge' => [],
                    'to_refund' => [],
                ],
            ]);

            $this->assertEquals(
                'Cancelled',
                TourBookingPaymentLinkItem::find($item->id)->status,
                'The pending addon link item covering the deleted field must be cancelled'
            );
        } finally {
            TourBookingPaymentLinkItem::where('payment_link_id', $link->id)->delete();
            DB::table('tour_booking_payment_links')->where('id', $link->id)->delete();
            TourBookingAddonField::withTrashed()->where('tour_booking_id', $bookingId)->forceDelete();
            DB::table('addon_fields')->where('id', $photoFieldDef->id)->delete();
            DB::table('addon_forms')->where('id', $form->id)->delete();
            $booking->cleanup();
        }
    }
}
