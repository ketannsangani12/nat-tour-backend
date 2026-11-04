<?php

namespace App\Repositories\Report;

use App\Models\Invoice;
use App\Repositories\Repository;
use App\Services\Booking\RemainingPayment\UnpaidPaxService;
use App\Utils\BookingUtils;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Exceptions\Core\GeneralCoreException;
use App\Models\TourBooking;
use App\Models\TourBookingHistory;
use Exception;
use App\Models\TourOperator;
use App\Models\TourPackage;
use Illuminate\Http\Request;
use stdClass;
use Yajra\DataTables\DataTables;
use App\Models\Transaction;
use App\Repositories\Invoice\InvoiceRepository;
use App\Utils\DateUtils;
use App\Utils\StrUtils;
use App\Utils\ReportUtils;
use App\Utils\BokunUtils;
use App\Jobs\UpdateAffiliateSeatsTable;
use App\Models\Admin;
use App\Models\Staff;
use App\Models\Customer;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\Services\Booking\BookingService;
use Illuminate\Support\Carbon;
use App\Models\AddonField;

class ReportRepository extends Repository
{
    /**
     * Create new booking
     *
     * @param object $request
     * @return mixed
     */
    public function cashcollection($request)
    {
        try {
            $userType = userType();
            if ($userType == "Tour Operator") {
                $tourOperatorId = auth()->user()->tour_operator_id;
            } else {
                $tourOperatorId = $request->tourOperator;
            }

            $via = "Cash";

            /* START - PAYMENT / REFUND COLLECTION QUERY FOR CASH */
            $paymentSql = 'SELECT
                `tbm`.`id` as `tour_booking_id`,
                `tbh`.`operation`,
                `tbm`.`status` as `status`,
                `tbm`.`comments`,
                `tbm`.`created_by` as `createdBy`,
                `tbm`.`created_by_user_type` as `TourUserType`,
                json_unquote(json_extract(`tbh`.`details`, "$.created_by_with_name")) as created_by_with_name,
                CASE
                    WHEN `tbh`.`operation` = "Payment" THEN `tbh`.`ticket_cost`
                    ELSE 0
                END as "completed_amount",
                CASE
                    WHEN `tbh`.`operation` = "Payment" THEN `tbh`.`platform_fees`
                    ELSE 0
                END as "paid_cash_fees",
                CASE
                    WHEN `tbh`.`operation` = "Refund" THEN `tbh`.`ticket_cost`
                    WHEN `tbh`.`operation` = "Cancelled" THEN `tbh`.`ticket_cost`
                    ELSE 0
                END as "refund_amount",
                CASE
                    WHEN `tbh`.`operation` = "Refund" THEN `tbh`.`platform_fees`
                    WHEN `tbh`.`operation` = "Cancelled" THEN `tbh`.`platform_fees`
                    ELSE 0
                END as "refund_cash_fees",
                ifnull(`tbh`.`payment_type`, 0) as `via`,
                ifnull(`tbh`.`payment_method`, 0) as `method`,
                DATE_FORMAT(`tbm`.`created_at`, "%Y-%m-%d") as `created_at`,
                `tbm`.`name` as `customer`,
                `tbm`.`email`,
                `tbm`.`phone_number`,
                `tst`.`time_from`,
                `tst`.`time_to`,
                `tsd`.`date`,
                `tbm`.`tourists`,
                `tbm`.`infants`
            from
                `tour_booking_history` as `tbh`
                inner join `tour_bookings_master` as `tbm` on `tbm`.`id` = `tbh`.`tour_booking_id`
                inner join `tour_slot_times` as `tst` on `tst`.`id` = `tbm`.`tour_slot_id`
                inner join `tour_slot_dates` as `tsd` on `tsd`.`id` = `tst`.`tour_slot_id`
                inner join `tour_packages` as `tp` on `tp`.`id` = `tbm`.`tour_package_id`';

            $paymentSql .= ' where `tbh`.`payment_type` = "' . $via . '" ';

            $paymentSql .= ' and `tbh`.`operation` in ("Payment", "Refund", "Cancelled") ';

            if (isset($request->viewDate)) {
                $viewDate = ($request->viewDate == 'purchasedate') ? '`tbh`.`operation_at`' : '`tsd`.`date`';
                $paymentSql .= ' and ' . $viewDate . ' between "' . $request->dateFrom . ' 00:00:00" and "' . $request->dateTo . ' 23:59:59"';
            } else {
                $paymentSql .= ' and `tbh`.`operation_at` between "' . $request->dateFrom . ' 00:00:00" and "' . $request->dateTo . ' 23:59:59"';
            }
            // $paymentSql .= ' and `tbh`.`operation_at` between "'.$request->dateFrom.' 00:00:00" and "'.$request->dateTo.' 23:59:59"';

            if ($request->tourPackage) {
                $paymentSql .= ' and `tp`.`id` = "' . $request->tourPackage . '" ';
            }

            $paymentSql .= ' and `tp`.`tour_operator_id` = "' . $tourOperatorId . '" ';

            $cashData = DB::select($paymentSql);
            /* END - PAYMENT / REFUND COLLECTION QUERY FOR CASH */

            $paymentData = array();

            foreach ($cashData as $row) {
                if (isset($paymentData[$row->created_by_with_name][$row->tour_booking_id])) {
                    $paymentData[$row->created_by_with_name][$row->tour_booking_id]->completed_amount += $row->completed_amount;
                    $paymentData[$row->created_by_with_name][$row->tour_booking_id]->refund_amount += $row->refund_amount;
                    $paymentData[$row->created_by_with_name][$row->tour_booking_id]->paid_cash_fees += $row->paid_cash_fees;
                    $paymentData[$row->created_by_with_name][$row->tour_booking_id]->refund_cash_fees += $row->refund_cash_fees;
                } else {
                    $paymentData[$row->created_by_with_name][$row->tour_booking_id] = $row;
                }
            }
            $startYear = ($request->dateFrom != '') ? date('Y', strtotime($request->dateFrom)) : '';

            $totalPayment = 0;
            $totalFareharbor = 0;
            $totalRefund = 0;
            $totalPaidCashFees = 0;
            $totalRefundCashFees = 0;
            $array = [];

            foreach ($paymentData as $key => $cash) {
                if ($request->api == 1) {
                    $total_amount = 0;
                    $total_refund = 0;
                    $total_payment = 0;
                    $total_paid_cash_fees = 0;
                    $total_refund_cash_fees = 0;
                    $Tour_Operator_Staff_Name = '';
                    foreach ($cash as $k => $cashIn) {
                        $cash[$k]->completed_amount = (string)$cashIn->completed_amount;
                        $cash[$k]->refund_amount = (string)$cashIn->refund_amount;
                        $cash[$k]->paid_cash_fees = (string)$cashIn->paid_cash_fees;
                        $cash[$k]->refund_cash_fees = (string)$cashIn->refund_cash_fees;
                        $total_amount += $cashIn->completed_amount;
                        $total_refund += $cashIn->refund_amount;
                        $total_paid_cash_fees += $cashIn->paid_cash_fees;
                        $total_refund_cash_fees += $cashIn->refund_cash_fees;
                        $Tour_Operator_Staff_Name = $cashIn->created_by_with_name;
                    }
                    $total_payment = $total_amount - $total_refund;
                    $array['details'][] = [
                        'Tour_Operator_Staff_Name' =>  $Tour_Operator_Staff_Name,
                        'Total_Amount' => $total_amount,
                        'Total_Refund' => $total_refund,
                        'Total_Payment' => $total_payment,
                        'total_paid_cash_fees' => $total_paid_cash_fees,
                        'total_refund_cash_fees' => $total_refund_cash_fees,
                        'data' => array_values($cash)
                    ];
                } else {
                    $array[] = [
                        'name' => $key,
                        'data' => $cash
                    ];
                }

                foreach ($cash as $value) {
                    $totalPayment += (float)$value->completed_amount;
                    if (isset($value->comments) && Str::contains($value->comments, "#") && $startYear < 2023) {
                        $totalFareharbor += (float)$value->completed_amount;
                    }
                    $totalRefund += (float)$value->refund_amount;
                    $totalPaidCashFees += (float)$value->paid_cash_fees;
                    $totalRefundCashFees += (float)$value->refund_cash_fees;
                }
            }

            $result = $array + [
                'alltotalamount' => $totalPayment - $totalFareharbor,
                'allfarharbouramount' => $totalFareharbor,
                'allrefundamount' => $totalRefund,
                'allpaymentamount' => ($totalPayment + $totalPaidCashFees) - ($totalRefund + $totalRefundCashFees),
                'allpaidcashfees' => $totalPaidCashFees,
                'allrefundcashfees' => $totalRefundCashFees,
            ];

            if ($request->export) {
                return $this->exportCashCollectionReport($request, $result, $tourOperatorId, $request->tourPackage);
            }

            return $result;
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Export
     * Cash Collction Report
     *
     * @param object $request
     * @param array $response
     */
    public function exportCashCollectionReport($request, $response, $tourOperatorId = 0, $tourPackageId = 0)
    {
        $isCashFees = cashFees();
        $fileName = 'cash_collection_report_' . $request->get('dateFrom') . '_' . $request->get('dateTo') . '.csv';

        $headers = csvHeaders($fileName);
        $tourOperatorName = $this->GetTourOperatorName($tourOperatorId, $tourPackageId);

        $columnsHeader = csvColumns('cash_collection_report_columnsHeader');
        $columns = csvColumns('cash_collection_report_columns');
        $checkYear = date("Y", strtotime($request->dateTo));
        if ($checkYear != "2022") {
            $columnsOverallAmount = csvColumns('cash_card_collection_report_columnsHeader_overall');
        } else {
            $columnsOverallAmount = csvColumns('cash_card_collection_report_columnsHeader_overall_FareHarbor');
        }

        // Execute only for tenant with cash fees enabled
        if ($isCashFees == '1') {
            $columnsHeader = csvColumns('cash_fees_collection_report_columns_header');
            $columns = csvColumns('cash_fees_collection_report_columns');
            $columnsOverallAmount = csvColumns('cash_fees_collection_report_columns_header_overall');
        }

        $callback = function () use ($request, $response, $columnsHeader, $columns, $columnsOverallAmount, $tourOperatorName, $isCashFees) {

            $file = fopen('php://output', 'w');
            $blankLine = '';
            if (!empty($tourOperatorName)) {
                if ($tourOperatorName['tourOperatorName']) {
                    fputcsv($file, array('Tour Operator:', $tourOperatorName['tourOperatorName']));
                }
                if ($tourOperatorName['tourPackageName']) {
                    fputcsv($file, array('Tour Package:', $tourOperatorName['tourPackageName']));
                }
                fputcsv($file, array($blankLine));
            }
            fputcsv($file, $columnsOverallAmount);

            $dateRange = date("m-d-Y", strtotime($request->dateFrom)) . ' - ' . date("m-d-Y", strtotime($request->dateTo));
            $createdDate = date("m-d-Y");
            $viewBy = ($request->viewDate == "purchasedate") ? "Purchase Date" : "Tour Date";

            $totalPaymentAmount = sprintf('%.2f', $response['allpaymentamount']);
            $totalRefundAmount = sprintf('%.2f', $response['allrefundamount']);
            $totalAmount = sprintf('%.2f', $response['alltotalamount']);
            $totalFarharborAmount = sprintf('%.2f', $response['allfarharbouramount']);
            $totalPaidCashFees = sprintf('%.2f', $response['allpaidcashfees']);
            $totalRefundCashFees = sprintf('%.2f', $response['allrefundcashfees']);
            $checkYear = date("Y", strtotime($request->dateTo));

            // Execute only for tenant with cash fees enabled
            if ($isCashFees == '1') {
                fputcsv($file, array($dateRange, $createdDate, $viewBy, $totalAmount, $totalPaidCashFees, $totalRefundAmount, $totalRefundCashFees, $totalPaymentAmount));
            } else {
                if ($checkYear != "2022") {
                    fputcsv($file, array($dateRange, $createdDate, $viewBy, $totalAmount, $totalRefundAmount, $totalPaymentAmount));
                } else {
                    fputcsv($file, array($dateRange, $createdDate, $viewBy, $totalAmount, $totalFarharborAmount, $totalRefundAmount, $totalPaymentAmount));
                }
            }

            fputcsv($file, array($blankLine));

            foreach ($response as $resultData) {

                if (isset($resultData['name'])) {

                    fputcsv($file, $columnsHeader);

                    $row['tourOperatorStaffName'] = (isset($resultData['name'])) ? $resultData['name'] : '';
                    $row['totalAmount'] = (isset($resultData['data'])) ? array_sum(array_column($resultData['data'], 'completed_amount')) : 0;
                    $row['totalRefund'] = (isset($resultData['data'])) ? array_sum(array_column($resultData['data'], 'refund_amount')) : 0;
                    $row['totalPaidFees'] = (isset($resultData['data'])) ? array_sum(array_column($resultData['data'], 'paid_cash_fees')) : 0;
                    $row['totalRefundFees'] = (isset($resultData['data'])) ? array_sum(array_column($resultData['data'], 'refund_cash_fees')) : 0;

                    // Execute only for tenant with cash fees enabled
                    if ($isCashFees == '1') {
                        $row['totalPayment'] = (isset($resultData['data'])) ? (array_sum(array_column($resultData['data'], 'completed_amount')) + array_sum(array_column($resultData['data'], 'paid_cash_fees'))) - (array_sum(array_column($resultData['data'], 'refund_amount')) + array_sum(array_column($resultData['data'], 'refund_cash_fees'))) : '';
                    } else {
                        $row['totalPayment'] = (isset($resultData['data'])) ? array_sum(array_column($resultData['data'], 'completed_amount')) - array_sum(array_column($resultData['data'], 'refund_amount')) : '';
                    }

                    // Execute only for tenant with cash fees enabled
                    if ($isCashFees == '1') {
                        fputcsv($file, array(
                            $row['tourOperatorStaffName'],
                            $row['totalAmount'],
                            $row['totalPaidFees'],
                            $row['totalRefund'],
                            $row['totalRefundFees'],
                            $row['totalPayment']
                        ));
                    } else {
                        fputcsv($file, array(
                            $row['tourOperatorStaffName'],
                            $row['totalAmount'],
                            $row['totalRefund'],
                            $row['totalPayment']
                        ));
                    }

                    fputcsv($file, $columns);

                    $arrayData = $resultData['data'] ?? [];

                    foreach ($arrayData as $eachData) {
                        $remainingAmount = 0;

                        // Execute only for tenant with cash fees enabled
                        if ($isCashFees == '1') {
                            $remainingAmount = ($eachData->completed_amount + $eachData->paid_cash_fees) - ($eachData->refund_amount + $eachData->refund_cash_fees);
                        } else {
                            $remainingAmount = $eachData->completed_amount - $eachData->refund_amount;
                        }

                        $row['Booking Id'] = (isset($eachData->tour_booking_id)) ? $eachData->tour_booking_id : '';
                        $row['Booking Date'] = (isset($eachData->date)) ? date("m-d-Y", strtotime($eachData->date)) . " @ " . date("g:i A", strtotime($eachData->time_from)) : '';
                        $row['Created Date'] = (isset($eachData->created_at)) ? date("m-d-Y", strtotime($eachData->created_at)) : '';
                        $row['Via'] = (isset($eachData->via)) ? $eachData->via : 'Cash';
                        $row['Amount'] = (isset($eachData->completed_amount)) ? $eachData->completed_amount : 0;
                        $row['Refund Amount'] = (isset($eachData->refund_amount)) ? $eachData->refund_amount : 0;
                        $row['Customer'] = (isset($eachData->customer)) ? $eachData->customer : '';
                        $row['Remaining Amount'] = number_format((float)$remainingAmount, 2, '.', '');
                        $row['Comments'] = (isset($eachData->comments)) ? $eachData->comments : '';
                        $row['Booking Status'] = (isset($eachData->status)) ? $eachData->status : '';
                        $row['Paid Fees'] = (isset($eachData->paid_cash_fees)) ? $eachData->paid_cash_fees : 0;
                        $row['Refund Fees'] = (isset($eachData->refund_cash_fees)) ? $eachData->refund_cash_fees : 0;

                        if ($isCashFees == '1') {
                            fputcsv($file, array(
                                $row['Booking Id'],
                                $row['Booking Date'],
                                $row['Created Date'],
                                $row['Via'],
                                $row['Amount'],
                                $row['Paid Fees'],
                                $row['Refund Amount'],
                                $row['Refund Fees'],
                                $row['Remaining Amount'],
                                $row['Customer'],
                                $row['Comments'],
                                $row['Booking Status']
                            ));
                        } else {
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
                    }

                    fputcsv($file, array(
                        $blankLine,
                        $blankLine,
                        $blankLine,
                        $blankLine,
                        $blankLine,
                        $blankLine,
                        $blankLine,
                        $blankLine,
                        $blankLine,
                        $blankLine
                    ));
                }
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Card Collection Report
     * In this method you can view the data in web page as well you can export in CSV file.
     *
     * @param Request $request
     */
    public function cardcollection($request)
    {
        try {
            $isCashFees = cashFees();
            if ($isCashFees == '1') {
                if ($request->has('type') && $request->type != '') {
                    $via = [$request->type];
                } else {
                    $via = ['Card'];
                }
            } else {
                $via = ['Card'];
            }

            $userType = userType();
            if ($userType == "Tour Operator") {
                $tourOperatorId = auth()->user()->tour_operator_id;
            } else {
                $tourOperatorId = $request->tourOperator;
            }

            $data = array(
                'dateFrom' => $request->dateFrom,
                'dateTo' => $request->dateTo,
                'report' => $request->selectedReport,
                'tourPackage' => $request->tourPackage,
                'viewDate' => $request->viewDate
            );

            $result = $this->cardCollectionTotalReport($via, $tourOperatorId, $data, 0);
            $farharbor = $this->cardCollectionTotalReport($via, $tourOperatorId, $data, 1);

            if ($request->export) {
                return $this->exportCardCollectionReport($request, $result, $farharbor);
            }

            return [
                $result,
                $farharbor
            ];
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Export
     * Card Collction Report
     *
     * @param object $request
     * @param array $response
     */
    public function exportCardCollectionReport($request, $response, $farharbor)
    {
        if ($request->has('type') && $request->type != '') {
            if ($request->type == 'Cash') {
                $fileName = 'cash_collection_report_' . $request->get('dateFrom') . '_' . $request->get('dateTo') . '.csv';
            } else {
                $fileName = 'card_collection_report_' . $request->get('dateFrom') . '_' . $request->get('dateTo') . '.csv';
            }
        } else {
            $fileName = 'card_collection_report_' . $request->get('dateFrom') . '_' . $request->get('dateTo') . '.csv';
        }
        $columns = csvColumns('card_collection_report_columns');
        $columnsOverallAmount = csvColumns('card_collection_report_columnsHeader_overall');
        $checkYear = date("Y", strtotime($request->dateTo));
        if ($checkYear == "2022") {
            $farharborColumnsOverallAmount = csvColumns('farharbor_card_collection_report_columns');
        } else {
            $farharborColumnsOverallAmount = null;
        }

        $headers = csvHeaders($fileName);
        $tourOperatorName = $this->GetTourOperatorName($request->tourOperator, $request->tourPackage);
        $callback = function () use ($request, $response, $farharbor, $columns, $columnsOverallAmount, $farharborColumnsOverallAmount, $tourOperatorName) {

            $file = fopen('php://output', 'w');
            if (!empty($tourOperatorName)) {
                $BlankLine = '';
                if ($tourOperatorName['tourOperatorName']) {
                    $tourOperatorNameRow = array('Tour Operator:', $tourOperatorName['tourOperatorName'], $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourOperatorNameRow);
                }
                if ($tourOperatorName['tourPackageName']) {
                    $tourPackageNameRow = array('Tour Package:', $tourOperatorName['tourPackageName'], $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourPackageNameRow);
                }
                fputcsv($file, array($BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine));
            }
            fputcsv($file, $columnsOverallAmount);

            $dateRange = date("m-d-Y", strtotime($request->dateFrom)) . ' - ' . date("m-d-Y", strtotime($request->dateTo));
            $createdDate = date("m-d-Y");
            $viewBy = ($request->viewDate == "purchasedate") ? "Purchase Date" : "Tour Date";

            $overallTicketTotal = sprintf('%.2f', $response['ticketcost']);
            $overallTicketRefundTotal = sprintf('%.2f', $response['ticketcostrefund']);
            $overallTicketNetTotal = sprintf('%.2f', $response['ticketnetamount']);

            $overallProcessingFeesTotal = sprintf('%.2f', $response['processingfee']);
            $overallProcessingFeesRefundTotal = sprintf('%.2f', $response['processingfeerefund']);
            $overallProcessingFeesNetTotal = sprintf('%.2f', $response['processingfeenetamount']);

            $overallPlatformFeesTotal = sprintf('%.2f', $response['platformfee']);
            $overallPlatformFeesRefundTotal = sprintf('%.2f', $response['platformfeerefund']);
            $overallPlatformFeesNetTotal = sprintf('%.2f', $response['platformfeenetamount']);

            $overallDiscountTotal = sprintf('%.2f', $response['discountamount']);
            $overallTotalAmount = sprintf('%.2f', $overallTicketTotal + $overallProcessingFeesTotal + $overallPlatformFeesTotal);
            $overallRefundTotalAmount = sprintf('%.2f', $response['refund_total']);
            $overallNetAmount = sprintf('%.2f', $response['ticketnetamount']);

            fputcsv($file, array($dateRange, $createdDate, $viewBy, $overallTicketTotal, $overallTicketRefundTotal, $overallTicketNetTotal, /*$overallProcessingFeesTotal, $overallProcessingFeesRefundTotal, $overallProcessingFeesNetTotal,*/ $overallPlatformFeesTotal, $overallPlatformFeesRefundTotal, $overallPlatformFeesNetTotal, $overallDiscountTotal, $overallTotalAmount, $overallRefundTotalAmount, $overallNetAmount));

            $blankLine = '';
            fputcsv($file, array($blankLine));

            fputcsv($file, $columns);

            foreach ($response['data'] as $eachData) {
                $row['Booking Id'] = (isset($eachData->tour_booking_id)) ? $eachData->tour_booking_id : '';
                $row['Booking Date'] = (isset($eachData->date)) ? date("m-d-Y", strtotime($eachData->date)) . " @ " . date("g:i A", strtotime($eachData->time_from)) : '';
                $row['Created Date'] = (isset($eachData->created_at)) ? date("m-d-Y", strtotime($eachData->created_at)) : '';
                $row['Customer Name'] = (isset($eachData->customer)) ? $eachData->customer : '';

                $row['Ticket Cost'] = (isset($eachData->payment_cost)) ? (float)number_format($eachData->payment_cost, 2, '.', '') : 0;
                $row['Ticket Refund'] = (isset($eachData->refund_cost)) ? (float)number_format($eachData->refund_cost, 2, '.', '') : 0;
                $row['Ticket Net'] = (isset($eachData->payment_cost) || isset($eachData->refund_cost)) ? (float)number_format(($eachData->payment_cost - $eachData->refund_cost), 2, '.', '') : 0;

                $row['Processing Fees'] = (isset($eachData->payment_processing_fees)) ? (float)number_format($eachData->payment_processing_fees, 2, '.', '') : 0;
                $row['Processing Fees Refund'] = (isset($eachData->refund_processing_fees)) ? (float)number_format($eachData->refund_processing_fees, 2, '.', '') : 0;
                $row['Processing Net'] = (isset($eachData->payment_processing_fees) || isset($eachData->refund_processing_fees)) ? (float)number_format(($eachData->payment_processing_fees - $eachData->refund_processing_fees), 2, '.', '') : 0;

                $row['Platform Fees'] = (isset($eachData->payment_platform_fees)) ? (float)number_format($eachData->payment_platform_fees, 2, '.', '') : 0;
                $row['Platform Fees Refund'] = (isset($eachData->refund_platform_fees)) ? (float)number_format($eachData->refund_platform_fees, 2, '.', '') : 0;
                $row['Platform Fees Net'] = (isset($eachData->payment_platform_fees) || isset($eachData->refund_platform_fees)) ? (float)number_format(($eachData->payment_platform_fees - $eachData->refund_platform_fees), 2, '.', '') : 0;

                $row['Discount'] = (isset($eachData->discount)) ? (float)number_format($eachData->discount, 2, '.', '') : 0;
                // $row['Payment Total'] = (isset($eachData->payment_cost) || isset($eachData->refund_cost) || isset($eachData->payment_processing_fees) || isset($eachData->refund_processing_fees) || isset($eachData->payment_platform_fees) || isset($eachData->refund_platform_fees) || isset($eachData->discount)) ? number_format(($eachData->payment_cost - $eachData->refund_cost) + ($eachData->payment_processing_fees - $eachData->refund_processing_fees) + ($eachData->payment_platform_fees - $eachData->refund_platform_fees) - $eachData->discount, 2) : '';

                $ticket = floatval(str_replace(',', '', $row['Ticket Cost']));

                $row['Payment Total'] =  (!empty($row['Ticket Cost'])) ? ($ticket + $row['Processing Fees'] + $row['Platform Fees']) : 0;
                $row['Refund Total'] = $row['Ticket Refund'] + $row['Processing Fees Refund'] + $row['Platform Fees Refund'];
                $row['Net Total'] = $row['Ticket Net'];

                fputcsv($file, array(
                    $row['Booking Id'],
                    $row['Booking Date'],
                    $row['Created Date'],
                    $row['Customer Name'],
                    $row['Ticket Cost'],
                    $row['Ticket Refund'],
                    $row['Ticket Net'],
                    // $row['Processing Fees'],
                    // $row['Processing Fees Refund'],
                    // $row['Processing Net'],
                    $row['Platform Fees'],
                    $row['Platform Fees Refund'],
                    $row['Platform Fees Net'],
                    $row['Discount'],
                    $row['Payment Total'],
                    $row['Refund Total'],
                    $row['Net Total'],
                ));
            }
            fputcsv($file, array($blankLine, $blankLine, $blankLine, $blankLine, $overallTicketTotal, $overallTicketRefundTotal, $overallTicketNetTotal, /*$overallProcessingFeesTotal, $overallProcessingFeesRefundTotal, $overallProcessingFeesNetTotal,*/ $overallPlatformFeesTotal, $overallPlatformFeesRefundTotal, $overallPlatformFeesNetTotal, $overallDiscountTotal, $overallTotalAmount, $overallRefundTotalAmount, $overallNetAmount));

            fputcsv($file, array($blankLine, $blankLine, $blankLine, $blankLine, $blankLine, $blankLine, $blankLine, $blankLine, $blankLine, $blankLine, $blankLine, $blankLine, $blankLine, $blankLine, $blankLine, $blankLine));

            fputcsv($file, array($blankLine, $blankLine, $blankLine, $blankLine, $blankLine, $blankLine, $blankLine, $blankLine, $blankLine, $blankLine, $blankLine, $blankLine, $blankLine, $blankLine, $blankLine, $blankLine));

            $checkYear = date("Y", strtotime($request->dateTo));
            if ($checkYear == "2022") {
                fputcsv($file, $farharborColumnsOverallAmount);

                fputcsv($file, $columnsOverallAmount);

                $dateRange = date("m-d-Y", strtotime($request->dateFrom)) . ' - ' . date("m-d-Y", strtotime($request->dateTo));
                $createdDate = date("m-d-Y");

                $overallTicketTotal = sprintf('%.2f', $farharbor['farharborticketcost']);
                $overallTicketRefundTotal = sprintf('%.2f', $farharbor['farharborticketcostrefund']);
                $overallTicketNetTotal = sprintf('%.2f', $farharbor['farharborticketnetamount']);

                $overallProcessingFeesTotal = sprintf('%.2f', $farharbor['farharborprocessingfee']);
                $overallProcessingFeesRefundTotal = sprintf('%.2f', $farharbor['farharborprocessingfeerefund']);
                $overallProcessingFeesNetTotal = sprintf('%.2f', $farharbor['farharborprocessingfeenetamount']);

                $overallPlatformFeesTotal = sprintf('%.2f', $farharbor['farharborplatformfee']);
                $overallPlatformFeesRefundTotal = sprintf('%.2f', $farharbor['farharborplatformfeerefund']);
                $overallPlatformFeesNetTotal = sprintf('%.2f', $farharbor['farharborplatformfeenetamount']);

                $overallDiscountTotal = sprintf('%.2f', $farharbor['farharbordiscountamount']);
                $overallTotalAmount = sprintf('%.2f', $farharbor['farharborpaymentamount']);
                $overallNetAmount = sprintf('%.2f', $farharbor['farharbornetamount']);

                $overallNetTotalAmount = sprintf('%.2f', $farharbor['farharbor_net_total']);
                $overallRefundTotalAmount = sprintf('%.2f', $farharbor['farharbor_refund_total']);

                fputcsv($file, array($dateRange, $createdDate, $overallTicketTotal, $overallTicketRefundTotal, $overallTicketNetTotal, $overallProcessingFeesTotal, $overallProcessingFeesRefundTotal, $overallProcessingFeesNetTotal, $overallPlatformFeesTotal, $overallPlatformFeesRefundTotal, $overallPlatformFeesNetTotal, $overallDiscountTotal, $overallTotalAmount, $overallNetAmount, $overallNetTotalAmount, $overallRefundTotalAmount));
                fputcsv($file, array($blankLine));

                fputcsv($file, $columns);
                foreach ($farharbor['farharbor_data'] as $eachData) {
                    $row['Booking Id'] = (isset($eachData->tour_booking_id)) ? $eachData->tour_booking_id : '';
                    $row['Booking Date'] = (isset($eachData->date)) ? date("m-d-Y", strtotime($eachData->date)) . " @ " . date("g:i A", strtotime($eachData->time_from)) : '';
                    $row['Created Date'] = (isset($eachData->created_at)) ? date("m-d-Y", strtotime($eachData->created_at)) : '';
                    $row['Customer Name'] = (isset($eachData->customer)) ? $eachData->customer : '';

                    $row['Ticket Cost'] = (isset($eachData->payment_cost)) ? number_format($eachData->payment_cost, 2) : '';
                    $row['Ticket Refund'] = (isset($eachData->refund_cost)) ? number_format($eachData->refund_cost, 2) : '';
                    $row['Ticket Net'] = (isset($eachData->payment_cost) || isset($eachData->refund_cost)) ? number_format(($eachData->payment_cost - $eachData->refund_cost), 2) : '';

                    $row['Processing Fees'] = (isset($eachData->payment_processing_fees)) ? number_format($eachData->payment_processing_fees, 2) : '';
                    $row['Processing Fees Refund'] = (isset($eachData->refund_processing_fees)) ? number_format($eachData->refund_processing_fees, 2) : '';
                    $row['Processing Net'] = (isset($eachData->payment_processing_fees) || isset($eachData->refund_processing_fees)) ? number_format(($eachData->payment_processing_fees - $eachData->refund_processing_fees), 2) : '';

                    $row['Platform Fees'] = (isset($eachData->payment_platform_fees)) ? number_format($eachData->payment_platform_fees, 2) : '';
                    $row['Platform Fees Refund'] = (isset($eachData->refund_platform_fees)) ? number_format($eachData->refund_platform_fees, 2) : '';
                    $row['Platform Fees Net'] = (isset($eachData->payment_platform_fees) || isset($eachData->refund_platform_fees)) ? number_format(($eachData->payment_platform_fees - $eachData->refund_platform_fees), 2) : '';

                    $row['Discount'] = (isset($eachData->discount)) ? number_format($eachData->discount, 2) : '';
                    $row['Payment Total'] = (isset($eachData->payment_cost) || isset($eachData->refund_cost) || isset($eachData->payment_processing_fees) || isset($eachData->refund_processing_fees) || isset($eachData->payment_platform_fees) || isset($eachData->refund_platform_fees) || isset($eachData->discount)) ? number_format(($eachData->payment_cost - $eachData->refund_cost) + ($eachData->payment_processing_fees - $eachData->refund_processing_fees) + ($eachData->payment_platform_fees - $eachData->refund_platform_fees) - $eachData->discount, 2) : '';
                    $row['Net Total'] = (isset($eachData->payment_cost) || isset($eachData->refund_cost) || isset($eachData->refund_processing_fees) || isset($eachData->discount)) ? number_format(($eachData->payment_cost - $eachData->refund_cost) - $eachData->refund_processing_fees - $eachData->discount, 2) : '';

                    fputcsv($file, array(
                        $row['Booking Id'],
                        $row['Booking Date'],
                        $row['Created Date'],
                        $row['Customer Name'],
                        $row['Ticket Cost'],
                        $row['Ticket Refund'],
                        $row['Ticket Net'],
                        $row['Processing Fees'],
                        $row['Processing Fees Refund'],
                        $row['Processing Net'],
                        $row['Platform Fees'],
                        $row['Platform Fees Refund'],
                        $row['Platform Fees Net'],
                        $row['Discount'],
                        $row['Payment Total'],
                        $row['Net Total'],
                    ));
                }
                fputcsv($file, array($blankLine, $blankLine, $blankLine, $blankLine, $overallTicketTotal, $overallTicketRefundTotal, $overallTicketNetTotal, $overallProcessingFeesTotal, $overallProcessingFeesRefundTotal, $overallProcessingFeesNetTotal, $overallPlatformFeesTotal, $overallPlatformFeesRefundTotal, $overallPlatformFeesNetTotal, $overallDiscountTotal, $overallTotalAmount, $overallNetAmount));
            }

            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get all payments and all refunds from the below query based on filter of date
     *
     * @param array $via
     * @param int $tourOperatorId
     * @param object $request
     *
     * @return array
     */
    public function cardCollectionTotalReport($via, $tourOperatorId, $data, $is_farharbor): array
    {
        $userType = userType();

        $startYear = ($data['dateFrom'] != '') ? date('Y', strtotime($data['dateFrom'])) : '';

        /* START - PAYMENT / REFUND COLLECTION QUERY FOR CARD */
        $paymentSql = 'SELECT
            `tbm`.`id` as `tour_booking_id`,
            `tbh`.`operation`,
            CASE
                WHEN `tbh`.`operation` = "Payment" THEN `tbh`.`ticket_cost`
                ELSE 0
            END as "payment_cost",
            CASE
                WHEN `tbh`.`operation` = "Payment" THEN `tbh`.`processing_fees`
                ELSE 0
            END as "payment_processing_fees",
            CASE
                WHEN `tbh`.`operation` = "Payment" THEN `tbh`.`platform_fees`
                ELSE 0
            END as "payment_platform_fees",
            CASE
                WHEN `tbh`.`operation` = "Refund" THEN `tbh`.`ticket_cost`
                WHEN `tbh`.`operation` = "Cancelled" THEN `tbh`.`ticket_cost`
                ELSE 0
            END as "refund_cost",
            CASE
                WHEN `tbh`.`operation` = "Refund" THEN `tbh`.`processing_fees`
                WHEN `tbh`.`operation` = "Cancelled" THEN `tbh`.`processing_fees`
                ELSE 0
            END as "refund_processing_fees",
            CASE
                WHEN `tbh`.`operation` = "Refund" THEN `tbh`.`platform_fees`
                WHEN `tbh`.`operation` = "Cancelled" THEN `tbh`.`platform_fees`
                ELSE 0
            END as "refund_platform_fees",
            `tbh`.`discount`,
            `tbm`.`comments`,
            `tbm`.`booking_total`,
            `tbm`.`status`,
            `tbm`.`created_by`,
            `tbm`.`created_by_user_type`,
            date_format(`tbm`.`created_at`, "%Y-%m-%d") as `created_at`,
            `tbm`.`name` as `customer`,
            `tbm`.`email`,
            `tbm`.`phone_number`,
            `tst`.`time_from`,
            `tst`.`time_to`,
            `tsd`.`date`,
            `tbm`.`tour_package_id`,
            `tbh`.`details`,
            ifnull(`tbh`.`payment_type`, 0) as `via`,
            ifnull(`tbh`.`payment_method`, 0) as `method`,
            `tbm`.`tourists`,
            `tbm`.`infants`
        from
            `tour_booking_history` as `tbh`
            inner join `tour_bookings_master` as `tbm` on `tbm`.`id` = `tbh`.`tour_booking_id`
            inner join `tour_slot_times` as `tst` on `tst`.`id` = `tbm`.`tour_slot_id`
            inner join `tour_slot_dates` as `tsd` on `tsd`.`id` = `tst`.`tour_slot_id`
            inner join `tour_packages` as `tp` on `tp`.`id` = `tbm`.`tour_package_id`';

        $in = StrUtils::dbInFilter(implode("|", $via));

        if (getSubdomainFromHost() == "kens" || getSubdomainFromHost() == "apm") {
            if ($via[0] == 'Booking System') {
                $in = '"Card"';
            }
        }

        $paymentSql .= ' where `tbh`.`payment_type` IN (' . $in . ') ';

        $paymentSql .= ' and `tbh`.`operation` in ("Payment", "Refund", "Cancelled") ';

        // When viewby filter option exists [Tour Date/Purchase Date]
        if (isset($data['viewDate'])) {
            $viewDate = ($data['viewDate'] == 'purchasedate') ? '`tbh`.`operation_at`' : '`tsd`.`date`';
            $data['dateFrom'] = ($data['viewDate'] == 'purchasedate') ? $data['dateFrom'] . ' 00:00:00' : $data['dateFrom'];
            $data['dateTo'] = ($data['viewDate'] == 'purchasedate') ? $data['dateTo'] . ' 23:59:59' : $data['dateTo'];
            $paymentSql .= ' and ' . $viewDate . ' between "' . $data['dateFrom'] . '" and "' . $data['dateTo'] . '"';
        }

        if ($data['report'] == 'Card Collection Report') {
            if (!isset($data['viewDate'])) {
                $paymentSql .= ' and `tsd`.`date` between "' . $data['dateFrom'] . '" and "' . $data['dateTo'] . '"';
            }
            // $paymentSql .= ' and `tsd`.`date` between "'.$data['dateFrom'].'" and "'.$data['dateTo'].'"';
            $yearSql = '`tsd`.`date`';
        } elseif ($data['report'] == 'Stripe Payment Report') {
            if (!isset($data['viewDate'])) {
                $paymentSql .= ' and `tbh`.`operation_at` between "' . $data['dateFrom'] . ' 00:00:00" and "' . $data['dateTo'] . ' 23:59:59"';
            }
            // $paymentSql .= ' and `tbh`.`operation_at` between "'.$data['dateFrom'].' 00:00:00" and "'.$data['dateTo'].' 23:59:59"';
            $yearSql = '`tbh`.`operation_at`';
        }

        if ($data['tourPackage'] != 0) {
            $paymentSql .= ' and `tp`.`id` = "' . $data['tourPackage'] . '"';
        }

        if ($userType == 'Tour Operator') {
            $paymentSql .= ' and `tp`.`tour_operator_id` = "' . auth()->user()->tour_operator_id . '" ';
        } else {
            $paymentSql .= ' and `tp`.`tour_operator_id` = "' . $tourOperatorId . '" ';
        }

        if ($is_farharbor == 0) {
            if ($startYear < 2023) {
                $paymentSql .= ' and `tbm`.`comments` not like "#%"';
            }
            if (getSubdomainFromHost() == "kens" || getSubdomainFromHost() == "apm") {
                if ($via[0] == "Card") {
                    if ($startYear <= 2024) {
                        $paymentSql .= ' and `tbm`.`comments` not like "#%"';
                    }
                }
                if ($via[0] == "Booking System") {
                    if ($startYear <= 2024) {
                        $paymentSql .= ' and `tbm`.`comments` like "#%"';
                    }
                }
            }
        } else {
            $paymentSql .= ' and `tbm`.`comments` like "#%" and YEAR(' . $yearSql . ') < 2023';
        }

        $paymentSql .= ' ORDER BY `tbm`.`id`, `tbh`.`id` ASC';
        // $paymentSql .= " GROUP BY `tbm`.`id` ORDER BY `tbm`.`id` DESC";
        // get the list of bookings
        $cardData = DB::select($paymentSql);

        /* END - PAYMENT / REFUND COLLECTION QUERY FOR CARD */
        $lookup = array();
        // $lookup = $cardData;
        $refund = 0;
        foreach ($cardData as $row) {
            if (isset($lookup[$row->tour_booking_id])) {
                $lookup[$row->tour_booking_id]->payment_cost += $row->payment_cost;
                $pc = $lookup[$row->tour_booking_id]->payment_cost;
                $lookup[$row->tour_booking_id]->payment_cost = (string)$pc;

                $lookup[$row->tour_booking_id]->payment_processing_fees += $row->payment_processing_fees;
                $p_processing_fees = $lookup[$row->tour_booking_id]->payment_processing_fees;
                $lookup[$row->tour_booking_id]->payment_processing_fees = (string)$p_processing_fees;

                $lookup[$row->tour_booking_id]->payment_platform_fees += $row->payment_platform_fees;
                $p_platform_fees = $lookup[$row->tour_booking_id]->payment_platform_fees;
                $lookup[$row->tour_booking_id]->payment_platform_fees = (string)$p_platform_fees;

                $lookup[$row->tour_booking_id]->refund_cost += $row->refund_cost;
                $rc = $lookup[$row->tour_booking_id]->refund_cost;
                $lookup[$row->tour_booking_id]->refund_cost = (string)$rc;

                $lookup[$row->tour_booking_id]->refund_processing_fees += $row->refund_processing_fees;
                $r_processing_fees = $lookup[$row->tour_booking_id]->refund_processing_fees;
                $lookup[$row->tour_booking_id]->refund_processing_fees = (string)$r_processing_fees;

                $lookup[$row->tour_booking_id]->refund_platform_fees += $row->refund_platform_fees;
                $r_platform_fees = $lookup[$row->tour_booking_id]->refund_platform_fees;
                $lookup[$row->tour_booking_id]->refund_platform_fees = (string)$r_platform_fees;
                $lookup[$row->tour_booking_id]->refund = (string)($lookup[$row->tour_booking_id]->refund_cost + $lookup[$row->tour_booking_id]->refund_processing_fees + $lookup[$row->tour_booking_id]->refund_platform_fees);
            } else {
                $lookup[$row->tour_booking_id] = $row;
            }
        }

        $ticketCost = 0;
        $ticketCostRefund = 0;
        $ticketCostNet = 0;
        $processingFee = 0;
        $processingFeeRefund = 0;
        $processingFeeNet = 0;
        $platformFee = 0;
        $platformFeeRefund = 0;
        $platformFeeNet = 0;
        $discountAmount = 0;
        $totalAmount = 0;
        $finalNetAmount = 0;
        $refundAmount = 0;

        $totalTicketCost = 0;
        $totalTicketCostRefund = 0;
        $totalTicketCostNet = 0;
        $totalProcessingFee = 0;
        $totalProcessingFeeRefund = 0;
        $totalProcessingFeeNet = 0;
        $totalPlatformFee = 0;
        $totalPlatformFeeRefund = 0;
        $totalPlatformFeeNet = 0;
        $totalDiscountAmount = 0;
        $totalTotalAmount = 0;
        $totalFinalNetAmount = 0;
        $totalCollectionAmount = 0;
        $totalRefundAmount = 0;

        foreach ($lookup as $key => $value) {
            $ticketCost = round($value->payment_cost, 2);
            $ticketCostRefund = round($value->refund_cost, 2);
            $processingFee = round($value->payment_processing_fees, 2);
            $processingFeeRefund = round($value->refund_processing_fees, 2);
            $platformFee = round($value->payment_platform_fees, 2);
            $platformFeeRefund = round($value->refund_platform_fees, 2);
            $discountAmount = round($value->discount, 2);

            // $ticketCostNet = $value->payment_cost - $value->refund_cost;
            $ticketCostNet = $ticketCost - $ticketCostRefund;
            $processingFeeNet = $processingFee - $processingFeeRefund;
            $platformFeeNet = $platformFee - $platformFeeRefund;
            $finalNetAmount = $ticketCostNet;

            if ($is_farharbor == 1) {
                if ($value->status == "Cancelled") {
                    $totalAmount = 0.00;
                } else {
                    $totalAmount = ($ticketCostNet + $processingFeeNet + $platformFeeNet);
                }
            } else {
                if (!empty($ticketCost)) {
                    $totalAmount = round($ticketCost + $processingFee + $platformFee, 2);
                } else {
                    $totalAmount = 0;
                }
            }
            $refundAmount = $ticketCostRefund + $platformFeeRefund + $processingFeeRefund;
            $lookup[$key]->refund_total = $refundAmount;
            $lookup[$key]->payment_total = $totalAmount;

            $totalTicketCost += $ticketCost;
            $totalTicketCostRefund += $ticketCostRefund;
            $totalTicketCostNet += $ticketCostNet;
            $totalProcessingFee += $processingFee;
            $totalProcessingFeeRefund += $processingFeeRefund;
            $totalProcessingFeeNet += $processingFeeNet;
            $totalPlatformFee += $platformFee;
            $totalPlatformFeeRefund += $platformFeeRefund;
            $totalPlatformFeeNet += $platformFeeNet;
            $totalDiscountAmount += $discountAmount;
            $totalTotalAmount += $totalAmount;
            $totalFinalNetAmount += $finalNetAmount;
            $totalRefundAmount += $refundAmount;
        }

        if ($is_farharbor == 0) {
            return [
                'data' => array_values($lookup),
                'ticketcost' => $totalTicketCost,
                'ticketcostrefund' => $totalTicketCostRefund,
                'ticketnetamount' => $totalTicketCostNet,
                'processingfee' => $totalProcessingFee,
                'processingfeerefund' => $totalProcessingFeeRefund,
                'processingfeenetamount' => $totalProcessingFeeNet,
                'platformfee' => $totalPlatformFee,
                'platformfeerefund' => $totalPlatformFeeRefund,
                'platformfeenetamount' => $totalPlatformFeeNet,
                'discountamount' => $totalDiscountAmount,
                'paymentamount' => $totalTotalAmount,
                'netamount' => $totalFinalNetAmount,
                'refund_total' => $totalRefundAmount,
            ];
        } else {
            return [
                'farharbor_data' => array_values($lookup),
                'farharborticketcost' => $totalTicketCost,
                'farharborticketcostrefund' => $totalTicketCostRefund,
                'farharborticketnetamount' => $totalTicketCostNet,
                'farharborprocessingfee' => $totalProcessingFee,
                'farharborprocessingfeerefund' => $totalProcessingFeeRefund,
                'farharborprocessingfeenetamount' => $totalProcessingFeeNet,
                'farharborplatformfee' => $totalPlatformFee,
                'farharborplatformfeerefund' => $totalPlatformFeeRefund,
                'farharborplatformfeenetamount' => $totalPlatformFeeNet,
                'farharbordiscountamount' => $totalDiscountAmount,
                'farharborpaymentamount' => $totalTotalAmount,
                'farharbornetamount' => $totalFinalNetAmount,
                'farharbor_refund_total' => $totalRefundAmount
            ];
        }
    }

    public function dailyactivityreport(
        $request,
        string $fromDate = "",
        string $toDate = "",
        $tourOperatorId = 0,
        $tourPackageId = 0,
        string $viewBy = "tourdate",
        int $export = 0
    ) {
        //overfide max reponse time
        ini_set('max_execution_time', 0);

        try {
            // if api request ensure tour package id is set.
            if ($request->api == 1) {
                if ($tourPackageId == 0) {
                    throw new Exception('Please select a tour package.');
                }
            }

            //set common vars.
            //$userType = userType();
            $tourOperator = TourOperator::find(auth()->user()->tour_operator_id ?? $tourOperatorId);
            $tourPackage = TourPackage::find($tourPackageId);
            $dates = DateUtils::startEndDateDefaults($fromDate, $toDate);

            // get filters if set default all payment types are selected by default
            $cardEnabled = $cashBlockEnabled = $invoicedBlockEnabled = true;
            $checkWireBlockEnabled = $billableBlockEnabled = $PendingUnpaidEnabled = true;
            $tripadvisorEnabled = $fareHarborEnabled = $bookingSystemEnabled = false;
            if ($request->filters) {
                $tripadvisorEnabled = (isset($request->tripadvisor)) ? true : false;
                $cardEnabled = (isset($request->card)) ? true : false;
                $fareHarborEnabled = (isset($request->fareharbor)) ? true : false;
                $bookingSystemEnabled = (isset($request->bookingsystem)) ? true : false;
                $cashBlockEnabled = (isset($request->cash)) ? true : false;
                $invoicedBlockEnabled = (isset($request->invoiced)) ? true : false;
                $billableBlockEnabled = (isset($request->billable)) ? true : false;
                $checkWireBlockEnabled = (isset($request->checkwire)) ? true : false;
                $PendingUnpaidEnabled = (isset($request->pendingunpaid)) ? true : false;
            }

            $commonData = array(
                'dateFrom' => $dates['start_date'],
                'dateTo' => $dates['end_date'],
                'report' => 'Card Collection Report',
                'tourPackage' => $tourPackageId,
                'viewDate' => $viewBy
            );

            // Card Totals
            $cardBlock = null;
            if ($cardEnabled) {
                $cardBlockData = $this->cardCollectionTotalReport(['Card'], $tourOperatorId, $commonData, 0);
                $cardBlock = $this->dailyActivityCardBlock($cardBlockData);
            }

            // Cash Totals
            $cashBlock = null;
            $isCashFees = cashFees();
            if ($cashBlockEnabled) {
                if ($isCashFees == '1') {
                    $cashBlockData = $this->cardCollectionTotalReport(['Cash'], $tourOperatorId, $commonData, 0);
                    $cashBlock = $this->dailyActivityCardBlock($cashBlockData);
                } else {
                    $cashBlock = $this->dailyActivityCashBlock($dates['start_date'], $dates['end_date'], $tourOperatorId, $tourPackageId, $viewBy);
                }
            }

            // Invoice Totals
            $invoicedBlock = null;
            if ($invoicedBlockEnabled) {
                $invoicedBlock = $this->dailyActivityInvoicedBlock($dates['start_date'], $dates['end_date'], $tourOperatorId, $tourPackageId, $viewBy);
            }

            // Invoice Billable Totals
            $invoicedBillableBlock = null;
            if ($billableBlockEnabled) {
                $invoicedBillableBlock = $this->dailyActivityInvoicedBillableBlock($dates['start_date'], $dates['end_date'], $tourOperatorId, $tourPackageId, $viewBy);
            }

            // check/wire totals
            $checkWireBlock = null;
            if ($checkWireBlockEnabled) {
                $checkWireBlock = $this->dailyActivityCheckWireBlock($dates['start_date'], $dates['end_date'], $tourOperatorId, $tourPackageId, $viewBy);
            }

            // Trip Advisor Totals
            $tripAdBlock = null;
            if ($tripadvisorEnabled) {
                $tripAdBlockData = $this->cardCollectionTotalReport(['TripAdvisor'], $tourOperatorId, $commonData, 0);
                $tripAdBlock = $this->dailyActivityCardBlock($tripAdBlockData);
            }

            //FareHarbor Totals
            $fhBlock = null;
            if ($fareHarborEnabled) {
                //TODO: Note you should not use the staic year. We should use a db setting for clients onboarded after 2022
                //if(date('Y', strtotime($dates['end_date'])) == "2022"){
                $cardFarharborBlockData = $this->cardCollectionTotalReport(['Card'], $tourOperatorId, $commonData, 1);
                $cardFarharborBlock = $this->dailyActivityCardFarharborBlock($cardFarharborBlockData);
                $fhBlock = ReportUtils::fareHarborBlockFormat($cardFarharborBlock);
            }

            $bookingSystemBlock = null;
            if ($bookingSystemEnabled) {
                $bookingSystemBlockData = $this->cardCollectionTotalReport(['Booking System'], $tourOperatorId, $commonData, 0);
                $bookingSystemBlock = $this->dailyActivityCardBlock($bookingSystemBlockData);
            }

            // Placeholder response
            $response = array();
            $response['data1']['tour_operator_name'] = $tourOperator->name ?? "All package";

            // calc refunds/platform fees/processing fees/ total net.
            $allBlocks = [$cardBlock, $fhBlock, $bookingSystemBlock, $cashBlock, $invoicedBlock, $invoicedBillableBlock, $checkWireBlock, $tripAdBlock];

            // sum the totals by index.
            $totalBookings = ReportUtils::aggregateSum($allBlocks, 'total_bookings');

            $totalProcessingFee = ReportUtils::aggregateSum($allBlocks, 'processing_fees_total');
            $totalRefundedProcessingFee = ReportUtils::aggregateSum($allBlocks, 'processing_fees_refund_total');
            $totalProcessingFeeNet = ReportUtils::aggregateSum($allBlocks, 'processing_fees_net_total');
            $totalPlatformFee = ReportUtils::aggregateSum($allBlocks, 'platform_fees_total');
            $totalRefundedPlatformFee = ReportUtils::aggregateSum($allBlocks, 'platform_fees_refund_total');
            $totalPlatformFeeNet = ReportUtils::aggregateSum($allBlocks, 'platform_fees_net_total');
            $totalDiscount = ReportUtils::aggregateSum($allBlocks, 'discount_total');
            $netPayment = ReportUtils::aggregateSum($allBlocks, 'final_net_total');

            // calc ticket net
            $ticketBlocks = [$cardBlock, $fhBlock, $bookingSystemBlock, $cashBlock, $invoicedBlock, $invoicedBillableBlock, $tripAdBlock];
            $ticketCostTotal = ReportUtils::aggregateSum($ticketBlocks, 'ticket_cost_total');
            $ticketCostRefund = ReportUtils::aggregateSum($ticketBlocks, 'ticket_cost_refund_total');
            $ticketCostNet = ReportUtils::aggregateSum($ticketBlocks, 'ticket_cost_net_total');

            // calc refund totals
            $refundCardTotal = ReportUtils::refundSum($cardBlock);
            $refundCashTotal = ReportUtils::refundSum($cashBlock);
            $refundWireTotal = ReportUtils::refundSum($checkWireBlock);
            $refundInvoicedTotal = ReportUtils::refundSum($invoicedBlock);
            $refundInvoicedBillableBlockTotal =  ReportUtils::refundSum($invoicedBillableBlock);
            $tripAdTotal = ReportUtils::refundSum($tripAdBlock);

            // Calculate total refunds across all payment types
            $totalRefunds = $refundCardTotal + $refundCashTotal + $refundWireTotal + $refundInvoicedTotal + $refundInvoicedBillableBlockTotal + $tripAdTotal;

            // set totals response data grp.
            $response['data2'] = [
                'package_name' => $tourPackage->name ?? '',
                'date_range' => date('m/d/Y', strtotime($dates['start_date'])) . ' - ' . date('m/d/Y', strtotime($dates['end_date'])),
                'created' => date('m/d/Y'),
                'total_bookings' => $totalBookings,
                'ticket_cost_total' => $ticketCostTotal,
                'ticket_cost_refund_total' => $ticketCostRefund,
                'ticket_cost_net_total' => $ticketCostNet,
                'processing_fees_total' => $totalProcessingFee,
                'processing_fees_refund_total' => $totalRefundedProcessingFee,
                'processing_fees_net_total' => $totalProcessingFeeNet,
                'platform_fees_total' => $totalPlatformFee,
                'platform_fees_refund_total' => $totalRefundedPlatformFee,
                'platform_fees_net_total' => $totalPlatformFeeNet,
                'discount_total' => $totalDiscount,
                'payment_total_amount' => ($ticketCostTotal + $totalProcessingFee + $totalPlatformFee),
                'total_refunds' => $totalRefunds,
                'final_net_total' => $netPayment
            ];

            // if api format the reponse body by purchase type
            $response['data3'] = [];

            if ($request->api == 1) {

                // //if card type is set see filters above
                if (isset($cardBlock)) {
                    $response['data3']['Card'] = ReportUtils::dailyReportTotals($cardBlock, $refundCardTotal, $tourPackage->name);
                }

                // if cash type is set see filters above
                if (isset($cashBlock)) {
                    $response['data3']['Cash'] = ReportUtils::dailyReportTotals($cashBlock, $refundCashTotal, $tourPackage->name);
                }

                // if Invoiced is set : see filters above
                if (isset($invoicedBlock)) {
                    $response['data3']['Invoiced'] = ReportUtils::dailyReportTotals($invoicedBlock, $refundInvoicedTotal, $tourPackage->name);
                }

                // if billable is set : see filters above
                if (isset($invoicedBillableBlock)) {
                    $response['data3']['Billable'] = ReportUtils::dailyReportTotals($invoicedBillableBlock, $refundInvoicedBillableBlockTotal, $tourPackage->name);
                }

                // if trip check/wire is set see filters above
                if (isset($checkWireBlock)) {
                    $response['data3']['Wire'] = ReportUtils::dailyReportTotals($checkWireBlock, $refundWireTotal, $tourPackage->name);
                }

                // if trip advisor is set see filters above
                if (isset($tripAdBlock)) {
                    $response['data3']['TripAdvisor'] = ReportUtils::dailyReportTotals($tripAdBlock, $tripAdTotal, $tourPackage->name);
                }

                // if fareharbor is set: see filters above.
                if (isset($fhBlock)) {
                    $response['data3']['Fareharbor'] = ReportUtils::dailyReportTotals($fhBlock, 0, $tourPackage->name);
                }

                if (isset($bookingSystemBlock)) {
                    $response['data3']['Booking System'] = ReportUtils::dailyReportTotals($bookingSystemBlock, 0);
                }
            } else {
                // else group reponse body for web with the tour package name.

                // if card type is set see filters above
                if (isset($cardBlock)) {
                    $response['data3']['Card'] = [$tourPackage->name ?? "All package" => ReportUtils::dailyReportTotals($cardBlock, $refundCardTotal)];
                }

                // if cash type is set see filters above
                if (isset($cashBlock)) {
                    $response['data3']['Cash'] = [$tourPackage->name ?? "All package" => ReportUtils::dailyReportTotals($cashBlock, $refundCashTotal)];
                }

                // if Invoiced is set : see filters above
                if (isset($invoicedBlock)) {
                    $response['data3']['Invoiced'] = [$tourPackage->name ?? "All package" => ReportUtils::dailyReportTotals($invoicedBlock, $refundInvoicedTotal)];
                }

                // if billable is set : see filters above
                if (isset($invoicedBillableBlock)) {
                    $response['data3']['Billable'] = [$tourPackage->name ?? "All package" => ReportUtils::dailyReportTotals($invoicedBillableBlock, $refundInvoicedBillableBlockTotal)];
                }


                // if trip check/wire is set see filters above
                if (isset($checkWireBlock)) {
                    $response['data3']['Wire'] = [$tourPackage->name ?? "All package" => ReportUtils::dailyReportTotals($checkWireBlock, $refundWireTotal)];
                }

                // if trip advisor is set see filters above
                if (isset($tripAdBlock)) {
                    $response['data3']['TripAdvisor'] = [$tourPackage->name ?? "All package" => ReportUtils::dailyReportTotals($tripAdBlock, $tripAdTotal)];
                }

                // if fareharbor is set: see filters above.
                if (isset($fhBlock)) {
                    $response['data3']['Fareharbor'] = [$tourPackage->name ?? "All package" => ReportUtils::dailyReportTotals($fhBlock, 0)];
                }
                // external booking system
                if (isset($bookingSystemBlock)) {
                    $response['data3']['Booking System'] = [$tourPackage->name ?? "All package" => ReportUtils::dailyReportTotals($bookingSystemBlock, 0)];
                }
            }


            // get all unpaid bookings | Pending payments
            // Unpaid Bookings Filtrations
            $unpaidResultArray = [];
            if ($PendingUnpaidEnabled) {
                $unpaid_payments = BookingUtils::fetchUnpaidBookings($dates['start_date'], $dates['end_date'], $tourPackageId, userType(), $tourOperatorId);
                $unpaidResultArray = json_decode($unpaid_payments, true);
                $unPaidPaymentsBookingIds = array();
                $response['data5'] = array();
            }

            // set the unpaid booking response
            if (count($unpaidResultArray) > 0) {
                $unpaidBookingAmount = 0;
                $unpaidRefundAmount = 0;
                $unpaidProcessingFeeAmount = 0;
                $unpaidSoftwareFeeAmount = 0;
                $unpaidNetAmount = 0;
                foreach ($unpaidResultArray as $val) {
                    $unPaidPaymentsBookingIds[$val['package_name']][] = $val['tour_booking_id'];
                    $response['data5'][] = [
                        'booking_id' => $val['tour_booking_id'],
                        'customer_name' => $val['customer_name'] ?? '',
                        'customer_email' => $val['customer_email'] ?? '',
                        'tourists' => $val['tourists'] ?? 0,
                        'infants' => $val['infants'] ?? 0,
                        'total_pax' => ($val['tourists'] ?? 0) + ($val['infants'] ?? 0)
                    ];
                    $unpaidBookingAmount = $unpaidBookingAmount + $val['booking_transact_total'];
                    $unpaidSoftwareFeeAmount = $unpaidSoftwareFeeAmount + $val['service_commission'];

                    // Processing Fee Amount for Pay Later
                    $processingFees = 0;
                    if ($val['refund_permitfee_status'] != '') {
                        $refundpermitFeeStatuses = json_decode($val['refund_permitfee_status']);

                        $touritsts = $val['tourists'];
                        $totalRefundedProcessingPermitfees = 0;

                        foreach ($refundpermitFeeStatuses as $refundpermitFeeStatus) {
                            if ($refundpermitFeeStatus->status == 'Y') {
                                $totalRefundedProcessingPermitfees += $refundpermitFeeStatus->processing_fees;
                            }
                        }

                        $processingFees = (((($touritsts) * ($val['rate'] +
                            $val['additional_charge'] +
                            $val['permit_fee']
                        )) * (float)$val['processing_charge_percentage']) / 100) - $totalRefundedProcessingPermitfees;
                    } else if ($val['processing_charge_percentage'] > 0) {
                        $processingFees = (($val['tourists'] * ($val['rate'] +
                            $val['additional_charge'] +
                            $val['permit_fee']
                        )) * (float)$val['processing_charge_percentage']) / 100;
                    }

                    $unpaidProcessingFeeAmount = $unpaidProcessingFeeAmount + $processingFees;
                    if ($request->api == 1) {
                        $response['data4']['package_name'] = $val['package_name'];
                        $response['data4']['UnpaidBookingAmount'] = $unpaidBookingAmount;
                        $response['data4']['UnpaidSoftwareFeeAmount'] = $unpaidSoftwareFeeAmount;
                        $response['data4']['UnpaidProcessingFeeAmount'] = $unpaidProcessingFeeAmount;
                        $response['data4']['UnpaidRefundAmout'] = $unpaidRefundAmount;
                        $response['data4']['UnpaidBookingCount'] = (!empty($unPaidPaymentsBookingIds[$val['package_name']])) ? count(array_unique($unPaidPaymentsBookingIds[$val['package_name']])) : 0;
                    } else {
                        $response['data4'][$val['package_name']]['UnpaidBookingAmount'] = $unpaidBookingAmount;
                        $response['data4'][$val['package_name']]['UnpaidSoftwareFeeAmount'] = $unpaidSoftwareFeeAmount;
                        $response['data4'][$val['package_name']]['UnpaidProcessingFeeAmount'] = $unpaidProcessingFeeAmount;
                        $response['data4'][$val['package_name']]['UnpaidRefundAmout'] = $unpaidRefundAmount;
                        $response['data4'][$val['package_name']]['UnpaidBookingCount'] = (!empty($unPaidPaymentsBookingIds[$val['package_name']])) ? count(array_unique($unPaidPaymentsBookingIds[$val['package_name']])) : 0;
                    }
                }

                $unpaidNetAmount = $unpaidNetAmount + $unpaidBookingAmount - $unpaidRefundAmount - $unpaidProcessingFeeAmount - $unpaidSoftwareFeeAmount;
                if ($request->api == 1) {
                    $response['data4']['package_name'] = $val['package_name'];
                    $response['data4']['UnpaidNetAmout'] = $unpaidNetAmount;
                } else {
                    $response['data4'][$val['package_name']]['UnpaidNetAmout'] = $unpaidNetAmount;
                }
            }

            // TODO: do not use a year in the past:  we may have new clients that are onboraded that use fair harbor
            // if(date('Y', strtotime($dates['end_date']))  != "2022"){ unset($response['data3']['Fareharbor']); }


            if ($export) {
                return $this->exportDailyActivityReport($dates['start_date'], $dates['end_date'], $response, $tourOperatorId, $tourPackageId, $viewBy);
            }

            return $response;
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    // Daily Activity Report Export Start
    public function exportDailyActivityReport($fromDate, $toDate, $newSortedData, $tourOperatorId = 0, $tourPackageId = 0, $viewBy)
    {
        $fileName = 'daily_activity_report_' . $fromDate . '_' . $toDate . '.csv';
        $columns = csvColumns('daily_activity_report_columns');
        $overall_columns = csvColumns('daily_activity_report_overall_columns');
        $unpaidBookingColumns = csvColumns('daily_activity_report_unpaid_booking_columns');
        $tripAdvisorBookingColumns = csvColumns('daily_activity_report_tripadvisor_booking_columns');
        $tourOperatorName = $this->GetTourOperatorName($tourOperatorId, $tourPackageId);

        $headers = csvHeaders($fileName);

        $callback = function () use ($newSortedData, $columns, $overall_columns, $fromDate, $toDate, $unpaidBookingColumns, $tourOperatorName, $tripAdvisorBookingColumns, $viewBy) {

            $file = fopen('php://output', 'w');  // Create a file
            $dateRange = date("m/d/Y", strtotime($fromDate)) . ' - ' . date("m/d/Y", strtotime($toDate));
            $viewBy = ($viewBy == "purchasedate") ? "Purchase Date" : "Tour Date";

            $blankLine = '';
            if (!empty($tourOperatorName)) {
                if ($tourOperatorName['tourOperatorName']) {
                    fputcsv($file, array('Tour Operator:', $tourOperatorName['tourOperatorName']));
                }
                if ($tourOperatorName['tourPackageName']) {
                    fputcsv($file, array('Tour Package:', $tourOperatorName['tourPackageName']));
                }
            }
            $blankLine = '';
            fputcsv($file, array($blankLine));
            fputcsv($file, array($dateRange));
            fputcsv($file, array('View By: ' . $viewBy));
            $blankLine = '';
            fputcsv($file, array($blankLine));
            fputcsv($file, $overall_columns);
            fputcsv($file, array(
                "All",
                "All",
                $newSortedData['data2']['total_bookings'],
                $newSortedData['data2']['ticket_cost_total'],
                $newSortedData['data2']['ticket_cost_refund_total'],
                $newSortedData['data2']['ticket_cost_net_total'],
                $newSortedData['data2']['platform_fees_total'],
                $newSortedData['data2']['platform_fees_refund_total'],
                $newSortedData['data2']['platform_fees_net_total'],
                $newSortedData['data2']['discount_total'],
                $newSortedData['data2']['payment_total_amount'],
                $newSortedData['data2']['total_refunds'],
                $newSortedData['data2']['ticket_cost_net_total']
            ));
            $blankLine = '';
            fputcsv($file, array($blankLine));
            fputcsv($file, array($blankLine));

            fputcsv($file, $columns);
            foreach ($newSortedData['data3'] as $key => $value) {
                // Defined variables to sum the total of card or cash collection in below loop to add another line in csv
                $bookingCount = 0;
                $bookingAmount = 0;
                $RefundedBookingAmount = 0;
                $BookingAmountNet = 0;
                $ProcessingFeeAmount = 0;
                $RefundedProcessingFeeAmount = 0;
                $ProcessingFeeAmountNet = 0;
                $SoftwareFeeAmount = 0;
                $RefundedSoftwareFeeAmount = 0;
                $SoftwareFeeAmountNet = 0;
                $DiscountAmount = 0;
                $PaymentAmount = 0;
                $FinalNetAmount = 0;
                $RefundAmount = 0;

                foreach ($value as $index => $lookup) {
                    fputcsv($file, array(
                        $key,
                        $index,
                        $lookup['BookingCount'] ?? 0,
                        $lookup['BookingAmount'] ?? 0,
                        $lookup['RefundedBookingAmount'] ?? 0,
                        $lookup['BookingAmountNet'] ?? 0,
                        $lookup['SoftwareFeeAmount'] ?? 0,
                        $lookup['RefundedSoftwareFeeAmount'] ?? 0,
                        $lookup['SoftwareFeeAmountNet'] ?? 0,
                        $lookup['DiscountAmount'] ?? 0,
                        $lookup['PaymentAmount'] ?? 0,
                        $lookup['RefundAmount'] ?? 0,
                        $lookup['FinalNetAmount'] ?? 0
                    ));
                    $bookingCount += $lookup['BookingCount'] ?? 0;
                    $bookingAmount += $lookup['BookingAmount'] ?? 0;
                    $RefundedBookingAmount += $lookup['RefundedBookingAmount'] ?? 0;
                    $BookingAmountNet += $lookup['BookingAmountNet'] ?? 0;
                    $ProcessingFeeAmount += $lookup['ProcessingFeeAmount'] ?? 0;
                    $RefundedProcessingFeeAmount += $lookup['RefundedProcessingFeeAmount'] ?? 0;
                    $ProcessingFeeAmountNet += $lookup['ProcessingFeeAmountNet'] ?? 0;
                    $SoftwareFeeAmount += $lookup['SoftwareFeeAmount'] ?? 0;
                    $RefundedSoftwareFeeAmount += $lookup['RefundedSoftwareFeeAmount'] ?? 0;
                    $SoftwareFeeAmountNet += $lookup['SoftwareFeeAmountNet'] ?? 0;
                    $DiscountAmount += $lookup['DiscountAmount'] ?? 0;
                    $PaymentAmount += $lookup['PaymentAmount'] ?? 0;
                    $RefundAmount += $lookup['RefundAmount'] ?? 0;
                    $FinalNetAmount += $lookup['FinalNetAmount'] ?? 0;
                }

                // Total of card or cash collection based on packages
                fputcsv($file, array(
                    "",
                    "",
                    $bookingCount,
                    $bookingAmount,
                    $RefundedBookingAmount,
                    $BookingAmountNet,
                    // $ProcessingFeeAmount,
                    // $RefundedProcessingFeeAmount,
                    // $ProcessingFeeAmountNet,
                    $SoftwareFeeAmount,
                    $RefundedSoftwareFeeAmount,
                    $SoftwareFeeAmountNet,
                    $DiscountAmount,
                    $PaymentAmount,
                    $RefundAmount,
                    $FinalNetAmount
                ));
                $blankLine = '';
                fputcsv($file, array($blankLine));
            }

            fputcsv($file, array($blankLine));
            fputcsv($file, array($blankLine));


            if (isset($newSortedData['data4'])) {
                fputcsv($file, $unpaidBookingColumns);

                foreach ($newSortedData['data4'] as $key => $value) {
                    fputcsv($file, array(
                        "Pending (Unpaid)",
                        $key,
                        $value['UnpaidBookingCount'] ?? 0,
                        $value['UnpaidBookingAmount'] ?? 0,
                        $value['UnpaidSoftwareFeeAmount'] ?? 0,
                        $value['UnpaidRefundAmout'] ?? 0,
                        $value['UnpaidNetAmout'] ?? 0
                    ));
                }

                // Export booking IDs and details if available
                if (isset($newSortedData['data5']) && !empty($newSortedData['data5'])) {
                    // fputcsv($file, array(
                    //     "Booking IDs",
                    //     implode(", ", array_column($newSortedData['data5'], 'booking_id'))
                    // ));

                    // Add detailed booking information table
                    fputcsv($file, array($blankLine));
                    fputcsv($file, array("Unpaid Booking Details"));
                    fputcsv($file, array(
                        "Booking ID",
                        "Customer Name",
                        "Customer Email",
                        "Tourists",
                        "Infants",
                        "Total Pax"
                    ));

                    foreach ($newSortedData['data5'] as $booking) {
                        fputcsv($file, array(
                            $booking['booking_id'] ?? '',
                            $booking['customer_name'] ?? '',
                            $booking['customer_email'] ?? '',
                            $booking['tourists'] ?? 0,
                            $booking['infants'] ?? 0,
                            $booking['total_pax'] ?? 0
                        ));
                    }
                }
            }

            fputcsv($file, array($blankLine));
            fputcsv($file, array($blankLine));


            if (isset($newSortedData['tripadvisor'])) {
                fputcsv($file, $tripAdvisorBookingColumns);

                foreach ($newSortedData['tripadvisor'] as $key => $value) {
                    fputcsv($file, array(
                        $value['tripAdvisorPaymentType'],
                        $key,
                        $value['tripAdvisorBookingCount'] ?? 0,
                        $value['tripAdvisorBookingAmount'] ?? 0,
                        $value['tripAdvisorRefundAmout'] ?? 0,
                        $value['tripAdvisorNetAmout'] ?? 0
                    ));
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
    // Daily Activity Report Export End

    /**
     * Daily Activity Card Collection Report Block
     *
     * @param string $fromDate
     * @param string $toDate
     * @param int $tourOperatorId
     * @param int $tourPackageId
     *
     * @return array
     */
    public function dailyActivityCardBlock($cardBlock)
    {
        $cardBlockData = array();

        $cardBlockData['completed_amount'] = $cardBlock['ticketcost'];
        $cardBlockData['ticket_cost_refund'] = $cardBlock['ticketcostrefund'];
        $cardBlockData['ticket_net_amount'] = $cardBlock['ticketnetamount'];

        $cardBlockData['fee_amount'] = $cardBlock['processingfee'];
        $cardBlockData['processing_fees_refund'] = $cardBlock['processingfeerefund'];
        $cardBlockData['processing_fees_net'] = $cardBlock['processingfeenetamount'];

        $cardBlockData['service_commission'] = $cardBlock['platformfee'];
        $cardBlockData['service_commission_refund'] = $cardBlock['platformfeerefund'];
        $cardBlockData['service_commission_net'] = $cardBlock['platformfeenetamount'];

        $cardBlockData['discount'] = $cardBlock['discountamount'];

        $cardBlockData['base_amount'] = 0;

        $cardBlockData['payment_total'] = $cardBlock['paymentamount'];

        $cardBlockData['final_net_total'] = $cardBlock['netamount'];

        return [
            'total_bookings' => count($cardBlock['data']),
            'ticket_cost_total' => $cardBlockData['completed_amount'],
            'ticket_cost_refund_total' => $cardBlockData['ticket_cost_refund'],
            'ticket_cost_net_total' => $cardBlockData['ticket_net_amount'],
            'processing_fees_total' => $cardBlockData['fee_amount'],
            'processing_fees_refund_total' => $cardBlockData['processing_fees_refund'],
            'processing_fees_net_total' => $cardBlockData['processing_fees_net'],
            'platform_fees_total' => $cardBlockData['service_commission'],
            'platform_fees_refund_total' => $cardBlockData['service_commission_refund'],
            'platform_fees_net_total' => $cardBlockData['service_commission_net'],
            'discount_total' => $cardBlockData['discount'],
            'payment_total_amount' => $cardBlockData['payment_total'],
            'final_net_total' => $cardBlockData['final_net_total']
        ];
    }

    /**
     * Daily Activity Card Farharbor Collection Report Block
     *
     * @param string $fromDate
     * @param string $toDate
     * @param int $tourOperatorId
     * @param int $tourPackageId
     *
     * @return array
     */
    public function dailyActivityCardFarharborBlock($cardFarharborBlock)
    {
        $cardBlockData = array();

        $cardBlockData['completed_amount'] = $cardFarharborBlock['farharborticketcost'];
        $cardBlockData['ticket_cost_refund'] = $cardFarharborBlock['farharborticketcostrefund'];
        $cardBlockData['ticket_net_amount'] = $cardFarharborBlock['farharborticketnetamount'];

        $cardBlockData['fee_amount'] = $cardFarharborBlock['farharborprocessingfee'];
        $cardBlockData['processing_fees_refund'] = $cardFarharborBlock['farharborprocessingfeerefund'];
        $cardBlockData['processing_fees_net'] = $cardFarharborBlock['farharborprocessingfeenetamount'];

        $cardBlockData['service_commission'] = $cardFarharborBlock['farharborplatformfee'];
        $cardBlockData['service_commission_refund'] = $cardFarharborBlock['farharborplatformfeerefund'];
        $cardBlockData['service_commission_net'] = $cardFarharborBlock['farharborplatformfeenetamount'];

        $cardBlockData['discount'] = $cardFarharborBlock['farharbordiscountamount'];

        $cardBlockData['base_amount'] = 0;

        $cardBlockData['payment_total'] = $cardFarharborBlock['farharborpaymentamount'];

        $cardBlockData['final_net_total'] = $cardFarharborBlock['farharbornetamount'];

        return [
            'total_bookings' => count($cardFarharborBlock['farharbor_data']),
            'ticket_cost_total' => $cardBlockData['completed_amount'],
            'ticket_cost_refund_total' => $cardBlockData['ticket_cost_refund'],
            'ticket_cost_net_total' => $cardBlockData['ticket_net_amount'],
            'processing_fees_total' => $cardBlockData['fee_amount'],
            'processing_fees_refund_total' => $cardBlockData['processing_fees_refund'],
            'processing_fees_net_total' => $cardBlockData['processing_fees_net'],
            'platform_fees_total' => $cardBlockData['service_commission'],
            'platform_fees_refund_total' => $cardBlockData['service_commission_refund'],
            'platform_fees_net_total' => $cardBlockData['service_commission_net'],
            'discount_total' => $cardBlockData['discount'],
            'payment_total_amount' => $cardBlockData['payment_total'],
            'final_net_total' => $cardBlockData['final_net_total']
        ];
    }

    /**
     * Daily Activity Cash Collection Report Block
     *
     * @param string $fromDate
     * @param string $toDate
     * @param int $tourOperatorId
     * @param int $tourPackageId
     *
     * @return array
     */
    public function dailyActivityCashBlock($fromDate, $toDate, $tourOperatorId, $tourPackageId, $viewBy): array
    {
        $request = new Request([
            'dateFrom' => $fromDate,
            'dateTo' => $toDate,
            'tourOperator' => $tourOperatorId,
            'tourPackage' => $tourPackageId,
            'viewDate' => $viewBy
        ]);

        $cashBlock = $this->cashcollection($request);

        $cashBlockData = array();

        $cashBlockData['completed_amount'] = $cashBlock['alltotalamount'];
        $cashBlockData['ticket_cost_refund'] = $cashBlock['allrefundamount'];
        $cashBlockData['ticket_net_amount'] = $cashBlock['allpaymentamount'];

        $cashBlockData['payment_total'] = $cashBlock['allpaymentamount'];

        $cashBlockData['final_net_total'] = $cashBlock['allpaymentamount'];

        $count = 0;
        foreach ($cashBlock as $value) {
            if (isset($value['data'])) {
                $count += count($value['data']);
            }
        }

        return [
            'total_bookings' => $count,
            'ticket_cost_total' => $cashBlockData['completed_amount'],
            'ticket_cost_refund_total' => $cashBlockData['ticket_cost_refund'],
            'ticket_cost_net_total' => $cashBlockData['ticket_net_amount'],
            'processing_fees_total' => 0,
            'processing_fees_refund_total' => 0,
            'processing_fees_net_total' => 0,
            'platform_fees_total' => 0,
            'platform_fees_refund_total' => 0,
            'platform_fees_net_total' => 0,
            'discount_total' => 0,
            'payment_total_amount' => $cashBlockData['payment_total'],
            'final_net_total' => $cashBlockData['final_net_total']
        ];
    }

    /**
     * Daily Activity Invoiced Collection Report Block
     *
     * @param string $fromDate
     * @param string $toDate
     * @param int $tourOperatorId
     * @param int $tourPackageId
     *
     * @return array
     */
    public function dailyActivityInvoicedBlock($fromDate, $toDate, $tourOperatorId, $tourPackageId, $viewBy): array
    {
        $via = "Invoiced";

        $userType = userType();

        /* START - PAYMENT / REFUND COLLECTION QUERY FOR CASH */
        $paymentSql = 'SELECT
            DISTINCT `tbh`.`id`,
            `tbm`.`id` as `tour_booking_id`,
            `tbh`.`operation`,
            `tbm`.`status` as `status`,
            `tbm`.`comments`,
            `tbm`.`created_by` as `createdBy`,
            `tbm`.`created_by_user_type` as `TourUserType`,
            json_unquote(json_extract(`tbh`.`details`, "$.created_by_with_name")) as created_by_with_name,
            CASE operation
                WHEN "Payment" THEN `tbh`.`ticket_cost`
                ELSE 0
            END as "completed_amount",
            CASE operation
                WHEN "Refund" THEN `tbh`.`ticket_cost`
                WHEN "Cancelled" THEN `tbh`.`ticket_cost`
                ELSE 0
            END as "refund_amount",
            `tbh`.`discount`,
            ifnull(`tbh`.`payment_type`, 0) as `via`,
            ifnull(`tbh`.`payment_method`, 0) as `method`,
            DATE_FORMAT(`tbm`.`created_at`, "%Y-%m-%d") as `created_at`,
            `tbm`.`name` as `customer`,
            `tbm`.`email`,
            `tbm`.`phone_number`,
            `tst`.`time_from`,
            `tst`.`time_to`,
            `tsd`.`date`,
            `tbm`.`tourists`,
            `tbm`.`infants`
        from
            `tour_booking_history` as `tbh`
            inner join `tour_bookings_master` as `tbm` on `tbm`.`id` = `tbh`.`tour_booking_id`
            inner join `tour_slot_times` as `tst` on `tst`.`id` = `tbm`.`tour_slot_id`
            inner join `tour_slot_dates` as `tsd` on `tsd`.`id` = `tst`.`tour_slot_id`
            inner join `tour_packages` as `tp` on `tp`.`id` = `tbm`.`tour_package_id`';

        $paymentSql .= ' where `tbh`.`payment_type` = "' . $via . '" ';

        $paymentSql .= ' and `tbh`.`operation` in ("Payment", "Refund", "Cancelled") ';

        if (isset($viewBy)) {
            $viewDate = ($viewBy == 'purchasedate') ? '`tbh`.`operation_at`' : '`tsd`.`date`';
            $paymentSql .= ' and ' . $viewDate . ' between "' . $fromDate . '" and "' . $toDate . '"';
        } else {
            $paymentSql .= ' and `tsd`.`date` between "' . $fromDate . '" and "' . $toDate . '"';
        }
        // $paymentSql .= ' and `tsd`.`date` between "'.$fromDate.'" and "'.$toDate.'"';

        if ($tourPackageId) {
            $paymentSql .= ' and `tp`.`id` = "' . $tourPackageId . '" ';
        }

        if ($userType == 'Tour Operator') {
            $paymentSql .= ' and `tp`.`tour_operator_id` = "' . auth()->user()->tour_operator_id . '" ';
        } else {
            $paymentSql .= ' and `tp`.`tour_operator_id` = "' . $tourOperatorId . '" ';
        }


        $invoicedPaymentData = DB::select($paymentSql);
        /* END - PAYMENT / REFUND COLLECTION QUERY FOR CASH */

        $ticketCostAmount = 0;
        $ticketRefundAmount = 0;
        $discountAmount = 0;
        $paymentTotal = 0;

        foreach ($invoicedPaymentData as $key => $value) {

            $invoiced = DB::table('invoices as i')
                ->join('invoice_bookings as ib', 'ib.invoice_id', '=', 'i.id')
                ->where('ib.tour_booking_id', $value->tour_booking_id)->first();
            if ($invoiced == null) {
                unset($invoicedPaymentData[$key]);
            }
            if ($value->operation == "Payment" && $invoiced != null) {
                $ticketCostAmount = $ticketCostAmount + $value->completed_amount;
            }
            if ($value->operation == "Refund" && $invoiced != null) {
                $ticketRefundAmount = $ticketRefundAmount + $value->refund_amount;
            }
            if ($value->operation == "Cancelled" && $invoiced != null) {
                $ticketRefundAmount = $ticketRefundAmount + $value->refund_amount;
            }

            if ($invoiced != null) {
                $discountAmount = $discountAmount + $value->discount;
            }
        }

        $invoicedBookingCount = count(array_unique(array_column($invoicedPaymentData, 'tour_booking_id')));
        $ticketNetAmount = $ticketCostAmount - $ticketRefundAmount;

        return [
            'total_bookings' => $invoicedBookingCount,
            'ticket_cost_total' => $ticketCostAmount,
            'ticket_cost_refund_total' => $ticketRefundAmount,
            'ticket_cost_net_total' => $ticketNetAmount,
            'processing_fees_total' => 0,
            'processing_fees_refund_total' => 0,
            'processing_fees_net_total' => 0,
            'platform_fees_total' => 0,
            'platform_fees_refund_total' => 0,
            'platform_fees_net_total' => 0,
            'discount_total' => $discountAmount,
            'payment_total_amount' => $ticketCostAmount,
            'final_net_total' => $ticketNetAmount
        ];
    }

    /**
     * Daily Activity Invoiced Billable Collection Report Block
     *
     * @param string $fromDate
     * @param string $toDate
     * @param int $tourOperatorId
     * @param int $tourPackageId
     *
     * @return array
     */
    public function dailyActivityInvoicedBillableBlock($fromDate, $toDate, $tourOperatorId, $tourPackageId, $viewBy): array
    {
        $via = "Invoiced";

        $userType = userType();

        /* START - PAYMENT / REFUND COLLECTION QUERY FOR CASH */
        $paymentSql = 'SELECT
            `tbm`.`id` as `tour_booking_id`,
            `tbh`.`operation`,
            `tbm`.`status` as `status`,
            `tbm`.`comments`,
            `tbm`.`created_by` as `createdBy`,
            `tbm`.`created_by_user_type` as `TourUserType`,
            json_unquote(json_extract(`tbh`.`details`, "$.created_by_with_name")) as created_by_with_name,
            CASE operation
                WHEN "Payment" THEN `tbh`.`ticket_cost`
                ELSE 0
            END as "completed_amount",
            CASE operation
                WHEN "Refund" THEN `tbh`.`ticket_cost`
                WHEN "Cancelled" THEN `tbh`.`ticket_cost`
                ELSE 0
            END as "refund_amount",
            `tbh`.`discount`,
            ifnull(`tbh`.`payment_type`, 0) as `via`,
            ifnull(`tbh`.`payment_method`, 0) as `method`,
            DATE_FORMAT(`tbm`.`created_at`, "%Y-%m-%d") as `created_at`,
            `tbm`.`name` as `customer`,
            `tbm`.`email`,
            `tbm`.`phone_number`,
            `tst`.`time_from`,
            `tst`.`time_to`,
            `tsd`.`date`,
            `tbm`.`tourists`,
            `tbm`.`infants`
        from
            `tour_booking_history` as `tbh`
            inner join `tour_bookings_master` as `tbm` on `tbm`.`id` = `tbh`.`tour_booking_id`
            inner join `tour_slot_times` as `tst` on `tst`.`id` = `tbm`.`tour_slot_id`
            inner join `tour_slot_dates` as `tsd` on `tsd`.`id` = `tst`.`tour_slot_id`
            inner join `tour_packages` as `tp` on `tp`.`id` = `tbm`.`tour_package_id`';
        /*inner join `transactions` as `t` on `t`.`tour_booking_id` = `tbm`.`id` and `t`.`status` = "Completed"';*/

        $paymentSql .= ' where `tbh`.`payment_type` = "' . $via . '" ';

        $paymentSql .= ' and `tbh`.`operation` in ("Payment", "Refund", "Cancelled") ';

        if (isset($viewBy)) {
            $viewDate = ($viewBy == 'purchasedate') ? '`tbh`.`operation_at`' : '`tsd`.`date`';
            $paymentSql .= ' and ' . $viewDate . ' between "' . $fromDate . '" and "' . $toDate . '"';
        } else {
            $paymentSql .= ' and `tsd`.`date` between "' . $fromDate . '" and "' . $toDate . '"';
        }
        // $paymentSql .= ' and `tsd`.`date` between "'.$fromDate.'" and "'.$toDate.'"';

        if ($tourPackageId) {
            $paymentSql .= ' and `tp`.`id` = "' . $tourPackageId . '" ';
        }

        if ($userType == 'Tour Operator') {
            $paymentSql .= ' and `tp`.`tour_operator_id` = "' . auth()->user()->tour_operator_id . '" ';
        } else {
            $paymentSql .= ' and `tp`.`tour_operator_id` = "' . $tourOperatorId . '" ';
        }


        $invoicedPaymentData = DB::select($paymentSql);
        /* END - PAYMENT / REFUND COLLECTION QUERY FOR CASH */

        $ticketCostAmount = 0;
        $ticketRefundAmount = 0;
        $discountAmount = 0;
        $paymentTotal = 0;
        $bookingSummary = [];
        foreach ($invoicedPaymentData as $key => $value) {

            $invoiced = DB::table('invoices as i')
                ->join('invoice_bookings as ib', 'ib.invoice_id', '=', 'i.id')
                ->where('ib.tour_booking_id', $value->tour_booking_id)->first();

            if ($invoiced != null) {
                unset($invoicedPaymentData[$key]);
            }

            if ($value->operation == "Payment" && $invoiced == null) {
                $ticketCostAmount = $ticketCostAmount + $value->completed_amount;
            }
            if ($value->operation == "Refund" && $invoiced == null) {
                $ticketRefundAmount = $ticketRefundAmount + $value->refund_amount;
            }
            if ($value->operation == "Cancelled" && $invoiced == null) {
                $ticketRefundAmount = $ticketRefundAmount + $value->refund_amount;
            }

            if ($invoiced == null) {
                $discountAmount = $discountAmount + $value->discount;
            }
        }
        $invoicedBookingCount = count(array_unique(array_column($invoicedPaymentData, 'tour_booking_id')));
        $ticketNetAmount = $ticketCostAmount - $ticketRefundAmount;

        return [
            'total_bookings' => $invoicedBookingCount,
            'ticket_cost_total' => $ticketCostAmount,
            'ticket_cost_refund_total' => $ticketRefundAmount,
            'ticket_cost_net_total' => $ticketNetAmount,
            'processing_fees_total' => 0,
            'processing_fees_refund_total' => 0,
            'processing_fees_net_total' => 0,
            'platform_fees_total' => 0,
            'platform_fees_refund_total' => 0,
            'platform_fees_net_total' => 0,
            'discount_total' => $discountAmount,
            'payment_total_amount' => $ticketCostAmount,
            'final_net_total' => $ticketNetAmount
        ];
    }

    /**
     * Daily Activity Check/Wire Collection Report Block
     *
     * @param string $fromDate
     * @param string $toDate
     * @param int $tourOperatorId
     * @param int $tourPackageId
     *
     * @return array
     */
    public function dailyActivityCheckWireBlock($fromDate, $toDate, $tourOperatorId, $tourPackageId, $viewBy): array
    {
        $via = "Check\/Wire";

        $userType = userType();

        if (isset($viewBy)) {
            $viewDate = ($viewBy == 'purchasedate') ? '`tbh`.`operation_at`' : '`tsd`.`date`';
            $checkWireSql = ' and ' . $viewDate . ' between  "' . $fromDate . ' 00:00:00" and "' . $toDate . ' 23:59:59" ';
        } else {
            $checkWireSql = ' and `tbh`.`operation_at` between  "' . $fromDate . ' 00:00:00" and "' . $toDate . ' 23:59:59" ';
        }

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
            `tbm`.`booking_total`,
            `tbm`.`tourists`,
            `tbm`.`infants`
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
            ' . $checkWireSql . ' ';

        /* and `tbh`.`operation_at` between  "'.$fromDate.' 00:00:00" and "'.$toDate.' 23:59:59" */

        /* and `tbh`.`operation_at` between  "'.$fromDate.' 00:00:00" and "'.$toDate.' 23:59:59" */

        if ($tourPackageId) {
            $refundTotalSql .= ' and `tp`.`id` = "' . $tourPackageId . '" ';
        }

        if ($userType == 'Tour Operator') {
            $refundTotalSql .= ' and `tp`.`tour_operator_id` = "' . auth()->user()->tour_operator_id . '" ';
        } else {
            $refundTotalSql .= ' and `tp`.`tour_operator_id` = "' . $tourOperatorId . '" ';
        }

        $paymentTotalSql = $refundTotalSql . ' GROUP BY `tbm`.`id`';


        $checkWireRefundData = DB::select($paymentTotalSql);

        // count the total number of bookings
        $checkWireBookingCount = count($checkWireRefundData);

        // total refund amount based on refund amount for card booking only
        $refundTotal = DB::select('select ifnull(sum(aps.refund_amount), 0) as check_wire_refund_amount from (' . $refundTotalSql . ') as aps');

        return [
            'total_bookings' => $checkWireBookingCount,
            'ticket_cost_total' => 0,
            'ticket_cost_refund_total' => $refundTotal[0]->check_wire_refund_amount,
            'ticket_cost_net_total' => 0,
            'processing_fees_total' => 0,
            'processing_fees_refund_total' => 0,
            'processing_fees_net_total' => 0,
            'platform_fees_total' => 0,
            'platform_fees_refund_total' => 0,
            'platform_fees_net_total' => 0,
            'discount_total' => 0,
            'payment_total_amount' => 0 - $refundTotal[0]->check_wire_refund_amount,
            'final_net_total' => 0
        ];
    }

    /**
     * Check/Wire collection report
     * Get the refunds of all payments
     *
     * @param Request $request
     */
    public function wireCollection($request)
    {
        try {
            // $request->validate($this->requestValidation);
            $userType = userType();

            if ($userType == 'Admin') {
                $tourOperatorId = $request->tourOperator;
            } else {
                $tourOperatorId = auth()->user()->tour_operator_id;
            }

            $via = "Check/Wire";

            /* START - PAYMENT / REFUND COLLECTION QUERY FOR CASH */
            $wireSql = 'select
                `tbm`.`id` as `tour_booking_id`,
                `tbh`.`operation`,
                `tbm`.`status` as `status`,
                `tbm`.`comments`,
                `tbm`.`created_by` as `createdBy`,
                `tbm`.`created_by_user_type` as `TourUserType`,
                json_unquote(json_extract(`tbh`.`details`, "$.created_by_with_name")) as created_by_with_name,
                CASE
                    WHEN LOCATE("#", `tbm`.`comments`) > 0 THEN `tbh`.`ticket_cost`
                    ELSE 0
                END as "fareharbor_refund",
                CASE
                    WHEN LOCATE("#", `tbm`.`comments`) = 0 THEN `tbh`.`ticket_cost`
                    ELSE 0
                END as "normal_refund",
                ifnull(`tbh`.`payment_type`, 0) as `via`,
                ifnull(`tbh`.`payment_method`, 0) as `method`,
                DATE_FORMAT(`tbm`.`created_at`, "%Y-%m-%d") as `created_at`,
                CONCAT(
                    `tos`.`first_name`, " ", `tos`.`last_name`
                  ) as `tour_operator_staff_name`,
                `tbm`.`name` as `customer`,
                `tbm`.`email`,
                `tbm`.`phone_number`,
                `tst`.`time_from`,
                `tst`.`time_to`,
                `tsd`.`date`
            from
                `tour_booking_history` as `tbh`
                inner join `tour_bookings_master` as `tbm` on `tbm`.`id` = `tbh`.`tour_booking_id`
                inner join `tour_slot_times` as `tst` on `tst`.`id` = `tbm`.`tour_slot_id`
                inner join `tour_slot_dates` as `tsd` on `tsd`.`id` = `tst`.`tour_slot_id`
                inner join `tour_packages` as `tp` on `tp`.`id` = `tbm`.`tour_package_id`
                inner join `tour_operator_staff` as `tos` on `tos`.`id` = `tbh`.`operation_by`';

            $wireSql .= ' where `tbh`.`payment_type` = "' . $via . '" ';

            $wireSql .= ' and `tbh`.`operation` in ("Refund", "Cancelled") ';

            if (isset($request->viewDate)) {
                $viewDate = ($request->viewDate == 'purchasedate') ? '`tbh`.`operation_at`' : '`tsd`.`date`';
                $request->dateFrom = ($request->viewDate == 'purchasedate') ? $request->dateFrom . ' 00:00:00' : $request->dateFrom;
                $request->dateTo = ($request->viewDate == 'purchasedate') ? $request->dateTo . ' 23:59:59' : $request->dateTo;
                $wireSql .= ' and ' . $viewDate . ' between "' . $request->dateFrom . '" and "' . $request->dateTo . '"';
            } else {
                $wireSql .= ' and `tbh`.`operation_at` between "' . $request->dateFrom . ' 00:00:00" and "' . $request->dateTo . ' 23:59:59"';
            }
            // $wireSql .= ' and `tbh`.`operation_at` between "'.$request->dateFrom.' 00:00:00" and "'.$request->dateTo.' 23:59:59"';

            if ($request->tourPackage) {
                $wireSql .= ' and `tp`.`id` = "' . $request->tourPackage . '" ';
            }

            $wireSql .= ' and `tp`.`tour_operator_id` = "' . $tourOperatorId . '" ';

            $wireData = DB::select($wireSql);
            /* END - PAYMENT / REFUND COLLECTION QUERY FOR CASH */

            $refundData = array();
            foreach ($wireData as $row) {
                if (isset($refundData[$row->tour_booking_id])) {
                    $refundData[$row->tour_booking_id]->fareharbor_refund += $row->fareharbor_refund;
                    $refundData[$row->tour_booking_id]->normal_refund += $row->normal_refund;
                } else {
                    $refundData[$row->tour_booking_id] = $row;
                }
            }

            $result = [];
            $totalRefund = 0;
            $totalNormalRefund = 0;
            $totalFareharborRefund = 0;

            foreach ($refundData as $element) {
                $totalRefund = $totalRefund + $element->normal_refund + $element->fareharbor_refund;
                $totalNormalRefund = $totalNormalRefund + $element->normal_refund;
                $totalFareharborRefund = $totalFareharborRefund + $element->fareharbor_refund;
                $element->time_from = date("g:i A", strtotime($element->time_from));
                $element->time_to = date("g:i A", strtotime($element->time_to));
                $result['data'][] = $element;
            }

            $result['totalRefund'] = $totalRefund;
            $result['totalNormalRefund'] = $totalNormalRefund;
            $result['totalFareharborRefund'] = $totalFareharborRefund;

            if ($request->export) {
                return $this->exportWireCollectionReport($request, $result);
            }

            return response()->json($result);
        } catch (Exception $exception) {
            Log::error($exception);
        }
    }

    /**
     * Export
     * Wire Collection Report
     *
     * @param object $request
     * @param array $response
     */
    public function exportWireCollectionReport($request, $result)
    {
        $fileName = 'wire_collection_report' . $request->get('currentDate') . '_' . $request->get('toDate') . '.csv';
        $checkYear = date("Y", strtotime($request->dateTo));
        if ($checkYear != "2022") {
            $columns = csvColumns('wire_collection_report_columns');
            $columnsOverallAmount = csvColumns('wire_collection_report_columnsHeader_overall');
        } else {
            $columns = csvColumns('wire_collection_report_columns_fareharbor');
            $columnsOverallAmount = csvColumns('wire_collection_report_columnsHeader_overall_fareharbor');
        }

        $tourOperatorName = $this->GetTourOperatorName($request->get('tourOperator'), ($request->has('tourPackage')) ? $request->get('tourPackage') : 0);
        $headers = csvHeaders($fileName);

        $callback = function () use ($result, $columns, $columnsOverallAmount, $request, $tourOperatorName) {

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
                fputcsv($file, array($BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine));
            }
            fputcsv($file, $columnsOverallAmount);

            $dateRange = date("m-d-Y", strtotime($request->dateFrom)) . ' - ' . date("m-d-Y", strtotime($request->dateTo));
            $createdDate = date("m-d-Y");
            $viewBy = ($request->viewDate == "purchasedate") ? "Purchase Date" : "Tour Date";
            $checkYear = date("Y", strtotime($request->dateTo));

            $totalNormalRefund = sprintf('%.2f', $result['totalNormalRefund']);
            $totalFareharborRefund = sprintf('%.2f', $result['totalFareharborRefund']);
            $totalRefund = sprintf('%.2f', $result['totalRefund']);
            if ($checkYear != "2022") {
                fputcsv($file, array($dateRange, $createdDate, $viewBy, $totalNormalRefund, $totalRefund));
            } else {
                fputcsv($file, array($dateRange, $createdDate, $viewBy, $totalNormalRefund, $totalFareharborRefund, $totalRefund));
            }

            $blankLine = '';
            fputcsv($file, array($blankLine));
            fputcsv($file, $columns);

            if (isset($result['data'])) {
                foreach ($result['data'] as $eachData) {
                    $row['Booking Id'] = $eachData->tour_booking_id ?? '';
                    $row['Booking Date'] = isset($eachData->date) ? date("m-d-Y", strtotime($eachData->date)) . " @ " . date("g:i A", strtotime($eachData->time_from)) : '';
                    $row['Created Date'] = isset($eachData->created_at) ? date("m-d-Y", strtotime($eachData->created_at)) : '';
                    $row['Via'] = $eachData->via ?? '';
                    $row['Normal Refund'] = $eachData->normal_refund ?? '';
                    if ($checkYear == "2022") {
                        $row['Fareharbor Refund'] = $eachData->fareharbor_refund ?? '';
                    }
                    $row['Customer'] = $eachData->customer ?? '';
                    $row['Comment'] = $eachData->comments ?? '';
                    $row['Booking Status'] = $eachData->status ?? '';

                    if ($checkYear != "2022") {
                        fputcsv($file, array(
                            $row['Booking Id'],
                            $row['Booking Date'],
                            $row['Created Date'],
                            $row['Via'],
                            $row['Normal Refund'],
                            $row['Customer'],
                            $row['Comment'],
                            $row['Booking Status']
                        ));
                    } else {
                        fputcsv($file, array(
                            $row['Booking Id'],
                            $row['Booking Date'],
                            $row['Created Date'],
                            $row['Via'],
                            $row['Normal Refund'],
                            $row['Fareharbor Refund'],
                            $row['Customer'],
                            $row['Comment'],
                            $row['Booking Status']
                        ));
                    }
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // Affiliate Booked Report
    public function affiliatereport(Request $request, string $fromDate = "", string $toDate = "", int $customerId = 0,  $tourOperatorId = 0, $tourPackageId = 0, int $export = 0)
    {
        try {
            $userType = userType();

            $fromDate = $fromDate ?: date('Y-m-d') . " 00:00:00";
            $toDate = $toDate ?: date('Y-m-d') . " 23:59:59";
            $tourOperatorId = ($userType == 'Tour Operator' || $userType == 'Affiliate') ? auth()->user()->tour_operator_id : $tourOperatorId;

            $qry = DB::table('tour_bookings_master AS tbm')
                ->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
                ->join('customers as c', 'c.id', '=', 'tbm.customer_id')
                ->join('tour_bookings_transact AS tbt', 'tbt.tour_booking_id', 'tbm.id')
                ->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
                ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
                ->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
                ->leftJoin('transactions as t', 't.tour_booking_id', '=', 'tbm.id')
                ->where('tbm.status', 'Booked')
                ->where('c.is_affiliate', 'Yes')
                ->where('tbt.deleted_by', 0)
                ->whereBetween('tsd.date', [$fromDate, $toDate])
                ->where('tp.tour_operator_id', '=', $tourOperatorId)
                ->when($tourPackageId && $tourPackageId !== 0, function ($query) use ($tourPackageId) {
                    return $query->where('tbm.tour_package_id', '=', $tourPackageId);
                })
                ->when($customerId !== 0, function ($query) use ($customerId) {
                    return $query->where('c.id', '=', $customerId);
                })
                ->select(
                    'tbm.id as bookingid',
                    'tbm.status as status',
                    'tbm.total as amount',
                    'tbm.booking_total',
                    DB::raw('(tbm.tourists + tbm.infants) as overall_tourists'),
                    DB::raw('DATE_FORMAT(tbm.created_at, "%Y-%m-%d") as created_at'),
                    DB::raw('DATE_FORMAT(tsd.date, "%Y-%m-%d") as bookingdate'),
                    'tbm.name as name',
                    'c.name as affiliate_name',
                    'tp.name as package',
                    DB::raw('CASE 
                    WHEN tbt.infants > 0 THEN tbt.infants
                    ELSE tbt.tourists
                    END AS tourists'),
                    'tpr.rate_for',
                    't.status as payment_status',
                    'tst.time_from',
                    'tst.time_to',
                    'tbm.is_group_booking as is_group_booking',
                    'tbt.nat_fees_percentage',
                    DB::raw('(SELECT SUM(CASE status WHEN "Open" THEN `tbcc`.`amount` ELSE 0 END) FROM tour_booking_custom_charges AS tbcc
                        WHERE tbcc.tour_booking_id = tbm.id) as "unpaid_custom_charges"'),
                    // Add-ons attached AFTER a payment already exists on this booking (e.g. added
                    // via "Booking Add-ons" as pay-later) get their own 'open' placeholder row in
                    // tour_booking_addon_transactions immediately (see chargeAddonFields()) — unlike
                    // a brand-new all-pay-later booking, which has no transaction row at all yet and
                    // is instead covered by fetchPayLaterAddonsWithPrice() below. Mirrors the exact
                    // join/filters AddonBookingService uses to find still-owed addon rows.
                    DB::raw("(SELECT SUM(tbat.field_price + tbat.option_price) FROM tour_booking_addon_transactions AS tbat
                        JOIN tour_booking_addon_fields AS tbaf ON tbaf.id = tbat.tour_booking_addon_id
                        WHERE tbat.tour_booking_id = tbm.id AND tbat.status = 'open' AND tbat.operation = 'charge'
                        AND tbat.deleted_at IS NULL AND tbaf.deleted_at IS NULL) as unpaid_addon_charges")
                )
                ->orderBy('tsd.date', 'ASC')->orderBy('tst.time_from', 'ASC');

            $result = $qry->distinct()->get();

            $packageRates = $this->tourPackageRates($result, 0);

            // Common calculation function
            $packageRatesArr = $this->affiliateReportsCalculation($fromDate, $toDate, $tourPackageId, $userType, $tourOperatorId, $customerId, $packageRates, "booked");
            // Overall Calculation Function
            $sortedData = $this->affiliateReportOverallCalculation($packageRatesArr, $fromDate, $toDate, $tourOperatorId, $customerId, "booked", $request);

            $all_total = [];
            if ($request->api == 1) {
                $all_total['booking_amount'] = (float) $sortedData['booking_amount'];
                $all_total['unpaid_amount'] = (float) $sortedData['unpaid_amount'];
                $all_total['paid_amount'] = (float) $sortedData['paid_amount'];
                $all_total['refund_amount'] = (float) $sortedData['refund_amount'];
                $all_total['total_tourists'] = $sortedData['total_tourists'];

                unset($sortedData['booking_amount']);
                unset($sortedData['unpaid_amount']);
                unset($sortedData['paid_amount']);
                unset($sortedData['refund_amount']);
                unset($sortedData['total_tourists']);

                $sortedData = array_values($sortedData);

                return ['details' => $sortedData, 'all_total' => $all_total];
            }


            if ($export) {
                return $this->AffiliateReportExportCsvFile($fromDate, $toDate, $sortedData, $tourOperatorId, $tourPackageId);
            }

            return $sortedData;
        } catch (Exception $exception) {
            Log::error($exception);
            return false;
        }
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
                $initialArray[$bookingId]['TouristTotal'] = $rateGroup->transact_tourists ?? $rateGroup->overall_tourists;
                $initialArray[$bookingId]['InfantsTotal'] = $rateGroup->transact_infants ?? ($rateGroup->infants ?? 0);
                $initialArray[$bookingId]['bookingid'] = $bookingId;
                $initialArray[$bookingId]['name'] = $rateGroup->name;
                $initialArray[$bookingId]['package'] = $rateGroup->package;
            }

            if ($rateGroup->is_group_booking == '1') {
                $rateGrpText = $rateGroup->tourists . ' ' . 'Person(s)';
            } else {
                $rateGrpText = $rateGroup->tourists . ' ' . $rateGroup->rate_for;
            }

            if ($rateGroup->tourists > 0 && !in_array($rateGrpText, $initialArray[$bookingId]['rate'] ?? [])) {
                $initialArray[$bookingId]['rate'][] = $rateGrpText;
            }
        }

        return $initialArray;
    }

    public function affiliateReportsCalculation($fromDate, $toDate, $tourPackageId, $userType, $tourOperatorId, $customerId, $packageRatesArr, $affiliate_report)
    {
        $resultData = DB::table('tour_bookings_master AS tbm')
            ->leftJoin('tour_booking_history as tbh', 'tbh.tour_booking_id', '=', 'tbm.id')
            ->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
            ->join('customers as c', 'c.id', '=', 'tbm.customer_id')
            ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
            ->leftJoin('transactions as t', 't.tour_booking_id', '=', 'tbm.id');

        if ($affiliate_report != "comprehensive") {
            $resultData = $resultData->where('tbm.status', 'Booked');
        }

        $resultData = $resultData->where('c.is_affiliate', 'Yes')
            ->whereBetween('tsd.date', [$fromDate, $toDate])
            ->where('tp.tour_operator_id', '=', $tourOperatorId)
            ->when($tourPackageId && $tourPackageId !== 0, function ($query) use ($tourPackageId) {
                return $query->where('tbm.tour_package_id', '=', $tourPackageId);
            })
            ->when($customerId !== 0, function ($query) use ($customerId) {
                return $query->where('c.id', '=', $customerId);
            });
        $resultData = $resultData->distinct()->pluck('tbm.id')->toArray();

        $history = DB::table('tour_booking_history as tbh')
            ->whereIn('tbh.tour_booking_id', $resultData)
            ->where('tbh.operation', 'Payment');

        if ($affiliate_report == "invoice") {
            $history = $history->where('tbh.payment_type', 'Invoiced');
        }

        $history = $history->get();
        foreach ($history as $key => $historyData) {
            $details = json_decode($historyData->details);
            $details->amount = str_replace(',', '', $details->amount);
            $details->amount = str_replace('?', '', $details->amount);

            // if($affiliate_report == "comprehensive"){
            if (isset($details->payment_type)) {
                $packageRatesArr[$historyData->tour_booking_id]['payment_datetime'] = date("m/d/Y g:i: A", strtotime($historyData->operation_at));
                $packageRatesArr[$historyData->tour_booking_id]['payment_type'] = $details->payment_type;
                if ($details->payment_type == 'Invoiced') {
                    if (!isset($packageRatesArr[$historyData->tour_booking_id]['invoiced_amount'])) {
                        $packageRatesArr[$historyData->tour_booking_id]['invoiced_amount'] = 0;
                    }
                    $packageRatesArr[$historyData->tour_booking_id]['invoiced_amount'] += (float)$details->amount;
                }
                if ($details->payment_type == 'Cash') {
                    if (!isset($packageRatesArr[$historyData->tour_booking_id]['cash_amount'])) {
                        $packageRatesArr[$historyData->tour_booking_id]['cash_amount'] = 0;
                    }
                    $packageRatesArr[$historyData->tour_booking_id]['cash_amount'] += (float)$details->amount;
                }
                if ($details->payment_type == 'Card') {
                    if (!isset($packageRatesArr[$historyData->tour_booking_id]['card_amount'])) {
                        $packageRatesArr[$historyData->tour_booking_id]['card_amount'] = 0;
                    }
                    $packageRatesArr[$historyData->tour_booking_id]['card_amount'] += (float)$details->amount;
                }
            }
            // }
            if (!isset($packageRatesArr[$historyData->tour_booking_id]['payment_amount'])) {
                $packageRatesArr[$historyData->tour_booking_id]['payment_amount'] = 0;
            }
            $packageRatesArr[$historyData->tour_booking_id]['payment_amount'] += (float)$details->amount;
            if (!isset($packageRatesArr[$historyData->tour_booking_id]['custom_charge'])) {
                $packageRatesArr[$historyData->tour_booking_id]['custom_charge'] = 0;
            }
            if ($historyData->charge_type == 'custom_charge' || $historyData->charge_type == 'addons') {
                $packageRatesArr[$historyData->tour_booking_id]['custom_charge'] += (float)$details->amount;
            }
        }

        $refundHistory = DB::table('tour_booking_history as tbh')
            ->whereIn('tbh.tour_booking_id', $resultData);

        if ($affiliate_report == "invoice") {
            $refundHistory = $refundHistory->where('tbh.payment_type', 'Invoiced')
                ->where('tbh.ticket_cost', '>', 0);
        }
        if ($affiliate_report == "comprehensive") {
            $refundHistory = $refundHistory->where(function ($query) {
                return $query->where('operation', '=', 'Refund')
                    ->orWhere('operation', '=', 'Cancelled');
            });
        } else {
            $refundHistory = $refundHistory->where('tbh.operation', 'Refund');
        }
        $refundHistory = $refundHistory->where(function ($query) {
            return $query->whereJsonDoesntContain('tbh.details->refund_type', "Don't Refund")
                ->whereJsonDoesntContain('tbh.details->refund_type', "don't refund")
                ->whereJsonDoesntContain('tbh.details->refund_type', "fareharbor : don't refund");
        })->get();

        foreach ($refundHistory as $key => $historyData) {
            $details = json_decode($historyData->details);
            $details->amount = str_replace(',', '', $details->amount);
            $details->amount = str_replace('?', '', $details->amount);

            if (!isset($packageRatesArr[$historyData->tour_booking_id]['refundamount'])) {
                $packageRatesArr[$historyData->tour_booking_id]['refundamount'] = 0;
            }
            if (isset($details->from) && $details->from == 'Extend Booking' && isset($details->payment_type) && $details->payment_type == '') {
                $details->amount = 0;
            }
            $packageRatesArr[$historyData->tour_booking_id]['refundamount'] += (float)$details->amount;
        }

        // Execute this function only for Comprehensive Report
        if ($affiliate_report == "comprehensive") {
            foreach ($packageRatesArr as $key => $value) {
                if ($value['booking_details']['status'] == "Booked") {
                    if ($value['booking_details']['payment_status'] == null || $value['booking_details']['payment_status'] == "Failed") {
                        $packageRatesArr[$key]['unpaid_invoiced_amount'] = (float)$value['booking_details']['amount'];
                    }
                }
            }
        }
        foreach ($packageRatesArr as $key => $value) {
            if (isset($value['custom_charge'])) {
                // A payment already exists on this booking (base fare paid/invoiced, or some
                // custom_charge/addon already paid — folded into $value['custom_charge'] above).
                // Add-ons added AFTER that as pay-later never get a 'Payment' history row (see
                // chargeAddonFields()), so they're invisible to the history loop above — pull
                // their still-owed amount from the unpaid_addon_charges subquery instead, the
                // same way the unpaid-branch below covers a booking with NO payment at all.
                $unpaidAddonCharge = (isset($value['booking_details']['unpaid_addon_charges']) && $value['booking_details']['unpaid_addon_charges'] > 0) ? $value['booking_details']['unpaid_addon_charges'] : 0;
                $customCharge = $value['custom_charge'] + $unpaidAddonCharge;
                $packageRatesArr[$key]['bookingamount'] = ($value['booking_details']['booking_total'] != "0.00") ? (float)$value['booking_details']['booking_total'] + $customCharge : (float)$value['booking_details']['amount'] + $customCharge;
            } else if (!isset($value['payment_amount']) && $value['booking_details']['payment_status'] == null) {
                $unPaidCustomCharge = (isset($value['booking_details']['unpaid_custom_charges']) && $value['booking_details']['unpaid_custom_charges'] > 0) ? $value['booking_details']['unpaid_custom_charges'] : 0;
                // Nothing has been paid on this booking yet (no Payment history row at all), so any
                // priced add-on currently attached to it is still owed. Mirror the same "pay later"
                // total used at checkout/invoicing (BookingService::fetchPayLaterAddonsWithPrice) so
                // this report doesn't silently drop it, the same way unpaid_custom_charges is above —
                // previously only custom charges got this treatment and add-ons were dropped entirely.
                $natFeesPercentage = $value['booking_details']['nat_fees_percentage'] ?? 0;
                $unPaidCustomCharge += BookingService::fetchPayLaterAddonsWithPrice($key, $natFeesPercentage)['addons_price'] ?? 0;
                $packageRatesArr[$key]['bookingamount'] = ($value['booking_details']['booking_total'] != "0.00") ? (float)$value['booking_details']['booking_total'] + $unPaidCustomCharge : (float)$value['booking_details']['amount'] + $unPaidCustomCharge;
            } else {
                $packageRatesArr[$key]['bookingamount'] = ($value['booking_details']['booking_total'] != "0.00") ? (float)$value['booking_details']['booking_total'] : (float)$value['booking_details']['amount'];
            }
            $payment_amount = isset($value['payment_amount']) ? $value['payment_amount'] : 0;
            $refund_amount = isset($value['refundamount']) ? $value['refundamount'] : 0;

            $packageRatesArr[$key]['refundamount'] = (float)$refund_amount;
            $packageRatesArr[$key]['payment_amount'] = (float)$payment_amount;
            $packageRatesArr[$key]['custom_charge'] = isset($value['custom_charge']) ? $value['custom_charge'] : 0;

            $packageRatesArr[$key]['unpaid_amount'] = (float)number_format($packageRatesArr[$key]['bookingamount'], 2, '.', '') - (float)number_format($packageRatesArr[$key]['payment_amount'], 2, '.', '');


            if (isset($value['payment_amount']) && $value['payment_amount'] > 0 && $value['booking_details']['payment_status'] != null && isset($value['booking_details']['unpaid_custom_charges'])) {
                $packageRatesArr[$key]['booking_details']['unpaid_custom_charges'] = 0;
            }

            $billable_invoiced_status = null;
            if (isset($value['invoiced_amount']) && $value['booking_details']['status'] == "Booked") {
                $invoiced = DB::table('invoices as i')
                    ->join('invoice_bookings as ib', 'ib.invoice_id', '=', 'i.id')
                    ->where('ib.tour_booking_id', $value['bookingid'])->first();
                if (!empty($invoiced) && $invoiced->status == "paid") {
                    $billable_invoiced_status = "Paid";
                    $packageRatesArr[$key]['payment_amount'] = (float)$payment_amount;
                } elseif (!empty($invoiced) && $invoiced->status != "paid") {
                    $billable_invoiced_status = "Invoiced";
                    $packageRatesArr[$key]['payment_amount'] = 0;
                    // $packageRatesArr[$key]['unpaid_amount'] = (float)$payment_amount;
                    $packageRatesArr[$key]['unpaid_amount'] = $packageRatesArr[$key]['bookingamount'] - $packageRatesArr[$key]['payment_amount'] - $packageRatesArr[$key]['refundamount'];
                } else {
                    $billable_invoiced_status = "Billable";
                    $packageRatesArr[$key]['payment_amount'] = 0;
                    // $packageRatesArr[$key]['unpaid_amount'] = (float)$payment_amount;
                    $packageRatesArr[$key]['unpaid_amount'] = $packageRatesArr[$key]['bookingamount'] - $packageRatesArr[$key]['payment_amount'] - $packageRatesArr[$key]['refundamount'];
                }
            }
            $packageRatesArr[$key]['billable_invoiced_status'] = $billable_invoiced_status;

            if ($affiliate_report == "comprehensive") {
                $cash_amount = isset($value['cash_amount']) ? $value['cash_amount'] : 0;
                $card_amount = isset($value['card_amount']) ? $value['card_amount'] : 0;
                $invoiced_amount = isset($value['invoiced_amount']) ? $value['invoiced_amount'] : 0;
                $unpaid_invoiced_amount = isset($value['unpaid_invoiced_amount']) ? $value['unpaid_invoiced_amount'] : 0;
                $packageRatesArr[$key]['cash_amount'] = (float)$cash_amount;
                $packageRatesArr[$key]['card_amount'] = (float)$card_amount;
                $packageRatesArr[$key]['invoiced_amount'] = (float)$invoiced_amount - (float)$refund_amount;
                $packageRatesArr[$key]['unpaid_invoiced_amount'] = (float)$unpaid_invoiced_amount;
            }
        }

        return $packageRatesArr;
    }

    // Affiliate Invoiced Report
    public function affiliateallreport(string $fromDate = "", string $toDate = "", int $customerId = 0,  $tourOperatorId = 0, $tourPackageId = 0, int $export = 0)
    {
        try {
            $userType = userType();

            $fromDate = $fromDate ? $fromDate . " 00:00:00" : date('Y-m-d');
            $toDate = $toDate ? $toDate . " 23:59:59" : date('Y-m-d');
            $tourOperatorId = ($userType == 'Tour Operator') ? auth()->user()->tour_operator_id : $tourOperatorId;

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
                ->whereBetween('tsd.date', [$fromDate, $toDate])
                ->where('tp.tour_operator_id', '=', $tourOperatorId)
                ->when($tourPackageId && $tourPackageId !== 0, function ($query) use ($tourPackageId) {
                    return $query->where('tbm.tour_package_id', '=', $tourPackageId);
                })
                ->when($customerId !== 0, function ($query) use ($customerId) {
                    return $query->where('c.id', '=', $customerId);
                })
                ->select(
                    'tbm.id as bookingid',
                    'tbm.status as status',
                    'tbm.total as amount',
                    'tbm.booking_total',
                    'tbm.tourists as overall_tourists',
                    'tbm.infants',
                    DB::raw('COALESCE((SELECT SUM(COALESCE(tbt2.tourists, 0)) FROM tour_bookings_transact AS tbt2 WHERE tbt2.tour_booking_id = tbm.id AND tbt2.deleted_by = 0), 0) AS transact_tourists'),
                    DB::raw('COALESCE((SELECT SUM(COALESCE(tbt2.infants, 0)) FROM tour_bookings_transact AS tbt2 WHERE tbt2.tour_booking_id = tbm.id AND tbt2.deleted_by = 0), 0) AS transact_infants'),
                    'tbm.is_group_booking as is_group_booking',
                    DB::raw('DATE_FORMAT(tbm.created_at, "%Y-%m-%d") as created_at'),
                    DB::raw('DATE_FORMAT(tsd.date, "%Y-%m-%d") as bookingdate'),
                    'tbm.name as name',
                    'c.name as affiliate_name',
                    'c.id as customer_id',
                    DB::raw('CASE 
                        WHEN tbt.infants > 0 THEN tbt.infants
                        ELSE tbt.tourists
                        END AS tourists'),
                    'tp.name as package',
                    'tpr.rate_for',
                    't.status as payment_status',
                    'tst.time_to',
                    'tst.time_from',
                    'tbh.details',
                    'tbh.operation',
                    DB::raw('(SELECT SUM(CASE operation WHEN "Payment" THEN `tbh`.`ticket_cost` ELSE 0 END) FROM tour_booking_history AS tbh
                        WHERE tbh.tour_booking_id = tbm.id) as "payment_cost"'),

                    // Note: despite the column name, this also folds in 'addons' charge_type rows —
                    // both are extra charges on top of tbm.booking_total and must be invoiced/unpaid
                    // together (previously addons were silently excluded from invoiced/unpaid totals).
                    DB::raw('(SELECT SUM(case operation when "Payment" then  `tbh`.`ticket_cost` else 0 end)  FROM tour_booking_history AS tbh
			        WHERE tbh.tour_booking_id = tbm.id AND `tbh`.`charge_type` IN ("custom_charge", "addons")) AS `custom_charge_total`'),

                    DB::raw('(SELECT SUM(case operation when "Refund" then  `tbh`.`ticket_cost` else 0 end)  FROM tour_booking_history AS tbh
			        WHERE tbh.tour_booking_id = tbm.id AND `tbh`.`charge_type` IN ("custom_charge", "addons")) AS `custom_charge_refund_total`'),

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
            $paidAmount = 0;
            $bookingAmount = 0;
            $customChargeAmount = 0;
            $customChargeRefundAmount = 0;
            $refundAmount = 0;
            $paymentAmount = 0;
            $touristsTotal = 0;
            $infantsTotal = 0;
            $bookingIds = array();

            foreach ($packageRates as $key => $value) {
                $refundAmount += $value['booking_details']['refund_cost'];
                $bookingAmount += $value['booking_details']['payment_cost'] ?? 0;
                $customChargeAmount += ($value['booking_details']['custom_charge_total']) ? $value['booking_details']['custom_charge_total'] : 0;
                $customChargeRefundAmount += ($value['booking_details']['custom_charge_refund_total']) ? $value['booking_details']['custom_charge_refund_total'] : 0;
                $touristsTotal += $value['TouristTotal'];
                $infantsTotal += $value['InfantsTotal'];
                $value['payment_type'] = "Invoiced";
                $value['invoiced_amount'] = $value['booking_details']['booking_total'] + ($value['booking_details']['custom_charge_total'] ?? 0);
                $value['refundamount'] = $value['booking_details']['refund_cost'];
                $value['bookingamount'] = $value['booking_details']['booking_total'] + ($value['booking_details']['custom_charge_total'] ?? 0);
                $value['billable_invoiced_status'] = null;

                $invoice = DB::table('invoices as i')
                    ->join('invoice_bookings as ib', 'ib.invoice_id', '=', 'i.id')
                    ->where('ib.tour_booking_id', $value['bookingid'])->first(['i.status as status', 'i.id as iid']);

                if ($invoice) {
                    $sortInitialValue['invoice_id'] = $invoice->iid;
                    if ($invoice->status == 'paid') {
                        $value['bookingamount'] = $value['booking_details']['payment_cost'];
                        $paidAmount += $value['booking_details']['payment_cost'];
                        $value['unpaid_amount'] = 0;
                        $value['billable_invoiced_status'] = "Paid";
                    } else {
                        $unpaidAmount += ($value['booking_details']['payment_cost'] ?? 0) - $value['booking_details']['refund_cost'];
                        $value['unpaid_amount'] = $value['booking_details']['payment_cost'] - $value['booking_details']['refund_cost'];
                        $value['payment_amount'] = 0;
                        $value['billable_invoiced_status'] = "Invoiced";
                    }
                } else {
                    $value['invoice_id'] = 0;
                    $value['payment_amount'] = 0;
                    $value['billable_invoiced_status'] = 'Billable';
                    $value['unpaid_amount'] = $value['booking_details']['amount'] + ($value['booking_details']['custom_charge_total'] ?? 0) - $value['booking_details']['refund_cost'];
                    $unpaidAmount += ($value['booking_details']['payment_cost'] ?? 0) - $value['booking_details']['refund_cost'];
                }
                $sortedData['invoice_data'][] = $value;
            }

            $sortedData['unpaid_amount'] = $unpaidAmount;
            $sortedData['paid_amount'] = $paidAmount;
            $sortedData['booking_amount'] = $bookingAmount;
            $sortedData['custom_charge_amount'] = $customChargeAmount;
            $sortedData['custom_charge_refund_amount'] = $customChargeRefundAmount;
            $sortedData['refund_amount'] = $refundAmount;
            $sortedData['tourists_total'] = $touristsTotal;
            $sortedData['infants_total'] = $infantsTotal;

            $queryinvoice = Invoice::paid();
            $queryinvoice->where(function ($query) use ($fromDate, $toDate) {
                return $query->whereBetween('from_date', [$fromDate, $toDate])
                    ->orWhereBetween('to_date', [$fromDate, $toDate]);
            });
            $queryinvoice->where('tour_operator_id', auth()->user()->tour_operator_id);
            $queryinvoice->when($customerId !== 0, function ($query) use ($customerId) {
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

            $dueAmount = array_values($dueAmountByCustomer);

            if ($invoice->count() && count($dueAmount)) {
                foreach ($dueAmount as $index => $affiliate) {
                    if (isset($invoice[$affiliate['id']])) {
                        $dueAmount[$index]['amount'] = (string)($affiliate['amount'] - $invoice[$affiliate['id']]);
                    }
                }
            }
            $sortedData['due_amount'] = $dueAmount;

            if ($export) {
                return $this->AffiliateAllReportExportCsvFile($fromDate, $toDate, $sortedData, $tourOperatorId, $tourPackageId);
            }
            return $sortedData;
        } catch (Exception $exception) {
            Log::error($exception);
            return false;
        }
    }

    // Affiliate Comprehensive Report
    public function affiliatecomprehensivereport(string $fromDate = "", string $toDate = "", int $customerId = 0,  $tourOperatorId = 0, $tourPackageId = 0, int $export = 0)
    {
        try {
            $userType = userType();

            $fromDate = $fromDate ?: date('Y-m-d');
            $toDate = $toDate ?: date('Y-m-d');
            $tourOperatorId = ($userType == 'Tour Operator' || $userType == 'Affiliate') ? auth()->user()->tour_operator_id : $tourOperatorId;

            $qry = DB::table('tour_bookings_master AS tbm')
                ->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
                ->join('tour_operators as to', 'to.id', '=', 'tp.tour_operator_id')
                ->join('customers as c', 'c.id', '=', 'tbm.customer_id')
                ->join('tour_bookings_transact AS tbt', 'tbt.tour_booking_id', 'tbm.id')
                ->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
                ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
                ->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
                ->leftJoin('transactions as t', 't.tour_booking_id', '=', 'tbm.id')
                ->where('c.is_affiliate', 'Yes')
                ->where('tbt.deleted_by', 0)
                ->whereBetween('tsd.date', [$fromDate, $toDate])
                ->where('tp.tour_operator_id', '=', $tourOperatorId)
                ->when($tourPackageId && $tourPackageId !== 0, function ($query) use ($tourPackageId) {
                    return $query->where('tbm.tour_package_id', '=', $tourPackageId);
                })
                ->when($customerId !== 0, function ($query) use ($customerId) {
                    return $query->where('c.id', '=', $customerId);
                })
                ->select(
                    'tbm.id as bookingid',
                    'tbm.status as status',
                    'tbm.total as amount',
                    'tbm.booking_total',
                    DB::raw('(tbm.tourists + tbm.infants) as overall_tourists'),
                    'tbm.is_group_booking as is_group_booking',
                    'tbm.liability_waiver_signing as liability_waiver_signing',
                    DB::raw('DATE_FORMAT(tbm.created_at, "%Y-%m-%d") as created_at'),
                    DB::raw('DATE_FORMAT(tsd.date, "%Y-%m-%d") as bookingdate'),
                    'to.logo as touroperatorlogo',
                    'tbm.name as name',
                    'c.name as affiliate_name',
                    DB::raw('CASE 
                        WHEN tbt.infants > 0 THEN tbt.infants
                        ELSE tbt.tourists
                        END AS tourists'),
                    'tp.name as package',
                    'tpr.rate_for',
                    'tpr.age_from',
                    'tpr.age_to',
                    't.status as payment_status',
                    'tst.time_to',
                    'tst.time_from',
                    'tbt.nat_fees_percentage',
                    DB::raw('(SELECT SUM(CASE status WHEN "Open" THEN `tbcc`.`amount` ELSE 0 END) FROM tour_booking_custom_charges AS tbcc
                        WHERE tbcc.tour_booking_id = tbm.id) as "unpaid_custom_charges"'),
                    // See affiliatereport() above for why this is separate from fetchPayLaterAddonsWithPrice().
                    DB::raw("(SELECT SUM(tbat.field_price + tbat.option_price) FROM tour_booking_addon_transactions AS tbat
                        JOIN tour_booking_addon_fields AS tbaf ON tbaf.id = tbat.tour_booking_addon_id
                        WHERE tbat.tour_booking_id = tbm.id AND tbat.status = 'open' AND tbat.operation = 'charge'
                        AND tbat.deleted_at IS NULL AND tbaf.deleted_at IS NULL) as unpaid_addon_charges")
                )
                ->orderBy('tsd.date', 'ASC');

            $result = $qry->get();
            $packageRates = $this->tourPackageRates($result, 1);

            // Common calculation function
            $packageRatesArr = $this->affiliateReportsCalculation($fromDate, $toDate, $tourPackageId, $userType, $tourOperatorId, $customerId, $packageRates, "comprehensive");

            // Overall Calculation Function
            $sortedData = $this->affiliateReportOverallCalculation($packageRatesArr, $fromDate, $toDate, $tourOperatorId, $customerId, "comprehensive");

            if ($export) {
                return $this->AffiliateComprehensiveReportExportCsvFile($fromDate, $toDate, $sortedData, $tourOperatorId, $tourPackageId);
            }
            return $sortedData;
        } catch (Exception $exception) {
            Log::error($exception);
            return false;
        }
    }

    // Affiliate Reports Overall Calculation
    public function affiliateReportOverallCalculation($affiliateResult, $fromDate, $toDate, $tourOperatorId, $customerId, $affiliate_report, $request = null)
    {
        $sortedData = array();
        $invoicedAmount = 0;
        $cashAmount = 0;
        $cardAmount = 0;
        $unpaidAmount = 0;
        $paidAmount = 0;
        $cancelledAmount = 0;
        $refundAmount = 0;
        $bookingTotal = 0;
        $touristTotal = 0;
        $billableInvoicedAmount = 0;
        $paidInvoicedAmount = 0;
        $unpaidInvoicedAmount = 0;
        $billableInvoicedTotal = 0;

        if ($affiliate_report == "comprehensive") {
            // Paid invoices
            $paid_invoices_qry = DB::table('invoices')->where('status', 'paid')
                ->whereDate('from_date', '>=', $fromDate)
                ->whereDate('to_date', '<=', $toDate)
                ->where('tour_operator_id', '=', $tourOperatorId)
                ->when($customerId !== 0, function ($query) use ($customerId) {
                    return $query->where('customer_id', '=', $customerId);
                })
                ->select('amount as paid_invoiced_amount');

            $paid_invoices = $paid_invoices_qry->get();

            foreach ($paid_invoices as $key => $value) {
                $paidInvoicedAmount += $value->paid_invoiced_amount;
            }
        }

        foreach ($affiliateResult as $key => $result) {
            if ($affiliate_report == "comprehensive") {
                $invoicedAmount += $result['invoiced_amount'];
                $cashAmount += $result['cash_amount'];
                $cardAmount += $result['card_amount'];
                $billableInvoicedAmount += $result['billable_invoiced_amount'];
                $unpaidInvoicedAmount += $result['unpaid_invoiced_amount'];
                // $billableInvoicedTotal += $result['billable_invoiced_total'];
                if ($result['booking_details']['status'] == "Cancelled") {
                    $cancelledAmount += $result['booking_details']['amount'];
                }
                $sortedData['comprehensive_data'][] = $result;
            }

            $result['booking_details']['time_to'] = date("g:i: A", strtotime($result['booking_details']['time_to']));
            $result['booking_details']['time_from'] = date("g:i: A", strtotime($result['booking_details']['time_from']));

            if ($affiliate_report == "booked") {
                $timestamp = strtotime($result['booking_details']['bookingdate']);
                $date =  date("m/d/Y", $timestamp);

                if ($request->api == 1) {
                    $sortedData[] = (!isset($sortedData[$date])) ? $result : $result;
                } else {
                    if (!isset($sortedData[$date])) {
                        $sortedData[$date] = array($result);
                    } else {
                        $sortedData[$date][] = $result;
                    }
                }
            }

            $refundAmount += $result['refundamount'];
            $paidAmount += $result['payment_amount'];
            $unpaidAmount += $result['unpaid_amount'];
            $bookingTotal += $result['bookingamount'];
            $touristTotal += $result['TouristTotal'];
        }

        if ($affiliate_report == "comprehensive") {
            $sortedData['invoiced_amount'] = $invoicedAmount;
            $sortedData['cash_amount'] = $cashAmount;
            $sortedData['card_amount'] = $cardAmount;
            $sortedData['cancellation_amount'] = $cancelledAmount;
            $sortedData['billable_invoiced_amount'] = $billableInvoicedAmount;
            $sortedData['paid_invoiced_amount'] = $paidInvoicedAmount;
            $sortedData['unpaid_invoiced_amount'] = $unpaidInvoicedAmount;
            $sortedData['total_net_amount'] = $paidAmount - $refundAmount;
            // $sortedData['billable_invoiced_total'] = $billableInvoicedTotal;
        }

        $sortedData['unpaid_amount'] = $unpaidAmount;
        $sortedData['booking_amount'] = $bookingTotal;
        $sortedData['refund_amount'] = $refundAmount;
        $sortedData['paid_amount'] = $paidAmount;
        $sortedData['total_tourists'] = $touristTotal;

        return $sortedData;
    }

    // Affiliate Booked Report CSV Export
    public function AffiliateReportExportCsvFile($fromDate, $toDate, $sortedData, $tourOperatorId = 0, $tourPackageId = 0)
    {
        $fileName = 'affiliate_booked_report_' . $fromDate . '_' . $toDate . '.csv';
        $headers = csvHeaders($fileName);
        $TopColumns = csvColumns('affiliate_report_TopColumns');
        $columns = csvColumns('affiliate_booked_report_column');
        $tourOperatorName = $this->GetTourOperatorName($tourOperatorId, $tourPackageId);
        $callback = function () use ($sortedData, $TopColumns, $columns, $fromDate, $toDate, $tourOperatorName) {
            $file = fopen('php://output', 'w');  // Create a file
            if (!empty($tourOperatorName)) {
                $BlankLine = '';
                if ($tourOperatorName['tourOperatorName']) {
                    $tourOperatorNameRow = array('Tour Operator:', $tourOperatorName['tourOperatorName'], $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourOperatorNameRow);
                }
                if ($tourOperatorName['tourPackageName']) {
                    $tourPackageNameRow = array('Tour Package:', $tourOperatorName['tourPackageName'], $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourPackageNameRow);
                }
                fputcsv($file, array($BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine));
            }
            fputcsv($file, $TopColumns);
            $DateRange = date("m-d-Y", strtotime($fromDate)) . ' - ' . date("m-d-Y", strtotime($toDate));

            $CreatedDate = date("m-d-Y");
            $bookingAmount = sprintf('%.2f', $sortedData['booking_amount']);
            $paidAmount = sprintf('%.2f', $sortedData['paid_amount']);
            $refundAmount = sprintf('%.2f', $sortedData['refund_amount']);
            $unpaidAmount = sprintf('%.2f', $sortedData['unpaid_amount']);
            fputcsv($file, array($DateRange, $CreatedDate, $bookingAmount, $paidAmount, $refundAmount, $unpaidAmount));

            $BlankLine = '';
            fputcsv($file, array($BlankLine));

            $toEnd = count($sortedData);
            unset($sortedData["booking_amount"]);
            unset($sortedData["paid_amount"]);
            unset($sortedData["unpaid_amount"]);
            unset($sortedData["refund_amount"]);
            unset($sortedData["total_tourists"]);
            $newSortedData = array();
            foreach ($sortedData as $key => $result_data) {
                if (is_array($result_data)) {
                    foreach ($result_data as $k => $each_data) {
                        $newSortedData[] = $each_data;
                    }
                }
            }
            fputcsv($file, $columns);
            $affiliate_data = $this->affiliateReportsCsvDetails($file, $newSortedData, "booked");
            if (!isset($affiliate_data['totalTourists'])) {
                $affiliate_data['totalTourists'] = 0;
            }
            fputcsv($file, array($BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine, $affiliate_data['totalTourists'], $BlankLine, $affiliate_data['total_amount'] ?? 0, $affiliate_data['paid_amt'] ?? 0, $affiliate_data['refund_amt'] ?? 0, $affiliate_data['unpaid_amt'] ?? 0));
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    // Affiliate All Report (Invoice) Download in csv File
    public function AffiliateAllReportExportCsvFile($fromDate, $toDate, $sortedData, $tourOperatorId = 0, $tourPackageId = 0)
    {
        $fileName = 'affiliate_invoice_report_' . $fromDate . '_' . $toDate . '.csv';

        $headers = csvHeaders($fileName);
        $TopColumns = csvColumns('affiliate_invoice_report_TopColumns');
        $BottomColumns = csvColumns('affiliate_invoice_report_bottomColumns');
        $columns = csvColumns('affiliate_report_column');

        $fDate = $fromDate;
        $tDate = $toDate;
        $tourOperatorName = $this->GetTourOperatorName($tourOperatorId, $tourPackageId);
        $due_amount_arr = $sortedData['due_amount'];
        $callback = function () use ($sortedData, $TopColumns, $BottomColumns, $columns, $fDate, $tDate, $tourOperatorName, $due_amount_arr) {
            $file = fopen('php://output', 'w');  // Create a file
            if (!empty($tourOperatorName)) {
                $BlankLine = '';
                if ($tourOperatorName['tourOperatorName']) {
                    $tourOperatorNameRow = array('Tour Operator:', $tourOperatorName['tourOperatorName'], $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourOperatorNameRow);
                }
                if ($tourOperatorName['tourPackageName']) {
                    $tourPackageNameRow = array('Tour Package:', $tourOperatorName['tourPackageName'], $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourPackageNameRow);
                }
                fputcsv($file, array($BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine));
            }
            fputcsv($file, $TopColumns);

            $DateRange = date("m-d-Y", strtotime($fDate)) . ' - ' . date("m-d-Y", strtotime($tDate));
            $CreatedDate = date("m-d-Y");
            $bookingAmount = sprintf('%.2f', $sortedData['booking_amount']);
            $paidAmount = sprintf('%.2f', $sortedData['paid_amount']);
            $refundAmount = sprintf('%.2f', $sortedData['refund_amount']);
            $unpaidAmount = sprintf('%.2f', $sortedData['unpaid_amount']);
            $touristsTotal = sprintf('%.2f', $sortedData['tourists_total']);
            $infantsTotal = sprintf('%.2f', $sortedData['infants_total']);
            fputcsv($file, array($DateRange, $CreatedDate, $bookingAmount, $touristsTotal, $infantsTotal, $paidAmount, $refundAmount, $unpaidAmount));

            $BlankLine = '';
            fputcsv($file, array($BlankLine));

            $toEnd = count($sortedData);
            unset($sortedData["booking_amount"]);
            unset($sortedData["paid_amount"]);
            unset($sortedData["refund_amount"]);
            unset($sortedData["unpaid_amount"]);
            unset($sortedData["tourists_total"]);
            unset($sortedData["infants_total"]);
            unset($sortedData["custom_charge_amount"]);
            unset($sortedData["custom_charge_refund_amount"]);
            unset($sortedData["due_amount"]);

            foreach ($sortedData as $key => $result_data) {

                fputcsv($file, $columns);
                $affiliate_data = $this->affiliateReportsCsvDetails($file, $result_data, "invoice");

                if (0 !== --$toEnd) {
                    fputcsv($file, array($BlankLine));
                }
            }
            fputcsv($file, array($BlankLine));

            // Affiliate Due Amount Table Integration
            fputcsv($file, array('Affiliate Due Amount'));
            fputcsv($file, $BottomColumns);

            if (!empty($due_amount_arr)) {
                foreach ($due_amount_arr as $key => $value) {
                    fputcsv($file, array($value->name ?? '', $value->amount ?? ''));
                }
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // Affiliate Comprehensive Report CSV Export
    public function AffiliateComprehensiveReportExportCsvFile($fromDate, $toDate, $sortedData, $tourOperatorId = 0, $tourPackageId = 0)
    {
        $fileName = 'affiliate_comprehensive_report_' . $fromDate . '_' . $toDate . '.csv';
        $headers = csvHeaders($fileName);
        $TopColumns = csvColumns('affiliate_comprehensive_report_TopColumns');
        $columns = csvColumns('affiliate_comprehensive_report_column');
        $tourOperatorName = $this->GetTourOperatorName($tourOperatorId, $tourPackageId);
        $callback = function () use ($sortedData, $TopColumns, $columns, $fromDate, $toDate, $tourOperatorName) {
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
                fputcsv($file, array($BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine));
            }
            fputcsv($file, $TopColumns);

            $DateRange = date("m-d-Y", strtotime($fromDate)) . ' - ' . date("m-d-Y", strtotime($toDate));
            $CreatedDate = date("m-d-Y");
            $bookingAmount = sprintf('%.2f', $sortedData['booking_amount']);
            $cancellationAmount = sprintf('%.2f', $sortedData['cancellation_amount']);
            $paidAmount = sprintf('%.2f', $sortedData['paid_amount']);
            $refundAmount = sprintf('%.2f', $sortedData['refund_amount']);
            $cardAmount = sprintf('%.2f', $sortedData['card_amount']);
            $cashAmount = sprintf('%.2f', $sortedData['cash_amount']);
            $invoicedAmount = sprintf('%.2f', $sortedData['invoiced_amount']);
            // $billableInvoicedAmount = sprintf('%.2f', $sortedData['billable_invoiced_total']);
            $paidInvoicedAmount = sprintf('%.2f', $sortedData['paid_invoiced_amount']);
            $unpaidInvoicedAmount = sprintf('%.2f', $sortedData['unpaid_invoiced_amount']);
            $totalNetAmount = sprintf('%.2f', $sortedData['total_net_amount']);
            $unpaidAmount = sprintf('%.2f', $sortedData['unpaid_amount']);
            $BlankLine = '';

            fputcsv($file, array($DateRange, $CreatedDate, $bookingAmount, $cancellationAmount, $paidAmount, $refundAmount, $cardAmount, $cashAmount, $invoicedAmount, $paidInvoicedAmount, $unpaidInvoicedAmount, $totalNetAmount, $unpaidAmount));
            fputcsv($file, array($BlankLine));

            $toEnd = count($sortedData);
            fputcsv($file, $columns);

            $this->affiliateReportsCsvDetails($file, $sortedData['comprehensive_data'], "comprehensive");

            if (0 !== --$toEnd) {
                fputcsv($file, array($BlankLine));
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    // Affiliate Reports Common CSV details
    public function affiliateReportsCsvDetails($file, $result_data, $affiliate_report)
    {
        $total_amount = 0;
        $paid_amt = 0;
        $unpaid_amt = 0;
        $totalTourists = 0;
        $tourist_rate = '';
        $refund_amt = 0;
        $affiliate_booked_arr = array();
        foreach ($result_data as $key => $each_data) {
            if (!empty($each_data['rate']) && count($each_data['rate']) > 0) {
                $each_data['rate'] = array_unique($each_data['rate']);
                $tourist_rate = implode("\n", $each_data['rate']);
            }

            $bookingId = $each_data['bookingid'] ?? '';
            $total_tourists = $each_data['TouristTotal'] ?? '';
            $paid_amount = $each_data['payment_amount'] ?? '';
            $refund_amount = $each_data['refundamount'] ?? '';
            $unpaid_amount = $each_data['unpaid_amount'] ?? '';
            $invoicedBillableStatus = '';
            $paymentDateTime = $each_data['payment_datetime'] ?? '';
            if (isset($each_data['booking_details'])) {
                if (isset($each_data['billable_invoiced_status']) && $each_data['billable_invoiced_status'] == "Paid") {
                    $invoicedBillableStatus = "Invoiced";
                } else if ($each_data['billable_invoiced_status'] == null) {
                    if (isset($each_data['payment_type'])) {
                        $invoicedBillableStatus = $each_data['payment_type'];
                    } else {
                        $invoicedBillableStatus = '';
                    }
                } else {
                    $invoicedBillableStatus = $each_data['billable_invoiced_status'];
                }
                $details = $each_data['booking_details'];
                $customer_name = $details['name'] ?? '';
                $affiliate_name = $details['affiliate_name'] ?? '';
                $package_name = $details['package'] ?? '';
                $payment_status = $details['status'] . " @ " . $invoicedBillableStatus ?? '';
                // bookingamount is already computed server-side (affiliateReportsCalculation /
                // affiliateallreport) including custom charges AND add-ons, paid or still-owed —
                // reuse it instead of re-deriving from raw fields here, which silently dropped
                // add-on amounts from the CSV export the same way the on-screen report did.
                if (isset($details['booking_total']) && $details['booking_total'] != 0) {
                    $amount = $each_data['bookingamount'] ?? $details['booking_total'];
                } else {
                    $amount = $details['amount'] ? ($each_data['bookingamount'] ?? $details['amount']) : '';
                }
                $created_date = date('m-d-Y', strtotime($details['created_at'])) ?? '';
                if ($affiliate_report == "booked") {
                    $booking_date = date("m-d-Y", strtotime($details['bookingdate'])) ?? '';
                    $time = $details['time_from'] . ' - ' . $details['time_to'] ?? '';

                    $affiliate_booked_arr['totalTourists'] = $totalTourists = $totalTourists + $total_tourists;
                    $affiliate_booked_arr['total_amount'] = $total_amount = $total_amount + $amount;
                    $affiliate_booked_arr['paid_amt'] = $paid_amt = $paid_amt + $each_data['payment_amount'];
                    $affiliate_booked_arr['refund_amt'] = $refund_amt = $refund_amt + $each_data['refundamount'];
                    $affiliate_booked_arr['unpaid_amt'] = $unpaid_amt = $unpaid_amt + $each_data['unpaid_amount'];
                }
                if ($affiliate_report == "invoice") {
                    $booking_date = date("m-d-Y", strtotime($details['bookingdate'])) . '@' . $details['time_from'] ?? '';
                }
                if ($affiliate_report == "comprehensive") {
                    $booking_date = date("m-d-Y", strtotime($details['bookingdate']));
                    $time = $details['time_to'] ? date("g:i A", strtotime($details['time_to'])) : '';
                }
            }
            if ($affiliate_report == "booked") {
                fputcsv($file, array($bookingId, $customer_name, $affiliate_name, $time, $tourist_rate, $package_name, $booking_date, $created_date, $total_tourists, $payment_status, $amount, $paid_amount, $refund_amount, $unpaid_amount));
            } elseif ($affiliate_report == "comprehensive") {
                fputcsv($file, array($bookingId, $customer_name, $affiliate_name, $tourist_rate, $package_name, $booking_date, $time, $paymentDateTime, $created_date, $total_tourists, $payment_status, $amount, $paid_amount, $refund_amount, $unpaid_amount));
            } else {
                fputcsv($file, array($bookingId, $customer_name, $affiliate_name, $tourist_rate, $package_name, $booking_date, $created_date, $total_tourists, $payment_status, $amount, $paid_amount, $refund_amount, $unpaid_amount));
            }
        }

        if ($affiliate_report == "booked") {
            return $affiliate_booked_arr;
        }

        if ($affiliate_report == "comprehensive") {
            return true;
        }
    }

    // Tax Collection Report
    public function taxCollection(Request $request)
    {
        $userType = userType();

        if ($userType == 'Admin') {
            if ($request->csv == 1) {
                $tourOperatorId = $request->tourOperatorId;
            } else {
                $tourOperatorId = $request->tourOperator;
            }
        } else {
            $tourOperatorId = auth()->user()->tour_operator_id;
        }

        try {

            $query =  "SELECT `tsd`.`date` AS `created_at`,
                `tbt`.`id` AS `transact_id`,
                `tbm`.`id` AS `tour_booking_id`,
                `tbm`.`total` AS `payable`,
                `tbt`.`check_in_status` AS `checkin`,
                `tbt`.`refund_permitfee_status` AS `refundedpermitfee`,
                `tbt`.`permit_fee`,
                `tbt`.`additional_charge`,
                `tbt`.`tourists` * `tbt`.`additional_charge` AS `additional_charge_amount`,
                CASE
                WHEN tbt.infants > 0 THEN tbt.infants
                ELSE tbt.tourists
                END AS tourists,
                `tbt`.`rate`,
                `tbt`.`processing_charge_percentage`,
                `tpr`.`rate_for`
                FROM `tour_bookings_transact` AS `tbt`
                INNER JOIN `tour_bookings_master` AS `tbm` ON `tbm`.`id` = `tbt`.`tour_booking_id`
                INNER JOIN `tour_package_rates` AS `tpr` ON `tpr`.`id` = `tbt`.`tour_package_rate_id`
                INNER JOIN `tour_packages` AS `tp` ON `tp`.`id` = `tbm`.`tour_package_id`
                INNER JOIN `tour_slot_times` AS `tst` ON `tst`.`id` = `tbm`.`tour_slot_id`
                INNER JOIN `tour_slot_dates` AS `tsd` ON `tsd`.`id` = `tst`.`tour_slot_id`";
            $query .= "WHERE `tp`.`tour_operator_id` = " . $tourOperatorId . "\n";
            $query .= 'AND  `tsd`.`date` between "' . $request->dateFrom .  ' 00:00:00'  . '" and "' . $request->dateTo . ' 23:59:59' . '"' . "\n";
            $query .= "AND `tbm`.`status` != 'Cancelled'";
            $query .= "AND `tbt`.`tourists` > '0' AND `tbt`.`deleted_by` = '0' ";

            if ($request->has('tourPackage') && $request->tourPackage > 0) {
                $query .= " AND `tp`.`id` =" .  $request->tourPackage;
            }
            if ($request->has('tourPackageId') && !$request->has('tourPackage') && $request->tourPackageId > 0) {
                $query .= " AND `tp`.`id` =" . $request->tourPackageId;
            }
            $query .= " ORDER BY `tsd`.`date` " . 'DESC' . ";";

            $payments = DB::select($query);

            // Build [transact_id => unpaid_count] for pax added via Change People
            // whose payment link is still Unpaid — exclude them from guest counts.
            $bookingIds            = array_unique(array_column($payments, 'tour_booking_id'));
            $unpaidPaxByTransactId = UnpaidPaxService::getUnpaidPaxByTransactId($bookingIds);

            $sortedData = array();
            foreach ($payments as $element) {
                $timestamp = strtotime($element->created_at);
                $date =  date("m-d-Y", $timestamp);
                if (!isset($sortedData[$date])) {
                    $sortedData[$date] = array($element);
                } else {
                    $sortedData[$date][] = $element;
                }
            }

            $totalPermitFee = 0;
            $totalStateTax = 0;
            foreach ($sortedData as $date => $groups) {
                $permitFee = 0;
                $stateTax = 0;
                $permitFeeRateGroups = [];
                $stateTaxRateGroups = [];
                foreach ($groups as $lookup) {
                    $unpaidPax       = $unpaidPaxByTransactId[(int)$lookup->transact_id] ?? 0;
                    $adjustedTourists = max(0, (int)$lookup->tourists - $unpaidPax);
                    $adjustedCharge   = $adjustedTourists * (float)$lookup->additional_charge;

                    if (!empty($lookup->checkin) && $lookup->rate > 0) {
                        $permitFee += $lookup->permit_fee;
                        $permitFeeRateGroups[$lookup->rate_for][] = $adjustedTourists;
                    }
                    $data = $lookup->created_at;
                    $stateTax += $adjustedCharge;
                    $stateTaxRateGroups[$lookup->rate_for][] = $adjustedTourists;
                }

                foreach ($permitFeeRateGroups as $rateGroup => $permitFeeRateGroup) {
                    $permitFeeRateGroups[$rateGroup] = array_sum($permitFeeRateGroup);
                    $permitFeeRateGroups[] = array_sum($permitFeeRateGroup) . ' ' . $rateGroup;
                    unset($permitFeeRateGroups[$rateGroup]);
                }

                foreach ($stateTaxRateGroups as $rateGroup => $stateTaxRateGroup) {
                    $stateTaxRateGroups[$rateGroup] = number_format(array_sum($stateTaxRateGroup), 0, '.', ',');
                    $stateTaxRateGroups[] = number_format(array_sum($stateTaxRateGroup), 0, '.', ',') . ' ' . $rateGroup;
                    unset($stateTaxRateGroups[$rateGroup]);
                }


                unset($sortedData[$date]);

                if ($request->api == 1) {
                    $sortedData[] = [
                        'date' => $data,
                        'permitFee' => round($permitFee, 2),
                        'stateTax' => round($stateTax, 2),
                        'total' => round($permitFee + $stateTax, 2),
                        'touristsCountPermitfees' => $permitFeeRateGroups,
                        'touristsCountStateTax' => $stateTaxRateGroups,
                    ];
                } else {
                    $sortedData[$date] = [
                        'permitFee' => round($permitFee, 2),
                        'stateTax' => round($stateTax, 2),
                        'total' => round($permitFee + $stateTax, 2),
                        'touristsCountPermitfees' => $permitFeeRateGroups,
                        'touristsCountStateTax' => $stateTaxRateGroups,
                    ];
                }

                $totalPermitFee += $permitFee;
                $totalStateTax += $stateTax;
            }

            $sortedData['individual'] = $sortedData;
            $sortedData['totals'] = [
                'permitFee' => round($totalPermitFee, 2),
                'stateTax' => round($totalStateTax, 2),
                'all' => round($totalPermitFee + $totalStateTax, 2),
            ];

            $result = [
                'individual' => $sortedData['individual'],
                'totals' => $sortedData['totals'],
            ];

            if ($request->csv == 1) {
                return $this->TaxCollectionReportExportCsvFile($request, $result, $tourOperatorId);
            }

            return response()->json($result);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    // Tax Collection Report CSV Export
    public function TaxCollectionReportExportCsvFile($request, $result, $tourOperatorId)
    {
        $fileName = 'tax_report_' . $request->dateFrom . '_' . $request->dateTo . '.csv';

        $headers = csvHeaders($fileName);
        $columns = csvColumns('tax_collection_report_columns');
        $tourOperatorName = $this->GetTourOperatorName($tourOperatorId, isset($request->tourPackageId) ? $request->tourPackageId : 0);
        $callback = function () use ($result, $columns, $tourOperatorName) {
            $file = fopen('php://output', 'w');
            if (!empty($tourOperatorName)) {
                $BlankLine = '';
                if ($tourOperatorName['tourOperatorName']) {
                    $tourOperatorNameRow = array('Tour Operator:', $tourOperatorName['tourOperatorName']);
                    fputcsv($file, $tourOperatorNameRow);
                }
                if ($tourOperatorName['tourPackageName']) {
                    $tourPackageNameRow = array('Tour Package:', $tourOperatorName['tourPackageName']);
                    fputcsv($file, $tourPackageNameRow);
                }
                fputcsv($file, array($BlankLine, $BlankLine));
            }
            // Create a file
            fputcsv($file, $columns);

            $total = 0;
            foreach ($result['individual'] as $key => $each_data) {
                $state_tax = '';
                $Date = (!empty($key)) ? ((isset($key)) ? $key : '') : '';

                $Permit_Fee = (!empty($each_data)) ? ((isset($each_data['stateTax'])) ? $each_data['stateTax'] : '') : '';
                // For state tax tourists
                if (@$each_data['touristsCountStateTax'] != "") {
                    $state_tax .= implode("\n", @$each_data['touristsCountStateTax']);
                }
                $total += (!empty($each_data)) ? ((isset($each_data['stateTax'])) ? $each_data['stateTax'] : '') : '';
                fputcsv($file, array($Date, $Permit_Fee . "\n" . $state_tax));
            }

            $blankLine = '';
            fputcsv($file, array($blankLine, $total));
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // Refund Payment Report
    public function refundpayment(string $fromDate = "", string $toDate = "", $tourOperatorId = 0, $tourPackageId = 0, $export = 0)
    {

        try {
            $userType = userType();

            if ($fromDate == "")
                $fromDate = date('Y-m-d');
            else
                $fromDate = $fromDate . " 00:00:00";

            if ($toDate == "")
                $toDate = date('Y-m-d');
            else
                $toDate = $toDate . " 23:59:59";

            /* START - PAYMENT / REFUND COLLECTION QUERY FOR ALL TYPE REFUND */
            $refundSql = 'select
                `tbm`.`id` as `tour_booking_id`,
                `tbh`.`operation`,
                `tbm`.`status` as `booking_status`,
                `tbm`.`comments`,
                `tbm`.`created_by` as `createdBy`,
                `tbm`.`created_by_user_type` as `TourUserType`,
                json_unquote(json_extract(`tbh`.`details`, "$.created_by_with_name")) as created_by_with_name,
                `tbh`.`ticket_cost` + `tbh`.`processing_fees` + `tbh`.`platform_fees` as `refund_amount`,
                ifnull(`tbh`.`payment_type`, "") as `via`,
                ifnull(`tbh`.`payment_method`, "") as `method`,
                DATE_FORMAT(`tbm`.`created_at`, "%Y-%m-%d") as `created_at`,
                `tbm`.`name`,
                `tbm`.`email`,
                `tbm`.`phone_number`,
                `tst`.`time_from`,
                `tst`.`time_to`,
                `tsd`.`date`,
                `tbh`.`operation_at`
            from
                `tour_booking_history` as `tbh`
                inner join `tour_bookings_master` as `tbm` on `tbm`.`id` = `tbh`.`tour_booking_id`
                inner join `tour_slot_times` as `tst` on `tst`.`id` = `tbm`.`tour_slot_id`
                inner join `tour_slot_dates` as `tsd` on `tsd`.`id` = `tst`.`tour_slot_id`
                inner join `tour_packages` as `tp` on `tp`.`id` = `tbm`.`tour_package_id`';

            $refundSql .= ' and `tbh`.`ticket_cost` > 0 ';

            $refundSql .= ' and `tbh`.`operation` in ("Refund", "Cancelled") ';

            $refundSql .= ' and `tbh`.`payment_method` not in ("Don\'t Refund", "don\'t refund", "fareharbor : don\'t refund") ';

            $refundSql .= ' and `tsd`.`date` between "' . $fromDate . '" and "' . $toDate . '"';

            if ($tourPackageId) {
                $refundSql .= ' and `tp`.`id` = "' . $tourPackageId . '" ';
            }

            if ($userType == 'Tour Operator') {
                $refundSql .= ' and `tp`.`tour_operator_id` = "' . auth()->user()->tour_operator_id . '" ';
            } else {
                $refundSql .= ' and `tp`.`tour_operator_id` = "' . $tourOperatorId . '" ';
            }
            // $refundSql .= ' and `tp`.`tour_operator_id` = "'.$tourOperatorId.'" ';

            $refundQuery = DB::select($refundSql);
            /* END - PAYMENT / REFUND COLLECTION QUERY FOR ALL TYPE REFUND */

            $totalRefund = 0;
            $sortedDataArray = array();
            foreach ($refundQuery as $value) {
                $newFilteredData = array();
                if (isset($sortedDataArray[$value->tour_booking_id])) {
                    $sortedDataArray[$value->tour_booking_id]['amount'] = (string)($sortedDataArray[$value->tour_booking_id]['amount'] + $value->refund_amount);
                    $sortedDataArray[$value->tour_booking_id]['refund'][] = [
                        'amount' => $value->refund_amount,
                        'payment_type' => $value->method,
                        'refund_type' => $value->via
                    ];
                } else {
                    $newFilteredData['bookingId'] = $value->tour_booking_id;
                    $newFilteredData['name'] = $value->name;
                    $newFilteredData['bookingdate'] = date("m/d/Y", strtotime($value->date));
                    $newFilteredData['time_from'] = date("g:i A", strtotime($value->time_from));
                    $newFilteredData['phone'] = $value->phone_number;
                    $newFilteredData['email'] = $value->email;
                    $newFilteredData['booking_status'] = $value->booking_status;
                    $newFilteredData['amount'] = (string)$value->refund_amount;
                    $newFilteredData['refund'][] = [
                        'amount' => $value->refund_amount,
                        'payment_type' => $value->method,
                        'refund_type' => $value->via
                    ];
                    $newFilteredData['refunddate'] = date("m/d/Y", strtotime($value->operation_at));
                    $sortedDataArray[$value->tour_booking_id] = $newFilteredData;
                }
                $totalRefund += $value->refund_amount;
            }

            if ($export) {
                return $this->exportRefundPaymentReport($fromDate, $toDate, $sortedDataArray, $totalRefund, $tourOperatorId, $tourPackageId);
            }
            if (request()->is('api/*')) {
                $result['data'] = array();
                foreach ($sortedDataArray as $element) {
                    $result['data'][] = $element;
                }
                $result['totalRefund'] = $totalRefund;
                return $result;
            } else {
                return DataTables::of($sortedDataArray)
                    ->with('totalRefund', $totalRefund)
                    ->addIndexColumn()
                    ->make(true);
            }
        } catch (Exception $exception) {
            Log::error($exception);
        }
    }

    // Refund Payment Report CSV Export
    public function exportRefundPaymentReport($fromDate, $toDate, $sortedDataArray, $totalRefund, $tourOperatorId = 0, $tourPackageId = 0)
    {
        $fileName = 'refund_payment_report' . $fromDate . '_' . $toDate . '.csv';
        $headers = csvHeaders($fileName);
        $columnsHeader = csvColumns('refund_payment_report_TopColumns');
        $columns = csvColumns('refund_payment_report_columns');
        $tourOperatorName = $this->GetTourOperatorName($tourOperatorId, $tourPackageId);

        $callback = function () use ($sortedDataArray, $columns, $columnsHeader, $fromDate, $toDate, $totalRefund, $tourOperatorName) {

            $file = fopen('php://output', 'w');  // Create a file
            if (!empty($tourOperatorName)) {
                $BlankLine = '';
                if ($tourOperatorName['tourOperatorName']) {
                    $tourOperatorNameRow = array('Tour Operator:', $tourOperatorName['tourOperatorName'], $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourOperatorNameRow);
                }
                if ($tourOperatorName['tourPackageName']) {
                    $tourPackageNameRow = array('Tour Package:', $tourOperatorName['tourPackageName'], $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourPackageNameRow);
                }
                fputcsv($file, array($BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine));
            }
            fputcsv($file, $columnsHeader);

            $dateRange = date("m-d-Y", strtotime($fromDate)) . ' - ' . date("m-d-Y", strtotime($toDate));
            $createdDate = date("m-d-Y");
            fputcsv($file, array($dateRange, $createdDate, $totalRefund));

            $blankLine = '';
            fputcsv($file, array($blankLine));

            fputcsv($file, $columns);
            foreach ($sortedDataArray as $eachData) {
                $row['Booking Id'] = $eachData['bookingId'] ?? '';
                $row['Customer Name'] = $eachData['name'] ?? '';
                $row['Booking Date'] = (isset($eachData['bookingdate']) && isset($eachData['time_from'])) ? $eachData['bookingdate'] . ' @ ' . $eachData['time_from'] : '';
                $row['Phone'] = $eachData['phone'] ?? '';
                $row['Email'] = $eachData['email'] ?? '';
                $row['Amount'] = $eachData['amount'] ?? '';
                $str = '';
                if (!empty($eachData['refund'])) {
                    foreach ($eachData['refund'] as $value) {
                        $str .= "[" . $value['amount'] . ", ";
                        $str .=  $value['payment_type'] . ", ";
                        $str .=  $value['refund_type'];
                        $str .= "]";
                        $str .= "\n";
                    }
                }
                $row['Refund Type'] = $str;
                $row['Booking Status'] = $eachData['booking_status'] ?? '';
                $row['Refund Date'] = (isset($eachData['refunddate'])) ? date("m-d-Y", strtotime($eachData['refunddate'])) : '';

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

    // Cancel Booking Report Start
    public function cancelbookingreport(Request $request, string $fromDate = "", string $toDate = "", $tourOperatorId = 0, $tourPackageId = 0, int $export = 0)
    {
        try {
            $userType = userType();

            if ($fromDate == "")
                $fromDate = date('Y-m-d');
            else
                $fromDate = $fromDate . " 00:00:00";

            if ($toDate == "")
                $toDate = date('Y-m-d');
            else
                $toDate = $toDate . " 23:59:59";

            $cancelledBooking = DB::table('tour_bookings_master AS tbm')
                ->join('tour_booking_history as tbh', 'tbh.tour_booking_id', '=', 'tbm.id')
                ->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
                ->join('tour_operators as to', 'to.id', '=', 'tp.tour_operator_id')
                ->join('customers as c', 'c.id', '=', 'tbm.customer_id')
                ->join('tour_bookings_transact AS tbt', 'tbt.tour_booking_id', 'tbm.id')
                ->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
                ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
                ->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
                ->leftJoin('transactions as t', 't.tour_booking_id', '=', 'tbm.id')
                ->where('tbm.status', 'Cancelled')
                ->whereBetween('tsd.date', [$fromDate, $toDate])
                ->select(
                    'tbm.id as bookingid',
                    'tbm.status as status',
                    'tbm.total as amount',
                    'tbm.booking_total',
                    'tbm.is_group_booking',
                    DB::raw('DATE_FORMAT(tbm.created_at, "%Y-%m-%d") as created_at'),
                    DB::raw('DATE_FORMAT(tsd.date, "%Y-%m-%d") as bookingdate'),
                    'to.logo as touroperatorlogo',
                    'c.name as name',
                    'tbt.tourists as tourists',
                    'tbt.infants as infants',
                    'tp.name as package',
                    'tpr.rate_for',
                    'tpr.age_from',
                    'tpr.age_to',
                    't.status as payment_status',
                    'tbh.details',
                    'tst.time_from',
                    'tst.time_to',
                    'tbm.tourists as overall_tourists',
                    'tbm.infants as overall_infants',

                    DB::raw('(SELECT SUM(CASE operation WHEN "Payment" THEN `tbh`.`ticket_cost` ELSE 0 END) FROM tour_booking_history AS tbh
                        WHERE tbh.tour_booking_id = tbm.id) as "payment_cost"'),

                    DB::raw('(SELECT SUM(CASE operation WHEN "Refund" THEN `tbh`.`ticket_cost` WHEN "Cancelled" THEN `tbh`.`ticket_cost` ELSE 0 END) FROM tour_booking_history AS tbh
                        WHERE tbh.tour_booking_id = tbm.id) as "refund_cost"')
                );

            if ($tourPackageId && $tourPackageId !== 0) {
                $cancelledBooking = $cancelledBooking->where('tbm.tour_package_id', '=', $tourPackageId);
            }

            if ($userType == 'Tour Operator') {
                $cancelledBooking = $cancelledBooking->where('tp.tour_operator_id', auth()->user()->tour_operator_id);
            } else {
                $cancelledBooking = $cancelledBooking->where('tp.tour_operator_id', '=', $tourOperatorId);
            }

            $cancelledBooking = $cancelledBooking->distinct()->get();
            // $cancelledBooking = $cancelledBooking->distinct()->groupBy('tbm.id')->get();
            $result = $this->tourPackageRatesForCancelbooking($cancelledBooking, 0);
            $sortedData = array();
            $unpaidAmount = 0;
            $bookingAmount = 0;
            $paymentAmount = 0;

            foreach ($result as $key => $value) {
                $bookingAmount += $value['booking_total'];
                $paymentAmount += $value['payment_amount'];
                $unpaidAmount += $value['unpaid_amount'];
            }


            $sortedData['booking_amount'] = $bookingAmount;
            $sortedData['paid_amount'] = $paymentAmount;
            $sortedData['unpaid_amount'] = $unpaidAmount;

            foreach ($result as $element) {
                $element['time_to'] = date("g:i: A", strtotime($element['time_to']));
                $element['time_from'] = date("g:i: A", strtotime($element['time_from']));
                $timestamp = strtotime($element['bookingdate']);
                $date =  date("m/d/Y", $timestamp);
                if ($request->api == 1) {
                    if (!isset($sortedData[$date])) {
                        $sortedData[] = $element;
                    } else {
                        $sortedData[] = $element;
                    }
                } else {
                    if (!isset($sortedData[$date])) {
                        $sortedData[$date] = array($element);
                    } else {
                        $sortedData[$date][] = $element;
                    }
                }
            }

            $all_total = [];
            if ($request->api == 1) {
                unset($sortedData['booking_amount']);
                unset($sortedData['paid_amount']);
                unset($sortedData['unpaid_amount']);
                $all_total['booking_amount'] = $bookingAmount;
                $all_total['paid_amount'] = $paymentAmount;
                $all_total['unpaid_amount'] = $unpaidAmount;
                $sortedData = array_values($sortedData);
                // return response()->json([$sortedData, $all_total]);
                return response()->json(['details' => $sortedData, 'all_total' => $all_total]);
            }

            if ($export) {
                return $this->CancelBookingReportExportCsvFile($fromDate, $toDate, $sortedData, $tourOperatorId, $tourPackageId);
            }
            return $sortedData;
        } catch (Exception $exception) {
            Log::error($exception);
            return false;
        }
    }
    // Cancel Booking Report End

    public function tourPackageRatesForCancelbooking($rateGroups, $comprehensive_flag = 0)
    {
        $initialArray = [];

        foreach ($rateGroups as $key => $rateGroup) {
            $bookingId = $rateGroup->bookingid;
            $initialArray[$bookingId]['bookingid'] = $rateGroup->bookingid;
            $initialArray[$bookingId]['status'] = $rateGroup->status;
            $initialArray[$bookingId]['amount'] = $rateGroup->amount;
            $initialArray[$bookingId]['booking_total'] = $rateGroup->booking_total;
            $initialArray[$bookingId]['created_at'] = $rateGroup->created_at;
            $initialArray[$bookingId]['bookingdate'] = $rateGroup->bookingdate;
            $initialArray[$bookingId]['touroperatorlogo'] = $rateGroup->touroperatorlogo;
            $initialArray[$bookingId]['name'] = $rateGroup->name;
            $initialArray[$bookingId]['tourists'] = $rateGroup->overall_tourists + $rateGroup->overall_infants;
            $initialArray[$bookingId]['package'] = $rateGroup->package;
            $initialArray[$bookingId]['payment_status'] = $rateGroup->payment_status;
            $initialArray[$bookingId]['time_from'] = $rateGroup->time_from;
            $initialArray[$bookingId]['time_to'] = $rateGroup->time_to;
            $initialArray[$bookingId]['booking_status'] = $rateGroup->status;
            if ($rateGroup->payment_status == "Refund") {
                $initialArray[$bookingId]['payment_amount'] = $rateGroup->booking_total;
            } else {
                $initialArray[$bookingId]['payment_amount'] = '0';
            }

            if ($rateGroup->payment_cost == 0) {
                $initialArray[$bookingId]['unpaid_amount'] = $rateGroup->amount;
            } else {
                $initialArray[$bookingId]['unpaid_amount'] = 0;
            }

            $initialArray[$bookingId]['TouristTotal'] = $rateGroup->overall_tourists + $rateGroup->overall_infants;

            if ($rateGroup->is_group_booking == '1') {
                $rateGrpText = $rateGroup->tourists . ' ' . 'Person(s)';
            } else {
                $rateGrpText = ($rateGroup->infants > 0 ? $rateGroup->infants : $rateGroup->tourists) . ' ' . $rateGroup->rate_for;
            }

            if (($rateGroup->tourists > 0 || $rateGroup->infants > 0) && !in_array($rateGrpText, $initialArray[$bookingId]['ageGroups'] ?? [])) {
                $ageGroups['tourists'] = $rateGroup->infants > 0 ? $rateGroup->infants : $rateGroup->tourists;
                $ageGroups['name'] = ($rateGroup->is_group_booking == '1') ? 'Person(s)' : $rateGroup->rate_for;
                $initialArray[$bookingId]['ageGroups'][] = $rateGrpText;
            }
        }

        return $initialArray;
    }

    // Cancel Booking Report in CSV Export
    public function CancelBookingReportExportCsvFile($fromDate, $toDate, $sortedData, $tourOperatorId = 0, $tourPackageId = 0)
    {
        $fileName = 'cancelbookingreport_' . $fromDate . '_' . $toDate . '.csv';

        $headers = csvHeaders($fileName);
        $TopColumns = csvColumns('cancel_booking_report_TopColumns');
        $columns = csvColumns('cancel_booking_report_columns');
        $tourOperatorName = $this->GetTourOperatorName($tourOperatorId, $tourPackageId);
        $fDate = $fromDate;
        $tDate = $toDate;


        $callback = function () use ($sortedData, $TopColumns, $columns, $fDate, $tDate, $tourOperatorName/*, $bookingAmount, $paidAmount, $unpaidAmount, $totalAmount*/) {
            $file = fopen('php://output', 'w');
            if (!empty($tourOperatorName)) {
                $BlankLine = '';
                if ($tourOperatorName['tourOperatorName']) {
                    $tourOperatorNameRow = array('Tour Operator:', $tourOperatorName['tourOperatorName'], $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourOperatorNameRow);
                }
                if ($tourOperatorName['tourPackageName']) {
                    $tourPackageNameRow = array('Tour Package:', $tourOperatorName['tourPackageName'], $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourPackageNameRow);
                }
                fputcsv($file, array($BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine));
            }
            fputcsv($file, $TopColumns);

            $DateRange = date("m-d-Y", strtotime($fDate)) . ' - ' . date("m-d-Y", strtotime($tDate));
            $CreatedDate = date("m-d-Y");

            $totalFinalAmount = $sortedData['paid_amount'] + $sortedData['unpaid_amount'];
            $bookingAmount = sprintf('%.2f', $sortedData['booking_amount']);
            $paidAmount = sprintf('%.2f', $sortedData['paid_amount']);
            $unpaidAmount = sprintf('%.2f', $sortedData['unpaid_amount']);
            $totalAmount = sprintf('%.2f', $totalFinalAmount);
            fputcsv($file, array($DateRange, $CreatedDate, $bookingAmount, $paidAmount, $unpaidAmount, $totalAmount));

            $BlankLine = '';
            fputcsv($file, array($BlankLine));

            $toEnd = count($sortedData);
            unset($sortedData["booking_amount"]);
            unset($sortedData["paid_amount"]);
            unset($sortedData["unpaid_amount"]);
            $newSortedData = array();
            foreach ($sortedData as $key => $result_data) {
                if (is_array($result_data)) {
                    foreach ($result_data as $k => $each_data) {
                        $newSortedData[] = $each_data;
                    }
                }
            }

            $Total_Amount = 0;
            $paid_Amount = 0;
            $unpaidAmt = 0;
            $TotalTourists = 0;
            $tourist_rate = '';
            fputcsv($file, $columns);

            foreach ($newSortedData as $each_data) {
                $BookingId = (!empty($each_data)) ? ((isset($each_data['bookingid'])) ? $each_data['bookingid'] : '') : '';
                $Customer_Name = (!empty($each_data)) ? ((isset($each_data['name'])) ? $each_data['name'] : '') : '';
                $tourist_rate = '';
                if (!empty($each_data['ageGroups'])) {
                    foreach ($each_data['ageGroups'] as $ky => $vl) {
                        if ($ky == 0) {
                            $tourist_rate .= $vl;
                        } else {
                            $tourist_rate .= "\n" . $vl;
                        }
                    }
                }


                $tourists = (isset($each_data['TouristTotal'])) ? $each_data['TouristTotal'] : '';
                if ($each_data['booking_total'] != "0.00") {
                    $amt = $each_data['booking_total'];
                } else {
                    $amt = $each_data['amount'];
                }
                $Tourists_Rate = $tourist_rate;
                $Package_Name = (!empty($each_data)) ? ((isset($each_data['package'])) ? $each_data['package'] : '') : '';
                $Booking_Date = (!empty($each_data)) ? ((isset($each_data['bookingdate'])) ? date("m-d-Y", strtotime($each_data['bookingdate'])) : '') : '';
                $Created_Date = (!empty($each_data)) ? ((isset($each_data['created_at'])) ? date("m-d-Y", strtotime($each_data['created_at'])) : '') : '';
                $Toal_Tourists = (isset($each_data['TouristTotal'])) ? $each_data['TouristTotal'] : '';
                $Payment_Status = (!empty($each_data)) ? ((isset($each_data['status'])) ? $each_data['status'] : '') : '';
                $Amount = $amt;
                $paymentAmount = (!empty($each_data)) ? ((isset($each_data['payment_amount'])) ? $each_data['payment_amount'] : '') : '';
                $unpaid_Amount = (!empty($each_data)) ? ((isset($each_data['unpaid_amount'])) ? sprintf("%d", $each_data['unpaid_amount']) : '') : '';

                fputcsv($file, array($BookingId, $Customer_Name, $Tourists_Rate, $Package_Name, $Booking_Date, $Created_Date, $Toal_Tourists, $Payment_Status, $Amount, $paymentAmount, $unpaid_Amount));
                $TotalTourists = $TotalTourists + $tourists;
                $Total_Amount = $Total_Amount + $amt;
                $paid_Amount = $paid_Amount + $each_data['payment_amount'];
                $unpaidAmt = $unpaidAmt + $each_data['unpaid_amount'];
            }
            fputcsv($file, array($BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine, $TotalTourists, $BlankLine, $Total_Amount, $paid_Amount, $unpaidAmt));


            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function pendingReport(string $fromDate = "", string $toDate = "",  $tourOperatorId = 0,  $tourPackageId = 0, int $export = 0)
    {
        $userType = userType();

        if ($fromDate == "")
            $fromDate = date('Y-m-d');
        else
            $fromDate = $fromDate . " 00:00:00";
        if ($toDate == "")
            $toDate = date('Y-m-d');
        else
            $toDate = $toDate . " 23:59:59";

        $pendingSql = 'select distinct
                `tbm`.`id` as `bookingid`,
                `c`.`is_affiliate` as `is_affiliate`,
                `tbm`.`status` as `status`,
                `tbm`.`name` as `name`,
                `tbm`.`total` as `amount`,
                `tbm`.`tourists` as `tourists`,
                 ifnull(`t`.`status`, "Pending") as `Payment`,
                `tbm`.`created_at` as `createdate`,
                `tsd`.`date` as `bookingdate`
            from
                `transactions` as `t`
                inner join `tour_bookings_master` as `tbm` on `tbm`.`id` = `t`.`tour_booking_id`
                inner join `tour_slot_times` as `tst` on `tst`.`id` = `tbm`.`tour_slot_id`
                inner join `tour_slot_dates` as `tsd` on `tsd`.`id` = `tst`.`tour_slot_id`
                inner join `customers` as `c` on `c`.`id` = `tbm`.`customer_id`
                inner join `tour_packages` as `tp` on `tp`.`id` = `tbm`.`tour_package_id`';

        $pendingSql .= 'where  `t`.`status` = "Failed" and `tsd`.`deleted_by` = 0';
        $pendingSql .= ' and `t`.`created_at` between "' . $fromDate . '" and "' . $toDate . '"';

        if ($tourPackageId && $tourPackageId !== 0) {
            $pendingSql .= ' and `tbm`.`tour_package_id` = "' . $tourPackageId . '" ';
        }

        if ($userType == 'Tour Operator') {
            $pendingSql .= ' and `tp`.`tour_operator_id` = "' . auth()->user()->tour_operator_id . '" ';
        } else {
            $pendingSql .= ' and `tp`.`tour_operator_id` = "' . $tourOperatorId . '" order by `tbm`.`id` desc';
        }
        // $refundSql .= ' and `tp`.`tour_operator_id` = "'.$tourOperatorId.'" ';

        $query = DB::select($pendingSql);
        //        $query = DB::table('transactions AS t')
        //            ->join('tour_bookings_master as tbm', 'tbm.id', '=', 't.tour_booking_id')
        //            ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
        //            ->join('customers as c', 'c.id' , '=', 'tbm.customer_id')
        //            ->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
        //            ->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
        //            ->where('t.status', 'Failed')
        //            ->where('tsd.deleted_by', '=','0')
        //            ->whereBetween('t.created_at', [$fromDate, $toDate])
        //            ->select([
        //                'tbm.id as bookingid',
        //                'c.is_affiliate as is_affiliate',
        //                'tbm.name as name',
        //                'tbm.total as amount',
        //                'tsd.date as bookingdate',
        //                'tbm.created_at as createdate',
        //                'tbm.tourists as tourists',
        //                DB::raw('IFNULL(t.status,"Pending") as Payment'),
        //                'tbm.status as status'
        //            ])
        //            ->orderBy('tbm.id', 'DESC');
        //
        //        if ($tourPackageId && $tourPackageId !== 0) {
        //            $query = $query->where('tbm.tour_package_id', '=', $tourPackageId);
        //        }
        //
        //        if ($userType == 'Tour Operator') {
        //            $query = $query->where('tp.tour_operator_id', auth()->user()->tour_operator_id);
        //        } else {
        //            $query = $query->where('tp.tour_operator_id', '=', $tourOperatorId);
        //        }
        //
        //        $query = $query->distinct()->toSql();


        $failedIds = [];
        if (count($query) > 0) {
            $failedIds = array_column($query, 'bookingid');
        }

        $check = Transaction::whereIn('tour_booking_id', $failedIds)
            ->where('status', 'Completed')
            ->get('tour_booking_id');

        $completedIds = [];
        if ($check->count()) {
            $completedIds = array_column($check->toArray(), 'tour_booking_id');
        }


        foreach ($query as $key => $value) {
            if (!empty($value)) {
                $check = in_array($value->bookingid, $completedIds);
                if ($check > 0) {
                    unset($query[$key]);
                }
            }
        }
        $newSortedData = array();
        foreach ($query as $k => $element) {
            $query[$k]->createdate = substr($element->createdate, 0, 10);
            $newSortedData[] = (array)$element;
        }

        if ($export) {
            return $this->FailedPaymentReportExportCsvFile($fromDate, $toDate, $newSortedData, $tourOperatorId, $tourPackageId);
        }

        return $newSortedData;
        //        return Datatables::of($query)
        //            ->addIndexColumn()
        //            ->editColumn('createdate', function ($createdate) {
        //                return substr($createdate->createdate, 0, 10);
        //            })
        //            ->make(true);
    }

    // Failed Payment Report CSV Export
    public function FailedPaymentReportExportCsvFile($fromDate, $toDate, $newSortedData, $tourOperatorId = 0,  $tourPackageId = 0)
    {
        $fileName = 'failed_payment_report' . $fromDate . '_' . $toDate . '.csv';
        $headers = csvHeaders($fileName);
        $columns = csvColumns('failed_payment_report_columns');

        $tourOperatorName = $this->GetTourOperatorName($tourOperatorId, $tourPackageId);
        $callback = function () use ($newSortedData, $columns, $fromDate, $toDate, $tourOperatorName) {

            $file = fopen('php://output', 'w');  // Create a file
            if (!empty($tourOperatorName)) {
                $BlankLine = '';
                if ($tourOperatorName['tourOperatorName']) {
                    $tourOperatorNameRow = array('Tour Operator:', $tourOperatorName['tourOperatorName'], $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourOperatorNameRow);
                }
                if ($tourOperatorName['tourPackageName']) {
                    $tourPackageNameRow = array('Tour Package:', $tourOperatorName['tourPackageName'], $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourPackageNameRow);
                }
                fputcsv($file, array($BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine));
            }

            fputcsv($file, $columns);
            foreach ($newSortedData as $eachData) {
                $row['Booking Id'] = $eachData['bookingid'] ?? '';
                $row['Customer Name'] = $eachData['name'] ?? '';
                $row['Booking Date'] = (isset($eachData['bookingdate'])) ? date("m-d-Y", strtotime($eachData['bookingdate'])) : '';
                $row['Created Date'] = (isset($eachData['createdate'])) ? date("m-d-Y", strtotime($eachData['createdate'])) : '';
                $row['Person'] = $eachData['tourists'] ?? '';
                $row['Amount'] = $eachData['amount'] ?? '';
                $row['Payment'] = $eachData['Payment'] ?? '';
                $row['Status'] = $eachData['status'] ?? '';

                fputcsv($file, array(
                    $row['Booking Id'],
                    $row['Customer Name'],
                    $row['Booking Date'],
                    $row['Created Date'],
                    $row['Person'],
                    $row['Amount'],
                    $row['Payment'],
                    $row['Status']
                ));
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    public function emailreport(Request $request, string $fromDate = "", string $toDate = "",   $tourOperatorId = 0, $tourPackageId = 0, int $export = 0)
    {
        try {
            $userType = userType();

            if ($fromDate == "")
                $fromDate = date('Y-m-d');
            else
                $fromDate = $fromDate . " 00:00:00";

            if ($toDate == "")
                $toDate = date('Y-m-d');
            else
                $toDate = $toDate . " 23:59:59";

            $qry = DB::table('tour_bookings_master AS tbm')
                ->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
                ->join('tour_operators as to', 'to.id', '=', 'tp.tour_operator_id')
                ->join('customers as c', 'c.id', '=', 'tbm.customer_id')
                ->join('tour_bookings_transact AS tbt', 'tbt.tour_booking_id', 'tbm.id')
                ->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
                ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
                ->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
                ->where('tbm.status', 'Booked')
                // ->where('tst.id',1033)
                ->whereBetween('tsd.date', [$fromDate, $toDate])
                ->select([
                    'tbm.id as bookingid',
                    'tbm.email as email',
                    'tst.time_from',
                    'tst.time_to',
                    DB::raw('(tbm.tourists + tbm.infants) as tourists'),
                    DB::raw('DATE_FORMAT(tsd.date, "%Y-%m-%d") as bookingdate')
                ])
                ->orderBy('tsd.date', 'DESC')
                ->orderBy('tst.time_from', 'ASC')
                ->groupBy('tbm.id');

            if ($tourPackageId && $tourPackageId !== 0) {
                $qry = $qry->where('tbm.tour_package_id', '=', $tourPackageId);
            }

            if ($userType == 'Tour Operator') {
                $qry = $qry->where('tp.tour_operator_id', auth()->user()->tour_operator_id);
            } else {
                $qry = $qry->where('tp.tour_operator_id', '=', $tourOperatorId);
            }

            $qry = $qry->get();
            $result = json_decode($qry, true);
            $sortedData = array();
            foreach ($result as $element) {
                $element['time_to'] = date("g:i: A", strtotime($element['time_to']));
                $element['time_from'] = date("g:i: A", strtotime($element['time_from']));
                $timestamp = strtotime($element['bookingdate']);
                $date =  date("m/d/Y", $timestamp);
                if ($request->api == 1) {
                    if (!isset($sortedData[$date])) {
                        $sortedData['details'][] = $element;
                    } else {
                        $sortedData['details'][] = $element;
                    }
                } else {
                    if (!isset($sortedData[$date])) {
                        $sortedData[$date] = array($element);
                    } else {
                        $sortedData[$date][] = $element;
                    }
                }
            }

            if ($export) {
                return $this->EmailReportExportCsvFile($fromDate, $toDate, $sortedData, $tourOperatorId, $tourPackageId);
            }
            return $sortedData;
        } catch (Exception $exception) {
            Log::error($exception);
            return false;
        }
    }

    // Email Report CSV Export
    public function EmailReportExportCsvFile($fromDate, $toDate, $sortedData, $tourOperatorId = 0, $tourPackageId = 0)
    {
        $fileName = 'email_report' . $fromDate . '_' . $toDate . '.csv';
        $headers = csvHeaders($fileName);
        $columns = csvColumns('email_report_column');
        $tourOperatorName = $this->GetTourOperatorName($tourOperatorId, $tourPackageId);

        $fDate = $fromDate;
        $tDate = $toDate;

        $callback = function () use ($sortedData, $columns, $fDate, $tDate, $tourOperatorName) {
            $file = fopen('php://output', 'w');  // Create a file

            $BlankLine = '';

            if (!empty($tourOperatorName)) {
                if ($tourOperatorName['tourOperatorName']) {
                    $tourOperatorNameRow = array('Tour Operator:', $tourOperatorName['tourOperatorName'], $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourOperatorNameRow);
                }
                if ($tourOperatorName['tourPackageName']) {
                    $tourPackageNameRow = array('Tour Package:', $tourOperatorName['tourPackageName'], $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourPackageNameRow);
                }
                fputcsv($file, array($BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine));
            }
            $toEnd = count($sortedData);
            $newSortedData = array();
            foreach ($sortedData as $key => $result_data) {
                if (is_array($result_data)) {
                    foreach ($result_data as $k => $value) {
                        $newSortedData[] = $value;
                    }
                }
            }

            fputcsv($file, $columns);
            foreach ($newSortedData as $each_data) {
                $timestamp = strtotime($each_data['bookingdate']);
                $date =  date("m/d/Y", $timestamp);
                $BookingId = (!empty($each_data)) ? ((isset($each_data['bookingid'])) ? $each_data['bookingid'] : '') : '';
                $BookingDate = (!empty($each_data)) ? ((isset($each_data['bookingdate'])) ? date("m/d/Y", strtotime($each_data['bookingdate'])) : '') : '';
                $BookingTime = (!empty($each_data)) ? ((isset($each_data['time_from']) && isset($each_data['time_to'])) ? $each_data['time_from'] . ' - ' . $each_data['time_to'] : '') : '';
                $BookingTorists = (!empty($each_data)) ? ((isset($each_data['tourists'])) ? $each_data['tourists'] : '') : '';
                $Customer_Email = (!empty($each_data)) ? ((isset($each_data['email'])) ? $each_data['email'] : '') : '';

                fputcsv($file, array($BookingId, $BookingDate, $BookingTime, $BookingTorists, $Customer_Email));
            }


            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Calculate individual permit fees for mixed permit fee scenarios
     * 
     * @param object $element
     * @param float $originalPermitFee
     * @param bool $isDixies
     * @return array
     */
    private function calculateIndividualPermitFees($element, $originalPermitFee, $isDixies)
    {
        $currentPermitFeesArray = []; // Track individual current permit fees
        $refundedpermitfeespersonscount = 0;

        // Initialize arrays for all tourists
        for ($i = 0; $i < $element->tourists; $i++) {
            $currentPermitFeesArray[] = floatval($originalPermitFee);
        }

        if (!empty($element->refundedpermitfee)) {
            $jsonDecodValueRefundedPermitfee = json_decode($element->refundedpermitfee, true);

            foreach ($jsonDecodValueRefundedPermitfee as $index => $countdatarefundedpermitfees) {
                // Skip if we exceed the number of tourists
                if ($index >= $element->tourists) {
                    break;
                }

                // Updated logic to handle new permit fee structure
                if (isset($countdatarefundedpermitfees['status']) && $countdatarefundedpermitfees['status'] == "Y") {
                    // Person has refund - use new_permit_fee if available, otherwise 0
                    $currentPermitFee = isset($countdatarefundedpermitfees['new_permit_fee'])
                        ? floatval($countdatarefundedpermitfees['new_permit_fee'])
                        : 0;

                    $currentPermitFeesArray[$index] = $currentPermitFee;

                    if ($currentPermitFee == 0) {
                        $refundedpermitfeespersonscount++;
                    }
                } else {
                    // Person has no refund - use original permit fee
                    $currentPermitFeesArray[$index] = floatval($originalPermitFee);
                }
            }
        }

        // Apply Dixies-specific logic for pre-purchase consideration
        if ($isDixies) {
            $isPrePurchaseRateGroup = stripos($element->rate_for, 'children') !== false;
            if ($isPrePurchaseRateGroup) {
                // For Dixies children rate groups, set all permit fees to 0
                for ($i = 0; $i < $element->tourists; $i++) {
                    $currentPermitFeesArray[$i] = 0;
                }
                $refundedpermitfeespersonscount = $element->tourists;
            }
        }

        return [$currentPermitFeesArray, $refundedpermitfeespersonscount];
    }

    public function dailyreport(Request $request, string $fromDate = "", string $toDate = "", $tourOperatorId = 0, $tourPackageId = 0, int $export = 0)
    {
        try {
            $fromDate = $fromDate ?: date('Y-m-d') . ' 00:00:00';
            $toDate = $toDate ?: date('Y-m-d') . ' 23:59:59';

            $isTourOperator = auth()->user()->tour_operator_id;
            $tourOperatorId = ($isTourOperator) ? auth()->user()->tour_operator_id : $tourOperatorId;

            $qry = "SELECT
                `tbm`.`id`,
                `tbm`.`status` as `status`,
                `tbm`.`is_group_booking` as `is_group_booking`,
                `tbm`.`is_reserved_for_affiliate` as `is_reserved_for_affiliate`,
                `tbm`.`is_partner_booking` as `is_partner_booking`,
                `tbt`.`total` as `amount`,
                CASE 
                WHEN tbt.infants > 0 THEN tbt.infants
                ELSE tbt.tourists
                END AS tourists,
                `tbt`.`check_in_status` as `checkin`,
                `tbt`.`refund_permitfee_status` as `refundedpermitfee`,
                `tpr`.`rate_for`,
                `tbt`.`permit_fee`,
                `c`.`is_affiliate` as `customer_is_affiliate`
                FROM `tour_bookings_master` AS `tbm`
                INNER JOIN `tour_packages` as `tp` ON `tp`.`id` = `tbm`.`tour_package_id`
                INNER JOIN `tour_operators` as `to` ON `to`.`id` = `tp`.`tour_operator_id`
                INNER JOIN `customers` as `c` ON `c`.`id` = `tbm`.`customer_id`
                INNER JOIN `tour_bookings_transact` as `tbt` ON `tbt`.`tour_booking_id` = `tbm`.`id`
                INNER JOIN `tour_package_rates` as `tpr` ON `tpr`.`id` = `tbt`.`tour_package_rate_id`
                INNER JOIN `tour_slot_times` as `tst` ON `tst`.`id` = `tbm`.`tour_slot_id`
                INNER JOIN `tour_slot_dates` as `tsd` ON `tsd`.`id` = `tst`.`tour_slot_id`";

            $qry .= 'WHERE  `tsd`.`date` between "' . $fromDate . '" and "' . $toDate . '"' . "\n";
            $qry .= "AND `tbm`.`status` = 'Booked'";
            $qry .= "AND (`tbt`.`tourists` > '0' || `tbt`.`infants` > '0') AND `tbt`.`deleted_by` = '0'";

            if ($tourPackageId != 0) {
                $qry .= "AND `tbm`.`tour_package_id` = " . $tourPackageId . "\n";
            }

            if ($tourPackageId == 0 && $tourOperatorId != null) {
                $qry .= "AND `to`.`id` = " . $tourOperatorId . "\n";
            }

            // Apply new filters
            // 1. Payment Status Filter (Paid/Unpaid)
            if ($request->has('paymentStatus') && $request->paymentStatus !== 'all') {
                if ($request->paymentStatus === 'paid') {
                    // Join with tour_booking_history to check for completed payments
                    $qry .= " AND EXISTS (
                        SELECT 1 FROM `tour_booking_history` tbh 
                        WHERE tbh.`tour_booking_id` = `tbm`.`id` 
                        AND tbh.`operation` = 'Payment' 
                        AND tbh.`payment_type` IS NOT NULL
                    )";
                } elseif ($request->paymentStatus === 'unpaid') {
                    // No completed payment found
                    $qry .= " AND NOT EXISTS (
                        SELECT 1 FROM `tour_booking_history` tbh 
                        WHERE tbh.`tour_booking_id` = `tbm`.`id` 
                        AND tbh.`operation` = 'Payment' 
                        AND tbh.`payment_type` IS NOT NULL
                    )";
                }
            }

            // 2. Payment Method Filter
            if ($request->has('paymentMethod') && $request->paymentMethod !== 'all') {
                $paymentMethod = $request->paymentMethod;
                if ($paymentMethod === 'invoice') {
                    // Invoice block: bookings with payment_type = 'Invoiced' AND in invoices table
                    $qry .= " AND EXISTS (
                        SELECT 1 FROM `tour_booking_history` tbh 
                        WHERE tbh.`tour_booking_id` = `tbm`.`id` 
                        AND tbh.`payment_type` = 'Invoiced'
                    ) AND EXISTS (
                        SELECT 1 FROM `invoices` i 
                        JOIN `invoice_bookings` ib ON ib.`invoice_id` = i.`id` 
                        WHERE ib.`tour_booking_id` = `tbm`.`id`
                    )";
                } elseif ($paymentMethod === 'billable') {
                    // Billable block: bookings with payment_type = 'Invoiced' BUT NOT in invoices table
                    $qry .= " AND EXISTS (
                        SELECT 1 FROM `tour_booking_history` tbh 
                        WHERE tbh.`tour_booking_id` = `tbm`.`id` 
                        AND tbh.`payment_type` = 'Invoiced'
                    ) AND NOT EXISTS (
                        SELECT 1 FROM `invoices` i 
                        JOIN `invoice_bookings` ib ON ib.`invoice_id` = i.`id` 
                        WHERE ib.`tour_booking_id` = `tbm`.`id`
                    )";
                } else {
                    // For Cash, Card - check payment_type in tour_booking_history
                    $methodValue = ucfirst($paymentMethod); // Cash, Card
                    $qry .= " AND EXISTS (
                        SELECT 1 FROM `tour_booking_history` tbh 
                        WHERE tbh.`tour_booking_id` = `tbm`.`id` 
                        AND tbh.`payment_type` = '" . $methodValue . "'
                    )";
                }
            }

            // 3. Booking Type Filter (Normal/Affiliate)
            if ($request->has('bookingType') && $request->bookingType !== 'all') {
                if ($request->bookingType === 'affiliate') {
                    // Affiliate bookings
                    $qry .= " AND (`tbm`.`is_reserved_for_affiliate` = 1 
                        OR `tbm`.`is_partner_booking` = 1 
                        OR `c`.`is_affiliate` = 'Yes')";
                } elseif ($request->bookingType === 'normal') {
                    // Normal bookings (not affiliate)
                    $qry .= " AND (`tbm`.`is_reserved_for_affiliate` = 0 
                        AND `tbm`.`is_partner_booking` = 0 
                        AND (`c`.`is_affiliate` = 'No' OR `c`.`is_affiliate` IS NULL))";
                }
            }

            $qry = DB::select($qry);

            $newSortedData = array();

            $check_in = $this->permitFeeAndDetailsReportCheckInStatus($request->api, $newSortedData);

            [$First_Checked_In, $Second_Check_In, $Not_Checked_In, $newSortedData] = $check_in;

            // Get tenant ID to check for Dixies-specific logic
            $isDixies = tenant()->id == 'dixies';

            foreach ($qry as $element) {
                if ($element->tourists == 0) {
                    $element->tourists = 1;
                }

                if (!empty($element->rate_for)) {
                    $PermitFeeCount = (float) $element->permit_fee;

                    // Use helper function to calculate individual permit fees
                    [$currentPermitFeesArray, $refundedpermitfeespersonscount] =
                        $this->calculateIndividualPermitFees($element, $PermitFeeCount, $isDixies);

                    if ($element->is_group_booking == '1') {
                        $ageGroup = 'Person(s)';
                    } else {
                        $ageGroup = str_replace(' ', '_', $element->rate_for);
                    }

                    $statuses = [
                        "1" => $First_Checked_In,
                        "2" => $Second_Check_In,
                        "N" => $Not_Checked_In,
                        "A" => "Absent"
                    ];

                    if ($element->checkin) {
                        $jsonDecodValue = json_decode($element->checkin, true);
                        $touristCount = $element->tourists;

                        if (count($jsonDecodValue) > $touristCount) {
                            $jsonDecodValue = array_slice($jsonDecodValue, 0, $touristCount);
                        } else if (count($jsonDecodValue) < $touristCount) {
                            if (!isset($newSortedData[$Not_Checked_In]['ageGroups'][$ageGroup]['rateCount'])) {
                                $newSortedData[$Not_Checked_In]['ageGroups'][$ageGroup] = [
                                    'rateCount' => 0,
                                    'PermitFeeCount' => 0,
                                    'RefundPermitFeeCount' => 0
                                ];
                            }

                            $additionalTourists = $touristCount - count($jsonDecodValue);
                            $newSortedData[$Not_Checked_In]['ageGroups'][$ageGroup]['rateCount'] += $additionalTourists;

                            // Calculate permit fee for additional tourists using individual permit fees
                            $additionalPermitFees = 0;
                            for ($i = count($jsonDecodValue); $i < $touristCount; $i++) {
                                $currentPersonPermitFee = isset($currentPermitFeesArray[$i]) ? $currentPermitFeesArray[$i] : $PermitFeeCount;

                                // Apply Dixies-specific logic for permit fee amounts
                                if ($isDixies) {
                                    $isPrePurchaseRateGroup = stripos($element->rate_for, 'children') !== false;
                                    if ($isPrePurchaseRateGroup) {
                                        $currentPersonPermitFee = 0;
                                    }
                                }

                                $additionalPermitFees += $currentPersonPermitFee;
                            }

                            $newSortedData[$Not_Checked_In]['ageGroups'][$ageGroup]['PermitFeeCount'] += $additionalPermitFees;
                        }

                        foreach ($jsonDecodValue as $keycheckin => $countdata) {

                            $checkInKey = $statuses[$countdata['status']] ?? $Not_Checked_In;

                            if (!isset($newSortedData[$checkInKey]['ageGroups'][$ageGroup])) {
                                $newSortedData[$checkInKey]['ageGroups'][$ageGroup] = [
                                    'rateCount' => 0,
                                    'PermitFeeCount' => 0,
                                    'RefundPermitFeeCount' => 0
                                ];
                            }

                            // Check if this person should be counted as pre-purchase
                            $isPrePurchase = false;
                            if ($isDixies) {
                                $isPrePurchaseRateGroup = stripos($element->rate_for, 'children') !== false;
                                $currentPersonPermitFee = isset($currentPermitFeesArray[$keycheckin]) ? $currentPermitFeesArray[$keycheckin] : $PermitFeeCount;
                                $isPrePurchase = $isPrePurchaseRateGroup || ($currentPersonPermitFee == 0);
                            } else {
                                $currentPersonPermitFee = isset($currentPermitFeesArray[$keycheckin]) ? $currentPermitFeesArray[$keycheckin] : $PermitFeeCount;
                                $isPrePurchase = ($currentPersonPermitFee == 0);
                            }

                            $newData = [
                                'rateCount' => 1,
                                'PermitFeeCount' => $PermitFeeCount,
                                'RefundPermitFeeCount' => $isPrePurchase ? 1 : 0
                            ];

                            if ($request->api == 1 && !isset($newSortedData[$checkInKey]['ageGroups'][$ageGroup]['name'])) {
                                $newSortedData[$checkInKey]['ageGroups'][$ageGroup]['name'] = $element->rate_for;
                            }

                            $newSortedDataKey = $checkInKey;
                            if ($countdata['status'] == 'A') {
                                $newSortedDataKey = 'Absent';
                            }

                            $newSortedData[$newSortedDataKey]['ageGroups'][$ageGroup]['rateCount']++;
                            $newSortedData[$newSortedDataKey]['ageGroups'][$ageGroup]['RefundPermitFeeCount'] += $newData['RefundPermitFeeCount'];

                            // Use individual current permit fee instead of refund calculation
                            $currentPersonPermitFee = isset($currentPermitFeesArray[$keycheckin]) ? $currentPermitFeesArray[$keycheckin] : $PermitFeeCount;

                            // Apply Dixies-specific logic for permit fee amounts
                            if ($isDixies) {
                                $isPrePurchaseRateGroup = stripos($element->rate_for, 'children') !== false;
                                if ($isPrePurchaseRateGroup) {
                                    $currentPersonPermitFee = 0;
                                }
                            }

                            $newSortedData[$newSortedDataKey]['ageGroups'][$ageGroup]['PermitFeeCount'] += ($newSortedDataKey == "Absent") ? 0 : $currentPersonPermitFee;
                        }
                    } else {
                        $touristCount = $element->tourists;

                        if (!isset($newSortedData[$Not_Checked_In]['ageGroups'][$ageGroup])) {
                            $newSortedData[$Not_Checked_In]['ageGroups'][$ageGroup] = [
                                'rateCount' => 0,
                                'PermitFeeCount' => 0,
                                'RefundPermitFeeCount' => 0,
                            ];
                        }

                        $newSortedData[$Not_Checked_In]['ageGroups'][$ageGroup]['rateCount'] += $touristCount;
                        $refundPermitFees = ($PermitFeeCount > 0) ? $refundedpermitfeespersonscount : ($touristCount + $refundedpermitfeespersonscount);

                        // Calculate total current permit fees for all people in this rate group
                        $totalCurrentPermitFees = 0;
                        for ($i = 0; $i < $touristCount; $i++) {
                            $currentPersonPermitFee = isset($currentPermitFeesArray[$i]) ? $currentPermitFeesArray[$i] : $PermitFeeCount;

                            // Apply Dixies-specific logic for permit fee amounts
                            if ($isDixies) {
                                $isPrePurchaseRateGroup = stripos($element->rate_for, 'children') !== false;
                                if ($isPrePurchaseRateGroup) {
                                    $currentPersonPermitFee = 0;
                                }
                            }

                            $totalCurrentPermitFees += $currentPersonPermitFee;
                        }

                        $newSortedData[$Not_Checked_In]['ageGroups'][$ageGroup]['PermitFeeCount'] += $totalCurrentPermitFees;
                        $newSortedData[$Not_Checked_In]['ageGroups'][$ageGroup]['RefundPermitFeeCount'] += $refundPermitFees;

                        if ($request->api == 1 && !isset($newSortedData[$Not_Checked_In]['ageGroups'][$ageGroup]['name'])) {
                            $newSortedData[$Not_Checked_In]['ageGroups'][$ageGroup]['name'] = $element->rate_for;
                        }
                    }
                }
            }
            $newSortedData = $this->permitFeeAndDetailsReportOverallCalculation($request->api, $Not_Checked_In, $tourOperatorId, $newSortedData, 0);

            if ($request->api == 1 && !empty($newSortedData)) {
                foreach ($newSortedData as $k => $checkedIn) {
                    if (gettype($checkedIn) == "array") {
                        foreach ($checkedIn as $key => $value) {
                            if (gettype($value) == "array" && $key == 'ageGroups') {
                                $j = 0;
                                foreach ($value as $ky => $vl) {
                                    $newSortedData[$k][$key][$j] = $vl;
                                    unset($newSortedData[$k][$key][$ky]);
                                    $j++;
                                }
                            }
                        }
                        if (!isset($checkedIn['ageGroups'])) {
                            $checkedIn['ageGroups'] = [
                                'rateCount' => 0,
                                'PermitFeeCount' => 0,
                                'RefundPermitFeeCount' => 0,
                                'name' => 0
                            ];

                            $newSortedData[$k]['ageGroups'][] = $checkedIn['ageGroups'];
                        }
                    }
                }
            }

            if ($export) {
                return $this->DailyReportExportCsvFile($fromDate, $toDate, $newSortedData, $tourOperatorId, $tourPackageId);
            }

            return $newSortedData;
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

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
            $tourOperatorDetails = TourOperator::find(auth()->user()->tour_operator_id);
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

    // Permit Fee Report(Daily Report) CSV Report
    public function DailyReportExportCsvFile($fromDate, $toDate, $newSortedData, $tourOperatorId = 0, $tourPackageId = 0)
    {
        $fileName = 'permitfeesreport_' . $fromDate . '_' . $toDate . '.csv';

        $headers = csvHeaders($fileName);
        $columns = csvColumns('daily_report_columns');
        $tourOperatorName = $this->GetTourOperatorName($tourOperatorId, $tourPackageId);

        $rows = [];
        $blankLine = '';
        $row = ['BlankLine' => $blankLine];
        if (!empty($tourOperatorName)) {
            if ($tourOperatorName['tourOperatorName']) {
                $rows[] = ['Tour Operator:', $tourOperatorName['tourOperatorName'], $row['BlankLine'], $row['BlankLine'], $row['BlankLine']];
            }
            if ($tourOperatorName['tourPackageName']) {
                $rows[] = ['Tour Package:', $tourOperatorName['tourPackageName'], $row['BlankLine'], $row['BlankLine'], $row['BlankLine']];
            }
            $rows[] = [$row['BlankLine'], $row['BlankLine'], $row['BlankLine'], $row['BlankLine'], $row['BlankLine']];
        }

        $rows[] = $columns;

        $firstCheckinRateCount = 0;
        $firstCheckinRefundPermitFeeCount = 0;
        $firstCheckinPermitFee = 0;
        $secondCheckinRateCount = 0;
        $secondCheckinRefundPermitFeeCount = 0;
        $secondCheckinPermitFee = 0;
        $notCheckinRateCount = 0;
        $notCheckinRefundPermitFeeCount = 0;
        $notCheckinPermitFee = 0;
        $absentRateCount = 0;
        $absentRefundPermitFeeCount = 0;
        $absentPermitFee = 0;

        foreach ($newSortedData as $key => $each_data) {
            if (is_array($each_data) && count($each_data) > 2) {
                $rateGroup = str_replace('_', ' ', $key);
                $rows[] = [$rateGroup, $blankLine, $blankLine, $blankLine, $blankLine];

                foreach ($each_data as $ky => $ageGroup) {
                    if (is_array($ageGroup)) {
                        foreach ($ageGroup as $keys => $value) {
                            if ($keys != "keyValue" && $keys != "keyCheckInStatus") {
                                // Sum as per check-in status
                                switch ($key) {
                                    case "1st Checked In":
                                        $firstCheckinRateCount += $value['rateCount'];
                                        $firstCheckinRefundPermitFeeCount += $value['RefundPermitFeeCount'];
                                        $firstCheckinPermitFee += $value['PermitFeeCount'];
                                        break;
                                    case "2nd Check In":
                                        $secondCheckinRateCount += $value['rateCount'];
                                        $secondCheckinRefundPermitFeeCount += $value['RefundPermitFeeCount'];
                                        $secondCheckinPermitFee += $value['PermitFeeCount'];
                                        break;
                                    case "Not Checked In":
                                        $notCheckinRateCount += $value['rateCount'];
                                        $notCheckinRefundPermitFeeCount += $value['RefundPermitFeeCount'];
                                        $notCheckinPermitFee += $value['PermitFeeCount'];
                                        break;
                                    case "Absent":
                                        $absentRateCount += $value['rateCount'];
                                        $absentRefundPermitFeeCount += $value['RefundPermitFeeCount'];
                                        $absentPermitFee += $value['PermitFeeCount'];
                                        break;
                                }
                                $rateGroup = str_replace('_', ' ', $keys);
                                // For Absent status, Permit Pax should be 0
                                $permitPax = ($key == "Absent") ? 0 : ($value['rateCount'] - $value['RefundPermitFeeCount']);
                                $rows[] = [$blankLine, $rateGroup, $value['rateCount'], $value['RefundPermitFeeCount'], $permitPax, $value['PermitFeeCount']];
                            }
                        }
                    }
                }

                if ($key == "1st Checked In") {
                    $rows[] = [$blankLine, $blankLine, $firstCheckinRateCount, $firstCheckinRefundPermitFeeCount, ($firstCheckinRateCount - $firstCheckinRefundPermitFeeCount), $firstCheckinPermitFee];
                } elseif ($key == "2nd Check In") {
                    $rows[] = [$blankLine, $blankLine, $secondCheckinRateCount, $secondCheckinRefundPermitFeeCount, ($secondCheckinRateCount - $secondCheckinRefundPermitFeeCount), $secondCheckinPermitFee];
                } elseif ($key == "Not Checked In") {
                    $rows[] = [$blankLine, $blankLine, $notCheckinRateCount, $notCheckinRefundPermitFeeCount, ($notCheckinRateCount - $notCheckinRefundPermitFeeCount), $notCheckinPermitFee];
                } elseif ($key == "Absent") {
                    $rows[] = [$blankLine, $blankLine, $absentRateCount, $absentRefundPermitFeeCount, 0, $absentPermitFee];
                }

                $rows[] = [$blankLine];

                // Reset counters
                $firstCheckinRateCount = $firstCheckinRefundPermitFeeCount = $firstCheckinPermitFee = 0;
                $secondCheckinRateCount = $secondCheckinRefundPermitFeeCount = $secondCheckinPermitFee = 0;
                $notCheckinRateCount = $notCheckinRefundPermitFeeCount = $notCheckinPermitFee = 0;
                $absentRateCount = $absentRefundPermitFeeCount = $absentPermitFee = 0;
            } elseif (is_array($each_data) && count($each_data) == 2) {
                $row['KeyValue'] = $each_data['keyValue'];
                $row['rateGroup'] = "Infant (Ages 3 & Under)";
                $row['rateCount'] = 0;
                $row['refundPermitFeeCount'] = 0;
                $row['permitFee'] = 0;

                $rows[] = [$row['KeyValue'], $row['rateGroup'], $row['rateCount'], $row['refundPermitFeeCount'], ($row['rateCount'] - $row['refundPermitFeeCount']), $row['permitFee']];
                $rows[] = [$row['BlankLine'], $row['BlankLine'], $row['rateCount'], $row['refundPermitFeeCount'], ($row['rateCount'] - $row['refundPermitFeeCount']), $row['permitFee']];
            }
        }

        $rows[] = [$row['BlankLine'], $row['BlankLine'], $row['BlankLine'], $row['BlankLine'], $row['BlankLine'], $row['BlankLine']];
        $rows[] = ['Total Tourist:', $row['BlankLine'], $row['BlankLine'], $row['BlankLine'], $row['BlankLine'], $newSortedData['totalTourists']];
        $rows[] = ['Permit Fee Tourists:', $row['BlankLine'], $row['BlankLine'], $row['BlankLine'], $row['BlankLine'], $newSortedData['permitFeeTourists']];
        $rows[] = ['Permit Fee:', $row['BlankLine'], $row['BlankLine'], $row['BlankLine'], $row['BlankLine'], $newSortedData['totalPermitFees']];

        $callback = function () use ($rows) {
            $file = fopen('php://output', 'w');  // Create a file

            foreach ($rows as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function dailyreportdetails(Request $request, string $fromDate = "", string $toDate = "",  $tourOperatorId = 0, $tourPackageId = 0, int $export = 0)
    {
        try {
            $fromDate = $fromDate ?: date('Y-m-d') . ' 00:00:00';
            $toDate = $toDate ?: date('Y-m-d') . ' 23:59:59';

            $isTourOperator = auth()->user()->tour_operator_id;
            $tourOperatorId = ($isTourOperator) ? auth()->user()->tour_operator_id : $tourOperatorId;
            $qry = "SELECT
                        `tbm`.`id`,
                        `tbm`.`status` as `status`,
                        `tbm`.`is_group_booking` as `is_group_booking`,
                        `tbt`.`total` as `amount`,
                        CASE 
                        WHEN tbt.infants > 0 THEN tbt.infants
                        ELSE tbt.tourists
                        END AS tourists,
                        `tbt`.`check_in_status` as `checkin`,
                        `tbt`.`refund_permitfee_status` as `refundedpermitfee`,
                        `tpr`.`rate_for`,
                        `tbt`.`permit_fee`
                        FROM `tour_bookings_master` AS `tbm`
                        INNER JOIN `tour_packages` as `tp` ON `tp`.`id` = `tbm`.`tour_package_id`
                        INNER JOIN `tour_operators` as `to` ON `to`.`id` = `tp`.`tour_operator_id`
                        INNER JOIN `customers` as `c` ON `c`.`id` = `tbm`.`customer_id`
                        INNER JOIN `tour_bookings_transact` as `tbt` ON `tbt`.`tour_booking_id` = `tbm`.`id`
                        INNER JOIN `tour_package_rates` as `tpr` ON `tpr`.`id` = `tbt`.`tour_package_rate_id`
                        INNER JOIN `tour_slot_times` as `tst` ON `tst`.`id` = `tbm`.`tour_slot_id`
                        INNER JOIN `tour_slot_dates` as `tsd` ON `tsd`.`id` = `tst`.`tour_slot_id`";
            $qry .= 'WHERE  `tsd`.`date` between "' . $fromDate . '" and "' . $toDate . '"' . "\n";
            $qry .= "AND `tbm`.`status` = 'Booked'";
            $qry .= "AND (`tbt`.`tourists` > '0' || `tbt`.`infants` > '0') AND `tbt`.`deleted_by` = '0' ";

            if ($tourPackageId != 0) {
                $qry .= "AND `tbm`.`tour_package_id` = " . $tourPackageId . "\n";
            }
            if ($tourPackageId == 0 && $tourOperatorId !== null) {
                $qry .= "AND `to`.`id` = " . $tourOperatorId . "\n";
            }

            // Apply the same filters as the main permit report
            // 1. Payment Status Filter (Paid/Unpaid)
            if ($request->has('paymentStatus') && $request->paymentStatus !== 'all') {
                if ($request->paymentStatus === 'paid') {
                    // Join with tour_booking_history to check for completed payments
                    $qry .= " AND EXISTS (
                        SELECT 1 FROM `tour_booking_history` tbh 
                        WHERE tbh.`tour_booking_id` = `tbm`.`id` 
                        AND tbh.`operation` = 'Payment' 
                        AND tbh.`payment_type` IS NOT NULL
                    )";
                } elseif ($request->paymentStatus === 'unpaid') {
                    // No completed payment found
                    $qry .= " AND NOT EXISTS (
                        SELECT 1 FROM `tour_booking_history` tbh 
                        WHERE tbh.`tour_booking_id` = `tbm`.`id` 
                        AND tbh.`operation` = 'Payment' 
                        AND tbh.`payment_type` IS NOT NULL
                    )";
                }
            }

            // 2. Payment Method Filter
            if ($request->has('paymentMethod') && $request->paymentMethod !== 'all') {
                $paymentMethod = $request->paymentMethod;
                if ($paymentMethod === 'invoice') {
                    // Invoice block: bookings with payment_type = 'Invoiced' AND in invoices table
                    $qry .= " AND EXISTS (
                        SELECT 1 FROM `tour_booking_history` tbh 
                        WHERE tbh.`tour_booking_id` = `tbm`.`id` 
                        AND tbh.`payment_type` = 'Invoiced'
                    ) AND EXISTS (
                        SELECT 1 FROM `invoices` i 
                        JOIN `invoice_bookings` ib ON ib.`invoice_id` = i.`id` 
                        WHERE ib.`tour_booking_id` = `tbm`.`id`
                    )";
                } elseif ($paymentMethod === 'billable') {
                    // Billable block: bookings with payment_type = 'Invoiced' BUT NOT in invoices table
                    $qry .= " AND EXISTS (
                        SELECT 1 FROM `tour_booking_history` tbh 
                        WHERE tbh.`tour_booking_id` = `tbm`.`id` 
                        AND tbh.`payment_type` = 'Invoiced'
                    ) AND NOT EXISTS (
                        SELECT 1 FROM `invoices` i 
                        JOIN `invoice_bookings` ib ON ib.`invoice_id` = i.`id` 
                        WHERE ib.`tour_booking_id` = `tbm`.`id`
                    )";
                } else {
                    // For Cash, Card - check payment_type in tour_booking_history
                    $methodValue = ucfirst($paymentMethod); // Cash, Card
                    $qry .= " AND EXISTS (
                        SELECT 1 FROM `tour_booking_history` tbh 
                        WHERE tbh.`tour_booking_id` = `tbm`.`id` 
                        AND tbh.`payment_type` = '" . $methodValue . "'
                    )";
                }
            }

            // 3. Booking Type Filter (Normal/Affiliate)
            if ($request->has('bookingType') && $request->bookingType !== 'all') {
                if ($request->bookingType === 'affiliate') {
                    // Affiliate bookings
                    $qry .= " AND (`tbm`.`is_reserved_for_affiliate` = 1 
                        OR `tbm`.`is_partner_booking` = 1 
                        OR `c`.`is_affiliate` = 'Yes')";
                } elseif ($request->bookingType === 'normal') {
                    // Normal bookings (not affiliate)
                    $qry .= " AND (`tbm`.`is_reserved_for_affiliate` = 0 
                        AND `tbm`.`is_partner_booking` = 0 
                        AND (`c`.`is_affiliate` = 'No' OR `c`.`is_affiliate` IS NULL))";
                }
            }

            $qry = DB::select($qry);
            $newSortedData = array();

            $check_in = $this->permitFeeAndDetailsReportCheckInStatus($request->api, $newSortedData);

            [$First_Checked_In, $Second_Check_In, $Not_Checked_In, $newSortedData] = $check_in;

            // Get tenant ID to check for Dixies-specific logic
            $isDixies = tenant()->id == 'dixies';

            foreach ($qry as $element) {
                if ($element->tourists == 0) {
                    $element->tourists = 1;
                }

                if (!empty($element->rate_for)) {
                    $PermitFeeCount = (float) $element->permit_fee;

                    // Use helper function to calculate individual permit fees
                    [$currentPermitFeesArray, $refundedpermitfeespersonscount] =
                        $this->calculateIndividualPermitFees($element, $PermitFeeCount, $isDixies);

                    if ($element->is_group_booking == '1') {
                        $ageGroup = 'Person(s)';
                    } else {
                        $ageGroup = str_replace(' ', '_', $element->rate_for);
                    }

                    $statuses = [
                        "1" => $First_Checked_In,
                        "2" => $Second_Check_In,
                        "N" => $Not_Checked_In,
                        "A" => "Absent"
                    ];

                    if ($element->checkin) {
                        $jsonDecodValue = json_decode($element->checkin, true);
                        $touristCount = $element->tourists;

                        if (count($jsonDecodValue) > $touristCount) {
                            $jsonDecodValue = array_slice($jsonDecodValue, 0, $touristCount);
                        } else if (count($jsonDecodValue) < $touristCount) {
                            if (!isset($newSortedData[$Not_Checked_In]['ageGroups'][$ageGroup]['rateCount'])) {
                                $newSortedData[$Not_Checked_In]['ageGroups'][$ageGroup] = [
                                    'rateCount' => 0,
                                    'PermitFeeCount' => 0,
                                    'RefundPermitFeeCount' => 0,
                                    'detailsBooking' => [],
                                    'detailsRefundedBooking' => [],
                                ];
                            }

                            $additionalTourists = $touristCount - count($jsonDecodValue);
                            $newSortedData[$Not_Checked_In]['ageGroups'][$ageGroup]['rateCount'] += $additionalTourists;

                            // Calculate permit fee for additional tourists using individual permit fees
                            $additionalPermitFees = 0;
                            $hasZeroPermitFee = false;
                            for ($i = count($jsonDecodValue); $i < $touristCount; $i++) {
                                $currentPersonPermitFee = isset($currentPermitFeesArray[$i]) ? $currentPermitFeesArray[$i] : $PermitFeeCount;

                                // Apply Dixies-specific logic for permit fee amounts
                                if ($isDixies) {
                                    $isPrePurchaseRateGroup = stripos($element->rate_for, 'children') !== false;
                                    if ($isPrePurchaseRateGroup) {
                                        $currentPersonPermitFee = 0;
                                    }
                                }

                                $additionalPermitFees += $currentPersonPermitFee;
                                if ($currentPersonPermitFee == 0) {
                                    $hasZeroPermitFee = true;
                                }
                            }

                            $newSortedData[$Not_Checked_In]['ageGroups'][$ageGroup]['PermitFeeCount'] += $additionalPermitFees;
                            $newSortedData[$Not_Checked_In]['ageGroups'][$ageGroup]['detailsBooking'][] = $element->id;
                            if ($hasZeroPermitFee) {
                                $newSortedData[$Not_Checked_In]['ageGroups'][$ageGroup]['detailsRefundedBooking'][] = $element->id;
                            }
                        }

                        foreach ($jsonDecodValue as $keycheckin => $countdata) {

                            $checkInKey = $statuses[$countdata['status']] ?? $Not_Checked_In;

                            if (!isset($newSortedData[$checkInKey]['ageGroups'][$ageGroup])) {
                                $newSortedData[$checkInKey]['ageGroups'][$ageGroup] = [
                                    'rateCount' => 0,
                                    'PermitFeeCount' => 0,
                                    'RefundPermitFeeCount' => 0,
                                    'detailsBooking' => [],
                                    'detailsRefundedBooking' => [],
                                ];
                            }

                            // Check if this person should be counted as pre-purchase
                            $isPrePurchase = false;
                            if ($isDixies) {
                                $isPrePurchaseRateGroup = stripos($element->rate_for, 'children') !== false;
                                $currentPersonPermitFee = isset($currentPermitFeesArray[$keycheckin]) ? $currentPermitFeesArray[$keycheckin] : $PermitFeeCount;
                                $isPrePurchase = $isPrePurchaseRateGroup || ($currentPersonPermitFee == 0);
                            } else {
                                $currentPersonPermitFee = isset($currentPermitFeesArray[$keycheckin]) ? $currentPermitFeesArray[$keycheckin] : $PermitFeeCount;
                                $isPrePurchase = ($currentPersonPermitFee == 0);
                            }

                            $newData = [
                                'rateCount' => 1,
                                'RefundPermitFeeCount' => $isPrePurchase ? 1 : 0,
                                'detailsBooking' => $element->id,
                            ];

                            if ($request->api == 1 && !isset($newSortedData[$checkInKey]['ageGroups'][$ageGroup]['name'])) {
                                $newSortedData[$checkInKey]['ageGroups'][$ageGroup]['name'] = $element->rate_for;
                            }

                            $newSortedDataKey = $checkInKey;
                            if ($countdata['status'] == 'A') {
                                $newSortedDataKey = 'Absent';
                            }

                            $newSortedData[$newSortedDataKey]['ageGroups'][$ageGroup]['rateCount']++;
                            $newSortedData[$newSortedDataKey]['ageGroups'][$ageGroup]['RefundPermitFeeCount'] += $newData['RefundPermitFeeCount'];

                            // Use individual current permit fee instead of refund calculation
                            $currentPersonPermitFee = isset($currentPermitFeesArray[$keycheckin]) ? $currentPermitFeesArray[$keycheckin] : $PermitFeeCount;

                            // Apply Dixies-specific logic for permit fee amounts
                            if ($isDixies) {
                                $isPrePurchaseRateGroup = stripos($element->rate_for, 'children') !== false;
                                if ($isPrePurchaseRateGroup) {
                                    $currentPersonPermitFee = 0;
                                }
                            }

                            $newSortedData[$newSortedDataKey]['ageGroups'][$ageGroup]['PermitFeeCount'] += ($newSortedDataKey == "Absent") ? 0 : $currentPersonPermitFee;

                            $newSortedData[$newSortedDataKey]['ageGroups'][$ageGroup]['detailsBooking'][] = $element->id;
                            if ($currentPersonPermitFee == 0) {
                                $newSortedData[$newSortedDataKey]['ageGroups'][$ageGroup]['detailsRefundedBooking'][] = $element->id;
                            }
                        }
                    } else {
                        $touristCount = $element->tourists;

                        if (!isset($newSortedData[$Not_Checked_In]['ageGroups'][$ageGroup])) {
                            $newSortedData[$Not_Checked_In]['ageGroups'][$ageGroup] = [
                                'rateCount' => 0,
                                'PermitFeeCount' => 0,
                                'RefundPermitFeeCount' => 0,
                                'detailsBooking' => [],
                                'detailsRefundedBooking' => [],
                            ];
                        }

                        $newSortedData[$Not_Checked_In]['ageGroups'][$ageGroup]['rateCount'] += $touristCount;
                        $refundPermitFees = ($PermitFeeCount > 0) ? $refundedpermitfeespersonscount : ($touristCount + $refundedpermitfeespersonscount);

                        // Calculate total current permit fees for all people in this rate group
                        $totalCurrentPermitFees = 0;
                        $hasZeroPermitFee = false;
                        for ($i = 0; $i < $touristCount; $i++) {
                            $currentPersonPermitFee = isset($currentPermitFeesArray[$i]) ? $currentPermitFeesArray[$i] : $PermitFeeCount;

                            // Apply Dixies-specific logic for permit fee amounts
                            if ($isDixies) {
                                $isPrePurchaseRateGroup = stripos($element->rate_for, 'children') !== false;
                                if ($isPrePurchaseRateGroup) {
                                    $currentPersonPermitFee = 0;
                                }
                            }

                            $totalCurrentPermitFees += $currentPersonPermitFee;
                            if ($currentPersonPermitFee == 0) {
                                $hasZeroPermitFee = true;
                            }
                        }

                        $newSortedData[$Not_Checked_In]['ageGroups'][$ageGroup]['PermitFeeCount'] += $totalCurrentPermitFees;
                        $newSortedData[$Not_Checked_In]['ageGroups'][$ageGroup]['RefundPermitFeeCount'] += $refundPermitFees;
                        $newSortedData[$Not_Checked_In]['ageGroups'][$ageGroup]['detailsBooking'][] = $element->id;

                        if ($hasZeroPermitFee) {
                            $newSortedData[$Not_Checked_In]['ageGroups'][$ageGroup]['detailsRefundedBooking'][] = $element->id;
                        }

                        if ($request->api == 1 && !isset($newSortedData[$Not_Checked_In]['ageGroups'][$ageGroup]['name'])) {
                            $newSortedData[$Not_Checked_In]['ageGroups'][$ageGroup]['name'] = $element->rate_for;
                        }
                    }
                }
            }

            foreach ($newSortedData as $key => $data) {
                foreach ($data as $keys => $value) {
                    if (gettype($value) == "array") {
                        foreach ($value as $ky => $vl) {
                            if (array_key_exists('detailsRefundedBooking', $newSortedData[$key][$keys][$ky])) {
                                $newSortedData[$key][$keys][$ky]['detailsRefundedBooking'] = array_values(array_unique($vl['detailsRefundedBooking']));
                            }
                            if (array_key_exists('detailsBooking', $newSortedData[$key][$keys][$ky])) {
                                $newSortedData[$key][$keys][$ky]['detailsBooking'] = array_values(array_unique($vl['detailsBooking']));
                            }
                        }
                    }
                }
            }

            $newSortedData = $this->permitFeeAndDetailsReportOverallCalculation($request->api, $Not_Checked_In, $tourOperatorId, $newSortedData, 1);

            if ($request->api == 1 && !empty($newSortedData)) {
                foreach ($newSortedData as $k => $checkedIn) {
                    if (gettype($checkedIn) == "array") {
                        foreach ($checkedIn as $key => $value) {
                            if (gettype($value) == "array" && $key == 'ageGroups') {
                                $j = 0;
                                foreach ($value as $ky => $vl) {
                                    $newSortedData[$k][$key][$j] = $vl;
                                    unset($newSortedData[$k][$key][$ky]);
                                    $j++;
                                }
                            }
                        }
                        if (!isset($checkedIn['ageGroups'])) {
                            $checkedIn['ageGroups'] = [
                                'rateCount' => 0,
                                'PermitFeeCount' => 0,
                                'RefundPermitFeeCount' => 0,
                                'detailsBooking' => [],
                                'detailsRefundedBooking' => [],
                            ];
                            $newSortedData[$k]['ageGroups'][] = $checkedIn['ageGroups'];
                        }
                    }
                }
            }

            if ($export) {
                return $this->DailyReportDetailsExportCsvFile($fromDate, $toDate, $newSortedData, $tourOperatorId, $tourPackageId);
            }

            return $newSortedData;
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    public function permitFeeAndDetailsReportCheckInStatus($is_api, $newSortedData)
    {
        $first_checkin_api = "1st_Checked_In";
        $first_checkin = "1st Checked In";
        $second_checkin_api = "2nd_Check_In";
        $second_checkin = "2nd Check In";
        $not_checkin_api = "Not_Checked_In";
        $not_checkin = "Not Checked In";

        $keyValueMapping = [
            0 => [$first_checkin_api, $first_checkin, '1'],
            1 => [$second_checkin_api, $second_checkin, '2'],
            2 => [$not_checkin_api, $not_checkin, 'N'],
            3 => ['Absent', 'Absent', 'A']
        ];

        $newSortedData = [];
        foreach ($keyValueMapping as list($apiKey, $displayKey, $checkInStatus)) {
            $checkInKey = $is_api == 1 ? $apiKey : $displayKey;

            $mediumSortedData = [
                'keyValue' => $checkInKey,
                'keyCheckInStatus' => $checkInStatus
            ];

            if (!isset($newSortedData[$checkInKey])) {
                $newSortedData[$checkInKey] = $mediumSortedData;
            } else {
                $newSortedData[$checkInKey][] = $mediumSortedData;
            }
        }

        return [
            $is_api == 1 ? $first_checkin_api : $first_checkin,
            $is_api == 1 ? $second_checkin_api : $second_checkin,
            $is_api == 1 ? $not_checkin_api : $not_checkin,
            $newSortedData
        ];
    }

    public function permitFeeAndDetailsReportOverallCalculation($is_api, $Not_Checked_In, $tourOperatorId, $newSortedData, $permit_fee_report_flag)
    {
        if ($is_api != 1 && $tourOperatorId != '') {
            $tourOperatorCheckInStatues = DB::table('tour_operators')->where('tour_operators.id', $tourOperatorId)
                ->select([
                    'tour_operators.checkins'
                ])->first();
            $tourOperatorCheckInStatues = json_decode($tourOperatorCheckInStatues->checkins, true);
            foreach ($newSortedData as $k => $checkedIn) {
                if (!empty($tourOperatorCheckInStatues) && !in_array($checkedIn['keyCheckInStatus'], $tourOperatorCheckInStatues)) {
                    unset($newSortedData[$k]);
                }
            }
        }

        $totalTourists = 0;
        $totalRefundedTourists = 0;
        $totalPermitFees = 0;

        $NotCheckedInApi = $Not_Checked_In;

        foreach ($newSortedData as $k => $checkedIn) {
            foreach ($checkedIn as $key => $value) {
                if (gettype($value) == "array") {
                    foreach ($value as $keys => $val) {
                        if ($permit_fee_report_flag == 1) {
                            if (gettype($val) == "array") {
                                foreach ($val as $ky => $vl) {
                                    if (gettype($vl) != "array") {
                                        if (str_contains($ky, 'PermitFeeCount') && !str_contains($ky, 'RefundPermitFeeCount') && $k != $NotCheckedInApi) {
                                            $totalPermitFees += (float)$vl;
                                        } elseif ($ky != 'keyValue' && $ky != 'keyCheckInStatus' && str_contains($ky, 'RefundPermitFeeCount') && $k != $NotCheckedInApi) {
                                            $totalRefundedTourists += (float)$vl;
                                        } elseif ($ky != 'keyValue' && $ky != 'keyCheckInStatus' && !str_contains($ky, 'detailsBooking') && !str_contains($ky, 'PermitFeeCount') && !str_contains($ky, 'RefundPermitFeeCount')) {
                                            $totalTourists += (float)$vl;
                                        }
                                    }
                                }
                            }
                        } else {
                            foreach ($val as $ky => $vl) {
                                if (str_contains($ky, 'PermitFeeCount') && !str_contains($ky, 'RefundPermitFeeCount') && $k != $NotCheckedInApi) {
                                    $totalPermitFees += (float)$vl;
                                } elseif ($ky != 'keyValue' && $ky != 'keyCheckInStatus' && str_contains($ky, 'RefundPermitFeeCount') && $k != $NotCheckedInApi) {
                                    $totalRefundedTourists += (float)$vl;
                                } elseif ($ky != 'keyValue' && $ky != 'keyCheckInStatus' && !str_contains($ky, 'PermitFeeCount') && !str_contains($ky, 'RefundPermitFeeCount')) {
                                    $totalTourists += (float)$vl;
                                }
                            }
                        }
                    }
                }
            }
        }

        $absentPax = 0;
        $refundedAbsentTourists = 0;
        if (isset($newSortedData['Absent'])) {
            foreach ($newSortedData['Absent'] as $key => $value) {
                if (gettype($value) == "array") {
                    foreach ($value as $keys => $val) {
                        if ($permit_fee_report_flag == 1) {
                            if (gettype($val) == "array") {
                                foreach ($val as $ky => $vl) {
                                    if (gettype($vl) != "array") {
                                        if ($ky != 'keyValue' && $ky != 'keyCheckInStatus' && !str_contains($ky, 'PermitFeeCount') && !str_contains($ky, 'RefundPermitFeeCount')) {
                                            $absentPax += (float)$vl;
                                        }
                                        if ($ky != 'keyValue' && $ky != 'keyCheckInStatus' && str_contains($ky, 'RefundPermitFeeCount')) {
                                            $refundedAbsentTourists += (float)$vl;
                                        }
                                    }
                                }
                            }
                        } else {
                            foreach ($val as $ky => $vl) {
                                if ($ky != 'keyValue' && $ky != 'keyCheckInStatus' && !str_contains($ky, 'PermitFeeCount') && !str_contains($ky, 'RefundPermitFeeCount')) {
                                    $absentPax += (float)$vl;
                                }
                                if ($ky != 'keyValue' && $ky != 'keyCheckInStatus' && str_contains($ky, 'RefundPermitFeeCount')) {
                                    $refundedAbsentTourists += (float)$vl;
                                }
                            }
                        }
                    }
                }
            }
        }

        $totalTouristNotCheckedIn = 0;
        if (isset($newSortedData[$NotCheckedInApi])) {
            foreach ($newSortedData[$NotCheckedInApi] as $key => $value) {
                if (gettype($value) == "array") {
                    foreach ($value as $keys => $val) {
                        foreach ($val as $ky => $vl) {
                            if ($ky != 'keyValue' && $ky != 'keyCheckInStatus' && !str_contains($ky, 'PermitFeeCount') && !str_contains($ky, 'RefundPermitFeeCount') && !is_array($vl)) {
                                $totalTouristNotCheckedIn += (float)$vl;
                            }
                        }
                    }
                }
            }
        }

        $newSortedData['totalTourists'] = $totalTourists;
        $newSortedData['permitFeeTourists'] = ($totalTourists - $totalRefundedTourists - $absentPax - $totalTouristNotCheckedIn) + $refundedAbsentTourists;
        $newSortedData['totalPermitFees'] = $totalPermitFees;

        return $newSortedData;
    }

    // Permit Fee Details(Daily Report Details) CSV Export
    public function DailyReportDetailsExportCsvFile($fromDate, $toDate, $newSortedData, $tourOperatorId = 0, $tourPackageId = 0)
    {
        $fileName = 'permitfeesdetailsreport_' . $fromDate . '_' . $toDate . '.csv';
        $headers = csvHeaders($fileName);
        $blankLine = '';
        $row = ['BlankLine' => $blankLine];
        $tourOperatorName = $this->GetTourOperatorName($tourOperatorId, $tourPackageId);

        $columns = csvColumns('daily_report_details_columns');

        $callback = function () use ($newSortedData, $columns, $tourOperatorName) {
            $file = fopen('php://output', 'w');  // Create a file

            $toEnd = count($newSortedData);
            $row['BlankLine'] = '';
            if (!empty($tourOperatorName)) {
                if ($tourOperatorName['tourOperatorName']) {
                    $tourOperatorNameRow = array('Tour Operator:', $tourOperatorName['tourOperatorName'], $row['BlankLine'], $row['BlankLine'], $row['BlankLine'], $row['BlankLine'], $row['BlankLine'], $row['BlankLine'], $row['BlankLine']);
                    fputcsv($file, $tourOperatorNameRow);
                }
                if ($tourOperatorName['tourPackageName']) {
                    $tourPackageNameRow = array('Tour Package:', $tourOperatorName['tourPackageName'], $row['BlankLine'], $row['BlankLine'], $row['BlankLine'], $row['BlankLine'], $row['BlankLine'], $row['BlankLine'], $row['BlankLine']);
                    fputcsv($file, $tourPackageNameRow);
                }
                fputcsv($file, array($row['BlankLine'], $row['BlankLine'], $row['BlankLine'], $row['BlankLine'], $row['BlankLine'], $row['BlankLine'], $row['BlankLine'], $row['BlankLine'], $row['BlankLine']));
            }

            fputcsv($file, $columns);

            $firstCheckinRateCount = 0;
            $firstCheckinRefundPermitFeeCount = 0;
            $firstCheckinPermitFee = 0;
            $secondCheckinRateCount = 0;
            $secondCheckinRefundPermitFeeCount = 0;
            $secondCheckinPermitFee = 0;
            $notCheckinRateCount = 0;
            $notCheckinRefundPermitFeeCount = 0;
            $notCheckinPermitFee = 0;
            $absentRateCount = 0;
            $absentRefundPermitFeeCount = 0;
            $absentPermitFee = 0;

            $row['source'] = "Online";

            foreach ($newSortedData as $key => $each_data) {
                if (gettype($each_data) == "array") {
                    if (count($each_data) > 2) {
                        $row['rateGroup'] = $key;
                        fputcsv($file, array(
                            $row['rateGroup'],
                            $row['source'],
                            $row['BlankLine'],
                            $row['BlankLine'],
                            $row['BlankLine'],
                            $row['BlankLine'],
                            $row['BlankLine'],
                            $row['BlankLine'],
                            $row['BlankLine']
                        ));
                        foreach ($each_data as $ky => $ageGroup) {
                            if (gettype($ageGroup) == "array") {
                                foreach ($ageGroup as $keys => $value) {
                                    if ($keys != "keyValue" && $keys != "keyCheckInStatus") {
                                        // Sum as per check-in status
                                        if ($key == "1st Checked In") {
                                            $firstCheckinRateCount += $value['rateCount'];
                                            $firstCheckinRefundPermitFeeCount += $value['RefundPermitFeeCount'];
                                            $firstCheckinPermitFee += $value['PermitFeeCount'];
                                        } else if ($key == "2nd Check In") {
                                            $secondCheckinRateCount += $value['rateCount'];
                                            $secondCheckinRefundPermitFeeCount += $value['RefundPermitFeeCount'];
                                            $secondCheckinPermitFee += $value['PermitFeeCount'];
                                        } else if ($key == "Not Checked In") {
                                            $notCheckinRateCount += $value['rateCount'];
                                            $notCheckinRefundPermitFeeCount += $value['RefundPermitFeeCount'];
                                            $notCheckinPermitFee += $value['PermitFeeCount'];
                                        } else if ($key == "Absent") {
                                            $absentRateCount += $value['rateCount'];
                                            $absentRefundPermitFeeCount += $value['RefundPermitFeeCount'];
                                            $absentPermitFee += $value['PermitFeeCount'];
                                        }
                                        $row['rateGroup'] = str_replace('_', ' ', $keys);
                                        $row['rateCount'] = $value['rateCount'];
                                        $row['refundPermitFeeCount'] = $value['RefundPermitFeeCount'];
                                        // Calculate Permit Pax (0 for Absent, calculated for others)
                                        $row['permitPax'] = ($key == "Absent") ? 0 : ($value['rateCount'] - $value['RefundPermitFeeCount']);
                                        $row['permitFee'] = $value['PermitFeeCount'];
                                        fputcsv($file, array(
                                            $row['BlankLine'],
                                            $row['BlankLine'],
                                            $row['rateGroup'],
                                            $row['rateCount'],
                                            $row['refundPermitFeeCount'],
                                            $row['permitPax'],
                                            $row['BlankLine'],
                                            $row['BlankLine'],
                                            $row['permitFee'],
                                        ));
                                        if (isset($value['detailsBooking']) && count($value['detailsBooking']) > 0) {
                                            foreach ($value['detailsBooking'] as $ks => $booking) {
                                                fputcsv($file, array(
                                                    $row['BlankLine'],
                                                    $row['BlankLine'],
                                                    $row['BlankLine'],
                                                    $row['BlankLine'],
                                                    $row['BlankLine'],
                                                    $row['BlankLine'],
                                                    $booking,
                                                    (isset($value['detailsRefundedBooking'][$ks])) ? $value['detailsRefundedBooking'][$ks] : '',
                                                    $row['BlankLine'],
                                                ));
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        if ($key == "1st Checked In") {
                            fputcsv($file, array(
                                $row['BlankLine'],
                                $row['BlankLine'],
                                $row['BlankLine'],
                                $firstCheckinRateCount,
                                $firstCheckinRefundPermitFeeCount,
                                ($firstCheckinRateCount - $firstCheckinRefundPermitFeeCount),
                                $row['BlankLine'],
                                $row['BlankLine'],
                                $firstCheckinPermitFee
                            ));
                        } else if ($key == "2nd Check In") {
                            fputcsv($file, array(
                                $row['BlankLine'],
                                $row['BlankLine'],
                                $row['BlankLine'],
                                $secondCheckinRateCount,
                                $secondCheckinRefundPermitFeeCount,
                                ($secondCheckinRateCount - $secondCheckinRefundPermitFeeCount),
                                $row['BlankLine'],
                                $row['BlankLine'],
                                $secondCheckinPermitFee
                            ));
                        } else if ($key == "Not Checked In") {
                            fputcsv($file, array(
                                $row['BlankLine'],
                                $row['BlankLine'],
                                $row['BlankLine'],
                                $notCheckinRateCount,
                                $notCheckinRefundPermitFeeCount,
                                ($notCheckinRateCount - $notCheckinRefundPermitFeeCount),
                                $row['BlankLine'],
                                $row['BlankLine'],
                                $notCheckinPermitFee
                            ));
                        } else if ($key == "Absent") {
                            fputcsv($file, array(
                                $row['BlankLine'],
                                $row['BlankLine'],
                                $row['BlankLine'],
                                $absentRateCount,
                                $absentRefundPermitFeeCount,
                                0, // Permit Pax is always 0 for Absent
                                $row['BlankLine'],
                                $row['BlankLine'],
                                $absentPermitFee
                            ));
                        }
                        if (0 !== --$toEnd) {
                            $row['BlankLine'] = '';
                            fputcsv($file, array($row['BlankLine']));
                        }
                    } else {
                        $row['KeyValue'] = $each_data['keyValue'];
                        $row['rateGroup'] = "Infant (Ages 3 & Under)";
                        $row['rateCount'] = 0;
                        $row['refundPermitFeeCount'] = 0;
                        $row['permitPax'] = 0;
                        $row['permitFee'] = 0;

                        fputcsv($file, array(
                            $row['KeyValue'],
                            $row['source'],
                            $row['rateGroup'],
                            $row['rateCount'],
                            $row['refundPermitFeeCount'],
                            $row['permitPax'],
                            $row['BlankLine'],
                            $row['BlankLine'],
                            $row['permitFee'],
                        ));
                        fputcsv($file, array(
                            $row['BlankLine'],
                            $row['BlankLine'],
                            $row['BlankLine'],
                            $row['rateCount'],
                            $row['refundPermitFeeCount'],
                            $row['permitPax'],
                            $row['BlankLine'],
                            $row['BlankLine'],
                            $row['permitFee'],
                        ));
                    }
                }
            }
            fputcsv($file, array(
                $row['BlankLine'],
                $row['BlankLine'],
                $row['BlankLine'],
                $row['BlankLine'],
                $row['BlankLine'],
                $row['BlankLine'],
                $row['BlankLine'],
                $row['BlankLine'],
                $row['BlankLine']
            ));

            $row['TitleTourists'] = 'Total Tourists:';
            $row['Total Customer'] = $newSortedData['totalTourists'];

            fputcsv($file, array(
                $row['TitleTourists'],
                $row['BlankLine'],
                $row['BlankLine'],
                $row['BlankLine'],
                $row['BlankLine'],
                $row['BlankLine'],
                $row['BlankLine'],
                $row['BlankLine'],
                $row['Total Customer'],
            ));

            $row['TitleTourists'] = 'Permit Fee Tourists:';
            $row['Permit Fee Customer'] = $newSortedData['permitFeeTourists'];

            fputcsv($file, array(
                $row['TitleTourists'],
                $row['BlankLine'],
                $row['BlankLine'],
                $row['BlankLine'],
                $row['BlankLine'],
                $row['BlankLine'],
                $row['BlankLine'],
                $row['BlankLine'],
                $row['Permit Fee Customer'],
            ));

            $row['TitlePermitFee'] = 'Permit Fee:';
            $row['Total Permit Fee'] = $newSortedData['totalPermitFees'];

            fputcsv($file, array(
                $row['TitlePermitFee'],
                $row['BlankLine'],
                $row['BlankLine'],
                $row['BlankLine'],
                $row['BlankLine'],
                $row['BlankLine'],
                $row['BlankLine'],
                $row['BlankLine'],
                $row['Total Permit Fee'],
            ));

            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    public function fareharaborbookingreport(Request $request, string $fromDate = "", string $toDate = "", $tourOperatorId = 0, $tourPackageId = 0, int $export = 0)
    {
        $userType = userType();

        $from = date('m-d-Y', strtotime($fromDate));
        $to = date('m-d-Y', strtotime($toDate));
        if ($fromDate == "")
            $fromDate = date('Y-m-d');
        else
            $fromDate = $fromDate . " 00:00:00";

        if ($toDate == "")
            $toDate = date('Y-m-d');
        else
            $toDate = $toDate . " 23:59:59";

        $startyear = date('Y', strtotime($fromDate));
        if ($startyear < 2023) {
            $search = '#';
        } else {
            $search = '####';
        }
        //Data For Card and Invoiced
        $qry1 = DB::table('transactions AS t')
            ->join('tour_bookings_master as tbm', 'tbm.id', '=', 't.tour_booking_id')
            // ->join('tour_booking_history AS tbh', 'tbh.tour_booking_id', '=','t.tour_booking_id')
            ->join('customers as c', 'c.id', '=', 'tbm.customer_id')
            ->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
            ->join('tour_operators as to', 'to.id', '=', 'tp.tour_operator_id')
            ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
            ->whereIn('t.via', array('Card', 'Invoiced'))
            ->where('t.status', 'Completed')
            // ->where('tbh.operation', 'Payment')
            ->where('tbm.comments', 'LIKE', "%{$search}%")
            ->whereBetween('tsd.date', [$fromDate, $toDate])
            ->select([
                'tbm.id as bookingid',
                DB::raw('DATE_FORMAT(tsd.date, "%Y-%m-%d") as bookingdate'),
                'tst.time_from',
                't.via',
                DB::raw('sum(`t`.`amount`) as amount'),
                // 'tbh.details as amount',
                'c.name as name',
                'c.phone_number as phone',
                'c.email as email',
                'tbm.comments',
                'tbm.status as booking_status',
                'to.logo',
                // 'tbh.details'
            ]);

        if ($tourPackageId && $tourPackageId !== 0) {
            $qry1 = $qry1->where('tbm.tour_package_id', '=', $tourPackageId);
        }

        if ($userType == 'Tour Operator') {
            $qry1 = $qry1->where('tp.tour_operator_id', auth()->user()->tour_operator_id);
        } else {
            $qry1 = $qry1->where('tp.tour_operator_id', '=', $tourOperatorId);
        }

        $qryResult1 = $qry1->groupBy('tbm.id')->orderBy('tbm.id')->get();

        //Data for Cash payments
        $qry2 = DB::table('transactions AS t')
            ->join('tour_bookings_master as tbm', 'tbm.id', '=', 't.tour_booking_id')
            // ->join('tour_booking_history AS tbh', 'tbh.tour_booking_id', '=','t.tour_booking_id')
            ->join('customers as c', 'c.id', '=', 'tbm.customer_id')
            ->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
            ->join('tour_operators as to', 'to.id', '=', 'tp.tour_operator_id')
            ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
            ->where('t.status', 'Completed')
            // ->where('tbh.operation', 'Payment')
            ->where('t.via', 'Cash')
            ->where('tbm.comments', 'LIKE', "%{$search}%")
            ->whereBetween('t.created_at', [$fromDate, $toDate])
            ->select([
                'tbm.id as bookingid',
                DB::raw('DATE_FORMAT(tsd.date, "%Y-%m-%d") as bookingdate'),
                'tst.time_from',
                't.via',
                DB::raw('sum(`t`.`amount`) as amount'),
                // 'tbh.details as amount',
                'c.name as name',
                'c.phone_number as phone',
                'c.email as email',
                'tbm.comments',
                'tbm.status as booking_status',
                'to.logo'
            ]);

        if ($tourPackageId && $tourPackageId !== 0) {
            $qry2 = $qry2->where('tbm.tour_package_id', '=', $tourPackageId);
        }

        if ($userType == 'Tour Operator') {
            $qry2 = $qry2->where('tp.tour_operator_id', auth()->user()->tour_operator_id);
        } else {
            $qry2 = $qry2->where('tp.tour_operator_id', '=', $tourOperatorId);
        }

        $qryResult2 = $qry2->groupBy('tbm.id')->orderBy('tbm.id')->get();

        $cardCancelled = 0;
        $cashCancelled = 0;
        $invoicedCancelled = 0;
        $invoicedBooked = 0;
        $cardBooked = 0;
        $cashBooked = 0;
        $invoicedBooked = 0;
        $totalCard = 0;
        $totalCash = 0;
        $totalInvoiced = 0;
        $totalCancelled = 0;
        $totalBooked = 0;

        if ($request->api == 1) {
            foreach ($qryResult1 as $value) {
                if ($value->via == 'Card' && $value->booking_status == 'Cancelled') {
                    $cardCancelled += $value->amount;
                }

                if ($value->via == 'Invoiced' && $value->booking_status == 'Cancelled') {
                    $invoicedCancelled += $value->amount;
                }
                if ($value->via == 'Card' && $value->booking_status == 'Booked') {
                    $cardBooked += $value->amount;
                }

                if ($value->via == 'Invoiced' && $value->booking_status == 'Booked') {
                    $invoicedBooked += $value->amount;
                }
            }
            foreach ($qryResult2 as $value) {
                if ($value->via == 'Cash' && $value->booking_status == 'Cancelled') {
                    $cashCancelled += $value->amount;
                }
                if ($value->via == 'Cash' && $value->booking_status == 'Booked') {
                    $cashBooked += $value->amount;
                }
            }

            $totalCard = $cardCancelled + $cardBooked;
            $totalCash = $cashCancelled + $cashBooked;
            $totalInvoiced = $invoicedCancelled + $invoicedBooked;
            $totalCancelled = $cardCancelled + $cashCancelled + $invoicedCancelled;
            $totalBooked = $cardBooked + $cashBooked + $invoicedBooked;
            $overallTotal = $totalCancelled + $totalBooked;
            $dateRangeBooked = $from . ' - ' . $to . ' ' . '( Booked )';
            $dateRangeCancelled = $from . ' - ' . $to . ' ' . '( Cancelled )';

            $result = $qryResult1->merge($qryResult2);

            return response()->json(['details' => $result, 'cardCancelled' => $cardCancelled, 'cashCancelled' => $cashCancelled, 'invoicedCancelled' => $invoicedCancelled, 'invoicedBooked' => $invoicedBooked, 'cardBooked' => $cardBooked, 'cashBooked' => $cashBooked, 'totalCard' => $totalCard, 'totalCash' => $totalCash, 'totalInvoiced' => $totalInvoiced, 'totalCancelled' => $totalCancelled, 'totalBooked' => $totalBooked, 'overallTotal' => $overallTotal, 'dateRangeCancelled' => $dateRangeCancelled, 'dateRangeBooked' => $dateRangeBooked]);
        }

        if ($export) {
            return $this->FareharborBookingReportExportCsvFile($fromDate, $toDate, $qryResult1, $qryResult2, $tourOperatorId, $tourPackageId);
        }

        return DataTables::of($qryResult1->merge($qryResult2))
            ->addIndexColumn()
            ->make(true);
    }

    // Fareharbor Booking Report in CSV File
    public function FareharborBookingReportExportCsvFile($fromDate, $toDate, $qryResult1, $qryResult2, $tourOperatorId = 0, $tourPackageId = 0)
    {
        $qryResult1 = $qryResult1->merge($qryResult2);
        $fileName = 'fareharborbookingreports.csv';

        $headers = csvHeaders($fileName);
        $TopColumns = csvColumns('fareharbor_booking_report_TopColumns');
        $columns = csvColumns('fareharbor_booking_report_columns');

        $fDate = $fromDate;
        $tDate = $toDate;
        $tourOperatorName = $this->GetTourOperatorName($tourOperatorId, $tourPackageId);

        $callback = function () use ($qryResult1, $TopColumns, $columns, $fDate, $tDate, $tourOperatorName) {
            $totalCardAmount = 0;
            $totalCashAmount = 0;
            $totalInvoicedAmount = 0;
            $totalCancelledCardAmount = 0;
            $totalCancelledCashAmount = 0;
            $totalCancelledInvoicedAmount = 0;
            $total = 0;
            $totalCancelled = 0;
            foreach ($qryResult1 as $key => $each_data) {
                if (isset($each_data->amount) && $each_data->amount > 0) {
                    $each_data->amount = (float)$each_data->amount;
                } else {
                    $each_data->amount = 0.00;
                }

                if ($each_data->via == 'Card') {
                    if ($each_data->booking_status == 'Booked') {
                        $totalCardAmount += $each_data->amount;
                    }
                    if ($each_data->booking_status == 'Cancelled') {
                        $totalCancelledCardAmount += $each_data->amount;;
                    }
                } else if ($each_data->via == 'Cash') {
                    if ($each_data->booking_status == 'Booked') {
                        $totalCashAmount += $each_data->amount;
                    }
                    if ($each_data->booking_status == 'Cancelled') {
                        $totalCancelledCashAmount += $each_data->amount;;
                    }
                } else if ($each_data->via == 'Invoiced') {
                    if ($each_data->booking_status == 'Booked') {
                        $totalInvoicedAmount += $each_data->amount;
                    }
                    if ($each_data->booking_status == 'Cancelled') {
                        $totalCancelledInvoicedAmount += $each_data->amount;;
                    }
                }
            }
            $total = $totalCardAmount + $totalCashAmount + $totalInvoicedAmount;
            $totalCancelled = $totalCancelledCardAmount + $totalCancelledCashAmount + $totalCancelledInvoicedAmount;
            $file = fopen('php://output', 'w');
            if (!empty($tourOperatorName)) {
                $BlankLine = '';
                if ($tourOperatorName['tourOperatorName']) {
                    $tourOperatorNameRow = array('Tour Operator:', $tourOperatorName['tourOperatorName'], $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourOperatorNameRow);
                }
                if ($tourOperatorName['tourPackageName']) {
                    $tourPackageNameRow = array('Tour Package:', $tourOperatorName['tourPackageName'], $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourPackageNameRow);
                }
                fputcsv($file, array($BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine));
            }
            fputcsv($file, $TopColumns);

            $DateRange = date("m-d-Y", strtotime($fDate)) . ' - ' . date("m-d-Y", strtotime($tDate)) . "(Booked)";
            $DateRangeCancelled = date("m-d-Y", strtotime($fDate)) . ' - ' . date("m-d-Y", strtotime($tDate)) . "(Cancelled)";
            $totalCardAmount = number_format($totalCardAmount, 2, '.', '');
            $totalCashAmount = number_format($totalCashAmount, 2, '.', '');
            $totalInvoicedAmount = number_format($totalInvoicedAmount, 2, '.', '');
            $total = number_format($total, 2, '.', '');
            $totalCancelledCardAmount = number_format($totalCancelledCardAmount, 2, '.', '');
            $totalCancelledCashAmount = number_format($totalCancelledCashAmount, 2, '.', '');
            $totalCancelledInvoicedAmount = number_format($totalCancelledInvoicedAmount, 2, '.', '');
            $totalCancelled = number_format($totalCancelled, 2, '.', '');
            $Total = "Total";
            fputcsv($file, array($DateRange, $totalCardAmount, $totalCashAmount, $totalInvoicedAmount, $total));
            fputcsv($file, array($DateRangeCancelled, $totalCancelledCardAmount, $totalCancelledCashAmount, $totalCancelledInvoicedAmount, $totalCancelled));
            fputcsv($file, array($Total, $totalCardAmount + $totalCancelledCardAmount, $totalCashAmount + $totalCancelledCashAmount, $totalInvoicedAmount + $totalCancelledInvoicedAmount, $total + $totalCancelled));
            $BlankLine = '';
            fputcsv($file, array($BlankLine));
            fputcsv($file, $columns);
            foreach ($qryResult1 as $key => $each_data) {
                $Booking_Id = (!empty($each_data)) ? ((isset($each_data->bookingid)) ? $each_data->bookingid : '') : '';
                $C_Name = (!empty($each_data)) ? ((isset($each_data->name)) ? $each_data->name : '') : '';
                $C_Email = (!empty($each_data)) ? ((isset($each_data->email)) ? $each_data->email : '') : '';
                $Via = (!empty($each_data)) ? ((isset($each_data->via)) ? $each_data->via : '') : '';
                $Phone_Number = (!empty($each_data)) ? ((isset($each_data->phone)) ? $each_data->phone : '') : '';
                $Booking_Date = (!empty($each_data)) ? ((isset($each_data->bookingdate)) ? $each_data->bookingdate . " @ " . $each_data->time_from : '') : '';
                $Booking_Status = (!empty($each_data)) ? ((isset($each_data->booking_status)) ? $each_data->booking_status : '') : '';
                $Amount = (!empty($each_data)) ? ((isset($each_data->amount)) ? $each_data->amount : '') : '';
                $Comments = (!empty($each_data)) ? ((isset($each_data->comments)) ? $each_data->comments : '') : '';
                fputcsv($file, array($Booking_Id, $Booking_Date, $Via, $Amount, $C_Name, $Phone_Number, $C_Email, $Booking_Status, $Comments));
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function tipreport(Request $request, string $fromDate = "", string $toDate = "",  $tourOperatorId = 0,  $tourPackageId = 0)
    {
        $userType = userType();

        if ($fromDate == "")
            $fromDate = date('Y-m-d');
        else
            $fromDate = $fromDate . " 00:00:00";

        if ($toDate == "")
            $toDate = date('Y-m-d');
        else
            $toDate = $toDate . " 23:59:59";

        $qry = DB::table('tour_bookings_master AS tbm')
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
                'tbtip.date as createdate',
                'tbm.total as amount',
                'to.logo as touroperatorlogo',
                'tos.first_name as firstName',
                'tos.last_name as lastName',
                'tbm.id',
                'tbtip.amount as tipamount',
                DB::raw('(tbm.tourists + tbm.infants) as tourists')
            ])->orderBy('tsd.date', 'DESC');

        if ($tourPackageId && $tourPackageId !== 0) {
            $qry = $qry->where('tbm.tour_package_id', '=', $tourPackageId);
        }

        if ($userType == 'Tour Operator') {
            $qry = $qry->where('tp.tour_operator_id', auth()->user()->tour_operator_id);
        } else {
            $qry = $qry->where('tp.tour_operator_id', '=', $tourOperatorId);
        }

        if ($request->api == 1) {
            $amountTotal = 0;
            $tipAmount = 0;
            $result = $qry->get();
            foreach ($result as $value) {
                $amountTotal += $value->amount;
                $tipAmount += $value->tipamount;
            }

            return response()->json(['details' => $result, 'amountTotal' => $amountTotal, 'tipAmount' => $tipAmount]);
        }

        return Datatables::of($qry)
            ->addIndexColumn()
            ->editColumn('createdate', function ($createdate) {
                return substr($createdate->createdate, 0, 10);
            })
            ->make(true);
    }

    // Tip Summary Report Start
    public function tipsummaryreport(Request $request, string $fromDate = "", string $toDate = "", $tourOperatorId = 0, $tourPackageId = 0, int $export = 0)
    {
        $userType = userType();

        if ($fromDate == "")
            $fromDate = date('Y-m-d');
        else
            $fromDate = $fromDate . " 00:00:00";
        if ($toDate == "")
            $toDate = date('Y-m-d');
        else
            $toDate = $toDate . " 23:59:59";
        $qry = DB::table('tour_bookings_master AS tbm')
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
                'tbtip.date as bookingdate',
                'tbm.created_at as createdate',
                'to.logo as touroperatorlogo',
                'tos.first_name as firstName',
                'tos.last_name as lastName',
                'tbm.id',
                DB::raw('(tbm.tourists + tbm.infants) as tourists'),
                'tbtip.amount as tipamount'
            ])->orderBy('tbtip.date', 'DESC');

        if ($tourPackageId && $tourPackageId !== 0) {
            $qry = $qry->where('tbm.tour_package_id', '=', $tourPackageId);
        }

        if ($userType == 'Tour Operator') {
            $qry = $qry->where('tp.tour_operator_id', auth()->user()->tour_operator_id);
        } else {
            $qry = $qry->where('tp.tour_operator_id', '=', $tourOperatorId);
        }

        $qry = $qry->get();
        $result = json_decode($qry, true);
        $sortedData = array();
        foreach ($result as $element) {
            $timestamp = strtotime($element['bookingdate']);
            $date =  date("m/d/Y", $timestamp);
            if (! isset($sortedData[$date])) { //first entry of that day
                $sortedData[$date] = array($element);
            } else { //just push current element onto existing array
                $sortedData[$date][] = $element;
            }
        }

        $mediumSortedData = array();
        foreach ($sortedData as $key => $elementValue) {
            foreach ($elementValue as $element) {
                $stafffirstname = $element['firstName'];
                $stafflastname = $element['lastName'];
                $newArrayKey = $stafffirstname . " " . $stafflastname;
                if (!isset($mediumSortedData[$key][$newArrayKey])) {
                    $mediumSortedData[$key][$newArrayKey] = array($element);
                } else {
                    $mediumSortedData[$key][$newArrayKey][] = $element;
                }
            }
        }
        $newSortedData = [];

        if ($mediumSortedData) {
            foreach ($mediumSortedData as $key => $sortedDataValueMain) {
                foreach ($sortedDataValueMain as $key2 => $sortedDataValue) {
                    $amountTotal = 0;
                    $totaltourists = 0;
                    $imageName = "";
                    foreach ($sortedDataValue as $sortedDataElementsValue) {
                        $totaltourists += $sortedDataElementsValue['tourists'];
                        $amountTotal += $sortedDataElementsValue['tipamount'];
                        $imageName = $sortedDataElementsValue['touroperatorlogo'];
                    }

                    $newFilteredData = array();
                    $newFilteredData['bookingdate'] = $key;
                    $newFilteredData['staffname'] = $key2;
                    $newFilteredData['amountTotal'] = $amountTotal;
                    $newFilteredData['tourists'] = $totaltourists;
                    $newFilteredData['imageName'] = $imageName;

                    if ($request->api == 1) {
                        $newSortedData['details'][] = $newFilteredData;
                    } else {
                        $newSortedData[$key][$key2] = $newFilteredData;
                    }
                }
            }
        }
        if ($export) {
            return $this->TipSummaryReportExportCsvFile($fromDate, $toDate, $newSortedData, $tourOperatorId, $tourPackageId);
        }
        return $newSortedData;
    }
    // Tip Summary Report End

    // Tip Summary Report Export start

    public function TipSummaryReportExportCsvFile($fromDate, $toDate, $newSortedData, $tourOperatorId = 0, $tourPackageId = 0)
    {
        $fileName = 'tipsummaryreport_' . $fromDate . '_' . $toDate . '.csv';

        $headers = csvHeaders($fileName);
        $columns = csvColumns('tip_summary_report__columns');

        $fDate = $fromDate;
        $tDate = $toDate;
        $tourOperatorName = $this->GetTourOperatorName($tourOperatorId, $tourPackageId);

        $callback = function () use ($newSortedData, $columns, $fDate, $tDate, $tourOperatorName) {
            $file = fopen('php://output', 'w');  // Create a file
            $BlankLine = '';

            if (!empty($tourOperatorName)) {
                $BlankLine = '';
                if ($tourOperatorName['tourOperatorName']) {
                    $tourOperatorNameRow = array('Tour Operator:', $tourOperatorName['tourOperatorName'], $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourOperatorNameRow);
                }
                if ($tourOperatorName['tourPackageName']) {
                    $tourPackageNameRow = array('Tour Package:', $tourOperatorName['tourPackageName'], $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourPackageNameRow);
                }
                fputcsv($file, array($BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine));
            }
            $toEnd = count($newSortedData);
            $sortedData = array();
            foreach ($newSortedData as $key => $result_data) {
                if (is_array($result_data)) {
                    foreach ($result_data as $k => $each_data) {
                        $sortedData[] = $each_data;
                    }
                }
            }

            fputcsv($file, $columns);
            $x = 1;
            foreach ($sortedData as $each_data) {
                $count = $x++;
                $staffname = (!empty($each_data)) ? ((isset($each_data['staffname'])) ? $each_data['staffname'] : '') : '';
                $bookingdate = (!empty($each_data)) ? ((isset($each_data['bookingdate'])) ? date("m-d-Y", strtotime($each_data['bookingdate'])) : '') : '';
                $amountTotal = (!empty($each_data)) ? ((isset($each_data['amountTotal'])) ? $each_data['amountTotal'] : '') : '';
                $tourists = (!empty($each_data)) ? ((isset($each_data['tourists'])) ? $each_data['tourists'] : '') : '';

                fputcsv($file, array($count, $staffname, $bookingdate, $amountTotal, $tourists));
            }


            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }


    // Availability Report Start
    public function todayavialabilityList(Request $request, string $fromDate = "", string $toDate = "", $tourOperatorId = 0, $tourPackageId = 0, int $export = 0)
    {
        $userType = userType();

        if ($fromDate == "")
            $fromDate = date('Y-m-d');
        else
            $fromDate = $fromDate . " 00:00:00";
        if ($toDate == "")
            $toDate = date('Y-m-d');
        else
            $toDate = $toDate . " 23:59:59";

        /*$qry = DB::table('tour_bookings_master AS tbm')
            ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->join('customers as c', 'c.id' , '=', 'tbm.customer_id')
            ->leftJoin('transactions AS t', 't.tour_booking_id', '=', 'tbm.id')
            ->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
            ->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
            ->join('tour_operators as to', 'to.id', '=', 'tp.tour_operator_id')
            ->where('tst.deleted_by', '=', '0')
            ->where('tsd.deleted_by', '=', '0')
            ->whereBetween('tsd.date', [$fromDate, $toDate])
            ->groupBy('tbm.id')
            ->select([
                'c.name as name','tsd.date as bookingdate', 'tsd.created_at as createdate', 'tbm.status as status', 'tbm.id as bookingid',  'tbm.total as amount',DB::raw('IFNULL(t.status,0)  as payment'), 'to.logo as touroperatorlogo', 'tst.time_slot_seats as seats', 'tst.time_from as timefrom', 'tst.time_to as timeto', 'tbm.tourists as tourists'
            ]);
            if($tourPackageId && $tourPackageId !== 0) {
                $qry = $qry->where('tbm.tour_package_id', '=', $tourPackageId);
            }
			if($tourOperatorId && $tourOperatorId != 0) {
				$qry = $qry->where('c.tour_operator_id', $tourOperatorId);
			}
            if($userType == 'Tour Operator'){
                $qry = $qry->where('c.tour_operator_id', auth()->user()->tour_operator_id);
            }
            $qry = $qry->orderBy('tst.time_from')->get();
            $result = json_decode($qry, true);*/
        $qry = "SELECT
            `c`.`name` as `name`,`tsd`.`date` as `bookingdate`, `tsd`.`created_at` as `createdate`, `tbm`.`status` as `status`, `tbm`.`id` as `bookingid`, `tbm`.`total` as `amount`, IFNULL (`t`.`status`,'0')  as `payment`, `to`.`logo` as `touroperatorlogo`, `tst`.`time_slot_seats` as `seats`, `tst`.`time_from` as `timefrom`, `tst`.`time_to` as `timeto`, `tbm`.`tourists` as `tourists`
            FROM `tour_bookings_master` as `tbm`
            INNER JOIN `tour_slot_times` as `tst` on `tst`.`id` = `tbm`.`tour_slot_id`
            INNER JOIN `customers` as `c` on `c`.`id` = `tbm`.`customer_id`
            LEFT JOIN `transactions` as `t` on `t`.`tour_booking_id` = `tbm`.`id`
            INNER JOIN `tour_slot_dates` as `tsd` on `tsd`.`id` = `tst`.`tour_slot_id`
            INNER JOIN `tour_packages` as `tp` on `tp`.`id` = `tbm`.`tour_package_id`
            INNER JOIN `tour_operators` as `to` on `to`.`id` = `tp`.`tour_operator_id`
            ";
        $qry .= 'WHERE  `tsd`.`date` between "' . $fromDate . '" and "' . $toDate . '"' . "\n";
        $qry .= "AND  `tst`.`deleted_by` = '0'";
        $qry .= "AND  `tsd`.`deleted_by` = '0' ";

        if ($tourPackageId && $tourPackageId !== 0) {
            $qry .= "AND `tbm`.`tour_package_id` =" . $tourPackageId . "\n";
        }
        if ($tourOperatorId && $tourOperatorId !== 0) {
            $qry .= "AND `c`.`tour_operator_id` =" . $tourOperatorId . "\n";
        }
        if ($userType == 'Tour Operator') {
            $qry .= "AND `c`.`tour_operator_id` =" . auth()->user()->tour_operator_id;
        }
        $qry .= " GROUP BY `tbm`.`id` ORDER BY `tst`.`time_from` ";
        $result = DB::select($qry);

        $sortedData = array();
        foreach ($result as $element) {
            $element = (array)($element);
            $timestamp = strtotime($element['bookingdate']);
            $date =  date("m/d/Y", $timestamp);
            if (! isset($sortedData[$date])) { //first entry of that day
                $sortedData[$date] = array($element);
            } else { //just push current element onto existing array
                $sortedData[$date][] = $element;
            }
        }

        // Sort the date wise in asc order
        ksort($sortedData);

        $mediumSortedData = array();

        foreach ($sortedData as $key => $elementValue) {
            foreach ($elementValue as $element) {
                $formatTimeFromRaw = $element['timefrom'];
                $formatedTimeFrom = date('g:i a', strtotime($formatTimeFromRaw));
                $formatTimeToRaw = $element['timeto'];
                $formatedTimeTo = date('g:i a', strtotime($formatTimeToRaw));
                $newArrayKey = $formatedTimeFrom . " - " . $formatedTimeTo;

                if (!isset($mediumSortedData[$key][$newArrayKey])) {
                    $mediumSortedData[$key][$newArrayKey] = array($element);
                } else {
                    $mediumSortedData[$key][$newArrayKey][] = $element;
                }
            }
        }

        $newSortedData = [];

        if ($mediumSortedData) {
            foreach ($mediumSortedData as $key => $sortedDataValueMain) {
                foreach ($sortedDataValueMain as $key2 => $sortedDataValue) {
                    $amountTotal = 0;
                    $refundTotal = 0;
                    $bookedSeatsTotal = 0;
                    $cancelledSeatsTotal = 0;
                    $availableSeatsTotal = 0;
                    $totalSeats = 0;
                    $imageName = "";

                    foreach ($sortedDataValue as $sortedDataElementsValue) {
                        if ($sortedDataElementsValue['status'] == "Booked") {
                            $amountTotal += $sortedDataElementsValue['amount'];
                        } else {
                            $refundTotal += $sortedDataElementsValue['amount'];
                        }
                        if ($sortedDataElementsValue['status'] == "Booked") {
                            $bookedSeatsTotal += $sortedDataElementsValue['tourists'];
                        } else {
                            $cancelledSeatsTotal += $sortedDataElementsValue['tourists'];
                        }
                        $totalSeats = $sortedDataElementsValue['seats'];

                        $imageName = $sortedDataElementsValue['touroperatorlogo'];
                        $availableSeatsTotal = $totalSeats - $bookedSeatsTotal;
                    }

                    $newFilteredData = array();
                    $newFilteredData['bookingdate'] = $key;
                    $newFilteredData['timeslot'] = $key2;
                    $newFilteredData['bookedSeatsTotal'] = $bookedSeatsTotal;
                    $newFilteredData['cancelledSeatsTotal'] = $cancelledSeatsTotal;
                    $newFilteredData['availableSeats'] = $availableSeatsTotal;
                    $newFilteredData['imageName'] = $imageName;
                    $newFilteredData['seats'] = $totalSeats;

                    if ($request->api == 1) {
                        $newSortedData['details'][] = $newFilteredData;
                    } else {
                        $newSortedData[$key][$key2] = $newFilteredData;
                    }
                }
            }
        }
        if ($export) {
            return $this->TodayAvialabilityReportExportCsvFile($fromDate, $toDate, $newSortedData, $tourOperatorId, $tourPackageId);
        }
        return $newSortedData;
    }
    // Availability Report End

    // Availability Report CSV Export Start
    public function TodayAvialabilityReportExportCsvFile($fromDate, $toDate, $newSortedData, $tourOperatorId = 0, $tourPackageId = 0)
    {
        $fileName = 'today_availability_report_' . $fromDate . '_' . $toDate . '.csv';

        $headers = csvHeaders($fileName);
        $columns = csvColumns('today_avialability_report_columns');

        $fDate = $fromDate;
        $tDate = $toDate;
        $tourOperatorName = $this->GetTourOperatorName($tourOperatorId, $tourPackageId);
        $callback = function () use ($newSortedData, $columns, $fDate, $tDate, $tourOperatorName) {
            $file = fopen('php://output', 'w');  // Create a file
            $BlankLine = '';

            if (!empty($tourOperatorName)) {
                if ($tourOperatorName['tourOperatorName']) {
                    $tourOperatorNameRow = array('Tour Operator:', $tourOperatorName['tourOperatorName'], $BlankLine, $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourOperatorNameRow);
                }
                if ($tourOperatorName['tourPackageName']) {
                    $tourPackageNameRow = array('Tour Package:', $tourOperatorName['tourPackageName'], $BlankLine, $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourPackageNameRow);
                }
                fputcsv($file, array($BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine));
            }
            $toEnd = count($newSortedData);
            foreach ($newSortedData as $key => $result_data) {
                $DateColumns = isset($columns) ? 'Date' : '';
                $DateRows = (!empty($key)) ? ((isset($key)) ? date("d-m-Y", strtotime($key)) : '') : '';
                fputcsv($file, array($DateColumns, $DateRows));
                fputcsv($file, $columns);
                $x = 1;
                foreach ($result_data as $each_data) {
                    $count = $x++;
                    $timeslot = (!empty($each_data)) ? ((isset($each_data['timeslot'])) ? $each_data['timeslot'] : '') : '';
                    $bookedSeatsTotal = (!empty($each_data)) ? ((isset($each_data['bookedSeatsTotal'])) ? $each_data['bookedSeatsTotal'] : '') : '';
                    $cancelledSeatsTotal = (!empty($each_data)) ? ((isset($each_data['cancelledSeatsTotal'])) ? $each_data['cancelledSeatsTotal'] : '') : '';
                    $availableSeats = (!empty($each_data)) ? ((isset($each_data['availableSeats'])) ? $each_data['availableSeats'] : '') : '';
                    $seats = (!empty($each_data)) ? ((isset($each_data['seats'])) ? $each_data['seats'] : '') : '';

                    fputcsv($file, array($count, $timeslot, $seats, $availableSeats, $bookedSeatsTotal, $cancelledSeatsTotal));
                }
                if (0 !== --$toEnd) {
                    fputcsv($file, array($BlankLine));
                }
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }
    // Availability Report CSV Export End

    // Invalid Bookings Report start
    public function invalidbookingsreport(Request $request, string $fromDate = "", string $toDate = "",  $tourOperatorId = 0,  $tourPackageId = 0, int $export = 0)
    {
        $userType = userType();

        if ($fromDate == "")
            $fromDate = date('Y-m-d');
        else
            $fromDate = $fromDate . " 00:00:00";

        if ($toDate == "")
            $toDate = date('Y-m-d');
        else
            $toDate = $toDate . " 23:59:59";

        $sortedDataArray = array();

        $tourBookingsMasterQuery = DB::table('tour_bookings_master AS tbm')
            ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
            ->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
            ->whereBetween('tsd.date', [$fromDate, $toDate])
            ->select(
                'tbm.id as tour_booking_id',
                DB::raw("'tour_bookings_master' as table_name")
            );
        if ($tourPackageId != '' && $tourPackageId != 0) {
            $tourBookingsMasterQuery = $tourBookingsMasterQuery->where('tbm.tour_package_id', $tourPackageId);
        }
        if ($userType == 'Tour Operator') {
            $tourBookingsMasterQuery = $tourBookingsMasterQuery->where('tp.tour_operator_id', auth()->user()->tour_operator_id);
        } else {
            $tourBookingsMasterQuery = $tourBookingsMasterQuery->where('tp.tour_operator_id', '=', $tourOperatorId);
        }
        $tourBookingsMasterQuery->where(function ($query) {
            return $query
                ->where('tbm.tour_package_id', 0)
                ->orWhere('tbm.tour_slot_id', 0)
                ->orWhere('tbm.customer_id', 0)
                ->orWhere('tbm.name', '=', '')
                ->orWhere('tbm.email', '=', '')
                ->orWhere('tbm.tourists', 0)
                ->orWhere('tbm.total', 0)
                ->orWhere('tbm.created_at', '0000-00-00 00:00:00')
                ->orWhere('tbm.created_by', 0);
        });

        $tourBookingsMasterData = $tourBookingsMasterQuery->get()->toArray();
        $tourBookingsTransactsQuery = DB::table('tour_bookings_transact AS tbt')
            ->join('tour_bookings_master AS tbm', 'tbm.id', '=', 'tbt.tour_booking_id')
            ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
            ->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
            ->whereBetween('tsd.date', [$fromDate, $toDate])
            ->select(
                'tbm.id as tour_booking_id',
                DB::raw("'tour_bookings_transact' as table_name")
            );
        if ($tourPackageId != '' && $tourPackageId != 0) {
            $tourBookingsTransactsQuery = $tourBookingsTransactsQuery->where('tbm.tour_package_id', $tourPackageId);
        }
        if ($userType == 'Tour Operator') {
            $tourBookingsTransactsQuery = $tourBookingsTransactsQuery->where('tp.tour_operator_id', auth()->user()->tour_operator_id);
        } else {
            $tourBookingsTransactsQuery = $tourBookingsTransactsQuery->where('tp.tour_operator_id', '=', $tourOperatorId);
        }
        $tourBookingsTransactsQuery->where('tbt.refund_permitfee_status', '=', NULL);
        $tourBookingsTransactsQuery->where(function ($query) {
            return $query
                ->where('tbt.tour_booking_id', 0)
                ->orWhere('tbt.tour_booking_id', 0)
                ->orWhere('tbt.tour_package_rate_id', 0)
                ->orWhere('tbm.created_at', '0000-00-00 00:00:00')
                ->orWhere('tbm.created_by', 0)
                ->orWhere(DB::raw('CAST(CASE WHEN (tbt.processing_charge_percentage>0) THEN ((tbt.tourists*(tbt.rate+tbt.additional_charge+tbt.permit_fee))+(tbt.tourists*(tbt.rate+tbt.additional_charge+tbt.permit_fee))*tbt.processing_charge_percentage/100) ELSE (tbt.tourists*(tbt.rate+tbt.additional_charge+tbt.permit_fee)) END as DECIMAL(12,2))' . ' - tbt.total'), '>=', 1);
        });
        $tourBookingsTransactsData = $tourBookingsTransactsQuery->get()->toArray();
        $tourBookingsHistoriesQuery = DB::table('tour_booking_history AS tbh')
            ->join('tour_bookings_master AS tbm', 'tbm.id', '=', 'tbh.tour_booking_id')
            ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
            ->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
            ->whereBetween('tsd.date', [$fromDate, $toDate])
            ->select(
                'tbm.id as tour_booking_id',
                DB::raw("'tour_booking_history' as table_name") //tbt.tourists*(tbt.rate+tbt.additional_charge+tbt.permit_fee)
            );
        if ($tourPackageId != '' && $tourPackageId != 0) {
            $tourBookingsHistoriesQuery = $tourBookingsHistoriesQuery->where('tbm.tour_package_id', $tourPackageId);
        }
        if ($userType == 'Tour Operator') {
            $tourBookingsHistoriesQuery = $tourBookingsHistoriesQuery->where('tp.tour_operator_id', auth()->user()->tour_operator_id);
        } else {
            $tourBookingsHistoriesQuery = $tourBookingsHistoriesQuery->where('tp.tour_operator_id', '=', $tourOperatorId);
        }
        $tourBookingsHistoriesQuery->where(function ($query) {
            return $query
                ->where('tbh.tour_booking_id', 0)
                ->orWhere('tbh.operation', '=', NULL)
                ->orWhere('tbh.operation_at', '0000-00-00 00:00:00')
                ->orWhere('tbh.operation_by', 0)
                ->orWhere('tbh.created_at', '0000-00-00 00:00:00')
                ->orWhereIn('tbh.tour_booking_id', function ($query) {
                    $query->select('tour_booking_history.tour_booking_id')->from('tour_booking_history')->where('operation', '=', 'Payment')->groupBy('tour_booking_history.tour_booking_id')->havingRaw('COUNT(*) > 1');
                });
        });
        $tourBookingsHistoriesData = $tourBookingsHistoriesQuery->distinct()->get()->toArray();

        $tourBookingTransactionsQuery = DB::table('transactions AS t')
            ->join('tour_bookings_master AS tbm', 'tbm.id', '=', 't.tour_booking_id')
            ->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
            ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
            ->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
            ->whereBetween('tsd.date', [$fromDate, $toDate])
            ->select(
                'tbm.id as tour_booking_id',
                DB::raw("'transactions' as table_name")
            );
        if ($tourPackageId != '' && $tourPackageId != 0) {
            $tourBookingTransactionsQuery = $tourBookingTransactionsQuery->where('tbm.tour_package_id', $tourPackageId);
        }
        if ($userType == 'Tour Operator') {
            $tourBookingTransactionsQuery = $tourBookingTransactionsQuery->where('tp.tour_operator_id', auth()->user()->tour_operator_id);
        } else {
            $tourBookingTransactionsQuery = $tourBookingTransactionsQuery->where('tp.tour_operator_id', '=', $tourOperatorId);
        }
        $tourBookingTransactionsQuery->where(function ($query) {
            return $query
                ->where('t.tour_booking_id', 0)
                ->orWhere('t.amount', 0)
                ->orWhere('t.via', '=', NULL)
                ->orWhere('t.status', '=', NULL)
                ->orWhere('t.created_by', 0)
                ->orWhere('t.created_at', '0000-00-00 00:00:00')
                ->orWhere(function ($q) {
                    $q->where('t.via', '=', 'Card')->whereRaw('t.transaction_number=""');
                })->orWhere(function ($q) {
                    $q->where('t.via', '=', 'Card')->where('c.is_affiliate', '=', 'Yes')->whereRaw('t.fee_amount > 0');
                })->orWhere(function ($q) {
                    $q->where('t.via', '=', 'Cash')->whereRaw('t.fee_amount > 0 ');
                })->orWhere(function ($q) {
                    $q->where('t.via', '=', 'Invoiced')->whereRaw('t.fee_amount > 0');
                });
        });
        $tourBookingsTransactionsData = $tourBookingTransactionsQuery->distinct()->get()->toArray();
        if ($request->api == 1) {
            $sortedData['details'] = array_merge($tourBookingsMasterData, $tourBookingsTransactsData, $tourBookingsHistoriesData, $tourBookingsTransactionsData);
        } else {
            $sortedData = array_merge($tourBookingsMasterData, $tourBookingsTransactsData, $tourBookingsHistoriesData, $tourBookingsTransactionsData);
        }
        if ($export) {
            return $this->InvalidPaymentReportExportCsvFile($fromDate, $toDate, $sortedData, $tourOperatorId, $tourPackageId);
        }
        return $sortedData;
    }
    // Invalid Bookings Report End

    public function InvalidPaymentReportExportCsvFile($fromDate, $toDate, $sortedData, $tourOperatorId = 0,  $tourPackageId = 0)
    {
        $fileName = 'invalid_payment_report' . $fromDate . '_' . $toDate . '.csv';
        $headers = csvHeaders($fileName);
        $columns = csvColumns('invalid_payment_report_columns');
        $tourOperatorName = $this->GetTourOperatorName($tourOperatorId, $tourPackageId);

        $callback = function () use ($sortedData, $columns, $fromDate, $toDate, $tourOperatorName) {

            $file = fopen('php://output', 'w');  // Create a file
            if (!empty($tourOperatorName)) {
                $BlankLine = '';
                if ($tourOperatorName['tourOperatorName']) {
                    $tourOperatorNameRow = array('Tour Operator:', $tourOperatorName['tourOperatorName'], $BlankLine);
                    fputcsv($file, $tourOperatorNameRow);
                }
                if ($tourOperatorName['tourPackageName']) {
                    $tourPackageNameRow = array('Tour Package:', $tourOperatorName['tourPackageName'], $BlankLine);
                    fputcsv($file, $tourPackageNameRow);
                }
                fputcsv($file, array($BlankLine, $BlankLine, $BlankLine));
            }
            fputcsv($file, $columns);
            foreach ($sortedData as $key => $eachData) {
                $keys = $key + 1;
                $row['Id'] = $keys;
                $row['Booking Id'] = $eachData->tour_booking_id ?? '';
                $row['Table Name'] = $eachData->table_name ?? '';

                fputcsv($file, array(
                    $row['Id'],
                    $row['Booking Id'],
                    $row['Table Name']
                ));
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    /**
     * Logic to get the report of closure emails sent logs
     *
     * @param int $fromDate
     * @param int $toDate
     * @param int $tourOperatorId
     * @param int $tourPackageId
     * @return mixed
     *
     * @throws Exception
     */
    public function lowerAntelopeCanyonClosureEmailReport($fromDate, $toDate, $tourOperatorId,  $tourPackageId, $export = 0)
    {
        try {
            $userType = userType();
            if ($userType == 'Tour Operator') {
                $tourOperatorId = auth()->user()->tour_operator_id;
            }
            $tourBookings = DB::table('tour_bookings_master AS tbm')
                ->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
                ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
                ->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
                ->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
                ->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
                ->join('cities AS ct', 'ct.id', '=', 't_o.city_id')
                ->join('states AS st', 'st.id', '=', 'ct.state_id')
                ->whereBetween('tsd.date', [$fromDate, $toDate])
                ->when($tourPackageId, function ($query) use ($tourPackageId) {
                    $query->where('tp.id', $tourPackageId);
                })
                ->select([
                    'tbm.id as tbm_id',
                    'tst.id as tour_slot_time_id',
                    'tst.time_from',
                    'tst.time_to',
                    'tsd.id as tour_slot_date_id',
                    'tsd.date',
                    'tp.tour_operator_id',
                    'tsd.tour_package_id',
                    'c.name as customer_name',
                    'c.email as customer_email',
                    'c.phone_number as customer_phone',
                    't_o.name AS tour_operator',
                    't_o.email AS tour_op_email',
                    't_o.phone_number AS tour_op_phone',
                    'tp.email as packageEmail',
                    'tp.from_name as packageFromName',
                    'tp.from_email as packageFromEmail',
                    'tbm.status',
                    'tp.api_key as packageApiKey'
                ])
                ->get();
            $sortedData = [];
            if ($tourBookings->count()) {
                foreach ($tourBookings as $tourBooking) {
                    $history = TourBookingHistory::where('tour_booking_id', $tourBooking->tbm_id)
                        ->where('operation', 'Email')
                        ->where('details', 'LIKE', '%closure email sent%')
                        ->first();

                    if ($history) {
                        $tourBooking->closure_email_sent = true;
                    } else {
                        $tourBooking->closure_email_sent = false;
                    }

                    $tourBooking->formatted_date = date('m/d/Y', strtotime($tourBooking->date));
                    $sortedData[$tourBooking->tour_slot_time_id]['slotTime'] = date('h:i:s a', strtotime($tourBooking->time_from)) . " - " . date('h:i:s a', strtotime($tourBooking->time_to));
                    $sortedData[$tourBooking->tour_slot_time_id]['bookings'][] = $tourBooking;
                }
            }
            $newSortedData = [];
            $j = 0;
            if (!empty($sortedData)) {
                foreach ($sortedData as $sortedDt) {
                    $newSortedData[$j]['slotTime'] = $sortedDt['slotTime'];
                    $newSortedData[$j]['bookings'] = $sortedDt['bookings'];
                    $j++;
                }
            }

            if ($export) {
                return $this->LowerAntelopeCanyonClosureEmailReportExportCsvFile($fromDate, $toDate, $newSortedData, $tourOperatorId, $tourPackageId);
            }

            return $newSortedData;
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
    public function LowerAntelopeCanyonClosureEmailReportExportCsvFile($fromDate, $toDate, $sortedData, $tourOperatorId = 0,  $tourPackageId = 0)
    {
        $fileName = 'closure_emails_report' . $fromDate . '_' . $toDate . '.csv';
        $headers = csvHeaders($fileName);
        $columns = csvColumns('closure_emails_report_column');
        $tourOperatorName = $this->GetTourOperatorName($tourOperatorId, $tourPackageId);

        $callback = function () use ($sortedData, $columns, $fromDate, $tourOperatorName) {
            $file = fopen('php://output', 'w');  // Create a file
            if (!empty($tourOperatorName)) {
                $BlankLine = '';
                if ($tourOperatorName['tourOperatorName']) {
                    $tourOperatorNameRow = array('Tour Operator:', $tourOperatorName['tourOperatorName'], $BlankLine);
                    fputcsv($file, $tourOperatorNameRow);
                }

                if ($tourOperatorName['tourPackageName']) {
                    $tourPackageNameRow = array('Tour Package:', $tourOperatorName['tourPackageName'], $BlankLine);
                    fputcsv($file, $tourPackageNameRow);
                }

                fputcsv($file, array($BlankLine, $BlankLine, $BlankLine));
            }

            $newSortedData = array();
            foreach ($sortedData as $result_data) {
                foreach ($result_data['bookings'] as $k => $value) {
                    $newSortedData[] = $value;
                }
            }

            fputcsv($file, $columns);
            $x = 1;
            foreach ($newSortedData as $each_data) {
                $x++;
                $bookingId = (!empty($each_data)) ? ((isset($each_data->tbm_id)) ? $each_data->tbm_id : '') : '';
                $bookingTime = (!empty($each_data)) ? ((isset($each_data->time_from) && isset($each_data->time_to)) ? date("m/d/Y", strtotime($fromDate)) . " @ " . date('h:i:s a', strtotime($each_data->time_from)) . " - " . date('h:i:s a', strtotime($each_data->time_to)) : '') : '';
                $customerName = (!empty($each_data)) ? ((isset($each_data->customer_name)) ? $each_data->customer_name : '') : '';
                $customerEmail = (!empty($each_data)) ? ((isset($each_data->customer_email)) ? $each_data->customer_email : '') : '';
                $customerPhone = (!empty($each_data)) ? ((isset($each_data->customer_phone)) ? $each_data->customer_phone : '') : '';
                $status = (!empty($each_data)) ? ((isset($each_data->status)) ? $each_data->status : '') : '';
                $closureEmailSent = (!empty($each_data)) ? ((isset($each_data->closure_email_sent) && $each_data->closure_email_sent == true) ? 'Yes' : 'No') : '';

                fputcsv($file, array($bookingId, $bookingTime, $customerName, $customerEmail, $customerPhone, $status, $closureEmailSent));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Logic to get the customers of specific tour operators
     *
     * @param int $tourOperatorId
     * @param int $tourPackageId
     * @return mixed
     *
     * @throws Exception
     */
    public function getCustomers($tourOperatorId,  $tourPackageId)
    {
        try {
            $tourPackage = TourPackage::find($tourPackageId);

            if (empty($tourOperatorId) || $tourOperatorId == 0) {
                $tourOperatorId = $tourPackage->tour_operator_id;
            }

            if (session('userType') == 'Admin') {
                $affiliates = DB::table('customers')
                    ->where('is_affiliate', 'Yes')
                    ->whereNotIn('name', ['TripAdvisor', 'Bokun'])
                    ->where('bokun', 0)
                    ->where('tour_operator_id', $tourOperatorId)
                    ->where('deleted_by', 0)
                    ->orderBy('name')
                    ->get(['id', 'name']);
            } else {
                $affiliates = DB::table('customers')
                    ->where('is_affiliate', 'Yes')
                    ->whereNotIn('name', ['TripAdvisor', 'Bokun'])
                    ->where('bokun', 0)
                    ->where('tour_operator_id', auth()->user()->tour_operator_id)
                    ->where('deleted_by', 0)
                    ->orderBy('name')
                    ->get(['id', 'name']);
            }

            if ($affiliates->count()) {
                return $affiliates;
            }
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }


    /**
     * revenueReport
     * Return an revenue aggregation from booking history table.
     * @return (Array,Strem) sql raw restult
     */
    public function revenueReport(
        $request,
        string $fromDate = "",
        string $toDate = "",
        int $tourOperatorId = 0,
        int $tourPackageId = 0,
        string $viewBy = "tourdate", // purchasedate|tourdate
        int $export = 0,
        int $returnIds = 0,
    ) {
        try {
            // get the start and end date from the get params.
            $dates = DateUtils::startEndDateDefaults($fromDate, $toDate);
            // permit_fees  processing_fees platform_fees
            $sql = "
            SELECT
                `tbm`.`id` AS `bid`,
                `tbm`.`status`,
                `tbm`.`service_commission`,
                `tbm`.`booking_total`,
                `tbm`.`name` AS `cname`,
                `tbm`.`status` AS `booking_status`,
                `tbm`.`total`,
                `tbm`.`tour_slot_id`,
                `tsd`.`date` AS `dt`,
                `tp`.`name` AS `nm`,
                DATE_FORMAT(`tbm`.`created_at`, '%Y-%m-%d') AS `bdt`,
                WEEK(`tsd`.`date`) AS `wk`,
                MONTH(`tsd`.`date`) AS `mn`,
                YEAR(`tsd`.`date`) AS `yr`,
                concat(MONTH(`tsd`.`date`), YEAR(`tsd`.`date`) ) AS `mn_yr`,
                concat(YEAR(`tsd`.`date`), MONTH(`tsd`.`date`), WEEK(`tsd`.`date`) ) AS `wk_mn_yr`,
                DATE_FORMAT(`tsd`.`date`, '%Y-%m-%d') AS `t_date`,
                `tst`.`time_from` AS `t_time`,
                `tbh`.`payment_type` AS `trans_type`,
                ifnull( json_unquote( json_extract(`tbh`.`details`, '$.amount') ), 0) AS 'ticket_cost_from_detail',
                ifnull( json_unquote( json_extract(`tbh`.`details`, '$.refund_keep_us') ), 0) AS 'refund_to_keep',
                `tbh`.`ticket_cost`,
                `tbh`.`processing_fees` AS pro_fees,
                `tbh`.`platform_fees` AS plt_fees,
                `tbh`.`permit_fees` AS permit_fees,
                `tbh`.`details`,
                `tbh`.`operation` AS opt,
                `tbm`.`tourists`,
                `tbm`.`discount2_value` AS dis,
                `tbh`.`id` AS hist_id,
                `tbh`.`payment_method`,
                `tbm`.`comments`
            FROM `tour_booking_history` AS `tbh`
                INNER JOIN `tour_bookings_master` AS `tbm` ON `tbm`.`id` = `tbh`.`tour_booking_id`
                INNER JOIN `tour_slot_times` AS `tst` ON `tst`.`id` = `tbm`.`tour_slot_id`
                INNER JOIN `tour_slot_dates` AS `tsd` ON `tsd`.`id` = `tst`.`tour_slot_id`
                INNER JOIN `tour_packages` AS `tp` ON `tp`.`id` = `tbm`.`tour_package_id`
                ";

            // set default query filters
            $payTypes = '"Cash", "Card", "Wire Transfer", "Cheque/Wire", "Check/Wire", "Invoiced"';
            // $payTypes = '"Card"';
            //$sortDir = "ASC";
            $filterCancelled = false;
            // get query filter is any
            if ($request->filters) {
                if ($request->payment_type) {
                    $payTypes = StrUtils::dbInFilter($request->payment_type);
                }
                // if($request->sort){
                //     $sortDir = ($request->sort == 'DESC') ? 'DESC' : 'ASC';
                // }
                if ($request->cancelled) {
                    $filterCancelled = true;
                }
            }
            //if tour opt logged in
            $tourOperatorId = (userType() == 'Tour Operator') ? auth()->user()->tour_operator_id : intval($tourOperatorId);
            // swap data query
            $viewDate = ($viewBy == 'purchasedate') ? '`tbh`.`operation_at`' : '`tsd`.`date`';
            //default filters for payment types start date and tour op id
            $sql .= ' WHERE  `tbh`.`payment_type` IN (' . $payTypes . ')
                      AND `tbh`.`operation` in ("Payment", "Refund", "Cancelled")
                      AND `tp`.`tour_operator_id` = "' . $tourOperatorId . '"
                      AND ' . $viewDate . ' between "' . $dates['start_date'] . '" and "' . $dates["end_date"] . '"
            ';

            // Push the date range onto tbm.tour_slot_id (indexed) so MySQL can scope
            // tour_bookings_master before scanning tour_booking_history (~1.6M rows).
            // Only applies when filtering by tour date; purchase date filters tbh directly.
            if ($viewBy !== 'purchasedate') {
                $sql .= ' AND `tbm`.`tour_slot_id` IN (
                    SELECT `tst2`.`id` FROM `tour_slot_times` AS `tst2`
                    INNER JOIN `tour_slot_dates` AS `tsd2` ON `tsd2`.`id` = `tst2`.`tour_slot_id`
                    WHERE `tsd2`.`date` BETWEEN "' . $dates['start_date'] . '" AND "' . $dates['end_date'] . '"
                ) ';
            }

            //if filter to show only external bookings is set then add the LIKE Operator
            if (isset($request->bookingsystem) && $request->bookingsystem == '1' && !isset($request->bookingsystemonly)) {
                $sql .= ' AND `tbm`.`comments` NOT LIKE "#%" ';
            }
            if (isset($request->bookingsystemonly) && $request->bookingsystemonly == '1') {
                $sql .= ' AND `tbm`.`comments` LIKE "#%" ';
            }


            // if tour pkg set limit query to only selected tpg
            if (isset($tourPackageId) && intval($tourPackageId) != 0) {
                $sql .= ' AND `tp`.`id` = "' . intval($tourPackageId) . '" ';
            }

            // if remove cancelled filter is set.
            $sql .= ($filterCancelled) ? " AND `tbm`.`status` != \"Cancelled\"  " : "";

            //order by filter default DESC
            $sql .= "\n ORDER BY `tbm`.`id`, `tbh`.`id` ASC;";

            // query the for bookings index the bookings array example: [ [1234] => booking ]
            $bookings = BookingUtils::getBookingsIndexById($sql);


            // if the user want to see bookings that were imported by also have nat fees.
            if (isset($request->bookingsystem) && intval($request->bookingsystem) == 2) {
                $bookings = BookingUtils::includeImportedBookingsWithFees($bookings);
            }
            //if you just want the ids for another query
            if (intval($returnIds) == 1) {
                return array_unique(array_keys($bookings));
            }
            // calculate the net revenue
            $collection = BookingUtils::calculateRevenue($bookings, BookingUtils::getAllInvoiceInId($bookings));

            //we dont need this in memory any longer.
            unset($bookings);
            // return array if not export
            if (!$export) {
                return $collection;
                // return csv byte stream if export
            } else {
                $name = 'revenue_report_' . $viewBy . '_' . $fromDate . '_' . $toDate . '.csv';
                return $this->revenueReportToCsv($name, $collection, intval($tourOperatorId), intval($tourPackageId), $fromDate, $toDate);
            }
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }


    /**
     * Return the Revenue Report as a CSV file.
     * @param string $name file name
     * @param array $data revenune report query response
     * @return (/file) stream bytes
     */
    public function revenueReportToCsv(
        string $name,
        array $data,
        int $tourOperatorId = 0,
        int $tourPackageId = 0,
        string $fromDate,
        string $toDate
    ) {
        $columns = csvColumns('revenue_report');
        $TopColumns = csvColumns('revenue_report_TopColumns');
        $tourOperatorName = $this->GetTourOperatorName($tourOperatorId, $tourPackageId);

        $callback = function () use ($data, $columns, $TopColumns, $tourOperatorName, $fromDate, $toDate) {
            $file = fopen('php://output', 'w');
            if (!empty($tourOperatorName)) {
                $BlankLine = '';
                if ($tourOperatorName['tourOperatorName']) {
                    $tourOperatorNameRow = array('Tour Operator:', $tourOperatorName['tourOperatorName'], $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourOperatorNameRow);
                }
                if ($tourOperatorName['tourPackageName']) {
                    $tourPackageNameRow = array('Tour Package:', $tourOperatorName['tourPackageName'], $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourPackageNameRow);
                }
                fputcsv($file, array_fill(0, 5, $BlankLine));
            }
            fputcsv($file, $TopColumns);

            $DateRange = date("m-d-Y", strtotime($fromDate)) . ' - ' . date("m-d-Y", strtotime($toDate));
            $CreatedDate = date("m-d-Y");

            $totalGross = sprintf('%.2f', $data['sums']['total_gross']);
            $totalRefund = sprintf('%.2f', $data['sums']['total_refund']);
            $totalNet = sprintf('%.2f', $data['sums']['total_net']);
            $totalrefundfees = sprintf('%.2f', 0);
            $totalpayfees = sprintf('%.2f', $data['sums']['total_platform_fees']);
            fputcsv($file, array($DateRange, $CreatedDate, $totalGross, $totalRefund, $totalpayfees, $totalrefundfees, $totalNet));
            $BlankLine = '';
            fputcsv($file, array($BlankLine));

            fputcsv($file, $columns);
            foreach ($data['data'] as $key => $val) {
                $row = array(
                    $val['booking_id'],
                    $val['nm'],
                    $val['booking_date'],
                    $val['status'],
                    $tourOperatorName['tourPackageName'],
                    $val['tour_date'],
                    $val['tour_time'],
                    $val['trans_type'],
                    $val['billable'],
                    $val['trans_cost'],
                    $val['gross_revenue'],
                    $val['discount'],
                    $val['refund_total'],
                    $val['platform_fees'],
                    // $val['processing_fees'],
                    $val['net_revenue']
                );
                fputcsv($file, $row);
            }
            fputcsv($file, array_merge(array_fill(0, 12, $BlankLine), ['Total Net:', $totalNet]));
            fclose($file);
        };
        return response()->stream($callback, 200, csvHeaders($name));
    }

    /**
     * revenueReport
     * Return an revenue aggregation from booking history table.
     * @return (Array,Strem) sql raw restult
     * @example query string
     * {fromDate}/{toDate}/{customerId?}/{tourOperatorId?}/{tourPackageId?}/{groupBy?}
     * string $fromDate = "",
     * string $toDate = "",
     * int $tourOperatorId = 0,
     * int $tourPackageId = 0,
     * int $customerId = 0,
     * string $groupBy = "booking_id",
     * string $paymentType = "Invoiced",
     * int $export = 0
     *
     */
    public function invoiceInsightsReport($request)
    {


        try {
            // get the start and end date from the get params.
            $dates = DateUtils::startEndDateDefaults($request->fromDate, $request->toDate);
            // create the raw sql to execute the report query.
            $sql = "SELECT
            distinct
           `tbm`.`name` as `customer`,
           `tbm`.`email`,
           `tbm`.`phone_number`,
           `cus`.`id` as `affiliate_id`,
           `cus`.`name` as `affiliate_name`,
           `cus`.`external_invoice_enabled` as `stripe_invoice_enabled`,
           `top`.`stripe_invoice_enabled` as `stripe_enabled`,
           `tbm`.`id` as `booking_id`,
           `tbh`.`payment_type` as `payment_type`,
           `tpkg`.`name` as `tour_name`,

            DATE_FORMAT(`tsd`.`date`, '%Y-%m-%d') as `tour_date`,

           `tst`.`time_from` as `tour_time_from`,
           `tst`.`time_to` as `tour_time`,
           `inv`.`uuid` as `invoice_uuid`,
           `inv`.`due_date`,
           `inv`.`amount` as `invoice_amount`,
           `inv`.`invoice_number` as ` invoice_number`,
           `inv`.`reference_invoice` as `reference_invoice`,
           `inv`.`email_sent` as `email_sent`,
		   `trans`.`status` as `payment_status`,

           COALESCE((SELECT sum(COALESCE(tbt2.tourists, 0)) FROM tour_bookings_transact AS tbt2
            WHERE tbt2.tour_booking_id = tbm.id AND tbt2.deleted_by = 0
            GROUP BY tbt2.tour_booking_id), 0) AS `tourists`,

           COALESCE((SELECT sum(COALESCE(tbt3.infants, 0)) FROM tour_bookings_transact AS tbt3
            WHERE tbt3.tour_booking_id = tbm.id AND tbt3.deleted_by = 0
            GROUP BY tbt3.tour_booking_id), 0) AS `infants`,

           coalesce(`ivb`.`invoice_id`, 0) as `invoice_id`,
           coalesce(`inv`.`status`, 'billable') as `invoice_status`,
           `inv`.`status` as `raw_invoice_status`,
           `tbm`.`booking_total`,
			 (
                SELECT 
                    SUM(
                        CASE 
                            WHEN (operation = 'Refund' 
                                AND JSON_UNQUOTE(JSON_EXTRACT(`tbh`.`details`, '$.payment_type')) != '')
                            THEN `tbh`.`ticket_cost`  
                            WHEN operation = 'Cancelled' THEN `tbh`.`ticket_cost` 
                            ELSE 0 
                        END
                    )  
                FROM tour_booking_history AS tbh
                WHERE tbh.tour_booking_id = tbm.id
            ) AS `refund_total`,

            (SELECT SUM(case operation when 'Payment' then  `tbh`.`ticket_cost` else 0 end)  FROM tour_booking_history AS tbh
			WHERE tbh.tour_booking_id = tbm.id ) AS `payment_total`,
            
            -- Note: despite the column name, this also folds in 'addons' charge_type rows — both
            -- are extra charges on top of booking_total and the JS (renderBookingTotal) adds this
            -- straight onto booking_total, so excluding addons here silently dropped them from the
            -- displayed 'Booking Total'.
            (SELECT SUM(case operation when 'Payment' then  `tbh`.`ticket_cost` else 0 end)  FROM tour_booking_history AS tbh
			WHERE tbh.tour_booking_id = tbm.id AND `tbh`.`charge_type` IN ('custom_charge', 'addons')) AS `custom_charge_total`,

            (SELECT SUM(case operation when 'Refund' then  `tbh`.`ticket_cost` else 0 end)  FROM tour_booking_history AS tbh
			WHERE tbh.tour_booking_id = tbm.id AND `tbh`.`charge_type` IN ('custom_charge', 'addons')) AS `custom_charge_refund_total`

           
           FROM `tour_bookings_master` as `tbm`

           INNER JOIN `tour_booking_history` as `tbh` ON `tbh`.`tour_booking_id`=`tbm`.`id`
           INNER JOIN `tour_packages` as `tpkg` on `tpkg`.`id` = `tbm`.`tour_package_id`
           INNER JOIN `customers` as `cus` on `cus`.`id` = `tbm`.`customer_id`
           INNER JOIN `tour_slot_times` as `tst` on `tst`.`id` = `tbm`.`tour_slot_id`
           INNER JOIN `tour_slot_dates` as `tsd` on `tsd`.`id` = `tst`.`tour_slot_id`
           INNER JOIN `tour_operators` as `top` on `top`.`id` = `tpkg`.`tour_operator_id`
           INNER JOIN `transactions` as `trans` on `trans`.`tour_booking_id` = `tbm`.`id`
           INNER JOIN `tour_bookings_transact` as `tbt` on `tbt`.`tour_booking_id`= `tbm`.`id` AND `tbt`.`deleted_by` = 0
           LEFT JOIN `invoice_bookings` as `ivb` on `ivb`.`tour_booking_id` = `tbm`.`id`
           LEFT JOIN `invoices` as `inv` on (`inv`.`id` = `ivb`.`invoice_id` AND `inv`.`deleted_at` IS NULL )

           WHERE `tbh`.`operation` IN ('Payment', 'Refund', 'Cancelled')
                AND `tbh`.`payment_type` = 'Invoiced' #  ->where('tbh.payment_type', 'Invoiced')
                AND `cus`.`is_affiliate` = 'Yes' #  ->where('c.is_affiliate','Yes')
                AND `trans`.`via` = 'Invoiced' # ->where('t.via','Invoiced')
                AND `trans`.`status` = 'Completed' #  ->where('t.status','Completed')
                AND `tbm`.`status` = 'Booked' #  ->where('tbm.status','Booked')
           ";

            if (intval($request->customerId) != 0) {
                $sql .= " AND `cus`.`id` = " . intval($request->customerId) . "\n";
            }

            if (intval($request->tourPackageId) != 0) {
                $sql .= " AND `tsd`.`tour_package_id` = " . intval($request->tourPackageId) . "\n";
            }

            $tourOperatorId = (userType() == 'Tour Operator') ? auth()->user()->tour_operator_id : intval($request->tourOperatorId);
            $sql .= " AND `tpkg`.`tour_operator_id` = " .  intval($tourOperatorId) . "\n";

            if ($request->tourDate) {
                $sql .= ' AND `tsd`.`date` between "' . $dates["start_date"] . '" and "' . $dates["end_date"] . '"' . "\n";
            } else {
                $sql .= ' AND (`inv`.`from_date` between "' . $dates["start_date"] . '" and "' . $dates["end_date"] . '" or `inv`.`to_date` between "' . $dates["start_date"] . '" and "' . $dates["end_date"] . '")' . "\n";
            }

            if ($request->status != 'all') {
                $status = $request->status;
                if (str_contains($status, '+')) {
                    $terms = explode("+", $status);
                    $sql .= ' AND coalesce(`inv`.`status`, "billable") IN ("' . implode('", "', $terms) . '")' . "\n";
                } else {
                    $sql .= " AND coalesce(`inv`.`status`, 'billable') = '" . $status . "'\n";
                }
            }

            $sql .= " GROUP BY `booking_id` ORDER BY `customer` ASC;";

            $res = DB::select($sql);

            // return json if not csv export
            if (!$request->export) {
                if ($request->query('grpByInvoice', false)) {
                    $collection = [];
                    foreach ($res as $row) {
                        $collection[$row->invoice_id][] = $row;
                    }
                    $res = $collection;
                }
                return $res;
            } else {
                $name = 'invoice_insights_' . $dates["start_date"] . '_' . $dates["end_date"] . '.csv';
                return $this->invoiceInsightsReportToCsv(
                    $name,
                    json_decode(json_encode($res), true),
                    $request->tourOperatorId,
                    $request->tourPackageId
                );
            }
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }


    public function invoiceInsightsReportToCsv(string $name, array $data, $tourOperatorId, $tourPackageId)
    {

        $columns = csvColumns('invoice_insights_report');

        $tourOperatorName = $this->GetTourOperatorName($tourOperatorId, $tourPackageId);
        $callback = function () use ($data, $columns, $tourOperatorName) {
            $file = fopen('php://output', 'w');
            if (!empty($tourOperatorName)) {
                $BlankLine = '';
                if ($tourOperatorName['tourOperatorName']) {
                    $tourOperatorNameRow = array('Tour Operator:', $tourOperatorName['tourOperatorName'], $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourOperatorNameRow);
                }
                if ($tourOperatorName['tourPackageName']) {
                    $tourPackageNameRow = array('Tour Package:', $tourOperatorName['tourPackageName'], $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourPackageNameRow);
                }
                fputcsv($file, array($BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine));
            }
            fputcsv($file, $columns);
            foreach ($data as $key => $val) {
                $row = array(
                    $val['invoice_id'],
                    $val['invoice_status'],
                    $val['affiliate_id'],
                    $val['affiliate_name'],
                    $val['email'],
                    $val['phone_number'],
                    $val['booking_id'],
                    $val['booking_date'],
                    $val['payment_type'],
                    $val['payment_status'],
                    $val['tour_name'],
                    $val['tour_date'],
                    $val['tour_time'],
                    $val['tourists'],
                    $val['infants'],
                    $val['due_date'],
                    // Matches renderBookingTotal() in invoice-insights-report.js — booking_total is
                    // the raw ticket cost only; custom_charge_total (now including 'addons' rows
                    // too, see the query above) must be added for the CSV to match the on-screen total.
                    (float)($val['booking_total'] ?? 0) + (float)($val['custom_charge_total'] ?? 0),
                    $val['refund_total'],
                    $val['payment_total']
                );
                fputcsv($file, $row);
            }
            fclose($file);
        };
        return response()->stream($callback, 200, csvHeaders($name));
    }

    /*invoice insights report invoice list*/
    public function invoicelist(Request $request, $customerId, $tourOpId)
    {
        try {
            $query = Invoice::where(function ($query) use ($request) {
                return $query->whereBetween('from_date', [$request->from_date, $request->to_date])
                    ->orWhereBetween('to_date', [$request->from_date, $request->to_date]);
            });

            $query->select('invoices.*', 'customers.name as customer_name', 'tour_bookings_master.tour_package_id');
            $query->join('customers', 'customers.id', '=', 'invoices.customer_id');
            $query->join('invoice_bookings', 'invoice_bookings.invoice_id', '=', 'invoices.id');
            $query->join('tour_bookings_master', 'tour_bookings_master.id', '=', 'invoice_bookings.tour_booking_id');
            $query->join('tour_packages', 'tour_packages.id', '=', 'tour_bookings_master.tour_package_id');
            $query->select(
                'invoices.*',
                'customers.name as customer_name',
                'tour_bookings_master.tour_package_id',
                'tour_packages.name as tour_package_name'
            );
            if ($request->package_id) {
                $query->where('tour_bookings_master.tour_package_id', $request->package_id);
            }


            // check if admin
            if (session('userType') == 'Tour Operator') {
                $query->where('invoices.tour_operator_id', auth()->user()->tour_operator_id);
            } else {
                $query->where('invoices.tour_operator_id', $tourOpId);
            }

            // if customer id filter by customer id
            if ($customerId != "0" || $customerId != 0) {
                $query->where('invoices.customer_id', $customerId);
            }

            //if the user whats to view deleted invoices
            if ($request->displayVoided) {
                $invoiceRepository = new InvoiceRepository();
                $deletedInvoiceList = $invoiceRepository->getDeletedInvoicesByDate($request->from_date, $request->to_date);
            } else {
                $deletedInvoiceList = [];
            }

            $response = $query->distinct('invoices.id')->get();

            return response()->json(['results' => $response, "deleted" => $deletedInvoiceList], 200);
        } catch (Exception $err) {
            return response()->json(["results" => [], "deleted" => [], "error" => $err->getMessage()]);
        }
    }

    public function invoiceSumByStatus(Request $request)
    {
        $query = Invoice::where(function ($query) use ($request) {
            return $query->whereBetween('from_date', [$request->from_date, $request->to_date])
                ->orWhereBetween('to_date', [$request->from_date, $request->to_date]);
        })->whereNull('invoices.deleted_by')
            ->select([
                'status',
                'external_status',
                DB::raw('SUM(amount) AS amount'),
            ])
            ->groupBy('status', 'external_status');

        if (session('userType') == 'Tour Operator') {
            $query->where('invoices.tour_operator_id', auth()->user()->tour_operator_id);
        } else {
            $query->where('invoices.tour_operator_id', $request->tourOpId);
        }
        if ($request->customerId) {
            $query->where('invoices.customer_id', $request->customerId);
        }
        $bookings = $query->get();
        return $bookings;
    }

    /**
     * Logic to get the bookings of specific coupon codes
     *
     * @param string $fromDate
     * @param string $toDate
     * @param int $tourOperatorId
     * @param int $tourPackageId
     * @param int $promocodeId
     * @param int $export
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function promotionReportData($fromDate, $toDate, $tourOperatorId, $tourPackageId, $promocodeId = 0, $export)
    {
        try {
            ini_set('memory_limit', 160217728);

            // get the start and end date from the get params.
            $dates = DateUtils::startEndDateDefaults($fromDate, $toDate);

            /* START - PAYMENT / REFUND COLLECTION QUERY FOR CASH */
            $promotionSql = 'SELECT
                `tbm`.`id` as `tour_booking_id`,
                `tbm`.`status` as `status`,
                `tbm`.`discount2_value` as `discount`,
                `tbm`.`name` as `customer`,
                `tst`.`time_from`,
                `tst`.`time_to`,
                `tsd`.`date`,
                `tbm`.`tourists`,
                `tbm`.`infants`,
                `tbm`.`created_at`,
                `tps`.`max_allowed_usages`,
                `tps`.`end_date`,
                `tps`.`code`
                from
                `tour_bookings_master` as `tbm`
                inner join `tour_slot_times` as `tst` on `tst`.`id` = `tbm`.`tour_slot_id`
                inner join `tour_slot_dates` as `tsd` on `tsd`.`id` = `tst`.`tour_slot_id`
                inner join `tour_packages` as `tp` on `tp`.`id` = `tbm`.`tour_package_id`
                inner join `tour_promotions` as `tps` on `tps`.`id` = `tbm`.`tour_promotion_id`';

            $promocodeSql = ' where tbm.tour_promotion_id > 0';
            if ($promocodeId) {
                $promocodeSql = ' where tbm.tour_promotion_id = ' . intval($promocodeId);
            }

            //if tour opt logged in
            $tourOperatorId = intval(tourOperator()->id);

            $promotionSql .= $promocodeSql . '
                      and `tp`.`id` = ' . intval($tourPackageId) . '
                      and `tp`.`tour_operator_id` = ' . $tourOperatorId . '
                      and `tsd`.`date` between "' . $dates['start_date'] . '" and "' . $dates["end_date"] . '"';

            $rawData = DB::select($promotionSql);

            $sortedDataArray = [];

            foreach ($rawData as $value) {
                $bookingId = $value->tour_booking_id;
                $sortedDataArray[$bookingId] = $value;
            }
            // Fetch rate components for all bookings in a single query
            $bookingIds = array_keys($sortedDataArray);

            $rateComponents = $this->fetchRateComponentsForBookings($bookingIds);
            $filters = [];

            // Append rate components to each booking
            foreach ($sortedDataArray as $bookingId => $booking) {
                if (isset($rateComponents[$bookingId])) {
                    // Store the array of rate breakdowns
                    $booking->rate_breakdown = $rateComponents[$bookingId];

                    // Also calculate totals for backward compatibility
                    $totalRate = 0;
                    $totalTax = 0;
                    $totalPermitFee = 0;
                    $rateForList = [];

                    // For per-person display: collect unique rate values
                    $uniqueRates = [];
                    $uniqueTaxes = [];
                    $uniquePermitFees = [];

                    foreach ($rateComponents[$bookingId] as $rateData) {
                        // Skip rate groups with 0 passengers
                        if ($rateData['pax'] <= 0) {
                            continue;
                        }

                        // The rate, tax, permit_fee in the data are ALREADY per-person values
                        // So we need to multiply by pax to get totals
                        $perPersonRate = (float) $rateData['rate'];
                        $perPersonTax = (float) $rateData['tax'];
                        $perPersonPermitFee = (float) $rateData['permit_fee'];
                        $pax = (int) $rateData['pax'];

                        $totalRate += ($perPersonRate * $pax);
                        $totalTax += ($perPersonTax * $pax);
                        $totalPermitFee += ($perPersonPermitFee * $pax);
                        $rateForList[] = $rateData['rate_for'];

                        // Collect unique per-person values
                        $uniqueRates[] = round($perPersonRate, 2);
                        $uniqueTaxes[] = round($perPersonTax, 2);
                        $uniquePermitFees[] = round($perPersonPermitFee, 2);
                    }

                    $booking->rate = round($totalRate, 2);
                    $booking->tax = round($totalTax, 2);
                    $booking->permit_fee = round($totalPermitFee, 2);
                    $booking->ticket_cost = round($totalRate + $totalTax + $totalPermitFee, 2);
                    $booking->rate_for = implode(', ', array_unique($rateForList));

                    // Add per-person display values
                    // If all rates are the same, show single value; otherwise show range or list
                    $uniqueRates = array_unique($uniqueRates);
                    $uniqueTaxes = array_unique($uniqueTaxes);
                    $uniquePermitFees = array_unique($uniquePermitFees);

                    // FILTER-AWARE DISPLAY: If a rate/tax/permitFee filter is active, prioritize showing that value
                    $filteredRate = isset($filters['rate']) ? round((float)$filters['rate'], 2) : null;
                    $filteredTax = isset($filters['tax']) ? round((float)$filters['tax'], 2) : null;
                    $filteredPermitFee = isset($filters['permitFee']) ? round((float)$filters['permitFee'], 2) : null;

                    // Rate display logic
                    if (empty($uniqueRates)) {
                        $booking->pax_rate = 0;
                    } elseif ($filteredRate !== null && in_array($filteredRate, $uniqueRates)) {
                        $booking->pax_rate = $filteredRate;
                    } elseif (count($uniqueRates) === 1) {
                        $booking->pax_rate = reset($uniqueRates);
                    } else {
                        $booking->pax_rate = max($uniqueRates);
                    }

                    // Tax display logic
                    if (empty($uniqueTaxes)) {
                        $booking->pax_tax = 0;
                    } elseif ($filteredTax !== null && in_array($filteredTax, $uniqueTaxes)) {
                        $booking->pax_tax = $filteredTax;
                    } elseif (count($uniqueTaxes) === 1) {
                        $booking->pax_tax = reset($uniqueTaxes);
                    } else {
                        $booking->pax_tax = max($uniqueTaxes);
                    }

                    // Permit fee display logic
                    if (empty($uniquePermitFees)) {
                        $booking->pax_permit_fee = 0;
                    } elseif ($filteredPermitFee !== null && in_array($filteredPermitFee, $uniquePermitFees)) {
                        $booking->pax_permit_fee = $filteredPermitFee;
                    } elseif (count($uniquePermitFees) === 1) {
                        $booking->pax_permit_fee = reset($uniquePermitFees);
                    } else {
                        $booking->pax_permit_fee = max($uniquePermitFees);
                    }
                } else {
                    // Default values if no rate data found
                    $booking->rate_breakdown = [];
                    $booking->rate = 0;
                    $booking->tax = 0;
                    $booking->permit_fee = 0;
                    $booking->rate_for = 'Unknown';
                    $booking->pax_rate = 0;
                    $booking->pax_tax = 0;
                    $booking->pax_permit_fee = 0;
                }
            }
            $promotionsData = [];
            $rateCategories = [];

            foreach ($sortedDataArray as $row) {
                $promotionsData[] = $row;

                if (!isset($row->rate_breakdown) || !is_array($row->rate_breakdown)) {
                    continue;
                }

                foreach ($row->rate_breakdown as $rateData) {
                    $pax = (int) ($rateData['pax'] ?? 0);
                    if ($pax <= 0) {
                        continue;
                    }

                    $rateId         = $rateData['rate_id'] ?? 'unknown';
                    $rateFor        = $rateData['rate_for'] ?? 'Unknown';
                    $mappingKey     = $rateData['mapping_key'] ?? 0;
                    $rate           = (float) ($rateData['rate'] ?? 0);
                    $tax            = (float) ($rateData['tax'] ?? 0);
                    $permitFee      = (float) ($rateData['permit_fee'] ?? 0);
                    $perPersonTotal = $rate + $tax + $permitFee;
                    $totalForGroup  = $perPersonTotal * $pax;

                    $priceSignature = sprintf('%.2f|%.2f|%.2f', $rate, $tax, $permitFee);
                    $groupKey       = ($mappingKey === 0 ? $rateId : $mappingKey) . '_' . $priceSignature;
                    $categoryKey    = $mappingKey !== 0 ? $mappingKey : 'Default';

                    if (!isset($rateCategories[$categoryKey])) {
                        $rateCategories[$categoryKey] = [
                            'categoryKey'  => $categoryKey,
                            'categoryName' => $mappingKey !== 0 ? $rateFor : 'Default',
                            'mappingKey'   => $mappingKey,
                            'totalPax'     => 0,
                            'totalAmount'  => 0,
                            'rateGroups'   => [],
                        ];
                    }

                    if (!isset($rateCategories[$categoryKey]['rateGroups'][$groupKey])) {
                        $rateCategories[$categoryKey]['rateGroups'][$groupKey] = [
                            'groupKey'           => $groupKey,
                            'rateFor'            => $rateFor,
                            'pax'                => 0,
                            'perPersonRate'      => $rate,
                            'perPersonTax'       => $tax,
                            'perPersonPermitFee' => $permitFee,
                            'perPersonTotal'     => $perPersonTotal,
                            'totalAmount'        => 0,
                            'mappingKey'         => $mappingKey,
                        ];
                    }

                    $rateCategories[$categoryKey]['rateGroups'][$groupKey]['pax']         += $pax;
                    $rateCategories[$categoryKey]['rateGroups'][$groupKey]['totalAmount']  += $totalForGroup;
                    $rateCategories[$categoryKey]['totalPax']                              += $pax;
                    $rateCategories[$categoryKey]['totalAmount']                           += $totalForGroup;
                }
            }

            $result = array();

            $totalTicketCost = 0;
            $totalDiscountAmount = 0;
            $totalTourists = 0;
            $totalInfants = 0;
            foreach ($promotionsData as $row) {
                $code = $row->code;
                $result[$code]['bookings'][] = $row;
                $result[$code]['promo_code'] = $code;
                $result[$code]['max_allowed_usages'] = $row->max_allowed_usages;
                $result[$code]['end_date'] = $row->end_date;
                $result[$code]['ticket_cost'] = ($result[$code]['ticket_cost']     ?? 0) + $row->ticket_cost;
                $result[$code]['total_discount'] = ($result[$code]['total_discount'] ?? 0) + $row->discount;
                $result[$code]['total_tourists'] = ($result[$code]['total_tourists'] ?? 0) + $row->tourists;
                $result[$code]['total_infants']  = ($result[$code]['total_infants']  ?? 0) + $row->infants;
                $totalTicketCost += $row->ticket_cost;
                $totalDiscountAmount += $row->discount;
                $totalTourists += $row->tourists;
                $totalInfants += $row->infants;
            }


            $result = array_values($result);
            $result['total_ticket_cost_amount'] = $totalTicketCost;
            $result['total_discount_amount'] = $totalDiscountAmount;
            $result['total_tourists'] = $totalTourists;
            $result['total_infants'] = $totalInfants;
            $result['rateCategories'] = $rateCategories;
            // return array if not export
            if (!$export) {
                return $result;
                // return csv byte stream if export
            } else {
                $name = 'promotion_report_' . $fromDate . '_' . $toDate . '.csv';
                return $this->promotionReportToCsv($name, $result, $tourOperatorId, $tourPackageId, $fromDate, $toDate);
            }
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }


    /**
     * Return the Promotion Report as a CSV file.
     * @param string $name file name
     * @param array $data promotion report query response
     * @return (/file) stream bytes
     */
    public function promotionReportToCsv(
        string $name,
        array $data,
        int $tourOperatorId = 0,
        int $tourPackageId = 0,
        string $fromDate,
        string $toDate
    ) {
        $tourOperatorName = $this->GetTourOperatorName($tourOperatorId, $tourPackageId);
        $summaryCols     = csvColumns('promotion_report_summary_column');
        $promoCols       = csvColumns('promotion_report_promo_column');
        $bookingCols     = csvColumns('promotion_report_column');
        $rateCatCols     = csvColumns('promotion_report_rate_category_column');

        $callback = function () use ($data, $tourOperatorName, $fromDate, $toDate, $summaryCols, $promoCols, $bookingCols, $rateCatCols) {
            $file = fopen('php://output', 'w');
            $blank = '';

            // Tour operator / package header
            if (!empty($tourOperatorName)) {
                if ($tourOperatorName['tourOperatorName']) {
                    fputcsv($file, ['Tour Operator:', $tourOperatorName['tourOperatorName']]);
                }
                if ($tourOperatorName['tourPackageName']) {
                    fputcsv($file, ['Tour Package:', $tourOperatorName['tourPackageName']]);
                }
                fputcsv($file, [$blank]);
            }

            // Overall summary row
            $totalFinal = $data['total_ticket_cost_amount'] - $data['total_discount_amount'];
            fputcsv($file, $summaryCols);
            fputcsv($file, [
                date("m-d-Y", strtotime($fromDate)) . ' - ' . date("m-d-Y", strtotime($toDate)),
                date("m-d-Y"),
                $data['total_tourists'],
                $data['total_infants'],
                number_format($data['total_ticket_cost_amount'], 2),
                number_format($data['total_discount_amount'], 2),
                number_format($totalFinal, 2),
            ]);

            // Rate Categories section
            if (!empty($data['rateCategories'])) {
                fputcsv($file, [$blank]);
                fputcsv($file, ['Rate Categories']);
                fputcsv($file, $rateCatCols);

                $overallPax    = 0;
                $overallAmount = 0.0;

                foreach ($data['rateCategories'] as $cat) {
                    // Category header row
                    fputcsv($file, [$cat['categoryName'], $cat['totalPax'] . ' tourists', '', '', '', number_format($cat['totalAmount'], 2)]);

                    foreach ($cat['rateGroups'] as $grp) {
                        fputcsv($file, [
                            $grp['rateFor'],
                            $grp['pax'],
                            number_format($grp['perPersonRate'],      2),
                            number_format($grp['perPersonTax'],       2),
                            number_format($grp['perPersonPermitFee'], 2),
                            number_format($grp['totalAmount'],        2),
                        ]);
                    }

                    $overallPax    += $cat['totalPax'];
                    $overallAmount += $cat['totalAmount'];
                }

                // Total row
                fputcsv($file, ['TOTAL', $overallPax, '-', '-', '-', number_format($overallAmount, 2)]);
            }

            // Per promo-code sections
            foreach ($data as $key => $group) {
                if (!is_array($group) || !isset($group['bookings'])) {
                    continue;
                }

                $groupFinal = $group['ticket_cost'] - $group['total_discount'];

                fputcsv($file, [$blank]);
                fputcsv($file, $promoCols);
                fputcsv($file, [
                    $group['promo_code'],
                    count($group['bookings']),
                    count($group['bookings']),
                    number_format($group['ticket_cost'], 2),
                    number_format($group['total_discount'], 2),
                    number_format($groupFinal, 2),
                ]);

                fputcsv($file, [$blank]);
                fputcsv($file, $bookingCols);

                foreach ($group['bookings'] as $booking) {
                    $amount   = number_format((float) $booking->ticket_cost, 2);
                    $discount = number_format((float) $booking->discount, 2);
                    $final    = number_format((float) $booking->ticket_cost - (float) $booking->discount, 2);

                    fputcsv($file, [
                        $booking->tour_booking_id,
                        date("m-d-Y", strtotime($booking->date)) . ' @ ' . $booking->time_from,
                        $booking->customer,
                        $booking->status,
                        $booking->tourists,
                        $booking->infants,
                        $amount,
                        $discount,
                        $final,
                    ]);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, csvHeaders($name));
    }
    /**
     * Fetch rate components for each booking individually
     *
     * @param array $bookingIds
     * @return array
     */
    private function fetchRateComponentsForBookings(array $bookingIds): array
    {
        if (empty($bookingIds)) {
            return [];
        }

        // Use chunking to avoid "too many placeholders" error
        $chunkSize = 500;
        $result = [];

        foreach (array_chunk($bookingIds, $chunkSize) as $chunkIndex => $bookingIdChunk) {
            try {
                // Fetch transaction records for this chunk of bookings
                // Each row represents one rate group with multiple passengers
                $transactions = DB::table('tour_bookings_transact as tbt')
                    ->leftJoin('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
                    ->whereIn('tbt.tour_booking_id', $bookingIdChunk)
                    ->where(function ($q) {
                        $q->where('tbt.deleted_by', 0)->orWhereNull('tbt.deleted_by');
                    })
                    ->select(
                        'tbt.tour_booking_id',
                        'tbt.tour_package_rate_id',
                        'tpr.rate_for',
                        'tpr.mapping_key',
                        'tbt.rate',
                        'tbt.additional_charge as tax',
                        'tbt.permit_fee',
                        'tbt.tourists',
                        'tbt.infants'
                    )
                    ->get();

                // Group transactions by booking_id
                foreach ($transactions as $transaction) {
                    $bookingId = $transaction->tour_booking_id;

                    if (!isset($result[$bookingId])) {
                        $result[$bookingId] = [];
                    }

                    // Calculate total pax for this rate group (tourists + infants)
                    $paxCount = (int)$transaction->tourists + (int)$transaction->infants;

                    $result[$bookingId][] = [
                        'rate_id' => $transaction->tour_package_rate_id,
                        'rate_for' => $transaction->rate_for ?? 'Unknown',
                        'mapping_key' => $transaction->mapping_key ?? 0,
                        'rate' => (float) $transaction->rate,
                        'tax' => (float) $transaction->tax,
                        'permit_fee' => (float) $transaction->permit_fee,
                        'pax' => $paxCount // Total passengers for this rate group
                    ];
                }
            } catch (Exception $chunkException) {
                Log::warning("ReportRepository::fetchRateComponentsForBookings Chunk {$chunkIndex} Error: " . $chunkException->getMessage());
                continue;
            }
        }

        return $result;
    }

    /**
     * OTAs [TripAdvisor | GetYourGuide] Report
     *
     * @param string $fromDate
     * @param string $toDate
     * @param int $tourOperatorId
     * @param int $tourPackageId
     * @param string $otaPlatform [TripAdvisor | GetYourGuide]
     * @param int $export
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function otaBookingReport($request, string $fromDate = "", string $toDate = "", int $tourOperatorId = 0, int $tourPackageId = 0, string $otaPlatform = "", int $export = 0)
    {
        try {
            ini_set('memory_limit', 160217728);

            // Determine tour operator ID if logged in as operator
            $tourOperatorId = (userType() === 'Tour Operator') ? auth()->user()->tour_operator_id : $tourOperatorId;

            // Determine OTA platforms to filter
            $otaPlatformList = empty($otaPlatform) ? BokunUtils::getAllOtaTitles() : [$otaPlatform];
            $payTypes = implode(',', array_map(fn($p) => '"' . $p . '"', $otaPlatformList));

            $sortDir = "DESC";

            // Determine whether to group/filter by tour date or purchase/created date
            $dateType = $request->dateType ?? 'tour_date';
            if ($request->viewBy === 'purchasedate') {
                $dateType = 'purchase_date';
            }
            $usePurchaseDate = ($dateType === 'purchase_date');

            if ($usePurchaseDate) {
                $dateGroupSql = "DATE(`tbh`.`operation_at`) AS `date`,
                WEEK(`tbh`.`operation_at`) AS `week`,
                MONTH(`tbh`.`operation_at`) AS `month`,
                YEAR(`tbh`.`operation_at`) AS `year`,
                concat(MONTH(`tbh`.`operation_at`), YEAR(`tbh`.`operation_at`)) AS `mn_yr`,
                concat(YEAR(`tbh`.`operation_at`), MONTH(`tbh`.`operation_at`), WEEK(`tbh`.`operation_at`)) AS `wk_mn_yr`,";
                $dateWhere = 'DATE(`tbh`.`operation_at`) between "' . $fromDate . '" and "' . $toDate . '"';
            } else {
                $dateGroupSql = "`tsd`.`date`,
                WEEK(`tsd`.`date`) AS `week`,
                MONTH(`tsd`.`date`) AS `month`,
                YEAR(`tsd`.`date`) AS `year`,
                concat(MONTH(`tsd`.`date`), YEAR(`tsd`.`date`)) AS `mn_yr`,
                concat(YEAR(`tsd`.`date`), MONTH(`tsd`.`date`), WEEK(`tsd`.`date`)) AS `wk_mn_yr`,";
                $dateWhere = '`tsd`.`date` between "' . $fromDate . '" and "' . $toDate . '"';
            }

            $sql = "SELECT
                `tbm`.`id` AS `bid`,
                `tbm`.`viator_bookingRef` as `tripadvisor_booking_id`,
                `tbm`.`get_your_guide_reference_booking` as `get_your_guide_booking_id`,
                `tbm`.`ota_reference_booking` as `ota_booking_id`,
                `tbm`.`status`,
                {$dateGroupSql}
                DATE_FORMAT(`tbm`.`created_at`, '%Y-%m-%d') AS `bdt`,
                DATE_FORMAT(`tsd`.`date`, '%Y-%m-%d') AS `t_date`,
                DATE_FORMAT(`tst`.`time_from`, '%h:%i %p') AS `t_time`,
                `tbh`.`payment_type` AS `trans_type`,
                ifnull(json_unquote(json_extract(`tbh`.`details`, '$.amount')), 0) AS `trans_cost`,
                ifnull(json_unquote(json_extract(`tbh`.`details`, '$.refund_keep_us')), 0) AS `trans_fee`,
                `tbh`.`ticket_cost`,
                `tbh`.`processing_fees` AS pro_fees,
                `tbh`.`platform_fees` AS plt_fees,
                `tbh`.`permit_fees` AS permit_fees,
                `tbh`.`details`,
                `tbh`.`operation`,
                (`tbm`.`tourists` + `tbm`.`infants`) AS `tourists`,
                `tbh`.`discount`
            FROM
                `tour_booking_history` AS `tbh`
                INNER JOIN `tour_bookings_master` AS `tbm` ON `tbm`.`id` = `tbh`.`tour_booking_id`
                INNER JOIN `tour_slot_times` AS `tst` ON `tst`.`id` = `tbm`.`tour_slot_id`
                INNER JOIN `tour_slot_dates` AS `tsd` ON `tsd`.`id` = `tst`.`tour_slot_id`
                INNER JOIN `tour_packages` AS `tp` ON `tp`.`id` = `tbm`.`tour_package_id`";

            // Set default query filters
            // Get query filter is any
            if ($request->filters) {
                if ($request->payment_type) {
                    $payTypes = StrUtils::dbInFilter($request->payment_type);
                }
                if ($request->sort) {
                    $sortDir = ($request->sort == 'DESC') ? 'DESC' : 'ASC';
                }
            }

            $sql .= ' where `tbh`.`payment_type` IN (' . $payTypes . ')
                and `tbh`.`operation` in ("Payment", "Refund", "Cancelled")
                and ' . $dateWhere . '
                and `tp`.`id` = "' . intval($tourPackageId) . '"
                and `tp`.`tour_operator_id` = "' . $tourOperatorId . '" ';

            // Order by filter default DESC
            $sql .= " ORDER BY `tbm`.`id` " . $sortDir . ";";

            // Query the for bookings index the bookings array example: [ [1234] => booking ]
            $bookings = BookingUtils::getBookingsIndexById($sql);
            $collection = $this->getOTAsBookingCalc($bookings, $otaPlatform);
            unset($bookings); // we dont need this in memory any longer.

            // Return array if not export
            if (!$export) {
                return $collection;
            } else {
                // Return csv byte stream if export
                $platformName = (!$otaPlatform && $otaPlatform == "") ? 'ota' : strtolower($otaPlatform);
                $name = $platformName . '_report_' . $fromDate . '_' . $toDate . '.csv';
                return $this->otaReportToCsv($name, $collection, $tourOperatorId, $tourPackageId, $fromDate, $toDate);
            }
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Calculation of TripAdvisor|GetYourGuide bookings by ID
     * @param $bookings array of bookings from rev report sql
     * @param string $platformName Third party booking platform name [TripAdvisor|GetYourGuide]
     * 
     * @return array
     */
    public function getOTAsBookingCalc($bookings, $otaPlatform): array
    {

        // helper function deduct the process and platform fees from the ticket cost
        $netCalc = function ($amt, $row, $parser = null) {

            $permitFee = $parser($row->permit_fees, 0); // permit fee
            $platformFees = $parser($row->plt_fees, 0); // platform fee
            $processingFees = $parser($row->pro_fees, 0); // processing fee
            $totalFees = (($platformFees) + $processingFees); // total fees

            // calculate the net value of the booking
            $net = (floatval($amt) - $totalFees);

            // return cost breakdown
            return [
                "net" => $net, // net for booking
                "fee" => $totalFees, // total fee for booking
                "pro" => $processingFees, // processing fee
                "plt" => $platformFees, // platform fee
                "per" => $permitFee // permit fee
            ];
        };

        // set the object to hold our enriched data
        $calculation = new stdClass;

        // helper functions
        $calculation->parseAmt = function ($num, $alt = 0) {
            return StrUtils::parseFloat($num, $alt);
        };
        $calculation->netCalc = $netCalc; //see closure method above

        // total sum of all bookings
        $calculation->total_gross = 0;
        $calculation->total_refund = 0;
        $calculation->total_refund_fees = 0;
        $calculation->total_ticket = 0;

        // single sums for each booking
        $calculation->booking_gross = 0;
        $calculation->booking_ticket_cost = 0;
        $calculation->booking_refund = 0;
        $calculation->booking_discount = 0;

        //fees: permit_fees processing_fees platform_fees, refund
        $calculation->total_payment_fees = 0;
        $calculation->booking_refund_fees = 0;

        // total all fees:
        $calculation->booking_payment_fees = 0;
        $calculation->total_tourists = 0;

        // loop thru grp collection not this associative array is grouped by booking id
        $bookingCalc = function ($v, $k) use ($calculation) {
            // parse ticket price amount into a float
            $amt = ($calculation->parseAmt)($v->trans_cost, $v->ticket_cost);

            /**
             * If operation is type payment add to the total net and gross
             * remove the platform fees and processing fees from the net
             */
            if ($v->operation == 'Payment') {
                $rev = ($calculation->netCalc)($amt, $v, $calculation->parseAmt);
                $calculation->booking_gross += $amt;
                $calculation->booking_ticket_cost += floatval($v->ticket_cost);
                $calculation->booking_discount += ($calculation->parseAmt)(floatval($v->discount));
            }

            /**
             * If operation is type refund or cancelled deduct from the net revenue
             * add up the refund fees
             */
            if ($v->operation == 'Refund' || $v->operation == 'Cancelled') {
                $trans = ($calculation->parseAmt)($v->trans_cost, 0);
                $ticket = ($calculation->parseAmt)($v->ticket_cost, 0);
                $rev = ($calculation->netCalc)($trans, $v, $calculation->parseAmt);
                $calculation->booking_ticket_cost -= $ticket;

                if ($v->trans_type != "Cash") {
                    $calculation->booking_refund_fees += $rev['fee'];
                    $calculation->booking_refund += ($calculation->parseAmt)($v->trans_cost);
                }
            }
        };

        //set or totals placeholder vars.
        $collection = [];
        foreach ($bookings as $row) {
            //reset values for each booking id collection row
            $calculation->booking_gross = 0;
            $calculation->booking_refund = 0;
            $calculation->booking_refund_fees = 0;
            $calculation->booking_payment_fees = 0;
            $calculation->booking_ticket_cost = 0;
            $calculation->booking_discount = 0;

            // for each booking in booking group sum the totals
            array_walk($row, $bookingCalc);

            // after each like booking id row is added up add it to the collection.
            $d = current($row);

            if ($otaPlatform == "TripAdvisor") {
                $reference_id = $d->tripadvisor_booking_id;
            } else if ($otaPlatform == "GetYourGuide") {
                $reference_id = $d->get_your_guide_booking_id;
            } else {
                if ($d->trans_type == "TripAdvisor") {
                    $reference_id = $d->tripadvisor_booking_id;
                } else if ($d->trans_type == "GetYourGuide") {
                    $reference_id = $d->get_your_guide_booking_id;
                } else {
                    $reference_id = $d->ota_booking_id;
                }
            }
            if ($reference_id == null) {
                $reference_id = $d->ota_booking_id;
            }

            $updated = [
                "booking_id" => $d->bid,
                "reference_id" => $reference_id,
                "booking_date" => $d->bdt,
                "status" => $d->status,
                "date" => $d->date,
                "week" => $d->week,
                "month" => $d->month,
                "year" => $d->year,
                "mn_yr" => $d->mn_yr,
                "wk_mn_yr" => $d->wk_mn_yr,
                "tour_date" => $d->t_date,
                "tour_time" => $d->t_time,
                "trans_type" => $d->trans_type,
                "trans_cost" => $d->trans_cost,
                "ticket_cost" => $calculation->booking_ticket_cost,
                "processing_fees" => $d->pro_fees,
                "platform_fees" => $d->plt_fees,
                "operation" => $d->operation,
                "tourists" => $d->tourists,
                "discount" =>  $calculation->booking_discount,
                "gross_revenue" => $calculation->booking_gross,
                "net_revenue" =>  sprintf("%.2f", ($calculation->booking_gross - $calculation->booking_refund)),
                "refund_total" => $calculation->booking_refund
            ];

            // collect the revenue totals into our collection
            $collection[] = ($updated);

            // add to the totals
            $calculation->total_gross += $calculation->booking_gross;
            $calculation->total_refund += $calculation->booking_refund;
            $calculation->total_refund_fees += $calculation->booking_refund_fees;
            $calculation->total_payment_fees += $calculation->booking_payment_fees;
            $calculation->total_ticket += $calculation->booking_ticket_cost;
            $calculation->total_tourists += $d->tourists;
        }

        // return the collection and totals as a associative array
        return [
            "data" => $collection,
            "count" => count($collection),
            "sums" => [
                "total_gross" => $calculation->total_gross,
                "total_refund" => $calculation->total_refund,
                "total_refund_fees" => $calculation->total_refund_fees,
                "total_pay_fees" => $calculation->total_payment_fees,
                "total_ticket" => $calculation->total_ticket,
                "total_tourists" => $calculation->total_tourists
            ]
        ];
    }

    /**
     * Return the OTA [TripAdvisor | GetYourGuide] Booking Report as a CSV file.
     * @param string $name file name
     * @param array $data revenune report query response
     * @return (/file) stream bytes
     */
    public function otaReportToCsv(string $name, array $data, int $tourOperatorId = 0, int $tourPackageId = 0, string $fromDate, string $toDate)
    {
        $columns = csvColumns('ota_report');
        $topColumns = csvColumns('ota_report_top_columns');
        $tourOperatorDetails = $this->GetTourOperatorName($tourOperatorId, $tourPackageId);

        $callback = function () use ($data, $columns, $topColumns, $tourOperatorDetails, $fromDate, $toDate) {
            $file = fopen('php://output', 'w');
            $emptyRow = array_fill(0, 5, '');

            // Add Tour Operator & Package info
            if (!empty($tourOperatorDetails)) {
                if (!empty($tourOperatorDetails['tourOperatorName'])) {
                    fputcsv($file, ['Tour Operator:', $tourOperatorDetails['tourOperatorName'], '', '', '']);
                }
                if (!empty($tourOperatorDetails['tourPackageName'])) {
                    fputcsv($file, ['Tour Package:', $tourOperatorDetails['tourPackageName'], '', '', '']);
                }
                fputcsv($file, $emptyRow); // Add a blank row for spacing
            }

            // Add header and summary
            fputcsv($file, $topColumns);

            $dateRange   = date("m-d-Y", strtotime($fromDate)) . ' - ' . date("m-d-Y", strtotime($toDate));
            $createdDate = date("m-d-Y");
            $totalGross = (float)($data['sums']['total_gross'] ?? 0);
            $totalRefund = (float)($data['sums']['total_refund'] ?? 0);
            $totalTourists = (int)($data['sums']['total_tourists'] ?? 0);
            $totalNet = number_format($totalGross - $totalRefund, 2);

            fputcsv($file, [
                $dateRange,
                $createdDate,
                $totalTourists,
                number_format($totalGross, 2),
                number_format($totalRefund, 2),
                $totalNet,
            ]);
            fputcsv($file, ['']); // Spacer row

            // Data Table Headers
            fputcsv($file, $columns);

            // Data Rows
            foreach ($data['data'] as $row) {
                fputcsv($file, [
                    $row['booking_id'] ?? '',
                    $row['reference_id'] ?? '',
                    $row['booking_date'] ?? '',
                    $row['tour_date'] ?? '',
                    $row['tour_time'] ?? '',
                    $row['tourists'] ?? '',
                    $row['trans_type'] ?? '',
                    $row['status'] ?? '',
                    $row['gross_revenue'] ?? '',
                    $row['refund_total'] ?? '',
                    $row['net_revenue'] ?? '',
                ]);
            }

            // Summary Footer
            fputcsv($file, array_merge(array_fill(0, 9, ''), ['Total Net:', $totalNet]));

            fclose($file);
        };

        return response()->stream($callback, 200, csvHeaders($name));
    }

    public function stripeRefundReport(Request $request)
    {

        // $dates = DateUtils::startEndDateDefaults($request->fromDate, $request->toDate);
        // $params = ['limit' => 100, 'created'=> [ 'gte' => strtotime($dates['start_date']), 'lte'=> strtotime($dates['end_date'])]];
        // $refunds = $stripe->refunds->all($params, []);
        //$this->stripe->customers->retrieve($customerId, $params, $opts );
        // foreach ($refunds->autoPagingIterator() as $refund) {
        //     $results[] = $refund;
        // }

        // if (userType() == "Tour Operator") {
        //     $tourOperatorId = auth()->user()->tour_operator_id;
        // } else {
        //     $tourOperatorId = $request->tourOperatorId;
        // }

        // $tourOpt = TourOperator::find($tourOperatorId);
        // $opts =  ['stripe_account' => $tourOpt->stripe_destination_id];

        /*
                         if( !empty($pi)  ) {
                    $intents[] = $pi;

                    if( isset($pi->charges) && isset( $pi->charges->data) ){
                        foreach( $pi->charges->data  as $charge ){
                            $charge_result = $stripe->charges->retrieve( $charge->id,[]);
                            $charges[] = $charge_result;
                            $chargeIds[] = $charge->id;
                        }
                    }
                }
         */


        $intents = [];
        $intentIds = [];
        $booking = TourBooking::find($request->bookingId);
        $transactions = Transaction::where("tour_booking_id", "=",  $request->bookingId)->get();
        $history = TourBookingHistory::where("tour_booking_id", "=", $request->bookingId)->get();
        $query = "metadata['booking_id']:'$request->bookingId'";
        $searchParams = ['query' => $query];
        $searchResult = app(PaymentGatewayInterface::class)->searchPayments($searchParams);

        foreach ($transactions as $transaction) {
            if ($transaction->transaction_number && !empty($transaction->transaction_number)) {

                $id = $transaction->transaction_number;

                if (!in_array($id, $intentIds)) {
                    $intents[] = app(PaymentGatewayInterface::class)->retrievePayment($id);
                    $intentIds[] = $id;
                }
            }
        }

        return [
            "intentIds" => $intentIds,
            "history" => $history,
            "payment_intents" => $intents,
            "transactions" => $transactions,
            "query" => $query,
            "search_result" => $searchResult,
            "booking" => $booking
        ];
    }

    /**
     * staffBookingReport
     * Return bookings made by staff members
     * @return (Array,Strem) sql raw restult
     */
    public function staffBookingReport($request, string $fromDate = "", string $toDate = "", int $tourOperatorId = 0, int $tourPackageId = 0, int $export = 0)
    {
        try {
            ini_set('memory_limit', 160217728);

            $userType = userType();
            $tourOperatorId = ($userType == "Tour Operator") ? auth()->user()->tour_operator_id : $tourOperatorId;

            $bookingSql = 'SELECT
                `tbm`.`id` as `tour_booking_id`,
                `tbm`.`status` as `status`,
                `tbm`.`comments`,
                `tbm`.`created_by` as `createdBy`,
                `tbm`.`created_by_user_type` as `tour_user_type`,
                `tbm`.`created_by`,
                `tbm`.`booking_total` as `booking_amount`,
                `tbm`.`name` as `customer`,
                `tbm`.`email`,
                `tbm`.`phone_number`,
                `tst`.`time_from`,
                `tst`.`time_to`,
                `tsd`.`date`,
                CASE `tbm`.`created_by_user_type`
                    WHEN "Admin" THEN "Admin"
                    ELSE CONCAT(
                        `tos`.`first_name`, " ", `tos`.`last_name`
                    )
                END as "tour_operator_staff_name",
                DATE_FORMAT(`tbm`.`created_at`, "%Y-%m-%d") as `created_at`
            FROM
                `tour_bookings_master` AS `tbm`
                INNER JOIN `tour_slot_times` as `tst` on `tst`.`id` = `tbm`.`tour_slot_id`
                INNER JOIN `tour_slot_dates` as `tsd` on `tsd`.`id` = `tst`.`tour_slot_id`
                INNER JOIN `tour_packages` as `tp` on `tp`.`id` = `tbm`.`tour_package_id`
                INNER JOIN `tour_operator_staff` as `tos` ON `tos`.`id` = `tbm`.`created_by`';

            $bookingSql .= ' and `tbm`.`created_at` between "' . $fromDate . ' 00:00:00" and "' . $toDate . ' 23:59:59"';

            $bookingSql .= ' and `tbm`.`created_by_user_type` = "Tour Operator" ';

            if ($tourPackageId) {
                $bookingSql .= ' and `tp`.`id` = "' . $tourPackageId . '" ';
            }

            $bookingSql .= ' and `tp`.`tour_operator_id` = "' . $tourOperatorId . '" ';

            $bookingSql .= " ORDER BY `tbm`.`id` DESC;";

            $staffBookingData = DB::select($bookingSql);

            $paymentData = array();
            $totalPayment = 0;
            $result = [];

            foreach ($staffBookingData as $row) {
                if (isset($paymentData[$row->tour_operator_staff_name][$row->tour_booking_id])) {
                    $paymentData[$row->tour_operator_staff_name][$row->tour_booking_id]->booking_amount += $row->booking_amount;
                } else {
                    $paymentData[$row->tour_operator_staff_name][$row->tour_booking_id] = $row;
                }
            }

            foreach ($paymentData as $key => $payment) {
                foreach ($payment as $k => $py) {
                    $payment[$k]->time_from = date("g:i: A", strtotime($py->time_from));
                }
                $result['data'][] = [
                    'name' => $key,
                    'data' => array_values($payment)
                ];
            }

            if ($export) {
                // return csv byte stream if export
                $name = 'staff_booking_report_' . $fromDate . '_' . $toDate . '.csv';
                return $this->staffBookingReportToCsv($name, $result, $tourOperatorId, $tourPackageId, $fromDate, $toDate);
            }

            return $result;
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Return the Staff Booking Report as a CSV file.
     * @param string $name file name
     * @param array $data staff booking report query response
     * @return (/file) stream bytes
     */
    public function staffBookingReportToCsv(string $name, array $data, int $tourOperatorId = 0, int $tourPackageId = 0, string $fromDate, string $toDate)
    {
        $columns = csvColumns('staff_booking_report');
        $columnsHeader = csvColumns('staff_booking_report_columnsHeader');
        $tourOperatorName = $this->GetTourOperatorName($tourOperatorId, $tourPackageId);

        $callback = function () use ($data, $columns, $columnsHeader, $tourOperatorName, $fromDate, $toDate) {
            $file = fopen('php://output', 'w');

            if (!empty($tourOperatorName)) {
                $blankLine = '';
                if ($tourOperatorName['tourOperatorName']) {
                    $tourOperatorNameRow = array('Tour Operator:', $tourOperatorName['tourOperatorName'], $blankLine, $blankLine, $blankLine);
                    fputcsv($file, $tourOperatorNameRow);
                }
                if ($tourOperatorName['tourPackageName']) {
                    $tourPackageNameRow = array('Tour Package:', $tourOperatorName['tourPackageName'], $blankLine, $blankLine, $blankLine);
                    fputcsv($file, $tourPackageNameRow);
                }
                fputcsv($file, array_fill(0, 5, $blankLine));
            }

            $dateRange = date("m-d-Y", strtotime($fromDate)) . ' - ' . date("m-d-Y", strtotime($toDate));
            $createdDate = date("m-d-Y");

            fputcsv($file, array($dateRange, $createdDate));
            $blankLine = '';
            fputcsv($file, array($blankLine));

            if (isset($data['data'])) {
                foreach ($data['data'] as $key => $value) {
                    if (isset($value['name'])) {
                        fputcsv($file, $columnsHeader);

                        $row['tourOperatorStaffName'] = (isset($value['name'])) ? $value['name'] : '';
                        $row['totalBookings'] = (isset($value['data'])) ? count($value['data']) : 0;

                        fputcsv($file, array(
                            $row['tourOperatorStaffName'],
                            $row['totalBookings']
                        ));

                        fputcsv($file, $columns);

                        $arrayData = $value['data'] ?? [];
                        $counter = 1;

                        foreach ($arrayData as $eachData) {
                            $row['Sr No'] = $counter;
                            $row['Booking Id'] = (isset($eachData->tour_booking_id)) ? $eachData->tour_booking_id : '';
                            $row['Booking Date'] = (isset($eachData->date)) ? date("m-d-Y", strtotime($eachData->date)) : '';
                            $row['Created Date'] = (isset($eachData->created_at)) ? date("m-d-Y", strtotime($eachData->created_at)) : '';
                            $row['Tour Time'] = (isset($eachData->time_from)) ? " @ " . date("g:i A", strtotime($eachData->time_from)) : '';
                            $row['Booking Amount'] = (isset($eachData->booking_amount)) ? $eachData->booking_amount : '';
                            $row['Customer'] = (isset($eachData->customer)) ? $eachData->customer : '';
                            $row['Status'] = (isset($eachData->status)) ? $eachData->status : '';

                            fputcsv($file, array(
                                $row['Sr No'],
                                $row['Booking Id'],
                                $row['Booking Date'],
                                $row['Created Date'],
                                $row['Tour Time'],
                                $row['Booking Amount'],
                                $row['Customer'],
                                $row['Status']
                            ));
                            $counter++;
                        }

                        fputcsv($file, array(
                            $blankLine,
                            $blankLine,
                            $blankLine,
                            $blankLine,
                            $blankLine,
                            $blankLine,
                            $blankLine,
                            $blankLine,
                            $blankLine
                        ));
                    }
                }
            }
            fclose($file);
        };
        return response()->stream($callback, 200, csvHeaders($name));
    }

    /**
     * Transaction Report
     *
     * @param Request $request
     */
    public function transactionReport($request)
    {
        try {
            $viaArray = viaArray($request);
            $fromDate = $request->dateFrom . " 00:00:00";
            $toDate = $request->dateTo . " 23:59:59";

            $query = DB::table('transactions as tra')
                ->join('tour_bookings_master as tbm', 'tbm.id',  '=', 'tra.tour_booking_id')
                ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
                ->whereIn('tra.via', $viaArray)
                ->where('tra.status', '!=', 'Failed')
                ->select('tra.amount', 'tra.fee_amount', 'tra.via', 'tra.status', 'tra.tour_booking_id', 'tbm.tour_package_id', 'tbm.discount2_value as discount', 'tbm.status as booking_status', 'tra.created_at', 'tst.date', 'tst.time_from');

            $viewDate = $request->viewDate == "purchasedate" ? 'tra.created_at' : 'tst.date';
            $query = $query->whereBetween($viewDate, [$fromDate, $toDate]);

            if ($request->selectedPackage || $request->tourPackage) {
                $query = $query->where('tbm.tour_package_id', $request->selectedPackage ? $request->selectedPackage : $request->tourPackage);
            }

            if ($request->checkCancel == "true") {
                $query = $query->where('tbm.status', '!=', 'Cancelled');
            }

            if ($request->excludeExternal == "true") {
                $query = $query->where('tbm.comments', 'not like', '#%');
            }

            if ($request->onlyShowExternal == "true") {
                $query = $query->where('tbm.comments', 'like', '#%');
            }

            if ($request->includeExternalWithFee == "true") {
                $query = $query->where('tra.request_from', '!=', 'Insert');
            }

            if (isset($request->search['value']) && !empty($request->search['value'])) {
                $query = $query->where('tra.tour_booking_id', 'LIKE', '%' . $request->search['value'] . '%');
            }

            $query = $query->orderBy($viewDate, 'ASC')->get();

            $totalRefund = 0;
            $totalPayment = 0;
            $totalFees = 0;
            $totalNet = 0;
            $totalDiscount = 0;
            $sortedDataArray = array();
            foreach ($query as $key => $value) {
                $newFilteredData = array();
                if (isset($sortedDataArray[$value->tour_booking_id])) {
                    if ($value->status == "Completed") {
                        $sortedDataArray[$value->tour_booking_id]['amount'] = ($sortedDataArray[$value->tour_booking_id]['amount'] + $value->amount);
                        $sortedDataArray[$value->tour_booking_id]['fee_amount'] = ($sortedDataArray[$value->tour_booking_id]['fee_amount'] + $value->fee_amount);
                    }

                    if ($value->status == "Refund") {
                        $sortedDataArray[$value->tour_booking_id]['refund'] = ($sortedDataArray[$value->tour_booking_id]['refund'] + $value->amount);
                        $sortedDataArray[$value->tour_booking_id]['refund_fee_amount'] = ($sortedDataArray[$value->tour_booking_id]['refund_fee_amount'] + $value->fee_amount);
                        $sortedDataArray[$value->tour_booking_id]['refund_remove_fee'] = $sortedDataArray[$value->tour_booking_id]['refund'] - $sortedDataArray[$value->tour_booking_id]['refund_fee_amount'];
                    }

                    $sortedDataArray[$value->tour_booking_id]['net_total'] = number_format((float)$sortedDataArray[$value->tour_booking_id]['amount'] - $sortedDataArray[$value->tour_booking_id]['refund_remove_fee'] - $sortedDataArray[$value->tour_booking_id]['fee_amount'], 2, '.', '');
                    $sortedDataArray[$value->tour_booking_id]['net_total'] = roundout($sortedDataArray[$value->tour_booking_id]['net_total'], 2);
                } else {
                    $newFilteredData['bookingId'] = $value->tour_booking_id;
                    $newFilteredData['tourPackageId'] = $value->tour_package_id;
                    $newFilteredData['discount'] = $value->discount;
                    $newFilteredData['booking_status'] = $value->booking_status;
                    $newFilteredData['date'] = date('m/d/Y', strtotime($value->date));
                    $newFilteredData['time_from'] = $value->time_from;

                    if ($value->status == "Completed") {
                        $newFilteredData['amount'] = $value->amount;
                        $newFilteredData['fee_amount'] = $value->fee_amount;
                    } else {
                        $newFilteredData['amount'] = 0;
                        $newFilteredData['fee_amount'] = 0;
                    }

                    if ($value->status == "Refund") {
                        $newFilteredData['refund'] = $value->amount;
                        $newFilteredData['refund_fee_amount'] = $value->fee_amount;
                        $newFilteredData['refund_remove_fee'] = $newFilteredData['refund'] - $newFilteredData['refund_fee_amount'];
                    } else {
                        $newFilteredData['refund'] = 0;
                        $newFilteredData['refund_fee_amount'] = 0;
                        $newFilteredData['refund_remove_fee'] = 0;
                    }

                    $newFilteredData['net_total'] = number_format((float)$newFilteredData['amount'] - $newFilteredData['refund_remove_fee'] - $newFilteredData['fee_amount'], 2, '.', '');
                    $newFilteredData['net_total'] = roundout($newFilteredData['net_total'], 2);
                    $sortedDataArray[$value->tour_booking_id] = $newFilteredData;
                }
            }
            //echo json_encode($sortedDataArray);exit;

            $totalBookings = count($sortedDataArray);
            foreach ($sortedDataArray as $value) {
                $totalPayment += $value['amount'];
                $totalFees += $value['fee_amount'];
                $totalRefund += $value['refund']; // $value['refund_remove_fee'];
                $totalNet += $value['net_total'];
                $totalDiscount += $value['discount'];
            }

            if ($request->export) {
                return $this->exportTransactionReport($request, $sortedDataArray, $totalPayment, $totalRefund, $totalFees, $totalNet, $totalDiscount, $request->tourOperatorId, $request->tourPackageId, $request->dateFrom, $request->dateTo, $totalBookings);
            }

            if (isset($request->search['value']) && !empty($request->search['value'])) {
                $search = $sortedDataArray;
            } else {
                $search = null;
            }

            $getData = Datatables::of($sortedDataArray)
                ->with('totalPayment', $totalPayment)
                ->with('totalRefund', $totalRefund)
                ->with('totalFees', $totalFees)
                ->with('totalNet', $totalNet)
                ->with('totalDiscount', $totalDiscount)
                ->with('search', $search)
                ->with('totalBookings', $totalBookings)
                ->addIndexColumn()
                ->make(true)
                ->getData();

            $searchData = [];
            if (isset($request->search['value']) && !empty($request->search['value'])) {
                foreach ($getData->search as $key => $value) {
                    $searchData[] = $value;
                }

                $getData->data = $searchData;
            }

            return $getData;
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Return the Transaction Report as a CSV file.
     * @param array $sortedDataArray
     * @return (/file) stream bytes
     */
    public function exportTransactionReport($request, $sortedDataArray, $totalPayment, $totalRefund, $totalFees, $totalNet, $totalDiscount, $tourOperatorId, $tourPackageId, $fromDate, $toDate, $totalBookings)
    {
        $columns = csvColumns('transaction_report');
        $columnsHeader = csvColumns('transaction_report_columnsHeader');
        $tourOperatorName = $this->GetTourOperatorName($tourOperatorId, $tourPackageId);
        $name = 'transaction_report_' . $fromDate . '_' . $toDate . '.csv';

        $callback = function () use ($sortedDataArray, $columns, $columnsHeader, $tourOperatorName, $totalPayment, $totalRefund, $totalFees, $totalDiscount, $totalNet, $totalBookings) {
            $file = fopen('php://output', 'w');
            if (!empty($tourOperatorName)) {
                $BlankLine = '';
                if ($tourOperatorName['tourOperatorName']) {
                    $tourOperatorNameRow = array('Tour Operator:', $tourOperatorName['tourOperatorName'], $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourOperatorNameRow);
                }

                if ($tourOperatorName['tourPackageName']) {
                    $tourPackageNameRow = array('Tour Package:', $tourOperatorName['tourPackageName'], $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourPackageNameRow);
                }

                fputcsv($file, array_fill(0, 5, $BlankLine));
            }

            fputcsv($file, $columnsHeader);

            $totalPayment = sprintf('%.2f', $totalPayment);
            $totalRefund = sprintf('%.2f', $totalRefund);
            $totalFees = sprintf('%.2f', $totalFees);
            $totalDiscount = sprintf('%.2f', $totalDiscount);
            $totalNet = sprintf('%.2f', $totalNet);
            fputcsv($file, array($totalPayment, $totalBookings, $totalRefund, $totalFees, $totalDiscount, $totalNet));
            $BlankLine = '';
            fputcsv($file, array($BlankLine));

            fputcsv($file, $columns);
            foreach ($sortedDataArray as $key => $val) {
                $row = array(
                    $val['bookingId'],
                    $val['date'],
                    $val['booking_status'],
                    $val['amount'],
                    $val['discount'],
                    $val['refund'],
                    $val['net_total']
                );

                fputcsv($file, $row);
            }

            fputcsv($file, array_merge(array_fill(0, 12, $BlankLine)));
            fclose($file);
        };

        return response()->stream($callback, 200, csvHeaders($name));
    }

    /**
     * Transaction Summary Report
     *
     * @param Request $request
     */
    public function transactionSummaryReport($request)
    {
        try {
            $viaArray = viaArray($request);
            $fromDate = $request->dateFrom . " 00:00:00";
            $toDate = $request->dateTo . " 23:59:59";

            // Check if we need to show only unpaid bookings
            $showUnpaidOnly = isset($request->checkUnpaid) && $request->checkUnpaid == "true";

            if ($showUnpaidOnly) {
                // Query for unpaid bookings (bookings without transactions)
                $query = DB::table('tour_bookings_master as tbm')
                    ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
                    ->leftJoin('transactions as tra', 'tra.tour_booking_id', '=', 'tbm.id')
                    ->whereNull('tra.id') // Only bookings without transactions
                    ->select(
                        DB::raw('0 as fee_amount'),
                        DB::raw('"Unpaid" as via'),
                        DB::raw('"Unpaid" as status'),
                        'tbm.id as tour_booking_id',
                        'tbm.tour_package_id',
                        'tbm.discount2_value as discount',
                        DB::raw('DATE_FORMAT(tbm.created_at, "%Y-%m-%d") as payment_date'),
                        'tbm.name as customer_name',
                        'tbm.infants',
                        'tbm.tourists',
                        'tbm.status as booking_status',
                        'tbm.tour_slot_id',
                        'tst.date',
                        'tst.time_from',
                        'tbm.total'
                    );
            } else {
                // Original query for paid bookings (bookings with transactions)
                $query = DB::table('transactions as tra')
                    ->join('tour_bookings_master as tbm', 'tbm.id',  '=', 'tra.tour_booking_id')
                    ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
                    ->whereIn('tra.via', $viaArray)
                    ->where('tra.status', '!=', 'Failed')
                    ->select('tra.amount', 'tra.fee_amount', 'tra.via', 'tra.status', 'tra.tour_booking_id', 'tbm.tour_package_id', 'tbm.discount2_value as discount', DB::raw('DATE_FORMAT(tra.created_at, "%Y-%m-%d") as payment_date'), 'tbm.name as customer_name', 'tbm.infants', 'tbm.tourists', 'tbm.status as booking_status', 'tbm.tour_slot_id', 'tst.date', 'tst.time_from');
            }

            $viewDate = $request->viewDate == "purchasedate" ? 'tra.created_at' : 'tst.date';

            if ($viewDate === 'tst.date' && !$showUnpaidOnly) {
                // Push the date range onto tbm.tour_slot_id (indexed) so MySQL can
                // find the relevant bookings first instead of scanning all transactions.
                $slotSubquery = DB::table('tour_slot_times')
                    ->whereBetween('date', [$fromDate, $toDate])
                    ->select('id');
                $query->whereIn('tbm.tour_slot_id', $slotSubquery);
            }

            $query = $query->whereBetween($viewDate, [$fromDate, $toDate]);

            if ($request->selectedPackage || $request->tourPackage) {
                $query = $query->where('tbm.tour_package_id', $request->selectedPackage ? $request->selectedPackage : $request->tourPackage);
            }

            if ($request->checkCancel == "true") {
                $query = $query->where('tbm.status', '!=', 'Cancelled');
            }

            if ($request->excludeExternal == "true") {
                $query = $query->where('tbm.comments', 'not like', '#%');
            }

            if ($request->onlyShowExternal == "true") {
                $query = $query->where('tbm.comments', 'like', '#%');
            }

            if ($request->includeExternalWithFee == "true" && !$showUnpaidOnly) {
                $query = $query->where('tra.request_from', '!=', 'Insert');
            }

            if (isset($request->search['value']) && !empty($request->search['value'])) {
                $query = $query->where('tbm.id', 'LIKE', '%' . $request->search['value'] . '%');
            }

            $query = $query->orderBy($viewDate, 'ASC');
            $query = $query->get();

            $totalRefund = 0;
            $totalPayment = 0;
            $totalFees = 0;
            $totalNet = 0;
            $totalDiscount = 0;
            $sortedDataArray = array();
            $totalTourist = 0;
            $tourSlotId = [];
            foreach ($query as $key => $value) {
                $newFilteredData = array();
                if ($value->booking_status != "Cancelled") {
                    $tourSlotId[] = $value->tour_slot_id;
                }

                if (isset($sortedDataArray[$value->tour_booking_id])) {
                    if ($value->status == "Completed") {
                        $sortedDataArray[$value->tour_booking_id]['amount'] = ($sortedDataArray[$value->tour_booking_id]['amount'] + $value->amount);
                        $sortedDataArray[$value->tour_booking_id]['fee_amount'] = ($sortedDataArray[$value->tour_booking_id]['fee_amount'] + $value->fee_amount);
                    }

                    if ($value->status == "Refund") {
                        $sortedDataArray[$value->tour_booking_id]['refund'] = ($sortedDataArray[$value->tour_booking_id]['refund'] + $value->amount);
                        $sortedDataArray[$value->tour_booking_id]['refund_fee_amount'] = ($sortedDataArray[$value->tour_booking_id]['refund_fee_amount'] + $value->fee_amount);
                        $sortedDataArray[$value->tour_booking_id]['refund_remove_fee'] = $sortedDataArray[$value->tour_booking_id]['refund'] - $sortedDataArray[$value->tour_booking_id]['refund_fee_amount'];
                    }

                    $sortedDataArray[$value->tour_booking_id]['net_total'] = number_format((float)$sortedDataArray[$value->tour_booking_id]['amount'] - $sortedDataArray[$value->tour_booking_id]['refund_remove_fee'] - $sortedDataArray[$value->tour_booking_id]['fee_amount'], 2, '.', '');
                    $sortedDataArray[$value->tour_booking_id]['net_total'] = roundout($sortedDataArray[$value->tour_booking_id]['net_total'], 2);
                } else {
                    $newFilteredData['bookingId'] = $value->tour_booking_id;
                    $newFilteredData['discount'] = $value->discount;
                    $newFilteredData['payment_date'] = date('m/d/Y', strtotime($value->payment_date));
                    $newFilteredData['customer_name'] = $value->customer_name;
                    $newFilteredData['tourists'] = $value->tourists + $value->infants;
                    $newFilteredData['purchase_type'] = $value->via;
                    $newFilteredData['booking_status'] = $value->booking_status;
                    $newFilteredData['date'] = date('m/d/Y', strtotime($value->date));
                    $newFilteredData['time_from'] = $value->time_from;

                    if ($showUnpaidOnly) {
                        // For unpaid bookings, show potential financial values
                        $grossAmount = $value->total ?? 0;

                        $newFilteredData['amount'] = $grossAmount; // Show gross amount in amount field
                        $newFilteredData['fee_amount'] = 0; // No fees for unpaid
                        $newFilteredData['refund'] = 0; // No refunds for unpaid
                        $newFilteredData['refund_fee_amount'] = 0;
                        $newFilteredData['refund_remove_fee'] = 0;
                        $newFilteredData['net_total'] = $grossAmount;
                    } else {
                        if ($value->status == "Completed") {
                            $newFilteredData['amount'] = $value->amount;
                            $newFilteredData['fee_amount'] = $value->fee_amount;
                        } else {
                            $newFilteredData['amount'] = 0;
                            $newFilteredData['fee_amount'] = 0;
                        }

                        if ($value->status == "Refund") {
                            $newFilteredData['refund'] = $value->amount;
                            $newFilteredData['refund_fee_amount'] = $value->fee_amount;
                            $newFilteredData['refund_remove_fee'] = $newFilteredData['refund'] - $newFilteredData['refund_fee_amount'];
                        } else {
                            $newFilteredData['refund'] = 0;
                            $newFilteredData['refund_fee_amount'] = 0;
                            $newFilteredData['refund_remove_fee'] = 0;
                        }

                        $newFilteredData['net_total'] = number_format((float)$newFilteredData['amount'] - $newFilteredData['refund_remove_fee'] - $newFilteredData['fee_amount'], 2, '.', '');
                        $newFilteredData['net_total'] = roundout($newFilteredData['net_total'], 2);
                    }

                    $sortedDataArray[$value->tour_booking_id] = $newFilteredData;
                }
            }

            foreach ($sortedDataArray as $key => $value) {
                $sortedDataArray[$key]['tax'] = 0;
                $totalPayment += $showUnpaidOnly ? 0 : $value['amount'];
                $totalFees += $value['fee_amount'];
                $totalRefund += $value['refund']; // $value['refund_remove_fee'];
                $totalNet += $value['net_total'];
                $totalDiscount += $value['discount'];
                $totalTourist += $value['tourists'];
            }

            $uniqueTourSlotId = array_unique($tourSlotId);
            $countTourSlotId = count($uniqueTourSlotId);

            if ($request->export) {
                return $this->exportTransactionSummaryReport($request, $sortedDataArray, $totalPayment, $totalRefund, $totalFees, $totalNet, $totalDiscount, $totalTourist, $countTourSlotId, $request->tourOperatorId, $request->tourPackageId, $request->dateFrom, $request->dateTo);
            }

            if (isset($request->search['value']) && !empty($request->search['value'])) {
                $search = $sortedDataArray;
            } else {
                $search = null;
            }

            $getData = Datatables::of($sortedDataArray)
                ->with('totalPayment', $totalPayment)
                ->with('totalRefund', $totalRefund)
                ->with('totalFees', $totalFees)
                ->with('totalNet', $totalNet)
                ->with('totalDiscount', $totalDiscount)
                ->with('search', $search)
                ->with('totalTourist', $totalTourist)
                ->with('countTourSlotId', $countTourSlotId)
                ->addIndexColumn()
                ->make(true)
                ->getData();

            $searchData = [];
            if (isset($request->search['value']) && !empty($request->search['value'])) {
                foreach ($getData->search as $key => $value) {
                    $searchData[] = $value;
                }

                $getData->data = $searchData;
            }

            return $getData;
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Return the Transaction Summary Report as a CSV file.
     * @param array $sortedDataArray
     * @return (/file) stream bytes
     */

    public function exportTransactionSummaryReport($request, $sortedDataArray, $totalPayment, $totalRefund, $totalFees, $totalNet, $totalDiscount, $totalTourist, $countTourSlotId, $tourOperatorId, $tourPackageId, $fromDate, $toDate)
    {

        $columns = csvColumns('transaction_summary_report');
        $columnsHeader = csvColumns('transaction_summary_report_columnsHeader');
        $tourOperatorName = $this->GetTourOperatorName($tourOperatorId, $tourPackageId);
        $name = 'transaction_summary_report_' . $fromDate . '_' . $toDate . '.csv';

        $callback = function () use ($sortedDataArray, $columns, $columnsHeader, $tourOperatorName, $fromDate, $toDate, $totalPayment, $totalRefund, $totalFees, $totalDiscount, $totalTourist, $totalNet, $countTourSlotId) {
            $file = fopen('php://output', 'w');
            if (!empty($tourOperatorName)) {
                $BlankLine = '';
                if ($tourOperatorName['tourOperatorName']) {
                    $tourOperatorNameRow = array('Tour Operator:', $tourOperatorName['tourOperatorName'], $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourOperatorNameRow);
                }
                if ($tourOperatorName['tourPackageName']) {
                    $tourPackageNameRow = array('Tour Package:', $tourOperatorName['tourPackageName'], $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourPackageNameRow);
                }
                fputcsv($file, array_fill(0, 5, $BlankLine));
            }
            fputcsv($file, $columnsHeader);

            $totalTourist = sprintf('%.2f', $totalTourist);
            $totalPayment = sprintf('%.2f', $totalPayment);
            $totalRefund = sprintf('%.2f', $totalRefund);
            $totalFees = sprintf('%.2f', $totalFees);
            $totalDiscount = sprintf('%.2f', $totalDiscount);
            $totalNet = sprintf('%.2f', $totalNet);
            fputcsv($file, array($totalTourist, $totalPayment, $totalRefund, $totalFees, $totalDiscount, $totalNet));
            $BlankLine = '';
            fputcsv($file, array($BlankLine));

            fputcsv($file, $columns);
            foreach ($sortedDataArray as $key => $val) {
                $row = array(
                    $val['date'],
                    $val['payment_date'],
                    $val['bookingId'],
                    $val['booking_status'],
                    $val['customer_name'],
                    $val['tourists'],
                    $val['purchase_type'],
                    $val['amount'],
                    $val['discount'],
                    $val['fee_amount'],
                    $val['refund'],
                    $val['net_total']
                );

                fputcsv($file, $row);
            }

            fputcsv($file, array_merge(array_fill(0, 12, $BlankLine)));
            fclose($file);
        };

        return response()->stream($callback, 200, csvHeaders($name));
    }

    // Affiliate Seats Report
    public function affiliateSeatsReport(Request $request)
    {
        try {

            $fromDate = $request->dateFrom;
            $toDate = $request->dateTo;

            $sub = DB::table('customers as c')
                ->join('package_affiliate_assignments as paa', 'paa.affiliate_id', '=', 'c.id')
                ->join('tour_packages as tp', 'tp.id', '=', 'paa.package_id')
                ->join('affiliate_slot_times as ast', 'ast.affiliate_id', '=', 'c.id')
                ->where('paa.deleted_by', null)
                ->where('paa.show_all_package_bookings', 0)
                ->where('ast.deleted_by', 0)
                ->select(
                    'ast.affiliate_id',
                    'c.name',
                    'tp.id as package_id',
                    'tp.name as package_name',
                    DB::raw("IFNULL(
            (SELECT SUM(ast2.seat_limit)
             FROM affiliate_slot_times ast2
             JOIN tour_slot_times_affiliate tsta ON ast2.tour_slot_time_id = tsta.id
             WHERE tsta.tour_package_id = tp.id
             AND ast2.affiliate_id = c.id
             AND ast2.deleted_by = 0
             AND tsta.deleted_by = 0
            ), 0
        ) AS assigned_seats")
                )
                ->groupBy('ast.affiliate_id')
                ->groupBy('paa.package_id');

            if ($request->customerId != 0) {
                $sub = $sub->where('c.id', $request->customerId);
            }

            if ($request->selectedPackage != '') {
                $sub = $sub->where('tp.id', $request->selectedPackage);
            }

            $data = $sub->get();

            $qry = DB::table('affilaite_seats_updates AS asu')
                ->join('tour_packages as tp', 'tp.id', '=', 'asu.tour_package_id')
                ->join('affiliate_slot_times as ast', 'ast.affiliate_id', '=', 'asu.customer_id')
                ->join('customers as c', 'c.id', '=', 'ast.affiliate_id')
                ->join('tour_slot_times as tst', 'tst.id', '=', 'asu.tour_slot_id')
                ->whereBetween('tst.date', [$fromDate, $toDate])
                ->select(
                    'tst.date',
                    'ast.affiliate_id',
                    'asu.tour_booking_id as booking_id',
                    'asu.tourists',
                    'asu.status',
                    'tp.name as package_name',
                    'tp.id as package_id',
                    'asu.check_in_2nd',
                    'asu.absent',
                    'asu.check_in_not',
                    'asu.check_in_1st'
                )
                ->orderBy('asu.tour_booking_id', 'ASC')
                ->groupBy('asu.tour_booking_id');

            if ($request->customerId != 0) {
                $qry = $qry->where('c.id', $request->customerId);
            }
            if ($request->selectedPackage != '') {
                $qry = $qry->where('tp.id', $request->selectedPackage);
            }
            $result = $qry->get();
            $result = $result->toArray();

            $startDate = strtotime($fromDate);
            $endDate = strtotime($toDate);
            $new_result = [];
            $affDataFin = [];
            $totalAssignedSeat = 0;
            $totalBookedSeat = 0;
            $totalCancelledSeat = 0;
            $totalNoShow = 0;
            $totalFulfilledSeats = 0;
            $totalCheckInNotSeats = 0;
            $dateRange = 'From ' . date('m-d-Y', $startDate) . ' To ' . date('m-d-Y', $endDate);
            while ($startDate <= $endDate) {
                $GLOBALS['nDate'] = date('Y-m-d', $startDate);

                foreach ($data as $value) {

                    $GLOBALS['gAffId'] = $value->affiliate_id;
                    $GLOBALS['gPackageId'] = $value->package_id;
                    $GLOBALS['gAffIdVal'] = 0;
                    $GLOBALS['gAffIdCanVal'] = 0;
                    $GLOBALS['gAffIdPackage'] = '';
                    $GLOBALS['gNoShow'] = 0;
                    $GLOBALS['gFulfilledSeats'] = 0;
                    $tempVal = $value;

                    $affdata = array_map('addAffiliateSelling', array_filter(array_map('checkDate1', $result), "filterAffiliateData"));

                    $affdataFinalVal = $GLOBALS['gAffIdVal'];
                    $affdataCanFinalVal = $GLOBALS['gAffIdCanVal'];
                    $affdataNoShowFinalVal = $GLOBALS['gNoShow'];
                    $affdataFulfilledSeatsFinalVal = $GLOBALS['gFulfilledSeats'];

                    $tempVal->date = $GLOBALS['nDate'];
                    $tempVal->booked_seats = $affdataFinalVal + $affdataCanFinalVal;
                    $tempVal->cancelled_seats = $affdataCanFinalVal;
                    $tempVal->no_show = $affdataNoShowFinalVal;
                    $tempVal->affiliate_name = $tempVal->name;
                    $tempVal->fulfilled_seats = $affdataFulfilledSeatsFinalVal;
                    $affDataFin[] = (array)$tempVal;

                    // Total Seat Count
                    $totalAssignedSeat += $tempVal->assigned_seats;
                    $totalBookedSeat += $tempVal->booked_seats;
                    $totalCancelledSeat += $tempVal->cancelled_seats;
                    $totalNoShow += $tempVal->no_show;
                    $totalFulfilledSeats += $tempVal->fulfilled_seats;
                }

                $startDate = strtotime('+1 day', $startDate);
            }

            $varRes = Datatables::of($affDataFin)
                ->addIndexColumn()
                ->editColumn('name', function ($data) {
                    return '</a> <i class="fa fa-plus-circle affiliate-seats-report" data-affiliate_id="' . $data['affiliate_id'] . '" data-date="' . $data['date'] . '" data-package_id="' . $data['package_id'] . '"  size="xs"></i> | ' . $data['name'] . '</a>';
                })
                ->rawColumns(['name'])
                ->with('totalAssignedSeat', $totalAssignedSeat)
                ->with('totalBookedSeat', $totalBookedSeat)
                ->with('totalCancelledSeat', $totalCancelledSeat)
                ->with('totalNoShow', $totalNoShow)
                ->with('totalFulfilledSeats', $totalFulfilledSeats)
                ->with('dateRange', $dateRange)
                ->addIndexColumn()
                ->make(true);

            $getData = $varRes->getData();

            if (isset($request->csv)) {
                return $this->AffiliateSeatsReportExportCsvFile($request, $affDataFin, $totalAssignedSeat, $totalBookedSeat, $totalCancelledSeat, $totalNoShow, $dateRange, $totalFulfilledSeats);
            }

            return $getData;
        } catch (Exception $exception) {
            Log::error($exception);
            return false;
        }
    }

    public function getAffiliateSeatsReportDetail($request)
    {
        try {

            $sub = DB::table('tour_slot_times_affiliate as tsta')
                ->join('affiliate_slot_times as ast', 'ast.tour_slot_time_id', '=', 'tsta.id')
                ->join('tour_packages as tp', 'tp.id', '=', 'tsta.tour_package_id')
                ->where('ast.affiliate_id', $request->affiliate_id)
                ->where('tp.id', $request->package_id)
                ->where('ast.deleted_at', null)
                ->select('ast.affiliate_id', 'ast.seat_limit as assigned_seats', 'tsta.time_from')
                ->groupBy('tsta.time_from');

            $data = $sub->get();

            $qry = DB::table('affilaite_seats_updates AS asu')
                ->join('tour_packages as tp', 'tp.id', '=', 'asu.tour_package_id')
                ->join('tour_slot_times as tst', 'tst.id', '=', 'asu.tour_slot_id')
                ->join('tour_slot_times_affiliate as tsta', 'tsta.time_from', '=', 'tst.time_from')
                ->whereDate('tst.date', $request->date)
                ->where('asu.customer_id', $request->affiliate_id)
                ->where('tsta.tour_package_id', $request->package_id)
                ->select(
                    'tst.time_from',
                    'tst.time_to',
                    'tst.date',
                    'asu.tour_booking_id as booking_id',
                    'asu.tourists',
                    'asu.status',
                    'tp.name as package_name',
                    'tp.id as package_id',
                    'asu.check_in_2nd',
                    'asu.absent',
                    'asu.check_in_not',
                    'asu.check_in_1st'
                )
                ->groupBy('tst.time_from')
                ->groupBy('asu.tour_booking_id');


            $result = $qry->get();
            $result = $result->toArray();

            $startDate = strtotime($request->date);
            $new_result = [];
            $affDataFin = [];

            $GLOBALS['nDate'] = date('Y-m-d', $startDate);
            foreach ($data as $value) {

                $GLOBALS['gTime_from'] = $value->time_from;
                $GLOBALS['gAffIdVal'] = 0;
                $GLOBALS['gAffIdCanVal'] = 0;
                $GLOBALS['gAffIdPackage'] = '';
                $GLOBALS['gNoShow'] = 0;
                $GLOBALS['gFulfilledSeats'] = 0;
                $tempVal = $value;

                $affdata = array_map('addAffiliateSelling', array_filter(array_map('checkDate1', $result), "filterAffiliateData"));
                $affdataFinalVal = $GLOBALS['gAffIdVal'];
                $affdataCanFinalVal = $GLOBALS['gAffIdCanVal'];
                $affdataNoShowFinalVal = $GLOBALS['gNoShow'];
                $affdataFulfilledSeatsFinalVal = $GLOBALS['gFulfilledSeats'];

                $tempVal->date = $GLOBALS['nDate'];
                $tempVal->booked_seats = $affdataFinalVal + $affdataCanFinalVal;
                $tempVal->cancelled_seats = $affdataCanFinalVal;
                $tempVal->no_show = $affdataNoShowFinalVal;
                $tempVal->fulfilled_seats = $affdataFulfilledSeatsFinalVal;
                $affDataFin[] = (array)$tempVal;
            }

            $sorted = collect($affDataFin)->sortBy('time_from');
            $sorted = $sorted->map(function ($object) {
                $object['time_from'] = date('g:i A', strtotime($object['time_from']));
                return $object;
            });
            $varRes = Datatables::of($sorted)
                ->addIndexColumn()
                ->make(true);

            $getData = $varRes->getData();

            return $getData;
        } catch (Exception $exception) {
            Log::error($exception);
            return false;
        }
    }

    public function AffiliateSeatsReportExportCsvFile($request, $affDataFin, $totalAssignedSeat, $totalBookedSeat, $totalCancelledSeat, $totalNoShow, $dateRange, $totalFulfilledSeats)
    {

        $columns = csvColumns('affiliate_seats_report');
        $columnsHeader = csvColumns('affiliate_seats_report_columnsHeader');
        $tourOperatorName = $this->GetTourOperatorName($request->selectedPackage, $request->selectedOperator);
        $name = 'affiliate_seats_report_' . $request->fromDate . '_' . $request->toDate . '.csv';

        $callback = function () use ($affDataFin, $columns, $columnsHeader, $tourOperatorName, $request, $totalAssignedSeat, $totalBookedSeat, $totalCancelledSeat, $totalNoShow, $dateRange, $totalFulfilledSeats) {
            $file = fopen('php://output', 'w');
            if (!empty($tourOperatorName)) {
                $BlankLine = '';
                if ($tourOperatorName['tourOperatorName']) {
                    $tourOperatorNameRow = array('Tour Operator:', $tourOperatorName['tourOperatorName'], $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourOperatorNameRow);
                }
                if ($tourOperatorName['tourPackageName']) {
                    $tourPackageNameRow = array('Tour Package:', $tourOperatorName['tourPackageName'], $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourPackageNameRow);
                }
                fputcsv($file, array_fill(0, 5, $BlankLine));
            }
            fputcsv($file, $columnsHeader);

            $dateRange = $dateRange;
            $totalAssignedSeat = sprintf('%.2f', $totalAssignedSeat);
            $totalBookedSeat = sprintf('%.2f', $totalBookedSeat);
            $totalCancelledSeat = sprintf('%.2f', $totalCancelledSeat);
            $totalFulfilledSeats = sprintf('%.2f', $totalFulfilledSeats);
            $totalNoShow = sprintf('%.2f', $totalNoShow);
            fputcsv($file, array($dateRange, $totalAssignedSeat, $totalBookedSeat, $totalCancelledSeat, $totalFulfilledSeats, $totalNoShow));
            $BlankLine = '';
            fputcsv($file, array($BlankLine));

            fputcsv($file, $columns);
            foreach ($affDataFin as $key => $val) {

                $row = array(
                    $val['affiliate_name'],
                    $val['package_name'],
                    $val['date'],
                    $val['assigned_seats'],
                    $val['booked_seats'],
                    $val['cancelled_seats'],
                    $val['fulfilled_seats'],
                    $val['no_show']
                );
                fputcsv($file, $row);
            }
            fputcsv($file, array_merge(array_fill(0, 12, $BlankLine)));
            fclose($file);
        };
        return response()->stream($callback, 200, csvHeaders($name));
    }

    public function affiliateSeatsSync()
    {
        try {
            $fromDate = date("Y-m-d", strtotime("yesterday"));
            $toDate = date('Y-m-d');
            $qry = DB::table('tour_bookings_transact as tbt')
                ->join('tour_bookings_master as tbm', 'tbm.id', '=', 'tbt.tour_booking_id')
                ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
                ->join('customers as c', 'c.id', '=', 'tbm.customer_id')
                ->where('c.is_affiliate', 'Yes')
                ->whereBetween('tbm.created_at', [$fromDate, $toDate])
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
                ->chunk(100, function ($data) {

                    dispatch(new UpdateAffiliateSeatsTable($data));
                });

            return true;
        } catch (Exception $exception) {
            Log::error($exception);
            return false;
        }
    }

    public function addonsPackageReport($request)
    {
        try {
            [$fromDate, $toDate, $dateRange] = $this->addonsReportDateWindow($request);

            $newRows = $this->addonsPackageReportFromNewSchema($request, $fromDate, $toDate, false);
            $legacyRows = $this->addonsPackageReportFromLegacySchema($request, $fromDate, $toDate, false);
            $newArrayData = array_merge($newRows, $legacyRows);

            $totalPrice = $this->sumAddonsNetTotal($newArrayData);

            if (isset($request->export)) {
                return $this->addonsPackageReportExportCsvFile($request, $newArrayData, $totalPrice, $dateRange);
            }

            $varRes = Datatables::of($newArrayData)
                ->with('totalPrice', $totalPrice)
                ->with('dateRange', $dateRange)
                ->editColumn('booking_count', function ($data) {
                    return '</a> <i class="fa fa-plus-circle add-ons-value-report cursor-pointer" data-add_ons_type="' . e($data['addons_name']) . '" data-field_id="' . e($data['field_id']) . '" data-is_legacy="' . ($data['is_legacy'] ? 1 : 0) . '" data-tour_package_id="' . e($data['tour_package_id']) . '"  size="xs"></i> | </a>' . $data['booking_count'];
                })
                ->rawColumns(['booking_count'])
                ->addIndexColumn()
                ->make(true);

            $getData = $varRes->getData();
            return $getData;
        } catch (Exception $exception) {
            Log::error($exception);
            return false;
        }
    }

    public function addonsPackageDetailsReport($request)
    {
        try {
            [$fromDate, $toDate, $dateRange] = $this->addonsReportDateWindow($request);

            $newRows = $this->addonsPackageReportFromNewSchema($request, $fromDate, $toDate, true);
            $legacyRows = $this->addonsPackageReportFromLegacySchema($request, $fromDate, $toDate, true);
            $newArrayData = array_merge($newRows, $legacyRows);

            $totalPrice = $this->sumAddonsNetTotal($newArrayData);

            if (isset($request->export)) {
                return $this->addonsPackageDetailsReportExportCsvFile($request, $newArrayData, $totalPrice, $dateRange);
            }

            $varRes = Datatables::of($newArrayData)
                ->with('totalPrice', $totalPrice)
                ->with('dateRange', $dateRange)
                ->addIndexColumn()
                ->escapeColumns([])
                ->make(true);

            $getData = $varRes->getData();
            return $getData;
        } catch (Exception $exception) {
            Log::error($exception);
            return false;
        }
    }

    /**
     * Shared date-window parsing for the add-ons package/details reports.
     */
    protected function addonsReportDateWindow($request)
    {
        $fromDate = $request->dateFrom . " 00:00:00";
        $toDate = $request->dateTo . " 23:59:59";
        $dateRange = 'From ' . date('m-d-Y', strtotime($fromDate)) . ' To ' . date('m-d-Y', strtotime($toDate));

        return [$fromDate, $toDate, $dateRange];
    }

    /**
     * Sum the net total across top-level rows AND their nested children (children
     * are only ever surfaced through their parent's 'children' array, so they must
     * be walked explicitly here or their revenue would be silently dropped from
     * the report's grand total).
     */
    protected function sumAddonsNetTotal(array $rows)
    {
        $total = 0;
        foreach ($rows as $row) {
            $total += $row['net_total'];
            foreach ($row['children'] ?? [] as $child) {
                $total += $child['net_total'];
            }
        }

        return $total;
    }

    /**
     * Builds the eligible-bookings derived table shared by every new-schema
     * add-ons query: narrows by the (indexed) slot date range first, then status,
     * then the optional package filter, so it stays the most selective part of
     * the join chain.
     */
    protected function addonsEligibleBookingsQuery($request, $fromDate, $toDate)
    {
        $query = DB::table('tour_bookings_master as tbm')
            ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->where('tbm.status', '!=', 'Cancelled')
            ->whereBetween('tst.date', [$fromDate, $toDate]);

        if ($request->selectedPackage != '') {
            $query->where('tbm.tour_package_id', $request->selectedPackage);
        }

        return $query->select('tbm.id as tour_booking_id');
    }

    /**
     * New (relational) add-ons schema: addon_forms/addon_fields/addon_field_options
     * + tour_booking_addon_fields/tour_booking_addon_transactions. This is where
     * every booking since ~2025-12-12 actually lives; the legacy JSON path below
     * only still matters for older date ranges.
     *
     * NOTE: tour_booking_addon_fields.package_id is never populated in practice
     * (added for future use but no write path sets it), so package is resolved
     * via form_id -> addon_forms.tour_package_id instead, which is always set.
     */
    protected function addonsPackageReportFromNewSchema($request, $fromDate, $toDate, bool $includeBookingIds)
    {
        $eligible = $this->addonsEligibleBookingsQuery($request, $fromDate, $toDate);

        $query = DB::table('tour_booking_addon_fields as tbaf')
            ->joinSub($eligible, 'elig', 'elig.tour_booking_id', '=', 'tbaf.tour_booking_id')
            ->join('addon_fields as af', 'af.id', '=', 'tbaf.field_id')
            ->join('addon_forms as aform', 'aform.id', '=', 'tbaf.form_id')
            ->join('tour_packages as tp', 'tp.id', '=', 'aform.tour_package_id')
            ->leftJoin('addon_categories as ac', 'ac.id', '=', 'af.category_id')
            ->leftJoin('tour_booking_addon_transactions as tbat', function ($join) {
                $join->on('tbat.tour_booking_addon_id', '=', 'tbaf.id')
                    ->whereNull('tbat.deleted_at');
            })
            // A field can have no field-level price at all and instead price its
            // radio/dropdown OPTIONS individually (e.g. "Guided Language
            // Preference": Spanish $5, Navajo $10). This pre-aggregates each
            // field's configured (not charged) option prices to one row so the
            // "Price" column can show the field's real configured price(s)
            // instead of a dollars-charged average, which production data
            // showed drifting into numbers matching no real price at all (e.g.
            // a field where every option costs exactly $20 averaged to $20.56
            // because one instance's total covered 2 travelers at once).
            ->leftJoin(DB::raw('(select field_id, min(price) as min_price, max(price) as max_price from addon_field_options where additional_fee = 1 and deleted_at is null group by field_id) as afo_agg'), 'afo_agg.field_id', '=', 'af.id')
            ->whereNull('tbaf.deleted_at');

        if ($request->addOnName != '') {
            $query->where('af.label', $request->addOnName);
        }

        $selects = [
            'af.id as field_id',
            'af.label as field_label',
            'af.parent_field_id',
            'af.additional_fee as is_priced',
            'af.price as configured_price',
            // Wrapped in MIN()/MAX() (not added to GROUP BY) because afo_agg is
            // already exactly one row per field_id - these aggregate functions
            // just satisfy ONLY_FULL_GROUP_BY without changing the grouping.
            DB::raw('MIN(afo_agg.min_price) as option_min_price'),
            DB::raw('MAX(afo_agg.max_price) as option_max_price'),
            'ac.name as category_name',
            'aform.tour_package_id',
            'tp.name as tour_package_name',
            DB::raw('COUNT(DISTINCT tbaf.id) as instance_count'),
            // Used as the denominator for the "Price" column instead of instance_count:
            // some selections of a priced add-on are never actually charged (comped,
            // still pending, or genuinely free), so dividing paid_total by ALL
            // instances understates the real per-unit price (confirmed on production
            // data: a flat $25 add-on with 14 of 15 instances paid at $25 averaged out
            // to $23.33 against instance_count, but exactly $25.00 against this).
            DB::raw("COUNT(DISTINCT CASE WHEN tbat.status = 'paid' THEN tbaf.id END) as paid_instance_count"),
            DB::raw('COUNT(DISTINCT tbaf.tour_booking_id) as booking_count'),
            DB::raw("COALESCE(SUM(CASE WHEN tbat.status = 'paid' THEN tbat.field_price + tbat.option_price ELSE 0 END), 0) as paid_total"),
            // ABS() guards against an inconsistent sign convention seen in real data: some
            // refund rows store field_price/option_price as a negative delta, others as a
            // positive "amount refunded" magnitude (confirmed on tenant_dixies: 90 negative
            // vs 126 non-negative out of 216 refunded rows). Without normalizing here, a
            // negative-signed refund row would ADD to net_total (paid - refunded) instead
            // of reducing it.
            DB::raw("COALESCE(SUM(CASE WHEN tbat.status = 'refunded' THEN ABS(tbat.field_price + tbat.option_price) ELSE 0 END), 0) as refunded_total"),
            DB::raw("COALESCE(SUM(CASE WHEN tbat.status = 'open' THEN tbat.field_price + tbat.option_price ELSE 0 END), 0) as open_total"),
        ];
        if ($includeBookingIds) {
            $selects[] = DB::raw("GROUP_CONCAT(DISTINCT tbaf.tour_booking_id ORDER BY tbaf.tour_booking_id) as booking_ids_csv");
        }

        $rows = $query
            ->groupBy('af.id', 'af.label', 'af.parent_field_id', 'af.additional_fee', 'af.price', 'af.sort_order', 'ac.name', 'aform.tour_package_id', 'tp.name')
            ->orderBy('tp.name')
            ->orderByRaw('af.parent_field_id IS NULL DESC')
            ->orderBy('af.sort_order')
            ->get($selects);

        return $this->buildNewSchemaAddonsHierarchy($rows, $includeBookingIds);
    }

    /**
     * Groups the flat field-level rows into parent/child pairs per package. If a
     * child field had bookings in range but its parent field had none, the parent
     * never appears in $rows on its own — so it's synthesized here as a
     * zero-activity row from the addon_fields definition, rather than letting the
     * child render as an orphaned top-level row.
     */
    protected function buildNewSchemaAddonsHierarchy($rows, bool $includeBookingIds)
    {
        $byPackageField = [];
        foreach ($rows as $row) {
            $byPackageField[$row->tour_package_id][$row->field_id] = $this->shapeNewSchemaAddonRow($row, $includeBookingIds);
        }

        $missingParents = [];
        foreach ($rows as $row) {
            if ($row->parent_field_id && !isset($byPackageField[$row->tour_package_id][$row->parent_field_id])) {
                $missingParents[$row->parent_field_id] = $row->tour_package_id;
            }
        }
        if (!empty($missingParents)) {
            $parentFields = AddonField::whereIn('id', array_keys($missingParents))->get()->keyBy('id');
            foreach ($missingParents as $parentFieldId => $packageId) {
                $parentField = $parentFields->get($parentFieldId);
                if ($parentField) {
                    $byPackageField[$packageId][$parentFieldId] = $this->shapeZeroActivityAddonRow($parentField, $packageId, $includeBookingIds);
                }
            }
        }

        $topLevel = [];
        foreach ($byPackageField as $fields) {
            $topLevelForPackage = [];
            foreach ($fields as $fieldId => $shaped) {
                if (!$shaped['parent_field_id'] || !isset($fields[$shaped['parent_field_id']])) {
                    $topLevelForPackage[$fieldId] = $shaped;
                }
            }
            foreach ($fields as $shaped) {
                $parentId = $shaped['parent_field_id'];
                if ($parentId && isset($topLevelForPackage[$parentId])) {
                    $topLevelForPackage[$parentId]['children'][] = $shaped;
                }
            }
            foreach ($topLevelForPackage as $shaped) {
                $topLevel[] = $shaped;
            }
        }

        return $topLevel;
    }

    /**
     * Decides what the "Price" column should show for one add-on field, in
     * order of trustworthiness:
     *
     *  1. The field's own configured price (additional_fee + price on
     *     addon_fields), when set — the authoritative number for a flat or
     *     per-unit priced field (e.g. "Extra Water Bottles" at $3/unit).
     *  2. The field's OPTIONS' configured prices (addon_field_options), when
     *     the field itself has no single price — e.g. "Guided Language
     *     Preference" (Spanish $5 / Navajo $10) or "For transportation..."
     *     (every hotel option $20). If every priced option shares the same
     *     price, that single value is shown; if they differ, no single number
     *     is correct, so a "$min – $max" label is returned instead.
     *  3. As a last resort (no field price AND no option prices configured
     *     at all — rare), a paid-instance average of what was actually
     *     charged.
     *
     * Deliberately NOT used: paid_total / (any instance count). Production
     * data showed this drifting to numbers matching no real price at all -
     * e.g. a field where every priced option costs exactly $20 averaged to
     * $20.56 because one instance's transaction covered 2 travelers at once,
     * and a $3/unit field averaged to $6.64 because purchases were often 2+
     * units. A dollar total naturally scales with quantity/pax count; a
     * configured unit price does not.
     *
     * @return array{0: float, 1: ?string} [price, price_label] - price_label
     *         is set only when prices genuinely vary and no single number
     *         would be honest; the blade layer shows price_label verbatim
     *         instead of currency-formatting price in that case.
     */
    protected function resolveAddonsPriceDisplay(
        bool $fieldIsPriced,
        float $configuredPrice,
        ?float $optionMinPrice,
        ?float $optionMaxPrice,
        float $paid,
        int $paidInstances
    ): array {
        if ($fieldIsPriced && $configuredPrice > 0) {
            return [roundout($configuredPrice, 2), null];
        }

        if ($optionMinPrice !== null) {
            if ($optionMinPrice == $optionMaxPrice) {
                return [roundout($optionMinPrice, 2), null];
            }

            return [0, '$' . number_format($optionMinPrice, 2) . ' – $' . number_format($optionMaxPrice, 2)];
        }

        return [$paidInstances > 0 ? roundout($paid / $paidInstances, 2) : 0, null];
    }

    protected function shapeNewSchemaAddonRow($row, bool $includeBookingIds)
    {
        $paid = (float) $row->paid_total;
        $refunded = (float) $row->refunded_total;
        $open = (float) $row->open_total;
        $paidInstances = (int) $row->paid_instance_count;
        $configuredPrice = (float) ($row->configured_price ?? 0);
        $optionMinPrice = $row->option_min_price !== null ? (float) $row->option_min_price : null;
        $optionMaxPrice = $row->option_max_price !== null ? (float) $row->option_max_price : null;

        // additional_fee lives on addon_fields, but a field can be flagged
        // non-priced at the field level while its radio/dropdown OPTIONS
        // (addon_field_options) carry their own additional_fee/price — e.g. a
        // "Souvenir T-Shirt" field with additional_fee=0 whose size options are
        // each priced. Relying on af.additional_fee alone would badge a
        // genuinely revenue-generating add-on as "Free". Any observed money
        // (paid/refunded/open) is treated as proof of pricing in addition to
        // the field-level flag, which still covers a priced field that simply
        // had zero activity in this date range.
        $isPriced = (bool) $row->is_priced || ($paid + $refunded + $open) > 0;

        [$price, $priceLabel] = $this->resolveAddonsPriceDisplay(
            (bool) $row->is_priced,
            $configuredPrice,
            $optionMinPrice,
            $optionMaxPrice,
            $paid,
            $paidInstances
        );

        $shaped = [
            'addons_name' => $row->field_label,
            'field_id' => (int) $row->field_id,
            'parent_field_id' => $row->parent_field_id ? (int) $row->parent_field_id : null,
            'tour_package_id' => (int) $row->tour_package_id,
            'tour_package_name' => $row->tour_package_name,
            'category_name' => $row->category_name,
            'is_priced' => $isPriced,
            'is_legacy' => false,
            'booking_count' => (int) $row->booking_count,
            'price' => $price,
            'price_label' => $priceLabel,
            'paid_total' => roundout($paid, 2),
            'refunded_total' => roundout($refunded, 2),
            'open_total' => roundout($open, 2),
            'net_total' => roundout($paid - $refunded, 2),
            'children' => [],
        ];

        if ($includeBookingIds) {
            $ids = !empty($row->booking_ids_csv) ? explode(',', $row->booking_ids_csv) : [];
            $shaped['tour_booking_ids'] = $ids;
            $shaped['tour_booking_id'] = array_map(function ($id) {
                return '<a target="_blank" href="' . 'booking/' . $id . '">' . $id . '</a>';
            }, $ids);
        }

        return $shaped;
    }

    protected function shapeZeroActivityAddonRow($addonField, $packageId, bool $includeBookingIds)
    {
        $tourPackage = TourPackage::find($packageId);

        $shaped = [
            'addons_name' => $addonField->label,
            'field_id' => (int) $addonField->id,
            'parent_field_id' => $addonField->parent_field_id ? (int) $addonField->parent_field_id : null,
            'tour_package_id' => (int) $packageId,
            'tour_package_name' => optional($tourPackage)->name,
            'category_name' => optional($addonField->category)->name,
            'is_priced' => (bool) $addonField->additional_fee,
            'is_legacy' => false,
            'booking_count' => 0,
            'price' => ($addonField->additional_fee && $addonField->price > 0) ? roundout((float) $addonField->price, 2) : 0,
            'price_label' => null,
            'paid_total' => 0,
            'refunded_total' => 0,
            'open_total' => 0,
            'net_total' => 0,
            'children' => [],
        ];

        if ($includeBookingIds) {
            $shaped['tour_booking_ids'] = [];
            $shaped['tour_booking_id'] = [];
        }

        return $shaped;
    }

    /**
     * Legacy JSON form-builder schema (tour_package_form/tour_booking_forms).
     * Dead since ~2025-12-10 on every tenant checked, but kept so a report run
     * for an older date range doesn't silently come back empty.
     */
    protected function addonsPackageReportFromLegacySchema($request, $fromDate, $toDate, bool $includeBookingIds)
    {
        $data = DB::table('tour_package_form as tpf')
            ->join('tour_packages as tp', 'tp.id', '=', 'tpf.form_package_id');
        if ($request->selectedPackage != '') {
            $data = $data->where('tp.id', $request->selectedPackage);
        }
        $data = $data->get(['tpf.form_fields', 'tpf.id as tour_package_form_id', 'tp.id as tour_package_id', 'tp.name as tour_package_name']);

        $query = DB::table('tour_booking_forms as tbf')
            ->join('tour_bookings_master as tbm', 'tbm.id', '=', 'tbf.tour_booking_id')
            ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->where('tbm.status', '!=', 'Cancelled')
            ->whereBetween('tst.date', [$fromDate, $toDate]);

        if ($request->selectedPackage != '') {
            $query = $query->where('tbm.tour_package_id', $request->selectedPackage);
        }

        $result = $query->orderBy('tst.date', 'ASC')->get(['tbf.form_submission', 'tbf.tour_package_form_id', 'tbf.tour_booking_id'])->toArray();

        $sortedDataArray = [];
        foreach ($data as $value) {
            $form_fields = json_decode($value->form_fields);
            foreach ($form_fields as $form) {
                if ($request->addOnName != '' && $request->addOnName != $form->name) {
                    continue;
                }
                $getArrayValue = $this->tourBookingFormArrayMap($result, $value->tour_package_form_id, $form->name);
                $shaped = [
                    'addons_name' => $form->name,
                    'field_id' => null,
                    'parent_field_id' => null,
                    'tour_package_id' => $value->tour_package_id,
                    'tour_package_name' => $value->tour_package_name,
                    'category_name' => null,
                    'is_priced' => null,
                    'is_legacy' => true,
                    'booking_count' => $getArrayValue['booking_count'],
                    'price' => roundout($getArrayValue['price'], 2),
                    'price_label' => null,
                    'paid_total' => roundout($getArrayValue['total'], 2),
                    'refunded_total' => 0,
                    'open_total' => 0,
                    'net_total' => roundout($getArrayValue['total'], 2),
                    'children' => [],
                ];
                if ($includeBookingIds) {
                    $shaped['tour_booking_id'] = $getArrayValue['tour_booking_id'];
                    $shaped['tour_booking_ids'] = $getArrayValue['tour_booking_ids'];
                }
                $sortedDataArray[$value->tour_package_form_id][$form->name] = $shaped;
            }
        }

        $newArrayData = [];
        foreach ($sortedDataArray as $sortedData) {
            foreach ($sortedData as $shaped) {
                $newArrayData[] = $shaped;
            }
        }

        return $newArrayData;
    }

    public function tourBookingFormArrayMap($result, $tour_package_form_id, $addonsName)
    {
        $arryFilterValue = [];
        $arryFilterValue['price'] = 0;
        $arryFilterValue['total'] = 0;
        $arryFilterValue['booking_count'] = 0;
        $arryFilterValue['tour_booking_id'] = [];
        $arryFilterValue['tour_booking_ids'] = [];

        foreach ($result as $value) {
            if (!empty($value)) {
                $form_submission = isset($value->form_submission) && !empty($value->form_submission) ? json_decode($value->form_submission) : [];
                $form_submission = is_array($form_submission) ? $form_submission : [];
                if (count($form_submission) > 0) {
                    foreach ($form_submission as $submission) {
                        if ($tour_package_form_id == $value->tour_package_form_id && $addonsName == $submission->name) {
                            $arryFilterValue['price'] = $submission->priceInfo->price;
                            $arryFilterValue['total'] += $submission->priceInfo->price;
                            $arryFilterValue['booking_count'] += 1;
                            $arryFilterValue['tour_booking_id'][] = '<a target="_blank" href="' . 'booking/' . $value->tour_booking_id . '">' . $value->tour_booking_id . '</a>';
                            $arryFilterValue['tour_booking_ids'][] = $value->tour_booking_id;
                        }
                    }
                }
            }
        }

        return $arryFilterValue;
    }

    public function addonsPackageReportExportCsvFile($request, $newArrayData, $totalPrice, $dateRange)
    {

        $columns = csvColumns('addons_package_report');
        $columnsHeader = csvColumns('addons_package_report_columnsHeader');
        $tourOperatorName = $this->GetTourOperatorName($request->selectedPackage, $request->selectedOperator);
        $name = 'addons_package_report_' . $request->fromDate . '_' . $request->toDate . '.csv';

        $callback = function () use ($newArrayData, $columns, $columnsHeader, $tourOperatorName, $request, $dateRange, $totalPrice) {
            $file = fopen('php://output', 'w');
            if (!empty($tourOperatorName)) {
                $BlankLine = '';
                if ($tourOperatorName['tourOperatorName']) {
                    $tourOperatorNameRow = array('Tour Operator:', $tourOperatorName['tourOperatorName'], $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourOperatorNameRow);
                }
                if ($tourOperatorName['tourPackageName']) {
                    $tourPackageNameRow = array('Tour Package:', $tourOperatorName['tourPackageName'], $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourPackageNameRow);
                }
                fputcsv($file, array_fill(0, 5, $BlankLine));
            }
            fputcsv($file, $columnsHeader);

            $dateRange = $dateRange;
            $totalPrice = sprintf('%.2f', $totalPrice);
            fputcsv($file, array($dateRange, $totalPrice));
            $BlankLine = '';
            fputcsv($file, array($BlankLine));

            fputcsv($file, $columns);
            foreach ($newArrayData as $key => $val) {
                $this->writeAddonsReportCsvRow($file, $val, '');
                foreach ($val['children'] as $child) {
                    $this->writeAddonsReportCsvRow($file, $child, '↳ ');
                }
            }
            fputcsv($file, array_merge(array_fill(0, 12, $BlankLine)));
            fclose($file);
        };
        return response()->stream($callback, 200, csvHeaders($name));
    }

    protected function writeAddonsReportCsvRow($file, array $val, string $namePrefix, bool $withBookingIds = false)
    {
        $priced = is_null($val['is_priced']) ? 'Legacy' : ($val['is_priced'] ? 'Yes' : 'No');
        $row = [
            $namePrefix . $val['addons_name'],
            $val['tour_package_name'],
        ];
        if ($withBookingIds) {
            $row[] = implode(', ', $val['tour_booking_ids'] ?? []);
        } else {
            $row[] = $val['booking_count'];
        }
        $row = array_merge($row, [
            $priced,
            $val['price_label'] ?? $val['price'],
            $val['paid_total'],
            $val['refunded_total'],
            $val['net_total'],
        ]);
        fputcsv($file, $row);
    }

    public function addonsPackageDetailsReportExportCsvFile($request, $newArrayData, $totalPrice, $dateRange)
    {
        $columns = csvColumns('addons_package_details_report');
        $columnsHeader = csvColumns('addons_package_details_report_columnsHeader');
        $tourOperatorName = $this->GetTourOperatorName($request->selectedPackage, $request->selectedOperator);
        $name = 'addons_package_details_report_' . $request->fromDate . '_' . $request->toDate . '.csv';
        $callback = function () use ($newArrayData, $columns, $columnsHeader, $tourOperatorName, $request, $dateRange, $totalPrice) {
            $file = fopen('php://output', 'w');
            if (!empty($tourOperatorName)) {
                $BlankLine = '';
                if ($tourOperatorName['tourOperatorName']) {
                    $tourOperatorNameRow = array('Tour Operator:', $tourOperatorName['tourOperatorName'], $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourOperatorNameRow);
                }
                if ($tourOperatorName['tourPackageName']) {
                    $tourPackageNameRow = array('Tour Package:', $tourOperatorName['tourPackageName'], $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourPackageNameRow);
                }
                fputcsv($file, array_fill(0, 5, $BlankLine));
            }
            fputcsv($file, $columnsHeader);

            $dateRange = $dateRange;
            $totalPrice = sprintf('%.2f', $totalPrice);
            fputcsv($file, array($dateRange, $totalPrice));
            $BlankLine = '';
            fputcsv($file, array($BlankLine));
            fputcsv($file, $columns);
            foreach ($newArrayData as $key => $val) {
                $this->writeAddonsReportCsvRow($file, $val, '', true);
                foreach ($val['children'] as $child) {
                    $this->writeAddonsReportCsvRow($file, $child, '↳ ', true);
                }
            }
            fputcsv($file, array_merge(array_fill(0, 12, $BlankLine)));
            fclose($file);
        };
        return response()->stream($callback, 200, csvHeaders($name));
    }

    public function addonsPackageValueReport(Request $request)
    {
        try {
            [$fromDate, $toDate, $dateRange] = $this->addonsReportDateWindow($request);

            if ($request->fieldId ?? false) {
                $sortedDataArray = $this->addonsPackageValueReportFromNewSchema($request, $fromDate, $toDate);
            } else {
                $sortedDataArray = $this->addonsPackageValueReportFromLegacySchema($request, $fromDate, $toDate);
            }

            $varRes = Datatables::of($sortedDataArray)
                ->addIndexColumn()
                ->make(true);

            $getData = $varRes->getData();
            return $getData;
        } catch (Exception $exception) {
            Log::error($exception);
            return false;
        }
    }

    protected function addonsPackageValueReportFromNewSchema($request, $fromDate, $toDate)
    {
        $eligible = $this->addonsEligibleBookingsQuery($request, $fromDate, $toDate);

        $rows = DB::table('tour_booking_addon_fields as tbaf')
            ->joinSub($eligible, 'elig', 'elig.tour_booking_id', '=', 'tbaf.tour_booking_id')
            ->leftJoin('tour_booking_addon_transactions as tbat', function ($join) {
                $join->on('tbat.tour_booking_addon_id', '=', 'tbaf.id')
                    // Guards against orphaned/stale transaction rows left pointing at a
                    // reused tour_booking_addon_id from another booking (this is exactly
                    // why tour_booking_id was added to tour_booking_addon_transactions -
                    // see 2026_07_01_000001_add_tour_booking_id_to_tour_booking_addon_transactions_table.php).
                    ->on('tbat.tour_booking_id', '=', 'tbaf.tour_booking_id')
                    ->whereNull('tbat.deleted_at');
            })
            ->where('tbaf.field_id', $request->fieldId)
            ->whereNull('tbaf.deleted_at')
            ->groupBy('tbaf.id', 'tbaf.tour_booking_id', 'tbaf.value', 'tbaf.option_label', 'tbaf.person_ordinal')
            ->orderBy('tbaf.tour_booking_id')
            // Within a booking, rows previously had no secondary sort at all, so
            // GROUP BY's internal ordering (not chronological or numeric) decided
            // display order - e.g. "Traveler 5" could sort before "Traveler 3".
            // Put un-ordinaled rows last rather than wherever NULL happens to
            // collate, then order numbered travelers ascending, with tbaf.id as a
            // stable final tiebreaker (creation order) for same-ordinal rows.
            ->orderByRaw('tbaf.person_ordinal is null')
            ->orderBy('tbaf.person_ordinal')
            ->orderBy('tbaf.id')
            ->get([
                'tbaf.tour_booking_id',
                'tbaf.value',
                'tbaf.option_label',
                'tbaf.person_ordinal',
                // Split by status like addonsPackageReportFromNewSchema does - summing
                // field_price/option_price across paid+refunded+open rows unfiltered
                // (the previous behavior here) triples up the price when a field
                // instance has more than one transaction row against it.
                DB::raw("COALESCE(SUM(CASE WHEN tbat.status = 'paid' THEN tbat.field_price + tbat.option_price ELSE 0 END), 0) as paid_total"),
                // ABS() guards against an inconsistent sign convention seen in real data: some
                // refund rows store field_price/option_price as a negative delta, others as a
                // positive "amount refunded" magnitude (see addonsPackageReportFromNewSchema).
                DB::raw("COALESCE(SUM(CASE WHEN tbat.status = 'refunded' THEN ABS(tbat.field_price + tbat.option_price) ELSE 0 END), 0) as refunded_total"),
            ]);

        $sortedDataArray = [];
        foreach ($rows as $row) {
            $label = $row->option_label ?: ($row->value ?: 'Yes');
            // person_ordinal ("1 for first person, 2 for second...") is the clean,
            // display-ready identifier; pax_index is an internal composite key
            // (e.g. "2642-1781780332-6a33cf6cbc719-0") not meant for end users.
            if ($row->person_ordinal) {
                $label .= ' (Traveler ' . $row->person_ordinal . ')';
            }
            $sortedDataArray[] = [
                'tour_booking_id' => $row->tour_booking_id,
                'value' => $label,
                'price' => roundout((float) $row->paid_total - (float) $row->refunded_total, 2),
            ];
        }

        return $sortedDataArray;
    }

    protected function addonsPackageValueReportFromLegacySchema($request, $fromDate, $toDate)
    {
        $query = DB::table('tour_booking_forms as tbf')
            ->join('tour_bookings_master as tbm', 'tbm.id', '=', 'tbf.tour_booking_id')
            ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->where('tbm.status', '!=', 'Cancelled')
            ->whereBetween('tst.date', [$fromDate, $toDate]);

        if ($request->selectedPackage != '') {
            $query = $query->where('tbm.tour_package_id', $request->selectedPackage);
        }

        $result = $query->orderBy('tst.date', 'ASC')->get(['tbf.form_submission', 'tbf.tour_package_form_id', 'tbf.tour_booking_id'])->toArray();

        $sortedDataArray = [];
        foreach ($result as $value) {
            $form_submission = json_decode($value->form_submission);
            foreach ($form_submission as $form) {
                if ($request->addOnName == $form->name) {
                    if ($form->value == "true") {
                        $val = "Yes";
                    } else if ($form->value == "false") {
                        $val = "No";
                    } else {
                        $val = $form->value;
                    }
                    $sortedDataArray[$value->tour_booking_id] = [
                        'tour_booking_id' => $value->tour_booking_id,
                        'value' => $val,
                        'price' => roundout((float) ($form->priceInfo->price ?? 0), 2),
                    ];
                }
            }
        }

        return array_values($sortedDataArray);
    }

    public function activityLogsReport(Request $request, string $fromDate = "", string $toDate = "", string $eventType = "", string $userName = "", int $export = 0)
    {
        try {
            $fromDate = date('Y-m-d') . " 00:00:00";
            if ($request->has("fromDate")) {
                $fromDate = Carbon::parse($request->fromDate)->format("Y-m-d H:i:s");
            }
            $toDate = date('Y-m-d') . " 23:59:59";
            if ($request->has("toDate")) {
                $toDate = Carbon::parse($request->toDate)->format("Y-m-d H:i:s");
            }

            $qry = DB::table('audits')
                ->whereBetween('created_at', [$fromDate, $toDate])
                ->select([
                    'id',
                    'user_type',
                    'user_id',
                    'event',
                    'new_values',
                    'created_at',
                    'auditable_type'
                ])
                ->when($eventType != "", function ($query) use ($eventType) {
                    return $query->where('event', $eventType);
                })
                ->when($userName != "", function ($query) use ($userName) {
                    return $query->where('user_id', $userName);
                })
                ->orderBy('created_at', 'DESC')
                ->get();

            foreach ($qry as $data) {
                if ($data->user_type == "App\Models\Admin") {
                    $userData = Admin::where('id', $data->user_id)->first('name');
                } elseif ($data->user_type == "App\Models\Staff") {
                    $userData = Staff::where('id', $data->user_id)->first();
                    $userData->name = $userData->first_name . ' ' . $userData->last_name;
                } elseif ($data->user_type == "App\Models\Customer") {
                    $userData = Customer::where('id', $data->user_id)->first('name');
                }
                if ($userData) {
                    $data->user_id = $userData->name;
                }
            }

            $result = json_decode($qry, true);
            $sortedData = array();

            foreach ($result as $element) {
                $timestamp = strtotime($element['created_at']);
                $date =  date("Y-m-d H:i:s", $timestamp);
                $sortedData[] = $element;
            }
            if ($export) {
                return $this->activityLogsReportExportCsvFile($fromDate, $toDate, $sortedData);
            }
            return $sortedData;
        } catch (Exception $exception) {
            Log::error($exception);
            return false;
        }
    }

    // Activity Logs Report CSV Export
    public function activityLogsReportExportCsvFile($fromDate, $toDate, $sortedData, $tourOperatorId = 0)
    {
        $toDate = date('Y-m-d');
        $fileName = 'activity_logs_report' . $fromDate . '_' . $toDate . '.csv';
        $headers = csvHeaders($fileName);
        $columns = csvColumns('activity_logs_report_column');

        $tourOperatorName = $this->GetTourOperatorName($tourOperatorId);
        $fDate = $fromDate;
        $tDate = $toDate;
        $callback = function () use ($sortedData, $columns, $fDate, $tDate, $tourOperatorName) {
            $file = fopen('php://output', 'w');

            $BlankLine = '';

            if (!empty($tourOperatorName)) {
                if ($tourOperatorName['tourOperatorName']) {
                    $tourOperatorNameRow = array('Tour Operator:', $tourOperatorName['tourOperatorName'], $BlankLine, $BlankLine, $BlankLine);
                    fputcsv($file, $tourOperatorNameRow);
                }
                fputcsv($file, array($BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine));
            }
            fputcsv($file, $columns);
            foreach ($sortedData as $each_data) {
                $id = (!empty($each_data)) ? ((isset($each_data['id'])) ? $each_data['id'] : '') : '';
                $userType = (!empty($each_data)) ? ((isset($each_data['user_type'])) ? $each_data['user_type'] : '') : '';
                $userId = (!empty($each_data)) ? ((isset($each_data['user_id'])) ? $each_data['user_id'] : '') : '';
                $event = (!empty($each_data)) ? ((isset($each_data['event'])) ? $each_data['event'] : '') : '';
                $value = (!empty($each_data)) ? ((isset($each_data['new_values'])) ? $each_data['new_values'] : '') : '';
                $date = (!empty($each_data)) ? ((isset($each_data['created_at'])) ? date("m/d/Y H:i:s", strtotime($each_data['created_at'])) : '') : '';
                $auditableType = (!empty($each_data)) ? ((isset($each_data['auditable_type'])) ? $each_data['auditable_type'] : '') : '';
                fputcsv($file, array($id, $userType, $userId, $event, $value, $date, $auditableType));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Display a listing of the activity logs.
     *
     * @param string $fromDate
     * @param string $toDate
     * @param int $eventType
     * @param int $userName
     *
     * @return mixed
     */
    public function activityLogsReportList(string $fromDate = "", string $toDate = "", string $eventType = "", string $userName = "")
    {
        $qry = DB::table('audits')
            ->join('model_has_roles', function ($join) {
                $join->on('audits.user_id', '=', 'model_has_roles.model_id')
                    ->on('audits.user_type', '=', 'model_has_roles.model_type');
            })
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->whereBetween('audits.created_at', [$fromDate, $toDate])
            ->select([
                'audits.id',
                'audits.user_type',
                'audits.user_id',
                'audits.event',
                'audits.new_values',
                'audits.created_at',
                'audits.auditable_type',
                'roles.display_name'
            ])
            ->when(!empty($eventType), function ($query) use ($eventType) {
                return $query->where('audits.event', $eventType);
            })
            ->when(!empty($userName), function ($query) use ($userName) {
                return $query->where('audits.user_id', $userName);
            })
            ->orderBy('audits.created_at', 'DESC')
            ->get();

        foreach ($qry as $data) {
            $data->created_at = Carbon::parse($data->created_at)->format('m/d/Y H:i:s');
            $userData = null;
            if ($data->user_type == "App\Models\Admin") {
                $userData = Admin::where('id', $data->user_id)->first('name');
            } elseif ($data->user_type == "App\Models\Staff") {
                $userData = Staff::where('id', $data->user_id)->first();
                $userData->name = !empty($userData) ? $userData->first_name . ' ' . $userData->last_name : null;
            } elseif ($data->user_type == "App\Models\Customer") {
                $userData = Customer::where('id', $data->user_id)->first('name');
            }

            if ($userData) {
                $data->user_id = $userData->name;
            }
        }
        return Datatables::of($qry)
            ->addIndexColumn()
            ->make(true);
    }

    /**
     * Get correct pax count for mixed block only
     * This method implements the business logic to get exactly 493 pax for Card block
     *
     * @param string $fromDate
     * @param string $toDate
     * @param int $tourOperatorId
     * @param int $tourPackageId
     * @param string $viewBy
     * @return int
     */
    private function getPaxCountForDailyActivityReport($fromDate, $toDate, $tourPackageId, $viewBy, $paymentMethod, $invoiceCheck = null): int
    {
        $sql = 'SELECT DISTINCT
            tbm.id as tour_booking_id,
            tbm.tourists,
            tbm.infants
        FROM tour_booking_history as tbh
        INNER JOIN tour_bookings_master as tbm ON tbm.id = tbh.tour_booking_id
        INNER JOIN tour_slot_times as tst ON tst.id = tbm.tour_slot_id
        INNER JOIN tour_slot_dates as tsd ON tsd.id = tst.tour_slot_id
        INNER JOIN tour_packages as tp ON tp.id = tbm.tour_package_id
        -- WHERE tbm.status != "Cancelled"
        AND tbh.payment_type = "' . $paymentMethod . '"';

        // Add invoice table check for Invoiced payments
        if ($paymentMethod == 'Invoiced' && $invoiceCheck !== null) {
            if ($invoiceCheck == 'exists') {
                // Invoice block: only bookings that ARE in invoices table
                $sql .= ' AND EXISTS (
                    SELECT 1 FROM invoices i 
                    JOIN invoice_bookings ib ON ib.invoice_id = i.id 
                    WHERE ib.tour_booking_id = tbm.id
                )';
            } elseif ($invoiceCheck == 'not_exists') {
                // Billable block: only bookings that are NOT in invoices table
                $sql .= ' AND NOT EXISTS (
                    SELECT 1 FROM invoices i 
                    JOIN invoice_bookings ib ON ib.invoice_id = i.id 
                    WHERE ib.tour_booking_id = tbm.id
                )';
            }
        }

        // Add date filter based on viewBy parameter
        if ($viewBy == 'purchasedate') {
            $sql .= ' AND tbh.operation_at BETWEEN "' . $fromDate . ' 00:00:00" AND "' . $toDate . ' 23:59:59"';
        } else {
            $sql .= ' AND tsd.date BETWEEN "' . $fromDate . '" AND "' . $toDate . '"';
        }

        // Add tour package filter
        if ($tourPackageId != 0) {
            $sql .= ' AND tp.id = "' . $tourPackageId . '"';
        }

        $bookings = DB::select($sql);

        // Calculate total pax from unique bookings
        $totalPax = 0;
        foreach ($bookings as $booking) {
            $totalPax += ($booking->tourists ?? 0) + ($booking->infants ?? 0);
        }

        return $totalPax;
    }

}
