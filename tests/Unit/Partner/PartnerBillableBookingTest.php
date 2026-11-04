<?php

namespace Tests\Unit\Partner;

use App\Repositories\PartnerAPI\PartnerApiRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Partner-API bookings for billable (invoiced) partners must land as "Billable",
 * not "Paid". The OCTO confirm path (reused verbatim) writes the local (lc)
 * transaction with via=<resellerName> and status='Completed', which reads as paid.
 * PartnerApiRepository::markLocalBookingBillable() reclassifies that single
 * transaction + its Payment history row to 'Invoiced' so the booking reads as
 * Billable and is picked up by the affiliate invoice subsystem
 * (InvoiceController::mapBookingsWithInvoice, which requires transactions.via='Invoiced'
 * + status='Completed' + tbm.status='Booked' + customers.is_affiliate='Yes').
 *
 * These tests simulate the just-confirmed local state (reseller-labelled transaction +
 * Payment history + a CONFIRMED octo_bookings row) and drive the helper directly, so
 * they do not depend on the heavy ON_HOLD -> confirm fixture. Every row is tracked and
 * removed in tearDown().
 *
 * Run with:
 *   docker exec -it nat-app php artisan test tests/Unit/Partner/PartnerBillableBookingTest.php
 */
class PartnerBillableBookingTest extends TestCase
{
    private const TENANT = 'dixies';

    protected array $cleanupCustomerIds    = [];
    protected array $cleanupPackageIds     = [];
    protected array $cleanupDateIds        = [];
    protected array $cleanupSlotIds        = [];
    protected array $cleanupTourBookingIds = [];
    protected array $cleanupOctoUuids      = [];

    protected function tearDown(): void
    {
        if (!empty($this->cleanupTourBookingIds)) {
            DB::connection('tenant')->table('tour_booking_history')->whereIn('tour_booking_id', $this->cleanupTourBookingIds)->delete();
            DB::connection('tenant')->table('transactions')->whereIn('tour_booking_id', $this->cleanupTourBookingIds)->delete();
            DB::connection('tenant')->table('tour_bookings_master')->whereIn('id', $this->cleanupTourBookingIds)->delete();
        }
        if (!empty($this->cleanupOctoUuids)) {
            DB::connection('tenant')->table('octo_bookings')->whereIn('uuid', $this->cleanupOctoUuids)->delete();
        }
        if (!empty($this->cleanupSlotIds)) {
            DB::connection('tenant')->table('tour_slot_times')->whereIn('id', $this->cleanupSlotIds)->delete();
        }
        if (!empty($this->cleanupDateIds)) {
            DB::connection('tenant')->table('tour_slot_dates')->whereIn('id', $this->cleanupDateIds)->delete();
        }
        if (!empty($this->cleanupPackageIds)) {
            DB::connection('tenant')->table('tour_packages')->whereIn('id', $this->cleanupPackageIds)->delete();
        }
        if (!empty($this->cleanupCustomerIds)) {
            DB::connection('tenant')->table('customers')->whereIn('id', $this->cleanupCustomerIds)->delete();
        }

        parent::tearDown();
    }

    // ── Fixture helpers ─────────────────────────────────────────────────────────

    private function createAffiliate(): int
    {
        $operatorId = DB::connection('tenant')->table('tour_operators')->value('id');
        $this->assertNotNull($operatorId, 'Test requires a tour_operator in tenant_' . self::TENANT . '.');

        $id = DB::connection('tenant')->table('customers')->insertGetId([
            'tour_operator_id' => $operatorId,
            'name'             => 'Partner Billable ' . uniqid(),
            'email'            => 'billable-' . uniqid() . '@test.com',
            'phone_number'     => '0000000000',
            'is_affiliate'     => 'Yes',
            'is_billable'      => 1,
            'is_ota'           => 1,
            'ota_id'           => 'chd',
            'status'           => 'Active',
            'created_at'       => Carbon::now(),
            'updated_at'       => Carbon::now(),
        ]);
        $this->cleanupCustomerIds[] = $id;
        return $id;
    }

    private function createSlot(int $packageId): int
    {
        $date = Carbon::now()->addDays(30)->toDateString();

        $dateId = DB::connection('tenant')->table('tour_slot_dates')->insertGetId([
            'tour_package_id' => $packageId,
            'date'            => $date,
            'seats'           => 10,
            'status'          => 'Active',
            'deleted_by'      => 0,
            'created_at'      => Carbon::now(),
            'updated_at'      => Carbon::now(),
        ]);
        $this->cleanupDateIds[] = $dateId;

        $slotId = DB::connection('tenant')->table('tour_slot_times')->insertGetId([
            'tour_slot_id'    => $dateId,
            'tour_package_id' => $packageId,
            'date'            => $date,
            'time_from'       => '09:00:00',
            'time_to'         => '09:00:00',
            'time_slot_seats' => 10,
            'bookable_status' => 'Open',
            'deleted_by'      => 0,
            'created_at'      => Carbon::now(),
            'updated_at'      => Carbon::now(),
        ]);
        $this->cleanupSlotIds[] = $slotId;
        return $slotId;
    }

    private function createPackage(): int
    {
        $operatorId = DB::connection('tenant')->table('tour_operators')->value('id');
        $id = DB::connection('tenant')->table('tour_packages')->insertGetId([
            'tour_operator_id'     => $operatorId,
            'name'                 => 'Partner Billable Pkg ' . uniqid(),
            'status'               => 'Active',
            'type'                 => 'Regular',
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => Carbon::now(),
            'updated_at'           => Carbon::now(),
        ]);
        $this->cleanupPackageIds[] = $id;
        return $id;
    }

    /**
     * Build the state that OctoRepository::confirmBooking leaves behind for a partner
     * booking: a Booked master, a Completed transaction labelled with the reseller name,
     * a Payment history row, and a CONFIRMED octo_bookings row pointing at the booking.
     *
     * @return array{uuid:string, bookingId:int, transactionId:int}
     */
    private function confirmedPartnerBooking(int $affiliateId, string $resellerName): array
    {
        $packageId = $this->createPackage();
        $slotId    = $this->createSlot($packageId);

        $bookingId = DB::connection('tenant')->table('tour_bookings_master')->insertGetId([
            'ota_reference_booking' => 'CHD-' . uniqid(),
            'tour_package_id'       => $packageId,
            'tour_slot_id'          => $slotId,
            'customer_id'           => $affiliateId,
            'name'                  => 'Billable Guest',
            'email'                 => 'guest@test.com',
            'phone_number'          => '',
            'tourists'              => 2,
            'infants'               => 0,
            'comments'              => '',
            'note'                  => '',
            'mask_required'         => 'Yes',
            'total'                 => 100,
            'booking_total'         => 100,
            'service_commission'    => 0,
            'status'                => 'Booked',
            'request_from'          => $resellerName,
            'created_by'            => $affiliateId,
            'created_by_user_type'  => 'Affiliate',
            'created_at'            => Carbon::now(),
            'updated_at'            => Carbon::now(),
        ]);
        $this->cleanupTourBookingIds[] = $bookingId;

        $transactionId = DB::connection('tenant')->table('transactions')->insertGetId([
            'tour_booking_id'       => $bookingId,
            'amount'                => 100,
            'fee_amount'            => 0,
            'via'                   => $resellerName,
            'transaction_number'    => '',
            'payment_type'          => 0,
            'response_init'         => '',
            'response_end'          => '',
            'response_end_datetime' => Carbon::now(),
            'status'                => 'Completed',
            'visitor'               => '127.0.0.1',
            'request_from'          => strtoupper($resellerName),
            'created_by'            => $affiliateId,
            'created_by_user_type'  => 'Affiliate',
            'created_at'            => Carbon::now(),
        ]);

        DB::connection('tenant')->table('tour_booking_history')->insert([
            'tour_booking_id'        => $bookingId,
            'transaction_id'         => $transactionId,
            'tourists'               => 2,
            'operation'              => 'Payment',
            'ticket_cost'            => 100,
            'platform_fees'          => 0,
            'payment_type'           => $resellerName,
            'payment_method'         => 'Full Payment',
            'is_processed'           => 1,
            'details'                => json_encode(['amount' => 100, 'payment_type' => $resellerName]),
            'operation_at'           => Carbon::now(),
            'operation_by'           => $affiliateId,
            'operation_by_user_type' => 'Affiliate',
            'created_at'             => Carbon::now(),
            'updated_at'             => Carbon::now(),
        ]);

        $uuid = 'test-' . uniqid();
        DB::connection('tenant')->table('octo_bookings')->insert([
            'uuid'            => $uuid,
            'affiliate_id'    => $affiliateId,
            'tour_slot_id'    => $slotId,
            'tour_package_id' => $packageId,
            'tour_booking_id' => $bookingId,
            'status'          => 'CONFIRMED',
            'tourists'        => 2,
            'infants'         => 0,
            'unit_items'      => json_encode([]),
            'expire_at'       => Carbon::now()->addHour(),
            'created_at'      => Carbon::now(),
            'updated_at'      => Carbon::now(),
        ]);
        $this->cleanupOctoUuids[] = $uuid;

        return ['uuid' => $uuid, 'bookingId' => $bookingId, 'transactionId' => $transactionId];
    }

    private function markBillable(string $uuid): void
    {
        $repo   = app(PartnerApiRepository::class);
        $method = new ReflectionMethod($repo, 'markLocalBookingBillable');
        $method->setAccessible(true);
        $method->invoke($repo, $uuid);
    }

    /** The core invoice-harvest predicate (InvoiceController::mapBookingsWithInvoice:781-784). */
    private function isInvoiceEligible(int $bookingId): bool
    {
        return DB::connection('tenant')->table('tour_bookings_master AS tbm')
            ->join('customers as c', 'c.id', '=', 'tbm.customer_id')
            ->join('transactions as t', 't.tour_booking_id', '=', 'tbm.id')
            ->where('t.via', 'Invoiced')
            ->where('t.status', 'Completed')
            ->where('tbm.status', 'Booked')
            ->where('c.is_affiliate', 'Yes')
            ->where('tbm.id', $bookingId)
            ->exists();
    }

    // ── Tests ────────────────────────────────────────────────────────────────────

    public function test_reclassifies_partner_payment_as_invoiced_and_makes_it_invoice_eligible(): void
    {
        $affiliateId = $this->createAffiliate();
        $booking     = $this->confirmedPartnerBooking($affiliateId, 'CHD');

        // Precondition: as OCTO leaves it, the booking reads as paid (reseller-labelled)
        // and is NOT eligible for the affiliate invoice harvest.
        $this->assertFalse(
            $this->isInvoiceEligible($booking['bookingId']),
            'A reseller-labelled (paid) partner booking must NOT be invoice-eligible before reclassification.'
        );

        $this->markBillable($booking['uuid']);

        $transaction = DB::connection('tenant')->table('transactions')->where('id', $booking['transactionId'])->first();
        $this->assertSame('Invoiced', $transaction->via, 'Transaction via must be reclassified to Invoiced.');
        $this->assertSame('Completed', $transaction->status, 'Transaction must remain Completed (invoice harvest requires it).');

        $paymentType = DB::connection('tenant')->table('tour_booking_history')
            ->where('tour_booking_id', $booking['bookingId'])
            ->where('operation', 'Payment')
            ->value('payment_type');
        $this->assertSame('Invoiced', $paymentType, 'Payment history payment_type must be reclassified to Invoiced.');

        $this->assertTrue(
            $this->isInvoiceEligible($booking['bookingId']),
            'After reclassification the booking must be eligible for the affiliate invoice harvest (Billable).'
        );
    }

    public function test_reclassification_preserves_reseller_attribution(): void
    {
        $affiliateId = $this->createAffiliate();
        $booking     = $this->confirmedPartnerBooking($affiliateId, 'CHD');

        $master   = DB::connection('tenant')->table('tour_bookings_master')->where('id', $booking['bookingId'])->first();
        $refBefore = $master->ota_reference_booking;

        $this->markBillable($booking['uuid']);

        $masterAfter = DB::connection('tenant')->table('tour_bookings_master')->where('id', $booking['bookingId'])->first();
        $this->assertSame($refBefore, $masterAfter->ota_reference_booking, 'OTA reference (attribution) must be untouched.');
        $this->assertSame('CHD', $masterAfter->request_from, 'request_from (attribution) must remain the reseller.');
        $this->assertSame($affiliateId, (int) $masterAfter->created_by, 'created_by (attribution) must remain the affiliate.');

        // The transaction keeps its reseller request_from; only via/payment_type flip.
        $transaction = DB::connection('tenant')->table('transactions')->where('id', $booking['transactionId'])->first();
        $this->assertSame('CHD', $transaction->request_from, 'Transaction request_from (attribution) must remain the reseller.');
    }

    public function test_noop_when_uuid_has_no_local_booking(): void
    {
        // A helper call for an unknown uuid must not throw (defensive, logged path).
        $this->markBillable('nonexistent-' . uniqid());
        $this->addToAssertionCount(1);
    }
}
