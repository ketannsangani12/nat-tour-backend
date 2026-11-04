<?php

namespace App\Utils;

use App\Constants\Constants;
use Illuminate\Support\Facades\DB;

class BokunUtils
{
    /**
     * Detect and return only the OTA prefix (e.g. 'GET', 'VIA', 'HEAD')
     */
    public static function initPrefix(?string $source): ?array
    {
        $otas = config('constants.otas');
        if (!$source) return null;

        // Try to match by alias
        if ($source) {
            $normalized = strtolower(str_replace([' ', '.', '-'], '', trim($source)));

            foreach ($otas as $ota) {
                if (empty($ota['alias'])) {
                    continue; // Skip Bokun fallback
                }

                foreach ($ota['alias'] as $alias) {
                    $aliasNormalized = strtolower(str_replace([' ', '.', '-'], '', $alias));
                    if (str_contains($normalized, $aliasNormalized)) {
                        return [
                            'prefix' => $ota['prefix'],
                            'title'  => $ota['title'],
                            'logo'   => $ota['logo'] ?? null
                        ];
                    }
                }
            }
        }

        // Fallback → Bokun (no alias)
        foreach ($otas as $ota) {
            if (empty($ota['alias'])) {
                return [
                    'prefix' => $ota['prefix'],
                    'title'  => $ota['title'],
                    'logo'   => $ota['logo'] ?? null
                ];
            }
        }

        return null;
    }

    /**
     * Normalise a reseller name into a consistent kebab-case slug.
     * Used for ota_id, octo_keys.reseller, and the key segment — must stay identical across all three.
     * Examples: "National Park Express CHD" → "national-park-express-chd"
     *           "Get Your Guide"            → "get-your-guide"
     *           "GetYourGuide"              → "getyourguide"
     */
    public static function toResellerSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[\s\-_]+/', '-', $slug);
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        return trim($slug, '-');
    }

    /**
     * Return the logo filename for a given OTA title (e.g. "CHD" → "chd.png").
     * Returns null if no matching OTA is found.
     */
    public static function getOTALogoByTitle(?string $title): ?string
    {
        if (!$title) return null;

        foreach (config('constants.otas', []) as $ota) {
            if (isset($ota['title']) && strcasecmp($ota['title'], $title) === 0) {
                return $ota['logo'] ?? null;
            }
        }

        return null;
    }

    /**
     * Return all OTA titles from constants.otas
     */
    public static function getAllOtaTitles(): array
    {
        $otas = config('constants.otas', []);

        return array_map(fn($ota) => $ota['title'], $otas);
    }

    /**
     * Return OTA titles that represent real, identifiable resale channels — for building
     * user-facing "third party platform" filters/toggles. Excludes the BOKUNTEST/OCTOTEST
     * catch-all entries (prefix containing 'TEST'), which only label bookings made through
     * Bokun/Octo with no matched reseller alias — not distinct platforms a report should
     * let a user filter or enable/disable by name.
     */
    public static function getReportableOtaTitles(): array
    {
        $otas = config('constants.otas', []);

        return array_values(array_map(
            fn($ota) => $ota['title'],
            array_filter($otas, fn($ota) => !str_contains(strtoupper($ota['prefix'] ?? ''), 'TEST'))
        ));
    }

    /**
     * Get OTA title from OTA booking reference code.
     *
     * @param string|null $bookingCode
     * @return string|null
     */
    public static function getTitleFromOtaBookingCode(?string $bookingCode): ?string
    {
        if (!$bookingCode) {
            return null;
        }

        // Extract prefix (everything before first hyphen)
        $prefix = strtoupper(explode('-', $bookingCode)[0] ?? '');

        if (!$prefix) {
            return null;
        }

        // Loop through OTAs and find matching prefix
        $otas = config('constants.otas', []);

        foreach ($otas as $ota) {
            if (isset($ota['prefix']) && ($ota['prefix'] === $prefix)) {
                return $ota['title']; // e.g., "Viator" or "GetYourGuide"
            }
        }

        return null; // No match found
    }

    /**
     * Get OTA title from booking ID.
     *
     * @param int $bookingId
     * @return string|null
     */
    public static function getOTAChannelNameByBookingId($bookingId)
    {
        $tourBooking = DB::table('tour_bookings_master')
            ->where('id', $bookingId)
            ->select(
                'ota_reference_booking',
                'viator_bookingRef',
                'get_your_guide_reference_booking'
            )
            ->first();

        // Determine which code is not empty
        if (!$tourBooking) {
            return null;
        }

        if (!empty($tourBooking->ota_reference_booking)) {
            return self::getTitleFromOtaBookingCode($tourBooking->ota_reference_booking);
        }

        if (!empty($tourBooking->viator_bookingRef)) {
            return self::getTitleFromOtaBookingCode($tourBooking->viator_bookingRef);
        }

        if (!empty($tourBooking->get_your_guide_reference_booking)) {
            return self::getTitleFromOtaBookingCode($tourBooking->get_your_guide_reference_booking);
        }

        return null;
    }

    /**
     * Generate a unique booking confirmation code based on OTA source.
     */
    public static function generateBookingReferenceCode(?string $source): ?array
    {
        $ota = BokunUtils::initPrefix($source);

        if (!$ota) {
            return null; // No matching OTA alias
        }

        $prefix = $ota['prefix'];
        $bookingVia = $ota['title'];
        $requestFrom = strtoupper($ota['title']);

        // Get the latest booking code for this prefix
        $fields = [
            'ota_reference_booking',
            'viator_bookingRef',
            'get_your_guide_reference_booking'
        ];

        $lastCode = null;
        
        foreach ($fields as $field) {
            $code = DB::table('tour_bookings_master')
                ->where($field, 'like', $prefix . '-%')
                ->orderByDesc('id')
                ->value($field);

            // Keep the highest numeric part among all
            if ($code && preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)$/', $code, $m)) {
                $num = (int) $m[1];
                $lastCode = $lastCode === null ? $num : max($lastCode, $num);
            }
        }

        // Extract number part
        $number = $lastCode ? $lastCode + 1 : Constants::OTA_REFERENCE_ID;
        $bookingCode = $prefix . '-' . $number;

        return [
            'ota_prefix' => $prefix,
            'booking_code' => $bookingCode,
            'booking_via' => $bookingVia,
            'request_from' => $requestFrom
        ];
    }
}