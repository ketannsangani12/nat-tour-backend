<?php

namespace App\Services\ExtendBooking;

use App\Constants\Constants;
use App\Events\BookingNew;
use App\Exceptions\Core\GeneralCoreException;
use App\Models\BookingTransact;
use App\Models\Customer;
use App\Models\TourBooking;
use App\Models\TourPackageRate;
use App\Models\Transaction;
use App\Models\TourBookingContact;
use App\Services\Booking\BookingService;
use App\Services\Booking\EmailService;
use App\Services\Booking\HistoryService;
use App\Services\Booking\TransactionService;
use App\Services\DeletePersonFullRefundService;
use App\Services\InvoicedBookingService;
use App\Services\ThreeWayPayment\ThreeWayPaymentService;
use App\Services\Booking\AddonBookingService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExtendBookingInvoicedService
{
	/**
	 * Execute the service.
	 *
	 * @return mixed
	 */
	public static function run($request)
	{
		DB::beginTransaction();
		try {
			$check = $request->input('check', []);
			$array = array_count_values($check);
			$userType = userType();
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
				]);

			if ($userType == 'Tour Operator' || $userType == "Affiliate") {
				$info = $info->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
			}

			$info = $info->first();
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
			$trans = Transaction::where('tour_booking_id', $request->bookingID)->where('status', 'Completed')->orderBy('id', 'DESC')->first();
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

							if (isset($refundpermitfeesstatusInfo[$m]->status) && $refundpermitfeesstatusInfo[$m]->status == 'Y' && isset($refundpermitfeesstatusInfo[$m]->amount) && $refundpermitfeesstatusInfo[$m]->amount > 0) {
								$refundedpermitarray[$k][] = $m;
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
			$totalTourists = 0;
			$totalInfants = 0;
			$partnerFeesAmount = 0;
			$touristGroups = [];
			$txnHistoryIds = [];
			foreach ($array as $key => $value) {
				$rateCheck = BookingTransact::where('tour_booking_id', $request->bookingID)->where('tour_package_rate_id', $key)->first();
				$subtotal = $rateCheck->rate + $rateCheck->additional_charge + $rateCheck->permit_fee + ($isPartnerBooking == 1 ? $rateCheck->partner_fees_amount : 0);
				$ticketCostForRateGroup = ($isPartnerBooking == 1) ? $rateCheck->rate + $rateCheck->additional_charge + $rateCheck->permit_fee : 0;
				$rateCheckname = TourPackageRate::where('id', $key)->first();
				$isInfantRate = $rateCheck->is_infant_rate;
				$totalRefundedPermitFees = 0;
				//calculation for refunded permit fees in old booking
				if (isset($updatedRefundedPermitfeesForSplitBooking[$key])) {
					foreach ($updatedRefundedPermitfeesForSplitBooking[$key] as $k => $v) {
						if (isset($v->status) && $v->status == 'Y' && isset($v->amount) && $v->amount > 0) {
							$totalRefundedPermitFees += $v->amount;
						}
					}
				}
				if ($rateCheck->permit_fee > 0) {
					$subtotal = ($subtotal * $value) - $totalRefundedPermitFees;
				} else {
					$subtotal = ($subtotal * $value);
				}

				if ($isPartnerBooking == 1) {
					if ($rateCheck->permit_fee > 0) {
						$ticketCost = ($ticketCostForRateGroup * $value) - $totalRefundedPermitFees;
					} else {
						$ticketCost = ($ticketCostForRateGroup * $value);
					}
				}
				$subtotal = number_format((float)$subtotal, 2, '.', '');
				$partnerFees = ($isPartnerBooking == 1) ? $rateCheck->partner_fees_amount * $value : 0;
				$partnerFeesAmount += $partnerFees;
				$TourBookingInfo = TourBooking::find($request->bookingID);
				$refundedPermitFeesDetails = (isset($updatedRefundedPermitfeesForSplitBooking[$key]) && !empty($updatedRefundedPermitfeesForSplitBooking[$key])) ? json_encode((array_values($updatedRefundedPermitfeesForSplitBooking[$key]))) : '';
				$checkInsDetails = (isset($updatedCheckInsForSplitBooking[$key]) && !empty($updatedCheckInsForSplitBooking[$key])) ? json_encode((array_values($updatedCheckInsForSplitBooking[$key]))) : '';
				$redeemTicketDetails = (isset($updatedRedeemTicketsForSplitBooking[$key]) && !empty($updatedRedeemTicketsForSplitBooking[$key])) ? json_encode((array_values($updatedRedeemTicketsForSplitBooking[$key]))) : '';
				$totalForNewBooking += $subtotal;
				if ($isInfantRate == 1) {
					$totalInfants += $value;
				} else {
					$totalTourists += $value;
				}
				$setTotal = $rateCheck->total - $subtotal;
				$setTourists = ($isInfantRate == 1) ? $rateCheck->tourists : $rateCheck->tourists - $value;
				$setInfants = ($isInfantRate == 1) ? $rateCheck->infants - $value : $rateCheck->infants;
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

				$tourBookingSetTotal = $TourBookingInfo->total - $subtotal;
				$tourBookingSetTourist = ($isInfantRate == 1) ? $TourBookingInfo->tourists : $TourBookingInfo->tourists - $value;
				$tourBookingSetInfant = ($isInfantRate == 1) ? $TourBookingInfo->infants - $value : $TourBookingInfo->infants;
				TourBooking::where('id', $request->bookingID)->update([
					'total' => $tourBookingSetTotal,
					'tourists' => $tourBookingSetTourist,
					'infants' => $tourBookingSetInfant
				]);
				DB::commit();
				$bookingInfo = TourBooking::find($request->bookingID);
				$refundTransId = TransactionService::storeRefundInTransaction(
						$request->bookingID, 
						$subtotal, 
						0, 
						'Invoiced', 
						'',
						'',
						null,
						true
					);
				if ($isPartnerBooking == 1 && $ticketCost > 0) {
					$transactionId = $trans->id;
					ThreeWayPaymentService::storeReverseTransferInPayout($request->bookingID, $transactionId, $refundTransId ?? null, $ticketCost, $partnerFees);
				}
				$historyId = HistoryService::storeRefundDataInHistory(
					$request->bookingID,
					$bookingInfo->tourists + $bookingInfo->infants,
					$createdByWithName,
					null,
					'Full Refund minus Fees',
					null,
					$subtotal,
					'',
					[],
					[],
					'Invoiced',
					null,
					null,
					null,
					$rateCheckname->rate_for . ' person ' . $value,
					0,
					'Extend Booking',
					$partnerFees
				);
				DB::commit();
				$txnHistoryIds[] = $historyId;
				DeletePersonFullRefundService::run($historyId, $request, 'Invoiced', $refundTransId);
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
				'service_commission' => 0,
				'total' => $totalForNewBooking,
				'booking_total' => $totalForNewBooking,
				'liability_waiver_signing' => (!empty($liabilityWaiverSigningSplitBooking)) ? json_encode((array_values($liabilityWaiverSigningSplitBooking))) : '',
				'status' => 'Booked',
				'is_partner_booking' => $isPartnerBooking,
				'visitor' => $request->getClientIp(),
				'request_from' => $request->is('api/*') ? Constants::API : Constants::BACKEND,
				'expire_at' => $tourBooking->expire_at,
				'created_by' =>  Auth::id(),
				'created_by_user_type' => userType(),
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

			$transactionId = TransactionService::storePaymentInTransaction($newTourBooking->id, $totalForNewBooking, 0, 'Invoiced', 0, null, null, [], true);
			//Below line is for to store payout for partner package
			if ($isPartnerBooking == 1) {
				$payoutData = [
					'amount' => $totalForNewBooking,
					'platform_fees' => 0,
					'partner_fees' => $partnerFeesAmount,
					'booking_id' => $newTourBooking->id,
					'transaction_id' => $transactionId,
					'package_id' => $newTourBooking->tour_package_id,
					'payment_method' => 'Cash'
				];
				ThreeWayPaymentService::transferToPartner($payoutData);
			}
			DB::commit();
			$paymentHistdata = [
				'total' => $totalForNewBooking,
				'payment_type' => 'Invoiced'
			];
			$paymentHistoryId = HistoryService::storeCardPaymentInHistory($newTourBooking->id, $newTourBooking->tourists + $newTourBooking->infants, $paymentHistdata, '', '', $createdByWithName, [], null);
			DB::commit();
			$paymentHistoryIds[] = $paymentHistoryId;

			// Settle price-per-pax add-ons for the split pax: an Add-ons Refund entry on the old
			// booking and an Add-ons Payment entry on the new booking, written after the tour
			// amount's own refund/payment entries above so history reads tour-then-addons on both
			// sides.
			if (!empty($explicitAddonFieldIds)) {
				try {
					AddonBookingService::splitBookingPaxAddons($request->bookingID, $newTourBooking->id, $explicitAddonFieldIds);
				} catch (Exception $ex) {
					Log::error($ex->getMessage());
				}
			}

			HistoryService::storeSplitBookingInHistory($tourBooking->id, $newTourBooking->id, $tourists, $totalTourists + $totalInfants, 'Invoiced', $createdByWithName, $txnHistoryIds, $paymentHistoryIds);

			$isSplitOrFull =  'Full Payment';
			InvoicedBookingService::run($newTourBooking, $transactionId, $paymentHistoryId, $isSplitOrFull, $request->all());
			DB::commit();
			$slotInfo = DB::table('tour_slot_times AS tst')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
				->where('tst.id', $tourBooking->tour_slot_id)
				->when($userType == 'Tour Operator', function ($query) {
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
				'paid' =>  $totalForNewBooking,
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
				'payment_method' => 'Invoiced',
				'booked_seats' => $slotInfo->booked_seats,
				'broadcaster_user_id' => Auth::id(),
				'env' => config('app.env'),
				'invoiceStatus' => 0,
				'BookingTotal' => $newTourBooking->BookingTotal,
				'RefundAmount' => 0,
				'PaymentTotal' => $newTourBooking->BookingTotal,
				'paid_split' =>  null,
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
}
