<?php

namespace App\Http\Controllers\API\V1\TourSlot;

use App\Http\Controllers\API\V1\ApiController;
use App\Http\Requests\API\TourSlot\TourSlotRequest;
use App\Repositories\TourSlot\TourSlotRepository;
use App\Transformers\TourSlotResource;
use App\Exceptions\Core\GeneralCoreException;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Repositories\TourPackageRate\TourPackageRateRepository;

class TourSlotController extends ApiController
{
    protected $tourSlotRepository, $tourPackageRateRepository;

    /**
     * @param  TourSlotRepository  $tourSlotRepository
     * @param  TourPackageRateRepository  $tourPackageRateRepository
     */
    public function __construct(TourSlotRepository $tourSlotRepository,TourPackageRateRepository $tourPackageRateRepository)
    {
        $this->tourSlotRepository = $tourSlotRepository;
        $this->tourPackageRateRepository = $tourPackageRateRepository;
    }

    /**
     * @param  TourSlotRequest  $request
     * @return JsonResponse
     *
     * @throws GeneralCoreException
     */
    public function getTourSlotTimesByDate(TourSlotRequest $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tourslot.api_response.tourslot_by_date'))
                ->respond(new TourSlotResource($this->tourSlotRepository->getTourSlotTimesByDate($request)));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @param  int $timeSlotId
     * @return JsonResponse
     *
     * @throws GeneralCoreException
     */
    public function timeSlotBookingsInfo(int $timeSlotId): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tourslot.api_response.tourslot_time_bookings_info'))
                ->respond($this->tourSlotRepository->timeSlotBookingsInfo($timeSlotId));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @param  int $timeSlotId
     * @return JsonResponse
     *
     * @throws GeneralCoreException
     */
    public function timeSlotTouristsInfo(int $timeSlotId): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tourslot.api_response.tourslot_time_tourists_info'))
                ->respond($this->tourSlotRepository->timeSlotTouristsInfo($timeSlotId));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Logic to send the emails to the users when tour closed because of some reasons
     * 
     * @param Request $request
     */
    public function lowerAntelopeCanyonClosureEmail(Request $request)
    {
        try {
            return $this
                ->setMessage(__('tourslot.api_response.closure_email_send_process_started'))
                ->respond($this->tourSlotRepository->lowerAntelopeCanyonClosureEmailSendProcess($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
	 * Get tour rates by time slot
	 * @param Request $request
	 */
	public function getTourRatesBySlot(Request $request)
	{
		try {
			$data = $request->all();
            return $this
                ->setMessage(__('tourslot.api_response.tourrates_by_slot'))
                ->respond($this->tourPackageRateRepository->getTourRatesBySlot($data));
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

}
