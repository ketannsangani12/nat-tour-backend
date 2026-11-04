<?php

namespace App\Http\Controllers\API\V1\TourPackage;

use App\Http\Controllers\API\V1\ApiController;
use App\Repositories\TourPackage\TourPackageRepository;
use App\Exceptions\Core\GeneralCoreException;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Models\TourPackageForm;
use App\Transformers\PackageFormResource;
use App\Transformers\AddonSubmittedFormResource;
use Illuminate\Http\Request;
use App\Transformers\AddonUpdatedFormResource;


class TourPackageController extends ApiController
{
    protected $tourPackageRepository;

    /**
     * @param  TourPackageRepository  $tourPackageRepository
     */
    public function __construct(TourPackageRepository $tourPackageRepository)
    {
        $this->tourPackageRepository = $tourPackageRepository;
    }

    /**
     * @param  int $id
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function packages(int $id): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-package.api_response.packages_data_success'))
                ->respond($this->tourPackageRepository->getPackages($id));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @param  int $tourPackageId
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function getPackageForm($tourPackageId)
    {
        try {
            return $this
                ->setMessage(__('tour-package.api_response.packages_data_success'))
                ->respond(new PackageFormResource($this->tourPackageRepository->getCustomForm($tourPackageId)));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @param  int $bookingId
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function getPackageSubmittedForm($bookingId)
    {
        try {
            return $this
                ->setMessage(__('tour-package.api_response.packages_data_success'))
                ->respond(new AddonSubmittedFormResource($this->tourPackageRepository->getSubmittedCustomForm($bookingId)));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function updateSubmittedForm(Request $request)
    {
        try {
            return $this
                ->setMessage(__('tour-package.api_response.packages_data_update_success'))
                ->respond(new AddonUpdatedFormResource($this->tourPackageRepository->updateSubmittedCustomForm($request)));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

}
