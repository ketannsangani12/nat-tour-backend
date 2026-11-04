<?php

namespace App\Services\SwitchTourPackage;

use App\Exceptions\Core\GeneralCoreException;
use App\Models\Customer;
use App\Models\Payout;
use App\Models\TourBooking;
use App\Models\TourBookingHistory;
use App\Models\TourPackage;
use App\Models\Transaction;
use App\Services\Booking\BookingService;
use App\Services\Booking\EditBookingService;
use App\Services\Booking\HistoryService;
use App\Services\Booking\TransactionService;
use App\Services\CardBookingService;
use App\Services\CashBookingService;
use App\Services\SwitchPackageRefundService;
use App\Services\ThreeWayPayment\ThreeWayPaymentService;
use App\Services\TimeSlot\TimeSlotAvailabilityService;
use App\Services\TourPackage\TourPackageService;
use App\Services\TourPackageRate\TourPackageRateService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SwitchTourPackageCashService
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
			$isValidationCheckForBookingSwipePackage = $request->get('validation_check') === 'true';
			$tourPackageId = $request->tour_package_switch;
			$slotId = $request->tour_slot_time_switch;
			$userType = userType();
			$isCashFees = cashFees();

			$info = TourPackageService::getTourPackageSlotInfo($slotId);
			if (!$info) {
				throw new Exception('Unable to switch tour package');
			}

			$tourBooking = TourBooking::find($request->bookingID);
			$isPartnerBooking = $tourBooking->is_partner_booking;
			$oldDiscount = $tourBooking->discount2_value;
			$oldDiscountPercentage = $tourBooking->discount2_percentage;
			$isGroupBooking = $tourBooking->is_group_booking;
			$oldTourPackageId = $tourBooking->tour_package_id;
			$oldSlotId = $tourBooking->tour_slot_id;
			$oldPackage = TourPackage::findOrFail($oldTourPackageId);
			$customer = Customer::where('id', $tourBooking->customer_id)->withTrashed()->first();
			$trans = Transaction::where('tour_booking_id', $request->bookingID)->where('status', 'Completed')->orderBy('id', 'DESC')->first();

			$commissionRates = TourPackageService::getCommissionFeesPercentage($request, $customer, $info);

			// Assign the returned values to respective variables
			$natFeesPercentage = $commissionRates['nat_fees_percentage'];
			$stripeFeesPercentage = $commissionRates['stripe_fees_percentage'];

			$rateGroups = TourPackageRateService::getMappedRateGroups($tourBooking, $customer, $info);

			if (empty($rateGroups)) {
				$message = "No rate groups found. Please add rate groups for the package.";
				if ($request->is('api/*')) {
					throw new Exception($message);
				}
				return sendJsonErrMsg($message);
			}


			$createdByWithName = BookingService::createdUserInfo();
			$result = EditBookingService::processCalculationsSwitchPackage($rateGroups, $isGroupBooking, $info->is_partner_package, $request, $customer, $oldDiscount, $natFeesPercentage, $stripeFeesPercentage, $tourBooking, $userType);
			$subTotal = $result['subTotal'];
			$total = $result['total'];
			$serviceCommissionTotal = ($isCashFees == '1') ? $result['serviceCommissionTotal'] : 0;
			$partnerFeesTotal = $result['partnerFeesTotal']; //For partner booking or partner package
			$ticketCostWithoutPartnerFees = $result['ticketCostWithoutPartnerFees']; //For partner booking or partner package
			$tbTransact = $result['tbTransact'];
			$addedPeoplesTexts = $result['addedPeoplesTexts'];

			$total = 0;
			if ($isCashFees == '1') {
				if ($oldDiscount > 0) {
					$total = $subTotal - $oldDiscount;
					$serviceCommissionTotal = roundout(($total * $natFeesPercentage / 100), 2);
					$total = $total + $serviceCommissionTotal;
					$total = round($total, 2);
				} else {
					$total = $subTotal + $serviceCommissionTotal;
					$total = round($total, 2);
				}
			} else {
				if ($oldDiscount > 0) {
					$total = $subTotal - $oldDiscount;
					$total = round($total, 2);
				} else {
					$total = round($subTotal, 2);
				}
			}

			$newDiscountPercent = 0;
			if ($oldDiscountPercentage > 0) {
				$newDiscountedTotalWithoutServiceCharge = $subTotal - $oldDiscount;
				$newDiscountPercent = round((($subTotal - $newDiscountedTotalWithoutServiceCharge) * 100) / $subTotal, 2);
			}

			$refundType = '';
			if ($request->has('refund_type')) {
				$refundType = $request->refund_type;
				//This is to calculate remaining ticket cost because partial value should be deduct from ticket cost
				if ($refundType == 'partial_refund') {
					$refundType = 'full_refund';
				}

				if ($refundType == 'dont_refund') {
					$refundType = '100_refund';
				}
			}


			//Calculations of remaining refund amount for old package
			$remainingAmounts = static::calculateRemainingAmount($request->bookingID, $refundType);
			$refundProcess = false;
			$paymentAmount = $total;
			$refundTicketCostAmount = $remainingAmounts['ticketCostAmount'];
			$refundPlatformFeesAmount = $remainingAmounts['platformFeesAmount'];
			$refundPartnerFeesAmount = $remainingAmounts['partnerFeesAmount'];
			$remainingAmount = $remainingAmounts['netAmount'];
			$refundKeepus = $remainingAmounts['refundKeepus'];

			if ($request->has('refund_type')) {
				if ($request->refund_type == 'partial_refund') {
					$refundKeepus = $tourBooking->total - $request->partial_refund_value;
				}
			}

			if (!$request->is('api/*')) {
				if ($isValidationCheckForBookingSwipePackage) {
					if ($paymentAmount > $remainingAmount) {
						$netAmount = $paymentAmount - $remainingAmount;
						return [
							'payment' => 1,
							'amount' => round($netAmount, 2)
						];
					} else if ($remainingAmount > $paymentAmount) {
						return [
							'payment' => 0
						];
					}
				}
			} else {
				if ($isValidationCheckForBookingSwipePackage) {
					if ($paymentAmount > $remainingAmount) {
						$netAmount = $paymentAmount - $remainingAmount;
						return [
							'payment' => 1,
							'amount' => round($netAmount, 2)
						];
					} else if ($remainingAmount > $paymentAmount) {
						return [
							'payment' => 0
						];
					}
				}
			}
			//Net Ticket cost and Platform fees calculations
			if ($paymentAmount > $remainingAmount) {
				$refundProcess = false;
				$netTicketCostAmount = $subTotal - $oldDiscount - $refundTicketCostAmount;
				$netPlatFormFeesAmount = $serviceCommissionTotal - $refundPlatformFeesAmount;
				$netPartnerFeesAmount = $partnerFeesTotal - $refundPartnerFeesAmount;
				$netAmount = round($netTicketCostAmount + $netPlatFormFeesAmount, 2);
				$netPlatFormFeesAmount = $netPlatFormFeesAmount < 0 ? 0 : $netPlatFormFeesAmount;
				$feesAmount = roundout($netPlatFormFeesAmount, 2);
			} else if ($remainingAmount > $paymentAmount) {
				$refundProcess = true;
				$netTicketCostAmount = $subTotal - $oldDiscount - $refundTicketCostAmount;
				$netPlatFormFeesAmount = $serviceCommissionTotal - $refundPlatformFeesAmount;
				$netPartnerFeesAmount = $partnerFeesTotal - $refundPartnerFeesAmount;
				$netPartnerFeesAmount  = abs($netPartnerFeesAmount);
				$netPlatFormFeesAmount = abs($netPlatFormFeesAmount);
				$netTicketCostAmount = abs($netTicketCostAmount);
				if ($request->refund_type == '100_refund' || $request->refund_type == 'dont_refund') {
					$netAmount = round($netTicketCostAmount + $netPlatFormFeesAmount, 2);
				} else if ($request->refund_type == 'partial_refund') {
					$netAmount = $request->partial_refund_value;
				} else {
					$netAmount = round($netTicketCostAmount, 2);
				}
				$feesAmount = roundout($netPlatFormFeesAmount, 2);
			} else if ($remainingAmount == $paymentAmount) {
				$netAmount = 0;
				$feesAmount = 0;
			}

			$oldRateGroupsQry = DB::table('tour_bookings_transact AS tbt')
				->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
				->where('tbt.tour_booking_id', $request->bookingID)
				->where('tpr.tour_package_id', $tourBooking->tour_package_id)
				->where('tbt.deleted_by', 0);

			$oldRateGroups = $oldRateGroupsQry->select(['tpr.rate_for', 'tbt.tourists', 'tbt.stripe_fees_percentage', 'tbt.infants'])->get();

			$deletedPeoplesTexts = array();
			foreach ($oldRateGroups as $oldRateGroup) {
				if ($isGroupBooking == '1') {
					$deletedPeoplesTexts[] = "Person(s) " . $oldRateGroup->tourists;
				} else {
					$deletedPeoplesTexts[] = $oldRateGroup->rate_for . " person " . ($oldRateGroup->infants > 0 ? $oldRateGroup->infants : $oldRateGroup->tourists);
				}
			}

			$oldStripeFeesPercentage = $oldRateGroups[0]->stripe_fees_percentage;

			$oldRateGroupsQry->update([
				'tbt.deleted_at' => Carbon::now(),
				'tbt.deleted_by' => Auth::id()
			]);
			DB::commit();

			DB::table('tour_bookings_transact')->insert($tbTransact);
			DB::commit();

			// Determine service commission update based on refund type
			// For 100% refund: subtract the fees difference (we're refunding fees too)
			// For partial/full refund minus fees: keep the original service commission (fees are kept)
			// For payment (not refund): add the new fees difference
			if ($refundProcess) {
				if ($request->refund_type == '100_refund') {
					// For 100% refund, we need to adjust service_commission to match new package
					$actualFeesDifference = $tourBooking->service_commission - $serviceCommissionTotal;
					$serviceCommissionUpdate = DB::raw('service_commission-' . (($isCashFees == '1') ? abs($actualFeesDifference) : 0));
				} else {
					// For partial refund, full refund minus fees, and don't refund - keep original service_commission
					$serviceCommissionUpdate = $tourBooking->service_commission;
				}
			} else {
				$serviceCommissionUpdate = DB::raw('service_commission+' . (($isCashFees == '1') ? $feesAmount : 0));
			}

			$totalUpdate = DB::raw(
				'total' . ($refundProcess ? '-' : '+') . (($isCashFees == '1') ? $netAmount : $netAmount)
			);

			$updates = [
				'tour_package_id' => $tourPackageId,
				'tour_slot_id' => $slotId,
				'is_partner_booking' => ($info->is_partner_package != 1) ? 0 : $isPartnerBooking,
				'service_commission' => $serviceCommissionUpdate,
				'total' => $totalUpdate,
				'discount2_percentage' => $newDiscountPercent,
				'updated_at' => mysqlDT(),
				'updated_by' => Auth::id(),
				'updated_by_user_type' => $userType,
			];

			if ($info->is_partner_package == 1 && $tourBooking->is_partner_booking != 1) {
				$updates['is_partner_booking'] = 1;
			} else if ($info->is_partner_package != 1) {
				$updates['is_partner_booking'] = 0;
			}

			// Include 'booking_total' only when not in refund process
			if (!$refundProcess) {
				$updates['booking_total'] = DB::raw(
					'booking_total+' . (($isCashFees == '1') ? $netAmount : $netAmount)
				);
			}

			$tourBooking->update($updates);
			DB::commit();

			$swipeHistoryId = HistoryService::storeSwitchPackageInPackageHistory($tourBooking, $oldTourPackageId, $tourPackageId, 'Cash');
			DB::commit();

			$historyData = [
				'old_tour_package_id' => $oldTourPackageId,
				'new_tour_package_id' => $tourPackageId,
				'old_package' => $oldPackage->name,
				'new_package' => $info->package_name,
				'added' => $addedPeoplesTexts,
				'deleted' => $deletedPeoplesTexts
			];

			if ($remainingAmount == $paymentAmount) {
				HistoryService::storeSwitchPackageInHistory($tourBooking, $swipeHistoryId, $historyData, $createdByWithName);
				DB::commit();
			}

			if ($isCashFees == '1') {
				$totalAmount = $netAmount;
				$feesAmount = roundout($feesAmount, 2);
			} else {
				$totalAmount = $netAmount;
				$feesAmount = 0;
			}

			//Remaining Refund process
			if ($remainingAmount != $paymentAmount) {
				if ($refundProcess) {
					$refundVia = 'Cash';
					if (isset($request->is_cash) && $request->is_cash != '') {
						if ($request->is_cash == 'Cash') {
							$refundVia = 'Cash';
						} elseif ($request->is_cash == 'Wire Transfer') {
							$refundVia = 'Check/Wire';
						}
					}
					$payoutId = '';

					//Below lines are for to reverse transfer ticket cost to platform account to make refund
					if ($isPartnerBooking == 1) {
						$transferData = [
							'old_tour_package_id' => $oldTourPackageId,
							'amount' => $refundTicketCostAmount,
							'partner_fees' => $refundPartnerFeesAmount,
							'booking_id' => $tourBooking->id,
							'payment_method' => $refundVia
						];
						$payoutId = ThreeWayPaymentService::reverseTransferToPlatformAccount($transferData);
					}

					if ($isPartnerBooking == 1 && $info->is_partner_package == 1) {
						//Below lines are for to tranfer ticket cost new package partner
						$transferData = [
							'old_tour_package_id' => $oldTourPackageId,
							'new_tour_package_id' => $tourPackageId,
							'amount' => $remainingAmount,
							'platform_fees' => $refundPlatformFeesAmount,
							'partner_fees' => $refundPartnerFeesAmount,
							'booking_id' => $tourBooking->id,
							'payment_method' => $refundVia
						];
						ThreeWayPaymentService::transferToNewPartner($transferData);
					}

					$refundTransId = TransactionService::storeRefundInTransaction(
						$tourBooking->id,
						$netAmount,
						($request->refund_type == '100_refund') ? $feesAmount : 0,
						$refundVia,
						'',
						''
					);


					//Below line is for to store refund payout for partner package
					if (($isPartnerBooking == 1 || $info->is_partner_package == 1) && $netPartnerFeesAmount > 0  && $request->refund_type != 'dont_refund') {
						$netTicketCost = ($request->refund_type == 'partial_refund') ? $request->partial_refund_value : $netTicketCostAmount - $netPartnerFeesAmount;
						ThreeWayPaymentService::storeReverseTransferInSwitchPackage($tourBooking->id, $refundTransId ?? null, $netTicketCost, ($request->refund_type == 'partial_refund') ? 0 : $netPartnerFeesAmount, $request->refund_type);
					}

					//Below lines are for to update payout with refund transactions id if destination package is normal package
					if ($isPartnerBooking == 1 && $info->is_partner_package != 1 && $payoutId != '') {
						Payout::where('id', $payoutId)
							->update([
								'transaction_id' => $refundTransId ?? null,
								'updated_at' => Carbon::now()
							]);
					}

					DB::commit();

					$refundTypes = [
						'100_refund' => '100 % Refund',
						'full_refund' => 'Full Refund minus Fees',
						'partial_refund' => 'Partial Refund',
						'dont_refund' => "Don't Refund"
					];

					$refundType = $refundTypes[$request->refund_type] ?? "100 % Refund";

					$historyId = HistoryService::storeRefundDataInHistory(
						$tourBooking->id,
						$tourBooking->tourists + $tourBooking->infants,
						$createdByWithName,
						null,
						$refundType,
						null,
						$netAmount,
						null,
						[],
						[],
						$refundVia,
						null,
						null,
						$refundKeepus,
						null,
						($request->refund_type == '100_refund') ? $feesAmount : 0,
						'Switch Package',
						($request->refund_type == '100_refund' || $request->refund_type == 'full_refund') ? $netPartnerFeesAmount : 0,
					);
					DB::commit();
					$historyData['txnhistoryid'] = $historyId;
					SwitchPackageRefundService::run($tourBooking, $historyId, 'Cash', $refundTransId, $oldStripeFeesPercentage);
					DB::commit();
				} else {
					//Remaining Payment process
					$paymentTransactionId = TransactionService::storePaymentInTransaction($tourBooking->id, $totalAmount, $feesAmount, 'Cash', 0, null, null);
					//Below line is for to store payout for partner package
					if ($info->is_partner_package == 1 && $totalAmount > 0) {
						$payoutData = [
							'amount' => ($isPartnerBooking != 1) ? $total : $totalAmount,  //If booking is not partner booking but selected package is partner then need to tranfer whole ticket cost to partner
							'platform_fees' => ($isPartnerBooking != 1) ? $serviceCommissionTotal : $feesAmount,
							'partner_fees' => ($isPartnerBooking != 1) ? $partnerFeesTotal  : $netPartnerFeesAmount,
							'booking_id' => $tourBooking->id,
							'transaction_id' => $paymentTransactionId,
							'package_id' => $tourBooking->tour_package_id,
							'payment_method' => 'Cash'
						];
						ThreeWayPaymentService::transferToPartner($payoutData);
					}

					//Below line is for to reverse old partner fees if switched to normal package to partner package
					if ($isPartnerBooking == 1 && $info->is_partner_package != 1) {
						$transactionId = $trans->id;
						$refundTransId = TransactionService::storeRefundInTransaction(
							$tourBooking->id,
							0,
							0,
							'Cash',
							'',
							''
						);
						ThreeWayPaymentService::storeReverseTransferOfPartnerFeesOnly($request->bookingID, $transactionId, $refundTransId ?? null, abs($netPartnerFeesAmount));
					}
					DB::commit();

					$data = [
						'amount' => $totalAmount,
						'fees' => ($isCashFees == '1') ? $feesAmount : 0,
						'payment_method' => 'Full Payment',
						'swipeHistoryId' => $swipeHistoryId
					];

					$paymentHistoryId = HistoryService::storeCashPaymentInHistory($tourBooking->id, $tourBooking->tourists + $tourBooking->infants, $data, '', $createdByWithName);
					DB::commit();
					$historyData['txnhistoryid'] = $paymentHistoryId;
				}

				HistoryService::storeSwitchPackageInHistory($tourBooking, $swipeHistoryId, $historyData, $createdByWithName);
				DB::commit();


				if (!$refundProcess) {
					// Below line is for to update tour booking history table
					$isSplitOrFull = 'Full Payment';
					if ($isCashFees == '1') {
						$tourBookingData = [
							'booking_id' => $request->bookingID,
							'customer_id' => $tourBooking->customer_id,
							'is_package_switched' => true
						];

						CardBookingService::run($tourBookingData, $paymentTransactionId, $paymentHistoryId, $isSplitOrFull, $request->all());
					} else {
						CashBookingService::run($tourBooking, $paymentTransactionId, $paymentHistoryId, $isSplitOrFull, $request->all() + ['is_package_switched' => true]);
					}
				}
			}
			static::updatePackagesSlotSummary($tourBooking->tourists, $oldSlotId, $slotId);
			DB::commit();
			return [
				'bookingId' => $tourBooking->id,
				'bookingToken' => Crypt::encryptString($tourBooking->id)
			];
		} catch (Exception $exception) {
			DB::rollBack();
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	public static function calculateRemainingAmount($bookingId, $refundType)
	{
		if ($refundType == '') {
			$refundType = '100_refund';
		}

		$isCashFees = cashFees();
		$tourBooking = TourBooking::find($bookingId);
		$trans = Transaction::where('tour_booking_id', $bookingId)->where('status', 'Completed')->whereNull('charge_type')->orderBy('id', 'asc')->first();
		// whereNull('charge_type') matches $trans/$paymentTransCount just above: NULL is the
		// convention for a genuine ticket/pax payment or refund. Tips use their own 'Tip'
		// operation entirely (never reach here), but add-ons ('addons') and custom charges
		// ('custom_charge') also write operation=Payment/Refund rows — without this filter
		// their amounts got folded into the ticket-cost pool below, corrupting the switch's
		// charge/refund delta for any booking that ever had a paid custom charge (add-ons are
		// separately blocked from ever reaching Switch at all, so that half of this bug was
		// unreachable in practice — custom charges were not).
		$PaymentHistory = TourBookingHistory::where('tour_booking_id', $bookingId)->where('operation', 'Payment')->whereNull('charge_type')->get();
		$RefundHistory = TourBookingHistory::where('tour_booking_id', $bookingId)->where('operation', 'Refund')->whereNull('charge_type')->get();
		$netAmount = 0;
		$netTicketCostAmount = 0;
		$netPlatformFeesAmount = 0;
		$netPartnerFeesAmount = 0;
		$refundKeepus = 0;

		if ($refundType == '100_refund') {
			$ref = 0;
			$netAmountSplit = 0;
			$dontRefundAmountFee = 0;
			$dontRefundAmount = 0;
			$platformFeesAmount = 0;
			$platformFeesRefundAmount = 0;
			$ticketCostAmount = 0;
			$ticketRefundCostAmount = 0;
			$partnerFeesAmount = 0;
			$partnerFeesRefundAmount = 0;
			foreach ($RefundHistory as $value) {
				$details = json_decode($value->details);
				$ref += $details->amount;
				if ($isCashFees == '1' && $value->payment_method == 'Full Refund minus Fees') {
					$dontRefundAmountFee += $details->refund_keep_us ?? 0;
				}

				if ($isCashFees == '1' && $value->payment_method == "Don't Refund") {
					$dontRefundAmount += $details->amount;
				}

				$platformFeesRefundAmount += $value->platform_fees;
				$ticketRefundCostAmount += $value->ticket_cost;
				$partnerFeesRefundAmount += $value->partner_fees;
			}

			foreach ($PaymentHistory as $value) {
				$details = json_decode($value->details);
				$netAmountSplit += $details->amount;
				$platformFeesAmount += $value->platform_fees;
				$ticketCostAmount += $value->ticket_cost;
				$partnerFeesAmount += $value->partner_fees;
			}

			if ($isCashFees == '1' && $trans->via == "Cash") {
				$paymentTransCount = Transaction::where('tour_booking_id', $bookingId)->where('status', 'Completed')->whereNull('charge_type')->count();
				if ($trans->payment_type == 1 || $paymentTransCount > 1) {
					$netAmount = $netAmountSplit - $ref - $dontRefundAmountFee;
				} else {
					$netAmount = $tourBooking->total;
				}
			} else {
				$netAmount = ($trans->payment_type == 1) ? ($netAmountSplit - $ref) : (($ref == $tourBooking->booking_total) ? 0 : $tourBooking->total);
			}

			$netPlatformFeesAmount = $platformFeesAmount - $platformFeesRefundAmount;
			$netTicketCostAmount = $ticketCostAmount - $ticketRefundCostAmount;
			$netPartnerFeesAmount = $partnerFeesAmount - $partnerFeesRefundAmount;
		} else if ($refundType == 'full_refund') {
			$ref = 0;
			$netAmountSplit = 0;
			$fees = 0;
			$dontRefundAmount = 0;
			$oldRefundKeepUs = 0;
			$ticketCostAmount = 0;
			$platformFeesAmount = 0;
			$platformFeesRefundAmount = 0;
			$ticketCostAmount = 0;
			$ticketRefundCostAmount = 0;
			$partnerFeesAmount = 0;
			$partnerFeesRefundAmount = 0;
			foreach ($RefundHistory as $value) {
				$details = json_decode($value->details);
				$ref += $details->amount;
				if ($isCashFees == '1' && $value->payment_method == "Don't Refund") {
					$dontRefundAmount += $details->amount;
				}

				if ($isCashFees == '1' && isset($details->refund_type) && $details->refund_type == 'Full Refund minus Fees') {
					if (isset($details->refund_keep_us) && $details->refund_keep_us > 0) {
						$oldRefundKeepUs += $details->refund_keep_us ?? 0;
					}
				}

				$platformFeesRefundAmount += $value->platform_fees;
				$ticketRefundCostAmount += $value->ticket_cost;
				$partnerFeesRefundAmount += $value->partner_fees;
			}

			foreach ($PaymentHistory as $value) {
				$details = json_decode($value->details);
				$netAmountSplit += $details->amount;
				$fees += $value->platform_fees;
				$platformFeesAmount += $value->platform_fees;
				$ticketCostAmount += $value->ticket_cost;
				$partnerFeesAmount += $value->partner_fees;
			}

			if ($isCashFees == '1' && $trans->via == "Cash") {
				$paymentTransCount = Transaction::where('tour_booking_id', $bookingId)->where('status', 'Completed')->whereNull('charge_type')->count();
				if ($trans->payment_type == 1 || $paymentTransCount > 1) {
					$netAmount = $ticketCostAmount - $ref - $dontRefundAmount;
				} else {
					$netAmount = $tourBooking->total + $oldRefundKeepUs - $tourBooking->service_commission;
				}
			} else {
				$netAmount = $netAmountSplit - $ref;
			}

			$netPlatformFeesAmount = 0;
			$netTicketCostAmount = $ticketCostAmount - $ticketRefundCostAmount;
			$netPartnerFeesAmount = $partnerFeesAmount - $partnerFeesRefundAmount;
			$refundKeepus = $platformFeesAmount - $platformFeesRefundAmount;
		}

		return [
			'netAmount' => $netAmount,
			'platformFeesAmount' => $netPlatformFeesAmount,
			'ticketCostAmount' => $netTicketCostAmount,
			'partnerFeesAmount' => $netPartnerFeesAmount,
			'refundKeepus' => $refundKeepus
		];
	}
	public static function updatePackagesSlotSummary($tourists, $oldSlotId, $slotId)
	{
		// Update slot summary for old slot
		$seatsDiff = - ($tourists);
		TimeSlotAvailabilityService::updateSlotSummary($oldSlotId, $seatsDiff);
		// Add slot summary to for slot
		$seatsAdd = $tourists;
		TimeSlotAvailabilityService::updateSlotSummary($slotId, $seatsAdd);
	}
}
