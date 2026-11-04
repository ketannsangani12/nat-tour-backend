<?php

namespace App\Http\Controllers\API\V1\TripAdvisorAPI;

use App\Repositories\GetYourGuide\GetYourGuideRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Exceptions\Core\GeneralCoreException;
use App\Http\Requests\API\GetYourGuide\PackageReservationRequest;
use App\Http\Requests\API\GetYourGuide\PackageReservationCancellationRequest;
use App\Http\Requests\API\GetYourGuide\CreateBookingRequest;
use App\Http\Requests\API\GetYourGuide\CancelBookingRequest;
use App\Http\Requests\API\GetYourGuide\NotificationRequest;
use Illuminate\Support\Facades\Log;
use Exception;

class GetYourGuideAPIController
{
    protected $getYourGuideRepository;

    /**
     * @param  GetYourGuideRepository  $getYourGuideRepository
     */
    public function __construct(GetYourGuideRepository $getYourGuideRepository)
    {
        $this->getYourGuideRepository = $getYourGuideRepository;
    }

    /**
     * Package/Product list API [Get list of packages/tours for supplier]
     * @param int|string $supplierId
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function packageList($supplierId)
    {
        try {
            return $this->getYourGuideRepository->packageList($supplierId);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Package/Product Details API [Get details of package/tour for supplier]
     * @param int|string $productId
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function packageDetails($productId)
    {
        try {
            return $this->getYourGuideRepository->packageDetails($productId);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Package/Product Pricing Details API [Get pricing of package/tour for supplier]
     * @param int|string $productId
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function packagePricing($productId)
    {
        try {
            return $this->getYourGuideRepository->packagePricing($productId);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Package/Product Availability Details API [Get availability of package/tour for supplier]
     * @param Request $request
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function packageAvailability(Request $request)
    {
        try {
            return $this->getYourGuideRepository->packageAvailability($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Package/Product Reservation Details API [Reservation of package/tour for supplier]
     * @param PackageReservationRequest $request
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function packageReservation(PackageReservationRequest $request)
    {
        try {
            return $this->getYourGuideRepository->packageReservation($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Package/Product Cancel Reservation API [Cancel Reservation of package/tour for supplier]
     * @param PackageReservationCancellationRequest $request
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function packageCancelReservation(PackageReservationCancellationRequest $request)
    {
        try {
            return $this->getYourGuideRepository->packageCancelReservation($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Booking Creation API [Create booking for package/tour for supplier]
     * @param CreateBookingRequest $request
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function createBooking(CreateBookingRequest $request)
    {
        try {
            return $this->getYourGuideRepository->createBooking($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Booking Cancellation API [Cancel booking for package/tour for supplier]
     * @param CancelBookingRequest $request
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function cancelBooking(CancelBookingRequest $request)
    {
        try {
            return $this->getYourGuideRepository->cancelBooking($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Notification API [Notify affected product/package and the event for package/tour for supplier]
     * @param NotificationRequest $request
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function notification(NotificationRequest $request)
    {
        try {
            return $this->getYourGuideRepository->notification($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
}