<?php

namespace App\Services\SwitchTourPackage;

use App\Exceptions\Core\GeneralCoreException;
use App\Models\Customer;
use App\Models\Payout;
use App\Models\SurchargeDate;
use App\Models\Tax;
use App\Models\TourBooking;
use App\Models\TourPackageRate;
use App\Models\Transaction;
use App\Services\Booking\BookingService;
use App\Services\Booking\HistoryService;
use App\Services\Booking\TransactionService;
use App\Services\CardBookingService;
use App\Services\SwitchPackageRefundService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\TourPackage;
use App\Services\Booking\EditBookingService;
use App\Services\Booking\StripeService;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\Services\Payments\PaymentModeInference;
use App\Services\Payments\PlatformFeeMetadata;
use App\Services\ThreeWayPayment\ThreeWayPaymentService;
use App\Services\TourPackage\TourPackageService;
use App\Services\TourPackageRate\TourPackageRateService;
use Illuminate\Support\Facades\Auth;
use Stripe\Exception\CardException;

class SwitchTourPackageCardService
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
			$rateGroups = TourPackageRateService::getMappedRateGroups($tourBooking, $customer, $info);

			if (!$rateGroups) {
				$message = "No rate groups found. Please add rate groups for the package.";
				if ($request->is('api/*')) {
					throw new Exception($message);
				}
				return sendJsonErrMsg($message);
			}

			$createdByWithName = BookingService::createdUserInfo();
			$commissionRates = TourPackageService::getCommissionFeesPercentage($request, $customer, $info);

			// Assign the returned values to respective variables
			$natFeesPercentage = $commissionRates['nat_fees_percentage'];
			$stripeFeesPercentage = $commissionRates['stripe_fees_percentage'];

			$result = EditBookingService::processCalculationsSwitchPackage($rateGroups, $isGroupBooking, $info->is_partner_package, $request, $customer, $oldDiscount, $natFeesPercentage, $stripeFeesPercentage, $tourBooking, $userType);
			$subTotal = $result['subTotal'];
			$total = $result['total'];
			$serviceCommissionTotal = $result['serviceCommissionTotal'];
			$partnerFeesTotal = $result['partnerFeesTotal']; //For partner booking or partner package
			$ticketCostWithoutPartnerFees = $result['ticketCostWithoutPartnerFees']; //For partner booking or partner package
			$tbTransact = $result['tbTransact'];
			$addedPeoplesTexts = $result['addedPeoplesTexts'];

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
			$refundPlatformFeesAmount = $remainingAmounts['platformFeesAmount'];
			$refundPartnerFeesAmount = $remainingAmounts['partnerFeesAmount'];
			$remainingAmount = $remainingAmounts['netAmount'];
			$refundKeepus = $remainingAmounts['refundKeepus'];

			if ($request->has('refund_type')) {
				if ($request->refund_type == 'partial_refund') {
					$refundKeepus = $tourBooking->total - $request->partial_refund_value;
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

			if ($isValidationCheckForBookingSwipePackage) {
				if ($paymentAmount > $remainingAmount) {
					$netAmount = $paymentAmount - $remainingAmount;
					return [
						'payment' => 1,
						'amount' => round($netAmount, 2),
						'fees' => $feesAmount
					];
				} else if ($remainingAmount > $paymentAmount) {
					return [
						'payment' => 0
					];
				}
			}



			if (!$refundProcess && $netAmount > 0) {
				$payoutAmount = $netAmount - $feesAmount - $netPartnerFeesAmount;
				$stripeMetadata = [
					'description' => 'Booking Id: ' . $request->bookingID .
						"\nTour Package: " . $info->package_name .
						"\nTour Date: " . $info->date .
						"\nTour Time: " . $info->time_from .
						"\nTourists: " . ($tourBooking->tourists + $tourBooking->infants) .
						"\nEmail: " . $customer->email,
					'metadata' => [
						'booking_id' => $request->bookingID,
						'tour_package' => $info->package_name,
						'tour_date' => $info->date,
						'tour_time' => $info->time_from,
						'tourists' => $tourBooking->tourists + $tourBooking->infants,
						'customer_name' => $customer->name,
						'customer_phone' => $customer->phone_number,
						'email' => $customer->email,
						'old_package' => $oldPackage->name,
						'amount' => $netAmount ?? 0.00,
						'platform_fee' => $feesAmount ?? 0.00,
						'partner_fee' => $netPartnerFeesAmount ?? 0.00,
						'payout_amount' => $payoutAmount ?? 0.00,
						'split_payment' => 0,
						'by' => 'Switch Package - Web',
						'new_slot_id' => $slotId,
						'new_package_id' => $tourPackageId,
						'tenant_id' => tenant()->id,
					],
				];
				$paymentIntentId = ($request->is('api/*')) ? $request->paymentIntentId : $request->payment_intent_id;
				$retrieveOptions = ['expand' => ['charges.data.transfer']];
				$paymentIntent = app(PaymentGatewayInterface::class)->retrievePayment($paymentIntentId, $retrieveOptions);
				$updateParams = $stripeMetadata;
				app(PaymentGatewayInterface::class)->updatePayment($paymentIntentId, $updateParams);
				if (isset($paymentIntent->charges->data[0]->transfer->destination_payment)) {
					$chargeParams = $stripeMetadata;
					app(PaymentGatewayInterface::class)->updateConnectedPayment(
						$paymentIntent->charges->data[0]->transfer->destination_payment,
						$chargeParams,
						$paymentIntent->charges->data[0]->transfer->destination
					);
				}
			}

			$oldRateGroupsQry = DB::table('tour_bookings_transact AS tbt')
				->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
				->where('tbt.tour_booking_id', $request->bookingID)
				->where('tpr.tour_package_id', $tourBooking->tour_package_id);

			$oldRateGroups = $oldRateGroupsQry->select(['tpr.rate_for', 'tbt.tourists', 'tbt.stripe_fees_percentage', 'tbt.infants'])->get();

			$deletedPeoplesTexts = [];
			foreach ($oldRateGroups as $oldRateGroup) {
				$peopleText = $isGroupBooking == '1'
					? "Person(s) {$oldRateGroup->tourists}"
					: "{$oldRateGroup->rate_for} person " . ($oldRateGroup->infants > 0 ? $oldRateGroup->infants : $oldRateGroup->tourists);

				$deletedPeoplesTexts[] = $peopleText;
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
					// feesAmount here is the new service commission (since refundPlatformFeesAmount is 0 for full_refund type)
					// We need to calculate the actual difference
					$actualFeesDifference = $tourBooking->service_commission - $serviceCommissionTotal;
					$serviceCommissionUpdate = DB::raw('service_commission-' . abs($actualFeesDifference));
				} else {
					// For partial refund, full refund minus fees, and don't refund - keep original service_commission
					$serviceCommissionUpdate = $tourBooking->service_commission;
				}
			} else {
				$serviceCommissionUpdate = DB::raw('service_commission+' . $feesAmount);
			}

			// Prepare the update payload
			$updates = [
				'tour_package_id' => $tourPackageId,
				'tour_slot_id' => $slotId,
				'is_partner_booking' => ($info->is_partner_package != 1) ? 0 : $isPartnerBooking,
				'service_commission' => $serviceCommissionUpdate,
				'discount2_percentage' => $newDiscountPercent,
				'total' => DB::raw(($refundProcess ? 'total-' : 'total+') . $netAmount),
				'booking_total' => $refundProcess ? null : DB::raw('booking_total+' . $netAmount),
				'updated_at' => mysqlDT(),
				'updated_by' => Auth::id(),
				'updated_by_user_type' => $userType,
			];

			// Remove booking_total if it's a refund process to avoid unnecessary updates
			if ($refundProcess) {
				unset($updates['booking_total']);
			}

			$tourBooking->update($updates);

			$swipeHistoryId = HistoryService::storeSwitchPackageInPackageHistory($tourBooking, $oldTourPackageId, $tourPackageId, 'Card');
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

			$totalAmount = $netAmount;
			$feesAmount = roundout($feesAmount, 2);

			//Remaining Refund process
			if ($remainingAmount != $paymentAmount) {
				if ($refundProcess) {

					$refundVia = 'Card';
					if (isset($request->is_cash) && $request->is_cash != '') {
						if ($request->is_cash == 'Cash') {
							$refundVia = 'Cash';
						} elseif ($request->is_cash == 'Wire Transfer') {
							$refundVia = 'Check/Wire';
						}
					}
					$trans = Transaction::where('tour_booking_id', $request->bookingID)->where('status', 'Completed')->whereNull('charge_type')->orderBy('id', 'DESC')->first();
					$refundTicketCostWithoutPartnerFeesAmount = ($request->refund_type == 'partial_refund') ? $request->partial_refund_value : $refundTicketCostAmount - $refundPartnerFeesAmount;
					$payoutId = '';
					//Below lines are for to reverse transfer ticket cost to platform account to make refund
					if ($isPartnerBooking == 1) {
						$transferData = [
							'old_tour_package_id' => $oldTourPackageId,
							'amount' => $refundTicketCostAmount,
							'partner_fees' => $refundPartnerFeesAmount,
							'booking_id' => $tourBooking->id,
							'old_package_destination_id' => $oldPackage->stripe_destination_id,
							'payment_method' => $refundVia
						];
						$payoutId = ThreeWayPaymentService::reverseTransferToPlatformAccount($transferData);
					}

					if ($refundVia == 'Card' && $trans->transaction_number != '' && $request->refund_type != 'dont_refund') {
						$refundArray = [
							'payment_intent' => $trans->transaction_number,
							'amount' => $netAmount * 100,
							'reverse_transfer' => true,
						];

						if ($request->refund_type == '100_refund' || $request->refund_type == 'partial_refund') {
							$refundArray['refund_application_fee'] = true;
						}

						app(PaymentGatewayInterface::class)->refundPayment($refundArray, $trans->payment_mode);
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
							'payment_intent_id' => $trans->transaction_number,
							'application_fee_id' => $trans->application_fee_id,
							'new_package_destination_id' => $info->partnerDestinationId,
							'payment_method' => $refundVia
						];
						ThreeWayPaymentService::transferToNewPartner($transferData);
					}

					$refundTypes = [
						'100_refund' => '100 % Refund',
						'full_refund' => 'Full Refund minus Fees',
						'partial_refund' => 'Partial Refund',
						'dont_refund' => "Don't Refund"
					];

					$refundType = $refundTypes[$request->refund_type] ?? "100 % Refund";


					$refundTransId = TransactionService::storeRefundInTransaction(
						$tourBooking->id,
						($request->refund_type == "dont_refund") ? 0 : $netAmount,
						($request->refund_type == '100_refund') ? $feesAmount : 0,
						$refundVia,
						($refundVia == 'Card') ? $trans->transaction_number : null,
						($refundVia == 'Card') ? $trans->application_fee_id : null,
						mode: $trans->payment_mode
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
						($request->refund_type == '100_refund' || $request->refund_type == 'full_refund') ? $netPartnerFeesAmount : 0
					);
					$historyData['txnhistoryid'] = $historyId;
					HistoryService::storeSwitchPackageInHistory($tourBooking, $swipeHistoryId, $historyData, $createdByWithName);
					DB::commit();


					SwitchPackageRefundService::run($tourBooking, $historyId, 'Card', $refundTransId, $oldStripeFeesPercentage);
					DB::commit();
					
				} else {
					$trans = Transaction::where('tour_booking_id', $request->bookingID)->where('status', 'Completed')->whereNull('charge_type')->orderBy('id', 'DESC')->first();
					$paymentTransactionId = TransactionService::storePaymentInTransaction(
						$tourBooking->id,
						$totalAmount,
						$feesAmount,
						'Card',
						0,
						!empty($paymentIntent->id) ? $paymentIntent->id : null,
						!empty($paymentIntent->charges->data[0]->application_fee) ? $paymentIntent->charges->data[0]->application_fee : null,
						mode: PaymentModeInference::fromStripeObject($paymentIntent)
					);

					//Below lines are for to transfer ticket cost from one partner connected account to another partner connected account
					if ($isPartnerBooking == 1 && $info->is_partner_package == 1) {
						$transferData = [
							'old_tour_package_id' => $oldTourPackageId,
							'new_tour_package_id' => $tourPackageId,
							'amount' => $remainingAmount,
							'platform_fees' => $refundPlatformFeesAmount,
							'ticket_cost' => $refundTicketCostAmount,
							'partner_fees' => $refundPartnerFeesAmount,
							'booking_id' => $tourBooking->id,
							'payment_intent_id' => $paymentIntent->id,
							'application_fee_id' => $paymentIntent->charges->data[0]->application_fee,
							'old_package_destination_id' => $oldPackage->stripe_destination_id,
							'new_package_destination_id' => $info->partnerDestinationId,
							'transfer_group' => $paymentIntent->transfer_group ?? null,
							'payment_method' => 'Card'
						];
						ThreeWayPaymentService::transferBetweenConnectedAccounts($transferData);
					} else if ($isPartnerBooking == 1 && $info->is_partner_package != 1) {
						//Below lines are for to reverse ticket cost from one partner connected account to platform account
						$transferData = [
							'old_tour_package_id' => $oldTourPackageId,
							'partner_fees' => $refundPartnerFeesAmount,
							'booking_id' => $tourBooking->id,
							'payment_intent_id' => $paymentIntent->id,
							'application_fee_id' => $paymentIntent->charges->data[0]->application_fee,
							'old_package_destination_id' => $oldPackage->stripe_destination_id,
							'payment_method' => 'Card'
						];
						ThreeWayPaymentService::reverseTransferToPlatformAccount($transferData);
					}

					DB::commit();

					//Below line is for to store payout for partner package
					if ($info->is_partner_package == 1 && $totalAmount > 0) {
						$payoutData = [
							'amount' => ($isPartnerBooking != 1) ? $total : $totalAmount,  //If booking is not partner booking but selected package is partner then need to tranfer whole ticket cost to partner
							'platform_fees' => ($isPartnerBooking != 1) ? $serviceCommissionTotal : $feesAmount,
							'partner_fees' => ($isPartnerBooking != 1) ? $partnerFeesTotal  : $netPartnerFeesAmount,
							'booking_id' => $tourBooking->id,
							'transaction_id' => $paymentTransactionId,
							'package_id' => $tourBooking->tour_package_id,
							'partner_destination_id' => $info->partnerDestinationId,
							'transfer_group' => $paymentIntent->transfer_group ?? null,
							'payment_method' => 'Card'
						];
						ThreeWayPaymentService::transferToPartner($payoutData);
					}
					DB::commit();

					$cardDetails = StripeService::extractCardDetails($paymentIntent);
					$crd = $cardDetails['last4'];
					$cardType = $cardDetails['brand'];

					$data = [
						'total' => $totalAmount,
						'fees' => $feesAmount,
						'swipeHistoryId' => $swipeHistoryId
					];

					$paymentHistoryId = HistoryService::storeCardPaymentInHistory($tourBooking->id, $tourBooking->tourists + $tourBooking->infants, $data, $crd, $cardType, $createdByWithName, [], 'Switch Package');
					DB::commit();
					$historyData['txnhistoryid'] = $paymentHistoryId;			
					HistoryService::storeSwitchPackageInHistory($tourBooking, $swipeHistoryId, $historyData, $createdByWithName);
					DB::commit();

					if (!$refundProcess) {
						// Below line is for to update tour booking history table
						$isSplitOrFull = 'Full Payment';
						$tourBookingData = [
							'booking_id' => $request->bookingID,
							'customer_id' => $tourBooking->customer_id,
							'is_package_switched' => true
						];

						CardBookingService::run($tourBookingData, $paymentTransactionId, $paymentHistoryId, $isSplitOrFull, $request->all());
						DB::commit();
					}
				}
				SwitchTourPackageCashService::updatePackagesSlotSummary($tourBooking->tourists, $oldSlotId, $slotId);
				DB::commit();
			}

			return [
				'bookingId' => $tourBooking->id,
				'bookingToken' => Crypt::encryptString($tourBooking->id)
			];
		} catch (CardException $exception) {
			DB::rollBack();
			Log::error($exception);

			HistoryService::storeCardPaymentErrorInHistory($data['booking_id'], $exception);

			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Update package in 3d secure payments in make payment feature
	 *
	 * @param array $data
	 *
	 * @return string
	 */
	public static function completeSecureCardPayment($data): string
	{
		DB::beginTransaction();
		try {
			$tourPackageId = $data['new_package_id'];
			$slotId = $data['new_slot_id'];
			$bookingID = $data['booking_id'];
			$paymentIntentDetails = $data['paymentIntentDetails'];
			$userType = userType();
			$paymentAcknowledgement = '';

			if ($paymentIntentDetails->status == 'succeeded') {
				$info = DB::table('tour_slot_times AS tst')
					->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
					->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
					->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
					->join('cities AS ct', 'ct.id', '=', 't_o.city_id')
					->join('states AS st', 'st.id', '=', 'ct.state_id')
					->where('tst.id', $slotId)
					->select([
						'tsd.id',
						'tst.time_from',
						'tsd.date',
						'tst.time_slot_seats as seats',
						'tp.tour_operator_id',
						'tsd.tour_package_id',
						'tp.name AS package_name',
						'tp.important_notes',
						'tp.things_to_bring',
						'ct.state_id',
						'tp.service_commission_percentage',
						'tp.stripe_commission_percentage',
						't_o.tenant_id',
						'tp.stripe_destination_id',
						't_o.id as tour_operator_id',
						't_o.statement_descriptor'
					])
					->first();

				if (!$info) {
					throw new Exception('Unable to switch tour package');
				}

				$tourBooking = TourBooking::find($bookingID);
				$isGroupBooking = $tourBooking->is_group_booking;
				$tourists = $tourBooking->tourists + $tourBooking->infants;
				$oldTourPackageId = $tourBooking->tour_package_id;

				if ($oldTourPackageId == $tourPackageId) {
					throw new Exception('Unable to switch tour package');
				}

				$oldPackage = TourPackage::findOrFail($oldTourPackageId);
				$customer = Customer::where('id', $tourBooking->customer_id)->withTrashed()->first();
				$affiliateId = $customer->is_affiliate == 'Yes' ? $customer->id : null;
				$isAffiliate = $customer->is_affiliate;

				/* START: Logic to send slot info in helper */
				$standardRateDate = SurchargeDate::where('affiliate_id', $affiliateId)
					->where('tour_package_id', $info->tour_package_id)
					->where('date_range_start', '<=', $info->date)
					->where('date_range_end', '>=', $info->date)
					->orderBy('priority', 'DESC')
					->first();

				if (isset($standardRateDate) && $standardRateDate->apply_on_slots) {
					$customRateDate = isCustomDateRateEnabled($info->date, $info->tour_package_id, $affiliateId, $info->id);
				} else {
					$customRateDate = isCustomDateRateEnabled($info->date, $info->tour_package_id, $affiliateId);
				}
				/* END */

				$tourPackageRate = TourPackageRate::where('affiliate_id', $tourBooking->customer_id)
					->where('scheduling_year', date('Y', strtotime($info->date)))
					->when($customRateDate, function ($query) use ($customRateDate) {
						return $query->where('custom_date_id', $customRateDate);
					}, function ($query): mixed {
						return $query->whereNull('custom_date_id');
					})
					->first();

				$rateGroup = TourPackageRate::where('tour_package_id', $info->tour_package_id)
					->where('status', 'Active')
					->when($isGroupBooking == 1, function ($query) use ($tourists) {
						return $query->where('min_group_size', '<=', $tourists)
							->where('max_group_size', '>=', $tourists);
					})
					->when($isAffiliate == 'Yes' && $tourPackageRate, function ($query) use ($customer) {
						return $query->where('affiliate_id', $customer->id);
					}, function ($query): mixed {
						return $query->whereNull('affiliate_id');
					})
					->when($customRateDate, function ($query) use ($customRateDate) {
						return $query->where('custom_date_id', $customRateDate);
					}, function ($query): mixed {
						return $query->whereNull('custom_date_id');
					})
					->where('scheduling_year', date('Y', strtotime($info->date)))
					->first();

				if (!$rateGroup) {
					throw new Exception('No rate groups found. Please add rate groups for the package.');
				}

				$rateGroup = $rateGroup->toArray();

				$createdByWithName = BookingService::createdUserInfo();

				if ($isGroupBooking != '1') {
					$taxes = Tax::where([
						'state_id' => $info->state_id,
						'status' => 'Active',
						'deleted_by' => 0
					])
						->get(['value', 'value_type']);

					for ($c = 0, $cc = count($taxes); $c < $cc; $c++) {
						$stateTax = ($taxes[$c]->value_type == 'Percent'
							? $rateGroup['rate'] * $taxes[$c]->value / 100
							: $taxes[$c]->value
						);
					}
				}

				if ($isGroupBooking == '1') {
					$subTotal = $rateGroup['rate'] + $rateGroup['additional_charge'];
				} else {
					$subTotal = ($tourBooking->tourists * ($rateGroup['rate'] +
						$rateGroup['additional_charge'] +
						$rateGroup['permit_fee']
					)) + $stateTax;
				}
				$isBlackoutDate = isBlackoutDateEnabled($info->date);
				$natFeesPercentage = $info->service_commission_percentage;
				$stripeFeesPercentage = $info->stripe_commission_percentage;
				if ($customer->is_affiliate == 'Yes' && !$isBlackoutDate) {
					$natFeesPercentage = $customer->affiliate_processing_percentage;
					$stripeFeesPercentage = $customer->stripe_affiliate_percentage;

					if (getNextYearCommissionPercentage($info->date)) {
						$affiliateCommission = getAffiliateCommissionPercentage($customer->id);
						$natFeesPercentage = $affiliateCommission['affiliate_processing_percentage'];
						$stripeFeesPercentage = $affiliateCommission['stripe_affiliate_percentage'];
					}
				} elseif (getNextYearCommissionPercentage($info->date)) {
					$serviceCommission = getNormalCommissionPercentage($info->tour_package_id);
					$natFeesPercentage = $serviceCommission['service_commission_percentage'];
					$stripeFeesPercentage = $serviceCommission['stripe_commission_percentage'];
				}

				$netAmount = (isset($paymentIntentDetails->charges->data[0]->amount)) ? $paymentIntentDetails->charges->data[0]->amount / 100 : 0;
				$feesAmount = PlatformFeeMetadata::feeFor($paymentIntentDetails, (isset($paymentIntentDetails->charges->data[0]->application_fee_amount)) ? $paymentIntentDetails->charges->data[0]->application_fee_amount / 100 : 0);

				$addedPeoplesTexts = [$rateGroup['rate_for'] . " person " . $tourBooking->tourists];

				$oldRateGroupsQry = DB::table('tour_bookings_transact AS tbt')
					->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
					->where('tbt.tour_booking_id', $bookingID)
					->where('tpr.tour_package_id', $tourBooking->tour_package_id);

				$oldRateGroups = $oldRateGroupsQry->get();

				$deletedPeoplesTexts = array();
				foreach ($oldRateGroups as $oldRateGroup) {
					$deletedPeoplesTexts[] = $oldRateGroup->rate_for . " person " . ($oldRateGroup->infants > 0 ? $oldRateGroup->infants : $oldRateGroup->tourists);
				}

				$oldRateGroupsQry->update([
					'tbt.deleted_at' => Carbon::now(),
					'tbt.deleted_by' => Auth::id()
				]);;
				DB::commit();

				$tbTransact = [
					'tour_booking_id' => $tourBooking->id,
					'tour_package_rate_id' => $rateGroup['id'],
					'tourists' => $tourBooking->tourists,
					'infants' => $tourBooking->infants,
					'is_infant_rate' => $rateGroup['is_infant_rate'],
					'processing_charge_percentage' => 0,
					'nat_fees_percentage' => $natFeesPercentage,
					'stripe_fees_percentage' => $stripeFeesPercentage,
					'additional_tax_percentage' => ($isGroupBooking != '1') ? ($rateGroup['additional_tax_percentage']) : 0,
					'additional_charge' => ($rateGroup['additional_charge']),
					'permit_fee' => ($isGroupBooking != '1') ? $rateGroup['permit_fee'] : 0,
					'rate' => $rateGroup['rate'],
					'total' => $subTotal,
					'created_by' => Auth::id(),
					'created_by_user_type' => $userType,
					'created_at' => Carbon::now(),
				];
				DB::table('tour_bookings_transact')->insert($tbTransact);
				DB::commit();

				$updates = [
					'tour_package_id' => $tourPackageId,
					'tour_slot_id' => $slotId,
					'service_commission' => DB::raw('service_commission+' . $feesAmount),
					'total' => DB::raw('total+' . $netAmount),
					'booking_total' => DB::raw('booking_total+' . $netAmount),
					'updated_at' => mysqlDT(),
					'updated_by' => Auth::id(),
					'updated_by_user_type' => $userType,
				];

				$tourBooking->update($updates);

				DB::commit();

				$swipeHistoryId = HistoryService::storeSwitchPackageInPackageHistory($tourBooking, $oldTourPackageId, $tourPackageId, 'Card');
				DB::commit();

				$historyData = [
					'old_tour_package_id' => $oldTourPackageId,
					'new_tour_package_id' => $tourPackageId,
					'old_package' => $oldPackage->name,
					'new_package' => $info->package_name,
					'added' => $addedPeoplesTexts,
					'deleted' => $deletedPeoplesTexts
				];


				$paymentTransactionId = TransactionService::storePaymentInTransaction(
					$tourBooking->id,
					$netAmount,
					$feesAmount,
					'Card',
					0,
					!empty($paymentIntentDetails->id) ? $paymentIntentDetails->id : null,
					!empty($paymentIntentDetails->charges->data[0]->application_fee) ? $paymentIntentDetails->charges->data[0]->application_fee : null,
					mode: PaymentModeInference::fromStripeObject($paymentIntentDetails)
				);

				DB::commit();

				$crd = !empty($paymentIntentDetails->charges->data[0]->payment_method_details->card->last4) ? $paymentIntentDetails->charges->data[0]->payment_method_details->card->last4 : '';
				$cardType = !empty($paymentIntentDetails->charges->data[0]->payment_method_details->card->brand) ? $paymentIntentDetails->charges->data[0]->payment_method_details->card->brand : '';

				$data = [
					'total' => $netAmount,
					'fees' => $feesAmount,
					'swipeHistoryId' => $swipeHistoryId
				];

				$paymentHistoryId = HistoryService::storeCardPaymentInHistory($tourBooking->id, $tourBooking->tourists + $tourBooking->infants, $data, $crd, $cardType, $createdByWithName, [], 'Switch Package');
				DB::commit();
				$historyData['txnhistoryid'] = $paymentHistoryId;

				HistoryService::storeSwitchPackageInHistory($tourBooking, $swipeHistoryId, $historyData, $createdByWithName);
				DB::commit();

				// Below line is for to update tour booking history table
				$isSplitOrFull = 'Full Payment';
				$tourBookingData = [
					'booking_id' => $bookingID,
					'customer_id' => $tourBooking->customer_id,
					'is_package_switched' => true
				];

				CardBookingService::run($tourBookingData, $paymentTransactionId, $paymentHistoryId, $isSplitOrFull, request()->all());
				DB::commit();
				$paymentAcknowledgement = 'success';
			} else {
				$paymentAcknowledgement = 'failed';
			}
			return $paymentAcknowledgement;
		} catch (CardException $exception) {
			DB::rollBack();
			Log::error($exception);
			HistoryService::storeCardPaymentErrorInHistory($data['booking_id'], $exception);
			throw new GeneralCoreException($exception);
		}
	}
}
