<?php

namespace Tests\Unit\Bookings;

use App\Models\Admin;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use App\Services\Payments\PaymentModeResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression for the production crash on tenant `kens` (2026-09-27):
 *
 *   ErrorException: Undefined variable $paymentTrans in
 *   TourBookingRepository::refundCustomCharge()
 *
 * $paymentTrans was only assigned inside the nested
 * `if ($request->refund_type != "dont_refund")` block, but read unconditionally
 * further down whenever payment_method == "Card":
 *
 *   $customChargeMode = ($request->payment_method == "Card") ? $paymentTrans->payment_mode : ...;
 *
 * so submitting "Don't Refund" for a Card-paid custom charge (which skips that
 * nested block entirely) hit an undefined variable. Introduced in 741e319da8.
 *
 * CMD: php artisan test --filter=RefundCustomChargeCardDontRefundTest
 */
class RefundCustomChargeCardDontRefundTest extends TestCase
{
    protected TourBookingRepository $repo;

    public function setUp(): void
    {
        parent::setUp();
        $this->repo = app(TourBookingRepository::class);
        Queue::fake();
    }

    protected function authAsAdmin(): Admin
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    public function test_dont_refund_on_a_card_paid_custom_charge_does_not_crash(): void
    {
        $this->authAsAdmin();

        $builder = (new CreateABooking())->withTourists(1)->withPaymentMethod('Card')->create();

        try {
            // The already-collected custom charge's own settlement transaction. Deliberately
            // PLATFORM_HELD (not the DESTINATION default) so the assertion below can prove
            // $customChargeMode came from this row rather than falling back to the default.
            $customChargeTransactionNumber = 'TEST-CARD-CC-' . uniqid();
            DB::table('transactions')->insert([
                'tour_booking_id' => $builder->bookingId,
                'amount' => 15.00,
                'fee_amount' => 0,
                'via' => 'Card',
                'payment_type' => 2,
                'status' => 'Completed',
                'charge_type' => 'custom_charge',
                'transaction_number' => $customChargeTransactionNumber,
                'payment_mode' => PaymentModeResolver::PLATFORM_HELD,
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
                'created_at' => now(),
            ]);

            $chargeId = DB::table('tour_booking_custom_charges')->insertGetId([
                'tour_booking_id' => $builder->bookingId,
                'parent_id' => null,
                'amount' => 15.00,
                'taxable' => 0,
                'fees' => 0,
                'transaction_id' => $customChargeTransactionNumber,
                'description' => 'Card custom charge, refunded via Don\'t Refund',
                'payment_method' => 'Card',
                'status' => 'Paid',
                'operation_by' => 1,
                'operation_by_user_type' => 'Admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $request = new Request();
            $request->merge([
                'custom_charge_ids' => json_encode([$chargeId]),
                'refund_type'       => 'dont_refund',
                'payment_method'    => 'Card',
            ]);

            $response = $this->repo->refundCustomCharge($request, $builder->bookingId);

            $this->assertEquals(
                200,
                $response->getStatusCode(),
                'refundCustomCharge() should succeed, not throw on the undefined $paymentTrans.'
            );

            $refundRow = DB::table('transactions')
                ->where('tour_booking_id', $builder->bookingId)
                ->where('charge_type', 'custom_charge')
                ->where('status', 'Refund')
                ->latest('id')
                ->first();

            $this->assertNotNull($refundRow, 'The "Don\'t Refund" ledger row was never written.');
            $this->assertEquals(
                PaymentModeResolver::PLATFORM_HELD,
                $refundRow->payment_mode,
                'payment_mode must come from the original Card transaction, not the DESTINATION default.'
            );

            $historyRow = DB::table('tour_booking_history')
                ->where('tour_booking_id', $builder->bookingId)
                ->where('charge_type', 'custom_charge')
                ->where('payment_method', "Don't Refund")
                ->first();
            $this->assertNotNull($historyRow, 'A history row for the "Don\'t Refund" decision must be written.');
        } finally {
            DB::table('tour_booking_custom_charges')->where('tour_booking_id', $builder->bookingId)->delete();
            $builder->cleanup();
        }
    }
}
