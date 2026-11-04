<?php

namespace App\Http\Controllers\API\V1\Report;

use App\Http\Controllers\API\V1\ApiController;
use App\Repositories\Report\ReportRepository;
use App\Exceptions\Core\GeneralCoreException;
use Exception;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\API\Report\CashReportRequest;
use App\Http\Requests\API\Report\CardReportRequest;
use App\Http\Requests\API\Report\CheckWireReportRequest;
use App\Http\Requests\API\Report\TaxCollectionReportRequest;
use App\Http\Requests\API\Report\TransactionReportRequest;
use App\Http\Requests\API\Report\TransactionSummaryReportRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class ReportController extends ApiController
{
    protected $reportRepository;

    /**
     * @param  ReportRepository  $reportRepository
     */
    public function __construct(ReportRepository $reportRepository)
    {
        $this->reportRepository = $reportRepository;
    }

    /**
     * @return JsonResponse
     *
     * @param CashReportRequest $request
     * @throws Exception
     */
    public function cashCollection(CashReportRequest $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-report.api_response.cash_report_success'))
                ->respond($this->reportRepository->cashcollection($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param CardReportRequest $request
     * @throws Exception
     */
    public function cardCollection(CardReportRequest $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-report.api_response.card_report_success'))
                ->respond($this->reportRepository->cardcollection($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param string $fromDate
     * @param string $toDate
     * @param int $tourOperatorId
     * @param int $tourPackageId
     * @throws Exception
     */
    public function dailyactivityreport(Request $request, string $fromDate, string $toDate, int $tourOperatorId, int $tourPackageId): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-report.api_response.daily_activity_report_success'))
                ->respond($this->reportRepository->dailyactivityreport($request, $fromDate, $toDate, $tourOperatorId, $tourPackageId));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param CheckWireReportRequest $request
     * @throws Exception
     */
    public function wireCollection(CheckWireReportRequest $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-report.api_response.check_wire_report_success'))
                ->respond($this->reportRepository->wirecollection($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param string $fromDate
     * @param string $toDate
     * @param int $customerId
     * @param int $tourOperatorId
     * @param int $tourPackageId
     * @param int $export
     * @throws Exception
     */
    public function affiliateReport(Request $request, string $fromDate = "", string $toDate = "", int $customerId = 0, $tourOperatorId = 0, int $tourPackageId = 0, int $export = 0): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-report.api_response.affiliate_booked_report_success'))
                ->respond($this->reportRepository->affiliatereport($request, $fromDate, $toDate, $customerId, $tourOperatorId, $tourPackageId, $export));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param string $fromDate
     * @param string $toDate
     * @param int $customerId
     * @param int $tourOperatorId
     * @param int $tourPackageId
     * @param int $export
     * @throws Exception
     */
    public function affiliateInvoiceReport(string $fromDate = "", string $toDate = "", int $customerId = 0,  $tourOperatorId = 0, int $tourPackageId = 0, int $export = 0): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-report.api_response.affiliate_invoiced_report_success'))
                ->respond($this->reportRepository->affiliateallreport($fromDate, $toDate, $customerId, $tourOperatorId, $tourPackageId, $export));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param string $fromDate
     * @param string $toDate
     * @param int $customerId
     * @param int $tourOperatorId
     * @param int $tourPackageId
     * @param int $export
     * @throws Exception
     */
    public function affiliateComprehensiveReport(string $fromDate = "", string $toDate = "", int $customerId = 0,  $tourOperatorId = 0, int $tourPackageId = 0, int $export = 0): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-report.api_response.affiliate_comprehensive_report_success'))
                ->respond($this->reportRepository->affiliatecomprehensivereport($fromDate, $toDate, $customerId, $tourOperatorId, $tourPackageId, $export));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param TaxCollectionReportRequest $request
     * @throws Exception
     */
    public function taxCollection(TaxCollectionReportRequest $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-report.api_response.tax_collection_report_success'))
                ->respond($this->reportRepository->taxCollection($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param string $fromDate
     * @param string $toDate
     * @param int $tourOperatorId
     * @param int $tourPackageId
     * @param int $export
     * @throws Exception
     */
    public function refundPayment(string $fromDate = "", string $toDate = "", $tourOperatorId = 0, int $tourPackageId = 0, int $export = 0): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-report.api_response.refund_report_success'))
                ->respond($this->reportRepository->refundpayment($fromDate, $toDate, $tourOperatorId, $tourPackageId, $export));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @param string $fromDate
     * @param string $toDate
     * @param int $tourOperatorId
     * @param int $tourPackageId
     * @param int $export
     * @throws Exception
     */
    public function cancelBookingReport(Request $request, string $fromDate = "", string $toDate = "", $tourOperatorId = 0, int $tourPackageId = 0, int $export = 0): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-report.api_response.cancel_booking_report_success'))
                ->respond($this->reportRepository->cancelbookingreport($request, $fromDate, $toDate, $tourOperatorId, $tourPackageId, $export));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param string $fromDate
     * @param string $toDate
     * @param int $tourOperatorId
     * @param int $tourPackageId
     * @param int $export
     * @throws Exception
     */
    public function pendingReport(string $fromDate = "", string $toDate = "", $tourOperatorId = 0, int $tourPackageId = 0): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-report.api_response.faild_booking_report_success'))
                ->respond($this->reportRepository->pendingReport($fromDate, $toDate, $tourOperatorId, $tourPackageId));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @param string $fromDate
     * @param string $toDate
     * @param int $tourOperatorId
     * @param int $tourPackageId
     * @param int $export
     * @throws Exception
     */
    public function dailyreport(Request $request, string $fromDate = "", string $toDate = "", int $tourOperatorId = 0, int $tourPackageId = 0, int $export = 0): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-report.api_response.permit_fee_report_success'))
                ->respond($this->reportRepository->dailyreport($request, $fromDate, $toDate, $tourOperatorId, $tourPackageId, $export));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @param string $fromDate
     * @param string $toDate
     * @param int $tourOperatorId
     * @param int $tourPackageId
     * @param int $export
     * @throws Exception
     */
    public function dailyreportdetails(Request $request, string $fromDate = "", string $toDate = "", int $tourOperatorId = 0, int $tourPackageId = 0, int $export = 0): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-report.api_response.permit_fee_details_report_success'))
                ->respond($this->reportRepository->dailyreportdetails($request, $fromDate, $toDate, $tourOperatorId, $tourPackageId, $export));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param string $fromDate
     * @param string $toDate
     * @param int $tourOperatorId
     * @param int $export
     * @throws Exception
     */
    public function emailreport(Request $request, string $fromDate = "", string $toDate = "",  $tourOperatorId = 0, int $tourPackageId = 0): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-report.api_response.email_report_success'))
                ->respond($this->reportRepository->emailreport($request, $fromDate, $toDate, $tourOperatorId, $tourPackageId));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @param string $fromDate
     * @param string $toDate
     * @param int $tourOperatorId
     * @param int $tourPackageId
     * @throws Exception
     */
    public function fareharaborbookingreport(Request $request, string $fromDate = "", string $toDate = "", $tourOperatorId = 0,  $tourPackageId = 0): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-report.api_response.fareharabor_booking_report_success'))
                ->respond($this->reportRepository->fareharaborbookingreport($request, $fromDate, $toDate, $tourOperatorId, $tourPackageId));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @param string $fromDate
     * @param string $toDate
     * @param int $tourOperatorId
     * @param int $tourPackageId
     * @throws Exception
     */
    public function tipreport(Request $request, string $fromDate = "", string $toDate = "", int $tourOperatorId = 0, int $tourPackageId = 0): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-report.api_response.tip_report_success'))
                ->respond($this->reportRepository->tipreport($request, $fromDate, $toDate, $tourOperatorId, $tourPackageId));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @param string $fromDate
     * @param string $toDate
     * @param int $tourOperatorId
     * @param int $tourPackageId
     * @throws Exception
     */
    public function tipSummaryReport(Request $request, string $fromDate = "", string $toDate = "", int $tourOperatorId = 0, int $tourPackageId = 0): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-report.api_response.tip_summary_report_success'))
                ->respond($this->reportRepository->tipsummaryreport($request, $fromDate, $toDate, $tourOperatorId, $tourPackageId));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @param string $fromDate
     * @param string $toDate
     * @param int $tourPackageId
     * @throws Exception
     */
    public function todayAvailabilityList(Request $request, string $fromDate = "", string $toDate = "", int $tourPackageId = 0): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-report.api_response.availability_report_success'))
                ->respond($this->reportRepository->todayavialabilityList($request, $fromDate, $toDate, $tourPackageId));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @param string $fromDate
     * @param string $toDate
     * @param int $tourOperatorId
     * @param int $tourPackageId
     * @throws Exception
     */
    public function invalidBookingsReport(Request $request, string $fromDate = "", string $toDate = "", int $tourOperatorId = 0, int $tourPackageId = 0): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-report.api_response.invalid_booking_report_success'))
                ->respond($this->reportRepository->invalidbookingsreport($request, $fromDate, $toDate, $tourOperatorId, $tourPackageId));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Logic to get the report of closure emails sent logs
     *
     * @param string $fromDate
     * @param string $toDate
     * @param int $tourOperatorId
     * @param int $tourPackageId
     */
    public function lowerAntelopeCanyonClosureReport(string $fromDate = "", string $toDate = "", $tourOperatorId = 0, int $tourPackageId = 0): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-report.api_response.closure_success'))
                ->respond($this->reportRepository->lowerAntelopeCanyonClosureEmailReport($fromDate, $toDate, $tourOperatorId, $tourPackageId));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Logic to get the list of customers from tour operator
     *
     * @param int $tourOperatorId
     * @param int $tourPackageId
     */
    public function getCustomers($tourOperatorId = 0, int $tourPackageId = 0): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-report.api_response.get_customers'))
                ->respond($this->reportRepository->getCustomers($tourOperatorId, $tourPackageId));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Logic to get the invoice Insights Report
     *
     * @param Request $request
     */
    public function invoiceInsightsReport(Request $request)
    {
        try {
            return $this
                ->setMessage(__('tour-report.api_response.invoice_insights_report'))
                ->respond($this->reportRepository->invoiceInsightsReport($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Logic to get the invoice list
     *
     * @param Request $request
     * @param int $customerId
     * @param int $tourOpId
     */
    public function invoicelist(Request $request, $customerId, $tourOpId)
    {
        try {
            return $this
                ->setMessage(__('tour-report.api_response.invoice_list'))
                ->respond($this->reportRepository->invoicelist($request, $customerId, $tourOpId));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * invoice Sum By Status
     *
     * @param Request $request
     */
    public function invoiceSumByStatus(Request $request)
    {
        try {
            return $this
                ->setMessage(__('tour-report.api_response.invoice_sum_by_status'))
                ->respond($this->reportRepository->invoiceSumByStatus($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param TransactionReportRequest $request
     * @throws Exception
     */
    public function transactionReport(TransactionReportRequest $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-report.api_response.transaction_report_success'))
                ->respond($this->reportRepository->transactionReport($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param TransactionSummaryReportRequest $request
     * @throws Exception
     */
    public function transactionSummaryReport(TransactionSummaryReportRequest $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-report.api_response.transaction_summary_report_success'))
                ->respond($this->reportRepository->transactionSummaryReport($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
}
