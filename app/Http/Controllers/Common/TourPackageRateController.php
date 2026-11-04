<?php

namespace App\Http\Controllers\Common;

use App\Exceptions\Core\GeneralCoreException;
use App\Http\Controllers\Controller;
use App\Repositories\TourPackageRate\TourPackageRateRepository;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TourPackageRateController extends Controller
{
	protected $tourPackageRateRepository;

	/**
	 * @param  TourPackageRateRepository  $tourPackageRateRepository
	 */
	public function __construct(TourPackageRateRepository $tourPackageRateRepository)
	{
		parent::__construct();

		$this->tourPackageRateRepository = $tourPackageRateRepository;
	}

	/**
	 * Display the tour package rates
	 *
	 * @return mixed
	 */
    public function packageRate()
	{
        try {
			$vars = $this->tourPackageRateRepository->getDataForFilter();
			$vars['page_title'] = 'Tour Package Rates';

			return view('users.common.tour-package-rates.index', $vars);
		} catch (Exception $exception) {
			// Deliberately not back(): these are full pages, and the layouts do
			// not render the error bag, so redirecting made a broken page look
			// like a permissions guard bouncing the user to the dashboard.
			Log::error($exception);
			throw $exception;
		}
    }

	/**
	 * Display the tour package rates
	 *
	 * @return mixed
	 */
    public function getPackageRates(Request $request)
	{
        try {
			$data = $request->all();

			$vars = $this->tourPackageRateRepository->getPackageRates($data);

			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
    }

	/**
	 * Save the tour package rates
	 *
	 * @return mixed
	 */
    public function savePackageRates(Request $request)
	{
        try {
			$data = $request->all();
			$vars = $this->tourPackageRateRepository->savePackageRates($data);

			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
    }

	/**
	 * Delete the tour package rate
	 *
	 * @return mixed
	 */
    public function deletePackageRates(Request $request)
	{
        try {
			$data = $request->all();

			$vars = $this->tourPackageRateRepository->deletePackageRates($data);

			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
    }

	/**
	 * Display the tour package rates
	 *
	 * @return mixed
	 */
    public function customDateRate()
	{
        try {
			$vars = $this->tourPackageRateRepository->getDataForFilter();
			$vars['page_title'] = 'Custom Date Rates';

			return view('users.common.custom-date-rates.index', $vars);
		} catch (Exception $exception) {
			// Deliberately not back(): these are full pages, and the layouts do
			// not render the error bag, so redirecting made a broken page look
			// like a permissions guard bouncing the user to the dashboard.
			Log::error($exception);
			throw $exception;
		}
    }

	/**
	 * Save the tour package rates
	 *
	 * @return mixed
	 */
    public function saveCustomDateRates(Request $request)
	{
        try {
			$data = $request->all();
			$vars = $this->tourPackageRateRepository->saveCustomDateRates($data);

			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
    }

	/**
	 * Get time slots for package
	 *
	 * @return mixed
	 */
    public function getTimeSlots(Request $request)
	{
        try {
			$data = $request->all();

			$vars = $this->tourPackageRateRepository->getTimeSlots($data);

			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
    }

	/**
	 * Assign slot to the tour package rates
	 *
	 * @param Request $request
	 * @return mixed
	 */
    public function unassignSlotToDateRate(Request $request)
	{
        try {
			$data = $request->all();

			$vars = $this->tourPackageRateRepository->unassignSlotToDateRate($data);

			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
    }

	/**
	 * Summary of deleteCustomDateRate
	 * @param \Illuminate\Http\Request $request
	 * @throws \App\Exceptions\Core\GeneralCoreException
	 * @return void
	 */
    public function deleteCustomDateRate(Request $request)
	{
        try {
			$data = $request->all();

			$vars = $this->tourPackageRateRepository->deleteCustomDateRate($data);

			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
    }

	/**
	 * Display the global package rates
	 *
	 * @return mixed
	 */
    public function globalRate()
	{
        try {
			$vars = $this->tourPackageRateRepository->getDataForFilter();
			$vars['page_title'] = 'Global Package Rates';

			return view('users.common.global-package-rates.index', $vars);
		} catch (Exception $exception) {
			// Deliberately not back(): these are full pages, and the layouts do
			// not render the error bag, so redirecting made a broken page look
			// like a permissions guard bouncing the user to the dashboard.
			Log::error($exception);
			throw $exception;
		}
    }

	/**
	 * Save the global package rates
	 *
	 * @return mixed
	 */
    public function saveGlobalRates(Request $request)
	{
        try {
			$data = $request->all();

			$vars = $this->tourPackageRateRepository->saveGlobalRates($data);

			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
    }

	/**
	 * Save the schedule event for package rates
	 *
	 * @return mixed
	 */
    public function saveScheduleEvent(Request $request)
	{
        try {
			$data = $request->all();

			$vars = $this->tourPackageRateRepository->saveScheduleEvent($data);

			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
    }

	/**
	 * Fetch the schedule events for package rates
	 *
	 * @return mixed
	 */
    public function fetchScheduleEvents(Request $request)
	{
        try {
			$data = $request->all();

			$vars = $this->tourPackageRateRepository->fetchScheduleEvents($data);

			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
    }

	/**
	 * Delete the schedule event for package rates
	 *
	 * @return mixed
	 */
    public function deleteScheduleEvent(Request $request)
	{
        try {
			$data = $request->all();

			$vars = $this->tourPackageRateRepository->deleteScheduleEvent($data);

			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
    }
}
