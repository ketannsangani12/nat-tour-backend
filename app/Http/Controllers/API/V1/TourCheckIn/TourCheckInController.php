<?php

namespace App\Http\Controllers\API\V1\TourCheckIn;

use Exception;
use App\Http\Controllers\API\V1\ApiController;
use App\Exceptions\Core\GeneralCoreException;
use App\Repositories\CheckIn\CheckInRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TourCheckInController extends ApiController
{
    private $checkInRepository;

    /**
     * Init CheckIn Class
     * @return mixed
     */
    public function __construct(CheckInRepository $checkInRepo)
    {
        $this->checkInRepository = $checkInRepo;
    }

    /**
     * getBooking 
     * Return the booking by id embeded in the qr code
     * @param $Bookingid
     * @throws Exception
     * @return JsonResponse
     */
    public function booking($bookingId)
    {
        try {
            $reqName = 'self-check-in.api_response.booking';
            $data = $this->checkInRepository->getBooking($bookingId);
            return $this->setMessage(__($reqName))->respond($data);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * waiver 
     * Return the waiver content for the tour op.
     * @param Request required params ( bookingId, touristNum isAdult )
     * @throws Exception
     * @return JsonResponse
     */
    public function waiver(Request $request)
    {
        try {
            $reqName = 'self-check-in.api_response.waiver';
            $data = $this->checkInRepository->waiver($request);

            return $this->setMessage(__($reqName))->respond($data);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * signWaiver 
     * post waiver form and set check-in status
     * @param Request required 
     * POST BODY EXAMPLE:
     * -----------------------------
     * { "booking_id": 117134,
     *   "tour_operator_id": 1,
     *   "checkins": [{   
     *          "booking_id": "117134",
     *          "tpr_id": 164,
     *          "index": 0,
     *          "check_in_status": "1"
     *      },
     *      { "booking_id": "117134",
     *          "tpr_id": 164,
     *          "index": 1,
     *          "check_in_status": "1"
     *  } ],
     *  "signatures": {
     *       "name": [ "Person One", "Person Two"],
     *       "age": [ 33, 38],
     *       "agree": "on"
     *     }
     * }
     * @throws Exception
     * @return JsonResponse
     */
    public function signWaiver(Request $request)
    {
        try {
            $reqName = 'self-check-in.api_response.signWaiver';
            $waiver = $this->checkInRepository->signwaiver($request->signatures, $request->booking_id);
            $checkin = $this->checkInRepository->bulkCheckIn($request->checkins, $request->tour_operator_id);

            return $this->setMessage(__($reqName))
                ->respond([
                    'waiver' => $waiver,
                    'checkin' => $checkin
                ]);
        } catch (Exception $exception) {
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * checkin post 
     * @param Request required 
     * POST BODY EXAMPLE:
     * -----------------------------
     * { "tour_operator_id": 1,
     *  "checkins": [{ "booking_id": "119090",
     *                "tpr_id": 164,
     *                "index": 0,
     *                "check_in_status": "1"
     *              },
     *              { "booking_id": "119090",
     *                "tpr_id": 164,
     *                "index": 1,
     *                "check_in_status": "1"
     *              }]
     * }
     * @throws Exception
     * @return JsonResponse
     */
    public function checkin(Request $request)
    {
        $reqName = 'self-check-in.api_response.checkin';
        $data = $this->checkInRepository->bulkCheckIn($request->checkins, $request->tour_operator_id);

        return $this->setMessage(__($reqName))->respond($data);
    }

    /**
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function packages(): JsonResponse
    {
        try {
            $reqName = 'check-in.api_response.packages';
            $data = $this->checkInRepository->getPackages();
            return $this->setMessage(__($reqName))->respond($data);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @param  $bookingGroupId
     * @param int $tourPackageId
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function getBooking($bookingGroupId, int $tourPackageId): JsonResponse
    {
        try {
            $reqName = 'check-in.api_response.packages';
            $data = $this->checkInRepository->getBookingDetails($bookingGroupId, $tourPackageId);
            return $this->setMessage(__($reqName))->respond($data);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Redeem ticket POST
     * @param Request required 
     * POST BODY EXAMPLE:
     * -----------------------------
     * { 
     *      "booking_id": 117134,
     *      "tour_operator_id": 1,
     *      "redeem_tickets": [
     *          {   
     *              "tpr_id": 164,
     *              "index": 0,
     *              "status": "Active"
     *          },
     *          { 
     *              "tpr_id": 164,
     *              "index": 1,
     *              "status": "Active"
     *          } 
     *      ]
     * }
     * @throws Exception
     * @return JsonResponse
     */
    public function redeemTickets(Request $request)
    {
        try {
            $reqName = 'check-in.api_response.redeem_tickets';
            $data = $this->checkInRepository->redeemTickets($request);

            return $this->setMessage(__($reqName))->respond($data);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
}
