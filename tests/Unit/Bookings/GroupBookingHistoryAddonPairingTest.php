<?php

namespace Tests\Unit\Bookings;

use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Regression: adding a new pax via Change People, with add-on(s) selected for that same new
 * pax, writes THREE tour_booking_history rows within the same request (near-identical
 * operation_at): one 'Update' (sub_operation 'Tourists Added', renamed 'Update Pax' by
 * groupBookingHistory()), and — when nothing was actually charged (Pay Later) — the add-ons
 * "Payment" row (charge_type='addons', $0, written by changePeopleBooking()'s Pay Later
 * bypass branch). groupBookingHistory()'s fallback pairing (no explicit txnhistoryid stored)
 * picked the FIRST unpaired 'Payment'/'Refund' row within 5 seconds of the Update Pax entry
 * with no charge_type filter at all — so it grabbed the unrelated add-ons row, marked it
 * "paired" (removing it from $grouped, i.e. from ever rendering as its own history entry) and
 * rendered it instead as the Update Pax card's generic "Guest Added — Additional Charge" inline
 * payment, whose template only shows ticket_cost/platform_fees (both $0 for this row) — the
 * add-on's own name/price never surfaces anywhere. The fallback must only ever pair a genuine
 * tour-side row (charge_type null), never an independently-recorded addons/custom_charge/
 * discount entry that merely happened to land within the same few seconds.
 */
class GroupBookingHistoryAddonPairingTest extends TestCase
{
    public function test_addons_payment_row_is_not_paired_into_an_unrelated_update_pax_entry(): void
    {
        $now = '2026-08-19 03:00:57';

        $updatePax = (object) [
            'id' => 5001,
            'operation' => 'Update',
            'operation_at' => $now,
            'package_swipe_history_id' => null,
            'charge_type' => null,
            'details' => json_encode([
                'sub_operation' => 'Tourists Added',
                'newTourists' => 1,
                'addedTourists' => 1,
            ]),
        ];

        // The Pay Later add-ons "Payment" row — same request, same second, $0, charge_type=addons.
        $addonsPayment = (object) [
            'id' => 5002,
            'operation' => 'Payment',
            'operation_at' => $now,
            'package_swipe_history_id' => null,
            'charge_type' => 'addons',
            'ticket_cost' => 0,
            'platform_fees' => 0,
            'payment_type' => null,
            'details' => json_encode([
                'operation' => 'added',
                'addons' => [
                    ['name' => 'Photography Package', 'price' => 15, 'fee' => 0, 'payment_method' => '', 'status' => 'open'],
                ],
                'amount' => 0,
            ]),
        ];

        $history = new Collection([$updatePax, $addonsPayment]);

        ['grouped' => $grouped] = groupBookingHistory($history);

        $groupedIds = collect($grouped)->pluck('id')->all();

        $this->assertContains(
            5002,
            $groupedIds,
            'The add-ons Payment row must still appear as its own independent history entry, not be silently consumed as a pairing target'
        );

        $updatePaxResult = collect($grouped)->firstWhere('id', 5001);
        $this->assertNotNull($updatePaxResult);
        $this->assertFalse(
            isset($updatePaxResult->paired) && $updatePaxResult->paired->id === 5002,
            'The Update Pax entry must not claim the unrelated add-ons Payment row as its paired tour-side payment'
        );
    }

    public function test_a_genuine_tour_side_payment_row_still_pairs_correctly(): void
    {
        $now = '2026-08-19 03:00:57';

        $updatePax = (object) [
            'id' => 5101,
            'operation' => 'Update',
            'operation_at' => $now,
            'package_swipe_history_id' => null,
            'charge_type' => null,
            'details' => json_encode([
                'sub_operation' => 'Tourists Added',
                'newTourists' => 1,
                'addedTourists' => 1,
            ]),
        ];

        // A real tour-side charge for the newly added pax (charge_type null) — this SHOULD pair.
        $tourPayment = (object) [
            'id' => 5102,
            'operation' => 'Payment',
            'operation_at' => $now,
            'package_swipe_history_id' => null,
            'charge_type' => null,
            'ticket_cost' => 50,
            'platform_fees' => 0,
            'payment_type' => 'Card',
            'details' => json_encode(['amount' => 50]),
        ];

        $history = new Collection([$updatePax, $tourPayment]);

        ['grouped' => $grouped] = groupBookingHistory($history);
        $groupedIds = collect($grouped)->pluck('id')->all();

        // The tour payment IS consumed as the pairing target, so it should NOT appear standalone.
        $this->assertNotContains(5102, $groupedIds);

        $updatePaxResult = collect($grouped)->firstWhere('id', 5101);
        $this->assertNotNull($updatePaxResult);
        $this->assertTrue(isset($updatePaxResult->paired));
        $this->assertEquals(5102, $updatePaxResult->paired->id);
    }
}
