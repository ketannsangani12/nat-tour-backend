<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use App\Models\MassCancellation;
use App\Models\TourPackage;
use App\Services\CancelBooking\MassCancellationService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class MassCancellationController extends Controller
{
	/**
	 * Display the mass cancellation page.
	 *
	 * @return mixed
	 */
	public function index()
	{
		abort_unless(Gate::allows('mass-cancellation'), 403);

		$tourPackages = TourPackage::departmentPackage()
			->active()
			->orderBy('name')
			->get(['id', 'name']);

		$vars = [
			'page_title' => 'Mass Cancellation',
			'tourPackages' => $tourPackages,
			'allowedRefundTypes' => MassCancellationService::allowedRefundTypes(),
			'today' => Carbon::now(tourOperator()->timezone ?? config('app.timezone'))->toDateString(),
		];

		return view('users.common.mass-cancellation', $vars);
	}

	/**
	 * Time slots having bookings on the selected date.
	 *
	 * @param Request $request
	 */
	public function timeSlots(Request $request)
	{
		abort_unless(Gate::allows('mass-cancellation'), 403);

		$request->validate([
			'date' => 'required|date',
			'tour_package_id' => 'required|integer',
		], [
			'tour_package_id.required' => 'Please select tour package.',
		]);

		try {
			return response()->json([
				'timeSlots' => MassCancellationService::getTimeSlots($request->date, (int) $request->tour_package_id),
			]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Bookings for the selected date / package / time.
	 *
	 * @param Request $request
	 */
	public function bookings(Request $request)
	{
		abort_unless(Gate::allows('mass-cancellation'), 403);

		$request->validate([
			'date' => 'required|date',
			'tour_package_id' => 'required|integer',
			'time' => 'required|date_format:H:i:s',
		], [
			'tour_package_id.required' => 'Please select tour package.',
			'time.required' => 'Please select a time slot.',
		]);

		try {
			return response()->json([
				'bookings' => MassCancellationService::getBookings($request->date, (int) $request->tour_package_id, $request->time),
				// A run still in progress for this slot, so the page can resume showing it
				'activeRun' => MassCancellationService::activeRun($request->date, (int) $request->tour_package_id, $request->time),
			]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Validate the selected bookings and queue their cancellation.
	 *
	 * @param Request $request
	 */
	public function cancel(Request $request)
	{
		abort_unless(Gate::allows('mass-cancellation'), 403);

		$request->validate([
			'date' => 'required|date',
			'tour_package_id' => 'required|integer',
			'time' => 'required|date_format:H:i:s',
			'booking_ids' => 'required|array|min:1|max:' . MassCancellationService::MAX_BOOKINGS_PER_REQUEST,
			'booking_ids.*' => 'integer',
			'refund_type' => 'required|in:' . implode(',', array_keys(MassCancellationService::REFUND_TYPES)),
			'comment' => 'required|string|max:1000',
			'send_cancel_mail' => 'nullable|boolean',
		], [
			'comment.required' => 'Please specify the reason to cancel the bookings.',
		]);

		try {
			$result = MassCancellationService::startRun(
				$request->booking_ids,
				$request->only(['refund_type', 'comment']) + ['send_cancel_mail' => $request->boolean('send_cancel_mail')],
				['date' => $request->date, 'tour_package_id' => (int) $request->tour_package_id, 'time_from' => $request->time],
				$request->getClientIp()
			);

			return response()->json($result);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Progress of a queued mass cancellation (polled by the page).
	 *
	 * @param MassCancellation $massCancellation
	 */
	public function status(MassCancellation $massCancellation)
	{
		abort_unless(Gate::allows('mass-cancellation'), 403);

		return response()->json($massCancellation->toProgressArray());
	}
}
