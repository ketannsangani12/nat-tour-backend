<?php

namespace App\Http\Controllers\API\V2;

use App\Models\Transaction;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use App\Services\ReceiptPrint\ReceiptPrintService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReceiptController extends BaseController
{
    protected TourBookingRepository $tourBookingRepository;
    protected ReceiptPrintService $receiptPrintService;

    public function __construct(
        TourBookingRepository $tourBookingRepository,
        ReceiptPrintService $receiptPrintService
    ) {
        $this->tourBookingRepository = $tourBookingRepository;
        $this->receiptPrintService = $receiptPrintService;
    }

    /**
     * Get receipt data for a booking.
     *
     * @param Request $request
     * @param int $bookingId
     * @return JsonResponse
     */
    public function getReceiptData(Request $request, int $bookingId): JsonResponse
    {
        try {
            $vars = $this->tourBookingRepository->booking($bookingId, true);

            if (!isset($vars['bookingM'])) {
                return $this->sendError('Booking not found', [], 404);
            }

            $includeLogo = $request->query('logoBase64') !== 'false';

            // The main booking query excludes custom_charge and tip transactions.
            // Fetch them separately and merge into paymentTransactions for the receipt.
            $paymentTransactions = $vars['paymentTransactions'] ?? collect();
            $additionalTransactions = Transaction::where('tour_booking_id', $bookingId)
                ->where('status', 'Completed')
                ->whereIn('charge_type', ['custom_charge', 'tip'])
                ->orderBy('id', 'DESC')
                ->get();

            if ($additionalTransactions->isNotEmpty()) {
                $paymentTransactions = $paymentTransactions->merge($additionalTransactions)->sortBy('created_at')->values();
            }

            $receiptData = $this->receiptPrintService->getReceiptData(
                $vars['bookingM'],
                $vars['bookingT'],
                $vars['pricedAddons'] ?? [],
                $vars['payLaterAddons'] ?? [],
                $vars['tipAmounts'] ?? [],
                $vars['customChargePrice'] ?? [],
                $vars['paymentOnlyCheck'] ?? 0,
                $vars['Refund_Amount'] ?? 0,
                $paymentTransactions,
                $includeLogo
            );
            return response()->json(["success" => 'true', "code" => 200, "data" => $receiptData], 200);
            //return $this->sendResponse($receiptData, 'Receipt data fetched successfully');
        } catch (Exception $e) {
            Log::error('Get Receipt Data API Error: ' . $e->getMessage());
            return response()->json(["success" => 'false', "code" => 500, "data" => null], 500);
            //return $this->sendError($e->getMessage(), [], 500);
        }
    }
}
