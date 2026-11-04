<?php

namespace Tests\Unit\Bookings;

use App\Mail\TourBooking\TourBookingPaymentLink as TourBookingPaymentLinkMailable;
use App\Models\AddonField;
use App\Models\AddonForm;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingPaymentLink;
use App\Repositories\TourBooking\TourBookingAddonRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression (real-world report): the "Passenger Add-ons" modal's "Delete Add-on(s)" button
 * posts to delete-pax-addons -> TourBookingAddonRepository::deletePaxAddons() -- a THIRD add-on
 * entry point, separate from updateBookingAddons()/updatePaxAddons() (already fixed to keep a
 * never-paid booking's whole-booking Payment Link in sync). Removing a priced add-on left that
 * same link stuck at its old (too high) amount, with no updated link resent to the customer.
 */
class DeletePaxAddonsPayLaterPaymentLinkTest extends TestCase
{
    /** @test */
    public function deleting_a_pax_addon_shrinks_the_active_payment_link_and_resends_it(): void
    {
        Mail::fake();

        $booking = (new CreateABooking())->withPaymentMethod('Pay Later')->withoutTransaction()->create();
        $bookingId = $booking->bookingId;
        DB::table('tour_bookings_master')->where('id', $bookingId)->update(['group_id' => $bookingId]);
        $baseTicketTotal = (float) DB::table('tour_bookings_master')->where('id', $bookingId)->value('total');

        $packageId = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tour_package_id');
        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name' => 'Delete Pax Addons Payment Link Test Form ' . uniqid(),
            'status' => 'Active',
        ]);
        $photoField = AddonField::create([
            'form_id' => $form->id, 'label' => 'Photography Package', 'type' => 'checkbox',
            'unit_type' => 'price_per_pax', 'additional_fee' => true, 'price' => 15.00,
            'status' => 'Active', 'visibility' => 'both', 'sort_order' => 0,
        ]);

        // The never-paid add-on already on the booking (mirrors the reported "Pending" field).
        $addonField = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $photoField->id,
            'pax_id' => $booking->transactId, 'field_label' => 'Photography Package',
            'pax_index' => 'delete-test-adult-1', 'person_ordinal' => 0,
            'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        // The active whole-booking Payment Link already includes this add-on's $15 (no NAT fee
        // configured on this fixture, matching the sibling PayLaterAddonUpdatesPaymentLinkTest).
        $startingAmount = $baseTicketTotal + 15.00;
        $link = TourBookingPaymentLink::create([
            'order_id' => $bookingId, 'section' => null, 'amount' => $startingAmount,
            'reason_of_payment' => 'A payment link has been generated for Test Package, which includes 1 Adult, with a total amount of $' . number_format($startingAmount, 2) . '.',
            'email' => 'test@example.com', 'phone_number' => '5555551234',
            'token' => Str::random(64), 'status' => 'Unpaid', 'override_status' => 'Yes',
            'expired_at' => now()->addDays(30),
            'created_by' => 1, 'created_by_user_type' => 'Admin',
        ]);

        try {
            $repo = app(TourBookingAddonRepository::class);
            $repo->deletePaxAddons($bookingId, json_encode(['delete-test-adult-1']));

            $this->assertSoftDeleted('tour_booking_addon_fields', ['id' => $addonField->id]);

            $this->assertEquals('Expired', TourBookingPaymentLink::find($link->id)->status, 'The stale (too high) link must be superseded');

            $updatedLink = TourBookingPaymentLink::where('order_id', $bookingId)->where('status', 'Unpaid')->latest('id')->first();
            $this->assertNotNull($updatedLink, 'A fresh Unpaid link reflecting the removal must be created and (re)sent to the customer');
            $this->assertEqualsWithDelta($baseTicketTotal, (float) $updatedLink->amount, 0.01, 'The link must shrink back down by exactly the removed add-on\'s $15');

            Mail::assertQueued(TourBookingPaymentLinkMailable::class);

            DB::table('tour_booking_payment_links')->where('id', $updatedLink->id)->delete();
        } finally {
            DB::table('tour_booking_payment_links')->where('id', $link->id)->delete();
            TourBookingAddonField::withTrashed()->where('tour_booking_id', $bookingId)->forceDelete();
            DB::table('addon_fields')->where('id', $photoField->id)->delete();
            DB::table('addon_forms')->where('id', $form->id)->delete();
            $booking->cleanup();
        }
    }
}
