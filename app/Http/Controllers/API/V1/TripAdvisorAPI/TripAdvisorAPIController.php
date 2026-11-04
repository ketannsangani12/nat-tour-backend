<?php

namespace App\Http\Controllers\API\V1\TripAdvisorAPI;

use App\Repositories\TripAdvisor\TripAdvisorRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Exceptions\Core\GeneralCoreException;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\API\TripAdvisor\TourListRequest;
use App\Http\Requests\API\TripAdvisor\BatchAvailabilityRequest;
use App\Http\Requests\API\TripAdvisor\RealTimeAvailabilityRequest;
use App\Http\Requests\API\TripAdvisor\CreateBookingRequest;
use App\Http\Requests\API\TripAdvisor\CancelBookingRequest;
use App\Http\Requests\API\TripAdvisor\AmendmentBookingRequest;
use App\Http\Requests\API\TripAdvisor\ProductSearchRequest;
use App\Http\Requests\API\TripAdvisor\TourAvailabilityCheckRequest;
use App\Http\Requests\API\TripAdvisor\SupplierInformationRequest;
use App\Http\Requests\API\TripAdvisor\GetAttractionsRequest;
use App\Http\Requests\API\TripAdvisor\ProductBulkDetailsRequest;
use App\Http\Requests\API\TripAdvisor\AvailabilityScheduleBulkRequest;
use Exception;

class TripAdvisorAPIController
{
    protected $tripAdvisorRepository;

    /**
     * @param  TripAdvisorRepository  $tripAdvisorRepository
     */
    public function __construct(TripAdvisorRepository $tripAdvisorRepository)
    {
        $this->tripAdvisorRepository = $tripAdvisorRepository;
    }

    /**
     * Tour list API [Enables suppliers to provide a list of available tours]
     * @param TourListRequest $request
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function tourList(TourListRequest $request)
    {
        try {
            return $this->tripAdvisorRepository->tourListRequest($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Batch availability API [Enables Viator to the determine availability in bulk scale]
     * @param BatchAvailabilityRequest $request
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function batchAvailability(BatchAvailabilityRequest $request)
    {
        try {
            return $this->tripAdvisorRepository->batchAvailabilityRequest($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Real-time availability API [To determine if a tour (with or without specifying a particular tour option) is availabile given a specific number of passengers on a certain date – or, across a range of dates]
     * @param RealTimeAvailabilityRequest $request
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function realTimeAvailability(RealTimeAvailabilityRequest $request)
    {
        try {
            return $this->tripAdvisorRepository->realTimeAvailabilityRequest($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Booking API [Enables suppliers to receive booking requests from Viator systems in real time]
     * @param CreateBookingRequest $request
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function createBooking(CreateBookingRequest $request)
    {
        try {
            return $this->tripAdvisorRepository->createBookingRequest($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Booking Amendment API [Enables suppliers to receive amendment requests to confirmed bookings from Viator systems]
     * @param AmendmentBookingRequest $request
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function amendmentBooking(AmendmentBookingRequest $request)
    {
        try {
            return $this->tripAdvisorRepository->amendmentBookingRequest($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Booking cancellation API [Cancel previously confirmed bookings directly with suppliers]
     * @param CancelBookingRequest $request
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function cancelBooking(CancelBookingRequest $request)
    {
        try {
            return $this->tripAdvisorRepository->cancelBookingRequest($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * productTags API [To get all tags that are related to tour packages]
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function productTags()
    {
        try {
            return $this->tripAdvisorRepository->productTagsRequest();
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * getDestinations API [To get all destinations such as country taxonomy/city nodes as a flat list]
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function getDestinations()
    {
        try {
            return $this->tripAdvisorRepository->getDestinationsRequest();
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @param  ProductSearchRequest $request
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function productSearch(ProductSearchRequest $request): JsonResponse
    {
        try {
            return $this->tripAdvisorRepository->productSearchRequest($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * getPackageDetails API [Get full product details for a single product]
     * @param string $productCode
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function getPackageDetails(string $productCode)
    {
        try {
            return $this->tripAdvisorRepository->getPackageDetailsRequest($productCode);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * getBookingQuestions API [Get full details of all available preset booking questions]
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function getBookingQuestions()
    {
        try {
            return $this->tripAdvisorRepository->getBookingQuestionsRequest();
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * checkAvailability API [Check real-time availability]
     * @param TourAvailabilityCheckRequest $request
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function checkAvailability(TourAvailabilityCheckRequest $request): JsonResponse
    {
        try {
            return $this->tripAdvisorRepository->checkAvailabilityRequest($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * availabilityScheduleBulk API [Get availability and pricing details for all product options of all requested products]
     * @param AvailabilityScheduleBulkRequest $request
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function availabilityScheduleBulk(AvailabilityScheduleBulkRequest $request): JsonResponse
    {
        try {
            return $this->tripAdvisorRepository->availabilityScheduleBulkRequest($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * searchFreetext API [Perform a search for products, attractions and/or destinations]
     * @param Request $request
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function searchFreetext(Request $request): JsonResponse
    {
        try {
            return $this->tripAdvisorRepository->searchFreetextRequest($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * supplierInformation API [Gets a collection of supplier information objects for the provided products]
     * @param SupplierInformationRequest $request
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function supplierInformation(SupplierInformationRequest $request): JsonResponse
    {
        try {
            return $this->tripAdvisorRepository->supplierInformationRequest($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * modifiedProduct API [Get full product details for all products modified since a specified time]
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function modifiedProduct(): JsonResponse
    {
        try {
            return $this->tripAdvisorRepository->modifiedProductRequest();
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * getAttractions API [Retrieves a list of attractions]
     * @param GetAttractionsRequest $request
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function getAttractions(GetAttractionsRequest $request): JsonResponse
    {
        try {
            return $this->tripAdvisorRepository->getAttractionsRequest($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * productBulkDetails API [Get full product details for all requested products]
     * @param ProductBulkDetailsRequest $request
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function productBulkDetails(ProductBulkDetailsRequest $request): JsonResponse
    {
        try {
            return $this->tripAdvisorRepository->productBulkDetailsRequest($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * availabilitySchedule API [Get availability and pricing details for all product options of the requested product]
     * @param string $productCode
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function availabilitySchedule(string $productCode)
    {
        try {
            return $this->tripAdvisorRepository->availabilityScheduleRequest($productCode);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * availabilityScheduleModifiedData API [Get full availability details for all products modified since the specified time]
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function availabilityScheduleModifiedData()
    {
        try {
            return $this->tripAdvisorRepository->availabilityScheduleModifiedDataRequest();
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * bookingsHold API [Used to check either the price or availability (or both) of the product will be retained until a booking request is made]
     * @param Request $request
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function bookingsHold(Request $request)
    {
        try {
            return $this->tripAdvisorRepository->bookingsHoldRequest($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * productBooking API [Requests a booking for a product]
     * @param Request $request
     * 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function productBooking(Request $request)
    {
        try {
            return $this->tripAdvisorRepository->productBookingRequest($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
}
