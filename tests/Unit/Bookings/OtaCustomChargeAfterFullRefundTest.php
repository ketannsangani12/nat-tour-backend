<?php

namespace Tests\Unit\Bookings;

use App\Models\Admin;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use App\Services\Booking\CustomChargeBookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression for PR #5874 (feature-ota-custom-charge):
 *
 *   An OTA booking (settled through the OTA, never through this app's own
 *   Cash/Card/Invoiced flow) is locked to whichever payment method its first
 *   custom charge used, for the life of the booking -- same invariant every
 *   other booking type already has (a booking has exactly one payment method).
 *   That lock does NOT release just because the original charge was later
 *   fully refunded: CustomChargeBookingService::activeOtaCustomChargeMethod()
 *   looks at whether ANY custom charge (parent_id IS NULL row) exists at all,
 *   not whether it's still "active" -- refunded or not, it already committed
 *   the booking to that method.
 *
 *   addCustomCharge() resolves the method with:
 *
 *     if ($isOtaBooking) {
 *         $lockedMethod = CustomChargeBookingService::activeOtaCustomChargeMethod($id);
 *         $chosenMethod = $lockedMethod ?: $request->payment_method;
 *         ...
 *     } elseif (!empty($transaction) && in_array($transaction->via, [...])) {
 *         ...
 *     }
 *
 *   -- checked BEFORE the generic $transaction->via branch on purpose: a
 *   custom charge's refund is logged as a separate status="Refund" row
 *   (TransactionService::storeCustomChargeRefundInTransaction()), never
 *   touching the original charge's status="Completed" row, so $transaction
 *   would otherwise still resolve to that stale, already-refunded method.
 *
 *   The locked method here is Cash, not Card: a locked Card booking routes
 *   addCustomCharge() into the real Stripe PaymentIntent flow (it needs
 *   actual card details this test doesn't supply), which is a separate
 *   concern from what this test is verifying -- that the lock, not the
 *   request, decides the method.
 *
 * CMD: php artisan test --filter=OtaCustomChargeAfterFullRefundTest
 */
class OtaCustomChargeAfterFullRefundTest extends TestCase
{
    protected TourBookingRepository $repo;

    public function setUp(): void
    {
        parent::setUp();
        $this->repo = app(TourBookingRepository::class);
    }

    protected function authAsAdmin(): Admin
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    // ---------------------------------------------------------------
    // Helper: temporarily flip a settings key for one test
    // ---------------------------------------------------------------
    private function withSetting(string $key, $value, callable $callback): void
    {
        $existing = DB::table('settings')->where('key', $key)->first();
        $original = $existing?->value;

        DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value]);
        try {
            $callback();
        } finally {
            if ($existing) {
                DB::table('settings')->where('key', $key)->update(['value' => $original]);
            } else {
                DB::table('settings')->where('key', $key)->delete();
            }
        }
    }

    public function test_a_new_charge_after_a_full_refund_stays_locked_to_the_original_method(): void
    {
        $this->authAsAdmin();

        // An OTA booking: settled via the OTA, not through this app's Cash/Card/
        // Invoiced flow, so it never gets one of those as its "payment method".
        // withoutTransaction()/withoutHistory() so the only transaction/history
        // rows present are the ones this test seeds below -- full control over
        // which "Completed" transaction addCustomCharge() will find as latest.
        $builder = (new CreateABooking())
            ->withoutTransaction()
            ->withoutHistory()
            ->withBookingOverrides(['ota_reference_booking' => 'HEAD-' . uniqid()])
            ->create();

        try {
            // A prior custom charge that was collected via Cash and has since been
            // fully refunded: the original charge's transaction row stays
            // status=Completed forever (only the refund gets its own status=Refund
            // row), and the custom charge ledger shows it as fully refunded via a
            // child row.
            $staleCashTransactionId = DB::table('transactions')->insertGetId([
                'tour_booking_id' => $builder->bookingId,
                'amount' => 20.00,
                'fee_amount' => 0,
                'via' => 'Cash',
                'payment_type' => 2,
                'status' => 'Completed',
                'charge_type' => 'custom_charge',
                'transaction_number' => 'TEST-CASH-' . uniqid(),
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
                'created_at' => now(),
            ]);

            $parentChargeId = DB::table('tour_booking_custom_charges')->insertGetId([
                'tour_booking_id' => $builder->bookingId,
                'parent_id' => null,
                'amount' => 20.00,
                'taxable' => 0,
                'fees' => 0,
                'transaction_id' => (string) $staleCashTransactionId,
                'description' => 'Prior custom charge, later fully refunded',
                'payment_method' => 'Cash',
                'status' => 'Paid',
                'operation_by' => 1,
                'operation_by_user_type' => 'Admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('tour_booking_custom_charges')->insert([
                'tour_booking_id' => $builder->bookingId,
                'parent_id' => $parentChargeId,
                'amount' => 20.00,
                'taxable' => 0,
                'fees' => 0,
                'transaction_id' => (string) $staleCashTransactionId,
                'description' => 'Full refund of the prior cash custom charge',
                'payment_method' => 'Cash',
                'status' => '100 % Refund',
                'operation_by' => 1,
                'operation_by_user_type' => 'Admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Sanity check on the setup itself: even after a full refund, the
            // booking stays locked to the method its first custom charge used --
            // otherwise this test isn't exercising the intended (permanent-lock)
            // policy.
            $this->assertSame(
                'Cash',
                CustomChargeBookingService::activeOtaCustomChargeMethod($builder->bookingId),
                'Setup invalid: a booking must stay locked to its first custom charge\'s method even after a full refund.'
            );

            // addCustomCharge() no-ops entirely -- OTA branch included -- unless
            // the CUSTOM_CHARGE setting is on. CicdSeeder seeds it as '0', so
            // without this the whole feature is skipped on a fresh CI database
            // even though a long-lived local dev DB may already have it enabled,
            // making the test pass locally by accident and fail on CI.
            $this->withSetting('CUSTOM_CHARGE', 1, function () use ($builder, $parentChargeId) {
                $request = new Request();
                $request->merge([
                    'custom_charge_amount' => 15.00,
                    'taxable' => 0,
                    'comment' => 'Second custom charge -- requested as Card, but the booking is locked to Cash',
                    'custom_charge_fees' => 0,
                    'custom_charge_total' => 15.00,
                    'payment_method' => 'Card',
                ]);

                $response = $this->repo->addCustomCharge($request, $builder->bookingId);

                $this->assertArrayNotHasKey(
                    'require3ds',
                    $response,
                    'Should not attempt a Stripe charge -- the lock must override the requested Card method back to Cash.'
                );

                $newCharge = DB::table('tour_booking_custom_charges')
                    ->where('tour_booking_id', $builder->bookingId)
                    ->where('id', '!=', $parentChargeId)
                    ->whereNull('parent_id')
                    ->orderBy('id', 'DESC')
                    ->first();

                $this->assertNotNull($newCharge, 'The new custom charge was never recorded.');
                $this->assertSame(
                    'Cash',
                    $newCharge->payment_method,
                    'The booking is locked to Cash from its first custom charge; a later request for Card must be overridden, not honored.'
                );

                $this->assertSame(
                    'Cash',
                    DB::table('transactions')
                        ->where('tour_booking_id', $builder->bookingId)
                        ->where('charge_type', 'custom_charge')
                        ->where('status', 'Completed')
                        ->orderBy('id', 'DESC')
                        ->value('via'),
                    'A Cash ledger transaction should have been recorded for the new charge, matching the locked method.'
                );
            });
        } finally {
            // If addCustomCharge() threw (e.g. the pre-fix bug routing this Card request
            // into the Card/Stripe branch anyway), a nested transaction opened deeper in that
            // call can be left un-popped even after its own catch block's DB::rollBack() -- one
            // rollBack() only closes the outermost level. Left alone, the deletes below would
            // run inside that still-open, never-committed transaction and silently vanish
            // when the connection eventually closes, orphaning the booking. Unwind fully
            // before cleaning up so this test never leaks data on failure.
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            DB::table('tour_booking_custom_charges')->where('tour_booking_id', $builder->bookingId)->delete();
            $builder->cleanup();
        }
    }
}
