<?php

namespace App\Services\Payments;

use DateTimeInterface;

/**
 * Decides whether a booking's card/card-reader/payment-link charge should settle via today's Stripe
 * destination-charge model or the new platform-held model ("Payout Simplification"), per
 * docs/payments/2027-Payout-Simplification-*.
 *
 * This is the single place the 2027-01-01 boundary is expressed in code — every PaymentIntent/Checkout
 * Session-creation call site asks this resolver rather than hardcoding the date itself.
 *
 * Deliberately dependency-light: it takes plain tour-date values in, returns a mode string out, and
 * touches no Stripe API and no tenancy-initialization concerns of its own. It keeps the actual
 * PaymentIntent-creation call sites responsible for locking the *result* onto the transaction row at
 * creation time (`transactions.payment_mode`) and never re-invoking this resolver later against a
 * booking's possibly-since-changed tour date.
 *
 * Per-tenant kill-switch: every call site funnels through resolve()/resolveForSingleDate(), so the
 * kill-switch setting is checked right here, once, via the existing hasSetting() helper
 * (app/helpers.php) — the same mechanism already used for AUTO_PAYOUT/DISPUTE_REVERSAL. Deliberately
 * fails CLOSED: when the setting is missing entirely (an un-migrated tenant) or explicitly disabled,
 * this returns DESTINATION unconditionally, without even inspecting the dates — the safe, pre-project
 * behavior.
 */
class PaymentModeResolver
{
    /** transactions.payment_mode value: today's destination-charge model. */
    public const DESTINATION = 'destination';

    /** transactions.payment_mode value: the charge lands fully on the platform's own Stripe balance. */
    public const PLATFORM_HELD = 'platform_held';

    /** First calendar day (inclusive) whose tour date must settle via PLATFORM_HELD instead of DESTINATION. */
    public const PLATFORM_HELD_STARTS_ON = '2027-01-01';

    /** Tenant-scoped `settings` key gating the whole feature. */
    public const KILL_SWITCH_SETTING_KEY = 'PLATFORM_HELD_PAYOUT_ENABLED';

    /**
     * Resolve the settlement mode for one PaymentIntent/Checkout Session covering one or more booking rows.
     *
     * A cart can bundle multiple tour_bookings_master rows (each with its own tour_slot_time date) under
     * a single charge. The rule is conservative: if *any* row's tour date falls on or after the
     * boundary, the whole charge uses platform-held mode — Stripe cannot split one charge's settlement
     * two ways, and treating a pre-2027 item as platform-held is the safer direction to be wrong in than
     * the reverse.
     *
     * @param iterable<string|DateTimeInterface|null> $tourDates One tour date per booking row being paid
     *        for in this charge (typically `tour_slot_times.date`/`tour_slot_dates.date`). Null/empty
     *        entries are skipped rather than treated as a match, so a booking with no resolvable date
     *        safely falls back to today's destination-charge behavior instead of silently going
     *        platform-held.
     * @return string One of self::DESTINATION or self::PLATFORM_HELD.
     */
    public function resolve(iterable $tourDates): string
    {
        if (!hasSetting(self::KILL_SWITCH_SETTING_KEY)) {
            return self::DESTINATION;
        }

        foreach ($tourDates as $tourDate) {
            if ($this->normalizeDate($tourDate) >= self::PLATFORM_HELD_STARTS_ON) {
                return self::PLATFORM_HELD;
            }
        }

        return self::DESTINATION;
    }

    /**
     * Convenience for the common single-booking call sites, so callers don't have to wrap one date in an array.
     */
    public function resolveForSingleDate(string|DateTimeInterface|null $tourDate): string
    {
        return $this->resolve([$tourDate]);
    }

    /**
     * Normalizes any accepted input down to a plain 'Y-m-d' string (or '' when unresolvable), so the boundary
     * check is always a simple, timezone-independent lexical string comparison. Mirrors the same convention
     * used elsewhere in this codebase for tour_slot_times.date/tour_slot_dates.date (a plain SQL DATE column
     * with no time-of-day component, already the tenant's own local tour day — no timezone conversion needed).
     */
    private function normalizeDate(string|DateTimeInterface|null $tourDate): string
    {
        if ($tourDate === null || $tourDate === '') {
            return '';
        }

        if ($tourDate instanceof DateTimeInterface) {
            return $tourDate->format('Y-m-d');
        }

        // Accepts both a bare date ('2027-01-01') and a datetime string ('2027-01-01 00:00:00') —
        // only the first 10 characters ever matter for this comparison.
        $date = substr($tourDate, 0, 10);

        // A garbage string (e.g. a client-side "Invalid date" placeholder from an unset/unparseable
        // date field) must fail closed to '' like null/empty above, not fall through to the caller's
        // lexical '>= 2027-01-01' comparison — plain string comparison has no concept of "not a real
        // date," so e.g. "Invalid da" (its own first 10 characters) compares as GREATER than
        // '2027-01-01' purely because 'I' > '2' as characters, silently resolving to PLATFORM_HELD
        // for a booking whose real tour date might not even be in the future.
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return '';
        }

        return $date;
    }
}
