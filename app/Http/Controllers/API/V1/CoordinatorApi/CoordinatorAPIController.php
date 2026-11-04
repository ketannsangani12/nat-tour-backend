<?php

namespace App\Http\Controllers\API\V1\CoordinatorApi;

use App\Http\Controllers\API\V1\ApiController;
use App\Repositories\Coordinator\CoordinatorRepository;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Exceptions\Core\GeneralCoreException;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Http\Requests\API\Coordinator\AddBookingRequest;
use App\Http\Requests\API\Coordinator\BookingAssignedRequest;
use App\Http\Requests\API\Coordinator\TourGuideAssingedBookingListRequest;
use App\Http\Requests\API\Coordinator\MoveBookingRequest;
use App\Http\Requests\API\Coordinator\AssignedBookingDeleteRequest;
use App\Http\Requests\API\Coordinator\AddTourGuide;
use App\Http\Requests\API\Coordinator\UpdateTourGuide;
use App\Http\Requests\API\Coordinator\TourGuideStatus;

class CoordinatorAPIController extends ApiController
{
    protected $coordinatorRepository;

    /**
     * @param  CoordinatorRepository  $coordinatorRepository
     */
    public function __construct(CoordinatorRepository $coordinatorRepository)
    {
        $this->coordinatorRepository = $coordinatorRepository;
    }

    /**
     * Provide the add Booking details with tour guide list
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function addBooking(AddBookingRequest $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('coordinator.api_response.add_booking_success'))
                ->respond($this->coordinatorRepository->addBooking($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Assigned the booking to the tour guide
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function bookingAssigned(BookingAssignedRequest $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('coordinator.api_response.booking_assigned_success'))
                ->respond($this->coordinatorRepository->bookingAssigned($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Tour guide's assigned booking list 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function tourGuideAssingedBookingList(TourGuideAssingedBookingListRequest $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('coordinator.api_response.tour_guide_assinged_booking_list_success'))
                ->respond($this->coordinatorRepository->tourGuideAssingedBookingList($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Tour guide's move bookings to an onther tour guide 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function moveBooking(MoveBookingRequest $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('coordinator.api_response.tour_guide_move_booking_success'))
                ->respond($this->coordinatorRepository->moveBooking($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Tour guide's move bookings to an onther tour guide 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function assignedBookingDelete(AssignedBookingDeleteRequest $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('coordinator.api_response.tour_guide_assigned_booking_delete_success'))
                ->respond($this->coordinatorRepository->assignedBookingDelete($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * View and print the invoice of booking 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function bookingInvoiceForTourGuide($bookingId): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('coordinator.api_response.tour_guide_booking_invoice_for_tour_guide_success'))
                ->respond($this->coordinatorRepository->bookingInvoiceForTourGuide($bookingId));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * get tour packages for coordinator
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function getTourPackagesForCoordinator($tourOperatorId): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('coordinator.api_response.get_tour_packages_for_coordinator_success'))
                ->respond($this->coordinatorRepository->getTourPackagesForCoordinator($tourOperatorId));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * get DateTime 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function getDateTime($tourOperatorId, $tourPackageId, $date): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('coordinator.api_response.co_ordinator_get_date_time_success'))
                ->respond($this->coordinatorRepository->getDateTime($tourOperatorId, $tourPackageId, $date));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * get Tour Guide 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function getTourGuide($tourOperatorId, $tourPackageId, $timeSlotId): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('coordinator.api_response.get_tour_guide_success'))
                ->respond($this->coordinatorRepository->getTourGuide($tourOperatorId, $tourPackageId, $timeSlotId));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Add New Tour Guide  
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function addTourGuide(AddTourGuide $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('coordinator.api_response.add_tour_guide_success'))
                ->respond($this->coordinatorRepository->addTourGuide($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Tour Guide Update 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function updateTourGuide(UpdateTourGuide $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('coordinator.api_response.update_tour_guide_success'))
                ->respond($this->coordinatorRepository->updateTourGuide($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Tour Guide List
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function tourGuideList(Request $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('coordinator.api_response.tour_guide_list_success'))
                ->respond($this->coordinatorRepository->tourGuideList($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Tour Guide Status Update (Active / Inactive) 
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function tourGuideStatusUpdate(TourGuideStatus $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('coordinator.api_response.tour_guide_status_update_success'))
                ->respond($this->coordinatorRepository->tourGuideStatusUpdate($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
}
