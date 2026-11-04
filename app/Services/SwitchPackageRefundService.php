<?php

namespace App\Services;

use App\Models\BookingTransact;
use App\Models\Customer;
use App\Models\TourBookingHistory;
use App\Models\TourBooking;
use App\Models\Transaction;
use App\Services\Booking\BookingService;

class SwitchPackageRefundService
{
	/**
	 * Execute the service.
	 *
	 * @return void
	 */
	public static function run($booking, $historyId, $refundVia, $transactionId, $stripeFeesPercentage)
	{
		$isCashFees = cashFees();
		$tourBookingHistory = TourBookingHistory::find($historyId);
		if ($tourBookingHistory) {
			$cardNumber = '';
			$history = json_decode($tourBookingHistory->details, true);

			if ($refundVia == "Card" || ($isCashFees == '1' && $refundVia == 'Cash')) {
				$platformFees = $history['fees'];

				$convertedAmount = $history['amount'] - $platformFees;

				$transactionDetails = Transaction::where('id', $transactionId)->first();
				if ($transactionDetails && $transactionDetails->transaction_number && !isset($history['cards']) && $refundVia == "Card") {
					$cardDetails = BookingService::cardLastFourDigit($booking->id, $transactionDetails->transaction_number);
					$cardNumber = !empty($cardDetails) ? $cardDetails['card_last_4_digit'] : '';
				}
			} else {
				$convertedAmount = $history['amount'];
				$platformFees = 0;
			}
			if ($history['refund_type'] != "Don't Refund") {
				$tourBookingHistory->ticket_cost = $convertedAmount;
			}
			$tourBookingHistory->platform_fees = $platformFees;
			$tourBookingHistory->partner_fees = $history['partner_fees'] ?? 0;
			$tourBookingHistory->transaction_id = $transactionId;
			if ($refundVia == "Card") {
				$tourBookingHistory->card_last_4_digit = $cardNumber;
			}
			$tourBookingHistory->payment_type = $refundVia;
			$tourBookingHistory->payment_method = $history['refund_type'];
			$tourBookingHistory->is_processed = 1;
			$tourBookingHistory->save();

		}
	}
}
