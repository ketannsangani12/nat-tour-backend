<?php

namespace App\Services\Octo;

use App\Models\Category;
use App\Models\Customer;
use App\Models\OctoBooking;
use App\Models\TourPackage;
use App\Models\TourPackageRate;
use App\Services\BokunService;
use App\Services\TimeSlot\TimeSlotService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OctoService
{
    /**
     * Returns true if the given capability was requested via the Octo-Capabilities header.
     */
    public static function hasCapability(string $capability): bool
    {
        return in_array($capability, request()->octo_capabilities ?? [], true);
    }

    /**
     * Formats a phone number to E.164 using the country's phone_code.
     * Strips non-numeric characters from the local number, then prepends +{code}.
     */
    public static function formatPhoneE164(?string $phone, ?string $countryPhoneCode): ?string
    {
        $digits = preg_replace('/\D/', '', $phone ?? '');
        $code   = preg_replace('/\D/', '', $countryPhoneCode ?? '');

        if (empty($digits) || empty($code)) {
            return null;
        }

        // Avoid double-prepending if the number already starts with the country code
        if (str_starts_with($digits, $code)) {
            return '+' . $digits;
        }

        return '+' . $code . $digits;
    }

    /**
     * Builds an OCTO-spec media object.
     */
    public static function buildMediaObject(string $src, string $rel, string $type = 'image/png', ?string $title = null, ?string $caption = null, ?string $copyright = null): array
    {
        return [
            'type'      => $type,
            'rel'       => $rel,
            'src'       => $src,
            'title'     => $title,
            'caption'   => $caption,
            'copyright' => $copyright,
        ];
    }

    /**
     * Builds the content capability fields for a product and its option.
     * Returns ['product' => [...], 'option' => [...]] to be merged into the respective arrays.
     */
    public static function buildProductContent(object $package, array $photos, string $tenantId, ?string $categoryName, ?string $operatorName = null): array
    {
        $durationMinutes = (((int) ($package->hours ?? 0)) * 60) + ((int) ($package->minutes ?? 0));
        $media           = self::buildPackageMedia($photos, $tenantId, $package->id, $package->name, $operatorName);
        $features        = self::buildPackageFeatures($package);
        $locations       = self::buildPackageLocations($package);

        $shared = [
            'title'               => $package->name,
            'shortDescription'    => $package->short_description ?? null,
            'description'         => $package->long_description ?? null,
            'features'            => $features,
            'media'               => $media,
            'locations'           => $locations,
            'categoryLabels'      => $categoryName ? [$categoryName] : [],
            'durationMinutesFrom' => $durationMinutes ?: null,
        ];

        return [
            'product' => $shared,
            'option'  => $shared,
        ];
    }

    public static function buildPackageFeatures(object $package): array
    {
        $features = [];
        foreach (['things_to_bring' => 'PREARRIVAL_INFORMATION', 'important_notes' => 'ADDITIONAL_INFORMATION', 'cancellation' => 'CANCELLATION_TERM'] as $field => $type) {
            if (empty($package->$field)) continue;

            if ($field === 'things_to_bring') {
                $value = $package->$field;
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $items = array_filter(array_map('trim', $decoded));
                } elseif (str_contains($value, ',')) {
                    $items = array_filter(array_map('trim', explode(',', $value)));
                } else {
                    $items = array_filter(array_map('trim', explode("\n", $value)));
                }
                if ($items) {
                    $features[] = ['shortDescription' => implode(', ', $items), 'type' => $type];
                }
                continue;
            }

            foreach (array_filter(array_map('trim', explode("\n", $package->$field))) as $line) {
                $features[] = ['shortDescription' => $line, 'type' => $type];
            }
        }
        return $features;
    }

    public static function buildPackageMedia(array $photos, string $tenantId, int $packageId, ?string $title = null, ?string $copyright = null): array
    {
        $media = [];
        foreach ($photos as $photo) {
            $s3Path  = 'images/tour-operator/' . $tenantId . '/package/' . $packageId . '/' . $photo->path;
            $media[] = self::buildMediaObject(Storage::disk('s3')->url($s3Path), 'GALLERY', 'image/png', $title, null, $copyright);
        }
        return $media;
    }

    public static function buildPackageLocations(object $package): array
    {
        if (empty($package->latitude) || empty($package->longitude)) {
            return [];
        }
        $durationMinutes = (((int) ($package->hours ?? 0)) * 60) + ((int) ($package->minutes ?? 0));
        return [[
            'title'            => $package->name,
            'shortDescription' => null,
            'types'            => ['START', 'END'],
            'minutesTo'        => null,
            'minutesAt'        => $durationMinutes ?: null,
            'place'            => [
                'latitude'     => (float) $package->latitude,
                'longitude'    => (float) $package->longitude,
                'postalAddress' => [
                    'streetAddress'       => null,
                    'addressLocality'     => null,
                    'addressRegion'       => null,
                    'postalCode'          => null,
                    'addressCountry'      => null,
                    'postOfficeBoxNumber' => null,
                ],
                'identifiers' => [
                    'googlePlaceId'        => null,
                    'applePlaceId'         => null,
                    'tripadvisorLocationId'=> null,
                    'yelpPlaceId'          => null,
                    'facebookPlaceId'      => null,
                    'foursquarePlaceId'    => null,
                    'baiduPlaceId'         => null,
                    'amapPlaceId'          => null,
                ],
                'sameAs'      => [],
            ],
        ]];
    }

    /**
     * Contact fields collected once per booking (lead traveler).
     * Applied at the Option level.
     */
    public static function requiredContactFields(): array
    {
        return ['firstName', 'lastName', 'emailAddress'];
    }

    /**
     * Contact fields collected per ticket/pax (unit level).
     * Empty by default — contact info is collected once per booking.
     * Override per unit type when per-pax details are required.
     */
    public static function requiredUnitContactFields(): array
    {
        return [];
    }

    /**
     * Maps internal rate_for label to OCTO unit type enum.
     * Ref: https://docs.octo.travel — unit type possible values.
     */
    public static function mapUnitType(string $rateFor): string
    {
        $normalized = strtolower(trim($rateFor));

        $keywords = [
            'adult'    => 'ADULT',
            'youth'    => 'YOUTH',
            'child'    => 'CHILD',
            'infant'   => 'INFANT',
            'family'   => 'FAMILY',
            'senior'   => 'SENIOR',
            'student'  => 'STUDENT',
            'military' => 'MILITARY',
        ];

        foreach ($keywords as $keyword => $type) {
            if (str_contains($normalized, $keyword)) {
                return $type;
            }
        }

        return 'OTHER';
    }

    /**
     * Returns distinct "HH:MM" start times configured for a single affiliate+package.
     * Uses the affiliate slot config table — lightweight query suitable for single product.
     */
    public static function getAvailabilityStartTimes(int $packageId, int $affiliateId): array
    {
        $affiliate = (object) ['id' => $affiliateId];
        $request   = (object) ['tourPackageId' => $packageId];

        $slots = TimeSlotService::getAffiliateTimeSlots($affiliate, $request);

        return collect($slots)
            ->filter(fn($slot) => (int) $slot->seat_limit > 0)
            ->pluck('time_from')
            ->map(fn($time) => substr($time, 0, 5))
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * Per-date availability for the calendar endpoint.
     * Aggregates all slots on each date into a single calendar entry.
     * Past slots (in the given timezone) are excluded from available capacity.
     * Slots within the OTA cutoff window ($otaCutoffHours) are also excluded.
     */
    public static function getCalendarAvailability(int $packageId, int $affiliateId, string $startDate, string $endDate, string $timezone = 'UTC', int $otaCutoffHours = 0, ?int $affiliateMaxPax = null): array
    {
        $rows    = self::fetchAffiliateSlotRows($packageId, $affiliateId, $startDate, $endDate, $affiliateMaxPax);
        $nowInTz = Carbon::now($timezone);

        $byDate = [];
        foreach ($rows as $row) {
            $byDate[$row->date][] = $row;
        }

        $calendar = [];
        foreach ($byDate as $date => $slots) {
            $openSlots = array_values(array_filter($slots, function ($s) use ($timezone, $otaCutoffHours, $nowInTz) {
                if (in_array($s->bookable_status, ['Closed', 'Only by Phone'])) {
                    return false;
                }
                if (self::isSlotPast($s, $timezone)) {
                    return false;
                }
                if ($otaCutoffHours > 0) {
                    $slotStart = Carbon::createFromFormat('Y-m-d H:i:s', $s->date . ' ' . $s->time_from, $timezone);
                    if ($slotStart->lt($nowInTz->copy()->addHours($otaCutoffHours))) {
                        return false;
                    }
                }
                return true;
            }));

            if (empty($openSlots)) {
                $calendar[] = [
                    'localDate'    => $date,
                    'available'    => false,
                    'status'       => 'CLOSED',
                    'vacancies'    => 0,
                    'capacity'     => 0,
                    'openingHours' => [],
                ];
                continue;
            }

            // capacity  = total seats across all open slots for the day
            // vacancies = highest remaining from any single slot (per OCTO spec)
            // status    = derived from the best slot's fill ratio
            $totalCapacity    = 0;
            $maxVacancies     = 0;
            $bestSlotCapacity = 0;
            $hasFreesale      = false;

            foreach ($openSlots as $slot) {
                [$slotCapacity, $slotAvailable] = self::resolveSlotAvailability($slot);
                $totalCapacity += $slotCapacity;
                // slotCapacity === 0 means FREESALE (no affiliate seat cap)
                if ($slotCapacity === 0) {
                    $hasFreesale = true;
                }
                if ($slotAvailable > $maxVacancies) {
                    $maxVacancies     = $slotAvailable;
                    $bestSlotCapacity = $slotCapacity;
                }
            }

            $openingHours = array_map(fn($s) => [
                'from' => substr($s->time_from, 0, 5),
                'to'   => substr($s->time_to, 0, 5),
            ], $openSlots);

            $calendar[] = [
                'localDate'    => $date,
                'available'    => $hasFreesale || $maxVacancies > 0,
                'status'       => self::resolveStatus($hasFreesale ? 1 : $maxVacancies, $hasFreesale ? 0 : $bestSlotCapacity),
                'vacancies'    => $hasFreesale ? null : $maxVacancies,
                'capacity'     => $totalCapacity,
                'openingHours' => array_values($openingHours),
            ];
        }

        return $calendar;
    }

    /**
     * Builds an OCTO pricing object from a TourPackageRate.
     * Amounts are in the smallest currency unit (cents for USD).
     *
     * @param  TourPackageRate  $rate
     * @param  string           $currency   ISO-4217 code, e.g. 'USD'
     */
    public static function buildPricingObject(TourPackageRate $rate, string $currency = 'USD'): array
    {
        $isGroupRate = (int) $rate->is_group_rate === 1;
        $perPerson   = (float) $rate->rate + (float) $rate->additional_charge + ($isGroupRate ? 0.0 : (float) $rate->permit_fee);

        $cents = (int) round($perPerson * 100);

        return [
            'original'          => $cents,
            'retail'            => $cents,
            'net'               => null,
            'currency'          => $currency,
            'currencyPrecision' => 2,
            'includedTaxes'     => [],
        ];
    }

    /**
     * Per-slot availability for the availability check endpoint.
     * Each time slot on each date becomes its own Availability object.
     * Past slots (based on operator timezone) are returned with 0 vacancies and status CLOSED.
     *
     * @param  array|null  $unitPricingByDate  date → Collection<standardRateId, effectiveTourPackageRate>
     *                                         Pass when pricing capability is active.
     * @param  \Illuminate\Support\Collection|null  $standardUnits  Collection<id, TourPackageRate>
     *                                         Pass when availability capability is active.
     */
    public static function getAvailabilityCheck(int $packageId, int $affiliateId, string $startDate, string $endDate, $operator, int $otaCutoffHours = 0, ?array $unitPricingByDate = null, ?\Illuminate\Support\Collection $standardUnits = null, ?int $affiliateMinPax = null, ?int $affiliateMaxPax = null): array
    {
        $rows             = self::fetchAffiliateSlotRows($packageId, $affiliateId, $startDate, $endDate, $affiliateMaxPax);
        $timezone         = $operator?->timezone ?? 'UTC';
        $cancellationDays = (int) ($operator?->cancellation_within_days ?? 1);
        $currency         = currencyFormation(code: true) ?: 'USD';
        $nowInTz          = Carbon::now($timezone);

        $availabilities = [];
        foreach ($rows as $slot) {
            $slotStart = Carbon::createFromFormat('Y-m-d H:i:s', $slot->date . ' ' . $slot->time_from, $timezone);
            $slotEnd   = Carbon::createFromFormat('Y-m-d H:i:s', $slot->date . ' ' . $slot->time_to, $timezone);

            $isPast         = $slotStart->lte($nowInTz);
            $isWithinCutoff = !$isPast
                && $otaCutoffHours > 0
                && $slotStart->lt($nowInTz->copy()->addHours($otaCutoffHours));
            $isClosed = $isPast || $isWithinCutoff || in_array($slot->bookable_status, ['Closed', 'Only by Phone']);

            [$slotCapacity, $slotAvailable] = self::resolveSlotAvailability($slot);
            // slotCapacity === 0 means FREESALE (no affiliate seat cap) — always available unless closed
            $freesale  = !$isClosed && $slotCapacity === 0;
            $vacancies = $isClosed ? 0 : $slotAvailable;
            $status    = $isClosed ? 'CLOSED' : self::resolveStatus($vacancies, $slotCapacity);
            // Affiliate-level max pax from customers table; null = no restriction
            $maxUnits = $isClosed ? 0
                : ($freesale
                    ? ((int) $affiliateMaxPax ?: null)
                    : ((int) $affiliateMaxPax > 0 ? min($vacancies, (int) $affiliateMaxPax) : null)
                );

            $localStart = $slotStart->toIso8601String();
            $localEnd   = $slotEnd->toIso8601String();
            // Priority: ota_duration (hours) → block_ctb_duration (hours) → cancellation_within_days (days)
            $cutoffAt = $otaCutoffHours > 0
                ? $slotStart->copy()->subHours($otaCutoffHours)->utc()->toIso8601ZuluString()
                : $slotStart->copy()->subDays($cancellationDays)->utc()->toIso8601ZuluString();

            $entry = [
                'id'                 => (string) $slot->slot_id,
                'localDateTimeStart' => $localStart,
                'localDateTimeEnd'   => $localEnd,
                'utcCutoffAt'        => $cutoffAt,
                'allDay'             => false,
                'available'          => !$isClosed && ($freesale || $vacancies > 0),
                'status'             => $status,
                'vacancies'          => $freesale ? null : $vacancies,
                'capacity'           => $slotCapacity,
                'maxUnits'           => $maxUnits,
                'openingHours'       => [[
                    'from' => substr($slot->time_from, 0, 5),
                    'to'   => substr($slot->time_to, 0, 5),
                ]],
            ];

            if ($unitPricingByDate !== null) {
                // Slot-specific surcharge (apply_on_slots=1) takes priority over general (apply_on_slots=0)
                $slotKey      = $slot->date . '_' . substr($slot->time_from, 0, 5);
                $ratesForSlot = $unitPricingByDate['by_slot_time'][$slotKey]
                    ?? $unitPricingByDate['by_date'][$slot->date]
                    ?? collect();

                $entry['unitPricing'] = $ratesForSlot->map(fn (TourPackageRate $rate, $standardRateId) => array_merge(
                    ['unitId' => (string) $rate->id],
                    self::buildPricingObject($rate, $currency)
                ))->values()->toArray();
            }

            if ($standardUnits !== null) {
                $entry['unitItems'] = $standardUnits->map(fn (TourPackageRate $unit) => [
                    'unitId'    => (string) $unit->id,
                    'available' => !$isClosed && ($freesale || $vacancies > 0),
                    'vacancies' => $freesale ? null : $vacancies,
                ])->values()->toArray();
            }

            $availabilities[] = $entry;
        }

        return $availabilities;
    }

    /**
     * Batch-loads start times for multiple packages in one query.
     * Returns array keyed by package_id — use this in product list to avoid N+1.
     */
    public static function batchAvailabilityStartTimes(array $packageIds, int $affiliateId): array
    {
        if (empty($packageIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($packageIds), '?'));

        $rows = DB::select("
            SELECT tsta.tour_package_id, tsta.time_from
            FROM tour_slot_times_affiliate tsta
            JOIN affiliate_slot_times ast ON ast.tour_slot_time_id = tsta.id
            WHERE ast.affiliate_id = ?
              AND tsta.tour_package_id IN ({$placeholders})
              AND ast.seat_limit > 0
              AND tsta.status = 'Active'
              AND tsta.deleted_by = 0
              AND ast.deleted_by = 0
            ORDER BY tsta.tour_package_id, tsta.time_from ASC
        ", array_merge([$affiliateId], $packageIds));

        $grouped = [];
        foreach ($rows as $row) {
            $time = substr($row->time_from, 0, 5);
            if (!isset($grouped[$row->tour_package_id]) || !in_array($time, $grouped[$row->tour_package_id])) {
                $grouped[$row->tour_package_id][] = $time;
            }
        }

        return $grouped;
    }

    /**
     * Returns true when this affiliate has at least one active infant rate for the package,
     * meaning infants are explicitly booked and count against seat capacity.
     */
    public static function hasInfantRates(int $packageId, int $affiliateId): bool
    {
        return TourPackageRate::where('tour_package_id', $packageId)
            ->where('affiliate_id', $affiliateId)
            ->where('is_infant_rate', 1)
            ->where('status', 'Active')
            ->whereNull('custom_date_id')
            ->exists();
    }

    /**
     * Shared query used by both calendar and availability-check endpoints.
     * Returns per-slot rows with affiliate seat limits and booking counts.
     * When the package has infant rates configured, infants are included in the affiliate-scoped
     * booked count (afl_booked) only. global_booked always stays tourists-only, matching
     * resolveRemainingSeats()/the main calendar/BokunService — otherwise availability here would
     * over-subtract capacity relative to what the booking-creation path actually enforces whenever
     * any booking on the slot carries non-zero infants.
     */
    private static function fetchAffiliateSlotRows(int $packageId, int $affiliateId, string $startDate, string $endDate, ?int $affiliateMaxPax = null): array
    {
        $countInfants = self::hasInfantRates($packageId, $affiliateId);
        $obInfant     = $countInfants ? ' + ob.infants'  : '';
        $tbmInfant    = $countInfants ? ' + tbm.infants' : '';
        $now          = now()->toDateTimeString();

        return DB::select("
            SELECT
                tst.id         AS slot_id,
                tst.date,
                tst.time_from,
                tst.time_to,
                tst.bookable_status,
                IFNULL(tst.time_slot_seats, 0) AS slot_seats,
                IFNULL(ast.seat_limit, 0) AS afl_seat_limit,
                IFNULL((
                    SELECT SUM(tbm.tourists{$tbmInfant})
                    FROM tour_bookings_master tbm
                    WHERE tbm.tour_slot_id = tst.id
                      AND tbm.status != 'Cancelled'
                      AND tbm.customer_id = ?
                ), 0) + IFNULL((
                    SELECT SUM(ob.tourists{$obInfant})
                    FROM octo_bookings ob
                    WHERE ob.tour_slot_id = tst.id
                      AND ob.status = 'ON_HOLD'
                      AND ob.expire_at > ?
                      AND ob.affiliate_id = ?
                ), 0) AS afl_booked,
                IFNULL((
                    SELECT SUM(tbm.tourists)
                    FROM tour_bookings_master tbm
                    WHERE tbm.tour_slot_id = tst.id
                      AND tbm.status != 'Cancelled'
                ), 0) + IFNULL((
                    SELECT SUM(ob.tourists)
                    FROM octo_bookings ob
                    WHERE ob.tour_slot_id = tst.id
                      AND ob.status = 'ON_HOLD'
                      AND ob.expire_at > ?
                ), 0) + IFNULL((
                    SELECT SUM(br.seats)
                    FROM bokun_reservations br
                    WHERE br.tour_slot_id = tst.id
                      AND br.expired_at > ?
                ), 0) AS global_booked
            FROM tour_slot_times tst
            JOIN tour_slot_times_affiliate tsta
                ON  tsta.tour_package_id = tst.tour_package_id
                AND tsta.time_from       = tst.time_from
            JOIN affiliate_slot_times ast
                ON  ast.tour_slot_time_id = tsta.id
                AND ast.affiliate_id      = ?
            WHERE tst.tour_package_id = ?
              AND tst.date BETWEEN ? AND ?
              AND tst.deleted_by  = 0
              AND tst.hide_slot   = 0
              AND tsta.status     = 'Active'
              AND tsta.deleted_by = 0
              AND ast.deleted_by  = 0
              AND ast.seat_limit  > 0
            ORDER BY tst.date ASC, tst.time_from ASC
        ", [$affiliateId, $now, $affiliateId, $now, $now, $affiliateId, $packageId, $startDate, $endDate]);
    }

    public static function getAvailabilityCheckByIds(int $packageId, int $affiliateId, array $slotIds, $operator, int $otaCutoffHours = 0, ?\Illuminate\Support\Collection $standardUnits = null, ?int $affiliateMinPax = null, ?int $affiliateMaxPax = null): array
    {
        if (empty($slotIds)) {
            return [];
        }

        $bounds = DB::table('tour_slot_times')
            ->whereIn('id', $slotIds)
            ->selectRaw('MIN(date) AS min_date, MAX(date) AS max_date')
            ->first();

        if (!$bounds?->min_date) {
            return [];
        }

        $idSet = array_map('strval', $slotIds);
        $all   = self::getAvailabilityCheck($packageId, $affiliateId, $bounds->min_date, $bounds->max_date, $operator, $otaCutoffHours, null, $standardUnits, $affiliateMinPax, $affiliateMaxPax);

        return array_values(array_filter($all, fn ($entry) => in_array($entry['id'], $idSet)));
    }


    /**
     * Core availability formula — single source of truth used by both the batch
     * availability path and the transactional reservation path.
     * Pass $slotTotalSeats = 0 for freesale (no global cap; only affiliate limit applies).
     *
     * @return array{0: int, 1: int}  [capacity, availableSeats]
     */
    private static function computeAvailableSeats(int $slotTotalSeats, int $aflLimit, int $globalBooked, int $aflBooked): array
    {
        $slotCapacity    = $slotTotalSeats > 0 ? min($slotTotalSeats, $aflLimit) : $aflLimit;
        $globalRemaining = $slotTotalSeats > 0 ? max(0, $slotTotalSeats - $globalBooked) : PHP_INT_MAX;
        $aflRemaining    = max(0, $slotCapacity - $aflBooked);

        return [$slotCapacity, min($globalRemaining, $aflRemaining)];
    }

    /** @return array{0: int, 1: int}  [capacity, availableSeats] */
    private static function resolveSlotAvailability(object $slot): array
    {
        return self::computeAvailableSeats(
            (int) $slot->slot_seats,
            (int) $slot->afl_seat_limit,
            (int) $slot->global_booked,
            (int) $slot->afl_booked,
        );
    }

    /**
     * Global active ON_HOLD seat count across ALL affiliates for a slot.
     * Used for globalBooked — subtracted from total slot capacity.
     * Expired and cancelled reservations are excluded — they do not consume seat capacity.
     */
    public static function getActiveReservationSeats(int $slotId, bool $countInfants = false): int
    {
        $query = OctoBooking::where('tour_slot_id', $slotId)
            ->where('status', 'ON_HOLD')
            ->where('expire_at', '>', now());

        return $countInfants
            ? (int) $query->selectRaw('SUM(tourists + infants) as total')->value('total')
            : (int) $query->sum('tourists');
    }

    /**
     * Affiliate-scoped active ON_HOLD seat count for a slot.
     * Used for aflBooked — subtracted from this reseller's individual seat limit.
     * Ensures each reseller (e.g. Headout, TripAdvisor) stays within their own allocation.
     * Expired and cancelled reservations are excluded — they do not consume seat capacity.
     */
    public static function getAffiliateActiveReservationSeats(int $slotId, int $affiliateId, bool $countInfants = false): int
    {
        $query = OctoBooking::where('tour_slot_id', $slotId)
            ->where('affiliate_id', $affiliateId)
            ->where('status', 'ON_HOLD')
            ->where('expire_at', '>', now());

        return $countInfants
            ? (int) $query->selectRaw('SUM(tourists + infants) as total')->value('total')
            : (int) $query->sum('tourists');
    }

    /**
     * Transactional reservation path (createBooking).
     * Runs fresh DB queries — must be called inside a lockForUpdate transaction to prevent races.
     */
    public static function resolveRemainingSeats(int $slotId, int $affiliateId, int $affiliateLimit, int $slotTotalSeats, int $packageId): int
    {
        $countInfants = self::hasInfantRates($packageId, $affiliateId);
        $aflCol       = $countInfants ? DB::raw('tourists + infants') : 'tourists';

        // Global capacity tracks tourists only — matches TimeSlotAvailabilityService::updateSlotSummary
        // which never adds infants. Including infants here would diverge from the UI count whenever
        // any booking on the slot carries non-zero infants, producing false "slot full" errors.
        $globalConfirmed = (int) DB::table('tour_bookings_master')
            ->where('tour_slot_id', $slotId)
            ->where('status', '!=', 'Cancelled')
            ->sum('tourists');

        // Affiliate count respects the infant-rate flag so the affiliate's own seat cap is enforced
        // correctly when this affiliate explicitly books infants as capacity-consuming seats.
        $aflConfirmed = (int) DB::table('tour_bookings_master')
            ->where('tour_slot_id', $slotId)
            ->where('customer_id', $affiliateId)
            ->where('status', '!=', 'Cancelled')
            ->sum($aflCol);

        $globalBooked = $globalConfirmed
            + self::getActiveReservationSeats($slotId, false)
            + BokunService::getActiveReservationSeats($slotId);

        $aflBooked = $aflConfirmed + self::getAffiliateActiveReservationSeats($slotId, $affiliateId, $countInfants);

        [, $available] = self::computeAvailableSeats($slotTotalSeats, $affiliateLimit, $globalBooked, $aflBooked);

        return $available;
    }

    /**
     * Returns true when the slot's start datetime has already passed in the given timezone.
     */
    private static function isSlotPast(object $slot, string $timezone): bool
    {
        return Carbon::createFromFormat('Y-m-d H:i:s', $slot->date . ' ' . $slot->time_from, $timezone)
            ->lte(Carbon::now($timezone));
    }

    // ── Booking response helpers ──────────────────────────────────────────────

    /**
     * Builds the full OCTO booking response envelope.
     */
    public static function formatBookingResponse(OctoBooking $octoBooking, ?array $pricing = null, ?array $unitPricingByRateId = null, ?string $cancelReason = null, string $refundType = 'NONE'): array
    {
        $isConfirmed = $octoBooking->status === 'CONFIRMED';
        $isCancelled = $octoBooking->status === 'CANCELLED';
        $tourBooking = $isConfirmed ? $octoBooking->tourBooking : null;
        $supplierRef = 'OCTO-' . str_pad((string) $octoBooking->id, 6, '0', STR_PAD_LEFT);

        $bookingPrefix = getSettingsByKey("BOOKING_ID_PREFIX");
        $prefix = optional($bookingPrefix)->value ?? "";

        $voucher = [
            'redemptionMethod' => 'DIGITAL',
            'utcRedeemedAt'    => null,
            'deliveryOptions'  => $isConfirmed ? [
                [
                    'deliveryFormat' => 'QRCODE',
                    'deliveryValue'  => $prefix . $tourBooking->id,
                ]
            ] : [],
        ];

        [$product, $option] = self::buildProductForBooking($octoBooking);

        $storedContact   = $octoBooking->contact ?? [];
        $resellerRef     = $storedContact['resellerReference'] ?? '';
        unset($storedContact['resellerReference']);
        $contactResponse = array_merge(self::emptyContact(), $storedContact);

        // Determine whether this booking can still be cancelled:
        // ON_HOLD → always cancellable (no cutoff applies to holds)
        // CONFIRMED → cancellable only if we are still before the cutoff window
        // CANCELLED / EXPIRED → not cancellable
        $cancellable = false;
        if ($octoBooking->status === 'ON_HOLD') {
            $cancellable = true;
        } elseif ($isConfirmed) {
            $operatorRow      = DB::table('tour_operators')->select('timezone', 'cancellation_within_days')->first();
            $timezone         = $operatorRow?->timezone ?? 'UTC';
            $cancellationDays = (int) ($operatorRow?->cancellation_within_days ?? 1);
            $slot             = DB::table('tour_slot_times')->where('id', $octoBooking->tour_slot_id)->first();
            if ($slot) {
                $cutoff      = Carbon::createFromFormat('Y-m-d H:i:s', $slot->date . ' ' . $slot->time_from, $timezone)
                    ->subDays($cancellationDays);
                $cancellable = Carbon::now($timezone)->lt($cutoff);
            }
        }

        $response = [
            'id'                => $supplierRef,
            'uuid'              => $octoBooking->uuid,
            'testMode'          => false,
            'resellerReference' => $resellerRef,
            'supplierReference' => $supplierRef,
            'status'            => $octoBooking->status,
            'utcCreatedAt'      => $octoBooking->created_at->utc()->toIso8601ZuluString(),
            'utcUpdatedAt'      => $octoBooking->updated_at->utc()->toIso8601ZuluString(),
            'utcExpiresAt'      => ($isConfirmed || $isCancelled) ? null : $octoBooking->expire_at?->utc()->toIso8601ZuluString(),
            'utcRedeemedAt'     => null,
            'utcConfirmedAt'    => $octoBooking->confirmed_at?->utc()->toIso8601ZuluString(),
            'productId'         => (string) $octoBooking->tour_package_id,
            'product'           => $product,
            'optionId'          => 'default',
            'option'            => $option,
            'cancellable'       => $cancellable,
            'cancellation'      => $isCancelled ? [
                'refund'         => $refundType,
                'reason'         => $cancelReason ?? '',
                'utcCancelledAt' => $octoBooking->updated_at->utc()->toIso8601ZuluString(),
            ] : null,
            'freesale'          => false,
            'availabilityId'    => (string) $octoBooking->tour_slot_id,
            'availability'      => self::buildAvailabilityObject($octoBooking),
            'contact'           => $contactResponse,
            'notes'             => $octoBooking->notes,
            'deliveryMethods'   => ['VOUCHER'],
            'voucher'           => $voucher,
            'unitItems'         => self::formatUnitItems($octoBooking, $tourBooking, $unitPricingByRateId),
        ];

        if ($pricing !== null) {
            $response['pricing'] = $pricing;
        }

        return $response;
    }

    private static function buildProductForBooking(OctoBooking $octoBooking): array
    {
        $package   = TourPackage::find($octoBooking->tour_package_id);
        $affiliate = Customer::find($octoBooking->affiliate_id);

        $operatorRow = DB::table('tour_operators as to')
            ->leftJoin('cities', 'cities.id', '=', 'to.city_id')
            ->leftJoin('states', 'states.id', '=', 'cities.state_id')
            ->leftJoin('countries', 'countries.id', '=', 'states.country_id')
            ->select('to.name', 'to.timezone', 'to.cancellation_within_days', 'to.tenant_id', 'countries.locale')
            ->first();

        $unitIds       = array_column($octoBooking->unit_items, 'unitId');
        $rateCountById = array_count_values($unitIds);

        $rates = TourPackageRate::whereIn('id', array_keys($rateCountById))
            ->where('tour_package_id', $octoBooking->tour_package_id)
            ->where('affiliate_id', $octoBooking->affiliate_id)
            ->where('status', 'Active')
            ->get()
            ->keyBy('id');

        $startTimes       = self::getAvailabilityStartTimes($octoBooking->tour_package_id, $octoBooking->affiliate_id);
        $cancellationDays = (int) ($operatorRow?->cancellation_within_days ?? 1);

        $isGroupPackage = (int) ($package->is_group_rate_enabled ?? 0) === 1;

        $units = [];
        foreach (array_keys($rateCountById) as $rateId) {
            $rate = $rates->get($rateId);
            if (!$rate) continue;

            $isGroupUnit = $isGroupPackage && (int) $rate->is_group_rate === 1;

            $unit = [
                'id'           => (string) $rate->id,
                'internalName' => $rate->rate_for,
                'reference'    => null,
                'type'         => self::mapUnitType($rate->rate_for),
                'restrictions' => [
                    'minAge'        => (int) ($rate->age_from ?? 0),
                    'maxAge'        => (int) ($rate->age_to ?? 99),
                    'idRequired'    => false,
                    'minQuantity'   => $isGroupUnit
                        ? (($rate->min_group_size > 0) ? (int) $rate->min_group_size : null)
                        : (($rate->min_pax_allowed && $rate->min_pax_allowed > 0) ? (int) $rate->min_pax_allowed : null),
                    'maxQuantity'   => $isGroupUnit
                        ? (($rate->max_group_size > 0) ? (int) $rate->max_group_size : null)
                        : (($rate->max_pax_allowed && $rate->max_pax_allowed > 0) ? (int) $rate->max_pax_allowed : null),
                    'paxCount'      => $isGroupUnit ? max(1, (int) $rate->min_group_size) : 1,
                    'accompaniedBy' => [],
                ],
                'requiredContactFields' => self::requiredUnitContactFields(),
            ];

            if (self::hasCapability('content')) {
                $unit['title']            = $rate->rate_for;
                $unit['shortDescription'] = $rate->description ?? null;
                $unit['features']         = [];
            }

            $units[] = $unit;
        }

        $option = [
            'id'                          => 'default',
            'default'                     => true,
            'internalName'                => $package?->name ?? '',
            'reference'                   => null,
            'availabilityLocalStartTimes' => $startTimes,
            'cancellationCutoff'          => $cancellationDays . ' day',
            'cancellationCutoffAmount'    => $cancellationDays,
            'cancellationCutoffUnit'      => 'day',
            'requiredContactFields'       => self::requiredContactFields(),
            'restrictions'                => [
                'minUnits' => $isGroupPackage ? 1 : ($affiliate->min_pax ?: 0),
                'maxUnits' => $isGroupPackage ? 1 : ($affiliate->max_pax ?: 0),
            ],
            'units' => $units,
        ];

        $product = [
            'id'                   => (string) $octoBooking->tour_package_id,
            'internalName'         => $package?->name ?? '',
            'reference'            => null,
            'locale'               => $operatorRow?->locale ?? 'en',
            'timeZone'             => $operatorRow?->timezone ?? 'UTC',
            'allowFreesale'        => true,
            'instantConfirmation'  => true,
            'instantDelivery'      => true,
            'availabilityRequired' => true,
            'availabilityType'     => 'START_TIME',
            'deliveryFormats'      => ['QRCODE'],
            'deliveryMethods'      => ['VOUCHER'],
            'redemptionMethod'     => 'DIGITAL',
            'options'              => [$option],
        ];

        if (self::hasCapability('content') && $package) {
            $tenantId = (string) ($operatorRow->tenant_id ?? '');
            $photos   = DB::table('tour_package_photos')
                ->where('tour_package_id', $package->id)
                ->where('status', 'Active')
                ->where('deleted_by', 0)
                ->orderBy('placement')
                ->get(['path'])
                ->toArray();
            $categoryName = $package->category_id ? Category::find($package->category_id)?->name : null;

            $content = self::buildProductContent($package, $photos, $tenantId, $categoryName, $operatorRow?->name);
            $product = array_merge($product, $content['product']);
            $option  = array_merge($option, $content['option']);
            $product['options'][0] = array_merge($product['options'][0], $content['option']);
        }

        return [$product, $option];
    }

    private static function buildAvailabilityObject(OctoBooking $octoBooking): ?array
    {
        $slot = DB::table('tour_slot_times')->where('id', $octoBooking->tour_slot_id)->first();

        if (!$slot) {
            return null;
        }

        $operator         = DB::table('tour_operators')->first();
        $timezone         = $operator?->timezone ?? 'UTC';
        $cancellationDays = (int) ($operator?->cancellation_within_days ?? 1);

        $slotStart = Carbon::createFromFormat('Y-m-d H:i:s', $slot->date . ' ' . $slot->time_from, $timezone);
        $slotEnd   = Carbon::createFromFormat('Y-m-d H:i:s', $slot->date . ' ' . $slot->time_to, $timezone);
        $isPast    = $slotStart->lte(Carbon::now($timezone));
        $cutoffAt  = $slotStart->copy()->subDays($cancellationDays)->utc()->toIso8601ZuluString();

        // Query affiliate seat limit directly — avoids fetchAffiliateSlotRows which
        // filters out freesale rows (seat_limit = 0) and would return null for them.
        $aflLimit = (int) DB::table('affiliate_slot_times as ast')
            ->join('tour_slot_times_affiliate as tsta', 'tsta.id', '=', 'ast.tour_slot_time_id')
            ->where('tsta.tour_package_id', $octoBooking->tour_package_id)
            ->where('tsta.time_from', $slot->time_from)
            ->where('ast.affiliate_id', $octoBooking->affiliate_id)
            ->value('ast.seat_limit');

        $slotSeats = (int) ($slot->time_slot_seats ?? 0);
        $capacity  = $slotSeats > 0 ? min($slotSeats, $aflLimit ?: $slotSeats) : $aflLimit;
        $status    = $isPast ? 'CLOSED' : 'AVAILABLE';

        return [
            'id'                 => (string) $slot->id,
            'localDateTimeStart' => $slotStart->toIso8601String(),
            'localDateTimeEnd'   => $slotEnd->toIso8601String(),
            'utcCutoffAt'        => $cutoffAt,
            'allDay'             => false,
            'available'          => !$isPast,
            'status'             => $status,
            'vacancies'          => $isPast ? 0 : $capacity,
            'capacity'           => $capacity,
            'maxUnits'           => null,
            'openingHours'       => [[
                'from' => substr($slot->time_from, 0, 5),
                'to'   => substr($slot->time_to, 0, 5),
            ]],
        ];
    }

    private static function formatUnitItems(OctoBooking $octoBooking, $tourBooking, ?array $unitPricingByRateId = null): array
    {
        $items       = $octoBooking->unit_items;
        $isConfirmed = $octoBooking->status === 'CONFIRMED';

        $unitIds = array_unique(array_column($items, 'unitId'));
        $rateMap = TourPackageRate::whereIn('id', $unitIds)->get()->keyBy('id');

        $result = [];
        foreach ($items as $index => $item) {
            $rate = $rateMap->get($item['unitId']);

            $unit = $rate ? [
                'id'           => (string) $rate->id,
                'internalName' => $rate->rate_for,
                'reference'    => null,
                'type'         => self::mapUnitType($rate->rate_for),
                'restrictions' => [
                    'minAge'        => (int) ($rate->age_from ?? 0),
                    'maxAge'        => (int) ($rate->age_to ?? 99),
                    'idRequired'    => false,
                    'minQuantity'   => ($rate->min_pax_allowed && $rate->min_pax_allowed > 0) ? (int) $rate->min_pax_allowed : null,
                    'maxQuantity'   => ($rate->max_pax_allowed && $rate->max_pax_allowed > 0) ? (int) $rate->max_pax_allowed : null,
                    'paxCount'      => 1,
                    'accompaniedBy' => [],
                ],
                'requiredContactFields' => self::requiredUnitContactFields(),
            ] : null;

            $ticketCode = ($isConfirmed && $tourBooking)
                ? 'NAT-' . $tourBooking->id . '-' . ($index + 1)
                : null;

            $unitItem = [
                'uuid'               => $item['uuid'],
                'resellerReference'  => $item['resellerReference'] ?? null,
                'supplierReference'  => $item['supplierReference'] ?? null,
                'unitId'             => $item['unitId'],
                'unit'               => $unit,
                'status'             => $octoBooking->status,
                'utcRedeemedAt'      => null,
                'contact'            => $item['contact'] ?? self::emptyContact(),
                'ticket'             => null,
            ];

            if ($unitPricingByRateId !== null) {
                $rateId = (string) ($item['unitId'] ?? '');
                if (isset($unitPricingByRateId[$rateId])) {
                    $unitItem['pricing'] = $unitPricingByRateId[$rateId];
                }
            }

            $result[] = $unitItem;
        }

        return $result;
    }

    public static function emptyContact(): array
    {
        return [
            'fullName'       => null,
            'firstName'      => null,
            'lastName'       => null,
            'emailAddress'   => null,
            'phoneNumber'    => null,
            'locales'        => [],
            'postalCode'     => null,
            'country'        => null,
            'notes'          => null
        ];
    }

    /**
     * Maps vacancies/capacity to an OCTO status enum value.
     * capacity = 0 means no seat cap configured → FREESALE (unlimited).
     */
    private static function resolveStatus(int $vacancies, int $capacity): string
    {
        if ($capacity === 0) {
            return 'FREESALE';
        }

        if ($vacancies === 0) {
            return 'SOLD_OUT';
        }

        if ($vacancies <= (int) ($capacity / 2)) {
            return 'LIMITED';
        }

        return 'AVAILABLE';
    }
}
