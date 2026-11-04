<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\TourBooking;
use App\Models\TourPackage;
use App\Models\TourPackageRate;
use App\Utils\BokunUtils;
use App\Services\TimeSlot\TimeSlotAvailabilityService;
use App\Services\TimeSlot\TimeSlotService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Self-contained cross-tenant engine for Partner API cross-tenant packages.
 *
 * A cross-tenant package lives in the partner tenant (e.g. "lc") and has one or
 * more partner_cross_tenant_origins rows pointing at origin packages that live in
 * OTHER tenants, each with its own time_from. ONE origin is a single-package
 * passthrough resell; TWO OR MORE is a combo.
 * Either way the mechanism is identical — this service is the ONLY place that
 * reaches into those origin tenants:
 *
 *   - aggregateVacancies() : intersect availability across every origin
 *   - confirmOrigins()     : at confirmation, create a standard booking in each
 *                            origin tenant (subtracting that origin's seats)
 *   - cancelOrigins()      : reverse those origin bookings, restoring seats
 *
 * Everything here is guarded so a package with no origin rows is left untouched
 * (callers only invoke it for cross-tenant packages, and each entry point returns
 * an empty/no-op result for a plain package anyway).
 *
 * IMPORTANT: none of these methods may run inside an open transaction on the
 * partner tenant's connection. tenancy()->initialize() reconfigures and
 * reconnects the default DB connection, which would discard any open
 * transaction. Callers therefore fan out AFTER their local commit and rely on
 * the compensation logic below to unwind on partial failure.
 */
class PartnerBookings
{
    /**
     * Availability intersection across the origins of one departure, keyed by DATE.
     *
     * Each origin is looked up at ITS OWN configured time_from (origins in a
     * combo run at different times), so the intersection is by date, not by time.
     * When $localTime is given, only the origins for that local (lc) departure
     * time are considered (multi-departure combos); when null, all origins are
     * used (single-departure — the original behaviour).
     *
     * Returns a map keyed by "Y-m-d" whose value is the minimum vacancy across
     * every origin on that date. A date is present ONLY when every origin has a
     * bookable slot at its time_from on that date — so a caller can treat "date
     * absent" as "not bookable that date".
     *
     * @return array<string,int>
     */
    public static function aggregateVacancies(TourPackage $package, string $startDate, string $endDate, ?string $localTime = null): array
    {
        $origins = $package->crossTenantOrigins;
        if ($localTime !== null) {
            $lt = substr($localTime, 0, 5);
            $origins = $origins->filter(
                fn ($o) => $o->local_time_from !== null && substr((string) $o->local_time_from, 0, 5) === $lt
            );
        }
        if ($origins->isEmpty()) {
            return [];
        }

        $perOrigin = []; // list of maps: [ "date" => vacancy ]

        foreach ($origins as $origin) {
            $tenantId = $origin->origin_tenant;
            $pkgId    = (int) $origin->origin_package_id;
            $timeFrom = $origin->time_from;
            if (!$tenantId || !$pkgId || !$timeFrom) {
                // Malformed origin config => nothing is bookable.
                return [];
            }

            $map = self::runInTenant($tenantId, function () use ($pkgId, $timeFrom, $startDate, $endDate) {
                $overbook = (int) (TourPackage::whereKey($pkgId)->value('overbook_seats') ?? 0);

                $slots = DB::table('tour_slot_times')
                    ->where('tour_package_id', $pkgId)
                    ->whereBetween('date', [$startDate, $endDate])
                    ->where('time_from', $timeFrom)
                    ->where('bookable_status', 'Open')
                    ->whereNull('deleted_at')
                    ->get(['id', 'date', 'time_slot_seats']);

                $out = [];
                foreach ($slots as $slot) {
                    // Key by date only: each origin is matched at its own time_from.
                    $out[(string) $slot->date] = self::originVacancy($slot, $overbook);
                }
                return $out;
            });

            // An origin that is entirely unavailable makes the whole package unavailable.
            if (empty($map)) {
                return [];
            }

            $perOrigin[] = $map;
        }

        // Intersect: keep only dates present in EVERY origin; value = min.
        $intersection = array_shift($perOrigin);
        foreach ($perOrigin as $map) {
            foreach ($intersection as $date => $vac) {
                if (!array_key_exists($date, $map)) {
                    unset($intersection[$date]);
                    continue;
                }
                $intersection[$date] = min($vac, $map[$date]);
            }
        }

        return $intersection;
    }

    /**
     * Sorted distinct local (lc) departure times (HH:MM) configured on the
     * package. Empty means the package is single-departure — its origins apply to
     * every lc slot (the original behaviour). Non-empty means each time is a
     * separate combo departure that the booked lc slot time selects.
     *
     * @return array<int,string>
     */
    public static function departureTimes(TourPackage $package): array
    {
        return $package->crossTenantOrigins
            ->pluck('local_time_from')
            ->filter(fn ($t) => $t !== null && $t !== '')
            ->map(fn ($t) => substr((string) $t, 0, 5))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Fan a confirmed cross-tenant booking out to every origin tenant.
     *
     * Saga with compensation: each origin write is its own single-DB transaction.
     * If any origin fails, every origin already booked in this call is reversed
     * and the original exception is rethrown so the caller can undo the partner
     * booking too. No half-booked cross-tenant bookings are left behind.
     *
     * @return array<int,array<string,mixed>> origin_bookings linkage rows
     */
    public static function confirmOrigins(
        string $reference,
        TourPackage $package,
        object $lcSlot,
        int $tourists,
        int $infants,
        array $contact
    ): array {
        $origins = $package->crossTenantOrigins;
        if ($origins->isEmpty()) {
            return [];
        }

        // Multi-departure: book only the origins for the departure matching the
        // booked lc slot time. Single-departure (no local_time_from) books all.
        if (!empty(self::departureTimes($package))) {
            $bookedTime = substr((string) ($lcSlot->time_from ?? ''), 0, 5);
            $origins = $origins->filter(
                fn ($o) => $o->local_time_from !== null && substr((string) $o->local_time_from, 0, 5) === $bookedTime
            );
            if ($origins->isEmpty()) {
                throw new \RuntimeException(
                    "Cross-tenant package {$package->id} has no origins configured for the booked departure {$bookedTime}."
                );
            }
        }

        // Each origin books on the same date as the local booking, at its OWN
        // configured time_from (not the local slot's time).
        $date    = $lcSlot->date;
        $created = [];

        foreach ($origins as $origin) {
            $tenantId   = $origin->origin_tenant;
            $pkgId      = (int) $origin->origin_package_id;
            $timeFrom   = $origin->time_from;
            $customerId = $origin->origin_customer_id !== null ? (int) $origin->origin_customer_id : null;

            if (!$tenantId || !$pkgId || !$timeFrom) {
                self::compensate($created);
                throw new \RuntimeException("Cross-tenant package {$package->id} has a malformed origin entry.");
            }

            try {
                $ref = self::runInTenant($tenantId, function () use ($tenantId, $pkgId, $customerId, $date, $timeFrom, $tourists, $infants, $contact, $reference) {
                    $slot = self::findOriginSlot($pkgId, $date, $timeFrom);
                    if (!$slot) {
                        throw new \RuntimeException("Origin {$tenantId}/{$pkgId} has no bookable slot for {$date} {$timeFrom}.");
                    }

                    return DB::transaction(function () use ($tenantId, $pkgId, $customerId, $slot, $tourists, $infants, $contact, $reference) {
                        TimeSlotService::lockSlotForUpdate($slot->id);

                        $overbook = (int) (TourPackage::whereKey($pkgId)->value('overbook_seats') ?? 0);
                        $vacancy  = self::originVacancy($slot, $overbook);
                        if ($vacancy < $tourists) {
                            throw new \RuntimeException("Origin {$tenantId}/{$pkgId} has insufficient seats ({$vacancy} < {$tourists}).");
                        }

                        $tourBookingId = self::createOriginBooking($pkgId, $slot, $customerId, $tourists, $infants, $contact, $reference);

                        return [
                            'tenant'          => $tenantId,
                            'package_id'      => $pkgId,
                            'tour_slot_id'    => (int) $slot->id,
                            'tour_booking_id' => $tourBookingId,
                            'tourists'        => $tourists,
                            'infants'         => $infants,
                            'status'          => 'Booked',
                        ];
                    });
                });

                $created[] = $ref;
            } catch (\Throwable $e) {
                Log::error('PartnerBookings: origin confirmation failed, compensating', [
                    'cross_tenant_package_id' => $package->id,
                    'reference'        => $reference,
                    'failed_origin'    => $tenantId . '/' . $pkgId,
                    'already_created'  => $created,
                    'error'            => $e->getMessage(),
                ]);
                self::compensate($created);
                throw $e;
            }
        }

        return $created;
    }

    /**
     * Reverse every origin booking in the given linkage array. Best-effort: one
     * origin failing to cancel does not stop the others; failures are logged and
     * collected, then surfaced. Idempotent (an already-cancelled origin booking
     * is skipped).
     *
     * @param array<int,array<string,mixed>> $originBookings
     */
    public static function cancelOrigins(array $originBookings): void
    {
        if (empty($originBookings)) {
            return;
        }

        $failures = [];

        foreach ($originBookings as $ref) {
            $tenantId = $ref['tenant'] ?? null;
            if (!$tenantId) {
                continue;
            }
            try {
                self::runInTenant($tenantId, fn () => self::reverseOriginBooking($ref));
            } catch (\Throwable $e) {
                $failures[] = $tenantId . '/' . ($ref['tour_booking_id'] ?? '?') . ': ' . $e->getMessage();
                Log::error('PartnerBookings: origin cancellation failed', [
                    'origin' => $ref,
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        if (!empty($failures)) {
            throw new \RuntimeException('One or more origin cancellations failed: ' . implode('; ', $failures));
        }
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    /**
     * Run $fn within the given tenant's context, then restore the previously
     * active tenant (the partner tenant). try/finally guarantees restoration.
     */
    private static function runInTenant(string $tenantId, callable $fn)
    {
        $previous = tenant(); // partner tenant (e.g. lc), already initialized by domain

        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            throw new \RuntimeException("Origin tenant '{$tenantId}' does not exist.");
        }

        tenancy()->initialize($tenant);
        try {
            return $fn();
        } finally {
            tenancy()->end();
            if ($previous) {
                tenancy()->initialize($previous);
            }
        }
    }

    private static function compensate(array $created): void
    {
        foreach ($created as $ref) {
            $tenantId = $ref['tenant'] ?? null;
            if (!$tenantId) {
                continue;
            }
            try {
                self::runInTenant($tenantId, fn () => self::reverseOriginBooking($ref));
            } catch (\Throwable $ce) {
                Log::error('PartnerBookings: compensation reversal failed', [
                    'origin' => $ref,
                    'error'  => $ce->getMessage(),
                ]);
            }
        }
    }

    /** Matching origin slot for a partner slot, by package + date + start time. */
    private static function findOriginSlot(int $packageId, string $date, string $timeFrom): ?object
    {
        return DB::table('tour_slot_times')
            ->where('tour_package_id', $packageId)
            ->where('date', $date)
            ->where('time_from', $timeFrom)
            ->where('bookable_status', 'Open')
            ->whereNull('deleted_at')
            ->first();
    }

    /** Seats left on an origin slot = capacity + overbook - active tourists. */
    private static function originVacancy(object $slot, int $overbook): int
    {
        $booked = (int) DB::table('tour_bookings_master')
            ->where('tour_slot_id', $slot->id)
            ->where('status', '!=', 'Cancelled')
            ->sum('tourists');

        return max(0, (int) $slot->time_slot_seats + $overbook - $booked);
    }

    /**
     * Create a STANDARD booking in the (already-initialized) origin tenant so the
     * origin operator sees an ordinary booking that consumes seats.
     *
     * Pricing comes from the origin AFFILIATE's own rates (the origin's negotiated
     * net rate for this partner, keyed by origin_customer_id) — never lc's retail
     * price, which is never forwarded here. The affiliate is required, and the
     * booking must produce at least one priced line: the origin tenant's
     * booking-info view assumes >= 1 rate row, so a rate-less booking would be
     * un-openable. When no priceable rate exists we throw, and the confirmOrigins
     * saga compensates rather than leave a broken booking behind.
     *
     * @return int origin tour_bookings_master id
     */
    private static function createOriginBooking(
        int $packageId,
        object $slot,
        ?int $customerId,
        int $tourists,
        int $infants,
        array $contact,
        string $reference
    ): int {
        $firstName = $contact['firstName'] ?? '';
        $lastName  = $contact['lastName'] ?? '';
        $email     = $contact['emailAddress'] ?? '';
        $phone     = $contact['phoneNumber'] ?? '';
        $name      = trim($firstName . ' ' . $lastName) ?: 'Guest';

        $slotYear    = (int) substr((string) $slot->date, 0, 4);
        $originTenant = (string) (tenant()?->id ?? '?');

        // The origin affiliate is required to price the booking.
        if (!$customerId) {
            throw new \RuntimeException(
                "Origin {$originTenant}/{$packageId} has no affiliate (origin_customer_id) configured for pricing."
            );
        }

        // Rates are the origin AFFILIATE's own active, current-year, standard rates.
        $rates = TourPackageRate::where('tour_package_id', $packageId)
            ->where('affiliate_id', $customerId)
            ->where('status', 'Active')
            ->whereNull('custom_date_id')
            ->where('scheduling_year', $slotYear)
            ->get();

        $adultRate  = $rates->first(fn ($r) => (int) $r->is_infant_rate === 0 && (int) $r->is_group_rate === 0);
        $infantRate = $rates->first(fn ($r) => (int) $r->is_infant_rate === 1);

        // Must produce >= 1 priced line, else the booking is un-openable in the
        // origin tenant. Fail here so the fan-out saga compensates.
        $willPriceAdult  = $adultRate  && $tourists > 0;
        $willPriceInfant = $infantRate && $infants > 0;
        if (!$willPriceAdult && !$willPriceInfant) {
            throw new \RuntimeException(
                "Origin {$originTenant}/{$packageId} is not priceable for affiliate {$customerId} (no active {$slotYear} rate)."
            );
        }

        // The booking code's prefix is what the UI resolves the reseller's title and
        // logo from (getTitleFromOtaBookingCode -> getOTALogoByTitle), so it has to be
        // the affiliate's OTA prefix, not a literal. A hardcoded 'PARTNER-' matches no
        // entry in constants.otas, so both lookups return null and the calendar renders
        // a broken <img src="images/otas/">. Resolve it from the origin affiliate's
        // ota_id, mirroring the OCTO path. initPrefix() only returns null for an empty
        // source; an unmatched one still falls back to a real OTA entry.
        $affiliate = Customer::find($customerId);
        $otaPrefix = BokunUtils::initPrefix($affiliate?->ota_id ?: $affiliate?->name)['prefix'] ?? 'PARTNER';

        $tourBooking = TourBooking::create([
            'ota_reference_booking'    => $otaPrefix . '-' . $reference,
            'tour_package_id'          => $packageId,
            'tour_slot_id'             => (int) $slot->id,
            'customer_id'              => $customerId ?? 0,
            'name'                     => $name,
            'email'                    => $email,
            'phone_number'             => $phone ?: '',
            'tourists'                 => $tourists,
            'infants'                  => $infants,
            'comments'                 => '',
            'note'                     => '',
            'mask_required'            => 'Yes',
            'total'                    => 0,
            'booking_total'            => 0,
            'service_commission'       => 0,
            'liability_waiver_signing' => '',
            'status'                   => 'Booked',
            'is_group_booking'         => 0,
            'request_from'             => 'Partner',
            'created_by'               => $customerId ?? 0,
            'created_by_user_type'     => 'Affiliate',
        ]);

        $transactRows = [];
        $bookingTotal = 0.0;

        foreach ([[$adultRate, $tourists, false], [$infantRate, $infants, true]] as [$rate, $qty, $isInfant]) {
            if (!$rate || $qty <= 0) {
                continue;
            }
            $lineTotal = round(
                ($rate->rate + $rate->additional_charge + $rate->permit_fee) *
                (1 + (float) $rate->processing_charge_percentage / 100) *
                $qty,
                2
            );
            $bookingTotal += $lineTotal;

            $transactRows[] = [
                'tour_booking_id'              => $tourBooking->id,
                'tour_package_rate_id'         => $rate->id,
                'tourists'                     => $isInfant ? 0 : $qty,
                'infants'                      => $isInfant ? $qty : 0,
                'is_infant_rate'               => $isInfant ? 1 : 0,
                'is_group_rate'                => 0,
                'additional_tax_percentage'    => $rate->additional_tax_percentage ?? 0,
                'processing_charge_percentage' => $rate->processing_charge_percentage ?? 0,
                'nat_fees_percentage'          => 0,
                'stripe_fees_percentage'       => 0,
                'additional_charge'            => $rate->additional_charge ?? 0,
                'permit_fee'                   => $rate->permit_fee ?? 0,
                'rate'                         => $rate->rate ?? 0,
                'total'                        => $lineTotal,
                'check_in_status'              => '',
                'deleted_by'                   => 0,
                'created_by'                   => $customerId ?? 0,
                'created_by_user_type'         => 'Affiliate',
                'created_at'                   => now(),
                'updated_at'                   => now(),
            ];
        }

        // Guaranteed non-empty by the priceable guard above.
        DB::table('tour_bookings_transact')->insert($transactRows);
        $tourBooking->update(['total' => $bookingTotal, 'booking_total' => $bookingTotal]);

        $totalPax = $tourists + $infants;
        DB::table('tour_booking_history')->insert([
            'tour_booking_id'        => $tourBooking->id,
            'tourists'               => $totalPax,
            'operation'              => 'Added',
            'details'                => json_encode([
                'sub_operation' => 'Tourists Added',
                'newTourists'   => $totalPax,
            ], JSON_NUMERIC_CHECK),
            'operation_at'           => mysqlDT(),
            'operation_by'           => $customerId ?? 0,
            'operation_by_user_type' => 'Affiliate',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        if ($tourists > 0) {
            TimeSlotAvailabilityService::updateSlotSummary($slot->id, $tourists);
        }

        // Mirror the origin operator's own "close slot on booking" preference.
        if ((int) (TourPackage::whereKey($packageId)->value('slot_close_on_booking') ?? 0) === 1) {
            TimeSlotService::closeSlotOnBooking($packageId, (int) $slot->id);
        }

        return (int) $tourBooking->id;
    }

    /**
     * Cancel a single origin booking (in the already-initialized origin tenant)
     * and restore its seats. Idempotent.
     *
     * @param array<string,mixed> $ref
     */
    private static function reverseOriginBooking(array $ref): void
    {
        $tourBookingId = (int) ($ref['tour_booking_id'] ?? 0);
        if (!$tourBookingId) {
            return;
        }

        $tourBooking = TourBooking::find($tourBookingId);
        if (!$tourBooking || $tourBooking->status === 'Cancelled') {
            return; // already gone / never created
        }

        DB::transaction(function () use ($tourBooking) {
            $totalPax = (int) $tourBooking->tourists + (int) $tourBooking->infants;

            DB::table('tour_booking_history')->insert([
                'tour_booking_id'        => $tourBooking->id,
                'tourists'               => $totalPax,
                'operation'              => 'Cancelled',
                'details'                => json_encode([
                    'sub_operation' => 'Cross-tenant Cancelled',
                    'note'          => 'Partner cross-tenant cancellation',
                ], JSON_NUMERIC_CHECK),
                'operation_at'           => mysqlDT(),
                'operation_by'           => $tourBooking->customer_id,
                'operation_by_user_type' => 'Affiliate',
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);

            $tourBooking->update([
                'status'               => 'Cancelled',
                'updated_by'           => $tourBooking->customer_id,
                'updated_by_user_type' => 'Affiliate',
            ]);

            if ((int) $tourBooking->tourists > 0) {
                TimeSlotAvailabilityService::updateSlotSummary($tourBooking->tour_slot_id, -(int) $tourBooking->tourists);
            }

            TimeSlotService::reopenSlotOnCancellation(
                (int) $tourBooking->tour_package_id,
                (int) $tourBooking->tour_slot_id,
                (int) $tourBooking->id
            );
        });
    }
}
