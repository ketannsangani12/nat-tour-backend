<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use App\Repositories\Customer\CustomerRepository;
use App\Repositories\Pos\PosRepository;
use Exception;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PosController extends Controller
{
	protected $customerRepository;

	protected $posRepository;

	/**
	 * @param  CustomerRepository  $customerRepository
	 * @param  PosRepository  $posRepository
	 */
	public function __construct(
		CustomerRepository $customerRepository,
		PosRepository $posRepository,
	) {
		parent::__construct();

		$this->customerRepository = $customerRepository;
		$this->posRepository = $posRepository;

		Stripe::setApiKey(getStripeSecretKey());
	}

	/**
	 * Show POS system 
	 *
	 * 
	 * @return mixed
	 */
	public function initPos()
	{
		try {
			$posSetting = hasSetting('POS_VIEW');

			if ($posSetting != 1) {
				$routePrefix = userType() == 'Admin' ? 'admin.' : 'tour-operator.';

				return redirect()
					->route($routePrefix . 'dashboard');
			} else {
				$vars = $this->posRepository->initPos();

				return view('users.common.pos', $vars);
			}
		} catch (Exception $exception) {
			Log::error($exception);
			return back()
				->withErrors(['errors' => $exception->getMessage()])
				->withInput();
		}
	}
	/**
	 * Fetch packages for POS 
	 *
	 * @param int $categoryId
	 * @param $request
	 * @return mixed
	 */
	public function getPackages($categoryId, Request $request)
	{
		try {
			return $this->posRepository->getPackages($categoryId, $request);
		} catch (Exception $exception) {
			Log::error($exception);
			return back()
				->withErrors(['errors' => $exception->getMessage()])
				->withInput();
		}
	}
	/**
	 * The manual pin check for POS
	 *
	 * @param Request $request
	 */
	public function manualPinCheck(Request $request)
	{
		try {
			return $this->posRepository->manualPinCheck($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}
	/**
	 * Validate Cart in POS
	 *
	 * @param $request
	 * @return mixed
	 */
	public function validateCart(Request $request)
	{
		try {
			return $this->posRepository->validateCart($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return back()
				->withErrors(['errors' => $exception->getMessage()])
				->withInput();
		}
	}

	/**
	 * Create bookings in POS
	 *
	 * @param $request
	 * @return mixed
	 */
	public function createBookings(Request $request)
	{
		try {
			return $this->posRepository->createBookings($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return back()
				->withErrors(['errors' => $exception->getMessage()])
				->withInput();
		}
	}
	/**
	 * Apply Promo Code for package in POS
	 *
	 * @param $request
	 * @return mixed
	 */
	public function applyPromoCode(Request $request)
	{
		try {
			return $this->posRepository->applyPromoCode($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return back()
				->withErrors(['errors' => $exception->getMessage()])
				->withInput();
		}
	}
	/**
	 * Get list of orders in POS
	 *
	 * 
	 * @return mixed
	 */
	public function orders()
	{
		try {
			return $this->posRepository->orders();
		} catch (Exception $exception) {
			Log::error($exception);
			return back()
				->withErrors(['errors' => $exception->getMessage()])
				->withInput();
		}
	}
	/**
	 * Get order info in POS
	 *
	 * @param int $orderId
	 * @return mixed
	 */
	public function order($orderId)
	{
		try {
			return $this->posRepository->order($orderId);
		} catch (Exception $exception) {
			Log::error($exception);
			return back()
				->withErrors(['errors' => $exception->getMessage()])
				->withInput();
		}
	}
	/**
	 * Print receipt of order in POS
	 *
	 * @param int $orderId
	 * @return mixed
	 */
	public function printReceipt($orderId)
	{
		try {
			$vars = $this->posRepository->printReceipt($orderId);
			return view('users.common.pos-bookings-info-2', $vars);
		} catch (Exception $exception) {
			Log::error($exception);
			return back()
				->withErrors(['errors' => $exception->getMessage()])
				->withInput();
		}
	}
	/**
	 * Send receipt of order in Mail 
	 *
	 * @param int $orderId
	 * @param $request
	 * @return mixed
	 */
	public function sendReceipt($orderId, Request $request)
	{
		try {
			return $this->posRepository->sendReceipt($orderId, $request);
		} catch (Exception $exception) {
			Log::error($exception);
			return back()
				->withErrors(['errors' => $exception->getMessage()])
				->withInput();
		}
	}
	/**
	 * Settings of POS
	 *
	 *
	 * @return mixed
	 */
	public function settings()
	{
		try {
			return $this->posRepository->settings();
		} catch (Exception $exception) {
			Log::error($exception);
			return back()
				->withErrors(['errors' => $exception->getMessage()])
				->withInput();
		}
	}
	/**
	 * Update Settings in POS
	 *
	 * @param $request
	 * @return mixed
	 */
	public function updateSettings(Request $request)
	{
		try {
			return $this->posRepository->updateSettings($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return back()
				->withErrors(['errors' => $exception->getMessage()])
				->withInput();
		}
	}
	/**
	 * Cancel Order
	 *
	 * @param int $orderId
	 * @param $request
	 * @return mixed
	 */
	public function cancelOrder($orderId, Request $request)
	{
		try {
			return $this->posRepository->cancelOrder($orderId, $request);
		} catch (Exception $exception) {
			Log::error($exception);
			return back()
				->withErrors(['errors' => $exception->getMessage()])
				->withInput();
		}
	}
	/**
	 * Refund Order
	 *
	 * @param int $orderId
	 * @param $request
	 * @return mixed
	 */
	public function refundOrder($orderId, Request $request)
	{
		try {
			return $this->posRepository->refundOrder($orderId, $request);
		} catch (Exception $exception) {
			Log::error($exception);
			return back()
				->withErrors(['errors' => $exception->getMessage()])
				->withInput();
		}
	}
	/**
	 * Request to the manager
	 * @param int $orderId
	 * @param Request $request
	 */
	public function checkRequest($orderId, Request $request)
	{
		try {
			return $this->posRepository->checkRequest($orderId, $request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}
	/**
	 * Request to the manager
	 *
	 * @param Request $request
	 */
	public function requestToManager(Request $request)
	{
		try {
			return $this->posRepository->requestToManager($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}
}
