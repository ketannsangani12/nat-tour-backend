<?php

namespace App\Services\Octo;

use App\Models\BookingTransact;
use App\Models\Customer;
use App\Models\OctoBooking;
use App\Models\TourBooking;
use App\Models\TourPackageRate;
use App\Utils\BokunUtils;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Resolves an OCTO booking identifier that may point at a booking made through the
 * old Bokun channel-manager integration, before OCTO existed for that OTA.
 *
 * Those bookings live only in tour_bookings_master, under the generic "Bokun" customer,
 * priced from that customer's own rate sheet — never through octo_bookings, and never
 * against the OTA's dedicated OCTO affiliate/rate sheet. This service finds them by their
 * ota_reference_booking and lazily creates the octo_bookings row they would have had if
 * booked through OCTO from the start, so getBooking/updateBooking/cancelBooking can then
 * operate on them exactly as they would for any native OCTO booking.
 */
class OctoLegacyBookingBackfillService
{
    /**
     * @throws RuntimeException when a legacy booking is found but its rates can't be
     *                          translated to the OCTO affiliate's own rate sheet.
     */
    public static function resolve(string $identifier, Customer $affiliate): ?OctoBooking
    {
        $octoBooking = OctoBooking::where('uuid', $identifier)->where('affiliate_id', $affiliate->id)->first();
        if ($octoBooking) {
            return $octoBooking;
        }

        $tourBooking = self::findLegacyTourBooking($identifier, $affiliate);
        if (!$tourBooking) {
            return null;
        }

        return DB::transaction(function () use ($tourBooking, $affiliate) {
            // Guards against two near-simultaneous requests (e.g. a client retry) both
            // trying to backfill the same legacy booking at once.
            $existing = OctoBooking::where('tour_booking_id', $tourBooking->id)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            return self::backfill($tourBooking, $affiliate);
        });
    }

    /**
     * Only ever matches references under this affiliate's own OTA prefix — an OTA's
     * bearer token must never be able to discover another OTA's booking by guessing
     * or replaying a full reference string.
     */
    private static function findLegacyTourBooking(string $identifier, Customer $affiliate): ?TourBooking
    {
        $ota    = BokunUtils::initPrefix($affiliate->ota_id ?? $affiliate->name ?? 'octo');
        $prefix = $ota['prefix'] ?? null;
        if (!$prefix) {
            return null;
        }

        $suffix        = preg_replace('/^' . preg_quote($prefix, '/') . '-/i', '', $identifier);
        $fullReference = $prefix . '-' . $suffix;

        return TourBooking::where('ota_reference_booking', $fullReference)->first();
    }

    private static function backfill(TourBooking $tourBooking, Customer $affiliate): OctoBooking
    {
        $transactRows = BookingTransact::where('tour_booking_id', $tourBooking->id)->get();

        $unitItems = [];
        foreach ($transactRows as $transact) {
            $octoRate = self::translateRateId((int) $transact->tour_package_rate_id, $tourBooking->tour_package_id, $affiliate->id);

            // Re-point this row at its OCTO-affiliate equivalent rate so future OCTO updates
            // (which diff old vs. new rate composition by tour_package_rate_id) compare like
            // with like, instead of seeing the old Bokun-scoped id and the new OCTO-scoped id
            // for the same tier as two unrelated rate groups — which would otherwise look like
            // a full refund-and-rebook of unchanged pax. Only the rate-definition pointer
            // changes here; the row's own historical rate/total/permit_fee/etc. columns (what
            // was actually charged) are untouched.
            if ((int) $transact->tour_package_rate_id !== (int) $octoRate->id) {
                $transact->update([
                    'tour_package_rate_id' => $octoRate->id,
                    'updated_by'           => $affiliate->id,
                    'updated_by_user_type' => 'Affiliate',
                ]);
            }

            for ($i = 0; $i < (int) $transact->tourists; $i++) {
                $unitItems[] = self::buildUnitItem((string) $octoRate->id);
            }
        }

        [$firstName, $lastName] = self::splitName($tourBooking->name);

        $ota               = BokunUtils::initPrefix($affiliate->ota_id ?? $affiliate->name ?? 'octo');
        $prefix            = $ota['prefix'] ?? '';
        $resellerReference = preg_replace('/^' . preg_quote($prefix, '/') . '-/i', '', $tourBooking->ota_reference_booking);

        $octoBooking = OctoBooking::create([
            'uuid'            => (string) Str::uuid(),
            'affiliate_id'    => $affiliate->id,
            'tour_slot_id'    => $tourBooking->tour_slot_id,
            'tour_package_id' => $tourBooking->tour_package_id,
            'tour_booking_id' => $tourBooking->id,
            'status'          => $tourBooking->status === 'Cancelled' ? 'CANCELLED' : 'CONFIRMED',
            'tourists'        => $tourBooking->tourists,
            'infants'         => $tourBooking->infants,
            'unit_items'      => $unitItems,
            'contact'         => [
                'fullName'          => $tourBooking->name,
                'firstName'         => $firstName,
                'lastName'          => $lastName,
                'emailAddress'      => $tourBooking->email,
                'phoneNumber'       => $tourBooking->phone_number,
                'resellerReference' => $resellerReference,
            ],
            'notes'           => $tourBooking->comments ?: $tourBooking->note,
            'expire_at'       => $tourBooking->created_at,
            'confirmed_at'    => $tourBooking->created_at,
        ]);

        return $octoBooking;
    }

    /**
     * Translates a rate id from whatever affiliate's rate sheet it actually belongs to
     * (typically the generic Bokun customer, for a legacy booking) to the equivalent
     * rate under the given affiliate's own rate sheet, via the shared mapping_key — the
     * two are different TourPackageRate rows (different ids, possibly different prices),
     * not the same row reused across affiliates. For a rate that already belongs to the
     * target affiliate (the native-OCTO-booking case), this simply resolves back to itself.
     *
     * Public because OctoRepository::buildPricingForBooking() reuses it to key historical
     * pricing data the same way unit_items are keyed, so the two line up.
     *
     * @throws RuntimeException when the source rate no longer exists, or no equivalent
     *                          rate exists under the target affiliate.
     */
    public static function translateRateId(int $rateId, int $packageId, int $affiliateId): TourPackageRate
    {
        $sourceRate = TourPackageRate::find($rateId);
        if (!$sourceRate) {
            throw new RuntimeException("tour_package_rate_id={$rateId} no longer exists; cannot map it to an affiliate rate.");
        }

        // Reject a rate from an unrelated package outright, rather than let an unlucky
        // mapping_key coincidence under the right package produce a wrong match. This
        // matters once callers pass client-supplied ids (see updateBooking()'s legacy-id
        // normalisation) rather than only ids we ourselves stored on this exact booking.
        if ((int) $sourceRate->tour_package_id !== $packageId) {
            throw new RuntimeException("tour_package_rate_id={$rateId} does not belong to package={$packageId}.");
        }

        if ((int) $sourceRate->affiliate_id === $affiliateId) {
            return $sourceRate;
        }

        $targetRate = TourPackageRate::where('tour_package_id', $packageId)
            ->where('affiliate_id', $affiliateId)
            ->where('scheduling_year', $sourceRate->scheduling_year)
            ->where('mapping_key', $sourceRate->mapping_key)
            ->first();

        if (!$targetRate) {
            throw new RuntimeException("No matching rate (mapping_key={$sourceRate->mapping_key}, package={$packageId}, year={$sourceRate->scheduling_year}) found for affiliate={$affiliateId} — its rate sheet is incomplete relative to the source affiliate's.");
        }

        return $targetRate;
    }

    private static function buildUnitItem(string $rateId): array
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $ref   = '';
        for ($i = 0; $i < 6; $i++) {
            $ref .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return [
            'uuid'              => (string) Str::uuid(),
            'resellerReference' => '',
            'supplierReference' => $ref,
            'unitId'            => $rateId,
            'contact'           => null,
        ];
    }

    private static function splitName(?string $name): array
    {
        $name = trim((string) $name);
        if ($name === '') {
            return ['', ''];
        }

        $parts = explode(' ', $name, 2);
        return [$parts[0], $parts[1] ?? ''];
    }
}
