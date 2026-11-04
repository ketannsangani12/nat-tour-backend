<?php

namespace App\Repositories\Invoice;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\Repositories\Repository;
use App\Utils\DateUtils;
use Exception;
use Illuminate\Support\Facades\Auth;

/**
 * Methods for working with the stripe invoice api.
 * https://stripe.com/docs/api/invoices
 * Flow create invoice -> add invoice item -> finalize -> send -> collect payment -> watch for paid webhook
 *
 */
class InvoiceRepository extends Repository
{

	/**
	 * initStripeInvoice
	 * set up a stripe invoice in connected account.
	 * @param string $uuid invoice unique id
	 * @return string|null return the new stripe invoice id if tour opt has stripe invoice enabled.
	 */
	public function initStripeInvoice(string $uuid)
	{

		$invoice = Invoice::uuid($uuid)->with(['tourOperator', 'affiliate'])->first();
		$affiliate = $invoice->affiliate;
		$tourOpt = $invoice->tourOperator;


		// if this tour operator does not have stripe invoicing enabled return false.
		if (!$invoice->tourOperator->stripe_invoice_enabled) {
			return null;
		}

		if (!$affiliate->external_invoice_enabled) {
			return null;
		}


		$customerId = $this->initConnectCustomer($invoice);
		$days = DateUtils::daysBetweenDates(now(), $invoice->due_date);
		$opts = ['stripe_account' => $tourOpt->stripe_destination_id];
		$params = [
			'description' => $affiliate->name . "  " . $invoice->invoice_number,
			'days_until_due' => $days,
			'statement_descriptor' => $tourOpt->statement_descriptor,
			'footer' => "date range: " . $invoice->from_date . " - " . $invoice->to_date,
			'payment_settings' => [
				'payment_method_types' => ['us_bank_account', 'ach_credit_transfer']
			],
			'metadata' => ['start_date' => $invoice->from_date,
				'end_date' => $invoice->to_date,
				'affiliate_customer_id' => $affiliate->id,
				'operator_customer_stripe_id' => $tourOpt->stripe_destination_id,
				'operator_id' => $tourOpt->id,
				'operator_name' => $tourOpt->name,
				'invoice_id' => $invoice->uuid,
				'operator_connect_customer_id' => $customerId,
				'tour_statement_descriptor' => $tourOpt->statement_descriptor,
				'tenant_id' => tenant()->id]];

		$stripeInvoice = $this->createStripeInvoice($customerId, $params, $opts);
		$invoice->update(['external_invoice_id' => $stripeInvoice->id,
			'external_customer_id' => $customerId,
			'external_status' => 'init']);

		return $stripeInvoice->id;
	}


	/**
	 * initInvoiceStripeInvoiceItem
	 * Add the item to the newly created invoice
	 * @param string $uuid invoice unique id.
	 * @param int $numOfBookings number of bookings attached to the invoice.
	 * @param float $amount total amount of outstanding payment
	 * @param bool $append if the invoice is in draf you can attached bookings and update the invoiceItem
	 * @return boolean
	 */
	public function initInvoiceStripeInvoiceItem(string $uuid, int $numOfBookings, float $amount = 0, bool $append = false)
	{
		$invoice = Invoice::uuid($uuid)->with(['tourOperator', 'affiliate'])->first();
		$affiliate = $invoice->affiliate;
		$tourOpt = $invoice->tourOperator;
		// if this tour operator does not have stripe invoicing enabled return false.
		if (!isset($invoice->external_invoice_id)) {
			return false;
		} else if (!$invoice->tourOperator->stripe_invoice_enabled) {
			return false;
		} else if (!$affiliate->external_invoice_enabled) {
			return false;
		}
		$params = ['amount' => floatval($amount) * 100,
			'description' => 'Booking Invoice ' . $invoice->from_date . ' - ' . $invoice->to_date,
			'metadata' => ['nat_invoice_id' => $invoice->id,
				'nat_booking_count' => $numOfBookings,
				'tenant_id' => tenant()->id]];

		$opts = ['stripe_account' => $tourOpt->stripe_destination_id];

		if ($append) {
			$stripeInvoice = $this->readStripeInvoice($invoice->external_invoice_id, [], $opts);
			$ii = $stripeInvoice["lines"]["data"][0]["invoice_item"];
			$this->updateStripeInvoiceItem($ii,
				$params,
				$opts);
		} else {
			$this->createStripeInvoiceItem($affiliate->external_customer_id,
				$invoice->external_invoice_id,
				$params,
				$opts);
		}

		return true;

	}

	/**
	 * Get invoice by stripe invoice id
	 * https://stripe.com/docs/api/invoices/retrieve
	 * @param string $incoiceId stripe invoice id in_xxxxxxx
	 * @param array $opts ['stripe_account' => '{{CONNECTED_ACCOUNT_ID}}']
	 * @example
	 * $invoice = $this->invoiceRepository->readStripeInvoice($id, [], $opts);
	 */
	public function readStripeInvoice(string $invoiceId, array $params = [], array $opts = [])
	{
		return app(PaymentGatewayInterface::class)->retrieveInvoice($invoiceId, $params, $opts);
	}

	/**
	 * set the stripe invoice to open so it can be sent.
	 * @param Invoice $invoice local invoice object
	 * @param array $params
	 * @param array $opts
	 * @returns Stripe\Invoice stripe invloice object.
	 */
	public function finalizeStripeInvoice(Invoice $invoice, $params = [], $opts = [])
	{
		$inv = $this->readStripeInvoice($invoice->external_invoice_id, $params, $opts);
		if (isset($inv) && $inv["status"] != "draft") {
			return $inv;
		}
		return app(PaymentGatewayInterface::class)->finalizeInvoice($invoice->external_invoice_id, $params, $opts);
	}


	/**
	 * Wrapper function to create a customer in the connect account.
	 * @param Invoice $invoice the invoice object
	 * @return string connected customer connect id
	 */
	public function initConnectCustomer(Invoice $invoice)
	{
		$affiliate = $invoice->affiliate;
		$tourOpt = $invoice->tourOperator;
		$opts = ['stripe_account' => $tourOpt->stripe_destination_id];
		if (empty($affiliate->external_customer_id)) {
			$params = ['name' => $affiliate->name,
				'description' => 'Affiliate Invoice Customer',
				'email' => $affiliate->email,
				'phone' => $affiliate->phone_number,
				'metadata' => ['nat_id' => $affiliate->id,
					'nat_stripe_id' => $affiliate->stripe_id,
					'tour_operator_id' => $tourOpt->id,
					'tour_operator_name' => $tourOpt->name,
					'tour_destination_id' => $tourOpt->stripe_destination_id,
					'tour_statement_descriptor' => $tourOpt->statement_descriptor,
					'tenant_id' => tenant()->id,
				]];
			$customer = $this->createConnectCustomer($params, $opts);
			$customerId = $customer->id;
			$affiliate->external_customer_id = $customerId;
			$affiliate->save();
		} else {
			$customerId = $affiliate->external_customer_id;
		}
		return $customerId;
	}

	/**
	 * Create a customer in the connect account
	 * https://stripe.com/docs/api/customers/create
	 * @param array $params name|description|email|phone|metadata
	 * @param array $opts ['stripe_account' => '{{CONNECTED_ACCOUNT_ID}}']
	 */
	public function createConnectCustomer(array $params, array $opts = [])
	{
		$customerParams = [
			'name'        => $params['name'],
			'description' => $params['description'] ?? '',
			'email'       => $params['email'],
			'phone'       => $params['phone'] ?? '',
			'metadata'    => $params['metadata'] ?? '',
		];
		return app(PaymentGatewayInterface::class)->createConnectedCustomer($customerParams, $opts);
	}

	/**
	 * return a customer from main account or customer from connect account.
	 * https://stripe.com/docs/api/customers/retrieve
	 * @param string $customerId
	 * @param array $params
	 * @param array $opts
	 */
	public function getConnectCustomer(string $customerId, array $params = [], array $opts = [])
	{
		return app(PaymentGatewayInterface::class)->retrieveConnectedCustomer($customerId, $params, $opts);
	}

	/**
	 * update a customer in the connected account
	 * https://stripe.com/docs/api/customers/update
	 * @param string $customerId
	 * @param array $params
	 * @param array $opts
	 */
	public function updateConnectCustomer(string $customerId, array $params = [], array $opts = [])
	{
		app(PaymentGatewayInterface::class)->updateConnectedCustomer($customerId, $params, $opts);
	}

	/**
	 * Check if the external status of an invoice is in a draft state and can be updated
	 * @param string $status invlice external status field
	 * @return boolean
	 */
	public function isInDraftState(string $status)
	{
		$states = ["init", "draft", "editable"];
		return (in_array($status, $states));
	}

	/**
	 * check if the current invoice is connected to a stripe invoice
	 * @param Invoice $invoice invoice object found by uuid with tourOpt and aff. props
	 * @return boolean
	 */
	public function hasStripeInvoice(Invoice $invoice)
	{
		return ($invoice->tourOperator->stripe_invoice_enabled
			&& !empty($invoice->external_invoice_id)) ? true : false;

	}

	/**
	 * public createStipeInvoice
	 * Create a stripe invoice with meta data Note: the use of the pending_invoice_items_behavior
	 * this is an important flag because it sets the new inovice to pending...
	 * https://stripe.com/docs/api/invoices/create
	 * @param int $customerId
	 * @param array $params metadata|description|days_until_due|statement_descriptor|fromDate|toDate
	 * @param array $opts ['stripe_account' => '{{CONNECTED_ACCOUNT_ID}}']
	 */
	public function createStripeInvoice($customerId, $params = [], $opts = [])
	{
		//set the default params passed to the invoice creation method.
		$prms = [
			'pending_invoice_items_behavior' => 'exclude',
			'customer' => $customerId,
			'metadata' => $params['metadata'],
			'description' => $params['description'],
			'days_until_due' => intval($params['days_until_due']),
			'statement_descriptor' => $params['statement_descriptor'] ?? 'Tour Booking Invoice',
			'footer' => $params['footer'] ?? '',
			'collection_method' => 'send_invoice'];

		// if payment methods set
		if (isset($params['payment_settings'])) {
			$prms['payment_settings'] = $params['payment_settings'];
		}
		// if destination is set update the params array to include transfer_data.
		if (isset($params['destination'])) {
			$prms['transfer_data'] = ['destination' => $params['destination']];
		}
		// if is set on_behalf_of
		if (isset($params['on_behalf_of'])) {
			$prms['on_behalf_of'] = $params['on_behalf_of'];
		}

		return app(PaymentGatewayInterface::class)->createInvoice($prms, $opts);
	}

	public function udpateStripeInvoice($invoiceId, $params = [], $opts = [])
	{
		return app(PaymentGatewayInterface::class)->updateInvoice($invoiceId, $params, $opts);
	}

	public function voidStripeInvoice($invoiceId, $params = [], $opts = [])
	{
		return app(PaymentGatewayInterface::class)->voidInvoice($invoiceId, $params, $opts);
	}

	public function deleteStripeInvoice($invoiceId, $params = [], $opts = [])
	{
		try {
			$inv = $this->readStripeInvoice($invoiceId, [], $opts);
			if ($inv['status'] == "draft") {
				$res = app(PaymentGatewayInterface::class)->deleteInvoice($invoiceId, $params, $opts);
			} else {
				$res = $this->voidStripeInvoice($invoiceId, [], $opts);
			}

		} catch (Exception $err) {
			$res = null;
		}
		return $res;
	}


	/**
	 * Add a item to a pending invoice.
	 * https://stripe.com/docs/api/invoiceitems/update
	 * @param string $customerId
	 * @param string $invoiceId
	 * @param array $params amount|description|metadata|quantity
	 * @param array $opts ['stripe_account' => '{{CONNECTED_ACCOUNT_ID}}']
	 */
	public function createStripeInvoiceItem($customerId, $invoiceId, $params, $opts = [])
	{
		$itemParams = [
			'customer'     => $customerId,
			'invoice'      => $invoiceId,
			'amount'       => $params['amount'],
			'description'  => $params['description'],
			'metadata'     => $params['metadata'],
			'tax_behavior' => $params['tax_behavior'] ?? 'exclusive',
		];
		return app(PaymentGatewayInterface::class)->createInvoiceItem($itemParams, $opts);
	}


	/**
	 * Update an invoice item by invoice item id
	 * https://stripe.com/docs/api/invoiceitems/update
	 * @param string $invoiceItemId
	 * @param array $params
	 * @param array $opts
	 */
	public function updateStripeInvoiceItem($invoiceItemId, $params, $opts)
	{
		return app(PaymentGatewayInterface::class)->updateInvoiceItem($invoiceItemId, $params, $opts);
	}

	/**
	 * read single invoice item by invoice item id
	 * https://stripe.com/docs/api/invoiceitems/retrieve
	 * @param string $invoiceItemId
	 */
	public function readStripeInvoiceItem($invoiceItemId, $opts)
	{
		return app(PaymentGatewayInterface::class)->retrieveInvoiceItem($invoiceItemId, $opts);
	}

	/**
	 * delete single invoice item by invoice item id
	 * https://stripe.com/docs/api/invoiceitems/delete
	 * @param string $invoiceItemId
	 */
	public function deleteStripeInvoiceItem($invoiceItemId, $opts)
	{
		return app(PaymentGatewayInterface::class)->deleteInvoiceItem($invoiceItemId, $opts);
	}

	/**
	 * public listStripeInvoicesByDateRange
	 * Get a list of invoices by customer id start and end date
	 * @param int $customerId
	 * @param array $params fromDate|toDate|customerId|account
	 * @param array $opts
	 * @return array|null
	 */
	public function listStripeInvoicesByDateRange(array $params = [])
	{
		// append time 00:00:00 : 23:59:59 to string date
		$dates = DateUtils::startEndDateDefaults($params['fromDate'], $params['toDate']);
		$start = strtotime($dates['end_date']);
		$end = strtotime($dates['end_date']);

		//create the query
		$query = "period_start gte " . intval($start);
		$query .= " AND period_end lte " . intval($end);
		$query .= " AND metadata['customer_id'] : '" . $params['customerId'] . "'";

		$searchParams = ['query' => $query];
		$searchOpts   = ['stripe_account' => $params['account']];
		$invoices = app(PaymentGatewayInterface::class)->searchInvoices($searchParams, $searchOpts);
		$invoicesAll = [];
		foreach ($invoices->autoPagingIterator() as $invoice) {
			$invoicesAll[] = $invoice;
		}
		return $invoicesAll;
	}


	function getDeletedInvoicesByDate($from, $to)
	{
		$sql = "select `invoices`.*,  `customers`.`name` as `customer_name`
                   from `invoices`
                   inner join `customers` on `customers`.`id` = `invoices`.`customer_id`
                   where (`from_date` between '$from' and '$to' or `to_date` between '$from' and '$to')
                   and `invoices`.`deleted_by` is not null";
		return DB::select($sql);
	}


	/**
	 * return json used to display a table or graph of invoice by rate for types.
	 * @param Request $request
	 *
	 * from_date: 2023-06-01
	 * to_date: 2023-06-30
	 * customerId: 0
	 * tourOpId: 1
	 *
	 * @return array
	 */
	public function rateforGraph(Request $request)
	{
		$results = [];
		$ids = [];
		$dates = DateUtils::startEndDateDefaults($request->from_date, $request->to_date);
		$tourOperatorId = (userType() == 'Tour Operator') ? Auth::user()->tour_operator_id : intval($request->tourOpId);

		$sql = "select distinct  `invoice_bookings`.`tour_booking_id` as `booking_id`
                from `invoices`
                left join `invoice_bookings` on `invoice_bookings`.`invoice_id`=`invoices`.`id`
                inner join `tour_bookings_master` on `tour_bookings_master`.`id`=`invoice_bookings`.`tour_booking_id`
                inner join `tour_packages` on `tour_packages`.`id`=`tour_bookings_master`.`tour_package_id`
                left join `customers` as `cus` on `cus`.`id` = `tour_bookings_master`.`customer_id`
                where(`from_date`between '" . $dates['start_date'] . "' and '" . $dates['end_date'] . "'
                or `to_date` between '" . $dates['start_date'] . "' and '" . $dates['end_date'] . "' )
                and `invoices`.`deleted_at` is null
                and `invoices`.`tour_operator_id` = " . intval($tourOperatorId);
		if (!empty($request->tourPackageId)) {
			$sql .= " and `tour_bookings_master`.`tour_package_id` = " . intval($request->tourPackageId);
		}
		if (!empty($request->customerId)) {
			$sql .= " and `cus`.`id` = " . intval($request->customerId);
		}

		$booking_ids = DB::select($sql);
		if (empty($booking_ids)) {
			return [];
		}
		foreach ($booking_ids as $id) {
			array_push($ids, $id->booking_id);
		}
		$sql = "SELECT tbt.* , tpr.rate_for
                FROM tour_bookings_transact AS tbt
                INNER join tour_package_rates AS tpr ON tpr.id = tbt.tour_package_rate_id
                WHERE tbt.tour_booking_id IN (" . implode(",", $ids) . ")";

		$rates = DB::select($sql);
		//if the aggregate flag is set nest the totals inside the rate for indices
		if ($request->aggregate) {
			foreach ($rates as $rate) {
				if (!isset($results[$rate->rate_for])) {
					$results[$rate->rate_for] = ["total_amount" => 0, "tourists" => 0, "total_rate" => 0, "total_permit_fee" => 0, "rate" => $rate->rate];
				}
				$results[$rate->rate_for]["total_amount"] += $rate->total;
				$results[$rate->rate_for]["total_rate"] += $rate->rate;
				$results[$rate->rate_for]["total_permit_fee"] += $rate->permit_fee;
				$results[$rate->rate_for]["tourists"] += $rate->tourists;
			}
		} else {
			//else return the raw result set
			$results = $rates;
		}
		return $results;

	}


	/**
	 * Return a list of bookings by status.
	 * @param Request $request get request object.
	 */
	public function sumByStatus(Request $request)
	{
		$query = Invoice::where(function ($query) use ($request) {
			return $query->whereBetween('from_date', [$request->from_date, $request->to_date])
				->orWhereBetween('to_date', [$request->from_date, $request->to_date]);
		})->whereNull('invoices.deleted_by')
			->select(['status', 'external_status',
				DB::raw('SUM(amount) AS amount'),
			])
			->groupBy('status', 'external_status');

		if (session('userType') == 'Tour Operator') {
			$query->where('invoices.tour_operator_id', Auth::user()->tour_operator_id);
		} else {
			$query->where('invoices.tour_operator_id', $request->tourOpId);
		}
		if ($request->customerId) {
			$query->where('invoices.customer_id', $request->customerId);
		}
		$bookings = $query->get();
		return $bookings;
	}


}
