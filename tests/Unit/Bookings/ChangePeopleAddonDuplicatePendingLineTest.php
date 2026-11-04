<?php

namespace Tests\Unit\Bookings;

use App\Models\AddonField;
use App\Models\AddonFieldOption;
use App\Models\AddonForm;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingPaymentLink;
use App\Models\TourBookingPaymentLinkItem;
use App\Services\Booking\ChangePeopleAddonService;
use App\Services\Booking\PaymentLinkService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression (real-world report, booking #41960): a pax add-on was added via Change People →
 * "Pay with Link" (Vegetarian Wrap $16, link item with an EMPTY field_to_db and the field on the
 * line's old_db_id), changed again in Change People, then changed from "Edit Passenger Add-ons".
 * The link ended up with TWO $17.57 lines for the one $16 lunch:
 *  - fieldHasUnpaidLinkItem() only looked at field_to_db, so the second Change People edit didn't
 *    see the field as pending and stacked a second line instead of superseding the first;
 *  - repricing set EVERY pending line of the field to its whole current price.
 */
class ChangePeopleAddonDuplicatePendingLineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Mail::fake();
    }

    private function scenario(int $lineCount): array
    {
        $booking   = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;
        DB::table('tour_bookings_master')->where('id', $bookingId)->update(['group_id' => $bookingId]);
        $packageId = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tour_package_id');
        DB::table('tour_bookings_transact')->where('tour_booking_id', $bookingId)->update(['nat_fees_percentage' => 9.809]);

        $form = AddonForm::create(['tour_package_id' => $packageId, 'name' => 'Dup Line ' . uniqid(), 'status' => 'Active']);
        $def = AddonField::create([
            'form_id' => $form->id, 'label' => 'Boxed Trail Lunch', 'type' => 'dropdown', 'unit_type' => 'price_per_pax',
            'additional_fee' => false, 'price' => 0, 'status' => 'Active', 'visibility' => 'both', 'sort_order' => 0,
        ]);
        $veg = AddonFieldOption::create(['field_id' => $def->id, 'label' => 'Vegetarian Wrap', 'value' => 'veg', 'additional_fee' => true, 'price' => 16.00, 'sort_order' => 0]);
        $gf  = AddonFieldOption::create(['field_id' => $def->id, 'label' => 'Gluten-Free Wrap', 'value' => 'gf_wrap', 'additional_fee' => true, 'price' => 18.00, 'sort_order' => 1]);
        $row = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $def->id,
            'pax_index' => 'dup-line-1', 'field_label' => 'Boxed Trail Lunch',
            'option_id' => $veg->id, 'option_label' => 'Vegetarian Wrap', 'value' => 'veg', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        $link = TourBookingPaymentLink::create([
            'order_id' => $bookingId, 'section' => 'remaining_payment', 'amount' => 17.57 * $lineCount,
            'reason_of_payment' => 'Payment for add-ons on your booking', 'email' => 'test@example.com',
            'phone_number' => '5555551234', 'token' => Str::random(64), 'status' => 'Unpaid', 'override_status' => 'Yes',
            'expired_at' => Carbon::now()->addDays(30), 'created_by' => 1, 'created_by_user_type' => 'Admin',
        ]);
        // Exactly the shape upsertChangePeopleAddonPaymentLink() writes: field_to_db empty,
        // the field only on the line's old_db_id.
        $items = [];
        for ($i = 0; $i < $lineCount; $i++) {
            $items[] = TourBookingPaymentLinkItem::create([
                'payment_link_id' => $link->id, 'type' => 'addon', 'reference_id' => null, 'amount' => 17.57, 'status' => 'Unpaid',
                'details' => [
                    'booking_id' => $bookingId, 'form_id' => (string) $form->id, 'field_to_db' => [], 'platform_fees' => 1.57,
                    'financial' => [
                        'action' => 'charge', 'net_amount' => 16, 'to_refund' => [],
                        'to_charge' => [[
                            'field_id' => $def->id, 'pax_index' => 'dup-line-1', 'old_db_id' => $row->id, 'option_id' => $veg->id,
                            'charge_amount' => 16, 'new_price' => 16, 'field_price' => 0, 'option_price' => 16,
                            'field_fee' => 0, 'option_fee' => 1.57, 'option_additional_fee' => true,
                        ]],
                    ],
                    'added' => [], 'updated' => [], 'pax' => [],
                ],
                'created_by' => 1, 'created_by_user_type' => 'Admin', 'updated_by' => 1, 'updated_by_user_type' => 'Admin',
            ]);
        }

        return compact('booking', 'bookingId', 'form', 'def', 'veg', 'gf', 'row', 'link', 'items');
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

    /** @test */
    public function a_change_people_pending_line_found_only_via_old_db_id_is_superseded_by_an_upgrade(): void
    {
        $s = $this->scenario(1);
        try {
            $d = ChangePeopleAddonService::calculatePricedAddonDelta([], [[
                'db_id' => $s['row']->id, 'field_id' => $s['def']->id, 'option_id' => $s['gf']->id, 'value' => 'gf_wrap',
            ]], [], 9.809, true);

            $this->assertCount(1, $d['chargeItems']);
            $this->assertTrue($d['chargeItems'][0]['supersedes_pending_link'],
                'The field is pending via Change People — the upgrade must supersede that line, not stack beside it');
            $this->assertEqualsWithDelta(18.00, $d['net_price'], 0.001, 'Never paid → the full new price, replacing the $16 line');
        } finally {
            $this->cleanup($s);
        }
    }

    /** @test */
    public function change_people_pay_with_link_upgrade_replaces_the_fields_pending_line(): void
    {
        $s = $this->scenario(1);
        try {
            // As changePeopleBooking(): the delta is computed against the saved (Vegetarian) row,
            // then processAddons() saves the new selection, then the link is updated.
            $delta = ChangePeopleAddonService::calculatePricedAddonDelta([], [[
                'db_id' => $s['row']->id, 'field_id' => $s['def']->id, 'option_id' => $s['gf']->id, 'value' => 'gf_wrap',
            ]], [], 9.809, true);
            $s['row']->update(['option_id' => $s['gf']->id, 'option_label' => 'Gluten-Free Wrap', 'value' => 'gf_wrap']);

            $repo = app(\App\Repositories\TourBooking\V3\TourBookingRepository::class);
            $method = new \ReflectionMethod($repo, 'upsertChangePeopleAddonPaymentLink');
            $method->setAccessible(true);
            $tourBooking = \App\Models\TourBooking::find($s['bookingId']);
            $info = \App\Utils\BookingUtils::getBookingDetails($s['bookingId']);
            $method->invoke(
                $repo, $delta, (object) ['is_affiliate' => 'No'], 0,
                (object) ['bookingID' => $s['bookingId'], 'addons_form_id' => $s['form']->id],
                9.809, $tourBooking, ['field_db_id_map' => []], 'Test: (Admin)', Carbon::now()->addDay(), $info
            );

            $unpaid = TourBookingPaymentLinkItem::whereHas('paymentLink', fn ($q) => $q->where('order_id', $s['bookingId']))
                ->where('type', 'addon')->where('status', 'Unpaid')->get();
            $this->assertCount(1, $unpaid, 'Only the new Gluten-Free line may be pending — the old Vegetarian line is superseded');
            $this->assertEqualsWithDelta(18.00 + round(18 * 9.809 / 100, 2), (float) $unpaid->first()->amount, 0.02);
        } finally {
            $this->cleanup($s);
        }
    }

    /** @test */
    public function repricing_a_field_with_two_pending_lines_keeps_only_one(): void
    {
        $s = $this->scenario(2);
        try {
            PaymentLinkService::syncPendingAddonLinkItemsForUpdatedFields($s['bookingId'], [$s['row']->id], [], true);

            $unpaid = TourBookingPaymentLinkItem::where('payment_link_id', $s['link']->id)->where('status', 'Unpaid')->get();
            $this->assertCount(1, $unpaid, 'One $16 lunch must be on the link once, not once per line');
            $this->assertEqualsWithDelta(17.57, (float) $unpaid->sum('amount'), 0.02);
        } finally {
            $this->cleanup($s);
        }
    }
}
