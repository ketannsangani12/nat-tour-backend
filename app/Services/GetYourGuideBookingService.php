<?php

namespace App\Services;

use App\Models\TourBookingHistory;
use App\Models\BookingTransact;
use App\Repositories\TripAdvisor\TripAdvisorRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetYourGuideBookingService
{
    /**
	 * Summary of getPackageDetails
	 * @param mixed $productCode

     * @return mixed
	 */
	public static function getPackageDetails($productCode)
	{
        $package = collect(DB::select("SELECT 
            `t_o`.`id` as tourOperatorId,
            `tp`.`id` as tourPackageId,
            `tp`.`name` as package_name,
            `tp`.`get_your_guide_product_code`,
            `tp`.`service_commission_percentage`,
            `tp`.`stripe_commission_percentage`,
            `c`.`id` as affiliateId,
            `c`.`name` as affiliate_name,
            `c`.`status`
                FROM `tour_packages` as tp
                INNER JOIN `tour_operators` as t_o ON `t_o`.`id` = `tp`.`tour_operator_id`
                INNER JOIN customers as c ON `c`.`tour_operator_id` = `t_o`.`id`
                WHERE `tp`.`get_your_guide_product_code` = '" .$productCode. "'
                AND `c`.`get_your_guide` = 1
                AND `c`.`is_affiliate` = 'Yes'
                AND `c`.`deleted_by` = 0
                AND `tp`.`status` = 'Active'
                AND `tp`.`deleted_by` = 0
        "))->first();
        
        return $package;
    }

    /**
	 * Summary of getRateGroups
	 * @param mixed $productCode

     * @return mixed
	 */
	public static function getRateGroups($productCode)
	{
        $package = self::getPackageDetails($productCode);

        // Get rate groups for GetYourGuide Affiliate
        $rateGroups = DB::select("
            SELECT
                `tpr`.`id`, `tpr`.`rate_for`,
                `tpr`.`rate`, `tpr`.`additional_tax_percentage`,
                `tpr`.`processing_charge_percentage`, `tpr`.`additional_charge`,
                `tpr`.`permit_fee`
            FROM
                `tour_package_rates` AS `tpr`
            INNER JOIN `tour_packages` AS `tp` ON `tp`.`id` = `tpr`.`tour_package_id`
            INNER JOIN `customers` AS `c` ON `c`.`id` = `tpr`.`affiliate_id`
            WHERE
                `tpr`.`deleted_by` = 0
            AND
                `tpr`.`tour_package_id` = " . $package->tourPackageId . "
            AND
                `c`.`id` = " . $package->affiliateId . "
            AND
                `tpr`.`status` = 'Active'
            ORDER BY
                `tpr`.`orderby` ASC
        ");

        return $rateGroups;
    }

    public static function initTimeSlotsQuery($tourPackageId)
	{
        $initTimeSlotsQuery = "SELECT `tp`.`id` as `package_id`, `tst`.`id`, `tst`.`time_from`, `tst`.`time_to`, `tst`.`time_slot_seats` AS `seats`, `tst`.`bookable_status`, `tst`.`date`, `t_op`.`timezone`,
            IFNULL((SELECT SUM(`tbm2`.`tourists`)
				FROM `tour_bookings_master` AS `tbm2`
				WHERE `tbm2`.`tour_slot_id` = `tst`.`id`
					AND `tbm2`.`status` != 'Cancelled'
                    AND `tbm2`.`tour_package_id` = " . $tourPackageId . "
				GROUP BY `tbm2`.`tour_slot_id`), 0) AS booked_seats,
            IFNULL((SELECT SUM(`tbm`.`tourists`)
                FROM `tour_bookings_master` AS tbm
                JOIN `customers` AS `c` ON `c`.`id` = `tbm`.`customer_id`
                WHERE `tbm`.`tour_slot_id` = `tst`.`id`
                    AND `c`.`is_affiliate` = 'Yes'
                    AND `tbm`.`status` != 'Cancelled'
                    AND `tbm`.`tour_package_id` = " . $tourPackageId . "
                GROUP BY `tbm`.`tour_slot_id`, `tbm`.`tour_package_id`), 0) AS afl_tourists
            FROM `tour_slot_times` AS `tst`
            INNER JOIN `tour_packages` AS tp ON `tp`.`id` = `tst`.`tour_package_id`
            INNER JOIN `tour_operators` AS t_op ON `t_op`.`id` = `tp`.`tour_operator_id`
            INNER JOIN `tour_slot_dates` AS tsd ON `tsd`.`id` = `tst`.`tour_slot_id`";

        return $initTimeSlotsQuery;
    }

    /**
	 * Summary of getTimeSlotsByDate
	 * @param mixed $tourOperatorId
	 * @param mixed $tourPackageId
	 * @param mixed $affiliateId
	 * @param mixed $startDate
	 * @param mixed $dateRange
	 * @param mixed $endDate
	 * @return array
	 */
	public static function getTimeSlotsByDate($tourOperatorId, $tourPackageId, $affiliateId, $startDate, $endDate)
	{
		$timeSlots = [];

        $slotTimes = self::initTimeSlotsQuery($tourPackageId);

		$slotTimes .= " WHERE (`tst`.`date` BETWEEN '" . $startDate . "' AND '" . $endDate . "')";

		$slotTimes .= " AND `tst`.`tour_package_id` = " . $tourPackageId . "
            AND `tp`.`tour_operator_id` = " . $tourOperatorId . "
            AND `tst`.`hide_slot` = 0
            AND `tsd`.`deleted_by` = 0
			AND `tst`.`deleted_by` = 0
			AND `tst`.`bookable_status` != 'Closed'
			ORDER BY `tp`.`id` ASC";

		$slotTimes = DB::select($slotTimes);

        $tripAdvisorRepository = new TripAdvisorRepository();
		$affiliateTimeSlots = $tripAdvisorRepository->affiliateTimeSlots($affiliateId, $tourPackageId);

		foreach ($slotTimes as $key => $slot) {
			$timeSlots[] = $tripAdvisorRepository->limitedSeatsCalculation($slot, $affiliateTimeSlots);
		}

		return $timeSlots;
	}

    /**
	 * Check the overbooking for the specific slot user trying to book
	 * @param mixed $slotTime
	 * @param mixed $tourPackageId
	 * @param mixed $startDate
	 * @param mixed $affiliateId
	 * @return mixed
	 */
	public static function checkSelectedTimeSlotBooking($slotTime, $tourPackageId, $startDate, $affiliateId)
	{
        $timeSlotQuery = self::initTimeSlotsQuery($tourPackageId);

        $timeSlotQuery .= " WHERE `tst`.`date` = '" . $startDate . "'
			AND `tst`.`time_from` = '" . $slotTime . "'
			AND `tst`.`bookable_status` != 'Closed'
            AND `tsd`.`deleted_by` = 0
			AND `tst`.`deleted_by` = 0
			AND `tp`.`id` = " . $tourPackageId . "
        ";

        $timeSlot = collect(DB::select($timeSlotQuery))->first();
        
        $tripAdvisorRepository = new TripAdvisorRepository();
		$affiliateTimeSlots = $tripAdvisorRepository->affiliateTimeSlots($affiliateId, $tourPackageId);

		if ($timeSlot) {
			$timeSlot = $tripAdvisorRepository->limitedSeatsCalculation($timeSlot, $affiliateTimeSlots);
		}

		return $timeSlot;
	}

    /**
     * Execute the service.
     *
     * @return void
     */
    public static function storePricingInHistory($booking, $transactionId, $historyId)
    {
        $tourBookingHistory = TourBookingHistory::with('tourBooking.customer')
            ->where('id', $historyId)
            ->first();

        $tourBookingTransact = BookingTransact::where('tour_booking_id', $booking->id)->where('deleted_by', 0)->get();

        $ticketCostTotal = 0;
        $permitFeesTotal = 0;
        $platformFeesTotal = 0;
        foreach ($tourBookingTransact as $pax) {
            $ticketCost = $pax->tourists * ($pax->rate + $pax->additional_charge + $pax->permit_fee);
            $ticketCostTotal += $ticketCost;

            $permitFee = $pax->tourists * $pax->permit_fee;
            $permitFeesTotal += $permitFee;
        }

        $tourBookingHistory->ticket_cost = $ticketCostTotal;
        $tourBookingHistory->permit_fees = $permitFeesTotal;
        $tourBookingHistory->platform_fees = $platformFeesTotal;
        $tourBookingHistory->transaction_id = $transactionId;
        $tourBookingHistory->payment_type = 'GetYourGuide';
        $tourBookingHistory->payment_method = 'Full Payment';
        $tourBookingHistory->is_processed = 1;
        $tourBookingHistory->save();
    }

    /**
	 * Store refund booking data in the history.
	 *
	 * @param int $bookingId
     * @param int $transactionId
     * @param int $tourists
     * @param float $amount
     * @param float $refundKeepUs
     * @param string $cancelNote
     * @param int $customerId
	 *
	 * @return int
	 */
	public static function storeRefundDataInHistory($bookingId, $transactionId = null, $tourists, $amount = 0, $refundKeepUs = 0, $cancelNote, $customerId, $paymentType = '', $refundType = ''): int 
    {
        $historyId = DB::table('tour_booking_history')
            ->insertGetId([
                'tour_booking_id' => $bookingId,
                'transaction_id' => $transactionId,
                'tourists' => $tourists,
                'operation' => 'Cancelled',
                'details' => json_encode([
                    'amount' => $amount,
                    'refund_keep_us' => $refundKeepUs,
                    'payment_type' => $paymentType,
                    'refund_type' => $refundType,
                    'note' => $cancelNote
                ], JSON_NUMERIC_CHECK),
                'operation_at' => mysqlDT(),
                'operation_by' => $customerId,
                'operation_by_user_type' => 'Customer',
                'updated_at' => Carbon::now(),
                'created_at' => Carbon::now(),
            ]);

        return $historyId;
    }
}
