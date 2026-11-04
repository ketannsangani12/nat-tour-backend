<?php

namespace App\Repositories\Network;

use App\Repositories\Repository;
use App\Services\Network\NetworkService;

class NetworkRepository extends Repository
{
    protected NetworkService $networkService;

    /**
     * Inject NetworkService dependency.
     */
    public function __construct(NetworkService $networkService)
    {
        $this->networkService = $networkService;
    }

    /**
     * Fetch tenants for the current network.
     *
     * @return array
     */
    public function getTenants(): array
    {
        return $this->networkService->getTenants();
    }

    /**
     * Fetch packages for the current network.
     *
     * @return array
     */
    public function getPackages(): array
    {
        return $this->networkService->getPackages();
    }

    /**
     * Fetch a specific package from a connected tenant.
     *
     * @param string $tenantId
     * @param int $packageId
     *
     * @return array
     */
    public function getPackageDetails(string $tenantId, int $packageId): array
    {
        return $this->networkService->getPackageDetails(tenantId: $tenantId, packageId: $packageId);
    }

    /**
     * Fetch available time slots for a specific package.
     *
     * @param string $tenantId
     * @param int $packageId
     * @param string $requestDate
     *
     * @return array
     */
    public function getTimeSlots(string $tenantId, int $packageId, string $requestDate): array
    {
        return $this->networkService->getTimeSlots(tenantId: $tenantId, packageId: $packageId, requestDate: $requestDate);
    }

    /**
     * Fetch available rate groups for a specific package.
     *
     * @param string $tenantId
     * @param int $packageId
     * @param string $requestDate
     * @param int|null $slotTimeId
     *
     * @return array
     */
    public function getRateGroups(string $tenantId, int $packageId, string $requestDate, ?int $slotTimeId = null): array
    {
        return $this->networkService->getRateGroups(tenantId: $tenantId, packageId: $packageId, requestDate: $requestDate, slotTimeId: $slotTimeId);
    }

    /**
     * Fetch custom form for a specific package.
     *
     * @param string $tenantId
     * @param int $packageId
     *
     * @return array
     */
    public function getCustomForm(string $tenantId, int $packageId): array
    {
        return $this->networkService->getCustomForm(tenantId: $tenantId, packageId: $packageId);
    }

    /**
     * Set coupon for a specific package.
     *
     * @param string $tenantId
     * @param int $packageId
     * @param string $coupon
     * @param string $requestDate
     * @param int|null $tourists Optional tourist count (infants excluded), for enforcing the
     *                           promotion's own min_allowed_tourists.
     *
     * @return array
     */
    public function setCoupon(string $tenantId, int $packageId, string $coupon, string $requestDate, ?int $tourists = null): array
    {
        return $this->networkService->setCoupon(tenantId: $tenantId, packageId: $packageId, coupon: $coupon, requestDate: $requestDate, tourists: $tourists);
    }

    /**
     * Create payment intent
     * 
     * @param array $request
     * 
     * @return array
     */
    public function createPaymentIntent($request): array
    {
        return $this->networkService->createPaymentIntent(request: $request);
    }

    /**
     * Create bookings
     * 
     * @param array $request
     * 
     * @return array
     */
    public function createBookings($request): array
    {
        return $this->networkService->createBookings(request: $request);
    }
}
