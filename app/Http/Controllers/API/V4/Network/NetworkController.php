<?php

namespace App\Http\Controllers\API\V4\Network;

use App\Http\Controllers\API\V4\ApiController;
use Illuminate\Support\Facades\Log;
use App\Repositories\Network\NetworkRepository;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NetworkController extends ApiController
{
    protected NetworkRepository $networkRepository;

    /**
     * Inject NetworkRepository dependency.
     */
    public function __construct(NetworkRepository $networkRepository)
    {
        $this->networkRepository = $networkRepository;
    }

    /**
     * Fetch active tenants linked to the current network.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTenants(): JsonResponse
    {
        try {
            $tenants = $this->networkRepository->getTenants();
            return $this->sendSuccess($tenants, 'Tenants fetched successfully');
        } catch (Exception $e) {
            Log::error('Get Tenants API Error: ' . $e->getMessage());
            return $this->sendError($e->getMessage(), $e->getCode());
        }
    }

    /**
     * Fetch available packages from connected tenants.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPackages(): JsonResponse
    {
        try {
            $packages = $this->networkRepository->getPackages();
            return $this->sendSuccess($packages, 'Packages fetched successfully');
        } catch (Exception $e) {
            Log::error('Get Packages API Error: ' . $e->getMessage());
            return $this->sendError($e->getMessage(), $e->getCode());
        }
    }

    /**
     * Fetch a specific package from a connected tenant.
     *
     * @param string $tenantId
     * @param int $packageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPackageDetails(string $tenantId, int $packageId): JsonResponse
    {
        try {
            $package = $this->networkRepository->getPackageDetails(tenantId: $tenantId, packageId: $packageId);
            return $this->sendSuccess($package, 'Package fetched successfully');
        } catch (Exception $e) {
            Log::error('Get Package API Error: ' . $e->getMessage());
            return $this->sendError($e->getMessage(), $e->getCode());
        }
    }

    /**
     * Fetch available time slots for a specific package.
     *
     * @param \Illuminate\Http\Request $request
     * @param string $tenantId
     * @param int $packageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTimeSlots(Request $request, string $tenantId, int $packageId): JsonResponse
    {
        try {
            $request->validate([
                'date' => 'required|date',
            ]);
            $timeSlots = $this->networkRepository->getTimeSlots(tenantId: $tenantId, packageId: $packageId, requestDate: $request->date);
            return $this->sendSuccess($timeSlots, 'Time slots fetched successfully');
        } catch (Exception $e) {
            Log::error('Get Time Slots API Error: ' . $e->getMessage());
            return $this->sendError($e->getMessage(), $e->getCode());
        }
    }

    /**
     * Fetch available rate groups for a specific package.
     *
     * @param \Illuminate\Http\Request $request
     * @param string $tenantId
     * @param int $packageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRateGroups(Request $request, string $tenantId, int $packageId): JsonResponse
    {
        try {
            $request->validate([
                'date' => 'required|date',
                'slot_time_id' => 'integer',
            ]);
            $timeSlots = $this->networkRepository->getRateGroups(tenantId: $tenantId, packageId: $packageId, requestDate: $request->date, slotTimeId: $request->slot_time_id ?? null);
            return $this->sendSuccess($timeSlots, 'Rate groups fetched successfully');
        } catch (Exception $e) {
            Log::error('Get Rate Groups API Error: ' . $e->getMessage());
            return $this->sendError($e->getMessage(), $e->getCode());
        }
    }

    /**
     * Fetch custom form for a specific package.
     *
     * @param string $tenantId
     * @param int $packageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCustomForm(string $tenantId, int $packageId): JsonResponse
    {
        try {
            $customForm = $this->networkRepository->getCustomForm(tenantId: $tenantId, packageId: $packageId);
            return $this->sendSuccess($customForm, 'Custom form fetched successfully');
        } catch (Exception $e) {
            Log::error('Get Custom Form API Error: ' . $e->getMessage());
            return $this->sendError($e->getMessage(), $e->getCode());
        }
    }

    /**
     * Set coupon for a specific package.
     *
     * @param \Illuminate\Http\Request $request
     * @param string $tenantId
     * @param int $packageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function setCoupon(Request $request, string $tenantId, int $packageId): JsonResponse
    {
        try {
            $request->validate([
                'coupon' => 'required|string',
                'date' => 'required|date',
                // Optional -- partner integrations don't send this yet, so min_allowed_tourists
                // enforcement only activates once a caller actually supplies it.
                'tourists' => 'nullable|integer|min:0',
            ]);
            $coupon = $this->networkRepository->setCoupon(
                tenantId: $tenantId,
                packageId: $packageId,
                coupon: $request->coupon,
                requestDate: $request->date,
                tourists: $request->filled('tourists') ? (int) $request->tourists : null
            );
            return $this->sendSuccess($coupon, 'Coupon set successfully');
        } catch (Exception $e) {
            Log::error('Set Coupon API Error: ' . $e->getMessage());
            return $this->sendError($e->getMessage(), $e->getCode());
        }
    }

    /**
     * Create payment intent
     * 
     * @param \Illuminate\Http\Request $request
     * 
     * @return array
     */
    public function createPaymentIntent(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:0',
                'currency' => 'required|string|in:USD',
            ]);
            $paymentIntent = $this->networkRepository->createPaymentIntent(request: $request->all());
            return $this->sendSuccess($paymentIntent, 'Payment intent created successfully');
        } catch (Exception $e) {
            Log::error('Create Payment Intent API Error: ' . $e->getMessage());
            return $this->sendError($e->getMessage(), $e->getCode());
        }
    }

    /**
     * Check availability for a specific package.
     *
     * @param \Illuminate\Http\Request $request
     * @param string $tenantId
     * @param int $packageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkAvailability(Request $request, string $tenantId, int $packageId): JsonResponse
    {
        try {
            $request->validate([
                'date' => 'required|date',
                'slot_time_id' => 'integer',
            ]);
            $timeSlots = $this->networkRepository->getTimeSlots(tenantId: $tenantId, packageId: $packageId, requestDate: $request->date);
            return $this->sendSuccess($timeSlots, 'Availability checked successfully');
        } catch (Exception $e) {
            Log::error('Check Availability API Error: ' . $e->getMessage());
            return $this->sendError($e->getMessage(), $e->getCode());
        }
    }

    /**
     * Create bookings
     * 
     * @param \Illuminate\Http\Request $request
     * 
     * @return array
     */
    public function createBookings(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'cartItems' => 'required|array|min:1',
                'customerInfo' => 'required|array',
                'totalAmount' => 'required|numeric|min:0',
                'paymentMethod' => 'required|string|in:Card',
                'bookingId' => 'required|string',
                'bookingDate' => 'required|date',
            ]);

            $timeSlots = $this->networkRepository->createBookings(request: $request->all());
            return $this->sendSuccess($timeSlots, 'Bookings created successfully');
        } catch (Exception $e) {
            Log::error('Create Bookings API Error: ' . $e->getMessage());
            return $this->sendError($e->getMessage(), $e->getCode());
        }
    }
}
