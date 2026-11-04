<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\BookingTransact;
use App\Models\Customer;
use App\Models\Hotel;
use App\Models\Payout;
use App\Models\Staff;
use App\Models\TourBooking;
use App\Models\TourBookingHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\Booking\CustomChargeBookingService;
use App\Services\Booking\TipService;
use App\Services\Booking\AddonBookingService;

class CashBookingService
{
	/**
	 * Execute the service.
	 *
	 * @return void
	 */
	public static function run($booking, $transactionId, $historyId, $paymentMethod, $request)
	{
		$tourBookingHistory = TourBookingHistory::find($historyId);

		// Previous finding is for checking that the last transaction done from booking info page is full or split
		// If we found even a single transaction and then after any transaction done for that booking then it is a split payment
		$previousSplitPayment = TourBookingHistory::where('tour_booking_id', $booking->id)
			->where('payment_method', 'Split Payment')
			->count();

		$bookingData = TourBooking::find($booking->id);
		//This is for swipe package feature if booking has first split payment
		if (isset($request['is_swiped_package']) && $request['is_swiped_package']) {
			$previousSplitPayment = 0;
		}

		$discount = TourBookingHistory::where('tour_booking_id', $booking->id)->sum('discount');

		//Fetch payout transaction for the booking for partner fees
		$payoutTransaction = Payout::where('tour_booking_id', $booking->id)
			->where('transaction_id', $transactionId)
			->where('payout_status', 'Completed')
			->first();
		if ($tourBookingHistory && !isset($request['is_pax_added'])) {

			if ($paymentMethod === 'Full Payment' && $previousSplitPayment === 0) {
				$history = json_decode($tourBookingHistory->details, true);

				$tourBookingTransact = BookingTransact::where('tour_booking_id', $booking->id)->where('deleted_by', 0)->get();

				$partnerFeesTotal = ($payoutTransaction) ? $payoutTransaction->commission_amount : 0;

				// check if discount is applied in the transaction or not
				$discountedAmount = 0;
				if (isset($request['discount_type']) || ($discount == 0 && $bookingData->discount2_value > 0)) {
					$discountedAmount = $bookingData->discount2_value;
				}

				$tourBookingHistory->ticket_cost = $history['amount'];
				$tourBookingHistory->discount = $discountedAmount;
				$tourBookingHistory->partner_fees = $partnerFeesTotal;
				$tourBookingHistory->transaction_id = $transactionId;
				$tourBookingHistory->payment_type = 'Cash';
				$tourBookingHistory->payment_method = $paymentMethod;
				$tourBookingHistory->save();

				AddonBookingService::insertBookingAddonTransactions(
					$booking->id,
					null,
					$tourBookingHistory->payment_type,
					$request['pax_addons_data'] ?? null,
					$request['addons_data'] ?? null,
					'paid'
				);

				// Store custom charge
				CustomChargeBookingService::createBookingStoreCustomCharge($booking, $tourBookingHistory->payment_type);
				//Store Tip
				TipService::createBookingStoreTip($booking, $tourBookingHistory->payment_type);
			}

			if ($paymentMethod === 'Split Payment' || $previousSplitPayment > 0) {
				// override the full payment text to split payment text if any previous split payment did by user
				$paymentMethod = ($paymentMethod == 'Full Payment') ? 'Full Payment' : 'Split Payment';

				$history = json_decode($tourBookingHistory->details, true);

				$tourBookingTransact = BookingTransact::where('tour_booking_id', $booking->id)->where('deleted_by', 0)->get();

				$ticketCostTotal = 0;
				$permitFeesTotal = 0;
				foreach ($tourBookingTransact as $pax) {
					if ($pax->is_group_rate == '1') {
						$ticketCost = $pax->rate + $pax->partner_fee;
						$ticketCostTotal += $ticketCost;
					} else {
						$ticketCost = $pax->tourists * ($pax->rate + $pax->additional_charge + $pax->permit_fee + $pax->partner_fee);
						$ticketCostTotal += $ticketCost;

						$permitFee = $pax->tourists * $pax->permit_fee;
						$permitFeesTotal += $permitFee;
					}
				}
				$partnerFeesTotal = ($payoutTransaction) ? $payoutTransaction->commission_amount : 0;
				$total = $ticketCostTotal - $booking->discount2_value;

				// count the percentage of payment done from total amount to calculate permit fee
				$paidAmountPercentage = $history['amount'] / $total * 100;

				// check if discount is applied in the transaction or not
				$discountedAmount = 0;
				if (isset($request['discount_type']) || ($discount == 0 && $bookingData->discount2_value > 0)) {
					$discountedAmount = $bookingData->discount2_value;
				}

				$tourBookingHistory->ticket_cost = $history['amount'];
				$tourBookingHistory->discount = $discountedAmount;
				$tourBookingHistory->partner_fees = $partnerFeesTotal;
				$tourBookingHistory->permit_fees = $permitFeesTotal * $paidAmountPercentage / 100;
				$tourBookingHistory->transaction_id = $transactionId;
				$tourBookingHistory->payment_type = 'Cash';
				$tourBookingHistory->payment_method = $paymentMethod;
				$tourBookingHistory->is_processed = 1;
				$tourBookingHistory->save();
			}

			if ($booking->hotel_id) {
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

		// This below condition only execute when new pax added in the existing bookings
		if (isset($request['is_pax_added'])) {
			$partnerFeesTotal = ($payoutTransaction) ? $payoutTransaction->commission_amount : 0;
			$history = json_decode($tourBookingHistory->details, true);

			$tourBookingHistory->ticket_cost = $history['amount'];
			$tourBookingHistory->partner_fees = $partnerFeesTotal;
			$tourBookingHistory->transaction_id = $transactionId;
			$tourBookingHistory->payment_type = 'Cash';
			$tourBookingHistory->payment_method = $paymentMethod;
			$tourBookingHistory->is_processed = 1;
			$tourBookingHistory->save();
		}
	}

}
