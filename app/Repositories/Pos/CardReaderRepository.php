<?php

namespace App\Repositories\Pos;


use App\Repositories\Repository;
use App\Services\TourOperator\TourOperatorService;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\Services\Payments\PaymentModeResolver;
use Illuminate\Support\Facades\Log;
use App\Models\Customer;
use App\Models\TourBooking;

class CardReaderRepository extends Repository
{
	public $stripeDestinationId;
	public $operator;
	public $errors = [];

	public function __construct()
	{
		$this->operator = TourOperatorService::getTourOperator();
		$this->stripeDestinationId = $this->operator->stripe_destination_id ?? null;
	}

	/**
	 * Create a connection token
	 * @param bool $connect default false
	 * @return string|null
	 */
	public function createConnectionToken($connect = false)
	{
		# if connect return token from connected account
		$opts = $connect ? ['stripe_account' => $this->stripeDestinationId] : [];
		$connectionToken = app(PaymentGatewayInterface::class)->createConnectionToken([], $opts);
		return $connectionToken->secret ?? null;
	}

	/**
	 * Return the subset of the Stripe account settings we want exposed in the
	 * UI, plus the tour-operator's configured terminal location id. Sensitive
	 * fields (capabilities, payouts schedules, tax info, external accounts,
	 * requirements, etc.) are intentionally omitted.
	 *
	 * @param bool $connect default false
	 * @return array
	 */
	public function getAccountSettings($connect = false)
	{
		# if connect return settings for the destination id
		$opts = $connect ? ['stripe_account' => $this->stripeDestinationId] : [];
		$account = app(PaymentGatewayInterface::class)->retrieveAccount($this->stripeDestinationId, $opts);

		// The Stripe account id is intentionally omitted from the response —
		// the UI masks it as "*****" and no client-side code needs the raw value.
		$settings = [
			'country'          => $account->country ?? null,
			'default_currency' => $account->default_currency ?? null,
			'business_name'    => $account->settings->dashboard->display_name
				?? $account->business_profile->name
				?? null,
			'charges_enabled'  => $account->charges_enabled ?? false,
		];

		$terminalLocationId = $this->operator->stripe_terminal_location_id ?? null;

		return [
			'settings'             => $settings,
			'terminal_location_id' => $terminalLocationId,
		];
	}

	/**
	 * Retrieve a single Stripe Terminal location by id. Used by the Account
	 * Settings view to show the location's display name and address when the
	 * admin clicks the id badge.
	 *
	 * @param string $locationId
	 * @param bool $connect default false
	 * @return array|null
	 */
	public function getLocation($locationId, $connect = false)
	{
		if (empty($locationId)) {
			$this->errors[] = 'Missing location id';
			return null;
		}

		// A tenant may only look up its own configured Terminal Location.
		// $locationId reaches here straight from client request input — without
		// this check, a tampered request could pass another tenant's location
		// id and read its display name/address (Terminal Locations currently
		// all live under one shared Stripe account, so Stripe itself won't
		// reject the lookup on ownership grounds).
		if ($locationId !== ($this->operator->stripe_terminal_location_id ?? null)) {
			$this->errors[] = 'Location does not belong to this account';
			return null;
		}

		try {
			$opts = $connect ? ['stripe_account' => $this->stripeDestinationId] : [];
			$location = app(PaymentGatewayInterface::class)->retrieveTerminalLocation($locationId, $opts);
			$address = $location->address ?? null;

			return [
				'id'           => $location->id ?? $locationId,
				'display_name' => $location->display_name ?? null,
				'livemode'     => $location->livemode ?? null,
				'address'      => [
					'line1'       => $address->line1 ?? null,
					'line2'       => $address->line2 ?? null,
					'city'        => $address->city ?? null,
					'state'       => $address->state ?? null,
					'postal_code' => $address->postal_code ?? null,
					'country'     => $address->country ?? null,
				],
			];
		} catch (\Exception $err) {
			$this->errors[] = $err->getMessage();
			return null;
		}
	}

	/**
	 * Get the pairing code from the reader under settings
	 * @param array $input
	 * @return array|null
	 */
	public function pairReader($input, $connect = false)
	{
		// $input['location_id'] reaches here straight from a client-supplied
		// hidden form field — always pair against this tenant's own
		// configured Terminal Location rather than trusting it, so a
		// tampered request can't register a physical reader against a
		// different tenant's location.
		$locationId = $this->operator->stripe_terminal_location_id ?? null;
		if (empty($locationId)) {
			$this->errors[] = 'No terminal location configured for this account';
			return null;
		}

		// Create a new reader with the pairing code
		$opts = $connect ? ['stripe_account' => $this->stripeDestinationId] : [];
		$readerParams = [
			'registration_code' => $input["pairing_code"],
			'location'          => $locationId,
			'label'             => $input["label"],
		];
		$reader = app(PaymentGatewayInterface::class)->createTerminalReader($readerParams, $opts);
		return [
			'reader_id'          => $reader->id ?? null,
			'reader_label'       => $reader->label ?? null,
			'reader_device_type' => $reader->device_type ?? null,
			'error'              => null,
		];
	}

	/**
	 * Remove a reader from the account
	 * @param string $readerId
	 * @param bool $connect default false
	 * @return \Stripe\Terminal\Reader|null
	 */
	public function unpairReader($readerId, $connect = false)
	{
		if (empty($readerId)) {
			$this->errors[] = 'Missing reader id';
			return null;
		}

		$opts = $connect ? ['stripe_account' => $this->stripeDestinationId] : [];

		// $readerId reaches here straight from client request input — confirm
		// it's actually registered to this tenant's own Terminal Location
		// before deleting it. Without this, a tampered request could delete
		// another tenant's reader (Terminal readers currently all live under
		// one shared Stripe account, so Stripe itself won't reject the delete
		// on ownership grounds — it doesn't know about "tenants").
		try {
			$reader = app(PaymentGatewayInterface::class)->retrieveTerminalReader($readerId, $opts);
		} catch (\Stripe\Exception\ApiErrorException $err) {
			$this->errors[] = $err->getMessage();
			return null;
		}

		if (($reader->location ?? null) !== ($this->operator->stripe_terminal_location_id ?? null)) {
			$this->errors[] = 'Reader does not belong to this account';
			return null;
		}

		return app(PaymentGatewayInterface::class)->deleteTerminalReader($readerId, $opts);
	}

	/**
	 * Resolve the settlement mode for a card-reader PaymentIntent from whatever booking
	 * context the caller included in metadata.
	 *
	 * Two shapes are accepted, checked in order:
	 * - metadata.dates: an array of tour-date strings, for flows charging a NOT-YET-CREATED
	 *   booking (e.g. the POS new-booking card-reader flow), where there's no booking row yet
	 *   to look the date up from — the client already has these dates selected in its own UI
	 *   state. Resolved via the mixed-cart rule (any 2027+ date makes the whole charge
	 *   platform-held).
	 * - metadata.booking_id: a single existing booking id, for flows charging an ALREADY-
	 *   CREATED booking (e.g. Make a Payment) — the date is looked up here from the real
	 *   tour_slot_times row rather than trusted from the client.
	 *
	 * Neither present (an older client build during the rollout window, or a caller with no
	 * booking context at all) falls back to DESTINATION — PaymentModeResolver's own fail-closed
	 * design already treats "no date available" this way, so this is the rollout-safe default,
	 * not a special case.
	 *
	 * @param array $input
	 * @return string One of PaymentModeResolver::DESTINATION/PLATFORM_HELD.
	 */
	private function resolveModeFromInput(array $input): string
	{
		$dates = $input['metadata']['dates'] ?? null;
		if (is_array($dates) && !empty($dates)) {
			return app(PaymentModeResolver::class)->resolve($dates);
		}

		$bookingId = $input['metadata']['booking_id'] ?? null;
		if (!empty($bookingId)) {
			$tourDate = TourBooking::with('tourSlotTime')->find($bookingId)?->tourSlotTime?->date;
			return app(PaymentModeResolver::class)->resolveForSingleDate($tourDate);
		}

		return PaymentModeResolver::DESTINATION;
	}

	/**
	 * Payment Intent Creation
	 * @param $input
	 * @return array
	 */
	public function getPaymentIntent($input, $connect = false)
	{
		// Set payment_method_types from params or default to ['card']
		$paymentMethodTypes = isset($input['payment_method_types']) ? explode(',', $input['payment_method_types']) : ['card_present'];

		$statementDescriptor = $this->operator->statement_descriptor ?? $this->operator["name"];

		// `$input['description']` arrives as `''` (not absent) whenever a caller forgets to
		// build one, which defeats `??` below — an empty string is not null, so the 'Payment'
		// fallback never kicks in and Stripe's Dashboard shows the raw PaymentIntent ID instead
		// of anything useful. Synthesize the same "Booking Id / Tour Package" format the other
		// payment flows use (see StripeUtils::buildMakePaymentIntentData()) whenever the caller
		// at least passed a booking_id in metadata, before falling back to a generic label.
		$description = $input['description'] ?? '';
		if ($description === '' && !empty($input['metadata']['booking_id'])) {
			$description = sprintf(
				"Booking Id: %s\nTour Package: %s\nPayment Method: Card Reader",
				$input['metadata']['booking_id'],
				$input['metadata']['tour_package'] ?? ''
			);
		}
		if ($description === '') {
			$description = 'Payment';
		}

		// Build the payload with defaults where not provided
		$payload = [
			'amount' => $input['amount'] ?? 0,
			'description' => $description,
			'currency' => $input['currency'] ?? currencyFormation(code: true),
			'payment_method_types' => $paymentMethodTypes,
			'statement_descriptor_suffix' => $statementDescriptor,
			'expand' => ['charges.data.transfer'],
		];

		if (!$connect) {
			# if this is not for a connected account append the destination id here
			// application_fee_amount comes exclusively from buildConnectedPaymentData()'s
			// return value below (omitted entirely for platform_held) — it must never be set
			// on the base $payload above, or a platform-held charge would silently keep it.
			$payload = [...$payload, ...app(PaymentGatewayInterface::class)->buildConnectedPaymentData(
				$this->stripeDestinationId,
				(int) ($input['fees'] ?? 0),
				mode: $this->resolveModeFromInput($input)
			)];
		}

		if (isset($input['receipt_email'])) {
			$payload['receipt_email'] = $input['receipt_email'];
		}

		// Capture method (default: 'automatic')
		$payload['capture_method'] = $input['capture_method'] ?? 'automatic';

		// Add metadata if present
		if (isset($input['metadata'])) {
			$payload['metadata'] = $input['metadata'];
			// 'dates' only ever exists to feed resolveModeFromInput() above (for a not-yet-created
			// booking with no booking_id to look a date up from) — it's never meant to reach Stripe
			// itself. Stripe metadata values must be flat strings; forwarding this array as-is fails
			// the whole PaymentIntent creation with "Metadata values must be strings, but for key
			// `dates` you passed in a value of type `hash`."
			unset($payload['metadata']['dates']);
			$payload['metadata']['tenant_id'] = tenant()->id;

			// Check if customer exists or create a new one
			$customerEmail = $input['metadata']['customer_email'] ?? null;
			if ($customerEmail) {
				$listParams = ['email' => $customerEmail];
				$customers = app(PaymentGatewayInterface::class)->listCustomers($listParams);
				if (count($customers->data) > 0) {
					$customer = $customers->data[0];
				} else {
					$newCustomerParams = [
						'email' => $customerEmail,
						'name'  => $input['metadata']['customer_name'] ?? '',
						'phone' => $input['metadata']['customer_phone'] ?? '',
					];
					$customer = app(PaymentGatewayInterface::class)->createCustomer($newCustomerParams);
				}

				$payload['customer'] = $customer->id;
				$isAffiliate = Customer::where('email', $customerEmail)->where('is_affiliate', 'Yes')->first();
				if (isset($input['save_card_on_file']) && $input['save_card_on_file']) {
					if (hasSetting('DISABLE_CARD_SAVE') == false) {
						if ($isAffiliate && hasSetting('DISABLE_AFFILIATE_CARD_SAVE') == false) {
							$payload['setup_future_usage'] = 'off_session';
						} else if (!$isAffiliate) {
							$payload['setup_future_usage'] = 'off_session';
						}
					}
				}
			}
		}

		try {
			if ($connect) {
				$connectOpts   = ['stripe_account' => $this->stripeDestinationId];
				$paymentIntent = app(PaymentGatewayInterface::class)->createPayment($payload, $connectOpts);
			} else {
				$paymentIntent = app(PaymentGatewayInterface::class)->createPayment($payload);
			}
			return [
				"clientSecret" => $paymentIntent->client_secret,
				"results" => [
					"payment_intent" => $paymentIntent,
					"message" => "payment intent"
				],
				"error" => null
			];
		} catch (\Stripe\Exception\ApiErrorException $e) {
			return [
				"clientSecret" => null,
				"results" => [
					"payment_intent" => null,
					"message" => "payment intent fail"
				],
				"error" => $e->getMessage()
			];
		}
	}
}
