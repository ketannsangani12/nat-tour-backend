<?php

namespace Tests\Fixture;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Test helper for creating bookings with all required related records.
 *
 * Inserts directly via DB::table() to bypass model events, Stripe calls,
 * and other side effects. Every method returns the inserted ID so callers
 * can chain or override as needed.
 *
 * Usage:
 *   $builder = (new CreateABooking())
 *       ->withTourists(3)
 *       ->withRate(75.00)
 *       ->withPaymentMethod('Card')
 *       ->create();
 *
 *   // Access created IDs for assertions:
 *   $builder->bookingId;
 *   $builder->customerId;
 *   $builder->transactionId;
 *
 *   // Cleanup deletes ALL records by booking_id (including any
 *   // created by repo methods like cancel/refund/restore), plus
 *   // slot times, dates, rates, and summaries this builder created.
 *   $builder->cleanup();
 */
class CreateABooking
{
    // IDs of records created during build -- public for test assertions
    public ?int $customerId = null;
    public ?int $bookingId = null;
    public ?int $transactId = null;
    public ?int $transactionId = null;
    public ?int $historyId = null;
    public ?int $slotTimeId = null;
    public ?int $slotDateId = null;
    public ?int $packageId = null;
    public ?int $rateId = null;

    // Fluent overrides
    protected int $tourists = 1;
    protected int $infants = 0;
    protected float $rate = 50.00;
    protected float $permitFee = 0.00;
    protected float $cashRate = 0.00;
    protected float $cashTax  = 0.00;
    protected bool  $isCashRate = false;
    protected string $status = 'Booked';
    protected string $paymentMethod = 'Cash';
    protected string $createdByUserType = 'Admin';
    protected ?string $customerName = null;
    protected ?string $customerEmail = null;
    protected ?string $customerPhone = null;
    protected ?string $bookingDate = null;
    protected ?string $timeFrom = null;
    protected ?string $timeTo = null;
    protected ?int $seats = null;
    protected ?int $usePackageId = null;
    protected ?int $useRateId = null;
    protected ?int $useSlotTimeId = null;
    protected ?int $useCustomerId = null;
    protected array $bookingOverrides = [];
    protected array $transactOverrides = [];
    protected bool $withTransaction = true;
    protected bool $withHistory = true;
    protected bool $forceNewPackage = false;
    protected bool $createdPackage = false;
    protected string $rateFor = 'Adult';
    protected int $ageFrom = 18;
    protected int $ageTo = 64;

    // ---------------------------------------------------------------
    // Fluent setters
    // ---------------------------------------------------------------

    public function withTourists(int $count): static
    {
        $this->tourists = $count;
        return $this;
    }

    public function withInfants(int $count): static
    {
        $this->infants = $count;
        return $this;
    }

    public function withRate(float $rate): static
    {
        $this->rate = $rate;
        return $this;
    }

    public function withPermitFee(float $fee): static
    {
        $this->permitFee = $fee;
        return $this;
    }

    public function withStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    // Add fluent setter
    public function withCashRate(float $cashRate, float $cashTax = 0.00): static
    {
        $this->cashRate   = $cashRate;
        $this->cashTax    = $cashTax;
        $this->isCashRate = true;
        return $this;
    }

    public function withPaymentMethod(string $method): static
    {
        $this->paymentMethod = $method;
        return $this;
    }

    public function withCustomer(?string $name = null, ?string $email = null, ?string $phone = null): static
    {
        $this->customerName = $name;
        $this->customerEmail = $email;
        $this->customerPhone = $phone;
        return $this;
    }

    public function withDate(string $date): static
    {
        $this->bookingDate = $date;
        return $this;
    }

    public function withTimeSlot(string $from, string $to): static
    {
        $this->timeFrom = $from;
        $this->timeTo = $to;
        return $this;
    }

    public function withSeats(int $seats): static
    {
        $this->seats = $seats;
        return $this;
    }

    public function withRateFor(string $rateFor, int $ageFrom, int $ageTo): static
    {
        $this->rateFor = $rateFor;
        $this->ageFrom = $ageFrom;
        $this->ageTo = $ageTo;
        return $this;
    }

    public function withCreatedByUserType(string $type): static
    {
        $this->createdByUserType = $type;
        return $this;
    }

    /**
     * Reuse an existing package instead of creating a new one.
     */
    public function usePackage(int $id): static
    {
        $this->usePackageId = $id;
        return $this;
    }

    /**
     * Reuse an existing rate instead of creating a new one.
     */
    public function useRate(int $id): static
    {
        $this->useRateId = $id;
        return $this;
    }

    /**
     * Reuse an existing slot time instead of creating a new one.
     */
    public function useSlotTime(int $id): static
    {
        $this->useSlotTimeId = $id;
        return $this;
    }

    /**
     * Reuse an existing customer instead of creating a new one.
     */
    public function useCustomer(int $id): static
    {
        $this->useCustomerId = $id;
        return $this;
    }

    /**
     * Merge arbitrary columns into the tour_bookings_master insert.
     */
    public function withBookingOverrides(array $overrides): static
    {
        $this->bookingOverrides = $overrides;
        return $this;
    }

    /**
     * Merge arbitrary columns into the tour_bookings_transact insert.
     */
    public function withTransactOverrides(array $overrides): static
    {
        $this->transactOverrides = $overrides;
        return $this;
    }

    public function withoutTransaction(): static
    {
        $this->withTransaction = false;
        return $this;
    }

    public function withoutHistory(): static
    {
        $this->withHistory = false;
        return $this;
    }

    /**
     * Force creation of a brand-new package instead of reusing an existing one.
     */
    public function withNewPackage(): static
    {
        $this->forceNewPackage = true;
        return $this;
    }

    // ---------------------------------------------------------------
    // Build
    // ---------------------------------------------------------------

    /**
     * Insert all records and return $this for chaining / inspection.
     */
    public function create(): static
    {
        $now = Carbon::now();
        $date = $this->bookingDate ?? $now->toDateString();
        $timeFrom = $this->timeFrom ?? '09:00:00';
        $timeTo = $this->timeTo ?? '11:00:00';
        $seats = $this->seats ?? 20;
        $name = $this->customerName ?? 'Test Customer';
        $email = $this->customerEmail ?? 'test-' . uniqid() . '@test.com';
        $phone = $this->customerPhone ?? '5550001111';
        $total = round($this->tourists * $this->rate + $this->tourists * $this->permitFee, 2);

        // -- Package (reuse or create) --
        if ($this->usePackageId) {
            $this->packageId = $this->usePackageId;
        } else {
            $this->packageId = $this->findOrCreatePackage($now);
        }

        // -- Rate (reuse or create) --
        if ($this->useRateId) {
            $this->rateId = $this->useRateId;
        } else {
            $this->rateId = $this->createRate($now);
        }

        // -- Slot date + time (reuse or create) --
        if ($this->useSlotTimeId) {
            $this->slotTimeId = $this->useSlotTimeId;
            $slotTime = DB::table('tour_slot_times')->find($this->slotTimeId);
            $this->slotDateId = $slotTime->tour_slot_id ?? null;
        } else {
            $this->slotDateId = $this->createSlotDate($date, $seats, $now);
            $this->slotTimeId = $this->createSlotTime($date, $timeFrom, $timeTo, $seats, $now);
        }

        // -- Customer (reuse or create) --
        if ($this->useCustomerId) {
            $this->customerId = $this->useCustomerId;
        } else {
            $this->customerId = $this->createCustomer($name, $email, $phone, $now);
        }

        // -- Booking --
        $this->bookingId = $this->createBooking($name, $email, $phone, $total, $now);

        // -- Transact line item --
        $this->transactId = $this->createTransact($total, $now);

        // -- Transaction --
        if ($this->withTransaction) {
            $this->transactionId = $this->createTransaction($total, $now);
        }

        // -- History --
        if ($this->withHistory) {
            $this->historyId = $this->createHistory($total, $now);
        }

        return $this;
    }

    // ---------------------------------------------------------------
    // Individual record creators
    // ---------------------------------------------------------------

    protected function findOrCreatePackage(Carbon $now): int
    {
        // Reuse first active package if one exists (unless forced to create new)
        if (!$this->forceNewPackage) {
            $existing = DB::table('tour_packages')->where('status', 'Active')->first();
            if ($existing) {
                return $existing->id;
            }
        }

        $this->createdPackage = true;
        return DB::table('tour_packages')->insertGetId([
            'tour_operator_id' => DB::table('tour_operators')->value('id') ?? 1,
            'name' => 'Test Tour Package ' . uniqid(),
            'duration' => 2,
            'status' => 'Active',
            'type' => 'Regular',
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected function createRate(Carbon $now): int
    {
        $date = $this->bookingDate ?? $now->toDateString();

        return DB::table('tour_package_rates')->insertGetId([
            'tour_package_id' => $this->packageId,
            'rate_for' => $this->rateFor,
            'age_from' => $this->ageFrom,
            'age_to' => $this->ageTo,
            'rate' => $this->rate,
            'primary_rate' => $this->rate,
            'permit_fee' => $this->permitFee,
            'cash_rate'    => $this->cashRate,
            'cash_tax'     => $this->cashTax,
            'is_cash_rate' => $this->isCashRate ? 1 : 0,
            'scheduling_year' => date('Y', strtotime($date)),
            'status' => 'Active',
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected function createSlotDate(string $date, int $seats, Carbon $now): int
    {
        return DB::table('tour_slot_dates')->insertGetId([
            'tour_package_id' => $this->packageId,
            'date' => $date,
            'seats' => $seats,
            'status' => 'Active',
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected function createSlotTime(string $date, string $from, string $to, int $seats, Carbon $now): int
    {
        return DB::table('tour_slot_times')->insertGetId([
            'tour_slot_id' => $this->slotDateId,
            'tour_package_id' => $this->packageId,
            'date' => $date,
            'time_from' => $from,
            'time_to' => $to,
            'time_slot_seats' => $seats,
            'bookable_status' => 'Open',
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected function createCustomer(string $name, string $email, string $phone, Carbon $now): int
    {
        return DB::table('customers')->insertGetId([
            'tour_operator_id' => DB::table('tour_operators')->value('id') ?? 1,
            'name' => $name,
            'email' => $email,
            'phone_number' => $phone,
            'is_affiliate' => 'No',
            'status' => 'Active',
            'remember_token' => '',
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => $now,
        ]);
    }

    protected function createBooking(string $name, string $email, string $phone, float $total, Carbon $now): int
    {
        $data = array_merge([
            'tour_package_id' => $this->packageId,
            'tour_slot_id' => $this->slotTimeId,
            'customer_id' => $this->customerId,
            'name' => $name,
            'email' => $email,
            'phone_number' => $phone,
            'tourists' => $this->tourists,
            'infants' => $this->infants,
            'comments' => '',
            'note' => '',
            'liability_waiver_signing' => '{}',
            'total' => $total,
            'booking_total' => $total,
            'service_commission' => 0,
            'discount' => 0,
            'discount2_value' => 0,
            'discount2_percentage' => 0,
            'status' => $this->status,
            'created_by' => 1,
            'created_by_user_type' => $this->createdByUserType,
            'created_at' => $now,
            'updated_at' => $now,
        ], $this->bookingOverrides);

        return DB::table('tour_bookings_master')->insertGetId($data);
    }

    protected function createTransact(float $total, Carbon $now): int
    {
        $checkInStatus = json_encode(
            array_fill(0, $this->tourists, ['status' => 'N', 'dateTime' => null])
        );

        $data = array_merge([
            'tour_booking_id' => $this->bookingId,
            'tour_package_rate_id' => $this->rateId,
            'tourists' => $this->tourists,
            'rate' => $this->rate,
            'permit_fee' => $this->permitFee * $this->tourists,
            'processing_charge' => 0,
            'processing_charge_percentage' => 0,
            'additional_tax_percentage' => 0,
            'nat_fees_percentage' => 0,
            'stripe_fees_percentage' => 0,
            'additional_charge' => 0,
            'state_tax' => 0,
            'total' => $total,
            'check_in_status' => $checkInStatus,
            'created_by' => 1,
            'created_by_user_type' => $this->createdByUserType,
            'created_at' => $now,
        ], $this->transactOverrides);

        return DB::table('tour_bookings_transact')->insertGetId($data);
    }

    protected function createTransaction(float $total, Carbon $now): int
    {
        return DB::table('transactions')->insertGetId([
            'tour_booking_id' => $this->bookingId,
            'amount' => $total,
            'transaction_number' => 'TEST-' . uniqid(),
            'via' => $this->paymentMethod,
            'payment_type' => $this->paymentMethod === 'Cash' ? 1 : 2,
            'response_init' => '{}',
            'response_end' => '{}',
            'response_end_datetime' => $now,
            'status' => 'Completed',
            'created_by' => 1,
            'created_by_user_type' => $this->createdByUserType,
            'created_at' => $now,
        ]);
    }

    protected function createHistory(float $total, Carbon $now): int
    {
        // ticket_cost is not in TourBookingHistory $fillable, so we use DB::table
        return DB::table('tour_booking_history')->insertGetId([
            'tour_booking_id' => $this->bookingId,
            'transaction_id' => $this->transactionId,
            'tourists' => $this->tourists,
            'operation' => 'Payment',
            'ticket_cost' => $total,
            'discount' => 0,
            'permit_fees' => $this->permitFee * $this->tourists,
            'processing_fees' => 0,
            'payment_type' => $this->paymentMethod,
            'payment_method' => $this->paymentMethod,
            'details' => json_encode([
                'payment_type' => $this->paymentMethod,
                'payment_method' => 'Full Payment',
                'created_by_with_name' => 'Test: (Admin)',
                'amount' => $total,
            ]),
            'operation_at' => $now,
            'operation_by' => 1,
            'operation_by_user_type' => $this->createdByUserType,
            'created_at' => $now,
        ]);
    }

    // ---------------------------------------------------------------
    // Cleanup
    // ---------------------------------------------------------------

    /**
     * Delete all records created by this builder (in FK-safe order).
     *
     * Deletes by booking_id so that records created by repository methods
     * (cancel history, refund transactions, restore history, etc.) are
     * also removed -- not just the rows this builder inserted directly.
     *
     * Also removes slot times, slot dates, rates, and slot summaries
     * that this builder created (skips reused/seeded records).
     */
    public function cleanup(): void
    {
        // Disable FK checks so cleanup order doesn't matter when repo
        // methods have created extra records referencing our test data.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            if ($this->bookingId) {
                DB::table('tour_booking_package_swipe_history')->where('tour_booking_id', $this->bookingId)->delete();
                DB::table('tour_booking_history')->where('tour_booking_id', $this->bookingId)->delete();
                DB::table('transactions')->where('tour_booking_id', $this->bookingId)->delete();
                DB::table('tour_bookings_transact')->where('tour_booking_id', $this->bookingId)->delete();
                DB::table('tour_bookings_master')->where('id', $this->bookingId)->delete();
            }
            if ($this->customerId) {
                DB::table('invoices')->where('customer_id', $this->customerId)->delete();
                DB::table('customers')->where('id', $this->customerId)->delete();
            }

            // Slot summary (table may not exist in all environments)
            if ($this->slotTimeId && !$this->useSlotTimeId) {
                try {
                    DB::table('tour_slot_summary')->where('tour_slot_id', $this->slotTimeId)->delete();
                } catch (\Exception $e) {
                    // Table may not exist; safe to ignore
                }
            }

            if ($this->slotTimeId && !$this->useSlotTimeId) {
                DB::table('tour_slot_times')->where('id', $this->slotTimeId)->delete();
            }
            if ($this->slotDateId && !$this->useSlotTimeId) {
                DB::table('tour_slot_dates')->where('id', $this->slotDateId)->delete();
            }
            if ($this->rateId && !$this->useRateId) {
                DB::table('tour_package_rates')->where('id', $this->rateId)->delete();
            }
            if ($this->packageId && $this->createdPackage) {
                DB::table('tour_packages')->where('id', $this->packageId)->delete();
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
