<?php

namespace App\Repositories\Coordinator;

use App\Repositories\Repository;
use Illuminate\Http\Request;
use App\Exceptions\Core\GeneralCoreException;
use Exception;
use App\Models\TourPackage;
use App\Http\Controllers\API\V1\ApiController;
use App\Models\TourPackageImage;
use App\Models\TourOperator;
use App\Models\TourBookingHistory;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\TourPackageRate;
use App\Models\Customer;
use App\Models\TourBooking;
use App\Models\BookingTransact;
use App\Models\Transaction;
use App\Models\Admin;
use App\Models\Staff;
use Illuminate\Support\Facades\Log;
use App\Services\Booking\BookingService;
use App\Services\Booking\PrintService;
use Illuminate\Support\Facades\Auth;

class CoordinatorRepository extends Repository
{
	public $tourPackage = TourPackage::class;

	public $apiController = ApiController::class;

	public $tourPackageImage = TourPackageImage::class;

	public $tourOperator = TourOperator::class;

	public $tourPackageRate = TourPackageRate::class;

	public $customer = Customer::class;

	public $tourBooking = TourBooking::class;

	public $bookingTransact = BookingTransact::class;

	public $transaction = Transaction::class;

	public $admin = Admin::class;

	public $staff = Staff::class;

	public $tourBookingHistory = TourBookingHistory::class;


	/**
	 * Provide the add Booking details with tour guide list
	 *
	 * @return array
	 */

	 public function addBooking($request)
    {
    	try {
			$bookingM = DB::table('tour_bookings_master AS tbm')
				->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
				->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
				->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
				->where('tbm.id', $request->booking_id)
				->first([
					'tbm.id', 'tbm.tourists',
					'tp.name AS tour_package',
					'tsd.date', 'tst.time_from','tst.id as time_slot_id', 'tbm.tour_guide_id', 'tp.id as tour_package_id'
				]);

			if (!$bookingM) {
				throw new Exception("Tour booking is not found.");
			}

			if(!empty($bookingM)){
				if($bookingM->tour_guide_id == null){
					$bookingM->tour_guide_assigned = "No";
					$bookingM->tour_guide_name = '';
				}else{
					$bookingM->tour_guide_assigned = "Yes";
					$tourGuideDetails = $this->staff::where('id', $bookingM->tour_guide_id)->first();
					$bookingM->tour_guide_name = $tourGuideDetails->first_name;
				}
			}

			$bookingT = DB::table('tour_bookings_transact AS tbt')
				->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
				->where('tbt.tour_booking_id', $request->booking_id)
				->orderBy('tpr.orderby', 'asc')
				->get([
					'tbt.id', 'tbt.tourists',
					'tpr.rate_for'
				]);

			if (!$bookingT) {
				abort(500, "Unable to get booking's information.");
			}
			$tour_d_t = $bookingM->date . ' ' . $bookingM->time_from;
			$bookingM->tourDateTime = date('l, F j, Y @ g:i a', strtotime($tour_d_t));

			$tour_guide = DB::table('tour_operator_staff as tos')
						->join('tour_operator_staff_roles as tosr', 'tosr.tour_operator_staff_id', '=', 'tos.id')
						->where('tosr.role_id', 7)
						->where('tosr.deleted_by', 0)
						// ->where('tos.tour_operator_id', $request->tour_operator_id)
						->where('tos.status', 'Active')->get(['tos.first_name','tos.email', 'tos.id as tour_guide_id']);

			if($bookingM->tour_guide_id != null){
    			$title = "This booking is already assigned";
    		}else{
    			$title = "Successfully assigned to the list";
    		}

			$vars = [
				'status' => "Success",
				'title' => $title,
				'bookingM' => $bookingM,
				'bookingT' => $bookingT,
				'tour_guide' => $tour_guide,
			];

			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
            throw new GeneralCoreException($exception);
		}
    }

	/**
	 * Tour guide's assigned booking list
	 *
	 * @param object $request
	 * @return mixed
	 *
	 *	@throws Exception
	 */
    public function bookingAssigned($request)
    {
    	try {
	    		$userType = userType();
	    		$request->check_in_status = 2;
	    		DB::beginTransaction();

    			$created_by_with_name = BookingService::createdUserInfo();
    			$tourBooking = $this->tourBooking::where('id', $request->booking_id)->first();
    
    			if ($tourBooking->tour_guide_id != null) {
    				throw new Exception('This booking is already assigned');
    			}

    			$tourBooking->tour_guide_id = $request->tour_guide_id;
    			$tourBooking->coordinator_id = Auth::id();
    			$tourBooking->save();

    			DB::table('tour_booking_history')->insert([
					'tour_booking_id' => $request->booking_id,
					'operation' => 'Tour Guide',
					'details' => json_encode([
							'tour_guide_id' => $request->tour_guide_id,
							'booking_id' => $request->booking_id,
							'old_tour_guide_id' => '',
							'created_by_with_name' => "",
						], JSON_NUMERIC_CHECK),
					'operation_at' => mysqlDT(),
					'operation_by' => Auth::id(),
					'operation_by_user_type' => $userType,
					'updated_at' => Carbon::now(),
					'created_at' => Carbon::now(),
				]);

				$query = DB::table('tour_bookings_transact AS tbt')
					->join('tour_bookings_master AS tbm', 'tbm.id', '=', 'tbt.tour_booking_id')
					->where('tbt.tour_booking_id', $request->booking_id)
					->where('tbt.deleted_by', 0);

				if ($request->has('tpr_id')) // set status for a particular age/rate groups
					$query = $query->where('tbt.tour_package_rate_id', $request->tpr_id);

				$bookingT = $query->get(['tbm.status', 'tbt.id', 'tbt.tourists', 'tbt.check_in_status','tbt.tour_package_rate_id']);
				$updated = 0;
				$mysqlDT = mysqlDT();
				for ($a = 0, $ac = count($bookingT); $a < $ac; $a++) {
					if ($bookingT[$a]->check_in_status == '')
						$statusInfo = [];
					else
						$statusInfo = json_decode($bookingT[$a]->check_in_status);

					for ($b = 0; $b < $bookingT[$a]->tourists; $b++) {
						if (!isset($statusInfo[$b])) {
							$statusInfo[$b] = new \stdClass;
							$statusInfo[$b]->status = 'N';
						}

						if ($request->has('index')) {
							if ($request->index == $b) {
								$statusInfo[$b]->status = $request->check_in_status;
								$statusInfo[$b]->dateTime = $mysqlDT;
								break;
							}
						} else {
							$statusInfo[$b]->status = $request->check_in_status;
							$statusInfo[$b]->dateTime = $mysqlDT;
						}
					}

					if ($bookingT[$a]->tourists < count($statusInfo)) {
						$updated_arr_count = count($statusInfo) - $bookingT[$a]->tourists;
						$statusInfo = array_slice($statusInfo, 0, -$updated_arr_count);
					}

					$updated = $updated + DB::table('tour_bookings_transact')
						->where('id', $bookingT[$a]->id)
						->update(['check_in_status' => json_encode($statusInfo)]);
				}

				if ($updated) {
					$checkInStatuses = ['N' => 'Not Checked In', '1' => '1st Check In', '2' => '2nd Check In', 'A' => 'Absent'];

					if ($request->has('tpr_id')) {
						$rateGrp = DB::table('tour_package_rates AS tpr')
							->join('tour_bookings_transact AS tst', 'tst.tour_package_rate_id', '=', 'tpr.id')
							->where('tst.tour_booking_id', $request->booking_id)
							->where('tst.deleted_by', 0)
							->where('tpr.id', $request->tpr_id)
							->first(['tpr.rate_for', 'tst.tourists']);

						if (!$rateGrp) {
							DB::rollBack();
							throw new Exception('Unable to set check-in status.');
						}

						$whoCheckedIn = "{$rateGrp->rate_for} #" . ($request->index + 1);
					} else
						$whoCheckedIn = 'All';

					$dateTime = mysqlDT();

					DB::table('tour_booking_history')
						->insert([
							'tour_booking_id' => $request->booking_id,
							'operation' => 'Check-in',
							'details' => json_encode([
								'status' => $checkInStatuses[$request->check_in_status],
								'who' => $whoCheckedIn,
								'created_by_with_name' => $created_by_with_name
							]),
							'operation_at' => $dateTime,
							'operation_by' => Auth::id(),
							'operation_by_user_type' => $userType,
							'updated_at' => Carbon::now(),
							'created_at' => Carbon::now(),
						]);
					DB::commit();

					return [
						'status' => "Success",
						'title' => "Successfully assigned to the tour guide",
						'tour_guide_id' => $request->tour_guide_id,
						'tour_booking_id' => $request->booking_id,
					];
				} else {
					throw new Exception('Check-in status could not be updated. The selected status may already be applied to this booking.');
				}
    	} catch (Exception $exception) {
			Log::error($exception);
            throw new GeneralCoreException($exception);
		}
    }

    /**
     *   Tour guide's assigned booking list
     * @return mixed
     *
     * @throws Exception
     */
    public function tourGuideAssingedBookingList($request)
    {
    	try {

    		$qry = DB::table('tour_bookings_master AS tbm')
	                ->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
	                ->join('tour_operators as to', 'to.id', '=', 'tp.tour_operator_id')
	                ->join('tour_bookings_transact AS tbt', 'tbt.tour_booking_id', 'tbm.id')
	                ->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
	                ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
	                ->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
	                ->where('tbm.tour_guide_id', $request->tour_guide_id)
	                ->where('tst.id', $request->time_slot_id)
	                ->where('tbm.tour_package_id', '=', $request->tour_package_id)
				    ->where('tbt.deleted_by', 0)
	                ->select(
	                    'tbm.id as bookingid',
	                    'tbm.tourists as overall_tourists',
	                    DB::raw('DATE_FORMAT(tbm.created_at, "%Y-%m-%d") as created_at'),
	                    DB::raw('DATE_FORMAT(tsd.date, "%Y-%m-%d") as bookingdate'),
	                    'tbm.name as name',
	                    'tbm.email as email',
	                    'tbm.phone_number as phone_number',
	                    'tbt.tourists as tourists',
	                    'tpr.rate_for',
	                    // 't.status as payment_status',
	                )
	                ->orderBy('tsd.date', 'DESC');

            $result = $qry->get();
            $packageRates = $this->tourPackageRates($result,0);

            $arraySetResult = [];
            $booked_seat = 0;
            foreach ($packageRates as $key => $value) {
            	$arraySetResult[$key]['bookingid'] = $value['booking_details']['bookingid'];
            	$arraySetResult[$key]['tourists']= $value['booking_details']['overall_tourists'];
            	$arraySetResult[$key]['date'] = $value['booking_details']['bookingdate'];
            	$arraySetResult[$key]['name'] = $value['booking_details']['name'];
            	$arraySetResult[$key]['email'] = $value['booking_details']['email'];
            	$arraySetResult[$key]['phone_number'] = $value['booking_details']['phone_number'];
            	$arraySetResult[$key]['rate'] = $value['rate'];
            	$booked_seat += $value['booking_details']['overall_tourists'];
            }

            $arrayFinalResult['details'] = [];
            foreach ($arraySetResult as $key => $value) {
            	$arrayFinalResult['details'][] = $value;
            }

            // Staff total, booked and available seats
        	$staff = $this->staff::where('id', $request->tour_guide_id)->first();
        	$arrayFinalResult['seats'] = [	'total_seat' => $staff->total_seat,
	    									'booked' => $booked_seat,
	    									'available' => $staff->total_seat - $booked_seat
            							 ];

			return $arrayFinalResult;

    	} catch (Exception $exception) {
    		Log::error($exception);
            throw new GeneralCoreException($exception);
    	}
    }

    /**
     *   Tour guide's tourPackageRates
     * @return mixed
     *
     * @throws Exception
     */
    public function tourPackageRates($rateGroups)
    {
    	try {
    		$initialArray = [];
        	foreach ($rateGroups as $rateGroup) {
	            $bookingId = $rateGroup->bookingid;
	            if (!isset($initialArray[$bookingId])) {
	                $initialArray[$bookingId]['booking_details'] = (array)$rateGroup;
	            }

	            if ($rateGroup->tourists > 0 && !in_array($rateGroup->tourists . ' ' . $rateGroup->rate_for, $initialArray[$bookingId]['rate'] ?? [])) {
	                $initialArray[$bookingId]['rate'][] = $rateGroup->tourists . ' ' . $rateGroup->rate_for;
	            }
        	}

        return $initialArray;

    	} catch (Exception $exception) {
    		Log::error($exception);
            throw new GeneralCoreException($exception);
    	}
    }

     /**
     *   Tour guide's move bookings to an onther tour guide
     * @return mixed
     *
     * @throws Exception
     */
    public function moveBooking($request)
    {
    	try {
    		$userType = userType();
			$created_by_with_name = BookingService::createdUserInfo();

    		DB::beginTransaction();
    		$bookingIdArray = explode(',',$request->booking_id);

    		foreach ($bookingIdArray as $id) {
    			$tourBooking = $this->tourBooking::where('id', $id)->first();

				if (!$tourBooking) {
					throw new Exception("Tour booking is not found for booking id " . $id);
				}

				$tourBooking->tour_guide_id = $request->tour_guide_id;

				DB::table('tour_booking_history')->insert([
					'tour_booking_id' => $id,
					'operation' => 'Tour Guide',
					'details' => json_encode([
						'tour_guide_id' => $request->tour_guide_id,
						'booking_id' => $id,
						'old_tour_guide_id' => $tourBooking->tour_guide_id,
						'created_by_with_name' => $created_by_with_name,
					], JSON_NUMERIC_CHECK),
					'operation_at' => mysqlDT(),
					'operation_by' => Auth::id(),
					'operation_by_user_type' => $userType,
					'updated_at' => Carbon::now(),
					'created_at' => Carbon::now(),
				]);

				$tourBooking->save();
    		}
    		DB::commit();

    		return [
				'status' => "Success",
				'title' => "Successfully assigned to the list",
				'tour_guide_id' => $request->tour_guide_id,
				'booking_id' => $request->booking_id,
			];
    	} catch (Exception $exception) {
    		Log::error($exception);
            throw new GeneralCoreException($exception);
    	}
    }

     /**
     * Tour guide's assigned booking delete
     * @return mixed
     *
     * @throws Exception
     */
    public function assignedBookingDelete($request)
    {
    	try {
    		DB::beginTransaction();
			$tourBooking = $this->tourBooking::where('id', $request->booking_id)->where('tour_guide_id', $request->tour_guide_id)->first();
			if($tourBooking){
				$tourBooking->tour_guide_id = null;
				$tourBooking->save();
				DB::commit();
			}else{
				throw new Exception('Booking id or tour guide id is wrong.');
			}
    		return [
					'status' => "Success",
					'title' => "Assigned booking is deleted Successfully",
					'booking_id' => $request->booking_id,
					];

    	} catch (Exception $exception) {
    		Log::error($exception);
            throw new GeneralCoreException($exception);
    	}
    }

    /**
	 * View and print the invoice of booking
	 *
	 * @param int $id
	 */
	public function bookingInvoiceForTourGuide(int $id)
	{
		try {
			$bookingM = DB::table('tour_bookings_master AS tbm')
				->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
				->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
				->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
				->leftJoin('tour_promotions AS promo', 'promo.id', '=', 'tbm.tour_promotion_id')
				->where('tbm.id', $id)
				->first([
					'tbm.id', 'tbm.tourists', 'tbm.status', 'tbm.total', 'tbm.discount', 'tbm.discount2_value', 'tbm.discount2_percentage', 'tbm.service_commission', 'tbm.created_at',
					'tp.name AS tour_package', 't_o.name AS tour_operator',
					't_o.email AS tour_op_email', 't_o.phone_number AS tour_op_phone', 't_o.logo_print','t_o.tenant_id',
					'tsd.date', 'tst.time_from', 'tst.time_to',
					'tbm.name AS customer', 'tbm.email AS cust_email', 'tbm.phone_number AS cust_phone',
					'promo.discount_value', 'promo.discount_value_type','tbm.booking_total'
				]);

			if (!$bookingM) {
				throw new Exception("Tour booking not found.");
			}
			$bookingM->date = formatDateTime($bookingM->date . ' ' . $bookingM->time_from);
			$bookingT = DB::table('tour_bookings_transact AS tbt')
				->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
				->where('tbt.tour_booking_id', $id)
				->where('tbt.deleted_by', 0)
				->orderBy('tpr.orderby', 'asc')
				->get([
					'tbt.id', 'tbt.tourists', 'tbt.processing_charge_percentage', 'tbt.additional_tax_percentage',
					'tbt.permit_fee', 'tbt.additional_charge', 'tbt.state_tax', 'tbt.rate', 'tbt.total',
					'tpr.rate_for'
				]);

			if (!$bookingT) {
				abort(500, "Unable to get booking's information.");
			}

			$history = DB::table('tour_booking_history')
				->orderByDesc('operation_at')
				->where('tour_booking_id', $id)
				->get();

			$transaction = $this->transaction::where('tour_booking_id', $id)->first();
			$trans = $this->transaction::where('tour_booking_id', $id)->where('status', 'Completed')->orderBy('id', 'DESC')->first();
			$HistoryPaymentTime = $this->tourBookingHistory::where('tour_booking_id', $id)->where('operation', 'Payment')->first();

			if (!empty($HistoryPaymentTime)) {
				$TourBookingHistory = $this->tourBookingHistory::where('tour_booking_id', $id)->where('operation_at', '>', $HistoryPaymentTime->operation_at)
					->where(function ($query) {
						return $query->where('operation', '=', 'Refund')
							->orWhere('operation', '=', 'Cancelled');
					})
					->get();
			}

			$paymentOnly = $this->tourBookingHistory::where('tour_booking_id', $id)->where('operation', 'Payment')->get();
			$paymentTrans = $this->transaction::where('tour_booking_id', $id)->where('status', 'Completed')->get(['id','tour_booking_id','amount','fee_amount','transaction_number','application_fee_id','via','payment_type','status']);

			$paymentTransCount = count($paymentTrans);
            $paymentOnlyCheck = 0;
            foreach ($paymentOnly as $value) {
               $details = json_decode($value->details);
               $paymentOnlyCheck += $details->amount;
            }

            $transP = $this->transaction::where('tour_booking_id', $id)->where('status', 'Completed')->orderBy('id', 'ASC')->first();

            $promoDiscount = 0;

		if ($bookingM->discount2_percentage > 0) {
			$promoDiscount = str_replace('.00', '', $bookingM->discount2_value) . '%';
		}elseif ($bookingM->discount2_value > 0) {
			$promoDiscount = currencyFormation(false, true) . $bookingM->discount2_value;
		}

		$totalBeforeServiceCommission = $bookingM->total - $bookingM->service_commission;
		$serviceCommissionPercentage = 0;
		if ($totalBeforeServiceCommission != 0) {
			$serviceCommissionPercentage = round($bookingM->service_commission * 100 / $totalBeforeServiceCommission, 2);
		}

		$amount = 0;
		$refund_keep_us = 0;
		$deletedAgeGroup = [];

		if (!empty($trans)) {
			$paidF = $paymentOnlyCheck;
		}else {
			$paidF = 0;
		}

		if (!empty($TourBookingHistory)) {
			foreach ($TourBookingHistory as $BookingHistory) {
				$details = json_decode($BookingHistory->details);

				if (isset($details->refund_type) && !in_array(strtolower($details->refund_type), ["don't refund", "partial refund"])) {
					$amount += $details->amount;

					if (isset($details->deleted)) {
						$deletedAgeGroup[] = $details->deleted;
					}
				}

				$details->refund_keep_us = $details->refund_keep_us ?? 0;
				if (isset($details->refund_type) && strtolower($details->refund_type) !== "partial refund") {
					$refund_keep_us += $details->refund_keep_us;
				}

				$checkAmount = json_decode($HistoryPaymentTime->details);
				$paidF = $paymentOnlyCheck - $amount;

				if ($checkAmount->amount == $amount) {
					$paidF = 0;
				}
			}
		}

		$resultArray = [];
		if (count($deletedAgeGroup)) {
			$personCount = [];
			foreach ($deletedAgeGroup as $entry) {
				// Get the prefix text "Adults (Ages 13 & Up) person"
				$prefix = substr($entry, 0, strpos($entry, "person"));

				// Extract the person number from the entry and convert to integer
				$personNumber = intval(substr($entry, strrpos($entry, " ") + 1));

				// Check if the entry with the same prefix exists in the person count array
				if (!isset($personCount[$prefix])) {
					// Create a new entry in the person count array
					$personCount[$prefix] = 0;
				}

				// Increment the sum of person numbers for the existing entry
				$personCount[$prefix] += $personNumber;
			}

			// Convert the person count array to the desired format
			foreach ($personCount as $prefix => $count) {
				$resultArray[] = $prefix . " " . $count;
			}
		}

		if ($bookingM->status == "Cancelled") {
			if (count($resultArray) > 0) {
				foreach ($resultArray as $key => $ar) {
					$rate = substr($ar, 0, strripos($ar, " "));
					$rateNumber = intval(substr($ar, strrpos($ar, " ") + 1));
					$ratespacetrim = rtrim($rate);

					foreach ($bookingT as $bt) {
						if ($ratespacetrim == $bt->rate_for) {
						   $totaltourists =	$rateNumber + $bt->tourists;
						   unset($resultArray[$key]);
						}

						if (!empty($totaltourists)) {
							$resultArray[] = $bt->rate_for . " " . $totaltourists;
						} else {
							$resultArray[] = $bt->rate_for . " " . $bt->tourists;
						}
					}
				}
			} else {
				foreach ($bookingT as $bt){
					$resultArray[] = $bt->rate_for . " " . $bt->tourists;
				}
			}
		}

		$ageGroup =  $resultArray;

		$partialAmount = 0;
			if ($paidF >= 1 && isset($refundType) && $refundType === 'partial_refund') {
				if ($bookingM->service_commission > 0) {
					$stripeFeesPercentage = $paidF * $bookingT[0]->stripe_fees_percentage / 100;
					$partialAmount = round(($paidF - $stripeFeesPercentage) / 2, 2);
				} else {
					$partialAmount = $paidF / 2;
				}
			}
			if((!empty($trans) && $transP->payment_type == 1) || (!empty($trans) && ($transP->payment_type == 0 || $transP->payment_type=='') && $paymentTransCount>1)){
				$bookingM->booking_total = $bookingM->booking_total;
			}elseif(!empty($checkAmount)){
				$bookingM->booking_total = $checkAmount->amount;
			}else{
				$bookingM->booking_total = $bookingM->booking_total;
			}

			$userType = userType();

			$bookingMS = BookingService::information($userType, $id);

			$printInfo = PrintService::data($bookingMS);

			$sub_total = $bookingM->total - $bookingM->service_commission;
			$vars = [
				'bookingM' => $bookingM,
				'bookingT' => $bookingT,
				'non_refundable_fees' => $refund_keep_us,
				'refund_amount' => $amount,
				'sub_total' => $sub_total,
				'refunded_pax' => $ageGroup,
				'payment_total' => $paidF,
				'printInfo' => $printInfo,
			];

			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
            throw new GeneralCoreException($exception);
		}
	}

	/**
     * get tour packages for coordinator
     * @return mixed
     *
     * @throws Exception
     */

    public function getTourPackagesForCoordinator($tourOperatorId)
    {
         try {
           		$tourPackages = $this->tourPackage::where('status', 'Active')
				->where('tour_operator_id', $tourOperatorId)
				->orderBy('order_by')
				->get(['id', 'name']);

				return [
					'status' => "Success",
					'title' => "Tour packages fetched successfully",
					'tourPackages' => $tourPackages,
					];
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

	/**
     * get DateTime
     * @return mixed
     *
     * @throws Exception
     */

    public function getDateTime($tourOperatorId,$tourPackageId,$date)
    {
         try {
         	// $date = date('Y-m-d');
         	$today = Carbon::now();
            $currentTime = $today->format('H:i:s');

	        $fields = [
            	'tst.id', 'tsd.tour_package_id', 'tst.time_from', 'tst.time_to', 'tst.time_slot_seats as seats', 'tst.bookable_status',
        	];

	        $slotTimes = DB::table('tour_slot_times AS tst')
	            ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
	            ->whereDate('tsd.date', $date)
	            ->where('tsd.tour_package_id', $tourPackageId)
	            ->where('tsd.status', 'Active')
	            ->where('tst.deleted_by', '0')
	            ->when($currentTime !== 0, function ($query) use ($currentTime) {
	                $query->whereTime('tst.time_from', '>=', $currentTime);
	            })
	            ->orderBy('tst.time_from');

	        $slotTimes = $slotTimes->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
	            ->where('tp.tour_operator_id', $tourOperatorId);

	        $slotTimes = $slotTimes->get($fields);
	        $totalTourists = 0;
        $slots = array();
        $i = 0;
        foreach ($slotTimes as $responseVal) {
            $slotDateTime = $date . ' ' . $responseVal->time_from;
            $slots[$i]['Id']              = $responseVal->id;
            $slots[$i]['Time']            = date('g:i A', strtotime($slotDateTime));
            $slots[$i]['date']            = $date;
            $slots[$i]['bookable_status'] = $responseVal->bookable_status;
            $i++;
        }

          return $slots;
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * get Tour Guide
     * @return mixed
     *
     * @throws Exception
     */

    public function getTourGuide($tourOperatorId, $tourPackageId, $timeSlotId)
    {
        try {

        	// $fromDate = date('Y-m-d');
         	// $toDate = date('Y-m-d');

			$tour_guide = DB::table('tour_operator_staff as tos')
				->join('tour_operator_staff_roles as tosr', 'tosr.tour_operator_staff_id', '=', 'tos.id')
				->where('tosr.role_id', 7)
				->where('tosr.deleted_by', 0)
				->where('tos.tour_operator_id', $tourOperatorId)
				->where('tos.status', 'Active')
				->get(['tos.first_name', 'tos.email', 'tos.id as tour_guide_id', 'tos.total_seat'
			,'tos.status']);

			foreach ($tour_guide as $key => $value) {
					$bookingM = DB::table('tour_bookings_master AS tbm')
						->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
						->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
						->join('tour_bookings_transact AS tbt', 'tbt.tour_booking_id', 'tbm.id')
                		->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
						->where('tst.id', $timeSlotId)
		                ->where('tbm.tour_guide_id', $value->tour_guide_id)
		                ->where('tbm.tour_package_id', '=', $tourPackageId)
		                ->get([
						'tbm.id', 'tbm.tourists'
					]);
					$tour_guide[$key]->booked = 0;
					$tour_guide[$key]->available = $value->total_seat;


					$qry = DB::table('tour_bookings_master AS tbm')
			                ->join('tour_bookings_transact AS tbt', 'tbt.tour_booking_id', 'tbm.id')
			                ->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
			                ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
			                ->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
			                // ->leftJoin('transactions as t', 't.tour_booking_id', '=', 'tbm.id')
			                ->where('tbm.tour_guide_id', $value->tour_guide_id)
			                // ->whereBetween('tsd.date', [$fromDate, $toDate])
			                ->where('tst.id', $timeSlotId)
			                ->where('tbm.tour_package_id', '=', $tourPackageId)
						    ->where('tbt.deleted_by', 0)
			                ->select(
			                    'tbm.id as bookingid',
			                    'tbm.tourists as overall_tourists',
				                    DB::raw('DATE_FORMAT(tbm.created_at, "%Y-%m-%d") as created_at'),
				                    DB::raw('DATE_FORMAT(tsd.date, "%Y-%m-%d") as bookingdate'),
			                    'tbm.name as name',
			                    'tbm.email as email',
			                    'tbm.phone_number as phone_number',
			                    'tbt.tourists as tourists',
			                    'tpr.rate_for',
			                )
			                ->orderBy('tsd.date', 'DESC');

		            $result = $qry->get();
		            $packageRates = $this->tourPackageRates($result,0);
		            // Tour guide with assinged booking details -- Required details are added
		            $arraySetResult = [];
		            foreach ($packageRates as $k => $pack) {
		            	$arraySetResult[$k]['bookingid'] = $pack['booking_details']['bookingid'];
		            	$arraySetResult[$k]['tourists']= $pack['booking_details']['overall_tourists'];
		            	$arraySetResult[$k]['date'] = $pack['booking_details']['bookingdate'];
		            	$arraySetResult[$k]['name'] = $pack['booking_details']['name'];
		            	$arraySetResult[$k]['email'] = $pack['booking_details']['email'];
		            	$arraySetResult[$k]['phone_number'] = $pack['booking_details']['phone_number'];
		            	$arraySetResult[$k]['rate'] = $pack['rate'];

		            	$tour_guide[$key]->booked += $pack['booking_details']['overall_tourists'];
		            }
		            $tour_guide[$key]->available = (string)($value->total_seat - $tour_guide[$key]->booked);
		            $arrayFinalResult = [];
		            $tour_guide[$key]->details = [];

		            if(count($arraySetResult) > 0){
		            	foreach ($arraySetResult as $result) {
			            	$arrayFinalResult['details'][] = $result;
			            	$tour_guide[$key]->details = $arrayFinalResult['details'];
			            }
		            }

			}

			return $tour_guide;
		} catch (Exception $exception) {
			Log::error($exception);
            throw new GeneralCoreException($exception);
		}
    }

     /**
     * Add New Tour Guide
     * @return mixed
     *
     * @throws Exception
     */

    public function addTourGuide($request)
    {
         try {
         	DB::beginTransaction();

         	$tourOperatorId = Auth::user()->tour_operator_id;

            $staff =  new Staff;
            $staff->first_name = $request->first_name;
            $staff->email = $request->email;
            $staff->tour_operator_id = $tourOperatorId;
            $staff->phone_number = $request->phone_number;
            $staff->total_seat = $request->total_seat;
            $staff->status = $request->status;
            $staff->save();

            $staffRoles[] = [
					'role_id' => 7,
					'tour_operator_staff_id' => $staff->id,
					'created_by' => Auth::id(),
					'created_by_user_type' => userType(),
				];
			DB::table('tour_operator_staff_roles')
				->insert($staffRoles);

            DB::commit();

            return [
					'status' => "Success",
					'title' => "Tour Guide added successfully",
					];
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

     /**
     * Tour Guide Update
     * @return mixed
     *
     * @throws Exception
     */

    public function updateTourGuide($request)
    {
        try {
            $staff = Staff::find($request->tour_guide_id);
            $staff->first_name = $request->first_name;
            $staff->total_seat = $request->total_seat;
            $staff->status = $request->status;
            $staff->save();
            return [
					'status' => "Success",
					'title' => "Tour Guide updated successfully",
					];
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }


     /**
     * Tour Guide List
     * @return mixed
     *
     * @throws Exception
     */

    public function tourGuideList(Request $request)
    {
         try {
         		$tourOperator =  tourOperator();
         		$tour_guide = DB::table('tour_operator_staff as tos')
				->join('tour_operator_staff_roles as tosr', 'tosr.tour_operator_staff_id', '=', 'tos.id')
				->where('tosr.role_id', 7)
				->where('tosr.deleted_by', 0)
				->where('tos.tour_operator_id', $tourOperator->id)
				->get(['tos.first_name', 'tos.email', 'tos.id as tour_guide_id', 'tos.total_seat'
					,'tos.status']);

				return $tour_guide;

        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Tour Guide Status Update (Active / Inactive)
     * @return mixed
     *
     * @throws Exception
     */

    public function tourGuideStatusUpdate($request)
    {
         try {

            $tourGuideIdArray = explode(',', $request->tour_guide_id);

            foreach ($tourGuideIdArray as $id) {
            	$data = $this->staff::where('id', $id)->first();
            	if($data->status == "Active"){
            		$data->status = "Inactive";
            	}else{
            		$data->status = "Active";
            	}
            	$data->updated_by = Auth::id();
            	$data->updated_by_user_type = userType();
            	$data->save();
            }
             return [
					'status' => "Success",
					'title' => "Tour Guide status updated successfully",
					];
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
}
