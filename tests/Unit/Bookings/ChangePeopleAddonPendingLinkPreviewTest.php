<?php

namespace Tests\Unit\Bookings;

use App\Models\AddonField;
use App\Models\AddonFieldOption;
use App\Models\AddonForm;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingAddonTransaction;
use App\Models\TourBookingPaymentLink;
use App\Models\TourBookingPaymentLinkItem;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use App\Services\Booking\ChangePeopleAddonService;
use App\Services\Booking\PaymentLinkService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Follow-ups to ChangePeopleAddonPartlyPaidPendingLinkTest:
 *  - the partly-paid fee baseline must never produce a negative charge fee / a refund of a fee
 *    that was never collected when the earlier payment method priced fees differently;
 *  - the Card PaymentIntent pre-calc (PaymentLinkService::pendingAddonLinkDropAmounts()) must
 *    predict exactly what the link shrinks by once the edit is committed (drop / reprice);
 *  - the Pay Later link upsert must drop a superseded pending line even when the edit nets <= $0.
 */
class ChangePeopleAddonPendingLinkPreviewTest extends TestCase
{
    private const FEE_PCT = 9.809;

    private function scenario(bool $paid, float $paidFee = 1.57): array
    {
        $booking   = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;
        DB::table('tour_bookings_master')->where('id', $bookingId)->update(['group_id' => $bookingId]);
        $packageId = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tour_package_id');

        $form = AddonForm::create(['tour_package_id' => $packageId, 'name' => 'Preview ' . uniqid(), 'status' => 'Active']);
        $def = AddonField::create([
            'form_id' => $form->id, 'label' => 'Boxed Trail Lunch', 'type' => 'dropdown', 'unit_type' => 'price_per_pax',
            'additional_fee' => false, 'price' => 0, 'status' => 'Active', 'visibility' => 'both', 'sort_order' => 0,
        ]);
        $none   = AddonFieldOption::create(['field_id' => $def->id, 'label' => 'No lunch needed', 'value' => 'none', 'additional_fee' => false, 'price' => 0, 'sort_order' => 0]);
        $cheap  = AddonFieldOption::create(['field_id' => $def->id, 'label' => 'Side Salad', 'value' => 'salad', 'additional_fee' => true, 'price' => 10.00, 'sort_order' => 1]);
        $turkey = AddonFieldOption::create(['field_id' => $def->id, 'label' => 'Classic Turkey Wrap', 'value' => 'turkey', 'additional_fee' => true, 'price' => 16.00, 'sort_order' => 2]);
        $gf     = AddonFieldOption::create(['field_id' => $def->id, 'label' => 'Gluten-Free Wrap', 'value' => 'gf_wrap', 'additional_fee' => true, 'price' => 18.00, 'sort_order' => 3]);
        $deluxe = AddonFieldOption::create(['field_id' => $def->id, 'label' => 'Deluxe', 'value' => 'deluxe', 'additional_fee' => true, 'price' => 25.00, 'sort_order' => 4]);

        $row = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $def->id,
            'pax_index' => 'preview-0', 'field_label' => 'Boxed Trail Lunch',
            'option_id' => $gf->id, 'option_label' => 'Gluten-Free Wrap', 'value' => 'gf_wrap',
            'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        if ($paid) {
            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $row->id, 'payment_method' => 'card',
                'status' => 'paid', 'operation' => 'charge', 'field_additional_fee' => false, 'option_additional_fee' => true,
                'field_price' => 0, 'field_fee' => 0, 'option_price' => 16.00, 'option_fee' => $paidFee,
                'operation_at' => now(), 'operation_by' => 1, 'operation_by_user_type' => 'Admin',
            ]);
        }

        $link = TourBookingPaymentLink::create([
            'order_id' => $bookingId, 'section' => 'remaining_payment', 'amount' => $paid ? 2.20 : 19.77,
            'reason_of_payment' => 'Payment for add-ons on your booking', 'email' => 'test@example.com',
            'phone_number' => '5555551234', 'token' => Str::random(64), 'status' => 'Unpaid', 'override_status' => 'Yes',
            'expired_at' => Carbon::now()->addDays(30), 'created_by' => 1, 'created_by_user_type' => 'Admin',
        ]);
        $item = TourBookingPaymentLinkItem::create([
            'payment_link_id' => $link->id, 'type' => 'addon', 'reference_id' => null, 'amount' => $paid ? 2.20 : 19.77,
            'status' => 'Unpaid',
            'details' => [
                'booking_id' => $bookingId, 'platform_fees' => $paid ? 0.20 : 1.77,
                'field_to_db' => [$def->id . '_preview-0' => $row->id],
                'financial' => [
                    'to_charge' => [[
                        'field_id' => $def->id, 'pax_index' => 'preview-0', 'old_db_id' => $row->id,
                        'old_paid' => $paid ? 16.00 : 0, 'new_price' => 18.00, 'charge_amount' => $paid ? 2.00 : 18.00,
                        'field_price' => 0, 'option_price' => 18.00,
                    ]],
                    'to_refund' => [], 'fee_amount' => $paid ? 0.20 : 1.77, 'net_amount' => $paid ? 2.00 : 18.00,
                ],
            ],
            'created_by' => 1, 'created_by_user_type' => 'Admin', 'updated_by' => 1, 'updated_by_user_type' => 'Admin',
        ]);

        return compact('booking', 'bookingId', 'form', 'def', 'none', 'cheap', 'turkey', 'gf', 'deluxe', 'row', 'link', 'item');
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

    private function update(array $s, $option, bool $applyFees): array
    {
        return ChangePeopleAddonService::calculatePricedAddonDelta([], [[
            'db_id' => $s['row']->id, 'field_id' => $s['def']->id, 'option_id' => $option->id, 'value' => $option->value,
        ]], [], self::FEE_PCT, $applyFees);
    }

    /** @test */
    public function partly_paid_charge_never_carries_a_negative_fee_when_this_edit_prices_no_fee(): void
    {
        $s = $this->scenario(true);
        try {
            // $16 was paid by card WITH a $1.57 fee; this edit is priced without fees (e.g. Cash, fees off).
            $d = $this->update($s, $s['deluxe'], false);

            $this->assertEqualsWithDelta(9.00, $d['chargeItems'][0]['price'], 0.001);
            $this->assertEqualsWithDelta(0.00, $d['chargeItems'][0]['fee'], 0.001, 'Fee must clamp at 0, not -$1.57');
            $this->assertGreaterThanOrEqual(0.0, $d['chargeItems'][0]['field_fee']);
            $this->assertGreaterThanOrEqual(0.0, $d['chargeItems'][0]['option_fee']);
            $this->assertGreaterThanOrEqual(0.0, $d['net_fee']);
        } finally {
            $this->cleanup($s);
        }
    }

    /** @test */
    public function partly_paid_downgrade_never_refunds_a_fee_that_was_not_collected(): void
    {
        $s = $this->scenario(true, 0.0); // paid with no fee (e.g. Cash)
        try {
            // Now priced WITH fees (Card): the cheaper $10 option has a $0.98 fee that was never paid.
            $d = $this->update($s, $s['cheap'], true);

            $this->assertEqualsWithDelta(-6.00, $d['net_price'], 0.001);
            $this->assertEqualsWithDelta(0.00, $d['refundItems'][0]['fee'], 0.001, 'No fee was collected, so none may be refunded');
            $this->assertEqualsWithDelta(0.00, $d['net_fee'], 0.001);
        } finally {
            $this->cleanup($s);
        }
    }

    /** @test */
    public function partly_paid_downgrade_refunds_the_fee_difference_that_was_collected(): void
    {
        $s = $this->scenario(true, 1.57);
        try {
            $d = $this->update($s, $s['cheap'], true);

            $this->assertEqualsWithDelta(-6.00, $d['net_price'], 0.001);
            $this->assertGreaterThan(0.0, $d['refundItems'][0]['fee']);
            $this->assertLessThanOrEqual(1.57, $d['refundItems'][0]['fee']);
        } finally {
            $this->cleanup($s);
        }
    }

    /** @test */
    public function drop_preview_matches_what_dropping_the_line_takes_off_the_link(): void
    {
        $s = $this->scenario(true);
        try {
            $preview = PaymentLinkService::pendingAddonLinkDropAmounts($s['bookingId'], [$s['row']->id], null, self::FEE_PCT);
            $this->assertEqualsWithDelta(2.20, $preview['amount'], 0.001, 'The only line is dropped: the whole item amount');

            PaymentLinkService::syncPendingAddonLinkItemsForUpdatedFields($s['bookingId'], [$s['row']->id], [$s['row']->id], false, self::FEE_PCT);
            $this->assertSame('Cancelled', TourBookingPaymentLinkItem::find($s['item']->id)->status);
        } finally {
            $this->cleanup($s);
        }
    }

    /** @test */
    public function reprice_preview_matches_the_real_reprice(): void
    {
        $s = $this->scenario(false);
        try {
            $before = (float) TourBookingPaymentLinkItem::find($s['item']->id)->amount;

            // Preview BEFORE the new selection is saved -- this is what the PaymentIntent sees.
            $d = $this->update($s, $s['turkey'], true);
            $this->assertSame([$s['row']->id], $d['pending_link_reprice_db_ids']);
            $this->assertArrayHasKey($s['row']->id, $d['pending_link_reprice_selections']);
            $preview = PaymentLinkService::pendingAddonLinkDropAmounts(
                $s['bookingId'], [], null, self::FEE_PCT, $d['pending_link_reprice_selections']
            );
            $this->assertGreaterThan(0.0, $preview['amount']);

            // Commit: save the selection, then the real reprice.
            TourBookingAddonField::where('id', $s['row']->id)->update([
                'option_id' => $s['turkey']->id, 'option_label' => 'Classic Turkey Wrap', 'value' => 'turkey',
            ]);
            PaymentLinkService::syncPendingAddonLinkItemsForUpdatedFields($s['bookingId'], [$s['row']->id], [], false, self::FEE_PCT);
            $after = (float) TourBookingPaymentLinkItem::find($s['item']->id)->amount;

            $this->assertEqualsWithDelta($before - $after, $preview['amount'], 0.001, 'Preview and real reprice must agree to the cent');
        } finally {
            $this->cleanup($s);
        }
    }

    /** @test */
    public function reprice_preview_to_a_free_selection_drops_the_whole_line(): void
    {
        $s = $this->scenario(false);
        try {
            $d = $this->update($s, $s['none'], true);
            $preview = PaymentLinkService::pendingAddonLinkDropAmounts(
                $s['bookingId'], [], null, self::FEE_PCT, $d['pending_link_reprice_selections']
            );
            $this->assertEqualsWithDelta(19.77, $preview['amount'], 0.001);
        } finally {
            $this->cleanup($s);
        }
    }

    /** @test */
    public function pay_later_link_upsert_drops_a_superseded_line_even_when_the_edit_nets_to_zero(): void
    {
        $s = $this->scenario(false);
        try {
            $repo = app(TourBookingRepository::class);
            $method = new ReflectionMethod($repo, 'upsertChangePeopleAddonPaymentLink');
            $tourBooking = (object) ['id' => $s['bookingId'], 'group_id' => $s['bookingId']];

            // An upgrade of the pending field offset by a bigger downgrade elsewhere: net $0, so
            // nothing new is added to the link -- but the old pending line is still superseded.
            $method->invoke(
                $repo,
                ['net_price' => 0, 'net_fee' => 0, 'refundItems' => [], 'chargeItems' => [[
                    'db_id' => $s['row']->id, 'field_id' => $s['def']->id, 'price' => 25.0, 'supersedes_pending_link' => true,
                ]]],
                (object) ['is_affiliate' => 'No'], $s['bookingId'], (object) ['bookingID' => $s['bookingId']], self::FEE_PCT,
                $tourBooking, [], 'tester', Carbon::now(), (object) []
            );

            $this->assertSame('Cancelled', TourBookingPaymentLinkItem::find($s['item']->id)->status);
        } finally {
            $this->cleanup($s);
        }
    }
}
