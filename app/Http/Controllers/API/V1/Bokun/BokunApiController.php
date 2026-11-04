<?php

namespace App\Http\Controllers\API\V1\Bokun;

use App\Repositories\Bokun\BokunRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Exceptions\Core\GeneralCoreException;
// use App\Http\Requests\API\GetYourGuide\PackageReservationRequest;
use Illuminate\Support\Facades\Log;
use Exception;

class BokunApiController
{
    protected $bokunRepository;

    /**
     * @param  BokunRepository  $bokunRepository
     */
    public function __construct(BokunRepository $bokunRepository)
    {
        $this->bokunRepository = $bokunRepository;
    }

    /**
     * Package/Product list API [Get list of packages/tours for supplier]
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function pluginDefinition()
    {
        try {
            return $this->bokunRepository->pluginDefinition();
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Return information about products
     * @param Request $request
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function searchProduct(Request $request)
    {
        try {
            return $this->bokunRepository->searchProduct($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Retrieve detailed information about given product by ID (Bokun Product ID)
     * @param Request $request
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function productDetailsById(Request $request)
    {
        try {
            return $this->bokunRepository->productDetailsById($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * A set of product ids provided, return their availability over given date range
     * @param Request $request
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function productGetAvailable(Request $request)
    {
        try {
            return $this->bokunRepository->productGetAvailable($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Get availability of a particular product over a date range
     * @param Request $request
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function getProductAvailability(Request $request)
    {
        try {
            return $this->bokunRepository->getProductAvailability($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * This call secures necessary resource(s), such as activity time slot which can later become a booking.
     * @param Request $request
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function bookingReservation(Request $request)
    {
        try {
            return $this->bokunRepository->bookingReservation($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * This call allows the reseller to indicate that previously made reservation should be cancelled.
     * @param Request $request
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function bookingCancelReservation(Request $request)
    {
        try {
            return $this->bokunRepository->bookingCancelReservation($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * This call is to proceed with the booking.
     * @param Request $request
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function bookingConfirmation(Request $request)
    {
        try {
            return $this->bokunRepository->bookingConfirmation($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * This call is to proceed with the cancel booking
     * @param Request $request
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function bookingCancellation(Request $request)
    {
        try {
            return $this->bokunRepository->bookingCancellation($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * This call is to proceed with the amendment booking [Reschedule | Add People | Delete People]
     * @param Request $request
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function bookingAmendment(Request $request)
    {
        try {
            return $this->bokunRepository->bookingAmendment($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
}