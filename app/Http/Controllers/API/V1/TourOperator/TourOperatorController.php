<?php

namespace App\Http\Controllers\API\V1\TourOperator;

use App\Http\Controllers\API\V1\ApiController;
use App\Repositories\TourOperator\TourOperatorRepository;
use App\Exceptions\Core\GeneralCoreException;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Http\Requests\API\Staff\StaffRequest;

class TourOperatorController extends ApiController
{
    protected $tourOperatorRepository;

    /**
     * @param  TourOperatorRepository  $tourOperatorRepository
     */
    public function __construct(TourOperatorRepository $tourOperatorRepository)
    {
        $this->tourOperatorRepository = $tourOperatorRepository;
    }

    /**
     * @param  int $id
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function checkIsAffiliate(int $id): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.affiliate_data_success'))
                ->respond($this->tourOperatorRepository->checkIsAffiliate($id));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Get list of tour operator staff
     * @return JsonResponse
     *
     * @param int $id
     * @throws Exception
     */
    public function bookedbyInfo(Request $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.bookedby_info_success'))
                ->respond($this->tourOperatorRepository->bookedbyInfo($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Update details of tour operator staff
     * @return JsonResponse
     *
     * @param StaffRequest $request
     * @param int $id
     * @throws Exception
     */
    public function updateStaffProfile(StaffRequest $request, int $id): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.tour_operator_staff_update_success'))
                ->respond($this->tourOperatorRepository->updateStaffProfile($request, $id));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Update build number details
     * @return JsonResponse
     *
     * @param Request $request
     * @throws Exception
     */
    public function saveBuildNumber(Request $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.tour_operator_staff_update_buildno_success'))
                ->respond($this->tourOperatorRepository->saveBuildNumber($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Provide the stripe keys data to the app
     * @return JsonResponse
     *
     * @param Request $request
     * @throws Exception
     */
    public function getStripeKeys(Request $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.stripe_keys_fetched'))
                ->respond($this->tourOperatorRepository->getStripeKeys());
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
}
