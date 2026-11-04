<?php

namespace App\Http\Controllers\API\V1\Customer;

use App\Http\Controllers\API\V1\ApiController;
use App\Repositories\Customer\CustomerRepository;
use App\Exceptions\Core\GeneralCoreException;
use Exception;
use App\Http\Requests\API\Customer\CustomerRequest;
use App\Http\Requests\API\Customer\CustomerLiabilityWaiverRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CustomerController extends ApiController
{
    protected $customerRepository;

    /**
     * @param  CustomerRepository  $customerRepository
     */
    public function __construct(CustomerRepository $customerRepository)
    {
        $this->customerRepository = $customerRepository;
    }

    /**
     * @param  CustomerRequest  $request
     * @param  int $id
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function updateByAdminOrTourOp(CustomerRequest $request, int $id): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('customer.api_response.customer_update'))
                ->respond($this->customerRepository->updateByAdminOrTourOp($request, $id));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @param  Request $request
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function customers(Request $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('customer.api_response.customers_data_success'))
                ->respond($this->customerRepository->getCustomers($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param int $bookingId, int $touristNum, int $isAdult
     * @throws Exception
     */
    public function getLiabilityWaiver(int $bookingId, int $touristNum, int $isAdult): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.get_liability_waiver'))
                ->respond($this->customerRepository->getLiabilityWaiver($bookingId, $touristNum, $isAdult));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @param  CustomerLiabilityWaiverRequest  $request
     * @param  int $id
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function updateLiabilityWaiver(CustomerLiabilityWaiverRequest $request, int $id): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('customer.api_response.customer_liablity_waiver_success'))
                ->respond($this->customerRepository->updateLiabilityWaiver($request, $id));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
}
