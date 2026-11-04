<?php

namespace App\Services\Webhook;

use App\Models\TourBookingPaymentLink;
use App\Services\Booking\HistoryService;
use App\Services\Booking\PaymentLinkService;
use Illuminate\Support\Facades\DB;
use Stripe\PaymentIntent;

class StripePaymentIntentSucceededForPaymentLink
{
	/**
	 * Execute the service.
	 *
	 * @return void
	 */
	public static function run($paymentIntent)
	{
		if (!isset($paymentIntent->metadata['payment_id'])) {
			return;
		}

		$paymentLink = TourBookingPaymentLink::find($paymentIntent->metadata['payment_id']);

		if (!$paymentLink || $paymentLink->section !== 'remaining_payment') {
			return;
		}

		$bookingId = $paymentIntent->metadata['booking_id'] ?? null;
		if (!$bookingId) {
			return;
		}

		// Retrieve with charges expanded so fee/card details are available
		$piWithCharges = PaymentIntent::retrieve($paymentIntent->id, ['expand' => ['charges']]);

		HistoryService::getSavedCardFromPaymentIntent($piWithCharges->id, $bookingId, "PAYMENT LINK");

		PaymentLinkService::finalizeRemainingPaymentLink($paymentLink, $piWithCharges, $bookingId);

		DB::commit();
	}
}
