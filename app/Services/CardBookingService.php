<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Hotel;
use App\Models\Payout;
use App\Models\Staff;
use App\Models\TourBooking;
use App\Models\TourBookingHistory;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\Booking\AddonBookingService;
use App\Services\Booking\CustomChargeBookingService;
use App\Services\Booking\HistoryService;
use App\Services\Booking\TipService;
use App\Services\Booking\TransactionService;

class CardBookingService
{
	/**
	 * Execute the service.
	 *
	 * @return void
	 */
	public static function run($booking, $transactionId, $historyId, $paymentMethod, $request, $paymentIntent = null)
	{
		$tourBookingHistory = TourBookingHistory::with('tourBooking.customer')
			->where('id', $historyId)
			->first();

		$bookingData = TourBooking::find($booking['booking_id']);

		// Previous finding is for checking that the last transaction done from booking info page is full or split
		// If we found even a single transaction and then after any transaction done for that booking then it is a split payment
		$previousSplitPayment = TourBookingHistory::where('tour_booking_id', $bookingData->id)
			->where('payment_method', 'Split Payment')
			->count();

		$discount = TourBookingHistory::where('tour_booking_id', $bookingData->id)->sum('discount');
		$isCashFees = cashFees();
		$trans = Transaction::where('id', $transactionId)->first();
		//Fetch payout transaction for the booking for partner fees
		$payoutTransaction = Payout::where('tour_booking_id', $bookingData->id)
			->where('transaction_id', $transactionId)
			->where('payout_status', 'Completed')
			->first();
		$partnerFeesTotal = $payoutTransaction ? $payoutTransaction->commission_amount : 0;
		
		if ($tourBookingHistory && $bookingData && !isset($booking['is_pax_added']) && !isset($booking['is_package_switched'])) {
			if ($paymentMethod === 'Full Payment' && $previousSplitPayment === 0) {
				$history = json_decode($tourBookingHistory->details, true);

				// check if discount is applied in the transaction or not
				$discountedAmount = 0;
				if ((isset($request['discount_type']) && !empty($request['discount_type'])) || ($discount == 0 && $bookingData->discount2_value > 0) || (isset($booking['is_change_payment_type']) && $bookingData->discount2_value > 0)) {
					$discountedAmount = $bookingData->discount2_value;
				}

				$tourBookingHistory->ticket_cost = $trans->amount - $trans->fee_amount;
				$tourBookingHistory->discount = $discountedAmount;
				$tourBookingHistory->platform_fees = $trans->fee_amount;
				$tourBookingHistory->partner_fees = $partnerFeesTotal;
				$tourBookingHistory->transaction_id = $transactionId;

				if ($isCashFees == '1' && (isset($request['payment_method']) && $request['payment_method'] == 'Cash') || (isset($request['payment_method_cart_payment']) && $request['payment_method_cart_payment'] == 'Cash')) {
					$tourBookingHistory->payment_type = 'Cash';
				} else {
					$tourBookingHistory->payment_type = 'Card';
					$tourBookingHistory->card_last_4_digit = $history['card_number'] ?? '';
				}
				$tourBookingHistory->payment_method = $paymentMethod;
				$tourBookingHistory->is_processed = 1;
				$tourBookingHistory->save();

				$addonPayMethod = strtolower($tourBookingHistory->payment_type);
				$addonApplyFees = $addonPayMethod === 'card' || ($addonPayMethod === 'cash' && $isCashFees === '1');
				AddonBookingService::createAddonTransactionsFromFields(
					$booking['booking_id'],
					$paymentIntent,
					$addonPayMethod,
					$addonApplyFees
				);

				// Store custom charge
				CustomChargeBookingService::createBookingStoreCustomCharge($booking, $tourBookingHistory->payment_type, $paymentIntent);

				//Store Tip
				TipService::createBookingStoreTip($booking, $tourBookingHistory->payment_type, $paymentIntent);
			}

			if ($paymentMethod === 'Split Payment' || $previousSplitPayment > 0) {
				// override the full payment text to split payment text if any previous split payment did by user
				
				$paymentMethod = ($paymentMethod == 'Full Payment') ? 'Full Payment' : 'Split Payment';

				$history = json_decode($tourBookingHistory->details, true);

				// check if discount is applied in the transaction or not
				$discountedAmount = 0;
				if ((isset($request['discount_type']) && !empty($request['discount_type'])) || ($discount == 0 && $bookingData->discount2_value > 0)) {
					$discountedAmount = $bookingData->discount2_value;
				}

				$tourBookingHistory->ticket_cost = $trans->amount - $trans->fee_amount;
				$tourBookingHistory->discount = $discountedAmount;
				$tourBookingHistory->platform_fees = $trans->fee_amount;
				$tourBookingHistory->partner_fees = $partnerFeesTotal;
				$tourBookingHistory->transaction_id = $transactionId;
				if ($isCashFees == '1' && isset($request['payment_method']) && $request['payment_method'] == 'Cash') {
					$tourBookingHistory->payment_type = 'Cash';
				} else {
					$tourBookingHistory->payment_type = 'Card';
					$tourBookingHistory->card_last_4_digit = isset($history['card_number']) ? $history['card_number'] : "";
				}
				
				$tourBookingHistory->payment_method = $paymentMethod;
				$tourBookingHistory->save();
			}

			if ($bookingData->hotel_id) {
				$checkHotelHistory = TourBookingHistory::where('operation', 'Pickup Hotel')
					->where('tour_booking_id', $bookingData->id)
					->count();

				if ($checkHotelHistory == 0) {
					if ($bookingData->created_by_user_type == "Admin") {
						$user = Admin::find($bookingData->created_by);
						$created_by_with_name = $user->name . ': (' . "Admin" . ')';
					} elseif ($bookingData->created_by_user_type == "Tour Operator") {
						$user = Staff::find($bookingData->created_by);
						$created_by_with_name = $user->name . ': (' . "Tour Operator" . ')';
					} elseif ($bookingData->created_by_user_type == "Affiliate") {
						$user = Customer::find($bookingData->created_by);
						$created_by_with_name = $user->name . ': (' . "Affiliate" . ')';
					}

					$hotel = Hotel::find($bookingData->hotel_id);

					DB::table('tour_booking_history')->insert([
						'tour_booking_id' => $bookingData->id,
						'operation' => 'Pickup Hotel',
						'details' => json_encode([
							'id' => $hotel->id,
							'name' => $hotel->name,
							'address' => $hotel->address,
							'email' => $hotel->email,
							'phone_number' => $hotel->phone_number,
							'status' => $hotel->status,
							'created_by_with_name' => $created_by_with_name
						], JSON_NUMERIC_CHECK),
						'operation_at' => mysqlDT(),
						'operation_by' => $bookingData->created_by,
						'operation_by_user_type' => $bookingData->created_by_user_type,
						'updated_at' => Carbon::now(),
						'created_at' => Carbon::now(),
					]);
				}
			}
		}

		// This below condition only execute when new pax added in the existing bookings and  switched package
		if (isset($booking['is_pax_added']) || isset($booking['is_package_switched'])) {
			$history = json_decode($tourBookingHistory->details, true);
			$discount = (isset($request['pr_amount']) && !empty($request['pr_amount'])) ? $request['pr_amount'] : 0;

			$tourBookingHistory->ticket_cost = $trans->amount - $trans->fee_amount;
			$tourBookingHistory->discount = $discount;
			$tourBookingHistory->platform_fees = $trans->fee_amount;
			$tourBookingHistory->partner_fees = $partnerFeesTotal;
			$tourBookingHistory->transaction_id = $transactionId;
			if ($isCashFees == '1' && isset($request['payment_method']) && $request['payment_method'] == 'Cash') {
				$tourBookingHistory->payment_type = 'Cash';
			} else {
				$tourBookingHistory->payment_type = 'Card';
				$tourBookingHistory->card_last_4_digit = isset($history['card_number']) ? $history['card_number'] : "";
			}
			$tourBookingHistory->payment_method = $paymentMethod;
			$tourBookingHistory->is_processed = 1;
			$tourBookingHistory->save();
		}
	}

}
