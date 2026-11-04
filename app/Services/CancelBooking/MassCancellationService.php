<?php

namespace App\Services\CancelBooking;

use App\Jobs\ProcessMassCancellationBooking;
use App\Models\MassCancellation;
use App\Models\OctoBooking;
use App\Models\TourBooking;
use App\Models\Transaction;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use App\Services\Booking\BookingService;
use App\Services\Booking\CustomChargeBookingService;
use App\Services\Booking\StripeService;
use App\Services\Booking\TipService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Cancel many bookings of a date / time slot in one go.
 *
 * Every booking is cancelled through the regular single-booking flow
 * (V3 TourBookingRepository::cancel()) so refunds, history, slot summary,
 * board updates and cancellation emails behave exactly as they do from the
 * booking page. The bookings are cancelled in the queue, one job per booking
 * (ProcessMassCancellationBooking).
 */
class MassCancellationService
{
	/**
	 * Refund types supported in mass cancellation, mapped to their permission.
	 * Partial refund is intentionally not supported — it is a fixed amount per
	 * booking and cannot be applied uniformly to a batch.
	 */
	public const REFUND_TYPES = [
		'full_refund' => 'cancel-ticket-refund',
		'100_refund'  => 'cancel-weather-refund',
		'dont_refund' => 'cancel-dont-refund',
	];

	public const MAX_BOOKINGS_PER_REQUEST = 200;

	/**
	 * Refund types the logged in user is allowed to use.
	 *
	 * @return array
	 */
	public static function allowedRefundTypes(): array
	{
		return array_keys(array_filter(self::REFUND_TYPES, fn ($permission) => Gate::allows($permission)));
	}

	/**
	 * Distinct time slots for a date, with active and cancelled booking counts.
	 *
	 * @param string $date
	 * @param int $tourPackageId
	 * @return array
	 */
	public static function getTimeSlots(string $date, int $tourPackageId): array
	{
		return self::baseQuery($date, $tourPackageId)
			->groupBy('tst.time_from')
			->orderBy('tst.time_from')
			->get([
				'tst.time_from',
				DB::raw("COUNT(DISTINCT CASE WHEN tbm.status != 'Cancelled' THEN tbm.id END) AS bookings_count"),
				DB::raw("COALESCE(SUM(CASE WHEN tbm.status != 'Cancelled' THEN tbm.tourists + tbm.infants END), 0) AS pax"),
				DB::raw("COUNT(DISTINCT CASE WHEN tbm.status = 'Cancelled' THEN tbm.id END) AS cancelled_count"),
			])
			->map(fn ($slot) => [
				'time_from'       => $slot->time_from,
				'label'           => Carbon::parse($slot->time_from)->format('g:i A'),
				'bookings_count'  => (int) $slot->bookings_count,
				'pax'             => (int) $slot->pax,
				'cancelled_count' => (int) $slot->cancelled_count,
			])
			->values()
			->toArray();
	}

	/**
	 * Bookings of one package time slot on a date (cancelled ones listed last),
	 * each flagged with whether it can be mass cancelled.
	 *
	 * @param string $date
	 * @param int $tourPackageId
	 * @param string $timeFrom  slot time_from (H:i:s)
	 * @return array
	 */
	public static function getBookings(string $date, int $tourPackageId, string $timeFrom): array
	{
		$rows = self::baseQuery($date, $tourPackageId)
			->where('tst.time_from', $timeFrom)
			->orderByRaw("tbm.status = 'Cancelled'")
			->orderBy('tbm.name')
			->get([
				'tbm.id',
				'tbm.group_id',
				'tbm.name',
				'tbm.email',
				'tbm.tourists',
				'tbm.infants',
				'tbm.total',
				'tbm.booking_total',
				DB::raw("(SELECT tbt.nat_fees_percentage FROM tour_bookings_transact AS tbt WHERE tbt.tour_booking_id = tbm.id ORDER BY tbt.id LIMIT 1) AS nat_fees_percentage"),
				'tbm.status',
				'tbm.ota_reference_booking',
				'tbm.viator_bookingRef',
				'tbm.get_your_guide_reference_booking',
				'tp.name AS package_name',
				'tst.date',
				'tst.time_from',
				'tst.time_to',
				DB::raw("(SELECT t.via FROM transactions AS t WHERE t.tour_booking_id = tbm.id AND t.status = 'Completed' AND t.charge_type IS NULL ORDER BY t.id ASC LIMIT 1) AS via"),
				// Net paid, same as "Payment Total" on the booking page: payments minus refunds
				DB::raw("(SELECT COALESCE(SUM(CASE WHEN t.status = 'Completed' THEN t.amount WHEN t.status = 'Refund' THEN -t.amount ELSE 0 END), 0) FROM transactions AS t WHERE t.tour_booking_id = tbm.id) AS paid"),
				DB::raw("(SELECT COUNT(*) FROM invoice_bookings AS ib WHERE ib.tour_booking_id = tbm.id) AS invoiced_count"),
			]);

		$octoBookingIds = OctoBooking::whereIn('tour_booking_id', $rows->pluck('id'))->pluck('tour_booking_id')->all();

		return $rows->map(function ($row) use ($octoBookingIds) {
			$isOcto = in_array($row->id, $octoBookingIds) && isOctoBookingCancellationWithinCutoff($row);

			return [
				'id'           => $row->id,
				'group_id'     => $row->group_id,
				'name'         => $row->name,
				'email'        => $row->email,
				'pax'          => (int) $row->tourists + (int) $row->infants,
				'total'        => self::bookingTotal($row),
				'paid'         => round((float) $row->paid, 2),
				'via'          => $row->via ?: 'Unpaid',
				'status'       => $row->status,
				'package_name' => $row->package_name,
				'time'         => Carbon::parse($row->time_from)->format('g:i A') . ($row->time_to ? ' - ' . Carbon::parse($row->time_to)->format('g:i A') : ''),
				'time_from'    => $row->time_from,
				'is_octo'      => $isOcto,
				'is_cancelled' => $row->status === 'Cancelled',
				'blocked_reason' => self::blockedReason($row, $isOcto),
			];
		})->values()->toArray();
	}

	/**
	 * Validate the selected bookings and queue their cancellation — all or nothing.
	 *
	 * 1. Every selected booking is validated first against the same conditions
	 *    that make cancel() fail. If any booking has an issue, nothing is queued
	 *    and the issues are returned per booking.
	 * 2. A mass cancellation run is stored and a chain of queued jobs (one per
	 *    booking) cancels the bookings in order. cancel() commits each booking and
	 *    issues real Stripe refunds, which cannot be rolled back, so the first
	 *    unexpected failure stops the run and the remaining bookings are left
	 *    untouched (see processBooking()).
	 *
	 * @param array $bookingIds
	 * @param array $options  refund_type, comment, send_cancel_mail
	 * @param array $slot  date, tour_package_id, time_from
	 * @param string|null $clientIp
	 * @return array{status:string, message:string, cancelled:int, results:array}
	 */
	public static function startRun(array $bookingIds, array $options, array $slot, ?string $clientIp = null): array
	{
		if (!array_key_exists($options['refund_type'] ?? '', self::REFUND_TYPES)) {
			throw new Exception('Please select a valid refund type.');
		}
		if (!Gate::allows(self::REFUND_TYPES[$options['refund_type']])) {
			throw new Exception('You do not have permission to use this refund type.');
		}

		$bookingIds = array_values(array_unique(array_map('intval', $bookingIds)));

		// Bookings already queued in another run must not be cancelled twice
		$alreadyQueued = MassCancellation::active()->get(['booking_ids'])
			->flatMap(fn ($run) => $run->booking_ids)
			->map(fn ($id) => (int) $id)
			->intersect($bookingIds)
			->values()
			->all();

		// Only bookings of the selected slot and of packages the user can access
		$departmentPackage = departmentPackage();
		$bookings = TourBooking::whereIn('tour_bookings_master.id', $bookingIds)
			->join('tour_slot_times AS tst', 'tst.id', '=', 'tour_bookings_master.tour_slot_id')
			->where('tour_bookings_master.tour_package_id', $slot['tour_package_id'])
			->where('tst.date', Carbon::parse($slot['date'])->toDateString())
			->where('tst.time_from', $slot['time_from'])
			->when($departmentPackage, fn ($query) => $query->whereIn('tour_bookings_master.tour_package_id', $departmentPackage))
			->get(['tour_bookings_master.*'])
			->keyBy('id');

		$results = [];
		foreach ($bookingIds as $bookingId) {
			$issue = in_array($bookingId, $alreadyQueued, true)
				? 'This booking is already being cancelled by another mass cancellation.'
				: self::validationIssue($bookings->get($bookingId), $options);

			$results[] = $issue
				? ['id' => $bookingId, 'status' => 'failed', 'message' => $issue]
				: ['id' => $bookingId, 'status' => 'queued', 'message' => 'Queued'];
		}

		$issueCount = count(array_filter($results, fn ($result) => $result['status'] === 'failed'));
		if ($issueCount > 0) {
			return [
				'status'    => 'validation_failed',
				'message'   => "No bookings were cancelled. {$issueCount} of " . count($bookingIds) . ' selected booking(s) have an issue — fix or unselect them and try again.',
				'cancelled' => 0,
				'results'   => array_map(function ($result) {
					if ($result['status'] === 'queued') {
						$result['status'] = 'not_processed';
						$result['message'] = 'Not cancelled';
					}
					return $result;
				}, $results),
			];
		}

		$run = MassCancellation::create([
			'tour_package_id'      => $slot['tour_package_id'],
			'date'                 => Carbon::parse($slot['date'])->toDateString(),
			'time_from'            => $slot['time_from'],
			'refund_type'          => $options['refund_type'],
			'comment'              => $options['comment'] ?? '',
			'send_cancel_mail'     => !empty($options['send_cancel_mail']),
			'booking_ids'          => $bookingIds,
			'results'              => $results,
			'status'               => MassCancellation::STATUS_QUEUED,
			'message'              => 'Queued — ' . count($bookingIds) . ' booking(s) waiting to be cancelled.',
			'total'                => count($bookingIds),
			'created_by'           => Auth::id(),
			'created_by_guard'     => session('guard') ?: Auth::getDefaultDriver(),
			'created_by_user_type' => session('userType') ?: userType(),
			'client_ip'            => $clientIp,
		]);

		Bus::chain(array_map(
			fn ($bookingId) => new ProcessMassCancellationBooking($run->id, $bookingId),
			$bookingIds
		))->dispatch();

		return $run->fresh()->toProgressArray();
	}

	/**
	 * Cancel one booking of a run (called by the queued job). On failure the run
	 * is stopped so the remaining jobs of the chain skip their bookings.
	 *
	 * @param MassCancellation $run
	 * @param int $bookingId
	 * @return void
	 */
	public static function processBooking(MassCancellation $run, int $bookingId): void
	{
		if ($run->status === MassCancellation::STATUS_QUEUED) {
			$run->status = MassCancellation::STATUS_PROCESSING;
			$run->started_at = now();
		}
		$run->setResult($bookingId, 'processing', 'Processing...');
		$run->message = 'Cancelling booking #' . $bookingId . ' (' . ($run->cancelled_count + 1) . ' of ' . $run->total . ')...';
		$run->save();

		$options = [
			'refund_type'      => $run->refund_type,
			'comment'          => $run->comment,
			'send_cancel_mail' => $run->send_cancel_mail,
		];

		// Re-check right before cancelling: the booking may have changed since it was queued
		$issue = self::validationIssue(TourBooking::find($bookingId), $options);
		if ($issue) {
			self::stopRun($run, $bookingId, $issue);
			return;
		}

		try {
			app(TourBookingRepository::class)->cancel(self::buildCancelRequest($options, $run->client_ip), $bookingId);

			// cancel() swallows some failures into a rollback; confirm the status actually changed
			if (TourBooking::whereKey($bookingId)->value('status') !== 'Cancelled') {
				throw new Exception('Booking could not be cancelled.');
			}
		} catch (Exception $exception) {
			// The refund services commit the cancellation + ticket refund part-way through, so an
			// error after that point (add-on / custom charge refund, board unassign, cancellation
			// email) leaves the booking genuinely cancelled. Report it as cancelled — not failed —
			// but still stop the run: the error may be a refund that did not go through.
			if (TourBooking::whereKey($bookingId)->value('status') === 'Cancelled') {
				Log::error("Mass cancellation #{$run->id} stopped after cancelling booking #{$bookingId}", ['error' => $exception->getMessage()]);
				self::stopRunAfterCancelled($run, $bookingId, self::cleanMessage($exception->getMessage()));
				return;
			}

			Log::error("Mass cancellation #{$run->id} stopped at booking #{$bookingId}", ['error' => $exception->getMessage()]);
			self::stopRun($run, $bookingId, self::cleanMessage($exception->getMessage()));
			return;
		}

		$run->refresh();

		// The job ran past the queue retry_after and was marked failed meanwhile (see
		// ProcessMassCancellationBooking::failed()), which already stopped the run and left the
		// remaining bookings untouched. The booking did get cancelled after all: record that, but
		// keep the run stopped — never report it as completed.
		if ($run->status === MassCancellation::STATUS_STOPPED) {
			$run->setResult($bookingId, 'cancelled', 'Cancelled (took longer than expected)');
			$run->cancelled_count++;
			$run->message = "Mass cancellation stopped: booking #{$bookingId} took longer than expected but was cancelled. "
				. "{$run->cancelled_count} booking(s) cancelled; the remaining bookings were not cancelled.";
			$run->save();
			return;
		}

		$run->setResult($bookingId, 'cancelled', 'Cancelled');
		$run->cancelled_count++;

		$waiting = array_filter($run->results, fn ($result) => in_array($result['status'], ['queued', 'processing'], true));
		if (empty($waiting)) {
			$run->status = MassCancellation::STATUS_COMPLETED;
			$run->message = "{$run->cancelled_count} booking(s) cancelled successfully.";
			$run->finished_at = now();
		}
		$run->save();
	}

	/**
	 * Stop a run at the given booking; the remaining bookings stay untouched.
	 *
	 * @param MassCancellation $run
	 * @param int $bookingId
	 * @param string $reason
	 * @return void
	 */
	public static function stopRun(MassCancellation $run, int $bookingId, string $reason): void
	{
		$run->refresh();
		$run->setResult($bookingId, 'failed', $reason);
		$run->markRemainingNotProcessed();
		$run->status = MassCancellation::STATUS_STOPPED;
		$run->message = "Mass cancellation stopped at booking #{$bookingId}: {$reason} "
			. "{$run->cancelled_count} booking(s) cancelled before the error; the remaining bookings were not cancelled.";
		$run->finished_at = now();
		$run->save();
	}

	/**
	 * Stop a run at a booking that WAS cancelled, but whose cancel flow then errored
	 * (e.g. an add-on / custom charge refund or the cancellation email failed).
	 *
	 * @param MassCancellation $run
	 * @param int $bookingId
	 * @param string $reason
	 * @return void
	 */
	public static function stopRunAfterCancelled(MassCancellation $run, int $bookingId, string $reason): void
	{
		$run->refresh();
		$run->setResult($bookingId, 'cancelled', "Cancelled, but an error occurred afterwards: {$reason} Please check this booking's refunds manually.");
		$run->cancelled_count++;
		$run->markRemainingNotProcessed();
		$run->status = MassCancellation::STATUS_STOPPED;
		$run->message = "Mass cancellation stopped at booking #{$bookingId}: the booking was cancelled, but an error occurred afterwards ({$reason}). "
			. "Please check this booking's refunds manually. {$run->cancelled_count} booking(s) cancelled; the remaining bookings were not cancelled.";
		$run->finished_at = now();
		$run->save();
	}

	/**
	 * Run still in progress for a time slot (so the page can resume showing it).
	 *
	 * @param string $date
	 * @param int $tourPackageId
	 * @param string $timeFrom
	 * @return array|null
	 */
	public static function activeRun(string $date, int $tourPackageId, string $timeFrom): ?array
	{
		$run = MassCancellation::active()
			->where('tour_package_id', $tourPackageId)
			->where('date', Carbon::parse($date)->toDateString())
			->where('time_from', $timeFrom)
			->latest('id')
			->first();

		return $run?->toProgressArray();
	}

	/**
	 * Booking total including add-ons, custom charges and tips — the same figure
	 * the booking page shows (tour_bookings_master.total only covers pax + fees).
	 *
	 * Paid bookings: "Booking Total" (gross, refunds are not deducted).
	 * Unpaid bookings: "Total Cost" (includes the add-ons still to be paid).
	 *
	 * @param object $row
	 * @return float
	 */
	private static function bookingTotal(object $row): float
	{
		$customCharge = CustomChargeBookingService::customChargePrice($row->id);
		$tips = TipService::getTipAmounts($row->id);
		$addons = BookingService::fetchAddonsWithPrice($row->id);

		if (!empty($row->via)) {
			$base = (float) $row->booking_total != 0.0 ? (float) $row->booking_total : (float) $row->total;

			return round($base + $customCharge['overall_custom_charge'] + $addons['addons_gross_total'] + $tips['tip_total'], 2);
		}

		$payLaterAddons = BookingService::fetchPayLaterAddonsWithPrice($row->id, $row->nat_fees_percentage);

		return round(
			(float) $row->total + $customCharge['custom_charge_total'] + $addons['addons_payment_total']
				+ $payLaterAddons['addons_price'] + $tips['tip_amount'],
			2
		);
	}

	/**
	 * Bookings of a package on a date, scoped to the user's accessible packages.
	 *
	 * @param string $date
	 * @param int $tourPackageId
	 * @return \Illuminate\Database\Query\Builder
	 */
	private static function baseQuery(string $date, int $tourPackageId)
	{
		$departmentPackage = departmentPackage();

		return DB::table('tour_bookings_master AS tbm')
			->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
			->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
			->whereNull('tbm.deleted_at')
			->whereNull('tst.deleted_at')
			->where('tst.date', Carbon::parse($date)->toDateString())
			->where('tbm.tour_package_id', $tourPackageId)
			->when($departmentPackage, fn ($query) => $query->whereIn('tbm.tour_package_id', $departmentPackage));
	}

	/**
	 * Reason a listed booking can never be mass cancelled (shown in the list).
	 *
	 * @param object $row
	 * @param bool $isOcto
	 * @return string|null
	 */
	private static function blockedReason(object $row, bool $isOcto): ?string
	{
		if ($row->status === 'Cancelled') {
			return 'Already cancelled';
		}
		if ((int) $row->invoiced_count > 0) {
			return 'Already invoiced';
		}

		$isOta = !empty($row->ota_reference_booking) || !empty($row->viator_bookingRef) || !empty($row->get_your_guide_reference_booking);
		if ($isOta && !$isOcto) {
			return 'OTA booking — cancel through its original channel';
		}

		return null;
	}

	/**
	 * Issue that would make cancel() fail for a selected booking, re-checked
	 * against the current data (a stale page can never double cancel).
	 *
	 * @param TourBooking|null $booking
	 * @param array $options
	 * @return string|null
	 */
	private static function validationIssue(?TourBooking $booking, array $options): ?string
	{
		if (!$booking) {
			return 'Booking not found.';
		}
		if ($booking->status === 'Cancelled') {
			return 'Already cancelled.';
		}
		if ($booking->isInvoiced()) {
			return 'Unable to cancel booking as it has already been invoiced.';
		}

		$isOcto = isOctoBookingCancellationWithinCutoff($booking);
		$isOta = !empty($booking->ota_reference_booking) || !empty($booking->viator_bookingRef) || !empty($booking->get_your_guide_reference_booking);
		if ($isOta && !$isOcto) {
			return 'This OTA booking cannot be cancelled from this dashboard. Please cancel it through its original booking channel.';
		}

		// OCTO bookings only support Ticket / Weather refunds and are refunded through the reseller
		if ($isOcto && $options['refund_type'] === 'dont_refund') {
			return "OTA (OCTO) booking — Don't Refund is not supported.";
		}

		// Imported card bookings have no Stripe charge to refund (same check as cancel()).
		// Mass cancellation always refunds through the original payment method, so these
		// must be cancelled from the booking page with the Cash or Check/Wire option.
		$transaction = Transaction::where('tour_booking_id', $booking->id)->where('status', 'Completed')->whereNull('charge_type')->first();
		if ($transaction && $transaction->via === 'Card' && !StripeService::stripeDetails($booking->id)) {
			return 'This booking is imported. Please cancel it from the booking page using the Cash or Check/Wire option.';
		}

		return null;
	}

	/**
	 * Request carrying the same fields the cancel booking modal posts.
	 *
	 * @param array $options
	 * @param string|null $clientIp
	 * @return Request
	 */
	private static function buildCancelRequest(array $options, ?string $clientIp): Request
	{
		$data = [
			'cancel_booking' => 1,
			'refund_type'    => $options['refund_type'],
			'comment'        => $options['comment'] ?? '',
			'api'            => 1,
		];

		if (!empty($options['send_cancel_mail'])) {
			$data['send_cancel_mail'] = 'Yes';
		}

		$request = new Request([], [], [], [], [], ['REMOTE_ADDR' => $clientIp ?? '127.0.0.1']);
		$request->merge($data);

		return $request;
	}

	/**
	 * Fallback message when the cancel flow throws without one.
	 *
	 * @param string $message
	 * @return string
	 */
	private static function cleanMessage(string $message): string
	{
		return trim($message) !== '' ? trim($message) : 'Unable to cancel booking.';
	}
}
