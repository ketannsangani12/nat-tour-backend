<?php

namespace App\Http\Controllers\API\V1\TourGuide;

use App\Http\Controllers\API\V1\ApiController;
use App\Repositories\TipCenter\GratuityRepository;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Exceptions\Core\GeneralCoreException;
use Illuminate\Support\Facades\Log;
use Exception;

class TourGuideController extends ApiController
{
    protected $gratuityRepository;

    /**
     * @param  GratuityRepository  $gratuityRepository
     */
    public function __construct(GratuityRepository $gratuityRepository)
    {
        $this->gratuityRepository = $gratuityRepository;
    }

    /**
     * Send tip payment link to customer
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function sendTipPaymentLink(Request $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.send_tip_payment_link_success'))
                ->respond($this->gratuityRepository->sendTipPaymentLink($request,1));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Create tip payment intent for tap to pay process
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function stripeCreateTipPaymentIntent(Request $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.create_payment_intent_success'))
                ->respond($this->gratuityRepository->stripeCreateTipPaymentIntent($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
    /**
     * Send tap to pay payment mail to customer
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function sendTapToPayPaymentMail(Request $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.send_tap_to_pay_payment_mail_success'))
                ->respond($this->gratuityRepository->sendTapToPayPaymentMail($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
    /**
     * After tip payment process for tap to pay process save data
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function afterTipPayment(Request $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.tip_payment_success'))
                ->respond($this->gratuityRepository->afterTipPayment($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
    /**
     * After tip payment process for tap to pay process save data
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function registerInternetReader(Request $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.register_internet_reader_success'))
                ->respond($this->gratuityRepository->registerInternetReader($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Update the guide status
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function updateGuideStatus(Request $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.guide_update_status_success'))
                ->respond($this->gratuityRepository->updateGuideStatus($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
}
