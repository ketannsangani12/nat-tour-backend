<?php

namespace Tests\Unit\Bookings;

use App\Models\AddonField;
use App\Models\AddonFieldOption;
use App\Models\AddonForm;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingAddonTransaction;
use App\Repositories\TourBooking\TourBookingAddonRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression (real-world report, booking #41959): "Edit Passenger Add-ons" → Update Add-on
 * (Confirm Change) on a partly-paid add-on — Gluten-Free Wrap $18 = $16 paid by card + $2 still
 * pending on a payment link — changed to Vegetarian Wrap $16. The $2 credit was never collected
 * (refund_amount $0, credit_amount $2), and the history line came out as $0 (or not at all).
 * It must be recorded as a "Don't Refund" $2 history line, with no ledger refund row.
 */
class UpdateAddonPartlyPaidDowngradeDontRefundHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Mail::fake();
    }

    /** @test */
    public function unpaid_part_of_a_partly_paid_downgrade_is_a_dont_refund_history_line(): void
    {
        $booking   = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;
        $packageId = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tour_package_id');

        $form = AddonForm::create(['tour_package_id' => $packageId, 'name' => 'Partly Paid DR ' . uniqid(), 'status' => 'Active']);
        $def = AddonField::create([
            'form_id' => $form->id, 'label' => 'Boxed Trail Lunch', 'type' => 'dropdown', 'unit_type' => 'one_time',
            'additional_fee' => false, 'price' => 0, 'status' => 'Active', 'visibility' => 'both', 'sort_order' => 0,
        ]);
        $veg = AddonFieldOption::create(['field_id' => $def->id, 'label' => 'Vegetarian Wrap', 'value' => 'veg', 'additional_fee' => true, 'price' => 16.00, 'sort_order' => 0]);
        $gf  = AddonFieldOption::create(['field_id' => $def->id, 'label' => 'Gluten-Free Wrap', 'value' => 'gf_wrap', 'additional_fee' => true, 'price' => 18.00, 'sort_order' => 1]);
        $row = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $def->id, 'field_label' => 'Boxed Trail Lunch',
            'option_id' => $gf->id, 'option_label' => 'Gluten-Free Wrap', 'value' => 'gf_wrap', 'unit_type' => 'one_time', 'unit' => 1,
        ]);
        // $16 actually paid by card (the $2 upgrade difference is only pending on a link).
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $row->id, 'payment_method' => 'card',
            'status' => 'paid', 'operation' => 'charge', 'transaction_id' => 'pi_test_partly_paid',
            'field_additional_fee' => false, 'option_additional_fee' => true,
            'field_price' => 0, 'field_fee' => 0, 'option_price' => 16.00, 'option_fee' => 1.57,
            'operation_at' => now(), 'operation_by' => 1, 'operation_by_user_type' => 'Admin',
        ]);

        $request = new Request();
        $request->merge([
            'booking_id' => $bookingId, 'form_id' => $form->id, 'added' => [], 'deleted' => [],
            'updated' => [[
                'db_id' => $row->id, 'field_id' => $def->id, 'option_id' => $veg->id, 'option_label' => 'Vegetarian Wrap',
                'value' => 'veg', 'unit_type' => 'one_time', 'unit' => 1, 'rule_id' => null, 'applied_rule_snapshot' => null,
            ]],
            'financial' => [
                'action' => 'refund', 'net_amount' => 2.00, 'payment_method' => 'Card', 'refund_type' => 'dont_refund',
                'total_charge' => 0, 'total_refund' => 0, 'to_charge' => [],
                'to_refund' => [[
                    'db_id' => $row->id, 'field_id' => $def->id,
                    'label' => 'Boxed Trail Lunch: Gluten-Free Wrap → Vegetarian Wrap',
                    'refund_amount' => 0, 'credit_amount' => 2.00, 'new_price' => 16.00,
                    'field_price' => 0, 'option_price' => 16.00, 'reason' => 'downgraded',
                ]],
            ],
        ]);

        try {
            $result = app(TourBookingAddonRepository::class)->updateBookingAddons($request);
            $this->assertTrue($result['success'] ?? false, $result['error'] ?? 'updateBookingAddons failed');

            $history = DB::table('tour_booking_history')->where('tour_booking_id', $bookingId)
                ->where('operation', 'Refund')->where('charge_type', 'addons')->orderByDesc('id')->first();
            $this->assertNotNull($history, 'A "Don\'t Refund" history entry must be written for the unpaid $2');
            $details = json_decode($history->details, true);
            $this->assertEqualsWithDelta(2.00, (float) $details['addons'][0]['price'], 0.001, 'Must show the $2, not $0');
            $this->assertSame("Don't Refund", $details['addons'][0]['status']);

            $this->assertSame(0, DB::table('tour_booking_addon_transactions')
                ->where('tour_booking_addon_id', $row->id)->where('operation', 'refund')->count(),
                'No ledger refund row — the $2 was never collected');
        } finally {
            TourBookingAddonField::withTrashed()->where('tour_booking_id', $bookingId)->forceDelete();
            DB::table('tour_booking_addon_transactions')->where('tour_booking_id', $bookingId)->delete();
            DB::table('addon_field_options')->where('field_id', $def->id)->delete();
            DB::table('addon_fields')->where('id', $def->id)->delete();
            DB::table('addon_forms')->where('id', $form->id)->delete();
            $booking->cleanup();
        }
    }
}
