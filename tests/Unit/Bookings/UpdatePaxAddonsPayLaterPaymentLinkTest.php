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
 * Regression (real-world report): the reported flow ("Add Passenger Add-ons" popup, "Payment
 * Required" modal choosing "Pay Later / Billable") posts to update-pax-addons →
 * TourBookingAddonRepository::updatePaxAddons() — a SEPARATE method from updateBookingAddons(),
 * which a prior fix updated but this one was missed. Confirmed against the real booking: its
 * whole-booking "Payment Link" (section=null) amount never changed and no new email was queued
 * after adding a $15 add-on. Both methods now share snapshotPayLaterAddonsForPaymentLink()/
 * syncPayLaterAddonsPaymentLink() so this can't drift apart again.
 */
class UpdatePaxAddonsPayLaterPaymentLinkTest extends TestCase
{
    /** @test */
    public function adding_a_pax_addon_via_update_pax_addons_updates_and_resends_the_active_payment_link(): void
    {
        Mail::fake();

        $booking = (new CreateABooking())->withPaymentMethod('Pay Later')->withoutTransaction()->create();
        $bookingId = $booking->bookingId;
        DB::table('tour_bookings_master')->where('id', $bookingId)->update(['group_id' => $bookingId]);
        $originalAmount = (float) DB::table('tour_bookings_master')->where('id', $bookingId)->value('total');

        $packageId = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tour_package_id');
        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name' => 'Update Pax Addons Payment Link Test Form ' . uniqid(),
            'status' => 'Active',
        ]);
        $photoField = AddonField::create([
            'form_id' => $form->id, 'label' => 'Photography Package', 'type' => 'checkbox',
            'unit_type' => 'price_per_pax', 'additional_fee' => true, 'price' => 15.00,
            'status' => 'Active', 'visibility' => 'both', 'sort_order' => 0,
        ]);

        $link = TourBookingPaymentLink::create([
            'order_id' => $bookingId, 'section' => null, 'amount' => $originalAmount,
            'reason_of_payment' => 'A payment link has been generated for Test Package, which includes 1 Adult, with a total amount of $' . number_format($originalAmount, 2) . '.',
            'email' => 'test@example.com', 'phone_number' => '5555551234',
            'token' => Str::random(64), 'status' => 'Unpaid', 'override_status' => 'Yes',
            'expired_at' => now()->addDays(30),
            'created_by' => 1, 'created_by_user_type' => 'Admin',
        ]);

        try {
            $repo = app(TourBookingAddonRepository::class);
            $repo->updatePaxAddons((object) [
                'booking_id' => $bookingId,
                'form_id'    => $form->id,
                'pax'        => [
                    [
                        'pax_id' => $booking->transactId, 'pax_index' => 'link-test-adult-1', 'person_ordinal' => 0,
                        'added'  => [[
                            'field_id' => $photoField->id, 'option_id' => null, 'option_label' => null,
                            'value' => null, 'unit_type' => 'price_per_pax', 'unit' => 1,
                            'rule_id' => null, 'applied_rule_snapshot' => null,
                        ]],
                        'updated' => [], 'deleted' => [],
                    ],
                ],
                'financial' => [
                    'action' => 'charge', 'net_amount' => 15.00, 'total_charge' => 15.00, 'total_refund' => 0,
                    'to_charge' => [[
                        'field_id' => $photoField->id, 'pax_index' => 'link-test-adult-1', 'label' => 'Photography Package',
                        'option_id' => null, 'unit' => 1, 'new_price' => 15.00, 'old_db_id' => null, 'old_paid' => 0,
                        'charge_amount' => 15.00, 'field_price' => 15.00, 'option_price' => 0,
                        'field_additional_fee' => true, 'option_additional_fee' => false, 'reason' => 'added',
                    ]],
                    'to_refund' => [],
                    'payment_method' => 'Pay Later',
                ],
            ]);

            $newField = TourBookingAddonField::where('tour_booking_id', $bookingId)->where('field_id', $photoField->id)->first();
            $this->assertNotNull($newField, 'Sanity check: the add-on field must have actually been created');

            $this->assertEquals('Expired', TourBookingPaymentLink::find($link->id)->status, 'The stale link must be superseded');

            $updatedLink = TourBookingPaymentLink::where('order_id', $bookingId)->where('status', 'Unpaid')->latest('id')->first();
            $this->assertNotNull($updatedLink, 'A fresh Unpaid link must be created and (re)sent to the customer');
            $this->assertEqualsWithDelta($originalAmount + 15.00, (float) $updatedLink->amount, 0.01, 'The link must grow by exactly the new add-on\'s price (no NAT fee configured on this fixture)');

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
