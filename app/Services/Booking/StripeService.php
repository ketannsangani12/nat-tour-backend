<?php

namespace App\Services\Booking;

use App\Constants\Constants;
use App\Exceptions\Core\GeneralCoreException;
use App\Models\BookingTransact;
use App\Models\Customer;
use App\Models\GratuitySetting;
use App\Models\TourBooking;
use App\Models\TourBookingHistory;
use App\Models\TourBookingPaymentLink;
use App\Models\TourOperator;
use App\Models\TourPackage;
use App\Models\TourPromotion;
use App\Models\Transaction;
use App\Services\Cart\CartService;
use App\Services\SwitchTourPackage\SwitchTourPackageCashService;
use App\Services\TourPackage\TourPackageService;
use App\Services\TourPackageRate\TourPackageRateService;
use App\Services\Payments\PaymentModeResolver;
use App\Utils\BookingUtils;
use App\Utils\StripeUtils;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Discount;
use App\Contracts\Payments\PaymentGatewayInterface;

class StripeService
{
	/**
	 * Execute the Stripe service to get the details.
	 *
	 * @param mixed $tourBookingId
	 *
	 * @return mixed
	 */
	public static function stripeDetails($tourBookingId)
	{
		$transaction = Transaction::where('tour_booking_id', $tourBookingId)
			->where('via', 'Card')->first();
		if (!empty($transaction) && $transaction->transaction_number != "") {
			return true;
		} else {
			return false;
		}
	}
	/**
	 * Extracts card details from the payment intent.
	 *
	 * @param object $paymentIntent
	 * @param bool $isApi
	 * @return array
	 */
	public static function extractCardDetails($paymentIntent, $isApi = false): array
	{
		$cardDetails = [
			'last4'     => '',
			'brand'     => '',
			'source'    => '',
		];

		$charges = $paymentIntent->charges->data[0]->payment_method_details ?? null;

		if (isset($charges->card_present)) {
			$cardDetails['last4'] = $charges->card_present->last4 ?? '';
			$cardDetails['brand'] = $charges->card_present->brand ?? '';
			$cardDetails['source'] = ($isApi) ? 'iOS Card Reader' : 'Web Card Reader';
		} elseif (isset($charges->card)) {
			$cardDetails['last4'] = $charges->card->last4 ?? '';
			$cardDetails['brand'] = $charges->card->brand ?? '';
			$cardDetails['source'] = ($isApi) ? 'iOS Manual Card' : 'Web Manual Card';
		}

		return $cardDetails;
	}
	/**
	 * Execute the Stripe service to create paymentintent for make payment for the tour booking.
	 *
	 * @param object $request
	 *
	 * @return mixed
	 */
	public static function stripeCreatePaymentIntentForMakePayment($request)
	{
		try {
			$oldBookingId = oldBookingId();
			$tourBooking = DB::table('tour_bookings_master as tbm')
				->select([
					'tbm.id',
					'tbm.id as booking_id',
					'tbm.discount2_value',
					'tbm.discount2_percentage',
					'tbm.is_group_booking',
					'c.id as customer_id',
					'c.name as Name',
					'sd.date as Date',
					'st.time_from as StartTime',
					'st.time_to as EndTime',
					'tp.package_has_slots',
					'c.is_affiliate',
					'c.phone_number as ContactNumber',
					'c.email as Email',
					'tbm.tourists',
					'tbm.infants',
					't_o.statement_descriptor',
					't_o.stripe_destination_id',
					't_o.name as tour_operator',
					'tp.name as package_name',
					'tp.service_commission_percentage',
					'c.affiliate_processing_percentage',
					'tbm.tour_package_id',
					'tbm.discount_promo_code',
				])
				->join('customers as c', 'c.id', '=', 'tbm.customer_id')
				->join('tour_bookings_transact as tbt', 'tbt.tour_booking_id', '=', 'tbm.id')
				->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
				->join('tour_operators as t_o', 't_o.id', '=', 'tp.tour_operator_id')
				->join('tour_slot_times as st', 'st.id', '=', 'tbm.tour_slot_id')
				->join('tour_slot_dates as sd', 'sd.id', '=', 'st.tour_slot_id')
				->where('tbm.id', $request->bookingID)
				->first(); // Use `first()` instead of `select()` if expecting a single record.

			$isGroupBooking = $tourBooking->is_group_booking;
			$bookingT = BookingTransact::with('packageRate')
				->where('tour_booking_id', $request->bookingID)
				->where('deleted_by', 0)
				->get();

			$total = 0;
			$serviceCommission = 0;
			$fees = 0;
			$oldDiscountAmt = 0;
			$natFeesPercentage = $bookingT[0]->nat_fees_percentage;
			$stripeFeesPercentage = $bookingT[0]->stripe_fees_percentage;
			// Calculate the booking amount
			$totalAmount = BookingService::calculateTotalAmount($request, $bookingT, $tourBooking, $oldBookingId, $isGroupBooking);
			$total = $totalAmount['total'];
			$serviceCommissionTotal = $totalAmount['serviceCommissionTotal'];
			$partnerFeesTotal = $totalAmount['partnerFeesTotal'];
			$payoutAmount = $totalAmount['payoutAmount'];

			// Pre-query for amount calculations; re-verified under lock inside the transaction below
			$trans = Transaction::where('tour_booking_id', $request->bookingID)
				->where('status', 'Completed')
				->whereNull('charge_type')
				->first(['payment_type']);

			// Discount given on pay later option when booking created
			// Here is the total minus discount amount
			$totalForDiscountWithSplit = 0;
			if (empty($trans) && !empty($request->split_value) && $tourBooking->discount2_value > 0 && !$request->has('discount_type') && empty($request->discount_percentage) && empty($request->discount_fixed_money)) {
				$totalForDiscountWithSplit = $total - $tourBooking->discount2_value;
			}

			//Discount applied in make payment with split payment
			if (empty($trans) && !empty($request->split_value) && $request->has('discount_type') && (!empty($request->discount_percentage) || !empty($request->discount_fixed_money) || !empty($request->discount_promo_code))) {
				$totalForDiscountWithSplit = DiscountService::calculateTotalWithSplitDiscount($request, $total, $tourBooking->tour_package_id, $trans);
			}

			if (!empty($trans) && $trans->payment_type == 1) {
				if ($request->split_value) {
					// Guard against a client-supplied split leg amount that would collect more
					// than the booking actually still owes (e.g. a stale/incorrectly divided split
					// value carried over from a previous leg's UI state). $stripeTotal below is
					// derived directly from split_value and becomes the real Stripe charge amount,
					// so it must never exceed the true remaining balance still owed on the booking.
					$paymentOnlyForSplitClamp = TourBookingHistory::where('tour_booking_id', $request->bookingID)->where('operation', 'Payment')->get();
					$paymentOnlyCheckForSplitClamp = 0;
					foreach ($paymentOnlyForSplitClamp as $value) {
						$details = json_decode($value->details);
						$paymentOnlyCheckForSplitClamp += $details->amount;
					}
					$tbOnForSplitClamp = TourBooking::where('id', $request->bookingID)->first(['total']);
					$remainingBalanceForSplitClamp = round($tbOnForSplitClamp->total - $paymentOnlyCheckForSplitClamp, 2);
					if ($remainingBalanceForSplitClamp >= 0 && (float) $request->split_value > $remainingBalanceForSplitClamp) {
						$request->split_value = $remainingBalanceForSplitClamp;
					}
					$total = $request->split_value;
				}
			}

			// get discount detail if promo code applied from booking info module
			$discountAmt = DiscountService::calculateDiscount($request, $total, $tourBooking->tour_package_id);

			if (empty($request->split_value)) {
				$total -= $discountAmt;
			}

			//Check for old discount applied when booking is done using Pay Later
			$discountData = DiscountService::calculateOldDiscount($tourBooking, $total, $request);
			$oldDiscountAmt = $discountData['oldDiscountAmt'];
			$total = $discountData['total'];

			// Add the service commission in the total amount
			$serviceCommission = self::calculateServiceCommissionMakePayment(
				$total,
				$totalForDiscountWithSplit,
				$natFeesPercentage,
				$serviceCommissionTotal,
				$request->bookingID,
				$oldBookingId,
				$discountAmt,
				$oldDiscountAmt,
				$request,
				empty($trans) // first payment on this booking (incl. leg 1 of a split)
			);
			$total = $total + $serviceCommission;
			$total = round($total, 2);
			$paymentOnly = TourBookingHistory::where('tour_booking_id', $request->bookingID)->where('operation', 'Payment')->get();
			$paymentOnlyCheck = 0;
			foreach ($paymentOnly as $value) {
				$details = json_decode($value->details);
				$paymentOnlyCheck += $details->amount;
			}
			if (!empty($trans) && $trans->payment_type == 1) {
				$tbOn = TourBooking::where('id', $request->bookingID)->first();
				if (empty($request->split_value)) {
					$total = $tbOn->total - $paymentOnlyCheck;
				}
				$serviceCommission = roundout(($total * $stripeFeesPercentage) / 100);
			}
			if (!empty($request->split_value)) {
				$fees = roundout($request->split_value * $stripeFeesPercentage / 100, 2);
			} else {
				$fees = $serviceCommission;
			}

			$stripeTotal = 0;
			if (!empty($request->split_value)) {
				$stripeTotal = $request->split_value;
				$stripeFees = $fees;
			} else {
				$addonsAmount = BookingService::fetchPayLaterAddonsWithPrice($request->bookingID, $natFeesPercentage);
				$stripeTotal = $total + $addonsAmount['addons_total'];
				$stripeFees = $fees + $addonsAmount['addons_fee'];

				// Only when custom charge applied to the pay later booking
				$customChargePrice = CustomChargeBookingService::customChargePrice($request->bookingID);

				if (isset($customChargePrice['custom_charge_final_total']) && $customChargePrice['custom_charge_final_total'] > 0) {
					$stripeTotal = $stripeTotal + $customChargePrice['pay_later_custom_charge_total'];
					$stripeFees = $stripeFees + $customChargePrice['pay_later_custom_charge_fees'];
				}
				//Only when tip applied to the pay later booking
				$tipAmounts = TipService::getTipAmounts($request->bookingID);
				if (isset($tipAmounts['pay_later_tip_total']) && $tipAmounts['pay_later_tip_total'] > 0 && isset($tipAmounts['pay_later_tip_fees']) && $tipAmounts['pay_later_tip_fees'] > 0) {
					$stripeTotal += $tipAmounts['pay_later_tip_total'] + $tipAmounts['pay_later_tip_fees'];
					$stripeFees += $tipAmounts['pay_later_tip_fees'];
				}
			}

			// When paying a pending remaining_payment link, override to the exact link amount
			if (!empty($request->remaining_link_id)) {
				$remainingLink = TourBookingPaymentLink::where('id', $request->remaining_link_id)
					->where('status', 'Unpaid')
					->where('section', 'remaining_payment')
					->first();
				if (!$remainingLink) {
					throw new Exception('This pending payment has already been processed. Please refresh the page.');
				}
				if ($remainingLink) {
					$stripeTotal = $remainingLink->amount;
					// Compute platform fees from non-Cancelled link items only — Cancelled items
					// were voided before payment and must not inflate application_fee_amount.
					$stripeFees = 0;
					$linkItemDetails = DB::table('tour_booking_payment_link_items')
						->where('payment_link_id', $remainingLink->id)
						->where('status', '!=', 'Cancelled')
						->pluck('details');
					foreach ($linkItemDetails as $rawDet) {
						$det = json_decode($rawDet ?? '[]', true) ?? [];
						$stripeFees += (float) ($det['platform_fees'] ?? 0);
					}
				}
			}

			// The entire reservation-check → PI creation → stamp block runs inside a single
			// transaction with a booking-row lock. This guarantees that no two concurrent
			// requests can both reach the Stripe API — the second waits at lockForUpdate,
			// then finds transaction_number already populated and returns the same clientSecret.
			$result = null;
			// Set instead of thrown from inside the transaction below, so that when it DOES
			// indicate "already paid", the transaction still commits normally first — any
			// reconciliation writes made below must survive, not get rolled back by the very
			// exception that reports them as done.
			$alreadyPaidException = null;

			DB::transaction(function () use ($request, $tourBooking, &$result, &$alreadyPaidException, $stripeTotal, $stripeFees, $partnerFeesTotal, $payoutAmount) {
				// Serialize all concurrent Make Payment attempts for this booking
				DB::table('tour_bookings_master')
					->where('id', $request->bookingID)
					->lockForUpdate()
					->first(['id']);

				// Re-verify under lock — catches any completion that raced the pre-query above
				$lockedTrans = Transaction::where('tour_booking_id', $request->bookingID)
					->where('status', 'Completed')
					->whereNull('charge_type')
					->first(['payment_type']);

				if (!empty($lockedTrans) && $lockedTrans->payment_type != 1 && empty($request->remaining_link_id)) {
					$alreadyPaidException = new Exception('This booking has already been fully paid.');
					return;
				}

				// Identity-agnostic, non-expiring in-flight check: whoever created the last open
				// reservation for this booking doesn't matter, and neither does its age — what
				// matters is whether its PaymentIntent is still confirmable in Stripe right now.
				// (A time/identity-scoped check here previously let a second, independent
				// PaymentIntent get created while an older one was still alive and confirmable,
				// which is how the same booking ended up with two successful charges.)
				$existingReservation = Transaction::where('tour_booking_id', $request->bookingID)
					->where('status', 'Initialized')
					->whereNull('charge_type')
					->orderByDesc('id')
					->first(['id', 'transaction_number']);

				if ($existingReservation && !empty($existingReservation->transaction_number)) {
					$paymentIntent = app(PaymentGatewayInterface::class)->retrievePayment($existingReservation->transaction_number);

					if ($paymentIntent->status === 'succeeded') {
						// Stripe already took this payment, but the local booking was never
						// completed — the browser's one follow-up call to paymentNewBooking()
						// (the only thing that writes the transaction/history/booking-total
						// records for this flow) never landed. Reconcile it now, synchronously,
						// reusing that exact completion logic — but only when the amount we'd
						// charge right now matches what Stripe actually charged; a mismatch
						// means booking state changed since the original charge (e.g. a discount
						// was applied since), and auto-completing would risk recording the wrong
						// amount, so that case is left for manual review instead.
						if ($paymentIntent->amount === (int) round($stripeTotal * 100)) {
							self::reconcileSucceededMakePayment($existingReservation->transaction_number, $request);
							$alreadyPaidException = new Exception('This booking has already been fully paid.');
							return;
						} elseif (!empty($request->remaining_link_id)) {
							// The PI amount doesn't match the current link's amount — this
							// Initialized row is an orphan from a PREVIOUS payment (e.g. an old
							// custom_charge or change_people link that was paid but whose Initialized
							// row was never cleaned up). reconcileSucceededMakePayment handles the
							// idempotency check (skips if already recorded) and cancels the orphaned
							// Initialized row. Then fall through so a fresh PI is created for the
							// current link instead of returning "Already Paid".
							self::reconcileSucceededMakePayment($existingReservation->transaction_number, $request);
							// reconcileSucceededMakePayment marked the Initialized row Cancelled —
							// create a brand-new reservation so the PI stamp below has a valid row.
							$reservationId = DB::table('transactions')->insertGetId([
								'tour_booking_id'      => $request->bookingID,
								'amount'               => 0,
								'fee_amount'           => 0,
								'transaction_number'   => null,
								'via'                  => 'Card',
								'payment_type'         => 0,
								'response_init'        => '',
								'response_end'         => '',
								'status'               => 'Initialized',
								'visitor'              => $request->getClientIp(),
								'request_from'         => $request->is('api/*') ? Constants::API : Constants::BACKEND,
								'created_by'           => Auth::id(),
								'created_by_user_type' => userType(),
								'created_at'           => Carbon::now(),
							]);
							// Don't return — fall through to create the PI for the current link.
						} else {
							Log::warning("Make-payment reconciliation skipped for booking {$request->bookingID}: Stripe-charged amount ({$paymentIntent->amount}) doesn't match the currently-computed total (" . (int) round($stripeTotal * 100) . '). Needs manual review.');
							$alreadyPaidException = new Exception('This booking has already been fully paid.');
							return;
						}
					}

					if ($paymentIntent->status !== 'canceled') {
						// Still confirmable — reuse it instead of minting a second PaymentIntent.
						// Refresh amount/description/metadata in case the total changed since it was created.
						// Also update if the existing PI has application_fee_amount=0 but the correct
						// fee is now > 0 (e.g. a PI created before the remaining_link fee fix was deployed).
						$amountChanged  = $paymentIntent->amount !== (int) round($stripeTotal * 100);
						$feeNeedsUpdate = $stripeFees > 0 && (int) ($paymentIntent->application_fee_amount ?? 0) === 0;
						if ($amountChanged || $feeNeedsUpdate) {
							$paymentIntent = StripeUtils::updateStripePaymentIntentMakePayment($paymentIntent->id, $tourBooking, $request, $stripeTotal, $stripeFees, $partnerFeesTotal, $payoutAmount);
							// Keep the local reservation row (this leg's own amount/fee record — see
							// the matching comment below) in step with whatever we just told Stripe,
							// since paymentNewBooking() reads fee_amount back from this row for a
							// platform-held charge rather than from Stripe's application_fee_amount,
							// which platform-held never sets.
							DB::table('transactions')
								->where('id', $existingReservation->id)
								->update(['amount' => $stripeTotal, 'fee_amount' => $stripeFees]);
						}
						$result = [
							'clientSecret' => $paymentIntent->client_secret,
							'savedCards'   => StripeUtils::getSavedCardsForCustomer($paymentIntent->customer),
						];
						return;
					}
					// Terminal (canceled) — clear it so a fresh one is created below, reusing this row
					DB::table('transactions')
						->where('id', $existingReservation->id)
						->update(['transaction_number' => null]);
					$reservationId = $existingReservation->id;
				} elseif ($existingReservation) {
					// Reservation exists with empty transaction_number (prior crash before stamp) — reuse it
					$reservationId = $existingReservation->id;
				} else {
					// No reservation yet — insert one to claim the slot
					$reservationId = DB::table('transactions')->insertGetId([
						'tour_booking_id'      => $request->bookingID,
						'amount'               => 0,
						'fee_amount'           => 0,
						'transaction_number'   => null,
						'via'                  => 'Card',
						'payment_type'         => 0,
						'response_init'        => '',
						'response_end'         => '',
						'status'               => 'Initialized',
						'visitor'              => $request->getClientIp(),
						'request_from'         => $request->is('api/*') ? Constants::API : Constants::BACKEND,
						'created_by'           => Auth::id(),
						'created_by_user_type' => userType(),
						'created_at'           => Carbon::now(),
					]);
				}

				// Create PI while the lock is still held — the stamp commits atomically with
				// the lock release, so no concurrent request can slip through and create a second PI
				$result = StripeUtils::createStripePaymentIntentMakePayment($tourBooking, $request, $stripeTotal, $stripeFees, $partnerFeesTotal, $payoutAmount);

				// Stamp this leg's own amount/fee onto its reservation row, not just the PI id.
				// A platform-held PaymentIntent never carries application_fee_amount (Stripe
				// simply doesn't set it there — see buildConnectedPaymentData()), so it can't be
				// read back later the way a destination-mode charge's real fee can.
				// paymentNewBooking() instead reads $stripeFees back from THIS row (matched by
				// transaction_number) when finalizing a platform-held payment — $stripeFees here
				// is already this leg's own split-scoped fee (see stripeTotal/stripeFees above),
				// not the whole booking's commission, which is what made the earlier "fall back
				// to $serviceCommission" fix wrong for every leg after the first.
				DB::table('transactions')
					->where('id', $reservationId)
					->update([
						'transaction_number' => $result['paymentIntentId'],
						'amount' => $stripeTotal,
						'fee_amount' => $stripeFees,
					]);
			});

			// Thrown only now, after the transaction above has already committed — so any
			// reconciliation writes made while producing this exception are preserved.
			if ($alreadyPaidException) {
				throw $alreadyPaidException;
			}

			return $result;
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			HistoryService::storeNormalErrorInHistory($request->bookingID, $exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Reconcile a make-payment PaymentIntent that Stripe reports as succeeded but that never got
	 * completed locally (the browser's one follow-up call to paymentNewBooking() never landed).
	 * Replays that exact completion logic synchronously via a request built to match what the
	 * original successful confirmation would have submitted — same transaction/history/booking-total
	 * writes, no webhook involved. Safe to call more than once for the same PaymentIntent:
	 * paymentNewBooking() itself is idempotent against being invoked twice for one payment_intent_id.
	 *
	 * @param string $paymentIntentId
	 * @param object $originalRequest
	 * @return void
	 */
	private static function reconcileSucceededMakePayment(string $paymentIntentId, $originalRequest): void
	{
		try {
			// Idempotency: if a Completed transaction already exists for this PI, the payment was
			// already recorded (either by a prior reconcile call or by the normal paymentNewBooking
			// path). Skip the reconcile to prevent duplicate transaction/history rows, but still
			// clean up any orphaned Initialized reservation row so it doesn't block future calls.
			$alreadyReconciled = Transaction::where('transaction_number', $paymentIntentId)
				->where('status', 'Completed')
				->exists();

			if (!$alreadyReconciled) {
				$syntheticParams = [
					'bookingID'         => $originalRequest->bookingID,
					'payment_method'    => 'Card',
					'payment_intent_id' => $paymentIntentId,
				];
				if (!empty($originalRequest->remaining_link_id)) {
					$syntheticParams['remaining_link_id'] = $originalRequest->remaining_link_id;
				}
				$syntheticRequest = Request::create('/internal/reconcile-make-payment', 'POST', $syntheticParams);

				$response = app(\App\Repositories\TourBooking\V3\TourBookingRepository::class)->paymentNewBooking($syntheticRequest);

				$data = method_exists($response, 'getData') ? $response->getData(true) : (array) $response;

				if (!empty($data['errors'])) {
					Log::error('Make-payment reconciliation failed for PaymentIntent ' . $paymentIntentId . ': ' . (is_array($data['errors']) ? json_encode($data['errors']) : $data['errors']));
					return;
				}

				Log::info('Reconciled a succeeded-but-uncompleted make-payment: PaymentIntent ' . $paymentIntentId . ' for booking ' . $originalRequest->bookingID . '.');
			}

			// Clean up any orphaned Initialized reservation row that still carries this PI.
			// TransactionService::storePaymentInTransaction updates the Initialized row to
			// Completed, but some payment paths (e.g. remaining_payment link handlers) create
			// a new Completed row directly, leaving the Initialized one behind. Without cleanup
			// the next Make Payment call re-finds it, fires reconcile again, and creates another
			// duplicate transaction.
			Transaction::where('transaction_number', $paymentIntentId)
				->where('status', 'Initialized')
				->update(['status' => 'Cancelled']);
		} catch (Exception $exception) {
			Log::error('Make-payment reconciliation threw for PaymentIntent ' . $paymentIntentId . ': ' . $exception->getMessage());
		}
	}
	/**
	 * Execute the Stripe service to create paymentintent for make payment for the tour booking.
	 *
	 * @param object $request
	 *
	 * @return mixed
	 */
	public static function stripeCreatePaymentIntentForMakeComboPayment($request)
	{
		try {
			$isCashFees = cashFees();
			$cartId = $request->cartID;
			$paymentMethod = $request->payment_method_cart_payment;
			$cartBookings = DB::table('tour_bookings_master AS tbm')
				->where('tbm.cart_id', $cartId)
				->get();
			$paymentsCountOfCart = DB::table('transactions AS t')
				->join('tour_bookings_master AS tbm', 'tbm.id', '=', 't.tour_booking_id')
				->where('tbm.cart_id', $cartId)
				->where('t.status', 'Completed')
				->whereNull('t.charge_type')
				->count();

			if ($paymentsCountOfCart > 0) {
				throw new Exception('Combo Bookings are already paid.');
			}
			$bookingDetails = TourBooking::where('id', $request->bookingID)->first();
			$customer = Customer::where('id', $bookingDetails->customer_id)
				->withTrashed()->first();

			$totalCartAmount = 0;
			$totalCartFees = 0;
			$cartAddonPrice = 0;
			$cartAddonFee = 0;
			$cartAddonTotal = 0;
			$cartCustomChargeFee = 0;
			$cartCustomChargeTotal = 0;
			foreach ($cartBookings as $cartBooking) {
				$bookingId = $cartBooking->id;
				$info = BookingUtils::getBookingDetails($bookingId);
				$commissionRates = TourPackageService::getCommissionFeesPercentage($request, $customer, $info);

				// Assign the returned values to respective variables
				$natFeesPercentage = $commissionRates['nat_fees_percentage'];
				$stripeFeesPercentage = $commissionRates['stripe_fees_percentage'];
				$bookingT = BookingTransact::with('packageRate')
					->where('tour_booking_id', $bookingId)
					->where('deleted_by', 0)
					->get();
				$result = CartService::calculateTotalAmount($bookingT, $paymentMethod, $isCashFees, $request, $info, $natFeesPercentage, $stripeFeesPercentage, false);
				$total = $result['total'];
				$serviceCommissionTotal = $result['serviceCommissionTotal'];
				$oldDiscountAmt = 0;

				// Check for old discount applied when booking is done using Pay Later
				if (isset($info->discount2_value) && $info->discount2_value > 0 && $info->discount2_percentage == 0) {
					$oldDiscountAmt = $info->discount2_value;
					$total -= $info->discount2_value;
				} else if ($info->discount2_percentage > 0) {
					$oldDiscountAmt = number_format(($total * $info->discount2_percentage) / 100, 2, '.', '');
					$total -= $oldDiscountAmt;
				}

				// Add the service commission in the total amount
				// Determine service commission based on affiliate status and booking conditions
				$serviceCommission = ($oldDiscountAmt > 0)
					? roundout(($total * $natFeesPercentage / 100), 2)
					: $serviceCommissionTotal;

				$total += $serviceCommission;

				$totalCartAmount += $total;
				$totalCartFees += $serviceCommission;
				$customCharge = CustomChargeBookingService::customChargePrice($bookingId);
				$addonsAmount = BookingService::fetchPayLaterAddonsWithPrice($info->id, $natFeesPercentage);

				$cartAddonPrice += $addonsAmount['addons_price'] ?? 0;
				$cartAddonFee += $addonsAmount['addons_fee'] ?? 0;
				$cartAddonTotal += $addonsAmount['addons_total'] ?? 0;
				$cartCustomChargeFee += $customCharge['pay_later_custom_charge_fees'] ?? 0;
				$cartCustomChargeTotal += $customCharge['pay_later_custom_charge_total'] ?? 0;
				$cardBookingsData[] = [
					'booking_id' => $bookingId,
					'name' => $bookingDetails->name,
					'email' => $bookingDetails->email,
					'phone_number' => $bookingDetails->phone_number,
					'stripe_destination_id' => $info->stripe_destination_id,
					'statement_descriptor' => $info->statement_descriptor,
					'tour_operator' => $info->tour_operator,
					'package_name' => $info->package_name,
					// Needed so createStripePaymentIntentMakeComboPayment() can resolve the mixed-cart
					// settlement mode across every booking in the combo, not just the last one.
					'date' => $info->date,
					// Needed so createStripePaymentIntentMakeComboPayment() knows whether to keep
					// transfer_group on this shared charge for ThreeWayPaymentService's sake.
					'is_partner_booking' => $info->is_partner_booking ?? 0,
				];
			}

			$totalCartAmount +=  $cartAddonTotal + $cartCustomChargeTotal;
			$totalCartFees = roundout($totalCartFees + $cartAddonFee + $cartCustomChargeFee, 2);
			$stripeTotal = round($totalCartAmount, 2);
			$stripeFees = $totalCartFees;
			$data = StripeUtils::createStripePaymentIntentMakeComboPayment($cardBookingsData, $request, $stripeTotal, $stripeFees, $info->customeremail);
			return $data;
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			HistoryService::storeNormalErrorInHistory($request->bookingID, $exception);
			throw new GeneralCoreException($exception);
		}
	}
	/**
	 * Calculate service commission for make payment.
	 *
	 * @param float $total
	 * @param float $totalForDiscountWithSplit
	 * @param float $natFeesPercentage
	 * @param float $serviceCommissionTotal
	 * @param int $bookingID
	 * @param int $oldBookingId
	 * @param float $discountAmt
	 * @param float $oldDiscountAmt
	 * @param object $request
	 * @return float
	 */
	public static function calculateServiceCommissionMakePayment(
		float $total,
		float $totalForDiscountWithSplit,
		float $natFeesPercentage,
		float $serviceCommissionTotal,
		int $bookingID,
		int $oldBookingId,
		float $discountAmt,
		float $oldDiscountAmt,
		object $request,
		bool $isFirstPayment = false
	): float {
		if ($bookingID >= $oldBookingId) { // Set booking ID 95440
			// A split leg on a booking that has NOT been paid yet ($isFirstPayment) prices the
			// whole booking, exactly like a full payment does — so its fee must be the same
			// per-person-rounded $serviceCommissionTotal, not the aggregate-rounded formula the
			// discount path uses. The bare `!empty(split_value)` gate below made that first leg
			// persist service_commission 57.58 / total 643.98 while the card amount was 57.60 /
			// 644.00 (real 8-pax booking, nat 9.82%), leaving the booking 2¢ short of Payment
			// Total. Later legs keep the old behaviour: $total is already the leg amount there.
			if ($isFirstPayment && $discountAmt <= 0 && $oldDiscountAmt <= 0 && $totalForDiscountWithSplit <= 0) {
				return $serviceCommissionTotal;
			}
			if ($discountAmt > 0 || $oldDiscountAmt > 0 || !empty($request->split_value)) {
				return $totalForDiscountWithSplit
					? roundout($totalForDiscountWithSplit * $natFeesPercentage / 100, 2)
					: roundout($total * $natFeesPercentage / 100, 2);
			}
			return $serviceCommissionTotal;
		} else {
			return roundout($total * 6 / 100, 2);
		}
	}

	/**
	 * Execute the Stripe service to create paymentintent for make payment for the tour booking.
	 *
	 * @param object $request
	 *
	 * @return mixed
	 */
	public static function stripeCreatePaymentIntentForChangePeople($request)
	{
		try {
			$tourBooking = TourBooking::find($request->bookingID);

			$isGroupBooking = $tourBooking->is_group_booking;
			$isPartnerBooking = $tourBooking->is_partner_booking;
			$bookingT = DB::table('tour_bookings_transact AS tbt')
				->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
				->where('tbt.tour_booking_id', $request->bookingID)
				->where('tbt.deleted_by', 0)
				->orderBy('tpr.orderby', 'asc')
				->get(['tpr.rate_for', 'tbt.tour_package_rate_id', 'tbt.partner_fees_type', 'tbt.partner_fees_amount', 'tbt.tourists', 'tbt.infants', 'tbt.is_infant_rate', 'tbt.rate', 'tbt.additional_charge', 'tbt.total', 'tbt.stripe_fees_percentage']);

			$rateGroupWiseTourists = array();
			$rateGroupWiseIsInfantRate = array();
			$totalTouristsOfBooking = 0;
			$oldGroupRateId = 0;
			$oldGroupTourists = 0;
			$oldGroupRate = 0;
			foreach ($bookingT as $value) {
				$rateGroupWiseTourists[$value->tour_package_rate_id] = ($value->infants > 0) ? $value->infants : $value->tourists;
				$rateGroupWiseIsInfantRate[$value->tour_package_rate_id] = $value->is_infant_rate;
				$totalTouristsOfBooking += $value->tourists + $value->infants;
				if ($isGroupBooking == '1') {
					$oldGroupRateId = $value->tour_package_rate_id;
					$oldGroupTourists += $value->tourists;
					$oldGroupRate = $value->total;
				}
			}
			//Validations to restrict user to perform only delete peoples
			$result = BookingUtils::calculateChangedTouristsData($request, $tourBooking, $isGroupBooking, $rateGroupWiseTourists, $rateGroupWiseIsInfantRate);

			$deletedPeopleCount = $result['deletedPeopleCount'];
			$rateGroupWiseAddedPeople = $result['rateGroupWiseAddedPeople'];
			$rateGroupWiseDeletedPeople = $result['rateGroupWiseDeletedPeople'];
			$rateGrpIds = $result['rateGrpIds'];
			$permitfeeAr = $result['permitfeeAr'];

			$customer = Customer::withTrashed()->find($tourBooking->customer_id);
			$info = TourPackageService::getTourPackageSlotInfo($request->slot_time);

			if (!$info) {
				throw new Exception('Unable to change people to tour package');
			}
			$commissionRates = TourPackageService::getCommissionFeesPercentage($request, $customer, $info);

			// Assign the returned values to respective variables
			$natFeesPercentage = $commissionRates['nat_fees_percentage'];

			$rateGroups = BookingUtils::getRateGroups($rateGrpIds, $isPartnerBooking);

			$rateGroupWiseRate = $bookingT->pluck('rate', 'tour_package_rate_id')->toArray();
			$rateGroupWiseAdditionalCharge = $bookingT->pluck('additional_charge', 'tour_package_rate_id')->toArray();
			$rateGroupWisePartnerFeesValue = $bookingT->pluck('partner_fees_percentage', 'tour_package_rate_id')->toArray();
			$rateGroupWisePartnerFees = $bookingT->pluck('partner_fees_amount', 'tour_package_rate_id')->toArray();
			$rateGroupWisePartnerFeesType = $bookingT->pluck('partner_fee_type', 'tour_package_rate_id')->toArray();

			foreach ($rateGroups as $rateGroup) {
				if (isset($rateGroupWiseRate[$rateGroup->id])) {
					$rateGroup->rate = $rateGroupWiseRate[$rateGroup->id];
				}
				if (isset($rateGroupWiseAdditionalCharge[$rateGroup->id])) {
					$rateGroup->additional_charge = $rateGroupWiseAdditionalCharge[$rateGroup->id];
				}
				if ($isPartnerBooking == 1 && isset($rateGroupWisePartnerFees[$rateGroup->id])) {
					$rateGroup->partner_fee_amount = $rateGroupWisePartnerFees[$rateGroup->id];
				}
				if ($isPartnerBooking == 1 && isset($rateGroupWisePartnerFeesValue[$rateGroup->id])) {
					$rateGroup->partner_fee_percentage = $rateGroupWisePartnerFeesValue[$rateGroup->id];
				}
				if ($isPartnerBooking == 1 && isset($rateGroupWisePartnerFeesType[$rateGroup->id])) {
					$rateGroup->partner_fee_type = $rateGroupWisePartnerFeesType[$rateGroup->id];
				}
			}

			$result = EditBookingService::processCalculationsChangePeople($request, $rateGroups, $rateGroupWiseAddedPeople, $rateGroupWiseDeletedPeople, $rateGroupWiseTourists, $permitfeeAr, $natFeesPercentage, $oldGroupRateId, $oldGroupRate, $isGroupBooking, NULL, $isPartnerBooking);

			$total = $result['total'];
			$serviceCommissionTotal = $result['serviceCommissionTotal'];
			$serviceCommissionNewlyAddedTotal = $result['serviceCommissionNewlyAddedTotal'];
			$partnerFeesTotal = $result['partnerFeesTotal'];
			$refundPartnerFeesTotal = $result['refundPartnerFeesTotal'];
			$netPartnerFeesTotal = ($isPartnerBooking == 1) ? ($partnerFeesTotal - $refundPartnerFeesTotal) : 0;
			$payoutAmount = $result['payoutAmount'] - $refundPartnerFeesTotal;
			$newlyAddedTotal = $result['newlyAddedTotal'];

			// Merge priced price_per_pax add-on deltas into the SAME total this PaymentIntent is
			// created for — otherwise the card gets authorized for less than what changePeopleBooking()
			// will actually charge/refund once the edit is committed.
			// NOTE: this method only EVER runs to create a Card PaymentIntent (Cash/Invoiced never
			// call it — they settle straight through changePeopleBooking() without a PaymentIntent).
			// Don't gate the fee on $request->payment_method here — that request (built from
			// `new FormData(form)` before the "Ensure payment_method is included" fix that
			// tour-booking-change-people.js applies to the LATER commit submission) doesn't
			// reliably carry it, which silently dropped the add-on's commission fee from the
			// actual card charge while the price still merged in fine (unconditional).
			$addonDelta = ChangePeopleAddonService::calculatePricedAddonDelta(
				$request->addons_added ?? [],
				$request->addons_updated ?? [],
				$request->addons_removed ?? [],
				$natFeesPercentage,
				true
			);
			if ($addonDelta['net_price'] != 0) {
				$total += $addonDelta['net_price'];
			}
			// $serviceCommissionTotal feeds the "people only added" fast path below (used AS-IS
			// instead of recomputing `$total * natFeesPercentage / 100`) — fold the add-on's own
			// commission in here too, or this PaymentIntent gets created for less than the actual
			// combined charge, undercharging the card.
			if ($addonDelta['net_fee'] != 0) {
				$serviceCommissionTotal += $addonDelta['net_fee'];
			}

			$subtt = $total;
			$discountAmt = 0;

			if ($request->has('discount_type')) {
				$discountAmt = match ($request->discount_type) {
					'Percentage' => round(($total * ($request->discount_percentage ?? 0)) / 100, 2),
					'Fixed Money' => round($request->discount_fixed_money ?? 0, 2),
					default => 0,
				};
				$total -= $discountAmt;
				$subtt -= $discountAmt;
			}

			if ($serviceCommissionTotal > 0 && $deletedPeopleCount == 0) { //This is the calculations if people only added
				$serviceCommission = $serviceCommissionTotal;
			} else {
				$serviceCommission = roundout(($total * $natFeesPercentage / 100), 2); //This is the calculations if people only added and deleted both
			}
			$total += $serviceCommission;
			if ($newlyAddedTotal > 0) {
				if ($serviceCommissionNewlyAddedTotal > 0 && $deletedPeopleCount == 0) { //This is the calculations if people only added
					$newlyAddedTotal += $serviceCommissionNewlyAddedTotal;
				} else {
					$newlyAddedTotal += roundout($newlyAddedTotal * $natFeesPercentage / 100, 2); //This is the calculations if people only added and deleted both
				}
			}

			$stripeTotal = round($total, 2);
			$stripeFees = round($serviceCommission, 2);

			// If a pending remaining_payment link exists for this booking, include its amount
			// and all pending item fees in this PI so the card covers everything in one charge.
			$pendingLinkForCp = TourBookingPaymentLink::where('order_id', $tourBooking->group_id)
				->where('status', 'Unpaid')
				->where('section', 'remaining_payment')
				->first();
			if ($pendingLinkForCp) {
				$stripeTotal = round($stripeTotal + (float) $pendingLinkForCp->amount, 2);
				$pendingItemDetails = DB::table('tour_booking_payment_link_items')
					->where('payment_link_id', $pendingLinkForCp->id)
					->pluck('details');
				foreach ($pendingItemDetails as $rawDet) {
					$det = json_decode($rawDet ?? '[]', true) ?? [];
					$stripeFees += (float) ($det['platform_fees'] ?? 0);
				}
				$stripeFees = round($stripeFees, 2);

				// This same combined PaymentIntent already charges the FULL new price (not just a
				// delta) for any add-on field that supersedes a still-pending, never-paid link line
				// (calculatePricedAddonDelta()'s 'supersedes_pending_link' flag, set above) — drop
				// that field's stale pending amount here, or the customer is charged for both the
				// old and the new selection.
				//
				// The same goes for every other pending line this edit takes off, or reprices on,
				// the link once it is committed -- the link is shrunk BEFORE its remaining items
				// are settled, so the card must not be charged for what's about to disappear:
				//  - a partly-paid field whose new selection is exactly covered by what was paid
				//    ('pending_link_drop_db_ids'),
				//  - a removed / downgraded field (refundItems -- reduceChangePeopleAddonPaymentLink()
				//    drops its line),
				//  - a never-paid field moved to a cheaper selection ('pending_link_reprice_selections').
				$supersededFieldIds = array_column(
					array_filter($addonDelta['chargeItems'], fn ($item) => !empty($item['supersedes_pending_link'])),
					'db_id'
				);
				$linkDropFieldIds = array_merge(
					$supersededFieldIds,
					$addonDelta['pending_link_drop_db_ids'] ?? [],
					array_column($addonDelta['refundItems'] ?? [], 'db_id')
				);
				$linkRepriceSelections = $addonDelta['pending_link_reprice_selections'] ?? [];
				if (!empty($linkDropFieldIds) || !empty($linkRepriceSelections)) {
					$drop = PaymentLinkService::pendingAddonLinkDropAmounts($tourBooking->id, $linkDropFieldIds, null, $natFeesPercentage, $linkRepriceSelections);
					$stripeTotal = round($stripeTotal - $drop['amount'], 2);
					$stripeFees = round($stripeFees - $drop['fees'], 2);
				}
			} else {
				// No itemized link -- the booking's only pending balance may still be the
				// original lump-sum link (section=NULL, created at booking creation via
				// storeTourBookingPaymentLink(), or by a paymentLinkUpdate() replacement).
				// Missing it here charges the card for only the new people's own delta, silently
				// leaving the booking's original amount uncollected -- same class of bug already
				// fixed for PaymentLinkService::upsertRemainingPaymentLink() and
				// StripeService::stripeCreatePaymentIntentForAddonEditCharge().
				$pendingLumpSumForCp = TourBookingPaymentLink::where('order_id', $tourBooking->group_id)
					->where('status', 'Unpaid')
					->where(function ($q) {
						$q->whereNull('section')->orWhere('section', '!=', 'remaining_payment');
					})
					->first();
				if ($pendingLumpSumForCp) {
					$lumpSumAmount = (float) $pendingLumpSumForCp->amount;
					$stripeTotal   = round($stripeTotal + $lumpSumAmount, 2);
					// Same fee back-out as the add-ons equivalent -- the link's amount is a
					// single fee-inclusive total with no stored per-item breakdown.
					if ($natFeesPercentage > 0) {
						$stripeFees = round($stripeFees + ($lumpSumAmount - ($lumpSumAmount / (1 + $natFeesPercentage / 100))), 2);
					}
				}
			}

			$data = StripeUtils::createStripePaymentIntentEditBooking($tourBooking, $info, $customer, $stripeTotal, $stripeFees, "Change People - Web", $netPartnerFeesTotal, $payoutAmount);

			return $data;
		} catch (Exception $exception) {
			Log::error($exception);
			HistoryService::storeNormalErrorInHistory($request->bookingID, $exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Execute the Stripe service to create paymentintent for make payment for the change payment type.
	 *
	 * @param object $request
	 *
	 * @return mixed
	 */
	public static function stripeCreatePaymentIntentForChangePaymentType($request)
	{
		try {
			$id = $request->historyId;
			// Fetch
			$history = TourBookingHistory::find($id);
			$tourBooking = TourBooking::find($request->bookingID);
			$isPartnerBooking = $tourBooking->is_partner_booking;

			// Only a Card refund leaves real gateway state that a payment-type switch could desync.
			// Cash/Invoiced "refunds" (e.g. removing a pax, discounts) are ledger-only adjustments —
			// no money ever moved through Stripe — so they must not block the switch.
			$refundTrans = Transaction::leftJoin('tour_booking_history as tbh', 'tbh.transaction_id', '=', 'transactions.id')
				->where('transactions.tour_booking_id', $request->bookingID)
				->where('transactions.status', 'Refund')
				->where('transactions.via', 'Card')
				->whereNull('transactions.charge_type')
				->get(['transactions.id', 'transactions.amount', 'tbh.details']);
			$totalRefundedAmount = 0;
			foreach ($refundTrans as $key => $val) {
				$details = json_decode($val->details, true);
				$totalRefundedAmount += (isset($details['from']) && $details['from'] == 'Extend Booking') ? 0 : $val->amount;
			}

			if ($totalRefundedAmount > 0) {
				throw new Exception('Unable to change payment type because one or more refunds done for the booking.');
			}

			// Only count real ticket payments here — add-ons and custom charges also log an
			// 'operation' => 'Payment' row (with charge_type set) for their own settlement, and
			// those aren't a second payment on the booking itself. Without whereNull('charge_type')
			// adding any add-on/custom charge permanently blocks payment type changes on the booking.
			$payments = TourBookingHistory::where('operation', 'Payment')->where('tour_booking_id', $history->tour_booking_id)->whereNull('charge_type')->count();
			if ($payments > 1) {
				throw new Exception('Unable to change payment type because one or more payments done for the booking.');
			}

			$customer = Customer::where('id', $tourBooking->customer_id)
				->withTrashed()
				->first();

			$newPaymentType = $request->payment_method;
			if ($newPaymentType == 'Card') {
				$info = BookingUtils::getBookingDetails($tourBooking->id);
				$isGroupBooking = $info->is_group_booking;

				$commissionRates = TourPackageService::getCommissionFeesPercentage($request, $customer, $info);

				// Assign the returned values to respective variables
				$natFeesPercentage = $commissionRates['nat_fees_percentage'];
				$stripeFeesPercentage = $commissionRates['stripe_fees_percentage'];

				$bookingT = BookingTransact::with('packageRate')
					->where('tour_booking_id', $tourBooking->id)
					->where('deleted_by', 0)
					->get();

				$discountAmt = 0;
				$oldDiscountAmt = 0;

				// Calculate the booking amount
				$result = EditBookingService::processCalculationsChangePaymentType($bookingT, $request, $info, $isGroupBooking, $natFeesPercentage, $stripeFeesPercentage, $isPartnerBooking);
				$total = $result['total'];
				$serviceCommissionTotal = $result['serviceCommissionTotal'];
				$partnerFeesTotal = $result['partnerFeesTotal'];
				$payoutAmount = $result['payoutAmount'];

				$trans = Transaction::where('tour_booking_id', $tourBooking->id)->where('status', 'Completed')->whereNull('charge_type')->first(['payment_type', 'amount']);
				if (!empty($trans) && ($trans->payment_type == 'Card')) {
					if (isset($request->api) && $request->api == 1) {
						throw new Exception('Tour booking already paid.');
					} else {
						return response()->json([
							'tourBookingId' => $tourBooking->id,
							'paid' => 1
						]);
					}
				}

				// Discount given on pay later option when booking created
				// Here is the total minus discount amount
				$totalForDiscountWithSplit = 0;
				if ($info->discount2_value > 0) {
					$discountAmt = $info->discount2_value;
					$total -= $discountAmt;
				}

				// Add the service commission in the total amount
				if ($customer->is_affiliate == 'No') {
					if ($discountAmt > 0 || $oldDiscountAmt > 0) {
						if ($totalForDiscountWithSplit) {
							$serviceCommission = roundout(($totalForDiscountWithSplit * $natFeesPercentage / 100), 2);
						} else {
							$serviceCommission = roundout(($total * $natFeesPercentage / 100), 2);
						}
					} else {
						$serviceCommission = $serviceCommissionTotal;
					}

					$total = $total + $serviceCommission;
				} elseif ($customer->is_affiliate == 'Yes') {
					if ($discountAmt > 0 || $oldDiscountAmt > 0) {
						if ($totalForDiscountWithSplit) {
							$serviceCommission = roundout(($totalForDiscountWithSplit * $natFeesPercentage / 100), 2);
						} else {
							$serviceCommission = roundout(($total * $natFeesPercentage / 100), 2);
						}
					} else {
						$serviceCommission = $serviceCommissionTotal;
					}

					$total = $total + $serviceCommission;
				}
			}

			$stripeTotal = round($total, 2);
			$stripeFees = round($serviceCommission, 2);

			$data = StripeUtils::createStripePaymentIntentEditBooking($tourBooking, $info, $customer, $stripeTotal, $stripeFees, "Change Payment Method - Web", $partnerFeesTotal, $payoutAmount);

			return $data;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Execute the Stripe service to create paymentintent for make payment for the switch package.
	 *
	 * @param object $request
	 *
	 * @return mixed
	 */
	public static function stripeCreatePaymentIntentForSwitchPackage($request)
	{
		try {
			$slotId = $request->tour_slot_time_switch;
			$userType = userType();

			$info = TourPackageService::getTourPackageSlotInfo($slotId);

			$tourBooking = TourBooking::find($request->bookingID);
			$isPartnerBooking = $tourBooking->is_partner_booking;
			$oldDiscount = $tourBooking->discount2_value;
			$isGroupBooking = $tourBooking->is_group_booking;
			$customer = Customer::where('id', $tourBooking->customer_id)->withTrashed()->first();
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


			$result = EditBookingService::processCalculationsSwitchPackage($rateGroups, $isGroupBooking, $info->is_partner_package, $request, $customer, $oldDiscount, $natFeesPercentage, $stripeFeesPercentage, $tourBooking, $userType);
			$subTotal = $result['subTotal'];
			$total = $result['total'];
			$serviceCommissionTotal = $result['serviceCommissionTotal'];
			$partnerFeesTotal = $result['partnerFeesTotal'];
			$payoutAmount = $result['payoutAmount'];

			$remainingAmounts = SwitchTourPackageCashService::calculateRemainingAmount($request->bookingID, '');


			$paymentAmount = $total;
			$refundTicketCostAmount = $remainingAmounts['ticketCostAmount'];
			$refundPlatformFeesAmount = $remainingAmounts['platformFeesAmount'];
			$refundPartnerFeesAmount = $remainingAmounts['partnerFeesAmount'];
			$remainingAmount = $remainingAmounts['netAmount'];

			if ($paymentAmount > $remainingAmount) {
				$netTicketCostAmount = $subTotal - $oldDiscount - $refundTicketCostAmount;
				$netPlatFormFeesAmount = $serviceCommissionTotal - $refundPlatformFeesAmount;
				$netPartnerFeesAmount = $partnerFeesTotal - $refundPartnerFeesAmount;
				$netAmount = round($netTicketCostAmount + $netPlatFormFeesAmount, 2);
				$payoutAmount = round($netTicketCostAmount - $netPartnerFeesAmount, 2);
				$netPlatFormFeesAmount = $netPlatFormFeesAmount < 0 ? 0 : $netPlatFormFeesAmount;
				$feesAmount = roundout($netPlatFormFeesAmount, 2);
			}

			$stripeTotal = round($netAmount, 2);
			$stripeFees = round($feesAmount, 2);

			$data = StripeUtils::createStripePaymentIntentEditBooking($tourBooking, $info, $customer, $stripeTotal, $stripeFees, "Switch Package - Web", $netPartnerFeesAmount, $payoutAmount);
			return $data;
		} catch (Exception $exception) {
			Log::error($exception);
			HistoryService::storeNormalErrorInHistory($request->bookingID, $exception);
			throw new GeneralCoreException($exception);
		}
	}
	/**
	 * Generate the tour booking description.
	 */
	public static function generateDescription($info, $totalTourists, $request): string
	{
		return implode(PHP_EOL, [
			"Tour Package: " . ($info->package_name ?? 'N/A'),
			"Tour Date: " . ($info->date ?? 'N/A'),
			"Tour Time: " . ($info->time_from ?? 'N/A'),
			"Tourists: " . ($totalTourists ?? '0'),
			"Email: " . ($request->customer_email ?? 'N/A')
		]);
	}

	/**
	 * Prepare booking metadata for Stripe.
	 */
	public static function prepareBookingData($info, $totalTourists, $request, $stripeTotal, $stripeFee, $partnerFee = 0, $payoutAmount = 0): array
	{
		return [
			'tour_package' => $info->package_name ?? 'N/A',
			'tour_date' => $info->date ?? 'N/A',
			'tour_time' => $info->time_from ?? 'N/A',
			'tourists' => $totalTourists ?? 0,
			'amount' => $stripeTotal ?? 0.00,
			'platform_fee' => $stripeFee ?? 0.00,
			'partner_fee' => $partnerFee ?? 0.00,
			'payout_amount' => $payoutAmount ?? 0.00,
			'customer_name' => $request->customer_name ?? 'N/A',
			'customer_phone' => $request->customer_phone_number ?? 'N/A',
			'email' => $request->customer_email ?? 'N/A',
			'split_payment' => (int) ($request->split_value ?? 0) > 0 ? 1 : 0,
		];
	}

	/**
	 * Calculate Stripe Fees and Total for cart.
	 *
	 * @param array $cardBookingsData
	 * @param array $data
	 * @param float $stripeFeesPercentage
	 * @return array
	 */
	public static function calculateStripeAmountsCart(array $cardBookingsData, array $data, float $stripeFeesPercentage): array
	{
		$stripeFee = 0;
		$stripeTotal = 0;
		$description = '';

		foreach ($cardBookingsData as $index => $booking) {
			if (!empty($data['split_value'])) {
				$stripeF = round($data['total'] * $stripeFeesPercentage / 100, 2);
				$stripeFee += $stripeF + $booking['addon_fee'] + $booking['custom_charge_fees'] + $booking['tip_fees'];
				$stripeTotal += $data['total'] + $booking['addon_total'] + $booking['custom_charge_amount'] + $booking['custom_charge_fees'] + $booking['tip_amount'] + $booking['tip_fees'];
			} else {
				$stripeFee += $booking['serviceCommission'] + $booking['addon_fee'] + $booking['custom_charge_fees'] + $booking['tip_fees'];
				$stripeTotal += $booking['total'] + $booking['addon_total'] + $booking['custom_charge_amount'] + $booking['custom_charge_fees'] + $booking['tip_amount'] + $booking['tip_fees'];
			}

			$description .= ($index > 0 ? "\n\n" : '') . self::formatDescription($booking, $data['customer_email']);
		}

		return compact('stripeFee', 'stripeTotal', 'description');
	}

	/**
	 * Format the Payment Description.
	 *
	 * @param array $booking
	 * @param string $customerEmail
	 * @return string
	 */
	private static function formatDescription(array $booking, string $customerEmail): string
	{
		return sprintf(
			"Tour Package: %s\nTour Date: %s\nTour Time: %s\nTourists: %s\nEmail: %s",
			$booking['tour_package'],
			$booking['date'],
			$booking['time_from'],
			$booking['tourists'],
			$customerEmail
		);
	}
	/**
	 * Updates the Stripe payment intent with metadata.
	 *
	 * @param string $paymentIntentId
	 * @param float $total
	 * @param float $subTotal
	 * @param float $serviceCommission
	 * @param object $info
	 * @param Request $request
	 * @param string $from
	 * @param float $partnerFeesTotal
	 */
	public static function updatePaymentIntentForMakePayment(
		string $paymentIntentId,
		$total,
		$subTotal,
		$serviceCommission,
		object $info,
		Request $request,
		string $from,
		$partnerFeesTotal
	): void {
		$payoutAmount = $subTotal - $partnerFeesTotal;
		$stripeMetadata = self::buildStripeMetadataForMakePayment($info, $request, $from, $total, $partnerFeesTotal, $serviceCommission, $payoutAmount);

		$retrieveOptions = ['expand' => ['charges.data.transfer']];
		$paymentIntent = app(PaymentGatewayInterface::class)->retrievePayment($paymentIntentId, $retrieveOptions);
		// Update the payment intent with metadata
		$updateParams = $stripeMetadata;
		app(PaymentGatewayInterface::class)->updatePayment($paymentIntentId, $updateParams);

		// If the payment has a destination transfer, update the charge
		if (isset($paymentIntent->charges->data[0]->transfer->destination_payment)) {
			$chargeParams = $stripeMetadata;
			app(PaymentGatewayInterface::class)->updateConnectedPayment(
				$paymentIntent->charges->data[0]->transfer->destination_payment,
				$chargeParams,
				$paymentIntent->charges->data[0]->transfer->destination
			);
		}
	}
	/* Builds metadata for the Stripe payment intent for make payment.
	*
	* @param object $info
	* @param Request $request
	* @param string $from
	* @return array
	*/
	private static function buildStripeMetadataForMakePayment(object $info, Request $request, string $from, $total, $partnerFees, $serviceCommission, $payoutAmount): array
	{
		$metadata = [
			'booking_id' => $info->id,
			'tour_package' => $info->package_name,
			'tour_date' => $info->date,
			'tour_time' => $info->time_from,
			'tourists' => $info->tourists + $info->infants,
			'amount' => $total ?? 0.00,
			'platform_fee' => $serviceCommission ?? 0.00,
			'partner_fee' => $partnerFees ?? 0.00,
			'payout_amount' => $payoutAmount ?? 0.00,
			'from' => $from,
			'tenant_id' => tenant()->id,
		];

		// Check if the request is from API and add relevant metadata
		if ($request->has('api') && $request->api == '1') {
			$metadata['app'] = Constants::STATUS_ONE;
			$metadata['split'] = !empty($request->split_value) ? 1 : 0;
		} else {
			$metadata['split_payment'] = !empty($request->split_value) ? 1 : 0;
		}

		return [
			'description' => self::generateDescriptionForMakePayment($info, $from),
			'metadata' => $metadata,
		];
	}

	/**
	 * Generates a description for the Stripe payment metadata for make payment.
	 *
	 * @param object $info
	 * @param string $from
	 * @return string
	 */
	private static function generateDescriptionForMakePayment(object $info, string $from): string
	{
		return sprintf(
			"Booking Id: %s\nTour Package: %s\nTour Date: %s\nTour Time: %s\nTourists: %s\nEmail: %s\nFrom: %s",
			$info->id,
			$info->package_name,
			$info->date,
			$info->time_from,
			$info->tourists + $info->infants,
			$info->customeremail,
			$from
		);
	}

	/**
	 * Execute the Stripe service to create paymentintent for edit discount.
	 *
	 * @param object $request
	 *
	 * @return mixed
	 */
	public static function stripeCreatePaymentIntentForEditDiscount($request)
	{
		try {

			$discountHistoryArray = $request->has('discountHistory') ? json_decode($request->discountHistory, true) : "";
			$master = TourBooking::find($discountHistoryArray['tour_booking_id']);

			$tourPackageInfo = static::getTourPackageInformation($master->tour_slot_id);

			$discountHistoryArray['webhook'] = (!empty($request->webhook) && $request->webhook == 1) ? 1 : 0;
			$total = $discountHistoryArray['new_total'] + $discountHistoryArray['service_commission'];
			$total = !empty($total) ? $total : 0;
			$customer = Customer::where('id', $master->customer_id)->first(['name as customer_name', 'email as customer_email', 'phone_number as customer_phone'])->toArray();

			$tourBookingTransaction = BookingTransact::where('tour_booking_id', $master->id)->where('deleted_by', 0)->first();
			$destinationAmount = roundout($total * $tourBookingTransaction->stripe_fees_percentage / 100, 2);

			// Add pending remaining_payment link amount and fees so the PI covers everything in one charge.
			$pendingLinkForDiscount = TourBookingPaymentLink::where('order_id', $master->group_id)
				->where('status', 'Unpaid')
				->where('section', 'remaining_payment')
				->first();
			if ($pendingLinkForDiscount) {
				$total = round($total + (float) $pendingLinkForDiscount->amount, 2);
				$pendingItemDetails = DB::table('tour_booking_payment_link_items')
					->where('payment_link_id', $pendingLinkForDiscount->id)
					->pluck('details');
				foreach ($pendingItemDetails as $rawDet) {
					$det = json_decode($rawDet ?? '[]', true) ?? [];
					$destinationAmount += (float) ($det['platform_fees'] ?? 0);
				}
				$destinationAmount = round($destinationAmount, 2);
			}

			$stripeDetails = [
				'stripeTotal' => $total,
				'stripeFee' => $destinationAmount,
				'description' => $customer['customer_name'],
				'tourists' => $master->tourists + $master->infants,
				'BookingId' => $master->id,
			];
			if ($master->is_partner_booking == 1) {
				$stripeDetails['payoutAmount'] = $total - $destinationAmount;
			}

			$data = StripeUtils::createStripePaymentIntentCommon($stripeDetails, $customer,  $tourPackageInfo, app(PaymentModeResolver::class)->resolveForSingleDate($tourPackageInfo->date));
			return $data;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}


	/**
	 * Get tour package information
	 * 
	 * @param int $tourSlotId
	 */
	public static function getTourPackageInformation($tourSlotId)
	{
		$info = "SELECT
			`t_o`.`stripe_destination_id`,`tp`.`stripe_destination_id` as `partnerDestinationId`,`t_o`.`statement_descriptor`, `t_o`.`name` as `tour_operator`, `tst`.`date`, `tst`.`time_from`, `tp`.`name` as `tour_package`
		FROM
			`tour_slot_times` as `tst`
			INNER JOIN `tour_packages` as `tp` ON `tp`.`id` = `tst`.`tour_package_id`
			INNER JOIN `tour_operators` as `t_o` ON `t_o`.`id` = `tp`.`tour_operator_id`
		WHERE
			`tst`.`id` = " . $tourSlotId . "
		";

		$info = collect(DB::select($info))->first();

		return $info;
	}

	/**
	 * Execute the Stripe service to create paymentintent for custom charge.
	 *
	 * @param object $request
	 *
	 * @return mixed
	 */
	public static function stripeCreatePaymentIntentForCustomCharge($request)
	{
		try {

			$isCustomCharge = customCharge();
			$id = $request->bookingID;

			// Get booking details
			$master = TourBooking::find($id);
			$tourPackageInfo = static::getTourPackageInformation($master->tour_slot_id);
			$customer = Customer::where('id', $master->customer_id)->first(['name as customer_name', 'email as customer_email', 'phone_number as customer_phone'])->toArray();

			$tourBookingTransaction = BookingTransact::where('tour_booking_id', $id)->where('deleted_by', 0)->first();

			if ($isCustomCharge == 1) {
				// Use combined_charge_total (new charge + existing pending items) when set by the frontend
				$finalCustomChargeAmount = ($request->has('combined_charge_total') && (float)$request->combined_charge_total > 0)
					? (float)$request->combined_charge_total
					: (float)$request->custom_charge_total;
				$customChargeBaseAmount = $request->custom_charge_amount ?? $finalCustomChargeAmount;
				$customChargeFees = isset($request->custom_charge_fees) ? floatval($request->custom_charge_fees) : 0;

				// Determine if taxable: check taxable flag OR if fees > 0 (fees are only sent when taxable)
				$isTaxable = (isset($request->taxable) && $request->taxable == 1) || $customChargeFees > 0 ? 1 : 0;

				// Use fees from request when taxable is checked, otherwise set to 0
				if ($isTaxable == 1) {
					// Use fees from request if available and > 0, otherwise calculate from base amount
					$stripeFees = $customChargeFees > 0
						? roundout($customChargeFees, 2)
						: roundout($customChargeBaseAmount * $tourBookingTransaction->stripe_fees_percentage / 100, 2);
				} else {
					$stripeFees = 0;
				}

				// Add platform_fees from ALL pending remaining_payment link items (custom_charge,
				// addon, change_people, etc.) so application_fee_amount covers every pending fee.
				$pendingLinkId = DB::table('tour_booking_payment_links')
					->where('order_id', $master->group_id)
					->where('status', 'Unpaid')
					->where('section', 'remaining_payment')
					->value('id');
				if ($pendingLinkId) {
					$pendingItemDetails = DB::table('tour_booking_payment_link_items')
						->where('payment_link_id', $pendingLinkId)
						->pluck('details');
					foreach ($pendingItemDetails as $rawDet) {
						$det = json_decode($rawDet ?? '[]', true) ?? [];
						$stripeFees += (float) ($det['platform_fees'] ?? 0);
					}
				}

				$stripeDetails = [
					'stripeTotal' => $finalCustomChargeAmount,
					'stripeFee' => $stripeFees,
					'description' => $tourPackageInfo->stripe_destination_id,
					'tourists' => $master->tourists + $master->infants,
					'BookingId' => $master->id,
				];

				$data = StripeUtils::createStripePaymentIntentCommon($stripeDetails, $customer,  $tourPackageInfo, app(PaymentModeResolver::class)->resolveForSingleDate($tourPackageInfo->date));
				return $data;
			} else {
				return [
					'bookingId' => $id,
					'message' => 'The custom charge feature is disabled; please enable it from the settings module.'
				];
			}
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Process Stripe refund.
	 *
	 * @param string $transactionId
	 * @param  $totalAmount
	 * @param string $mode One of PaymentModeResolver::DESTINATION/PLATFORM_HELD — the settlement mode
	 *                     the ORIGINAL transaction being refunded actually used. Required, not looked
	 *                     up here: every caller already has the Transaction row in scope for this exact
	 *                     transaction, so re-querying it inside this shared wrapper would be a redundant
	 *                     query at a site called from 6 places.
	 * @return void
	 */
	public static function processStripeRefund(string $transactionId, $totalAmount, string $mode): void
	{
		$refundParams = [
			'payment_intent' => $transactionId,
			'amount' => $totalAmount * 100,
			'reverse_transfer' => true,
			'refund_application_fee' => true
		];
		app(PaymentGatewayInterface::class)->refundPayment($refundParams, $mode);
	}

	/**
	 * Execute the Stripe service to create paymentintent for tip.
	 *
	 * @param object $request
	 *
	 * @return mixed
	 */
	public static function stripeCreatePaymentIntentForTip($request)
	{
		try {
			// Get booking details
			$master = TourBooking::find($request->booking_id);
			$tourPackageInfo = static::getTourPackageInformation($master->tour_slot_id);
			$customer = Customer::where('id', $master->customer_id)->first(['name as customer_name', 'email as customer_email', 'phone_number as customer_phone'])->toArray();

			$tipSettingId = $request->tip_setting_id;

			$gratuityetting = GratuitySetting::find($tipSettingId);
			if (!$gratuityetting) {
				throw new Exception('Gratuity setting not found.');
			}

			$tipAmount = $request->tip_amount + $request->tip_fees;
			$tipFees = $request->tip_fees;

			$stripeDetails = [
				'stripeTotal' => $tipAmount,
				'stripeFee' => $tipFees,
				'description' => 'Tip for Booking ID: ' . $master->id,
				'tourists' => $master->tourists,
				'BookingId' => $master->id,
			];

			// Tips are explicitly excluded from Payout Simplification — always DESTINATION,
			// regardless of the tour date, per the locked-in decision to defer that call.
			$data = StripeUtils::createStripePaymentIntentCommon($stripeDetails, $customer,  $tourPackageInfo, PaymentModeResolver::DESTINATION);
			return $data;
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			throw new GeneralCoreException($exception);
		}
	}
	/**
	 * Execute the Stripe service to create paymentintent for tip from guide tip center.
	 *
	 * @param object $request
	 *
	 * @return mixed
	 */
	public static function stripeCreatePaymentIntentForCreateTipTourGuide($request)
	{
		try {
			$validated = $request->validate([
				'tip_amount' => 'required|numeric|min:1',
			]);

			$tourOperator = TourOperator::first();
			$tourOperatorName = $tourOperator->name;
			$tipAmount = $validated['tip_amount'];
			$guideId = Auth::id();
			$guideInfo = DB::table('tour_operator_staff')
				->where('id', $guideId)
				->first();
			$feesAmount = round($tipAmount * $tourOperator->service_commission_percentage / 100, 2);
			$totalAmount = round($tipAmount + $feesAmount, 2);
			$stripeAttributes = StripeUtils::getStripeAttributesForCreateTip(
				$totalAmount,
				$feesAmount,
				$tourOperatorName,
				$tourOperator->stripe_destination_id
			);

			$stripeMetadata = StripeUtils::getStripeMetadataForCreateTip(
				$guideInfo,
				$totalAmount,
				$feesAmount,
				$tipAmount,
			);

			$paymentIntentParams = $stripeAttributes + $stripeMetadata;
			$paymentIntent = app(PaymentGatewayInterface::class)->createPayment($paymentIntentParams);
			return [
				'clientSecret' => $paymentIntent->client_secret,
			];
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Execute the Stripe service to create paymentintent for add-ons.
	 *
	 * @param object $request
	 *
	 * @return mixed
	 */
	/**
	 * Create a PaymentIntent for an addon edit that results in a net charge.
	 * The frontend computes `net_amount`; we add the processing fee on top.
	 */
	public static function stripeCreatePaymentIntentForAddonEditCharge($request)
	{
		try {
			$bookingId = $request->bookingId;
			$netAmount = round((float) $request->net_amount, 2);

			$master          = TourBooking::find($bookingId);

			// Without this, a cancelled booking's add-ons editor still reaches this point and
			// gets a real PaymentIntent confirmed against the customer's card — the JS-side
			// guard (TourBookingAddons.updateAddonForm()) and the settlement-side guard
			// (TourBookingAddonRepository::updateBookingAddons()) both run AFTER this call, too
			// late to stop the actual Stripe charge.
			if ($master && $master->status === 'Cancelled') {
				throw new Exception('This booking has been cancelled — add-ons can no longer be added or paid for.');
			}

			$customer        = Customer::where('id', $master->customer_id)
				->first(['name as customer_name', 'email as customer_email', 'phone_number as customer_phone'])
				->toArray();
			$tourPackageInfo = static::getTourPackageInformation($master->tour_slot_id);

			$tourBookingTransaction = BookingTransact::where('tour_booking_id', $bookingId)
				->where('deleted_by', 0)
				->first();

			$feePct = $tourBookingTransaction ? (float) $tourBookingTransaction->nat_fees_percentage : 0.0;

			// processAddonFinancialTransactions() rounds each addon item's fee independently and
			// sums them (unless a refund offsets part of the charge, in which case it rounds the
			// net amount once instead — see $hasChargeOffset there). Rounding the aggregate
			// net_amount here in every case can land a cent away from that per-item sum, so mirror
			// the same branch using the itemized breakdown the frontend already computed.
			$financialItems = json_decode($request->financial_items ?? '', true) ?: [];
			$toCharge = $financialItems['to_charge'] ?? [];
			$hasChargeOffset = !empty($financialItems['to_refund']);

			if ($feePct > 0 && !empty($toCharge) && !$hasChargeOffset) {
				$stripeFees = 0.0;
				foreach ($toCharge as $item) {
					// For 'upgraded' items field_price/option_price are the item's NEW total
					// price, but only the delta (charge_amount) is actually being charged —
					// scale them down proportionally first, exactly like
					// processAddonFinancialTransactions() does, otherwise the fee gets
					// computed on the full new price instead of the amount actually charged.
					$chargeAmount   = (float) ($item['charge_amount'] ?? 0);
					$rawFieldPrice  = isset($item['field_price'])  ? (float) $item['field_price']  : $chargeAmount;
					$rawOptionPrice = isset($item['option_price']) ? (float) $item['option_price'] : 0.0;
					$rawTotal       = $rawFieldPrice + $rawOptionPrice;

					if ($rawTotal > 0 && abs($rawTotal - $chargeAmount) > 0.001) {
						$scale       = $chargeAmount / $rawTotal;
						$fieldPrice  = round($rawFieldPrice * $scale, 2);
						$optionPrice = round($chargeAmount - $fieldPrice, 2);
					} else {
						$fieldPrice  = $rawFieldPrice;
						$optionPrice = $rawOptionPrice;
					}

					$stripeFees += round($fieldPrice * $feePct / 100, 2) + round($optionPrice * $feePct / 100, 2);
				}
				$stripeFees = round($stripeFees, 2);
			} else {
				$stripeFees = $tourBookingTransaction ? roundout($netAmount * $feePct / 100, 2) : 0;
			}

			$stripeTotal = round($netAmount + $stripeFees, 2);

			// If a pending remaining_payment link exists for this booking, include its amount
			// and all pending item fees in this PI so the card covers everything in one charge.
			$pendingLinkForAddon = TourBookingPaymentLink::where('order_id', $master->group_id)
				->where('status', 'Unpaid')
				->where('section', 'remaining_payment')
				->first();
			if ($pendingLinkForAddon) {
				$stripeTotal = round($stripeTotal + (float) $pendingLinkForAddon->amount, 2);
				$pendingItemDetails = DB::table('tour_booking_payment_link_items')
					->where('payment_link_id', $pendingLinkForAddon->id)
					->pluck('details');
				foreach ($pendingItemDetails as $rawDet) {
					$det = json_decode($rawDet ?? '[]', true) ?? [];
					$stripeFees += (float) ($det['platform_fees'] ?? 0);
				}

				// An upgrade of an add-on that's itself still pending on this link is charged its
				// FULL new price above (old_paid = 0), and its old pending line is dropped when the
				// edit is saved (TourBookingAddonRepository::syncPendingAddonLinkForEditedFields()).
				// Leave that old line out of the link portion here too, or the card is charged for
				// both the old and the new selection (e.g. Medium $15 still pending + Large $20).
				$upgradedFieldIds = array_values(array_filter(array_map(
					fn ($item) => (int) ($item['old_db_id'] ?? 0),
					$toCharge
				)));
				if (!empty($upgradedFieldIds)) {
					$drop        = PaymentLinkService::pendingAddonLinkDropAmounts((int) $bookingId, $upgradedFieldIds);
					$stripeTotal = round($stripeTotal - $drop['amount'], 2);
					$stripeFees  -= $drop['fees'];
				}
				$stripeFees = round($stripeFees, 2);
			} else {
				// No itemized link -- the booking's only pending balance may still be the
				// original lump-sum link (section=NULL, created at booking creation via
				// storeTourBookingPaymentLink(), or by a paymentLinkUpdate() replacement).
				// Missing it here charges the card for only the new add-on, silently leaving
				// the booking's original amount uncollected — same class of bug already fixed
				// for PaymentLinkService::upsertRemainingPaymentLink().
				$pendingLumpSumForAddon = TourBookingPaymentLink::where('order_id', $master->group_id)
					->where('status', 'Unpaid')
					->where(function ($q) {
						$q->whereNull('section')->orWhere('section', '!=', 'remaining_payment');
					})
					->first();
				if ($pendingLumpSumForAddon) {
					$lumpSumAmount = (float) $pendingLumpSumForAddon->amount;
					$stripeTotal   = round($stripeTotal + $lumpSumAmount, 2);
					// The lump-sum link's amount is a single fee-INCLUSIVE total with no stored
					// per-item breakdown (unlike the itemized branch above) -- back the fee
					// portion out algebraically at this same flat rate, matching
					// TourBookingAddonRepository::settleLumpSumLinkForCardCharge()'s identical
					// derivation for the same link. Without this, application_fee_amount only
					// ever reflected the add-on's own fee, under-collecting the platform's cut
					// on the whole lump-sum portion on every one of these combined charges.
					if ($feePct > 0) {
						$stripeFees = round($stripeFees + ($lumpSumAmount - ($lumpSumAmount / (1 + $feePct / 100))), 2);
					}
				}
			}

			$stripeDetails = [
				'stripeTotal' => $stripeTotal,
				'stripeFee'   => $stripeFees,
				'description' => sprintf(
					"Booking Id: %s\nTour Package: %s\nTour Date: %s\nTour Time: %s\nTourists: %s\nEmail: %s",
					$master->id,
					$tourPackageInfo->tour_package ?? '',
					$tourPackageInfo->date ?? '',
					$tourPackageInfo->time_from ?? '',
					$master->tourists + $master->infants,
					$customer['customer_email']
				),
				'tourists'    => $master->tourists + $master->infants,
				'BookingId'   => $master->id,
			];

			return StripeUtils::createStripePaymentIntentCommon($stripeDetails, $customer, $tourPackageInfo, app(PaymentModeResolver::class)->resolveForSingleDate($tourPackageInfo->date));
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			throw new GeneralCoreException($exception);
		}
	}

	public static function stripeCreatePaymentIntentForAddOns($request)
	{
		try {

			$paymentItems = json_decode($request->paymentItems, true);

			// Get booking details
			$amount = static::calculateTotalPaymentAmount($paymentItems);
			$master = TourBooking::find($request['bookingId']);

			// Same cancelled-booking guard as stripeCreatePaymentIntentForAddonEditCharge() above
			// — this must run before a real PaymentIntent is ever confirmed against the card.
			if ($master && $master->status === 'Cancelled') {
				throw new Exception('This booking has been cancelled — add-ons can no longer be added or paid for.');
			}
			$customer = Customer::where('id', $master->customer_id)->first(['name as customer_name', 'email as customer_email', 'phone_number as customer_phone'])->toArray();

			$tourPackageInfo = static::getTourPackageInformation($master->tour_slot_id);
			$stripeDetails = [
				'stripeTotal' => $amount['total'],
				'stripeFee'   => $amount['fee'],
				'description' => sprintf(
					"Booking Id: %s\nTour Package: %s\nTour Date: %s\nTour Time: %s\nTourists: %s\nEmail: %s",
					$master->id,
					$tourPackageInfo->tour_package ?? '',
					$tourPackageInfo->date ?? '',
					$tourPackageInfo->time_from ?? '',
					$master->tourists + $master->infants,
					$customer['customer_email']
				),
				'tourists'    => $master->tourists + $master->infants,
				'BookingId'   => $master->id,
			];

			$data = StripeUtils::createStripePaymentIntentCommon($stripeDetails, $customer,  $tourPackageInfo, app(PaymentModeResolver::class)->resolveForSingleDate($tourPackageInfo->date));
			return $data;
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Calculate the total amount to charge to the add-ons stripe
	 * 
	 * @param mixed $paymentItems
	 */
	public static function calculateTotalPaymentAmount($paymentItems, $type = 'total')
	{
		$amountTotal = 0;
		$feeTotal = 0;
		foreach ($paymentItems as $item) {
			$price = isset($item['priceInfo']['price']) ? floatval($item['priceInfo']['price']) : 0;
			$fee = isset($item['priceInfo']['fee']) ? floatval($item['priceInfo']['fee']) : 0;
			// Amount total
			$amountTotal += ($price + $fee);
			// Fee total
			$feeTotal += isset($item['priceInfo']['fee']) ? floatval($item['priceInfo']['fee']) : 0;
		}
		return $data = ['total' => $amountTotal, 'fee' => $feeTotal];
	}
}
