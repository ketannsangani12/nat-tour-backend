<?php

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Staff;
use App\Models\TourPackage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

if (!function_exists('stopNextYearBookings')) {
	function stopNextYearBookings($date)
	{
		$year = date('Y', strtotime($date));
		$nextYear = date('Y') + 1;

		if ($year == $nextYear && !hasSetting('ALLOW_NEXT_YEAR_BOOKINGS')) {
			throw new Exception(
				"Bookings for {$nextYear} have been disabled for your account.
                Please select a different year or contact support for further assistance."
			);
		}
	}
}

if (!function_exists('trimDeci')) {
	function trimDeci($num)
	{
		if (round($num) == $num)
			return round($num);
		elseif (round($num, 1) == $num)
			return round($num, 1);
		else
			return $num;
	}
}

if (!function_exists('prefixedRouteName')) {
	function prefixedRouteName($routeName)
	{
		if (session('userType') == 'Admin' && substr($routeName, 0, 6) != 'admin.')
			$routeName = "admin.$routeName";
		elseif (session('userType') == 'Tour Operator' && substr($routeName, 0, 14) != 'tour-operator.')
			$routeName = "tour-operator.$routeName";
		elseif (session('userType') == 'Affiliate' && substr($routeName, 0, 14) != 'affiliate.')
			$routeName = "affiliate.$routeName";
		elseif (session('userType') == 'Customer' && substr($routeName, 0, 9) != 'customer.')
			$routeName = "customer.$routeName";

		return $routeName;
	}
}

if (!function_exists('getActiveDisputeCount')) {
	function getActiveDisputeCount()
	{
		if (!tenant()) {
			return 0;
		}

		// Auth::check() uses the default guard (customer) which is never set for admins/staff.
		// Resolve the current user from the correct guard explicitly.
		$user = Auth::guard('admin')->user() ?? Auth::guard('tour_operator_staff')->user();

		if (!$user || !$user->can('view-payment-dispute')) {
			return 0;
		}

		try {
			// Use the DisputeService to get active disputes requiring attention
			$disputeService = app(\App\Services\Dispute\DisputeService::class);
			$activeDisputes = $disputeService->getActiveDisputesRequiringAttention(3);
			return $activeDisputes->count();
		} catch (Exception $e) {
			// Log error but don't break the layout
			Log::warning('Failed to get active dispute count for menu badge', [
				'error' => $e->getMessage(),
				'user_id' => $user->id
			]);
			return 0;
		}
	}
}

if (!function_exists('fromTagifyToDb')) {
	function fromTagifyToDb($thingsToBring)
	{
		if ($thingsToBring == '')
			return '';

		try {
			$things1 = json_decode($thingsToBring);
			$things2 = [];

			if (!isset($things1))
				return '';

			foreach ($things1 as $thing)
				$things2[] = $thing->value;

			return json_encode($things2);
		} catch (Exception $error) {
			return '';
		}
	}
}

if (!function_exists('fromDbToTagify')) {
	function fromDbToTagify($thingsToBring)
	{
		if ($thingsToBring == '')
			return '';

		$things1 = json_decode($thingsToBring);
		$things2 = [];

		if (!isset($things1))
			return '';

		foreach ($things1 as $thing)
			$things2[] = ['value' => $thing];

		return json_encode($things2);
	}
}

if (!function_exists('mysqlDT')) {
	function mysqlDT()
	{
		return date('Y-m-d H:i:s');
	}
}

if (!function_exists('sendJsonErrMsg')) {
	function sendJsonErrMsg($errMsg, $errCode = 500, $field = 'common')
	{
		return response()->json(['errors' => [
			(string) $field => (is_array($errMsg) ? $errMsg : [$errMsg])
		]], $errCode);
	}
}

if (!function_exists('formatTime')) {
	function formatTime($strTime)
	{
		if ($strTime == null)
			return '-';

		$arTm = explode(':', $strTime);
		return ($arTm[0] > 12 ? $arTm[0] - 12 : $arTm[0]) . ':' . $arTm[1] . ' ' . ($arTm[0] < 12 ? 'am' : 'pm');
	}
}

if (!function_exists('formatDate')) {
	function formatDate($date)
	{
		if ($date == null || substr($date, 0, 19) == '0000-00-00 00:00:00')
			return ' ';
		return date('n/j/Y', strtotime($date));
	}
}

if (!function_exists('formatDateTime')) {
	function formatDateTime($dateTime)
	{
		if ($dateTime == null || substr($dateTime, 0, 19) == '0000-00-00 00:00:00')
			return ' ';
		return date('n/j/Y @ g:i a', strtotime($dateTime));
	}
}

/**
 * Function to convert time string to seconds since midnight
 *
 * @param string $timeStr
 */
if (!function_exists('timeToSeconds')) {
	function timeToSeconds($timeStr)
	{
		$timeArr = explode(':', $timeStr);
		return $timeArr[0] * 3600 + $timeArr[1] * 60 + $timeArr[2];
	}
}

if (!function_exists('fileNameExt')) {
	function fileNameExt($fileName)
	{
		$lastDotPos = strrpos($fileName, '.');
		$fileNmExt = new \stdClass;
		$fileNmExt->name = substr($fileName, 0, $lastDotPos);
		$fileNmExt->ext = substr($fileName, $lastDotPos + 1);
		return $fileNmExt;
	}
}

if (!function_exists('validateRecaptcha')) {
	function validateRecaptcha($request)
	{
		if ($request->missing('g-recaptcha-response'))
			return 'missing';

		$url = 'https://www.google.com/recaptcha/api/siteverify?secret=' . env('RECAPTCHA_SECRET_KEY') . '&response=' . urlencode($request->input('g-recaptcha-response'));

		try {
			$response = Illuminate\Support\Facades\Http::get($url)->object();

			if ($response->success == false)
				return false;
		} catch (Exception $ex) {
			return false;
		}

		return true;
	}
}

if (!function_exists('sendEmail')) {
	function sendEmail(String $subject, array $to, String $view, array $arEmailData = [])
	{
		$arEmailData += [
			'subject' => str_replace('_SITE_NAME_', config('app.name'), $subject),
			'receiverName' => $to['name'],
		];

		return Illuminate\Support\Facades\Mail::to([$to])
			->send(new \App\Mail\Common($view, $arEmailData));
	}
}

if (!function_exists('isHotmailFamilyEmail')) {
	function isHotmailFamilyEmail(?string $email): bool
	{
		if (empty($email) || !str_contains($email, '@')) {
			return false;
		}

		$domain = strtolower(trim(substr($email, strrpos($email, '@') + 1)));

		return $domain === 'msn.com'
			|| str_starts_with($domain, 'hotmail.')
			|| str_starts_with($domain, 'outlook.')
			|| str_starts_with($domain, 'live.');
	}
}

if (!function_exists('resolveTourPackageFromEmail')) {
	function resolveTourPackageFromEmail(?int $tourPackageId, ?string $customerEmail, ?string $defaultFromEmail): ?string
	{
		if (empty($tourPackageId) || !isHotmailFamilyEmail($customerEmail)) {
			return $defaultFromEmail;
		}

		$hotmailFromEmail = TourPackage::whereKey($tourPackageId)->value('from_email_hotmail');

		return !empty($hotmailFromEmail) ? $hotmailFromEmail : $defaultFromEmail;
	}
}

if (!function_exists('diffRecord')) {
	function diffRecord($fields, $operationType, $oldValues1, $newValues1, &$oldValues2, &$newValues2)
	{
		foreach ($fields as $field) {
			if ($operationType == 'i' or (array_key_exists($field, $oldValues1) && array_key_exists($field, $newValues1))) {
				if ($operationType == 'i' or $oldValues1[$field] != $newValues1[$field]) {
					if ($operationType != 'i') // no old values when inserting
						$oldValues2[$field] = $oldValues1[$field];

					$newValues2[$field] = $newValues1[$field];
				}
			}
		}
	}
}

if (!function_exists('csvHeaders')) {
	function csvHeaders($fileName)
	{
		$headers = [
			"Content-type" => "text/csv",
			"Content-Disposition" => "attachment; filename=$fileName",
			"Pragma" => "no-cache",
			"Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
			"Expires" => "0"
		];

		return $headers;
	}
}

if (!function_exists('csvColumns')) {
	function csvColumns($Columns)
	{
		switch ($Columns) {
			case 'daily_report_columns':
				$columns = ["Check in status", "Customer Type", "# of Pax", "Permit Exempt", "Permit Pax", "Permit Fee"];
				return $columns;

			case 'daily_report_details_columns':
				$columns = ["Check in status", 'Source', "Customer Type", "# of Pax", "Permit Exempt", "Permit Pax", "Booking ID", "Exempt Booking ID", "Permit Fee"];
				return $columns;

			case 'daily_activity_report_overall_columns':
				$columns = ["Payment Type", "Package", "Total Bookings", "Ticket Cost", "Ticket Refund", "Ticket Net", "Platform Fees", "Platform Fees Refund", "Platform Fees Net", "Discount", "Payment Total", "Refund Total", "Total Net"];
				return $columns;

			case 'daily_activity_report_columns':
				$columns = ["Payment Type", "Package", "Total Bookings", "Ticket Cost", "Ticket Refund", "Ticket Net",/*"Processing Fees","Processing Fees Refund","Processing Net",*/ "Platform Fees", "Platform Fees Refund", "Platform Fees Net", "Discount", "Payment Total", "Refund Total", "Total Net"];
				return $columns;

			case 'daily_activity_report_unpaid_booking_columns':
				$columns = ["Payment Type", "Package", "Total Bookings", "NAT Gross", "Total Software Fees", "Refunds", "Net"];
				return $columns;

			case 'daily_activity_report_tripadvisor_booking_columns':
				$columns = ["Payment Type", "Package", "Total Bookings", "NAT Gross", "Refunds", "Net"];
				return $columns;

			case 'booking_details_report':
				$columns = ["ID", 'Name', "Booking Date", "Create Date", "No of Person", "Amount", "Payment", "Status"];
				return $columns;

			case 'tip_report_columns':
				$columns = ['Booking ID', 'Name', 'Phone', 'Booking Date', 'Tip Date', 'Staff Name', 'Num of Person', 'Amount', 'Tip Amount'];
				return $columns;

			case 'tax_collection_report_columns':
				$columnsHeader = ['Date', 'State Tax'];
				return $columnsHeader;

			case 'cash_collection_report_columnsHeader':
				$columnsHeader = ['Tour Operator Staff Name', 'Total Amount', 'Total Refund Amount', 'Total Payment Amount'];
				return $columnsHeader;

			case 'cash_fees_collection_report_columns_header':
				$columnsHeader = ['Tour Operator Staff Name', 'Total Amount', 'Total Paid Fees', 'Total Refund Amount', 'Total Fees Refund', 'Total Payment Amount'];
				return $columnsHeader;

			case 'cash_card_collection_report_columnsHeader_overall_FareHarbor':
				$columnsHeader = ['Date Range', 'Created', 'View By', 'Payment Total', 'FareHarbor Total', 'Refund Total', 'Final Total'];
				return $columnsHeader;

			case 'cash_card_collection_report_columnsHeader_overall':
				$columnsHeader = ['Date Range', 'Created', 'View By', 'Payment Total', 'Refund Total', 'Final Total'];
				return $columnsHeader;

			case 'cash_fees_collection_report_columns_header_overall':
				$columnsHeader = ['Date Range', 'Created', 'View By', 'Payment Total', 'Paid Fees', 'Refund Total', 'Fees Refund', 'Final Total'];
				return $columnsHeader;

			case 'cash_card_collection_report_columns':
				$columns = ['Booking ID', 'Booking Date', 'Created Date', 'Via', 'Payment Amount', 'Refund Amount', 'Final Amount', 'Customer Name', 'Comments', 'Status'];
				return $columns;

			case 'card_collection_report_columnsHeader_overall':
				$columnsHeader = ['Date Range', 'Created', 'View By', 'Ticket Cost', 'Ticket Refund', 'Ticket Net',/*'Processing Fees','Processing Fees Refund','Processing Net',*/ 'Platform Fees', 'Platform Fees Refund', 'Platform Fees Net', 'Discount', 'Payment Total', 'Total Refund', 'Total Net'];
				return $columnsHeader;

			case 'card_collection_report_columns':
				$columns = ['Booking', 'Booking Date', 'Created Date', 'Customer Name', 'Ticket Cost', 'Ticket Refund', 'Ticket Net',/*'Processing Fees','Processing Fees Refund','Processing Net',*/ 'Platform Fees', 'Platform Fees Refund', 'Platform Fees Net', 'Discount', 'Payment Total', 'Refund Total', 'Total Net'];
				return $columns;

			case 'farharbor_card_collection_report_columns':
				$columns = ['Fareharbor Card Collection'];
				return $columns;

			case 'stripe_collection_report_columns':
				$columns = ['Booking ID', 'Booking Date', 'Created Date', 'Via', 'Booking Amount', 'Refund Amount', 'Payment Amount', 'Customer Name', 'Phone', 'Status'];
				return $columns;

			case 'cash_collection_report_columns':
				$columns = ['Booking ID', 'Booking Date', 'Created Date', 'Via', 'Payment Amount', 'Refund Amount', 'Final Amount', 'Customer Name', 'Comments', 'Status'];
				return $columns;

			case 'cash_fees_collection_report_columns':
				$columns = ['Booking ID', 'Booking Date', 'Created Date', 'Via', 'Payment Amount', 'Paid Fees', 'Refund Amount', 'Fees Refund', 'Final Amount', 'Customer Name', 'Comments', 'Status'];
				return $columns;

			case 'wire_collection_report_columns':
				$columns = ['Booking ID', 'Booking Date', 'Created Date', 'Via', 'Normal Refund', 'Customer Name', 'Comments', 'Status'];
				return $columns;
			case 'wire_collection_report_columns_fareharbor':
				$columns = ['Booking ID', 'Booking Date', 'Created Date', 'Via', 'Normal Refund', 'Fareharbor Refund', 'Customer Name', 'Comments', 'Status'];
				return $columns;

			case 'wire_collection_report_columnsHeader_overall':
				$columnsHeader = ['Date Range', 'Created', 'View By', 'Normal Refund', 'Total Refund'];
				return $columnsHeader;
			case 'wire_collection_report_columnsHeader_overall_fareharbor':
				$columnsHeader = ['Date Range', 'Created', 'View By', 'Normal Refund', 'Fareharbor Refund', 'Total Refund'];
				return $columnsHeader;

			case 'refund_payment_report_TopColumns':
				$TopColumns = ['Date Range', 'Created', 'Refund Amount'];
				return $TopColumns;

			case 'refund_payment_report_columns':
				$columns = ['Booking ID', 'Name', 'Booking Date', 'Phone', 'Email', 'Amount', 'Refund', 'Booking Status', 'Refund Date'];
				return $columns;

			case 'affiliate_report_TopColumns':
				$TopColumns = ['Date Range', 'Created', 'Booking Amount', 'Paid Amount', 'Refund Amount', 'Unpaid Amount'];
				return $TopColumns;

			case 'affiliate_invoice_report_TopColumns':
				$TopColumns = ['Date Range', 'Created', 'Booking Amount', 'Tourists Total', 'Infants Total', 'Paid Amount', 'Refund Amount', 'Unpaid Amount'];
				return $TopColumns;

			case 'affiliate_comprehensive_report_TopColumns':
				$TopColumns = ['Date Range', 'Created', 'Booking Amount', 'Cancellation Amount', 'Paid Amount', 'Refund Amount', 'Net Card', 'Net Cash', 'Net Invoiced', 'Paid Invoices', 'Unpaid Invoices', 'Total Net Collected', 'Unpaid Amount'];
				return $TopColumns;

			case 'affiliate_report_column':
				$columns = ["ID", "Customer Name", "Affiliate Name", "Tourists", "Package", "Booking Date", "Created Date", "Total Tourists", "Booking Status", "Booking Amount", 'Paid Amount', 'Refund Amount', 'Unpaid Amount'];
				return $columns;

			case 'affiliate_comprehensive_report_column':
				$columns = ["ID", "Customer Name", "Affiliate Name", "Tourists", "Package", "Tour Date", "Tour Time", "Payment Date Time", "Created Date", "Total Tourists", "Booking Status", "Booking Amount", 'Paid Amount', 'Refund Amount', 'Unpaid Amount'];
				return $columns;

			case 'affiliate_invoice_report_bottomColumns':
				$columns = ["Affiliate", "Amount"];
				return $columns;

			case 'affiliate_booked_report_column':
				$columns = ["ID", "Customer Name", "Affiliate Name", "Tour Time", "Tourists", "Package", "Booking Date", "Created Date", "Total Tourists", "Booking Status", "Booking Amount", 'Paid Amount', 'Refund Amount', 'Unpaid Amount'];
				return $columns;

			case 'cancel_booking_report_TopColumns':
				$TopColumns = ['Date Range', 'Created', 'Booking Amount', 'Paid Amount', 'Unpaid Amount', 'Total'];
				return $TopColumns;

			case 'cancel_booking_report_columns':
				$columns = ["Booking ID", "Name", "Tourists", "Package", "Booking Date", "Created Date", "Total Tourist", "Booking Status", "Booking Amount", 'Paid Amount', 'Unpaid Amount'];
				return $columns;

			case 'today_avialability_report_columns':
				$columns = ["#", 'Time Slot', 'Total Seats', 'Available Seats', 'Booked Seats', 'Canceled Seats'];
				return $columns;

			case 'email_report_column':
				$columns = ["ID", 'Booking Date', 'Time', 'Tourists', 'Email'];
				return $columns;

			case 'tip_summary_report__columns':
				$columns = ["#", 'Staff Name', 'Booking Date', 'Tip Amount', '	Total Booking'];
				return $columns;

			case 'financial_report_TopColumns':
				$TopColumns = ['Date Range', 'Created', 'Tourists Total', 'Booking Amount', 'Payment Amount', 'Unpaid Amount', 'Refund Amount', 'Cancellation Amount'];
				return $TopColumns;

			case 'financial_report_column':
				$columns = ['Date', 'Total tourists', 'Amount Total', 'Paid Amount', 'Unpaid Amount', 'Refund Amount', 'Cancelled Amount', 'Persons'];
				return $columns;

			case 'fareharbor_booking_report_TopColumns':
				$TopColumns = ['Date Range', 'Total Card Amount', 'Total Cash Amount', 'Total Invoiced Amount', 'Total'];
				return $TopColumns;

			case 'fareharbor_booking_report_columns':
				$columns = ['Booking ID', 'Booking Date', 'Via', 'Amount', 'Name', 'Phone', 'Email', 'Booking Status', 'Comments'];
				return $columns;

			case 'failed_payment_report_columns':
				$columns = ['Booking ID', 'Name', 'Booking Date', 'Create Date', 'No of Person', 'Amount', 'Payment', 'Status'];
				return $columns;

			case 'invalid_payment_report_columns':
				$columns = ['Id', 'Booking ID', 'Table Name'];
				return $columns;

			case 'closure_emails_report_column':
				$columns = ["Booking Id", 'Time', 'Name', 'Email', 'Phone', 'Status', 'Email Sent?'];
				return $columns;

			case 'promotion_report_summary_column':
				$columns = ['Date Range', 'Created', 'Total Tourists', 'Total Infants', 'Total Amount', 'Total Discounted Amount', 'Total Final Amount'];
				return $columns;

			case 'promotion_report_promo_column':
				$columns = ['Promo Code', 'Usage', 'Total Bookings', 'Total Amount', 'Total Discounted Amount', 'Total Final Amount'];
				return $columns;

			case 'promotion_report_column':
				$columns = ['Booking', 'Booking Date', 'Customer Name', 'Status', 'Tourists', 'Infants', 'Amount', 'Discount', 'Final Amount'];
				return $columns;

			case 'promotion_report_rate_category_column':
				$columns = ['Rate Group', 'Tourist', 'Rate', 'Tax', 'Permit', 'Total'];
				return $columns;

			case 'revenue_report':
				$columns = ['Booking Id', 'Tour Name', 'Booking Purchase Date', 'Booking Status', 'Tour Name', 'Tour Date', 'Tour Time', 'Transaction Type',  'Billable', 'Transaction Cost', 'Gross Revenue',  'Discount', 'Refund Total', 'Platform Fees',  /*'Processing Fees',*/  'Net Revenue'];
				return $columns;

			case 'revenue_report_TopColumns':
				$TopColumns = ['Date Range', 'Created', 'Total Gross', 'Total Refund', 'Payment Fees', 'Refund Fees', 'Total Net'];
				return $TopColumns;

			case 'invoice_insights_report':
				$columns = ['Id', 'Status', 'Affiliate Id', 'Affiliate Name', 'Affiliate Email', 'Affiliate Phone', 'Booking Id', 'Booked Date', 'Payment Type', 'Payment Status', 'Tour Name', 'Tour Date', 'Tour Time', 'Tourist', 'Infants', 'Due Date', 'Booking Total', 'Refund', 'Paid'];
				return $columns;

			case 'staff_booking_report':
				$columns = ['Sr. No.', 'Booking Id', 'Booking Date', 'Created Date', 'Tour Time', 'Booking Amount', 'Customer Name', 'Status'];
				return $columns;

			case 'staff_booking_report_columnsHeader':
				$columnsHeader = ['Tour Operator Staff Name', 'Total Bookings'];
				return $columnsHeader;

			case 'agenda_columns':
				$columnsHeader = ['Booking #', 'Contact', 'Total', 'Not Checked In', '1st Check In', '2nd Check In', 'Absent', 'Comments', 'Waivers', 'Payment', 'Booked By', 'Notes'];
				return $columnsHeader;

			case 'transaction_report':
				$columns = ['Booking Id', 'Tour Date', 'Status', 'Payment Total', 'Discount', 'Refund Amount', 'Net Total'];
				return $columns;

			case 'transaction_report_columnsHeader':
				$columnsHeader = ['Payment Total', 'No. of Bookings', 'Refund Total', 'Fee Total', 'Total Discount', 'Total Net'];
				return $columnsHeader;

			case 'transaction_summary_report':
				$columns = ['Tour Date', 'Transaction Date', 'Confirmation', 'Status', 'Contact Name', 'Tourist', 'Purchase Type', 'Gross', 'Discount', 'Fees', 'Refund', 'Net'];
				return $columns;

			case 'transaction_summary_report_columnsHeader':
				$columnsHeader = ['Total Tourist', 'Payment Total', 'Refund Total', 'Fee Total', 'Total Discount', 'Total Net'];
				return $columnsHeader;

			case 'affiliate_seats_report':
				$columns = ['Name', 'Package', 'Slot Date', 'Assigned Seats', 'Booked Seats', 'Cancelled Seats', '	Fulfilled Seats', 'No Show'];
				return $columns;

			case 'affiliate_seats_report_columnsHeader':
				$columnsHeader = ['Date Range', 'Total Assigned Seats', 'Total Booked Seats', 'Total Cancelled Seats', 'Total Fulfilled Seats', 'Total No-Show'];
				return $columnsHeader;

			case 'addons_package_report':
				$columns = ['Add-Ons Type', 'Tour Package Name', 'Number of booking', 'Priced', 'Price', 'Paid', 'Refunded', 'Net'];
				return $columns;

			case 'addons_package_report_columnsHeader':
				$columnsHeader = ['Date Range', 'Net Total'];
				return $columnsHeader;

			case 'addons_package_details_report':
				$columns = ['Add-Ons Type', 'Tour Package Name', 'Booking IDs', 'Priced', 'Price', 'Paid', 'Refunded', 'Net'];
				return $columns;

			case 'addons_package_details_report_columnsHeader':
				$columnsHeader = ['Date Range', 'Net Total'];
				return $columnsHeader;

			case 'activity_logs_report_column':
				$columns = ["ID", 'User Type', 'User Name', 'Event', 'Data', 'Created Date and Time', 'Auditable Type'];
				return $columns;

			case 'wwr_roster_report': // wwr => White Water Rafting
				$columns = ["Sr. No.", "Package", "Date", "Time", "Duration", "Booking ID", "Customer", "Contact", "Age", "Weight(lbs)", "Special Needs"];
				return $columns;

			case 'ota_report':
				$columns = ['Booking Id', 'Reference Id', 'Purchase Date', 'Tour Date', 'Tour Time', 'Tourists', 'Via', 'Status', 'Amount', 'Refund', 'Net'];
				return $columns;

			case 'ota_report_top_columns':
				$TopColumns = array('Date Range', 'Created', 'Tourists', 'Amount Paid', 'Total Refund', 'Total Net');
				return $TopColumns;

			case 'tip_distributions_report_columns_headers':
				$columnsHeader = ['Tour Guide Name', 'Total Payout Amount'];
				return $columnsHeader;

			case 'tip_distributions_report_columns':
				$columns = ['Booking ID', 'Booking Date', 'Tour', 'Customer Name', 'Tip Amount', 'Status'];
				return $columns;	
		}
	}
}

if (!function_exists('reports')) {
	function reports()
	{
		$reports = [
			"Permit Fee Report" => "Permit Fee Report",
			"Permit Fee Details Report" => "Permit Fee Details Report",
			"Availability Report" => "Availability Report",
			"Financial Report" => "Financial Report",
			"Tip Report" => "Tip Report",
			"Tip Summary Report" => "Tip Summary Report",
			"Tax Collection Report" => "Tax Collection Report",
			"Software Fee Report" => "Software Fee Report",
			"Cash Collection Report" => "Cash Collection Report",
			"Card Collection Report" => "Card Collection Report",
			"Check/Wire Collection Report" => "Check/Wire Collection Report",
			"Stripe Payment Report" => "Stripe Payment Report",
			"Failed Payment Report" => "Failed Payment Report",
			"Refund payment" => "Refund payment",
			"Affiliate Booked Report" => "Affiliate Booked Report",
			"Affiliate Seats Report" => "Affiliate Seats Report",
			"Affiliate Invoice Report" => "Affiliate Invoice Report",
			"Affiliate Comprehensive Report" => "Affiliate Comprehensive Report",
			"Cancel Booking Report" => "Cancel Booking Report",
			"Email Report" => "Email Report",
			"FareHarbor Booking Report" => "FareHarbor Booking Report",
			"Daily Activity Report" => "Daily Activity Report",
			"Invalid Bookings Report" => "Invalid Bookings Report",
			"Closure Emails Report" => "Closure Emails Report",
			"Revenue Report" => "Revenue Report",
			"Revenue Summary Report" => "Revenue Summary Report",
			"Stripe Refund Lookup" => "Stripe Refund Lookup",
			"Affiliate Invoice Insights Report" => "Affiliate Invoice Insights Report",
			"Promotion Report" => "Promotion Report",
			"OTAs Booking Report" => "OTAs Booking Report",
			"Transaction Report" => "Transaction Report",
			"Staff Booking Report" => "Staff Booking Report",
			"Transaction Summary Report" => "Transaction Summary Report",
			"Add-ons Package Report" => "Add-ons Package Report",
			"Add-ons Package Details Report" => "Add-ons Package Details Report",
			"Activity Logs Report" => "Activity Logs Report",
			"Payment Breakdown Report" => "Payment Breakdown Report",
			"Save Credit Card Report" => "Save Credit Card Report",
		];

		asort($reports);

		return $reports;
	}
}

if (!function_exists("staticReportList")) {
	function staticReportList()
	{
		$reportNames = [
			"Permit Fee Report",
			"Permit Fee Details Report",
			"Availability Report",
			"Financial Report",
			"Tip Report",
			"Tip Summary Report",
			"Cash Collection Report",
			"Card Collection Report",
			"Stripe Payment Report",
			"Failed Payment Report",
			"Refund payment",
			"Affiliate Booked Report",
			"Affiliate Seats Report",
			"Affiliate Invoice Report",
			"Affiliate Comprehensive Report",
			"Cancel Booking Report",
			"Email Report",
			"FareHarbor Booking Report",
			"Daily Activity Report",
			"Closure Emails Report",
			"Revenue Report",
			"Revenue Summary Report",
			"Promotion Report",
			"Transaction Report",
			"Transaction Summary Report",
			"Payment Breakdown Report",
			"OTAs Booking Report",
			"Add-ons Package Report",
			"Add-ons Package Details Report",
			"Activity Logs Report",
			"Save Credit Card Report"
		];

		asort($reportNames);
		return $reportNames;
	}
}

if (!function_exists("adminStaicReportList")) {
	function adminStaicReportList()
	{
		$reportnames = [
			"Tax Collection Report",
			"Software Fee Report"
		];
		asort($reportnames);
		return $reportnames;
	}
}

if (!function_exists('checkins')) {
	function checkins()
	{
		//Don't change existing key-value here, just add new key values if needs
		$checkins = [
			"N" => "Not Checked In",
			"1" => "1st Check In",
			"2" => "2nd Check In",
			"A" => "Absent"
		];

		return $checkins;
	}
}

if (!function_exists('loadPdf')) {
	function loadPdf($fileName)
	{
		if (isset(tenancy()->tenant)) {
			$path = storage_path('app' . DIRECTORY_SEPARATOR . 'pdf' . DIRECTORY_SEPARATOR . tenancy()->tenant->id . DIRECTORY_SEPARATOR . $fileName);
		} else {
			$path = storage_path('app' . DIRECTORY_SEPARATOR . 'pdf' . DIRECTORY_SEPARATOR . $fileName);
		}

		if (!\Illuminate\Support\Facades\File::exists($path)) {
			abort(404);
		}

		$file = \Illuminate\Support\Facades\File::get($path);
		$type = \Illuminate\Support\Facades\File::mimeType($path);

		$response = \Illuminate\Support\Facades\Response::make($file, 200);
		$response->header("Content-Type", $type);

		return $response;
	}
}

if (!function_exists('moneyFormat')) {
	function moneyFormat($amount)
	{
		$amounts = number_format((float) $amount, 2);
		return currencyFormation(false, true) . $amounts;
	}
}

if (!function_exists('roundout')) {
	function roundout($amount, $places = 2)
	{
		if ($places < 0) {
			$places = 0;
		}
		return round($amount, $places);
	}
}

if (!function_exists('initStripeClient')) {
	function initStripeClient()
	{
		$stripeKey = config('constants.stripe_keys.' . config('app.env') . '.0.secret_key');
		return new Stripe\StripeClient($stripeKey);
	}
}

if (!function_exists('getStripePublicKey')) {
	function getStripePublicKey()
	{
		$stripeKey = config('constants.stripe_keys.' . config('app.env') . '.0.public_key');

		if (empty($stripeKey)) {
			throw new Exception('Stripe public key not configured for environment: ' . config('app.env'));
		}

		return $stripeKey;
	}
}

if (!function_exists('getStripeSecretKey')) {
	function getStripeSecretKey()
	{
		$stripeKey = config('constants.stripe_keys.' . config('app.env') . '.0.secret_key');

		if (empty($stripeKey)) {
			throw new Exception('Stripe secret key not configured for environment: ' . config('app.env'));
		}

		return $stripeKey;
	}
}

if (!function_exists('userType')) {
	function userType()
	{
		$userType = 'Customer';
		if (!isset(\Illuminate\Support\Facades\Auth::user()->id)) {
			return $userType;
		}

		$userType = (session('guard') == "affiliate") ? 'Affiliate' : (\Illuminate\Support\Facades\Auth::user()->tour_operator_id ? 'Tour Operator' : 'Admin');

		return $userType;
	}
}
if (!function_exists('checkUserRole')) {
	function checkUserRole()
	{
		$userType = userType();
		$userRole = '';
		if($userType == 'Tour Operator'){
			$userId = Auth::id();
			$userInfo = DB::table('tour_operator_staff as tos')
			->join('model_has_roles as mhr', 'mhr.model_id', '=', 'tos.id')
			->join('roles as r', 'r.id', '=', 'mhr.role_id')
			->where('mhr.model_type', 'App\Models\Staff')
			->where('tos.id', $userId)
			->first(['tos.id', 'r.name']);
			$userRole = $userInfo->name ?? '';
			#TODO: fix this null issue. this might return null if the user doesn't have a role assigned, so you might want to handle that case as well
		}
		return $userRole;
	}
}

if (!function_exists('getGuard')) {
	function getGuard($userType)
	{
		$guard = match ($userType) {
			"Affiliate" => 'affiliate',
			"Tour Operator" => 'tour_operator_staff',
			default => 'admin',
		};

		return $guard;
	}
}
if (!function_exists('profilePhoto')) {
	function profilePhoto($tenantId = null, $profilePhoto = null)
	{
		if ($tenantId == null && getSubdomainFromHost()) {
			$tenantId = getSubdomainFromHost();
		}

		return getDiskUrl("images/tour-operator/$tenantId/users/$profilePhoto");
	}
}
if (!function_exists('oldBookingId')) {
	function oldBookingId()
	{
		$setting = \Illuminate\Support\Facades\DB::table('settings')
			->where('key', 'OLD_BOOKING_ID')
			->first();

		$oldBookingId = 0;
		if ($setting) {
			$oldBookingId = $setting->value;
		}

		return $oldBookingId;
	}
}

if (!function_exists('surchargeDates')) {
	function surchargeDates()
	{
		$surchargeDates = \Illuminate\Support\Facades\DB::table('surcharge_dates')
			->pluck('date')
			->toArray();

		return $surchargeDates;
	}
}

if (!function_exists('QrCode')) {
	function QrCode($value)
	{
		$QrCode = SimpleSoftwareIO\QrCode\Facades\QrCode::size(200)->generate($value);
		return $QrCode;
	}
}

if (!function_exists('getBarcode')) {
	function getBarcode($value)
	{
		$barcode = Milon\Barcode\Facades\DNS1DFacade::getBarcodeHTML($value, 'C128', 2, 100);
		return $barcode;
	}
}

if (!function_exists('viatorFlags')) {
	function viatorFlags()
	{
		$flags = [
			"NEW_ON_VIATOR",
			"VIATOR_EXCLUSIVE",
			"FREE_CANCELLATION",
			"ALL_SALES_FINAL",
			"SKIP_THE_LINE",
			"PRIVATE_TOUR",
			"SPECIAL_OFFER",
			"LIKELY_TO_SELL_OUT"
		];
		return $flags;
	}
}

if (!function_exists('viatorBookingConfirmationTypes')) {
	function viatorBookingConfirmationTypes()
	{
		$flags = [
			"INSTANT",
			"MANUAL",
			"INSTANT_THEN_MANUAL"
		];
		return $flags;
	}
}

if (!function_exists('viatorBookingQuestions')) {
	function viatorBookingQuestions()
	{
		$questions = [
			/*[
				"legacyBookingQuestionId" => 1,
				"id" => "TRAVEL_DATE",
				"type" => "DATE",
				"group" => "PER_BOOKING",
				"label" => "Travel Date",
				"required" => "MANDATORY",
				// "maxLength" => 80
			],
			[
				"legacyBookingQuestionId" => 2,
				"id" => "TRAVEL_TIME",
				"type" => "TIME",
				"group" => "PER_BOOKING",
				"label" => "Travel Time",
				"required" => "MANDATORY",
				// "maxLength" => 80
			],*/
			[
				"legacyBookingQuestionId" => 1,
				"id" => "CUSTOMER_NAME",
				"type" => "STRING",
				"group" => "PER_BOOKING",
				"label" => "Customer Name",
				"required" => "MANDATORY",
				"maxLength" => 80
			],
			[
				"legacyBookingQuestionId" => 2,
				"id" => "CUSTOMER_PHONE_NUMBER",
				"type" => "NUMBER_AND_UNIT",
				"group" => "PER_BOOKING",
				"label" => "Phone Number",
				"required" => "OPTIONAL",
				"maxLength" => 30
			],
			[
				"legacyBookingQuestionId" => 3,
				"id" => "EMAIL",
				"type" => "STRING",
				"group" => "PER_BOOKING",
				"label" => "Email",
				"required" => "MANDATORY",
				"maxLength" => 50
			],
			[
				"legacyBookingQuestionId" => 4,
				"id" => "AGEBAND",
				"type" => "STRING",
				"group" => "PER_BOOKING",
				"label" => "Email",
				"required" => "MANDATORY",
				"maxLength" => 50,
				"allowedAnswers" => [
					"ADULT",
					"SENIOR",
					"YOUTH",
					"CHILD",
					"INFANT"
				],
			],
			[
				"legacyBookingQuestionId" => 5,
				"id" => "COMMENT_BY_TOURIST",
				"type" => "LOCATION_REF_OR_FREE_TEXT",
				"group" => "PER_BOOKING",
				"label" => "Any additional comment or request from tourist",
				"required" => "OPTIONAL",
				// "maxLength" => 2000
			],
		];
		return $questions;
	}
}

if (!function_exists('hotelImage')) {
	function hotelImage($tenantId, $tourPackageId, $hotelId, $name)
	{
		if ($name == null) {
			return asset('images/no-image-available.png');
		}

		return getDiskUrl("images/tour-operator/$tenantId/package/$tourPackageId/hotels/$hotelId/$name");
	}
}

if (!function_exists('tourOperatorLogo')) {
	function tourOperatorLogo($tenantId = null, $tourOperatorLogo = null)
	{
		if ($tenantId == null && getSubdomainFromHost()) {
			$tenantId = getSubdomainFromHost();
			$tourOperator = \Illuminate\Support\Facades\DB::table('tour_operators')->first('logo');
			$tourOperatorLogo = $tourOperator->logo;
		}

		return getDiskUrl("images/tour-operator/$tenantId/logo/$tourOperatorLogo");
	}
}
if (!function_exists('profilePhoto')) {
	function profilePhoto($tenantId = null, $profilePhoto = null)
	{
		if ($tenantId == null && getSubdomainFromHost()) {
			$tenantId = getSubdomainFromHost();
		}

		return getDiskUrl("images/tour-operator/$tenantId/users/$profilePhoto");
	}
}


if (!function_exists('checkGcwTenant')) {
	function checkGcwTenant()
	{
		return match (getSubdomainFromHost()) {
			App\Constants\Constants::TENANT_GCW => true,
			default => false,
		};
	}
}

if (!function_exists('tourPackageLogo')) {
	function tourPackageLogo($tenantId, $tourPackageId, $path)
	{
		return getDiskUrl("images/tour-operator/$tenantId/package/$tourPackageId/$path");
	}
}

if (!function_exists('tourPackageImage')) {
	function tourPackageImage($tenantId, $tourPackageId)
	{
		$tourPkgImage = \Illuminate\Support\Facades\DB::table('tour_package_photos')
			->where('tour_package_id', $tourPackageId)
			->where('placement', 7)
			->where('status', 'Active')
			->where('deleted_by', 0)
			->latest()
			->first(['path']);

		return getDiskUrl("images/tour-operator/$tenantId/package/$tourPackageId/" . ($tourPkgImage->path ?? 'images/no-photo.png'));
	}
}

if (!function_exists('tourOperatorPrintLogo')) {
	function tourOperatorPrintLogo($tenantId, $printLogo)
	{
		return getDiskUrl("images/tour-operator/$tenantId/logo/$printLogo");
	}
}

if (!function_exists('posOrderGenerateQr')) {
	function posOrderGenerateQr($tenantId, $bookingGroupId)
	{
		$disk = Illuminate\Support\Facades\Storage::disk('s3');
		$bookingPrefix = getSettingsByKey("BOOKING_ID_PREFIX");
		$prefix = (isset($bookingPrefix) && isset($bookingPrefix->value)) ? $bookingPrefix->value : "";
		$fileName = "qrcode-$bookingGroupId.png";
		if ($disk->exists("images/tour-operator/$tenantId/qrcode/$fileName")) {
			return getDiskUrl("images/tour-operator/$tenantId/qrcode/$fileName");
		} else {
			$qrcode = SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')->size(200)->generate($prefix . "" . $bookingGroupId);
			$disk->put("images/tour-operator/$tenantId/qrcode/$fileName", $qrcode);
			return getDiskUrl("images/tour-operator/$tenantId/qrcode/$fileName");
		}
	}
}

if (!function_exists('tourOperatorNavigationLogo')) {
	function tourOperatorNavigationLogo()
	{
		$tenantId = getSubdomainFromHost();
		$tourOperator = \Illuminate\Support\Facades\DB::table('tour_operators')->first('logo_navigation');
		$tourOperatorNavigationLogo = $tourOperator->logo_navigation;
		return ($tourOperatorNavigationLogo != '') ? getDiskUrl("images/tour-operator/$tenantId/logo/$tourOperatorNavigationLogo") : asset('images/no-image-available.png');
	}
}

if (!function_exists('getLogo')) {
	function getLogo($booking)
	{
		if (!isset($booking)) {
			return asset('images/no-image-available.png');
		}

		return (isset($booking->logo) && $booking->logo != '') ? getDiskUrl("images/tour-operator/$booking->tenant_id/logo/$booking->logo") : (($booking->tour_op_logo_print != '') ? getDiskUrl("images/tour-operator/$booking->tenant_id/logo/$booking->tour_op_logo_print") : (($booking->tour_op_logo != '') ? getDiskUrl("images/tour-operator/$booking->tenant_id/logo/$booking->tour_op_logo") : asset('images/no-image-available.png')));
	}
}

if (!function_exists('getDiskUrl')) {
	function getDiskUrl($path)
	{
		$fallback = asset('images/no-image-available.png');

		try {
			$disk = Illuminate\Support\Facades\Storage::disk('s3');

			// exists() can throw (e.g. a 403 HeadObject when the bucket policy
			// lacks s3:ListBucket, or a transient S3/network error) instead of
			// returning false. Treat any failure as "no image" so a missing or
			// unreachable asset never takes down the whole page.
			return $disk->exists($path) ? $disk->url($path) : $fallback;
		} catch (\Throwable $e) {
			\Illuminate\Support\Facades\Log::warning('getDiskUrl failed for "' . $path . '": ' . $e->getMessage());
			return $fallback;
		}
	}
}

if (!function_exists('printTourOptCustomer')) {
	function printTourOptCustomer($booking)
	{
		$tourOpInfo = [];
		$custInfo = [];

		if ($booking->tour_op_email != '')
			$tourOpInfo[] = e($booking->tour_op_email);

		if ($booking->tour_op_phone != '')
			$tourOpInfo[] = e($booking->tour_op_phone);

		if ($booking->tour_op_website != '')
			$tourOpInfo[] = e($booking->tour_op_website);

		if ($booking->cust_email != '')
			$custInfo[] = e($booking->cust_email);

		if ($booking->cust_phone != '')
			$custInfo[] = e($booking->cust_phone);

		return [
			'tourOpt' => $tourOpInfo,
			'tourCust' => $custInfo
		];
	}
}

if (!function_exists('hasSubdomain')) {
	function hasSubdomain()
	{
		$parsed = parse_url(request()->getHost());

		$exploded = explode('.', $parsed["path"]);

		if (count($exploded) > 2 && $exploded[0] != "staging" && $exploded[0] != "live") {
			return $exploded[0];
		}

		return false;
	}
}

if (!function_exists('isPermissionGranted')) {
	function isPermissionGranted()
	{
		return (config('app.env') == "local" && !isset(tenant()->id)) || !hasSubdomain();
	}
}

if (!function_exists('isCentralDomain')) {
	function isCentralDomain()
	{
		return in_array(request()->getHost(), config('tenancy.central_domains'));
	}
}

if (!function_exists('generateDateRange')) {
	function generateDateRange($startDate, $endDate)
	{
		$dates = [];
		$currentDate = strtotime($startDate);

		while ($currentDate <= strtotime($endDate)) {
			$dates[] = date('Y-m-d', $currentDate);
			$currentDate = strtotime('+1 day', $currentDate);
		}

		return $dates;
	}
}

if (!function_exists('agendaView')) {
	function agendaView()
	{
		$agendaView = config('constants.agenda_view.slot_wise');
		$setting = \Illuminate\Support\Facades\DB::table('settings')
			->where('key', 'AGENDA_VIEW')
			->first();

		if ($setting) {
			$agendaView = $setting->value;
		}

		return $agendaView;
	}
}

if (!function_exists('cartView')) {
	function cartView()
	{
		$cartView = 0;
		$setting = \Illuminate\Support\Facades\DB::table('settings')
			->where('key', 'CART_VIEW')
			->first();

		if ($setting) {
			$cartView = $setting->value;
		}

		return $cartView;
	}
}

if (!function_exists('cashFees')) {
	function cashFees()
	{
		$cashFees = 0;
		$setting = \Illuminate\Support\Facades\DB::table('settings')
			->where('key', 'CASH_FEES')
			->first();

		if ($setting) {
			$cashFees = $setting->value;
		}

		return $cashFees;
	}
}
if (!function_exists('cashPriceEnabled')) {
	function cashPriceEnabled()
	{
		$cashPrice = 0;
		$setting = \Illuminate\Support\Facades\DB::table('settings')
			->where('key', 'CASH_PRICE')
			->first();

		if ($setting) {
			$cashPrice = $setting->value;
		}

		return $cashPrice;
	}
}
if (!function_exists('minMaxPaxApply')) {
	function minMaxPaxApply()
	{
		$minMaxPaxApply = 1;
		$setting = \Illuminate\Support\Facades\DB::table('settings')
			->where('key', 'MIN_MAX_APPLY')
			->first();

		if ($setting) {
			$minMaxPaxApply = $setting->value;
		}

		return $minMaxPaxApply;
	}
}

if (!function_exists('getSettings')) {
	function getSettings()
	{
		$settings = \Illuminate\Support\Facades\DB::table('settings')->get(['key', 'value']);
		return $settings;
	}
}

if (!function_exists('getSettingsByKey')) {
	/**
	 * Example Usage: 
	 * $prefix = getSettingsByKey("BOOKING_ID_PREFIX") 
	 * 		>> (key->"BOOKING_ID_PREFIX", value -> "DX-" )
	 * Note it will return the same structure if a settings can not be found
	 * with an error.
	 */

	function getSettingsByKey($key)
	{
		$defaultVal = ["key" => $key, "value" => null, "error" => "not found"];
		try {
			$setting =  DB::table('settings')
				->where('key', '=', $key)
				->get(['key', 'value'])
				->first();
			return ($setting) ? $setting : (object) $defaultVal;
		} catch (Exception $err) {
			$defaultVal["error"] = $err->getMessage();
			return (object) $defaultVal;
		}
	}
}


if (!function_exists('hasSetting')) {
	function hasSetting($setting)
	{
		$res = DB::table('settings')->where('key', $setting)->first();
		if (isset($res) && isset($res->key) && $res->value == 1) {
			return true;
		}

		return false;
	}
}
if (!function_exists('paymentMethodsEnabled')) {
	function paymentMethodsEnabled()
	{
		$userType = userType();
		$settingKey = ($userType == 'Affiliate') ? 'AFFILIATE_PAYMENT_METHODS' : 'EMPLOYEES_PAYMENT_METHODS';
		$res = DB::table('settings')->where('key', $settingKey)->first();
		if (isset($res) && isset($res->key)) {
			return $res->value;
		}

		return false;
	}
}

if (!function_exists('hasCustomRateSettingsEnabled')) {
	function hasCustomRateSettingsEnabled($affiliateId = null)
	{
		if ($affiliateId) {
			return  hasSetting('CDR_AFFILIATES');
		} else {
			return hasSetting('CDR_PACKAGES');
		}
	}
}

if (!function_exists('getSubdomainFromHost')) {
	function getSubdomainFromHost()
	{
		$host = $_SERVER['HTTP_HOST'] ?? request()->getHost();
		if (!$host) {
			return null;
		}
		if (isset(explode('.', explode('|ext|', str_replace(array('.com', '.test'), '|ext|', $host))[0])[0])) {
			return explode('.', explode('|ext|', str_replace(array('.com', '.test'), '|ext|', $host))[0])[0];
		}

		return null;
	}
}

if (!function_exists('category_type')) {
	function category_type()
	{
		$category_type = ['Not Allowed', 'Allowed', 'Cover Images', 'Map', 'Splash'];

		return $category_type;
	}
}

if (!function_exists('tourOperator')) {
	function tourOperator()
	{
		$tourOperator = \App\Models\TourOperator::where('status', 'Active')
			->orderBy('name')
			->first();

		return $tourOperator;
	}
}

if (!function_exists('bookingPrefix')) {
	function bookingPrefix($id, $remove = false)
	{
		$setting = \Illuminate\Support\Facades\DB::table('settings')->where('key', 'BOOKING_ID_PREFIX')->first();

		if ($remove) {
			if ($setting && \Illuminate\Support\Str::startsWith($id, $setting->value)) {
				return \Illuminate\Support\Str::remove($setting->value, $id);
			}

			return $id;
		}

		$updatedId = $setting ? $setting->value . $id : $id;

		return $updatedId;
	}
}

if (!function_exists('bookingPrefixSetting')) {
	function bookingPrefixSetting()
	{
		$setting = \Illuminate\Support\Facades\DB::table('settings')->where('key', 'BOOKING_ID_PREFIX')->first();
		return $setting ? $setting->value : '';
	}
}

if (!function_exists('getDrivingDistance')) {
	// function getDrivingDistance($lat1 = '51.4822656', $lat2 = '51.4994794', $long1 = '-0.1933769', $long2 = '-0.1269979') {
	// function getDrivingDistance($lat1 = '19.228825', $lat2 = '28.644800', $long1 = '72.854118', $long2 = '77.216721') {
	// function getDrivingDistance($lat1 = '32.732998', $lat2 = '8.088306', $long1 = '74.864273', $long2 = '77.538452') {

	function getDrivingDistance($lat1, $lat2, $long1, $long2)
	{
		$url = "https://api.distancematrix.ai/maps/api/distancematrix/json?origins=$lat1,$long1&destinations=$lat2,$long2&mode=driving&key=9Xx7984DIswtL89Tc61c6QwUsZOOTkBBhvIhbTEly7cWPMKv6R4fMhhczDqE0jeK";
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_PROXYPORT, 3128);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		$response = curl_exec($ch);
		curl_close($ch);
		$responseDecoded = json_decode($response, true);

		$status = "ERROR";
		$dist   = 0;
		$time   = 0;
		if ($responseDecoded['rows'][0]['elements'][0]['status'] === 'OK') {
			$dist   = $responseDecoded['rows'][0]['elements'][0]['distance']['text']; // distance
			$time   = stringToTimeConversion($responseDecoded['rows'][0]['elements'][0]['duration']['text']); // duration
			$status = "OK";
		}

		return ['status' => $status, 'distance' => $dist, 'time' => $time];
	}
}

if (!function_exists('stringToTimeConversion')) {
	function stringToTimeConversion($timeString)
	{
		// Initialize days, hours, and minutes
		$days = 0;
		$hours = 0;
		$minutes = 0;

		// Extract days, hours, and minutes from the string
		if (preg_match('/(\d+) day (\d+) hour (\d+) mins/', $timeString, $matches)) {
			$days = $matches[1];
			$hours = $matches[2] + $days * 24;
			$minutes = $matches[3];
		} elseif (preg_match('/(\d+) day (\d+) hour/', $timeString, $matches)) {
			$days = $matches[1];
			$hours = $matches[2] + $days * 24;
		} elseif (preg_match('/(\d+) hour (\d+) mins/', $timeString, $matches)) {
			$hours = $matches[1];
			$minutes = $matches[2];
		} elseif (preg_match('/(\d+) day/', $timeString, $matches)) {
			$days = $matches[1];
			$hours = $matches[1] + $days * 24;
		} elseif (preg_match('/(\d+) mins/', $timeString, $matches)) {
			$minutes = $matches[1];
		}

		return ($hours || $minutes) ? sprintf("%02d:%02d:%02d", $hours, $minutes, 0) : null;
	}
}

if (!function_exists('timeToStringConversion')) {
	function timeToStringConversion($timeString)
	{
		// Split hours and minutes
		list($hours, $minutes, $seconds) = explode(":", $timeString);

		// Convert to total minutes
		$totalMinutes = $hours * 60 + $minutes;

		// Calculate days, hours, and remaining minutes
		$days = floor($totalMinutes / (24 * 60));
		$remainingMinutes = $totalMinutes % (24 * 60);
		$hours = floor($remainingMinutes / 60);
		$minutes = $remainingMinutes % 60;

		// Generate formatted strings
		$daysStr = ($days > 0) ? $days . (($days == 1) ? ' day' : ' days') : '';
		$hoursStr = ($hours > 0) ? $hours . (($hours == 1) ? ' hour' : ' hours') : '';
		$minutesStr = ($minutes > 0) ? $minutes . (($minutes == 1) ? ' minute' : ' minutes') : '0 minute';

		// Display the result
		return trim("$daysStr $hoursStr $minutesStr");
	}
}

if (!function_exists('affiliateTimeSlots')) {
	function affiliateTimeSlots($tourPackageId, $timeFrom, $timeTo, $createdAt, $createdBy, $createdByUserType, $affiliateTimeSlots)
	{
		// Get affiliate timeslot list tour package wise
		$affiliateTimeSlot = \Illuminate\Support\Facades\DB::table('tour_slot_times_affiliate')
			->where('tour_package_id', $tourPackageId)
			->where('time_from', $timeFrom)
			->where('time_to', $timeTo)
			->where('status', 'Active')
			->where('deleted_by', 0)
			->first();

		if (!$affiliateTimeSlot) {
			$affiliateTimeSlots[] = [
				'tour_package_id' => $tourPackageId,
				'time_from' => $timeFrom,
				'time_to' => $timeTo,
				'status' => 'Active',
				'created_at' => $createdAt,
				'created_by' => $createdBy,
				'created_by_user_type' => $createdByUserType
			];
		}

		return $affiliateTimeSlots;
	}
}

if (!function_exists('bookedFromFrontEnd')) {
	function bookedFromFrontEnd($bookingId)
	{
		$trans = \App\Models\Transaction::where('tour_booking_id', $bookingId)
			->where('status', 'Completed')
			->orderBy('id', 'ASC')
			->first();

		$isBookedFromFrontend = false;
		if (!empty($trans) && $trans->request_from == App\Constants\Constants::FRONTEND) {
			$isBookedFromFrontend = true;
		}

		return $isBookedFromFrontend;
	}
}

if (!function_exists('isComboBooking')) {
	function isComboBooking($cartId)
	{
		if ($cartId) {
			$tourBookingCount = \Illuminate\Support\Facades\DB::table('tour_bookings_master AS tbm')
				->where('tbm.cart_id', $cartId)
				->count();

			if ($tourBookingCount > 1) {
				return true;
			}
		}

		return false;
	}
}
if (!function_exists('isEditBookingDisabled')) {
	function isEditBookingDisabled($booking, $affiliateFeature, $transP)
	{
		return isBookingCancelled($booking) ||
			isAffiliateFeatureDisabled($affiliateFeature) ||
			isBookingFromTripAdvisor($booking) ||
			isBookingFromGetYourGuide($booking) ||
			isBookingFromOTA($booking); // ||
			// hasPermitFeeOfTwentyDollars($booking->id);
		//  ||isCashBookingWithPaymentDisabled($booking, $transP);
	}
}

if (!function_exists('isBookingCancelled')) {
	function isBookingCancelled($booking)
	{
		return $booking->statusm == 'Cancelled';
	}
}

if (!function_exists('isAffiliateFeatureDisabled')) {
	function isAffiliateFeatureDisabled($affiliateFeature)
	{
		return $affiliateFeature == 0;
	}
}

if (!function_exists('isCashBookingWithPaymentDisabled')) {
	function isCashBookingWithPaymentDisabled($booking, $transP)
	{
		return $booking->via == "Cash" && !empty($transP) && $transP->payment_type == 1;
	}
}

if (!function_exists('hasPermitFeeOfTwentyDollars')) {
	function hasPermitFeeOfTwentyDollars($bookingId)
	{
		// Check if any rate group in this booking has a permit fee of exactly $20
		$hasPermitFee = DB::table('tour_bookings_transact')
			->where('tour_booking_id', $bookingId)
			->where('permit_fee', 20.00)
			->exists();

		return $hasPermitFee;
	}
}

if (!function_exists('isRestoreBookingDisplay')) {
	function isRestoreBookingDisplay($bookingM, $isRestoreForCard, $customChargePrice, $pricedAddons)
	{
		return isBookingCancelled($bookingM) && session('userType') != 'Affiliate' && isAddonCustomChargePresent($customChargePrice, $pricedAddons) && ($bookingM->via == null || $bookingM->via == 'Cash' || $bookingM->via == 'Invoiced' || ($bookingM->status == 'Failed' && $bookingM->via == 'Card') || ($bookingM->via == 'Card' && $isRestoreForCard == true));
	}
}

if (!function_exists('isMakePaymentDisabled')) {
	function isMakePaymentDisabled($pay, $bok, $trans, $bookingM)
	{
		return (($pay >= $bok) || !empty($trans) && $trans->payment_type != 1 || isBookingCancelled($bookingM));
	}
}

if (!function_exists('isAddonCustomChargePresent')) {
	function isAddonCustomChargePresent($customChargePrice, $pricedAddons)
	{
		return $customChargePrice['custom_charge_total'] == 0 || $pricedAddons['addons_final_price'] == 0;
	}
}

if (!function_exists('isCancelBookingDisabled')) {
	function isCancelBookingDisabled($bookingM, $affiliateFeature, $customerData)
	{
		return isBookingCancelled($bookingM) ||
			isAffiliateFeatureDisabled($affiliateFeature) ||
			isBookingFromTripAdvisor($bookingM) ||
			isBookingFromGetYourGuide($bookingM) ||
			(isBookingFromOTA($bookingM) && !isOctoBookingCancellationWithinCutoff($bookingM)) ||
			isBlackoutDate($customerData, $bookingM);
	}
}

if (!function_exists('isBookingFromTripAdvisor')) {
	function isBookingFromTripAdvisor($booking)
	{
		return (session('userType') != 'Affiliate') && ($booking->via == "TripAdvisor" && $booking->tripadvisor_id != null);
	}
}

if (!function_exists('isBookingFromGetYourGuide')) {
	function isBookingFromGetYourGuide($booking)
	{
		return (session('userType') != 'Affiliate') && ($booking->via == "GetYourGuide" && $booking->get_your_guide_booking_id != null);
	}
}

if (!function_exists('isBookingFromOTA')) {
	function isBookingFromOTA($booking)
	{
		return (session('userType') != 'Affiliate') && ($booking->ota_booking_id != null);
	}
}

if (!function_exists('isOctoBookingCancellationWithinCutoff')) {
	function isOctoBookingCancellationWithinCutoff($booking)
	{
		if (empty($booking->id)) {
			return false;
		}

		$hasOctoBooking = \App\Models\OctoBooking::where('tour_booking_id', $booking->id)->exists();
		if (!$hasOctoBooking) {
			return false;
		}

		$hasCustomCharge = \App\Models\Transaction::where('tour_booking_id', $booking->id)
			->where('charge_type', 'custom_charge')
			->exists();

		return !$hasCustomCharge;
	}
}

if (!function_exists('isBlackoutDate')) {
	function isBlackoutDate($customerData, $bookingM)
	{
		// Get affiliate blackout dates only
		$blackoutRanges = App\Models\AffiliateBlackoutDate::get(['from_date', 'to_date']);

		if (session('userType') === 'Affiliate' &&  count($blackoutRanges) > 0) {

			// If affiliate is allowed to cancel even in blackout, don't disable the button
			if ($customerData->can('allow-cancellation-in-blackout')) {
				return false;
			}

			// Use booking date instead of today
			$bookingDate = Carbon\Carbon::parse($bookingM->date)->startOfDay();

			// Get blackout ranges
			$blackoutRanges = App\Models\AffiliateBlackoutDate::get(['from_date', 'to_date']);

			foreach ($blackoutRanges as $range) {
				$from = Carbon\Carbon::parse($range->from_date)->startOfDay();
				$to = Carbon\Carbon::parse($range->to_date)->endOfDay();

				if ($bookingDate->between($from, $to)) {
					return true; // Disable the button
				}
			}


			return false; // Not in blackout range, button enabled
		}

		// If not affiliate, always enable the button
		return false;
	}
}

if (!function_exists('paymentAmountForChangePaymentType')) {
	function paymentAmountForChangePaymentType($subtotal, $paymentTotalForCard, $paymentTotalForCardAffiliate, $isAffiliate, $cashPrices, $cashPriceEnabled)
	{
		if($cashPriceEnabled == 1) {
			return (cashFees() == '1') ? $cashPrices['cashPriceTotal'] : $cashPrices['cashPriceSubtotal'];	
		} else {
			return (cashFees() == '1') ? (($isAffiliate == 'Yes') ? $paymentTotalForCardAffiliate : $paymentTotalForCard) : $subtotal;
		}
	}
}

if (!function_exists('isSwitchPackageDisabled')) {
	function isSwitchPackageDisabled($bookingM, $trans, $paymentTrans, $refundTrans, $packageSwitchedCount, $pricedAddons, $payLaterAddons)
	{
		if ($pricedAddons['addons_payment_price'] || $payLaterAddons['addons_price']) {
			return true;
		}

		// count($paymentTrans) > 1 / count($refundTrans) > 0 used to block Switch on ANY
		// booking that ever had an extra ticket-level payment or refund after the original
		// booking — which is exactly what a "Change People" pax addition/removal creates, so
		// it disabled Switch for that case even with zero add-ons. That's no longer needed as
		// a blanket guard: $paymentTrans/$refundTrans are already scoped to genuine
		// ticket/pax activity (charge_type NULL or 'discount' — see V3\TourBookingRepository::
		// booking()'s $transactions query), the destination-package recompute already reads
		// LIVE pax counts (not stale originals), and SwitchTourPackageCashService::
		// calculateRemainingAmount() already branches correctly for >1 payment transactions
		// (e.g. split payments) — and its own $PaymentHistory/$RefundHistory queries are now
		// filtered to whereNull('charge_type') too, so a paid custom charge's history rows no
		// longer get folded into the ticket-cost delta it computes. Add-ons are the one thing
		// that recompute never touches at all, which is why they're still an unconditional
		// block above, separate from this.
		return (!empty($trans) && $trans->payment_type == 1) || isBookingCancelled($bookingM) || isBookingFromTripAdvisor($bookingM) || isBookingFromGetYourGuide($bookingM) || isBookingFromOTA($bookingM) || $packageSwitchedCount > 0;
	}
}

if (!function_exists('adminUser')) {
	function adminUser()
	{
		return App\Models\Admin::first();
	}
}

if (!function_exists('normalizedSearchTerm')) {
	function normalizedSearchTerm($searchTerm)
	{
		return preg_replace('/[^0-9+]/', '', $searchTerm);
	}
}

if (!function_exists('identifyText')) {
	function identifyText($text)
	{
		// Normalize the text by trimming whitespace
		$text = trim($text);

		// Define regex patterns for each type.
		// Order: booking before phone so numeric booking IDs are not misclassified as phone.
		// Booking regex split into two parts:
		//   Part 1: letter-prefixed IDs (e.g. "D-87775") — any digit length
		//   Part 2: pure-digit/short IDs — capped at 7 total chars (.? + up to 6 digits)
		//           so 8+ digit strings (phone numbers) fall through to the phone check.
		$patterns = [
			'email'   => '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
			'booking' => '/^[A-Za-z].?-?\d{1,16}$|^.?-?\d{1,6}$/',
			'phone'   => '/^\+?[1-9]{1}[0-9]{6,14}$|^\+?(\d{1,4}[\s\-.]?)?(\(?\d{1,4}?\)?[\s\-.]?)?[\d\s\-.]{4,15}$/',
		];

		// Check against each pattern
		foreach ($patterns as $type => $pattern) {
			if (preg_match($pattern, $text)) {
				return $type;
			}
		}

		// If no pattern matches, return 'normal'
		return 'normal';
	}
}

if (!function_exists('viaArray')) {
	function viaArray($request)
	{
		$viaArray = [];
		if ($request->checkCard == 'true') {
			$viaArray[] = 'Card';
		}
		if ($request->checkCash == 'true') {
			$viaArray[] = 'Cash';
		}
		if ($request->checkInvoiced == 'true') {
			$viaArray[] = 'Invoiced';
		}
		if ($request->checkWire == 'true') {
			$viaArray[] = 'Check/Wire';
			$viaArray[] = 'Cheque/Wire';
		}

		// Handle third-party payment types based on external bookings filter
		// Options: Exclude External, Include External, Include External With Fees, Only Show External
		if (!empty($viaArray)) {
			// Only exclude third-party types when "Exclude External Bookings" is explicitly selected
			$excludeExternal = isset($request->excludeExternal) && $request->excludeExternal == "true";

			if (!$excludeExternal) {
				// Include third-party payment types for all other options:
				// - Include External Bookings
				// - Include External Bookings With Fees
				// - Only Show External Bookings
				// - Default (no filter selected)
				$viaArray[] = 'Headout';
				$viaArray[] = 'TripAdvisor';
				$viaArray[] = 'GetYourGuide';
				$viaArray[] = 'FareHarbor';
			}
		}

		return $viaArray;
	}
}

if (!function_exists('requestFrom')) {
	function requestFrom($createdByUserType)
	{
		return $createdByUserType == "Customer" ? App\Constants\Constants::FRONTEND : (request()->is('api/*') ? App\Constants\Constants::API : App\Constants\Constants::BACKEND);
	}
}

if (!function_exists('customCharge')) {
	function customCharge()
	{
		$customCharge = 0;
		$setting = \Illuminate\Support\Facades\DB::table('settings')
			->where('key', 'CUSTOM_CHARGE')
			->first();

		if ($setting) {
			$customCharge = $setting->value;
		}

		return $customCharge;
	}
}

if (!function_exists('checkDate1')) {
	function checkDate1($ar)
	{
		return strtotime($ar->date) == strtotime($GLOBALS['nDate']) ? $ar : null;
	}
}

if (!function_exists('filterAffiliateData')) {
	function filterAffiliateData($var)
	{
		if (!empty($var)) {
			return $var;
		}
	}
}

if (!function_exists('addAffiliateSelling')) {
	function addAffiliateSelling($var)
	{
		if (isset($var->time_from)) {
			if ($var->time_from == $GLOBALS['gTime_from'] && $var->status == 'Booked') {
				$GLOBALS['gAffIdVal'] += $var->tourists - (/*$var->check_in_not +*/$var->check_in_1st);
				$GLOBALS['gNoShow'] += $var->absent;
				$GLOBALS['gFulfilledSeats'] += $var->check_in_2nd;
			} elseif ($var->time_from == $GLOBALS['gTime_from'] && $var->status == 'Cancelled') {
				$GLOBALS['gAffIdCanVal'] += $var->tourists - ($var->check_in_not + $var->check_in_1st);
			}

			return true;
		} else {
			if ($var->affiliate_id == $GLOBALS['gAffId'] && $var->package_id == $GLOBALS['gPackageId'] && $var->status == 'Booked') {
				$GLOBALS['gAffIdVal'] += $var->tourists - (/*$var->check_in_not +*/$var->check_in_1st);
				$GLOBALS['gNoShow'] += $var->absent;
				$GLOBALS['gFulfilledSeats'] += $var->check_in_2nd;
			} elseif ($var->affiliate_id == $GLOBALS['gAffId'] && $var->package_id == $GLOBALS['gPackageId'] && $var->status == 'Cancelled') {
				$GLOBALS['gAffIdCanVal'] += $var->tourists - ($var->check_in_not + $var->check_in_1st);
			}

			return true;
		}
	}
}

if (!function_exists('IsAddOnCustomChargeVisibile')) {
	function IsAddOnCustomChargeVisibile()
	{
		$userType = userType();
		$addOnEnable = 0;
		$customChargeEnable = 0;
		$addOnAccess = '';
		$customChargeAccess = '';
		$settingAddOn = \Illuminate\Support\Facades\DB::table('settings')
			->where('key', "CUSTOM_FIELDS")
			->first();
		$settingCustomCharge = \Illuminate\Support\Facades\DB::table('settings')
			->where('key', "CUSTOM_CHARGE")
			->first();

		if ($settingAddOn) {
			$addOnAccess = $settingAddOn->value;
		}
		if ($settingCustomCharge) {
			$customChargeAccess = $settingCustomCharge->value;
		}
		if ($userType == "Affiliate" || $userType == "Tour Operator") {
			if (Illuminate\Support\Str::contains($addOnAccess, '1')) {
				$addOnEnable = 1;
			}
			if (Illuminate\Support\Str::contains($customChargeAccess, '1')) {
				$customChargeEnable = 1;
			}
		} else if ($userType == "Admin") {
			if (Illuminate\Support\Str::contains($addOnAccess, '1') || Illuminate\Support\Str::contains($addOnAccess, '2') || Illuminate\Support\Str::contains($addOnAccess, '3')) {
				$addOnEnable = 1;
			}
			if (Illuminate\Support\Str::contains($customChargeAccess, '1') || Illuminate\Support\Str::contains($customChargeAccess, '2') || Illuminate\Support\Str::contains($customChargeAccess, '3')) {
				$customChargeEnable = 1;
			}
		}

		return ['addOnEnabled' => $addOnEnable, 'customChargeEnabled' => $customChargeEnable];
	}
}

if (!function_exists('checkPermission')) {
	/**
	 * Check if the authenticated user has the given permission.
	 *
	 * @param string $permission
	 * @return bool
	 */
	function checkPermission($permission): bool
	{
		/** @var \Illuminate\Foundation\Auth\User|null $user */
		$user = \Illuminate\Support\Facades\Auth::user();

		return $user && $user->can($permission);
	}
}

if (!function_exists('getNextYearCommissionPercentage')) {
	function getNextYearCommissionPercentage($date)
	{
		return date('Y', strtotime($date)) >= 2027;
	}
}

if (!function_exists('getNormalCommissionPercentage')) {
	function getNormalCommissionPercentage($packageId)
	{
		$package = App\Models\TourPackage::find($packageId);

		return [
			'service_commission_percentage' => $package->next_year_service_commission_percentage ?: $package->service_commission_percentage,
			'stripe_commission_percentage' => $package->next_year_stripe_commission_percentage ?: $package->stripe_commission_percentage
		];
	}
}

if (!function_exists('getAffiliateCommissionPercentage')) {
	function getAffiliateCommissionPercentage($affiliateId)
	{
		$affiliate = App\Models\Customer::find($affiliateId);

		return [
			'affiliate_processing_percentage' => $affiliate->next_year_affiliate_processing_percentage ?: $affiliate->affiliate_processing_percentage,
			'stripe_affiliate_percentage' => $affiliate->next_year_stripe_affiliate_percentage ?: $affiliate->stripe_affiliate_percentage
		];
	}
}

if (!function_exists('routePrefix')) {
	function routePrefix()
	{
		$routePrefix = match (session('guard')) {
			"affiliate" => 'affiliate',
			"tour_operator_staff" => 'tour-operator',
			default => 'admin.tour-operator',
		};

		return $routePrefix;
	}
}

if (!function_exists('routePrefixForJs')) {
	function routePrefixForJs()
	{
		$routePrefix = match (session('guard')) {
			"affiliate" => 'affiliate',
			"tour_operator_staff" => 'tour-operator',
			default => 'admin/tour-operator',
		};

		return $routePrefix;
	}
}

if (!function_exists('generateTransferGroup')) {
	function generateTransferGroup()
	{
		return 'booking_' . (string) \Illuminate\Support\Str::uuid();
	}
}
if (!function_exists('isBlackoutDateEnabled')) {
	function isBlackoutDateEnabled($date)
	{
		if (hasSetting('BLACKOUT_DATES_ENABLED')) {

			$blackoutRanges = App\Models\AffiliateBlackoutDate::get(['from_date', 'to_date']);

			if (count($blackoutRanges) > 0) {

				// Use booking date instead of today
				$bookingDate = Carbon\Carbon::parse($date)->startOfDay();

				// Get blackout ranges
				$blackoutRanges = App\Models\AffiliateBlackoutDate::get(['from_date', 'to_date']);

				foreach ($blackoutRanges as $range) {
					$from = Carbon\Carbon::parse($range->from_date)->startOfDay();
					$to = Carbon\Carbon::parse($range->to_date)->endOfDay();

					if ($bookingDate->between($from, $to)) {
						return true; // Disable the button
					}
				}


				return false; // Not in blackout range, button enabled
			}
		}

		// If not affiliate, always enable the button
		return false;
	}
}
if (!function_exists('hasSplitOrRemovedPax')) {
	/**
	 * Whether a booking has ever had a split or a pax removed. Both are
	 * logged as `Refund` (with or without a paired "Split Booking Original"
	 * entry) or `Split Booking Original` itself, so a plain existence check
	 * against those two operation names is enough.
	 *
	 * Shared by every place that bulk-touches an affiliate/soft-block
	 * booking's tourists/rate without going through the normal per-booking
	 * edit flow -- SoftBlockController's mass-update tool, and the
	 * CorrectAffiliateBooking/CorrectAffiliateSoftBlockBooking jobs that
	 * re-apply corrected package rates. Any of them overwriting a booking
	 * that's already been split or reduced would corrupt the same state
	 * this check exists to protect elsewhere.
	 *
	 * @param int $bookingId
	 * @return bool
	 */
	function hasSplitOrRemovedPax($bookingId)
	{
		return DB::table('tour_booking_history')
			->where('tour_booking_id', $bookingId)
			->whereIn('operation', ['Refund', 'Split Booking Original'])
			->exists();
	}
}
if (!function_exists('hasAnyPayment')) {
	/**
	 * Whether a booking has ever received any payment (fully or partially
	 * paid -- this doesn't distinguish, since either way it's no longer the
	 * "unpaid / Pay Later" state the mass soft-block tool is meant for).
	 *
	 * There's no stored payment-status column anywhere in this app -- it's
	 * always computed on the fly from `transactions`, the same way the
	 * booking-detail page does it (see e.g. `TourBookingRepository::booking()`
	 * or `CheckInRepository`'s `paids` subquery). This only needs the simple
	 * existence version: any amount from a `Completed` transaction at all
	 * means "not eligible", regardless of how much of the total that covers.
	 *
	 * @param int $bookingId
	 * @return bool
	 */
	function hasAnyPayment($bookingId)
	{
		$paid = DB::table('transactions')
			->where('tour_booking_id', $bookingId)
			->where('status', 'Completed')
			->sum('amount');

		return $paid > 0;
	}
}
if (!function_exists('isCustomDateRateEnabled')) {
	function isCustomDateRateEnabled($date, $packageId, $isAffiliate, $slotTimeId = 0)
	{
		if (hasSetting('CDR_PACKAGES') || (hasSetting('CDR_AFFILIATES') && ($isAffiliate == 'Yes' || $isAffiliate))) {
			$formattedDate = date('Y-m-d', strtotime($date));

			// Fetch custom rate date only once
			$customRateDate = App\Services\Booking\BookingService::getCustomRateDate($formattedDate, $packageId, $slotTimeId, $isAffiliate);
			// Early return if no custom rate date is found
			if (!$customRateDate) {
				return false;
			}

			return $customRateDate->id;
		}

		return false;
	}
}

if (!function_exists('managerRequestCount')) {
	function managerRequestCount()
	{
		$count = 0;
		if (isCentralDomain() === false) {
			$query = \Illuminate\Support\Facades\DB::table('booking_requests as br')
				->where('br.status', 'Pending')
				->select('br.order_id');

			if (userType() == "Tour Operator") {
				$query = $query->where('br.manager_id', \Illuminate\Support\Facades\Auth::user()->id);
			}

			$count = $query->count();
		}

		return $count;
	}
}

if (!function_exists('htmlPermissionList')) {
	function htmlPermissionList($permissions)
	{
		$html = '';
		if (!empty($permissions)) {
			$i = 1;
			$count = count($permissions);
			$moduloBy = ($count > 3) ? ceil(count($permissions) / 3) : 1;
			foreach ($permissions as $key => $permission) {
				// if (($i % $moduloBy) == 1 && $count > 3) {
				// 	$html .= '<div class="w-full sm:w-1/2 lg:w-1/3 max-w-full px-3">';
				// }
				$html .= '<div class="w-full sm:w-1/2 lg:w-1/3 max-w-full px-3"><div class="flex items-center mb-2 text-sm text-[#4A4F59]"><input type="checkbox" name="permission[]"' . $permission["checked"] . ' class="form-check-input permission-checkbox m-0 me-2 w-4 h-4 cursor-pointer" value="' . $permission["id"] . '" id="' . $permission["id"] . '">' . $permission["display_name"] . '</div></div>';
				// if (($i % $moduloBy) == 0 && $count > 3) {
				// 	$html .= '</div>';
				// }
				$i++;
			}
		}
		return $html;
	}
}

if (!function_exists('htmlAccordion')) {
	function htmlAccordion($category, $accordionName, $id)
	{
		$html = '';
		$html .= '<div class="accordion" id="accordionExample">
				  <div class="accordion-item permission-accordian mb-3">
				    <h2 class="accordion-header flex items-center relative" id="headingOne">
					<div class="header-permission-chkbox-div absolute left-4"><input type="checkbox" class="form-check-input m-0 me-2 header-permission w-4 h-4 cursor-pointer" value=""></div>
				      <button class="accordion-button accordion-button-wrap !pl-10" type="button" data-bs-toggle="collapse" data-bs-target="#' . $id . '" aria-expanded="true" aria-controls="' . $id . '">
					  '
			. $accordionName .
			'<svg width="9" height="5" viewBox="0 0 9 5" fill="none" xmlns="http://www.w3.org/2000/svg" class="inline-block align-text-top ml-auto"><path d="M0.861572 0.732422L4.40455 4.2754L7.94752 0.732422" stroke="#2C2421" stroke-width="1.18083" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
				    </h2>
				    <div id="' . $id . '" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#accordionExample">
				      <div class="accordion-body p-4 border border-[#E5E7EB] rounded-lg">
					      <div class="flex flex-wrap -mx-3">'
			. $category .
			'</div>
				      </div>
				    </div>
				  </div>
				</div>';
		return $html;
	}
}
if (!function_exists('departmentPackage')) {
	function departmentPackage() // packageStaffAssignment, package_staff_assignments
	{
		if (userType() == 'Tour Operator' && hasSetting('DEPARTMENT_PACKAGE')) {
			$departmentPackage = \Illuminate\Support\Facades\DB::table('department_package as dp')
				->where('dp.department_id', \Illuminate\Support\Facades\Auth::user()->department_id)
				->select('dp.package_id')
				->get();

			if ($departmentPackage->count()) {
				return $departmentPackage->pluck('package_id')->toArray();
			}

			return false;
		}

		return false;
	}
}

if (!function_exists('userRole')) {
	function userRole()
	{
		$user = \Illuminate\Support\Facades\Auth::user();
		$userRole = $user->roles->first()->name ?? '';
		if (count($user->roles) == 2) {
			$userRole = "manager";
		}

		return $userRole;
	}
}

if (!function_exists('taxLabel')) {
	function taxLabel(): string
	{
		static $label = null;

		if (!$label) {
			$label = \App\Models\Tax::active()->first()?->name ?? 'Tax'; // This runs only once per request
		}

		return $label;
	}
}

if (!function_exists('currencyFormation')) {
	function currencyFormation($code = false, $symbol = false)
	{
		static $country = null;

		if (!$country) {
			$country = \App\Models\Country::isActive()->first(); // This runs only once per request
		}

		if ($code && $symbol) {
			return [
				'code' => $country->currency ?? 'USD',
				'symbol' => $country->currency_symbol ?? '$'
			];
		} elseif ($code) {
			return $country->currency ?? 'USD';
		} elseif ($symbol) {
			return $country->currency_symbol ?? '$';
		}
	}
}


if (!function_exists('countryCode')) {
	function countryCode()
	{
		static $country = null;

		if (!$country) {
			$country = \App\Models\Country::isActive()->first(); // This runs only once per request
		}

		return $country->code ?? 'US';
	}
}

if (!function_exists('getTourOperatorCountry')) {
	// Returns the uppercase ISO country code for the current tenant's tour operator
	// (resolved via tour_operator -> city -> state -> country). Returns null when
	// the chain is incomplete or the country can't be identified. Cached per-request;
	// pass $reset=true in tests that switch tenants in-process.
	function getTourOperatorCountry(bool $reset = false): ?string
	{
		static $computed = false;
		static $cached = null;

		if ($reset) {
			$computed = false;
			$cached = null;
		}
		if ($computed) {
			return $cached;
		}

		try {
			$country = optional(
				optional(
					optional(\App\Models\TourOperator::first())->city
				)->state
			)->country;

			if ($country) {
				$code = strtoupper(trim((string) $country->code));
				if ($code !== '') {
					$cached = $code;
				} elseif (strcasecmp((string) $country->name, 'Canada') === 0) {
					$cached = 'CA';
				} elseif (
					strcasecmp((string) $country->name, 'USA') === 0
					|| strcasecmp((string) $country->name, 'United States') === 0
				) {
					$cached = 'US';
				}
			}
		} catch (\Throwable $e) {
			// leave $cached as null
		}

		$computed = true;
		return $cached;
	}
}

if (!function_exists('stripOnBehalfOfForCountry')) {
	// Country-aware wrapper for Stripe PaymentIntent params. For a DESTINATION-mode charge,
	// it strips `on_behalf_of` for Canadian tour operators (Stripe rejects cross-border
	// on_behalf_of paired with transfer_data for CA connected accounts); other countries
	// pass through untouched. For a PLATFORM_HELD-mode charge it never strips — confirmed by
	// direct empirical test (Payout Simplification Phase 0) that `on_behalf_of` with no
	// transfer_data works even against a CA connected account with zero card_payments
	// capability, since the capability requirement is specific to the on_behalf_of +
	// transfer_data destination-charge combination, not on_behalf_of alone. $mode is
	// deliberately required, not defaulted — every call site added since Payout
	// Simplification must state which shape it's building, since defaulting to DESTINATION
	// would silently strip on_behalf_of for a CA operator's platform-held charge, breaking
	// the statement-descriptor requirement for exactly that country.
	function stripOnBehalfOfForCountry(array $params, string $mode): array
	{
		if ($mode === \App\Services\Payments\PaymentModeResolver::DESTINATION && getTourOperatorCountry() === 'CA') {
			unset($params['on_behalf_of']);
		}
		return $params;
	}
}

if (!function_exists('switchTenant')) {
	function switchTenant()
	{
		$tenantId = getSubdomainFromHost();
		$userType = userType();
		$userId = Illuminate\Support\Facades\Auth::id();

		$connection = \Illuminate\Support\Facades\DB::connection('mysql')
			->table('switch_companies')
			->where('user_type', $userType)
			->where('user_id', $userId)
			->where('tenant_id', $tenantId)
			->first();

		$switchTenant = [];

		if ($connection) {
			$switchTenant = \Illuminate\Support\Facades\DB::connection('mysql')
				->table('switch_companies')
				->where('group_id', $connection->group_id)
				->where('tenant_id', '!=', $tenantId)
				->get();
		}

		return $switchTenant;
	}
}

if (!function_exists('switchCalendar')) {
	function switchCalendar()
	{
		$tenantId = getSubdomainFromHost();

		$switchCalendar = \Illuminate\Support\Facades\DB::connection('mysql')
			->table('configurations')
			->where('tenant_id', '!=', $tenantId)
			->where('calendar_switch',  1)
			->get(['tenant_id']);

		$switchCalendar->push((object)[
			'tenant_id' => $tenantId
		]);

		return $switchCalendar;
	}
}

if (!function_exists('statePermitFee')) {
	function statePermitFee()
	{
		$tourOperator = tourOperator();
		$city = App\Models\City::with('state')->where('id', $tourOperator->city_id)->first();
		return $city?->state?->permit_fee ?? 0;
	}
}

if (!function_exists('relationColor')) {
	function relationColor($index)
	{
		// Two colors only
		$colors = [
			'#FEF9C3', // light yellow
			'#DBEAFE'  // light blue
		];

		// Static memory to keep mapping between function calls
		static $indexColorMap = [];
		static $counter = 0;

		// If this index is seen the first time, assign a color
		if (!isset($indexColorMap[$index])) {
			$indexColorMap[$index] = $colors[$counter % 2];
			$counter++;
		}

		// Return the assigned color
		return $indexColorMap[$index];
	}
}

if (!function_exists('disableAffiliateCardSave')) {
	function disableAffiliateCardSave()
	{
		$userType = userType();
		$setting = hasSetting('DISABLE_AFFILIATE_CARD_SAVE');
		$disableAffiliateCardSave = false;

		if ($setting && $userType == "Affiliate") {
			$disableAffiliateCardSave = true;
		}

		return $disableAffiliateCardSave;
	}
}

if (!function_exists('getCardBrandLogo')) {
    function getCardBrandLogo($brand)
    {
        $brand = strtolower($brand);

        $available = [
            'visa',
            'mastercard',
            'amex',
            'discover',
            'diners',
            'jcb',
            'unionpay',
        ];

        if (!in_array($brand, $available)) {
            $brand = 'default';
        }

        return asset("images/card-brands/{$brand}.png");
    }
}

if (!function_exists('waiverPdfPath')) {
	function waiverPdfPath($waiverPdf)
	{
		$waiverPdfPath = '';
		if (Storage::disk('s3')->exists($waiverPdf)) {
			$waiverPdfPath = Storage::disk('s3')->url($waiverPdf);
		}			
		return $waiverPdfPath;	
	}
}

if (!function_exists('getCardBrandLogo')) {
    function getCardBrandLogo($brand)
    {
        $brand = strtolower($brand);

        $available = [
            'visa',
            'mastercard',
            'amex',
            'discover',
            'diners',
            'jcb',
            'unionpay',
        ];

        if (!in_array($brand, $available)) {
            $brand = 'default';
        }

        return asset("images/card-brands/{$brand}.png");
    }
}

if (!function_exists('paymentTypeChipStyle')) {
    function paymentTypeChipStyle($paymentType)
    {
        return match ($paymentType) {
            'Cash'        => 'background:#f0fdf4;border-color:#86efac;color:#15803d;',
            'Invoiced'    => 'background:#f5f3ff;border-color:#c4b5fd;color:#6d28d9;',
            'Billable'    => 'background:#fff7ed;border-color:#fdba74;color:#c2410c;',
            'Headout'     => 'background:#6b7280;border-color:#4b5563;color:#fff;',
            'TripAdvisor' => 'background:#6b7280;border-color:#4b5563;color:#fff;',
            default       => 'background:#f1f5f9;border-color:#cbd5e1;color:#475569;',
        };
    }
}

if (!function_exists('parseCreatedByWithName')) {
    function parseCreatedByWithName($createdByWithName)
    {
        if (empty($createdByWithName)) {
            return ['name' => 'Customer', 'role' => ''];
        }

        preg_match('/^(.+?):\s*\((.+?)\)$/', $createdByWithName, $m);

        return [
            'name' => $m[1] ?? $createdByWithName,
            'role' => $m[2] ?? '',
        ];
    }
}

if (!function_exists('getSplitBookingTouristsByBookingIds')) {
    function getSplitBookingTouristsByBookingIds($bookingIds): \Illuminate\Support\Collection
    {
        if (empty($bookingIds)) {
            return collect();
        }

        return DB::table('tour_booking_history')
            ->whereIn('tour_booking_id', $bookingIds)
            ->where('operation', 'Added')
            ->select('tour_booking_id', 'tourists')
            ->get()
            ->keyBy('tour_booking_id');
    }

    function groupBookingHistory(\Illuminate\Support\Collection $history)
    {
        $skip            = [];
        $pairsMap        = [];
        $splitAllEntries = [];
        $splitPrimaryId  = null;

        foreach ($history as $hist) {
            $details = is_string($hist->details) ? json_decode($hist->details) : $hist->details;

            if (
                $hist->operation == 'Update'
                && isset($details->sub_operation)
                && $details->sub_operation
                && ($details->sub_operation == 'Tourists Added' || $details->sub_operation == 'Tourists Changed')
            ) {
                $hist->operation = 'Update Pax';
            }

            if (!in_array($hist->operation, ['Update Pax', 'Switch Tour Package', 'Split Booking Original'])) {
                continue;
            }

            if ($hist->operation == 'Split Booking Original') {
                $txnHistoryIds = ($details && !empty($details->txnHistoryIds))
                    ? array_map('intval', (array) $details->txnHistoryIds)
                    : [];

                if (!empty($txnHistoryIds)) {
                    $pairedList = $history->filter(function ($h) use ($txnHistoryIds, $pairsMap, $skip) {
                        if ($h->operation !== 'Refund') return false;
                        if (!in_array($h->id, $txnHistoryIds)) return false;
                        if (isset($pairsMap[$h->id]) || in_array($h->id, $skip)) return false;
                        $hd = is_string($h->details) ? json_decode($h->details) : $h->details;
                        return $hd && ($hd->from ?? '') == 'Extend Booking';
                    })->values()->all();
                } else {
                    // Group candidates by operation_at: all refunds from one split operation
                    // (possibly one per rate group) share the same timestamp. Pick the group
                    // whose timestamp is closest to this split entry so that sibling split
                    // entries are not robbed of their refunds when multiple splits occur within
                    // the same 20-second window.
                    $candidateGroups = $history->filter(function ($h) use ($hist, $pairsMap, $skip) {
                        if ($h->operation != 'Refund') return false;
                        if (isset($pairsMap[$h->id]) || in_array($h->id, $skip)) return false;
                        if (abs(strtotime($h->operation_at) - strtotime($hist->operation_at)) > 20) return false;
                        $hd = is_string($h->details) ? json_decode($h->details) : $h->details;
                        return $hd && ($hd->from ?? '') == 'Extend Booking';
                    })->groupBy('operation_at');

                    if ($candidateGroups->isNotEmpty()) {
                        $bestOpAt = $candidateGroups->keys()->sortBy(
                            fn($opAt) => abs(strtotime((string) $opAt) - strtotime($hist->operation_at))
                        )->first();
                        $pairedList = $candidateGroups->get($bestOpAt)->values()->all();
                    } else {
                        $pairedList = [];
                    }
                }

                if ($splitPrimaryId == null) {
                    $splitPrimaryId = $hist->id;
                } else {
                    $skip[] = $hist->id;
                }

                $splitAllEntries[] = [
                    'hist'                => $hist,
                    'extended_booking_id' => $details->extended_booking_id ?? null,
                    'pairedRefunds'       => $pairedList,
                ];

                foreach ($pairedList as $p) {
                    $skip[] = $p->id;
                }

                continue;
            }

            if ($hist->operation == 'Switch Tour Package') {
                $matchId      = $hist->package_swipe_history_id ?? null;
                $txnHistoryId = $details->txnhistoryid ?? null;

                $paired = $matchId ? $history->first(
                    fn($h) =>
                    in_array($h->operation, ['Payment', 'Refund'])
                    && $h->package_swipe_history_id == $matchId
                    && !isset($pairsMap[$h->id])
                ) : null;

                if (!$paired && $txnHistoryId) {
                    $paired = $history->first(
                        fn($h) =>
                        in_array($h->operation, ['Payment', 'Refund'])
                        && $h->id == $txnHistoryId
                        && !isset($pairsMap[$h->id])
                    );
                }

                if (!$paired) {
                    // charge_type must be null (a genuine tour-side Payment/Refund) — an
                    // add-ons/custom_charge/discount row created within the same 5-second window
                    // (e.g. Change People adding a new pax's add-ons alongside the pax itself)
                    // has nothing to do with THIS tour-side event and must stay its own
                    // independent entry, not get silently swallowed into it.
                    $paired = $history->first(
                        fn($h) =>
                        in_array($h->operation, ['Payment', 'Refund'])
                        && empty($h->charge_type)
                        && !isset($pairsMap[$h->id])
                        && abs(strtotime($h->operation_at) - strtotime($hist->operation_at)) <= 5
                    );
                }
            } else {
                $txnHistoryId = $details->txnhistoryid ?? null;

                if ($txnHistoryId) {
                    $paired = $history->first(
                        fn($h) =>
                        in_array($h->operation, ['Payment', 'Refund'])
                        && $h->id == $txnHistoryId
                        && !isset($pairsMap[$h->id])
                    );
                } else {
                    // Same charge_type guard as above — this fallback only exists to find the
                    // tour-side Payment/Refund that goes WITH this pax change, never an
                    // independently-recorded add-ons/custom_charge/discount entry that merely
                    // happened within the same few seconds.
                    $paired = $history->first(
                        fn($h) =>
                        in_array($h->operation, ['Payment', 'Refund'])
                        && empty($h->charge_type)
                        && !isset($pairsMap[$h->id])
                        && abs(strtotime($h->operation_at) - strtotime($hist->operation_at)) <= 5
                    );
                }
            }

            if ($paired) {
                $pairsMap[$hist->id] = $paired;
                $skip[] = $paired->id;
            }
        }

        $splitFirstAt = null;
        if (!empty($splitAllEntries)) {
            $splitTimes = array_filter(array_map(fn($e) => $e['hist']->operation_at ?? null, $splitAllEntries));
            if (!empty($splitTimes)) {
                $splitFirstAt = min($splitTimes);
            }
        }

        $grouped = [];
        foreach ($history as $hist) {
            if (in_array($hist->id, $skip)) {
                continue;
            }

            if (isset($pairsMap[$hist->id])) {
                $hist->paired = $pairsMap[$hist->id];
            }

            $hist->_sort_at = ($splitFirstAt != null && $splitPrimaryId !== null && $hist->id == $splitPrimaryId)
                ? $splitFirstAt
                : ($hist->operation_at ?? '');

            $grouped[] = $hist;
        }

        if (!empty($grouped)) {
            usort($grouped, fn($a, $b) => strcmp((string) ($b->_sort_at ?? ''), (string) ($a->_sort_at ?? '')));
        }

        return [
            'grouped'         => $grouped,
            'splitAllEntries' => $splitAllEntries,
        ];
    }
}

if (!function_exists('resolveComboPaymentData')) {
    /**
     * Resolve all combo-booking payment data needed to render the split breakdown
     * in the Payment history entry.
     *
     * Returns an array with 'isComboPayment' => false when the entry is not a
     * qualifying combo payment, so the caller can branch on that key alone.
     *
     * @param  object  $hist             tour_booking_history row
     * @param  object|null  $details     decoded JSON details from $hist->details
     * @param  array   $comboBookingsData  from BookingService::comboBookingsData()
     */
    function resolveComboPaymentData($hist, $details, $comboBookingsData)
    {
        $skip = ['addons', 'custom_charge', 'discount'];

        if (\count($comboBookingsData) === 0 || \in_array($hist->charge_type, $skip, true)) {
            return ['isComboPayment' => false];
        }

        $comboPaymentHistIds = ($details !== null && !empty($details->combo_payment_history_ids))
            ? array_map('intval', (array) $details->combo_payment_history_ids)
            : [];

        // Fallback for old bookings that have no stored combo_payment_history_ids:
        // find the first Payment entry for each sibling booking within 30 seconds.
        if (empty($comboPaymentHistIds)) {
            $siblingIds = collect($comboBookingsData)
                ->pluck('id')
                ->reject(fn($id) => (int) $id === (int) $hist->tour_booking_id)
                ->values()
                ->toArray();
				

            if (!empty($siblingIds)) {
                DB::table('tour_booking_history')
                    ->whereIn('tour_booking_id', $siblingIds)
                    ->where('operation', 'Payment')
                    ->whereNull('charge_type')
                    ->whereRaw('ABS(TIMESTAMPDIFF(SECOND, operation_at, ?)) <= 5', [$hist->operation_at])
                    ->orderBy('id')
                    ->get()
                    ->unique('tour_booking_id')
                    ->each(function ($row) use (&$comboPaymentHistIds) {
                        $comboPaymentHistIds[] = (int) $row->id;
                    });
            }
        }	

        if (empty($comboPaymentHistIds)) {
            return ['isComboPayment' => false];
        }

        $allComboHistIds = array_values(array_unique(array_merge([$hist->id], $comboPaymentHistIds)));
        $allComboHists   = DB::table('tour_booking_history')
            ->whereIn('id', $allComboHistIds)
            ->get()
            ->keyBy('tour_booking_id');

        $totalComboAmount = 0.0;
        $totalTicketCost  = 0.0;
        $totalFees        = 0.0;
        foreach ($allComboHists as $ch) {
            $chDet = is_string($ch->details) ? json_decode($ch->details) : $ch->details;
            $totalComboAmount += (float) ($chDet->amount     ?? 0);
            $totalTicketCost  += (float) ($ch->ticket_cost   ?? 0);
            $totalFees        += (float) ($ch->platform_fees ?? 0);
        }
        $totalPax = (int) ($hist->tourists ?? 0);

        return [
            'isComboPayment'   => true,
            'allComboHists'    => $allComboHists,
            'childColors'      => ['#7c3aed', '#2563eb', '#d97706', '#db2777'],
            'comboRows'        => collect($comboBookingsData)->sortByDesc('is_parent')->values(),
            'totalComboAmount' => $totalComboAmount,
            'totalTicketCost'  => $totalTicketCost,
            'totalFees'        => $totalFees,
            'totalPax'         => $totalPax,
            'experienceCount'  => count($comboBookingsData),
        ];
    }
}

if (!function_exists('resolveComboHubCardData')) {
    /**
     * Resolve all data needed to render the combo hub card partial.
     * Returns an empty array when $comboBookingsData is empty (caller should skip rendering).
     *
     * @param  array        $comboBookingsData  from BookingService::comboBookingsData()
     * @param  object       $bookingM           tour_bookings_master model
     * @param  object|null  $trans              confirmed transaction or null
     * @param  object|null  $transF             failed transaction or null
     * @param  string|null  $routePrefix        e.g. 'tour-operator.' or ''
     */
    function resolveComboHubCardData($comboBookingsData, $bookingM, $trans = null, $transF = null, $routePrefix = null)
    {
        if (empty($comboBookingsData)) {
            return [];
        }

        $hubRoutePrefix = $routePrefix ?? (session('userType') == 'Affiliate' ? '' : 'tour-operator.');
        $hubBookingIds  = collect($comboBookingsData)->pluck('id')->toArray();

        // First Payment history row per booking — used for payment method / card meta display
        $hubPaymentHistories = DB::table('tour_booking_history')
            ->whereIn('tour_booking_id', $hubBookingIds)
            ->where('operation', 'Payment')
            ->orderBy('id')
            ->get()
            ->unique('tour_booking_id')
            ->keyBy('tour_booking_id');

        // Per-booking payment sums across ALL Payment rows, not just the first: add-ons,
        // custom charges, and tips each write their own charge_type-tagged Payment row
        // (HistoryService::storeAddonsPaymentInHistory / storeCustomChargePaymentInHistory /
        // the tip writer), so the first row alone understates ticket/fees by those amounts.
        // charge_type='discount' rows are excluded — they carry the booking's full replacement
        // total (new_total + service_commission), not an incremental charge, and would
        // double-count against the base row.
        $hubPaymentSums = DB::table('tour_booking_history')
            ->whereIn('tour_booking_id', $hubBookingIds)
            ->where('operation', 'Payment')
            ->where(function ($q) {
                $q->whereNull('charge_type')->orWhereIn('charge_type', ['addons', 'custom_charge', 'tip']);
            })
            ->groupBy('tour_booking_id')
            ->selectRaw("tour_booking_id,
                SUM(COALESCE(ticket_cost, 0)) AS ticket_sum,
                SUM(COALESCE(platform_fees, 0)) AS fees_sum,
                SUM(CASE WHEN charge_type = 'addons' THEN COALESCE(ticket_cost, 0) ELSE 0 END) AS addons_sum")
            ->get()
            ->keyBy('tour_booking_id');

        // Fallback: master table for bookings without a payment history entry
        $hubMasterRecords = DB::table('tour_bookings_master')
            ->whereIn('id', $hubBookingIds)
            ->get(['id', 'booking_total', 'service_commission'])
            ->keyBy('id');

        // Per-booking financials: payment history when paid, master table when unpaid.
        // Add-ons are broken out of 'ticket' into their own 'addons' figure (price only —
        // the add-on fee stays aggregated in 'fees', matching the invoice pages' convention).
        $hubFinancials = [];
        foreach ($hubBookingIds as $bid) {
            $psums = $hubPaymentSums->get($bid);
            if ($psums) {
                $pAddons = (float) $psums->addons_sum;
                $pTicket = (float) $psums->ticket_sum - $pAddons;
                $pFees   = (float) $psums->fees_sum;
                $hubFinancials[$bid] = [
                    'ticket' => $pTicket,
                    'addons' => $pAddons,
                    'fees'   => $pFees,
                    'total'  => $pTicket + $pAddons + $pFees,
                    'paid'   => true,
                ];
            } else {
                $master              = $hubMasterRecords->get($bid);
                $mFees               = (float) ($master->service_commission ?? 0);
                $mTotal              = (float) ($master->booking_total      ?? 0);
                $hubFinancials[$bid] = [
                    'ticket' => $mTotal - $mFees,
                    'addons' => 0.0,
                    'fees'   => $mFees,
                    'total'  => $mTotal,
                    'paid'   => false,
                ];
            }
        }

        $hubParent   = collect($comboBookingsData)->firstWhere('is_parent', true);
        $hubChildren = collect($comboBookingsData)->where('is_parent', false)->values();

        // Card last4 from first available payment history (for hub-total footer)
        $hubFirstHist   = $hubPaymentHistories->first();
        $hubFirstDet    = $hubFirstHist ? (is_string($hubFirstHist->details) ? json_decode($hubFirstHist->details) : $hubFirstHist->details) : null;
        $hubCardNumber  = !empty($hubFirstDet->card_number) ? str_pad($hubFirstDet->card_number, 4, '0', STR_PAD_LEFT) : null;
        $hubPaymentType = $hubFirstDet->payment_type ?? null;

        // Totals across all bookings
        $hubTotalTicket = 0.0;
        $hubTotalAddons = 0.0;
        $hubTotalFees   = 0.0;
        $hubTotalGuests = 0;
        foreach ($comboBookingsData as $cb) {
            $fin            = $hubFinancials[$cb['id']] ?? ['ticket' => 0, 'addons' => 0, 'fees' => 0];
            $hubTotalTicket += $fin['ticket'];
            $hubTotalAddons += $fin['addons'] ?? 0;
            $hubTotalFees   += $fin['fees'];
            $hubTotalGuests += (int) ($cb['tourists'] ?? 0);
        }

        // Status badge
        $hubStatus      = !empty($trans) ? 'Confirmed' : (!empty($transF) ? 'Failed' : 'Pending');
        $hubStatusClass = match($hubStatus) { 'Confirmed' => 'confirmed', 'Failed' => 'failed', default => 'pending' };

        return [
            'hubRoutePrefix'      => $hubRoutePrefix,
            'hubPaymentHistories' => $hubPaymentHistories,
            'hubFinancials'       => $hubFinancials,
            'hubParent'           => $hubParent,
            'hubChildren'         => $hubChildren,
            'hubCardNumber'       => $hubCardNumber,
            'hubPaymentType'      => $hubPaymentType,
            'hubTotalTicket'      => $hubTotalTicket,
            'hubTotalAddons'      => $hubTotalAddons,
            'hubTotalFees'        => $hubTotalFees,
            'hubTotalAmount'      => $hubTotalTicket + $hubTotalAddons + $hubTotalFees,
            'hubTotalGuests'      => $hubTotalGuests,
            'hubParentLabel'      => $hubParent ? '#' . bookingPrefix($hubParent['id']) : '',
            'hubChildrenLabel'    => (function () use ($hubChildren) {
                if ($hubChildren->count() >= 3) {
                    $first = '#' . bookingPrefix($hubChildren->first()['id']);
                    preg_match('/(\d+)$/', bookingPrefix($hubChildren->last()['id']), $m);
                    $lastNum = $m[1] ?? bookingPrefix($hubChildren->last()['id']);
                    return "{$first}–{$lastNum}";
                }
                return $hubChildren->map(fn($c) => '#' . bookingPrefix($c['id']))->implode(', ');
            })(),
            'hubBookedDate'       => \Carbon\Carbon::parse($bookingM->created_at)->format('M j, Y'),
            'hubStatus'           => $hubStatus,
            'hubStatusClass'      => $hubStatusClass,
            'hubColorKeys'        => ['exp-blue', 'exp-purple', 'exp-orange', 'exp-pink'],
            'hubColorHexMap'      => ['exp-green' => '#059669','exp-blue' => '#2563eb', 'exp-purple' => '#7c3aed', 'exp-orange' => '#d97706', 'exp-pink' => '#db2777'],
            'hubOrdered'          => collect($comboBookingsData)->sortByDesc('is_parent')->values(),
        ];
    }
}

if (!function_exists('resolveHistCreatedBy')) {
    /**
     * Decode $hist->details from JSON and resolve the created-by name/role.
     * Falls back to $hist->username when details has no created_by_with_name.
     * Returns decoded $details so the caller does not need to re-decode.
     */
    function resolveHistCreatedBy($hist)
    {
        $details = is_string($hist->details) ? json_decode($hist->details) : $hist->details;

        $createdByWithName = ($details && isset($details->created_by_with_name))
            ? (string) $details->created_by_with_name
            : (!empty($hist->username)
                ? $hist->username . ': (' . ($hist->operation_by_user_type ?? '') . ')'
                : null);

        ['name' => $createdByName, 'role' => $createdByRole] = parseCreatedByWithName($createdByWithName);

        return [
            'createdByName' => $createdByName,
            'createdByRole' => $createdByRole,
            'details'       => $details,
        ];
    }
}

if (!function_exists('resolveOriginalData')) {
    function resolveOriginalData(object $hist): array
    {
        $r             = resolveHistCreatedBy($hist);
        $createdByName = $r['createdByName'];
        $createdByRole = $r['createdByRole'];
        $details       = $r['details'];

        $newTourists  = $details->newTourists ?? 0;
        $rawAdded     = ($details && !empty($details->added)) ? (array) $details->added : [];
        $addedGrouped = groupPaxList($rawAdded);

        return [
            'createdByName' => $createdByName,
            'createdByRole' => $createdByRole,
            'details'       => $details,
            'newTourists'   => $newTourists,
            'addedGrouped'  => $addedGrouped,
        ];
    }
}

if (!function_exists('resolveCancelData')) {
    function resolveCancelData($hist, $isCashFees = '0')
    {
        $r             = resolveHistCreatedBy($hist);
        $createdByName = $r['createdByName'];
        $createdByRole = $r['createdByRole'];
        $details       = $r['details'];

        $paymentType     = ($details && !empty($details->payment_type)) ? (string) $details->payment_type : '';
        $histPaymentType = (string) ($hist->payment_type ?? '');
        $cardNumber      = '';
        if (($paymentType == 'Card' || $histPaymentType == 'Card') && !empty($hist->card_last_4_digit)) {
            $cardNumber = '(' . str_pad((string) $hist->card_last_4_digit, 4, '0', STR_PAD_LEFT) . ')';
        }

        $refundType  = $details ? ($details->refund_type ?? '') : '';
        $isCardBreakdown = ($paymentType == 'Card' || $histPaymentType == 'Card')
            && $details
            && !empty($details->cards)
            && !empty($details->refund_amounts);

        return [
            'createdByName'     => $createdByName,
            'createdByRole'     => $createdByRole,
            'details'           => $details,
            'paymentType'       => $paymentType,
            'histPaymentType'   => $histPaymentType,
            'cardNumber'        => $cardNumber,
            'note'              => ($details && !empty($details->note)) ? (string) $details->note : '',
            'refundAmount'      => $details ? (float) ($details->amount ?? 0) : 0.0,
            'totalTicketCost'   => (float) ($hist->ticket_cost    ?? 0),
            'totalPlatformFees' => (float) ($hist->platform_fees  ?? 0),
            'showPlatformFees'  => $paymentType == 'Card'
                || $histPaymentType == 'Card'
                || $isCashFees == '1'
                || $histPaymentType == 'Cash'
                || $paymentType == 'Cash',
            'refundType'        => $refundType,
            'cancelLabel'       => $refundType == 'Full Refund' ? 'Ticket Cost' : $refundType,
            'isCardBreakdown'   => $isCardBreakdown,
            'breakdownCards'    => $isCardBreakdown ? (array) $details->cards         : [],
            'breakdownAmounts'  => $isCardBreakdown ? (array) $details->refund_amounts : [],
        ];
    }
}

if (!function_exists('resolvePaymentData')) {
    function resolvePaymentData($hist, $comboBookingsData = [])
    {
        $r             = resolveHistCreatedBy($hist);
        $createdByName = $r['createdByName'];
        $createdByRole = $r['createdByRole'];
        $details       = $r['details'];

        // Both fields are only ever null when storeAddonsPaymentInHistory() deliberately left
        // them unset for a status='open' entry (e.g. a Pay Later add-on charge — nothing has
        // actually been collected yet BY any method). A real completed payment always has the
        // literal 'Full Payment'/real payment_type string already stored, so these defaults
        // only ever fire for the genuinely-unpaid case — blank matches the sibling
        // resolveRefundData()'s convention for the identical "nothing to show" case, instead of
        // a hardcoded 'Card'/'Full Payment' that falsely claimed a real payment was made.
        $paymentMethod = $details->payment_method ?? '';
        $paymentType   = $details->payment_type   ?? '';
        $cardNumber    = !empty($details->card_number)
            ? str_pad($details->card_number, 4, '0', STR_PAD_LEFT)
            : null;
        $paymentAmount = ($hist->charge_type == 'addons')
            ? $hist->ticket_cost + $hist->platform_fees
            : ($details->amount ?? 0);

        $combo          = resolveComboPaymentData($hist, $details, $comboBookingsData);
        $isComboPayment = $combo['isComboPayment'];
        $allComboHists  = $isComboPayment ? $combo['allComboHists']    : null;
        $childColors    = $isComboPayment ? $combo['childColors']      : [];
        $comboRows      = $isComboPayment ? $combo['comboRows']        : collect();
        $totalComboAmount = $isComboPayment ? $combo['totalComboAmount'] : 0.0;
        $totalTicketCost  = $isComboPayment ? $combo['totalTicketCost']  : 0.0;
        $totalFees        = $isComboPayment ? $combo['totalFees']        : 0.0;
        $totalPax         = $isComboPayment ? $combo['totalPax']         : 0;
        $experienceCount  = $isComboPayment ? $combo['experienceCount']  : 0;

        return [
            'createdByName'    => $createdByName,
            'createdByRole'    => $createdByRole,
            'details'          => $details,
            'paymentMethod'    => $paymentMethod,
            'paymentType'      => $paymentType,
            'cardNumber'       => $cardNumber,
            'paymentAmount'    => $paymentAmount,
            'isComboPayment'   => $isComboPayment,
            'allComboHists'    => $allComboHists,
            'childColors'      => $childColors,
            'comboRows'        => $comboRows,
            'totalComboAmount' => $totalComboAmount,
            'totalTicketCost'  => $totalTicketCost,
            'totalFees'        => $totalFees,
            'totalPax'         => $totalPax,
            'experienceCount'  => $experienceCount,
        ];
    }
}

if (!function_exists('resolveRefundData')) {
    function resolveRefundData($hist, $isCashFees = '0')
    {
        $r             = resolveHistCreatedBy($hist);
        $createdByName = $r['createdByName'];
        $createdByRole = $r['createdByRole'];
        $details       = $r['details'];

        $cardNumber  = !empty($hist->card_last_4_digit)
            ? str_pad((string) $hist->card_last_4_digit, 4, '0', STR_PAD_LEFT)
            : null;
        $paymentType = ($details && !empty($details->payment_type)) ? (string) $details->payment_type : '';
        $totalAmount = $details ? (float) ($details->amount ?? 0) : 0.0;

        // Every tour-side refund/cancel writer (TicketRefundService, WeatherRefundService,
        // PartialRefundCancelService, RefundHistoryUpdateService, etc.) stores the admin's note
        // under $details->note. HistoryService::storeAddonsRefundInHistory() — the single shared
        // writer behind EVERY add-ons refund (bulk pax add-ons, single add-on delete, Change
        // People's addon-only settlement, "Don't Refund" leftovers, all of it) — stores it under
        // $details->description instead (see its own $request->description param), matching the
        // legacy historyDetails()'s ADDONS-only branch just above, which already reads it that
        // way. Reading only ->note here silently dropped every add-ons refund note from display
        // even though it was really saved to the row. Scoped to charge_type=='addons' so a
        // custom-charge refund's own (unrelated) $details->description never gets shown as if it
        // were a note.
        $note = ($details && !empty($details->note))
            ? (string) $details->note
            : (($hist->charge_type == 'addons' && $details && !empty($details->description)) ? (string) $details->description : '');
        $refundType  = $details ? ($details->refund_type ?? '') : '';

        $refundType = match ($refundType) {
            'full_refund'            => '100% Refund',
            'ticket_refund'          => 'Ticket Refund',
            'dont_refund'            => "Don't Refund",
            // ChangePeopleAddonService's Card/Cash refund branch is the only caller that ever
            // passes this slug (a partial refund of the combined tour+add-on amount) — every
            // other refund flow uses one of the slugs above. Missing this case fell through to
            // the `default` arm below, leaving the raw slug on screen instead of a readable label.
            'partial_refund'         => 'Partial Refund',
            'Full Refund minus Fees' => 'Ticket Cost',
            default                  => $refundType,
        };

        $refundAmount = ($hist->charge_type == 'addons')
            ? (float) ($hist->ticket_cost ?? 0) + (float) ($hist->platform_fees ?? 0)
            : $totalAmount;

        $deletedRaw    = ($details && isset($details->deleted)) ? $details->deleted : null;
        $deletedStr    = is_array($deletedRaw) ? implode(', ', $deletedRaw) : (is_string($deletedRaw) ? $deletedRaw : '');
        $deletedGroups = [];
        $deletedCount  = 0;

        if ($deletedStr !== '') {
            $segments = preg_split('/\s*,\s*/', trim($deletedStr)) ?: [];
            foreach ($segments as $segment) {
                $segment = trim($segment);
                if ($segment == '') continue;
                if (preg_match('/^(.+?)\s+person\s+(\d+)$/i', $segment, $m)) {
                    $rateName = trim($m[1]);
                    $count    = max(1, (int) $m[2]);
                    $deletedGroups[$rateName] = ($deletedGroups[$rateName] ?? 0) + $count;
                    $deletedCount += $count;
                } elseif (preg_match('/^Person\(s\)\s+(\d+)$/i', $segment, $m)) {
                    $count = max(1, (int) $m[1]);
                    $deletedGroups['Person(s)'] = ($deletedGroups['Person(s)'] ?? 0) + $count;
                    $deletedCount += $count;
                } else {
                    $deletedGroups[$segment] = ($deletedGroups[$segment] ?? 0) + 1;
                    $deletedCount++;
                }
            }
        }

        $totalTicketCost    = (float) ($hist->ticket_cost    ?? 0);
        $totalPlatformFees  = (float) ($hist->platform_fees  ?? 0);
        $histPaymentType    = (string) ($hist->payment_type  ?? '');
        $showPlatformFees   = $paymentType == 'Card'
            || $histPaymentType == 'Card'
            || $isCashFees == '1'
            || $histPaymentType == 'Cash'
            || $paymentType == 'Cash';

        return [
            'createdByName'      => $createdByName,
            'createdByRole'      => $createdByRole,
            'details'            => $details,
            'cardNumber'         => $cardNumber,
            'paymentType'        => $paymentType,
            'totalAmount'        => $totalAmount,
            'note'               => $note,
            'refundType'         => $refundType,
            'refundAmount'       => $refundAmount,
            'deletedGroups'      => $deletedGroups,
            'deletedCount'       => $deletedCount,
            'totalTicketCost'    => $totalTicketCost,
            'totalPlatformFees'  => $totalPlatformFees,
            'perPersonTicket'    => $deletedCount > 0 ? round($totalTicketCost   / $deletedCount, 2) : 0.0,
            'perPersonPlatform'  => $deletedCount > 0 ? round($totalPlatformFees / $deletedCount, 2) : 0.0,
            'showPlatformFees'   => $showPlatformFees,
            'isCustomCharge'     => $hist->charge_type == 'custom_charge' && !empty($details->custom_charge),
            'isAddons'           => $hist->charge_type == 'addons',
        ];
    }
}

if (!function_exists('resolvePickupHotelData')) {
    function resolvePickupHotelData(object $hist): array
    {
        $r             = resolveHistCreatedBy($hist);
        $createdByName = $r['createdByName'];
        $createdByRole = $r['createdByRole'];
        $details       = $r['details'];

        return [
            'createdByName' => $createdByName,
            'createdByRole' => $createdByRole,
            'details'       => $details,
            'hotelName'     => ($details && !empty($details->name))         ? (string) $details->name         : null,
            'hotelAddress'  => ($details && !empty($details->address))      ? (string) $details->address      : null,
            'hotelEmail'    => ($details && !empty($details->email))        ? (string) $details->email        : null,
            'hotelPhone'    => ($details && !empty($details->phone_number)) ? (string) $details->phone_number : null,
        ];
    }
}

if (!function_exists('resolveUpdatePaxData')) {
    function resolveUpdatePaxData(object $hist, $pairedPayment = null): array
    {
        $r             = resolveHistCreatedBy($hist);
        $createdByName = $r['createdByName'];
        $createdByRole = $r['createdByRole'];
        $details       = $r['details'];

        $subOp        = $details->sub_operation ?? 'Tourists Added';
        $isChanged    = $subOp == 'Tourists Changed';
        $addedList    = ($details && !empty($details->added))   ? (array) $details->added   : [];
        $deletedList  = ($details && !empty($details->deleted)) ? (array) $details->deleted : [];

        $addedGrouped   = groupPaxList($addedList);
        $deletedGrouped = groupPaxList($deletedList);

        $summaryAdded   = $isChanged ? ($details->addedTourists  ?? count($addedList))   : ($details->newTourists    ?? count($addedList));
        $summaryDeleted = $isChanged ? ($details->deletedTourits ?? count($deletedList))  : 0;

        $titleParts = [];
        if ($summaryAdded > 0)   $titleParts[] = "{$summaryAdded} tourist(s) added";
        if ($summaryDeleted > 0) $titleParts[] = "{$summaryDeleted} tourist(s) deleted";
        $cardTitle = 'Edit Booking — ' . implode(', ', $titleParts);

        $paymentDetails = null;
        $paymentAmount  = 0;
        $paymentMethod  = null;
        $paymentType    = null;
        $refundType     = null;
        $cardNumber     = null;
        $isRefund       = false;
        $paymentTotal   = $details->paymenttotal ?? 0;
        $refundTotal    = $details->refundtotal  ?? 0;

        if ($pairedPayment) {
            $paymentDetails = is_string($pairedPayment->details) ? json_decode($pairedPayment->details) : $pairedPayment->details;
            $isRefund       = $pairedPayment->operation == 'Refund';
            $paymentMethod  = $paymentDetails->payment_method ?? ($isRefund ? 'Refund' : 'Full Payment');
            // Same convention as resolvePaymentData(): payment_type is only ever null when
            // storeAddonsPaymentInHistory()/similar deliberately left it unset for a
            // status='open' entry (e.g. a new pax's Pay Later add-on selection — nothing has
            // actually been collected BY any method yet). Blank, not a hardcoded 'Card' that
            // falsely claimed a real card payment was made.
            $paymentType    = $paymentDetails->payment_type   ?? '';
            $cardNumber     = !empty($paymentDetails->card_number)
                ? str_pad($paymentDetails->card_number, 4, '0', STR_PAD_LEFT)
                : null;
            $paymentAmount  = $paymentDetails->amount ?? 0;
            $refundType     = $isRefund ? ($paymentDetails->refund_type ?: 'Partial Refund') : '';
        }

        return [
            'createdByName'   => $createdByName,
            'createdByRole'   => $createdByRole,
            'details'         => $details,
            'subOp'           => $subOp,
            'isChanged'       => $isChanged,
            'addedGrouped'    => $addedGrouped,
            'deletedGrouped'  => $deletedGrouped,
            'summaryAdded'    => $summaryAdded,
            'summaryDeleted'  => $summaryDeleted,
            'cardTitle'       => $cardTitle,
            'payment'         => $pairedPayment,
            'paymentDetails'  => $paymentDetails,
            'paymentAmount'   => $paymentAmount,
            'paymentMethod'   => $paymentMethod,
            'paymentType'     => $paymentType,
            'refundType'      => $refundType,
            'cardNumber'      => $cardNumber,
            'isRefund'        => $isRefund,
            'paymentTotal'    => $paymentTotal,
            'refundTotal'     => $refundTotal,
        ];
    }
}

if (!function_exists('resolveWaiverData')) {
    function resolveWaiverData($hist)
    {
        $r             = resolveHistCreatedBy($hist);
        $createdByName = $r['createdByName'];
        $createdByRole = $r['createdByRole'];
        $details       = $r['details'];

        $signers = [];
        if ($details && !empty($details->whoSignIn)) {
            $whoSignIn = (array) $details->whoSignIn;
            $signers[] = [
                'name' => (string) ($whoSignIn[1] ?? $whoSignIn[0] ?? ''),
                'age'  => (string) ($whoSignIn[0] ?? ''),
            ];
        }

        return [
            'createdByName' => $createdByName,
            'createdByRole' => $createdByRole,
            'details'       => $details,
            'signers'       => $signers,
            'signerCount'   => count($signers),
        ];
    }
}

if (!function_exists('groupPaxList')) {
    function groupPaxList(array $list)
    {
        $counts = [];
        foreach ($list as $item) {
            $item = (string) $item;
            if (preg_match('/^(.+)\s+person\s+(\d+)$/i', $item, $m)) {
                $rateName = trim($m[1]);
                $count    = (int) $m[2];
            } elseif (preg_match('/^(.+?)\s+(\d+)$/', $item, $m)) {
                $rateName = trim($m[1]);
                $count    = (int) $m[2];
            } else {
                $rateName = $item;
                $count    = 1;
            }
            $counts[$rateName] = ($counts[$rateName] ?? 0) + $count;
        }
        return $counts;
    }
}

if (!function_exists('resolveSwitchTourPackageData')) {
    function resolveSwitchTourPackageData($hist, $pairedPayment = null, $isCashFees = '0')
    {
        $r             = resolveHistCreatedBy($hist);
        $createdByName = $r['createdByName'];
        $createdByRole = $r['createdByRole'];
        $details       = $r['details'];

        $addedList    = ($details && !empty($details->added))   ? (array) $details->added   : [];
        $deletedList  = ($details && !empty($details->deleted)) ? (array) $details->deleted : [];
        $addedGrouped   = groupPaxList($addedList);
        $deletedGrouped = groupPaxList($deletedList);
        $oldPackage = $details->old_package ?? null;
        $newPackage = $details->new_package ?? null;

        $paymentDetails  = null;
        $paymentAmount   = 0;
        $paymentMethod   = null;
        $paymentType     = null;
        $refundType      = null;
        $cardNumber      = null;
        $isRefund        = false;
        $showPlatformFees = false;

        if ($pairedPayment) {
            $paymentDetails  = is_string($pairedPayment->details) ? json_decode($pairedPayment->details) : $pairedPayment->details;
            $isRefund        = $pairedPayment->operation == 'Refund';
            $paymentMethod   = $paymentDetails->payment_method ?? ($isRefund ? 'Refund' : 'Full Payment');
            $paymentType     = $paymentDetails->payment_type ?? 'Card';
            $cardNumber      = ($paymentType == 'Card' && !empty($pairedPayment->card_last_4_digit))
                ? str_pad($pairedPayment->card_last_4_digit, 4, '0', STR_PAD_LEFT)
                : null;
            $paymentAmount   = $paymentDetails->amount ?? 0;
            $refundType      = $isRefund ? ($paymentDetails->refund_type ?: 'Partial Refund') : '';
            $histPaymentType = (string) ($pairedPayment->payment_type ?? '');
            $showPlatformFees = $paymentType == 'Card'
                || $histPaymentType == 'Card'
                || $isCashFees == '1'
                || $paymentType == 'Cash'
                || $histPaymentType == 'Cash';
        }

        return [
            'createdByName'   => $createdByName,
            'createdByRole'   => $createdByRole,
            'details'         => $details,
            'addedGrouped'    => $addedGrouped,
            'deletedGrouped'  => $deletedGrouped,
            'oldPackage'      => $oldPackage,
            'newPackage'      => $newPackage,
            'payment'         => $pairedPayment,
            'paymentDetails'  => $paymentDetails,
            'paymentAmount'   => $paymentAmount,
            'paymentMethod'   => $paymentMethod,
            'paymentType'     => $paymentType,
            'refundType'      => $refundType,
            'cardNumber'      => $cardNumber,
            'isRefund'        => $isRefund,
            'showPlatformFees' => $showPlatformFees,
        ];
    }
}

if (!function_exists('resolveSplitOriginalBookingData')) {
    function resolveSplitOriginalBookingData($hist, $bookingM, $splitEntries = [])
    {
        $r             = resolveHistCreatedBy($hist);
        $createdByName = $r['createdByName'];
        $createdByRole = $r['createdByRole'];
        $details       = $r['details'];

        usort($splitEntries, fn($a, $b) => ($a['extended_booking_id'] ?? 0) <=> ($b['extended_booking_id'] ?? 0));
        $splitsCreated    = count($splitEntries);
        $totalGuestsMoved = 0;
        $totalReallocated = 0.0;
        $splitCards       = [];

        $extBookingIds  = array_values(array_filter(array_column($splitEntries, 'extended_booking_id')));
        $newBookingsMap = getSplitBookingTouristsByBookingIds($extBookingIds);

		for ($i = 0; $i < $splitsCreated; $i++) {
            $entryHist    = $splitEntries[$i]['hist'];
            $extBookingId = $splitEntries[$i]['extended_booking_id'];

            $newBooking  = $extBookingId ? ($newBookingsMap[$extBookingId] ?? null) : null;
            $guestsMoved = $newBooking ? (int) ($newBooking->tourists ?? 0) : 0;
            $totalGuestsMoved += $guestsMoved;

            $cardAmount = 0.0;
            foreach ($splitEntries[$i]['pairedRefunds'] as $ref) {
                $rd = is_string($ref->details) ? json_decode($ref->details) : $ref->details;
                $cardAmount += (float) ($rd->amount ?? 0);
            }
            $totalReallocated += $cardAmount;

            $splitCards[] = [
                'extended_booking_id' => $extBookingId,
                'guestsMoved'         => $guestsMoved,
                'amount'              => $cardAmount,
                'operation_at'        => $entryHist->operation_at,
            ];
        }

        $remainingPax = isset($splitEntries[0]) && $splitEntries[0]['hist']->tourists > 0
            ? $splitEntries[0]['hist']->tourists - $totalGuestsMoved
            : (int) (($bookingM->tourists ?? 0) + ($bookingM->infants ?? 0));

        return [
            'createdByName'    => $createdByName,
            'createdByRole'    => $createdByRole,
            'details'          => $details,
            'splitEntries'     => $splitEntries,
            'splitsCreated'    => $splitsCreated,
            'totalGuestsMoved' => $totalGuestsMoved,
            'totalReallocated' => $totalReallocated,
            'splitCards'       => $splitCards,
            'remainingPax'     => $remainingPax,
            'firstSplitAt'     => collect($splitEntries)->min(fn($e) => $e['hist']->operation_at),
        ];
    }
}

if (!function_exists('resolveRescheduledData')) {
    function resolveRescheduledData($hist, $bookingM)
    {
        $r             = resolveHistCreatedBy($hist);
        $createdByName = $r['createdByName'];
        $createdByRole = $r['createdByRole'];
        $details       = $r['details'];

        $hasDetails   = $details && isset($details->dateFrom);
        $hasSlots     = !empty($bookingM->package_has_slots);
        $dateTimeFrom = null;
        $dateTimeTo   = null;

        if ($hasDetails) {
            $dateTimeFrom = $hasSlots
                ? formatDateTime($details->dateFrom . ' ' . ($details->timeFrom ?? ''))
                : formatDate($details->dateFrom);
            $dateTimeTo = $hasSlots
                ? formatDateTime($details->dateTo . ' ' . ($details->timeTo ?? ''))
                : formatDate($details->dateTo);
        }

        return [
            'createdByName' => $createdByName,
            'createdByRole' => $createdByRole,
            'details'       => $details,
            'hasDetails'    => $hasDetails,
            'hasSlots'      => $hasSlots,
            'dateTimeFrom'  => $dateTimeFrom,
            'dateTimeTo'    => $dateTimeTo,
        ];
    }
}

if (!function_exists('resolvePermitFeesRefundData')) {
    function resolvePermitFeesRefundData($hist)
    {
        $r             = resolveHistCreatedBy($hist);
        $createdByName = $r['createdByName'];
        $createdByRole = $r['createdByRole'];
        $details       = $r['details'];

        $cardNumber  = ($details && !empty($details->card_number))
            ? str_pad((string) $details->card_number, 4, '0', STR_PAD_LEFT)
            : null;
        $paymentType = ($details && !empty($details->payment_type)) ? (string) $details->payment_type : '';
        $totalAmount = $details ? (float) ($details->amount ?? 0) : 0.0;

        $deletedStr    = ($details && isset($details->deleted)) ? (string) $details->deleted : '';
        $permitPersons = [];
        $totalPermits  = 0;
        if ($deletedStr !== '') {
            $segments = preg_split('/\s*,\s*/', trim($deletedStr)) ?: [];
            foreach ($segments as $segment) {
                $segment = trim($segment);
                if ($segment == '') continue;
                if (preg_match('/^(.+?)\s+person\s+(\d+)$/i', $segment, $m)) {
                    $rateName = trim($m[1]);
                    $count    = max(1, (int) $m[2]);
                    for ($i = 1; $i <= $count; $i++) {
                        $permitPersons[] = $rateName . ' person ' . $i;
                    }
                    $totalPermits += $count;
                } else {
                    $permitPersons[] = $segment;
                    $totalPermits++;
                }
            }
        }

        return [
            'createdByName'   => $createdByName,
            'createdByRole'   => $createdByRole,
            'details'         => $details,
            'cardNumber'      => $cardNumber,
            'paymentType'     => $paymentType,
            'totalAmount'     => $totalAmount,
            'permitPersons'   => $permitPersons,
            'totalPermits'    => $totalPermits,
            'perPersonAmount' => $totalPermits > 0 ? round($totalAmount / $totalPermits, 2) : 0.0,
        ];
    }
}

if (!function_exists('resolveEmailHistData')) {
    function resolveEmailHistData($hist)
    {
        $emailDetails = is_string($hist->details) ? $hist->details : (string) $hist->details;

        $isSent        = str_contains($emailDetails, 'sent');
        $isDelivered   = str_contains($emailDetails, 'delivered');
        $isViewed      = str_contains($emailDetails, 'viewed') || str_contains($emailDetails, 'opened');
        $isClicked     = str_contains($emailDetails, 'clicked');
        $isSpam        = str_contains($emailDetails, 'spam');
        $isBounced     = str_contains($emailDetails, 'bounce');
        $isSystemEvent = !$isSent && ($isDelivered || $isViewed || $isClicked || $isSpam || $isBounced);

        if ($isSent)          { $statusKey = 'sent';      $badgeLabel = 'Email Sent'; $badgeColor = '#2d5ecc'; }
        elseif ($isDelivered) { $statusKey = 'delivered'; $badgeLabel = 'Delivered';  $badgeColor = '#1e8c42'; }
        elseif ($isViewed)    { $statusKey = 'viewed';    $badgeLabel = 'Viewed';     $badgeColor = '#d08020'; }
        elseif ($isClicked)   { $statusKey = 'clicked';   $badgeLabel = 'Clicked';    $badgeColor = '#7c3aed'; }
        elseif ($isSpam)      { $statusKey = 'spam';      $badgeLabel = 'Spam';       $badgeColor = '#b45309'; }
        elseif ($isBounced)   { $statusKey = 'bounced';   $badgeLabel = 'Bounced';    $badgeColor = '#dc2626'; }
        else                  { $statusKey = 'sent';      $badgeLabel = 'Email';      $badgeColor = '#2d5ecc'; }

        $emailUsername  = $hist->username ?? '';
        $emailUserType  = $hist->operation_by_user_type ?? '';
        $emailCreatedBy = $emailUsername
            ? ($emailUsername . ($emailUserType ? ': (' . $emailUserType . ')' : ''))
            : $emailUserType;

        ['name' => $createdByName, 'role' => $createdByRole] = parseCreatedByWithName(
            $isSystemEvent ? null : ($emailCreatedBy ?: null)
        );

        return [
            'createdByName' => $createdByName,
            'createdByRole' => $createdByRole,
            'isSystemEvent' => $isSystemEvent,
            'statusKey'     => $statusKey,
            'badgeLabel'    => $badgeLabel,
            'badgeColor'    => $badgeColor,
            'emailDetails'  => $emailDetails,
        ];
    }
}

if (!function_exists('resolveEmailPipelineData')) {
    function resolveEmailPipelineData($hist, $emailGroup)
    {
        $allEvents = collect($emailGroup)->flatMap(fn($h) => $h->emailEvents ?? collect());

        $sentEvent      = $allEvents->firstWhere('status', 'Sent');
        $deliveredEvent = $allEvents->firstWhere('status', 'Delivered');
        $openEvent      = $allEvents->firstWhere('status', 'Open');
        $bounceEvent    = $allEvents->firstWhere('status', 'Bounce');
        $complaintEvent = $allEvents->firstWhere('status', 'Complaint');

        $recipientEmail = $sentEvent?->recipient_email ?? '';
        $subject        = $sentEvent?->subject ?? '';
        $senderName     = $sentEvent?->sender_name ?? '';

        $detailsText    = is_string($hist->details) ? $hist->details : json_encode($hist->details);
        $isClosure      = stripos($detailsText, 'closure') !== false;
        $emailTypeLabel = $isClosure ? 'Closure Email' : 'Booking Confirmation';

        $isBounced    = $bounceEvent !== null;
        $isDelivered  = $deliveredEvent !== null;
        $isOpened     = $openEvent !== null;
        $openCount    = (int) ($openEvent?->opens ?? 0);
        $bounceReason = $bounceEvent?->reason ?? '';

        $tenantTimezone = tourOperator()?->timezone ?? config('app.timezone');
        $sentRaw        = $sentEvent?->sent_at ?? $sentEvent?->created_at;
        $fmtSentAt      = $sentRaw        ? \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $sentRaw, config('app.timezone'))->setTimezone($tenantTimezone)->format('g:i a') : '';
        $fullSentAt     = $sentRaw        ? \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $sentRaw, config('app.timezone'))->setTimezone($tenantTimezone)->format('n/j/Y @ g:i a') : '';
        $fmtDeliveredAt = $deliveredEvent ? \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $deliveredEvent->created_at, config('app.timezone'))->setTimezone($tenantTimezone)->format('g:i a') : '';
        $fmtBounceAt    = $bounceEvent    ? \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $bounceEvent->created_at, config('app.timezone'))->setTimezone($tenantTimezone)->format('g:i a') : '';
        $fmtOpenAt      = $openEvent      ? \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $openEvent->created_at, config('app.timezone'))->setTimezone($tenantTimezone)->format('g:i a') : '';

        $emailContent = $sentEvent?->content ?? '';

        // Use the "Sent" history entry for user display (it holds the actual sender).
        // $hist may be the most-recent entry in the group (e.g. "Delivered") which
        // would resolve as a system event instead of the real user.
        $sentHistEntry = collect($emailGroup)->first(function ($h) {
            return collect($h->emailEvents ?? [])->contains('status', 'Sent');
        }) ?? $hist;

        $d = resolveEmailHistData($sentHistEntry);

        // For the legacy chip we still want the details of whichever entry was passed in.
        $dFallback    = resolveEmailHistData($hist);
        $statusKey    = $dFallback['statusKey'];
        $badgeLabel   = $dFallback['badgeLabel'];
        $emailDetails = $dFallback['emailDetails'];

        // histEntry used for avatar resolution in the blade
        $senderHistEntry = $sentHistEntry;

        return [
            'allEvents'       => $allEvents,
            'hasEvents'       => $allEvents->isNotEmpty(),
            'recipientEmail'  => $recipientEmail,
            'subject'         => $subject,
            'senderName'      => $senderName,
            'emailTypeLabel'  => $emailTypeLabel,
            'isBounced'       => $isBounced,
            'isDelivered'     => $isDelivered,
            'isOpened'        => $isOpened,
            'openCount'       => $openCount,
            'bounceReason'    => $bounceReason,
            'fmtSentAt'       => $fmtSentAt,
            'fullSentAt'      => $fullSentAt,
            'fmtDeliveredAt'  => $fmtDeliveredAt,
            'fmtBounceAt'     => $fmtBounceAt,
            'fmtOpenAt'       => $fmtOpenAt,
            'emailContent'    => $emailContent,
            'senderHistEntry' => $senderHistEntry,
            'createdByName'   => $d['createdByName'],
            'createdByRole'   => $d['createdByRole'],
            'isSystemEvent'   => $d['isSystemEvent'],
            'statusKey'       => $statusKey,
            'badgeLabel'      => $badgeLabel,
            'emailDetails'    => $emailDetails,
        ];
    }
}

if (!function_exists('resolveCustomerInfoUpdateData')) {
    function resolveCustomerInfoUpdateData($hist)
    {
        $details   = is_string($hist->details) ? json_decode($hist->details) : $hist->details;
        $opByUType = (string) ($hist->operation_by_user_type ?? '');

        $createdByName = '';
        $createdByRole = '';

        if ($opByUType == 'Tour Operator') {
            $finduser      = Staff::where('id', $hist->operation_by)->first();
            $createdByName = $finduser
                ? trim(($finduser->first_name ?? '') . ' ' . ($finduser->last_name ?? ''))
                : 'Tour Operator Staff';
            $createdByRole = 'Tour Operator Staff';
        } elseif ($opByUType == 'Affiliate') {
            $finduser      = Customer::where('id', $hist->operation_by)->withTrashed()->first();
            $createdByName = $finduser ? (string) ($finduser->name ?? 'Affiliate') : 'Affiliate';
            $createdByRole = 'Affiliate';
        } else {
            $finduser = Admin::where('id', $hist->operation_by)->first();
            if ($finduser) {
                $createdByName = (string) ($finduser->name ?? 'Admin');
                $createdByRole = 'Admin';
            } else {
                $finduser      = Staff::where('id', $hist->operation_by)->first();
                $createdByName = $finduser
                    ? trim(($finduser->first_name ?? '') . ' ' . ($finduser->last_name ?? ''))
                    : 'Staff';
                $createdByRole = 'Tour Operator Staff';
            }
        }

        if (!$createdByName && $details && !empty($details->created_by_with_name)) {
            ['name' => $createdByName, 'role' => $createdByRole] = parseCreatedByWithName((string) $details->created_by_with_name);
        }

        return [
            'createdByName' => $createdByName,
            'createdByRole' => $createdByRole,
            'details'       => $details,
            'prevName'      => ($details && !empty($details->name))         ? (string) $details->name         : null,
            'prevEmail'     => ($details && !empty($details->email))        ? (string) $details->email        : null,
            'prevPhone'     => ($details && !empty($details->phone_number)) ? (string) $details->phone_number : null,
            'newName'       => ($details && !empty($details->new_name))     ? (string) $details->new_name     : null,
            'newEmail'      => ($details && !empty($details->new_email))    ? (string) $details->new_email    : null,
            'newPhone'      => ($details && !empty($details->new_phone))    ? (string) $details->new_phone    : null,
        ];
    }
}

if (!function_exists('resolveCheckinData')) {
    function resolveCheckinData($hist)
    {
        $r             = resolveHistCreatedBy($hist);
        $createdByName = $r['createdByName'];
        $createdByRole = $r['createdByRole'];
        $details       = $r['details'];

        $isRedeemed = $details && isset($details->checkin_type) && $details->checkin_type == 1;
        $status     = $details->status ?? '';
        $package    = ($details && !empty($details->package)) ? (string) $details->package : null;

        return [
            'createdByName' => $createdByName,
            'createdByRole' => $createdByRole,
            'details'       => $details,
            'isRedeemed'    => $isRedeemed,
            'status'        => $status,
            'who'           => $details->who ?? '',
            'package'       => $package,
            'cardVariant'   => $isRedeemed ? 'redeemed' : 'check-in',
            'badgeLabel'    => $isRedeemed ? 'Redeemed' : 'Check-in',
            'cardTitle'     => $isRedeemed
                ? ($package ? "Ticket Redeemed — {$package}" : 'Ticket Redeemed')
                : ($package ? "Check-In Status Updated — {$package}" : 'Check-In Status Updated'),
            'statusClass'   => match(true) {
                in_array($status, ['Active', '1st Check In']) => 'ci-1st',
                $status == '2nd Check In'                    => 'ci-2nd',
                $status == 'Absent'                          => 'ci-absent',
                default                                       => 'ci-not-checked',
            },
        ];
    }
}

if (!function_exists('resolveCardSaveData')) {
    function resolveCardSaveData($hist)
    {
        ['name' => $createdByName, 'role' => $createdByRole] = parseCreatedByWithName(
            $hist->username . ': (' . $hist->operation_by_user_type . ')'
        );

        return [
            'createdByName' => $createdByName,
            'createdByRole' => $createdByRole,
            'source'        => !empty($hist->details) ? ucwords(strtolower((string) $hist->details)) : null,
            'cardLast4'     => !empty($hist->card_last_4_digit)
                ? str_pad($hist->card_last_4_digit, 4, '0', STR_PAD_LEFT)
                : null,
        ];
    }
}

if (!function_exists('resolveBookedByData')) {
    function resolveBookedByData($hist)
    {
        $details = is_string($hist->details) ? json_decode($hist->details) : $hist->details;
        ['name' => $createdByName, 'role' => $createdByRole] = parseCreatedByWithName(
            ($details && isset($details->created_by_with_name)) ? (string) $details->created_by_with_name : null
        );

        return [
            'createdByName' => $createdByName,
            'createdByRole' => $createdByRole,
            'fromPerson'    => ($details && !empty($details->from)) ? (string) $details->from : '—',
            'toPerson'      => ($details && !empty($details->to))   ? (string) $details->to   : '—',
        ];
    }
}

if (!function_exists('buildHistPhotoMap')) {
    function buildHistPhotoMap(\Illuminate\Support\Collection $history): array
    {
        $adminIds    = $history->where('operation_by_user_type', 'Admin')
                               ->pluck('operation_by')->filter()->unique()->values();
        $staffIds    = $history->where('operation_by_user_type', 'Tour Operator')
                               ->pluck('operation_by')->filter()->unique()->values();
        $customerIds = $history->whereIn('operation_by_user_type', ['Customer', 'Affiliate'])
                               ->pluck('operation_by')->filter()->unique()->values();

        $map = [];

        if ($adminIds->isNotEmpty()) {
            \Illuminate\Support\Facades\DB::table('admins')
                ->whereIn('id', $adminIds)
                ->select('id', 'profile_photo_path')
                ->get()
                ->each(function ($row) use (&$map) {
                    if (!empty($row->profile_photo_path)) {
                        $map["Admin_{$row->id}"] = $row->profile_photo_path;
                    }
                });
        }

        if ($staffIds->isNotEmpty()) {
            \Illuminate\Support\Facades\DB::table('tour_operator_staff')
                ->whereIn('id', $staffIds)
                ->select('id', 'profile_photo_path')
                ->get()
                ->each(function ($row) use (&$map) {
                    if (!empty($row->profile_photo_path)) {
                        $map["Tour_Operator_{$row->id}"] = $row->profile_photo_path;
                    }
                });
        }

        if ($customerIds->isNotEmpty()) {
            \Illuminate\Support\Facades\DB::table('customers')
                ->whereIn('id', $customerIds)
                ->select('id', 'profile_photo_path')
                ->get()
                ->each(function ($row) use (&$map) {
                    if (!empty($row->profile_photo_path)) {
                        $map["Customer_{$row->id}"]  = $row->profile_photo_path;
                        $map["Affiliate_{$row->id}"] = $row->profile_photo_path;
                    }
                });
        }

        return $map;
    }
}

if (!function_exists('resolveHistAvatar')) {
    function resolveHistAvatar($hist, array $histPhotoMap): string
    {
        $type  = str_replace(' ', '_', $hist->operation_by_user_type ?? '');
        $id    = $hist->operation_by ?? '';
        $photo = $histPhotoMap["{$type}_{$id}"] ?? null;
        return $photo ?: asset('images/no-photo.png');
    }
}

if (!function_exists('isComboParentBooking')) {
    function isComboParentBooking(array $comboBookingsData, int $bookingId): bool
    {
        $entry = collect($comboBookingsData)->firstWhere('id', $bookingId);
        return (bool) ($entry && ($entry['is_parent'] ?? false));
    }
}

if (!function_exists('resolveTourGuideNoteData')) {
    /**
     * Detect whether a history note is a tour guide assignment or removal.
     *
     * Returns an array:
     *   isTourGuideNote (bool)
     *   tourGuideAction ('assigned'|'removed'|null)
     *   tourGuideName   (string|null)
     */
    function resolveTourGuideNoteData($hist): array
    {
        $noteText = is_string($hist->details) ? $hist->details : '';

        if (preg_match('/^Tour guide (.+?) has been successfully assigned to the booking/i', $noteText, $m)) {
            return ['isTourGuideNote' => true, 'tourGuideAction' => 'assigned', 'tourGuideName' => $m[1]];
        }

        if (preg_match('/^Tour guide (.+?) assignment updated/i', $noteText, $m)) {
            return ['isTourGuideNote' => true, 'tourGuideAction' => 'updated', 'tourGuideName' => $m[1]];
        }

        if (preg_match('/^Tour guide (.+?) has been successfully removed from the booking\./i', $noteText, $m)) {
            return ['isTourGuideNote' => true, 'tourGuideAction' => 'removed', 'tourGuideName' => $m[1]];
        }

        if (preg_match('/^The assigned tour guide has been removed from the booking\./i', $noteText)) {
            return ['isTourGuideNote' => true, 'tourGuideAction' => 'removed', 'tourGuideName' => null];
        }

        return ['isTourGuideNote' => false, 'tourGuideAction' => null, 'tourGuideName' => null];
    }
}

if (!function_exists('isFreePaidBooking')) {
    function isFreePaidBooking($bookingM, $trans): bool
    {
        return ($bookingM->discount2_percentage >= 100)
            && empty($trans)
            && $bookingM->statusm != 'Cancelled';
    }
}

if (!function_exists('getDefaultBookingSource')) {
	function getDefaultBookingSource(): ?string
	{
		return Auth::user()->default_booking_source
			?: (getSettingsByKey('DEFAULT_BOOKING_SOURCE')->value ?: null);
	}
}

if (!function_exists('getGroupRateTierTable')) {
	/**
	 * Every Group Rate tier sharing the given rate's exact context (same tour package,
	 * scheduling year, custom date, and affiliate), as a flat [{min, max, price}, ...] table.
	 *
	 * Group Rate tiers price the WHOLE group as one fixed amount per headcount range (e.g.
	 * 1-3 pax = $280.90, 4 pax = $307.40, 5-6 pax = $339.20) rather than a flat per-pax rate.
	 * Used by the "Delete Booking" modal's live refund preview (tour-booking-info.blade.php /
	 * delete-booking-refund.js) to look up "price for N people" for whatever headcount results
	 * from the admin's checkbox selection, instead of dividing the group's current total evenly
	 * by its headcount — an approximation that's flatly wrong whenever two headcounts share a
	 * tier (e.g. showing a nonzero refund for going from 6 to 5 pax when both cost $339.20).
	 *
	 * @param int $tourPackageRateId The rate group's CURRENTLY active tour_package_rates.id
	 *                               (e.g. $bt->tour_package_rate_id) — its own scheduling
	 *                               year/custom date/affiliate define which sibling tiers apply.
	 * @return array<int, array{min: int, max: int, price: float}>
	 */
	function getGroupRateTierTable(int $tourPackageRateId): array
	{
		$context = \Illuminate\Support\Facades\DB::table('tour_package_rates')
			->where('id', $tourPackageRateId)
			->first(['tour_package_id', 'scheduling_year', 'custom_date_id', 'affiliate_id']);

		if (!$context) {
			return [];
		}

		return \Illuminate\Support\Facades\DB::table('tour_package_rates')
			->where('tour_package_id', $context->tour_package_id)
			->where('is_group_rate', 1)
			->where('deleted_by', 0)
			->where('scheduling_year', $context->scheduling_year)
			->when(is_null($context->affiliate_id), function ($query) {
				return $query->whereNull('affiliate_id');
			}, function ($query) use ($context) {
				return $query->where('affiliate_id', $context->affiliate_id);
			})
			->when(is_null($context->custom_date_id), function ($query) {
				return $query->whereNull('custom_date_id');
			}, function ($query) use ($context) {
				return $query->where('custom_date_id', $context->custom_date_id);
			})
			->orderBy('min_group_size')
			->get(['min_group_size', 'max_group_size', 'rate', 'additional_charge'])
			->map(function ($tier) {
				return [
					'min' => (int) $tier->min_group_size,
					'max' => (int) $tier->max_group_size,
					// Matches EditBookingService::deletePaxFromGroupBooking()'s $newSubTotal
					// (rate + additional_charge, no tax/processing charge) — this must equal
					// what tour_bookings_transact.total already represents for the current
					// tier, or the "same tier => $0 delta" comparison won't hold.
					'price' => round((float) $tier->rate + (float) $tier->additional_charge, 2),
				];
			})
			->values()
			->all();
	}
}
