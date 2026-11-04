-- =====================================================================================
-- Affiliate rate overcharge — per-booking refund check            (READ-ONLY, MySQL 8)
-- Author: Devat Karetha — Solutions Lead                                    2026-10-19
--
-- Run against ONE tenant database (e.g. `USE tenant_kens;`). SELECT only — nothing is
-- written, updated or deleted.
--
-- What it finds
--   Bookings whose rate came from a custom date range limited to specific tour times
--   ("apply on slots") even though a HIGHER-priority all-times range for the same
--   package + affiliate covered the tour date and priced the same rate group lower.
--   Before the 2026-10-18 fix the time-specific range always won, whatever its priority.
--
-- How the refund is worked out (per booking, current guests only)
--   per-guest charged = rate + tax + permit + round(that * fee% / 100, 2)   (as charged)
--   per-guest correct = same formula using the higher-priority range's rate
--   net paid          = completed card/cash payments − refunds  (custom charges excluded)
--   still owed        = net paid − (guests × per-guest correct)
--   already refunded  = (guests × per-guest difference) − still owed
--
-- Limitations: bookings with a discount, a group rate or a partial permit-fee refund are
-- flagged in `needs_manual_review` instead of being trusted blindly.
-- =====================================================================================

WITH charged AS (
    SELECT
        b.id                                    AS booking_id,
        c.name                                  AS affiliate,
        d.date                                  AS tour_date,
        tst.time_from                           AS tour_time,
        t.tourists                              AS guests,
        t.nat_fees_percentage                   AS fee_pct,
        r.rate_for,
        r.scheduling_year,
        s.id                                    AS used_range_id,
        s.custom_date_name                      AS used_range,
        s.priority                              AS used_priority,
        t.rate + t.additional_charge + t.permit_fee AS unit_charged,
        b.discount, b.discount2_value, t.is_group_rate, t.refund_permitfee_status,
        s.tour_package_id, s.affiliate_id
    FROM tour_bookings_master b
    JOIN customers c                ON c.id = b.customer_id
    JOIN tour_bookings_transact t   ON t.tour_booking_id = b.id AND t.deleted_by = 0
    JOIN tour_package_rates r       ON r.id = t.tour_package_rate_id
    JOIN surcharge_dates s          ON s.id = r.custom_date_id AND s.apply_on_slots = 1
    JOIN tour_slot_times tst        ON tst.id = b.tour_slot_id
    JOIN tour_slot_dates d          ON d.id = tst.tour_slot_id
    WHERE b.status <> 'Cancelled'
      AND t.tourists > 0
),
correct AS (
    -- The highest-priority all-times range covering the tour date, if it outranks the one used.
    SELECT ch.*,
           g.custom_date_name                            AS correct_range,
           g.priority                                    AS correct_priority,
           r2.rate + r2.additional_charge + r2.permit_fee AS unit_correct,
           ROW_NUMBER() OVER (PARTITION BY ch.booking_id ORDER BY g.priority DESC, g.id DESC) AS rn
    FROM charged ch
    JOIN surcharge_dates g     ON g.tour_package_id = ch.tour_package_id
                              AND g.affiliate_id <=> ch.affiliate_id
                              AND g.apply_on_slots = 0
                              AND g.deleted_at IS NULL
                              AND g.date_range_start <= ch.tour_date
                              AND g.date_range_end   >= ch.tour_date
                              AND g.priority > ch.used_priority
    JOIN tour_package_rates r2 ON r2.custom_date_id = g.id
                              AND r2.rate_for = ch.rate_for
                              AND r2.scheduling_year = ch.scheduling_year
                              AND r2.deleted_at IS NULL
                              AND r2.status = 'Active'
                              AND r2.rate > 0
),
priced AS (
    SELECT co.*,
           ROUND(co.unit_charged + ROUND(co.unit_charged * co.fee_pct / 100, 2), 2) AS per_guest_charged,
           ROUND(co.unit_correct + ROUND(co.unit_correct * co.fee_pct / 100, 2), 2) AS per_guest_correct,
           (SELECT COALESCE(SUM(x.amount), 0) FROM transactions x
             WHERE x.tour_booking_id = co.booking_id AND x.status = 'Completed' AND x.charge_type IS NULL) AS paid,
           (SELECT COALESCE(SUM(x.amount), 0) FROM transactions x
             WHERE x.tour_booking_id = co.booking_id AND x.status = 'Refund'    AND x.charge_type IS NULL) AS refunded
    FROM correct co
    WHERE co.rn = 1
      AND co.unit_correct < co.unit_charged
)
SELECT
    booking_id,
    affiliate,
    tour_date,
    tour_time,
    guests,
    used_range,
    correct_range,
    per_guest_charged,
    per_guest_correct,
    CAST(per_guest_charged - per_guest_correct AS DECIMAL(10,2))                                      AS per_guest_difference,
    CAST(paid - refunded AS DECIMAL(10,2))                                                            AS net_paid,
    CAST(guests * (per_guest_charged - per_guest_correct) AS DECIMAL(10,2))                           AS total_overcharge,
    CAST(GREATEST(paid - refunded - guests * per_guest_correct, 0) AS DECIMAL(10,2))                  AS still_to_refund,
    CAST(guests * (per_guest_charged - per_guest_correct)
         - GREATEST(paid - refunded - guests * per_guest_correct, 0) AS DECIMAL(10,2))                AS already_refunded,
    (discount > 0 OR discount2_value > 0 OR is_group_rate = 1
        OR COALESCE(refund_permitfee_status, '') LIKE '%"status":"Y"%')                  AS needs_manual_review
FROM priced
WHERE paid > 0
ORDER BY affiliate, tour_date, booking_id;

-- Totals per affiliate: wrap the query above as a derived table, e.g.
--   SELECT affiliate, COUNT(*) bookings, SUM(guests) guests,
--          SUM(already_refunded) already_refunded, SUM(still_to_refund) still_to_refund
--   FROM ( <query above without ORDER BY> ) q GROUP BY affiliate WITH ROLLUP;
