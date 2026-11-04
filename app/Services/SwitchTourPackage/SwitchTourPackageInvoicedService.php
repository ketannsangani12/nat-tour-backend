<?php

namespace App\Services\SwitchTourPackage;

use App\Exceptions\Core\GeneralCoreException;
use App\Models\Customer;
use App\Models\Payout;
use App\Models\TourBooking;
use App\Models\TourPackage;
use App\Models\Transaction;
use App\Services\Booking\BookingService;
use App\Services\Booking\EditBookingService;
use App\Services\Booking\HistoryService;
use App\Services\Booking\TransactionService;
use App\Services\InvoicedBookingService;
use App\Services\SwitchPackageRefundService;
use App\Services\ThreeWayPayment\ThreeWayPaymentService;
use App\Services\TourPackage\TourPackageService;
use App\Services\TourPackageRate\TourPackageRateService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SwitchTourPackageInvoicedService
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
			$rateGroups = TourPackageRateService::getMappedRateGroups($tourBooking, $customer, $info);

			if (!$rateGroups) {
				$message = "No rate groups found. Please add rate groups for the package.";
				if ($request->is('api/*')) {
					throw new Exception($message);
				}
				return sendJsonErrMsg($message);
			}

			$commissionRates = TourPackageService::getCommissionFeesPercentage($request, $customer, $info);

			// Assign the returned values to respective variables
			$natFeesPercentage = $commissionRates['nat_fees_percentage'];
			$stripeFeesPercentage = $commissionRates['stripe_fees_percentage'];
			$createdByWithName = BookingService::createdUserInfo();

			$result = EditBookingService::processCalculationsSwitchPackage($rateGroups, $isGroupBooking, $info->is_partner_package, $request, $customer, $oldDiscount, $natFeesPercentage, $stripeFeesPercentage, $tourBooking, $userType);
			$subTotal = $result['subTotal'];
			$total = $result['total'];
			$partnerFeesTotal = $result['partnerFeesTotal']; //For partner booking or partner package
			$ticketCostWithoutPartnerFees = $result['ticketCostWithoutPartnerFees']; //For partner booking or partner package
			$tbTransact = $result['tbTransact'];
			$addedPeoplesTexts = $result['addedPeoplesTexts'];

			if ($oldDiscount > 0) {
				$total = $subTotal - $oldDiscount;
				$total = round($total, 2);
			} else {
				$total = round($subTotal, 2);
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

			$remainingAmounts = SwitchTourPackageCashService::calculateRemainingAmount($request->bookingID, $refundType);

			$refundProcess = false;
			$paymentAmount = $total;
			$refundTicketCostAmount = $remainingAmounts['ticketCostAmount'];
			$remainingAmount = $remainingAmounts['netAmount'];
			$refundPartnerFeesAmount = $remainingAmounts['partnerFeesAmount'];
			$refundKeepus = $remainingAmounts['refundKeepus'];

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
			}

			//Net Ticket cost and Platform fees calculations
			if ($paymentAmount > $remainingAmount) {
				$refundProcess = false;
				$netTicketCostAmount = $subTotal - $oldDiscount - $refundTicketCostAmount;
				$netPartnerFeesAmount = $partnerFeesTotal - $refundPartnerFeesAmount;
				$netAmount = round($netTicketCostAmount, 2);
			} else if ($remainingAmount > $paymentAmount) {
				$refundProcess = true;
				$netTicketCostAmount = $subTotal - $oldDiscount - $refundTicketCostAmount;
				$netTicketCostAmount = abs($netTicketCostAmount);
				$netPartnerFeesAmount = $partnerFeesTotal - $refundPartnerFeesAmount;
				$netPartnerFeesAmount  = abs($netPartnerFeesAmount);

				if ($request->refund_type == '100_refund' || $request->refund_type == 'dont_refund') {
					$netAmount = round($netTicketCostAmount, 2);
				} else if ($request->refund_type == 'partial_refund') {
					$netAmount = $request->partial_refund_value;
				} else {
					$netAmount = round($netTicketCostAmount, 2);
				}
			} else if ($remainingAmount == $paymentAmount) {
				$netAmount = 0;
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

			$operation = $refundProcess ? '-' : '+';

			$updates = [
				'tour_package_id' => $tourPackageId,
				'tour_slot_id' => $slotId,
				'is_partner_booking' => ($info->is_partner_package != 1) ? 0 : $isPartnerBooking,
				'total' => DB::raw('total' . $operation . $netAmount),
				'booking_total' => DB::raw('booking_total' . $operation . $netAmount),
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

			$tourBooking->update($updates);

			$swipeHistoryId = HistoryService::storeSwitchPackageInPackageHistory($tourBooking, $oldTourPackageId, $tourPackageId, 'Invoiced');
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

			//Remaining Refund process
			if ($remainingAmount != $paymentAmount) {
				if ($refundProcess) {
					$refundVia = 'Invoiced';
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
							'platform_fees' => 0,
							'partner_fees' => $refundPartnerFeesAmount,
							'booking_id' => $tourBooking->id,
							'payment_method' => $refundVia
						];
						ThreeWayPaymentService::transferToNewPartner($transferData);
					}

					$refundTransId = TransactionService::storeRefundInTransaction($tourBooking->id, $netAmount, 0, $refundVia, null, null);
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
						null,
						'Switch Package',
						($request->refund_type == '100_refund' || $request->refund_type == 'full_refund') ? $netPartnerFeesAmount : 0,
					);
					DB::commit();

					$historyData['txnhistoryid'] = $historyId;
					SwitchPackageRefundService::run($tourBooking, $historyId, 'Invoiced', $refundTransId, $oldStripeFeesPercentage);
					DB::commit();
				} else {
					$paymentTransactionId = TransactionService::storePaymentInTransaction($tourBooking->id, $netAmount, '', 'Invoiced', 0, null, null);
					//Below line is for to store payout for partner package
					if ($info->is_partner_package == 1 && $netAmount > 0) {
						$payoutData = [
							'amount' => ($isPartnerBooking != 1) ? $total : $netAmount,  //If booking is not partner booking but selected package is partner then need to tranfer whole ticket cost to partner
							'platform_fees' => 0,
							'partner_fees' => ($isPartnerBooking != 1) ? $partnerFeesTotal  : $netPartnerFeesAmount,
							'booking_id' => $tourBooking->id,
							'transaction_id' => $paymentTransactionId,
							'package_id' => $tourBooking->tour_package_id,
							'payment_method' => 'Invoiced'
						];
						ThreeWayPaymentService::transferToPartner($payoutData);
					}
					DB::commit();

					$data = [
						'amount' => $netAmount,
						'fees' => 0,
						'payment_method' => 'Full Payment',
						'payment_type' => 'Invoiced',
						'swipeHistoryId' => $swipeHistoryId
					];
					$paymentHistoryId = HistoryService::storeCashPaymentInHistory($tourBooking->id, $tourBooking->tourists + $tourBooking->infants, $data, '', '', $createdByWithName, [], 'Switch Package');
					DB::commit();
					$historyData['txnhistoryid'] = $paymentHistoryId;
				}

				HistoryService::storeSwitchPackageInHistory($tourBooking, $swipeHistoryId, $historyData, $createdByWithName);
				DB::commit();

				if (!$refundProcess) {
					// Below line is for to update tour booking history table
					$isSplitOrFull = 'Full Payment';

					InvoicedBookingService::run($tourBooking, $paymentTransactionId, $paymentHistoryId, $isSplitOrFull, $request->all());
				}
				DB::commit();
			}
			SwitchTourPackageCashService::updatePackagesSlotSummary($tourBooking->tourists, $oldSlotId, $slotId);
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
}
