<?php

namespace App\Http\Controllers\Common;

use App\Exceptions\Core\GeneralCoreException;
use App\Http\Controllers\Controller;
use App\Repositories\TourBooking\StripeRepository;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeController extends Controller
{
	protected $stripeRepository;

	/**
	 * @param  StripeRepository  $stripeRepository
	 */
	public function __construct(StripeRepository $stripeRepository)
	{
		parent::__construct();

		$this->stripeRepository = $stripeRepository;
	}

    public function createPaymentIntent(Request $request)
    {
        try {
			$vars = $this->stripeRepository->stripeCreatePaymentIntent($request);
			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
    }
	public function createPaymentIntentForMakePayment(Request $request)
    {
        try {
			$vars = $this->stripeRepository->stripeCreatePaymentIntentForMakePayment($request);
			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
    }
	public function createStripePaymentIntentForPos(Request $request)
    {
        try {
			$vars = $this->stripeRepository->stripeCreateStripePaymentIntentForPos($request);
			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
    }
	public function deletePaymentMethod(Request $request)
    {
        try {
			$response = $this->stripeRepository->stripeDeletePaymentMethod($request);
			return $response;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
    }

    public function saveCardList(Request $request)
    {
        try {
			$response = $this->stripeRepository->saveCardList($request);
			return $response;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
    }
}
