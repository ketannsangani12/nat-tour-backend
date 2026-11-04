<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MassCancellation extends Model
{
	public const STATUS_QUEUED = 'queued';
	public const STATUS_PROCESSING = 'processing';
	public const STATUS_COMPLETED = 'completed';
	public const STATUS_STOPPED = 'stopped';

	protected $table = 'mass_cancellations';

	protected $fillable = [
		'tour_package_id',
		'date',
		'time_from',
		'refund_type',
		'comment',
		'send_cancel_mail',
		'booking_ids',
		'results',
		'status',
		'message',
		'total',
		'cancelled_count',
		'created_by',
		'created_by_guard',
		'created_by_user_type',
		'client_ip',
		'started_at',
		'finished_at',
	];

	protected $casts = [
		'booking_ids' => 'array',
		'results' => 'array',
		'send_cancel_mail' => 'boolean',
		'started_at' => 'datetime',
		'finished_at' => 'datetime',
	];

	/**
	 * Runs still being processed by the queue.
	 */
	public function scopeActive($query)
	{
		return $query->whereIn('status', [self::STATUS_QUEUED, self::STATUS_PROCESSING]);
	}

	public function isFinished(): bool
	{
		return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_STOPPED], true);
	}

	/**
	 * Set the result of one booking.
	 */
	public function setResult(int $bookingId, string $status, string $message): void
	{
		$results = $this->results;
		foreach ($results as &$result) {
			if ((int) $result['id'] === $bookingId) {
				$result['status'] = $status;
				$result['message'] = $message;
			}
		}
		$this->results = $results;
	}

	/**
	 * Mark every booking still waiting in the queue as not processed.
	 */
	public function markRemainingNotProcessed(): void
	{
		$this->results = array_map(function ($result) {
			if (in_array($result['status'], ['queued', 'processing'], true)) {
				$result['status'] = 'not_processed';
				$result['message'] = 'Not cancelled';
			}
			return $result;
		}, $this->results);
	}

	/**
	 * Payload returned to the mass cancellation page.
	 */
	public function toProgressArray(): array
	{
		return [
			'id' => $this->id,
			'status' => $this->status,
			'message' => $this->message,
			'total' => $this->total,
			'cancelled' => $this->cancelled_count,
			'results' => $this->results,
		];
	}
}
