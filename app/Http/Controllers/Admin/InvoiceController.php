<?php

namespace App\Http\Controllers\Admin;

use Exception;
use App\Repositories\Invoice\InvoiceRepository;
use App\Constants\Constants;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateInvoicePdf;
use App\Mail\SendInvoiceToAffiliate;
use App\Services\Invoice\InvoiceDataService;
use App\Services\Invoice\InvoicePdfService;
use App\Models\Invoice;
use App\Models\InvoiceBooking;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Yajra\DataTables\DataTables;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Customer;
use Illuminate\Support\Facades\Auth;

class InvoiceController extends Controller
{

	protected $invoiceRepository;

	public $msgExists = 'The existing invoice contains a date range that cannot be generated.';

	public $msgAppend = 'Invoice Was Updated With New Bookings';

	public $msgOk = 'Invoice Generated Successfully.';

	/**
	 * int repository
	 */
	public function __construct(InvoiceRepository $invRepo)
	{
		parent::__construct();

		$this->invoiceRepository = $invRepo;
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return View
	 */
	public function index(): View
	{
		$vars = ['page_title' => 'Invoices'];

		return view('users.admin.invoice.index', $vars);
	}

	/**
	 * Json response for DataTable.
	 * @param \Yajra\DataTables\DataTables $dataTables
	 * @return JsonResponse
	 */
	public function indexAHR(DataTables $dataTables, String $tourOpId = null): JsonResponse
	{

		$query = Invoice::select('invoices.*', 'customers.name as customer_name')
			->join('customers', 'customers.id', '=', 'invoices.customer_id')
			->get();

		return $dataTables->of($query)
			->addIndexColumn()
			->editColumn('from_date', function ($invoice) {
				return date('m/d/Y', strtotime($invoice->from_date));
			})
			->editColumn('to_date', function ($invoice) {
				return date('m/d/Y', strtotime($invoice->to_date));
			})
			->editColumn('due_date', function ($invoice) {
				return date('m/d/Y', strtotime($invoice->due_date));
			})
			->editColumn('status', function ($invoice) {
				$badgeClasses = [
					'draft'     => 'bg-gray-200 text-gray-700',
					'sent'      => 'bg-blue-200 text-blue-700',
					'viewed'    => 'bg-indigo-200 text-indigo-700',
					'approved'  => 'bg-green-200 text-green-700',
					'partial'   => 'bg-yellow-200 text-yellow-700',
					'paid'      => 'bg-emerald-200 text-emerald-700',
					'overdue'   => 'bg-red-200 text-red-700',
					'unpaid'    => 'bg-orange-200 text-orange-700',
					'cancelled' => 'bg-gray-300 text-gray-800 line-through',
				];
				return '<span style="text-transform: capitalize;" class="px-2 py-1 text-xs font-semibold rounded ' . $badgeClasses[$invoice->status] . '">' . $invoice->status . '</span>'; //'<span style="text-transform: capitalize;" class="status-' . $invoice->status . '">' . $invoice->status . '</span>';
			})
			->editColumn('action', function ($invoice) {

				return $this->getCustomButton('View', route(routePrefix() . '.invoice.show', ['uuid' => $invoice->uuid]), 'btn btn-repeat btn-sm mr-2 btn-info-small', 'far fa-eye')
					. '<a onclick="return changeStatus(' . $invoice->id . ')" class="btn btn-edit btn-sm mr-2 btn-primary-small"><i class="far fa-edit mr-1"></i> Update</a>'
					. $this->getDeleteButton(route(routePrefix() . '.invoice.destroy', ['invoice' => $invoice->uuid]));
			})
			->rawColumns(['action', 'status'])
			->make(true);
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return \Illuminate\Http\RedirectResponse
	 */
	public function store(Request $request): RedirectResponse
	{
		DB::beginTransaction();
		try {
			if ($this->getInvoiceByDate($request)) {
				return redirect()
					->back()
					->withErrors(['errors' => $this->msgExists]);
			}

			$invoice = Invoice::create($request->all());
			$this->mapBookingsWithInvoice($invoice->id, $request);
			$this->savePdf($invoice->uuid);
			DB::commit();
			$routePrefix = routePrefix();

			return  redirect()
				->route($routePrefix . '.invoice.index')
				->with('message', 'Invoice Generated.');
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			return redirect()
				->back()
				->withErrors(['errors' => $exception->getMessage()]);
		}
	}

	/**
	 * Json response for report view.
	 * @param $tourOpId tour operator id if admin.
	 * @param  $customerId affiliate id
	 * @return JsonResponse
	 */
	public function list(Request $request, $customerId): JsonResponse
	{
		try {
			$departmentPackage = departmentPackage();

			$query = Invoice::where(function ($query) use ($request) {
				return $query->whereBetween('from_date', [$request->from_date, $request->to_date])
					->orWhereBetween('to_date', [$request->from_date, $request->to_date]);
			});

			$query->select('invoices.*', 'customers.name as customer_name', 'tour_bookings_master.tour_package_id');
			$query->join('customers', 'customers.id', '=', 'invoices.customer_id');
			$query->join('invoice_bookings', 'invoice_bookings.invoice_id', '=', 'invoices.id');
			$query->join('tour_bookings_master', 'tour_bookings_master.id', '=', 'invoice_bookings.tour_booking_id');
			$query->join('tour_packages', 'tour_packages.id', '=', 'tour_bookings_master.tour_package_id');
			$query->when($departmentPackage, function ($query) use ($departmentPackage) {
				return $query->whereIn('tour_packages.id', $departmentPackage);
			});
			$query->select(
				'invoices.*',
				'customers.name as customer_name',
				'tour_bookings_master.tour_package_id',
				'tour_packages.name as tour_package_name'
			);

			if ($request->package_id) {
				$query->where('tour_bookings_master.tour_package_id', $request->package_id);
			}

			// if customer id filter by customer id
			if ($customerId != "0" || $customerId != 0) {
				$query->where('invoices.customer_id', $customerId);
			}

			//if the user whats to view deleted invoices
			if ($request->displayVoided) {
				$deletedInvoiceList = $this->invoiceRepository->getDeletedInvoicesByDate($request->from_date, $request->to_date);
			} else {
				$deletedInvoiceList = [];
			}

			$response = $query->distinct('invoices.id')->get();

			return response()->json(['results' => $response, "deleted" => $deletedInvoiceList, "error" => null], 200);
		} catch (Exception $err) {
			return response()->json(["results" => [], "deleted" => [], "error" => $err->getMessage()]);
		}
	}

	/**
	 * Store a newly created invoice from async form with Stripe Invoice.
	 * @param Request $request post request object.
	 * @return JsonResponse invoice uuid / id
	 */
	public function storeWithStripeInvoice(Request $request): JsonResponse
	{
		DB::beginTransaction();
		try {
			//check if a invoice exists for the given date-range.
			$invoice = $this->getInvoiceByDate($request);
			//append or create
			$append = false;
			$paidInvoice = false;
			if ($invoice) {
				// if invoice is in draft add the billiable bookings.
				if ($invoice->status == 'draft') {
					$append = true;
				} else if ($invoice->status == 'paid') {
					$paidInvoice = true;
				} else {
					//if the status is not draft then return error message.
					return response()->json([
						"results" => [],
						"success" => null,
						"error" => $this->msgExists
					]);
				}
			}

			//if not append create the new invoice
			if (!$append || $paidInvoice) {
				// create invoice in local system from post params
				$invoice = Invoice::create($request->all());

				// craete the stripe invoice
				$this->invoiceRepository->initStripeInvoice($invoice->uuid);
			}

			// map the bookings ids to the invoice
			$mapData = $this->mapBookingsWithInvoice($invoice->id, $request, $append, $paidInvoice);
			// get the invoice amt
			$invoiceAmount = $this->invoiceSumByBookings(array_column($mapData["ids"], 'bookingid'));
			// create or update the stripe invoice item
			$this->invoiceRepository->initInvoiceStripeInvoiceItem($invoice->uuid, $mapData['count'], $invoiceAmount, $append);

			//if append update the existing invoice with the new total
			$invoice->update(['amount' => $invoiceAmount, 'due_date' => $request->due_date]);

			// save or overwrite pdf on s3
			$this->savePdf($invoice->uuid);

			DB::commit();
			return response()->json([
				"results" => ["uuid" => $invoice->uuid, "id" => $invoice->id],
				"success" => ($append) ? $this->msgAppend : $this->msgOk,
				"error" => null
			]);
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();

			return response()->json([
				"results" => [],
				"success" => null,
				"file" => $exception->getFile(),
				"line" => $exception->getLine(),
				"error" => $exception->getMessage()
			]);
		}
	}

	/**
	 * Check if an invoice exists by date time
	 * @param Request $request
	 * @return \App\Models\Invoice
	 */
	private function getInvoiceByDate($request)
	{
		return Invoice::where(function ($query) use ($request) {
			return $query->whereBetween('from_date', [$request->from_date, $request->to_date])
				->orWhereBetween('to_date', [$request->from_date, $request->to_date]);
		})
			->where('customer_id', $request->customer_id)
			->first();
	}

	/**
	 * Check if an invoice exists for the given date range and customer (API endpoint)
	 * @param Request $request
	 * @return JsonResponse
	 */
	public function checkInvoiceExists(Request $request): JsonResponse
	{
		try {
			$invoice = $this->getInvoiceByDate($request);

			if ($invoice) {
				return response()->json([
					'exists' => true,
					'invoice' => [
						'id' => $invoice->id,
						'uuid' => $invoice->uuid,
						'status' => $invoice->status,
						'amount' => $invoice->amount,
						'from_date' => $invoice->from_date,
						'to_date' => $invoice->to_date,
					],
					'message' => $this->msgExists
				]);
			}

			return response()->json([
				'exists' => false,
				'invoice' => null,
				'message' => null
			]);
		} catch (Exception $exception) {
			Log::error($exception);
			return response()->json([
				'exists' => false,
				'error' => $exception->getMessage()
			], 500);
		}
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  string  $uuid
	 */
	public function show(string $uuid, string $layout = "default")
	{
		try {
			$invoice = Invoice::uuid($uuid)
				->with(['tourOperator', 'affiliate'])
				->first();

			$bookings = $this->bookings($invoice);
			
			if ($bookings == '[]') {
				$return = redirect()
					->route(routePrefix() . '.invoice.index')
					->withErrors('Data not found for the invoice.');

				return $return;
			}
			$vars = $this->invoiceStub($bookings);
			$vars['invoice'] = $invoice;
			$vars['page_title'] = 'Show Invoice';
			$vars['layout'] = ($layout == "blank") ? "layouts.blank" : "layouts.default-2";

			// Tell the view whether a generated PDF already exists so it can
			// prompt the user to create one. status() swallows S3 errors and
			// reports not-ready, so a storage hiccup just shows the prompt.
			$vars['pdfReady'] = InvoicePdfService::status(tenancy()->tenant->id, $invoice->invoice_number)['ready'];

			return view('users.admin.invoice.show', $vars);
		} catch (Exception $exception) {
			Log::error($exception);
			return redirect()
				->back()
				->withErrors(['errors' => $exception->getMessage()]);
		}
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param Request $request
	 * @param int $id
	 * @return mixed
	 */
	public function update(Request $request, int $id)
	{
		DB::beginTransaction();
		try {
			$invoice = Invoice::find($id);
			$invoice->status = $request->status;
			$invoice->save();
			DB::commit();

			return redirect()
				->back()
				->with('message', 'Invoice Updated.');
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			return redirect()
				->back()
				->withErrors(['errors' => $exception->getMessage()]);
		}
	}

	/**
	 * Post function enable stripe invoice for affiliate
	 */
	public function enableStripeInvoiceForAffiliate(Request $request)
	{
		$affiliate = Customer::find($request->id);
		$affiliate->external_invoice_enabled = ($request->enabled == 'false') ? 0 : 1;
		$affiliate->save();

		return response()->json(["id" => $request->id, "state" => $request->enabled]);
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param string $uuid
	 * @return JsonResponse
	 */
	public function destroy(string $uuid): JsonResponse
	{
		DB::beginTransaction();
		try {
			$invoice = Invoice::uuid($uuid)->first();
			$invoice_id = $invoice->id;
			$invoiceBookings = InvoiceBooking::where('invoice_id', $invoice_id)
				->select('id')
				->get()
				->toArray();

			$invoiceBookingsArray = array();
			if (!empty($invoiceBookings)) {
				foreach ($invoiceBookings as $invoiceBooking) {
					$invoiceBookingsArray[] = $invoiceBooking['id'];
				}

				InvoiceBooking::whereIn('id', $invoiceBookingsArray)->delete();
			}
			$invoice->deleted_by = Auth::id();
			$invoice->save();

			// if the invoice has an external stripe invoice id delete that as well.
			if (!empty($invoice->external_invoice_id)) {
				$opts = ['stripe_account' => $invoice->tourOperator->stripe_destination_id];
				$this->invoiceRepository->deleteStripeInvoice($invoice->external_invoice_id, [], $opts);
			}
			$invoice->delete();
			DB::commit();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Print a invoice created resource in storage.
	 *
	 * @param string $uuid
	 */
	public function printInfo(string $uuid)
	{
		try {
			$invoice = Invoice::uuid($uuid)
				->with(['tourOperator', 'affiliate'])
				->first();

			$bookings = $this->bookings($invoice);
			$vars = $this->invoiceStub($bookings);

			$vars['invoice'] = $invoice;
			$vars['page_title'] = 'Print Invoice';
			if ($invoice) {
				$path = "pdf" . DIRECTORY_SEPARATOR . tenancy()->tenant->id  . DIRECTORY_SEPARATOR . $invoice->invoice_number . ".pdf";

				if (Storage::disk('s3')->exists($path)) {
					$pdfInvoiceFile = Storage::disk('s3')->url($path);
					return redirect()->to($pdfInvoiceFile);
				}

				return redirect()
					->back()
					->withErrors(['errors' => 'The PDF creation process is still running in the background, please check back later.']);
			}

			return redirect()
				->back()
				->withErrors(['errors' => 'Unable to find the invoice.']);
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			return redirect()
				->back()
				->withErrors(['errors' => $exception->getMessage()]);
		}
	}

	/**
	 * Send a invoice mail to affiliate created resource in storage.
	 *
	 * @param Request $request
	 * @param string $uuid
	 * @return \Illuminate\Http\RedirectResponse
	 */
	public function sendEmail(Request $request, string $uuid): RedirectResponse
	{
		DB::beginTransaction();
		try {
			$invoice = Invoice::uuid($uuid)
				->with(['tourOperator', 'affiliate'])
				->first();

			if ($invoice) {
				$invoice->status = 'sent';
				$invoice->email_sent = $invoice->email_sent + Constants::STATUS_ONE;

				// if the invoice has an exteranl stripe invoice link get the hosted url
				// for payment and inject it into the email
				if ($this->invoiceRepository->hasStripeInvoice($invoice)) {
					$opts = [
						'stripe_account' => $invoice->tourOperator->stripe_destination_id
					];

					if ($this->invoiceRepository->isInDraftState($invoice->external_status)) {
						$stripeInvoice = $this->invoiceRepository->finalizeStripeInvoice($invoice, [], $opts);
						$invoice->external_status = $stripeInvoice["status"];
					} else {
						$stripeInvoice = $this->invoiceRepository->readStripeInvoice($invoice->external_invoice_id, [], $opts);
					}

					$invoice->external_payment_link = $stripeInvoice["hosted_invoice_url"];
				}

				$invoice->save();
				DB::commit();

				$to = explode(", ", $request->email);
				Mail::to($to)->send(
					(new SendInvoiceToAffiliate($invoice))->afterCommit()
				);

				return redirect()
					->back()
					->with('message', 'Sent invoice mail to affiliate.');
			}

			return redirect()
				->back()
				->withErrors(['errors' => 'Unable to find the invoice.']);
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			return redirect()
				->back()
				->withErrors(['errors' => $exception->getMessage()]);
		}
	}

	/**
	 * Reference the invoice.
	 *
	 * @param Request $request
	 * @param string $uuid
	 * @return \Illuminate\Http\RedirectResponse
	 */
	public function referenceInvoice(Request $request, string $uuid): RedirectResponse
	{
		DB::beginTransaction();
		try {
			$invoice = Invoice::uuid($uuid)
				->with(['tourOperator', 'affiliate'])
				->first();

			if ($invoice) {
				if ($request->name) {
					$invoice->reference_invoice = trim($request->value);
				} else {
					$invoice->reference_invoice = $request->reference_invoice;
				}
				$invoice->save();
				DB::commit();

				return redirect()
					->back()
					->with('message', 'Reference invoice has been updated.');
			}

			return redirect()
				->back()
				->withErrors(['errors' => 'Unable to add reference invoice number.']);
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			return redirect()
				->back()
				->withErrors(['errors' => $exception->getMessage()]);
		}
	}

	/**
	 * Query to get the bookings from specific time frame.
	 *
	 * @param object|null $invoice
	 * @return string
	 */
	public function bookings($invoice = null, $ids = false): string
	{
		return InvoiceDataService::bookings($invoice, $ids);
	}

	/**
	 * Get total by invoice type for given invoice ids array
	 *
	 */
	public function invoiceBookingWhereIdArry(Request $request)
	{
		$ids = explode("|", $request->ids);
		$bookings = $this->bookings(null, $ids);

		return response()->json(['results' => $bookings], 200);
	}

	/**
	 * return the sum of a given invoice set by status
	 * used for displaying graphs on the invoice report
	 */
	public function invoiceSumByStatus(Request $request)
	{
		if ($request->ratefor) {
			return $this->invoiceRepository->rateforGraph($request);
		} else {
			return $this->invoiceRepository->sumByStatus($request);
		}
	}

	/**
	 *   $request = new \Illuminate\Http\Request;
	 *   $request->merge(["tourOperatorId"=>1, "fromDate"=>"2023-06-01",  "toDate"=>"2023-06-30", "aggregate"=>1 ]);
	 *   $result = $this->repo->rateforGraph($request);
	 */

	/**
	 * Return the sum of an invoice by the sum(amount) of the bookings
	 * @return float
	 */
	public function invoiceSumByBookings($ids)
	{
		$sql = DB::table('tour_booking_history AS tbh')
			->whereIn('tbh.tour_booking_id', $ids)
			->whereIn('tbh.operation', ['Payment', 'Refund', 'Cancelled'])
			->select([
				DB::raw("SUM(case `tbh`.`operation` when 'Payment' then `tbh`.`ticket_cost` else 0 end) AS total"),
				DB::raw('SUM(case when (`tbh`.`operation` = "Refund" 
                              AND JSON_UNQUOTE(JSON_EXTRACT(`tbh`.`details`, \'$.payment_type\')) != "")  
                            then `tbh`.`ticket_cost` 
                        WHEN `tbh`.`operation` = "Cancelled" 
                            then `tbh`.`ticket_cost` 
                        else 0  end) AS refund'),
				//DB::raw("SUM(case `tbh`.`operation` when 'Refund' then `tbh`.`ticket_cost` when 'Cancelled' then `tbh`.`ticket_cost` else 0 end) AS refund"),
			]);

		$bookings = $sql->get()->first();
		$amount = floatval($bookings->total) - floatval($bookings->refund);

		return floatval($amount);
	}

	/**
	 * Stub the data booking data to display in invoice section.
	 *
	 * @param string $invoice
	 * @return array
	 */
	public function invoiceStub(string $invoice): array
	{
		return InvoiceDataService::invoiceStub($invoice);
	}

	/**
	 * Store the PDF from the view of html.
	 *
	 * @param string $uuid
	 * @return mixed
	 */
	public function savePdf(string $uuid, int $reload = 0)
	{
		$request = request();
		$wantsJson = $request->ajax() || $request->wantsJson();

		try {
			$invoice = Invoice::uuid($uuid)->first();

			if (!$invoice) {
				if ($wantsJson) {
					return response()->json([
						'success' => false,
						'message' => 'Unable to find the invoice.',
					], 404);
				}

				return redirect()->back()->withErrors(['errors' => 'Unable to find the invoice.']);
			}

			// Capture the current PDF timestamp (if any) up front so the client
			// can tell a freshly generated PDF apart from a stale one.
			$before = InvoicePdfService::status(tenancy()->tenant->id, $invoice->invoice_number);

			dispatch(new GenerateInvoicePdf($uuid));

			if ($wantsJson) {
				return response()->json([
					'success' => true,
					'message' => 'Generating the invoice PDF. Large invoices can take up to a minute.',
					'status_url' => route(routePrefix() . '.invoice.pdf-status', ['uuid' => $invoice->uuid]),
					'previous_last_modified' => $before['last_modified'],
				]);
			}

			if ($reload) {
				return redirect()
					->back()
					->with('message', 'The PDF creation process has been started in background, please check back later.');
			}
		} catch (Exception $exception) {
			Log::error($exception);

			if ($wantsJson) {
				return response()->json([
					'success' => false,
					'message' => 'Could not start PDF generation. Please try again.',
				], 500);
			}
		}
	}

	/**
	 * Report whether an invoice PDF has finished generating.
	 *
	 * Polled by the invoice view after generation is kicked off so the user
	 * gets a real "ready / download" signal instead of a blind "check back
	 * later" message.
	 *
	 * @param string $uuid
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function pdfStatus(string $uuid): JsonResponse
	{
		$invoice = Invoice::uuid($uuid)->first();

		if (!$invoice) {
			return response()->json([
				'ready' => false,
				'url' => null,
				'last_modified' => null,
				'error' => 'Unable to find the invoice.',
			], 404);
		}

		return response()->json(
			InvoicePdfService::status(tenancy()->tenant->id, $invoice->invoice_number)
		);
	}

	/**
	 * Map bookings id with invoice in table
	 *
	 * @param int $invoice_id
	 * @param object $request
	 * @return array $count $ids number of bookings created;
	 */
	public function mapBookingsWithInvoice(int $invoice_id, object $request, $append = false, $paidInvoice = false): array
	{
		//set base vars
		$fromDate = $request->from_date . " 00:00:00";
		$toDate = $request->to_date . " 23:59:59";
		$tourPackageId = $request->tour_package;
		$tourOperatorId = $request->tour_operator_id;
		$customerId = $request->customer_id;
		$departmentPackage = departmentPackage();

		//select all bookings id within range.
		$qry = DB::table('tour_bookings_master AS tbm')
			->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
			->join('tour_operators as to', 'to.id', '=', 'tp.tour_operator_id')
			->join('customers as c', 'c.id', '=', 'tbm.customer_id')
			->join('tour_bookings_transact AS tbt', 'tbt.tour_booking_id', 'tbm.id')
			->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
			->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
			->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
			->leftJoin('transactions as t', 't.tour_booking_id', '=', 'tbm.id')
			->where('t.via', 'Invoiced')
			->where('t.status', 'Completed')
			->where('tbm.status', 'Booked')
			->where('c.is_affiliate', 'Yes')
			->whereBetween('tsd.date', [$fromDate, $toDate])
			->when($departmentPackage, function ($query) use ($departmentPackage) {
				return $query->whereIn('tp.id', $departmentPackage);
			})
			->select([
				'tbm.id as bookingid'
			])
			->orderBy('tsd.date', 'DESC');

		// is tour package set
		if ($tourPackageId && $tourPackageId !== 0) {
			$qry = $qry->where('tbm.tour_package_id', '=', $tourPackageId);
		}

		//if user is tour operator force opt id
		if (session('userType') == 'Tour Operator') {
			$qry = $qry->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
		} else {
			$qry = $qry->where('tp.tour_operator_id', '=', $tourOperatorId);
		}

		// if customer id append to where clause
		if ($customerId != 0) {
			$qry = $qry->where('c.id', '=', $customerId);
		}

		//get bookings
		$qry = $qry->get();

		//assign the ids to standard array
		$bookingIDs = json_decode($qry, true);
		// make unique list of bookings
		$bookingIDs = array_map("unserialize", array_unique(array_map("serialize", $bookingIDs)));
		$ids = $bookingIDs;
		//only append ids not already in invoice booking table.
		if ($append || $paidInvoice) {
			$in = [];
			$out = [];
			foreach ($bookingIDs as $id) {
				$in[] = $id['bookingid'];
			}

			$sql = DB::table('invoice_bookings')->whereIn('tour_booking_id', $in)->select('tour_booking_id');
			$existing = json_decode($sql->get());
			foreach ($existing as $eid) {
				$out[] = $eid->tour_booking_id;
			}

			$diff =  array_diff($in, $out);
			$bookingIDs = [];
			foreach ($diff as $id) {
				$bookingIDs[]['bookingid'] = $id;
			}
		}

		//if booking ids
		if (!empty($bookingIDs)) {
			// create new invoice bookings rows.
			foreach ($bookingIDs as $bookingID) {
				InvoiceBooking::create([
					'tour_booking_id' => $bookingID['bookingid'],
					'invoice_id' => $invoice_id,
					'created_by' => Auth::id(),
					'created_by_user_type' => session('userType')
				]);
			}
			$ids = $bookingIDs;
		}

		return ["count" => count($ids), "ids" => $ids];
	}

	/**
	 * Get the list of affiliate who is having an due amount till current date.
	 *
	 * @return View
	 */
	public function affiliateDueAmount(): View
	{
		$vars = ['page_title' => 'Affiliate Due Amount'];

		return view('users.admin.invoice.affiliate-due-amount', $vars);
	}

	/**
	 * Get the list of affiliate who is having an due amount till current date.
	 *
	 * @param \Yajra\DataTables\DataTables $dataTables
	 * @return JsonResponse
	 */
	public function affiliateDueAmountAHR(DataTables $dataTables, Request $request): JsonResponse
	{
		$customerId = ($request->has('customerId')) ? $request->customerId : 0;

		$tourPackageId = ($request->has('packageId')) ? $request->packageId : 0;
		$qry = DB::table('tour_bookings_master AS tbm')
			->join('tour_booking_history as tbh', 'tbh.tour_booking_id', '=', 'tbm.id')
			->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
			->join('customers as c', 'c.id', '=', 'tbm.customer_id')
			->join('tour_bookings_transact AS tbt', 'tbt.tour_booking_id', 'tbm.id')
			->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
			->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
			->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
			->join('transactions as t', 't.tour_booking_id', '=', 'tbm.id')
			->where('t.via', 'Invoiced')
			->where('t.status', 'Completed')
			->where('tbh.payment_type', 'Invoiced')
			->where('tbm.status', 'Booked')
			->where('c.is_affiliate', 'Yes')
			->where('tbt.deleted_by', 0)
			->when($request->has('fromDate') && $request->has('toDate'), function ($query) use ($request) {
				$fromDate = $request->fromDate;
				$toDate = $request->toDate;
				return $query->whereBetween('tsd.date', [$fromDate, $toDate]);
			})
			->when($tourPackageId && $tourPackageId != 0, function ($query) use ($tourPackageId) {
				return $query->where('tbm.tour_package_id', '=', $tourPackageId);
			})
			->when($customerId != 0, function ($query) use ($customerId) {
				return $query->where('c.id', '=', $customerId);
			})
			->select(
				'tbm.id as bookingid',
				'tbm.status as status',
				'tbm.total as amount',
				'tbm.booking_total',
				'tbm.tourists as overall_tourists',
				'c.name as affiliate_name',
				'c.id as customer_id',
				DB::raw('CASE 
                        WHEN tbt.infants > 0 THEN tbt.infants
                        ELSE tbt.tourists
                        END AS tourists'),
				'tp.name as package',
				't.status as payment_status',
				'tbh.details',
				'tbh.operation',
				DB::raw('(SELECT SUM(CASE operation WHEN "Payment" THEN `tbh`.`ticket_cost` ELSE 0 END) FROM tour_booking_history AS tbh
                        WHERE tbh.tour_booking_id = tbm.id) as "payment_cost"'),
				DB::raw('(SELECT SUM(CASE 
                        WHEN (operation = "Refund" 
                              AND JSON_UNQUOTE(JSON_EXTRACT(`tbh`.`details`, \'$.payment_type\')) != "")  
                            THEN `tbh`.`ticket_cost` 
                        WHEN operation = "Cancelled" 
                            THEN `tbh`.`ticket_cost` 
                        ELSE 0 
                        END) 
                        FROM tour_booking_history AS tbh 
                        WHERE tbh.tour_booking_id = tbm.id) as refund_cost')
			)
			->orderBy('tsd.date', 'ASC');

		$result = $qry->distinct()->get();
		$packageRates = $this->tourPackageRates($result, 0);
		$sortedData = array();

		$unpaidAmount = 0;
		$touristsTotal = 0;
		foreach ($packageRates as $key => $value) {
			$touristsTotal += $value['TouristTotal'];
			$value['payment_type'] = "Invoiced";
			$value['refundamount'] = $value['booking_details']['refund_cost'];
			$value['bookingamount'] = $value['booking_details']['booking_total'];
			
			$invoice = DB::table('invoices as i')
				->join('invoice_bookings as ib', 'ib.invoice_id', '=', 'i.id')
				->where('ib.tour_booking_id', $value['bookingid'])->first(['i.status as status', 'i.id as iid']);

			if ($invoice) {
				$sortInitialValue['invoice_id'] = $invoice->iid;
				if ($invoice->status == 'paid') {
					$value['bookingamount'] = $value['booking_details']['payment_cost'];
					$value['unpaid_amount'] = 0;
				} else {
					$value['unpaid_amount'] = $value['booking_details']['payment_cost'] - $value['booking_details']['refund_cost'];
					$value['payment_amount'] = 0;
				}
			} else {
				$value['invoice_id'] = 0;
				$value['payment_amount'] = 0;
				$value['unpaid_amount'] = $value['booking_details']['amount'];
			}
			$sortedData['invoice_data'][] = $value;
		}
		$queryinvoice = Invoice::paid();
		if ($request->has('fromDate') && $request->has('toDate')) {
			$fromDate = $request->fromDate;
			$toDate = $request->toDate;
			$queryinvoice->where(function ($query) use ($fromDate, $toDate) {
				return $query->whereBetween('from_date', [$fromDate, $toDate])
					->orWhereBetween('to_date', [$fromDate, $toDate]);
			});
		}
		$queryinvoice->where('tour_operator_id', auth()->user()->tour_operator_id);
		$queryinvoice->when($customerId != 0, function ($query) use ($customerId) {
			return $query->where('customer_id', '=', $customerId);
		});
		$invoice = $queryinvoice->groupBy('customer_id')
			->selectRaw('sum(amount) as amount, customer_id')
			->pluck('amount', 'customer_id');

		// Calculate due amount from existing invoice data instead of separate query
		$dueAmountByCustomer = [];

		// Initialize invoice_data if it doesn't exist
		if (!isset($sortedData['invoice_data'])) {
			$sortedData['invoice_data'] = [];
		}

		// Check if invoice_data exists and is array before processing
		if (isset($sortedData['invoice_data']) && is_array($sortedData['invoice_data'])) {
			foreach ($sortedData['invoice_data'] as $booking) {
				// Use the correct key structure from the processed data
				$customerId = isset($booking['customer_id']) ? $booking['customer_id'] : (isset($booking['booking_details']['customer_id']) ? $booking['booking_details']['customer_id'] : null);
				$customerName = isset($booking['affiliate_name']) ? $booking['affiliate_name'] : (isset($booking['booking_details']['affiliate_name']) ? $booking['booking_details']['affiliate_name'] : 'Unknown');

				if (!$customerId) {
					continue; // Skip if no customer ID found
				}

				if (!isset($dueAmountByCustomer[$customerId])) {
					$dueAmountByCustomer[$customerId] = [
						'id' => $customerId,
						'name' => $customerName,
						'amount' => 0,
						'tourists' => 0
					];
				}

				// Add unpaid amount for this booking
				$unpaidAmount = isset($booking['unpaid_amount']) ? $booking['unpaid_amount'] : 0;
				$touristTotal = isset($booking['TouristTotal']) ? $booking['TouristTotal'] : 0;

				$dueAmountByCustomer[$customerId]['amount'] += $unpaidAmount;
				$dueAmountByCustomer[$customerId]['tourists'] += $touristTotal;
			}
		} // Close the if statement for invoice_data check

		// Convert to collection format
		$dueAmount = collect(array_values($dueAmountByCustomer));

		if ($invoice->count() && $dueAmount->count()) {
			foreach ($dueAmount as $index => $affiliate) {
				if (isset($invoice[$affiliate['id']])) {
					$dueAmount[$index]['amount'] = (string)($affiliate['amount'] - $invoice[$affiliate['id']]);
				}
			}
		}
		//$sortedData['due_amount'] = $dueAmount;
		return $dataTables->of($dueAmount)
			->addIndexColumn()
			->make(true);
	}
	// Affiliate Booked, Invoiced and Comprehensive Report Package Rates Calculation
    public function tourPackageRates($rateGroups, $comprehensive_flag = 0)
    {
        $initialArray = [];
        foreach ($rateGroups as $rateGroup) {
            $bookingId = $rateGroup->bookingid;
            if ($comprehensive_flag == 1) {
                $initialArray[$bookingId]['billable_invoiced_amount'] = 0;
            }

            if (!isset($initialArray[$bookingId]['booking_details'])) {
                $initialArray[$bookingId]['booking_details'] = (array)$rateGroup;
                $initialArray[$bookingId]['booking_status'] = $rateGroup->status;
                $initialArray[$bookingId]['TouristTotal'] = $rateGroup->overall_tourists;
                $initialArray[$bookingId]['InfantsTotal'] = $rateGroup->infants ?? 0;
                $initialArray[$bookingId]['bookingid'] = $bookingId;
                $initialArray[$bookingId]['package'] = $rateGroup->package;
            }
        }

        return $initialArray;
    }

	/**
	 * readStripeInvoice
	 * https://stripe.com/docs/api/invoices/retrieve
	 * @param Request $request query string params
	 */
	public function readStripeInvoice(Request $request)
	{
		$invoice = Invoice::uuid($request->uuid)
			->with(['tourOperator', 'affiliate'])
			->first();

		$opts = [
			'stripe_account' => $invoice->tourOperator->stripe_destination_id
		];

		$stripe_invoice = $this->invoiceRepository->readStripeInvoice($request->stripe_id, [], $opts);

		return $stripe_invoice;
	}
}
