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
 * Regression: a genuinely never-paid (Pay Later) booking that already has an active, whole-booking
 * "Payment Link" (sent via the toolbar's "Payment Link" button — section=null, distinct from the
 * itemized remaining_payment links) must have that link's amount updated (and resent) whenever
 * add-ons are added or removed — addCustomCharge() already does this for custom charges via
 * PaymentLinkService::paymentLinkUpdate(), but updateBookingAddons() never did the same for
 * add-ons, so the customer kept being sent (or shown) a stale, un-updated total.
 */
class PayLaterAddonUpdatesPaymentLinkTest extends TestCase
{
    /** @test */
    public function adding_an_addon_increases_the_active_payment_link_and_resends_it(): void
    {
        Mail::fake();

        $booking = (new CreateABooking())->withoutTransaction()->create();
        $bookingId = $booking->bookingId;
        DB::table('tour_bookings_master')->where('id', $bookingId)->update(['group_id' => $bookingId]);
        $originalAmount = (float) DB::table('tour_bookings_master')->where('id', $bookingId)->value('total');

        $form = AddonForm::create([
            'tour_package_id' => $booking->packageId,
            'name' => 'Pay Later Addon Payment Link Test Form ' . uniqid(),
            'status' => 'Active',
        ]);
        $addonFieldDef = AddonField::create([
            'form_id' => $form->id, 'label' => 'Sandwich Option', 'type' => 'checkbox',
            'unit_type' => 'price_per_pax', 'additional_fee' => true, 'price' => 10.00,
            'status' => 'Active', 'visibility' => 'both', 'sort_order' => 0,
        ]);

        // The booking's whole-total "Payment Link" (section=null) — already covers the base
        // ticket only, no add-ons yet, matching the reported scenario's starting state.
        $link = TourBookingPaymentLink::create([
            'order_id' => $bookingId, 'section' => null, 'amount' => $originalAmount,
            'reason_of_payment' => 'Make Payment', 'email' => 'test@example.com', 'phone_number' => '5555551234',
            'token' => Str::random(64), 'status' => 'Unpaid', 'override_status' => 'Yes',
            'expired_at' => now()->addDays(30),
            'created_by' => 1, 'created_by_user_type' => 'Admin',
        ]);

        try {
            $repo = app(TourBookingAddonRepository::class);
            $repo->updateBookingAddons((object) [
                'booking_id' => $bookingId,
                'form_id'    => $form->id,
                'added'      => [
                    [
                        'field_id' => $addonFieldDef->id, 'option_id' => null, 'option_label' => null,
                        'value' => 'checked', 'unit_type' => 'price_per_pax', 'unit' => 1,
                        'rule_id' => null, 'applied_rule_snapshot' => null,
                    ],
                ],
                'updated'    => [],
                'deleted'    => [],
                'financial'  => ['action' => 'none', 'to_charge' => [], 'to_refund' => []],
            ]);

            $newField = TourBookingAddonField::where('tour_booking_id', $bookingId)->whereNull('deleted_at')->first();
            $this->assertNotNull($newField, 'Sanity check: the add-on field must have actually been created');

            $expiredOriginal = TourBookingPaymentLink::find($link->id);
            $this->assertEquals('Expired', $expiredOriginal->status, 'The stale link must be superseded, not left dangling as if still current');

            $updatedLink = TourBookingPaymentLink::where('order_id', $bookingId)->where('status', 'Unpaid')->latest('id')->first();
            $this->assertNotNull($updatedLink, 'A fresh Unpaid link must be created and (re)sent to the customer');
            $this->assertEqualsWithDelta(
                $originalAmount + 10.00,
                (float) $updatedLink->amount,
                0.01,
                'The link must grow by exactly the new add-on\'s price (no NAT fee configured on this fixture)'
            );

            Mail::assertQueued(TourBookingPaymentLinkMailable::class);

            DB::table('tour_booking_payment_links')->where('id', $updatedLink->id)->delete();
        } finally {
            DB::table('tour_booking_payment_links')->where('id', $link->id)->delete();
            TourBookingAddonField::withTrashed()->where('tour_booking_id', $bookingId)->forceDelete();
            DB::table('addon_fields')->where('id', $addonFieldDef->id)->delete();
            DB::table('addon_forms')->where('id', $form->id)->delete();
            $booking->cleanup();
        }
    }
}
