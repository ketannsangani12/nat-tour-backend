<?php

namespace App\Http\Controllers;

use App\Jobs\Affiliate\DeleteBooking;
use App\Models\TourOperator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Customer;
use App\Models\TourBooking;
use App\Models\BookingTransact;
use App\Models\Transaction;
use App\Models\TourPackage;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Common\ReportController;
use App\Jobs\Import\Booking\AddImportBooking;
use App\Jobs\Affiliate\CorrectAffiliateBooking;
use App\Jobs\Affiliate\DeleteBookingsByDate;
use App\Jobs\Affiliate\FetchAffiliateBookingByDate;
use App\Jobs\Affiliate\FetchAffiliateSoftBlockBookingByDate;
use App\Jobs\Affiliate\GenerateMultipleSoftAffiliateBooking;
use App\Jobs\GenerateSoftAffiliateBooking;
use App\Jobs\InsertSlotSeatsSummary;
use App\Jobs\TimeSlot\UpdateTimeSlotSeats;
use App\Models\Admin;
use App\Repositories\Report\ReportRepository;
use Exception;
use App\Jobs\UpdateTransactionsTablePointDeference;
use App\Jobs\UpdateAffiliateSeatsTable;
use App\Models\Department;
use App\Models\TourBookingForms;
use App\Models\TourPackageForm;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Services\Permissions\PermissionCacheService;

class ImportExportController extends Controller
{
	protected $reportRepository, $reportController, $permissionCache;

	public function __construct(ReportRepository $reportRepository, ReportController $reportController, PermissionCacheService $permissionCache)
	{
		parent::__construct();

		$this->reportRepository = $reportRepository;
		$this->reportController = $reportController;
		$this->permissionCache = $permissionCache;
	}

	/**
	 * Import view form for several actions to perform
	 */
	public function importview()
	{
		$affiliates = Customer::isAffiliate()->get();
		$packages = TourPackage::active()->get();
		$admins = Admin::all();

		$vars = [
			'page_title' => 'Add Import View',
			'formSubmitRoute' => route('admin.ImportSheet'),
			'softBlockBookingRoute' => route('admin.create-soft-block-bookings'),
			'correctAffiliateBookingRoute' => route('admin.correct-affiliate-bookings'),
			'deleteBookingRoute' => route('admin.delete-bookings'),
			'duplicateBookingRoute' => route('admin.duplicate-booking'),
			'date' => date('m/d/Y'),
			'hiddenDate' => date('Y-m-d'),
			'affiliates' => $affiliates,
			'packages' => $packages,
			'admins' => $admins
		];

		return view('excel.index', $vars);
	}

	/**
	 * Create multiple soft bookings for specific affiliate, slot and date range
	 * 
	 * @param Request $request
	 * @return mixed
	 */
	public function createSoftBlockBookings(Request $request)
	{
		$request->validate([
			'package_id' => 'required',
			'affiliate_id' => 'required',
			'date_range' => 'required',
			'time' => 'required',
			'spots' => 'required',
			'seat_preference' => 'required'
		]);

		try {
			$data = $request->all();

			$times = explode(',', $request->input('time')); // Split into array

			$filteredTimes = array_values(array_filter($times, function ($time) {
				return trim($time) !== '__:__:00' && !empty(trim($time));
			}));

			// Return a string if only one valid time exists, otherwise return an array
			$data['time'] = count($filteredTimes) === 1 ? $filteredTimes[0] : $filteredTimes;

			$dateSplit = explode(" - ", $data['date_range']);
			$startDate = strtotime($dateSplit[0]);
			$days = (isset($data['days']) && !empty($data['days'])) ? $data['days'] : [];
			$affiliateQuery = Customer::isAffiliate()->select('id')->get()->toArray();
			$affiliateIds = array_unique(array_column($affiliateQuery, 'id'));

			while ($startDate <= strtotime($dateSplit[1])) {
				$date = date("Y-m-d", $startDate);
				$data['date'] = $date;

				if (in_array($data['affiliate_id'], $affiliateIds)) {
					if (!empty($days)) {
						$dayOfWeek = date('N', $startDate);
						if (in_array($dayOfWeek, $days)) {
							if (count($filteredTimes) > 1) {
								dispatch(new GenerateMultipleSoftAffiliateBooking($data));
							} else {
								dispatch(new GenerateSoftAffiliateBooking($data));
							}
						}
					} else {
						if (count($filteredTimes) > 1) {
							dispatch(new GenerateMultipleSoftAffiliateBooking($data));
						} else {
							dispatch(new GenerateSoftAffiliateBooking($data));
						}
					}
				}

				$startDate = strtotime('+1 day', $startDate);
			}

			return redirect()->back()->with('message', 'Soft bookings will be formed in the background shortly; please check back in 5 minutes.');
		} catch (Exception $exception) {
			Log::error($exception);
			return redirect()->back()->with('error', $exception->getMessage());
		}
	}

	/**
	 * Correct multiple affiliate bookings for specific affiliate and date range
	 * 
	 * @param Request $request
	 * @return mixed
	 */
	public function correctAffiliateBookings(Request $request)
	{
		try {
			if (isset($request->booking_id) && !empty($request->booking_id)) {
				dispatch(new CorrectAffiliateBooking($request->booking_id));
			} else {
				$data = $request->all();

				$dateSplit = explode(" - ", $data['date_range']);
				$startDate = strtotime($dateSplit[0]);
				$endDate = strtotime($dateSplit[1]);

				while ($startDate <= $endDate) {
					$date = date("Y-m-d", $startDate);
					$data['date'] = $date;
					if ($request->spots > 0) {
						dispatch(new FetchAffiliateSoftBlockBookingByDate($data));
					} else {
						dispatch(new FetchAffiliateBookingByDate($data));
					}

					$startDate = strtotime('+1 day', $startDate);
				}
			}

			return redirect()->back()->with('message', 'Affiliate booking correction will be formed in the background shortly; please check back in 5 minutes.');
		} catch (Exception $exception) {
			Log::error($exception);
			return redirect()->back()->with('error', $exception->getMessage());
		}
	}

	/**
	 * Delete multiple bookings for specific affiliate and date range
	 * 
	 * @param Request $request
	 * @return mixed
	 */
	public function deleteBookings(Request $request)
	{
		try {
			if (isset($request->booking_id) && !empty($request->booking_id)) {
				dispatch(new DeleteBooking($request->booking_id));
			} else {
				$data = $request->all();

				$dateSplit = explode(" - ", $data['date_range']);
				$startDate = strtotime($dateSplit[0]);
				$endDate = strtotime($dateSplit[1]);
				$days = (isset($data['days']) && !empty($data['days'])) ? $data['days'] : [];

				while ($startDate <= $endDate) {
					$date = date("Y-m-d", $startDate);
					$data['date'] = $date;

					if (!empty($days)) {
						$dayOfWeek = date('N', $startDate);
						if (in_array($dayOfWeek, $days)) {
							dispatch(new DeleteBookingsByDate($data));
						}
					} else {
						dispatch(new DeleteBookingsByDate($data));
					}

					$startDate = strtotime('+1 day', $startDate);
				}
			}

			return redirect()->back()->with('message', 'The booking correction will be formed in the background shortly; please check back in 5 minutes.');
		} catch (Exception $exception) {
			Log::error($exception);
			return redirect()->back()->with('error', $exception->getMessage());
		}
	}

	/**
	 * Duplicate an existing booking by cloning all related records
	 */
	public function duplicateBooking(Request $request)
	{
		$request->validate([
			'booking_id' => 'required|integer|exists:tour_bookings_master,id'
		]);

		try {
			$originalBookingId = $request->booking_id;
			
			// Check if the original booking exists
			$originalBooking = DB::table('tour_bookings_master')->where('id', $originalBookingId)->first();
			
			if (!$originalBooking) {
				return redirect()->back()->with('error', 'Original booking not found.');
			}

			DB::beginTransaction();

			// 1. Clone tour_bookings_master record
			$bookingData = (array) $originalBooking;
			unset($bookingData['id']); // Remove the primary key
			unset($bookingData['created_at']); // Let it set new timestamp
			unset($bookingData['updated_at']); // Let it set new timestamp
			
			// Generate new group_id by getting the last one and incrementing
			$lastGroupId = DB::table('tour_bookings_master')->max('group_id') ?? 0;
			$newGroupId = $lastGroupId + 1;
			
			// Update some fields for the duplicate
			$bookingData['group_id'] = $newGroupId;
			$bookingData['created_by'] = $originalBooking->customer_id;
			$bookingData['created_by_user_type'] = 'Customer';
			
			$newBookingId = DB::table('tour_bookings_master')->insertGetId($bookingData);

			// 2. Clone transactions records
			$transactions = DB::table('transactions')->where('tour_booking_id', $originalBookingId)->get();
			foreach ($transactions as $transaction) {
				$transactionData = (array) $transaction;
				unset($transactionData['id']);
				unset($transactionData['created_at']);
				
				$transactionData['tour_booking_id'] = $newBookingId;
				$transactionData['created_by'] = $originalBooking->customer_id;
				$transactionData['created_by_user_type'] = 'Customer';
				
				$newTransactionId = DB::table('transactions')->insertGetId($transactionData);
				
				// 3. Clone tour_booking_history records related to this transaction
				$histories = DB::table('tour_booking_history')
					->where('tour_booking_id', $originalBookingId)
					->where('transaction_id', $transaction->id)
					->get();
					
				foreach ($histories as $history) {
					$historyData = (array) $history;
					unset($historyData['id']);
					unset($historyData['created_at']);
					unset($historyData['updated_at']);
					
					$historyData['tour_booking_id'] = $newBookingId;
					$historyData['transaction_id'] = $newTransactionId;
					$historyData['operation_by'] = $originalBooking->customer_id;
					$historyData['operation_by_user_type'] = 'Customer';
					$historyData['operation_at'] = now();
					
					DB::table('tour_booking_history')->insert($historyData);
				}
			}

			// 4. Clone tour_bookings_transact records
			$transacts = DB::table('tour_bookings_transact')->where('tour_booking_id', $originalBookingId)->get();
			foreach ($transacts as $transact) {
				$transactData = (array) $transact;
				unset($transactData['id']);
				unset($transactData['created_at']);
				unset($transactData['updated_at']);
				
				$transactData['tour_booking_id'] = $newBookingId;
				
				DB::table('tour_bookings_transact')->insert($transactData);
			}

			// 5. Clone any remaining tour_booking_history records not tied to transactions
			$remainingHistories = DB::table('tour_booking_history')
				->where('tour_booking_id', $originalBookingId)
				->whereNull('transaction_id')
				->get();
				
			foreach ($remainingHistories as $history) {
				$historyData = (array) $history;
				unset($historyData['id']);
				unset($historyData['created_at']);
				unset($historyData['updated_at']);
				
				$historyData['tour_booking_id'] = $newBookingId;
				$historyData['operation_by'] = $originalBooking->customer_id;
				$historyData['operation_by_user_type'] = 'Customer';
				$historyData['operation_at'] = now();
				
				DB::table('tour_booking_history')->insert($historyData);
			}

			DB::commit();

			return redirect()->back()->with('message', "Booking duplicated successfully. New booking ID: {$newBookingId}, New group ID: {$newGroupId}. Original booking ID: {$originalBookingId}");
			
		} catch (Exception $exception) {
			DB::rollBack();
			Log::error('Duplicate booking error: ' . $exception->getMessage());
			return redirect()->back()->with('error', 'Failed to duplicate booking: ' . $exception->getMessage());
		}
	}

	/**
	 * Transfer package slot seats between packages
	 * 
	 * @return void
	 */
	public function swipeSlotSeats()
	{
		$startDate = strtotime('2024-04-01');

		while ($startDate <= strtotime('2024-12-31')) {
			$date = date("Y-m-d", $startDate);

			dispatch(new UpdateTimeSlotSeats($date));

			$startDate = strtotime('+1 day', $startDate);
		}
	}

	/**
	 * Import Booking Data
	 * 
	 * @param Request $request
	 */
	public function ImportData(Request $request)
	{
		set_time_limit(0);
		$request->validate([
			'contacts_import' => 'required',
			'date' => 'required',
		]);

		if (tenant()->id == 'gcw') {
			return $this->importGCWBookings($request);
		}

		$CustomerImport = new Customer();
		$TourBookingImport = new TourBooking();

		$file = $request->contacts_import;
		$tempName = $file->getPathName();
		$fileExtension = $file->getClientOriginalExtension();
		$errors = $file->getError();

		if ($errors == 0) {
			if (($fileExtension == "csv" || $fileExtension == "excel" || $fileExtension == "xls") && (!empty($tempName))) {
				$i = 0;
				$delimiter = ',';
				$data = [];
				$headersArray = [];
				$CustomerArrayKeys = array_keys($CustomerImport->import['fields']);
				$TourBookingArrayKeys = array_keys($TourBookingImport->import['fields']);
				$importArrayKeys = array_merge($CustomerArrayKeys, $TourBookingArrayKeys);

				if (($handle = fopen($tempName, 'r')) !== false) {

					while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {

						$row = array_map("utf8_encode", $row);

						if ($i == 2) {
							$headersArray = $row;
						}

						if ($i > 3) {
							foreach ($row as $key => $value) {
								if (isset($value)) {
									if (
										in_array('Start', $headersArray) &&
										in_array('Booking ID', $headersArray)  &&
										in_array('Contact', $headersArray)  &&
										in_array('Phone', $headersArray) &&
										in_array('Customers', $headersArray) &&
										in_array('Pax', $headersArray) &&
										in_array('Check-in', $headersArray) &&
										in_array('Comments', $headersArray) &&
										in_array('Waivers', $headersArray)  &&
										in_array('Due', $headersArray) &&
										in_array('Notes', $headersArray)
									) {
										$data[$i][$importArrayKeys[$key]] = $value;
									}
								}
							}
						}
						$i++;
					}

					fclose($handle);
				}

				$length = count($data);
				$length =  $length + 4;
				$totalInsert = 0;

				if ($length != 0) {
					for ($i = 4; $i <= $length; $i++) {

						$trimStartDate = (isset($data[$i]['start'])) ? $data[$i]['start'] : 0;
						$trimname = (isset($data[$i]['name'])) ? $data[$i]['name'] : '';
						$trimphone = (isset($data[$i]['phone_number'])) ? $data[$i]['phone_number'] : '';
						$trimEmail = (isset($data[$i]['email'])) ? $data[$i]['email'] : '';
						$trimTourPackageId = 1;
						$trimTourPromotionId = 0;
						$trimtourists = (isset($data[$i]['tourists'])) ? $data[$i]['tourists'] : '';
						$trimrate = (isset($data[$i]['rate'])) ? $data[$i]['rate'] : '';
						$trimcomments = (isset($data[$i]['booking_id'])) ? $data[$i]['booking_id'] : '';
						$trimnote = (isset($data[$i]['note'])) ? $data[$i]['note'] : '';
						$trimvia = 'Cash';
						$date = $request->date;
						$start_time = date("G:i:s", strtotime($trimStartDate));

						$TourSlotId = DB::table('tour_slot_dates as tsd')
							->join('tour_slot_times as tst', 'tst.tour_slot_id', '=', 'tsd.id')
							->where('tsd.date', '=', $date)
							->where('tst.time_from', '=', $start_time)
							->select('tst.id as TimeSlotId')
							->first();

						if ($TourSlotId != '' && $TourSlotId) {
							$pkg_data = DB::table('tour_packages as pkg')
								->join('tour_operators as opt', 'opt.id', '=', 'pkg.tour_operator_id')
								->join('cities as city', 'city.id', '=', 'opt.city_id')
								->join('states as st', 'st.id', '=', 'city.state_id')
								->join('taxes as tax', 'tax.state_id', '=', 'st.id')
								->where('pkg.id', '=', $trimTourPackageId)
								->select('st.permit_fee', 'tax.value')
								->first();

							$TaxValue = $pkg_data->value;

							$customerData = Customer::where('email', '=', strtolower($trimEmail))->first();

							if ($customerData === null) {
								$customer = new Customer();
								$customer->tour_operator_id = 1;
								$customer->name = $trimname;
								$customer->email = strtolower($trimEmail);

								if ($trimphone == '#NAME?') {
									$phone = '';
								} else {
									$phone = $trimphone;
								}

								$customer->phone_number = $phone;
								$customer->created_by = Auth::id();
								$customer->created_by_user_type = session('userType');
								$customer->save();
								$customerId = $customer->id;
							} else {
								$customerId = $customerData->id;
							}

							$bookingMaster = new TourBooking;
							$bookingMaster->tour_package_id = $trimTourPackageId;
							$bookingMaster->tour_slot_id = $TourSlotId->TimeSlotId;
							$bookingMaster->tour_promotion_id = $trimTourPromotionId;
							$bookingMaster->customer_id = $customerId;
							$bookingMaster->name = $trimname;
							$bookingMaster->email = strtolower($trimEmail);

							if ($trimphone == '#NAME?') {
								$phone = '';
							} else {
								$phone = $trimphone;
							}

							$bookingMaster->phone_number = $phone;
							$bookingMaster->tourists = $trimtourists;
							$bookingMaster->comments = $trimcomments;
							$bookingMaster->note = $trimnote;
							$bookingMaster->created_by = Auth::id();
							$bookingMaster->created_by_user_type = session('userType');

							if ($bookingMaster->save()) {
								$rates = DB::table('tour_package_rates as pkg_rate')
									->where('pkg_rate.tour_package_id', '=', $trimTourPackageId)
									->get();

								foreach ($rates as $key => $value) {
									$tourist = explode(', ', $trimrate);

									if ($tourist && count($tourist) > 0) {
										foreach ($tourist as $tvalue) {
											$splittedRate = explode(' ', $tvalue, 2);

											$touristNumber = $splittedRate[0];
											$rateFor = '';
											if ($splittedRate[1] == "Adult (Ages 13 & Up)") {
												$splittedRate[1] = ("Adults (Ages 13 & Up)");
											}
											if ($splittedRate[1] == "Adult - Military/Teacher") {
												$splittedRate[1] = ("Adults - Military/Teacher");
											}
											if ($splittedRate[1] == "Child (Ages 4 -7)") {
												$splittedRate[1] = ("Children (Ages 4 -7)");
											}
											if ($splittedRate[1] == "Child (Ages 8 -12)") {
												$splittedRate[1] = ("Children (Ages 8-12)");
											}
											if ($splittedRate[1] == "Child (Ages 4-7) - Military/Teachers") {
												$splittedRate[1] = ("Children (Ages 4-7) - Military/Teachers");
											}
											if ($splittedRate[1] == "Child (Ages 8-12) - Military/Teachers") {
												$splittedRate[1] = ("Children (Ages 8-12) - Military/Teachers");
											}
											if ($splittedRate[1] == "Infant (Ages 3 & Under)") {
												$splittedRate[1] = ("Infants (Ages 3 & Under)");
											}
											if ($splittedRate[1] == "Native American") {
												$splittedRate[1] = ("Native Americans");
											}
											if ($splittedRate[1] == "Tour Guide") {
												$splittedRate[1] = ("Tour Guides");
											}
											if ($splittedRate[1] == "Local") {
												$splittedRate[1] = ("Locals");
											}
											if ($splittedRate[1] == "TLC Combo Spot") {
												$splittedRate[1] = ("TLC Combo Spots");
											}
											if ($splittedRate[1]) {
												$rateFor = $splittedRate[1];
											}

											if ($rateFor == $value->rate_for) {
												$transact = new BookingTransact;
												$transact->tour_booking_id = $bookingMaster->id;
												$transact->tour_package_rate_id = $value->id;
												$transact->tourists = $touristNumber;
												$transact->additional_tax_percentage = $value->additional_tax_percentage;
												$transact->processing_charge_percentage = 2.2;
												$transact->additional_charge = $value->additional_charge;
												$transact->rate = $value->rate;
												if ($value->rate > 0) {
													$transact->permit_fee = $value->permit_fee;
													$transact->state_tax = $TaxValue;

													$sumtotal = $value->rate + $value->additional_charge + $value->permit_fee + $TaxValue;
													$sumtotal = number_format((float)$sumtotal, 2, '.', '');
													$totalValue = (($sumtotal * 2.2) / 100);

													$subtotal = $sumtotal + $totalValue;
													$subtotal = number_format((float)$subtotal, 2, '.', '');
													$TotalCost = $touristNumber * $subtotal;
													$transact->total = number_format((float)$TotalCost, 2, '.', '');
												} else {
													$transact->permit_fee = 0;
													$transact->state_tax = 0;
													$transact->total = 0;
												}
												$transact->created_by = Auth::id();
												$transact->created_by_user_type = session('userType');
												$transact->save();
											}
										}
									}

									$transactBookingTotal = DB::table('tour_bookings_transact as pkg_rate')
										->where('pkg_rate.tour_booking_id', '=', $bookingMaster->id)
										->get()->sum("total");

									$bookingTotal = number_format((float)$transactBookingTotal, 2, '.', '');
									TourBooking::where('id', $bookingMaster->id)->update(['total' => $bookingTotal]);
								}
							}

							$transactionData = [
								'tour_booking_id' => $bookingMaster->id,
								'amount' => $bookingTotal,
								'via' => $trimvia,
								'status' => 'Completed',
								'created_by' => Auth::id(),
								'created_by_user_type' => session('userType')
							];

							Transaction::create($transactionData);

							DB::table('tour_booking_history')
								->insert([
									'tour_booking_id' => $bookingMaster->id,
									'operation' => 'Payment',
									'details' => json_encode(['amount' => $bookingTotal], JSON_NUMERIC_CHECK),
									'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
									'operation_by' => Auth::id(),
									'operation_by_user_type' => session('userType'),
								]);
							$totalInsert++;
						}
					}

					$message = 'Successfully import records!';
					$returnData = [
						'success' => true,
						'message' => $message
					];

					return response()->json($returnData);
				}
			}
		}
	}

	/**
	 * Summary of importGCWBookings
	 * @param mixed $request
	 * @return mixed
	 */
	public function importGCWBookings($request)
	{
		// Validate the uploaded file
		$request->validate([
			'contacts_import' => 'required|file|mimes:csv,txt,octet-stream|max:10240',
		], [
			'contacts_import.required' => 'Please upload a file.',
			'contacts_import.file' => 'The uploaded file must be a valid file.',
			'contacts_import.mimes' => 'The file must be a CSV or TXT file.',
			'contacts_import.max' => 'The file size should not exceed 10MB.',
		]);

		if ($request->hasFile('contacts_import')) {
			$file = $request->file('contacts_import');
		} else {
			dd('No file uploaded');
		}

		$data = [];

		try {
			// Open the file and read its contents
			if (($handle = fopen($file->getPathname(), 'r')) !== false) {
				// Read the header row
				$header = fgetcsv($handle);

				// Ensure the header is valid
				if (!$header || count($header) === 0) {
					return back()->with('error', 'The CSV file has an invalid or empty header.');
				}

				// Read each row of the CSV
				while (($row = fgetcsv($handle)) !== false) {
					if (count($row) === count($header)) {
						$data[] = array_combine($header, $row);
					} else {
						return back()->with('error', 'Mismatch between header and row column count.');
					}
				}

				// Close the file
				fclose($handle);
			}

			if (count($data)) {
				$packages = [
					'GA FIT' => 3,
					'Skywalk FIT' => 2,
					'Meal FIT' => 4,
					'Zipline FIT' => 5,
					'HP Papillon' => 6,
					'Whitewater FIT' => 8,
					'Rafting 2D' => 0, // no idea what
					'Heli Lift HP' => 0, // no idea what
					'GA GC' => 0, // no idea what
					'Rafting 2D Ground' => 0, // no idea what
				];

				$inputDate = $request->date;
				foreach ($data as $key => $val) {
					dispatch(new AddImportBooking($val, $packages, $inputDate));
					if ($key == 50) {
						die;
					}
				}
			} else {
				dump("No data found");
			}

			$message = 'Successfully import records!';
			$returnData = [
				'success' => true,
				'message' => $message
			];

			return response()->json($returnData);
		} catch (Exception $e) {
			$message = 'Error importing records: ' . $e->getMessage();
			$returnData = [
				'success' => false,
				'message' => $message
			];

			return response()->json($returnData);
		}
	}

	/**
	 * Sample File Download in CSV File
	 */
	public function downloadFile()
	{
		$filePath = public_path("uploads/dummy.csv");
		$headers = ['Content-Type: application/csv'];
		$fileName = time() . '.csv';

		return response()->download($filePath, $fileName, $headers);
	}

	/**
	 * Cancel Booking Report Download in CSV File
	 * 
	 * @param Request $request
	 */
	public function CancelBookingReportExportCsvFile(Request $request)
	{
		return $this->reportRepository->cancelbookingreport($request, $request->fromDate, $request->toDate, $request->tourOperatorId, $request->tourPackageId, $request->csv);
	}

	/**
	 * Faily Report Download in CSV File
	 * 
	 * @param Request $request
	 */
	public function DailyReportExportCsvFile(Request $request)
	{
		return $this->reportRepository->dailyreport($request, $request->fromDate, $request->toDate, $request->tourOperatorId, $request->tourPackageId, $request->csv);
	}

	/**
	 * Daily Report Download in CSV File
	 * 
	 * @param Request $request
	 */
	public function DailyReportDetailsExportCsvFile(Request $request)
	{
		return $this->reportRepository->dailyreportdetails($request, $request->fromDate, $request->toDate, $request->tourOperatorId, $request->tourPackageId, $request->csv);
	}

	/**
	 * BookingDetails Report Download in CSV File
	 * 
	 * @param Request $request
	 */
	public function BookingDetailsReportExportCsvFile(Request $request)
	{
		$fromDate = $request->get('fromDate');
		$toDate = $request->get('toDate');
		$tourPackageId = $request->get('tourPackageId');

		if ($fromDate == "")
			$fromDate = date('Y-m-d');

		if ($toDate == "")
			$toDate = date('Y-m-d');

		$query = DB::table('tour_bookings_master AS tbm')
			->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
			->join('customers as c', 'c.id', '=', 'tbm.customer_id')
			->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
			->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
			->leftJoin('transactions as t', 't.tour_booking_id', '=', 'tbm.id')
			->join('tour_operators as to', 'to.id', '=', 'tp.tour_operator_id')
			->where('tsd.deleted_by', '=', '0')
			->whereBetween('tbm.created_at', [$fromDate, $toDate])
			->orderBy('tbm.id', 'DESC');

		$fields = ['tbm.id as bookingid', 'c.is_affiliate as is_affiliate', 'tbm.name as name', 'tbm.total as amount',/*'tsd.date as bookingdate','tbm.created_at as createdate',*/ DB::raw('DATE_FORMAT(tsd.date,"%d-%m-%Y") as bookingdate'), DB::raw('DATE_FORMAT(tbm.created_at,"%d-%m-%Y") as createdate'), 'tbm.tourists as tourists', DB::raw('IFNULL(t.status,"Pending")  as payment'), 'to.logo as touroperatorlogo', 'tbm.status as status'];

		if ($tourPackageId && $tourPackageId !== 0) {
			$query = $query->where('tbm.tour_package_id', '=', $tourPackageId);
		}

		$query = $query->get($fields);
		$result = json_decode($query, true);

		$fileName = 'download.csv';

		$headers = csvHeaders($fileName);
		$columns = csvColumns('booking_details_report');

		$callback = function () use ($result, $columns) {

			$file = fopen('php://output', 'w');  // Create a file
			fputcsv($file, $columns);

			foreach ($result as $each_data) {

				$row['Booking Id'] = (!empty($each_data)) ? ((isset($each_data['bookingid'])) ? $each_data['bookingid'] : '') : '';
				$row['Customer Name'] = (!empty($each_data)) ? ((isset($each_data['name'])) ? $each_data['name'] : '') : '';
				$row['Booking Date'] = (!empty($each_data)) ? ((isset($each_data['bookingdate'])) ? $each_data['bookingdate'] : '') : '';
				$row['Created Date'] = (!empty($each_data)) ? ((isset($each_data['createdate'])) ? $each_data['createdate'] : '') : '';
				$row['Tourists'] = (!empty($each_data)) ? ((isset($each_data['tourists'])) ? $each_data['tourists'] : '') : '';
				$row['Amount'] = (!empty($each_data)) ? ((isset($each_data['amount'])) ? $each_data['amount'] : '') : '';
				$row['payment'] = (!empty($each_data)) ? ((isset($each_data['payment'])) ? $each_data['payment'] : '') : '';
				$row['status'] = (!empty($each_data)) ? ((isset($each_data['status'])) ? $each_data['status'] : '') : '';

				fputcsv($file, array(
					$row['Booking Id'],
					$row['Customer Name'],
					$row['Booking Date'],
					$row['Created Date'],
					$row['Tourists'],
					$row['Amount'],
					$row['payment'],
					$row['status']
				));
			}

			fclose($file);
		};

		return response()->stream($callback, 200, $headers);
	}

	/**
	 * Get tour operator name
	 * 
	 * @param int $tourOperatorId
	 * @param int $tourPackageId
	 */
	private function GetTourOperatorName($tourOperatorId = 0, $tourPackageId = 0)
	{
		$userType = userType();
		$tourOperatorName = '';
		$tourPackageName = '';

		if ($userType == 'Admin') {
			if ($tourOperatorId != 0 && $tourOperatorId != '') {
				$tourOperatorDetails = TourOperator::find($tourOperatorId);
				$tourOperatorName = $tourOperatorDetails->name;
			}
		} else {
			$tourOperatorDetails = TourOperator::find(Auth::user()->tour_operator_id);
			$tourOperatorName = $tourOperatorDetails->name;
		}

		if ($tourPackageId != 0 && $tourPackageId != '') {
			$tourPackageDetails = TourPackage::where('id', $tourPackageId)->withTrashed()->first(['id', 'name']);
			$tourPackageName = $tourPackageDetails->name;
		}

		return [
			'tourOperatorName' => $tourOperatorName,
			'tourPackageName' => $tourPackageName
		];
	}

	/**
	 * Tip Report Download in CSV File
	 * 
	 * @param Request $request
	 */
	public function TipReportExportCsvFile(Request $request)
	{
		$fromDate = $request->get('fromDate');
		$toDate = $request->get('toDate');
		$tourPackageId = $request->get('tourPackageId');

		if ($fromDate == "")
			$fromDate = date('Y-m-d');
		else
			$fromDate = $fromDate . " 00:00:00";

		if ($toDate == "")
			$toDate = date('Y-m-d');
		else
			$toDate = $toDate . " 23:59:59";

		if (session('userType') == 'Admin') {
			$tourOperatorId = $request->get('tourOperatorId');
		} elseif (session('userType') == 'Tour Operator') {
			$tourOperatorId = Auth::user()->tour_operator_id;
		} else {
			$tourOperatorId = Auth::user()->tour_operator_id;
		}

		$query = DB::table('tour_bookings_master AS tbm')
			->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
			->join('customers as c', 'c.id', '=', 'tbm.customer_id')
			->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
			->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
			->join('tour_operators as to', 'to.id', '=', 'tp.tour_operator_id')
			->join('tour_booking_tips as tbtip', 'tbtip.tour_booking_id', '=', 'tbm.id')
			->join('tour_operator_staff as tos', 'tos.id', '=', 'tbtip.tour_operator_staff_id')
			->where('tsd.deleted_by', '=', '0')
			->whereBetween('tbtip.date', [$fromDate, $toDate])
			->select([
				'c.name as name',
				'c.phone_number',
				'tsd.date as bookingdate',
				DB::raw('DATE_FORMAT(tbtip.date, "%Y-%m-%d") as createdate'),
				'tbm.total as amount',
				'to.logo as touroperatorlogo',
				'tos.first_name as firstName',
				'tos.last_name as lastName',
				'tbm.id',
				'tbtip.amount as tipamount',
				'tbm.tourists as tourists'
			])
			->orderBy('tsd.date', 'DESC');

		if ($tourPackageId && $tourPackageId !== 0) {
			$query = $query->where('tbm.tour_package_id', '=', $tourPackageId);
		}

		if ($tourOperatorId && $tourOperatorId !== 0) {
			$query = $query->where('tp.tour_operator_id', '=', $tourOperatorId);
		}

		$query = $query->get();
		$result = json_decode($query, true);

		$fileName = 'tip_report_' . $request->get('fromDate') . '_' . $request->get('toDate') . '.csv';

		$headers = csvHeaders($fileName);
		$columns = csvColumns('tip_report_columns');
		$tourOperatorName = $this->GetTourOperatorName($tourOperatorId, $tourPackageId);

		$callback = function () use ($result, $columns, $tourOperatorName) {
			$file = fopen('php://output', 'w');  // Create a file
			if (!empty($tourOperatorName)) {
				$BlankLine = '';
				if ($tourOperatorName['tourOperatorName']) {
					$tourOperatorNameRow = array('Tour Operator:', $tourOperatorName['tourOperatorName'], $BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine);
					fputcsv($file, $tourOperatorNameRow);
				}

				if ($tourOperatorName['tourPackageName']) {
					$tourPackageNameRow = array('Tour Package:', $tourOperatorName['tourPackageName'], $BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine);
					fputcsv($file, $tourPackageNameRow);
				}

				fputcsv($file, array($BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine));
			}

			fputcsv($file, $columns);

			$totalAmount = 0;
			$totalTipAmount = 0;

			foreach ($result as $each_data) {
				$Booking_Id = isset($each_data['id']) ? $each_data['id'] : '';
				$C_Name = isset($each_data['name']) ? $each_data['name'] : '';
				$Phone_Number = isset($each_data['phone_number']) ? $each_data['phone_number'] : '';
				$Booking_Date = isset($each_data['bookingdate']) ? $each_data['bookingdate'] : '';
				$Tip_Date = isset($each_data['createdate']) ? $each_data['createdate'] : '';
				$first_name = isset($each_data['firstName']) ? $each_data['firstName'] : '';
				$last_name = isset($each_data['lastName']) ? $each_data['lastName'] : '';
				$TOS_Name = $first_name . ' ' . $last_name;
				$Tourists = isset($each_data['tourists']) ? $each_data['tourists'] : '';
				$Amount = isset($each_data['amount']) ? $each_data['amount'] : '';
				$Tip_Amount = isset($each_data['tipamount']) ? $each_data['tipamount'] : '';

				fputcsv($file, array($Booking_Id, $C_Name, $Phone_Number, $Booking_Date, $Tip_Date, $TOS_Name, $Tourists, $Amount, $Tip_Amount));

				$totalAmount += $Amount;
				$totalTipAmount += $Tip_Amount;
			}

			$BlankLine = '';
			fputcsv($file, array($BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine, $totalAmount, $totalTipAmount));

			fclose($file);
		};

		return response()->stream($callback, 200, $headers);
	}

	/**
	 * Tip Report Download in CSV File
	 * 
	 * @param Request $request
	 */
	public function TipSummaryReportExportCsvFile(Request $request)
	{
		return $this->reportRepository->tipsummaryreport($request, $request->fromDate, $request->toDate, $request->tourOperatorId, $request->tourPackageId, $request->csv);
	}

	/**
	 * Tax Collection Report Download in CSV File
	 * 
	 * @param Request $request
	 */
	public function TaxCollectionReportExportCsvFile(Request $request)
	{
		$request['dateFrom'] = $request->currentDate;
		$request['dateTo'] = $request->toDate;
		$request['tourOperator'] = $request->tourOperatorId;
		$request['tourPackage'] = $request->tourPackageId;

		return $this->reportRepository->taxCollection($request);
	}

	/**
	 * Software Fee Report Download in CSV File
	 * 
	 * @param Request $request
	 */
	public function SoftwareFeeReportExportCsvFile(Request $request)
	{
		$fromDate = $request->get('fromDate');
		$toDate = $request->get('toDate');
		$tourPackageId = $request->get('tourPackageId');

		if ($fromDate == "")
			$fromDate = date('Y-m-d');
		else
			$fromDate = $fromDate . " 00:00:00";

		if ($toDate == "")
			$toDate = date('Y-m-d');
		else
			$toDate = $toDate . " 23:59:59";

		if (session('userType') == 'Admin') {
			$tourOperatorId = $request->get('tourOperatorId');
		} elseif (session('userType') == 'Tour Operator') {
			$tourOperatorId = Auth::user()->tour_operator_id;
		} else {
			$tourOperatorId = Auth::user()->tour_operator_id;
		}

		$query = DB::table('tour_bookings_master AS tbm')
			->join('transactions AS t', 't.tour_booking_id', '=', 'tbm.id')
			->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
			->whereBetween('tbm.created_at', [$fromDate, $toDate])
			->where('tbm.status', '!=', 'Cancelled')
			->where('t.status', 'Completed')
			->groupBy('tbm.id')
			->orderByDesc('tbm.created_at');

		if ($tourPackageId && $tourPackageId !== 0) {
			$query = $query->where('tp.id', '=', $tourPackageId);
		}

		if ($tourOperatorId && $tourOperatorId !== 0) {
			$query = $query->where('tp.tour_operator_id', '=', $tourOperatorId);
		}

		$payments = $query->select([
			DB::raw('DATE_FORMAT(tbm.created_at, "%Y-%m-%d") as created_at'),
			'tbm.id as tour_booking_id',
			'tbm.total AS payable',
			DB::raw('SUM(t.amount) AS paid'),
			't.fee_amount as fees'
		]);

		$payments = $query->get()->toArray();

		$taxCollections = [];

		for ($a = 0, $ac = count($payments); $a < $ac; $a++) {
			$date = substr($payments[$a]->created_at, 0, 10);

			if (!isset($taxCollections[$date])) {
				$taxCollections[$date] = ['additionalTax' => 0, 'permitFee' => 0, 'stateTax' => 0, 'softwarefee' => 0, 'processing_charge_percentage' => 0];
				$fees = DB::table('tour_bookings_transact')
					->where('tour_booking_id', $payments[$a]->tour_booking_id)
					->get(['rate', 'additional_charge', 'tourists', 'additional_tax_percentage', 'permit_fee', 'additional_charge', 'processing_charge_percentage']);
			}

			if ($payments[$a]->payable > 0) {
				$payableToPaidRatio = $payments[$a]->payable / $payments[$a]->paid;
			}

			for ($b = 0, $bc = count($fees); $b < $bc; $b++) {
				$tourOpFee = ($fees[$b]->rate + $fees[$b]->additional_charge) * $fees[$b]->tourists;
				$taxCollections[$date]['processing_charge_percentage'] += $fees[$b]->processing_charge_percentage;
				$taxCollections[$date]['additionalTax'] = ($tourOpFee * $payableToPaidRatio) * ($fees[$b]->additional_tax_percentage / 100) + $taxCollections[$date]['additionalTax'];
				$taxCollections[$date]['permitFee'] = ($fees[$b]->permit_fee * $fees[$b]->tourists) + $taxCollections[$date]['permitFee'];
				$taxCollections[$date]['stateTax'] += $fees[$b]->additional_charge;
				$taxCollections[$date]['softwarefee'] += $payments[$a]->fees;
			}
		}

		// calculating totals here as JavaScript isn't reliable for rounding numbers
		$additionalTaxTotal = $permitFeeTotal = $stateTaxTotal = $processing_charge_percentage = 0;
		$softwarefee = 0;
		foreach ($taxCollections as $date => $taxes) {
			$taxCollections[$date]['processing_charge_percentage'] = sprintf('%.2f', $taxCollections[$date]['processing_charge_percentage']);
			$taxCollections[$date]['softwarefee'] = sprintf('%.2f', $taxCollections[$date]['softwarefee']);
			$taxCollections[$date]['additionalTax'] = sprintf('%.2f', $taxCollections[$date]['additionalTax']);
			$taxCollections[$date]['permitFee'] = sprintf('%.2f', $taxCollections[$date]['permitFee']);
			$taxCollections[$date]['stateTax'] = sprintf('%.2f', $taxCollections[$date]['stateTax']);
			$taxCollections[$date]['total'] = sprintf('%.2f',  $taxCollections[$date]['softwarefee'] + $taxCollections[$date]['additionalTax'] + $taxCollections[$date]['permitFee'] + $taxCollections[$date]['stateTax'] + $taxCollections[$date]['processing_charge_percentage']);
			$softwarefee += $taxCollections[$date]['softwarefee'];
			$processing_charge_percentage += $taxCollections[$date]['processing_charge_percentage'];
			$additionalTaxTotal += $taxCollections[$date]['additionalTax'];
			$permitFeeTotal += $taxCollections[$date]['permitFee'];
			$stateTaxTotal += $taxCollections[$date]['stateTax'];
		}

		return response()->json([
			'individual' => $taxCollections,
			'totals' => [
				'processing_charge_percentage' => sprintf('%.2f', $processing_charge_percentage),
				'softwarefee' => sprintf('%.2f', $softwarefee),
				'additionalTax' => sprintf('%.2f', $additionalTaxTotal),
				'permitFee' => sprintf('%.2f', $permitFeeTotal),
				'stateTax' => sprintf('%.2f', $stateTaxTotal),
				'all' => sprintf('%.2f', $additionalTaxTotal + $permitFeeTotal + $stateTaxTotal + $softwarefee + $processing_charge_percentage),
			]
		]);
	}

	/**
	 * Card Collection Report Download in CSV File
	 * 
	 * @param Request $request
	 */
	public function CashCardCollectionReportExportCsvFile(Request $request)
	{
		$tourOperatorId = $request->get('tourOperatorId');
		$ReportName = $request->get('ReportName');
		$fromDate = $request->get('currentDate');
		$toDate = $request->get('toDate');

		if ($fromDate == "")
			$fromDate = date('Y-m-d');
		else
			$fromDate = $fromDate . " 00:00:00";

		if ($toDate == "")
			$toDate = date('Y-m-d');
		else
			$toDate = $toDate . " 23:59:59";

		/* subquery start */
		$via = "Card";
		$refundTotalSql = 'select
			ifnull(sum(json_extract(`tbhh`.`details`, "$.amount")), 0) as amount
		from
			`tour_booking_history` as `tbhh`
			inner join `tour_bookings_master` as `tbmm` on `tbmm`.`id` = `tbhh`.`tour_booking_id`
			inner join `tour_slot_times` as `tstt` on `tstt`.`id` = `tbmm`.`tour_slot_id`
			inner join `tour_slot_dates` as `tsdd` on `tsdd`.`id` = `tstt`.`tour_slot_id`
			inner join `tour_packages` as `tpp` on `tpp`.`id` = `tbmm`.`tour_package_id`
		where
			(
				`tbhh`.`operation` = "Refund"
				or `tbhh`.`operation` = "Cancelled"
			)
			and JSON_CONTAINS(
				`tbhh`.`details`, json_quote("' . $via . '"), "$.payment_type"
			)
			and (
				not JSON_CONTAINS(
					`tbhh`.`details`, json_quote("Don\'t Refund"), "$.refund_type"
				)
				and not JSON_CONTAINS(
					`tbhh`.`details`, json_quote("don\'t refund"), "$.refund_type"
				)
				and not JSON_CONTAINS(
					`tbhh`.`details`, json_quote("fareharbor : don\'t refund"), "$.refund_type"
				)
			)
			and `tbmm`.`id` = `tbm`.`id`
			and `tbhh`.`operation_at` between  "' . $fromDate . '" and "' . $toDate . '" ';

		if ($request->has('tourPackageId')) {
			$refundTotalSql .= ' and `tpp`.`id` = "' . $request->tourPackageId . '" ';
		}

		if (session('userType') == 'Tour Operator') {
			$refundTotalSql .= ' and `tpp`.`tour_operator_id` = "' . Auth::user()->tour_operator_id . '" ';
		} else {
			$refundTotalSql .= ' and `tpp`.`tour_operator_id` = "' . $tourOperatorId . '" ';
		}
		/* subquery end */

		$totalSql = 'select
			`tbm`.`id` as `tour_booking_id`,
			`tbm`.`status` as `status`,
			`tbm`.`comments`,
			`tbm`.`created_by` as `createdBy`,
			`tbm`.`created_by_user_type` as `TourUserType`,
			`tbh`.`details`,
			SUM(JSON_EXTRACT(`tbh`.`details`, "$.amount")) as `completed_amount`,
			(' . $refundTotalSql . ') as `refund_amount`,
			DATE_FORMAT(`tbm`.`created_at`, "%Y-%m-%d") as `created_at`,
			`tbm`.`name` as `customer`,
			`tbm`.`email`,
			`tbm`.`phone_number`,
			`tst`.`time_from`,
			`tst`.`time_to`,
			`tsd`.`date`,
			`tbm`.`booking_total`
		from
			`tour_booking_history` as `tbh`
			inner join `tour_bookings_master` as `tbm` on `tbm`.`id` = `tbh`.`tour_booking_id`
			inner join `tour_slot_times` as `tst` on `tst`.`id` = `tbm`.`tour_slot_id`
			inner join `tour_slot_dates` as `tsd` on `tsd`.`id` = `tst`.`tour_slot_id`
			inner join `tour_packages` as `tp` on `tp`.`id` = `tbm`.`tour_package_id`
			inner join `customers` as `c` on `c`.`id` = `tbm`.`customer_id`';

		$totalSql .= ' where
			`tbh`.`operation` = "Payment"
			and JSON_EXTRACT(`tbh`.`details`, "$.payment_type") = "' . $via . '"
			 ';

		$paymentTotalSqlMain = $totalSql;

		if ($ReportName == 'Card Collection Report') {
			$paymentTotalSqlMain .= ' and `tsd`.`date` between "' . $request->currentDate . '" and "' . $request->toDate . '"';
		} else if ($ReportName == 'Stripe Payment Report') {
			$paymentTotalSqlMain .= ' and `tsd`.`operation_at` between "' . $fromDate . '" and "' . $toDate . '"';
		}

		if ($request->has('tourPackageId')) {
			$paymentTotalSqlMain .= ' and `tp`.`id` = "' . $request->tourPackageId . '" ';
		}

		if (session('userType') == 'Tour Operator') {
			$paymentTotalSqlMain .= ' and `tp`.`tour_operator_id` = "' . Auth::user()->tour_operator_id . '" ';
		} else {
			$paymentTotalSqlMain .= ' and `tp`.`tour_operator_id` = "' . $tourOperatorId . '" ';
		}

		$paymentTotalSql = $paymentTotalSqlMain . ' GROUP BY `tbm`.`id`';

		$paymentData = DB::select($paymentTotalSql);

		$paymentTotal = DB::select('select ifnull(sum(aps.completed_amount), 0) as alltotalamount from (' . $paymentTotalSql . ') as aps');

		$paymentTotalSqlMain .= ' and `tbm`.`comments` LIKE "%#%"';

		$farharborTotal = DB::select('select ifnull(sum(aps.completed_amount), 0) as allfarharbouramount from (' . $paymentTotalSqlMain . ') as aps');

		$refundTotalSql = 'select
			0 as completed_amount,
			`tbm`.`id` as `tour_booking_id`,
			`tbm`.`status` as `status`,
			`tbm`.`created_by` as `createdBy`,
			`tbh`.`details`,
			json_extract(`tbh`.`details`, "$.amount") as refund_amount,
			`tbm`.`created_by_user_type` as `TourUserType`,
			DATE_FORMAT(`tbm`.`created_at`, "%Y-%m-%d") as created_at,
			`tbm`.`name` as `customer`,
			`tbm`.`email`,
			`tbm`.`phone_number`,
			`tst`.`time_from`,
			`tst`.`time_to`,
			`tsd`.`date`,
			`tbm`.`booking_total`
		from
			`tour_booking_history` as `tbh`
			inner join `tour_bookings_master` as `tbm` on `tbm`.`id` = `tbh`.`tour_booking_id`
			inner join `tour_slot_times` as `tst` on `tst`.`id` = `tbm`.`tour_slot_id`
			inner join `tour_slot_dates` as `tsd` on `tsd`.`id` = `tst`.`tour_slot_id`
			inner join `tour_packages` as `tp` on `tp`.`id` = `tbm`.`tour_package_id`
			inner join `customers` as `c` on `c`.`id` = `tbm`.`customer_id`
		where
			(
				`tbh`.`operation` = "Refund"
				or `tbh`.`operation` = "Cancelled"
			)
			and JSON_CONTAINS(
				`tbh`.`details`, json_quote("' . $via . '"), "$.payment_type"
			)
			and (
				not JSON_CONTAINS(
					`tbh`.`details`, json_quote("Don\'t Refund"), "$.refund_type"
				)
				and not JSON_CONTAINS(
					`tbh`.`details`, json_quote("don\'t refund"), "$.refund_type"
				)
				and not JSON_CONTAINS(
					`tbh`.`details`, json_quote("fareharbor : don\'t refund"), "$.refund_type"
				)
			)
			and `tbh`.`operation_at` between  "' . $fromDate . '" and "' . $toDate . '" ';

		if ($request->has('tourPackageId')) {
			$refundTotalSql .= ' and `tp`.`id` = "' . $request->tourPackageId . '" ';
		}

		if (session('userType') == 'Tour Operator') {
			$refundTotalSql .= ' and `tp`.`tour_operator_id` = "' . Auth::user()->tour_operator_id . '" ';
		} else {
			$refundTotalSql .= ' and `tp`.`tour_operator_id` = "' . $tourOperatorId . '" ';
		}

		$refundTotalSqlbook = $refundTotalSql . ' GROUP BY `tbm`.`id`';

		$refundDatabooking = DB::select($refundTotalSqlbook);
		$allPaymentsdata = array_merge($paymentData, $refundDatabooking);

		$refundTotal = DB::select('select ifnull(sum(aps.refund_amount), 0) as allrefundamount from (' . $refundTotalSql . ') as aps');

		$finalTotal = $paymentTotal[0]->alltotalamount - $refundTotal[0]->allrefundamount;

		$result = $allPaymentsdata;
		$alltotalamount = $paymentTotal[0]->alltotalamount;
		$allfarharbouramount = $farharborTotal[0]->allfarharbouramount;
		$allrefundamount = $refundTotal[0]->allrefundamount;
		$allpaymentamount = $finalTotal;

		if ($ReportName == 'Card Collection Report') {
			$fileName = 'card_collection_report' . $request->get('currentDate') . '_' . $request->get('toDate') . '.csv';
			$columns = csvColumns('cash_card_collection_report_columns');
			$columnsOverallAmount = csvColumns('cash_card_collection_report_columnsHeader_overall');
		} else if ($ReportName == 'Stripe Payment Report') {
			$fileName = 'stripe_payment_report' . $request->get('currentDate') . '_' . $request->get('toDate') . '.csv';
			$columns = csvColumns('stripe_collection_report_columns');
			$columnsOverallAmount = csvColumns('cash_card_collection_report_columnsHeader_overall');
		}

		$headers = csvHeaders($fileName);

		$callback = function () use ($result, $columns, $columnsOverallAmount, $fromDate, $toDate, $alltotalamount, $allfarharbouramount, $allpaymentamount, $allrefundamount, $ReportName) {

			$file = fopen('php://output', 'w');  // Create a file
			fputcsv($file, $columnsOverallAmount);
			$DateRange = date("m-d-Y", strtotime($fromDate)) . ' - ' . date("m-d-Y", strtotime($toDate));
			$CreatedDate = date("m-d-Y");

			$TotalPaymentAmount = sprintf('%.2f', $allpaymentamount);
			$TotalRefundAmount = sprintf('%.2f', $allrefundamount);
			if ($ReportName == 'Card Collection Report') {
				$TotalAmount = sprintf('%.2f', $alltotalamount - $allfarharbouramount);
				$TotalFarharborAmount = sprintf('%.2f', $allfarharbouramount);
				fputcsv($file, array($DateRange, $CreatedDate, $TotalAmount, $TotalFarharborAmount, $TotalRefundAmount, $TotalPaymentAmount));
			} else {
				$TotalAmount = sprintf('%.2f', $alltotalamount - $allfarharbouramount);
				$TotalFarharborAmount = sprintf('%.2f', $allfarharbouramount);
				fputcsv($file, array($DateRange, $CreatedDate, $TotalAmount, $TotalFarharborAmount, $TotalRefundAmount, $TotalPaymentAmount));
			}

			$BlankLine = '';
			fputcsv($file, array($BlankLine));
			fputcsv($file, $columns);
			foreach ($result as $each_data) {
				$row['Booking Id'] = (!empty($each_data)) ? ((isset($each_data->tour_booking_id)) ? $each_data->tour_booking_id : '') : '';
				$row['Booking Date'] = (!empty($each_data)) ? ((isset($each_data->date)) ? date("m-d-Y", strtotime($each_data->date)) . " @ " . date("g:i A", strtotime($each_data->time_from)) : '') : '';
				$row['Created Date'] = (!empty($each_data)) ? ((isset($each_data->created_at)) ? date("m-d-Y", strtotime($each_data->created_at)) : '') : '';
				$row['Via'] = (!empty($each_data)) ? ((isset($each_data->via)) ? $each_data->via : '') : '';
				$row['Booking Amount'] = (!empty($each_data)) ? ((isset($each_data->completed_amount)) ? $each_data->completed_amount : $each_data->completed_amount) : '';
				$row['Refund Amount'] = (!empty($each_data)) ? ((isset($each_data->refund_amount)) ? $each_data->refund_amount : '') : '';
				$row['Payment Amount'] = (!empty($each_data)) ? ((isset($each_data->completed_amount) && isset($each_data->refund_amount)) ? $each_data->completed_amount - $each_data->refund_amount : '') : '';
				$row['Customer'] = (!empty($each_data)) ? ((isset($each_data->customer)) ? $each_data->customer : '') : '';

				if ($ReportName == 'Card Collection Report') {
					$row['Phone Number'] = (!empty($each_data)) ? ((isset($each_data->comments)) ? $each_data->comments : '') : '';
				} else {
					$row['Phone Number'] = (!empty($each_data)) ? ((isset($each_data->phone_number)) ? $each_data->phone_number : '') : '';
				}

				$row['Booking Status'] = (!empty($each_data)) ? ((isset($each_data->status)) ? $each_data->status : '') : '';

				fputcsv($file, array(
					$row['Booking Id'],
					$row['Booking Date'],
					$row['Created Date'],
					$row['Via'],
					$row['Booking Amount'],
					$row['Refund Amount'],
					$row['Payment Amount'],
					$row['Customer'],
					$row['Phone Number'],
					$row['Booking Status']
				));
			}
			fclose($file);
		};

		return response()->stream($callback, 200, $headers);
	}

	/**
	 * Cash Collection Report Download in CSV File
	 * 
	 * @param Request $request
	 */
	public function CashCollectionReportExportCsvFile(Request $request)
	{
		$fromDate = $request->get('currentDate');
		$toDate = $request->get('toDate');

		if ($fromDate == "")
			$fromDate = date('Y-m-d');
		else
			$fromDate = $fromDate . " 00:00:00";

		if ($toDate == "")
			$toDate = date('Y-m-d');
		else
			$toDate = $toDate . " 23:59:59";

		$ReportName = $request->get('ReportName');

		if ($ReportName == 'Cash Collection Report')
			$Via = 'Cash';

		if (session('userType') == 'Admin') {
			$tourOperatorId = $request->get('tourOperatorId');
		} elseif (session('userType') == 'Tour Operator') {
			$tourOperatorId = Auth::user()->tour_operator_id;
		} else {
			$tourOperatorId = Auth::user()->tour_operator_id;
		}

		try {
			//Query for All Refunds
			$a = DB::table('tour_booking_history AS tbh')
				->join('tour_bookings_master as tbm', 'tbm.id', '=', 'tbh.tour_booking_id')
				->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
				->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
				->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
				->leftJoin('tour_operator_staff AS tos', 'tos.id', '=', 'tbh.operation_by')
				->where(function ($query) {
					return $query->where('tbh.operation', '=', 'Refund')
						->orWhere('tbh.operation', '=', 'Cancelled');
				})
				->whereJsonContains('tbh.details->payment_type', $Via)
				->where(function ($query) {
					return $query->whereJsonDoesntContain('tbh.details->refund_type', "Don't Refund")
						->whereJsonDoesntContain('tbh.details->refund_type', "don't refund")
						->whereJsonDoesntContain('tbh.details->refund_type', "fareharbor : don't refund");
				})
				->whereBetween('tbh.operation_at', [$fromDate, $toDate])
				->select(
					'tbm.id as tour_booking_id',
					'tbm.status as status',
					'tbh.operation_by as createdBy',
					'tbh.details',
					DB::raw('DATE_FORMAT(tbm.created_at, "%Y-%m-%d") as created_at'),
					DB::raw("CONCAT(tos.first_name,' ',tos.last_name) as tour_operator_staff_name"),
					'tbm.name AS customer',
					'tbm.email',
					'tbm.phone_number',
					'tst.time_from',
					'tst.time_to',
					'tsd.date',
					'tbm.booking_total',
				);

			if ($request->has('tourPackageId')) {
				$a = $a->where('tp.id', $request->get('tourPackageId'));
			}

			if (session('userType') == 'Tour Operator') {
				$a = $a->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
			} else {
				$a = $a->where('tp.tour_operator_id', '=', $tourOperatorId);
			}

			$a = $a->get();

			$refunds = array();
			if ($a) {
				foreach ($a as $v) {
					$details = json_decode($v->details, true);
					$refunds[$v->createdBy][$v->tour_booking_id]['tour_booking_id'] = $v->tour_booking_id;
					$refunds[$v->createdBy][$v->tour_booking_id]['completed_amount'] = 0;
					$refunds[$v->createdBy][$v->tour_booking_id]['refund_amount'] = (isset($refunds[$v->createdBy][$v->tour_booking_id]['refund_amount'])) ? $refunds[$v->createdBy][$v->tour_booking_id]['refund_amount'] + $details['amount'] : $details['amount'];
					$refunds[$v->createdBy][$v->tour_booking_id]['status'] = $v->status;
					$refunds[$v->createdBy][$v->tour_booking_id]['createdBy'] = $v->createdBy;
					$refunds[$v->createdBy][$v->tour_booking_id]['created_at'] = $v->created_at;
					$refunds[$v->createdBy][$v->tour_booking_id]['tour_operator_staff_name'] = $v->tour_operator_staff_name;
					$refunds[$v->createdBy][$v->tour_booking_id]['customer'] = $v->customer;
					$refunds[$v->createdBy][$v->tour_booking_id]['email'] = $v->email;
					$refunds[$v->createdBy][$v->tour_booking_id]['phone_number'] = $v->phone_number;
					$refunds[$v->createdBy][$v->tour_booking_id]['time_from'] = $v->time_from;
					$refunds[$v->createdBy][$v->tour_booking_id]['time_to'] = $v->time_to;
					$refunds[$v->createdBy][$v->tour_booking_id]['date'] = $v->date;
					$refunds[$v->createdBy][$v->tour_booking_id]['via'] = "Cash";
					$refunds[$v->createdBy][$v->tour_booking_id]['details'] = $v->details;
					$refunds[$v->createdBy][$v->tour_booking_id]['booking_total'] = $v->booking_total;
				}
			}

			//Query for All Payments
			$b = DB::table('transactions AS t')
				->join('tour_bookings_master as tbm', 'tbm.id', '=', 't.tour_booking_id')
				->join('tour_booking_history AS tbh', 'tbh.tour_booking_id', '=', 't.tour_booking_id')
				->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
				->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
				->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
				->join('tour_operator_staff AS tos', 'tos.id', '=', 'tbh.operation_by')
				->where('t.status', 'Completed')
				->where('tbh.operation', 'Payment')
				->where('t.via', $Via)
				->whereJsonContains('tbh.details->payment_type', $Via)
				->whereBetween('tbh.operation_at', [$fromDate, $toDate])
				->select(
					'tbm.id as tour_booking_id',
					'tbm.status as status',
					'tbm.comments',
					'tbh.operation_by as createdBy',
					'tbh.details',
					't.transaction_number',
					't.via',
					DB::raw('DATE_FORMAT(tbm.created_at, "%Y-%m-%d") as created_at'),
					DB::raw("CONCAT(tos.first_name,' ',tos.last_name) as tour_operator_staff_name"),
					'tbm.name AS customer',
					'tbm.email',
					'tbm.phone_number',
					'tst.time_from',
					'tst.time_to',
					'tsd.date',
					'tbm.booking_total',
				);

			if ($request->has('tourPackageId')) {
				$b = $b->where('tp.id', $request->get('tourPackageId'));
			}

			if (session('userType') == 'Tour Operator') {
				$b = $b->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
			} else {
				$b = $b->where('tp.tour_operator_id', '=', $tourOperatorId);
			}

			$b = $b->distinct()->get();

			$result = [];
			$totalamount = 0;
			$totalrefundamount = 0;
			$alltotalamount = 0;
			$allrefundamount = 0;
			$allpaymentamount = 0;
			$allfarharbouramount = 0;
			$paymentsResult = $b;
			$payments = array();
			$paymentamount = 0;
			foreach ($b as $key => $element) {
				$details = json_decode($element->details, true);
				$paymentamount += $details['amount'];
				if (isset($element->comments) && $element->comments != '') {
					if (Str::contains($element->comments, "#")) {
						$allfarharbouramount += $details['amount'];
					}
				}

				$payments[$element->createdBy][$element->tour_booking_id]['tour_booking_id'] = $element->tour_booking_id;
				$payments[$element->createdBy][$element->tour_booking_id]['completed_amount'] = (isset($payments[$element->createdBy][$element->tour_booking_id]['completed_amount'])) ? $payments[$element->createdBy][$element->tour_booking_id]['completed_amount'] + $details['amount'] : $details['amount'];
				$payments[$element->createdBy][$element->tour_booking_id]['status'] = $element->status;
				$payments[$element->createdBy][$element->tour_booking_id]['createdBy'] = $element->createdBy;
				$payments[$element->createdBy][$element->tour_booking_id]['created_at'] = $element->created_at;
				$payments[$element->createdBy][$element->tour_booking_id]['tour_operator_staff_name'] = $element->tour_operator_staff_name;
				$payments[$element->createdBy][$element->tour_booking_id]['customer'] = $element->customer;
				$payments[$element->createdBy][$element->tour_booking_id]['email'] = $element->email;
				$payments[$element->createdBy][$element->tour_booking_id]['phone_number'] = $element->phone_number;
				$payments[$element->createdBy][$element->tour_booking_id]['time_from'] = $element->time_from;
				$payments[$element->createdBy][$element->tour_booking_id]['time_to'] = $element->time_to;
				$payments[$element->createdBy][$element->tour_booking_id]['date'] = $element->date;
				$payments[$element->createdBy][$element->tour_booking_id]['via'] = "Cash";
				$payments[$element->createdBy][$element->tour_booking_id]['details'] = $element->details;
				$payments[$element->createdBy][$element->tour_booking_id]['comments'] = $element->comments;
				$payments[$element->createdBy][$element->tour_booking_id]['booking_total'] = $element->booking_total;
				if (isset($refunds[$element->createdBy][$element->tour_booking_id]['refund_amount'])) {
					$payments[$element->createdBy][$element->tour_booking_id]['refund_amount'] = $refunds[$element->createdBy][$element->tour_booking_id]['refund_amount'];
					unset($refunds[$element->createdBy][$element->tour_booking_id]);
				} else {
					if (!isset($payments[$element->createdBy][$element->tour_booking_id]['refund_amount'])) {
						$payments[$element->createdBy][$element->tour_booking_id]['refund_amount'] = 0.00;
					}
				}
			}

			$paymentsResult = array();
			if (!empty($payments)) {
				foreach ($payments as $key => $value) {
					if (isset($refunds[$key])) {
						$paymentsResult[] = array_merge((array)$refunds[$key], (array)$value);
						unset($refunds[$key]);
					} else {
						$paymentsResult[] = (array)$value;
					}
				}
			}

			$paymentsResult = array_merge($paymentsResult, $refunds);
			foreach ($paymentsResult as $key => $element) {
				$result[$key]['total'] = 0;
				$result[$key]['totalrefund'] = 0;
				$result[$key]['totalpayment'] = 0;
				foreach ($element as $item => $value) {
					$completed_amount = 0;
					if ($value['booking_total'] != "0.00" || $value['booking_total'] != 0) {
						$completed_amount = $value['booking_total'];
					} else {
						$completed_amount = $value['completed_amount'];
					}

					$totalamount = $value['completed_amount'];
					$totalrefundamount = $value['refund_amount'];
					$allrefundamount = $allrefundamount + $value['refund_amount'];
					$alltotalamount +=  $completed_amount;
					$allpaymentamount = $paymentamount - $allrefundamount;
					$value['time_from'] = date("g:i A", strtotime($value['time_from']));
					$value['time_to'] = date("g:i A", strtotime($value['time_to']));
					$details = json_decode($value['details'], true);

					$result[$key]['amount'] = $totalamount;
					$result[$key]['total'] += $value['completed_amount'];
					$result[$key]['totalrefund'] += $totalrefundamount;
					$result[$key]['totalpayment'] += ($value['completed_amount'] - $totalrefundamount);
					$result[$key]['name'] = $details['created_by_with_name'];
					$result[$key]['data'][] = $value;
				}
			}

			$fileName = 'cashcollectionreport_' . $request->get('currentDate') . '_' . $request->get('toDate') . '.csv';

			$headers = csvHeaders($fileName);
			$columnsHeader = csvColumns('cash_collection_report_columnsHeader');
			$columns = csvColumns('cash_collection_report_columns');
			$columnsOverallAmount = csvColumns('cash_card_collection_report_columnsHeader_overall');

			$callback = function () use ($result, $columns, $columnsHeader, $columnsOverallAmount, $fromDate, $toDate, $alltotalamount, $allfarharbouramount, $allpaymentamount, $allrefundamount) {
				$BlankLine = '';
				// Create a file
				$file = fopen('php://output', 'w');
				fputcsv($file, $columnsOverallAmount);
				$DateRange = date("m-d-Y", strtotime($fromDate)) . ' - ' . date("m-d-Y", strtotime($toDate));
				$CreatedDate = date("m-d-Y");
				$TotalAmount = sprintf('%.2f', ($alltotalamount - $allfarharbouramount));
				$TotalPaymentAmount = sprintf('%.2f', $allpaymentamount);
				$TotalRefundAmount = sprintf('%.2f', $allrefundamount);
				$TotalFarharborAmount = sprintf('%.2f', $allfarharbouramount);
				fputcsv($file, array($DateRange, $CreatedDate, $TotalAmount, $TotalFarharborAmount, $TotalRefundAmount, $TotalPaymentAmount));
				$BlankLine = '';
				fputcsv($file, array($BlankLine));

				foreach ($result as $result_data) {
					fputcsv($file, $columnsHeader);

					$row['tourOperatorStaffName'] = (!empty($result_data)) ? ((isset($result_data['name'])) ? $result_data['name'] : '') : '';
					$row['totalAmount'] = (!empty($result_data)) ? ((isset($result_data['total'])) ? $result_data['total'] : '') : '';
					$row['totalRefund'] = (!empty($result_data)) ? ((isset($result_data['totalrefund'])) ? $result_data['totalrefund'] : '') : '';
					$row['totalPayment'] = (!empty($result_data)) ? ((isset($result_data['totalpayment'])) ? $result_data['totalpayment'] : '') : '';

					fputcsv($file, array(
						$row['tourOperatorStaffName'],
						$row['totalAmount'],
						$row['totalRefund'],
						$row['totalPayment']
					));

					fputcsv($file, $columns);
					$arraydata = $result_data['data'];
					foreach ($arraydata as $each_data) {
						$remaining_amount = 0;
						$remaining_amount = $each_data['completed_amount'] - $each_data['refund_amount'];
						$row['Booking Id'] = isset($each_data['tour_booking_id']) ? $each_data['tour_booking_id'] : '';
						$row['Booking Date'] = isset($each_data['date']) ? date("m-d-Y", strtotime($each_data['date'])) . " @ " . date("g:i A", strtotime($each_data['time_from'])) : '';
						$row['Created Date'] = isset($each_data['created_at']) ? date("m-d-Y", strtotime($each_data['created_at'])) : '';
						$row['Via'] = isset($each_data['via']) ? $each_data['via'] : 'Cash';
						$row['Amount'] = isset($each_data['booking_total']) ? $each_data['booking_total'] : $each_data['completed_amount'];
						$row['Refund Amount'] = isset($each_data['refund_amount']) ? $each_data['refund_amount'] : '';
						$row['Remaining Amount'] = number_format((float)$remaining_amount, 2, '.', '');
						$row['Customer'] = isset($each_data['customer']) ? $each_data['customer'] : '';
						$row['Comments'] = isset($each_data['comments']) ? $each_data['comments'] : '';
						$row['Booking Status'] = isset($each_data['status']) ? $each_data['status'] : '';

						fputcsv($file, array(
							$row['Booking Id'],
							$row['Booking Date'],
							$row['Created Date'],
							$row['Via'],
							$row['Amount'],
							$row['Refund Amount'],
							$row['Remaining Amount'],
							$row['Customer'],
							$row['Comments'],
							$row['Booking Status']
						));
					}

					fputcsv($file, array(
						$BlankLine,
						$BlankLine,
						$BlankLine,
						$BlankLine,
						$BlankLine,
						$BlankLine,
						$BlankLine,
						$BlankLine,
						$BlankLine
					));
				}

				fclose($file);
			};
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to get data of payments.');
		}

		return response()->stream($callback, 200, $headers);
	}

	/**
	 * Wire Collection Report Download in CSV File
	 * 
	 * @param Request $request
	 */
	public function WireCollectionReportExportCsvFile(Request $request)
	{
		$fromDate = $request->get('currentDate');
		$toDate = $request->get('toDate');

		if ($fromDate == "")
			$fromDate = date('Y-m-d');
		else
			$fromDate = $fromDate . " 00:00:00";

		if ($toDate == "")
			$toDate = date('Y-m-d');
		else
			$toDate = $toDate . " 23:59:59";

		$ReportName = $request->get('ReportName');
		$Via = 'Check/Wire';
		if (session('userType') == 'Admin') {
			$tourOperatorId = $request->get('tourOperatorId');
		} elseif (session('userType') == 'Tour Operator') {
			$tourOperatorId = Auth::user()->tour_operator_id;
		} else {
			$tourOperatorId = Auth::user()->tour_operator_id;
		}

		//Query for All Refunds
		$a = DB::table('tour_booking_history AS tbh')
			->join('tour_bookings_master as tbm', 'tbm.id', '=', 'tbh.tour_booking_id')
			->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
			->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
			->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
			->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
			->where(function ($query) {
				return $query->where('tbh.operation', '=', 'Refund')
					->orWhere('tbh.operation', '=', 'Cancelled');
			})
			->whereJsonContains('tbh.details->payment_type', $Via)
			->where(function ($query) {
				return $query->whereJsonDoesntContain('tbh.details->refund_type', "Don't Refund")
					->whereJsonDoesntContain('tbh.details->refund_type', "don't refund")
					->whereJsonDoesntContain('tbh.details->refund_type', "fareharbor : don't refund");
			})
			->whereBetween('tbh.operation_at', [$fromDate, $toDate])
			->select(
				'tbm.id as tour_booking_id',
				'tbm.status as status',
				'tbm.created_by as createdBy',
				'tbh.details',
				'tbm.created_by_user_type as TourUserType',
				DB::raw('DATE_FORMAT(tbm.created_at, "%Y-%m-%d") as created_at'),
				'tbm.name AS customer',
				'tbm.email',
				'tbm.phone_number',
				'tst.time_from',
				'tst.time_to',
				'tsd.date'
			);
		if ($request->has('tourPackageId')) {
			$a = $a->where('tp.id', $request->get('tourPackageId'));
		}

		if (session('userType') == 'Tour Operator') {
			$a = $a->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
		} else {
			$a = $a->where('tp.tour_operator_id', '=', $tourOperatorId);
		}

		$a = $a->get();

		$refunds = array();

		if ($a) {
			foreach ($a as $k => $v) {

				$details = json_decode($v->details, true);
				$refunds[$k]['booking_id'] = $v->tour_booking_id;
				$refunds[$k]['refund_amount'] = $details['amount'];
			}
		}

		$refundarray = array_reduce($refunds, function ($carry, $item) {
			$carry[$item['booking_id']] = $carry[$item['booking_id']] ?? 0;
			$carry[$item['booking_id']] += $item['refund_amount'];
			return $carry;
		}, []);

		//Query for All Payments
		$b = DB::table('transactions AS t')
			->join('tour_bookings_master as tbm', 'tbm.id', '=', 't.tour_booking_id')
			->join('tour_booking_history AS tbh', 'tbh.tour_booking_id', '=', 't.tour_booking_id')
			->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
			->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
			->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
			->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
			->where('t.status', 'Completed')
			->where('tbh.operation', 'Payment')
			->where('t.via', $Via);

		$b->whereBetween('tbh.operation_at', [$fromDate, $toDate]);

		$b->select(
			'tbm.id as tour_booking_id',
			'tbm.status as status',
			'tbm.created_by as createdBy',
			'tbm.comments',
			'tbm.created_by_user_type as TourUserType',
			'tbh.details',
			't.transaction_number',
			't.via',
			DB::raw('DATE_FORMAT(tbm.created_at, "%Y-%m-%d") as created_at'),
			'tbm.name AS customer',
			'tbm.email',
			'tbm.phone_number',
			'tst.time_from',
			'tst.time_to',
			'tsd.date'
		);

		if ($request->has('tourPackageId')) {
			$b = $b->where('tp.id', $request->get('tourPackageId'));
		}

		if (session('userType') == 'Tour Operator') {
			$b = $b->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
		} else {
			$b = $b->where('tp.tour_operator_id', '=', $tourOperatorId);
		}

		$b = $b->get();

		$result = [];
		$alltotalamount = 0;
		$allrefundamount = 0;
		$allpaymentamount = 0;
		$paymentsResult = $b;
		$allfarharbouramount = 0;
		foreach ($paymentsResult as $key => $element) {
			if (isset($refundarray[$element->tour_booking_id])) {
				$paymentsResult[$key]->refund_amount = $refundarray[$element->tour_booking_id];
				unset($refundarray[$element->tour_booking_id]);
			} else {
				$paymentsResult[$key]->refund_amount = 0.00;
			}

			$details = json_decode($element->details, true);
			if ($element->comments != '') {
				if (Str::contains($element->comments, "#")) {
					$allfarharbouramount +=	$details['amount'];
				}
			}

			$paymentsResult[$key]->completed_amount = (isset($details['amount']) && $details['amount'] > 0) ? $details['amount'] : 0.00;
		}

		$onlyrefundsarray = array();
		// Query for which are only refunds
		if (!empty($refundarray)) {
			foreach ($refundarray as $k => $val) {
				$transactionhistory = DB::table('tour_booking_history AS tbh')
					->join('tour_bookings_master as tbm', 'tbm.id', '=', 'tbh.tour_booking_id')
					->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
					->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
					->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
					->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
					->where('tbm.id', $k)
					->where(function ($query) {
						return $query->where('tbh.operation', '=', 'Refund')
							->orWhere('tbh.operation', '=', 'Cancelled');
					})
					->whereJsonContains('tbh.details->payment_type', $Via)
					->whereBetween('tbh.operation_at', [$fromDate, $toDate])
					->select(
						'tbm.id as tour_booking_id',
						'tbm.status as status',
						'tbm.created_by as createdBy',
						'tbm.created_by_user_type as TourUserType',
						'tbh.details',
						DB::raw('DATE_FORMAT(tbm.created_at, "%Y-%m-%d") as created_at'),
						'tbm.name AS customer',
						'tbm.email',
						'tbm.phone_number',
						'tst.time_from',
						'tst.time_to',
						'tsd.date'
					)->first();

				$transactionhistory->refund_amount = $val;
				$transactionhistory->completed_amount = 0;
				$transactionhistory->via = "Wire Transfer";
				$onlyrefundsarray[] = $transactionhistory;
			}
		}

		$merged = $paymentsResult->merge($onlyrefundsarray);

		$paymentsResult = $merged->all();

		foreach ($paymentsResult as $element) {
			$allrefundamount = $allrefundamount + $element->refund_amount;
			$alltotalamount = $alltotalamount + $element->completed_amount;
			$allpaymentamount = $alltotalamount - $allrefundamount;
			$element->time_from = date("g:i A", strtotime($element->time_from));
			$element->time_to = date("g:i A", strtotime($element->time_to));
			$element->payment_amount = $element->completed_amount - $element->refund_amount;
			$result[] = $element;
		}

		$fileName = 'wire_collection_report' . $request->get('currentDate') . '_' . $request->get('toDate') . '.csv';
		$columns = csvColumns('wire_collection_report_columns');
		$columnsOverallAmount = csvColumns('wire_collection_report_columnsHeader_overall');

		$headers = csvHeaders($fileName);

		$callback = function () use ($result, $columns, $columnsOverallAmount, $fromDate, $toDate, $alltotalamount, $allfarharbouramount, $allpaymentamount, $allrefundamount, $ReportName) {

			$file = fopen('php://output', 'w');  // Create a file
			fputcsv($file, $columnsOverallAmount);
			$DateRange = date("m-d-Y", strtotime($fromDate)) . ' - ' . date("m-d-Y", strtotime($toDate));
			$CreatedDate = date("m-d-Y");

			$TotalPaymentAmount = sprintf('%.2f', $allpaymentamount);
			$TotalRefundAmount = sprintf('%.2f', $allrefundamount);
			$TotalAmount = sprintf('%.2f', $alltotalamount - $allfarharbouramount);
			$TotalFarharborAmount = sprintf('%.2f', $allfarharbouramount);
			fputcsv($file, array($DateRange, $CreatedDate, $TotalAmount, $TotalFarharborAmount, $TotalRefundAmount, $TotalPaymentAmount));

			$BlankLine = '';
			fputcsv($file, array($BlankLine));
			fputcsv($file, $columns);
			foreach ($result as $each_data) {
				$row['Booking Id'] = (!empty($each_data)) ? ((isset($each_data->tour_booking_id)) ? $each_data->tour_booking_id : '') : '';
				$row['Booking Date'] = (!empty($each_data)) ? ((isset($each_data->date)) ? date("m-d-Y", strtotime($each_data->date)) . " @ " . date("g:i A", strtotime($each_data->time_from)) : '') : '';
				$row['Created Date'] = (!empty($each_data)) ? ((isset($each_data->created_at)) ? date("m-d-Y", strtotime($each_data->created_at)) : '') : '';
				$row['Via'] = (!empty($each_data)) ? ((isset($each_data->via)) ? $each_data->via : '') : '';
				$row['Booking Amount'] = (!empty($each_data)) ? ((isset($each_data->completed_amount)) ? $each_data->completed_amount : '') : '';
				$row['Refund Amount'] = (!empty($each_data)) ? ((isset($each_data->refund_amount)) ? $each_data->refund_amount : '') : '';
				$row['Payment Amount'] = (!empty($each_data)) ? ((isset($each_data->payment_amount)) ? $each_data->payment_amount : '') : '';
				$row['Customer'] = (!empty($each_data)) ? ((isset($each_data->customer)) ? $each_data->customer : '') : '';

				if ($ReportName == 'Card Collection Report') {
					$row['Phone Number'] = (!empty($each_data)) ? ((isset($each_data->comments)) ? $each_data->comments : '') : '';
				} else {
					$row['Phone Number'] = (!empty($each_data)) ? ((isset($each_data->phone_number)) ? $each_data->phone_number : '') : '';
				}

				$row['Booking Status'] = (!empty($each_data)) ? ((isset($each_data->status)) ? $each_data->status : '') : '';

				fputcsv($file, array(
					$row['Booking Id'],
					$row['Booking Date'],
					$row['Created Date'],
					$row['Via'],
					$row['Booking Amount'],
					$row['Refund Amount'],
					$row['Payment Amount'],
					$row['Customer'],
					$row['Phone Number'],
					$row['Booking Status']
				));
			}

			fclose($file);
		};

		return response()->stream($callback, 200, $headers);
	}

	/**
	 * Refund Payment Report Download in CSV File
	 * 
	 * @param Request $request
	 */
	public function RefundPaymentReportExportCsvFile(Request $request)
	{
		$fromDate = $request->get('fromDate');
		$toDate = $request->get('toDate');
		$tourPackageId = $request->get('tourPackageId');

		if ($fromDate == "")
			$fromDate = date('Y-m-d');
		else
			$fromDate = $fromDate . " 00:00:00";

		if ($toDate == "")
			$toDate = date('Y-m-d');
		else
			$toDate = $toDate . " 23:59:59";

		if (session('userType') == 'Admin') {
			$tourOperatorId = $request->get('tourOperatorId');
		} elseif (session('userType') == 'Tour Operator') {
			$tourOperatorId = Auth::user()->tour_operator_id;
		} else {
			$tourOperatorId = Auth::user()->tour_operator_id;
		}

		$query =  DB::table('tour_bookings_master AS tbm')
			->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
			->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
			->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
			->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
			->join('tour_operators as to', 'to.id', '=', 'tp.tour_operator_id')
			->leftJoin('transactions as t', 't.tour_booking_id', '=', 'tbm.id')
			->leftjoin('tour_booking_history as tbh', 'tbh.tour_booking_id', '=', 'tbm.id')
			->where('t.status', 'Completed')
			->whereIn('tbm.status', ['Booked', 'Cancelled'])
			->whereBetween('tsd.date', [$fromDate, $toDate])
			->where('tbh.operation', 'Payment')
			->select([
				'tbm.id as tbmId',
				'c.name as name',
				'c.phone_number as phone',
				'c.email as email',
				'tbm.tourists as tbmtourists',
				'tbm.total as amount',
				'tbm.status as booking_status',
				'tsd.date as bookingdate',
				'tst.time_slot_seats as seats',
				'tp.id as packageId',
				't.status as payment_status',
				'tst.time_from',
				'tbh.details',
				'tbh.operation_at',
				'tbh.tour_booking_id',
				'tbh.operation',
				't.via'
			])
			->orderBy('bookingdate', 'DESC');

		if ($tourPackageId && $tourPackageId !== 0) {
			$query = $query->where('tbm.tour_package_id', '=', $tourPackageId);
		}

		if (session('userType') == 'Tour Operator') {
			$query = $query->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
		} else {
			$query = $query->where('tp.tour_operator_id', '=', $tourOperatorId);
		}

		$result = $query->distinct()->get();

		foreach ($result as $key => $value) {
			foreach ($result as $keys => $values) {
				if ($key != $keys && $value->tour_booking_id == $values->tour_booking_id) {
					unset($result[$key]);
				}
			}
		}

		$totalAmount = 0;
		$sortedDataArray = array();
		foreach ($result as $key => $value) {
			$refundAmountVal = 0;
			$historyData = DB::table('tour_booking_history as tbh')
				->select('tbh.id as tbhID', 'tbh.tour_booking_id as tbhBookingID', 'tbh.operation as operation', 'tbh.details as details', 'tbh.operation_at')
				->where('tbh.operation_at', '>', $value->operation_at)
				->where('tbh.tour_booking_id', '=', $value->tbmId)
				->where(function ($query) {
					return $query->where('tbh.operation', '=', 'Refund')
						->orWhere('tbh.operation', '=', 'Cancelled');
				})
				->where(function ($query) {
					return $query->whereJsonDoesntContain('tbh.details->refund_type', "Don't Refund")
						->whereJsonDoesntContain('tbh.details->refund_type', "don't refund")
						->whereJsonDoesntContain('tbh.details->refund_type', "fareharbor : don't refund");
				})
				->get();

			$refundAmountDetails = array();
			if (count($historyData) > 0) {
				foreach ($historyData as $keys => $refund) {
					$refundDateValue = date("m/d/Y", strtotime($refund->operation_at));
					$details = json_decode($refund->details, true);
					if ($details['amount'] != 0) {
						$refundAmountDetails[$keys]['amount'] = (float)$details['amount'];
						$refundAmountDetails[$keys]['payment_type'] = (isset($details['payment_type'])) ? $details['payment_type'] : $value->via;
						$refundAmountDetails[$keys]['refund_type'] = (isset($details['refund_type'])) ? $details['refund_type'] : "";
					}

					$refundAmountVal = $refundAmountVal + (float)$details['amount'];
				}

				$newFilteredData = array();
				$newFilteredData['bookingId'] = $value->tbmId;
				$newFilteredData['name'] = $value->name;
				$newFilteredData['bookingdate'] = date("m/d/Y", strtotime($value->bookingdate));
				$newFilteredData['time_from'] = date("g:i A", strtotime($value->time_from));
				$newFilteredData['phone'] = $value->phone;
				$newFilteredData['email'] = $value->email;
				$newFilteredData['booking_status'] = $value->booking_status;
				$newFilteredData['amount'] = number_format((float)$refundAmountVal, 2);;
				$newFilteredData['refund'] = $refundAmountDetails;
				$newFilteredData['refunddate'] = $refundDateValue;
				$sortedDataArray[] = $newFilteredData;
				$totalAmount += $newFilteredData['amount'];
			}
		}

		$fileName = 'refund_payment_report' . $request->get('fromDate') . '_' . $request->get('toDate') . '.csv';

		ob_clean();
		$headers = csvHeaders($fileName);
		$columnsHeader = csvColumns('refund_payment_report_TopColumns');
		$columns = csvColumns('refund_payment_report_columns');

		$callback = function () use ($sortedDataArray, $columns, $columnsHeader, $fromDate, $toDate, $totalAmount) {

			$file = fopen('php://output', 'w');  // Create a file

			fputcsv($file, $columnsHeader);

			$DateRange = date("m-d-Y", strtotime($fromDate)) . ' - ' . date("m-d-Y", strtotime($toDate));
			$CreatedDate = date("m-d-Y");
			$TotalAmount = sprintf('%.2f', $totalAmount);
			fputcsv($file, array($DateRange, $CreatedDate, $TotalAmount));

			$BlankLine = '';
			fputcsv($file, array($BlankLine));

			fputcsv($file, $columns);
			foreach ($sortedDataArray as $each_data) {
				$row['Booking Id'] = isset($each_data['bookingId']) ? $each_data['bookingId'] : '';
				$row['Customer Name'] = isset($each_data['name']) ? $each_data['name'] : '';
				$row['Booking Date'] = (isset($each_data['bookingdate']) && isset($each_data['time_from'])) ? $each_data['bookingdate'] . ' @ ' . $each_data['time_from'] : '';
				$row['Phone'] = isset($each_data['phone']) ? $each_data['phone'] : '';
				$row['Email'] = isset($each_data['email']) ? $each_data['email'] : '';
				$row['Amount'] = isset($each_data['amount']) ? $each_data['amount'] : '';

				$str = '';
				if (!empty($each_data['refund'])) {
					foreach ($each_data['refund'] as $refund) {
						$str .= "[" . $refund['amount'] . ", ";
						$str .=  $refund['refund_type'] . ", ";
						$str .=  $refund['payment_type'];
						$str .= "]";
						$str .= "\n";
					}
				}

				$row['Refund Type'] = $str;
				$row['Booking Status'] = (!empty($each_data)) ? ((isset($each_data['booking_status'])) ? $each_data['booking_status'] : '') : '';
				$row['Refund Date'] = (!empty($each_data)) ? ((isset($each_data['refunddate'])) ? date("m-d-Y", strtotime($each_data['refunddate'])) : '') : '';

				fputcsv($file, array(
					$row['Booking Id'],
					$row['Customer Name'],
					$row['Booking Date'],
					$row['Phone'],
					$row['Email'],
					$row['Amount'],
					$row['Refund Type'],
					$row['Booking Status'],
					$row['Refund Date']
				));
			}
			fclose($file);
		};

		return response()->stream($callback, 200, $headers);
	}

	/**
	 * Affiliate Report Download in CSV File
	 * 
	 * @param Request $request
	 */
	public function AffiliateReportExportCsvFile(Request $request)
	{
		// $bookedReport = new SummaryReportController();
		return $this->reportRepository->affiliatereport($request, $request->fromDate, $request->toDate, $request->customerId, $request->tourOperatorId, $request->tourPackageId, $request->csv);
	}

	/**
	 * Affiliate All Report (Invoice) Download in CSV File
	 * 
	 * @param Request $request
	 */
	public function AffiliateAllReportExportCsvFile(Request $request)
	{
		return $this->reportRepository->affiliateallreport($request->fromDate, $request->toDate, $request->customerId, $request->tourOperatorId, $request->tourPackageId, $request->csv);
	}

	/**
	 * Affiliate Comprehensive Report (Invoice) Download in CSV File
	 * 
	 * @param Request $request
	 */
	public function AffiliateComprehensiveReportExportCsvFile(Request $request)
	{
		return $this->reportRepository->affiliatecomprehensivereport($request->fromDate, $request->toDate, $request->customerId, $request->tourOperatorId, $request->tourPackageId, $request->csv);
	}

	/**
	 * Financial Report Download in CSV File
	 * 
	 * @param Request $request
	 */
	public function FinancialReportExportCsvFile(Request $request)
	{
		return $this->reportController->FinancialReport($request);
	}

	/**
	 * Email Report Download in CSV File
	 * 
	 * @param Request $request
	 */
	public function EmailReportExportCsvFile(Request $request)
	{
		return $this->reportRepository->emailreport($request, $request->fromDate, $request->toDate, $request->tourOperatorId, $request->tourPackageId, $request->csv);
	}

	/**
	 * FareHarbor Booking Report Download in CSV File
	 * 
	 * @param Request $request
	 */
	public function FareharborBookingReportExportCsvFile(Request $request)
	{
		return $this->reportRepository->fareharaborbookingreport($request, $request->fromDate, $request->toDate, $request->tourOperatorId, $request->tourPackageId, $request->csv);
	}

	/**
	 * Total Availability Report Download in CSV File
	 * 
	 * @param Request $request
	 */
	public function TodayAvialabilityReportExportCsvFile(Request $request)
	{
		return $this->reportRepository->todayavialabilityList($request, $request->fromDate, $request->toDate, $request->tourOperatorId, $request->tourPackageId, $request->csv);
	}

	/**
	 * Failed Payment Report Download in CSV File
	 * 
	 * @param Request $request
	 */
	public function FailedPaymentReportExportCsvFile(Request $request)
	{
		return $this->reportRepository->pendingReport($request->fromDate, $request->toDate, $request->tourOperatorId, $request->tourPackageId, $request->csv);
	}

	/**
	 * Invalid Payment Report Download in CSV File
	 * 
	 * @param Request $request
	 */
	public function InvalidPaymentReportExportCsvFile(Request $request)
	{
		return $this->reportRepository->invalidbookingsreport($request, $request->fromDate, $request->toDate, $request->tourOperatorId, $request->tourPackageId, $request->csv);
	}

	/**
	 * Closure Emails Report Download in CSV File
	 * 
	 * @param Request $request
	 */
	public function LowerAntelopeCanyonExportCsvFile(Request $request)
	{
		return $this->reportRepository->lowerAntelopeCanyonClosureEmailReport($request->fromDate, $request->toDate, $request->tourOperatorId, $request->tourPackageId, $request->csv);
	}

	/**
	 * Revenue Report Download
	 * 
	 * @param Request $request
	 */
	public function RevenuneReportExportCsvFile(Request $request)
	{
		return $this->reportRepository->revenueReport($request, $request->fromDate, $request->toDate, intval($request->tourOperatorId), intval($request->tourPackageId), $request->viewBy, $request->csv);
	}

	/**
	 * Promotion Report Download
	 * 
	 * @param Request $request
	 */
	public function PromotionReportExportCsvFile(Request $request)
	{
		return $this->reportRepository->promotionReportData($request->fromDate, $request->toDate, $request->tourOperatorId, $request->tourPackageId, $request->promotionId, $request->csv);
	}

	/**
	 * Invoice Insights Download
	 * 
	 * @param Request $request
	 */
	public function InvoiceInsightsExportCsvFile(Request $request)
	{
		return $this->reportRepository->invoiceInsightsReport($request);
	}

	/**
	 * OTA [TripAdvisor | GetYourGuide] Report Download
	 * 
	 * @param Request $request
	 */
	public function otaReportExportCsvFile(Request $request)
	{
		return $this->reportRepository->otaBookingReport($request, $request->fromDate, $request->toDate, $request->tourOperatorId, $request->tourPackageId, $request->otaPlatform, $request->csv);
	}

	/**
	 * Staff Booking Report Download
	 * 
	 * @param Request $request
	 */
	public function staffBookingReportExportCsvFile(Request $request)
	{
		return $this->reportRepository->staffBookingReport($request, $request->fromDate, $request->toDate, $request->tourOperatorId, $request->tourPackageId, $request->csv);
	}

	public function transactionsTablePointDeferenceUpdate($month)
	{
		if ($month == 1) {
			$fromDate = "2024-01-01 00:00:00";
			$toDate = "2024-01-31 23:59:59";
		}
		if ($month == 2) {
			$fromDate = "2024-02-01 00:00:00";
			$toDate = "2024-02-29 23:59:59";
		}
		if ($month == 3) {
			$fromDate = "2024-03-01 00:00:00";
			$toDate = "2024-03-31 23:59:59";
		}
		if ($month == 4) {
			$fromDate = "2024-04-01 00:00:00";
			$toDate = "2024-04-30 23:59:59";
		}
		if ($month == 5) {
			$fromDate = "2024-05-01 00:00:00";
			$toDate = "2024-05-31 23:59:59";
		}
		if ($month == 6) {
			$fromDate = "2024-06-01 00:00:00";
			$toDate = "2024-06-30 23:59:59";
		}
		if ($month == 7) {
			$fromDate = "2024-07-01 00:00:00";
			$toDate = "2024-07-31 23:59:59";
		}
		if ($month == 8) {
			$fromDate = "2024-08-01 00:00:00";
			$toDate = "2024-08-31 23:59:59";
		}
		if ($month == 9) {
			$fromDate = "2024-09-01 00:00:00";
			$toDate = "2024-09-30 23:59:59";
		}
		if ($month == 10) {
			$fromDate = "2024-10-01 00:00:00";
			$toDate = "2024-10-31 23:59:59";
		}

		DB::table('transactions AS t')
			->join('tour_booking_history as tbh', 'tbh.transaction_id', '=', 't.id')
			->join('tour_bookings_master as tbm', 'tbm.id', '=', 'tbh.tour_booking_id')
			->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
			->whereIn('t.via', ['Card', 'Cash', 'Check/Wire'])
			->where('t.status', '!=', 'Failed')
			->whereBetween('tst.date', [$fromDate, $toDate])
			->select([
				'tbh.tour_booking_id',
				'tbh.platform_fees',
				't.fee_amount',
				't.id',
				'tbh.details',
			])
			->orderBy('t.id')
			->chunk(2000, function ($data) {
				$result = [];
				$details = '';
				foreach ($data as $key => $value) {
					$details = json_decode($value->details, true);
					if (!isset($details['cards']) || (isset($details['cards']) && count($details['cards']) == 1)) {

						if ($value->platform_fees != $value->fee_amount) {
							$result[] = $value;
						}
					}
				}

				dispatch(new UpdateTransactionsTablePointDeference($result));
			});

		return "<b> Fee amount modification will be formed in the background shortly; please check back in 5 minutes.</b>";
	}

	/**
	 * Affiliate Seats Report Download in CSV File
	 * 
	 * @param Request $request
	 */
	public function AffiliateSeatsReportExportCsvFile(Request $request)
	{
		return $this->reportRepository->affiliateSeatsReport($request);
	}


	/**
	 * Activity Logs Report Download in CSV File
	 * 
	 * @param Request $request
	 */
	public function activityLogsReportExportCsvFile(Request $request)
	{
		return $this->reportRepository->activityLogsReport($request, $request->fromDate, $request->toDate, $request->eventType, $request->userName, $request->csv);
	}

	public function affiliateSeatsTableUpdate($month)
	{
		if ($month == 1) {
			$fromDate = "2024-01-01 00:00:00";
			$toDate = "2024-01-31 23:59:59";
		}
		if ($month == 2) {
			$fromDate = "2024-02-01 00:00:00";
			$toDate = "2024-02-29 23:59:59";
		}
		if ($month == 3) {
			$fromDate = "2024-03-01 00:00:00";
			$toDate = "2024-03-31 23:59:59";
		}
		if ($month == 4) {
			$fromDate = "2024-04-01 00:00:00";
			$toDate = "2024-04-30 23:59:59";
		}
		if ($month == 5) {
			$fromDate = "2024-05-01 00:00:00";
			$toDate = "2024-05-31 23:59:59";
		}
		if ($month == 6) {
			$fromDate = "2024-06-01 00:00:00";
			$toDate = "2024-06-30 23:59:59";
		}
		if ($month == 7) {
			$fromDate = "2024-07-01 00:00:00";
			$toDate = "2024-07-31 23:59:59";
		}
		if ($month == 8) {
			$fromDate = "2024-08-01 00:00:00";
			$toDate = "2024-08-31 23:59:59";
		}
		if ($month == 9) {
			$fromDate = "2024-09-01 00:00:00";
			$toDate = "2024-09-30 23:59:59";
		}
		if ($month == 10) {
			$fromDate = "2024-10-01 00:00:00";
			$toDate = "2024-10-31 23:59:59";
		}

		if ($month == 11) {
			$fromDate = "2024-11-01 00:00:00";
			$toDate = "2024-11-30 23:59:59";
		}

		if ($month == 12) {
			$fromDate = "2024-12-01 00:00:00";
			$toDate = "2024-12-31 23:59:59";
		}

		DB::table('tour_bookings_transact as tbt')
			->join('tour_bookings_master as tbm', 'tbm.id', '=', 'tbt.tour_booking_id')
			->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
			->join('customers as c', 'c.id', '=', 'tbm.customer_id')
			->where('c.is_affiliate', 'Yes')
			->whereBetween('tst.date', [$fromDate, $toDate])
			->select([
				'tbt.tour_booking_id',
				'tbm.tour_package_id',
				'tbm.tour_slot_id',
				'tbm.customer_id',
				'tbm.tourists',
				'tbt.check_in_status',
				'tbm.status'
			])
			->orderBy('tbt.tour_booking_id')
			->chunk(2000, function ($data) {
				dispatch(new UpdateAffiliateSeatsTable($data));
			});

		return "<b> Affiliate seats update table is updated; please check back in 5 minutes.</b>";
	}

	/**
	 * Summary of updateInfantRateGroups
	 * @return void
	 */
	public function updateInfantRateGroups()
	{
		// Check if the tenant is 'kens'
		if (tenant()->id === 'kens') {
			// Log::info('Starting to update bookings...');

			// Fetch all booking groups with specified rate IDs
			$rateIds = [324, 359, 362, 363, 366, 369, 371, 372, 374, 378, 382, 384, 385, 386, 388, 392, 393, 397, 398, 399, 400, 405, 410, 411, 412, 413, 415, 419, 421, 423, 425, 428, 429, 431, 434, 435];
			$bookingGroups = BookingTransact::whereIn('tour_package_rate_id', $rateIds)->get();

			// Initialize arrays to store inserts data
			$inserts = [];

			// Iterate through each booking group
			foreach ($bookingGroups as $bookingGroup) {
				$addonExists = TourBookingForms::where('tour_booking_id', $bookingGroup->tour_booking_id)->first();
				if ($addonExists) {
					Log::warning("Booking ID {$bookingGroup->tour_booking_id} exists in tour booking forms. Skipping...");
					continue;
				}

				// Fetch the corresponding booking
				$booking = TourBooking::find($bookingGroup->tour_booking_id);
				if (!$booking) {
					Log::warning("Booking ID {$bookingGroup->tour_booking_id} not found. Skipping...");
					continue;
				}

				// Log::info("Booking ID {$booking->id} found.");

				// Fetch the associated form for the booking's tour package
				$form = TourPackageForm::where('form_package_id', intval($booking->tour_package_id))
					->select(["id", "form_fields"])
					->first();

				if (!$form) {
					Log::warning("Form for package ID {$booking->tour_package_id} not found. Skipping...");
					continue;
				}

				// Decode form fields and find the specific field
				$field = collect(json_decode($form->form_fields, true))
					->firstWhere('name', 'How many Infant age 3 and under are in your group?');

				if ($field) {
					// Add or update the inserts entry for this booking
					$inserts[$bookingGroup->tour_booking_id] = $field;
					$inserts[$bookingGroup->tour_booking_id]['value'] =
						($inserts[$bookingGroup->tour_booking_id]['value'] ?? 0) + $bookingGroup->tourists;
				}
			}

			// Start a database transaction to batch insert the updates
			if (isset($form)) {
				DB::transaction(function () use ($inserts, $form) {
					$dataToInsert = [];

					// Prepare the data for insertion
					foreach ($inserts as $bookingId => $fieldData) {
						$dataToInsert[] = [
							'tour_package_form_id' => intval($form->id),
							'tour_booking_id' => intval($bookingId),
							'form_submission' => json_encode([$fieldData]),
							'created_at' => now(),
							'updated_at' => now(),
						];
					}

					// Perform bulk insertion
					if (!empty($dataToInsert)) {
						TourBookingForms::insert($dataToInsert);
					}
				});
			}

			// Delete the processed rate groups
			// BookingTransact::whereIn('tour_package_rate_id', $rateIds)->delete();
			// Log::info('Deleted rate groups from BookingTransact.');

			// Log completion messages
			// Log::info('All bookings updated successfully!');
			// Log::info('Total bookings processed: ' . $bookingGroups->count());
			// Log::info('Total forms updated: ' . count($inserts));
		}
	}

	public function roleAndPermission()
	{
		// DB::statement('SET FOREIGN_KEY_CHECKS=0;'); // Disable foreign key checks

		// Truncate the tables
		// DB::table('model_has_roles')->truncate();
		// DB::table('permissions')->truncate();
		// DB::table('roles')->truncate();
		// DB::table('model_has_permissions')->truncate();
		// DB::table('departments')->truncate();

		// DB::statement('SET FOREIGN_KEY_CHECKS=1;'); // Re-enable foreign key checks

		DB::beginTransaction();
		$roles = [
			[
				'name' => 'staff',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Staff',
				'description' => 'Staff user access',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'admin',
				'guard_name' => 'admin',
				'display_name' => 'Admin',
				'description' => 'Admin user access',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'super-admin',
				'guard_name' => 'admin',
				'display_name' => 'Super admin',
				'description' => 'Super admin user access',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'reservation-specialist',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Reservation Specialist',
				'description' => 'Reservation Specialist user access',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'co-ordinator',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Co-ordinator',
				'description' => 'Co-ordinator user access',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'tour-guide',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Tour Guide',
				'description' => 'Tour Guide user access',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'manager',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Manager',
				'description' => 'Manager user access',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'affiliate',
				'guard_name' => 'affiliate',
				'display_name' => 'Affiliate',
				'description' => 'Affiliate user access',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			]
		];

		$permissions = [
			[
				'name' => 'new-booking',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'New Booking',
				'description' => 'To create new booking',
				'category' => 'Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'tour-slots',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Tour Slots',
				'description' => 'Create, update, delete the tour slots',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'tour-promotions',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Tour Promotions',
				'description' => 'Add, edit, delete the tour promotions',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'tour-packages',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Tour Packages',
				'description' => 'Add, edit, delete the tour packages',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'affiliates',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Affiliate Users',
				'description' => 'Add, edit, delete the tour affiliates',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'affiliates-time-slots',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Affiliates Time Slots',
				'description' => 'Add, edit, delete the Affiliates Time Slots',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'invoices',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Invoices',
				'description' => 'Add, edit, delete the invoices',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'tour-operator-staff-users',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Tour Operator Staff Users',
				'description' => 'Add, edit, delete the Tour Operator Staff Users',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'countries',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Countries',
				'description' => 'Add, edit, delete the Countries',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'states-provinces',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'States / Provinces',
				'description' => 'Add, edit, delete the States / Provinces',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'cities',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Cities',
				'description' => 'Add, edit, delete the cities',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'roles',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Roles',
				'description' => 'Add, edit, delete the roles',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'permissions',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Permissions',
				'description' => 'Add, edit, delete the permissions',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'mail-tracker',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Email Tracker',
				'description' => 'Add, edit, delete the Eail Tracker',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'whats-new',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Whats New',
				'description' => 'Add, edit, delete the Whats New',
				'category' => 'Profile',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'dashboard',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Dashboard',
				'description' => 'Add, edit, delete the Dashboard',
				'category' => 'Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'calendar',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Calendar',
				'description' => 'Add, edit, delete the calendar',
				'category' => 'Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'agenda',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Agenda',
				'description' => 'Add, edit, delete the Agenda',
				'category' => 'Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'agenda-checkin-overview',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Agenda Checkin Overview',
				'description' => 'Agenda Checkin Overview',
				'category' => 'Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'reports',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Reports',
				'description' => 'Add, edit, delete the reports',
				'category' => 'Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'help',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Help',
				'description' => 'Add, edit, delete the help',
				'created_from' => 'BACKEND',
				'category' => 'Menu',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'qr',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'QR',
				'description' => 'Add, edit, delete the qr',
				'category' => 'Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'pos',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'POS',
				'description' => 'POS System',
				'category' => 'Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'pos-login',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Login',
				'description' => 'Login',
				'category' => 'POS',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'pos-orders',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Orders',
				'description' => 'POS Orders',
				'category' => 'POS',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'pos-refund-order',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Refund Order',
				'description' => 'POS Refund Order',
				'category' => 'POS',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'pos-cancel-order',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Cancel Order',
				'description' => 'POS Cancel Order',
				'category' => 'POS',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'new-booking',
				'guard_name' => 'admin',
				'display_name' => 'New Booking',
				'description' => 'To create new booking',
				'category' => 'Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],

			[
				'name' => 'tour-slots',
				'guard_name' => 'admin',
				'display_name' => 'Tour Slots',
				'description' => 'Create, update, delete the tour slots',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'tour-promotions',
				'guard_name' => 'admin',
				'display_name' => 'Tour Promotions',
				'description' => 'Add, edit, delete the tour promotions',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'tour-packages',
				'guard_name' => 'admin',
				'display_name' => 'Tour Packages',
				'description' => 'Add, edit, delete the tour packages',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'affiliates',
				'guard_name' => 'admin',
				'display_name' => 'Affiliate Users',
				'description' => 'Add, edit, delete the tour affiliates',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'affiliates-time-slots',
				'guard_name' => 'admin',
				'display_name' => 'Affiliates Time Slots',
				'description' => 'Add, edit, delete the Affiliates Time Slots',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'invoices',
				'guard_name' => 'admin',
				'display_name' => 'Invoices',
				'description' => 'Add, edit, delete the invoices',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'tour-operator-staff-users',
				'guard_name' => 'admin',
				'display_name' => 'Tour Operator Staff Users',
				'description' => 'Add, edit, delete the Tour Operator Staff Users',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'countries',
				'guard_name' => 'admin',
				'display_name' => 'Countries',
				'description' => 'Add, edit, delete the Countries',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'states-provinces',
				'guard_name' => 'admin',
				'display_name' => 'States / Provinces',
				'description' => 'Add, edit, delete the States / Provinces',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'cities',
				'guard_name' => 'admin',
				'display_name' => 'Cities',
				'description' => 'Add, edit, delete the cities',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'roles',
				'guard_name' => 'admin',
				'display_name' => 'Roles',
				'description' => 'Add, edit, delete the roles',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'permissions',
				'guard_name' => 'admin',
				'display_name' => 'Permissions',
				'description' => 'Add, edit, delete the permissions',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'mail-tracker',
				'guard_name' => 'admin',
				'display_name' => 'Email Tracker',
				'description' => 'Add, edit, delete the Email Tracker',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'whats-new',
				'guard_name' => 'admin',
				'display_name' => 'Whats New',
				'description' => 'Add, edit, delete the Whats New',
				'category' => 'Profile',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'dashboard',
				'guard_name' => 'admin',
				'display_name' => 'Dashboard',
				'description' => 'Add, edit, delete the Dashboard',
				'category' => 'Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'calendar',
				'guard_name' => 'admin',
				'display_name' => 'Calendar',
				'description' => 'Add, edit, delete the calendar',
				'category' => 'Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'agenda',
				'guard_name' => 'admin',
				'display_name' => 'Agenda',
				'description' => 'Add, edit, delete the Agenda',
				'category' => 'Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'agenda-checkin-overview',
				'guard_name' => 'admin',
				'display_name' => 'Agenda Checkin Overview',
				'description' => 'Agenda Checkin Overview',
				'category' => 'Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'reports',
				'guard_name' => 'admin',
				'display_name' => 'Reports',
				'description' => 'Add, edit, delete the reports',
				'category' => 'Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'help',
				'guard_name' => 'admin',
				'display_name' => 'Help',
				'description' => 'Add, edit, delete the help',
				'category' => 'Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'qr',
				'guard_name' => 'admin',
				'display_name' => 'QR',
				'description' => 'Add, edit, delete the qr',
				'category' => 'Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'pos',
				'guard_name' => 'admin',
				'display_name' => 'POS',
				'description' => 'POS System',
				'category' => 'Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'pos-login',
				'guard_name' => 'admin',
				'display_name' => 'Login',
				'description' => 'Login',
				'category' => 'POS',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'pos-orders',
				'guard_name' => 'admin',
				'display_name' => 'Orders',
				'description' => 'POS Orders',
				'category' => 'POS',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'pos-refund-order',
				'guard_name' => 'admin',
				'display_name' => 'Refund Order',
				'description' => 'POS Refund Order',
				'category' => 'POS',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'pos-cancel-order',
				'guard_name' => 'admin',
				'display_name' => 'Cancel Order',
				'description' => 'POS Cancel Order',
				'category' => 'POS',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'new-booking',
				'guard_name' => 'affiliate',
				'display_name' => 'New Booking',
				'description' => 'To create new booking',
				'category' => 'Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'tour-slots',
				'guard_name' => 'affiliate',
				'display_name' => 'Tour Slots',
				'description' => 'Create, update, delete the tour slots',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'tour-promotions',
				'guard_name' => 'affiliate',
				'display_name' => 'Tour Promotions',
				'description' => 'Add, edit, delete the tour promotions',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'tour-packages',
				'guard_name' => 'affiliate',
				'display_name' => 'Tour Packages',
				'description' => 'Add, edit, delete the tour packages',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'affiliates',
				'guard_name' => 'affiliate',
				'display_name' => 'Affiliate Users',
				'description' => 'Add, edit, delete the tour affiliates',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'affiliates-time-slots',
				'guard_name' => 'affiliate',
				'display_name' => 'Affiliates Time Slots',
				'description' => 'Add, edit, delete the Affiliates Time Slots',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'invoices',
				'guard_name' => 'affiliate',
				'display_name' => 'Invoices',
				'description' => 'Add, edit, delete the invoices',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'tour-operator-staff-users',
				'guard_name' => 'affiliate',
				'display_name' => 'Tour Operator Staff Users',
				'description' => 'Add, edit, delete the Tour Operator Staff Users',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'countries',
				'guard_name' => 'affiliate',
				'display_name' => 'Countries',
				'description' => 'Add, edit, delete the Countries',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'states-provinces',
				'guard_name' => 'affiliate',
				'display_name' => 'States / Provinces',
				'description' => 'Add, edit, delete the States / Provinces',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'cities',
				'guard_name' => 'affiliate',
				'display_name' => 'Cities',
				'description' => 'Add, edit, delete the cities',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'roles',
				'guard_name' => 'affiliate',
				'display_name' => 'Roles',
				'description' => 'Add, edit, delete the roles',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'permissions',
				'guard_name' => 'affiliate',
				'display_name' => 'Permissions',
				'description' => 'Add, edit, delete the permissions',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'mail-tracker',
				'guard_name' => 'affiliate',
				'display_name' => 'Email Tracker',
				'description' => 'Add, edit, delete the Email Tracker',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'whats-new',
				'guard_name' => 'affiliate',
				'display_name' => 'Whats New',
				'description' => 'Add, edit, delete the Whats New',
				'category' => 'Profile',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'dashboard',
				'guard_name' => 'affiliate',
				'display_name' => 'Dashboard',
				'description' => 'Add, edit, delete the Dashboard',
				'category' => 'Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'calendar',
				'guard_name' => 'affiliate',
				'display_name' => 'Calendar',
				'description' => 'Add, edit, delete the calendar',
				'category' => 'Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'agenda',
				'guard_name' => 'affiliate',
				'display_name' => 'Agenda',
				'description' => 'Add, edit, delete the Agenda',
				'category' => 'Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'agenda-checkin-overview',
				'guard_name' => 'affiliate',
				'display_name' => 'Agenda Checkin Overview',
				'description' => 'Agenda Checkin Overview',
				'category' => 'Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'reports',
				'guard_name' => 'affiliate',
				'display_name' => 'Reports',
				'description' => 'Add, edit, delete the reports',
				'category' => 'Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'help',
				'guard_name' => 'affiliate',
				'display_name' => 'Help',
				'description' => 'Add, edit, delete the help',
				'category' => 'Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'qr',
				'guard_name' => 'affiliate',
				'display_name' => 'QR',
				'description' => 'Add, edit, delete the qr',
				'category' => 'Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'pos-login',
				'guard_name' => 'affiliate',
				'display_name' => 'Login',
				'description' => 'Login',
				'category' => 'POS',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'pos-orders',
				'guard_name' => 'affiliate',
				'display_name' => 'Orders',
				'description' => 'POS Orders',
				'category' => 'POS',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'pos-refund-order',
				'guard_name' => 'affiliate',
				'display_name' => 'Refund Order',
				'description' => 'POS Refund Order',
				'category' => 'POS',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'pos-cancel-order',
				'guard_name' => 'affiliate',
				'display_name' => 'Cancel Order',
				'description' => 'POS Cancel Order',
				'category' => 'POS',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'dynamic-pricing',
				'guard_name' => 'affiliate',
				'display_name' => 'Dynamic Pricing',
				'description' => 'Add, edit, delete the dynamic pricing',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'dynamic-pricing',
				'guard_name' => 'admin',
				'display_name' => 'Dynamic Pricing',
				'description' => 'Add, edit, delete the dynamic pricing',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'dynamic-pricing',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Dynamic Pricing',
				'description' => 'Add, edit, delete the dynamic pricing',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'cancel-booking',
				'guard_name' => 'affiliate',
				'display_name' => 'Cancel Bookings',
				'description' => 'Add, edit, delete the Cancel Booking',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'cancel-booking',
				'guard_name' => 'admin',
				'display_name' => 'Cancel Bookings',
				'description' => 'Add, edit, delete the Cancel Booking',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'cancel-booking',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Cancel Bookings',
				'description' => 'Add, edit, delete the Cancel Booking',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'refund-booking',
				'guard_name' => 'affiliate',
				'display_name' => 'Refund Bookings',
				'description' => 'Add, edit, delete the Refund Booking',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'refund-booking',
				'guard_name' => 'admin',
				'display_name' => 'Refund Bookings',
				'description' => 'Add, edit, delete the Refund Booking',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'refund-booking',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Refund Bookings',
				'description' => 'Add, edit, delete the Refund Booking',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'reschedule-booking',
				'guard_name' => 'affiliate',
				'display_name' => 'Reschedule Booking',
				'description' => 'Add, edit, delete the Reschedule Booking',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'reschedule-booking',
				'guard_name' => 'admin',
				'display_name' => 'Reschedule Booking',
				'description' => 'Add, edit, delete the Reschedule Booking',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'reschedule-booking',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Reschedule Booking',
				'description' => 'Add, edit, delete the Reschedule Booking',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'manager-request',
				'guard_name' => 'affiliate',
				'display_name' => 'Manager Request',
				'description' => 'Add, edit, delete the Manager Request',
				'category' => 'Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'manager-request',
				'guard_name' => 'admin',
				'display_name' => 'Manager Request',
				'description' => 'Add, edit, delete the Manager Request',
				'category' => 'Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'manager-request',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Manager Request',
				'description' => 'Add, edit, delete the Manager Request',
				'category' => 'Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'booking-after-duration',
				'guard_name' => 'affiliate',
				'display_name' => 'Booking After Duration',
				'description' => 'Booking After Duration',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'discount',
				'guard_name' => 'admin',
				'display_name' => 'Discount',
				'description' => 'Discount',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'discount',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Discount',
				'description' => 'Discount',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'discount',
				'guard_name' => 'affiliate',
				'display_name' => 'Discount',
				'description' => 'Discount',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'refund-custom-charge',
				'guard_name' => 'admin',
				'display_name' => 'Refund Custom Charge',
				'description' => 'Refund Custom Charge',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'refund-custom-charge',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Refund Custom Charge',
				'description' => 'Refund Custom Charge',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'refund-custom-charge',
				'guard_name' => 'affiliate',
				'display_name' => 'Refund Custom Charge',
				'description' => 'Refund Custom Charge',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'refund-addons',
				'guard_name' => 'admin',
				'display_name' => 'Refund Addons',
				'description' => 'Refund Addons',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'refund-addons',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Refund Addons',
				'description' => 'Refund Addons',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'refund-addons',
				'guard_name' => 'affiliate',
				'display_name' => 'Refund Addons',
				'description' => 'Refund Addons',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'ticket-redeem',
				'guard_name' => 'admin',
				'display_name' => 'Ticket Redeem',
				'description' => 'Ticket Redeem',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'ticket-redeem',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Ticket Redeem',
				'description' => 'Ticket Redeem',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'ticket-redeem',
				'guard_name' => 'affiliate',
				'display_name' => 'Ticket Redeem',
				'description' => 'Ticket Redeem',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'check-ins',
				'guard_name' => 'admin',
				'display_name' => 'Check-ins',
				'description' => 'Check-ins',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'check-ins',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Check-ins',
				'description' => 'Check-ins',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'check-ins',
				'guard_name' => 'affiliate',
				'display_name' => 'Check-ins',
				'description' => 'Check-ins',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'department',
				'guard_name' => 'admin',
				'display_name' => 'Department',
				'description' => 'Department',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'department',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Department',
				'description' => 'Department',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'department',
				'guard_name' => 'affiliate',
				'display_name' => 'Department',
				'description' => 'Department',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'promo-code',
				'guard_name' => 'admin',
				'display_name' => 'Promo Code',
				'description' => 'Promo Code',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'promo-code',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Promo Code',
				'description' => 'Promo Code',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'promo-code',
				'guard_name' => 'affiliate',
				'display_name' => 'Promo Code',
				'description' => 'Promo Code',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'percent-discount',
				'guard_name' => 'admin',
				'display_name' => 'Percent Discount',
				'description' => 'Percent Discount',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'percent-discount',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Percent Discount',
				'description' => 'Percent Discount',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'percent-discount',
				'guard_name' => 'affiliate',
				'display_name' => 'Percent Discount',
				'description' => 'Percent Discount',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'fix-discount',
				'guard_name' => 'admin',
				'display_name' => 'Fix Discount',
				'description' => 'Fix Discount',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'fix-discount',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Fix Discount',
				'description' => 'Fix Discount',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'fix-discount',
				'guard_name' => 'affiliate',
				'display_name' => 'Fix Discount',
				'description' => 'Fix Discount',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'delete',
				'guard_name' => 'admin',
				'display_name' => 'Delete People',
				'description' => 'Delete',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'delete',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Delete People',
				'description' => 'Delete',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'delete',
				'guard_name' => 'affiliate',
				'display_name' => 'Delete People',
				'description' => 'Delete',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'permit',
				'guard_name' => 'admin',
				'display_name' => 'Remove Permit',
				'description' => 'Permit',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'permit',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Remove Permit',
				'description' => 'Remove Permit',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'permit',
				'guard_name' => 'affiliate',
				'display_name' => 'Remove Permit',
				'description' => 'Permit',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'change',
				'guard_name' => 'admin',
				'display_name' => 'Change People',
				'description' => 'Change',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'change',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Change People',
				'description' => 'Change',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'change',
				'guard_name' => 'affiliate',
				'display_name' => 'Change People',
				'description' => 'Change',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'split-booking',
				'guard_name' => 'admin',
				'display_name' => 'Split Booking',
				'description' => 'Split Booking',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'split-booking',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Split Booking',
				'description' => 'Split Booking',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'split-booking',
				'guard_name' => 'affiliate',
				'display_name' => 'Split Booking',
				'description' => 'Split Booking',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'switch',
				'guard_name' => 'admin',
				'display_name' => 'Switch Package',
				'description' => 'Switch',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'switch',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Switch Package',
				'description' => 'Switch',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'switch',
				'guard_name' => 'affiliate',
				'display_name' => 'Switch Package',
				'description' => 'Switch',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'rebook',
				'guard_name' => 'admin',
				'display_name' => 'Rebook',
				'description' => 'Rebook',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'rebook',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Rebook',
				'description' => 'Rebook',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'rebook',
				'guard_name' => 'affiliate',
				'display_name' => 'Rebook',
				'description' => 'Rebook',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name'         => 'admin-board',
				'guard_name'   => 'admin',
				'display_name' => 'Admin Board',
				'description'  => 'Admin Board',
				'category'     => 'Board',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'admin-board',
				'guard_name'   => 'tour_operator_staff',
				'display_name' => 'Admin Board',
				'description'  => 'Admin Board',
				'category'     => 'Board',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'guide-board',
				'guard_name'   => 'tour_operator_staff',
				'display_name' => 'Guide Board',
				'description'  => 'Guide Board',
				'category'     => 'Board',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'tips-center',
				'guard_name'   => 'admin',
				'display_name' => 'Tips Center',
				'description'  => 'Tips Center',
				'category'     => 'Tips Center',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'tips-center',
				'guard_name'   => 'tour_operator_staff',
				'display_name' => 'Tips Center',
				'description'  => 'Tips Center',
				'category'     => 'Tips Center',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'board-station-packages',
				'guard_name'   => 'admin',
				'display_name' => 'Board Station Packages',
				'description'  => 'Board Station Packages',
				'category'     => 'Board',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'board-station-packages',
				'guard_name'   => 'tour_operator_staff',
				'display_name' => 'Board Station Packages',
				'description'  => 'Board Station Packages',
				'category'     => 'Board',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name' => 'profile',
				'guard_name' => 'admin',
				'display_name' => 'Profile',
				'description' => 'Profile',
				'category' => 'Profile',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'profile',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Profile',
				'description' => 'Profile',
				'category' => 'Profile',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'profile',
				'guard_name' => 'affiliate',
				'display_name' => 'Profile',
				'description' => 'Profile',
				'category' => 'Profile',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'tax-on-custom-charge',
				'guard_name' => 'admin',
				'display_name' => 'Tax On Custom Charge',
				'description' => 'Tax On Custom Charge',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'tax-on-custom-charge',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Tax On Custom Charge',
				'description' => 'Tax On Custom Charge',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'tax-on-custom-charge',
				'guard_name' => 'affiliate',
				'display_name' => 'Tax On Custom Charge',
				'description' => 'Tax On Custom Charge',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'closure-email',
				'guard_name' => 'admin',
				'display_name' => 'Closure Email',
				'description' => 'Closure Email',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'closure-email',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Closure Email',
				'description' => 'Closure Email',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'closure-email',
				'guard_name' => 'affiliate',
				'display_name' => 'Closure Email',
				'description' => 'Closure Email',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'activity-logs-report',
				'guard_name' => 'admin',
				'display_name' => 'Activity Logs Report',
				'description' => 'Activity Logs Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'activity-logs-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Activity Logs Report',
				'description' => 'Activity Logs Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'activity-logs-report',
				'guard_name' => 'affiliate',
				'display_name' => 'Activity Logs Report',
				'description' => 'Activity Logs Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'add-ons-package-details-report',
				'guard_name' => 'admin',
				'display_name' => 'Add-ons Package Details Report',
				'description' => 'Add-ons Package Details Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'add-ons-package-details-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Add-ons Package Details Report',
				'description' => 'Add-ons Package Details Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'add-ons-package-details-report',
				'guard_name' => 'affiliate',
				'display_name' => 'Add-ons Package Details Report',
				'description' => 'Add-ons Package Details Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'add-ons-package-report',
				'guard_name' => 'admin',
				'display_name' => 'Add-ons Package Report',
				'description' => 'Add-ons Package Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'add-ons-package-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Add-ons Package Report',
				'description' => 'Add-ons Package Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'add-ons-package-report',
				'guard_name' => 'affiliate',
				'display_name' => 'Add-ons Package Report',
				'description' => 'Add-ons Package Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'affiliate-booked-report',
				'guard_name' => 'admin',
				'display_name' => 'Affiliate Booked Report',
				'description' => 'Affiliate Booked Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'affiliate-booked-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Affiliate Booked Report',
				'description' => 'Affiliate Booked Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'affiliate-booked-report',
				'guard_name' => 'affiliate',
				'display_name' => 'Affiliate Booked Report',
				'description' => 'Affiliate Booked Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'affiliate-comprehensive-report',
				'guard_name' => 'admin',
				'display_name' => 'Affiliate Comprehensive Report',
				'description' => 'Affiliate Comprehensive Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'affiliate-comprehensive-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Affiliate Comprehensive Report',
				'description' => 'Affiliate Comprehensive Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'affiliate-comprehensive-report',
				'guard_name' => 'affiliate',
				'display_name' => 'Affiliate Comprehensive Report',
				'description' => 'Affiliate Comprehensive Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'affiliate-invoice-insights-report',
				'guard_name' => 'admin',
				'display_name' => 'Affiliate Invoice Insights Report',
				'description' => 'Affiliate Invoice Insights Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'affiliate-invoice-insights-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Affiliate Invoice Insights Report',
				'description' => 'Affiliate Invoice Insights Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'affiliate-invoice-insights-report',
				'guard_name' => 'affiliate',
				'display_name' => 'Affiliate Invoice Insights Report',
				'description' => 'Affiliate Invoice Insights Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'affiliate-invoice-report',
				'guard_name' => 'admin',
				'display_name' => 'Affiliate Invoice Report',
				'description' => 'Affiliate Invoice Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'affiliate-invoice-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Affiliate Invoice Report',
				'description' => 'Affiliate Invoice Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'affiliate-invoice-report',
				'guard_name' => 'affiliate',
				'display_name' => 'Affiliate Invoice Report',
				'description' => 'Affiliate Invoice Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'affiliate-seats-report',
				'guard_name' => 'admin',
				'display_name' => 'Affiliate Seats Report',
				'description' => 'Affiliate Seats Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'affiliate-seats-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Affiliate Seats Report',
				'description' => 'Affiliate Seats Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'affiliate-seats-report',
				'guard_name' => 'affiliate',
				'display_name' => 'Affiliate Seats Report',
				'description' => 'Affiliate Seats Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'availability-report',
				'guard_name' => 'admin',
				'display_name' => 'Availability Report',
				'description' => 'Availability Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'availability-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Availability Report',
				'description' => 'Availability Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'availability-report',
				'guard_name' => 'affiliate',
				'display_name' => 'Availability Report',
				'description' => 'Availability Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'cancel-booking-report',
				'guard_name' => 'admin',
				'display_name' => 'Cancel Booking Report',
				'description' => 'Cancel Booking Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'cancel-booking-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Cancel Booking Report',
				'description' => 'Cancel Booking Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'cancel-booking-report',
				'guard_name' => 'affiliate',
				'display_name' => 'Cancel Booking Report',
				'description' => 'Cancel Booking Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'card-collection-report',
				'guard_name' => 'admin',
				'display_name' => 'Card Collection Report',
				'description' => 'Card Collection Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'card-collection-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Card Collection Report',
				'description' => 'Card Collection Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'card-collection-report',
				'guard_name' => 'affiliate',
				'display_name' => 'Card Collection Report',
				'description' => 'Card Collection Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'cash-collection-report',
				'guard_name' => 'admin',
				'display_name' => 'Cash Collection Report',
				'description' => 'Cash Collection Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'cash-collection-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Cash Collection Report',
				'description' => 'Cash Collection Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'cash-collection-report',
				'guard_name' => 'affiliate',
				'display_name' => 'Cash Collection Report',
				'description' => 'Cash Collection Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'check-wire-collection-report',
				'guard_name' => 'admin',
				'display_name' => 'Check/Wire Collection Report',
				'description' => 'Check/Wire Collection Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'check-wire-collection-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Check/Wire Collection Report',
				'description' => 'Check/Wire Collection Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'check-wire-collection-report',
				'guard_name' => 'affiliate',
				'display_name' => 'Check/Wire Collection Report',
				'description' => 'Check/Wire Collection Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'closure-emails-report',
				'guard_name' => 'admin',
				'display_name' => 'Closure Emails Report',
				'description' => 'Closure Emails Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'closure-emails-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Closure Emails Report',
				'description' => 'Closure Emails Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'closure-emails-report',
				'guard_name' => 'affiliate',
				'display_name' => 'Closure Emails Report',
				'description' => 'Closure Emails Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'daily-activity-report',
				'guard_name' => 'admin',
				'display_name' => 'Daily Activity Report',
				'description' => 'Daily Activity Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'daily-activity-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Daily Activity Report',
				'description' => 'Daily Activity Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'daily-activity-report',
				'guard_name' => 'affiliate',
				'display_name' => 'Daily Activity Report',
				'description' => 'Daily Activity Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'email-report',
				'guard_name' => 'admin',
				'display_name' => 'Email Report',
				'description' => 'Email Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'email-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Email Report',
				'description' => 'Email Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'email-report',
				'guard_name' => 'affiliate',
				'display_name' => 'Email Report',
				'description' => 'Email Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'failed-payment-report',
				'guard_name' => 'admin',
				'display_name' => 'Failed Payment Report',
				'description' => 'Failed Payment Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'failed-payment-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Failed Payment Report',
				'description' => 'Failed Payment Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'failed-payment-report',
				'guard_name' => 'affiliate',
				'display_name' => 'Failed Payment Report',
				'description' => 'Failed Payment Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'fareharbor-booking-report',
				'guard_name' => 'admin',
				'display_name' => 'FareHarbor Booking Report',
				'description' => 'FareHarbor Booking Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'fareharbor-booking-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'FareHarbor Booking Report',
				'description' => 'FareHarbor Booking Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'fareharbor-booking-report',
				'guard_name' => 'affiliate',
				'display_name' => 'FareHarbor Booking Report',
				'description' => 'FareHarbor Booking Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'financial-report',
				'guard_name' => 'admin',
				'display_name' => 'Financial Report',
				'description' => 'Financial Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'financial-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Financial Report',
				'description' => 'Financial Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'financial-report',
				'guard_name' => 'affiliate',
				'display_name' => 'Financial Report',
				'description' => 'Financial Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'invalid-bookings-report',
				'guard_name' => 'admin',
				'display_name' => 'Invalid Bookings Report',
				'description' => 'Invalid Bookings Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'invalid-bookings-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Invalid Bookings Report',
				'description' => 'Invalid Bookings Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'invalid-bookings-report',
				'guard_name' => 'affiliate',
				'display_name' => 'Invalid Bookings Report',
				'description' => 'Invalid Bookings Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'permit-fee-details-report',
				'guard_name' => 'admin',
				'display_name' => 'Permit Fee Details Report',
				'description' => 'Permit Fee Details Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'permit-fee-details-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Permit Fee Details Report',
				'description' => 'Permit Fee Details Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'permit-fee-details-report',
				'guard_name' => 'affiliate',
				'display_name' => 'Permit Fee Details Report',
				'description' => 'Permit Fee Details Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'permit-fee-report',
				'guard_name' => 'admin',
				'display_name' => 'Permit Fee Report',
				'description' => 'Permit Fee Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'permit-fee-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Permit Fee Report',
				'description' => 'Permit Fee Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'permit-fee-report',
				'guard_name' => 'affiliate',
				'display_name' => 'Permit Fee Report',
				'description' => 'Permit Fee Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'promotion-report',
				'guard_name' => 'admin',
				'display_name' => 'Promotion Report',
				'description' => 'Promotion Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'promotion-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Promotion Report',
				'description' => 'Promotion Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'promotion-report',
				'guard_name' => 'affiliate',
				'display_name' => 'Promotion Report',
				'description' => 'Promotion Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'refund-payment',
				'guard_name' => 'admin',
				'display_name' => 'Refund payment',
				'description' => 'Refund Payment',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'refund-payment',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Refund payment',
				'description' => 'Refund Payment',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'refund-payment',
				'guard_name' => 'affiliate',
				'display_name' => 'Refund payment',
				'description' => 'Refund Payment',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'revenue-report',
				'guard_name' => 'admin',
				'display_name' => 'Revenue Report',
				'description' => 'Revenue Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'revenue-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Revenue Report',
				'description' => 'Revenue Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'revenue-report',
				'guard_name' => 'affiliate',
				'display_name' => 'Revenue Report',
				'description' => 'Revenue Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'revenue-summary-report',
				'guard_name' => 'admin',
				'display_name' => 'Revenue Summary Report',
				'description' => 'Revenue Summary Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'revenue-summary-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Revenue Summary Report',
				'description' => 'Revenue Summary Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'revenue-summary-report',
				'guard_name' => 'affiliate',
				'display_name' => 'Revenue Summary Report',
				'description' => 'Revenue Summary Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'software-fee-report',
				'guard_name' => 'admin',
				'display_name' => 'Software Fee Report',
				'description' => 'Software Fee Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'software-fee-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Software Fee Report',
				'description' => 'Software Fee Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'software-fee-report',
				'guard_name' => 'affiliate',
				'display_name' => 'Software Fee Report',
				'description' => 'Software Fee Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'staff-booking-report',
				'guard_name' => 'admin',
				'display_name' => 'Staff Booking Report',
				'description' => 'Staff Booking Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'staff-booking-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Staff Booking Report',
				'description' => 'Staff Booking Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'staff-booking-report',
				'guard_name' => 'affiliate',
				'display_name' => 'Staff Booking Report',
				'description' => 'Staff Booking Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'stripe-payment-report',
				'guard_name' => 'admin',
				'display_name' => 'Stripe Payment Report',
				'description' => 'Stripe Payment Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'stripe-payment-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Stripe Payment Report',
				'description' => 'Stripe Payment Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'stripe-payment-report',
				'guard_name' => 'affiliate',
				'display_name' => 'Stripe Payment Report',
				'description' => 'Stripe Payment Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'stripe-refund-lookup',
				'guard_name' => 'admin',
				'display_name' => 'Stripe Refund Lookup',
				'description' => 'Stripe Refund Lookup',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'stripe-refund-lookup',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Stripe Refund Lookup',
				'description' => 'Stripe Refund Lookup',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'stripe-refund-lookup',
				'guard_name' => 'affiliate',
				'display_name' => 'Stripe Refund Lookup',
				'description' => 'Stripe Refund Lookup',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'tax-collection-report',
				'guard_name' => 'admin',
				'display_name' => 'Tax Collection Report',
				'description' => 'Tax Collection Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'tax-collection-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Tax Collection Report',
				'description' => 'Tax Collection Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'tax-collection-report',
				'guard_name' => 'affiliate',
				'display_name' => 'Tax Collection Report',
				'description' => 'Tax Collection Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'tip-report',
				'guard_name' => 'admin',
				'display_name' => 'Tip Report',
				'description' => 'Tip Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'tip-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Tip Report',
				'description' => 'Tip Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'tip-report',
				'guard_name' => 'affiliate',
				'display_name' => 'Tip Report',
				'description' => 'Tip Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'tip-summary-report',
				'guard_name' => 'admin',
				'display_name' => 'Tip Summary Report',
				'description' => 'Tip Summary Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'tip-summary-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Tip Summary Report',
				'description' => 'Tip Summary Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'tip-summary-report',
				'guard_name' => 'affiliate',
				'display_name' => 'Tip Summary Report',
				'description' => 'Tip Summary Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'transaction-report',
				'guard_name' => 'admin',
				'display_name' => 'Transaction Report',
				'description' => 'Transaction Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'transaction-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Transaction Report',
				'description' => 'Transaction Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'transaction-report',
				'guard_name' => 'affiliate',
				'display_name' => 'Transaction Report',
				'description' => 'Transaction Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'transaction-summary-report',
				'guard_name' => 'admin',
				'display_name' => 'Transaction Summary Report',
				'description' => 'Transaction Summary Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'transaction-summary-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Transaction Summary Report',
				'description' => 'Transaction Summary Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'transaction-summary-report',
				'guard_name' => 'affiliate',
				'display_name' => 'Transaction Summary Report',
				'description' => 'Transaction Summary Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'otas-booking-report',
				'guard_name' => 'admin',
				'display_name' => 'OTAs Booking Report',
				'description' => 'OTAs Booking Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'otas-booking-report',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'OTAs Booking Report',
				'description' => 'OTAs Booking Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'otas-booking-report',
				'guard_name' => 'affiliate',
				'display_name' => 'OTAs Booking Report',
				'description' => 'OTAs Booking Report',
				'category' => 'Report',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'ticketing-app-login',
				'guard_name' => 'admin',
				'display_name' => 'Login',
				'description' => 'Login',
				'category' => 'Ticketing App',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'ticketing-app-login',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Login',
				'description' => 'Login',
				'category' => 'Ticketing App',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'ticketing-app-login',
				'guard_name' => 'affiliate',
				'display_name' => 'Login',
				'description' => 'Login',
				'category' => 'Ticketing App',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'card-reader-settings',
				'guard_name' => 'admin',
				'display_name' => 'Card Reader Settings',
				'description' => 'Card Reader Settings',
				'category' => 'Profile',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'card-reader-settings',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Card Reader Settings',
				'description' => 'Card Reader Settings',
				'category' => 'Profile',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'affiliate-agenda',
				'guard_name' => 'affiliate',
				'display_name' => 'Affiliate Agenda',
				'description' => 'Affiliate Agenda',
				'category' => 'Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'affiliate-agenda',
				'guard_name' => 'admin',
				'display_name' => 'Affiliate Agenda',
				'description' => 'Affiliate Agenda',
				'category' => 'Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'affiliate-agenda',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Affiliate Agenda',
				'description' => 'Affiliate Agenda',
				'category' => 'Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'assign-tour-guide',
				'guard_name' => 'admin',
				'display_name' => 'Assign Tour Guide',
				'description' => 'Assign Tour Guide',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'assign-tour-guide',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Assign Tour Guide',
				'description' => 'Assign Tour Guide',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'assign-tour-guide',
				'guard_name' => 'affiliate',
				'display_name' => 'Assign Tour Guide',
				'description' => 'Assign Tour Guide',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'mass-bookings',
				'guard_name' => 'admin',
				'display_name' => 'Mass Bookings',
				'description' => 'Mass Bookings',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'mass-bookings',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Mass Bookings',
				'description' => 'Mass Bookings',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'mass-bookings',
				'guard_name' => 'affiliate',
				'display_name' => 'Mass Bookings',
				'description' => 'Mass Bookings',
				'category' => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'payment-link',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'Payment Link',
				'description' => 'Enable payment link',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'payment-link',
				'guard_name' => 'admin',
				'display_name' => 'Payment Link',
				'description' => 'Enable payment link',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'payment-link',
				'guard_name' => 'affiliate',
				'display_name' => 'Payment Link',
				'description' => 'Enable payment link',
				'category' => 'Action',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'pt-seo',
				'guard_name' => 'tour_operator_staff',
				'display_name' => 'SEO',
				'description' => 'Enable SEO',
				'category' => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'pt-seo',
				'guard_name' => 'admin',
				'display_name' => 'SEO',
				'description' => 'Enable SEO',
				'category' => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name' => 'pt-seo',
				'guard_name' => 'affiliate',
				'display_name' => 'SEO',
				'description' => 'Enable SEO',
				'category' => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'name'         => 'pt-photos',
				'guard_name'   => 'tour_operator_staff',
				'display_name' => 'Photos',
				'description'  => 'Enable Photos',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-photos',
				'guard_name'   => 'admin',
				'display_name' => 'Photos',
				'description'  => 'Enable Photos',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-photos',
				'guard_name'   => 'affiliate',
				'display_name' => 'Photos',
				'description'  => 'Enable Photos',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-waiver',
				'guard_name'   => 'tour_operator_staff',
				'display_name' => 'Waiver',
				'description'  => 'Enable Waiver',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-waiver',
				'guard_name'   => 'admin',
				'display_name' => 'Waiver',
				'description'  => 'Enable Waiver',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-waiver',
				'guard_name'   => 'affiliate',
				'display_name' => 'Waiver',
				'description'  => 'Enable Waiver',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],

			[
				'name'         => 'pt-relations',
				'guard_name'   => 'tour_operator_staff',
				'display_name' => 'Relations',
				'description'  => 'Enable Relations',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-relations',
				'guard_name'   => 'admin',
				'display_name' => 'Relations',
				'description'  => 'Enable Relations',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-relations',
				'guard_name'   => 'affiliate',
				'display_name' => 'Relations',
				'description'  => 'Enable Relations',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-self-check-in',
				'guard_name'   => 'tour_operator_staff',
				'display_name' => 'Self Check-In',
				'description'  => 'Enable Self Check-In',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-self-check-in',
				'guard_name'   => 'admin',
				'display_name' => 'Self Check-In',
				'description'  => 'Enable Self Check-In',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-self-check-in',
				'guard_name'   => 'affiliate',
				'display_name' => 'Self Check-In',
				'description'  => 'Enable Self Check-In',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-frontend-link',
				'guard_name'   => 'tour_operator_staff',
				'display_name' => 'Frontend Link',
				'description'  => 'Enable Frontend Link',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-frontend-link',
				'guard_name'   => 'admin',
				'display_name' => 'Frontend Link',
				'description'  => 'Enable Frontend Link',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-frontend-link',
				'guard_name'   => 'affiliate',
				'display_name' => 'Frontend Link',
				'description'  => 'Enable Frontend Link',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-assign-affiliates',
				'guard_name'   => 'tour_operator_staff',
				'display_name' => 'Assign Affiliates',
				'description'  => 'Enable Assign Affiliates',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-assign-affiliates',
				'guard_name'   => 'admin',
				'display_name' => 'Assign Affiliates',
				'description'  => 'Enable Assign Affiliates',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-assign-affiliates',
				'guard_name'   => 'affiliate',
				'display_name' => 'Assign Affiliates',
				'description'  => 'Enable Assign Affiliates',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-cancellation-policies',
				'guard_name'   => 'tour_operator_staff',
				'display_name' => 'Cancellation Policies',
				'description'  => 'Enable Cancellation Policies',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-cancellation-policies',
				'guard_name'   => 'admin',
				'display_name' => 'Cancellation Policies',
				'description'  => 'Enable Cancellation Policies',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-cancellation-policies',
				'guard_name'   => 'affiliate',
				'display_name' => 'Cancellation Policies',
				'description'  => 'Enable Cancellation Policies',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-add-ons',
				'guard_name'   => 'tour_operator_staff',
				'display_name' => 'Add-ons',
				'description'  => 'Enable Add-Ons',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-add-ons',
				'guard_name'   => 'admin',
				'display_name' => 'Add-ons',
				'description'  => 'Enable Add-ons',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-add-ons',
				'guard_name'   => 'affiliate',
				'display_name' => 'Add-ons',
				'description'  => 'Enable Add-ons',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-cancellation-policies',
				'guard_name'   => 'tour_operator_staff',
				'display_name' => 'Cancellation Policies',
				'description'  => 'Enable Cancellation Policies',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-cancellation-policies',
				'guard_name'   => 'admin',
				'display_name' => 'Cancellation Policies',
				'description'  => 'Enable Cancellation Policies',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-cancellation-policies',
				'guard_name'   => 'affiliate',
				'display_name' => 'Cancellation Policies',
				'description'  => 'Enable Cancellation Policies',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-add-ons',
				'guard_name'   => 'tour_operator_staff',
				'display_name' => 'Add-ons',
				'description'  => 'Enable Add-Ons',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-add-ons',
				'guard_name'   => 'admin',
				'display_name' => 'Add-ons',
				'description'  => 'Enable Add-ons',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-add-ons',
				'guard_name'   => 'affiliate',
				'display_name' => 'Add-ons',
				'description'  => 'Enable Add-ons',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-hotels',
				'guard_name'   => 'tour_operator_staff',
				'display_name' => 'Hotels',
				'description'  => 'Enable Hotels',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-hotels',
				'guard_name'   => 'admin',
				'display_name' => 'Hotels',
				'description'  => 'Enable Hotels',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-hotels',
				'guard_name'   => 'affiliate',
				'display_name' => 'Hotels',
				'description'  => 'Enable Hotels',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-settings',
				'guard_name'   => 'tour_operator_staff',
				'display_name' => 'Settings',
				'description'  => 'Enable Settings',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-settings',
				'guard_name'   => 'admin',
				'display_name' => 'Settings',
				'description'  => 'Enable Settings',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'pt-settings',
				'guard_name'   => 'affiliate',
				'display_name' => 'Settings',
				'description'  => 'Enable Settings',
				'category'     => 'Tour Package',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'allow-cancellation-in-blackout',
				'guard_name'   => 'affiliate',
				'display_name' => 'Allow Cancellation In Blackout',
				'description'  => 'Enable the cancel booking option',
				'category'     => 'Action',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'blackout-dates',
				'guard_name'   => 'admin',
				'display_name' => 'Blackout Dates',
				'description'  => 'Blackout Dates',
				'category'     => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'blackout-dates',
				'guard_name'   => 'tour_operator_staff',
				'display_name' => 'Blackout Dates',
				'description'  => 'Blackout Dates',
				'category'     => 'Sub Menu',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'switch-calendar',
				'guard_name'   => 'admin',
				'display_name' => 'Switch Calendar',
				'description'  => 'Switch Calendar',
				'category'     => 'Action',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'switch-calendar',
				'guard_name'   => 'tour_operator_staff',
				'display_name' => 'Switch Calendar',
				'description'  => 'Switch Calendar',
				'category'     => 'Action',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'payment-method-card',
				'guard_name'   => 'tour_operator_staff',
				'display_name' => 'Card',
				'description'  => 'Enable Card Payment Method',
				'category'     => 'Payment Methods',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'payment-method-card',
				'guard_name'   => 'admin',
				'display_name' => 'Card',
				'description'  => 'Enable Card Payment Method',
				'category'     => 'Payment Methods',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'payment-method-card',
				'guard_name'   => 'affiliate',
				'display_name' => 'Card',
				'description'  => 'Enable Card Payment Method',
				'category'     => 'Payment Methods',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'payment-method-cash',
				'guard_name'   => 'tour_operator_staff',
				'display_name' => 'Cash',
				'description'  => 'Enable Cash Payment Method',
				'category'     => 'Payment Methods',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'payment-method-cash',
				'guard_name'   => 'admin',
				'display_name' => 'Cash',
				'description'  => 'Enable Cash Payment Method',
				'category'     => 'Payment Methods',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'payment-method-cash',
				'guard_name'   => 'affiliate',
				'display_name' => 'Cash',
				'description'  => 'Enable Cash Payment Method',
				'category'     => 'Payment Methods',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'payment-method-pay-later',
				'guard_name'   => 'tour_operator_staff',
				'display_name' => 'Pay Later',
				'description'  => 'Enable Pay Later Payment Method',
				'category'     => 'Payment Methods',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'payment-method-pay-later',
				'guard_name'   => 'admin',
				'display_name' => 'Pay Later',
				'description'  => 'Enable Pay Later Payment Method',
				'category'     => 'Payment Methods',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'payment-method-pay-later',
				'guard_name'   => 'affiliate',
				'display_name' => 'Pay Later',
				'description'  => 'Enable Pay Later Payment Method',
				'category'     => 'Payment Methods',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'payment-method-payment-link',
				'guard_name'   => 'tour_operator_staff',
				'display_name' => 'Payment Link',
				'description'  => 'Enable Payment Link Payment Method',
				'category'     => 'Payment Methods',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'payment-method-payment-link',
				'guard_name'   => 'admin',
				'display_name' => 'Payment Link',
				'description'  => 'Enable Payment Link Payment Method',
				'category'     => 'Payment Methods',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'payment-method-payment-link',
				'guard_name'   => 'affiliate',
				'display_name' => 'Payment Link',
				'description'  => 'Enable Payment Link Payment Method',
				'category'     => 'Payment Methods',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'payment-method-billable',
				'guard_name'   => 'tour_operator_staff',
				'display_name' => 'Billable',
				'description'  => 'Enable Billable Payment Method',
				'category'     => 'Payment Methods',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'payment-method-billable',
				'guard_name'   => 'admin',
				'display_name' => 'Billable',
				'description'  => 'Enable Billable Payment Method',
				'category'     => 'Payment Methods',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'payment-method-billable',
				'guard_name'   => 'affiliate',
				'display_name' => 'Billable',
				'description'  => 'Enable Billable Payment Method',
				'category'     => 'Payment Methods',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'cancel-weather-refund',
				'guard_name'   => 'tour_operator_staff',
				'display_name' => 'Weather / 100% Refund',
				'description'  => 'Enable Weather / 100% Refund Method',
				'category'     => 'Cancellation Refund Methods',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'cancel-weather-refund',
				'guard_name'   => 'admin',
				'display_name' => 'Weather / 100% Refund',
				'description'  => 'Enable Weather / 100% Refund Method',
				'category'     => 'Cancellation Refund Methods',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'cancel-weather-refund',
				'guard_name'   => 'affiliate',
				'display_name' => 'Weather / 100% Refund',
				'description'  => 'Enable Weather / 100% Refund Method',
				'category'     => 'Cancellation Refund Methods',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'cancel-ticket-refund',
				'guard_name'   => 'tour_operator_staff',
				'display_name' => 'Ticket Refund',
				'description'  => 'Enable Ticket Refund Method',
				'category'     => 'Cancellation Refund Methods',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'cancel-ticket-refund',
				'guard_name'   => 'admin',
				'display_name' => 'Ticket Refund',
				'description'  => 'Enable Ticket Refund Method',
				'category'     => 'Cancellation Refund Methods',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'cancel-ticket-refund',
				'guard_name'   => 'affiliate',
				'display_name' => 'Ticket Refund',
				'description'  => 'Enable Ticket Refund Method',
				'category'     => 'Cancellation Refund Methods',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'cancel-partial-refund',
				'guard_name'   => 'tour_operator_staff',
				'display_name' => 'Partial Refund',
				'description'  => 'Enable Partial Refund Method',
				'category'     => 'Cancellation Refund Methods',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'cancel-partial-refund',
				'guard_name'   => 'admin',
				'display_name' => 'Partial Refund',
				'description'  => 'Enable Ticket Refund Method',
				'category'     => 'Cancellation Refund Methods',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'cancel-partial-refund',
				'guard_name'   => 'affiliate',
				'display_name' => 'Partial Refund',
				'description'  => 'Enable Partial Refund Method',
				'category'     => 'Cancellation Refund Methods',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'cancel-dont-refund',
				'guard_name'   => 'tour_operator_staff',
				'display_name' => 'Don\'t Refund',
				'description'  => 'Enable Dont Refund Method',
				'category'     => 'Cancellation Refund Methods',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'cancel-dont-refund',
				'guard_name'   => 'admin',
				'display_name' => 'Don\'t Refund',
				'description'  => 'Enable Dont Refund Method',
				'category'     => 'Cancellation Refund Methods',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
				'name'         => 'cancel-dont-refund',
				'guard_name'   => 'affiliate',
				'display_name' => 'Don\'t Refund',
				'description'  => 'Enable Dont Refund Method',
				'category'     => 'Cancellation Refund Methods',
				'created_from' => 'BACKEND',
				'created_by'   => null,
				'created_at'   => Carbon::now(),
				'updated_at'   => Carbon::now(),
			],
			[
		        'name' => 'tripadvisor-booking-report',
		        'guard_name' => 'admin',
		        'display_name' => 'TripAdvisor Booking Report',
		        'description' => 'TripAdvisor Booking Report',
		        'category' => 'Report',
		        'created_from' => 'BACKEND',
		        'created_by' => null,
		        'created_at' => Carbon::now(),
		        'updated_at' => Carbon::now(),
		    ],
		    [
		        'name' => 'tripadvisor-booking-report',
		        'guard_name' => 'tour_operator_staff',
		        'display_name' => 'TripAdvisor Booking Report',
		        'description' => 'TripAdvisor Booking Report',
		        'category' => 'Report',
		        'created_from' => 'BACKEND',
		        'created_by' => null,
		        'created_at' => Carbon::now(),
		        'updated_at' => Carbon::now(),
		    ],
		    [
		        'name' => 'tripadvisor-booking-report',
		        'guard_name' => 'affiliate',
		        'display_name' => 'TripAdvisor Booking Report',
		        'description' => 'TripAdvisor Booking Report',
		        'category' => 'Report',
		        'created_from' => 'BACKEND',
		        'created_by' => null,
		        'created_at' => Carbon::now(),
		        'updated_at' => Carbon::now(),
		    ],
		    [
		        'name' => 'view-payment-breakdown-report',
		        'guard_name' => 'admin',
		        'display_name' => 'View Payment Breakdown Report',
		        'description' => 'View payment breakdown reports and analytics',
		        'category' => 'Report',
		        'created_from' => 'BACKEND',
		        'created_by' => null,
		        'created_at' => Carbon::now(),
		        'updated_at' => Carbon::now(),
		    ],
		    [
		        'name' => 'view-payment-breakdown-report',
		        'guard_name' => 'tour_operator_staff',
		        'display_name' => 'View Payment Breakdown Report',
		        'description' => 'View payment breakdown reports and analytics',
		        'category' => 'Report',
		        'created_from' => 'BACKEND',
		        'created_by' => null,
		        'created_at' => Carbon::now(),
		        'updated_at' => Carbon::now(),
		    ],
		    [
		        'name' => 'view-payment-breakdown-report',
		        'guard_name' => 'affiliate',
		        'display_name' => 'View Payment Breakdown Report',
		        'description' => 'View payment breakdown reports and analytics',
		        'category' => 'Report',
		        'created_from' => 'BACKEND',
		        'created_by' => null,
		        'created_at' => Carbon::now(),
		        'updated_at' => Carbon::now(),
		    ],
		    [
		        'name' => 'export-payment-breakdown-report',
		        'guard_name' => 'admin',
		        'display_name' => 'Export Payment Breakdown Report',
		        'description' => 'Export payment breakdown reports to PDF, CSV, and Excel formats',
		        'category' => 'Report',
		        'created_from' => 'BACKEND',
		        'created_by' => null,
		        'created_at' => Carbon::now(),
		        'updated_at' => Carbon::now(),
		    ],
		    [
		        'name' => 'export-payment-breakdown-report',
		        'guard_name' => 'tour_operator_staff',
		        'display_name' => 'Export Payment Breakdown Report',
		        'description' => 'Export payment breakdown reports to PDF, CSV, and Excel formats',
		        'category' => 'Report',
		        'created_from' => 'BACKEND',
		        'created_by' => null,
		        'created_at' => Carbon::now(),
		        'updated_at' => Carbon::now(),
		    ],
		    [
		        'name' => 'export-payment-breakdown-report',
		        'guard_name' => 'affiliate',
		        'display_name' => 'Export Payment Breakdown Report',
		        'description' => 'Export payment breakdown reports to PDF, CSV, and Excel formats',
		        'category' => 'Report',
		        'created_from' => 'BACKEND',
		        'created_by' => null,
		        'created_at' => Carbon::now(),
		        'updated_at' => Carbon::now(),
		    ],
		    [
		        'name' => 'manage-payment-breakdown-filters',
		        'guard_name' => 'admin',
		        'display_name' => 'Manage Payment Breakdown Filters',
		        'description' => 'Manage and reset payment breakdown report filters',
		        'category' => 'Report',
		        'created_from' => 'BACKEND',
		        'created_by' => null,
		        'created_at' => Carbon::now(),
		        'updated_at' => Carbon::now(),
		    ],
		    [
		        'name' => 'manage-payment-breakdown-filters',
		        'guard_name' => 'tour_operator_staff',
		        'display_name' => 'Manage Payment Breakdown Filters',
		        'description' => 'Manage and reset payment breakdown report filters',
		        'category' => 'Report',
		        'created_from' => 'BACKEND',
		        'created_by' => null,
		        'created_at' => Carbon::now(),
		        'updated_at' => Carbon::now(),
		    ],
		    [
		        'name' => 'manage-payment-breakdown-filters',
		        'guard_name' => 'affiliate',
		        'display_name' => 'Manage Payment Breakdown Filters',
		        'description' => 'Manage and reset payment breakdown report filters',
		        'category' => 'Report',
		        'created_from' => 'BACKEND',
		        'created_by' => null,
		        'created_at' => Carbon::now(),
		        'updated_at' => Carbon::now(),
		    ],
		];

		foreach ($roles as $role) {
			Role::updateOrInsert(
				['name' => $role['name'], 'guard_name' => $role['guard_name']], // Conditions to check
				$role // Data to update or insert
			);
		}

		foreach ($permissions as $permission) {
			Permission::updateOrInsert(
				['name' => $permission['name'], 'guard_name' => $permission['guard_name']], // Conditions to check
				$permission // Data to update or insert
			);
		}

		/*DB::transaction(function () use ($permissions) {

		    // Build allowed (name|guard) keys
		    $allowedKeys = collect($permissions)
		        ->map(fn ($p) => $p['name'] . '|' . $p['guard_name'])
		        ->toArray();

		    // Get permission IDs that should be deleted
		    $deletePermissionIds = DB::table('permissions')
		        ->whereNotIn(
		            DB::raw("CONCAT(name,'|',guard_name)"),
		            $allowedKeys
		        )
		        ->pluck('id');

		    if ($deletePermissionIds->isNotEmpty()) {

		        // Delete dependent role-permission mappings FIRST
		        DB::table('default_role_permission')
		            ->whereIn('permission_id', $deletePermissionIds)
		            ->delete();

		        // Now delete permissions
		        DB::table('permissions')
		            ->whereIn('id', $deletePermissionIds)
		            ->delete();
		    }

		    // Upsert current permissions
		    DB::table('permissions')->upsert(
		        $permissions,
		        ['name', 'guard_name'],
		        ['display_name', 'description', 'category', 'updated_at']
		    );
		});*/

		// $department = Department::updateOrCreate(
		// 	['name' => 'Universal Division'], // Condition to check
		// 	['status' => 'Active'] // Data to update or insert
		// );

		// Admin::where('department_id', null)->update(['department_id' => $department->id]);

		// $adminUsers = Admin::get();
		// foreach ($adminUsers as $admin) {
		// 	if ($admin->roles->isEmpty()) { // Check if admin has any role
		// 		$admin->assignRole(3);
		// 	}
		// }

		// Customer::where('is_affiliate', 'Yes')->where('department_id', null)->update(['department_id' => $department->id]);
		// $affiliates = Customer::where('is_affiliate', 'Yes')->get();
		// foreach ($affiliates as $affiliate) {
		// 	if ($affiliate->roles->isEmpty()) { // Check if admin has any role
		// 		$affiliate->assignRole(8);
		// 	}
		// }

		// foreach ($affiliates as $affiliate) {
		// 	$permissions = Permission::where('guard_name', 'affiliate')->whereIn('name', ['calendar', 'agenda', 'reports', 'help', 'new-booking'])->get();

		// 	foreach ($permissions as $permission) {
		// 		$affiliate->givePermissionTo($permission->name);
		// 	}
		// }

		// Staff::where('department_id', null)->update(['department_id' => $department->id]);
		// $staffs = Staff::get();
		// foreach ($staffs as $staff) {
		// 	if ($staff->roles->isEmpty()) { // Check if admin has any role
		// 		$staff->assignRole(1);
		// 	}
		// }

		// foreach ($staffs as $staff) {
		// 	$permissionStaffs = Permission::where('guard_name', 'tour_operator_staff')->whereIn('name', ['dashboard', 'calendar', 'agenda', 'reports', 'help', 'qr', 'new-booking', 'tour-slots', 'tour-promotions', 'tour-packages', 'affiliates', 'affiliates-time-slots', 'tour-operator-staff-users', 'whats-new','cancel-booking', 'refund-booking', 'reschedule-booking', 'booking-after-duration', 'discount', 'refund-custom-charge', 'refund-addons', 'ticket-redeem', 'check-ins'])->get();

		// 	foreach ($permissionStaffs as $permissionStaff) {
		// 		$staff->givePermissionTo($permissionStaff->name);
		// 	}
		// }

		DB::commit();

		// Clear cached permissions after commit
		app(PermissionCacheService::class)->forgetCachedPermissions();

		return "<b> Role and permission has been updated successfully</b>";
	}
	public function paymentRefundPermissions()
	{

		DB::beginTransaction();
		try {
			$adminUsers = Admin::get();
			foreach ($adminUsers as $admin) {
				$permissions = Permission::where('guard_name', 'admin')->whereIn('name', ['payment-method-pay-later', 'payment-method-payment-link', 'payment-method-card', 'payment-method-cash', 'cancel-weather-refund', 'cancel-ticket-refund', 'cancel-partial-refund', 'cancel-dont-refund'])->get();
				if ($admin->hasRole(2)) {
					foreach ($permissions as $permission) {
						$admin->givePermissionTo($permission->name);
					}
				}
			}
			$staffs = Staff::get();
			foreach ($staffs as $staff) {
				$permissionStaffs = Permission::where('guard_name', 'tour_operator_staff')->whereIn('name', ['payment-method-pay-later', 'payment-method-payment-link', 'payment-method-card', 'payment-method-cash', 'cancel-weather-refund', 'cancel-ticket-refund', 'cancel-partial-refund', 'cancel-dont-refund'])->get();

				foreach ($permissionStaffs as $permissionStaff) {
					$staff->givePermissionTo($permissionStaff->name);
				}
			}
			$affiliates = Customer::where('is_affiliate', 'Yes')->get();
			foreach ($affiliates as $affiliate) {
				$permissionsAffiliates = Permission::where('guard_name', 'affiliate')->whereIn('name', ['payment-method-pay-later','payment-method-card', 'cancel-weather-refund', 'cancel-ticket-refund', 'cancel-partial-refund', 'cancel-dont-refund'])->get();

				foreach ($permissionsAffiliates as $permission) {
					$affiliate->givePermissionTo($permission->name);
				}
			}
			DB::commit();
			return "<b> Payment and Refund permissions has been updated successfully</b>";
		} catch (Exception $exception) {
			DB::rollBack();
			Log::error($exception);
			return redirect()->back()->with('error', $exception->getMessage());
		}
	}

	/**
	 * Clear permission cache
	 *
	 * @return mixed
	 */
	public function permissionCacheClear()
	{
		// Clear cached permissions using tenant-aware service
		app(PermissionCacheService::class)->forgetCachedPermissions();

		return "<b> Permission cache cleared successfully</b>";
	}

	/**
	 * Create multiple soft bookings for specific affiliate, slot and date range
	 *
	 * @return mixed
	 */
	public function insertSlotsSeatsSummary()
	{
		try {
			if (isset(tenant()->id) && tenant()->id !== 'live' && !empty(tenant()->id)) {
				DB::table('tour_bookings_master as tbm')
					->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
					->where('tbm.status', 'Booked')
					->where('tst.date', '>=', '2026-07-01') // ✅ safer date comparison
					->select([
						'tbm.tour_slot_id',
						DB::raw('SUM(tbm.tourists) as total_tourists'),
					])
					->groupBy('tbm.tour_slot_id')
					->orderBy('tbm.tour_slot_id')
					->chunk(2000, function ($data) {
						dispatch(new InsertSlotSeatsSummary($data));
					});
			}
		} catch (Exception $exception) {
			Log::error($exception);
			return redirect()->back()->with('error', $exception->getMessage());
		}
	}
}
