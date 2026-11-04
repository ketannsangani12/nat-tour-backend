<?php

namespace App\Utils;

use App\Models\Customer;
use stdClass;
use Exception;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Setting;
use App\Models\TourBooking;
use App\Utils\StrUtils;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use App\Utils\IpGeolocationUtils;

class BookingUtils
{
    /**
     * return default start and end date from date param string
     * @param string $startDate
     * @param string $endDate
     * @return array [ start_date: string, end_date: string]
     */
    public static function getTouristCount(int $bookingId)
    {
        $tourist_count = 0;

        $sql = "SELECT sum(tbt.tourists) AS count FROM tour_bookings_transact AS tbt 
        INNER join tour_package_rates AS tpr ON tpr.id = tbt.tour_package_rate_id
        WHERE tbt.tour_booking_id = :booking_id
        GROUP BY tbt.tour_booking_id";

        $pax = DB::select($sql, ['booking_id' => $bookingId]);
        if (count($pax) > 0) {
            $tourist_count = $pax[0]->count;
        }

        return $tourist_count;
    }

    /**
     * Return the standard reponse acc. array for time delta for a tour
     * @param $secs = 0, $mins = 0, $hours = 0, $days = 0 
     * @return array
     */
    public static function getTourTimeDiffValue($secs = 0, $mins = 0, $hours = 0, $days = 0)
    {
        return ["seconds" => $secs, 'minutes' => $mins, 'hours' => $hours, 'days' => $days];
    }

    /**
     * Parse the booking tour date and tour time as datetime
     * @param $datetime
     * @return array
     */
    public static function getTourTimeDiff($datetime)
    {
        $mins = $hours = $days = 0;
        $diff = strtotime($datetime) - strtotime(now());

        if ($diff > 0) {
            $hours = floor($diff / 3600);
            $mins  = floor($diff / 60);
            $days  = floor($diff / 3600 / 24);
        }

        return BookingUtils::getTourTimeDiffValue($diff,  $mins, $hours, $days);
    }

    /**
     * Return a acc. array of known error messages to direct checking flow. 
     * @return array
     */
    public static function getBookingErrorsMessages()
    {
        return [
            "notfound" => ["error" => "Booking Not Found", "message" => "Please see a representative at the front desk"],
            "expired" => ["error" => "You have missed your tour time, please see an attendant.", "message" => "Please see a representative at the front desk"],
            "cancelled" => ["error" =>  "This Tour has been cancelled", "message" => "Please see a representative at the front desk"],
            "late" => ["error" => "This Tour is starting shortly",  "message" => "Please see a representative at the front desk to ensure you can still make the tour"],
            "early" => ["error" => "This Tour is not scheduled today", "message" => "Please see a representative at the front desk to discuss your tour details"],
            "packagenotfound" => ["error" => "Package Not Found!!", "message" => "Please see a representative at the front desk"],
            "invalidpackage" => ["error" => "Package Not Found!!", "message" => "Please select valid tour package OR location"],
            "invalidcheckin" => ["error" => "Tickets Not Found!!", "message" =>  "Please see a representative at the front desk"],
            "unpaid" => ["error" => "This tour is not yet paid for", "message" =>  "Please see a representative at the front desk"],
            "partial_unpaid" => ["error" => "This tour is not fully paid", "message" =>  "Please pay the remaining balance with a representative at the front desk"],
        ];
    }

    /**
     * Check a booking record for standard error states
     * ie: notfound|expired|cancelled|deleted
     * @return array
     */
    public static function validateBooking($booking, $checkInRedeemTicketFlag = 0)
    {
        $code = $error = null;
        $errMessages = BookingUtils::getBookingErrorsMessages();

        // booking not found
        if (!isset($booking)) {
            $code = "notfound";
            $error = $errMessages[$code];
        }

        // tour date is in the past :: expired 
        if ($checkInRedeemTicketFlag == 1) {
            if (!$error) {
                // If settings is set AUTO_CHECKINS example 10 
                // add 10 mins to the current time 
                $currentDate = Carbon::now();
                $settings = Setting::where('key', 'AUTO_CHECKINS')->first();

                if ($settings && $settings->value > 0) {
                    $minutes = (int) $settings->value;
                    $currentDate = Carbon::now()->addMinutes($minutes);
                }

                $dateToCheck = Carbon::parse($booking->tour_d_t);
                $tourIsInThePast = DateUtils::dateIsInThePast($dateToCheck, $currentDate);
                if ($tourIsInThePast) {
                    $code = "expired";
                    $error = $errMessages[$code];
                }
            }
        }

        //cancelled
        if (!$error) {
            if (isset($booking) && $booking->status == 'Cancelled') {
                $code = "cancelled";
                $error = $errMessages[$code];
            }
        }

        //deleted
        if (!$error) {
            if (isset($booking) && intval($booking->deleted_by) !== 0) {
                $code = "cancelled";
                $error = $errMessages[$code];
            }
        }

        //early
        $time_delta = isset($booking) ? BookingUtils::getTourTimeDiff($booking->tour_d_t) : BookingUtils::getTourTimeDiffValue();
        if (!$error) {
            //varify time: get the time delta 
            if (isset($booking) && $time_delta['days'] >= 1) {
                $code = "early";
                $error = $errMessages[$code];
            }
        }

        return [
            'code' => $code,
            'error' => $error,
            'time_delta' => $time_delta
        ];
    }

    /**
     * Parse the waiver json data stored in the booking 
     * liability_waiver_signing field 
     * @param $booking master booking model object
     * @param $paxCount number of tourists 
     * @return array
     */
    public static function getWaiverInfo($booking, $paxCount = 0)
    {
        $data = null;
        $signed = false;

        try {
            if (isset($booking->liability_waiver_signing)) {
                $data = json_decode($booking->liability_waiver_signing);
                if ($data && count($data) >= $paxCount) {
                    $signed = true;
                }
            }
        } catch (Exception $err) {
            print($err);
        }

        return  ['signatures' => $data, 'signed' => $signed, 'paxCount' => $paxCount];
    }

    public static function getRates($bookingId)
    {
        return  DB::table('tour_bookings_transact as tbm')
            ->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbm.tour_package_rate_id')
            ->where('tbm.tour_booking_id', '=', $bookingId)
            ->select('tpr.rate_for', 'tpr.age_from', 'tpr.age_to', 'tbm.tourists')
            ->get();
    }

    /**
     * Get images from the tour_package_images Table
     * @param $tourId int tour package id
     */
    public static function getRestrictedAndAllowedItems($tourId)
    {
        $images =  DB::table('tour_package_images as img')
            ->where('img.tour_package_id', '=', $tourId)
            ->whereIn('img.category', ['NotAllowed', 'Allowed', 'Directions', 'Splash'])
            ->where('img.is_cover', '=', false)
            ->where('img.deleted_by', '=', 0)
            ->select(['tour_package_id', 'image', 'caption', 'category', 'url'])->get();

        $results = ['NotAllowed' => [], 'Allowed' => [], 'Directions' => [], 'Splash' => []];
        foreach ($images as $img) {
            $results[$img->category][] = $img;
        }

        return $results;
    }

    /** 
     * Check to see if the booking is paid in full
     * @param int $bookingId
     * @return array
     */
    public static function isBookingPaid($bookingId)
    {
        $transactions = DB::table('transactions')
            ->where('tour_booking_id', $bookingId)
            ->where('status', 'Completed')->get(['amount', 'created_at', 'via']);

        $history = DB::table('tour_booking_history')->whereIn('operation', ["Payment", "Cancelled", "Refund"])
            ->where('tour_booking_id', $bookingId)->get();

        return ['transactions' => $transactions, 'history' => $history];
    }

    /** 
     * Check to see if the booking is partial but not fully paid
     * @param int $bookingId
     * @return array
     */
    public static function isBookingPartialUnpaid($booking)
    {
        $transaction = DB::table('transactions')
            ->where('tour_booking_id', $booking->id)
            ->where('status', 'Completed')
            ->select('payment_type')
            ->first();

        return !(
            $transaction &&
            $transaction->payment_type == 1 &&
            $booking->booking_total > $booking->paids
        );
    }

    public static function getBookingCustomer($bookingId)
    {
        $customerData = collect(
            DB::select(
                'SELECT cus.*, tbm.id AS booking_id 
                FROM tour_bookings_master AS tbm 
                LEFT JOIN customers AS cus ON cus.id = tbm.customer_id
                WHERE tbm.id = ?
                LIMIT 1;',
                [$bookingId]
            )
        )
            ->first();

        return $customerData;
    }

    /**
     * Creates an easy to iterate associative array for check in 
     * @param $rates
     * @return array
     */
    public static function checkedIn($rates)
    {
        $checkins = [];
        $totalTourists = 0;
        $checkedIn = true;

        foreach ($rates as $rate) {
            $index = $rate->rate_for;
            $checkins[$index] = [];
            $totalTourists += intval($rate->tourists);
            if (isset($rate->check_in_status)  && is_array($rate->check_in_status)) {
                foreach ($rate->check_in_status as $chk) {
                    array_push($checkins[$index], $chk->status);
                    if ($chk->status == "N" || $chk->status == "A") {
                        $checkedIn = false;
                    }
                }
            }
        }

        return [
            'all_checked_in' => $checkedIn,
            'tourists' => $totalTourists,
            'checkins' => $checkins
        ];
    }

    /**
     * Create A Record in the History Table for a spcific booking 
     * @param string|int bookingId
     * @param string $operation Note|Comments|Payment|Refund|Cancelled|Email|Check-in|
     *                          Booked By|Rescheduled|Waiver|Tip|Added|Update|Restore|
     *                          Customer Info Update|Payment Type Update|Error|
     *                          Reference Booking|Card Error
     * @param string $details
     * @param string $userType 
     * @return array
     */
    public static function updateHistory($bookingId, $details = "", $operation = "Check-in", $userType = "Customer")
    {
        $success = true;
        $error = null;
        $result = null;

        try {
            DB::table('tour_booking_history')
                ->insert([
                    'tour_booking_id' =>  $bookingId,
                    'operation' => $operation,
                    'details' => $details,
                    'operation_at' => mysqlDT(),
                    'operation_by' => Auth::id(),
                    'operation_by_user_type' => $userType,
                    'updated_at' => Carbon::now(),
                    'created_at' => Carbon::now(),
                ]);

            $result = collect(DB::select('SELECT * FROM tour_booking_history where id=(SELECT LAST_INSERT_ID())'))->first();
            DB::commit();
        } catch (Exception $exp) {
            $success = false;
            $error = $exp;
        }

        return ['success' => $success, 'result' => $result, 'error' => $error];
    }

    /**
     * return rates for Liability Waiver 
     * @param string|int $bookingId
     * @return mixed
     */
    public static function signatureRates($bookingId)
    {
        $res = DB::table('tour_bookings_transact AS tst')
            ->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tst.tour_package_rate_id')
            ->where('tst.tour_booking_id', $bookingId)
            ->orderBy('tpr.orderby', 'asc')
            ->get([
                'tst.id',
                'tst.tour_package_rate_id',
                'tst.tourists',
                'tpr.rate_for',
                'tpr.age_from',
                'tpr.age_to',
                'tst.processing_charge_percentage',
                'tst.additional_tax_percentage',
                'tst.permit_fee',
                'tst.additional_charge',
                'tst.state_tax',
                'tst.rate',
                'tst.total'
            ]);

        return $res;
    }

    /**
     * try to parse to liability_waiver_signing json
     * @param string json $signing_str liability_waiver_signing
     * @return array
     */
    public static function parseLiabilityWaiverSigning($signing_str)
    {
        $error = null;
        $signatures = null;
        try {
            if (!empty($signing_str)) {
                $signatures = json_decode($signing_str);
            }
        } catch (Exception $err) {
            $error = $err;
        }

        return ['signatures' => $signatures, 'error' => $error];
    }

    /**
     * Unpaid booking details | Pending payments
     */
    public static function fetchUnpaidBookings(
        $fromDate,
        $toDate,
        $tourPackageId = null,
        $userType = null,
        $tourOperatorId = null
    ) {
        $unpaid_payments = DB::table('tour_bookings_master AS tbm')
            ->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
            ->join('tour_operators as to', 'to.id', '=', 'tp.tour_operator_id')
            ->join('tour_bookings_transact AS tbt', 'tbt.tour_booking_id', '=', 'tbm.id')
            ->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
            ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
            ->leftJoin('transactions as t', 't.tour_booking_id', '=', 'tbm.id')
            ->whereNull('t.tour_booking_id')
            ->where('tbm.status', 'Booked')
            ->whereBetween('tsd.date', [$fromDate, $toDate])
            ->select(
                'tbm.id as tour_booking_id',
                'tbm.status as status',
                DB::raw('DATE_FORMAT(tbm.created_at, "%Y-%m-%d") as created_at'),
                'tp.name as package_name',
                'tbm.service_commission',
                'to.name as tour_operator_name',
                'tbt.processing_charge_percentage',
                'tbt.additional_charge',
                'tbt.refund_permitfee_status',
                'tbt.total as booking_transact_total',
                'tbt.rate',
                'tbt.permit_fee',
                'tbt.tourists',
                'tbm.total as amount',
                'tbm.tour_package_id',
                'tbm.name as customer_name',
                'tbm.email as customer_email',
                'tbm.infants'
            )
            ->groupBy('tbm.id');

        if ($tourPackageId && $tourPackageId !== 0) {
            $unpaid_payments = $unpaid_payments->where('tbm.tour_package_id', '=', $tourPackageId);
        }

        if ($userType == 'Tour Operator') {
            $unpaid_payments = $unpaid_payments->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
        } else {
            $unpaid_payments = $unpaid_payments->where('tp.tour_operator_id', '=', $tourOperatorId);
        }

        return $unpaid_payments->get();
    }

    /**
     * return a list of bookings by col indexed by booking id
     * @param string|array $col.
     * @return array
     */
    public static function getBookingsIndexById(string|array $col, bool $make_query = true)
    {
        $res = [];
        if ($make_query) {
            $bookings = DB::select($col);
        } else {
            $bookings = $col;
        }

        //grby booking  id
        foreach ($bookings as $booking) {
            if (!isset($res[$booking->bid])) {
                $res[$booking->bid] = [];
            }
            $res[$booking->bid][] = $booking;
        }

        return $res;
    }

    public static function getAllInvoiceInId(array $bookings)
    {
        // get all of the invoices for the given booking ids we will use this array
        // to look up if a booking id has been invoiced if not we will mark the item billiable.
        $ids = join("','", array_keys($bookings));
        $sql = "select distinct ib.tour_booking_id as id from invoice_bookings AS ib WHERE ib.tour_booking_id IN ('" . $ids . "')";
        $invoices = DB::select($sql);

        $inv = [];
        //grby invoices by id
        foreach ($invoices as $invoice) {
            $inv[$invoice->id] = 1;
        }

        //return the indexed array
        return $inv;
    }

    /**
     * Total sum of all bookings
     * A standarized data struct for the booking totals
     * @return object
     */
    public static function revenueTotalsStruct(): object
    {
        $totals = new stdClass;
        $totals->total_gross = 0;
        $totals->total_ticket = 0;
        $totals->total_refund = 0;
        $totals->total_discounts = 0;
        $totals->total_net = 0;
        $totals->total_processing_fees = 0;
        $totals->total_platform_fees = 0;
        $totals->total_permit_fees = 0;
        $totals->total_refund_fees = 0;
        $totals->trips = 0;

        return $totals;
    }

    public static function getAggregateRow($d, $calc, $invoices = [])
    {

        $calc["net"] = $calc["gross"] - $calc["ticket_cost_sum"] - $calc["plt_fees"];
        return  [
            "booking_id" => $d->bid,
            "booking_date" => $d->bdt,
            "status" => $d->status,
            "nm" => $d->nm, // tour name
            "cnm" => $d->cname, // customer name
            "dt" => $d->dt,
            "wk" => $d->wk,
            "mn" => $d->mn,
            "yr" => $d->yr,
            "mn_yr" => $d->mn_yr,
            "wk_mn_yr" => $d->wk_mn_yr,
            "tour_date" => $d->t_date,
            "tour_time" => $d->t_time,
            "trans_type" => $d->trans_type,
            "trans_cost" => $d->ticket_cost_from_detail,
            "ticket_cost" => $calc["net"],
            "processing_fees" => $d->pro_fees,
            // "platform_fees" => $calc["platform_fees"],
            "platform_fees" => $calc["plt_fees"],
            "permit_fees" => $calc["permit_fees"],
            "operations" => $calc["operations"],
            "tourists" => $d->tourists,
            "discount" =>  $calc["discount"],
            "gross_revenue" => $calc["gross"],
            "net_revenue" =>  sprintf("%.2f", $calc["net"]),
            "refund_total" => $calc["refund"],
            "billable" => ($d->trans_type == "Invoiced") ? !array_key_exists($d->bid, $invoices) : false,
        ];
    }

    public static function getTotalsFees($b)
    {
        $oldFees = StrUtils::parseFloat($b->processing_fees, 0);
        $newFees =  StrUtils::parseFloat($b->service_commission, 0);

        return ($oldFees + $newFees);
    }

    public static function calculateNewRevenue($b, &$bookingTotals)
    {
        //calc discount
        $discount = StrUtils::parseFloat($b->dis);
        $bookingTotals["discount"] = $discount;

        // if history object is of operation type payment
        if ($b->opt == "Payment") {
            if (in_array($b->trans_type, ["Cash", "Wire Transfer", "Cheque/Wire", "Check/Wire"])) {
                $bookingTotals["gross"] += StrUtils::parseFloat($b->ticket_cost_from_detail);
                $bookingTotals["plt_fees"] += StrUtils::parseFloat($b->plt_fees);
                $bookingFees = StrUtils::parseFloat($b->service_commission, 0);
                $bookingTotal = StrUtils::parseFloat($b->ticket_cost_from_detail);

                //fix for cash in the future we will have to adjust this because we are going to charge a fee for cash
                if ($bookingFees > 0 && $bookingTotal > 0) {
                    $bookingTotals["net"] +=  StrUtils::parseFloat($b->ticket_cost);
                } else {
                    $bookingTotals["net"] += StrUtils::parseFloat($b->ticket_cost_from_detail);
                }
            } else {
                $bookingTotals["gross"] += StrUtils::parseFloat($b->ticket_cost_from_detail);
                $bookingTotals["plt_fees"] += StrUtils::parseFloat($b->plt_fees);
                $bookingTotals["net"] +=  StrUtils::parseFloat($b->ticket_cost, 0);
                $bookingTotals["permit_fees"] +=  StrUtils::parseFloat($b->permit_fees, 0);
            }
        }
        // if refund or cancelled
        if ($b->opt == "Refund" || $b->opt == "Cancelled") {
            //if parial then: min the refund from the existing refund value
            $fee = floatval($b->service_commission);
            $bookingTotals["ticket_cost_sum"] += floatval($b->ticket_cost);
            $net = (($b->booking_total - $bookingTotals["ticket_cost_sum"]) - $fee);
            if ($bookingTotals["gross"] == 0) {
                $bookingTotals["net"] = $bookingTotals["ticket_cost_sum"];
            }

            if ($bookingTotals["gross"] != 0 && $b->payment_method  == "100 % Refund" || $b->payment_method  == "Permitfees Refund") {
                $bookingTotals["refund"] += StrUtils::parseFloat($b->ticket_cost, 0);
                $bookingTotals["net"] = (($bookingTotals["gross"] - $bookingTotals["ticket_cost_sum"] - $bookingTotals["plt_fees"]));
                return;
            } else {
                $bookingTotals["refund"] += StrUtils::parseFloat($b->ticket_cost, 0);
                $bookingTotals["net"] = $net;
            }

            if ($bookingTotals["gross"] == 0) {
                $bookingTotals["net"] = -$bookingTotals["refund"];
            }
            if ($b->payment_method  == "Don't Refund") {
                $bookingTotals["refund"] = $bookingTotals["refund"] - StrUtils::parseFloat($b->ticket_cost, 0);
                return;
            }

            if (!in_array($b->payment_method, ["Full Refund minus Fees", "Partial Refund", "Full Refund"])) {
                $bookingTotals["ticket_refund"] = ($bookingTotals["gross"] - $bookingTotals["platform_fees"]);
            }

            if (!in_array($b->payment_method, $bookingTotals["payment_methods"])) {
                if ($b->payment_method != "Full Refund minus Fees") {
                    $bookingTotals["refund"] = $bookingTotals["refund"] - $bookingTotals["platform_fees"];
                }
            }

            return;
        } // end refund
    }

    public static function calculateOldRevenue($b, &$bookingTotals)
    {
        //calc discount
        $discount = StrUtils::parseFloat($b->dis);
        if ($discount > 0) {
            $bookingTotals["discount"] += $discount;
        }

        // // if history object is of operation type payment
        if ($b->opt == "Payment") {
            if (in_array($b->trans_type, ["Cash", "Wire Transfer", "Cheque/Wire", "Check/Wire"])) {
                if ($b->payment_method == "Split Payment") {
                    $bookingTotals["gross"] += StrUtils::parseFloat($b->ticket_cost_from_detail);
                    $bookingTotals["net"] += StrUtils::parseFloat($b->ticket_cost_from_detail);
                } else {
                    $bookingTotals["gross"] += StrUtils::parseFloat($b->booking_total, $b->total);
                    $bookingTotals["net"] +=  StrUtils::parseFloat($b->booking_total, $b->total);
                }
            } else {
                $bookingTotals["gross"] += StrUtils::parseFloat($b->ticket_cost_from_detail,  $b->total);

                if ($b->payment_method == "Split Payment") {
                    $bookingTotals["net"] +=  StrUtils::parseFloat($b->ticket_cost_from_detail, $b->ticket_cost);
                } else {
                    $bookingTotals["net"] += StrUtils::parseFloat($b->ticket_cost);
                    // $bookingTotals["net"] +=  StrUtils::parseFloat($b->booking_total, $b->ticket_cost);
                }
            }

            $bookingTotals["permit_fees"] +=  StrUtils::parseFloat($b->permit_fees, 0);
        }

        // if refund or cancelled
        if ($b->opt == "Refund" || $b->opt == "Cancelled") {
            $net = (StrUtils::parseFloat($bookingTotals["gross"]) - StrUtils::parseFloat($b->ticket_cost_from_detail, $b->ticket_cost));
            $net = $net - $bookingTotals["platform_fees"];

            $bookingTotals["refund"] += StrUtils::parseFloat($b->ticket_cost_from_detail, $b->ticket_cost);

            if ($b->payment_method  == "100 % Refund") {
                $bookingTotals["net"] = 0;
                $bookingTotals["refund"] = StrUtils::parseFloat($b->ticket_cost_from_detail, $b->total);
                return;
            }

            if ($b->payment_method  == "Don't Refund") {
                $bookingTotals["refund"] =  StrUtils::parseFloat($b->ticket_cost_from_detail, $b->ticket_cost);
                $bookingTotals["net"] = StrUtils::parseFloat($b->booking_total, $b->total) - $bookingTotals["refund"];
                $bookingTotals["gross"] = $bookingTotals["gross"] + StrUtils::parseFloat($b->ticket_cost_from_detail, $b->ticket_cost);
                return;
            }

            $net = $net - $bookingTotals["refund"];

            if (!in_array($b->payment_method, ["Full Refund minus Fees", "Partial Refund", "Full Refund"])) {
                $bookingTotals["ticket_refund"] = ($bookingTotals["gross"] - $bookingTotals["platform_fees"]);
            }

            if ($b->payment_method == "Full Refund minus Fees") {
                if ($b->booking_total == 0) {
                    $bookingTotals["net"] = $bookingTotals["gross"] - StrUtils::parseFloat($b->ticket_cost);
                }
            }

            if (!in_array($b->payment_method, $bookingTotals["payment_methods"])) {
                if ($b->payment_method != "Full Refund minus Fees") {
                    $bookingTotals["refund"] = $bookingTotals["refund"] - $bookingTotals["platform_fees"];
                }
            }

            return;
        } // end refund
    }

    /**
     * Return a collection of totals as a associative array
     * @param array $bookings 
     * @param array $invoices 
     * @return array
     */
    public static function calculateRevenue($bookings, $invoices = []): array
    {
        $collection = [];
        $totals = BookingUtils::revenueTotalsStruct();
        $oldBookingId = oldBookingId();
        $tourSlotIdArray = [];
        foreach ($bookings as $bookingId => $booking) {

            $bookingTotals = [
                "booking_id" => $bookingId,
                "gross" => 0,
                "net" => 0,
                "refund" => 0,
                "ticket_refund" => 0,
                "permit_fees" => 0,
                "ticket_cost_sum" => 0,
                "payment_methods" => [],
                "operations" => [],
                "platform_fees" => StrUtils::parseFloat($booking[0]->service_commission, 0),
                "discount" => 0,
                "plt_fees" => 0
            ];

            foreach ($booking as $b) {
                //collect history debug data
                if ($b->booking_status != "Cancelled") {
                    $tourSlotIdArray[] = $b->tour_slot_id;
                }

                $bookingTotals["operations"][] = [
                    "operation" => $b->opt,
                    "history_id" => $b->hist_id,
                    "payment_method" => $b->payment_method,
                    "ticket_cost" => StrUtils::parseFloat($b->ticket_cost, $b->ticket_cost_from_detail),
                    "ticket_cost_booking" => $b->ticket_cost,
                    "ticket_cost_details" => $b->ticket_cost_from_detail,
                    "total" => StrUtils::parseFloat($b->booking_total, $b->total),
                    "booking_fees" => StrUtils::parseFloat($booking[0]->service_commission, 0),
                    "discount" => StrUtils::parseFloat($b->dis, 0)
                ];

                // collect payment methods
                $bookingTotals["payment_methods"][] = $b->payment_method;

                // if new booking 
                // if ($b->bid > $oldBookingId) {
                BookingUtils::calculateNewRevenue($b, $bookingTotals);
                //else old booking id
                // } else {
                //     BookingUtils::calculateOldRevenue($b, $bookingTotals);
                // }
            } // end foreach booking

            $bookingTotals["net"] = $bookingTotals["gross"] - $bookingTotals["ticket_cost_sum"] - $bookingTotals["plt_fees"];

            // add to the global totals
            $trips = array_unique($tourSlotIdArray);
            $trips = count($trips);
            $totals->trips = $trips;
            $totals->total_gross += $bookingTotals["gross"];
            $totals->total_net += $bookingTotals["net"];
            $totals->total_refund += $bookingTotals["refund"];
            // $totals->total_platform_fees += $bookingTotals["platform_fees"];
            $totals->total_platform_fees += $bookingTotals["plt_fees"];
            $totals->total_permit_fees += $bookingTotals["permit_fees"];
            $totals->total_discounts += $bookingTotals["discount"];

            //add the booking row to the collection
            $collection[] = BookingUtils::getAggregateRow($b, $bookingTotals, $invoices);
        }

        return ["data" => $collection, "count" => count($collection), "sums" => (array)$totals];
    }

    /**
     * Return default totals to populate most reports
     * @param array $bookings 
     * note booking object must be formated with the booking id as the index example below 
     *     "1291": [
     *   {
     *       "cname": "UTOURS ",
     *       "bid": 1291,
     *       "service_fee": "0.00",
     *       "discount": "0.00",
     *       "total": "1798.20",
     *       "rate": "49.00",
     *       "tourists": 30,
     *       "permit_fee": "8.00",
     *       "additional_charge": "2.94",
     *       "rate_for": "$6 Affiliate",
     *       "payment_date": "2023-12-14 00:00:00",
     *       "operation": "Payment",
     *       "details": "{\"amount\":72.2,\"payment_type\":\"Card\",\"card_number\":\"0\",\"card_type\":\"\"}",
     *       "tour_date": "2023-12-12",
     *       "tour_start": "14:30:00",
     *       "tour_end": "15:30:00"
     *   }
     * ],
     */
    public static function getBookingReportTotals(array $bookings)
    {
        $totals = [];

        foreach ($bookings as $key => $booking) {
            $output = [
                "id" => $key,
                "name" => $booking[0]->cname,
                "status" => "complete",
                "refund" => false,
                "refund_amount" => 0,
                "permit_fee" => 0,
                "service_fee" => 0,
                "discount" => 0,
                "total" => 0,
                "tourists" =>  0,
                "tax" => 0,
                "rates" => [],
                "payment_date" => null,
                "operations" =>  [],
                "history_ids" => [],
                "tour_date" => $booking[0]->tour_date,
                "tour_start" =>  $booking[0]->tour_start,
                "tour_end" => $booking[0]->tour_end,
            ];

            foreach ($booking as $row) {
                if (strstr($row->details, "amount")) {
                    $details = json_decode($row->details);
                } else {
                    $details = null;
                }

                if (!isset($output["rates"][$row->rate_for])) {
                    $output["tourists"] += StrUtils::parseFloat($row->tourists);
                    $output["total"] += $row->total;
                }

                $output["rates"][$row->rate_for] = [
                    "total" => StrUtils::parseFloat($row->total),
                    "rate" => StrUtils::parseFloat($row->rate),
                    "pax" => StrUtils::parseFloat($row->tourists)
                ];

                if ($row->operation == 'Payment') {
                    if (in_array($row->history_id, $output["history_ids"])) {
                        continue;
                    }

                    if (!in_array($row->operation, $output["operations"])) {
                        $output["operations"][] = $row->operation;
                    }

                    $output["history_ids"][] = $row->history_id;
                    $output["discount"] = StrUtils::parseFloat($row->discount);
                    $output["payment_date"] = $row->payment_date;
                    $output["service_fee"] += StrUtils::parseFloat($row->service_fee);
                }

                if ($row->operation === "Cancelled") {
                    $output["status"] = $row->operation;
                }

                if ($row->operation === "Refund") {
                    $output["refund"] = true;
                    $output["refund_amount"] += StrUtils::parseFloat($details->amount);
                }
            }

            $totals[] = $output;
        }

        return $totals;
    }

    /**
     * only include bookings that have been imported that have nat fees. 
     */
    public static function includeImportedBookingsWithFees($bookings)
    {
        // if the filter is set to only show external bookings where nat has made fee from 
        $externalBookings = array_filter($bookings, function ($obj) {
            return (strstr($obj[0]->comments, "#") && strstr($obj[0]->status, "Cancel")) ? true : false;
        });



        if (count($externalBookings) > 0) {
            $charges = DB::select("SELECT DISTINCT(tour_booking_id), application_fee_id from transactions 
                WHERE tour_booking_id IN ( " . implode(',', array_keys($externalBookings)) . " )
                AND application_fee_id != '' 
                AND application_fee_id IS NOT NULL ");

            $externalBookingsToKeep = [];
            foreach ($charges as $charge) {
                $externalBookingsToKeep[] = $charge->tour_booking_id;
            }

            $bookings = array_filter($bookings, function ($obj) use ($externalBookingsToKeep) {
                if (strstr($obj[0]->comments, "#")) {

                    if (in_array(strval($obj[0]->bid), $externalBookingsToKeep)) {
                        return true;
                    } else {
                        return false;
                    }
                }
                return true;
            });
        } //end check if $externalBookings > 0

        return $bookings;
    }

    public static function getTripAdvisorBookings($fromDate, $toDate, $userType, $tourPackageId, $tourOperatorId)
    {
        // TripAdvisor Booking Integration
        $tripAdvisorBookings = DB::table('tour_bookings_master AS tbm')
            ->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
            ->join('tour_operators as to', 'to.id', '=', 'tp.tour_operator_id')
            ->join('tour_booking_history as tbh', 'tbh.tour_booking_id', '=', 'tbm.id')
            ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
            ->whereIn('tbm.status', ['Booked', 'Cancelled'])
            ->where('tbh.payment_type', 'TripAdvisor')
            ->whereIn('tbh.operation', ['Payment', 'Refund', 'Cancelled'])
            ->whereBetween('tsd.date', [$fromDate, $toDate])
            ->select(
                'tbm.id as tour_booking_id',
                DB::raw('DATE_FORMAT(tbm.created_at, "%Y-%m-%d") as created_at'),
                'tp.name as package_name',
                'tbm.service_commission',
                'to.name as tour_operator_name',
                'tbh.payment_type',
                'tbm.tourists',
                'tbh.operation',
                'tbm.total as amount',
                'tbm.tour_package_id'
            );

        if ($tourPackageId && $tourPackageId !== 0) {
            $tripAdvisorBookings = $tripAdvisorBookings->where('tbm.tour_package_id', '=', $tourPackageId);
        }

        $tourOperatorId = ($userType == 'Tour Operator') ? Auth::user()->tour_operator_id : $tourOperatorId;

        $tripAdvisorBookings = $tripAdvisorBookings->where('tp.tour_operator_id', $tourOperatorId);

        $tripAdvisorBookings = $tripAdvisorBookings->get();
        $tripAdvisorBookingsResult = json_decode($tripAdvisorBookings, true);

        $tripAdvisorArr = array();

        $response = [];

        if (count($tripAdvisorBookingsResult)) {
            $tripAdvisorBookingAmount = 0;
            $tripAdvisorRefundAmount = 0;
            $tripAdvisorNetAmount = 0;

            foreach ($tripAdvisorBookingsResult as $val) {
                $tripAdvisorArr[$val['package_name']][] = $val['tour_booking_id'];

                if ($val['operation'] == 'Payment') {
                    $tripAdvisorBookingAmount = $tripAdvisorBookingAmount + $val['amount'];
                }
                if ($val['operation'] == 'Cancelled') {
                    $tripAdvisorRefundAmount = $tripAdvisorRefundAmount + $val['amount'];
                }
                $response['tripadvisor'][$val['package_name']]['tripAdvisorBookingAmount'] = $tripAdvisorBookingAmount;
                $response['tripadvisor'][$val['package_name']]['tripAdvisorRefundAmout'] = $tripAdvisorRefundAmount;
                $response['tripadvisor'][$val['package_name']]['tripAdvisorBookingCount'] = (!empty($tripAdvisorArr[$val['package_name']])) ? count(array_unique($tripAdvisorArr[$val['package_name']])) : 0;
                $response['tripadvisor'][$val['package_name']]['tripAdvisorPaymentType'] = $val['payment_type'];
            }

            $tripAdvisorNetAmount = $tripAdvisorNetAmount + $tripAdvisorBookingAmount - $tripAdvisorRefundAmount;
            $response['tripadvisor'][$val['package_name']]['tripAdvisorNetAmout'] = $tripAdvisorNetAmount;
        }

        return $response;
    }

    /**
     * Return an error response(common) for redeem tickets APIs
     * @return array
     */
    public static function getErrorResponse($code)
    {
        $errorResponse = self::getBookingErrorsMessages();
        $error = $errorResponse[$code];

        return [
            'errorcode' => $code,
            'error' => $error
        ];
    }

    /**
     * Return custom error response for redeem tickets APIs
     * @return array
     */
    public static function getCustomErrorResponse($code, $error, $message)
    {
        $errorMessage = [
            "error" => $error,
            "message" => $message
        ];

        return [
            'errorcode' => $code,
            'error' => $errorMessage
        ];
    }
    /**
     * Checks if the user has already reserved a booking for the given slot.
     *
     * @param Request $request The HTTP request containing booking details.
     * @param object $info The tour package information.
     * @throws Exception If the user has already reserved a booking for the slot.
     */
    public static function checkAffiliateReservation($request, $info)
    {
        if ($request->has('is_reserved_for_affiliate') && $request->is_reserved_for_affiliate == 1) {
            $reserveBooking = TourBooking::whereDoesntHave('transactions')
                ->where('tour_package_id', $info->tour_package_id)
                ->where('is_reserved_for_affiliate', '1')
                ->where('customer_id', $request->affiliatecust)
                ->where('tour_slot_id', $request->slot_time)
                ->where('status', 'Booked')
                ->count();

            if ($reserveBooking > 0) {
                throw new Exception('Unable to book the tour package. You have already reserved a booking for the slot.');
            }
        }
    }

    /**
     * Validates if the booking time is in the future and prevents booking in the past.
     *
     * @param Request $request The HTTP request containing booking details.
     * @param object $info The tour package information.
     * @throws Exception If the booking date-time has already passed.
     */
    public static function checkBookingTimeValidity($request, $info)
    {
        $paymentIntentId = $request->has('payment_intent_id') && $request->payment_intent_id != ''
            ? $request->payment_intent_id
            : '';

        // Check if the booking is for a past date-time
        if ($info->package_has_slots && ("{$info->date} {$info->time_from}" <= mysqlDT()) && $paymentIntentId == '') {
            if ($request->has('api') && $request->api == '1') {
                throw new Exception("The tour's date-time has passed; booking isn't possible in the past.");
            } elseif (!$request->has('api')) {
                throw new Exception("The tour's date-time has passed; booking isn't possible in the past.");
            }
        }
    }

    /**
     * Blocks affiliates from booking within the configured duration restriction window.
     * Mirrors the UI-side affiliateFeature=0 logic so the API cannot be used to bypass it.
     *
     * @param object $info Slot info with date, time_from, and package_has_slots fields.
     * @throws Exception If the affiliate lacks permission and is within the restricted window.
     */
    public static function checkAffiliateDurationRestriction($info): void
    {
        if (userType() !== 'Affiliate') {
            return;
        }

        $affiliate = Customer::find(Auth::id());

        if (!$affiliate || $affiliate->duration <= 0 || checkPermission('booking-after-duration')) {
            return;
        }

        if (!$info->package_has_slots) {
            return;
        }

        $cutoff = Carbon::parse("{$info->date} {$info->time_from}");

        switch ($affiliate->day_hours) {
            case 1:
                $cutoff->subDays($affiliate->duration);
                break;
            case 2:
                $cutoff->subHours($affiliate->duration);
                break;
        }

        if (Carbon::now() >= $cutoff) {
            throw new Exception('Booking is not allowed within the restricted duration period. You do not have permission to book at this time.');
        }
    }

    /**
     * Retrieves or creates a customer based on affiliate ID or email.
     *
     * @param Request $request The HTTP request containing customer details.
     * @param object $info The tour package information.
     * @return array [Customer $customer, bool $newCustomer] Returns the customer instance and a flag if a new customer was created.
     */
    public static function getOrCreateCustomer($affiliateCustomer, $customerEmail, $customerName, $customerPhoneNumber, $tourOperatorId)
    {
        // Check if affiliate customer exists
        if ($affiliateCustomer > 0) {
            $customer = Customer::where('id', $affiliateCustomer)
                ->where('tour_operator_id', $tourOperatorId)
                ->first();
        } else {
            $customer = Customer::where('email', $customerEmail)
                ->where('tour_operator_id', $tourOperatorId)
                ->withTrashed()
                ->first();
        }

        // If customer does not exist and it's not an affiliate booking, create a new customer
        $pwd = '';

        if (!$customer && $affiliateCustomer == 0) {
            $pwdChars = '23456789~!@#$%^*()-=+_[]{};:?.,>abcdefghijkpqstuxyzABCDEFGHJKLMNPQRSTUXYZ';

            // Generate a random 10-character password
            for ($i = 0, $cl = strlen($pwdChars) - 1; $i < 10; $i++) {
                $pwd .= $pwdChars[rand(0, $cl)];
            }

            // Create new customer record
            $customer = Customer::create([
                'tour_operator_id' => $tourOperatorId,
                'name' => $customerName,
                'email' => $customerEmail,
                'phone_number' => str_replace(['(', ')', '+', '-', '.', ' '], '', $customerPhoneNumber),
                'password' => Hash::make(config('app.debug') ? '123456' : $pwd),
                'ip_address' => request()->ip(), // Capture IP address during customer creation
            ]);

            // Get and store location data for the new customer
            if ($customer && request()->ip()) {
                IpGeolocationUtils::updateCustomerLocationFromIp($customer->id, request()->ip());
            }

            DB::commit();

            $isNewCustomer = true;
        } else {
            $isNewCustomer = false;
        }
        return [
            'customer' => $customer,
            'isNewCustomer' => $isNewCustomer,
            'pwd' => $pwd
        ];
    }
    /**
     * Retrieves rate group details for the given rate group IDs.
     *
     * @param array $rateGrpIds The list of rate group IDs.
     * @return array The fetched rate group details.
     */
    public static function getRateGroups(array $rateGrpIds, $isPartnerPackageEnabled = 0, $isCashPrice = 0)
    {
        // Convert rate group IDs array into a comma-separated string
        $tprId = implode(',', $rateGrpIds);
        $parterCommissionFields = '';
        $cashPriceFields = '';
        if ($isPartnerPackageEnabled == 1) {
            $parterCommissionFields = ",`tpr`.`partner_fee_percentage`,`tpr`.`partner_fee_type`,`tpr`.`partner_fee_amount`,`tpr`.`margin`";
        }

        // Fetch rate group details
        return DB::select("
            SELECT
                `tpr`.`id`, `tpr`.`rate_for`,
                `tpr`.`rate`, `tpr`.`additional_tax_percentage`,
                `tpr`.`processing_charge_percentage`, `tpr`.`additional_charge`, `tpr`.`is_infant_rate`, `tpr`.`is_cash_rate`,
                `tpr`.`cash_rate`,`tpr`.`cash_tax`
                " . $parterCommissionFields . "
            FROM
                `tour_package_rates` AS `tpr`
            INNER JOIN `tour_packages` AS `tp` ON `tp`.`id` = `tpr`.`tour_package_id`
            WHERE
                `tpr`.`deleted_by` = 0
            AND
                `tpr`.`id` IN ($tprId)
            ORDER BY
                `tpr`.`orderby` ASC
        ");
    }
    /**
     * Handles cash payment adjustments for split and full payments.
     *
     * @param Request $request The payment request.
     * @param int $isCashFees Indicator if cash fees apply.
     */
    public static function handleCashPayment(&$request, $isCashFees)
    {
        // If cash change amount is less than ticket cost/total (split payment)
        if ($request->payment_method == "Cash" && $request->has('cash_paid_amount') && $request->cash_paid_amount != '' && $request->split_value > 0) {
            $cashPaidAmount = $request->cash_paid_amount;
            $paymentAmountCash = $request->subtt;

            if ($cashPaidAmount > 0 && $cashPaidAmount < $paymentAmountCash) {
                $request->split_value = $cashPaidAmount;
            }
        }

        // If cash change amount is less than ticket cost/total (full payment)
        if ($request->payment_method == "Cash" && $request->has('cash_paid_amount') && $request->cash_paid_amount != '' && empty($request->split_value)) {
            $cashPaidAmount = $request->cash_paid_amount;
            $paymentAmountCash = ($isCashFees == 1) ? $request->subtt + $request->fee : $request->subtt;

            if ($cashPaidAmount > 0 && $cashPaidAmount < $paymentAmountCash) {
                $request->split_value = $cashPaidAmount;
            }
        }
    }
    /**
     * Prepare booking data for dispatching events or further processing.
     *
     * @param object $tourBooking The tour booking instance.
     * @param object $request The request object containing booking details.
     * @param object $info Additional information about the tour package.
     * @param object $slotInfo Slot information including booked seats.
     * @param array $touristGroups List of tourist groups for the booking.
     * @param int $totalTourists Total number of tourists in the booking.
     * @param bool $isCustomCharge Flag indicating if a custom charge is applied.
     * @param float $customChargeAmount The amount of the custom charge (if any).
     * @param bool $isCashFees Flag indicating if additional cash fees apply.
     * @param float $tt The total amount including cash fees.
     * @return array The formatted booking data array.
     */
    public static function prepareBookingData(
        $tourBooking,
        $request,
        $info,
        $slotInfo,
        $touristGroups,
        $totalTourists,
        $isCustomCharge,
        $customChargeAmount,
        $isCashFees,
        $tt,
        $totalInfants = 0
    ) {
        // Extract subtotal and payment method from the request
        $subTotal = $request->subtt;
        $paymentMethod = $request->payment_method;

        /**
         * Calculate the paid amount:
         * - If "Pay Later" is selected, the paid amount is empty.
         * - If a custom charge is applied, deduct it from the subtotal.
         * - Otherwise, the full subtotal is considered as paid.
         */
        $paidAmount = ($paymentMethod == "Pay Later")
            ? ''
            : (($isCustomCharge && $customChargeAmount > 0) ? ($subTotal - $customChargeAmount) : $subTotal);

        /**
         * Determine the total amount for the booking:
         * - If payment is via Card or includes Cash Fees, use the `tt` value.
         * - If a custom charge is applied, deduct it from the subtotal.
         * - Otherwise, use the full subtotal.
         */
        $totalAmount = ($paymentMethod == "Card" || ($isCashFees && $paymentMethod == "Cash"))
            ? $tt
            : (($isCustomCharge && $customChargeAmount > 0) ? ($subTotal - $customChargeAmount) : $subTotal);

        // Construct the booking data array
        return [
            'id' => $tourBooking->id,
            'prefixedId' => bookingPrefix($tourBooking->id),
            'cust_name' => $tourBooking->name,
            'cust_id' => $tourBooking->customer_id,
            'pkg_id' => $info->tour_package_id,
            'tenant_id' => $info->tenant_id,
            'pkg_name' => $info->package_name,
            'paid' => $paidAmount,
            'total' => $totalAmount,
            'tour_slot_id' => $request->slot_time,
            'tour_d_t' => "{$info->date} {$info->time_from}",
            'booking_d_t' => $tourBooking->created_at,
            't_groups' => implode(' | ', $touristGroups),
            'tour_operator_id' => $info->tour_operator_id,
            'tourists' => $totalTourists,
            'infants' => $totalInfants,
            'is_affiliate' => $request->affiliatecust != 0 ? 'Yes' : 'No',
            'notif_for' => 'admin',
            'date' => $info->date,
            'time_from' => $info->time_from,
            'time_to' => $info->time_to,
            'package_has_slots' => $info->package_has_slots,
            'seats' => $info->seats,
            'payment_method' => $paymentMethod,
            'booked_seats' => $slotInfo->booked_seats,
            'broadcaster_user_id' => Auth::id(),
            'env' => Config::get('app.env'),
            'invoiceStatus' => 0,
            'BookingTotal' => ($paymentMethod == "Pay Later") ? 0 : $tourBooking->BookingTotal,
            'RefundAmount' => 0,
            'payment_total' => ($paymentMethod == "Pay Later") ? 0 : $tourBooking->BookingTotal,
            'paid_split' => !empty($request->split_value) ? $request->split_value : null,
            'domain' => url('/'),
            'background_colour_class' => self::determineBackgroundColor($tourBooking, $request),
        ];
    }

    /**
     * Determine background color for booking notification.
     * 
     * @param object $tourBooking The booking record
     * @param object $request The request object
     * @return string The background color class
     */
    private static function determineBackgroundColor($tourBooking, $request): string
    {
        // Check if it's a soft booking (reserved for affiliate)
        if ($tourBooking->is_reserved_for_affiliate) {
            return 'affiliate-soft bg-[#E4FFE6] hover:bg-[#E4FFF6]';
        }
        
        // Check if an affiliate customer was selected (affiliatecust != 0)
        if ($request->affiliatecust != 0) {
            return 'affiliate-catch bg-[#ff03] hover:bg-[#ff05]';
        }
        
        // Default to normal booking
        return 'normal-booking bg-[#f4feff] hover:bg-[#e4eeff]';
    }

    /**
     * Prepare structured booking data for further processing.
     *
     * @param object $tourBooking The tour booking instance.
     * @param object $request The request object containing booking details.
     * @param object $info Additional information about the tour package.
     * @param int $totalTourists Total number of tourists in the booking.
     * @param float $tt Total cost including additional fees.
     * @param float $discountAmt Discount amount applied to the booking.
     * @return array The structured booking data.
     */
    public static function prepareBookingDetails(
        $tourBooking,
        $request,
        $info,
        $totalTourists,
        $tt,
        $discountAmt
    ) {
        return [
            'total' => $tt, // Total amount including fees
            'discount2_value' => $discountAmt, // Applied discount
            'customer_id' => $tourBooking->customer_id, // Customer ID
            'customer_name' => $tourBooking->name, // Customer name
            'customer_email' => $tourBooking->email, // Customer email
            'customer_phone' => $tourBooking->phone_number, // Customer phone number
            'tourists' => $totalTourists, // Number of tourists
            'booking_id' => $tourBooking->id, // Unique booking ID
            'tour_package' => $info->package_name, // Name of the tour package
            'date' => $info->date, // Tour date
            'time_from' => $info->time_from, // Start time of the tour
            'subtotal' => $request->subtt, // Subtotal before fees
            'serviceCommission' => $request->fee, // Service commission fee
            'slot_time' => $request->slot_time, // Selected tour slot time
            'tour_operator_id' => $info->tour_operator_id, // Assigned tour operator
            'booked_by_staff' => $request->booked_by_staff, // Whether booked by staff
            'split_value' => $request->split_value, // Amount for split payments
            'api' => $request->api, // API call flag
            'splitCount' => $request->dividedBy, // Number of split payments
        ];
    }
    /**
     * Generate Stripe Metadata
     *
     * @param array $data
     * @param string $fromMetadata
     * @return array
     */
    public static function generateMetadata(array $data, string $fromMetadata): array
    {
        return [
            'description' => sprintf(
                "Booking Id: %s\nTour Package: %s\nTour Date: %s\nTour Time: %s\nTourists: %s\nEmail: %s\nFrom: %s",
                $data['booking_id'],
                $data['tour_package'],
                $data['date'],
                $data['time_from'],
                $data['tourists'],
                $data['customer_email'],
                $fromMetadata
            ),
            'metadata' => [
                'booking_id'      => $data['booking_id'],
                'tour_package'    => $data['tour_package'],
                'tour_date'       => $data['date'],
                'tour_time'       => $data['time_from'],
                'tourists'        => $data['tourists'],
                'amount' => $data['total'],
                'platform_fee' => $data['serviceCommission'],
                'partner_fee' => $data['partnerFees'],
                'payout_amount' => $data['subtotal'] - $data['partnerFees'],
                'split'           => !empty($data['split_value']) ? 1 : 0,
                'from'            => $fromMetadata,
                'tenant_id'       => tenant()->id,
                // 'app' => Constants::STATUS_ONE
            ]
        ];
    }
    /**
     * Calculates the changes in tourist counts for a booking in change people process.
     *
     * @param object $request Request object containing tourist data.
     * @param object $tourBooking Current tour booking data.
     * @param string $isGroupBooking Indicator if the booking is a group booking.
     * @param array $rateGroupWiseTourists Current rate group-wise tourist counts.
     * @return array Contains added and deleted people counts and rate group-wise data.
     */
    public static function calculateChangedTouristsData($request, $tourBooking, $isGroupBooking, $rateGroupWiseTourists, $rateGroupWiseIsInfantRate = array()): array
    {
        $addedPeopleCount = 0;
        $deletedPeopleCount = 0;
        $addedInfantsCount = 0;
        $deletedInfantsCount = 0;
        $rateGroupWiseAddedPeople = array();
        $rateGroupWiseDeletedPeople = array();
        $rateGroupWiseAddedPeopleArray = array();
        $rateGroupWiseDeletedPeopleArray = array();
        $rateGrpIds = [];
        $permitfeeAr = [];
        // Affiliates cannot set the permit fee: force the rate's value (server source of truth).
        // Tour Operators/Admins may legitimately waive the fee, so keep their posted value.
        $isAffiliate = (userType() === 'Affiliate');
        $ratePermitFeeMap = DB::table('tour_package_rates')
            ->whereIn('id', $request->rate_group ?? [])
            ->pluck('permit_fee', 'id');
        for ($a = 0, $ac = count($request->tourists); $a < $ac; $a++) {
            $rateGroupWiseAddedPeople[$a] = 0;
            $rateGroupWiseDeletedPeople[$a] = 0;
            if ($request->tourists[$a]) {
                $rateGrpIds[] = $request->rate_group[$a];
                if ($isGroupBooking != '1') {
                    $permitfeeAr[$request->rate_group[$a]] = $isAffiliate
                        ? (float) ($ratePermitFeeMap[$request->rate_group[$a]] ?? 0)
                        : ($request->permit_fee[$a] ?? 0);
                }
            } else if ($request->tourists[$a] == 0 && isset($rateGroupWiseTourists[$request->rate_group[$a]]) && $rateGroupWiseTourists[$request->rate_group[$a]] > 0) {
                $rateGrpIds[] = $request->rate_group[$a];
                if ($isGroupBooking != '1') {
                    $permitfeeAr[$request->rate_group[$a]] = $isAffiliate
                        ? (float) ($ratePermitFeeMap[$request->rate_group[$a]] ?? 0)
                        : ($request->permit_fee[$a] ?? 0);
                }
            }
            if ($isGroupBooking == '1') {
                if ($tourBooking->tourists < $request->tourists[$a]) {
                    $addedPeopleCount += $request->tourists[$a] - $tourBooking->tourists;
                    $rateGroupWiseAddedPeople[$a] = (int)$request->tourists[$a] - $tourBooking->tourists;
                    $rateGroupWiseAddedPeopleArray[$request->rate_group[$a]] = $rateGroupWiseAddedPeople[$a];
                } else if ($tourBooking->tourists > $request->tourists[$a]) {
                    $deletedPeopleCount += $tourBooking->tourists - $request->tourists[$a];
                    $rateGroupWiseDeletedPeople[$a] = (int)$tourBooking->tourists - $request->tourists[$a];
                    $rateGroupWiseDeletedPeopleArray[$request->rate_group[$a]] = $rateGroupWiseDeletedPeople[$a];
                }
            } else {
                if (isset($request->rate_group[$a]) && isset($rateGroupWiseTourists[$request->rate_group[$a]]) && $request->tourists[$a] > $rateGroupWiseTourists[$request->rate_group[$a]]) {
                    $addedPeopleCount += $request->tourists[$a] - $rateGroupWiseTourists[$request->rate_group[$a]];
                    $rateGroupWiseAddedPeople[$a] = (int)$request->tourists[$a] - $rateGroupWiseTourists[$request->rate_group[$a]];
                    $rateGroupWiseAddedPeopleArray[$request->rate_group[$a]] = $rateGroupWiseAddedPeople[$a];
                    if(isset($rateGroupWiseIsInfantRate[$request->rate_group[$a]]) && $rateGroupWiseIsInfantRate[$request->rate_group[$a]] == 1){
                        $addedInfantsCount += $request->tourists[$a] - $rateGroupWiseTourists[$request->rate_group[$a]];
                    }

                }
                if (isset($request->rate_group[$a]) && isset($rateGroupWiseTourists[$request->rate_group[$a]]) && $request->tourists[$a] < $rateGroupWiseTourists[$request->rate_group[$a]]) {
                    $deletedPeopleCount += $rateGroupWiseTourists[$request->rate_group[$a]] - $request->tourists[$a];
                    $rateGroupWiseDeletedPeople[$a] = (int)$rateGroupWiseTourists[$request->rate_group[$a]] - $request->tourists[$a];
                    $rateGroupWiseDeletedPeopleArray[$request->rate_group[$a]] = $rateGroupWiseDeletedPeople[$a];
                    if(isset($rateGroupWiseIsInfantRate[$request->rate_group[$a]]) && $rateGroupWiseIsInfantRate[$request->rate_group[$a]] == 1){
                        $deletedInfantsCount += $rateGroupWiseTourists[$request->rate_group[$a]] - $request->tourists[$a];
                    }
                }
                if (isset($request->rate_group[$a]) && !isset($rateGroupWiseTourists[$request->rate_group[$a]]) && $request->tourists[$a] > 0) {
                    $addedPeopleCount += $request->tourists[$a];
                    $rateGroupWiseAddedPeople[$a] = (int)$request->tourists[$a];
                    $rateGroupWiseAddedPeopleArray[$request->rate_group[$a]] = (int)$rateGroupWiseAddedPeople[$a];
                    if(!isset($rateGroupWiseIsInfantRate[$request->rate_group[$a]])){
                        $rateGroupInfo = DB::table('tour_package_rates')->where('id', $request->rate_group[$a])->first();
                        if($rateGroupInfo && $rateGroupInfo->is_infant_rate == 1){  
                            $addedInfantsCount += $request->tourists[$a];
                        }
                    }
                }
            }
        }

        return [
            'rateGrpIds' => $rateGrpIds,
            'permitfeeAr' => $permitfeeAr,
            'addedPeopleCount' => $addedPeopleCount,
            'deletedPeopleCount' => $deletedPeopleCount,
            'addedInfantsCount' => $addedInfantsCount,
            'deletedInfantsCount' => $deletedInfantsCount,
            'rateGroupWiseAddedPeople' => $rateGroupWiseAddedPeople,
            'rateGroupWiseDeletedPeople' => $rateGroupWiseDeletedPeople,
            'rateGroupWiseAddedPeopleArray' => $rateGroupWiseAddedPeopleArray,
            'rateGroupWiseDeletedPeopleArray' => $rateGroupWiseDeletedPeopleArray
        ];
    }
    /**
     * Generates the appropriate text description for the number of people involved in the transaction.
     *
     * @param string $isGroupBooking Indicates if the booking is a group booking.
     * @param array $tbltrans The transaction data array.
     * @return string The formatted text for added/deleted people.
     */
    public static function getPeopleText($isGroupBooking, $tbltrans)
    {
        return ($isGroupBooking === '1')
            ? 'Person(s) ' . $tbltrans['tourists']
            : $tbltrans['rate_for'] . ' person ' . ($tbltrans['is_infant_rate'] == 1 ? $tbltrans['infants'] : $tbltrans['tourists']);
    }
    /**
     * Prepares the transaction update array with calculations for tourists, fees, and totals.
     *
     * @param array $tbltrans The transaction data array.
     * @param object $tbTransactexist Existing transaction data object.
     * @param string $isGroupBooking Indicates if the booking is a group booking.
     * @param string $userType The type of user performing the update.
     * @return array The structured transaction update data.
     */
    public static function getTransactionUpdatesChangePeople($tbltrans, $tbTransactexist, $isGroupBooking, $userType)
    {
        return [
            'tourists' => DB::raw(
                ($tbltrans['added'] ? 'tourists+' : 'tourists-') . $tbltrans['tourists']
            ),
            'infants' => DB::raw(
                ($tbltrans['added'] ? 'infants+' : 'infants-') . $tbltrans['infants']
            ),
            'permit_fee' => $tbltrans['permit_fee'] != 0
                ? $tbltrans['permit_fee']
                : $tbTransactexist->permit_fee,
            'total' => ($isGroupBooking == '1')
                ? $tbltrans['total']
                : DB::raw(
                    ($tbltrans['added'] ? 'total+' : 'total-') . $tbltrans['total']
                ),
            'updated_by' => Auth::id(),
            'updated_by_user_type' => $userType,
            'updated_at' => mysqlDT(),
        ];
    }
    /**
     * Prepares the transaction update array with calculations for tourists, fees, and totals in change people for group booking.
     *
     * @param array $tbltrans The transaction data array.
     * @param object $tbTransactexist Existing transaction data object.
     * @param string $newGroupRateId Indicates new rategroup id for group booking.
     * @param string $userType The type of user performing the update.
     * @return array The structured transaction update data.
     */
    public static function getTransactionUpdatesChangePeopleGroupBooking($tbltrans, $newGroupRateId, $userType)
    {
        return [
            'rate' => $tbltrans['rate'],
            'additional_charge' => $tbltrans['additional_charge'],
            'tour_package_rate_id' => $newGroupRateId,
            'tourists' => DB::raw(
                ($tbltrans['added'] ? 'tourists+' : 'tourists-') . $tbltrans['tourists']
            ),
            'total' => $tbltrans['total'],
            'updated_by' => Auth::id(),
            'updated_by_user_type' => $userType,
            'updated_at' => mysqlDT(),
        ];
    }
    /**
     * Updates the check-in status based on added or deleted tourists for change people.
     *
     * @param array $tbltrans The transaction data array.
     * @param object $tbTransactexist Existing transaction data object.
     * @return string The updated check-in status JSON string.
     */
    public static function updateCheckInStatusChangePeople($tbltrans, $tbTransactexist)
    {
        $statusInfo = json_decode($tbTransactexist->check_in_status) ?? [];
        
        if($tbltrans['is_infant_rate'] == 1) {
            $transactTourists = $tbltrans['infants'] + ($tbltrans['added'] ? $tbTransactexist->infants : -$tbltrans['infants']);
        } else {
            $transactTourists = $tbltrans['tourists'] + ($tbltrans['added'] ? $tbTransactexist->tourists : -$tbltrans['tourists']);
        }
       
        // Ensure status slots are created or removed accordingly
        for ($b = 0; $b < $transactTourists; $b++) {
            if (!isset($statusInfo[$b])) {
                $statusInfo[$b] = (object)['status' => 'N'];
            }
        }
        if ($tbltrans['deleted'] === true) {
            $tbTransactexistTourists = ($tbltrans['is_infant_rate'] == 1) ? $tbTransactexist->infants : $tbTransactexist->tourists;
            for ($c = $transactTourists; $c < $tbTransactexistTourists; $c++) {
                unset($statusInfo[$c]);
            }
        }

        return !empty($statusInfo) ? json_encode(array_values($statusInfo)) : '';
    }
    /**
     * Updates the redeem ticket info based on added or deleted tourists for change people.
     *
     * @param array $tbltrans The transaction data array.
     * @param object $tbTransactexist Existing transaction data object.
     * @return string The updated redeem ticket JSON string.
     */
    public static function updateRedeemTicketInfoChangePeople($tbltrans, $tbTransactexist)
    {
        $redeemTicketInfo = json_decode($tbTransactexist->redeem_ticket, true) ?? [];
        if($tbltrans['is_infant_rate'] == 1) {
            $transactTourists = $tbltrans['infants'] + ($tbltrans['added'] ? $tbTransactexist->infants : -$tbltrans['infants']);
        } else {
            $transactTourists = $tbltrans['tourists'] + ($tbltrans['added'] ? $tbTransactexist->tourists : -$tbltrans['tourists']);
        }

        // Ensure redeem ticket data slots are created or removed accordingly
        for ($b = 0; $b < $transactTourists; $b++) {
            foreach ($redeemTicketInfo as $y => $ticket) {
                if (!isset($ticket['data'][$b])) {
                    $redeemTicketInfo[$y]['data'][$b] = [
                        'status' => 'Active',
                        'dateTime' => mysqlDT()
                    ];
                }
            }
        }

        if ($tbltrans['deleted'] === true) {
            $tbTransactexistTourists = ($tbltrans['is_infant_rate'] == 1) ? $tbTransactexist->infants : $tbTransactexist->tourists;
            for ($c = $transactTourists; $c < $tbTransactexistTourists; $c++) {
                foreach ($redeemTicketInfo as $y => $ticket) {
                    unset($redeemTicketInfo[$y]['data'][$c]);
                }
            }
        }

        return !empty($redeemTicketInfo) ? json_encode(array_values($redeemTicketInfo)) : '';
    }
    public static function getBookingDetails(int $tourBookingID): ?object
    {
        return DB::table('tour_bookings_master AS tbm')
            ->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
            ->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
            ->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
            ->join('cities AS ct', 'ct.id', '=', 't_o.city_id')
            ->join('states AS st', 'st.id', '=', 'ct.state_id')
            ->join('customers AS cus', 'cus.id', '=', 'tbm.customer_id')
            ->where('tbm.id', $tourBookingID)
            ->select([
                'tbm.id',
                'tst.time_from',
                'tbm.tour_slot_id',
                'tsd.date',
                'tst.time_slot_seats AS seats',
                'tp.tour_operator_id',
                'tsd.tour_package_id',
                'tbm.discount2_value',
                'tbm.discount2_percentage',
                'tbm.is_group_booking',
                'tbm.is_partner_booking',
                'tbm.is_reserved_for_affiliate',
                'tbm.viator_bookingRef as tripadvisor_id',
                'tbm.get_your_guide_reference_booking as get_your_guide_booking_id',
                'tbm.ota_reference_booking as ota_booking_id',
				'tbm.reference_booking',
                'tp.name AS package_name',
                'tp.important_notes',
                'tp.things_to_bring',
                'ct.state_id',
                'st.permit_fee',
                't_o.name AS tour_operator',
                't_o.email AS tour_op_email',
                't_o.phone_number AS tour_op_phone',
                't_o.website AS tour_op_website',
                't_o.logo AS tour_op_logo',
                't_o.cancellation_policy',
                'tp.service_commission_percentage',
                'cus.id as customer_id',
                'cus.affiliate_processing_percentage',
                'cus.stripe_affiliate_percentage',
                'tp.stripe_commission_percentage',
                't_o.statement_descriptor',
                't_o.card_reader_prefix',
				'cus.is_affiliate',
                'cus.name AS customername',
                'cus.email AS customeremail',
                'cus.phone_number AS customerphone',
                'tp.email AS packageEmail',
                'tp.from_name AS packageFromName',
                'tp.from_email AS packageFromEmail',
                'tp.api_key AS packageApiKey',
                'tbm.tourists',
                'tbm.infants',
                't_o.tenant_id',
                't_o.stripe_destination_id',
                'tp.stripe_destination_id as partnerDestinationId',
                DB::raw("(SELECT SUM(tbm.tourists)
                    FROM tour_bookings_master AS tbm
                    WHERE tbm.tour_slot_id = tst.id
                        AND tbm.status != 'Cancelled'
                    GROUP BY tbm.tour_slot_id) AS booked_seats")
            ])
            ->first();
    }
    /**
     * Calculate the change amount for cash payments.
     *
     * @param string $paymentType The type of payment (e.g., 'Cash').
     * @param object $request The request object containing payment data.
     * @param array $jsonData The JSON data including the total amount.
     *
     * @return float The calculated change amount.
     */
    public static function calculateChangeAmount($paymentType, $request, $jsonData, $platformFees = 0): float
    {
        $changeAmount = 0;

        // Check if the payment type is Cash
        if ($paymentType === 'Cash') {
            // Get the total payment amount from JSON data
            $paymentAmountCash = $jsonData['amount'] - $platformFees;
            // Get the cash paid amount from the request if it exists and is not empty
            $cashPaidAmount = $request->has('cash_paid_amount') && $request->cash_paid_amount !== ''
                ? $request->cash_paid_amount
                : '';

            // Calculate the change amount if cash paid is greater than the total amount
            if ($cashPaidAmount !== '' && $cashPaidAmount > 0 && $cashPaidAmount > $paymentAmountCash) {
                $changeAmount = round($cashPaidAmount - $paymentAmountCash, 2);
            }
        }

        // Return the final change amount
        return $changeAmount;
    }
    /**
     * Prepare and update the JSON data structure for a payment history record.
     *
     * @param object $history The payment history object.
     * @param float $total The total amount.
     * @param string $paymentType The type of payment.
     * @param string|null $crd The card number (optional).
     * @param string|null $cardType The type of card (optional).
     * @param string $createdByWithName The creator's name.
     *
     * @return array The updated JSON data.
     */
    public static function updatePaymentHistoryData(
        object $history,
        float $total,
        string $paymentType,
        ?string $crd,
        ?string $cardType,
        string $createdByWithName
    ): array {
        $jsonData = json_decode($history->details, true);
        $jsonData['amount'] = $total;
        $jsonData['payment_type'] = $paymentType;
        $jsonData['card_number'] = $crd ?? null;
        $jsonData['card_type'] = $cardType ?? null;
        $jsonData['created_by_with_name'] = $createdByWithName;
        $jsonData['payment_method'] = 'Full Payment';

        return $jsonData;
    }

    /**
     * Get booking details.
     *
     * @param string $id.
     *
     * @return object data.
     */
    public static function getDetails($id)
    {
        $booking = DB::table('tour_bookings_master AS tbm')
            ->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
            ->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
            ->where('tbm.id', $id)
            ->first([
                'tbm.id',
                'tbm.name',
                'tbm.email',
                'tbm.phone_number',
                't_o.id as tour_operator_id',
                't_o.stripe_destination_id',
                't_o.statement_descriptor'
            ]);

        return $booking;
    }

    // Helper function to set min/max pax details
    public static function setMinMaxPaxDetails(&$tourPackagesMinMaxPax, $tourPackageId, $cartPackage, $rateDetails)
    {
        $tourPackagesMinMaxPax[$tourPackageId] = [
            'tourPackageName' => $cartPackage['tourPackageName'],
            'rateFor' => $rateDetails->rate_for,
            'minPaxAllowed' => $rateDetails->min_pax_allowed,
            'maxPaxAllowed' => $rateDetails->max_pax_allowed,
        ];
        return false;  // Ensures $bookingAllowed gets set to false
    }

    /**
     * Get active affiliates based on user type.
     *
     * @param int $tourOperatorId
     * @param string $userType
     * @return \Illuminate\Support\Collection
     */
    public static function getActiveAffiliates(int $tourOperatorId, string $userType)
    {
        return Customer::where('tour_operator_id', $tourOperatorId)
            ->where('status', 'Active')
            ->where('is_affiliate', true)
            ->when($userType == 'Affiliate', function ($query) {
                $query->where('id', Auth::id());
            })
            ->orderBy('name')
            ->get(['id', 'name']);
    }
    /**
     * Cancel a tour booking and update tour_booking_master table.
     *
     * @param int $bookingId  The booking instance containing the booking ID.
     * @param string $userType The type of user performing the update.
     * @return bool            Returns true if the update was successful, otherwise false.
     */
    public static function cancelBooking(int $bookingId, string $userType): bool
    {
        return TourBooking::where('id', $bookingId)
            ->update([
                'status' => 'Cancelled',
                'updated_by' => Auth::id(),
                'updated_by_user_type' => $userType,
            ]) > 0; // Returns true if at least one row was updated
    }
}
