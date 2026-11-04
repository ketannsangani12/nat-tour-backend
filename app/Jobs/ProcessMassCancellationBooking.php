<?php

namespace App\Jobs;

use App\Models\MassCancellation;
use App\Services\CancelBooking\MassCancellationService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cancels ONE booking of a mass cancellation run through the regular cancel flow.
 *
 * A run is dispatched as a chain of these jobs (one per booking) so each job
 * stays well below the queue `retry_after` (90s) — a single long job could be
 * handed to a second worker while still running and refund bookings twice.
 * As soon as one booking fails, the run is marked stopped and every following
 * job in the chain exits without touching its booking.
 */
class ProcessMassCancellationBooking implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable;

	/**
	 * Never retry — a retry could refund the same booking twice.
	 *
	 * @var int
	 */
	public $tries = 1;

	/**
	 * Below the database queue retry_after (90s).
	 *
	 * @var int
	 */
	public $timeout = 80;

	/**
	 * @var bool
	 */
	public $failOnTimeout = true;

	public function __construct(public int $massCancellationId, public int $bookingId)
	{
	}

	/**
	 * Execute the job.
	 *
	 * @return void
	 */
	public function handle(): void
	{
		$run = MassCancellation::find($this->massCancellationId);
		if (!$run || $run->isFinished()) {
			return;
		}

		$isQueueWorker = $this->job && $this->job->getConnectionName() !== 'sync';

		try {
			if ($isQueueWorker) {
				$this->actAs($run);
			}

			MassCancellationService::processBooking($run, $this->bookingId);
		} finally {
			if ($isQueueWorker) {
				Auth::forgetGuards();
				session()->flush();
			}
		}
	}

	/**
	 * The job crashed or timed out (e.g. worker killed). The booking may or may
	 * not have been cancelled at Stripe, so stop the run and flag it for review.
	 *
	 * @param Throwable $exception
	 * @return void
	 */
	public function failed(Throwable $exception): void
	{
		Log::error("Mass cancellation #{$this->massCancellationId} job failed for booking #{$this->bookingId}", ['error' => $exception->getMessage()]);

		// On a timeout (or a PHP Error cancel() does not catch) this runs in the same
		// process while cancel()'s DB transaction is still open — saving the run inside
		// it would be rolled back when the worker exits, leaving the run "processing"
		// forever. Roll it back first so the stopped status is actually persisted.
		while (DB::transactionLevel() > 0) {
			DB::rollBack();
		}

		$run = MassCancellation::find($this->massCancellationId);
		if (!$run || $run->isFinished()) {
			return;
		}

		MassCancellationService::stopRun(
			$run,
			$this->bookingId,
			'Processing was interrupted (' . $exception->getMessage() . '). Please check this booking manually before retrying.'
		);
	}

	/**
	 * Run as the user who started the mass cancellation, the same way a web
	 * request would (guard, session data used by userType() / routePrefix()).
	 *
	 * @param MassCancellation $run
	 * @return void
	 */
	private function actAs(MassCancellation $run): void
	{
		$guard = $run->created_by_guard ?: 'admin';
		$user = Auth::guard($guard)->getProvider()->retrieveById($run->created_by);

		if (!$user) {
			throw new Exception('The user who started the mass cancellation no longer exists.');
		}

		Auth::shouldUse($guard);
		Auth::guard($guard)->setUser($user);

		session([
			'guard' => $guard,
			'userType' => $run->created_by_user_type,
			'userId' => $user->id,
			'userName' => $user->name ?? '',
		]);

		request()->server->set('REMOTE_ADDR', $run->client_ip ?: '127.0.0.1');
	}
}
