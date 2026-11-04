<?php

namespace App\Services\Booking;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Hotel;
use App\Models\Staff;
use App\Utils\BokunUtils;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\SaveCreditCard;
use App\Models\TourBooking;
use App\Jobs\DetectPayoutAdjustment;
use App\Contracts\Payments\PaymentGatewayInterface;

class HistoryService
{
	/**
	 * Execute the history service to get the details.
	 *
	 * @param mixed $historyDet
	 *
	 * @return mixed
	 */
	public static function historyDetails($historyDet, $promoCode = "")
	{
		$isCashFees = cashFees();
		$otaPlatforms = BokunUtils::getAllOtaTitles();

		foreach ($historyDet as $key => $hist) {
			$operationDesc = '';
			$opByUType = $hist->operation_by_user_type;

			if ($hist->operation == 'Note') {
				if ($opByUType == 'Tour Operator') {
					$opByUType = 'A tour operator staff';
				} else if ($opByUType == 'Affiliate') {
					$opByUType = 'An affiliate';
				} else {
					$opByUType = 'An admin';
				}
				$descAdd = "<span style='color:green'>" . "$opByUType user ($hist->username) added/updated note:<br></span>";
				$operationDesc = $descAdd . "<textarea rows='3' disabled style='border: none;background: transparent;overflow: hidden;width: 100%;color: #212529;font-size: 16px;'>" . e($hist->details) . "</textarea>";
				$historyDet[$key]->history_api_data = $operationDesc;
			} elseif ($hist->operation == 'Error' || $hist->operation == 'Card Error') {
				$operationDesc = 'Booking error - ' . e($hist->details);
				$historyDet[$key]->history_api_data = $operationDesc;
			} elseif ($hist->operation == 'Reference Booking') {
				$operationDesc = 'Reference booking number added/updated. ' . $opByUType . '(' . $hist->username . '): <b>' . e($hist->details) . '</b>';
				$historyDet[$key]->history_api_data = $operationDesc;
			} elseif ($hist->operation == 'Comments') {
				$operationDesc = 'Comment from the customer added/updated. ' . $opByUType . '(' . $hist->username . '):<br>' . e($hist->details);
				$historyDet[$key]->history_api_data = $operationDesc;
			} elseif ($hist->operation == 'Restore') {
				$operationDesc = 'Booking restored by <b>' . e($hist->details->created_by_with_name) . '</b>';
				$historyDet[$key]->history_api_data = $operationDesc;
			} elseif ($hist->operation == 'Payment Type Update') {
				$operationDesc = 'History ID - <b>' . $hist->details->history_id . '</b>, Payment type changed from <b>' . $hist->details->from . '</b> to <b>' . $hist->details->to . '</b> by <b>' . e($hist->details->created_by_with_name) . '</b>';
				$historyDet[$key]->history_api_data = $operationDesc;
			} elseif ($hist->operation == 'Split Booking Original') {
				$historyDet[$key]->split_booking_id = $hist->details->extended_booking_id;
				$historyDet[$key]->history_api_data = 'Extended Booking ID - <b style="color:#0d6efd;">' . $hist->details->extended_booking_id . '</b> by <b>' . $hist->details->created_by_with_name . '</b>';
			} elseif ($hist->operation == 'Split Booking') {
				$historyDet[$key]->split_booking_id = $hist->details->original_booking_id;
				$historyDet[$key]->history_api_data = 'Original Booking ID - <b style="color:#0d6efd;">' . $hist->details->original_booking_id . '</b></a> by <b>' . $hist->details->created_by_with_name . '</b>';
			} elseif ($hist->operation == 'Switch Tour Package') {
				$details = $hist->details;
				$details->created_by_with_name = sprintf('%s', $details->created_by_with_name ?? '');
				$descAdd = (isset($details->added) && !empty($details->added)) ? implode("<br>", $details->added) : '';
				$descDelete = (isset($details->deleted) && !empty($details->deleted)) ? implode("<br>", $details->deleted) : '';
				$operationDesc = 'Edit booking - Switch <b>' . $details->old_package . '</b> to <b>' . $details->new_package . '</b> ' . $details->created_by_with_name . '<br>';
				if ($descAdd != "") {
					$operationDesc .= '<b>Added</b>: <br/>' . $descAdd . '<br/>';
				}
				if ($descDelete != "") {
					$operationDesc .= '<b>Deleted</b>: <br/>' . $descDelete . '<br/>';
				}

				if ($hist->tourists) {
					$operationDesc .= '<b>New Pax Count</b>: ' . $hist->tourists;
				}
				$historyDet[$key]->history_api_data = $operationDesc;
			} elseif ($hist->operation == 'Pickup Hotel') {
				$details = $hist->details;
				$operationDesc = 'Need to pick up visitors from the hotel below - by <b>' . $details->created_by_with_name . '</b>';
				$operationDesc .= '<br /> <b>Name</b>: ' . sprintf('%s', $details->name);
				$operationDesc .= '<br /> <b>Address</b>: ' . sprintf('%s', $details->address);
				$operationDesc .= '<br /> <b>Email</b>: ' . sprintf('%s', $details->email != '' ? $details->email : 'x');
				$operationDesc .= '<br /> <b>Phone</b>: ' . sprintf('%s', $details->phone_number != '' ? $details->email : 'x');
				$historyDet[$key]->history_api_data = $operationDesc;
			} elseif ($hist->operation == 'Payment' || $hist->operation == 'Refund') {
				$details = $hist->details;
				$details->amount = sprintf('%0.2f', $details->amount);
				$details->payment_type = sprintf('%s', $details->payment_type ?? '');
				$details->created_by_with_name = sprintf('%s', $details->created_by_with_name ?? '');
				if (isset($details->deleted) && !empty($details->deleted)) {
					if (is_array($details->deleted)) {
						$deletedImplode = implode('<br>', $details->deleted);
						$details->deleted = sprintf('%s', $deletedImplode);
					} else {
						$details->deleted = sprintf('%s', $details->deleted ?? '');
					}
				} else {
					$details->deleted = '';
				}

				$details->refund_type = sprintf('%s', $details->refund_type ?? '');
				if ($hist->operation == 'Payment') {
					$paymentMethod = '';
					if (!empty($details) && !empty($details->payment_method)) {
						$paymentMethod = $details->payment_method;
					}
					if ($hist->charge_type == 'discount') {
						$detailCostDesc = 'New Discount : ' . moneyFormat($details->discount) . '<br /> Old Discount : ' . moneyFormat($details->old_discount2_value);
					} else {
						$detailCostDesc = 'Ticket Cost: ' . moneyFormat($hist->ticket_cost);
					}
					if ($details->payment_type == "Cash" && isset($hist->change_amount) && $hist->change_amount > 0 && isset($details->amount) && $hist->charge_type != "discount") {
						$paidAmountCash = (float)$hist->change_amount + (float)$details->amount;
						$detailCostDesc .= '<br /> Paid: ' . moneyFormat($paidAmountCash);
						$detailCostDesc .= '<br /> Change: ' . moneyFormat($hist->change_amount);
					}
					if (((isset($hist->discount) && $hist->discount > 0) && (isset($paymentMethod) && $paymentMethod != 'Split Payment')) && $hist->charge_type != "discount") {
						$discountPromoCode = isset($hist->details->discount_promo_code) ? ' (' . $hist->details->discount_promo_code . ')' : "";
						$detailCostDesc .= '<br/> Discount: ' . moneyFormat($hist->discount) . $discountPromoCode;
					}

					$operationDesc = 'Received payment of ' . moneyFormat($details->amount) . ' ' . $details->payment_type . ' ' . $paymentMethod . ' ' . $details->created_by_with_name . '<br/>' . $detailCostDesc;

					//add on history start
					if ($hist->charge_type == 'addons') {
						$details = $hist->details;
						$detailCostDesc = '';
						$operationDesc = '';
						$operationDesc = 'Received payment of ' . moneyFormat($hist->ticket_cost + $hist->platform_fees) . ' ' . $details->created_by_with_name . '<br />';
						foreach ($details->addons as $addon) {
							$detailCostDesc .= '<b>Name</b>: ' . sprintf('%s', $addon->name);
							if ($addon->fee) {
								$detailCostDesc .= '<br /><b>Paid</b>: ' . moneyFormat($addon->price + $addon->fee) . ' (<b>Price</b>: ' . moneyFormat($addon->price) . ', <b>Fee</b>: ' . moneyFormat($addon->fee) . ')';
							} else {
								$detailCostDesc .= '<br /><b>Paid</b>: ' . moneyFormat($addon->price + $addon->fee);
							}
							$detailCostDesc .= '<br />';
						}
						// if ($details->payment_type == "Cash") {
						// 	$operationDesc = 'Received payment of ' . moneyFormat($details->amount) . ' ' . $details->payment_type . ' ' . $paymentMethod . ' ' . $details->created_by_with_name . '<br/>' . $detailCostDesc;
						// } 
						// else {
						// 	$cardNumber = (isset($details->card_number)) ? $details->card_number : '';
						// 	$operationDesc = 'Received payment of ' . moneyFormat($details->amount) . ' ' . $details->payment_type . ' ' . $paymentMethod . ' (' . $cardNumber . ') ' . $details->created_by_with_name . '<br/>' . $detailCostDesc;
						// }
					}
					//add on history end

					$historyDet[$key]->history_api_data = $operationDesc;
					if ($details->payment_type == "Card" || ($isCashFees == '1' && $details->payment_type == "Cash")) {
						$detailCostDesc = '';

						$cardNumber = (isset($details->card_number)) ? $details->card_number : '';
						if ($hist->charge_type == 'discount') {
							$detailCostDesc = 'New Discount : ' . moneyFormat($details->discount) . '<br /> Old Discount : ' . moneyFormat($details->old_discount2_value);
						} else {
							if ($details->payment_type == "Cash" && isset($hist->change_amount) && $hist->change_amount > 0 && isset($details->amount)) {
								$paidAmountCash = (float)$hist->change_amount + (float)$details->amount;
								$detailCostDesc .= 'Paid: ' . moneyFormat($paidAmountCash);
								$detailCostDesc .= '<br /> Change: ' . moneyFormat($hist->change_amount);
								$detailCostDesc .= '<br /> Ticket Cost: ' . moneyFormat($hist->ticket_cost) . '<br/> Processing Fees: ' . moneyFormat($hist->processing_fees) . '<br/> Platform Fees: ' . moneyFormat($hist->platform_fees) . '';
							} else {
								$detailCostDesc = 'Ticket Cost: ' . moneyFormat($hist->ticket_cost) . '<br/> Processing Fees: ' . moneyFormat($hist->processing_fees) . '<br/> Platform Fees: ' . moneyFormat($hist->platform_fees) . '';
							}
						}
						if ((isset($hist->discount) && $hist->discount > 0) && (isset($paymentMethod) && $paymentMethod != 'Split Payment')) {
							$discountPromoCode = '(' . $promoCode . ')';
							$detailCostDesc .= '<br/> Discount: ' . moneyFormat($hist->discount) . $discountPromoCode;
						}

						//add on history start
						if ($hist->charge_type == 'addons') {
							$details = $hist->details;
							$detailCostDesc = '';
							$operationDesc = 'Received payment of ' . moneyFormat($hist->ticket_cost + $hist->platform_fees) . ' ' . $details->created_by_with_name . '<br />';
							foreach ($details->addons as $addon) {
								$detailCostDesc .= '<b>Name</b>: ' . sprintf('%s', $addon->name);
								if ($addon->fee) {
									$detailCostDesc .= '<br /><b>Paid</b>: ' . moneyFormat($addon->price + $addon->fee) . ' (<b>Price</b>: ' . moneyFormat($addon->price) . ', <b>Fee</b>: ' . moneyFormat($addon->fee) . ')';
								} else {
									$detailCostDesc .= '<br /><b>Paid</b>: ' . moneyFormat($addon->price + $addon->fee);
								}
								$detailCostDesc .= '<br />';
							}
						}
						//add on history end

						if ($details->payment_type == "Cash") {
							$operationDesc = 'Received payment of ' . moneyFormat($details->amount) . ' ' . $details->payment_type . ' ' . $paymentMethod . ' ' . $details->created_by_with_name . '<br/>' . $detailCostDesc;
						} else {
							$operationDesc = 'Received payment of ' . moneyFormat($details->amount) . ' ' . $details->payment_type . ' ' . $paymentMethod . ' (' . $cardNumber . ') ' . $details->created_by_with_name . '<br/>' . $detailCostDesc;
						}


						$historyDet[$key]->history_api_data = $operationDesc;
					}
					if (in_array($details->payment_type, $otaPlatforms)) {
						$detailCostDesc = '';
						$card_number = (isset($details->card_number)) ? $details->card_number : '';
						$detailCostDesc = 'Ticket Cost: ' . moneyFormat($hist->ticket_cost) . '<br /> Processing Fees: ' . moneyFormat($hist->processing_fees) . '<br /> Platform Fees: ' . moneyFormat($hist->platform_fees) . '';
						if ((isset($hist->discount) && $hist->discount > 0) && (isset($paymentMethod) && $paymentMethod != 'Split Payment')) {
							$detailCostDesc .= '<br /> Discount: $' . $hist->discount;
						}
						$operationDesc = 'Received payment of ' . moneyFormat($details->amount) . ' ' . $details->payment_type . ' ' . $paymentMethod . $details->created_by_with_name . '<br />' . $detailCostDesc;
						$historyDet[$key]->history_api_data = $operationDesc;
					}
				} else {
					$detailCostDesc = '';
					$detailsBreakDownPartialRefundsDesc = '';
					$descDeleted = (isset($details->deleted) && !empty($details->deleted) && $details->refund_type != 'Permitfees Refund') ? '<b>Deleted</b>: <br/>' . $details->deleted . '<br />' : '';
					if ($hist->charge_type == 'discount') {
						$detailCostDesc = 'New Discount : ' . moneyFormat($details->discount) . '<br /> Old Discount : ' . moneyFormat($details->old_discount2_value);
					} else {
						if ($details->payment_type == 'Card' || $hist->payment_type == 'Card' || ($isCashFees == '1' && ($hist->payment_type == 'Cash' || $details->payment_type == 'Cash'))) {
							$detailCostDesc = 'Ticket Cost: ' . moneyFormat($hist->ticket_cost) . '<br/> Processing Fees: ' . moneyFormat($hist->processing_fees) . '<br/> Platform Fees: ' . moneyFormat($hist->platform_fees) . '';
						} else {
							$detailCostDesc = 'Ticket Cost: ' . moneyFormat($hist->ticket_cost);
						}
					}
					//add on history start
					if ($hist->charge_type == 'addons') {
						$details = $hist->details;
						$detailCostDesc = '';
						$operationDesc = 'Refunded amount of ' . moneyFormat($hist->ticket_cost + $hist->platform_fees) . ' ' . $details->created_by_with_name . '<br />';
						foreach ($details->addons as $addon) {
							$detailCostDesc .= '<b>Name</b>: ' . sprintf('%s', $addon->name);
							if ($addon->fee) {
								$detailCostDesc .= '<br /><b>Refunded</b>: ' . moneyFormat($addon->price + $addon->fee) . ' (<b>Price</b>: ' . moneyFormat($addon->price) . ', <b>Fee</b>: ' . moneyFormat($addon->fee) . ')';
							} else {
								$detailCostDesc .= '<br /><b>Refunded</b>: ' . moneyFormat($addon->price + $addon->fee);
							}
							$detailCostDesc .= '<br />';
						}
						if (isset($details->description)) {
							$detailCostDesc .= '<b>Note</b>: ' . $details->description;
						}
					}
					//add on history end
					$note = (isset($details->note)) ? $details->note : '';
					if ($details->refund_type == 'Full Refund minus Fees') {
						$details->refund_type = 'Ticket Cost';
					}
					$cardNumber = '';
					if ($details->payment_type == 'Card' || $hist->payment_type == 'Card') {
						if ($hist->card_last_4_digit != '') {
							$cardNumber = '(' . $hist->card_last_4_digit . ')';
						}
					}
					if ($hist->charge_type == 'discount') {
						$operationDesc = 'Refunded amount of ' . moneyFormat($details->amount) . ' ' . $details->payment_type . $cardNumber . ' ' . $details->deleted . ' ' . $details->refund_type . ' ' . $details->created_by_with_name . '<br/>' . $descDeleted . $detailCostDesc;
					} else {
						if ($note != '') {
							$operationDesc = 'Refunded amount of ' . moneyFormat($details->amount) . ' ' . $details->payment_type . $cardNumber . ' ' . $details->deleted . ' ' . $details->refund_type . ' ' . '(Note: ' . $note . ') ' . $details->created_by_with_name . '<br/>' . $descDeleted . $detailCostDesc;
						} else {
							$operationDesc = 'Refunded amount of ' . moneyFormat($details->amount) . ' ' . $details->payment_type . $cardNumber . ' ' . $details->deleted . ' ' . $details->refund_type . ' ' . $details->created_by_with_name . '<br/>' . $descDeleted . $detailCostDesc;
						}
					}
					if ($details->payment_type == 'Card' || $hist->payment_type == 'Card') {
						if ($details->refund_type == 'Partial Refund' && isset($details->cards) && !empty($details->cards) && isset($details->refund_amounts) && !empty($details->refund_amounts)) {
							$detailsBreakDownPartialRefundsDesc .= '<br/> <b>Breakdown</b>: <br/>';
							$last_key = array_key_last($details->cards);
							foreach ($details->cards as $cardk => $cardno) {
								$detailsBreakDownPartialRefundsDesc .= (isset($details->refund_amounts[$cardk])) ? moneyFormat($details->refund_amounts[$cardk]) . ' (' . $cardno . ') ' : '';
								if ($cardk != $last_key) {
									$detailsBreakDownPartialRefundsDesc .= '<br/>';
								}
							}
						}
					}
					$operationDesc .= $detailsBreakDownPartialRefundsDesc;
					$historyDet[$key]->history_api_data = $operationDesc;
					if (!empty($details) && isset($details->from) && $details->from == 'Extend Booking') {
						$historyDet[$key]->operation = 'Reallocated Funds';
					}
				}

				if ($hist->tourists && $hist->charge_type != 'discount') {
					$historyDet[$key]->history_api_data .= '<br /> <b>New Pax Count</b>: ' . $hist->tourists;
				}
			} elseif ($hist->operation == 'Update') {
				$details = $hist->details;
				if (isset($details->sub_operation) && $details->sub_operation && ($details->sub_operation === 'Tourists Added' || $details->sub_operation === 'Tourists Changed')) {
					$details->created_by_with_name = sprintf('%s', $details->created_by_with_name ?? '');

					$descAdd = '';
					if (isset($details->added) && !empty($details->added)) {
						if (is_array($details->added)) {
							$addedImplode = implode('<br>', $details->added);
							$descAdd = sprintf('%s', $addedImplode);
						} else {
							$descAdd = sprintf('%s', $details->added ?? '');
						}
					}

					$descDelete = '';
					if (isset($details->deleted) && !empty($details->deleted)) {
						if (is_array($details->deleted)) {
							$deletedImplode = implode('<br>', $details->deleted);
							$descDelete = sprintf('%s', $deletedImplode);
						} else {
							$descDelete = sprintf('%s', $details->deleted ?? '');
						}
					}

					if ($details->sub_operation === 'Tourists Added') {
						$operationDesc = 'Edit booking - ' . $details->newTourists . ' tourist(s) added. ' . $details->created_by_with_name . "<br>" . $descAdd;
					} else {
						$operationDesc = 'Edit booking - ' . $details->addedTourists . ' tourist(s) added. ' . $details->deletedTourits . ' tourist(s) deleted. ' . $details->created_by_with_name . '<br>';
						if ($descAdd != "") {
							$operationDesc .= '<b>Added</b>: <br/>' . $descAdd . '<br/>';
						}
						if ($descDelete != "") {
							$operationDesc .= '<b>Deleted</b>: <br/>' . $descDelete . '<br/>';
						}
					}
					$hist->operation = 'Update Pax';
					if ($hist->tourists) {
						$operationDesc .= '<b>New Pax Count</b>: ' . $hist->tourists;
					}
					$historyDet[$key]->history_api_data = $operationDesc;
				} else {
					$details->amount = sprintf('%0.2f', $details->amount);
					$details->created_by_with_name = sprintf('%s', $details->created_by_with_name ?? '');
					$hist->operation = 'Permit';
					if (isset($details->permit_fees)) {
						$operationDesc = 'Edit permit fee updated amount ' . moneyFormat($details->amount) . '(Permit Fees Refunded : ' . moneyFormat($details->permit_fees) . ' ) ' . $details->created_by_with_name;
						$historyDet[$key]->history_api_data = $operationDesc;
					} else {
						$operationDesc = 'Edit permit fee updated amount ' . moneyFormat($details->amount) . ' ' . $details->created_by_with_name;
						$historyDet[$key]->history_api_data = $operationDesc;
					}
				}
			} elseif ($hist->operation == 'Added') {
				$details = $hist->details;
				$details->created_by_with_name = sprintf('%s', $details->created_by_with_name ?? '');

				$descAdd = '';
				if (isset($details->added) && !empty($details->added)) {
					if (is_array($details->added)) {
						$addedImplode = implode('<br>', $details->added);
						$descAdd = sprintf('%s', $addedImplode);
					} else {
						$descAdd = sprintf('%s', $details->added ?? '');
					}
				}

				$operationDesc = 'Added booking - ' . $details->newTourists . ' tourist(s) added. ' . $details->created_by_with_name . "<br>" . $descAdd;
				$hist->operation = 'Original';
				if ($hist->tourists) {
					$operationDesc .= '<br /> <b>New Pax Count</b>: ' . $hist->tourists;
				}
				$historyDet[$key]->history_api_data = $operationDesc;
			} elseif ($hist->operation == 'Cancelled') {
				$details = $hist->details;

				$descDelete = '';
				$detailsBreakDownPartialRefundsDesc = '';
				if (isset($details->deleted) && !empty($details->deleted)) {
					if (is_array($details->deleted)) {
						$deletedImplode = implode('<br>', $details->deleted);
						$descDelete = sprintf('%s', $deletedImplode);
					} else {
						$descDelete = sprintf('%s', $details->deleted ?? '');
					}
				}

				$details->amount = sprintf('%0.2f', $details->amount);
				$details->created_by_with_name = sprintf('%s', $details->created_by_with_name ?? '');
				$details->payment_type = sprintf('%s', $details->payment_type ?? '');
				$details->refund_type = sprintf('%s', $details->refund_type ?? '');
				$details->note = sprintf('%s', $details->note ?? '');
				$detailCostDesc = '';
				$cardNumber = '';
				if ($details->payment_type == 'Card' || $hist->payment_type == 'Card') {
					if ($hist->card_last_4_digit != '') {
						$cardNumber = '(' . $hist->card_last_4_digit . ')';
					}
				}
				if ($details->payment_type == 'Card' || $hist->payment_type == 'Card' || ($isCashFees == '1' && ($details->payment_type == 'Cash' || $hist->payment_type == 'Cash'))) {
					$detailCostDesc = 'Ticket Cost: ' . moneyFormat($hist->ticket_cost) . '<br/> Processing Fees: ' . moneyFormat($hist->processing_fees) . '<br/> Platform Fees: ' . moneyFormat($hist->platform_fees) . '';
				} else {
					$detailCostDesc = 'Ticket Cost: ' . moneyFormat($hist->ticket_cost);
				}
				if ($details->refund_type == 'Full Refund') {
					$details->refund_type = 'Ticket Cost';
				}
				$operationDesc = 'Booking cancelled' . ' ' . $descDelete . ' ' . ' ' . moneyFormat($details->amount) . ' ' . $details->payment_type . ' ' . $cardNumber . ' ' . $details->refund_type . ' ' . '(Note: ' . $details->note . ') ' . $details->created_by_with_name . '<br/>' . $detailCostDesc;
				if ($details->payment_type == 'Card' || $hist->payment_type == 'Card') {
					if (isset($details->cards) && !empty($details->cards) && isset($details->refund_amounts) && !empty($details->refund_amounts)) {
						$detailsBreakDownPartialRefundsDesc .= '<br/> <b>Breakdown</b>: <br/>';
						$last_key = array_key_last($details->cards);
						foreach ($details->cards as $cardk => $cardno) {
							$detailsBreakDownPartialRefundsDesc .= (isset($details->refund_amounts[$cardk])) ? moneyFormat($details->refund_amounts[$cardk]) . ' (' . $cardno . ') ' : '';
							if ($cardk != $last_key) {
								$detailsBreakDownPartialRefundsDesc .= '<br/>';
							}
						}
					}
					$operationDesc .= $detailsBreakDownPartialRefundsDesc;
				}
				if ($hist->tourists) {
					$operationDesc .= '<br /> <b>New Pax Count</b>: ' . $hist->tourists;
				}
				$historyDet[$key]->history_api_data = $operationDesc;
			} elseif ($hist->operation == 'Email') {
				if (str_contains($hist->details, 'sent') || str_contains($hist->details, 'delivered') || str_contains($hist->details, 'viewed') || str_contains($hist->details, 'clicked') || str_contains($hist->details, 'spam') || str_contains($hist->details, 'bounce')) {
					if (str_contains($hist->details, 'sent')) {
						$operationDesc = e($hist->details) . ' by ' . $hist->username . ': (' . $hist->operation_by_user_type . ')';;
					} else {
						$operationDesc = e($hist->details);
					}
				} else {
					$operationDesc = e($hist->details) . ' by ' . $hist->username;
				}
				$historyDet[$key]->history_api_data = $operationDesc;
			} elseif ($hist->operation == 'Check-in') {
				$details = $hist->details;
				$details->created_by_with_name = sprintf('%s', $details->created_by_with_name ?? '');
				$operationDesc = 'Set check-in status to <b>' . $details->status . '</b> for <b>' . e($details->who) . '</b>' . ' ' . $details->created_by_with_name;
				$historyDet[$key]->history_api_data = $operationDesc;
			} elseif ($hist->operation == 'Booked By') {
				$details = $hist->details;
				$operationDesc = 'Changed booked by person from <b>' . e($details->from) . '</b> to <b>' . e($details->to) . '</b>';
				$historyDet[$key]->history_api_data = $operationDesc;
			} elseif ($hist->operation == 'Rescheduled') {
				$details = $hist->details;
				if (isset($details->dateFrom)) {
					$dateTimeFrom = formatDateTime($details->dateFrom . ' ' . $details->timeFrom);
					$dateTimeTo = formatDateTime($details->dateTo . ' ' . $details->timeTo);
					$details->created_by_with_name = sprintf('%s', $details->created_by_with_name ?? '');
					$operationDesc = "Rescheduled from <b>$dateTimeFrom</b> to <b>$dateTimeTo</b>" . ' ' . $details->created_by_with_name;
				} else {
					$operationDesc = "Rescheduled the booking";
				}
				$historyDet[$key]->history_api_data = $operationDesc;
			} elseif ($hist->operation == 'Waiver') {
				$details = $hist->details;
				$data = ($details && isset($details->whoSignIn) && $details->whoSignIn) ? $details->whoSignIn : '';
				$string = ($data) ? implode(" , ", $data) : '';
				$operationDesc = 'Waivers sign By <b>' . $string . '</b>';
				$historyDet[$key]->history_api_data = $operationDesc;
			} elseif ($hist->operation == 'Customer Info Update') {
				$details = $hist->details;
				$name = '';
				$email = '';
				$phone_number = '';
				if ($opByUType == 'Tour Operator') {
					$opByUType = 'A tour operator staff';
					$finduser = Staff::where('id', $hist->operation_by)->first();
					$staffname = 'updated by a tour operator staff' . ': ' . $finduser->first_name . ' ' . $finduser->last_name;
				} else if ($opByUType == 'Affiliate') {
					$opByUType = 'An affiliate';
					$finduser = Customer::where('id', $hist->operation_by)->withTrashed()->first();
					$staffname = 'updated by an affiliate' . ': ' . $finduser->name;
				} else {
					$opByUType = 'An admin';
					$finduser = Admin::where('id', $hist->operation_by)->first();
					if ($finduser == null) {
						$finduser = Staff::where('id', $hist->operation_by)->first();
						$staffname = 'updated by a tour operator staff' . ': ' . $finduser->first_name . ' ' . $finduser->last_name;
					} else {
						$staffname = 'updated by an admin' . ': ' . $finduser->name;
					}
				}
				if (isset($details->name) && !empty($details->name)) {
					$name = 'Name' . ': (' . $details->name . ')';
				}
				if (isset($details->email) && !empty($details->email)) {
					$email = 'Email' . ': (' . $details->email . ')';
				}
				if (isset($details->phone_number) && !empty($details->phone_number)) {
					$phone_number = 'Phone number' . ': (' . $details->phone_number . ')';
				}
				$operationDesc = 'Customer Information update <b>' . '' . $name . ' ' . $email . ' ' . $phone_number . ' ' . $staffname . '</b>';
				$historyDet[$key]->history_api_data = $operationDesc;
			} elseif ($hist->operation == 'Tip') {
				$details = $hist->details;
				$details->amount = sprintf('%0.2f', $details->amount);
				$details->payment_type = sprintf('%s', $details->payment_type ?? '');
				$details->created_by_with_name = sprintf('%s', $details->created_by_with_name ?? '');
				$operationDesc = 'Received Tip of ' . moneyFormat($details->amount) . ' ' . $details->payment_type . ' ' . $details->created_by_with_name;
				$historyDet[$key]->history_api_data = $operationDesc;
				if ($details->payment_type == "Card" && isset($details->card_number)) {
					$operationDesc = 'Received Tip of ' . moneyFormat($details->amount) . ' ' . $details->payment_type . ' (' . $details->card_number . ') ' . $details->created_by_with_name;
					$historyDet[$key]->history_api_data = $operationDesc;
				}
			}

			// unset($hist->id);
			unset($hist->tour_booking_id);
			// unset($hist->operation);
			unset($hist->details);
			unset($hist->operation_by);
			// unset($hist->opedddration_by_user_type);
			unset($hist->updated_at);
			unset($hist->created_at);
		}

		return $historyDet;
	}

	/**
	 * Execute the history service to get the details.
	 *
	 * @param mixed $bookingM
	 *
	 * @return mixed
	 */
	public static function historyData($bookingM)
	{
		$historyDet = DB::table('tour_booking_history')
			->orderByDesc('operation_at')
			->orderByRaw("CASE WHEN tourists > 0 THEN tourists END ASC")
			->where('tour_booking_id', $bookingM->id)
			->get();

		$emailHistoryIds = collect($historyDet)->where('operation', 'Email')->pluck('id');
		$emailEventsMap  = $emailHistoryIds->isNotEmpty()
			? DB::table('tour_booking_email_events')
				->leftJoin('sent_emails', function ($join) {
					$join->on('sent_emails.id', '=', 'tour_booking_email_events.sent_email_id')
						->where('tour_booking_email_events.status', '=', 'Sent');
				})
				->whereIn('tour_booking_email_events.tour_booking_history_id', $emailHistoryIds)
				->select(
					'tour_booking_email_events.*',
					'sent_emails.recipient_name',
					'sent_emails.recipient_email',
					'sent_emails.subject',
					'sent_emails.message_id',
					'sent_emails.sender_name',
					'sent_emails.content',
					'sent_emails.created_at as sent_at'
				)
				->get()
				->groupBy('tour_booking_history_id')
			: collect();

		$transactions = DB::table('transactions')->where('tour_booking_id', $bookingM->id)->get();


		$historyRefund = DB::table('tour_booking_history')
			->orderByDesc('operation_at')
			->where('tour_booking_id', $bookingM->id)
			->where('payment_method', 'Partial Refund')
			->first();

		$splitRefundCheck = 0;
		if (!empty($historyRefund)) {
			$details = json_decode($historyRefund->details);
			$splitRefundCheck = isset($details->deleted) ? 0 : 1;
		}

		$ticketCostRefundCheckChangePeople = 0;
		$created_by_with_name = '';
		foreach ($historyDet as $historyData) {
			$converted_date = '';

			if (($historyData->operation == "Note") || ($historyData->operation == "Comments") || ($historyData->operation == "Email") || ($historyData->operation == "Error") || ($historyData->operation == 'Card Error') || ($historyData->operation == "Reference Booking") || ($historyData->operation == "Payment Link")) {
				$historyData->username = '';
				if ($historyData->operation == "Note" || $historyData->operation == "Email" || $historyData->operation == "Comments" || $historyData->operation == "Reference Booking" || $historyData->operation == "Payment Link") {
					if ($historyData->operation_by_user_type == 'Tour Operator') {
						$finduser = Staff::where('id', $historyData->operation_by)->first();
						$name = $finduser->first_name . ' ' . $finduser->last_name;
					} else if ($historyData->operation_by_user_type == 'Affiliate') {
						$finduser = Customer::where('id', $historyData->operation_by)->withTrashed()->first();
						$name = $finduser->name;
					} else {
						$finduser = Admin::where('id', $historyData->operation_by)->first();
						$name = $finduser->name ?? '';
					}
					$historyData->username = $name;
					if ($historyData->operation == 'Email') {
						$historyData->emailEvents = $emailEventsMap->get($historyData->id, collect());
					}
				} else if ($historyData->operation == "Customer Info Update") {
					$name = '';
					$email = '';
					$phone_number = '';
					if ($historyData->operation_by_user_type == 'Tour Operator') {
						$finduser = Staff::where('id', $historyData->operation_by)->first();
					} else if ($historyData->operation_by_user_type == 'Affiliate') {
						$finduser = Customer::where('id', $historyData->operation_by)->withTrashed()->first();
					} else {
						$finduser = Admin::where('id', $historyData->operation_by)->first();
					}
					if (isset($details) && !empty($details->name)) {
						$name = 'Name' . ': (' . $details->name . ')';
					}
					if (isset($details->email) && !empty($details->email)) {
						$email = 'Email' . ': (' . $details->email . ')';
					}
					if (isset($details->phone_number) && !empty($details->phone_number)) {
						$phone_number = 'Phone number' . ': (' . $details->phone_number . ')';
					}
					$historyData->username = $name . ' ' . $email . ' ' . $phone_number;
				}
			} elseif ($historyData->charge_type == "discount") {
				$historyData->details = json_decode($historyData->details);
			} else {
				$historyData->details = json_decode($historyData->details);
			}
			if ($historyData->operation == 'Added') {
				$created_by_with_name = (isset($historyData->details->created_by_with_name) && $historyData->details->created_by_with_name != '') ? $historyData->details->created_by_with_name : '';
			}
			$operation_at = $historyData->operation_at;

			if ($operation_at != '' && $bookingM->timezone != '') {
				$converted_date = Carbon::createFromFormat('Y-m-d H:i:s', $operation_at, config('app.timezone'))
					->setTimezone($bookingM->timezone)->format('Y-m-d H:i:s');
				if ($converted_date != '') {
					$historyData->operation_at = $converted_date;
				}
			}

			// Condition to check ticket cost possible for change people operation
			if ($historyData->operation == "Update" && isset($historyData->details->added) && !empty($historyData->details->added) && isset($historyData->details->deleted) && !empty($historyData->details->deleted)) {
				$ticketCostRefundCheckChangePeople = 1;
			}
			$historyData->transaction_id = (int)$historyData->transaction_id;
		}
		return [
			'splitRefundCheck' => $splitRefundCheck,
			'ticketCostRefundCheckChangePeople' => $ticketCostRefundCheckChangePeople,
			'historyData' => $historyDet,
			'createdByWithName' => $created_by_with_name
		];
	}

	/**
	 * Build the customer-facing "Payment & Refund History" log for an invoice/receipt —
	 * a plain, chronological array the view can just loop over with no business logic of
	 * its own (a customer confused whether an old refund actually happened, e.g. a permit
	 * fee refund from months earlier, is what this exists to answer: every invoice render
	 * shows the FULL history, not just a net-total snapshot).
	 *
	 * Deliberately reuses the same resolve*Data() helpers (app/helpers.php) that already
	 * back the admin History tab (_payment.blade.php / _refund.blade.php /
	 * _cancel.blade.php / _permit-fees-refund.blade.php) and the same dispatch rule
	 * (_history-log-body.blade.php: operation == 'Refund' && payment_method ==
	 * 'Permitfees Refund' routes to the permit-fee case) — so the amounts and labels here
	 * can never drift from the ones already audited/trusted in the admin view.
	 *
	 * Takes the already-fetched $history collection (bookingInvoicePrint() already runs
	 * this exact query) rather than querying again.
	 *
	 * @param  iterable  $history  rows from tour_booking_history, one booking, any order
	 * @return array<int, array{date: string, label: string, amount: float, direction: 'in'|'out'}>
	 */
	public static function customerFacingPaymentLog($history): array
	{
		$log = [];

		foreach ($history as $hist) {
			switch ($hist->operation) {
				case 'Payment':
					$d = resolvePaymentData($hist);
					$amount = (float) $d['paymentAmount'];
					// A Pay Later add-on charge is recorded before anything is actually
					// collected (see resolvePaymentData()'s own comment) — nothing to log yet.
					if ($amount <= 0) {
						continue 2;
					}
					$label = match ($hist->charge_type) {
						'addons' => 'Payment — Add-ons',
						'custom_charge' => 'Payment — Custom Charge',
						'discount' => 'Payment — Discount Adjustment',
						default => 'Payment',
					};
					$log[] = ['date' => $hist->operation_at, 'label' => $label, 'amount' => $amount, 'direction' => 'in'];
					break;

				case 'Refund':
					if (empty($hist->payment_type)) {
						continue 2;
					}
					if ($hist->payment_method === 'Permitfees Refund') {
						$d      = resolvePermitFeesRefundData($hist);
						$amount = (float) $d['totalAmount'];
						$label  = 'Permit Fee Refund';
					} else {
						$d = resolveRefundData($hist);
						// "Don't Refund" carries the amount that was KEPT, not returned to the
						// customer — showing it here would read as money that came back when it
						// didn't. bookingInvoicePrint()'s own aggregate refund total already
						// excludes this refund_type for the same reason; matching that.
						if (($d['refundType'] ?? '') === "Don't Refund") {
							continue 2;
						}
						$amount = (float) $d['refundAmount'];
						$label  = match (true) {
							$d['isCustomCharge'] => 'Custom Charge Refund',
							$d['isAddons'] => 'Add-ons Refund',
							!empty($d['deletedGroups']) => implode(', ', array_keys($d['deletedGroups'])) . ' Removed — Refund',
							default => $d['refundType'] !== '' ? $d['refundType'] : 'Refund',
						};
					}
					if ($amount <= 0) {
						continue 2;
					}
					$log[] = ['date' => $hist->operation_at, 'label' => $label, 'amount' => $amount, 'direction' => 'out'];
					break;

				case 'Cancelled':
					$d      = resolveCancelData($hist);
					$amount = (float) ($d['refundAmount'] ?? 0);
					if ($amount <= 0) {
						continue 2;
					}
					$log[] = ['date' => $hist->operation_at, 'label' => 'Booking Cancelled — Refund', 'amount' => $amount, 'direction' => 'out'];
					break;

				default:
					// Note, Comments, Email, Waiver, Check-in, etc. don't move money — not
					// relevant to a payment/refund log a customer needs to reconcile.
					break;
			}
		}

		// Oldest first: this reads as a natural timeline on an invoice, the opposite of the
		// admin log's newest-first feed.
		usort($log, fn ($a, $b) => strtotime($a['date']) <=> strtotime($b['date']));

		return $log;
	}

	/**
	 * Store comments of the user in the history.
	 *
	 * @param string $comment
	 *
	 * @return void
	 */
	public static function storeCommentInHistory($comment, $bookingId): void
	{
		DB::table('tour_booking_history')
			->insert([
				'tour_booking_id' => $bookingId,
				'operation' => 'Comments',
				'details' => $comment,
				'operation_at' => mysqlDT(),
				'operation_by' => Auth::id(),
				'operation_by_user_type' => userType(),
				'updated_at' => Carbon::now(),
				'created_at' => Carbon::now(),
			]);
	}

	/**
	 * Store reference booking in the history.
	 *
	 * @param int $bookingId
	 *
	 * @return void
	 */
	public static function storeReferenceBookingInHistory($bookingId, $referenceBooking): void
	{
		DB::table('tour_booking_history')
			->insert([
				'tour_booking_id' => $bookingId,
				'operation' => 'Reference Booking',
				'details' => $referenceBooking,
				'operation_at' => mysqlDT(),
				'operation_by' => Auth::id(),
				'operation_by_user_type' => userType(),
				'updated_at' => Carbon::now(),
				'created_at' => Carbon::now(),
			]);
	}

	/**
	 * Store tourists data in the history.
	 *
	 * @param mixed $request
	 *
	 * @return void
	 */
	public static function storeTouristInHistory($request, $tourBooking, $discountAmt, $addedPeoplesText, $totalTourists, $created_by_with_name, $cartPackage = [], $fromMethod = null): void
	{
		$options = [
			'sub_operation' => 'Tourists Added',
			'discount2_value' => $discountAmt,
			'added' => $addedPeoplesText,
			'newTourists' => $totalTourists,
			'created_by_with_name' => $created_by_with_name,
		];

		$options['discount_percentage'] = 0;
		if ($request->discount_percentage) {
			$options['discount_percentage'] = $request->discount_percentage;
		}

		$options['discount_promo_code'] = '';
		if (isset($request->discount_promo_code)) {
			$options['discount_promo_code'] = $request->discount_promo_code;
		} elseif (isset($cartPackage['discount_promo_code'])) {
			$options['discount_promo_code'] = $cartPackage['discount_promo_code'];
		}

		if (isset($fromMethod) && !empty($fromMethod)) {
			$options['method'] = $fromMethod;
		}

		$operationTouristsAdded = [
			'tour_booking_id' => $tourBooking->id,
			'tourists' => $tourBooking->tourists + $tourBooking->infants,
			'operation' => 'Added',
			'details' => json_encode($options, JSON_NUMERIC_CHECK),
			'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
			'operation_by' => Auth::id(),
			'operation_by_user_type' => userType(),
			'updated_at' => Carbon::now(),
			'created_at' => Carbon::now(),
		];

		DB::table('tour_booking_history')->insert($operationTouristsAdded);
	}

	/**
	 * Store tourists data in the history.
	 *
	 * @param int $bookingId
	 *
	 * @return void
	 */
	public static function updateTouristInHistory($bookingId, $data, $tourBookingDetails, $created_by_with_name): void
	{
		if (isset($data['permit_fees'])) {
			$options = [
				'amount' => $data['amount'],
				'permit_fees' => $data['permit_fees'],
				'payment_type' => $data['payment_type'],
			];
		} else {
			$options = [
				'sub_operation' => 'Tourists Added',
				'discount_percentage' => $data['discount_percentage'] ?: 0,
				'discount2_value' => $data['discount2_value'] ?: 0,
				'added' => (isset($data['addedpeoplestexts'])) ? $data['addedpeoplestexts'] : '',
				'newTourists' => $data['newTourists'],
			];
		}

		$options['created_by_with_name'] = $created_by_with_name;

		$operationTouristsAdded = [
			'tour_booking_id' => $bookingId,
			'tourists' => $tourBookingDetails->tourists + $tourBookingDetails->infants,
			'operation' => 'Update',
			'details' => json_encode($options, JSON_NUMERIC_CHECK),
			'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
			'operation_by' => Auth::id(),
			'operation_by_user_type' => userType(),
			'updated_at' => Carbon::now(),
			'created_at' => Carbon::now(),
		];

		DB::table('tour_booking_history')
			->insert($operationTouristsAdded);
	}

	/**
	 * Store tourists data in the history.
	 *
	 * @param int $bookingId
	 *
	 * @return void
	 */
	public static function changeTouristInHistory($bookingId, $data, $created_by_with_name, int $userId = null, string $userType = null): void
	{
		$updatedUserType = $userType ?? userType();
    	$updatedUserId   = $userId ?? Auth::id();

		$operationTouristsChanged = [
			'tour_booking_id' => $bookingId,
			'tourists' => $data['tourists'],
			'operation' => 'Update',
			'details' => json_encode([
				'sub_operation' => 'Tourists Changed',
				'discount_percentage' => (isset($data['discount_percentage'])) ? $data['discount_percentage'] : 0,
				'discount2_value' => (isset($data['discount2_value'])) ? $data['discount2_value'] : 0,
				'added' => (isset($data['addedpeoplesetexts'])) ? $data['addedpeoplesetexts'] : '',
				'deleted' => (isset($data['deletedpeoplestexts'])) ? $data['deletedpeoplestexts'] : '',
				'addedTourists' => (isset($data['addedtourists'])) ? $data['addedtourists'] : '',
				'deletedTourits' => (isset($data['deletedtourits'])) ? $data['deletedtourits'] : '',
				'addedInfants' => (isset($data['addedinfants'])) ? $data['addedinfants'] : '',
				'deletedInfants' => (isset($data['deletedinfants'])) ? $data['deletedinfants'] : '',
				'paymenttotal' => (isset($data['paymenttotal'])) ? $data['paymenttotal'] : '',
				'refundtotal' => (isset($data['refundtotal'])) ? $data['refundtotal'] : '',
				'txnhistoryid' => (isset($data['txnhistoryid'])) ? $data['txnhistoryid'] : '',
				'created_by_with_name' => $created_by_with_name
			], JSON_NUMERIC_CHECK),
			'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
			'operation_by' => $updatedUserId,
			'operation_by_user_type' => $updatedUserType,
			'updated_at' => Carbon::now(),
			'created_at' => Carbon::now(),
		];

		DB::table('tour_booking_history')
			->insert($operationTouristsChanged);
	}

	/**
	 * Store payment data in the history.
	 *
	 * @param mixed $request
	 *
	 * @return int
	 */
	public static function storeCashPaymentInHistory($bookingId, $tourists, $data, $changeAmount, $created_by_with_name): int
	{
		$options = [
			'amount' => $data['amount'],
			'payment_method' => $data['payment_method'],
			'payment_type' => 'Cash',
			'created_by_with_name' => $created_by_with_name,
			'discount_promo_code' => '',
		];

		if (isset($data['payment_type']) && !empty($data['payment_type'])) {
			$options['payment_type'] = $data['payment_type'];
		}

		if (isset($data['fees'])) {
			$options['fees'] = $data['fees'];
		}
		if (isset($data['fees'])) {
			$options['fees'] = $data['fees'];
		}

		if (isset($data['discount_promo_code']) && !empty($data['discount_promo_code'])) {
			$options['discount_promo_code'] = $data['discount_promo_code'];
		}

		if (isset($data['paymenttotal']) && !empty($data['paymenttotal'])) {
			$options['paymenttotal'] = $data['paymenttotal'];
		}

		if (isset($data['refundtotal']) && !empty($data['refundtotal'])) {
			$options['refundtotal'] = $data['refundtotal'];
		}

		if ((isset($data['custom_charge_amount']) && !empty($data['custom_charge_amount'])) || (isset($data['addon_price']) && !empty($data['addon_price'])) && isset($data['cash_paid_amount']) && !empty($data['cash_paid_amount'])) {
			$options['paid_amount'] = $data['cash_paid_amount'];
		}

		$historyId = DB::table('tour_booking_history')
			->insertGetId([
				'tour_booking_id' => $bookingId,
				'tourists' => $tourists,
				'change_amount' => $changeAmount,
				'package_swipe_history_id' => (isset($data['swipeHistoryId'])) ? $data['swipeHistoryId'] : '',
				'operation' => 'Payment',
				'details' => json_encode($options, JSON_NUMERIC_CHECK),
				'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
				'operation_by' => Auth::id(),
				'operation_by_user_type' => userType(),
				'updated_at' => Carbon::now(),
				'created_at' => Carbon::now(),
			]);

		DetectPayoutAdjustment::dispatch($bookingId, $historyId, 'Payment');

		return $historyId;
	}

	/**
	 * Store payment data in the history.
	 *
	 * @param int $bookingId
	 *
	 * @return int
	 */
	public static function storeCardPaymentInHistory($bookingId, $tourists, $data, $crd, $cardType, $created_by_with_name, $cardBookingData = [], $fromMethod = null, $changeAmount = null, $paymentMethod = null): int
	{
		$options = [
			'payment_type' => 'Card',
			'payment_method' => (isset($data['split_value']) && !empty($data['split_value']) || (isset($data['split_pending_amount']) && $data['split_pending_amount'] > 0) || (isset($data['payment_method']) && $data['payment_method'] == 'Split Payment')) ? 'Split Payment' : 'Full Payment',
			'created_by_with_name' => $created_by_with_name
		];

		if (isset($crd) && !empty($crd)) {
			$options['card_number'] = $crd;
		}

		if (isset($cardType) && !empty($cardType)) {
			$options['card_type'] = $cardType;
		}

		if (isset($paymentMethod) && !empty($paymentMethod)) {
			$options['payment_method'] = $paymentMethod;
		}

		if (isset($data['split_value']) && !empty($data['split_value']) && $data['split_value'] > 0) {
			$options['amount'] = $data['total'];
		} elseif (isset($cardBookingData['total'])) {
			$options['amount'] = $cardBookingData['total'];
		} else {
			$options['amount'] = $data['total'];
		}

		if (isset($data['fees'])) {
			$options['fees'] = $data['fees'];
		}

		if (isset($data['discount_promo_code']) && !empty($data['discount_promo_code'])) {
			$options['discount_promo_code'] = $data['discount_promo_code'];
		}

		if (isset($fromMethod)) {
			$options['from_method'] = $fromMethod;
		}

		if (isset($data['payment_type']) && !empty($data['payment_type'])) {
			$options['payment_type'] = $data['payment_type'];
		}

		if (isset($data['paymenttotal']) && !empty($data['paymenttotal'])) {
			$options['paymenttotal'] = $data['paymenttotal'];
		}

		if (isset($data['refundtotal']) && !empty($data['refundtotal'])) {
			$options['refundtotal'] = $data['refundtotal'];
		}

		$xData = [
			'tour_booking_id' => $bookingId,
			'tourists' => $tourists,
			'package_swipe_history_id' => (isset($data['swipeHistoryId'])) ? $data['swipeHistoryId'] : '',
			'operation' => 'Payment',
			'details' => json_encode($options, JSON_NUMERIC_CHECK),
			'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
			// This method is also reached from customer-facing Payment Link completion (e.g.
			// PaymentLinkService::handleStripePayment()) — there is no Auth at all on that path
			// — so resolve the real Customer by the booking's own email and stamp
			// operation_by_user_type as the static 'Customer' there.
			'operation_by' => Auth::id() ?? BookingService::customerIdByBookingEmail($bookingId),
			'operation_by_user_type' => Auth::id() ? userType() : 'Customer',
			'updated_at' => Carbon::now(),
			'created_at' => Carbon::now(),
		];

		if (isset($changeAmount)) {
			$xData['change_amount'] = $changeAmount;
		}

		$historyId = DB::table('tour_booking_history')
			->insertGetId($xData);

		DetectPayoutAdjustment::dispatch($bookingId, $historyId, 'Payment');

		return $historyId;
	}

	/**
	 * Store rescheduled data in the history.
	 *
	 * @param int $id
	 *
	 * @return void
	 */
	public static function storeRescheduledDataInHistory($id, $booking, $info, $created_by_with_name, int $userId = null, string $userType = null): void
	{
		DB::table('tour_booking_history')
			->insert([
				'tour_booking_id' => $id,
				'operation' => 'Rescheduled',
				'details' => json_encode([
					'dateFrom' => $booking->date,
					'timeFrom' => $booking->time_from,
					'dateTo' => $info->date,
					'timeTo' => $info->time_from,
					'created_by_with_name' => $created_by_with_name
				]),
				'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
				'operation_by' => $userId ?? Auth::id(),
				'operation_by_user_type' => $userType ?? userType(),
				'updated_at' => Carbon::now(),
				'created_at' => Carbon::now(),
			]);
	}

	/**
	 * Store cancelled booking data in the history.
	 *
	 * @param int $id
	 *
	 * @return int
	 */
	public static function storeCancelledBookingInHistory($id, $tourists, $data, $created_by_with_name): int
	{
		$options = [
			'amount' => $data['amount'],
			'created_by_with_name' => $created_by_with_name,
			'refund_keep_us' => 0,
		];

		if (isset($data['refund_keep_us']) && !empty($data['refund_keep_us'])) {
			$options['refund_keep_us'] = $data['refund_keep_us'];
		}

		if (isset($data['payment_type']) && !empty($data['payment_type'])) {
			$options['payment_type'] = $data['payment_type'];
		}

		if (isset($data['refund_type']) && !empty($data['refund_type'])) {
			$options['refund_type'] = $data['refund_type'];
		}

		if (isset($data['comment']) && !empty($data['comment'])) {
			$options['note'] = $data['comment'];
		}

		$lookup = [
			'tour_booking_id' => $id,
			'tourists' => $tourists,
			'operation' => 'Cancelled',
			'details' => json_encode($options, JSON_NUMERIC_CHECK),
			'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
			'operation_by' => Auth::id(),
			'operation_by_user_type' => userType(),
			'updated_at' => Carbon::now(),
			'created_at' => Carbon::now(),
		];

		if (isset($data['transaction_id']) && !empty($data['transaction_id'])) {
			$lookup['transaction_id'] = $data['transaction_id'];
		}

		$historyId = DB::table('tour_booking_history')
			->insertGetId($lookup);

		DetectPayoutAdjustment::dispatch($id, $historyId, 'Cancelled');

		return $historyId;
	}

	/**
	 * Store refund booking data in the history.
	 *
	 * @param int $id
	 *
	 * @return int
	 */
	public static function storeRefundDataInHistory($id, $tourists, $created_by_with_name, $comment = null, $refund_type = null, $transaction = null, $amount = 0, $cardNumber = null, $cardsArray = [], $partialAmountArray = [], $paymentMethod = null, $paymentTotal = null, $refundTotal = null, $refundKeepUs = 0, $deleted = null, $fees = 0, $from = null, $partnerFees = 0, $refundPartnerFeesKeepUs = 0): int
	{
		$options = [
			'amount' => $amount,
			'created_by_with_name' => $created_by_with_name,
		];

		if (isset($comment) && !empty($comment)) {
			$options['note'] = $comment;
		}

		if (isset($refundKeepUs)) {
			$options['refund_keep_us'] = $refundKeepUs;
		}

		if (isset($transaction) && !empty($transaction)) {
			$options['payment_type'] = $transaction->via;
		} elseif (isset($paymentMethod)) {
			$options['payment_type'] = $paymentMethod;
		}

		if (isset($cardsArray) && !empty($cardsArray)) {
			$options['cards'] = $cardsArray;
		}

		if (isset($partialAmountArray) && !empty($partialAmountArray)) {
			$options['refund_amounts'] = $partialAmountArray;
		}

		if (isset($refund_type) && !empty($refund_type)) {
			$options['refund_type'] = $refund_type;
		}

		if (isset($cardNumber) && !empty($cardNumber)) {
			$options['card_number'] = $cardNumber;
		}

		if (isset($paymentTotal) && !empty($paymentTotal)) {
			$options['paymenttotal'] = $paymentTotal;
		}

		if (isset($refundTotal) && !empty($refundTotal)) {
			$options['refundtotal'] = $refundTotal;
		}

		if (isset($deleted) && !empty($deleted)) {
			$options['deleted'] = $deleted;
		}

		if (isset($fees)) {
			$options['fees'] = $fees;
		}

		if (isset($partnerFees)) {
			$options['partner_fees'] = $partnerFees;
		}

		if (isset($refundPartnerFeesKeepUs)) {
			$options['refund_partner_fees_keep_us'] = $refundPartnerFeesKeepUs;
		}

		if (isset($from) && !empty($from)) {
			$options['from'] = $from;
		}

		$details = json_encode($options, JSON_NUMERIC_CHECK);

		$data = [
			'tour_booking_id' => $id,
			'tourists' => $tourists,
			'operation' => 'Refund',
			'details' => $details,
			'operation_at' => mysqlDT(),
			'operation_by' => Auth::id(),
			'operation_by_user_type' => userType(),
			'updated_at' => Carbon::now(),
			'created_at' => Carbon::now(),
		];

		if (isset($cardNumber) && !empty($cardNumber)) {
			$data['card_last_4_digit'] = $cardNumber;
		}

		$historyId = DB::table('tour_booking_history')
			->insertGetId($data);

		DetectPayoutAdjustment::dispatch($id, $historyId, 'Refund');

		return $historyId;
	}

	/**
	 * Store note of the booking in the history.
	 *
	 * @param mixed $note
	 *
	 * @return void
	 */
	public static function storeNoteInHistory($note, $tourBookingId, array $attachments = []): void
	{
		DB::table('tour_booking_history')
			->insert([
				'tour_booking_id' => $tourBookingId,
				'operation' => 'Note',
				'details' => $note,
				'attachments' => !empty($attachments) ? json_encode($attachments, JSON_UNESCAPED_SLASHES) : null,
				'operation_at' => mysqlDT(),
				'operation_by' => Auth::id(),
				'operation_by_user_type' => userType(),
				'updated_at' => Carbon::now(),
				'created_at' => Carbon::now(),
			]);
	}

	/**
	 * Store priority note for a booking in the history.
	 *
	 * @param string $note
	 * @param int    $tourBookingId
	 * @param bool   $popup
	 *
	 * @return void
	 */
	public static function storePriorityNoteInHistory(string $note, int $tourBookingId, bool $popup = false): void
	{
		DB::table('tour_booking_history')
			->insert([
				'tour_booking_id'        => $tourBookingId,
				'operation'              => 'Priority Note',
				'details'                => $note,
				'charge_type'            => $popup ? '1' : null,
				'operation_at'           => mysqlDT(),
				'operation_by'           => Auth::id(),
				'operation_by_user_type' => userType(),
				'updated_at'             => Carbon::now(),
				'created_at'             => Carbon::now(),
			]);
	}

	/**
	 * Store pickup hotel data in the history.
	 *
	 * @param mixed $tourBooking
	 *
	 * @return void
	 */
	public static function storePickupHotelInHistory($tourBooking, $created_by_with_name, $cartPackage = []): void
	{
		$hotel = Hotel::find($tourBooking->hotel_id);

		$options = [
			'id' => $hotel->id,
			'name' => $hotel->name,
			'address' => $hotel->address,
			'email' => $hotel->email,
			'phone_number' => $hotel->phone_number,
			'status' => $hotel->status,
			'created_by_with_name' => $created_by_with_name
		];

		$options['discount_promo_code'] = '';
		if (isset($cartPackage['discount_promo_code'])) {
			$options['discount_promo_code'] = $cartPackage['discount_promo_code'];
		}

		DB::table('tour_booking_history')->insert([
			'tour_booking_id' => $tourBooking->id,
			'operation' => 'Pickup Hotel',
			'details' => json_encode($options, JSON_NUMERIC_CHECK),
			'operation_at' => mysqlDT(),
			'operation_by' => $tourBooking->created_by,
			'operation_by_user_type' => $tourBooking->created_by_user_type,
			'updated_at' => Carbon::now(),
			'created_at' => Carbon::now(),
		]);
	}

	/**
	 * Store check in data in the history.
	 *
	 * @param int $bookingId
	 *
	 * @return void
	 */
	public static function storeCheckInDataInHistory($bookingId, $details, $dateTime, $auto = false): void
	{
		$id = Auth::id();
		$userType = userType();
		
		if ($auto) {
			$id = 1;
			$userType = "Admin";
		}

		DB::table('tour_booking_history')
			->insert([
				'tour_booking_id' => $bookingId,
				'operation' => 'Check-in',
				'details' => json_encode($details),
				'operation_at' => $dateTime,
				'operation_by' => $id,
				'operation_by_user_type' => $userType,
				'updated_at' => Carbon::now(),
				'created_at' => Carbon::now(),
			]);
	}

	/**
	 * Store tip payment data in the history.
	 *
	 * @param int $bookingId
	 *
	 * @return void
	 */
	public static function storeTipPaymentInHistory($bookingId, $data, $crd, $cardType, $createdByWithName): void
	{
		DB::table('tour_booking_history')->insert([
			'tour_booking_id' => $bookingId,
			'operation' => 'Tip',
			'details' => json_encode([
				'amount' => $data['amount'],
				'payment_type' => 'Card',
				'card_number' => $crd,
				'card_type' => $cardType,
				'created_by_with_name' => $createdByWithName
			], JSON_NUMERIC_CHECK),
			'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
			'operation_by' => Auth::id(),
			'operation_by_user_type' => userType(),
			'updated_at' => Carbon::now(),
			'created_at' => Carbon::now(),
		]);
	}

	/**
	 * Store booked by data in the history.
	 *
	 * @param int $bookingId
	 *
	 * @return void
	 */
	public static function storeBookedByDataInHistory($bookingId, $bookerInfo, $createdByWithName): void
	{
		DB::table('tour_booking_history')
			->insert([
				'tour_booking_id' => $bookingId,
				'operation' => 'Booked By',
				'details' => json_encode([
					'from' => $bookerInfo['oldName'] . ' (Id: ' . $bookerInfo['oldId'] . ', ' . $bookerInfo['oldUserType'] . ')',
					'to' => $bookerInfo['newName'] . ' (Id: ' . $bookerInfo['newId'] . ', ' . $bookerInfo['newUserType'] . ')',
					'created_by_with_name' => $createdByWithName
				]),
				'operation_at' => mysqlDT(),
				'operation_by' => Auth::id(),
				'operation_by_user_type' => userType(),
				'updated_at' => Carbon::now(),
				'created_at' => Carbon::now(),
			]);
	}

	/**
	 * Store payment type update data in the history.
	 *
	 * @param int $bookingId
	 *
	 * @return void
	 */
	public static function storePaymentTypeUpdateInHistory($bookingId, $historyId, $originalPaymentType, $newPaymentType, $createdByWithName): void
	{
		DB::table('tour_booking_history')
			->insert([
				'tour_booking_id' => $bookingId,
				'operation' => 'Payment Type Update',
				'details' => json_encode([
					'history_id' => $historyId,
					'from' => $originalPaymentType,
					'to' => $newPaymentType,
					'created_by_with_name' => $createdByWithName
				], JSON_NUMERIC_CHECK),
				'operation_at' => mysqlDT(),
				'operation_by' => Auth::id(),
				'operation_by_user_type' => userType(),
				'updated_at' => Carbon::now(),
				'created_at' => Carbon::now(),
			]);
	}

	/**
	 * Store restore booking data in the history.
	 *
	 * @param int $bookingId
	 *
	 * @return void
	 */
	public static function storeRestoreBookingInHistory($bookingId, $createdByWithName): void
	{
		DB::table('tour_booking_history')
			->insert([
				'tour_booking_id' => $bookingId,
				'operation' => 'Restore',
				'details' => json_encode([
					'created_by_with_name' => $createdByWithName
				], JSON_NUMERIC_CHECK),
				'operation_at' => mysqlDT(),
				'operation_by' => Auth::id(),
				'operation_by_user_type' => userType(),
				'updated_at' => Carbon::now(),
				'created_at' => Carbon::now(),
			]);
	}

	/**
	 * Store email data in the history.
	 *
	 * @param int $bookingId
	 *
	 * @return void
	 */
	public static function storeEmailInHistory($bookingId, $detail): void
	{
		DB::table('tour_booking_history')
			->insert([
				'tour_booking_id' => $bookingId,
				'operation' => 'Email',
				'details' => $detail,
				'operation_at' => mysqlDT(),
				'operation_by' => Auth::id(),
				'operation_by_user_type' => userType(),
				'updated_at' => Carbon::now(),
				'created_at' => Carbon::now(),
			]);
	}

	/**
	 * Convert a caught exception's message into something a non-technical person can understand
	 * when reading it back in the booking history. Stripe's card-decline messages are already
	 * written in plain language and are left as-is; it's specifically Stripe's API-level errors
	 * (worded for developers - "retry the request... at a lower rate") that need translating.
	 * Matched by Stripe's own documented error code (Stripe\ErrorObject::CODE_*), not by pattern-
	 * matching the message text, so this doesn't silently stop working if Stripe rewords something.
	 * Application logs (Log::error(), called separately by every caller before this) still capture
	 * the real technical message untouched - only what staff/customers see in the booking history
	 * timeline is simplified here.
	 *
	 * @param mixed $exception
	 * @return string
	 */
	private static function friendlyErrorMessage($exception): string
	{
		if ($exception instanceof \Stripe\Exception\ApiErrorException) {
			switch ($exception->getStripeCode()) {
				case \Stripe\ErrorObject::CODE_LOCK_TIMEOUT:
					return 'Another payment action was already in progress for this booking at the exact same moment. This usually resolves itself — if the booking still looks unpaid, try again in a moment.';
				case \Stripe\ErrorObject::CODE_PAYMENT_INTENT_UNEXPECTED_STATE:
					return 'This payment had already been completed before this attempt could go through.';
			}
		}

		return $exception->getMessage();
	}

	/**
	 * Store card payment error in the history.
	 *
	 * @param int $bookingId
	 * @param mixed $exception
	 *
	 * @return void
	 */
	public static function storeCardPaymentErrorInHistory($bookingId, $exception): void
	{
		DB::table('tour_booking_history')
			->insert([
				'tour_booking_id' => $bookingId,
				'operation' => 'Card Error',
				// JSON, not a raw string, so the History panel's resolveHistCreatedBy() helper
				// can find created_by_with_name and show the real actor instead of falling back
				// to its hardcoded "Customer" default (the same convention storeCardPaymentInHistory
				// already uses for successful payments).
				'details' => json_encode([
					'message' => self::friendlyErrorMessage($exception),
					'created_by_with_name' => BookingService::createdUserInfo(false, (object) [
						'customer_id' => Auth::id() ?? BookingService::customerIdByBookingEmail($bookingId),
					]),
				]),
				'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
				// Reached from customer-facing Payment Link completion — there is no Auth at all
				// on that path — as well as admin-initiated Make Payment.
				'operation_by' => Auth::id() ?? BookingService::customerIdByBookingEmail($bookingId),
				'operation_by_user_type' => Auth::id() ? userType() : 'Customer',
				'updated_at' => Carbon::now(),
				'created_at' => Carbon::now(),
			]);
	}

	/**
	 * Store normal error in the history.
	 *
	 * @param int $bookingId
	 * @param mixed $exception
	 *
	 * @return void
	 */
	public static function storeNormalErrorInHistory($bookingId, $exception): void
	{
		DB::table('tour_booking_history')
			->insert([
				'tour_booking_id' => $bookingId,
				'operation' => 'Error',
				// JSON, not a raw string - see storeCardPaymentErrorInHistory() just above for why.
				'details' => json_encode([
					'message' => self::friendlyErrorMessage($exception),
					'created_by_with_name' => BookingService::createdUserInfo(false, (object) [
						'customer_id' => Auth::id() ?? BookingService::customerIdByBookingEmail($bookingId),
					]),
				]),
				'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
				// Reached from customer-facing Payment Link completion — there is no Auth at all
				// on that path — as well as admin-initiated Make Payment.
				'operation_by' => Auth::id() ?? BookingService::customerIdByBookingEmail($bookingId),
				'operation_by_user_type' => Auth::id() ? userType() : 'Customer',
				'updated_at' => Carbon::now(),
				'created_at' => Carbon::now(),
			]);
	}

	/**
	 * Store switch package history in the table
	 *
	 * @param mixed $tourBooking
	 * @param int $oldTourPackageId
	 * @param int $tourPackageId
	 * @param string $paymentMethod
	 *
	 * @return int
	 */
	public static function storeSwitchPackageInPackageHistory($tourBooking, $oldTourPackageId, $tourPackageId, $paymentMethod = ''): int
	{
		$tbPackageSwitchHist = [
			'tour_booking_id' => $tourBooking->id,
			'old_tour_package_id' => $oldTourPackageId,
			'new_tour_package_id' => $tourPackageId,
			'tourists' => $tourBooking->tourists + $tourBooking->infants,
			'payment_method' => $paymentMethod,
			'created_by' => Auth::id(),
			'created_by_user_type' => userType(),
			'created_at' => Carbon::now(),
		];
		$switchPackageHistoryId = DB::table('tour_booking_package_swipe_history')->insertGetId($tbPackageSwitchHist);
		return $switchPackageHistoryId;
	}

	/**
	 * Store switch package history in the table
	 *
	 * @param mixed $tourBooking
	 *
	 *
	 * @return void
	 */
	public static function storeSwitchPackageInHistory($tourBooking, $swipeHistoryId, $data, $createdByWithName): void
	{
		$operationPackageChanged = [
			'tour_booking_id' => $tourBooking->id,
			'package_swipe_history_id' => $swipeHistoryId,
			'tourists' => $tourBooking->tourists + $tourBooking->infants,
			'operation' => 'Switch Tour Package',
			'details' => json_encode(['sub_operation' => 'Tour Package Changed', 'old_tour_package_id' => $data['old_tour_package_id'], 'new_tour_package_id' => $data['new_tour_package_id'], 'old_package' => $data['old_package'], 'new_package' => $data['new_package'], 'added' => $data['added'], 'deleted' => $data['deleted'],'txnhistoryid' => $data['txnhistoryid'] ?? '', 'created_by_with_name' => $createdByWithName], JSON_NUMERIC_CHECK),
			'operation_at' => mysqlDT(),
			'operation_by' => Auth::id(),
			'operation_by_user_type' => userType(),
			'updated_at' => Carbon::now(),
			'created_at' => Carbon::now(),
		];
		DB::table('tour_booking_history')->insert($operationPackageChanged);
	}

	/**
	 * Store split booking history in the table
	 *
	 * @param mixed $tourBooking
	 *
	 *
	 * @return void
	 */
	public static function storeSplitBookingInHistory($oldBookingId, $newBookingId, $oldTourists, $newTourists, $paymentMethod, $createdByWithName, $txnHistoryIds = [], $paymentHistoryIds = []): void
	{
		DB::table('tour_booking_history')
			->insertGetId([
				'tour_booking_id' => $oldBookingId,
				'tourists' => $oldTourists,
				'operation' => 'Split Booking Original',
				'details' => json_encode(['booking_id' => $oldBookingId, 'extended_booking_id' => $newBookingId, 'payment_type' => $paymentMethod, 'txnHistoryIds'=> $txnHistoryIds,'created_by_with_name' => $createdByWithName], JSON_NUMERIC_CHECK),
				'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
				'operation_by' => Auth::id(),
				'operation_by_user_type' => userType(),
				'updated_at' => Carbon::now(),
				'created_at' => Carbon::now(),
			]);

		DB::table('tour_booking_history')
			->insertGetId([
				'tour_booking_id' => $newBookingId,
				'tourists' => $newTourists,
				'operation' => 'Split Booking',
				'details' => json_encode(['booking_id' => $newBookingId, 'original_booking_id' => $oldBookingId, 'payment_type' => $paymentMethod, 'txnHistoryId' => $paymentHistoryIds,  'created_by_with_name' => $createdByWithName], JSON_NUMERIC_CHECK),
				'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
				'operation_by' => Auth::id(),
				'operation_by_user_type' => userType(),
				'updated_at' => Carbon::now(),
				'created_at' => Carbon::now(),
			]);
	}

	/**
	 * Store edit discount of the booking in the history.
	 *
	 * @param int $tourBookingId
	 * @param array $data
	 *
	 * @return int
	 */
	public static function updateBookingDiscountInHistory(int $tourBookingId, array $data, $splitPayment = 0): int
	{
		$transaction = DB::table('tour_booking_history')->where('tour_booking_id', $tourBookingId)
			->where('operation', 'Payment')
			->first();

		$userId = Auth::id() ?? ($data['booked_by_staff'] ?? 1);
		$userType = Auth::id() ? userType() : ($data['booked_by_staff_type'] ?? 'Admin');

		if (Auth::id()) {
			$models = [
				'Admin' => Admin::class,
				'Tour Operator' => Staff::class,
				'Affiliate' => Customer::class,
			];

			$model = $models[$userType] ?? null;
			$user = $model ? $model::find($userId) : null;
			$createdByWithName = $user ? "$user->name: ($userType)" : "Unknown: ($userType)";
		} else {
			$createdByWithName = "Webhook: ($userType)";
		}

		$tourists = $transaction
			? $transaction->tourists
			: (int) DB::table('tour_bookings_master')
				->where('id', $tourBookingId)
				->selectRaw('tourists + infants as count')
				->value('count');

		if (!$transaction && ($data['payment_type'] ?? '') !== 'additional') {
			$toureBookingData = DB::table('tour_bookings_master')->where('id', $tourBookingId)->first();

			$detail = [
				'discount' => $data['new_discount2_value'],
				"amount" => 0.00,
				"created_by_with_name" => $createdByWithName,
				'tourists' => $toureBookingData->tourists + $toureBookingData->infants,
				'old_discount2_value' => isset($data['old_discount2_value']) ? $data['old_discount2_value'] : "",
				'old_tour_promotion_id' => $data['old_tour_promotion_id'],
				'old_discount2_percentage' => $data['old_discount2_percentage'],
				'old_discount_promo_code' => $data['old_discount_promo_code'],
				'old_total' => $data['old_total'],
				'new_discount2_percentage' => $data['new_discount2_percentage'],
				'new_discount_promo_code' => $data['new_discount_promo_code'],
				'from' => 'Edit Discount'
			];

			$finalHistoryData = [
				'tour_booking_id' => $tourBookingId,
				'operation' => 'Refund',
				'payment_type' => $data['payment_method'],
				'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
				'operation_by' => $userId,
				'operation_by_user_type' => $userType,
				'updated_at' => Carbon::now(),
				'created_at' => Carbon::now(),
				'charge_type' => "discount",
			];

			$details = json_encode($detail, JSON_NUMERIC_CHECK);
			$finalHistoryData['details'] = $details;

			$historyId = DB::table('tour_booking_history')->insertGetId($finalHistoryData);
			DetectPayoutAdjustment::dispatch($tourBookingId, $historyId, 'Refund');
			return $historyId;
		} else {
			$data['service_commission'] ??= 0;
			$amount = $data['new_total'] + $data['service_commission'];
			$ticketCost = $data['new_total'];
			$discount = $data['new_discount2_value'];
			$platformFee = $data['service_commission'];

			if ($splitPayment == 1) {
				$amount = 0.00;
				$ticketCost = 0.00;
				$platformFee = 0.00;
			}

			$detail = [
				"amount" => $amount,
				'discount' => $discount,
				'platform_fees' => $platformFee,
				"payment_type" => $data['payment_method'],
				'payment_status' => $data['payment_type'],
				"created_by_with_name" => $createdByWithName,
				'tourists' => $tourists,
				'old_discount2_value' => $data['old_discount2_value'],
				'old_tour_promotion_id' => $data['old_tour_promotion_id'],
				'old_discount2_percentage' => $data['old_discount2_percentage'],
				'old_discount_promo_code' => $data['old_discount_promo_code'],
				'old_total' => $data['old_total'],
				'new_discount2_percentage' => $data['new_discount2_percentage'],
				'new_discount_promo_code' => $data['new_discount_promo_code'],
				'from' => 'Edit Discount'
			];

			$finalHistoryData = [
				'tour_booking_id' => $tourBookingId,
				'payment_type' => $data['payment_method'],
				'operation_by_user_type' => $userType,
				'charge_type' => "discount",
			];

			$cardNumber = isset($data['card_detail']) ? substr($data['card_detail']['cardNumber'], -4) : "";
			$detail['card_number'] = $data['cardLast4'] ?? $cardNumber;

			$details = json_encode($detail, JSON_NUMERIC_CHECK);
			$finalHistoryData['details'] = $details;

			// Payment
			$finalHistoryData['card_last_4_digit'] = $data['cardLast4'] ?? '';
			$finalHistoryData['transaction_id'] = $data['transaction_id'] ?? NULL;
			$finalHistoryData['tourists'] = $tourists;
			$finalHistoryData['is_processed'] = 1;

			if ($data['payment_type'] === 'refund') {
				$finalHistoryData['operation'] = 'Refund';
				$finalHistoryData['operation_at'] = mysqlDT();
				$finalHistoryData['operation_by'] = $userId;
				$finalHistoryData['updated_at'] = Carbon::now();
				$finalHistoryData['created_at'] = Carbon::now();
				$finalHistoryData['discount'] = 0.00;
				$finalHistoryData['ticket_cost'] = $ticketCost;
				$finalHistoryData['permit_fees'] = 0.00;
				$finalHistoryData['processing_fees'] = 0.00;
				$finalHistoryData['platform_fees'] = $platformFee;
				$finalHistoryData['change_amount'] = 0.00;
				$finalHistoryData['payment_method'] = '100 % Refund';

				$detail['amount'] = $amount;
				$detail['refund_type'] = '100 % Refund';
				$details = json_encode($detail, JSON_NUMERIC_CHECK);
				$finalHistoryData['details'] = $details;
			} else {
				if ($transaction) {
					$detail['payment_method'] = ($data['split_pending_amount'] > 0) ? "Split Payment" : "Full Payment";
				}

				$finalHistoryData['operation'] = 'Payment';
				$finalHistoryData['operation_at'] = mysqlDT();
				$finalHistoryData['operation_by'] = $userId;
				$finalHistoryData['updated_at'] = Carbon::now();
				$finalHistoryData['created_at'] = Carbon::now();
				$finalHistoryData['discount'] = $data['new_discount2_value'];
				$finalHistoryData['ticket_cost'] = $ticketCost;
				$finalHistoryData['permit_fees'] = 0;
				$finalHistoryData['processing_fees'] = 0;
				$finalHistoryData['platform_fees'] = $platformFee;
				$finalHistoryData['change_amount'] = 0.00;
				$finalHistoryData['payment_method'] = ($data['split_pending_amount'] > 0) ? "Split Payment" : "Full Payment";

				$detail['amount'] = $amount;

				if (isset($data['new_discount2_value']) && !empty($data['new_discount2_value'])) {
					$detail['from'] = "Update Webhook";
				}

				$details = json_encode($detail, JSON_NUMERIC_CHECK);
				$finalHistoryData['details'] = $details;
			}

			$historyId = DB::table('tour_booking_history')->insertGetId($finalHistoryData);
			DetectPayoutAdjustment::dispatch($tourBookingId, $historyId, $finalHistoryData['operation']);
			return $historyId;
		}
	}

	/**
	 * Store the custom charge payment operation of the booking in the history.
	 *
	 * @param mixed $tourBookingId
	 * @return void
	 *
	 */
	public static function storeCustomChargePaymentInHistory($tourBookingId, $paymentType, $customChargeAmount, $customChargeFees, $history, $paymentIntent = null, $transactionId = null): void
	{
		$createdByUserName = BookingService::createdUserInfo();

		$amount = $customChargeAmount + $customChargeFees;

		$cardLast4 = $cardType = null;
		$cardInfo = $paymentIntent->charges->data[0]->payment_method_details ?? [];
		if (isset($cardInfo['card_present']['last4'])) {
			$cardLast4 = $cardInfo['card_present']['last4'] ?? '';
			$cardType = $cardInfo['card_present']['brand'] ?? '';
		} else if (isset($cardInfo['card']['last4'])) {
			$cardLast4 = $cardInfo['card']['last4'] ?? '';
			$cardType = $cardInfo['card']['brand'] ?? '';
		}

		$historyId = DB::table('tour_booking_history')
			->insertGetId([
				'tour_booking_id' => $tourBookingId,
				'transaction_id' => $transactionId,
				'ticket_cost' => $customChargeAmount,
				'platform_fees' => roundout($customChargeFees, 2),
				'card_last_4_digit' => $cardLast4,
				'payment_type' => $paymentType,
				'payment_method' => 'Full Payment',
				'operation' => 'Payment',
				'details' => json_encode([
					'booking_id' => $tourBookingId,
					'operation' => "added",
					'custom_charge' => $history,
					'payment_type' => ucwords($paymentType),
					'payment_method' => 'Full Payment',
					'card_number' => $cardLast4,
					'card_type' => $cardType,
					'amount' => $amount,
					'fees' => roundout($customChargeFees, 2),
					'created_by_with_name' => $createdByUserName
				], JSON_NUMERIC_CHECK),
				'is_processed' => 1,
				'charge_type' => 'custom_charge',
				'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
				'operation_by' => Auth::id(),
				'operation_by_user_type' => userType(),
				'updated_at' => Carbon::now(),
				'created_at' => Carbon::now(),
			]);

		DetectPayoutAdjustment::dispatch($tourBookingId, $historyId, 'Payment');
	}

	/**
	 * Store custom charge refund in booking history table
	 *
	 * @param mixed $tourBooking
	 * @return void
	 */
	public static function storeCustomChargeRefundInHistory($tourBookingId, $paymentMethod, $customChargeAmount, $customChargeFees, $history, $refundType, $cardLast4 = null, $transactionId = null, $operation): void
	{
		$createdByUserName = BookingService::createdUserInfo();
		$amount = $customChargeAmount + $customChargeFees;
		if ($refundType == "Don't Refund") {
			$customChargeAmount = $customChargeFees = 0;
		}

		$historyId = DB::table('tour_booking_history')
			->insertGetId([
				'tour_booking_id' => $tourBookingId,
				'transaction_id' => $transactionId,
				'ticket_cost' => $customChargeAmount,
				'platform_fees' => roundout($customChargeFees, 2),
				'card_last_4_digit' => $cardLast4,
				'payment_type' => $paymentMethod,
				'payment_method' => $refundType,
				'operation' => 'Refund',
				'details' => json_encode([
					'booking_id' => $tourBookingId,
					'operation' => $operation,
					'custom_charge' => $history,
					'amount' => $amount,
					'fees' => roundout($customChargeFees, 2),
					'payment_type' => ucwords($paymentMethod),
					'refund_type' => $refundType,
					'card_number' => $cardLast4,
					'created_by_with_name' => $createdByUserName
				], JSON_NUMERIC_CHECK),
				'is_processed' => 1,
				'charge_type' => 'custom_charge',
				'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
				'operation_by' => Auth::id(),
				'operation_by_user_type' => userType(),
				'updated_at' => Carbon::now(),
				'created_at' => Carbon::now(),
			]);

		DetectPayoutAdjustment::dispatch($tourBookingId, $historyId, 'Refund');
	}

	/**
	 * Store a pending (unpaid) custom charge history entry when sending a payment link.
	 * Sets is_processed = 0 so the entry shows as awaiting payment in the history log.
	 */
	public static function storeCustomChargePendingLinkInHistory(
		$tourBookingId, $customChargeAmount, $customChargeFees, array $history
	): void {
		$createdByUserName = BookingService::createdUserInfo();

		// Remove any stale pending entries before inserting the fresh one.
		// upsertRemainingPaymentLink keeps a single payment link per booking,
		// so there should never be more than one pending entry at a time.
		DB::table('tour_booking_history')
			->where('tour_booking_id', $tourBookingId)
			->where('charge_type', 'custom_charge')
			->where('payment_method', 'Payment Link')
			->where('is_processed', 0)
			->delete();

		DB::table('tour_booking_history')->insert([
			'tour_booking_id'        => $tourBookingId,
			'transaction_id'         => null,
			'ticket_cost'            => $customChargeAmount,
			'platform_fees'          => roundout($customChargeFees, 2),
			'payment_type'           => 'Pay Later',
			'payment_method'         => 'Payment Link',
			'operation'              => 'Payment',
			'charge_type'            => 'custom_charge',
			'is_processed'           => 0,
			'details'                => json_encode([
				'booking_id'           => $tourBookingId,
				'operation'            => 'pending',
				'custom_charge'        => $history,
				'payment_type'         => 'Pay Later',
				'payment_method'       => 'Payment Link',
				'amount'               => $customChargeAmount + $customChargeFees,
				'fees'                 => roundout($customChargeFees, 2),
				'created_by_with_name' => $createdByUserName,
			], JSON_NUMERIC_CHECK),
			'operation_at'           => mysqlDT(),
			'operation_by'           => Auth::id(),
			'operation_by_user_type' => userType(),
			'updated_at'             => Carbon::now(),
			'created_at'             => Carbon::now(),
		]);
	}

	/**
	 * Store addons payment in booking history table
	 *
	 * @param mixed $tourBooking
	 * @return void
	 */
	public static function storeAddonsPaymentInHistory($booking, $history, $operation = 'added', $paymentIntent, $transactionId = null, $status = 'open', $price, $fee, $removedAddonItems = []): void
	{
		$createdByUserName = BookingService::createdUserInfo(false, $booking);

		$paymentType = null;
		if ($transactionId) {
			$transaction = TransactionService::transaction($transactionId);
			$paymentType = ucwords($transaction->via);
		}

		$paymentMethod = null;
		if ($status != 'open') {
			$paymentMethod = 'Full Payment';
		}

		$cardLast4 = $cardType = null;
		$cardInfo = $paymentIntent->charges->data[0]->payment_method_details ?? [];
		if (isset($cardInfo['card_present']['last4'])) {
			$cardLast4 = $cardInfo['card_present']['last4'] ?? '';
			$cardType = $cardInfo['card_present']['brand'] ?? '';
		} else if (isset($cardInfo['card']['last4'])) {
			$cardLast4 = $cardInfo['card']['last4'] ?? '';
			$cardType = $cardInfo['card']['brand'] ?? '';
		}

		$amount = $price + $fee;

		$details = [
			'booking_id' => $booking->id,
			'operation' => $operation,
			'addons' => $history,
			'payment_type' => $paymentType,
			'payment_method' => $paymentMethod,
			'card_number' => $cardLast4,
			'card_type' => $cardType,
			'amount' => $amount,
			'fees' => roundout($fee, 2),
			'created_by_with_name' => $createdByUserName
		];

		if (!empty($removedAddonItems)) {
			$details['removed_addons'] = $removedAddonItems;
		}

		$historyId = DB::table('tour_booking_history')
			->insertGetId([
				'tour_booking_id' => $booking->id,
				'transaction_id' => $transactionId,
				'ticket_cost' => $price,
				'platform_fees' => roundout($fee, 2),
				'card_last_4_digit' => $cardLast4,
				'payment_type' => $paymentType,
				'payment_method' => $paymentMethod,
				'operation' => 'Payment',
				'details' => json_encode($details, JSON_NUMERIC_CHECK),
				'is_processed' => 1,
				'charge_type' => 'addons',
				'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
				'operation_by' => $booking->created_by,
				'operation_by_user_type' => $booking->created_by_user_type,
				'updated_at' => Carbon::now(),
				'created_at' => Carbon::now(),
			]);

		DetectPayoutAdjustment::dispatch($booking->id, $historyId, 'Payment');
	}

	/**
	 * Store addons refund in booking history table
	 *
	 * @param mixed $tourBooking
	 * @return void
	 */
	public static function storeAddonsRefundInHistory($booking, $bookingAddon, $transactionValue, $history, $operation, $refundType, $transactionId = null, $request, $addedAddonItems = []): void
	{
		$createdByUserName = BookingService::createdUserInfo(false, $booking);

		// Reuse the card that was actually charged on THIS refund's PaymentIntent — a booking
		// can have been charged across multiple PIs on different saved cards (e.g. the original
		// addon addition vs. a later upgrade), so grabbing the booking's earliest-ever card
		// (the old behavior) showed the same card on every refund record regardless of which
		// PI it actually came from.
		$cardLast4 = null;
		if (!empty($transactionValue['transaction_id'])) {
			$cardLast4 = DB::table('tour_booking_history as h')
				->join('transactions as t', 't.id', '=', 'h.transaction_id')
				->where('t.transaction_number', $transactionValue['transaction_id'])
				->whereNotNull('h.card_last_4_digit')
				->where('h.card_last_4_digit', '!=', '')
				->orderBy('h.id', 'asc')
				->value('h.card_last_4_digit');
		}

		// Fall back to the booking's earliest recorded card if this PI's own charge history
		// couldn't be found (e.g. legacy data recorded before per-PI card lookup existed).
		if (!$cardLast4) {
			$cardLast4 = DB::table('tour_booking_history')
				->where('tour_booking_id', $booking->id)
				->whereNotNull('card_last_4_digit')
				->where('card_last_4_digit', '!=', '')
				->orderBy('id', 'asc')
				->value('card_last_4_digit');
		}

		$amount = $transactionValue['price'] + $transactionValue['fee'];

		$details = [
			'booking_id' => $booking->id,
			'operation' => $operation,
			'addons' => $history,
			'amount' => $amount,
			'fees' => roundout($transactionValue['fee'], 2),
			'payment_type' => ucwords($bookingAddon->payment_method),
			'refund_type' => $refundType,
			'card_number' => $cardLast4,
			'created_by_with_name' => $createdByUserName,
			'description' => $request->description
		];

		if (!empty($addedAddonItems)) {
			$details['added_addons'] = $addedAddonItems;
		}

		$historyId = DB::table('tour_booking_history')
			->insertGetId([
				'tour_booking_id' => $booking->id,
				'transaction_id' => $transactionId,
				'ticket_cost' => $transactionValue['price'],
				'platform_fees' => roundout($transactionValue['fee'], 2),
				'card_last_4_digit' => $cardLast4,
				'payment_type' => $bookingAddon->payment_method,
				'payment_method' => $refundType,
				'operation' => 'Refund',
				'details' => json_encode($details, JSON_NUMERIC_CHECK),
				'is_processed' => 1,
				'charge_type' => 'addons',
				'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
				'operation_by' => Auth::id(),
				'operation_by_user_type' => userType(),
				'updated_at' => Carbon::now(),
				'created_at' => Carbon::now(),
			]);

		DetectPayoutAdjustment::dispatch($booking->id, $historyId, 'Refund');
	}
	/**
	 * Store the tip payment operation of the booking in the history.
	 *
	 * @param mixed $tourBookingId
	 * @return void
	 *
	 */
	public static function storeTipInHistory($tourBookingId, $paymentType, $tipAmount, $tipFees, $history, $paymentIntent = null, $transactionId = null): void
	{
		$createdByUserName = BookingService::createdUserInfo();

		$amount = $tipAmount + $tipFees;

		$cardLast4 = $cardType = null;
		$cardInfo = $paymentIntent->charges->data[0]->payment_method_details ?? [];
		if (isset($cardInfo['card_present']['last4'])) {
			$cardLast4 = $cardInfo['card_present']['last4'] ?? '';
			$cardType = $cardInfo['card_present']['brand'] ?? '';
		} else if (isset($cardInfo['card']['last4'])) {
			$cardLast4 = $cardInfo['card']['last4'] ?? '';
			$cardType = $cardInfo['card']['brand'] ?? '';
		}

		DB::table('tour_booking_history')
			->insertGetId([
				'tour_booking_id' => $tourBookingId,
				'transaction_id' => $transactionId,
				'ticket_cost' => $tipAmount,
				'platform_fees' => roundout($tipFees, 2),
				'card_last_4_digit' => $cardLast4,
				'payment_type' => $paymentType,
				'payment_method' => 'Full Payment',
				'operation' => 'Payment',
				'details' => json_encode([
					'booking_id' => $tourBookingId,
					'operation' => "added",
					'tip' => $history,
					'payment_type' => ucwords($paymentType),
					'payment_method' => 'Full Payment',
					'card_number' => $cardLast4,
					'card_type' => $cardType,
					'amount' => $amount,
					'fees' => roundout($tipFees, 2),
					'created_by_with_name' => $createdByUserName
				], JSON_NUMERIC_CHECK),
				'is_processed' => 1,
				'charge_type' => 'tip',
				'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
				'operation_by' => Auth::id(),
				'operation_by_user_type' => userType(),
				'updated_at' => Carbon::now(),
				'created_at' => Carbon::now(),
			]);
	}

	/**
	 * retrive tha save card date for the history.
	 *
	 * @param mixed $paymentIntentId
	 * @param mixed $bookingId
	 */
	public static function getSavedCardFromPaymentIntent($paymentIntentId, $bookingId, $requestFrom)
	{
		if (!$paymentIntentId) {
			return null;
		}

		$retrieveOptions = ['expand' => ['payment_method']];
		$paymentIntent = app(PaymentGatewayInterface::class)->retrievePayment($paymentIntentId, $retrieveOptions);

		if (
			!$paymentIntent ||
			empty($paymentIntent->payment_method) ||
			empty($paymentIntent->payment_method->card)
		) {
			return null;
		}

		// Detect "Save this card"
		$cardSaved = false;

		// Explicit consent
		if ($paymentIntent->setup_future_usage === 'off_session') {
			$cardSaved = true;
		}

		// Card actually attached to customer
		if (!empty($paymentIntent->payment_method->customer)) {
			$cardSaved = true;
		}

		if (!$cardSaved) {
			return null;
		}

		$paymentMethod = $paymentIntent->payment_method;
		$card = $paymentIntent->payment_method->card;

		// Tag the payment method with the current tenant so dedup can scope by tenant.
		$tenantId = tenancy()->tenant->id;
		$pmUpdateParams = ['metadata' => ['tenant_id' => $tenantId]];
		app(PaymentGatewayInterface::class)->updatePaymentMethod($paymentMethod->id, $pmUpdateParams);

		$saveCard = [
			'booking_id'  => $bookingId,
			'customer_id' => $paymentIntent->customer,
			'payment_method_id' => $paymentMethod->id,
			'brand'       => $card->brand ?? null,
			'last4'       => $card->last4 ?? null,
			'exp_month'   => $card->exp_month ?? null,
			'exp_year'    => $card->exp_year ?? null,
			'request_from' => $requestFrom,
		];

		if ($saveCard) {
			self::saveCreditCardDetails(
				$saveCard['booking_id'],
				$saveCard['customer_id'],
				$saveCard['payment_method_id'],
				$saveCard['brand'],
				$saveCard['last4'],
				$saveCard['exp_month'],
				$saveCard['exp_year'],
				$saveCard['request_from']
			);

			// Deduplicate payment methods by card fingerprint.
			// The V2 frontend flow sets setup_future_usage on every booking,
			// which creates a new pm_ each time even for the same physical card.
			// This cleans up duplicates so we stay under Stripe's PM limit.
			try {
				self::deduplicatePaymentMethods(
					$paymentIntent->customer,
					$paymentMethod->id,
					$card->fingerprint ?? null,
					$tenantId
				);
			} catch (\Exception $e) {
				Log::warning('PM dedup failed: ' . $e->getMessage());
			}
		}
	}

	/**
	 * Remove duplicate Stripe payment methods for the same physical card.
	 *
	 * Groups all card PMs on the customer by fingerprint and detaches
	 * duplicates, keeping only the PM that was just used. Also updates
	 * any save_credit_card DB rows that referenced a detached PM.
	 * @param mixed $customerId
	 * @param mixed $keepPaymentMethodId
	 * @param mixed $fingerprint
	 * @param mixed $currentTenantId
	 */
	private static function deduplicatePaymentMethods($customerId, $keepPaymentMethodId, $fingerprint, $currentTenantId)
	{
		if (!$customerId || !$fingerprint) {
			return;
		}

		$pmParams = ['customer' => $customerId, 'type' => 'card', 'limit' => 100];
		$allMethods = app(PaymentGatewayInterface::class)->listPaymentMethods($pmParams);

		$duplicates = [];
		foreach ($allMethods->data as $pm) {
			$pmTenantId = $pm->metadata->tenant_id ?? null;

			if (
				$pm->id !== $keepPaymentMethodId
				&& isset($pm->card->fingerprint)
				&& $pm->card->fingerprint === $fingerprint
				&& $pmTenantId === $currentTenantId
			) {
				$duplicates[] = $pm->id;
			}
		}

		if (empty($duplicates)) {
			return;
		}

		foreach ($duplicates as $duplicatePmId) {
			app(PaymentGatewayInterface::class)->detachPaymentMethod($duplicatePmId);

			SaveCreditCard::where('payment_method_id', $duplicatePmId)
				->where('deleted_by', 0)
				->update(['payment_method_id' => $keepPaymentMethodId]);
		}

		// Log::info('PM dedup: detached ' . count($duplicates) . ' duplicate(s) for customer ' . $customerId);
	}

	/**
	 * Save the credit details on save_credit_card table.
	 *
	 * @param mixed $bookingId
	 * @param mixed $customerId
	 * @param mixed $paymentMethodId
	 * @param mixed $brand
	 * @param mixed $last4
	 * @param mixed $expMonth
	 * @param mixed $expYear
	 * @param mixed $requestFrom
	 */
	public static function saveCreditCardDetails($bookingId, $customerId, $paymentMethodId, $brand, $last4, $expMonth, $expYear, $requestFrom)
	{
		// Prevent duplicate card save (match by customer + card details only,
		// not booking ID, so repeat purchases with the same card are skipped)
		$exists = SaveCreditCard::where('customer_id', $customerId)
			->where('last4', $last4)
			->where('exp_month', $expMonth)
			->where('exp_year', $expYear)
			->where('payment_method_id', $paymentMethodId)
			->where('deleted_by', 0)
			->exists();

		if ($exists) {
			return null;
		}

		$createdBy = Auth::id();
		$userType = userType();
		$tourBooking = TourBooking::find($bookingId);
		$customer = Customer::find($tourBooking->customer_id);

		if ($requestFrom == "PAYMENT LINK" || $requestFrom == 'FRONTEND') {
			$createdBy = $tourBooking->created_by;
			$userType = $tourBooking->created_by_user_type;
		}

		return SaveCreditCard::create([
			'tour_booking_id'       => $bookingId,
			'customer_id'           => $customerId,
			'payment_method_id'     => $paymentMethodId,
			// $customer can be null: Customer::find() above is scoped by SoftDeletes, so a
			// customer row that's been (soft-)deleted since this booking was created returns
			// null even though $tourBooking->customer_id is a real, valid id. Fall back to the
			// booking's own email column (save_credit_card.email is NOT NULL) rather than
			// crashing on "Attempt to read property email on null" — the two are the same
			// email in every normal case, this only ever engages when the customer row itself
			// is unavailable.
			'email'                 => $customer?->email ?? $tourBooking->email,
			'brand'                 => $brand,
			'last4'                 => $last4,
			'exp_month'             => $expMonth,
			'exp_year'              => $expYear,
			'request_from'          => $requestFrom,
			'created_by'            => $createdBy,
			'created_by_user_type'  => $userType
		]);
	}

	/**
	 * Make it the user type with name for history
	 *
	 * @param mixed $userId
	 * @param mixed $userType
	 */
	public static function resolveUserName($userId, $userType)
	{

		switch ($userType) {
			case 'Tour Operator':
				$user = Staff::find($userId);
				return $user ? $user->first_name . ' ' . $user->last_name : '';

			case 'Affiliate':
				$user = Customer::withTrashed()->find($userId);
				return $user->name ?? '';

			case 'Customer':
				$user = Customer::find($userId);
				return $user->name ?? '';

			case 'Admin':
				$user = Admin::find($userId);
				return $user->name ?? '';

			default:
				return '';
		}
	}
}
