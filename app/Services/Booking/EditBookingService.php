<?php

namespace App\Services\Booking;

use App\Constants\Constants;
use App\Models\BookingTransact;
use App\Models\Customer;
use App\Models\TourBooking;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingHistory;
use App\Models\TourPackageRate;
use App\Models\Transaction;
use App\Models\TourPackage;
use App\Repositories\TourBooking\TourBookingAddonRepository;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use App\Services\DeletePerson100RefundService;
use App\Services\DeletePersonDontRefundService;
use App\Services\DeletePersonFullRefundService;
use App\Services\DeletePersonPartialRefundService;
use Illuminate\Support\Facades\DB;
use App\Exceptions\Core\GeneralCoreException;
use App\Models\SurchargeDate;
use App\Services\ThreeWayPayment\ThreeWayPaymentService;
use App\Services\TimeSlot\TimeSlotAvailabilityService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\Services\Payments\PaymentModeResolver;

class EditBookingService
{
	/**
	 * Execute the trasnaction service to save payment
	 *
	 * @param mixed $request
	 *
	 * @return mixed
	 */
	public static function deletePaxFromGroupBooking($request, $modalUrl = '', $paymentTransCountForBooking, $fromChangePeople = false, array $addonRefundDelta = []): mixed
	{
		DB::beginTransaction();
		try {
			$isCashFees = cashFees();
			$oldBookingId = oldBookingId();
			// Pre-fee ticket amount removed from a never-paid (Pay Later) booking — returned to the
			// caller so it can reduce an Unpaid booking payment link by it. Stays 0 for paid bookings.
			$tourBookingTotalForPayLater = 0;

			$check = $request->input('check', []);

			$paymentTransCountForBooking = Transaction::where('tour_booking_id', $request->bookingID)->where('status', 'Completed')->whereNull('charge_type')->count();
			if ($request->payment_type == 'Card' && $request->refund_Type != "fareharbor" && $request->is_cash == '' && $paymentTransCountForBooking > 1 && ($request->has('checked_transactions') && $request->checked_transactions == '' && $request->refund_Type != 'dont_refund')) {
				if ((isset($request->api) && $request->api == 1) || $fromChangePeople) {
					throw new Exception("You must need to select payment transaction to delete person.");
				} else {
					$message = 'You must need to select payment transaction to delete person.';
					return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
				}
			}
			if ($request->refund_Type == "partial_refund") {
				if ($request->partial_Refund_value == '') {
					if ((isset($request->api) && $request->api == 1) || $fromChangePeople) {
						throw new Exception("Please enter partial refund amount value.");
					} else {
						$message = 'Please enter partial refund amount value.';
						return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
					}
				}
			}
			$tourBooking = TourBooking::find($request->bookingID);
			$packageHasContacts = TourPackage::find($tourBooking->tour_package_id);
			$customer = Customer::find($tourBooking->customer_id);
			$affiliateId = $customer->is_affiliate == 'Yes' ? $customer->id : null;
			$createdByWithName = BookingService::createdUserInfo();

			$checkArrays = array();
			$checkInStatusArray = array();
			$redeemTicketArray = array();
			$refundedPermitFeeArray = array();
			$currentSubTotal = 0;
			$serviceCommissionPercentage = 0;

			$isCashRateBooking = false;
			if ($fromChangePeople) {
				$bookingT = DB::table('tour_bookings_transact AS tbt')
					->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
					->where('tbt.tour_booking_id', $request->bookingID)
					->where('tbt.deleted_by', 0)
					->orderByDesc('tpr.age_from')
					->orderByDesc('tpr.age_to')
					->get(['tbt.id', 'tpr.rate_for', 'tbt.tour_package_rate_id', 'tbt.tourists', 'tbt.total', 'tbt.nat_fees_percentage', 'tbt.is_cash_rate']);

				$rateGroupWiseTourists = array();
				$currentGroupRateId = 0;
				foreach ($bookingT as $value) {
					$rateGroupWiseTourists[$value->tour_package_rate_id] = $value->tourists;
					$currentGroupRateId = $value->tour_package_rate_id;
					$currentSubTotal = $value->total;
					$serviceCommissionPercentage = $value->nat_fees_percentage;
					// The existing transact row's own total already reflects whichever pricing
					// basis was actually applied at booking time (cash_rate+cash_tax for a cash-
					// rate booking, rate+additional_charge otherwise) — $newSubTotal below must be
					// derived the SAME way, or comparing them to detect "same tier, no price
					// change" (e.g. removing 1 of 3 people from a "1-3 Person Group" tier) produces
					// a bogus non-zero delta purely from mixing the two pricing bases, even though
					// the group's real price never moved.
					$isCashRateBooking = (bool) $value->is_cash_rate;
				}

				$deletedPeopleCount = 0;
				$rateGroupWiseDeletedPeople = array();
				$rateGroupWiseDeletedPeopleArray = array();

				for ($a = 0, $ac = count($request->tourists); $a < $ac; $a++) {
					$rateGroupWiseDeletedPeople[$a] = 0;
					$deletedPeopleCount += $tourBooking->tourists - $request->tourists[$a];
					$rateGroupWiseDeletedPeople[$a] = (int)$tourBooking->tourists - $request->tourists[$a];
					$rateGroupWiseDeletedPeopleArray[$currentGroupRateId] = $rateGroupWiseDeletedPeople[$a];
				}
			} else {
				$btset = BookingTransact::where('tour_booking_id', $request->bookingID)->where('deleted_by', 0)->first();
				$currentSubTotal = $btset->total;
				$isCashRateBooking = (bool) $btset->is_cash_rate;
				foreach ($check as $key => $value) {
					$explode = explode('.', $key);

					$checkArrays[$value][] = $explode[1];
					$checkInStatusArray[$value] = $btset->check_in_status;
					$redeemTicketArray[$value] = $btset->redeem_ticket;
					$refundedPermitFeeArray[$value] = $btset->refund_permitfee_status;
					if ($customer->is_affiliate == 'Yes') {
						$serviceCommissionPercentage = $btset->nat_fees_percentage;
					} else {
						$serviceCommissionPercentage = $btset->nat_fees_percentage;
					}
				}
			}


			$updatedCheckIns = array();
			$updatedRedeemTickets = array();

			if (!empty($checkArrays)) {
				foreach ($checkArrays as $k => $checkArray) {
					if (isset($checkInStatusArray[$k]) && $checkInStatusArray[$k] != '')
						$checkInStatusInfo = json_decode($checkInStatusArray[$k]);
					else
						$checkInStatusInfo = [];

					if (isset($redeemTicketArray[$k]) && $redeemTicketArray[$k] != '')
						$redeemTicketInfo = json_decode($redeemTicketArray[$k], TRUE);
					else
						$redeemTicketInfo = [];

					foreach ($checkArray as $l => $m) {
						if (isset($checkInStatusInfo[$m]) && !empty($checkInStatusInfo[$m])) {
							unset($checkInStatusInfo[$m]);
						}
						if (!empty($redeemTicketInfo)) {
							for ($y = 0; $y < count($redeemTicketInfo); $y++) {
								if (isset($redeemTicketInfo[$y]['data'][$m]) && !empty($redeemTicketInfo[$y]['data'][$m])) {
									unset($redeemTicketInfo[$y]['data'][$m]);
								}
								if (isset($redeemTicketInfo[$y]['data'])) {
									$redeemTicketInfo[$y]['data'] = array_values($redeemTicketInfo[$y]['data']);
								}
							}
						}
					}
					$updatedCheckIns[$k] = $checkInStatusInfo;
					$updatedRedeemTickets[$k] = $redeemTicketInfo;
				}
			}
			if ($fromChangePeople) {
				$array = $rateGroupWiseDeletedPeopleArray;
			} else {
				$array = array_count_values($check);
			}

			$slotInfo = DB::table('tour_slot_times AS tst')
				->where('tst.id', $tourBooking->tour_slot_id)
				->select(['tst.date'])
				->first();

			$isAffiliate = $customer->is_affiliate;
			$totalTourists = $tourBooking->tourists;
			$currentGroupRateId = '';
			$touristsSelected = 0;
			foreach ($array as $k => $v) {
				$touristsSelected += $v;
				$currentGroupRateId = $k;
			}

			$remainingTourists = $totalTourists - $touristsSelected;

			/* START: Logic to send slot info in helper */
			$standardRateDate = SurchargeDate::where('affiliate_id', $affiliateId)
				->where('tour_package_id', $tourBooking->tour_package_id)
				->where('date_range_start', '<=', $slotInfo->date)
				->where('date_range_end', '>=', $slotInfo->date)
				->orderBy('priority', 'DESC')
				->first();

			if (isset($standardRateDate) && $standardRateDate->apply_on_slots) {
				$customRateDate = isCustomDateRateEnabled($slotInfo->date, $tourBooking->tour_package_id, $affiliateId, $tourBooking->tour_slot_id);
			} else {
				$customRateDate = isCustomDateRateEnabled($slotInfo->date, $tourBooking->tour_package_id, $affiliateId);
			}
			/* END */

			$tourPackageRate = TourPackageRate::where('affiliate_id', $tourBooking->customer_id)
				->where('scheduling_year', date('Y', strtotime($slotInfo->date)))
				->when($customRateDate, function ($query) use ($customRateDate) {
					return $query->where('custom_date_id', $customRateDate);
				}, function ($query): mixed {
					return $query->whereNull('custom_date_id');
				})
				->first();

			$newGroupRate = DB::table('tour_package_rates AS tpr')
				->join('tour_packages AS tp', 'tp.id', '=', 'tpr.tour_package_id')
				->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
				->where([
					'tpr.tour_package_id' => $tourBooking->tour_package_id,
					'tpr.deleted_by' => 0
				])
				->where('scheduling_year', date('Y', strtotime($slotInfo->date)))
				->where(function ($query) use ($remainingTourists) {
					return $query->where('tpr.min_group_size', '<=', $remainingTourists)
						->where('tpr.max_group_size', '>=', $remainingTourists);
				})
				->when($isAffiliate == 'Yes' && $tourPackageRate, function ($query) use ($tourBooking) {
					return $query->where('tpr.affiliate_id', $tourBooking->customer_id);
				}, function ($query): mixed {
					return $query->whereNull('tpr.affiliate_id');
				})
				->when($customRateDate, function ($query) use ($customRateDate) {
					return $query->where('tpr.custom_date_id', $customRateDate);
				}, function ($query): mixed {
					return $query->whereNull('tpr.custom_date_id');
				})
				->first([
					'tpr.id as groupRateId',
					'tpr.rate',
					'tpr.additional_tax_percentage',
					'tpr.processing_charge_percentage',
					'tpr.additional_charge',
					'tpr.cash_rate',
					'tpr.cash_tax'
				]);

			if (!$newGroupRate) {
				throw new Exception('Unable to edit the booking.');
			}

			// $currentSubTotal (tbt.total, above) was populated from cash_rate+cash_tax when this
			// booking used cash-tier pricing ($isCashRateBooking) — the new tier's price must be
			// read from the SAME pair of columns, or comparing the two mixes two different pricing
			// bases and produces a bogus non-zero delta even when the group's real price never
			// changed tiers (e.g. removing 1 of 3 people from a "1-3 Person Group" tier, which
			// should refund exactly $0 of ticket cost, wrongly refunded the rate/additional_charge
			// vs. cash_rate/cash_tax difference instead). Also used below when persisting the
			// BookingTransact row's own rate/additional_charge, so they stay on the same basis too.
			$newGroupRateRate = $isCashRateBooking ? $newGroupRate->cash_rate : $newGroupRate->rate;
			$newGroupRateAdditionalCharge = $isCashRateBooking ? $newGroupRate->cash_tax : $newGroupRate->additional_charge;

			$rate = $newGroupRateRate + $newGroupRateAdditionalCharge;


			$newSubTotal = $rate;

			$subTotal = $newSubTotal - $currentSubTotal;

			$refundProcess = false;
			if ($subTotal < 0) {
				$refundProcess = true;
				$subTotal = abs($subTotal);
			}
			$subTotal = number_format((float)$subTotal, 2, '.', '');
			if ($request->refund_Type == "partial_refund") {
				if ($request->partial_Refund_value > $subTotal) {
					if ((isset($request->api) && $request->api == 1) || $fromChangePeople) {
						throw new Exception('Unable to refund booking. Please enter amount less than total.');
					} else {
						$message = 'Unable to refund booking. Please enter amount less than total.';
						return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
					}
				}
			}
			$transK = $transaction = Transaction::where('status', 'Completed')->where('tour_booking_id', $request->bookingID)->whereNull('charge_type')->first();

			$feesAmount = 0;
			if (!empty($transaction) && ($transaction->via == "Card" || ($isCashFees == '1' && $transaction->via == 'Cash'))) {
				if ($request->bookingID < $oldBookingId) {
					if ($customer->is_affiliate === "No") {
						$feesAmount = ($subTotal * 6) / 100;
					}
				} else {
					$feesAmount = ($subTotal * $serviceCommissionPercentage) / 100;
				}
			}

			$totalFeeAmount = number_format((float)$feesAmount, 2, '.', '');
			$total = $subTotal;
			// group booking validations for delete end
			if ($request->payment_type == 'Cash') {
				$fees = $isCashFees == 1 ? $tourBooking->service_commission : 0.00;
			} else {
				$fees = $tourBooking->service_commission;
			}
			if (($request->payment_type == 'Cash' || $request->payment_type == 'Card') && $request->refund_Type != "dont_refund") {
				$checkTotalTourist = count($check);
				foreach ($array as $key => $value) {
					$rateCheck = BookingTransact::where('tour_booking_id', $request->bookingID)->where('tour_package_rate_id', $key)->first();
					$subtotal = $rateCheck->rate + $rateCheck->additional_charge + $rateCheck->permit_fees + $fees;
				}
				//calculate refund value
				$refundOnly = tourBookingHistory::where('tour_booking_id', $request->bookingID)
					->where('operation', 'Refund')
					->where('payment_method', '!=', "Don't Refund")
					->get([
						'id',
						'details'
					]);
				$refundOnlyCheck = 0;
				foreach ($refundOnly as $value) {
					$details = json_decode($value->details, true);
					$refundOnlyCheck += $details['amount'];
				}
				if ($request->refund_Type == "partial_refund") {
					if ($request->partial_Refund_value > ((float)$tourBooking->booking_total - $refundOnlyCheck)) {
						$message = 'Unable to refund booking. Refund amount is greater than the unrefunded amount.';
						return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
					}
				}
				$calculatedRefund = ($subtotal / $totalTourists) * $checkTotalTourist;
				$availableAmount = (float)$tourBooking->booking_total - $refundOnlyCheck;
				$tolerance = 0.01;
				if (abs($calculatedRefund - $availableAmount) > $tolerance) {
					if ($calculatedRefund > $availableAmount) {
						$message = 'Unable to refund booking. Refund amount is greater than the unrefunded amount.';
						return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
					}
				}
			}
			// group booking validations for delete end

			static::checkValidationsForCardRefund($request, $subTotal, $totalFeeAmount, $modalUrl, $paymentTransCountForBooking, $refundedPermitFeeArray, $fromChangePeople);

			static::updateWaiverAndPermitFees($request, $updatedCheckIns, $updatedRedeemTickets);

			//Check split payment before delete pax
			static::splitPaymentCheckBeforeDeleteGroupRate($request, $total, $feesAmount, $modalUrl);


			//Update tour booking transact entries
			$bt = BookingTransact::where('tour_booking_id', $request->bookingID)->where('tour_package_rate_id', $currentGroupRateId)->where('deleted_by', 0)->first();

			$setTotal = $bt->total - $subTotal;
			$setTourists = $bt->tourists - $touristsSelected;
			$totalDeletedPax = $touristsSelected;
			$setFeeAmount = 0;
			if ($transaction) {
				$setFeeAmount = $tourBooking->service_commission - (float)$totalFeeAmount;
				$setFeeAmount = number_format((float)$setFeeAmount, 2, '.', '');
			}

			BookingTransact::where('tour_booking_id', $request->bookingID)->where('tour_package_rate_id', $currentGroupRateId)->where('deleted_by', 0)
				->update([
					'tour_package_rate_id' => $newGroupRate->groupRateId,
					'rate' => $newGroupRateRate,
					'additional_charge' => $newGroupRateAdditionalCharge,
					'total' => $setTotal,
					'tourists' => $setTourists,
				]);

			//Updating tour booking master table
			$tourBookingSetTotal = $tourBooking->total - $total;
			$tourBookingSetTourist = $tourBooking->tourists - $touristsSelected;
			if (!empty($transaction)) {
				if (($transaction->via == "Card" || ($isCashFees == '1' && $transaction->via == 'Cash')) && $request->refund_Type != "fareharbor") {

					if ($request->refund_Type == "dont_refund") {

						$tourBookingSetTotal = $tourBooking->total - ($total + $feesAmount);

						$platFormFeeUpdate = ($tourBookingSetTotal * $bt->stripe_fees_percentage) / 100;
						$platFormFeeUpdate = number_format((float)$platFormFeeUpdate, 2, '.', '');

						if ($transK->payment_type == 1) {
							$tourBooking->update([
								'booking_total' => $tourBookingSetTotal,
								'service_commission' => $platFormFeeUpdate
							]);
							DB::commit();
						}
					}

					if ($request->refund_Type == "100_refund") {
						$tourBookingSetTotal = $tourBooking->total - ($total + $feesAmount);
					}

					if ($request->refund_Type == "full_refund") {
						$tourBookingSetTotal = $tourBooking->total - ($total + $feesAmount);
					}

					$tourBookingSetTotal = number_format((float)$tourBookingSetTotal, 2, '.', '');
					if ($request->refund_Type != "partial_refund") {
						$tourBooking->update([
							'total' => $tourBookingSetTotal,
							'tourists' => $tourBookingSetTourist,
						]);
						DB::commit();
					}
				} else {
					if ($request->refund_Type != "partial_refund") {
						$tourBooking->update([
							'total' => $tourBookingSetTotal,
							'tourists' => $tourBookingSetTourist
						]);
						DB::commit();
					}
				}
			} else {
				$tourBookingTotalForPayLater = $total;
				if ($request->refund_Type != "partial_refund") {
					$tourBooking->update([
						'total' => $tourBookingSetTotal,
						'booking_total' => $tourBookingSetTotal,
						'tourists' => $tourBookingSetTourist
					]);
					DB::commit();
				}
			}

			static::refundProcessStripe($request, $total, $feesAmount, $transaction);

			$rateChecknameForPartialRefund = 'Person(s) ' . $touristsSelected;

			$refundDetails = static::calculateRefundAmountsForDeletePax($request, $total, $transaction, $serviceCommissionPercentage, $customer);
			$refundVia = $refundDetails['refundVia'];
			$refundKeepUs = $refundDetails['refundKeepUs'];
			$data = [
				'amount' => $refundDetails['amount'],
				'refund_keep_us' => $refundDetails['refundKeepUs'],
				'payment_type' => $refundVia,
				'deleted' => 'Person(s) ' . $touristsSelected,
				'created_by_with_name' => $createdByWithName,
				'refund_type' => $refundDetails['refundType'],
				'comment' => null
			];

			if ($request->refund_Type != "partial_refund") {
				$historyId = HistoryService::storeRefundDataInHistory(
					$request->bookingID,
					$tourBooking->tourists,
					$createdByWithName,
					$data['comment'],
					$data['refund_type'],
					null,
					$data['amount'],
					null,
					[],
					[],
					$data['payment_type'],
					null,
					null,
					$data['refund_keep_us'],
					$data['deleted']
				);

				// Remove contacts if booking package has contacts
				if ($packageHasContacts->has_contacts == 1) {
					if (isset($request->contacts) && $request->contacts != null) {
						try {
							PackageHasContactsService::deleteContacts($request->contacts);
						} catch (Exception $ex) {
							Log::error($ex->getMessage());
						}
					}
				}
			}

			$paymentOnly = TourBookingHistory::where('tour_booking_id', $request->bookingID)->where('operation', 'Payment')->get();
			$tourBookingTransaction = BookingTransact::where('tour_booking_id', $request->bookingID)->where('deleted_by', 0)->first();
			$paymentOnlyCheck = 0;
			foreach ($paymentOnly as $value) {
				$details = json_decode($value->details);
				$paymentOnlyCheck += $details->amount;
			}
			$bok = round($tourBooking->booking_total);
			$pay = round($paymentOnlyCheck);

			//Inserting data in the transactions table

			if (!empty($transaction) && $request->payment_type == 'Card' && $request->refund_Type != "fareharbor" && $request->refund_Type != "partial_refund") {

				$TS = Transaction::where('tour_booking_id', $request->bookingID)->where('status', 'Completed')->whereNull('charge_type')->orderBy('id', 'DESC')->first();

				if ($TS->payment_type == 0) {
					if ($request->has('checked_transactions') && $request->checked_transactions != '') {
						$TS1 = Transaction::where('tour_booking_id', $request->bookingID)->where('transaction_number', $request->checked_transactions)->where('status', 'Completed')->whereNull('charge_type')->first();
						$refundAmount = $request->refund_Type == "dont_refund" ? 0.00 : $refundDetails['amount'];
						$refundFeesAmount = $request->refund_Type == "100_refund" ? round($refundDetails['amount'] * $tourBookingTransaction->stripe_fees_percentage / 100, 2) : 0.00;
						$refundTransId = TransactionService::storeRefundInTransaction($request->bookingID, $refundAmount, $refundFeesAmount, $refundVia, $TS1->transaction_number, $TS1->application_fee_id, mode: $TS1->payment_mode);
					} else {
						$refundAmount = $request->refund_Type == "dont_refund" ? 0.00 : $refundDetails['amount'];
						$refundFeesAmount = $request->refund_Type == "100_refund" ? round($refundDetails['amount'] * $tourBookingTransaction->stripe_fees_percentage / 100, 2) : 0.00;
						$refundTransId = TransactionService::storeRefundInTransaction($request->bookingID, $refundAmount, $refundFeesAmount, $refundVia, $TS->transaction_number, $TS->application_fee_id, mode: $TS->payment_mode);
					}
				}

				if ($pay >= $bok) {
					$TS->payment_type = 0;
					$TS->save();
				}
			} elseif (!empty($transaction) && $request->payment_type == 'Cash' && $request->refund_Type != "partial_refund") {
				$refundAmount = $request->refund_Type == "dont_refund" ? 0.00 : $refundDetails['amount'];
				if ($isCashFees == '1') {
					$refundFeesAmount = $request->refund_Type == "dont_refund" ? 0.00 : round($refundDetails['amount'] * $tourBookingTransaction->stripe_fees_percentage / 100, 2);
				} else {
					$refundFeesAmount = 0;
				}

				$refundTransId = TransactionService::storeRefundInTransaction($request->bookingID, $refundAmount, $refundFeesAmount, $refundVia, null, null);

				DB::commit();
			} elseif (!empty($transaction) && $request->refund_Type == "fareharbor" && $request->refund_Type != "partial_refund") {
				$refundAmount = $request->refund_Type == "dont_refund" ? 0.00 : $refundDetails['amount'];
				$refundTransId = TransactionService::storeRefundInTransaction($request->bookingID, $refundAmount, 0, $refundVia, null, null);
				DB::commit();
			} elseif (!empty($transaction) && $request->refund_Type != "partial_refund") {
				// Handle third-party platforms (TripAdvisor, GetYourGuide, Headout, etc.)
				$refundAmount = $request->refund_Type == "dont_refund" ? 0.00 : $refundDetails['amount'];
				$refundTransId = TransactionService::storeRefundInTransaction($request->bookingID, $refundAmount, 0, $refundVia, null, null);
				DB::commit();
			}
			// Below line is for to update tour booking history table
			if ($request->refund_Type != "partial_refund") {
				static::handleDeletePersonHistoryUpdateJobs($tourBooking, $historyId, $request->all(), $refundVia, $refundTransId ?? null);

				// Remove pax-addons if selected from the "Update Booking" delete modal — group rate
				// bookings never got this at all (unlike the equivalent non-group "Edit Booking"
				// delete flow in V3\TourBookingRepository::editBooking()), so a checked add-on (e.g.
				// "Guest 6 Photography Package") was silently left charged/un-refunded even when the
				// admin explicitly selected it. Only wired up for the direct delete flow
				// (!$fromChangePeople) below. Runs AFTER the tour-side refund transaction/history
				// above, matching the non-group flow's own ordering.
				if ($fromChangePeople) {
					// Change People (V2): the "Change People group-rate merge" upstream in
					// V3\TourBookingRepository (the "delete-only branch" — see its own comment)
					// already computed $addonRefundDelta via ChangePeopleAddonService::
					// calculatePricedAddonDelta() and hard-deleted the removed pax's price_per_pax
					// add-on field rows via processAddons() — but never actually refunded that
					// money for a Group-rate booking (it only ever got passed into the non-group
					// sibling, deletePeopleChangePeople()). Without this, a "Weather / 100%" (or
					// any) refund on a Group-rate pax removal silently kept the add-on's money
					// charged even though its field row was already gone, and the checked add-on
					// in the "Delete Booking" sub-modal had no effect at all.
					try {
						ChangePeopleAddonService::refundRemovedPaxAddonsForRateGroup(
							$addonRefundDelta,
							$bt->id,
							$transaction,
							$refundVia,
							$request->all(),
							$tourBooking,
							$request->bookingID,
							$currentGroupRateId
						);
					} catch (Exception $ex) {
						Log::error($ex->getMessage());
					}
				} elseif ($request->filled('pax_addons_ids') || $request->filled('delete_booking_addon_scope_ids') || !empty($checkArrays[$currentGroupRateId])) {
					try {
						$addonRefundType = match ($request->refund_Type ?? 'dont_refund') {
							'full_refund'    => Constants::ADDONS_TICKET_REFUND,
							'100_refund'     => Constants::ADDONS_FULL_REFUND,
							default          => Constants::ADDONS_DONT_REFUND,
						};
						// Admin's per-add-on refund selection from the "Update Booking" modal (null
						// when not submitted — deletePaxAddons() then refunds every field in scope).
						$selectedAddonFieldIds = null;
						if ($request->has('selected_addon_field_ids') && $request->selected_addon_field_ids !== null) {
							$decoded = is_array($request->selected_addon_field_ids)
								? $request->selected_addon_field_ids
								: json_decode($request->selected_addon_field_ids, true);
							$selectedAddonFieldIds = array_map('intval', $decoded ?? []);
						}
						// $bt is this rate group's own BookingTransact row (its id is add-on fields'
						// pax_id) — same role as $rateCheck in the non-group flow.
						$explicitFieldIds = null;
						if ($request->filled('delete_booking_addon_scope_ids')) {
							$decodedScope = json_decode($request->delete_booking_addon_scope_ids, true);
							$submittedFieldIds = array_map('intval', $decodedScope ?? []);
							$explicitFieldIds = empty($submittedFieldIds) ? [] : TourBookingAddonField::whereIn('id', $submittedFieldIds)
								->where('pax_id', $bt->id)
								->pluck('id')
								->all();
						} elseif (!$request->filled('pax_addons_ids') && !empty($checkArrays[$currentGroupRateId])) {
							$explicitFieldIds = (new TourBookingAddonRepository())->resolveFieldIdsByPaxPositions(
								$bt->id,
								array_map('intval', $checkArrays[$currentGroupRateId])
							);
						}
						$targetTransactionNumber = ($request->has('checked_transactions') && $request->checked_transactions != '') ? $request->checked_transactions : null;
						// Same "reimburse by hand instead of a real Stripe refund" override the
						// tour-side refund above already applies.
						$addonManualRefundVia = null;
						if (isset($request->is_cash) && $request->is_cash != '') {
							if ($request->is_cash == 'Cash') {
								$addonManualRefundVia = 'Cash';
							} elseif ($request->is_cash == 'Wire Transfer') {
								$addonManualRefundVia = 'Check/Wire';
							}
						}
						(new TourBookingAddonRepository())->deletePaxAddons($request->bookingID, $request->pax_addons_ids ?? '[]', $addonRefundType, null, $selectedAddonFieldIds, $explicitFieldIds, $targetTransactionNumber, $addonManualRefundVia);
					} catch (Exception $ex) {
						Log::error($ex->getMessage());
					}
				}
			}
			DB::commit();

			// Partial Refund edit booking
			if ($request->refund_Type == "partial_refund") {
				$tourBooking = TourBooking::find($request->bookingID);

				if ($request->has('checked_transactions') && $request->checked_transactions != '') {
					$transac = Transaction::where('tour_booking_id', $request->bookingID)->where('transaction_number', $request->checked_transactions)->where('status', 'Completed')->whereNull('charge_type')->first();
				} else {
					$transac = Transaction::where('tour_booking_id', $request->bookingID)->where('status', 'Completed')->whereNull('charge_type')->first();
				}

				$transactionFirst = Transaction::where('tour_booking_id', $request->bookingID)->whereNull('charge_type')->first();

				if ($refundVia == "Card") {
					if ($request->partial_Refund_value > 0) {
						$refundParams = [
							'payment_intent' => $transac->transaction_number,
							'amount' => $request->partial_Refund_value * 100,
							'reverse_transfer' => true,
							'refund_application_fee' => true
						];
						app(PaymentGatewayInterface::class)->refundPayment($refundParams, $transac->payment_mode);
					}
				}

				$btp = BookingTransact::where('tour_booking_id', $request->bookingID)->where('deleted_by', 0)->get();

				$tcount = 0;
				foreach ($btp as $value) {
					$tcount += $value->tourists;
				}

				if (!empty($transactionFirst)) {
					$ptotal = $tourBooking->total - $request->partial_Refund_value;
					$tourBooking->update([
						'total' => $ptotal,
						'tourists' => $tcount,
					]);
				} else {
					$tourBooking->update([
						'total' => $tourBooking->total - $tourBookingTotalForPayLater,
						'booking_total' => $tourBooking->booking_total - $tourBookingTotalForPayLater,
						'tourists' => $tcount
					]);
				}
				DB::commit();
				if (!empty($transactionFirst)) {
					$partialValue = $request->partial_Refund_value;
				} else {
					$partialValue = 0;
					$refundKeepUs = 0;
				}
				$refundType = "Partial Refund";
				$data = [
					'amount' => $partialValue,
					'refund_keep_us' => $refundKeepUs,
					'payment_type' => $refundVia,
					'deleted' => $rateChecknameForPartialRefund,
					'created_by_with_name' => $createdByWithName,
					'refund_type' => $refundType,
					'comment' => null
				];

				$historyId = HistoryService::storeRefundDataInHistory(
					$request->bookingID,
					$tcount,
					$createdByWithName,
					$data['comment'],
					$data['refund_type'],
					null,
					$data['amount'],
					null,
					[],
					[],
					$data['payment_type'],
					null,
					null,
					$data['refund_keep_us'],
					$data['deleted']
				);

				// Remove contacts if booking package has contacts
				if ($packageHasContacts->has_contacts == 1) {
					if (isset($request->contacts) && $request->contacts != null) {
						try {
							PackageHasContactsService::deleteContacts($request->contacts);
						} catch (Exception $ex) {
							Log::error($ex->getMessage());
						}
					}
				}

				if (!empty($transactionFirst)) {
					$refundAmount = $request->partial_Refund_value;
					$refundTransId = TransactionService::storeRefundInTransaction($request->bookingID, $refundAmount, 0, $refundVia, ($refundVia == "Card") ? $transac->transaction_number : null, null, mode: ($refundVia == "Card") ? $transac->payment_mode : PaymentModeResolver::DESTINATION);
					static::handleDeletePersonHistoryUpdateJobs($tourBooking, $historyId, $request->all(), $refundVia, $refundTransId ?? null);
				}
				DB::commit();
			}
			$seatsDiff = - ($totalDeletedPax);
			TimeSlotAvailabilityService::updateSlotSummary($tourBooking->tour_slot_id, $seatsDiff);
			DB::commit();
			if ($fromChangePeople) {
				return [
					'booking_id' => $request->bookingID,
					'success' => true,
					'message' => 'Booking has been updated' . ($request->refund_Type != "dont_refund" ? ' and refund has been initiated.' : '.'),
					'tourBookingTotalForPayLater' => (float)$tourBookingTotalForPayLater
				];
			} else {
				return [
					'bookingId' => $request->bookingID,
					'tourBookingTotalForPayLater' => (float)$tourBookingTotalForPayLater
				];
			}
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			throw new GeneralCoreException($exception);
		}
	}


	private static function checkValidationsForCardRefund($request, $subTotal, $feesAmount, $modalUrl, $paymentTransCountForBooking, $refundedPermitFeeArray = [], $fromChangePeople = false)
	{
		$tourBooking = TourBooking::find($request->bookingID);
		if ($request->payment_type == 'Card' && $request->refund_Type != "fareharbor" && $request->is_cash == '' && $paymentTransCountForBooking > 1 && ($request->has('checked_transactions') && $request->checked_transactions != '')) {
			$refundTrans = Transaction::where('tour_booking_id', $request->bookingID)->where('transaction_number', $request->checked_transactions)->where('status', 'Refund')->whereNull('charge_type')->get(['id', 'amount']);
			$paymentTrans = Transaction::where('tour_booking_id', $request->bookingID)->where('transaction_number', $request->checked_transactions)->where('status', 'Completed')->whereNull('charge_type')->first(['id', 'amount']);
			$totalRefundedAmount = 0;
			foreach ($refundTrans as $ky => $val) {
				$totalRefundedAmount += $val->amount;
			}
			$amountCapturedStripe = 0;
			$amountRefundedStripe = 0;

			$paymentIntent = app(PaymentGatewayInterface::class)->retrievePayment($request->checked_transactions);
			if (!empty($paymentIntent) && isset($paymentIntent->charges) && isset($paymentIntent->charges->data[0]) && !empty($paymentIntent->charges->data[0])) {
				$amountCapturedStripe = $paymentIntent->charges->data[0]->amount_captured;
				$amountRefundedStripe = ($paymentIntent->charges->data[0]->amount_refunded != '') ? $paymentIntent->charges->data[0]->amount_refunded : 0;
			}

			//Validation check if payment transactions fully refunded or not
			if ($paymentTrans->amount - $totalRefundedAmount <= 0) {
				if ((isset($request->api) && $request->api == 1) || $fromChangePeople) {
					throw new Exception('Unable to refund booking. Selected transaction already fully refunded.Please select different one.');
				} else {
					$message = 'Unable to refund booking. Selected transaction already fully refunded. Please select different one.';
					return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
				}
			}

			$totalAmountForValidation = $subTotal;
			if ($request->refund_Type == "partial_refund") {
				if ($request->partial_Refund_value > $totalAmountForValidation) {
					if ((isset($request->api) && $request->api == 1) || $fromChangePeople) {
						throw new Exception('Unable to refund booking. Please enter amount less than total.');
					} else {
						$message = 'Unable to refund booking. Please enter amount less than total.';
						return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
					}
				}
			}
			if ($request->refund_Type != "dont_refund" && $amountCapturedStripe > 0) {
				$remainingAmountStripe = $amountCapturedStripe / 100 - $amountRefundedStripe / 100;
				if ($totalAmountForValidation > $remainingAmountStripe) {
					if ((isset($request->api) && $request->api == 1) || $fromChangePeople) {
						throw new Exception('Unable to refund booking. For the chosen transaction, the refund amount is greater than the unrefunded amount. Please choose another transaction.');
					} else {
						$message = 'Unable to refund booking. For the chosen transaction, the refund amount is greater than the unrefunded amount. Please choose another transaction.';
						return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
					}
				}
			}

			//Validation check for 100% refund when deleting persons
			if ($request->refund_Type == "100_refund" && ($totalAmountForValidation > 0)) {
				$Refund100 = $totalAmountForValidation + $feesAmount;
				$Refund100 = round($Refund100, 2);

				$rounded = round($paymentTrans->amount - $totalRefundedAmount - $Refund100, 2);
				if ($rounded < -0.05) {
					if ((isset($request->api) && $request->api == 1) || $fromChangePeople) {
						throw new Exception('Unable to refund booking. Please choose a valid transaction or person to refund.');
					} else {
						$message = 'Unable to refund booking. Please choose a valid transaction or person to refund.';
						return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
					}
				}
			}

			//Validation check for full refund minus fees when deleting persons
			if ($request->refund_Type == "full_refund" && ($totalAmountForValidation > 0)) {

				$Refund100MinusFees = round($totalAmountForValidation, 2);

				if (($paymentTrans->amount - $totalRefundedAmount - $Refund100MinusFees) < -0.05) {
					if ((isset($request->api) && $request->api == 1) || $fromChangePeople) {
						throw new Exception('Unable to refund booking. Please choose a valid transaction or person to refund.');
					} else {
						$message = 'Unable to refund booking. Please choose a valid transaction or person to refund.';
						return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
					}
				}
			}
		}
	}

	public static function updateWaiverAndPermitFees($request, $updatedCheckIns, $updatedRedeemTickets, $updatedRefundedPermitFees = [])
	{
		$tourBooking = TourBooking::find($request->bookingID);
		$liabilityWwaiverSigning = $tourBooking->liability_waiver_signing;
		$touristsNumbers = ($request->has('tourits_numbers')) ? $request->input('tourits_numbers') : '';
		if ($liabilityWwaiverSigning != '' && $touristsNumbers != '') {
			$liabilityWwaiverSigning = json_decode($liabilityWwaiverSigning);
			if ($touristsNumbers != '') {
				$touristNumberArray = explode(",", $touristsNumbers);
				if (!empty($touristNumberArray)) {
					foreach ($touristNumberArray as $k => $value) {
						if (isset($liabilityWwaiverSigning[$value])) {
							unset($liabilityWwaiverSigning[$value]);
						}
					}
				}

				$tourBooking->update([
					'liability_waiver_signing' => (!empty($liabilityWwaiverSigning)) ? json_encode((array_values($liabilityWwaiverSigning))) : ''
				]);
			}
		}

		if (!empty($updatedCheckIns)) {
			foreach ($updatedCheckIns as $key => $value) {
				if (!empty($value)) {
					BookingTransact::where('tour_booking_id', $request->bookingID)->where('tour_package_rate_id', $key)->where('deleted_by', 0)
						->update([
							'check_in_status' => (!empty($value)) ? json_encode((array_values($value))) : ''
						]);
				}
			}
		}

		if (!empty($updatedRedeemTickets)) {
			foreach ($updatedRedeemTickets as $key => $value) {
				if (!empty($value)) {
					BookingTransact::where('tour_booking_id', $request->bookingID)->where('tour_package_rate_id', $key)->where('deleted_by', 0)
						->update([
							'redeem_ticket' => (!empty($value)) ? json_encode((array_values($value))) : ''
						]);
				}
			}
		}

		if (!empty($updatedRefundedPermitFees)) {
			foreach ($updatedRefundedPermitFees as $key => $value) {
				if (!empty($value)) {
					BookingTransact::where('tour_booking_id', $request->bookingID)->where('tour_package_rate_id', $key)->where('deleted_by', 0)
						->update([
							'refund_permitfee_status' => (!empty($value)) ? json_encode(array_values($value)) : '',
						]);
				}
			}
		}
	}


	private static function refundProcessStripe($request, $total, $feesAmount, $transaction = null)
	{
		$oldBookingID = oldBookingId();
		if ($request->payment_type == 'Card' && $request->refund_Type != "fareharbor" && ($request->is_cash == '')) {
			if ($request->refund_Type == "full_refund" && ($total > 0)) {
				if ($request->has('checked_transactions') && $request->checked_transactions != '') {
					// The admin explicitly picked this transaction, which is not necessarily
					// $transaction — look up its own persisted payment_mode. No fallback if missing:
					// a checked_transactions value with no matching row is a genuine data problem.
					$checkedTransaction = Transaction::where('tour_booking_id', $request->bookingID)->where('transaction_number', $request->checked_transactions)->where('status', 'Completed')->whereNull('charge_type')->firstOrFail(['id', 'payment_mode']);
					$refundParams = [
						'payment_intent' => $request->checked_transactions,
						'amount' => $total * 100,
						'reverse_transfer' => true,
					];
					app(PaymentGatewayInterface::class)->refundPayment($refundParams, $checkedTransaction->payment_mode);
				} else {
					$refundParams = [
						'payment_intent' => $transaction->transaction_number,
						'amount' => $total * 100,
						'reverse_transfer' => true,
					];
					app(PaymentGatewayInterface::class)->refundPayment($refundParams, $transaction->payment_mode);
				}
				DB::commit();
			}

			if ($request->refund_Type == "100_refund" && ($total > 0)) {
				$Refund100 = $total + $feesAmount;
				$Refund100 = round($Refund100, 2);

				if ($request->has('checked_transactions') && $request->checked_transactions != '') {
					$refundTrans = Transaction::where('tour_booking_id', $request->bookingID)->where('transaction_number', $request->checked_transactions)->where('status', 'Refund')->whereNull('charge_type')->get(['id', 'amount']);
					$paymentTrans = Transaction::where('tour_booking_id', $request->bookingID)->where('transaction_number', $request->checked_transactions)->where('status', 'Completed')->whereNull('charge_type')->first(['id', 'amount', 'payment_mode']);

					$totalRefundedAmount = 0;
					foreach ($refundTrans as $ky => $val) {
						$totalRefundedAmount += $val->amount;
					}

					$rounded = round($paymentTrans->amount - $totalRefundedAmount - $Refund100, 2);

					if ($rounded <= -0.01) {
						$refundParams = [
							'payment_intent' => $request->checked_transactions,
							'amount' => ($Refund100 + $rounded) * 100,
							'reverse_transfer' => true,
							'refund_application_fee' => true,
						];
						app(PaymentGatewayInterface::class)->refundPayment($refundParams, $paymentTrans->payment_mode);
					} else {
						$refundParams = [
							'payment_intent' => $request->checked_transactions,
							'amount' => $Refund100 * 100,
							'reverse_transfer' => true,
							'refund_application_fee' => true,
						];
						app(PaymentGatewayInterface::class)->refundPayment($refundParams, $paymentTrans->payment_mode);
					}
				} else {
					$refundParams = [
						'payment_intent' => $transaction->transaction_number,
						'amount' => $Refund100 * 100,
						'reverse_transfer' => true,
						'refund_application_fee' => true,
					];
					app(PaymentGatewayInterface::class)->refundPayment($refundParams, $transaction->payment_mode);
				}
			}
		}
	}

	private static function calculateRefundAmountsForDeletePax($request, $total, $transaction, $serviceCommissionPercentage, $customer)
	{
		$isCashFees = cashFees();
		$oldBookingID = oldBookingId();
		$feesAmount = 0;
		if ($request->refund_Type == "dont_refund") {
			if (($request->payment_type == 'Card' || ($isCashFees == '1' && $request->payment_type == 'Cash')) && $request->refund_Type != "fareharbor") {

				if ($request->bookingID < $oldBookingID) {
					if ($customer->is_affiliate === "No") {
						$feesAmount = ($total * 6) / 100;
					}
				} else {
					$feesAmount = ($total * $serviceCommissionPercentage) / 100;
				}

				$feesAmount = number_format((float)$feesAmount, 2, '.', '');
				$total = $total + $feesAmount;
				$refundKeepUs = $total;
			} else {
				$refundKeepUs = $total;
			}

			if (empty($transaction)) {
				$total = 0;
			}

			$feesAmount = 0;
			$refundType = "Don't Refund";
		}

		if ($request->refund_Type == "partial_refund") {
			if (($request->payment_type == 'Card' || ($isCashFees == '1' && $request->payment_type == 'Cash')) && $request->refund_Type != "fareharbor") {
				if ($request->bookingID < $oldBookingID) {
					if ($customer->is_affiliate === "No") {
						$feesAmount = ($total * 6) / 100;
					}
				} else {
					$feesAmount = ($total * $serviceCommissionPercentage) / 100;
				}

				$feesAmount = number_format((float)$feesAmount, 2, '.', '');
				$total = $total + $feesAmount;
				$refundKeepUs = $total - (float)$request->partial_Refund_value;
				$refundKeepUs = number_format((float)$refundKeepUs, 2, '.', '');
				$feesAmount = 0;
			} else {
				$refundKeepUs = $total - (float)$request->partial_Refund_value;
				$refundKeepUs = number_format((float)$refundKeepUs, 2, '.', '');
			}

			if (empty($transaction)) {
				$total = 0;
			}

			$total = number_format((float)$request->partial_Refund_value, 2, '.', '');
			$refundType = "Partial Refund";
		}

		if ($request->refund_Type == "full_refund") {
			if (($request->payment_type == 'Card' || ($isCashFees == '1' && $request->payment_type == 'Cash')) && $request->refund_Type != "fareharbor") {
				$feesAmount = 0;
				if ($request->bookingID < $oldBookingID) {
					if ($customer->is_affiliate === "No") {
						$feesAmount = ($total * 6) / 100;
					}
				} else {
					$feesAmount = ($total * $serviceCommissionPercentage) / 100;
				}

				$feesAmount = number_format((float)$feesAmount, 2, '.', '');
				$refundKeepUs = $feesAmount;
				$feesAmount = 0;
			} else {
				$refundKeepUs = 0;
			}

			if (empty($transaction)) {
				$total = 0;
			}

			$refundType = "Full Refund minus Fees";
		}

		if ($request->refund_Type == "fareharbor") {
			$refundKeepUs = $total;
			$refundType = "Fareharbor";
		}


		if ($request->refund_Type == "100_refund") {
			if (($request->payment_type == 'Card' || ($isCashFees == '1' && $request->payment_type == 'Cash')) && $request->refund_Type != "fareharbor") {
				if ($request->bookingID < $oldBookingID) {
					if ($customer->is_affiliate === "No") {
						$feesAmount = ($total * 6) / 100;
					}
				} else {
					$feesAmount = ($total * $serviceCommissionPercentage) / 100;
				}

				$feesAmount = number_format((float)$feesAmount, 2, '.', '');
				$total = $total + $feesAmount;
				$refundKeepUs = 0;
			} else {
				$refundKeepUs = 0;
			}

			if (empty($transaction)) {
				$total = 0;
			}

			$refundType = "100 % Refund";
		}

		if (isset($request->is_cash) && $request->is_cash != '') {
			if ($request->is_cash == 'Cash') {
				$refundVia = 'Cash';
			} elseif ($request->is_cash == 'Wire Transfer') {
				$refundVia = 'Check/Wire';
			}
		} else {
			$refundVia = $request->payment_type;
		}


		return [
			'amount' => $total,
			'feeAmount' => $feesAmount,
			'refundKeepUs' => $refundKeepUs,
			'refundType' => $refundType,
			'refundVia' => $refundVia
		];
	}

	/**
	 * Handle the queue jobs to perform the history data updates when delete person from edit booking section
	 *
	 * @param object $tourBooking
	 * @param int $historyId
	 * @param array $request
	 * @param string $refundVia
	 * @param int $transactionId
	 *
	 * @return void
	 */
	public static function handleDeletePersonHistoryUpdateJobs($tourBooking, $historyId, $request, $refundVia, $transactionId): void
	{
		// Perform the jobs based on categories
		if ($request['refund_Type'] == "dont_refund") {
			DeletePersonDontRefundService::run($historyId, $request, $refundVia, $transactionId);
		} elseif ($request['refund_Type'] == "partial_refund" || $request['refund_Type'] == "fareharbor") {
			DeletePersonPartialRefundService::run($historyId, $request, $refundVia, $transactionId);
		} elseif ($request['refund_Type'] == "full_refund") {
			DeletePersonFullRefundService::run($historyId, $request, $refundVia, $transactionId);
		} elseif ($request['refund_Type'] == "100_refund") {
			DeletePerson100RefundService::run($tourBooking, $historyId, $request, $refundVia, $transactionId);
		}
	}


	private static function splitPaymentCheckBeforeDeleteGroupRate($request, $total, $feesAmount, $modalUrl = '', $fromChangePeople = false)
	{
		$btv = TourBooking::find($request->bookingID);
		$btvtotal = $btv->total;
		$btvtotal = (float)$btvtotal;
		$total = (float)$total;

		$tsm = Transaction::where('tour_booking_id', $request->bookingID)->where('status', 'Completed')->whereNull('charge_type')->orderBy('id', 'DESC')->first();

		if (!empty($tsm) && $tsm->payment_type == 1 && $request->refund_Type == "dont_refund") {
			$paymentOnlyCheck = 0;
			$paymentOnly = TourBookingHistory::where('tour_booking_id', $request->bookingID)->where('operation', 'Payment')->get();

			foreach ($paymentOnly as $value) {
				$details = json_decode($value->details);
				$paymentOnlyCheck += $details->amount;
			}

			$pendingAmount = $btv->total - $paymentOnlyCheck;
			$pendingAmount = round($pendingAmount);
			$deleteRequestAmount = round($total + $feesAmount);

			if ($deleteRequestAmount > $pendingAmount) {
				if ((isset($request->api) && $request->api == 1) || $fromChangePeople) {
					throw new Exception('Unable to delete booking. Please delete unpaid pax first.');
				} else {
					$message = 'Unable to delete booking. Please delete unpaid pax first.';
					return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
				}
			}
		}

		if ($request->partial_Refund_value == "NaN") {
			$nan = 0;
		} else {
			$nan = $request->partial_Refund_value;
		}

		if (($nan > $total) || ($nan > $btvtotal)) {
			if ((isset($request->api) && $request->api == 1) || $fromChangePeople) {
				throw new Exception('Unable to refund booking. Please enter amount less than total.');
			} else {
				$message = 'Unable to refund booking. Please enter amount less than total.';
				return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
			}
		}

		if (($total > $btvtotal) && ($request->partial_Refund_value == "NaN") && ($request->refund_Type != "dont_refund")) {
			if ((isset($request->api) && $request->api == 1) || $fromChangePeople) {
				throw new Exception('Unable to refund booking. Please enter amount less than total.');
			} else {
				$message = 'Unable to refund booking. Please enter amount less than total.';
				return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
			}
		}
	}

	public static function updateTblTransactChangePeople($tbltrans, $tbTransactexist, $paymentMethod = '', $isGroupBooking, $userType)
	{

		if (($tbltrans['added'] == true || $tbltrans['deleted'] == true)) {
			$statusInfo = [];
			$redeemTicketInfo = [];
			$updatesTransact = [];
			if ($tbltrans['added'] == true) {

				$updatesTransact = [
					'tourists' => DB::raw('tourists+' . $tbltrans['tourists']),
					'infants' => DB::raw('infants+' . $tbltrans['infants']),
					'permit_fee' => $tbltrans['permit_fee'] != 0 ? $tbltrans['permit_fee'] : $tbTransactexist->permit_fee,
					'total' => ($isGroupBooking == '1') ? $tbltrans['total'] : DB::raw('total+' . $tbltrans['total']),
					'updated_by' => Auth::id(),
					'updated_by_user_type' => $userType,
					'updated_at' => mysqlDT()
				];
			} else if ($tbltrans['deleted'] == true) {
				$deletedPeoplesTexts[] = $tbltrans['rate_for'] . " person " . $tbltrans['tourists'];
				$updatesTransact = [
					'tourists' => DB::raw('tourists-' . $tbltrans['tourists']),
					'infants' => DB::raw('infants-' . $tbltrans['infants']),
					'permit_fee' => $tbltrans['permit_fee'] != 0 ? $tbltrans['permit_fee'] : $tbTransactexist->permit_fee,
					'total' => ($isGroupBooking == '1') ? $tbltrans['total'] : DB::raw('total-' . $tbltrans['total']),
					'updated_by' => Auth::id(),
					'updated_by_user_type' => $userType,
					'updated_at' => mysqlDT()
				];
			}

			if ($tbTransactexist->check_in_status != '') {
				$statusInfo = json_decode($tbTransactexist->check_in_status);
				$transactTourists = '';
				if ($tbltrans['added'] == true) {
					$transactTourists = $tbltrans['tourists'] + $tbTransactexist->tourists;
				} else if ($tbltrans['deleted'] == true && $tbTransactexist->tourists >= $tbltrans['tourists']) {
					$transactTourists = $tbTransactexist->tourists - $tbltrans['tourists'];
				} else {
					$transactTourists = $tbltrans['tourists'] + $tbTransactexist->tourists;
				}

				for ($b = 0; $b < $transactTourists; $b++) {
					if (!isset($statusInfo[$b])) {
						$statusInfo[$b] = new \stdClass;
						$statusInfo[$b]->status = 'N';
					}
				}

				if ($tbltrans['deleted'] == true) {
					for ($c = $transactTourists; $c < $tbTransactexist->tourists; $c++) {
						if (isset($statusInfo[$c])) {
							unset($statusInfo[$c]);
						}
					}
				}

				$updatesTransact['check_in_status'] = (!empty($statusInfo)) ? json_encode((array_values($statusInfo))) : '';
			}

			if ($tbTransactexist->redeem_ticket != '') {
				$redeemTicketInfo = json_decode($tbTransactexist->redeem_ticket, TRUE);
				$transactTourists = '';
				if ($tbltrans['added'] == true) {
					$transactTourists = $tbltrans['tourists'] + $tbTransactexist->tourists;
				} else if ($tbltrans['deleted'] == true && $tbTransactexist->tourists >= $tbltrans['tourists']) {
					$transactTourists = $tbTransactexist->tourists - $tbltrans['tourists'];
				} else {
					$transactTourists = $tbltrans['tourists'] + $tbTransactexist->tourists;
				}

				for ($b = 0; $b < $transactTourists; $b++) {
					for ($y = 0; $y < count($redeemTicketInfo); $y++) {
						if (!isset($redeemTicketInfo[$y]['data'][$b])) {
							$redeemTicketInfo[$y]['data'][$b]['status'] = 'Active';
							$redeemTicketInfo[$y]['data'][$b]['dateTime'] = mysqlDT();
						}
					}
				}

				if ($tbltrans['deleted'] == true) {
					for ($c = $transactTourists; $c < $tbTransactexist->tourists; $c++) {
						for ($y = 0; $y < count($redeemTicketInfo); $y++) {
							if (isset($redeemTicketInfo[$y]['data'][$c])) {
								unset($redeemTicketInfo[$y]['data'][$c]);
							}
						}
					}
				}

				$updatesTransact['redeem_ticket'] = (!empty($redeemTicketInfo)) ? json_encode((array_values($redeemTicketInfo))) : '';
			}

			$tourPackageRateGroup = TourPackageRate::find($tbltrans['tour_package_rate_id']);

			if (isset($updatesTransact) && !empty($updatesTransact)) {
				if ($isGroupBooking != '1') {
					if ($paymentMethod == "Card") {
						$updatesTransact = TourBookingRepository::saveCardPermitFeeJson($updatesTransact, $tbTransactexist, $tbltrans, $tourPackageRateGroup);
					} else {
						$updatesTransact = TourBookingRepository::saveNonCardPermitFeeJson($updatesTransact, $tbTransactexist, $tbltrans);
					}
				}
				BookingTransact::where('id', $tbTransactexist->id)->update($updatesTransact);
			}
		}
	}

	public static function updateTblTransactChangeGroupPeople($tbltrans, $tbTransactexist, $newGroupRateId, $userType)
	{

		if (($tbltrans['added'] == true || $tbltrans['deleted'] == true)) {
			$statusInfo = [];
			$updatesTransact = [];
			if ($tbltrans['added'] == true) {
				$addedPeoplesTexts[] = $tbltrans['rate_for'] . " person " . $tbltrans['tourists'];
				$updatesTransact = [
					'rate' => $tbltrans['rate'],
					'additional_charge' => $tbltrans['additional_charge'],
					'partner_fees_type' => $tbltrans['partner_fees_type'],
					'partner_fees_percentage' => $tbltrans['partner_fees_percentage'],
					'partner_fees_amount' => $tbltrans['partner_fees_amount'],
					'tour_package_rate_id' => $newGroupRateId,
					'tourists' => DB::raw('tourists+' . $tbltrans['tourists']),
					'total' => $tbltrans['total'],
					'updated_by' => Auth::id(),
					'updated_by_user_type' => $userType,
					'updated_at' => mysqlDT()
				];
			} else if ($tbltrans['deleted'] == true) {
				$deletedPeoplesTexts[] = $tbltrans['rate_for'] . " person " . $tbltrans['tourists'];
				$updatesTransact = [
					'rate' => $tbltrans['rate'],
					'additional_charge' => $tbltrans['additional_charge'],
					'partner_fees_type' => $tbltrans['partner_fees_type'],
					'partner_fees_percentage' => $tbltrans['partner_fees_percentage'],
					'partner_fees_amount' => $tbltrans['partner_fees_amount'],
					'tour_package_rate_id' => $newGroupRateId,
					'tourists' => DB::raw('tourists-' . $tbltrans['tourists']),
					'total' => $tbltrans['total'],
					'updated_by' => Auth::id(),
					'updated_by_user_type' => $userType,
					'updated_at' => mysqlDT()
				];
			}
			if ($tbTransactexist->check_in_status != '') {
				$statusInfo = json_decode($tbTransactexist->check_in_status);
				$transactTourists = '';
				if ($tbltrans['added'] == true) {
					$transactTourists = $tbltrans['tourists'] + $tbTransactexist->tourists;
				} else if ($tbltrans['deleted'] == true && $tbTransactexist->tourists >= $tbltrans['tourists']) {
					$transactTourists = $tbTransactexist->tourists - $tbltrans['tourists'];
				} else {
					$transactTourists = $tbltrans['tourists'] + $tbTransactexist->tourists;
				}

				for ($b = 0; $b < $transactTourists; $b++) {
					if (!isset($statusInfo[$b])) {
						$statusInfo[$b] = new \stdClass;
						$statusInfo[$b]->status = 'N';
					}
				}

				if ($tbltrans['deleted'] == true) {
					for ($c = $transactTourists; $c < $tbTransactexist->tourists; $c++) {
						if (isset($statusInfo[$c])) {
							unset($statusInfo[$c]);
						}
					}
				}

				$updatesTransact['check_in_status'] = (!empty($statusInfo)) ? json_encode((array_values($statusInfo))) : '';
			}

			if ($tbTransactexist->redeem_ticket != '') {
				$redeemTicketInfo = json_decode($tbTransactexist->redeem_ticket, TRUE);
				$transactTourists = '';
				if ($tbltrans['added'] == true) {
					$transactTourists = $tbltrans['tourists'] + $tbTransactexist->tourists;
				} else if ($tbltrans['deleted'] == true && $tbTransactexist->tourists >= $tbltrans['tourists']) {
					$transactTourists = $tbTransactexist->tourists - $tbltrans['tourists'];
				} else {
					$transactTourists = $tbltrans['tourists'] + $tbTransactexist->tourists;
				}

				for ($b = 0; $b < $transactTourists; $b++) {
					for ($y = 0; $y < count($redeemTicketInfo); $y++) {
						if (!isset($redeemTicketInfo[$y]['data'][$b])) {
							$redeemTicketInfo[$y]['data'][$b]['status'] = 'Active';
							$redeemTicketInfo[$y]['data'][$b]['dateTime'] = mysqlDT();
						}
					}
				}

				if ($tbltrans['deleted'] == true) {
					for ($c = $transactTourists; $c < $tbTransactexist->tourists; $c++) {
						for ($y = 0; $y < count($redeemTicketInfo); $y++) {
							if (isset($redeemTicketInfo[$y]['data'][$c])) {
								unset($redeemTicketInfo[$y]['data'][$c]);
							}
						}
					}
				}

				$updatesTransact['redeem_ticket'] = (!empty($redeemTicketInfo)) ? json_encode((array_values($redeemTicketInfo))) : '';
			}

			if (isset($updatesTransact) && !empty($updatesTransact)) {
				BookingTransact::where('id', $tbTransactexist->id)->update($updatesTransact);
			}
		}
	}

	/**
	 * Process booking adjustments based on tourists and rate groups.
	 */
	public static function processCalculationsChangePeople($request, $rateGroups, $rateGroupWiseAddedPeople, $rateGroupWiseDeletedPeople, $rateGroupWiseTourists, $permitfeeAr, $natFeesPercentage, $oldGroupRateId, $oldGroupRate, $isGroupBooking, $stripeFeesPercentage = null, $isPartnerPackage = 0, $isCashPriceApply = false)
	{
		$total = $paymentTotal = $refundTotal = $serviceCommissionTotal = $serviceCommissionNewlyAddedTotal = $newlyAddedTotal = $deletedTotal = $partnerFeesTotal = $refundPartnerFeesTotal = 0;
		$touristGroups = [];
		$tbTransact = [];
		$newGroupRateId = null;

		for ($a = 0, $ac = count($request->tourists); $a < $ac; $a++) {
			if (!$request->tourists[$a]) {
				if (!isset($rateGroupWiseTourists[$request->rate_group[$a]]) || (isset($rateGroupWiseTourists[$request->rate_group[$a]]) && $rateGroupWiseTourists[$request->rate_group[$a]] == 0)) {
					continue;
				}
			}

			$tourists = 0;
			$partnerFees = 0;
			$added = false;
			$deleted = false;
			if (isset($rateGroupWiseAddedPeople[$a]) && $rateGroupWiseAddedPeople[$a] > 0) {
				$tourists = $rateGroupWiseAddedPeople[$a];
				$added = true;
			}

			if (isset($rateGroupWiseDeletedPeople[$a]) && $rateGroupWiseDeletedPeople[$a] > 0) {
				$tourists = $rateGroupWiseDeletedPeople[$a];
				$deleted = true;
			}

			$groupTotal = 0;

			for ($b = 0, $bc = count($rateGroups); $b < $bc; $b++) {
				if ($request->rate_group[$a] == $rateGroups[$b]->id) {
					$touristGroups[] = $rateGroups[$b]->rate_for[0] . ':' . $tourists;
					break;
				}
			}

			if ($b >= $bc) { // rate group not found
				throw new Exception('Unable to change people to tour package.');
			}

			$isInfantRate = $rateGroups[$b]->is_infant_rate;
			$isCashRate = $rateGroups[$b]->is_cash_rate;
			$infants = ($isInfantRate == 1) ? $tourists : 0;
			$partnerFeeType = ($isPartnerPackage == 1 && $rateGroups[$b]->rate > 0 && $rateGroups[$b]->partner_fee_type != '') ? $rateGroups[$b]->partner_fee_type : '';
			$partnerFeesValue = ($isPartnerPackage == 1 && $rateGroups[$b]->rate > 0 && $rateGroups[$b]->partner_fee_percentage > 0) ? $rateGroups[$b]->partner_fee_percentage : 0;
			$affiliateFeesValue = ($isPartnerPackage == 1 && $rateGroups[$b]->rate > 0 && $request->affiliatecust > 0 && $rateGroups[$b]->margin > 0) ? $rateGroups[$b]->margin : 0;
			$partnerFees = ($isPartnerPackage == 1 && $rateGroups[$b]->partner_fee_amount > 0 && $rateGroups[$b]->rate > 0) ? $rateGroups[$b]->partner_fee_amount : 0.00;

			// Calculate effective permit fee for added people
			// If this rate group has existing permit fee refunds, new people should use the effective permit fee
			$effectivePermitFee = ($isGroupBooking == 1) ? 0 : $permitfeeAr[$request->rate_group[$a]]; // Default to requested permit fee

			if ($added && !($isGroupBooking == '1')) {
				// Check if existing booking transactions have permit fee refund status
				$existingTransaction = BookingTransact::where('tour_booking_id', $request->bookingID)
					->where('tour_package_rate_id', $rateGroups[$b]->id)
					->where('deleted_by', 0)
					->first();
				if ($existingTransaction && $existingTransaction->refund_permitfee_status != '') {
					$refundStatus = json_decode($existingTransaction->refund_permitfee_status);

					if (!empty($refundStatus)) {
						foreach ($refundStatus as $status) {
							if (isset($status->status) && $status->status == 'Y') {
								if (isset($status->new_permit_fee)) {
									$effectivePermitFee = $status->new_permit_fee; // Use partial refund amount (e.g., $15)
								} else {
									$effectivePermitFee = 0; // Full refund case
								}
								break; // All people in this rate group should use same effective permit fee
							}
						}
					}
				}
				//If user selected 0 permit fee for added people but existing people in the same rate group have permit fee and no refunds yet, then use existing permit fee for new people instead of making it zero
				if ($existingTransaction && $existingTransaction->refund_permitfee_status == '' && $permitfeeAr[$request->rate_group[$a]] == 0 && $existingTransaction->permit_fee > 0) {
					$effectivePermitFee = $existingTransaction->permit_fee; // No refunds yet, use existing permit fee
				}
			}

			$rate = ($isCashPriceApply && $rateGroups[$b]->is_cash_rate == 1) ? $rateGroups[$b]->cash_rate : $rateGroups[$b]->rate;
			$additionalCharge = ($isCashPriceApply && $rateGroups[$b]->is_cash_rate == 1) ? $rateGroups[$b]->cash_tax : $rateGroups[$b]->additional_charge;

			// rate may be zero for one or more age/rate group and
			// if rate is non-zero then only add other amounts
			if ($isGroupBooking == '1') {
				$groupTotal = $rate + $additionalCharge + $partnerFees;
			} else {
				$groupTotal = ($tourists * ($rate +
					$additionalCharge +
					$effectivePermitFee + $partnerFees
				));
			}


			$tbTransact[] = [
				'tour_package_rate_id' => $rateGroups[$b]->id,
				'tourists' => ($infants > 0) ? 0 : $tourists,
				'infants' => $infants,
				'added' => $added,
				'deleted' => $deleted,
				'rate_for' => $rateGroups[$b]->rate_for,
				'processing_charge_percentage' => 0,
				'nat_fees_percentage' => $natFeesPercentage,
				'stripe_fees_percentage' => $stripeFeesPercentage,
				'additional_tax_percentage' => ($isGroupBooking == '1') ? 0 : $rateGroups[$b]->additional_tax_percentage,
				'additional_charge' => $additionalCharge,
				'permit_fee' => ($isGroupBooking == '1') ? 0 : $effectivePermitFee,
				'partner_fees_type' => ($isPartnerPackage == 1 && $rateGroups[$b]->rate > 0) ? $partnerFeeType : '',
				'partner_fees_percentage' => ($isPartnerPackage == 1 && $rate > 0) ? $partnerFeesValue : 0,
				'partner_fees_amount' => ($rate > 0) ? $partnerFees : 0,
				'rate' => $rate,
				'total' => (($rate == 0 && $effectivePermitFee == 0) ? 0 : $groupTotal),
				'is_group_rate' => ($isGroupBooking == '1') ? 1 : 0,
				'is_infant_rate' => $isInfantRate,
				'is_cash_rate' => ($isCashPriceApply && $rateGroups[$b]->is_cash_rate == 1) ? 1 : 0,
				'created_by' => Auth::id(),
				'created_by_user_type' => userType(),
				'created_at' => Carbon::now(),
			];

			if ($request->has('discount_type') == false) {
				if ($isGroupBooking != '1') {
					$serviceCommissionPax = roundout((($rate + $additionalCharge + (($rate > 0) ? $effectivePermitFee : 0) + $partnerFees) * $natFeesPercentage / 100), 2);
					$serviceCommissionPax = $tourists * $serviceCommissionPax;
					$serviceCommissionTotal += $serviceCommissionPax;
				}
				$groupTotal += ($groupTotal * (float)$rateGroups[$b]->processing_charge_percentage) / 100;
			}
			

			if ($isGroupBooking == '1' && $oldGroupRateId != $rateGroups[$b]->id) {
				$newGroupRateId = $rateGroups[$b]->id;
			}
			if (isset($rateGroupWiseAddedPeople[$a]) && $rateGroupWiseAddedPeople[$a] > 0) {
				// For group bookings, total/payment is handled by the rate change block below (flat rate difference)
				if ($isGroupBooking != '1') {
					$total += $groupTotal;
					$paymentTotal += $groupTotal;
				}
				$partnerFeesTotal += ($partnerFees * $tourists);
			}
			if ($isGroupBooking == '1') {
				if ($oldGroupRateId != $rateGroups[$b]->id) {
					// Charge/refund the difference between old and new group rate
					// Add pax (upgrade): e.g. old=$60, new=$100 → total=+$40, payment=+$40
					// Remove pax (downgrade): e.g. old=$100, new=$60 → total=-$40, refund=$40
					$diff = $groupTotal - $oldGroupRate;
					$total += $diff;
					if ($diff > 0) {
						$paymentTotal += $diff;
					} else {
						$refundTotal += abs($diff);
					}
				}
			} else {
				if (isset($rateGroupWiseDeletedPeople[$a]) && $rateGroupWiseDeletedPeople[$a] > 0) {
					if (isset($rateGroupWiseTourists[$request->rate_group[$a]]) && $rateGroupWiseTourists[$request->rate_group[$a]] > 0) {
						$total -= $groupTotal;
						$refundTotal += $groupTotal;
						$refundPartnerFeesTotal += ($partnerFees * $tourists);
						if ($request->tourists[$a] == 0) {
							$deletedTotal += $groupTotal;
						}
					}
				}
			}
			if ($isGroupBooking == '1') {
				$serviceCommissionTotal = roundout((($total) * $natFeesPercentage / 100), 2);
			}

			$bookingTExists = BookingTransact::where('tour_booking_id', $request->bookingID)
				->where('tour_package_rate_id', $request->rate_group[$a])
				->where('deleted_by', 0)
				->count();

			if ($bookingTExists == 0) {
				if ($request->has('discount_type') == false) {
					if ($isGroupBooking == '1') {
						$serviceCommissionNewlyAddedTotal += roundout((($total) * $natFeesPercentage / 100), 2);
					} else {
						$serviceCommissionNewlyAddedPax = roundout((($rate + $additionalCharge + $permitfeeAr[$request->rate_group[$a]] + $partnerFees) * $natFeesPercentage / 100), 2);
						$serviceCommissionNewlyAddedPax = $tourists * $serviceCommissionNewlyAddedPax;
						$serviceCommissionNewlyAddedTotal += $serviceCommissionNewlyAddedPax;
					}
				}
				$newlyAddedTotal += $groupTotal;
			}
		}
		$payoutAmount = ($partnerFeesTotal > 0) ? $total - $partnerFeesTotal : 0.00;

		return compact('total', 'paymentTotal', 'refundTotal', 'serviceCommissionTotal', 'partnerFeesTotal', 'refundPartnerFeesTotal', 'payoutAmount', 'serviceCommissionNewlyAddedTotal', 'newlyAddedTotal', 'deletedTotal', 'touristGroups', 'newGroupRateId', 'tbTransact');
	}
	/**
	 * Process booking adjustments based on tourists and rate groups.
	 */
	public static function processCalculationsSwitchPackage($rateGroups, $isGroupBooking, $isPartnerPackage, $request, $customer, $oldDiscount, $natFeesPercentage, $stripeFeesPercentage, $tourBooking, $userType)
	{
		$isPartnerBooking = $tourBooking->is_partner_booking;
		$subTotal =  0;
		$serviceCommissionTotal = 0;
		$partnerFeesTotal = 0;
		$ticketCostWithoutPartnerFees = 0;
		$addedPeoplesTexts = array();
		$tbTransact = [];

		foreach ($rateGroups as $rateGroup) {
			$groupTotal = 0;
			if ($isGroupBooking == '1') {
				$groupTotal = $rateGroup['rate'] + $rateGroup['additional_charge'] + ($isPartnerPackage == 1 ? $rateGroup['partner_fee_amount'] : 0);
				$ticketCostWithoutPartnerFees += $rateGroup['rate'] + $rateGroup['additional_charge'];
			} else {
				$groupTotal = ($rateGroup['tourists']  * ($rateGroup['rate'] +
					$rateGroup['additional_charge'] +
					$rateGroup['permit_fee'] + ($isPartnerPackage == 1 ? $rateGroup['partner_fee_amount'] : 0)
				));
				$ticketCostWithoutPartnerFees += ($rateGroup['tourists']  * ($rateGroup['rate'] +
					$rateGroup['additional_charge'] +
					$rateGroup['permit_fee']));
			}

			if ($isGroupBooking == '1') {
				if ($oldDiscount == 0) {
					$serviceCommissionPax = roundout(($groupTotal * $natFeesPercentage / 100), 2);
					$serviceCommissionTotal = $serviceCommissionPax;
				}
			} else {
				if ($oldDiscount == 0) {
					$serviceCommissionPax = roundout((($rateGroup['rate'] + $rateGroup['additional_charge'] + $rateGroup['permit_fee'] + ($isPartnerPackage == 1 ? $rateGroup['partner_fee_amount'] : 0)) * $natFeesPercentage / 100), 2);
					$serviceCommissionPax = $rateGroup['tourists'] * $serviceCommissionPax;
					$serviceCommissionTotal += $serviceCommissionPax;
				}
			}
			$partnerFeesTotal += ($rateGroup['partner_fee_amount'] * $rateGroup['tourists']);
			$isInfantRate = $rateGroup['is_infant_rate'] ?? 0;
			$tbTransact[] = [
				'tour_booking_id' => $tourBooking->id,
				'tour_package_rate_id' => $rateGroup['rate_group_id'],
				'tourists' => ($isInfantRate != 1) ? $rateGroup['tourists'] : 0,
				'infants' => ($isInfantRate == 1) ? $rateGroup['tourists'] : 0,
				'is_infant_rate' => $isInfantRate,
				'processing_charge_percentage' => 0,
				'nat_fees_percentage' => $natFeesPercentage,
				'stripe_fees_percentage' => $stripeFeesPercentage,
				'partner_fees_type' => ($isPartnerPackage == 1 && $rateGroup['partner_fee_amount'] > 0) ? $rateGroup['partner_fee_type'] : NULL,
				'partner_fees_percentage' => ($isPartnerPackage == 1) ? $rateGroup['partner_fee_percentage'] : 0,
				'partner_fees_amount' => ($isPartnerPackage == 1) ? $rateGroup['partner_fee_amount'] : 0,
				'additional_tax_percentage' => ($isPartnerPackage != '1') ? ($rateGroup['additional_tax_percentage']) : 0,
				'additional_charge' => ($rateGroup['additional_charge']),
				'permit_fee' => ($isGroupBooking != '1') ? $rateGroup['permit_fee'] : 0,
				'rate' => $rateGroup['rate'],
				'total' => $groupTotal,
				'is_group_rate' => $isGroupBooking,
				'created_by' => Auth::id(),
				'created_by_user_type' => $userType,
				'created_at' => Carbon::now(),
			];
			$addedPeoplesTexts[] = ($isGroupBooking == '1') ? 'Person(s) ' . $tourBooking->tourists : $rateGroup['rate_for'] . " person " . $rateGroup['tourists'];
			$subTotal += $groupTotal;
		}

		$payoutAmount = ($partnerFeesTotal > 0) ? $subTotal - $partnerFeesTotal : 0.00;

		$total = 0;
		if ($oldDiscount > 0) {
			$total = $subTotal - $oldDiscount;
			$serviceCommissionTotal = roundout(($total * $natFeesPercentage / 100), 2);
			$total = $total + $serviceCommissionTotal;
			$total = round($total, 2);
		} else {
			$total = $subTotal + $serviceCommissionTotal;
			$total = round($total, 2);
		}

		return compact('total', 'subTotal', 'addedPeoplesTexts', 'serviceCommissionTotal', 'tbTransact', 'partnerFeesTotal', 'ticketCostWithoutPartnerFees', 'payoutAmount');
	}

	public static function processCalculationsChangePaymentType($bookingT, $request, $info, $isGroupBooking, $natFeesPercentage, $stripeFeesPercentage, $isPartnerBooking)
	{
		$total = 0;
		$serviceCommission = 0;
		$fees = 0;
		$discountAmt = 0;
		$oldDiscountAmt = 0;
		$serviceCommissionTotal = 0;
		$partnerFeesTotal = 0;
		$payoutAmount = 0;
		$cashPriceApplied = cashPriceEnabled();
		foreach ($bookingT as $tbTransaction) {
			$rate = ($cashPriceApplied == 1) ? $tbTransaction->packageRate->rate : $tbTransaction->rate;
			$permitFee = $tbTransaction->permit_fee;
			$additionalCharge = ($cashPriceApplied == 1) ? $tbTransaction->packageRate->additional_charge : $tbTransaction->additional_charge;
			$partnerFees = $tbTransaction->partner_fees_amount;
			$tourists = $tbTransaction->tourists;

			$procFee = $tbTransaction->packageRate->processing_charge_percentage;

			if ((!$request->has('discount_type') || $request->discount_type == '') && $info->discount2_value) {
				$rateWithCharges = $rate + $additionalCharge + ($isGroupBooking != '1' ? $permitFee : 0) + $partnerFees;
				$serviceCommissionPax = roundout(($rateWithCharges * $natFeesPercentage / 100), 2);
				$serviceCommissionTotal += $isGroupBooking == '1' ? $serviceCommissionPax : $tourists * $serviceCommissionPax;
			}


			$subTotal = $isGroupBooking == '1'
				? $rate + $additionalCharge + $partnerFees
				: ($rate + $permitFee + $additionalCharge + $partnerFees) * $tourists
					- BookingService::calculatePendingPermitFeeAdjustment($tbTransaction->refund_permitfee_status, $permitFee);

			if ($isPartnerBooking == 1) {
				$payoutAmount += ($rate + $permitFee + $additionalCharge) * $tourists;
			}
			$total += $subTotal + ($subTotal * ($procFee / 100));
			$partnerFeesTotal += ($partnerFees * $tourists);

			if ($cashPriceApplied == 1) {
				$tbTransaction->rate = $rate;
				$tbTransaction->additional_charge = $additionalCharge;
				$tbTransaction->is_cash_rate = 0;
			}
			$tbTransaction->processing_charge_percentage = $procFee;
			$tbTransaction->nat_fees_percentage = $natFeesPercentage;
			$tbTransaction->stripe_fees_percentage = $stripeFeesPercentage;
			$tbTransaction->total = $procFee ? $subTotal + ($subTotal * ($procFee / 100)) : $subTotal;
			$tbTransaction->save();
		}

		return compact('total', 'serviceCommissionTotal', 'partnerFeesTotal', 'payoutAmount');
	}
	public static function processCalculationsChangePaymentTypeInvoice($bookingT, $request, $info, $isGroupBooking, $isPartnerBooking, $originalPaymentType, $newPaymentType)
	{
		$total = 0;
		$serviceCommission = 0;
		$fees = 0;
		$discountAmt = 0;
		$oldDiscountAmt = 0;
		$serviceCommissionTotal = 0;
		$partnerFeesTotal = 0;
		$payoutAmount = 0;
		$cashPriceApplied = cashPriceEnabled();
		foreach ($bookingT as $tbTransaction) {
			if ($cashPriceApplied == 1 && $newPaymentType == 'Cash') {
				$hasCashRate = $tbTransaction->packageRate->is_cash_rate == 1;
				$rate = $hasCashRate ? $tbTransaction->packageRate->cash_rate : $tbTransaction->rate;
				$additionalCharge = $hasCashRate ? $tbTransaction->packageRate->cash_tax : $tbTransaction->additional_charge;
			} elseif ($cashPriceApplied == 1 && in_array($newPaymentType, ['Invoiced', 'Pay Later'])) {
				$rate = $tbTransaction->packageRate->rate;
				$additionalCharge = $tbTransaction->packageRate->additional_charge;
			} else {
				$rate = $tbTransaction->rate;
				$additionalCharge = $tbTransaction->additional_charge;
			}
			$permitFee = $tbTransaction->permit_fee;
			$partnerFees = $tbTransaction->partner_fees_amount;
			$tourists = $tbTransaction->tourists;

			$procFee = $tbTransaction->packageRate->processing_charge_percentage;

			$subTotal = $isGroupBooking == '1'
				? $rate + $additionalCharge + $partnerFees
				: ($rate + $permitFee + $additionalCharge + $partnerFees) * $tourists
					- BookingService::calculatePendingPermitFeeAdjustment($tbTransaction->refund_permitfee_status, $permitFee);

			if ($isPartnerBooking == 1) {
				$payoutAmount += ($rate + $permitFee + $additionalCharge) * $tourists;
			}
			$total += $subTotal + ($subTotal * ($procFee / 100));
			$partnerFeesTotal += ($partnerFees * $tourists);

			if ($cashPriceApplied == 1) {
				$tbTransaction->rate = $rate;
				$tbTransaction->additional_charge = $additionalCharge;
				$tbTransaction->is_cash_rate = ($newPaymentType == 'Cash') ? 1 : 0;
			}
			$tbTransaction->processing_charge_percentage = $procFee;
			$tbTransaction->total = $procFee ? $subTotal + ($subTotal * ($procFee / 100)) : $subTotal;
			$tbTransaction->save();
		}

		return compact('total','partnerFeesTotal', 'payoutAmount');
	}

	/**
	 * Validate refund request based on refund type for delete pax.
	 *
	 * @param object $request
	 * @param $refundAmount
	 * @param $totalAmount
	 * @param $modalUrl
	 * @return mixed
	 * @throws Exception
	 */
	public static function validateRefundRequestDeletePax($request, $refundAmount, $totalAmount, $modalUrl)
	{
		if (!in_array($request->refund_Type, ['partial_refund', 'full_refund', '100_refund'])) {
			return;
		}

		if ($refundAmount > $totalAmount || $totalAmount == 0) {
			$message = match ($request->refund_Type) {
				'partial_refund' => 'Unable to refund booking. Please enter amount less than total.',
				default => 'Unable to refund booking. Refund amount is greater than the unrefunded amount.',
			};

			if (isset($request->api) && $request->api == 1) {
				throw new Exception($message);
			}

			return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
		}
	}
}
