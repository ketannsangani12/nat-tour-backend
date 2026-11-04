<?php

namespace Tests\Unit\Bookings;

use App\Models\AddonField;
use App\Models\AddonFieldOption;
use App\Models\AddonForm;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingAddonTransaction;
use App\Models\TourBookingPaymentLink;
use App\Models\TourBookingPaymentLinkItem;
use App\Services\Booking\ChangePeopleAddonService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression (real-world report, booking #41958): a card-paid pax add-on (Classic Turkey Wrap $16)
 * was upgraded via "Pay with Link" (Gluten-Free Wrap, +$2 still pending), then removed/downgraded
 * in Change People. calculatePricedAddonDelta() treated ANY field with a pending link line as
 * "nothing paid", so → "No lunch needed" came out as a $0 change: no refund was recorded and the
 * edit fell into the Card-charge branch with no PaymentIntent ("Card payment could not be
 * processed"). The baseline is now what was actually paid; entirely unpaid fields moved to a
 * cheaper selection just get their pending line repriced.
 */
class ChangePeopleAddonPartlyPaidPendingLinkTest extends TestCase
{
    private function scenario(bool $paid): array
    {
        $booking   = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;
        DB::table('tour_bookings_master')->where('id', $bookingId)->update(['group_id' => $bookingId]);
        $packageId = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tour_package_id');

        $form = AddonForm::create(['tour_package_id' => $packageId, 'name' => 'Partly Paid ' . uniqid(), 'status' => 'Active']);
        $def = AddonField::create([
            'form_id' => $form->id, 'label' => 'Boxed Trail Lunch', 'type' => 'dropdown', 'unit_type' => 'price_per_pax',
            'additional_fee' => false, 'price' => 0, 'status' => 'Active', 'visibility' => 'both', 'sort_order' => 0,
        ]);
        $none   = AddonFieldOption::create(['field_id' => $def->id, 'label' => 'No lunch needed', 'value' => 'none', 'additional_fee' => false, 'price' => 0, 'sort_order' => 0]);
        $turkey = AddonFieldOption::create(['field_id' => $def->id, 'label' => 'Classic Turkey Wrap', 'value' => 'turkey', 'additional_fee' => true, 'price' => 16.00, 'sort_order' => 1]);
        $gf     = AddonFieldOption::create(['field_id' => $def->id, 'label' => 'Gluten-Free Wrap', 'value' => 'gf_wrap', 'additional_fee' => true, 'price' => 18.00, 'sort_order' => 2]);

        $row = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $def->id,
            'pax_index' => 'partly-paid-0', 'field_label' => 'Boxed Trail Lunch',
            'option_id' => $gf->id, 'option_label' => 'Gluten-Free Wrap', 'value' => 'gf_wrap',
            'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        if ($paid) {
            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $row->id, 'payment_method' => 'card',
                'status' => 'paid', 'operation' => 'charge', 'field_additional_fee' => false, 'option_additional_fee' => true,
                'field_price' => 0, 'field_fee' => 0, 'option_price' => 16.00, 'option_fee' => 1.57,
                'operation_at' => now(), 'operation_by' => 1, 'operation_by_user_type' => 'Admin',
            ]);
        }

        $link = TourBookingPaymentLink::create([
            'order_id' => $bookingId, 'section' => 'remaining_payment', 'amount' => $paid ? 2.20 : 19.77,
            'reason_of_payment' => 'Payment for add-ons on your booking', 'email' => 'test@example.com',
            'phone_number' => '5555551234', 'token' => Str::random(64), 'status' => 'Unpaid', 'override_status' => 'Yes',
            'expired_at' => Carbon::now()->addDays(30), 'created_by' => 1, 'created_by_user_type' => 'Admin',
        ]);
        TourBookingPaymentLinkItem::create([
            'payment_link_id' => $link->id, 'type' => 'addon', 'reference_id' => null, 'amount' => $paid ? 2.20 : 19.77,
            'status' => 'Unpaid',
            'details' => [
                'booking_id' => $bookingId, 'platform_fees' => $paid ? 0.20 : 1.77,
                'field_to_db' => [$def->id . '_partly-paid-0' => $row->id],
                'financial' => [
                    'to_charge' => [[
                        'field_id' => $def->id, 'pax_index' => 'partly-paid-0', 'old_db_id' => $row->id,
                        'old_paid' => $paid ? 16.00 : 0, 'new_price' => 18.00, 'charge_amount' => $paid ? 2.00 : 18.00,
                        'field_price' => 0, 'option_price' => 18.00,
                    ]],
                    'to_refund' => [], 'fee_amount' => $paid ? 0.20 : 1.77, 'net_amount' => $paid ? 2.00 : 18.00,
                ],
            ],
            'created_by' => 1, 'created_by_user_type' => 'Admin', 'updated_by' => 1, 'updated_by_user_type' => 'Admin',
        ]);

        return compact('booking', 'bookingId', 'form', 'def', 'none', 'turkey', 'gf', 'row', 'link');
    }

    private function cleanup(array $s): void
    {
        DB::table('tour_booking_payment_link_items')->where('payment_link_id', $s['link']->id)->delete();
        DB::table('tour_booking_payment_links')->where('order_id', $s['bookingId'])->delete();
        TourBookingAddonField::withTrashed()->where('tour_booking_id', $s['bookingId'])->forceDelete();
        DB::table('tour_booking_addon_transactions')->where('tour_booking_id', $s['bookingId'])->delete();
        DB::table('addon_field_options')->where('field_id', $s['def']->id)->delete();
        DB::table('addon_fields')->where('id', $s['def']->id)->delete();
        DB::table('addon_forms')->where('id', $s['form']->id)->delete();
        $s['booking']->cleanup();
    }

    private function update(array $s, $option): array
    {
        return ChangePeopleAddonService::calculatePricedAddonDelta([], [[
            'db_id' => $s['row']->id, 'field_id' => $s['def']->id, 'option_id' => $option->id, 'value' => $option->value,
        ]], [], 9.809, true);
    }

    /** @test */
    public function partly_paid_field_moved_to_no_lunch_refunds_only_what_was_paid(): void
    {
        $s = $this->scenario(true);
        try {
            $d = $this->update($s, $s['none']);
            $this->assertEqualsWithDelta(-16.00, $d['net_price'], 0.001, 'Must be a real $16 refund, not a $0 change');
            $this->assertCount(1, $d['refundItems']);
            $this->assertTrue($d['refundItems'][0]['supersedes_pending_link']);
            $this->assertEmpty($d['chargeItems']);
        } finally {
            $this->cleanup($s);
        }
    }

    /** @test */
    public function partly_paid_field_back_to_the_paid_option_moves_no_money_but_drops_the_pending_line(): void
    {
        $s = $this->scenario(true);
        try {
            $d = $this->update($s, $s['turkey']);
            $this->assertEqualsWithDelta(0.00, $d['net_price'], 0.001);
            $this->assertEmpty($d['chargeItems']);
            $this->assertEmpty($d['refundItems']);
            $this->assertSame([$s['row']->id], $d['pending_link_drop_db_ids']);
        } finally {
            $this->cleanup($s);
        }
    }

    /** @test */
    public function dropped_unpaid_pending_amount_is_recorded_as_dont_refund_in_history(): void
    {
        $s = $this->scenario(true);
        try {
            $d = $this->update($s, $s['turkey']);
            $this->assertCount(1, $d['unpaid_dont_refund_items']);
            $this->assertEqualsWithDelta(2.00, $d['unpaid_dont_refund_items'][0]['price'], 0.001);

            $d = $this->update($s, $s['none']);
            $this->assertEqualsWithDelta(2.00, $d['unpaid_dont_refund_items'][0]['price'], 0.001,
                'Removing it also drops the unpaid $2 — recorded as Don\'t Refund next to the real $16 refund');

            // End to end: processAddons() writes the history row.
            $request = new \Illuminate\Http\Request();
            $request->merge([
                'addons_added' => [], 'addons_removed' => [],
                'addons_updated' => [[
                    'db_id' => $s['row']->id, 'field_id' => $s['def']->id, 'option_id' => $s['turkey']->id,
                    'value' => 'turkey', 'pax_index' => 'partly-paid-0',
                ]],
            ]);
            ChangePeopleAddonService::processAddons($request, $s['bookingId'], [], ['action' => 'none']);

            $row = DB::table('tour_booking_history')->where('tour_booking_id', $s['bookingId'])
                ->where('operation', 'Refund')->orderByDesc('id')->first();
            $this->assertNotNull($row, 'A "Don\'t Refund" history entry must exist for the dropped $2');
            $details = json_decode($row->details, true);
            $this->assertEqualsWithDelta(2.00, (float) $details['addons'][0]['price'], 0.001);
            $this->assertSame("Don't Refund", $details['addons'][0]['status']);
        } finally {
            DB::table('tour_booking_history')->where('tour_booking_id', $s['bookingId'])->delete();
            $this->cleanup($s);
        }
    }

    /** @test */
    public function never_paid_downgrade_records_the_reduction_as_dont_refund(): void
    {
        $s = $this->scenario(false);
        try {
            $d = $this->update($s, $s['turkey']);
            $this->assertCount(1, $d['unpaid_dont_refund_items']);
            $this->assertEqualsWithDelta(2.00, $d['unpaid_dont_refund_items'][0]['price'], 0.001);
        } finally {
            $this->cleanup($s);
        }
    }

    /** @test */
    public function untouched_field_with_a_pending_line_is_left_alone(): void
    {
        $s = $this->scenario(true);
        try {
            $d = $this->update($s, $s['gf']);
            $this->assertEqualsWithDelta(0.00, $d['net_price'], 0.001, 'An unchanged selection must not pull the pending $2 into this edit');
            $this->assertEmpty($d['chargeItems']);
            $this->assertEmpty($d['pending_link_drop_db_ids']);
        } finally {
            $this->cleanup($s);
        }
    }

    /** @test */
    public function removing_a_partly_paid_field_refunds_only_the_paid_amount(): void
    {
        $s = $this->scenario(true);
        try {
            $d = ChangePeopleAddonService::calculatePricedAddonDelta([], [], [['db_id' => $s['row']->id]], 9.809, true);
            $this->assertEqualsWithDelta(-16.00, $d['net_price'], 0.001, 'Not the full $18 — only $16 was ever charged');
        } finally {
            $this->cleanup($s);
        }
    }

    /** @test */
    public function entirely_unpaid_field_downgraded_is_repriced_on_the_link_not_charged(): void
    {
        $s = $this->scenario(false);
        try {
            $d = $this->update($s, $s['turkey']);
            $this->assertEqualsWithDelta(0.00, $d['net_price'], 0.001, 'No card charge for a cheaper, still-unpaid selection');
            $this->assertEmpty($d['chargeItems']);
            $this->assertSame([$s['row']->id], $d['pending_link_reprice_db_ids']);
        } finally {
            $this->cleanup($s);
        }
    }

    /** @test */
    public function entirely_unpaid_field_upgraded_still_charges_the_full_new_price(): void
    {
        $s = $this->scenario(false);
        try {
            DB::table('addon_field_options')->where('id', $s['gf']->id)->update(['price' => 18.00]);
            $xl = AddonFieldOption::create(['field_id' => $s['def']->id, 'label' => 'Deluxe', 'value' => 'deluxe', 'additional_fee' => true, 'price' => 25.00, 'sort_order' => 3]);
            $d = $this->update($s, $xl);
            $this->assertEqualsWithDelta(25.00, $d['net_price'], 0.001, 'Existing behaviour: never-paid upgrade charges the full new price');
            $this->assertTrue($d['chargeItems'][0]['supersedes_pending_link']);
        } finally {
            $this->cleanup($s);
        }
    }
}
