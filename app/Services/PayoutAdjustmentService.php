<?php

namespace App\Services;

use App\Models\AutoPayout;
use App\Models\PayoutAdjustment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PayoutAdjustmentService
{
	/**
	 * Detect post-payout amendments and create pending adjustment records.
	 *
	 * Finds tour_booking_history records (Payment/Refund) that were created
	 * after the booking's payout was processed, and creates adjustment records
	 * for any that don't already have one.
	 */
	public function detectAndCreateAdjustments(): Collection
	{
		$newHistoryRecords = DB::table('tour_booking_history as tbh')
			->join('auto_payout_items as api', 'api.booking_id', '=', 'tbh.tour_booking_id')
			->join('auto_payouts as ap', 'ap.id', '=', 'api.payout_id')
			->join('tour_bookings_master as tbm', 'tbm.id', '=', 'tbh.tour_booking_id')
			->leftJoin('transactions as tra', 'tra.id', '=', 'tbh.transaction_id')
			->whereIn('tbh.operation', ['Payment', 'Refund', 'Cancelled'])
			->where('tbh.adjustment_processed', 0)
			->where('tbh.created_at', '>', DB::raw('ap.processed_date'))
			->where('ap.status', 'completed')
			->where('tbm.payout_made', 1)
			->select(
				'tbh.id as history_id',
				'tbh.tour_booking_id as booking_id',
				'tbh.operation',
				'tbh.ticket_cost',
				'tbh.platform_fees',
				'tbh.processing_fees',
				'tbh.details',
				'tbh.created_at as history_created_at',
				'tra.amount as transaction_amount',
				'tra.fee_amount as transaction_fee',
				'tra.status as transaction_status',
				'api.payout_id',
				'ap.payout_reference',
				'ap.processed_date'
			)
			->get();

		$newAdjustments = collect();

		foreach ($newHistoryRecords as $record) {
			// Atomically claim this history row to prevent duplicate adjustments
			// if detection runs concurrently. Only proceed if we actually flipped the flag.
			$claimed = DB::table('tour_booking_history')
				->where('id', $record->history_id)
				->where('adjustment_processed', 0)
				->update(['adjustment_processed' => 1]);

			if ($claimed === 0) {
				continue; // Another process already claimed this row
			}

			$adjustment = $this->createAdjustmentFromHistory($record);
			if ($adjustment) {
				$newAdjustments->push($adjustment);
			} else {
				// Creation was skipped (e.g. zero amount) -- reset the flag
				DB::table('tour_booking_history')
					->where('id', $record->history_id)
					->update(['adjustment_processed' => 0]);
			}
		}

		if ($newAdjustments->isNotEmpty()) {
			Log::info("PayoutAdjustmentService: Detected {$newAdjustments->count()} new post-payout adjustments");
		}

		return $newAdjustments;
	}

	/**
	 * Detect and create an adjustment for a single booking/history record.
	 * Used by the DetectPayoutAdjustment job for bookings that were already paid out.
	 */
	public function detectAndCreateAdjustmentForBooking(int $bookingId, int $historyId): ?PayoutAdjustment
	{
		$record = DB::table('tour_booking_history as tbh')
			->join('auto_payout_items as api', 'api.booking_id', '=', 'tbh.tour_booking_id')
			->join('auto_payouts as ap', 'ap.id', '=', 'api.payout_id')
			->join('tour_bookings_master as tbm', 'tbm.id', '=', 'tbh.tour_booking_id')
			->leftJoin('transactions as tra', 'tra.id', '=', 'tbh.transaction_id')
			->where('tbh.id', $historyId)
			->where('tbh.tour_booking_id', $bookingId)
			->whereIn('tbh.operation', ['Payment', 'Refund', 'Cancelled'])
			->where('tbh.adjustment_processed', 0)
			->where('tbh.created_at', '>', DB::raw('ap.processed_date'))
			->where('ap.status', 'completed')
			->where('tbm.payout_made', 1)
			->select(
				'tbh.id as history_id',
				'tbh.tour_booking_id as booking_id',
				'tbh.operation',
				'tbh.ticket_cost',
				'tbh.platform_fees',
				'tbh.processing_fees',
				'tbh.details',
				'tbh.created_at as history_created_at',
				'tra.amount as transaction_amount',
				'tra.fee_amount as transaction_fee',
				'tra.status as transaction_status',
				'api.payout_id',
				'ap.payout_reference',
				'ap.processed_date'
			)
			->first();

		if (!$record) {
			return null;
		}

		$claimed = DB::table('tour_booking_history')
			->where('id', $record->history_id)
			->where('adjustment_processed', 0)
			->update(['adjustment_processed' => 1]);

		if ($claimed === 0) {
			return null;
		}

		$adjustment = $this->createAdjustmentFromHistory($record);
		if (!$adjustment) {
			DB::table('tour_booking_history')
				->where('id', $record->history_id)
				->update(['adjustment_processed' => 0]);
		}

		return $adjustment;
	}

	/**
	 * Create an adjustment for an orphaned booking (never paid out, tour date past cycle window).
	 * Used by the DetectPayoutAdjustment job.
	 */
	public function createOrphanedBookingAdjustment(int $bookingId, int $historyId): ?PayoutAdjustment
	{
		$record = DB::table('tour_booking_history as tbh')
			->join('tour_bookings_master as tbm', 'tbm.id', '=', 'tbh.tour_booking_id')
			->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
			->leftJoin('transactions as tra', 'tra.id', '=', 'tbh.transaction_id')
			->where('tbh.id', $historyId)
			->where('tbh.tour_booking_id', $bookingId)
			->whereIn('tbh.operation', ['Payment', 'Refund', 'Cancelled'])
			->where('tbh.adjustment_processed', 0)
			->where('tbm.payout_made', 0)
			->select(
				'tbh.id as history_id',
				'tbh.tour_booking_id as booking_id',
				'tbh.operation',
				'tbh.ticket_cost',
				'tbh.platform_fees',
				'tbh.processing_fees',
				'tbh.details',
				'tbh.created_at as history_created_at',
				'tra.amount as transaction_amount',
				'tra.fee_amount as transaction_fee',
				'tra.status as transaction_status',
				DB::raw("CONCAT(tst.date, ' ', tst.time_from) as tour_datetime")
			)
			->first();

		if (!$record) {
			return null;
		}

		$claimed = DB::table('tour_booking_history')
			->where('id', $record->history_id)
			->where('adjustment_processed', 0)
			->update(['adjustment_processed' => 1]);

		if ($claimed === 0) {
			return null;
		}

		$grossAmount = (float) ($record->transaction_amount ?? $record->ticket_cost ?? 0);
		$platformFee = (float) ($record->transaction_fee ?? $record->processing_fees ?? 0);

		if ($grossAmount <= 0) {
			DB::table('tour_booking_history')
				->where('id', $record->history_id)
				->update(['adjustment_processed' => 0]);
			return null;
		}

		$netAmount = round($grossAmount - $platformFee, 2);

		if ($netAmount <= 0) {
			DB::table('tour_booking_history')
				->where('id', $record->history_id)
				->update(['adjustment_processed' => 0]);
			return null;
		}

		$type = $record->operation === 'Payment' ? 'credit' : 'debit';
		$formattedAmount = number_format($grossAmount, 2);
		$date = Carbon::parse($record->history_created_at)->format('M d, Y');
		$tourDate = Carbon::parse($record->tour_datetime)->format('M d, Y');

		if ($type === 'debit') {
			$reason = "Refund of \${$formattedAmount} on orphaned booking #{$record->booking_id} (tour date: {$tourDate}, never included in payout) issued {$date}";
		} else {
			$reason = "Payment of \${$formattedAmount} on orphaned booking #{$record->booking_id} (tour date: {$tourDate}, never included in payout) received {$date}";
		}

		$adjustment = PayoutAdjustment::create([
			'payout_id' => null,
			'payout_reference' => null,
			'booking_id' => $record->booking_id,
			'type' => $type,
			'gross_amount' => $grossAmount,
			'platform_fee' => $platformFee,
			'net_amount' => $netAmount,
			'reason' => $reason,
			'source_history_ids' => [$record->history_id],
			'status' => 'pending',
			'detected_at' => now(),
		]);

		Log::info("PayoutAdjustmentService: Created orphaned booking adjustment #{$adjustment->id} - {$type} \${$netAmount} on booking #{$bookingId}");

		return $adjustment;
	}

	/**
	 * Create a single adjustment record from a history record.
	 */
	private function createAdjustmentFromHistory(object $record): ?PayoutAdjustment
	{
		// Use transaction amounts as the source of truth (same as calculateAccountPayoutAmount)
		$grossAmount = (float) ($record->transaction_amount ?? $record->ticket_cost ?? 0);
		$platformFee = (float) ($record->transaction_fee ?? $record->processing_fees ?? 0);

		if ($grossAmount <= 0) {
			return null;
		}

		// For refunds, the fee was returned to the platform, so net = gross - fee
		// For payments, the fee is charged, so net = gross - fee
		$netAmount = round($grossAmount - $platformFee, 2);

		if ($netAmount <= 0) {
			return null;
		}

		// Payment after payout = credit (operator is owed more)
		// Refund after payout = debit (operator was overpaid, need to claw back)
		$type = $record->operation === 'Payment' ? 'credit' : 'debit';

		$reason = $this->buildReason($record, $type, $grossAmount);

		return PayoutAdjustment::create([
			'payout_id' => $record->payout_id,
			'payout_reference' => $record->payout_reference,
			'booking_id' => $record->booking_id,
			'type' => $type,
			'gross_amount' => $grossAmount,
			'platform_fee' => $platformFee,
			'net_amount' => $netAmount,
			'reason' => $reason,
			'source_history_ids' => [$record->history_id],
			'status' => 'pending',
			'detected_at' => now(),
		]);
	}

	/**
	 * Build a human-readable reason string.
	 */
	private function buildReason(object $record, string $type, float $amount): string
	{
		$formattedAmount = number_format($amount, 2);
		$date = Carbon::parse($record->history_created_at)->format('M d, Y');

		if ($type === 'debit') {
			return "Refund of \${$formattedAmount} on booking #{$record->booking_id} issued {$date}, after payout {$record->payout_reference}";
		}

		return "Additional payment of \${$formattedAmount} on booking #{$record->booking_id} received {$date}, after payout {$record->payout_reference}";
	}

	/**
	 * Create a manual adjustment (for accountant corrections or re-adding voided adjustments).
	 */
	public function createManualAdjustment(array $data, int $userId): PayoutAdjustment
	{
		$payoutId = $data['payout_id'];
		$payoutSource = $data['payout_source'] ?? null;
		$autoPayoutId = null;
		$payoutReference = null;

		// auto_payouts.id and pending_payouts.id are independent sequences and
		// regularly collide. Require the caller to disambiguate via payout_source.
		// Older callers that omit it fall through to the legacy "auto first,
		// then pending" probe -- but the booking-presence check below catches
		// the collision case and rerouters to the correct table.
		if ($payoutSource === 'auto') {
			$autoPayout = AutoPayout::find($payoutId);
			if (!$autoPayout) {
				throw new \Exception("Auto payout #{$payoutId} not found.");
			}
			$autoPayoutId = $autoPayout->id;
			$payoutReference = $autoPayout->payout_reference;
			$bookingInPayout = DB::table('auto_payout_items')
				->where('payout_id', $autoPayout->id)
				->where('booking_id', $data['booking_id'])
				->exists();
		} elseif ($payoutSource === 'pending') {
			$pendingPayout = \App\Models\PendingPayout::find($payoutId);
			if (!$pendingPayout) {
				throw new \Exception("Pending payout #{$payoutId} not found.");
			}
			$payoutReference = $pendingPayout->payout_reference;
			$bookingInPayout = DB::table('pending_payout_items')
				->where('pending_payout_id', $pendingPayout->id)
				->where('booking_id', $data['booking_id'])
				->exists();
		} else {
			// Legacy path: probe both tables and pick whichever actually contains
			// the booking. Avoids the collision where auto_payouts.id=X masks
			// pending_payouts.id=X for callers that didn't send payout_source.
			$autoPayout = AutoPayout::find($payoutId);
			$autoHasBooking = $autoPayout && DB::table('auto_payout_items')
				->where('payout_id', $autoPayout->id)
				->where('booking_id', $data['booking_id'])
				->exists();

			if ($autoHasBooking) {
				$autoPayoutId = $autoPayout->id;
				$payoutReference = $autoPayout->payout_reference;
				$bookingInPayout = true;
			} else {
				$pendingPayout = \App\Models\PendingPayout::find($payoutId);
				if (!$pendingPayout && !$autoPayout) {
					throw new \Exception("Payout #{$payoutId} not found.");
				}
				if ($pendingPayout) {
					$payoutReference = $pendingPayout->payout_reference;
					$bookingInPayout = DB::table('pending_payout_items')
						->where('pending_payout_id', $pendingPayout->id)
						->where('booking_id', $data['booking_id'])
						->exists();
				} else {
					$payoutReference = $autoPayout->payout_reference;
					$bookingInPayout = false;
				}
			}
		}

		if (!$bookingInPayout) {
			throw new \Exception("Booking #{$data['booking_id']} is not part of payout {$payoutReference}.");
		}

		$grossAmount = round((float) ($data['gross_amount'] ?? $data['net_amount']), 2);
		$platformFee = round((float) ($data['platform_fee'] ?? 0), 2);
		$netAmount = round((float) $data['net_amount'], 2);

		$adjustment = PayoutAdjustment::create([
			'payout_id' => $autoPayoutId,
			'payout_reference' => $payoutReference,
			'booking_id' => $data['booking_id'],
			'type' => $data['type'],
			'gross_amount' => $grossAmount,
			'platform_fee' => $platformFee,
			'net_amount' => $netAmount,
			'reason' => $data['reason'],
			'source_history_ids' => [],
			'status' => 'pending',
			'detected_at' => now(),
		]);

		Log::info("PayoutAdjustmentService: Manual adjustment #{$adjustment->id} created by user #{$userId} - {$data['type']} \${$netAmount} on booking #{$data['booking_id']}");

		return $adjustment;
	}

	/**
	 * Get the total of all pending adjustments.
	 *
	 * Returns credit_total, debit_total, net_adjustment (credits - debits), count,
	 * and the list of pending adjustment ids. The ids are snapshotted into a
	 * pending payout's calculation_data at creation so that exactly the same set
	 * is re-validated at recalculation and marked applied at execution -- see
	 * PayoutApprovalService::calculateAccountPayoutAmount and applyAdjustmentsToPayout.
	 */
	public function getPendingAdjustmentTotal(): array
	{
		$pending = PayoutAdjustment::pending()
			->select('id', 'type', 'net_amount')
			->get();

		$creditTotal = round((float) $pending->where('type', 'credit')->sum('net_amount'), 2);
		$debitTotal = round((float) $pending->where('type', 'debit')->sum('net_amount'), 2);

		return [
			'credit_total' => $creditTotal,
			'debit_total' => $debitTotal,
			'net_adjustment' => round($creditTotal - $debitTotal, 2),
			'count' => $pending->count(),
			'ids' => $pending->pluck('id')->map(fn ($id) => (int) $id)->all(),
		];
	}

	/**
	 * Mark the snapshotted adjustments as applied to the given payout.
	 *
	 * Applies ONLY the adjustments whose ids were captured for this payout at
	 * calculation time, and only those still 'pending'. This closes two bugs:
	 *  - a payout absorbing adjustments that were created after it was calculated
	 *    (the old global apply swept up everything pending at execution time), and
	 *  - two stacked pending payouts both claiming the same adjustment -- whichever
	 *    executes first flips them to 'applied'; the second finds none still pending.
	 * A row lock prevents a concurrent execution from double-applying.
	 *
	 * Passing a null/empty id list applies nothing (no snapshot => no global sweep).
	 *
	 * @param AutoPayout $payout
	 * @param int[]|null $adjustmentIds
	 * @return int number of adjustments marked applied
	 */
	public function applyAdjustmentsToPayout(AutoPayout $payout, ?array $adjustmentIds = null): int
	{
		if (empty($adjustmentIds)) {
			return 0;
		}

		return DB::transaction(function () use ($payout, $adjustmentIds) {
			$lockedIds = PayoutAdjustment::pending()
				->whereIn('id', $adjustmentIds)
				->lockForUpdate()
				->pluck('id')
				->all();

			if (empty($lockedIds)) {
				return 0;
			}

			$count = PayoutAdjustment::whereIn('id', $lockedIds)
				->where('status', 'pending')
				->update([
					'status' => 'applied',
					'applied_in_payout_id' => $payout->id,
					'applied_at' => now(),
				]);

			if ($count > 0) {
				Log::info("PayoutAdjustmentService: Applied {$count} adjustments to payout {$payout->payout_reference}");
			}

			return $count;
		});
	}

	/**
	 * Void a pending adjustment.
	 */
	public function voidAdjustment(PayoutAdjustment $adjustment, int $userId, string $reason): void
	{
		if ($adjustment->status !== 'pending') {
			throw new \Exception("Only pending adjustments can be voided. Current status: {$adjustment->status}");
		}

		$adjustment->update([
			'status' => 'voided',
			'voided_by' => $userId,
			'voided_at' => now(),
			'void_reason' => $reason,
		]);

		Log::info("PayoutAdjustmentService: Voided adjustment #{$adjustment->id} by user #{$userId}: {$reason}");
	}

	/**
	 * Get adjustments with optional filters, paginated.
	 */
	public function getAdjustments(array $filters = []): LengthAwarePaginator
	{
		$query = PayoutAdjustment::with(['originalPayout', 'booking'])
			->orderBy('created_at', 'desc');

		if (!empty($filters['status'])) {
			$query->where('status', $filters['status']);
		}

		if (!empty($filters['booking_id'])) {
			$query->where('booking_id', $filters['booking_id']);
		}

		if (!empty($filters['payout_reference'])) {
			$query->where('payout_reference', 'like', '%' . $filters['payout_reference'] . '%');
		}

		if (!empty($filters['type'])) {
			$query->where('type', $filters['type']);
		}

		if (!empty($filters['date_from'])) {
			$query->whereDate('detected_at', '>=', $filters['date_from']);
		}

		if (!empty($filters['date_to'])) {
			$query->whereDate('detected_at', '<=', $filters['date_to']);
		}

		$perPage = $filters['per_page'] ?? 20;

		return $query->paginate($perPage);
	}

	/**
	 * Get summary statistics for adjustments.
	 */
	public function getSummary(): array
	{
		$pending = PayoutAdjustment::pending()
			->selectRaw("
				COUNT(*) as count,
				COALESCE(SUM(CASE WHEN type = 'credit' THEN net_amount ELSE 0 END), 0) as credit_total,
				COALESCE(SUM(CASE WHEN type = 'debit' THEN net_amount ELSE 0 END), 0) as debit_total
			")
			->first();

		$applied = PayoutAdjustment::applied()
			->selectRaw("
				COUNT(*) as count,
				COALESCE(SUM(CASE WHEN type = 'credit' THEN net_amount ELSE 0 END), 0) as credit_total,
				COALESCE(SUM(CASE WHEN type = 'debit' THEN net_amount ELSE 0 END), 0) as debit_total
			")
			->first();

		$voided = PayoutAdjustment::voided()->count();

		return [
			'pending' => [
				'count' => (int) $pending->count,
				'credit_total' => round((float) $pending->credit_total, 2),
				'debit_total' => round((float) $pending->debit_total, 2),
				'net' => round((float) $pending->credit_total - (float) $pending->debit_total, 2),
			],
			'applied' => [
				'count' => (int) $applied->count,
				'credit_total' => round((float) $applied->credit_total, 2),
				'debit_total' => round((float) $applied->debit_total, 2),
				'net' => round((float) $applied->credit_total - (float) $applied->debit_total, 2),
			],
			'voided_count' => $voided,
		];
	}
}
