<?php

namespace Tests\Unit\Bookings;

use App\Models\AddonField;
use App\Models\AddonFieldOption;
use App\Models\AddonForm;
use App\Models\Admin;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingAddonTransaction;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression (real-world report, booking #41955): Pay Later booking collected via its original
 * lump-sum Payment Link. In "Edit Booking - Change People" the same pax's add-on dropdown was
 * changed Classic Turkey Wrap ($16, already folded into the link) → Gluten-Free Wrap ($18):
 *  - the link moved by $4 + fee instead of $2 + fee — the Pay Later fast path merged the add-on
 *    delta into $paymentTotal AND added it again when building the paymentLinkUpdate() amount;
 *  - the field's never-collected 'open' charge stayed at $16, so paying the link later settled
 *    the add-on at $16 although the selection is $18.
 */
class ChangePeoplePayLaterSamePaxAddonUpgradeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Mail::fake();
        $this->actingAs(Admin::first() ?? Admin::factory()->create(), 'admin');
    }

    /** @test */
    public function same_pax_dropdown_upgrade_moves_the_link_by_the_difference_only_and_refreshes_the_open_charge(): void
    {
        $feePct  = 9.82;
        $builder = (new CreateABooking())
            ->withTourists(2)->withRate(55.00)->withPermitFee(15.00)->withoutTransaction()
            ->withTransactOverrides(['nat_fees_percentage' => $feePct])
            ->create();
        $bookingId = $builder->bookingId;
        $groupId   = (string) $bookingId;
        DB::table('tour_bookings_master')->where('id', $bookingId)->update(['group_id' => $groupId]);
        $packageId = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tour_package_id');

        $form = AddonForm::create(['tour_package_id' => $packageId, 'name' => 'CP Same Pax ' . uniqid(), 'status' => 'Active']);
        $fieldDef = AddonField::create([
            'form_id' => $form->id, 'label' => 'Boxed Trail Lunch', 'type' => 'dropdown', 'unit_type' => 'price_per_pax',
            'additional_fee' => false, 'price' => 0, 'status' => 'Active', 'visibility' => 'both', 'sort_order' => 0,
        ]);
        $turkey = AddonFieldOption::create(['field_id' => $fieldDef->id, 'label' => 'Classic Turkey Wrap', 'value' => 'turkey', 'additional_fee' => true, 'price' => 16.00, 'sort_order' => 0]);
        $gf     = AddonFieldOption::create(['field_id' => $fieldDef->id, 'label' => 'Gluten-Free Wrap', 'value' => 'gf_wrap', 'additional_fee' => true, 'price' => 18.00, 'sort_order' => 1]);

        $paxId = (int) DB::table('tour_bookings_transact')->where('tour_booking_id', $bookingId)->value('id');
        $row = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $fieldDef->id,
            'pax_id' => $paxId, 'pax_index' => 'cp-same-pax-0', 'person_ordinal' => 0,
            'field_label' => 'Boxed Trail Lunch', 'option_id' => $turkey->id, 'option_label' => 'Classic Turkey Wrap',
            'value' => 'turkey', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $row->id, 'payment_method' => 'pay later',
            'status' => 'open', 'operation' => 'charge', 'field_additional_fee' => false, 'option_additional_fee' => true,
            'field_price' => 0, 'field_fee' => 0, 'option_price' => 16.00, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 1, 'operation_by_user_type' => 'Admin',
        ]);

        $linkId = DB::table('tour_booking_payment_links')->insertGetId([
            'order_id' => $groupId, 'section' => null, 'amount' => 178.55, 'status' => 'Unpaid',
            'reason_of_payment' => 'Make Payment', 'email' => 'test@example.com', 'phone_number' => '5555551234',
            'token' => \Illuminate\Support\Str::random(64), 'override_status' => 'Yes',
            'expired_at' => now()->addDays(30), 'created_by' => 1, 'created_by_user_type' => 'Admin', 'created_at' => now(),
        ]);

        $request = new Request();
        $request->merge([
            'bookingID' => $bookingId, 'slot_time' => $builder->slotTimeId,
            'tourists' => [2], 'rate_group' => [$builder->rateId], 'permit_fee' => [0], 'total_tourist' => 2,
            'discount_percentage' => 0, 'discount_fixed_money' => 0, 'payment_method' => 'Pay Later',
            'infantorchilds' => 0, 'payment_amount' => 0, 'refund_amount' => 0, 'validation_check' => 'false',
            'addons_added' => [], 'addons_removed' => [],
            'addons_updated' => [[
                'db_id' => $row->id, 'field_id' => $fieldDef->id, 'option_id' => $gf->id,
                'option_label' => 'Gluten-Free Wrap', 'value' => 'gf_wrap', 'pax_index' => 'cp-same-pax-0', 'unit' => 1,
            ]],
        ]);

        try {
            app(TourBookingRepository::class)->changePeopleBooking($request);

            $this->assertEquals('gf_wrap', TourBookingAddonField::find($row->id)->value);

            $newLink = DB::table('tour_booking_payment_links')->where('order_id', $groupId)->where('status', 'Unpaid')->first();
            $this->assertNotNull($newLink, 'The lump-sum link must be regenerated');
            $expected = round(178.55 + 2.00 + roundout(2.00 * $feePct / 100, 2), 2);
            $this->assertEqualsWithDelta($expected, (float) $newLink->amount, 0.01,
                'Only the $2 difference (+ fee) may be added — not the add-on delta twice');

            $open = DB::table('tour_booking_addon_transactions')
                ->where('tour_booking_addon_id', $row->id)->where('operation', 'charge')->where('status', 'open')
                ->whereNull('deleted_at')->get(['option_price']);
            $this->assertCount(1, $open);
            $this->assertEqualsWithDelta(18.00, (float) $open->first()->option_price, 0.001,
                'The open charge must follow the new selection so a later payment settles $18');
        } finally {
            DB::table('tour_booking_payment_links')->where('order_id', $groupId)->delete();
            TourBookingAddonField::withTrashed()->where('tour_booking_id', $bookingId)->forceDelete();
            DB::table('tour_booking_addon_transactions')->where('tour_booking_id', $bookingId)->delete();
            DB::table('tour_booking_history')->where('tour_booking_id', $bookingId)->delete();
            DB::table('addon_field_options')->where('field_id', $fieldDef->id)->delete();
            DB::table('addon_fields')->where('id', $fieldDef->id)->delete();
            DB::table('addon_forms')->where('id', $form->id)->delete();
            $builder->cleanup();
        }
    }
}
