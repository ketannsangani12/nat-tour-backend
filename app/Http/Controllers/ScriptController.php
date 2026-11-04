<?php

namespace App\Http\Controllers;

use App\Jobs\Affiliate\FetchAffiliateBookingByCustomer;
use App\Models\BookingTransact;
use App\Models\TourBooking;
use App\Models\TourBookingHistory;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScriptController extends Controller
{
    protected $tourBookingRepository;

	/**
	 * @param TourBookingRepository $tourBookingRepository
	 */
	public function __construct(TourBookingRepository $tourBookingRepository)
	{
		$this->tourBookingRepository = $tourBookingRepository;
	}

    /**
     * Modify the migrations table.
     * 
     * @param int $affiliateId1
     * @param int $affiliateId2
     * @return mixed
     */
    public function swapBookingSeats($affiliateId1, $affiliateId2)
    {
        $data = [
            'id1' => $affiliateId1,
            'id2' => $affiliateId2
        ];

        dispatch(new FetchAffiliateBookingByCustomer($data));

        return "Switching seats began in the backdrop.";
    }

    public function antelopeCanyonAZTimeSlotChange()
    {
        $data = DB::select("SELECT
            tbm.id as booking_id,
            tsd.date as scheduled_date,
            tst.time_from as from_time,
            tbm.tourists,
            IFNULL((SELECT tstt.id
                FROM tour_bookings_master AS tbmm
                    JOIN tour_slot_times as tstt ON tstt.id=tbmm.tour_slot_id
                    JOIN tour_slot_dates as tsdd ON tsdd.id=tstt.tour_slot_id
                    WHERE tbmm.status != 'Cancelled'
                    AND tbmm.tour_package_id = 1
                    AND tstt.time_from = '14:15:00'
                    AND tstt.date=tst.date
                    GROUP BY tbmm.tour_slot_id, tbmm.tour_package_id), 0) as slot_id,
            IFNULL((SELECT tstt.time_from
                FROM tour_bookings_master AS tbmm
                    JOIN tour_slot_times as tstt ON tstt.id=tbmm.tour_slot_id
                    JOIN tour_slot_dates as tsdd ON tsdd.id=tstt.tour_slot_id
                    WHERE tbmm.status != 'Cancelled'
                    AND tbmm.tour_package_id = 1
                    AND tstt.time_from = '14:15:00'
                    AND tstt.date=tst.date
                    GROUP BY tbmm.tour_slot_id, tbmm.tour_package_id), 0) as to_time,
            IFNULL((SELECT tstt.time_slot_seats
                FROM tour_bookings_master AS tbmm
                    JOIN tour_slot_times as tstt ON tstt.id=tbmm.tour_slot_id
                    JOIN tour_slot_dates as tsdd ON tsdd.id=tstt.tour_slot_id
                    WHERE tbmm.status != 'Cancelled'
                    AND tbmm.tour_package_id = 1
                    AND tstt.time_from = '14:15:00'
                    AND tstt.date=tst.date
                    GROUP BY tbmm.tour_slot_id, tbmm.tour_package_id), 0) as total_seats,
            IFNULL((SELECT SUM(tbmm.tourists)
                FROM tour_bookings_master AS tbmm
                    JOIN tour_slot_times as tstt ON tstt.id=tbmm.tour_slot_id
                    JOIN tour_slot_dates as tsdd ON tsdd.id=tstt.tour_slot_id
                    WHERE tbmm.status != 'Cancelled'
                    AND tbmm.tour_package_id = 1
                    AND tstt.time_from = '14:15:00'
                    AND tstt.date=tst.date
                    GROUP BY tbmm.tour_slot_id, tbmm.tour_package_id), 0) as booked_seats,
            (tst.time_slot_seats - IFNULL((SELECT SUM(tbmm.tourists)
                FROM tour_bookings_master AS tbmm
                    JOIN tour_slot_times as tstt ON tstt.id=tbmm.tour_slot_id
                    JOIN tour_slot_dates as tsdd ON tsdd.id=tstt.tour_slot_id
                    WHERE tbmm.status != 'Cancelled'
                    AND tbmm.tour_package_id = 1
                    AND tstt.time_from = '14:15:00'
                    AND tstt.date=tst.date
                    GROUP BY tbmm.tour_slot_id, tbmm.tour_package_id), 0)) as available_seats
            FROM `tour_bookings_master` as tbm
            JOIN tour_slot_times as tst ON tst.id=tbm.tour_slot_id
            JOIN tour_slot_dates as tsd ON tsd.id=tst.tour_slot_id
            WHERE tbm.`email` = 'reservations@antelopecanyon.az'
            AND tbm.`is_reserved_for_affiliate` = 1
            AND tst.time_from = '14:45:00'
            AND tsd.date BETWEEN '2024-02-04' AND '2024-12-31'
            AND tbm.updated_by = 0");

        foreach ($data as $booking) {
            if ($booking->available_seats > 0) {
                // get available seats
                $tourists = min($booking->tourists, $booking->available_seats);

                // For booking transact
                $swapBookingTransact = BookingTransact::where('tour_booking_id', $booking->booking_id)->first();
                $swapBookingTransact->tourists = $tourists;
                // calculation
                $rate = $swapBookingTransact->rate + $swapBookingTransact->additional_charge + $swapBookingTransact->permit_fee;
                $total = $tourists * $rate;
                $swapBookingTransact->total = $total;
                $swapBookingTransact->save();

                // For booking master
                $swapBooking = TourBooking::find($booking->booking_id);
                $swapBooking->tour_slot_id = $booking->slot_id;
                $swapBooking->tourists = $tourists;
                $swapBooking->total = $total;
                $swapBooking->booking_total = $total;
                $swapBooking->save();

                // For booking history
                $swapBookingHistory = TourBookingHistory::where('tour_booking_id', $booking->booking_id)
                    ->where('operation', 'Added')
                    ->first();
                $swapBookingHistory->tourists = $tourists;
                $swapBookingHistory->ticket_cost = 0;
                // modification
                $jsonData = json_decode($swapBookingHistory->details, true);
                $jsonData['newTourists'] = $tourists;
                if (isset($jsonData['added'][0])) {
                    $currentValue = $jsonData['added'][0];
                    $newValue = preg_replace('/(\d+)$/', $tourists, $currentValue);
                    $jsonData['added'][0] = $newValue;
                }
                $swapBookingHistory->details = json_encode($jsonData);
                $swapBookingHistory->save();
            } else {
                $x = TourBooking::find($booking->booking_id);
                $x->status = 'Cancelled';
                $x->updated_by = 1;
                $x->updated_by_user_type = 'Admin';
                $x->save();
            }
        }

        echo "Switching seats began in the backdrop.";
    }

    public function permitFeesRefund(Request $request, $packageId, $year, $bookingId = null)
    {
        $updated = 0;
        $updatedPermitFee = 15;

        // Get tour date parameters from request if provided
        $tourDate = $request->get('tour_date');
        $tourDateFrom = $request->get('tour_date_from');
        $tourDateTo = $request->get('tour_date_to');

        // Validate date parameters
        if (($tourDateFrom && !$tourDateTo) || (!$tourDateFrom && $tourDateTo)) {
            return response()->json([
                'error' => 'Both tour_date_from and tour_date_to are required when using date range filtering.'
            ], 400);
        }

        // Validate date formats
        if ($tourDate && !strtotime($tourDate)) {
            return response()->json([
                'error' => 'Invalid tour_date format. Please use YYYY-MM-DD format.'
            ], 400);
        }

        if ($tourDateFrom && (!strtotime($tourDateFrom) || !strtotime($tourDateTo))) {
            return response()->json([
                'error' => 'Invalid date format for date range. Please use YYYY-MM-DD format for both tour_date_from and tour_date_to.'
            ], 400);
        }

        // Validate date range logic
        if ($tourDateFrom && $tourDateTo && strtotime($tourDateFrom) > strtotime($tourDateTo)) {
            return response()->json([
                'error' => 'tour_date_from cannot be later than tour_date_to.'
            ], 400);
        }

        // Get next year fees
        $commissions = DB::table('tour_packages')
            ->where('id', $packageId)
            ->select(
                'next_year_service_commission_percentage',
                'next_year_stripe_commission_percentage'
            )
            ->first();

        if (!$commissions) return;

        // -------- QUERY --------
        $bookings = DB::table('tour_bookings_master as tbm')
            ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
            ->join('tour_booking_history as tbh', 'tbh.tour_booking_id', '=', 'tbm.id')
            ->join('transactions as t', 't.tour_booking_id', '=', 'tbm.id')
            ->where('tp.id', $packageId)
            ->where('tbm.status', 'Booked')
            ->where('tbh.payment_type', 'Card')
            ->where('tbh.operation', 'Payment')
            ->whereNotIn('tbh.operation', ['Cancelled', 'Refund', 'Switch Tour Package'])
            ->where('t.via', 'Card')
            ->where('t.status', 'Completed')
            ->where('tbm.comments', 'not like', '#%')
            ->when($bookingId, fn($q) => $q->where('tbm.id', $bookingId))
            ->when($tourDate, function ($q) use ($tourDate) {
                // Filter by specific tour date from tour_slot_times table
                $q->whereDate('tst.date', $tourDate);
            })
            ->when($tourDateFrom && $tourDateTo, function ($q) use ($tourDateFrom, $tourDateTo) {
                // Filter by tour date range from tour_slot_times table
                $q->whereBetween('tst.date', [$tourDateFrom, $tourDateTo]);
            })
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('tour_booking_history as tbhh')
                    ->whereColumn('tbhh.tour_booking_id','tbm.id')
                    ->where('tbhh.operation','Payment')
                    ->groupBy('tbhh.tour_booking_id')
                    ->havingRaw('COUNT(*) = 1');
            })
            // Check if booking has any transact records with permit_fee = 20
            ->whereExists(function ($q) use ($year) {
                $q->select(DB::raw(1))
                    ->from('tour_bookings_transact as tbt')
                    ->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
                    ->whereColumn('tbt.tour_booking_id', 'tbm.id')
                    ->where('tbt.permit_fee', 20)
                    ->where('tpr.scheduling_year', $year)
                    ->where('tbt.deleted_by', 0);
            })
            ->orderBy('tbm.id')
            ->select(
                'tbm.id as booking_id',
                'tbm.tourists',
                'tp.tour_operator_id'
            )
            ->limit(2)
            ->get();

        foreach ($bookings as $booking) {
            // Get all transact records for this booking with permit_fee = 20
            $transactRecords = DB::table('tour_bookings_transact as tbt')
                ->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
                ->where('tbt.tour_booking_id', $booking->booking_id)
                ->where('tbt.permit_fee', 20)
                ->where('tpr.scheduling_year', $year)
                ->where('tbt.deleted_by', 0)
                ->select(
                    'tbt.tour_package_rate_id',
                    'tbt.tourists as pax_count',
                    'tpr.rate_for'
                )
                ->get();

            $permitF = [];
            $check = [];

            foreach ($transactRecords as $row) {
                $count = (int) $row->pax_count;

                for ($i = 0; $i < $count; $i++) {
                    $key = "{$row->rate_for}.{$i}";

                    $permitF[$key] = (string) $updatedPermitFee;
                    $check[$key]   = (string) $row->tour_package_rate_id;
                }
            }

            // Build payload once per booking
            $payload = [
                "refund_Type"          => "full_refund",
                "is_cash"              => "",
                "partial_Refund_value" => "",
                "action_type"          => "update",
                "tourits_numbers"      => "",
                "contacts"             => "",
                "pax_addons_ids"       => "",
                "bookingID"            => (string)$booking->booking_id,
                "parent_url"           => url("/admin/tour-operator/booking/{$booking->booking_id}"),
                "tour_operator_id"     => (string)$booking->tour_operator_id,
                "payment_type"         => "Card",
                "total_tourist"        => (string)$booking->tourists,
                "checked_transactions" => "",
                "splitPendingAmount"   => "0",
                "is_calculate_amount"  => "0",
                "permitF"              => $permitF,
                "check"                => $check,
            ];

            try {
                DB::transaction(function () use (
                    $payload,
                    $booking,
                    $commissions
                ) {
                    $request = new Request($payload);

                    // Run core business logic
                    $this->tourBookingRepository->editBooking($request);

                    // Update fee % for all rate groups in this booking
                    DB::table('tour_bookings_transact')
                        ->where('tour_booking_id', $booking->booking_id)
                        ->update([
                            'nat_fees_percentage'    => $commissions->next_year_service_commission_percentage,
                            'stripe_fees_percentage' => $commissions->next_year_stripe_commission_percentage,
                        ]);
                });
                echo "✔ Booking {$booking->booking_id} updated<br>";
                $updated++;
            } catch (Exception $e) {
                echo "❌ Booking {$booking->booking_id} failed: {$e->getMessage()}<br>";
            }
        }

        echo "<br><strong>Total updated: {$updated}</strong>";
    }
}