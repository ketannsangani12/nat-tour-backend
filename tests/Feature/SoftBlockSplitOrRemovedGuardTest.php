<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\SoftBlockController;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * The mass soft-block "update existing bookings" tool skips (rather than
 * fails on) any booking that isn't eligible for it -- either because it's
 * ever had a split or a pax removed, or because it's ever received any
 * payment (this tool is only for unpaid "Pay Later" bookings). Both are
 * plain existence checks (has this ever happened?), not calculations.
 * Skipped bookings need to be edited individually instead; critically, the
 * rest of the batch still goes through -- a single problem booking in a
 * large date-range submission must not block every other, perfectly valid,
 * seat change in the same request.
 */
class SoftBlockSplitOrRemovedGuardTest extends TestCase
{
    public function test_mass_update_skips_a_booking_that_has_been_split()
    {
        $this->runWithContext(function ($ctx) {
            [$original, $admin, $customerId, $packageId] = $ctx;

            DB::table('tour_booking_history')->insert([
                'tour_booking_id' => $original->bookingId,
                'tourists' => 7,
                'operation' => 'Split Booking Original',
                'details' => json_encode([
                    'booking_id' => $original->bookingId,
                    'extended_booking_id' => 999999,
                    'created_by_with_name' => 'Test: (Admin)',
                ]),
                'operation_at' => now(),
                'operation_by' => 1,
                'operation_by_user_type' => 'Admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $response = $this->callCreateSoftBlocks($original, $admin, $customerId, $packageId, newSeats: 5);
            $data = json_decode($response->getContent(), true);

            // Skipped, not rejected -- the batch still succeeds overall.
            $this->assertSame(200, $response->getStatusCode(), json_encode($data));
            $this->assertCount(1, $data['warnings']);
            $this->assertStringContainsString("#{$original->bookingId}", $data['warnings'][0]);
            $this->assertStringContainsString('split and/or removed', $data['warnings'][0]);

            // Nothing was written for the skipped booking.
            $this->assertSame(10, (int) DB::table('tour_bookings_master')->where('id', $original->bookingId)->value('tourists'));
        });
    }

    public function test_mass_update_skips_a_booking_that_had_pax_removed()
    {
        $this->runWithContext(function ($ctx) {
            [$original, $admin, $customerId, $packageId] = $ctx;

            DB::table('tour_booking_history')->insert([
                'tour_booking_id' => $original->bookingId,
                'tourists' => 8,
                'operation' => 'Refund',
                'details' => json_encode([
                    'amount' => 100,
                    'deleted' => 'Adult person 2',
                    'created_by_with_name' => 'Test: (Admin)',
                ]),
                'operation_at' => now(),
                'operation_by' => 1,
                'operation_by_user_type' => 'Admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $response = $this->callCreateSoftBlocks($original, $admin, $customerId, $packageId, newSeats: 5);
            $data = json_decode($response->getContent(), true);

            $this->assertSame(200, $response->getStatusCode(), json_encode($data));
            $this->assertCount(1, $data['warnings']);
            $this->assertStringContainsString("#{$original->bookingId}", $data['warnings'][0]);
            $this->assertSame(10, (int) DB::table('tour_bookings_master')->where('id', $original->bookingId)->value('tourists'));
        });
    }

    public function test_mass_update_skips_a_booking_that_has_received_a_payment()
    {
        $this->runWithContext(function ($ctx) {
            [$original, $admin, $customerId, $packageId] = $ctx;

            DB::table('transactions')->insert([
                'tour_booking_id' => $original->bookingId,
                'amount' => 670.00,
                'payment_type' => 0,
                'response_init' => '{}',
                'response_end' => '{}',
                'status' => 'Completed',
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
                'created_at' => now(),
            ]);

            $response = $this->callCreateSoftBlocks($original, $admin, $customerId, $packageId, newSeats: 5);
            $data = json_decode($response->getContent(), true);

            $this->assertSame(200, $response->getStatusCode(), json_encode($data));
            $this->assertCount(1, $data['warnings']);
            $this->assertStringContainsString("#{$original->bookingId}", $data['warnings'][0]);
            $this->assertStringContainsString('payment', $data['warnings'][0]);

            // Nothing was written for the skipped booking.
            $this->assertSame(10, (int) DB::table('tour_bookings_master')->where('id', $original->bookingId)->value('tourists'));
        });
    }

    public function test_mass_update_skips_a_booking_with_only_a_partial_payment()
    {
        $this->runWithContext(function ($ctx) {
            [$original, $admin, $customerId, $packageId] = $ctx;

            // Booking total is 10 * $67 = $670; this only covers part of it,
            // but the guard doesn't distinguish full vs. partial -- any
            // payment at all is enough to make it ineligible.
            DB::table('transactions')->insert([
                'tour_booking_id' => $original->bookingId,
                'amount' => 100.00,
                'payment_type' => 1,
                'response_init' => '{}',
                'response_end' => '{}',
                'status' => 'Completed',
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
                'created_at' => now(),
            ]);

            $response = $this->callCreateSoftBlocks($original, $admin, $customerId, $packageId, newSeats: 5);
            $data = json_decode($response->getContent(), true);

            $this->assertSame(200, $response->getStatusCode(), json_encode($data));
            $this->assertCount(1, $data['warnings']);
            $this->assertSame(10, (int) DB::table('tour_bookings_master')->where('id', $original->bookingId)->value('tourists'));
        });
    }

    public function test_mass_update_ignores_a_failed_or_refunded_transaction()
    {
        $this->runWithContext(function ($ctx) {
            [$original, $admin, $customerId, $packageId] = $ctx;

            // Neither a Failed attempt nor a Refund line item counts as "has
            // received a payment" -- only a Completed transaction does.
            DB::table('transactions')->insert([
                'tour_booking_id' => $original->bookingId,
                'amount' => 670.00,
                'payment_type' => 0,
                'response_init' => '{}',
                'response_end' => '{}',
                'status' => 'Failed',
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
                'created_at' => now(),
            ]);

            $response = $this->callCreateSoftBlocks($original, $admin, $customerId, $packageId, newSeats: 5);
            $data = json_decode($response->getContent(), true);

            $this->assertSame(200, $response->getStatusCode(), json_encode($data));
            $this->assertSame(5, (int) DB::table('tour_bookings_master')->where('id', $original->bookingId)->value('tourists'));
        });
    }

    /**
     * The actual motivating scenario: a batch with two bookings, only one of
     * which has been split/reduced. The skipped one must be left untouched
     * (with a warning), but the other, perfectly valid, seat change in the
     * very same request must still go through.
     */
    public function test_mass_update_still_processes_the_rest_of_the_batch_when_one_booking_is_skipped()
    {
        $this->runWithContext(function ($ctx) {
            [$originalA, $admin, $customerId, $packageId] = $ctx;

            DB::table('tour_booking_history')->insert([
                'tour_booking_id' => $originalA->bookingId,
                'tourists' => 7,
                'operation' => 'Split Booking Original',
                'details' => json_encode(['extended_booking_id' => 999999]),
                'operation_at' => now(),
                'operation_by' => 1,
                'operation_by_user_type' => 'Admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $rateId = DB::table('tour_package_rates')
                ->where('tour_package_id', $packageId)
                ->where('affiliate_id', $customerId)
                ->value('id');

            $originalB = (new CreateABooking())
                ->withTourists(10)->withRate(50.00)->withDate(now()->addDays(14)->toDateString())->withTimeSlot('13:00:00', '15:00:00')
                ->usePackage($packageId)->useRate($rateId)->useCustomer($customerId)
                ->withBookingOverrides(['is_reserved_for_affiliate' => 0])
                ->withoutTransaction() // real mass-soft-block bookings never get a `transactions` row at all
                ->create();

            try {
                $slotA = DB::table('tour_slot_times')->find($originalA->slotTimeId);
                $slotB = DB::table('tour_slot_times')->find($originalB->slotTimeId);
                $dateFormatted = date('F d, Y', strtotime($slotA->date));

                $request = Request::create('/admin/soft-block/create-soft-blocks', 'POST', [
                    'packageId' => $packageId,
                    'affiliateId' => $customerId,
                    'isReservedForAffiliate' => '0',
                    'adminEmail' => $admin->email,
                    'updateExistingBookings' => true,
                    'timeSlots' => [
                        [
                            'date_range' => "{$dateFormatted} / {$dateFormatted}",
                            'times' => [$slotA->time_from, $slotB->time_from],
                            'seats' => 5,
                            'seat_preference' => '0',
                            'booking_ids' => [
                                "{$slotA->time_from}_{$dateFormatted}" => $originalA->bookingId,
                                "{$slotB->time_from}_{$dateFormatted}" => $originalB->bookingId,
                            ],
                        ],
                    ],
                ]);

                $response = (new SoftBlockController())->createSoftBlocks($request);
                $data = json_decode($response->getContent(), true);

                $this->assertSame(200, $response->getStatusCode(), json_encode($data));
                $this->assertCount(1, $data['warnings']);
                $this->assertStringContainsString("#{$originalA->bookingId}", $data['warnings'][0]);

                // A: skipped, untouched (still its original create-time value).
                $this->assertSame(10, (int) DB::table('tour_bookings_master')->where('id', $originalA->bookingId)->value('tourists'));
                // B: not flagged, updated normally in the very same request.
                $this->assertSame(5, (int) DB::table('tour_bookings_master')->where('id', $originalB->bookingId)->value('tourists'));
            } finally {
                $originalB->cleanup();
            }
        });
    }

    public function test_mass_update_allows_a_booking_with_no_split_or_removal_history()
    {
        $this->runWithContext(function ($ctx) {
            [$original, $admin, $customerId, $packageId] = $ctx;

            $response = $this->callCreateSoftBlocks($original, $admin, $customerId, $packageId, newSeats: 5);
            $data = json_decode($response->getContent(), true);

            $this->assertSame(200, $response->getStatusCode(), json_encode($data));
            $this->assertSame(5, (int) DB::table('tour_bookings_master')->where('id', $original->bookingId)->value('tourists'));
        });
    }

    public function test_preview_soft_blocks_surfaces_has_split_or_removed()
    {
        $this->runWithContext(function ($ctx) {
            [$original, , $customerId, $packageId] = $ctx;

            $slotTime = DB::table('tour_slot_times')->find($original->slotTimeId);
            $time = $slotTime->time_from;
            $dateFormatted = date('F d, Y', strtotime($slotTime->date));

            $buildRequest = fn () => Request::create('/admin/soft-block/preview-soft-blocks', 'POST', [
                'packageId' => $packageId,
                'affiliateId' => $customerId,
                'isReservedForAffiliate' => '0',
                'timeSlots' => [[
                    'date_range' => "{$dateFormatted} / {$dateFormatted}",
                    'times' => [$time],
                    'seats' => 10,
                    'seat_preference' => '0',
                ]],
            ]);

            // Before any split/removal.
            $response = (new SoftBlockController())->previewSoftBlocks($buildRequest());
            $data = json_decode($response->getContent(), true);
            $this->assertFalse($data['availability'][$time][0]['hasSplitOrRemoved']);

            // After a split.
            DB::table('tour_booking_history')->insert([
                'tour_booking_id' => $original->bookingId,
                'tourists' => 7,
                'operation' => 'Split Booking Original',
                'details' => json_encode(['extended_booking_id' => 999999]),
                'operation_at' => now(),
                'operation_by' => 1,
                'operation_by_user_type' => 'Admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $response = (new SoftBlockController())->previewSoftBlocks($buildRequest());
            $data = json_decode($response->getContent(), true);
            $this->assertTrue($data['availability'][$time][0]['hasSplitOrRemoved']);
        });
    }

    public function test_preview_soft_blocks_surfaces_has_payment()
    {
        $this->runWithContext(function ($ctx) {
            [$original, , $customerId, $packageId] = $ctx;

            $slotTime = DB::table('tour_slot_times')->find($original->slotTimeId);
            $time = $slotTime->time_from;
            $dateFormatted = date('F d, Y', strtotime($slotTime->date));

            $buildRequest = fn () => Request::create('/admin/soft-block/preview-soft-blocks', 'POST', [
                'packageId' => $packageId,
                'affiliateId' => $customerId,
                'isReservedForAffiliate' => '0',
                'timeSlots' => [[
                    'date_range' => "{$dateFormatted} / {$dateFormatted}",
                    'times' => [$time],
                    'seats' => 10,
                    'seat_preference' => '0',
                ]],
            ]);

            // Before any payment.
            $response = (new SoftBlockController())->previewSoftBlocks($buildRequest());
            $data = json_decode($response->getContent(), true);
            $this->assertFalse($data['availability'][$time][0]['hasPayment']);

            // After a completed payment.
            DB::table('transactions')->insert([
                'tour_booking_id' => $original->bookingId,
                'amount' => 670.00,
                'payment_type' => 0,
                'response_init' => '{}',
                'response_end' => '{}',
                'status' => 'Completed',
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
                'created_at' => now(),
            ]);

            $response = (new SoftBlockController())->previewSoftBlocks($buildRequest());
            $data = json_decode($response->getContent(), true);
            $this->assertTrue($data['availability'][$time][0]['hasPayment']);
        });
    }

    /**
     * Build a package + affiliate customer + original booking (10 seats),
     * run $callback with the context, and guarantee cleanup afterwards.
     */
    private function runWithContext(callable $callback): void
    {
        $packageId = null;
        $rateId = null;
        $customerId = null;
        $admin = null;
        $original = null;

        try {
            $packageId = DB::table('tour_packages')->insertGetId([
                'tour_operator_id' => DB::table('tour_operators')->value('id') ?? 1,
                'name' => 'Soft Block Test Package ' . uniqid(),
                'duration' => 2,
                'status' => 'Active',
                'type' => 'Regular',
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $customerId = DB::table('customers')->insertGetId([
                'tour_operator_id' => DB::table('tour_operators')->value('id') ?? 1,
                'name' => 'Test Affiliate',
                'email' => 'test-affiliate-' . uniqid() . '@test.com',
                'phone_number' => '5550002222',
                'is_affiliate' => 'Yes',
                'status' => 'Active',
                'remember_token' => '',
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
                'created_at' => now(),
            ]);

            $rateId = DB::table('tour_package_rates')->insertGetId([
                'tour_package_id' => $packageId,
                'affiliate_id' => $customerId,
                'rate_for' => 'Adult',
                'age_from' => 18,
                'age_to' => 64,
                'rate' => 50.00,
                'primary_rate' => 50.00,
                'permit_fee' => 0.00,
                'description' => '',
                'scheduling_year' => (int) now()->format('Y'),
                'status' => 'Active',
                'orderby' => 1,
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $admin = Admin::factory()->create();

            $original = (new CreateABooking())
                ->withTourists(10)
                ->withRate(50.00)
                ->withDate(now()->addDays(14)->toDateString())
                ->withTimeSlot('09:00:00', '11:00:00')
                ->usePackage($packageId)
                ->useRate($rateId)
                ->useCustomer($customerId)
                ->withBookingOverrides(['is_reserved_for_affiliate' => 0])
                ->withoutTransaction() // real mass-soft-block bookings never get a `transactions` row at all
                ->create();

            $callback([$original, $admin, $customerId, $packageId]);
        } finally {
            $original?->cleanup();
            if ($admin) {
                DB::table('admins')->where('id', $admin->id)->delete();
            }
            if ($rateId) {
                DB::table('tour_package_rates')->where('id', $rateId)->delete();
            }
            if ($customerId) {
                DB::table('customers')->where('id', $customerId)->delete();
            }
            if ($packageId) {
                DB::table('tour_packages')->where('id', $packageId)->delete();
            }
        }
    }

    private function callCreateSoftBlocks(CreateABooking $original, Admin $admin, int $customerId, int $packageId, int $newSeats)
    {
        $slotTime = DB::table('tour_slot_times')->find($original->slotTimeId);
        $time = $slotTime->time_from;
        $dateFormatted = date('F d, Y', strtotime($slotTime->date));

        $request = Request::create('/admin/soft-block/create-soft-blocks', 'POST', [
            'packageId' => $packageId,
            'affiliateId' => $customerId,
            'isReservedForAffiliate' => '0',
            'adminEmail' => $admin->email,
            'updateExistingBookings' => true,
            'timeSlots' => [
                [
                    'date_range' => "{$dateFormatted} / {$dateFormatted}",
                    'times' => [$time],
                    'seats' => $newSeats,
                    'seat_preference' => '0',
                    'booking_ids' => ["{$time}_{$dateFormatted}" => $original->bookingId],
                ],
            ],
        ]);

        return (new SoftBlockController())->createSoftBlocks($request);
    }
}
