<?php

namespace App\Services\ExtendBooking;

use App\Constants\Constants;
use App\Events\BookingNew;
use App\Jobs\DetectPayoutAdjustment;
use App\Exceptions\Core\GeneralCoreException;
use App\Models\BookingTransact;
use App\Models\Customer;
use App\Models\TourBooking;
use App\Models\TourBookingHistory;
use App\Models\TourPackageRate;
use App\Models\Transaction;
use App\Models\TourBookingContact;
use App\Services\Booking\BookingService;
use App\Services\Booking\EmailService;
use App\Services\Booking\HistoryService;
use App\Services\Booking\TransactionService;
use App\Services\CardBookingService;
use App\Services\DeletePerson100RefundService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\TourPackage;
use App\Services\ThreeWayPayment\ThreeWayPaymentService;
use App\Services\Booking\AddonBookingService;
use App\Services\Booking\PaymentLinkService;
use App\Services\Payments\PaymentModeResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class ExtendBookingCardService
{
	/**
	 * Execute the service.
	 *
	 * @return mixed
	 */
	public static function run($request)
	{
		try {
			$isCashFees = cashFees();

			$trans = Transaction::where('tour_booking_id', $request->bookingID)->where('status', 'Completed')->whereNull('charge_type')->orderBy('id', 'DESC')->first(['payment_type']);

			if (!empty($trans) && $trans->payment_type == 1) {
				$splitAmount = static::PaymentCheckBeforeSplit($request);
				$splitPendingAmount = $request->splitPendingAmount;
				$splitAmount = round($splitAmount);

				if ($splitAmount > $splitPendingAmount) {
					if (isset($request->api) && $request->api == 1) {
						throw new Exception('Unable to Split booking. Please use only paid pax for Split booking.');
					} else {
						Session::flash('error', 'Unable to Split booking. Please use unpaid pax for Split booking.');
						return ['splitAmount' => $splitAmount, 'bookingId' => $request->bookingID];
					}
				}
			}

			$transactionsCount = Transaction::where('tour_booking_id', $request->bookingID)->where('status', 'Completed')->whereNull('charge_type')->orderBy('id', 'DESC')->count();
			if ($transactionsCount > 1) {
				if ($isCashFees == '1' && $request->payment_type == 'Cash') {
					return static::forSinglePayment($request);
				} else {
					return static::forMultiplePayments($request);
				}
			} else if ($transactionsCount == 1) {
				return static::forSinglePayment($request);
			}
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	private static function forSinglePayment($request)
	{
		DB::beginTransaction();
		try {
			$check = $request->input('check', []);
			$checkPermitfee = $request->input('permitF', []);
			$array = array_count_values($check);

			// Build checkarrays for individual permit fee calculations
			$checkarrays = array();
			foreach ($check as $key => $value) {
				$explode = explode('.', $key);
				$checkarrays[$value][] = $explode[1];
			}

			$userType = userType();
			$isCashFees = cashFees();
			$splitPendingAmount = ($request->has('splitPendingAmount') && $request->splitPendingAmount > 0) ? $request->splitPendingAmount : 0;
			$createdByWithName = BookingService::createdUserInfo();
			$tourBooking = TourBooking::where('id', $request->bookingID)->first();
			$isPartnerBooking = $tourBooking->is_partner_booking;
			$tourists = $tourBooking->tourists + $tourBooking->infants;
			$nowTime = Carbon::now();

			$info = DB::table('tour_slot_times AS tst')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
				->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
				->join('cities AS ct', 'ct.id', '=', 't_o.city_id')
				->join('states AS st', 'st.id', '=', 'ct.state_id')
				->where('tst.id', $tourBooking->tour_slot_id)
				->select([
					'tst.time_from',
					'tst.time_to',
					'tsd.date',
					'tst.time_slot_seats as seats',
					'tp.tour_operator_id',
					'tsd.tour_package_id',
					'tp.name AS package_name',
					'tp.important_notes',
					'tp.things_to_bring',
					'tp.arrival_time_duration',
					'ct.state_id',
					'st.permit_fee',
					't_o.name AS tour_operator',
					't_o.email AS tour_op_email',
					't_o.phone_number AS tour_op_phone',
					't_o.website AS tour_op_website',
					't_o.logo AS tour_op_logo',
					't_o.cancellation_policy',
					'tp.service_commission_percentage',
					'tp.stripe_commission_percentage',
					't_o.tenant_id',
					'tp.stripe_destination_id',
					't_o.id as tour_operator_id',
					't_o.statement_descriptor',
					't_o.card_reader_prefix',
					'tp.email as packageEmail',
					'tp.from_name as packageFromName',
					'tp.from_email as packageFromEmail',
					'tp.api_key as packageApiKey',
					'tp.attach_waiver_pdf',
					'tp.waiver_pdf',
					'tp.has_contacts',
					DB::raw("(SELECT SUM(tbm.tourists)
					FROM tour_bookings_master AS tbm
					WHERE tbm.tour_slot_id = tst.id
						AND tbm.status != 'Cancelled'
					GROUP BY tbm.tour_slot_id) AS booked_seats")
				])
				->first();

			if (!$info) {
				throw new Exception('Unable to split booking.');
			}

			if ($request->has('api') && $request->api == '1') {
				if ($info->date != date('Y-m-d') && $info->date . ' ' . $info->time_from <= mysqlDT()) {
					throw new Exception("The tour's date-time has passed; split people isn't possible in the past.");
				}
			} else {
				if ($info->date != date('Y-m-d') && $info->date . ' ' . $info->time_from <= mysqlDT()) {
					throw new Exception("The tour's date-time has passed; split people isn't possible in the past.");
				}
			}

			$customer = Customer::where('id', $tourBooking->customer_id)->first();
			$bookedFromFrontend = false;
			if ($customer->is_affiliate == 'Yes') {
				$bookedFromFrontend = bookedFromFrontEnd($request->bookingID);
			}
			$refundedpermitarray = array();

			$checkarrays = array();
			$checkinstatusarray = array();
			$redeemTicketArray = array();
			$refundedpermitfeearray = array();

			foreach ($check as $key => $value) {
				$btset = BookingTransact::where('tour_booking_id', $request->bookingID)->where('tour_package_rate_id', $value)->first();

				$explode = explode('.', $key);

				$checkarrays[$value][] = $explode[1];
				$checkinstatusarray[$value] = $btset->check_in_status;
				$redeemTicketArray[$value] = $btset->redeem_ticket;
				$refundedpermitfeearray[$value] = $btset->refund_permitfee_status;
			}

			// Resolve which tour_booking_addon_fields ids belong to the pax positions being split
			// off, directly from $checkarrays — no separate "Passenger Add-ons" modal round trip.
			$explicitAddonFieldIds = AddonBookingService::resolveSplitAddonFieldIds($request->bookingID, $checkarrays);

			$updatedCheckIns = array();
			$updatedRedeemTickets = array();
			$updatedRefundedPermitfees = array();
			$updatedRefundedPermitfeesForSplitBooking = array();
			$updatedCheckInsForSplitBooking = array();
			$updatedRedeemTicketsForSplitBooking = array();
			if (!empty($checkarrays)) {
				foreach ($checkarrays as $k => $checkarray) {
					if (isset($checkinstatusarray[$k]) && $checkinstatusarray[$k] != '')
						$checkinstatusInfo = json_decode($checkinstatusarray[$k]);
					else
						$checkinstatusInfo = [];

					if (isset($redeemTicketArray[$k]) && $redeemTicketArray[$k] != '')
						$redeemTicketInfo = json_decode($redeemTicketArray[$k], TRUE);
					else
						$redeemTicketInfo = [];

					if (isset($refundedpermitfeearray[$k]) && $refundedpermitfeearray[$k] != '')
						$refundpermitfeesstatusInfo = json_decode($refundedpermitfeearray[$k]);
					else
						$refundpermitfeesstatusInfo = [];

					$updatedRefundedPermitfeesStatusInfo = [];
					$updatedCheckInsStatusInfo = [];
					$updatedRedeemTicketInfo = [];
					$count = 0;
					$cnt = 0;
					$redeemCount = 0;
					$newRedeemTicketArr = [];
					foreach ($checkarray as $l => $m) {
						if (isset($checkinstatusInfo[$m]) && !empty($checkinstatusInfo[$m])) {
							$updatedCheckInsStatusInfo[$cnt] = $checkinstatusInfo[$m];
							unset($checkinstatusInfo[$m]);
							$cnt++;
						}
						if (!empty($redeemTicketInfo)) {
							for ($y = 0; $y < count($redeemTicketInfo); $y++) {
								if (isset($redeemTicketInfo[$y]['data'][$m]) && !empty($redeemTicketInfo[$y]['data'][$m])) {
									$updatedRedeemTicketInfo[$y]['id'] = $redeemTicketInfo[$y]['id'];
									$updatedRedeemTicketInfo[$y]['name'] = $redeemTicketInfo[$y]['name'];
									$updatedRedeemTicketInfo[$y]['data'][$redeemCount] = [
										'status' => $redeemTicketInfo[$y]['data'][$m]['status'],
										'dateTime' => $redeemTicketInfo[$y]['data'][$m]['dateTime']
									];
									unset($redeemTicketInfo[$y]['data'][$m]);
									$redeemCount++;
								}
								if (isset($redeemTicketInfo[$y]['data'])) {
									$newRedeemTicketArr = $redeemTicketInfo;
									$newRedeemTicketArr[$y]['data'] = array_values($newRedeemTicketArr[$y]['data']);
								}
								$updatedRedeemTicketInfo[$y]['data'] = array_values($updatedRedeemTicketInfo[$y]['data']);
							}
						}
						if (isset($refundpermitfeesstatusInfo[$m]) && !empty($refundpermitfeesstatusInfo[$m])) {

							if (isset($refundpermitfeesstatusInfo[$m]->status) && $refundpermitfeesstatusInfo[$m]->status == 'Y') {
								// Include both full refunds (amount > 0) and partial refunds (new_permit_fee set)
								if ((isset($refundpermitfeesstatusInfo[$m]->amount) && $refundpermitfeesstatusInfo[$m]->amount > 0) ||
									isset($refundpermitfeesstatusInfo[$m]->new_permit_fee)
								) {
									$refundedpermitarray[$k][] = $m;
								}
							}
							$updatedRefundedPermitfeesStatusInfo[$count] = $refundpermitfeesstatusInfo[$m];
							$count++;
							unset($refundpermitfeesstatusInfo[$m]);
						}
					}
					$redeemTicketInfo = $newRedeemTicketArr;
					$updatedCheckIns[$k] = $checkinstatusInfo;
					$updatedRedeemTickets[$k] = $redeemTicketInfo;
					$updatedRefundedPermitfees[$k] = $refundpermitfeesstatusInfo;
					$updatedRefundedPermitfeesForSplitBooking[$k] = $updatedRefundedPermitfeesStatusInfo;
					$updatedCheckInsForSplitBooking[$k] = $updatedCheckInsStatusInfo;
					$updatedRedeemTicketsForSplitBooking[$k] = $updatedRedeemTicketInfo;
				}
			}
			$liability_waiver_signing = $tourBooking->liability_waiver_signing;
			$touristsNumbers = ($request->has('tourits_numbers')) ? $request->input('tourits_numbers') : '';
			$liabilityWaiverSigningSplitBooking = [];
			if ($liability_waiver_signing != '' && $touristsNumbers != '') {
				$liability_waiver_signing = json_decode($liability_waiver_signing);
				if ($touristsNumbers != '') {
					$touristNumberArray = explode(",", $touristsNumbers);
					$touristsCount = 0;
					if (!empty($touristNumberArray)) {
						foreach ($touristNumberArray as $k => $value) {
							if (isset($liability_waiver_signing[$value])) {
								$liabilityWaiverSigningSplitBooking[$touristsCount] = $liability_waiver_signing[$value];
								unset($liability_waiver_signing[$value]);
								$touristsCount++;
							}
						}
					}
					TourBooking::where('id', $request->bookingID)
						->update([
							'liability_waiver_signing' => (!empty($liability_waiver_signing)) ? json_encode((array_values($liability_waiver_signing))) : ''
						]);
				}
			}

			if (!empty($updatedCheckIns)) {
				foreach ($updatedCheckIns as $key => $value) {
					BookingTransact::where('tour_booking_id', $request->bookingID)->where('tour_package_rate_id', $key)
						->update([
							'check_in_status' => (!empty($value)) ? json_encode((array_values($value))) : ''
						]);
				}
			}

			if (!empty($updatedRedeemTickets)) {
				foreach ($updatedRedeemTickets as $key => $value) {
					BookingTransact::where('tour_booking_id', $request->bookingID)->where('tour_package_rate_id', $key)
						->update([
							'redeem_ticket' => (!empty($value)) ? json_encode((array_values($value))) : ''
						]);
				}
			}

			if (!empty($updatedRefundedPermitfees)) {
				foreach ($updatedRefundedPermitfees as $key => $value) {
					if (!empty($value)) {
						BookingTransact::where('tour_booking_id', $request->bookingID)->where('tour_package_rate_id', $key)
							->update([
								'refund_permitfee_status' => (!empty($value)) ? json_encode(array_values($value)) : '',
							]);
					}
				}
			}
			$totalForNewBooking = 0;
			$serviceCommissionTotal = 0;
			$partnerFeesAmount = 0;
			$totalTourists = 0;
			$totalInfants = 0;
			$touristGroups = [];
			$txnHistoryIds = [];
			$tourPackage = TourPackage::where('id', $info->tour_package_id)->first();

			$trans = Transaction::where('tour_booking_id', $request->bookingID)->where('status', 'Completed')->whereNull('charge_type')->orderBy('id', 'DESC')->first();
			$bookingHistoryFirstPayment = TourBookingHistory::where('tour_booking_id', $request->bookingID)->where('operation', 'Payment')->orderBy('id', 'DESC')->first();
			$bookingTransactFirst = BookingTransact::where('tour_booking_id', $request->bookingID)->where('deleted_at', null)->first();
			$detailsHistory = json_decode($bookingHistoryFirstPayment->details);
			$isBlackoutDate = isBlackoutDateEnabled($info->date);
			foreach ($array as $key => $value) {
				$rateCheck = BookingTransact::where('tour_booking_id', $request->bookingID)->where('tour_package_rate_id', $key)->first();
				$rateCheckname = TourPackageRate::where('id', $key)->first();
				$isInfantRate = $rateCheck->is_infant_rate;
				$customer = Customer::where('id', $tourBooking->customer_id)->first();
				$serviceCommissionPercentage = $bookingTransactFirst->nat_fees_percentage;
				if ($customer->is_affiliate == 'Yes') {
					$serviceCommissionPercentage = $bookingTransactFirst->nat_fees_percentage;
					if (getNextYearCommissionPercentage($info->date)) {
						$affiliateCommission = ($isBlackoutDate) ? getNormalCommissionPercentage($info->tour_package_id) : getAffiliateCommissionPercentage($customer->id);
						$serviceCommissionPercentage = ($isBlackoutDate) ? $affiliateCommission['service_commission_percentage'] : $affiliateCommission['affiliate_processing_percentage'];
					}
				} elseif (getNextYearCommissionPercentage($info->date)) {
					$serviceCommission = getNormalCommissionPercentage($info->tour_package_id);
					$serviceCommissionPercentage = $serviceCommission['service_commission_percentage'];
				}

				$subTotal = 0;
				$totalFeeAmount = 0;
				$partnerFees = 0;
				if ($rateCheck->refund_permitfee_status != '' && isset($refundedpermitarray) && !empty($refundedpermitarray) && isset($refundedpermitarray[$key])) {
					// Get the rate group type from the tour package rate
					$rateGroupType = $rateCheckname->rate_for;

					// Get people being split for this rate group from checkarrays
					$peopleToSplit = [];
					if (isset($checkarrays[$key])) {
						$peopleToSplit = $checkarrays[$key];
					}

					$totalSubtotal = 0;
					$totalFee = 0;

					// Calculate cost for each person being split individually
					foreach ($peopleToSplit as $personIndex) {
						// Get current permit fee for this specific person from permitF array
						$permitKey = $rateGroupType . '.' . $personIndex;
						$currentPermitFee = isset($checkPermitfee[$permitKey]) ? floatval($checkPermitfee[$permitKey]) : $rateCheck->permit_fee;

						// Calculate rate with individual permit fee
						$rate = $rateCheck->rate + $rateCheck->additional_charge + $currentPermitFee + ($isPartnerBooking == 1 ? $rateCheck->partner_fees_amount : 0);

						$per = $rate * ($rateCheck->additional_tax_percentage + $rateCheck->processing_charge_percentage) / 100;

						$personSubtotal = $rate + $per;
						$personFee = roundout($personSubtotal * $serviceCommissionPercentage / 100);

						$totalSubtotal += $personSubtotal;
						$totalFee += $personFee;
					}

					// Format final values
					$subTotal = round($totalSubtotal, 2);
					$totalFeeAmount = round($totalFee, 2);

					// Calculate ticket cost for partner booking
					if ($isPartnerBooking == 1) {
						$ticketCostTotal = 0;
						foreach ($peopleToSplit as $personIndex) {
							$permitKey = $rateGroupType . '.' . $personIndex;
							$currentPermitFee = isset($checkPermitfee[$permitKey]) ? floatval($checkPermitfee[$permitKey]) : $rateCheck->permit_fee;
							$ticketCostTotal += $rateCheck->rate + $rateCheck->additional_charge + $currentPermitFee;
						}
						$ticketCostForRateGroup = $ticketCostTotal / count($peopleToSplit);
					} else {
						$ticketCostForRateGroup = 0;
					}
				} else {
					$rate = $rateCheck->rate + $rateCheck->additional_charge + $rateCheck->permit_fee + ($isPartnerBooking == 1 ? $rateCheck->partner_fees_amount : 0);
					$ticketCostForRateGroup = ($isPartnerBooking == 1) ? $rateCheck->rate + $rateCheck->additional_charge + $rateCheck->permit_fee : 0;
					$per = $rate * ($rateCheck->additional_tax_percentage + $rateCheck->processing_charge_percentage) / 100;
					$subTotal = ($rate + $per);
					$totalFeeAmount = roundout($subTotal * $serviceCommissionPercentage / 100) * $value;
					$subTotal = number_format((float)$subTotal * $value, 2, '.', '');
				}

				$ticketCost = number_format((float)$ticketCostForRateGroup * $value, 2, '.', '');
				$partnerFees = ($isPartnerBooking == 1) ? $rateCheck->partner_fees_amount * $value : 0;
				$partnerFeesAmount += $partnerFees;
				$tourBookingInfo = TourBooking::find($request->bookingID);
				$refundedPermitFeesDetails = (isset($updatedRefundedPermitfeesForSplitBooking[$key]) && !empty($updatedRefundedPermitfeesForSplitBooking[$key])) ? json_encode((array_values($updatedRefundedPermitfeesForSplitBooking[$key]))) : '';
				$checkInsDetails = (isset($updatedCheckInsForSplitBooking[$key]) && !empty($updatedCheckInsForSplitBooking[$key])) ? json_encode((array_values($updatedCheckInsForSplitBooking[$key]))) : '';
				$redeemTicketDetails = (isset($updatedRedeemTicketsForSplitBooking[$key]) && !empty($updatedRedeemTicketsForSplitBooking[$key])) ? json_encode((array_values($updatedRedeemTicketsForSplitBooking[$key]))) : '';
				if ($isInfantRate == 1) {
					$totalInfants += $value;
				} else {
					$totalTourists += $value;
				}
				$setTotal = $rateCheck->total - $subTotal;
				$setTourist = ($isInfantRate == 1) ? $rateCheck->tourists : $rateCheck->tourists - $value;
				$setInfant = ($isInfantRate == 1) ? $rateCheck->infants - $value : $rateCheck->infants;
				$setFeeAmount = 0;
				$setFeeAmount = $tourBookingInfo->service_commission - (float)$totalFeeAmount;
				$setFeeAmount = number_format((float)$setFeeAmount, 2, '.', '');
				$serviceCommissionTotal += $totalFeeAmount;
				$totalForNewBooking += $subTotal + $totalFeeAmount;
				$touristGroups[] = $rateCheckname->rate_for[0] . ':' . $value;
				$tbTransact[] = [
					'tour_package_rate_id' => $key,
					'tourists' => $isInfantRate == 1 ? 0 : $value,
					'infants' => $isInfantRate == 1 ? $value : 0,
					'processing_charge_percentage' => 0,
					'nat_fees_percentage' => $rateCheck->nat_fees_percentage,
					'stripe_fees_percentage' => $rateCheck->stripe_fees_percentage,
					'partner_fees_type' => ($rateCheck->partner_fees_amount > 0) ? $rateCheck->partner_fees_type : NULL,
					'partner_fees_percentage' => $rateCheck->partner_fees_percentage,
					'partner_fees_amount' => $rateCheck->partner_fees_amount,
					'refund_permitfee_status' => $refundedPermitFeesDetails,
					'check_in_status' => $checkInsDetails,
					'is_infant_rate' => $isInfantRate,
					'redeem_ticket' => $redeemTicketDetails,
					'additional_tax_percentage' => ($rateCheck->additional_tax_percentage),
					'additional_charge' => ($rateCheck->additional_charge),
					'permit_fee' => $rateCheck->permit_fee,
					'rate' => $rateCheck->rate,
					'total' => $subTotal,
					'created_by' => Auth::id(),
					'created_by_user_type' => $userType,
					'created_at' => $nowTime,
				];
				$tbTransaction[] = [
					'tourists' => $value,
					'rate_for' => $rateCheckname->rate_for
				];
				if ($setTourist == 0 && $setInfant == 0) {
					BookingTransact::where('tour_booking_id', $request->bookingID)->where('tour_package_rate_id', $key)
						->update([
							'total' => $setTotal,
							'tourists' => $setTourist,
							'infants' => $setInfant,
							'check_in_status' => '',
							'redeem_ticket' => null,
							'refund_permitfee_status' => ''
						]);
				} else {
					BookingTransact::where('tour_booking_id', $request->bookingID)->where('tour_package_rate_id', $key)
						->update([
							'total' => $setTotal,
							'tourists' => $setTourist,
							'infants' => $setInfant
						]);
				}
				DB::commit();
				$total = 0;
				$total = $subTotal + $totalFeeAmount;
				$tourBookingSetTotal = $tourBookingInfo->total - $total;
				$tourBookingSetTourist = ($isInfantRate == 1) ? $tourBookingInfo->tourists : $tourBookingInfo->tourists - $value;
				$tourBookingSetInfant = ($isInfantRate == 1) ? $tourBookingInfo->infants - $value : $tourBookingInfo->infants;
				if (!empty($trans) && $trans->payment_type == 1 && $splitPendingAmount > 0) {
					$tourBookingSetBookingTotal = $tourBookingInfo->booking_total - $total;
					TourBooking::where('id', $request->bookingID)->update([
						'total' => $tourBookingSetTotal,
						'booking_total' => $tourBookingSetBookingTotal,
						'tourists' => $tourBookingSetTourist,
						'infants' => $tourBookingSetInfant,
						'service_commission' => $setFeeAmount
					]);
				} else {
					TourBooking::where('id', $request->bookingID)->update([
						'total' => $tourBookingSetTotal,
						'tourists' => $tourBookingSetTourist,
						'infants' => $tourBookingSetInfant,
						'service_commission' => $setFeeAmount
					]);
				}
				DB::commit();

				if (!empty($trans) && $trans->payment_type != 1 || $total == 0) {
					$refundType = "100 % Refund";
				} else {
					$refundType = "Don't Refund";
				}

				$bookingInfo = TourBooking::find($request->bookingID);

				$historyId = HistoryService::storeRefundDataInHistory(
					$request->bookingID,
					$bookingInfo->tourists + $bookingInfo->infants,
					$createdByWithName,
					null,
					$refundType,
					null,
					$total,
					$detailsHistory->card_number ?? '',
					[],
					[],
					($isCashFees == '1' && $request->payment_type == 'Cash') ? 'Cash' : 'Card',
					null,
					null,
					null,
					$rateCheckname->rate_for . ' person ' . $value,
					0,
					'Extend Booking',
					($refundType == '100 % Refund') ? $partnerFees : 0
				);
				DB::commit();

				$txnHistoryIds[] = $historyId;
				if (!empty($trans) && $trans->payment_type != 1) {
					$refundTransId = TransactionService::storeRefundInTransaction(
						$request->bookingID,
						$total,
						$totalFeeAmount,
						($isCashFees == '1' && $request->payment_type == 'Cash') ? 'Cash' : 'Card',
						($request->payment_type == 'Card') ? $trans->transaction_number : null,
						($request->payment_type == 'Card') ? $trans->application_fee_id : null,
						null,
						true,
						mode: $trans->payment_mode
					);
					if ($isPartnerBooking == 1 && $ticketCost > 0  && $refundType == '100 % Refund') {
						$transactionId = $trans->id;
						ThreeWayPaymentService::storeReverseTransferInPayout($request->bookingID, $transactionId, $refundTransId ?? null, $ticketCost, $partnerFees);
					}
				}
				DB::commit();
				if (!empty($trans) && $trans->payment_type != 1) {
					DeletePerson100RefundService::run($tourBookingInfo, $historyId, $request, ($isCashFees == '1' && $request->payment_type == 'Cash') ? 'Cash' : 'Card', $refundTransId);
				}
			}
			DB::commit();

			$newTourBooking = TourBooking::create([
				'tour_package_id' => $tourBooking->tour_package_id,
				'tour_slot_id' => $tourBooking->tour_slot_id,
				'customer_id' => $tourBooking->customer_id,
				'tourists' => $totalTourists,
				'infants' => $totalInfants,
				'name' => $tourBooking->name,
				'email' => $tourBooking->email,
				'phone_number' => $tourBooking->phone_number,
				'mask_required' => 'Yes',
				'service_commission' => $serviceCommissionTotal,
				'total' => $totalForNewBooking,
				'booking_total' => $totalForNewBooking,
				'liability_waiver_signing' => (!empty($liabilityWaiverSigningSplitBooking)) ? json_encode((array_values($liabilityWaiverSigningSplitBooking))) : '',
				'status' => 'Booked',
				'is_partner_booking' => $isPartnerBooking,
				'visitor' => $request->getClientIp(),
				'request_from' => $request->is('api/*') ? Constants::API : Constants::BACKEND,
				'expire_at' => $tourBooking->expire_at,
				'created_by' => Auth::id(),
				'created_by_user_type' => $userType,
				'updated_at' => null
			]);
			DB::commit();

			for ($a = 0, $ac = count($tbTransact); $a < $ac; $a++) {
				$tbTransact[$a]['tour_booking_id'] = $newTourBooking->id;
			}

			DB::table('tour_bookings_transact')->insert($tbTransact);
			DB::commit();

			// Update tour booking id in contacts table if booking package has contacts
			if ($info->has_contacts == 1) {
				if (isset($request->contacts) && $request->contacts != null) {
					try {
						$contactIds = json_decode($request->contacts, TRUE);

						if (count($contactIds) > 0) {
							foreach ($contactIds as $key => $contact) {

								$tourBookingContact = TourBookingContact::findOrFail($contact);
								$bookingTransact = BookingTransact::where('tour_booking_id', $request->bookingID)->get();

								foreach ($bookingTransact as $key => $transact) {
									$newBookingTransact = BookingTransact::where('tour_booking_id', $newTourBooking->id)->where('tour_package_rate_id', $transact->tour_package_rate_id)->first();

									if ($transact->id == $tourBookingContact->tour_booking_transact_id) {
										$tourBookingContact->update([
											'tour_booking_id' => $newTourBooking->id,
											'tour_booking_transact_id' => $newBookingTransact->id,
											'updated_at' => mysqlDT()
										]);
									}
								}
							}
						}
					} catch (Exception $ex) {
						Log::error($ex->getMessage());
					}
				}
			}

			$addedPeoplesText = array();
			if (!empty($tbTransaction)) {
				foreach ($tbTransaction as $tblTransaction) {
					$addedPeoplesText[] = $tblTransaction['rate_for'] . " person " . $tblTransaction['tourists'];
				}
			}
			HistoryService::storeTouristInHistory($request, $newTourBooking, 0, $addedPeoplesText, $newTourBooking->tourists + $newTourBooking->infants, $createdByWithName);
			DB::commit();
			$transactionId = 0;
			$historyId = 0;

			if (!empty($trans) && $trans->payment_type != 1 || $totalForNewBooking == 0) {
				$transactionId = TransactionService::storePaymentInTransaction(
					$newTourBooking->id,
					$totalForNewBooking,
					$serviceCommissionTotal,
					($isCashFees == '1' && $request->payment_type == 'Cash') ? 'Cash' : 'Card',
					0,
					($request->payment_type == 'Card') ? $trans->transaction_number : null,
					($request->payment_type == 'Card') ? $trans->application_fee_id : null,
					[],
					true,
					// This reuses an existing charge's reference rather than creating a new one, so the
					// mode must match the original charge being extended, not be re-derived.
					mode: ($request->payment_type == 'Card' && !empty($trans)) ? $trans->payment_mode : PaymentModeResolver::DESTINATION
				);

				//Below line is for to store payout for partner package
				if ($isPartnerBooking == 1 && $totalForNewBooking > 0) {
					$oldTransferInfo = DB::table('payouts')
						->where('tour_booking_id', $request->bookingID)
						->where('transaction_id', $trans->id)
						->where('payout_status', 'Completed')
						->first();
					$payoutData = [
						'amount' => $totalForNewBooking,
						'platform_fees' => $serviceCommissionTotal,
						'partner_fees' => $partnerFeesAmount,
						'booking_id' => $newTourBooking->id,
						'transaction_id' => $transactionId,
						'package_id' => $newTourBooking->tour_package_id,
						'payment_method' => 'Card',
						'partner_destination_id' => $info->stripe_destination_id,
						'transfer_id' => $oldTransferInfo->transfer_id ?? null,
					];
					ThreeWayPaymentService::storeTransferInPayout($payoutData);
				}
				$paymentHistdata = [
					'total' => $totalForNewBooking,
					'payment_type' => ($request->payment_type == 'Cash') ? 'Cash' : 'Card'
				];
				$historyId = HistoryService::storeCardPaymentInHistory($newTourBooking->id, $newTourBooking->tourists + $newTourBooking->infants, $paymentHistdata, $detailsHistory->card_number ?? '', $detailsHistory->card_type ?? '', $createdByWithName, [], null);

				// Settle price-per-pax add-ons for the split pax: an Add-ons Refund entry on the
				// old booking and an Add-ons Payment entry on the new booking, written after the
				// tour amount's own refund/payment entries above so history reads tour-then-addons
				// on both sides. Stamped with the SAME transaction/card just used for the tour
				// amount's own new-booking payment entry above, so both agree on which payment
				// this split is attributed to.
				if (!empty($explicitAddonFieldIds)) {
					try {
						$selectedTransaction = ($request->payment_type != 'Cash') ? [
							'transaction_number' => $trans->transaction_number ?? null,
							'application_fee_id' => $trans->application_fee_id ?? null,
							'card_last4' => $detailsHistory->card_number ?? null,
						] : null;
						AddonBookingService::splitBookingPaxAddons($request->bookingID, $newTourBooking->id, $explicitAddonFieldIds, $selectedTransaction);
						// Any of these fields that are still genuinely UNPAID (sent via "Payment
						// Link", not yet collected) must have their pending payment-link item moved
						// too — splitBookingPaxAddons() only re-points the field itself. Without this,
						// the old booking's link stays wrongly unreduced and CardBookingService::run()
						// below (via createAddonTransactionsFromFields()) would otherwise be the only
						// thing to "settle" these fields on the new booking, wrongly as paid.
						PaymentLinkService::movePendingAddonPaymentLinkItems($request->bookingID, $newTourBooking->id, $explicitAddonFieldIds);
					} catch (Exception $ex) {
						Log::error($ex->getMessage());
					}
				}
			}
			DB::commit();

			HistoryService::storeSplitBookingInHistory($tourBooking->id, $newTourBooking->id, $tourists, $totalTourists + $totalInfants, ($request->payment_type == 'Cash') ? 'Cash' : 'Card', $createdByWithName, $txnHistoryIds, $historyId);
			DB::commit();

			$isSplitOrFull = 'Full Payment';
			$bookingDetails['booking_id'] = $newTourBooking->id;
			$bookingDetails['customer_id'] = $newTourBooking->customer_id;
			$bookingDetails['tour_operator_id'] = $info->tour_operator_id;
			$bookingDetails['is_booking_extended'] = true;
			$bookingDetails['service_commission'] = $serviceCommissionTotal;
			$bookingDetails['tour_package_id'] = $newTourBooking->tour_package_id;
			$bookingDetails['booked_from_frontend'] = $bookedFromFrontend;
			$requestData = $request->all();
			$requestData['payment_method'] = $request->payment_type;
			CardBookingService::run((array)$bookingDetails, $transactionId, $historyId, $isSplitOrFull, $requestData);
			DB::commit();
			$slotInfo = DB::table('tour_slot_times AS tst')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
				->where('tst.id', $tourBooking->tour_slot_id)
				->when(($userType == 'Tour Operator' || $userType == 'Affiliate'), function ($query) {
					$query->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
				})
				->select([
					DB::raw("(SELECT SUM(tbm.tourists)
                        FROM tour_bookings_master AS tbm
                        WHERE tbm.tour_slot_id = tst.id
                            AND tbm.status != 'Cancelled'
                        GROUP BY tbm.tour_slot_id) AS booked_seats")
				])
				->first();
			$paymentOnly = TourBookingHistory::where('tour_booking_id', $request->bookingID)
				->where('operation', 'Payment')
				->get(['id', 'ticket_cost', 'processing_fees', 'platform_fees']);
			$paymentOnlyCheck = 0;
			foreach ($paymentOnly as $value) {
				$paymentOnlyCheck += $value->ticket_cost + $value->processing_fees + $value->platform_fees;
			}
			$paymentOnlyCheck = round($paymentOnlyCheck);
			$tourBookingSetTotal = round($tourBookingSetTotal);

			if ($paymentOnlyCheck >= $tourBookingSetTotal) {
				$trans->payment_type = 0;
				$trans->save();
			}
			$booking = [
				'id' => $newTourBooking->id,
				'group_id' => $newTourBooking->group_id,
				'prefixedId' => bookingPrefix($newTourBooking->id),
				'cust_name' => $newTourBooking->name,
				'pkg_id' => $info->tour_package_id,
				'pkg_name' => $info->package_name,
				'paid' => $totalForNewBooking,
				'total' => $totalForNewBooking,
				'tour_slot_id' => $newTourBooking->tour_slot_id,
				'tour_d_t' => $info->date . ' ' . $info->time_from,
				'booking_d_t' => $newTourBooking->created_at,
				't_groups' => implode(' | ', $touristGroups),
				'tour_operator_id' => $info->tour_operator_id,
				'tourists' => $totalTourists,
				'infants' => $totalInfants,
				'is_affiliate' => 'No',
				'notif_for' => 'admin',
				'date' => $info->date,
				'time_from' => $info->time_from,
				'time_to' => $info->time_to,
				'seats' => $info->seats,
				'payment_method' => ($request->payment_type == 'Cash') ? 'Cash' : 'Card',
				'booked_seats' => $slotInfo->booked_seats,
				'broadcaster_user_id' => Auth::id(),
				'env' => config('app.env'),
				'invoiceStatus' => 0,
				'BookingTotal' => $newTourBooking->BookingTotal,
				'RefundAmount' => 0,
				'PaymentTotal' => $newTourBooking->BookingTotal,
				'paid_split' => null,
				'domain' => url('/'),
				'background_colour_class' => $customer->is_affiliate == 'Yes' ? 'affiliate-catch' : 'normal-booking'
			];

			BookingNew::dispatch($booking);
			$booking['notif_for'] = 'Tour Operator';
			BookingNew::dispatch($booking);

			$arEmailData = [
				'tour_d_t' => $booking['tour_d_t'],
				'bookingId' => $newTourBooking->id,
				'bookingGroupId' => $newTourBooking->group_id,
				'custName' => $newTourBooking->name,
				'custEmail' => $newTourBooking->email,
				'custPhone' => $newTourBooking->phone_number,
			];

			if (in_array(Auth::user()->email, config('constants.prevent_sending_emails')) == false) {
				EmailService::sendAcknowledgementEmail($info, $arEmailData);
			}

			return [
				'bookingId' => $newTourBooking->id,
				'bookingToken' => Crypt::encryptString($newTourBooking->id)
			];
		} catch (Exception $exception) {
			DB::rollBack();
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	private static function forMultiplePayments($request)
	{
		DB::beginTransaction();
		try {
			$check = $request->input('check', []);
			$checkPermitfee = $request->input('permitF', []);
			$array = array_count_values($check);

			// Build checkarrays for individual permit fee calculations
			$checkarrays = array();
			foreach ($check as $key => $value) {
				$explode = explode('.', $key);
				$checkarrays[$value][] = $explode[1];
			}

			$checkedTransactions = explode(',', $request->checked_transactions);

			$userType = userType();

			$createdByWithName = BookingService::createdUserInfo();
			$tourBooking = TourBooking::where('id', $request->bookingID)->first();
			$tourists = $tourBooking->tourists + $tourBooking->infants;
			$isPartnerBooking = $tourBooking->is_partner_booking;
			$info = DB::table('tour_slot_times AS tst')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
				->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
				->join('cities AS ct', 'ct.id', '=', 't_o.city_id')
				->join('states AS st', 'st.id', '=', 'ct.state_id')
				->where('tst.id', $tourBooking->tour_slot_id)
				->select([
					'tst.time_from',
					'tst.time_to',
					'tsd.date',
					'tst.time_slot_seats as seats',
					'tp.tour_operator_id',
					'tsd.tour_package_id',
					'tp.name AS package_name',
					'tp.important_notes',
					'tp.things_to_bring',
					'tp.arrival_time_duration',
					'ct.state_id',
					'st.permit_fee',
					't_o.name AS tour_operator',
					't_o.email AS tour_op_email',
					't_o.phone_number AS tour_op_phone',
					't_o.website AS tour_op_website',
					't_o.logo AS tour_op_logo',
					't_o.cancellation_policy',
					'tp.service_commission_percentage',
					'tp.stripe_commission_percentage',
					't_o.tenant_id',
					'tp.stripe_destination_id',
					't_o.id as tour_operator_id',
					't_o.statement_descriptor',
					't_o.card_reader_prefix',
					'tp.email as packageEmail',
					'tp.from_name as packageFromName',
					'tp.from_email as packageFromEmail',
					'tp.api_key as packageApiKey',
					'tp.attach_waiver_pdf',
					'tp.waiver_pdf',
					'tp.has_contacts',
					DB::raw("(SELECT SUM(tbm.tourists)
					FROM tour_bookings_master AS tbm
					WHERE tbm.tour_slot_id = tst.id
						AND tbm.status != 'Cancelled'
					GROUP BY tbm.tour_slot_id) AS booked_seats")
				])
				->first();

			if (!$info) {
				throw new Exception('Unable to split booking.');
			}

			$transF = Transaction::where('tour_booking_id', $request->bookingID)->where('status', 'Completed')->whereNull('charge_type')->orderBy('id', 'DESC')->first();

			$splitpopUpoff = 0;
			if (!empty($transF) && $transF->payment_type == 1) {
				$splitpopUpoff = 1;
			}

			if (count($checkedTransactions) == 0) {
				throw new Exception("Choose from which accounts you want to split.");
			}

			$customer = Customer::where('id', $tourBooking->customer_id)->first();
			$bookedFromFrontend = false;
			if ($customer->is_affiliate == 'Yes') {
				$bookedFromFrontend = bookedFromFrontEnd($request->bookingID);
			}

			$refundedpermitarray = array();

			$checkarrays = array();
			$checkinstatusarray = array();
			$redeemTicketArray = array();
			$refundedpermitfeearray = array();

			foreach ($check as $key => $value) {
				$btset = BookingTransact::where('tour_booking_id', $request->bookingID)->where('tour_package_rate_id', $value)->first();

				$explode = explode('.', $key);

				$checkarrays[$value][] = $explode[1];
				$checkinstatusarray[$value] = $btset->check_in_status;
				$redeemTicketArray[$value] = $btset->redeem_ticket;
				$refundedpermitfeearray[$value] = $btset->refund_permitfee_status;
			}

			// Resolve which tour_booking_addon_fields ids belong to the pax positions being split
			// off, directly from $checkarrays — no separate "Passenger Add-ons" modal round trip.
			$explicitAddonFieldIds = AddonBookingService::resolveSplitAddonFieldIds($request->bookingID, $checkarrays);

			$updatedCheckIns = array();
			$updatedRedeemTickets = array();
			$updatedRefundedPermitfees = array();
			$updatedRefundedPermitfeesForSplitBooking = array();
			$updatedCheckInsForSplitBooking = array();
			$updatedRedeemTicketsForSplitBooking = array();
			if (!empty($checkarrays)) {
				foreach ($checkarrays as $k => $checkarray) {
					if (isset($checkinstatusarray[$k]) && $checkinstatusarray[$k] != '')
						$checkinstatusInfo = json_decode($checkinstatusarray[$k]);
					else
						$checkinstatusInfo = [];

					if (isset($redeemTicketArray[$k]) && $redeemTicketArray[$k] != '')
						$redeemTicketInfo = json_decode($redeemTicketArray[$k], TRUE);
					else
						$redeemTicketInfo = [];

					if (isset($refundedpermitfeearray[$k]) && $refundedpermitfeearray[$k] != '')
						$refundpermitfeesstatusInfo = json_decode($refundedpermitfeearray[$k]);
					else
						$refundpermitfeesstatusInfo = [];

					$updatedRefundedPermitfeesStatusInfo = [];
					$updatedCheckInsStatusInfo = [];
					$updatedRedeemTicketInfo = [];
					$count = 0;
					$cnt = 0;
					$redeemCount = 0;
					$newRedeemTicketArr = [];
					foreach ($checkarray as $l => $m) {
						if (isset($checkinstatusInfo[$m]) && !empty($checkinstatusInfo[$m])) {
							$updatedCheckInsStatusInfo[$cnt] = $checkinstatusInfo[$m];
							unset($checkinstatusInfo[$m]);
							$cnt++;
						}
						if (!empty($redeemTicketInfo)) {
							for ($y = 0; $y < count($redeemTicketInfo); $y++) {
								if (isset($redeemTicketInfo[$y]['data'][$m]) && !empty($redeemTicketInfo[$y]['data'][$m])) {
									$updatedRedeemTicketInfo[$y]['id'] = $redeemTicketInfo[$y]['id'];
									$updatedRedeemTicketInfo[$y]['name'] = $redeemTicketInfo[$y]['name'];
									$updatedRedeemTicketInfo[$y]['data'][$redeemCount] = [
										'status' => $redeemTicketInfo[$y]['data'][$m]['status'],
										'dateTime' => $redeemTicketInfo[$y]['data'][$m]['dateTime']
									];
									unset($redeemTicketInfo[$y]['data'][$m]);
									$redeemCount++;
								}
								if (isset($redeemTicketInfo[$y]['data'])) {
									$newRedeemTicketArr = $redeemTicketInfo;
									$newRedeemTicketArr[$y]['data'] = array_values($newRedeemTicketArr[$y]['data']);
								}
								$updatedRedeemTicketInfo[$y]['data'] = array_values($updatedRedeemTicketInfo[$y]['data']);
							}
						}
						if (isset($refundpermitfeesstatusInfo[$m]) && !empty($refundpermitfeesstatusInfo[$m])) {

							if (isset($refundpermitfeesstatusInfo[$m]->status) && $refundpermitfeesstatusInfo[$m]->status == 'Y') {
								// Include both full refunds (amount > 0) and partial refunds (new_permit_fee set)
								if ((isset($refundpermitfeesstatusInfo[$m]->amount) && $refundpermitfeesstatusInfo[$m]->amount > 0) ||
									isset($refundpermitfeesstatusInfo[$m]->new_permit_fee)
								) {
									$refundedpermitarray[$k][] = $m;
								}
							}
							$updatedRefundedPermitfeesStatusInfo[$count] = $refundpermitfeesstatusInfo[$m];
							$count++;
							unset($refundpermitfeesstatusInfo[$m]);
						}
					}
					$redeemTicketInfo = $newRedeemTicketArr;
					$updatedCheckIns[$k] = $checkinstatusInfo;
					$updatedRedeemTickets[$k] = $redeemTicketInfo;
					$updatedRefundedPermitfees[$k] = $refundpermitfeesstatusInfo;
					$updatedRefundedPermitfeesForSplitBooking[$k] = $updatedRefundedPermitfeesStatusInfo;
					$updatedCheckInsForSplitBooking[$k] = $updatedCheckInsStatusInfo;
					$updatedRedeemTicketsForSplitBooking[$k] = $updatedRedeemTicketInfo;
				}
			}
			//This is for calculating payment and refund amounts for each transactions selected
			$transactions = Transaction::where('tour_booking_id', $request->bookingID)
				->whereIn('status', ['Completed', 'Refund'])
				->whereIn('transaction_number', $checkedTransactions)
				->whereNull('charge_type')
				->orderBy('id', 'DESC')
				->get();

			$paymentTransactions = $transactions->where('status', 'Completed');

			$refundTransactions = $transactions->where('status', 'Refund');
			if ($refundTransactions->count()) {
				foreach ($paymentTransactions as $key => $payment) {
					$payment->refund_amount = 0;
					foreach ($refundTransactions as $refund) {
						if ($refund->transaction_number == $payment->transaction_number) {
							$payment->refund_amount += $refund->amount;
						}
					}
				}
			} else {
				foreach ($paymentTransactions as $payment) {
					$payment->refund_amount = 0;
				}
			}
			$totalAvailableAmount = 0;
			$transactionsArray = array();
			foreach ($paymentTransactions as $k => $payment) {
				$transactionsArray[$payment->transaction_number] = $payment->amount - $payment->refund_amount;
				$totalAvailableAmount += $payment->amount - $payment->refund_amount;
			}

			$TourPackage = TourPackage::where('id', $info->tour_package_id)->first();
			$oldBookingID = oldBookingId();
			$isBlackoutDate = isBlackoutDateEnabled($info->date);
			$bookingTransactFirst = BookingTransact::where('tour_booking_id', $request->bookingID)->where('deleted_at', null)->first();
			//This to validate totals for each selected pax with remaining amounts for each transactions
			$totalPaymentAmountForNewBooking = 0;
			$partnerFeesAmount = 0;
			$rateGroupWiseCalculatedAmounts = array();
			$rateGroupWiseAssignedTransactions = array();
			$transactionsNumbersArray = array();

			foreach ($array as $key => $value) {
				$rateCheck = BookingTransact::where('tour_booking_id', $request->bookingID)->where('tour_package_rate_id', $key)->first();
				$customer = Customer::where('id', $tourBooking->customer_id)->first();
				$serviceCommissionPercentage = $bookingTransactFirst->nat_fees_percentage;
				if ($customer->is_affiliate == 'Yes') {
					$serviceCommissionPercentage = $bookingTransactFirst->nat_fees_percentage;
					if (getNextYearCommissionPercentage($info->date)) {
						$affiliateCommission = ($isBlackoutDate) ? getNormalCommissionPercentage($info->tour_package_id) : getAffiliateCommissionPercentage($customer->id);
						$serviceCommissionPercentage = ($isBlackoutDate) ? $affiliateCommission['service_commission_percentage'] : $affiliateCommission['affiliate_processing_percentage'];
					}
				} elseif (getNextYearCommissionPercentage($info->date)) {
					$serviceCommission = getNormalCommissionPercentage($info->tour_package_id);
					$serviceCommissionPercentage = $serviceCommission['service_commission_percentage'];
				}
				$subTotal = 0;
				$totalFeeAmount = 0;
				if ($rateCheck->refund_permitfee_status != '' && isset($refundedpermitarray) && !empty($refundedpermitarray) && isset($refundedpermitarray[$key])) {
					// Get the rate group type from the tour package rate
					$tourPackageRate = TourPackageRate::withTrashed()->find($key);
					$rateGroupType = $tourPackageRate->rate_for;

					// Get people being split for this rate group from checkarrays
					$peopleToSplit = [];
					if (isset($checkarrays[$key])) {
						$peopleToSplit = $checkarrays[$key];
					}

					$totalSubtotal = 0;
					$totalFee = 0;

					// Calculate cost for each person being split individually
					foreach ($peopleToSplit as $personIndex) {
						// Get current permit fee for this specific person from permitF array
						$permitKey = $rateGroupType . '.' . $personIndex;
						$currentPermitFee = isset($checkPermitfee[$permitKey]) ? floatval($checkPermitfee[$permitKey]) : $rateCheck->permit_fee;

						// Calculate rate with individual permit fee
						$rate = $rateCheck->rate + $rateCheck->additional_charge + $currentPermitFee + ($isPartnerBooking == 1 ? $rateCheck->partner_fees_amount : 0);

						$per = $rate * ($rateCheck->additional_tax_percentage + $rateCheck->processing_charge_percentage) / 100;

						$personSubtotal = $rate + $per;
						$personFee = roundout($personSubtotal * $serviceCommissionPercentage / 100);

						$totalSubtotal += $personSubtotal;
						$totalFee += $personFee;
					}

					// Format final values
					$subTotal = round($totalSubtotal, 2);
					$totalFeeAmount = round($totalFee, 2);
				} else {
					$rate = $rateCheck->rate + $rateCheck->additional_charge + $rateCheck->permit_fee + ($isPartnerBooking == 1 ? $rateCheck->partner_fees_amount : 0);
					$per = $rate * ($rateCheck->additional_tax_percentage + $rateCheck->processing_charge_percentage) / 100;
					$subTotal = ($rate + $per);
					$totalFeeAmount = roundout($subTotal * $serviceCommissionPercentage / 100);
					$perPaxTotal[$key] = $subTotal + $totalFeeAmount;
					$totalFeeAmount = $value * $totalFeeAmount;
					$subTotal = number_format((float)$subTotal * $value, 2, '.', '');
				}

				$totalAmount = 0;
				$remainingAmount = $totalAmount = $subTotal + $totalFeeAmount;

				if ($request->payment_type != 'Cash') {
					foreach ($transactionsArray as $transNo => $amount) {

						if ($amount > 0 && $remainingAmount > 0) {
							if ($amount > $remainingAmount) {
								$rateGroupWiseAssignedTransactions[$key][] = $transNo;
								$transactionsArray[$transNo] -= $remainingAmount;
								if ($totalAmount == $remainingAmount) {
									$rateGroupWiseCalculatedAmounts[$key][] = $totalAmount;
								} else {
									$rateGroupWiseCalculatedAmounts[$key][] = $remainingAmount;
								}
								$remainingAmount = 0;
								if (!in_array($transNo, $transactionsNumbersArray)) {
									$transactionsNumbersArray[] = $transNo;
								}
							} else {

								if ($amount >= $totalAmount) {
									//($w < $totalAmount && $w==$remainingAmount) || ($w < $totalAmount && $totalAmount==$remainingAmount)
									$rateGroupWiseAssignedTransactions[$key][] = $transNo;
									$transactionsArray[$transNo] -= $remainingAmount;
									$remainingAmount -= $amount;
									$rateGroupWiseCalculatedAmounts[$key][] = $amount;
									if (!in_array($transNo, $transactionsNumbersArray)) {
										$transactionsNumbersArray[] = $transNo;
									}
								} else if ($amount < $totalAmount || $totalAmount == $remainingAmount) {
									$rateGroupWiseAssignedTransactions[$key][] = $transNo;
									$transactionsArray[$transNo] -= $remainingAmount;
									$remainingAmount -= $amount;
									$rateGroupWiseCalculatedAmounts[$key][] = $amount;
									if (!in_array($transNo, $transactionsNumbersArray)) {
										$transactionsNumbersArray[] = $transNo;
									}
								}
							}
						}
					}
				}
				$totalPaymentAmountForNewBooking += $totalAmount;
			}

			if ($request->has('is_calculate_amount') && $request->is_calculate_amount == '1') {
				return [
					'total' => number_format((float)$totalPaymentAmountForNewBooking, 2, '.', '')
				];
			}

			if ($splitpopUpoff == 0 && $request->payment_type == 'Card') {
				if ($totalAvailableAmount < $totalPaymentAmountForNewBooking || empty($rateGroupWiseAssignedTransactions) || $remainingAmount > 0 || empty($rateGroupWiseCalculatedAmounts)) {
					throw new Exception("Refund amount is greater than transactions amount,Please choose proper pax and accounts to split booking.");
				}
			}
			$liabilityWaiverSigning = $tourBooking->liability_waiver_signing;
			$touristsNumbers = ($request->has('tourits_numbers')) ? $request->input('tourits_numbers') : '';
			$liabilityWaiverSigningSplitBooking = [];
			if ($liabilityWaiverSigning != '' && $touristsNumbers != '') {
				$liabilityWaiverSigning = json_decode($liabilityWaiverSigning);
				if ($touristsNumbers != '') {
					$touristNumberArray = explode(",", $touristsNumbers);
					$touristsCount = 0;
					if (!empty($touristNumberArray)) {
						foreach ($touristNumberArray as $k => $value) {
							if (isset($liabilityWaiverSigning[$value])) {
								$liabilityWaiverSigningSplitBooking[$touristsCount] = $liabilityWaiverSigning[$value];
								unset($liabilityWaiverSigning[$value]);
								$touristsCount++;
							}
						}
					}
					TourBooking::where('id', $request->bookingID)
						->update([
							'liability_waiver_signing' => (!empty($liabilityWaiverSigning)) ? json_encode((array_values($liabilityWaiverSigning))) : ''
						]);
				}
			}

			if (!empty($updatedCheckIns)) {
				foreach ($updatedCheckIns as $key => $value) {

					BookingTransact::where('tour_booking_id', $request->bookingID)->where('tour_package_rate_id', $key)
						->update([
							'check_in_status' => (!empty($value)) ? json_encode((array_values($value))) : ''
						]);
				}
			}

			if (!empty($updatedRedeemTickets)) {
				foreach ($updatedRedeemTickets as $key => $value) {
					BookingTransact::where('tour_booking_id', $request->bookingID)->where('tour_package_rate_id', $key)
						->update([
							'redeem_ticket' => (!empty($value)) ? json_encode((array_values($value))) : ''
						]);
				}
			}

			if (!empty($updatedRefundedPermitfees)) {
				foreach ($updatedRefundedPermitfees as $key => $value) {
					if (!empty($value)) {
						BookingTransact::where('tour_booking_id', $request->bookingID)->where('tour_package_rate_id', $key)
							->update([
								'refund_permitfee_status' => (!empty($value)) ? json_encode(array_values($value)) : '',
							]);
					}
				}
			}
			$totalForNewBooking = 0;
			$serviceCommissionTotal = 0;
			$totalTourists = 0;
			$totalInfants = 0;
			$touristGroups = [];

			$paymentTransactionsForNewBooking = array();
			$totalAssignedAmount = 0;
			$totalFeesAmount = 0;
			$partnerFeesAmount = 0;
			$applicationFeesId = '';
			$firstTransactionId = '';
			$txnHistoryIds = [];
			foreach ($array as $key => $value) {
				$rateCheck = BookingTransact::where('tour_booking_id', $request->bookingID)->where('tour_package_rate_id', $key)->first();
				$rateCheckname = TourPackageRate::where('id', $key)->first();
				$isInfantRate = $rateCheck->is_infant_rate;
				$customer = Customer::where('id', $tourBooking->customer_id)->first();
				$serviceCommissionPercentage = $TourPackage->service_commission_percentage;
				if ($customer->is_affiliate == 'Yes' && !$isBlackoutDate) {
					$serviceCommissionPercentage = $customer->affiliate_processing_percentage;
					if (getNextYearCommissionPercentage($info->date)) {
						$affiliateCommission = getAffiliateCommissionPercentage($customer->id);
						$serviceCommissionPercentage = $affiliateCommission['affiliate_processing_percentage'];
					}
				} elseif (getNextYearCommissionPercentage($info->date)) {
					$serviceCommission = getNormalCommissionPercentage($info->tour_package_id);
					$serviceCommissionPercentage = $serviceCommission['service_commission_percentage'];
				}
				$subtotal = 0;
				$totalFeeAmount = 0;
				if ($rateCheck->refund_permitfee_status != '' && isset($refundedpermitarray) && !empty($refundedpermitarray) && isset($refundedpermitarray[$key])) {
					// Get the rate group type from the tour package rate
					$rateGroupType = $rateCheckname->rate_for;

					// Get people being split for this rate group from checkarrays
					$peopleToSplit = [];
					if (isset($checkarrays[$key])) {
						$peopleToSplit = $checkarrays[$key];
					}

					$totalSubtotal = 0;
					$totalFee = 0;

					// Calculate cost for each person being split individually
					foreach ($peopleToSplit as $personIndex) {
						// Get current permit fee for this specific person from permitF array
						$permitKey = $rateGroupType . '.' . $personIndex;
						$currentPermitFee = isset($checkPermitfee[$permitKey]) ? floatval($checkPermitfee[$permitKey]) : $rateCheck->permit_fee;

						// Calculate rate with individual permit fee
						$rate = $rateCheck->rate + $rateCheck->additional_charge + $currentPermitFee + ($isPartnerBooking == 1 ? $rateCheck->partner_fees_amount : 0);

						$per = $rate * ($rateCheck->additional_tax_percentage + $rateCheck->processing_charge_percentage) / 100;

						$personSubtotal = $rate + $per;
						$personFee = roundout($personSubtotal * $serviceCommissionPercentage / 100);

						$totalSubtotal += $personSubtotal;
						$totalFee += $personFee;
					}

					// Format final values
					$subtotal = round($totalSubtotal, 2);
					$totalFeeAmount = round($totalFee, 2);

					// Calculate ticket cost for partner booking
					if ($isPartnerBooking == 1) {
						$ticketCostTotal = 0;
						foreach ($peopleToSplit as $personIndex) {
							$permitKey = $rateGroupType . '.' . $personIndex;
							$currentPermitFee = isset($checkPermitfee[$permitKey]) ? floatval($checkPermitfee[$permitKey]) : $rateCheck->permit_fee;
							$ticketCostTotal += $rateCheck->rate + $rateCheck->additional_charge + $currentPermitFee;
						}
						$ticketCostForRateGroup = $ticketCostTotal / count($peopleToSplit);
					} else {
						$ticketCostForRateGroup = 0;
					}
				} else {
					$rate = $rateCheck->rate + $rateCheck->additional_charge + $rateCheck->permit_fee + ($isPartnerBooking == 1 ? $rateCheck->partner_fees_amount : 0);
					$ticketCostForRateGroup = ($isPartnerBooking == 1) ? $rateCheck->rate + $rateCheck->additional_charge + $rateCheck->permit_fee : 0;
					$per = $rate * ($rateCheck->additional_tax_percentage + $rateCheck->processing_charge_percentage) / 100;
					$subtotal = ($rate + $per);
					$totalFeeAmount = (roundout(($subtotal * $serviceCommissionPercentage) / 100, 2)) * $value;
					$subtotal = number_format((float)$subtotal * $value, 2, '.', '');
				}

				$ticketCost = number_format((float)$ticketCostForRateGroup * $value, 2, '.', '');
				$partnerFees = ($isPartnerBooking == 1) ? $rateCheck->partner_fees_amount * $value : 0;
				$partnerFeesAmount += $partnerFees;

				$TourBookingInfo = TourBooking::find($request->bookingID);
				$refundedPermitFeesDetails = (isset($updatedRefundedPermitfeesForSplitBooking[$key]) && !empty($updatedRefundedPermitfeesForSplitBooking[$key])) ? json_encode((array_values($updatedRefundedPermitfeesForSplitBooking[$key]))) : '';
				$checkInsDetails = (isset($updatedCheckInsForSplitBooking[$key]) && !empty($updatedCheckInsForSplitBooking[$key])) ? json_encode((array_values($updatedCheckInsForSplitBooking[$key]))) : '';
				$redeemTicketDetails = (isset($updatedRedeemTicketsForSplitBooking[$key]) && !empty($updatedRedeemTicketsForSplitBooking[$key])) ? json_encode((array_values($updatedRedeemTicketsForSplitBooking[$key]))) : '';
				if ($isInfantRate == 1) {
					$totalInfants += $value;
				} else {
					$totalTourists += $value;
				}
				$setTotal = $rateCheck->total - $subtotal;
				$setTourists = ($isInfantRate == 1) ? $rateCheck->tourists : $rateCheck->tourists - $value;
				$setInfants = ($isInfantRate == 1) ? $rateCheck->infants - $value : $rateCheck->infants;

				$setFeeAmount = 0;
				$setFeeAmount = $TourBookingInfo->service_commission - (float)$totalFeeAmount;
				$setFeeAmount = number_format((float)$setFeeAmount, 2, '.', '');
				$serviceCommissionTotal += $totalFeeAmount;
				$totalForNewBooking += $subtotal + $totalFeeAmount;
				$touristGroups[] = $rateCheckname->rate_for[0] . ':' . $value;
				$tbTransact[] = [
					'tour_package_rate_id' => $key,
					'tourists' => $isInfantRate == 1 ? 0 : $value,
					'infants' => $isInfantRate == 1 ? $value : 0,
					'processing_charge_percentage' => 0,
					'nat_fees_percentage' => $rateCheck->nat_fees_percentage,
					'stripe_fees_percentage' => $rateCheck->stripe_fees_percentage,
					'partner_fees_type' => ($rateCheck->partner_fees_amount > 0) ? $rateCheck->partner_fees_type : NULL,
					'partner_fees_percentage' => $rateCheck->partner_fees_percentage,
					'partner_fees_amount' => $rateCheck->partner_fees_amount,
					'refund_permitfee_status' => $refundedPermitFeesDetails,
					'check_in_status' => $checkInsDetails,
					'is_infant_rate' => $isInfantRate,
					'redeem_ticket' => $redeemTicketDetails,
					'additional_tax_percentage' => ($rateCheck->additional_tax_percentage),
					'additional_charge' => ($rateCheck->additional_charge),
					'permit_fee' => $rateCheck->permit_fee,
					'rate' => $rateCheck->rate,
					'total' => $subtotal,
					'created_by' => Auth::id(),
					'created_by_user_type' => $userType,
					'created_at' => Carbon::now(),
				];
				$tbTransaction[] = [
					'tour_package_rate_id' => $key,
					'tourists' => $value,
					'rate_for' => $rateCheckname->rate_for
				];
				if ($setTourists == 0 && $setInfants == 0) {
					BookingTransact::where('tour_booking_id', $request->bookingID)->where('tour_package_rate_id', $key)
						->update([
							'total' => $setTotal,
							'tourists' => $setTourists,
							'infants' => $setInfants,
							'check_in_status' => '',
							'redeem_ticket' => null,
							'refund_permitfee_status' => ''
						]);
				} else {
					BookingTransact::where('tour_booking_id', $request->bookingID)->where('tour_package_rate_id', $key)
						->update([
							'total' => $setTotal,
							'tourists' => $setTourists,
							'infants' => $setInfants,
						]);
				}
				DB::commit();
				$total = $subtotal + $totalFeeAmount;
				$tourBookingSetTotal = $TourBookingInfo->total - $total;
				$tourBookingSetTourist = ($isInfantRate == 1) ? $TourBookingInfo->tourists : $TourBookingInfo->tourists - $value;
				$tourBookingSetInfant = ($isInfantRate == 1) ? $TourBookingInfo->infants - $value : $TourBookingInfo->infants;
				TourBooking::where('id', $request->bookingID)->update([
					'total' => $tourBookingSetTotal,
					'tourists' => $tourBookingSetTourist,
					'infants' => $tourBookingSetInfant,
					'service_commission' => $setFeeAmount
				]);
				DB::commit();

				//This is for multiple entries of refunds and payments(for new booking) as per split payments
				if ($request->payment_type != 'Cash' && isset($rateGroupWiseAssignedTransactions[$key])) {
					foreach ($rateGroupWiseAssignedTransactions[$key] as $k => $assignedTransaction) {
						$trans = Transaction::where('tour_booking_id', $request->bookingID)->where('transaction_number', $assignedTransaction)->where('status', 'Completed')->whereNull('charge_type')->orderBy('id', 'DESC')->first();
						$bookingHistoryFirstPayment = TourBookingHistory::where('tour_booking_id', $request->bookingID)->where('transaction_id', $trans->id)->where('operation', 'Payment')->orderBy('id', 'DESC')->first();
						$detailsHistory = json_decode($bookingHistoryFirstPayment->details);
						$assignedAmount = 0;
						$platformFee = 0;
						$assignedAmount = (isset($rateGroupWiseCalculatedAmounts[$key][$k])) ? $rateGroupWiseCalculatedAmounts[$key][$k] : 0;
						$platformFee = $assignedAmount * $rateCheck->stripe_fees_percentage / 100;
						if (count($transactionsNumbersArray) == 1) {
							$totalAssignedAmount += $assignedAmount;
							$totalFeesAmount += $platformFee;
							$applicationFeesId = $trans->application_fee_id;
							$firstTransactionId = $trans->id;
							$firstTransactionMode = $trans->payment_mode;
						} else {
							$paymentTransactionsForNewBooking[] = [
								'amount' => $assignedAmount,
								'fee_amount' => $platformFee,
								'partner_fees' => $partnerFees,
								'transaction_number' => $assignedTransaction,
								'application_fee_id' => $trans->application_fee_id,
								'transaction_id' => $trans->id,
								'payment_mode' => $trans->payment_mode
							];
						}
						if (!empty($transF) && $transF->payment_type != 1 || $total == 0) {
							$refundType = "100 % Refund";
						} else {
							$refundType = "Don't Refund";
						}
						$bookingInfo = TourBooking::find($request->bookingID);
						$TourBookingHistoryId = HistoryService::storeRefundDataInHistory(
							$request->bookingID,
							$bookingInfo->tourists + $bookingInfo->infants,
							$createdByWithName,
							null,
							$refundType,
							null,
							$assignedAmount,
							$detailsHistory->card_number ?? '',
							[],
							[],
							'Card',
							null,
							null,
							null,
							$rateCheckname->rate_for . ' person ' . $value,
							0,
							'Extend Booking',
							($refundType == "100 % Refund") ? $partnerFees : 0
						);
						DB::commit();
						$txnHistoryIds[] = $TourBookingHistoryId;
						if (!empty($transF) && $transF->payment_type != 1) {
							$refundTransId = TransactionService::storeRefundInTransaction(
								$request->bookingID,
								$assignedAmount,
								$platformFee,
								'Card',
								$assignedTransaction,
								$trans->application_fee_id,
								null,
								true,
								mode: $trans->payment_mode
							);
							DB::commit();
							if ($isPartnerBooking == 1 && $ticketCost > 0 && $refundType == '100 % Refund') {
								$transactionId = $trans->id;
								ThreeWayPaymentService::storeReverseTransferInPayout($request->bookingID, $transactionId, $refundTransId ?? null, $ticketCost, $partnerFees);
							}
							DeletePerson100RefundService::run($TourBookingInfo, $TourBookingHistoryId, $request, 'Card', $refundTransId);
							DB::commit();
						}
					}
				} else {
					$bookingHistoryFirstPayment = TourBookingHistory::where('tour_booking_id', $request->bookingID)->where('operation', 'Payment')->orderBy('id', 'DESC')->first();
					$detailsHistory = json_decode($bookingHistoryFirstPayment->details);

					if (!empty($transF) && $transF->payment_type != 1 || $total == 0) {
						$refundType = "100 % Refund";
					} else {
						$refundType = "Don't Refund";
					}

					$bookingInfo = TourBooking::find($request->bookingID);
					$TourBookingHistoryId = HistoryService::storeRefundDataInHistory(
						$request->bookingID,
						$bookingInfo->tourists + $bookingInfo->infants,
						$createdByWithName,
						null,
						$refundType,
						null,
						0,
						$detailsHistory->card_number ?? '',
						[],
						[],
						'Card',
						null,
						null,
						null,
						$rateCheckname->rate_for . ' person ' . $value,
						0,
						'Extend Booking',
						($refundType == '100 % Refund') ? $partnerFees : 0
					);
					DB::commit();
					$txnHistoryIds[] = $TourBookingHistoryId;
					if (!empty($transF) && $transF->payment_type != 1) {
						$refundTransId = TransactionService::storeRefundInTransaction(
							$request->bookingID,
							0,
							0,
							'Card',
							'',
							'',
							null,
							true,
							mode: $transF->payment_mode
						);
						if ($isPartnerBooking == 1 && $ticketCost > 0 && $refundType == '100 % Refund') {
							$transactionId = $transF->id;
							ThreeWayPaymentService::storeReverseTransferInPayout($request->bookingID, $transactionId, $refundTransId ?? null, $ticketCost, $partnerFees);
						}
						DB::commit();
						DeletePerson100RefundService::run($TourBookingInfo, $TourBookingHistoryId, $request, 'Card', $refundTransId);
						DB::commit();
					}
				}
			}
			DB::commit();

			if (count($transactionsNumbersArray) == 1) {
				$paymentTransactionsForNewBooking[] = [
					'amount' => $totalAssignedAmount,
					'fee_amount' => $totalFeesAmount,
					'partner_fees' => $partnerFeesAmount,
					'transaction_number' => $transactionsNumbersArray[0],
					'application_fee_id' => $applicationFeesId,
					'transaction_id' => $firstTransactionId,
					'payment_mode' => $firstTransactionMode ?? PaymentModeResolver::DESTINATION
				];
			}

			$countPaymentForNewBooking = count($paymentTransactionsForNewBooking);

			$newTourBooking = TourBooking::create([
				'tour_package_id' => $tourBooking->tour_package_id,
				'tour_slot_id' => $tourBooking->tour_slot_id,
				'customer_id' => $tourBooking->customer_id,
				'tourists' => $totalTourists,
				'infants' => $totalInfants,
				'name' => $tourBooking->name,
				'email' => $tourBooking->email,
				'phone_number' => $tourBooking->phone_number,
				'mask_required' => 'Yes',
				'service_commission' => $serviceCommissionTotal,
				'total' => $totalForNewBooking,
				'booking_total' => $totalForNewBooking,
				'liability_waiver_signing' => (!empty($liabilityWaiverSigningSplitBooking)) ? json_encode((array_values($liabilityWaiverSigningSplitBooking))) : '',
				'status' => 'Booked',
				'is_partner_booking' => $isPartnerBooking,
				'visitor' => $request->getClientIp(),
				'request_from' => $request->is('api/*') ? Constants::API : Constants::BACKEND,
				'created_by' => Auth::id(),
				'created_by_user_type' => $userType,
				'updated_at' => null,
			]);
			DB::commit();

			for ($a = 0, $ac = count($tbTransact); $a < $ac; $a++) {
				$tbTransact[$a]['tour_booking_id'] = $newTourBooking->id;
			}

			DB::table('tour_bookings_transact')->insert($tbTransact);
			DB::commit();

			// Update tour booking id in contacts table if booking package has contacts
			if ($info->has_contacts == 1) {
				if (isset($request->contacts) && $request->contacts != null) {
					try {
						$contactIds = json_decode($request->contacts, TRUE);

						if (count($contactIds) > 0) {
							foreach ($contactIds as $key => $contact) {

								$tourBookingContact = TourBookingContact::findOrFail($contact);
								$bookingTransact = BookingTransact::where('tour_booking_id', $request->bookingID)->get();

								foreach ($bookingTransact as $key => $transact) {
									$newBookingTransact = BookingTransact::where('tour_booking_id', $newTourBooking->id)->where('tour_package_rate_id', $transact->tour_package_rate_id)->first();

									if ($transact->id == $tourBookingContact->tour_booking_transact_id) {
										$tourBookingContact->update([
											'tour_booking_id' => $newTourBooking->id,
											'tour_booking_transact_id' => $newBookingTransact->id,
											'updated_at' => mysqlDT()
										]);
									}
								}
							}
						}
					} catch (Exception $ex) {
						Log::error($ex->getMessage());
					}
				}
			}

			$addedPeoplesText = array();
			if (!empty($tbTransaction)) {
				foreach ($tbTransaction as $tblTransaction) {
					$addedPeoplesText[] = $tblTransaction['rate_for'] . " person " . $tblTransaction['tourists'];
				}
			}

			HistoryService::storeTouristInHistory($request, $newTourBooking, 0, $addedPeoplesText, $totalTourists + $totalInfants, $createdByWithName);
			DB::commit();
			$paymentHistoryIds = [];
			foreach ($paymentTransactionsForNewBooking as $paymentTransactions) {
				$paymentType = ($countPaymentForNewBooking > 1) ? 1 : 0;

				if (!empty($transF) && $transF->payment_type != 1 || $total == 0) {
					$transactionId = TransactionService::storePaymentInTransaction(
						$newTourBooking->id,
						$paymentTransactions['amount'],
						$paymentTransactions['fee_amount'],
						'Card',
						$paymentType,
						$paymentTransactions['transaction_number'],
						$paymentTransactions['application_fee_id'],
						[],
						true,
						mode: $paymentTransactions['payment_mode'] ?? PaymentModeResolver::DESTINATION
					);

					//Below line is for to store payout for partner package
					if ($isPartnerBooking == 1 && $totalForNewBooking > 0 && $paymentTransactions['partner_fees'] > 0) {
						$oldTransferInfo = DB::table('payouts')
							->where('tour_booking_id', $request->bookingID)
							->where('transaction_id', $paymentTransactions['transaction_id'])
							->where('payout_status', 'Completed')
							->first();
						$payoutData = [
							'amount' => $paymentTransactions['amount'],
							'platform_fees' => $paymentTransactions['fee_amount'],
							'partner_fees' => $paymentTransactions['partner_fees'],
							'booking_id' => $newTourBooking->id,
							'transaction_id' => $transactionId,
							'package_id' => $newTourBooking->tour_package_id,
							'payment_method' => 'Card',
							'partner_destination_id' => $info->stripe_destination_id,
							'transfer_id' => $oldTransferInfo->transfer_id ?? null,
						];
						ThreeWayPaymentService::storeTransferInPayout($payoutData);
					}
					DB::commit();

					$paymentHistdata = [
						'total' => $paymentTransactions['amount'],
						'payment_type' => 'Card',
						'payment_method' => ($paymentType == '1') ? 'Split Payment' : 'Full Payment'
					];
					$historyId = HistoryService::storeCardPaymentInHistory($newTourBooking->id, $totalTourists + $totalInfants, $paymentHistdata, $detailsHistory->card_number ?? '', $detailsHistory->card_type ?? '', $createdByWithName, [], null);
					DB::commit();
					$paymentHistoryIds[] = $historyId;
				}
				$isSplitOrFull = ($paymentType == '1') ? 'Split Payment' : 'Full Payment';
				$bookingDetails['booking_id'] = $newTourBooking->id;
				$bookingDetails['customer_id'] = $newTourBooking->customer_id;
				$bookingDetails['tour_operator_id'] = $info->tour_operator_id;
				$bookingDetails['is_booking_extended'] = true;
				$bookingDetails['service_commission'] = $serviceCommissionTotal;
				$bookingDetails['is_multiple_payments'] = true;
				$bookingDetails['tour_package_id'] = $newTourBooking->tour_package_id;
				$bookingDetails['booked_from_frontend'] = $bookedFromFrontend;
				$requestData = $request->all();
				$requestData['payment_method'] = $request->payment_type;
				CardBookingService::run((array)$bookingDetails, $transactionId, $historyId, $isSplitOrFull, $requestData);
				DB::commit();
			}
			if ($totalForNewBooking == 0) {
				$transactionId = TransactionService::storePaymentInTransaction(
					$newTourBooking->id,
					$newTourBooking->total,
					0,
					'Card',
					0,
					$transF->transaction_number,
					$transF->application_fee_id,
					[],
					true,
					mode: $transF->payment_mode
				);
				DB::commit();

				$historyId = DB::table('tour_booking_history')
					->insertGetId([
						'tour_booking_id' => $newTourBooking->id,
						'transaction_id' => $transactionId,
						'tourists' => $newTourBooking->tourists + $newTourBooking->infants,
						'ticket_cost' => $newTourBooking->total - $newTourBooking->service_commission,
						'platform_fees' => $newTourBooking->service_commission,
						'operation' => 'Payment',
						'details' => json_encode(['amount' => $newTourBooking->total, 'payment_type' => 'Card', 'created_by_with_name' => $createdByWithName, 'payment_method' => 'Full Payment', 'card_number' => $detailsHistory->card_number ?? '', 'card_type' => $detailsHistory->card_type ?? ''], JSON_NUMERIC_CHECK),
						'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
						'operation_by' => Auth::id(),
						'operation_by_user_type' => $userType,
						'updated_at' => Carbon::now(),
						'created_at' => Carbon::now(),
					]);
				DB::commit();
				$paymentHistoryIds[] = $historyId;
				DetectPayoutAdjustment::dispatch($newTourBooking->id, $historyId, 'Payment');
			}

			// Settle price-per-pax add-ons for the split pax: an Add-ons Refund entry on the old
			// booking and an Add-ons Payment entry on the new booking, written after every tour
			// amount payment entry above so history reads tour-then-addons on both sides. Stamped
			// with the SAME transaction/card the admin picked (or the single one assigned, when
			// there was nothing to choose) for the tour amount split above, via $transactionsNumbersArray
			// / $applicationFeesId / $detailsHistory populated by that split logic.
			if (!empty($explicitAddonFieldIds)) {
				try {
					$selectedTransaction = ($request->payment_type != 'Cash' && !empty($transactionsNumbersArray)) ? [
						'transaction_number' => $transactionsNumbersArray[0] ?? null,
						'application_fee_id' => $applicationFeesId ?: null,
						'card_last4' => $detailsHistory->card_number ?? null,
					] : null;
					AddonBookingService::splitBookingPaxAddons($request->bookingID, $newTourBooking->id, $explicitAddonFieldIds, $selectedTransaction);
					// See the sibling call site above for why this must run right after
					// splitBookingPaxAddons() and before this booking gets finalized as paid.
					PaymentLinkService::movePendingAddonPaymentLinkItems($request->bookingID, $newTourBooking->id, $explicitAddonFieldIds);
				} catch (Exception $ex) {
					Log::error($ex->getMessage());
				}
			}

			HistoryService::storeSplitBookingInHistory($tourBooking->id, $newTourBooking->id, $tourists, $totalTourists + $totalInfants, 'Card', $createdByWithName, $txnHistoryIds, $paymentHistoryIds);

			$paymentOnly = TourBookingHistory::where('tour_booking_id', $request->bookingID)
				->where('operation', 'Payment')
				->get(['id', 'ticket_cost', 'processing_fees', 'platform_fees']);

			$paymentOnlyCheck = 0;
			foreach ($paymentOnly as $value) {
				$paymentOnlyCheck += $value->ticket_cost + $value->processing_fees + $value->platform_fees;
			}
			$paymentOnlyCheck = round($paymentOnlyCheck);
			$tourBookingSetTotal = round($tourBookingSetTotal);

			if ($paymentOnlyCheck >= $tourBookingSetTotal) {
				$transF->payment_type = 0;
				$transF->save();
			}

			$slotInfo = DB::table('tour_slot_times AS tst')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
				->where('tst.id', $tourBooking->tour_slot_id)
				->when(($userType == 'Tour Operator' || $userType == 'Affiliate'), function ($query) {
					$query->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
				})
				->select([
					DB::raw("(SELECT SUM(tbm.tourists)
                        FROM tour_bookings_master AS tbm
                        WHERE tbm.tour_slot_id = tst.id
                            AND tbm.status != 'Cancelled'
                        GROUP BY tbm.tour_slot_id) AS booked_seats")
				])
				->first();
			$booking = [
				'id' => $newTourBooking->id,
				'group_id' => $newTourBooking->group_id,
				'prefixedId' => bookingPrefix($newTourBooking->id),
				'cust_name' => $newTourBooking->name,
				'pkg_id' => $info->tour_package_id,
				'pkg_name' => $info->package_name,
				'paid' => $totalForNewBooking,
				'total' => $totalForNewBooking,
				'tour_slot_id' => $newTourBooking->tour_slot_id,
				'tour_d_t' => $info->date . ' ' . $info->time_from,
				'booking_d_t' => $newTourBooking->created_at,
				't_groups' => implode(' | ', $touristGroups),
				'tour_operator_id' => $info->tour_operator_id,
				'tourists' => $totalTourists,
				'infants' => $totalInfants,
				'is_affiliate' => 'No',
				'notif_for' => 'admin',
				'date' => $info->date,
				'time_from' => $info->time_from,
				'time_to' => $info->time_to,
				'seats' => $info->seats,
				'payment_method' => 'Card',
				'booked_seats' => $slotInfo->booked_seats,
				'broadcaster_user_id' => Auth::id(),
				'env' => config('app.env'),
				'invoiceStatus' => 0,
				'BookingTotal' => $newTourBooking->BookingTotal,
				'RefundAmount' => 0,
				'PaymentTotal' => $newTourBooking->BookingTotal,
				'paid_split' => null,
				'background_colour_class' => $customer->is_affiliate == 'Yes' ? 'affiliate-catch' : 'normal-booking'
			];

			BookingNew::dispatch($booking);
			$booking['notif_for'] = 'Tour Operator';
			BookingNew::dispatch($booking);

			$arEmailData = [
				'tour_d_t' => $booking['tour_d_t'],
				'bookingId' => $newTourBooking->id,
				'bookingGroupId' => $newTourBooking->group_id,
				'custName' => $newTourBooking->name,
				'custEmail' => $newTourBooking->email,
				'custPhone' => $newTourBooking->phone_number,
			];

			if (in_array(Auth::user()->email, config('constants.prevent_sending_emails')) == false) {
				EmailService::sendAcknowledgementEmail($info, $arEmailData);
			}
			return [
				'bookingId' => $newTourBooking->id,
				'bookingToken' => Crypt::encryptString($newTourBooking->id)
			];
		} catch (Exception $exception) {
			DB::rollBack();
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	private static function PaymentCheckBeforeSplit($request)
	{
		$check = $request->input('check', []);
		$array = array_count_values($check);
		$isCashFees = cashFees();
		$totalFeeAmount = 0;
		foreach ($array as $key => $value) {
			$transaction = Transaction::where('tour_booking_id', $request->bookingID)->where('status', 'Completed')->whereNull('charge_type')->first();

			$tourBooking = TourBooking::where('id', $request->bookingID)->where('status', 'Booked')->first();
			$customer = Customer::where('id', $tourBooking->customer_id)->first();

			$rateCheck = BookingTransact::where('tour_package_rate_id', $key)->where('tour_booking_id', $request->bookingID)->first();

			$serviceCommissionPercentage = $rateCheck->nat_fees_percentage;

			if ($request->action_type == "delete" && $rateCheck->refund_permitfee_status != '' && isset($refundedpermitarray) && !empty($refundedpermitarray) && isset($refundedpermitarray[$key])) {
				$touristrefundedpermitfeesalready = count($refundedpermitarray[$key]);
				$rate = $rateCheck->rate + $rateCheck->additional_charge + $rateCheck->permit_fee + $rateCheck->partner_fees_amount;

				if ($request->payment_type === 'Card' || ($isCashFees == '1' && $request->payment_type === 'Cash')) {
					$per = $rate * ($rateCheck->additional_tax_percentage + $rateCheck->processing_charge_percentage) / 100;
				} else {
					$per = $rate * $rateCheck->additional_tax_percentage / 100;
				}
				$baseSubtotal = $rate + $per; // per person base cost

				// Per person subtotal (no refund)
				$personSubtotal = $baseSubtotal;

				// Per person subtotal (with permit refund)
				$personSubtotalRefunded = $baseSubtotal - $rateCheck->permit_fee;

				// Commission fee for each type
				$personFee          = roundout($personSubtotal * $serviceCommissionPercentage / 100);
				$personFeeRefunded  = roundout($personSubtotalRefunded * $serviceCommissionPercentage / 100);

				// Pax split
				$normalPax   = $value - $touristrefundedpermitfeesalready;
				$refundedPax = $touristrefundedpermitfeesalready;

				// Totals
				$totalFee     = ($normalPax * $personFee) + ($refundedPax * $personFeeRefunded);
				$totalSubtotal = ($normalPax * $personSubtotal) + ($refundedPax * $personSubtotalRefunded);

				// Format if you need final display
				$subTotal = round($totalSubtotal, 2);
				$totalFeeAmount = round($totalFee, 2);
			} else {
				$rate = $rateCheck->rate + $rateCheck->additional_charge + $rateCheck->permit_fee;
				if ($request->payment_type === 'Card' || ($isCashFees == '1' && $request->payment_type === 'Cash')) {
					$per = $rate * ($rateCheck->additional_tax_percentage + $rateCheck->processing_charge_percentage) / 100;
				} else {
					$per = $rate * $rateCheck->additional_tax_percentage / 100;
				}
				$subTotal = ($rate + $per);
				$totalFeeAmount = (roundout(($subTotal * $serviceCommissionPercentage) / 100, 2)) * $value;
				$subTotal = number_format((float)$subTotal * $value, 2, '.', '');
			}

			$total = $subTotal;
			$totalFeeAmount = number_format((float)$totalFeeAmount, 2, '.', '');

			$setFeeAmount = 0;
			if ($transaction) {
				$setFeeAmount = $tourBooking->service_commission - (float)$totalFeeAmount;
				$setFeeAmount = number_format((float)$setFeeAmount, 2, '.', '');
				if ($transaction->via == "Card" || ($isCashFees == '1' && $transaction->via == 'Cash')) {
					$setTotal = $setFeeAmount + $total;
					return $setTotal;
				}
			}
		}
	}
}
