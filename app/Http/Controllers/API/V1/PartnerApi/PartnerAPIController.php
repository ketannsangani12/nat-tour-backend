<?php

namespace App\Http\Controllers\API\V1\PartnerApi;

use Illuminate\Http\JsonResponse;

use App\Exceptions\Core\GeneralCoreException;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

use App\Repositories\PartnerAPI\PartnerApiRepository;
use App\Helpers\OctoErrorResponse;
use App\Http\Requests\API\PartnerApi\TourOperatorsRequest;
use App\Http\Requests\API\PartnerApi\ApiInfoRequest;
use App\Http\Requests\API\PartnerApi\TourListRequest;
use App\Http\Requests\API\PartnerApi\RateGroupsRequest;
use App\Http\Requests\API\PartnerApi\BatchAvailabilityRequest;
use App\Http\Requests\API\PartnerApi\TimeSlotRequest;
use App\Http\Requests\API\PartnerApi\BookingListRequest;

use App\Http\Requests\API\PartnerApi\RealTimeAvailabilityRequest;
use App\Http\Requests\API\PartnerApi\CreateBookingRequest;
use App\Http\Requests\API\PartnerAPI\CancelBookingRequest;


use App\Http\Requests\API\TripAdvisor\SupplierInformationRequest;
use App\Http\Requests\API\TripAdvisor\GetAttractionsRequest;
use App\Http\Requests\API\TripAdvisor\ProductBulkDetailsRequest;
use App\Http\Requests\API\TripAdvisor\AvailabilityScheduleBulkRequest;
use Exception;

class PartnerAPIController extends Controller
{
    protected $partnerApiRepository;

    /**
     * @param  PartnerApiRepository  $partnerApiRepository
     */
    public function __construct(Request $request, PartnerApiRepository $partnerApiRepository)
    {       
            // this is not working as expected, so we are commenting it out for now
            // Uncomment the following lines if you need to handle tenant-specific database connections
            // $tenant = $request['data']['SupplierId'] ?? NULL;
            
            // if ($tenant) {
            //     $db = "tenant_" . trim(strtolower($tenant));
            //     config(['database.connections.master.database' => $db]);
            //     DB::purge('master');
            //     DB::reconnect('master');

            // }

        $this->partnerApiRepository = $partnerApiRepository;
    }

    public function apiInfo(ApiInfoRequest $request)
    {
        return response()->json([
            'code' => 200,
            'message' => 'Welcome to the Partner API',
            'data' => [
                'version' => '1.0',
                'status' => 'active',
                'endpoints' => [
                    'apiInfo' => route('api.v1.partner-api.apiInfo'),
                    'tourOperators' => route('api.v1.partner-api.tourOperators'),
                    'tourList' => route('api.v1.partner-api.tourList'),
                    'bookingList' => route('api.v1.partner-api.bookingList'),
                    'rateGroups' => route('api.v1.partner-api.rateGroups'),
                    'timeSlots' => route('api.v1.partner-api.timeSlots'),
                    'batchAvailability' => route('api.v1.partner-api.batchAvailability'),
                    'realTimeAvailability' => route('api.v1.partner-api.realTimeAvailability'),
                    'createBooking' => route('api.v1.partner-api.createBooking'),
                    'cancelBooking' => route('api.v1.partner-api.cancelBooking')
                ]
            ]
        ]);
    }


    /**
     * Tour operators API [Enables suppliers to provide a list of tour operators]
     * @param TourOperatorsRequest $request
     *
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function tourOperators(TourOperatorsRequest $request)
    {
        try {
            return $this->partnerApiRepository->tourOperatorsRequest($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
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
            return $this->partnerApiRepository->tourListRequest($request);
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
            return $this->partnerApiRepository->batchAvailabilityRequest($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }


    /**
     * List Rate Groups API [Enables suppliers to provide a list of rate groups for a specific tour package]
     * @param RateGroupsRequest $request
     *
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function rateGroups(RateGroupsRequest $request)
    {
        try {
            return $this->partnerApiRepository->rateGroupsRequest($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Summary of timeSlots
     * @param TimeSlotRequest $request
     */
    public function timeSlots(TimeSlotRequest $request)
    {
        try {
            return $this->partnerApiRepository->timeSlotsRequest($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * List bookings API [Enables suppliers to provide a list of bookings]
     * @param BookingListRequest $request
     *
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function bookingList(BookingListRequest $request)
    {
        try {
            return $this->partnerApiRepository->bookingListRequest($request);
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
            return $this->partnerApiRepository->realTimeAvailabilityRequest($request);
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
            return $this->partnerApiRepository->createBookingRequest($request);
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
            return $this->partnerApiRepository->cancelBookingRequest($request);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Deployment version check.
     *
     * Lightweight, unauthenticated endpoint so the currently deployed build can
     * be verified quickly. Reports the configured version, the git commit and
     * branch, the environment, and the server time.
     *
     * The commit is taken from the APP_GIT_COMMIT config value when present
     * (recommended for deploys, since it survives `config:cache`); otherwise it
     * falls back to reading the local .git metadata.
     *
     * @return JsonResponse
     */
    public function version(): JsonResponse
    {
        //[$commit, $branch] = $this->resolveGitInfo();

        return response()->json([
            'code' => 200,
            'message' => 'Partner API version',
            'data' => [
                'version'     => config('app.version'),
                'environment' => app()->environment(),
                'php'         => PHP_VERSION,
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Best-effort resolution of the deployed git commit and branch.
     *
     * Prefers the APP_GIT_COMMIT config value; otherwise reads .git/HEAD and the
     * referenced ref (including the packed-refs fallback). Any failure returns
     * nulls rather than throwing, since git metadata may be absent in a build.
     *
     * @return array{0: ?string, 1: ?string} [commit, branch]
     */
    private function resolveGitInfo(): array
    {
        $branch = null;
        $commit = config('app.git_commit') ?: null;

        try {
            $gitDir = base_path('.git');
            $head = @file_get_contents($gitDir . '/HEAD');
            if ($head === false) {
                return [$commit, $branch];
            }
            $head = trim($head);

            if (str_starts_with($head, 'ref: ')) {
                $ref = substr($head, 5);
                $branch = basename($ref);

                if ($commit === null) {
                    $refPath = $gitDir . '/' . $ref;
                    if (is_file($refPath)) {
                        $commit = trim((string) @file_get_contents($refPath));
                    } else {
                        // Packed refs fallback: scan .git/packed-refs for the ref.
                        $packed = @file_get_contents($gitDir . '/packed-refs');
                        if ($packed !== false) {
                            foreach (preg_split('/\R/', $packed) as $line) {
                                if (str_ends_with($line, ' ' . $ref)) {
                                    $commit = trim(strtok($line, ' '));
                                    break;
                                }
                            }
                        }
                    }
                }
            } elseif ($commit === null) {
                // Detached HEAD: the file holds the raw commit hash.
                $commit = $head;
            }
        } catch (Exception $exception) {
            // Git metadata is best-effort; ignore and return what we have.
        }

        return [$commit ?: null, $branch];
    }

    // ── OCTO-shaped Partner endpoints (cross-tenant packages) ─────────────────
    // Thin wrappers that delegate to PartnerApiRepository and return OCTO-shaped
    // error envelopes on failure (matching the reused OCTO responses).

    public function getSupplier(Request $request): JsonResponse
    {
        try {
            return $this->partnerApiRepository->getSupplier($request);
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    public function getProductList(Request $request): JsonResponse
    {
        try {
            return $this->partnerApiRepository->getProductList($request);
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    public function getProductById(Request $request, string $productId): JsonResponse
    {
        try {
            return $this->partnerApiRepository->getProductById($request, $productId);
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    public function availabilityCalendar(Request $request): JsonResponse
    {
        try {
            return $this->partnerApiRepository->availabilityCalendar($request);
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    public function availabilityCheck(Request $request): JsonResponse
    {
        try {
            return $this->partnerApiRepository->availabilityCheck($request);
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    public function getBookingList(Request $request): JsonResponse
    {
        try {
            return $this->partnerApiRepository->getBookingList($request);
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    public function getBookingByUuid(Request $request, string $uuid): JsonResponse
    {
        try {
            return $this->partnerApiRepository->getBookingByUuid($request, $uuid);
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    public function bookingReservation(Request $request): JsonResponse
    {
        try {
            return $this->partnerApiRepository->bookingReservation($request);
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    public function bookingConfirmation(Request $request, string $uuid): JsonResponse
    {
        try {
            return $this->partnerApiRepository->bookingConfirmation($request, $uuid);
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }

    public function bookingCancellation(Request $request, string $uuid): JsonResponse
    {
        try {
            return $this->partnerApiRepository->bookingCancellation($request, $uuid);
        } catch (Exception $exception) {
            Log::error($exception);
            return OctoErrorResponse::internalServerError();
        }
    }
}
