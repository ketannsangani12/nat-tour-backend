<?php

namespace Tests\Unit\Bookings;

use App\Models\TourBookingAddonTransaction;
use App\Services\Booking\BookingService;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

class BookingServiceAddonPriceTest extends TestCase
{
    /**
     * Regression for the "Add-ons"/"Booking Fees"/"Booking Total" double-counting bug: refund
     * rows in tour_booking_addon_transactions are NOT written with a consistent sign across the
     * codebase — ChangePeopleAddonService writes them negative, but
     * TourBookingAddonRepository's direct addon-edit refund path (ADDONS_FULL_REFUND /
     * ADDONS_TICKET_REFUND) writes them POSITIVE. A prior version of fetchAddonsWithPrice()
     * summed charge+refund rows together in one SUM(), which silently ADDED a positive-convention
     * refund back onto the total instead of netting it out.
     */
    public function test_positive_convention_refund_row_still_nets_correctly(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        $dbId = 555001;

        try {
            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId,
                'tour_booking_addon_id' => $dbId,
                'payment_method' => 'card',
                'status' => 'paid',
                'operation' => 'charge',
                'transaction_id' => 'pi_test_addon_price',
                'field_price' => 0,
                'field_fee' => 0,
                'option_price' => 22.00,
                'option_fee' => 2.16,
                'operation_at' => now(),
                'operation_by' => 0,
                'operation_by_user_type' => 'Admin',
            ]);

            // Positive-convention refund row — matches TourBookingAddonRepository's direct addon-edit
            // refund path, NOT ChangePeopleAddonService's negative convention.
            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId,
                'tour_booking_addon_id' => $dbId,
                'payment_method' => 'card',
                'status' => 'refunded',
                'operation' => 'refund',
                'transaction_id' => 'pi_test_addon_price',
                'field_price' => 0,
                'field_fee' => 0,
                'option_price' => 22.00,
                'option_fee' => 2.16,
                'operation_at' => now(),
                'operation_by' => 0,
                'operation_by_user_type' => 'Admin',
            ]);

            $result = BookingService::fetchAddonsWithPrice($bookingId);

            $this->assertEquals(0.0, $result['addons_final_price'], 'Fully-refunded item must net to $0 price, not double the charge');
            $this->assertEquals(0.0, $result['addons_final_fee'], 'Fully-refunded item must net to $0 fee, not double the charge');
            $this->assertEquals(0.0, $result['addons_payment_total']);
        } finally {
            DB::table('tour_booking_addon_transactions')->where('tour_booking_addon_id', $dbId)->delete();
            $booking->cleanup();
        }
    }

    /** A still-active (unrefunded) add-on must still report its full price/fee. */
    public function test_active_addon_with_no_refund_reports_full_amount(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        $dbId = 555002;

        try {
            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId,
                'tour_booking_addon_id' => $dbId,
                'payment_method' => 'card',
                'status' => 'paid',
                'operation' => 'charge',
                'transaction_id' => 'pi_test_addon_price_2',
                'field_price' => 0,
                'field_fee' => 0,
                'option_price' => 20.00,
                'option_fee' => 1.96,
                'operation_at' => now(),
                'operation_by' => 0,
                'operation_by_user_type' => 'Admin',
            ]);

            $result = BookingService::fetchAddonsWithPrice($bookingId);

            $this->assertEquals(20.00, $result['addons_final_price']);
            $this->assertEquals(1.96, $result['addons_final_fee']);
        } finally {
            DB::table('tour_booking_addon_transactions')->where('tour_booking_addon_id', $dbId)->delete();
            $booking->cleanup();
        }
    }

    /**
     * Regression for booking 64708: "Booking Total" combines tour_bookings_master.booking_total
     * (a lifetime gross figure that is never decremented on refund, by design) with the add-ons
     * total — so the add-ons side must ALSO be a lifetime gross figure, not netted against
     * refunds like addons_final_total/addons_payment_total are. Without this, refunding an
     * add-on silently shrinks "Booking Total" even though booking_total itself never shrinks,
     * producing an inconsistent, understated figure.
     *
     * Gross is sourced from the main `transactions` ledger (charge_type='addons',
     * status='Completed'), which is what every real charge/refund code path writes alongside
     * the tour_booking_addon_transactions rows below (see
     * TourBookingAddonRepository/ChangePeopleAddonService, both of which call
     * TransactionService::storeAddonsPaymentInTransaction()/storeAddonsRefundInTransaction() in
     * lockstep) — so both ledgers are populated here to match production data.
     */
    public function test_gross_total_ignores_refunds_unlike_final_total(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        $dbId = 555003;

        try {
            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $dbId,
                'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
                'transaction_id' => 'pi_test_gross', 'field_price' => 0, 'field_fee' => 0,
                'option_price' => 22.00, 'option_fee' => 2.16,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);
            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $dbId,
                'payment_method' => 'card', 'status' => 'refunded', 'operation' => 'refund',
                'transaction_id' => 'pi_test_gross', 'field_price' => 0, 'field_fee' => 0,
                'option_price' => 22.00, 'option_fee' => 2.16,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);
            DB::table('transactions')->insert([
                'tour_booking_id' => $bookingId, 'amount' => 24.16, 'fee_amount' => 2.16,
                'transaction_number' => 'pi_test_gross', 'via' => 'Card', 'charge_type' => 'addons',
                'status' => 'Completed', 'created_by' => 0, 'created_by_user_type' => 'Admin',
                'created_at' => now(),
            ]);
            DB::table('transactions')->insert([
                'tour_booking_id' => $bookingId, 'amount' => 24.16, 'fee_amount' => 2.16,
                'transaction_number' => 'pi_test_gross', 'via' => 'Card', 'charge_type' => 'addons',
                'status' => 'Refund', 'created_by' => 0, 'created_by_user_type' => 'Admin',
                'created_at' => now(),
            ]);

            $result = BookingService::fetchAddonsWithPrice($bookingId);

            // Fully refunded — final/payment totals net to $0 (existing, unchanged behavior).
            $this->assertEquals(0.0, $result['addons_final_total']);
            $this->assertEquals(0.0, $result['addons_payment_total']);
            // Gross totals ignore the refund entirely.
            $this->assertEquals(22.00, $result['addons_gross_price']);
            $this->assertEquals(2.16, $result['addons_gross_fee']);
            $this->assertEquals(24.16, $result['addons_gross_total']);
        } finally {
            DB::table('tour_booking_addon_transactions')->where('tour_booking_addon_id', $dbId)->delete();
            DB::table('transactions')->where('tour_booking_id', $bookingId)->where('transaction_number', 'pi_test_gross')->delete();
            $booking->cleanup();
        }
    }

    /**
     * Regression for booking 66520: a "swap" (remove item A + add item B in the same refund
     * action) books item B's charge as its own 'paid' row in tour_booking_addon_transactions,
     * but the real payment ledger only ever records the NET effect as a single Refund
     * transaction — no new money was ever actually collected for item B. Gross must not count
     * that never-collected amount, or "Booking Total" overstates by exactly item B's value.
     */
    public function test_gross_total_excludes_swap_addition_never_actually_collected(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        $removedDbId = 555006;
        $addedDbId   = 555007;

        try {
            // Original charge for the item being removed — a real, independently-collected charge.
            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $removedDbId,
                'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
                'transaction_id' => 'pi_test_swap', 'field_price' => 5.00, 'field_fee' => 0,
                'option_price' => 0, 'option_fee' => 0,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);
            DB::table('transactions')->insert([
                'tour_booking_id' => $bookingId, 'amount' => 5.00, 'fee_amount' => 0,
                'transaction_number' => 'pi_test_swap', 'via' => 'Card', 'charge_type' => 'addons',
                'status' => 'Completed', 'created_by' => 0, 'created_by_user_type' => 'Admin',
                'created_at' => now(),
            ]);

            // The swap itself: item-level ledger gets a refund row for the removed item AND a
            // charge row for the added item, but the top-level ledger only gets ONE net Refund
            // transaction (5 - 3 = 2) — matching TourBookingAddonRepository's/
            // ChangePeopleAddonService's actual behavior for a same-operation swap.
            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $removedDbId,
                'payment_method' => 'card', 'status' => 'refunded', 'operation' => 'refund',
                'transaction_id' => 'pi_test_swap', 'field_price' => 5.00, 'field_fee' => 0,
                'option_price' => 0, 'option_fee' => 0,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);
            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $addedDbId,
                'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
                'transaction_id' => 'pi_test_swap', 'field_price' => 3.00, 'field_fee' => 0,
                'option_price' => 0, 'option_fee' => 0,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);
            DB::table('transactions')->insert([
                'tour_booking_id' => $bookingId, 'amount' => 2.00, 'fee_amount' => 0,
                'transaction_number' => 'pi_test_swap', 'via' => 'Card', 'charge_type' => 'addons',
                'status' => 'Refund', 'created_by' => 0, 'created_by_user_type' => 'Admin',
                'created_at' => now(),
            ]);

            $result = BookingService::fetchAddonsWithPrice($bookingId);

            // Current/net state correctly reflects the added item is now active ($3).
            $this->assertEquals(3.00, $result['addons_final_price']);
            // Gross must equal only what was ever really collected via a Completed transaction
            // ($5 original charge) — NOT $8 (5 removed + 3 swapped-in), which double-counts the
            // swap addition as if it were fresh, independently-collected money.
            $this->assertEquals(5.00, $result['addons_gross_price']);
            $this->assertEquals(5.00, $result['addons_gross_total']);
        } finally {
            DB::table('tour_booking_addon_transactions')->whereIn('tour_booking_addon_id', [$removedDbId, $addedDbId])->delete();
            DB::table('transactions')->where('tour_booking_id', $bookingId)->where('transaction_number', 'pi_test_swap')->delete();
            $booking->cleanup();
        }
    }

    /**
     * Regression for booking 65091: ChangePeopleAddonService::removePaxAddons() soft-deletes the
     * ORIGINAL 'paid' transaction row the moment a pax's add-on field is removed, then
     * settleAddonTransactions() separately creates a fresh 'refunded' row alongside it — so a
     * genuinely-active, still-charged add-on elsewhere on the SAME booking must not be
     * under-counted just because a DIFFERENT (already-refunded) item's original charge row
     * happens to carry a deleted_at timestamp.
     */
    public function test_soft_deleted_charge_row_still_nets_correctly_against_its_refund(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        $refundedDbId = 555004;
        $activeDbId   = 555005;

        try {
            // Refunded item: original charge row is soft-deleted (matches removePaxAddons()),
            // and a fresh refund row records the actual refund.
            $chargeRow = TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $refundedDbId,
                'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
                'transaction_id' => 'pi_test_soft_delete', 'field_price' => 0, 'field_fee' => 0,
                'option_price' => 22.00, 'option_fee' => 2.16,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);
            $chargeRow->delete(); // soft-delete, same as removePaxAddons()
            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $refundedDbId,
                'payment_method' => 'card', 'status' => 'refunded', 'operation' => 'refund',
                'transaction_id' => 'pi_test_soft_delete', 'field_price' => 0, 'field_fee' => 0,
                'option_price' => -22.00, 'option_fee' => -2.16,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);

            // A genuinely different, still-active add-on elsewhere on the same booking.
            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $activeDbId,
                'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
                'transaction_id' => 'pi_test_soft_delete_2', 'field_price' => 0, 'field_fee' => 0,
                'option_price' => 20.00, 'option_fee' => 1.96,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);

            $result = BookingService::fetchAddonsWithPrice($bookingId);

            $this->assertEquals(20.00, $result['addons_final_price'], 'The refunded item must net to $0 (not under-count the active item by excluding its soft-deleted charge)');
            $this->assertEquals(1.96, $result['addons_final_fee']);
        } finally {
            DB::table('tour_booking_addon_transactions')->whereIn('tour_booking_addon_id', [$refundedDbId, $activeDbId])->delete();
            $booking->cleanup();
        }
    }
}
