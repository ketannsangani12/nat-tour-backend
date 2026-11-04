<?php

namespace Tests\Unit\Bookings;

use App\Models\AddonField;
use App\Models\AddonFieldOption;
use App\Models\AddonForm;
use App\Models\BookingTransact;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingAddonTransaction;
use App\Models\TourBookingPaymentLink;
use App\Repositories\TourBooking\TourBookingAddonRepository;
use App\Services\Booking\PaymentLinkService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression (real-world report, booking #41953): a Pay Later booking collected via its original
 * LUMP-SUM payment link (section NULL). An add-on was added ($16 Classic Turkey Wrap — folded into
 * the link), then upgraded on the same field to Gluten-Free Wrap ($18):
 *  - the "Payment Required" preview showed the upgrade as +$18 instead of +$2 — no per-field share
 *    existed for a lump-sum link (pendingAddonLinkLineShares() only knew itemized lines);
 *  - the ledger kept BOTH 'open' charge rows ($16 and $18) for the one field.
 */
class PayLaterLumpSumAddonUpgradeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Mail::fake();
    }

    private function makeScenario(): array
    {
        $booking   = (new CreateABooking())->withPaymentMethod('Pay Later')->withoutTransaction()->create();
        $bookingId = $booking->bookingId;
        $packageId = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tour_package_id');
        DB::table('tour_bookings_master')->where('id', $bookingId)->update(['group_id' => $bookingId]);
        BookingTransact::where('tour_booking_id', $bookingId)->where('deleted_by', 0)->update(['nat_fees_percentage' => 9.809]);

        $form = AddonForm::create(['tour_package_id' => $packageId, 'name' => 'Lump Sum Upgrade ' . uniqid(), 'status' => 'Active']);
        $fieldDef = AddonField::create([
            'form_id' => $form->id, 'label' => 'Boxed Trail Lunch', 'type' => 'dropdown', 'unit_type' => 'price_per_pax',
            'additional_fee' => false, 'price' => 0, 'status' => 'Active', 'visibility' => 'both', 'sort_order' => 0,
        ]);
        $turkey = AddonFieldOption::create(['field_id' => $fieldDef->id, 'label' => 'Classic Turkey Wrap', 'value' => 'turkey', 'additional_fee' => true, 'price' => 16.00, 'sort_order' => 0]);
        $gf     = AddonFieldOption::create(['field_id' => $fieldDef->id, 'label' => 'Gluten-Free Wrap', 'value' => 'gf_wrap', 'additional_fee' => true, 'price' => 18.00, 'sort_order' => 1]);

        $row = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $fieldDef->id,
            'field_label' => 'Boxed Trail Lunch', 'option_id' => $turkey->id, 'option_label' => 'Classic Turkey Wrap',
            'value' => 'turkey', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        // The add-on's never-collected Pay Later charge.
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $row->id, 'payment_method' => 'pay later',
            'status' => 'open', 'operation' => 'charge', 'field_additional_fee' => false, 'option_additional_fee' => true,
            'field_price' => 0, 'field_fee' => 0, 'option_price' => 16.00, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 1, 'operation_by_user_type' => 'Admin',
        ]);

        $link = TourBookingPaymentLink::create([
            'order_id' => $bookingId, 'section' => null, 'amount' => 178.55,
            'reason_of_payment' => 'Make Payment', 'email' => 'test@example.com', 'phone_number' => '5555551234',
            'token' => Str::random(64), 'status' => 'Unpaid', 'override_status' => 'Yes',
            'expired_at' => Carbon::now()->addDays(30), 'created_by' => 1, 'created_by_user_type' => 'Admin',
        ]);

        return compact('booking', 'bookingId', 'form', 'fieldDef', 'turkey', 'gf', 'row', 'link');
    }

    private function cleanup(array $s): void
    {
        DB::table('tour_booking_payment_links')->where('order_id', $s['bookingId'])->delete();
        TourBookingAddonField::withTrashed()->where('tour_booking_id', $s['bookingId'])->forceDelete();
        DB::table('tour_booking_addon_transactions')->where('tour_booking_id', $s['bookingId'])->delete();
        DB::table('tour_booking_history')->where('tour_booking_id', $s['bookingId'])->delete();
        DB::table('addon_field_options')->where('field_id', $s['fieldDef']->id)->delete();
        DB::table('addon_fields')->where('id', $s['fieldDef']->id)->delete();
        DB::table('addon_forms')->where('id', $s['form']->id)->delete();
        $s['booking']->cleanup();
    }

    /** @test */
    public function lump_sum_link_exposes_each_add_on_share_for_the_preview(): void
    {
        $s = $this->makeScenario();
        try {
            $shares = PaymentLinkService::pendingAddonLinkLineShares($s['bookingId']);
            $this->assertArrayHasKey($s['row']->id, $shares);
            $this->assertEqualsWithDelta(16.00, $shares[$s['row']->id]['net'], 0.001);
            $this->assertEqualsWithDelta(1.57, $shares[$s['row']->id]['fee'], 0.001, 'Same per-component fee rounding as fetchPayLaterAddonsWithPrice()');
            $this->assertTrue($shares[$s['row']->id]['lump_sum']);
        } finally {
            $this->cleanup($s);
        }
    }

    /** @test */
    public function no_lump_sum_share_once_the_booking_has_a_payment(): void
    {
        $s = $this->makeScenario();
        try {
            DB::table('transactions')->insert([
                'tour_booking_id' => $s['bookingId'], 'amount' => 10, 'fee_amount' => 0, 'via' => 'Card',
                'status' => 'Completed', 'created_by' => 1, 'created_by_user_type' => 'Admin', 'created_at' => now(),
            ]);
            $this->assertSame([], PaymentLinkService::pendingAddonLinkLineShares($s['bookingId']));
        } finally {
            DB::table('transactions')->where('tour_booking_id', $s['bookingId'])->delete();
            $this->cleanup($s);
        }
    }

    /** @test */
    public function pay_later_upgrade_replaces_the_old_open_charge_instead_of_stacking(): void
    {
        $s = $this->makeScenario();
        try {
            $request = new Request();
            $request->merge([
                'booking_id' => $s['bookingId'],
                'form_id'    => $s['form']->id,
                'added'      => [],
                'updated'    => [[
                    'db_id' => $s['row']->id, 'field_id' => $s['fieldDef']->id, 'option_id' => $s['gf']->id,
                    'option_label' => 'Gluten-Free Wrap', 'value' => 'gf_wrap', 'unit_type' => 'price_per_pax',
                    'unit' => 1, 'rule_id' => null, 'applied_rule_snapshot' => null,
                ]],
                'deleted'    => [],
                'financial'  => [
                    'action' => 'charge', 'payment_method' => 'Pay Later', 'net_amount' => 18.00,
                    'total_charge' => 18.00, 'total_refund' => 0, 'to_refund' => [],
                    'to_charge' => [[
                        'field_id' => $s['fieldDef']->id, 'label' => 'Boxed Trail Lunch: Classic Turkey Wrap → Gluten-Free Wrap',
                        'option_id' => $s['gf']->id, 'unit' => 1, 'new_price' => 18.00, 'old_db_id' => $s['row']->id,
                        'old_paid' => 0, 'charge_amount' => 18.00, 'field_price' => 0, 'option_price' => 18.00,
                        'field_additional_fee' => false, 'option_additional_fee' => true, 'reason' => 'upgraded',
                    ]],
                ],
            ]);

            $result = app(TourBookingAddonRepository::class)->updateBookingAddons($request);
            $this->assertTrue($result['success'] ?? false, $result['error'] ?? 'updateBookingAddons failed');

            $open = DB::table('tour_booking_addon_transactions')
                ->where('tour_booking_addon_id', $s['row']->id)->where('operation', 'charge')->where('status', 'open')
                ->get(['option_price']);
            $this->assertCount(1, $open, 'Only the upgraded selection may remain as an open charge');
            $this->assertEqualsWithDelta(18.00, (float) $open->first()->option_price, 0.001);
        } finally {
            $this->cleanup($s);
        }
    }
}
