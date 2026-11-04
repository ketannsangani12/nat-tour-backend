<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class Transaction extends Model implements Auditable
{
	use SoftDeletes, HasFactory, AuditTrait;

	const UPDATED_AT = null;

	protected $table = 'transactions';

	protected $fillable = [
		'tour_booking_id',
		'amount',
		'transaction_number',
		'via',
		'response_init',
		'response_end',
		'response_end_datetime',
		'status',
		'is_reallocated',
		'visitor',
		'request_from',
		'created_by',
		'created_by_user_type'
	];

	public function tourBooking()
	{
		return $this->belongsTo(TourBooking::class);
	}

	public function bookingHistory()
	{
		return $this->hasOne(TourBookingHistory::class, 'transaction_id');
	}

	/**
	 * Get the dispute associated with this transaction.
	 */
	public function dispute()
	{
		return $this->hasOne(Dispute::class, 'transaction_id');
	}

	/**
	 * Check if this transaction is disputed.
	 */
	public function isDisputed(): bool
	{
		return $this->dispute()->exists();
	}

	/**
	 * Get the dispute status for this transaction.
	 */
	public function getDisputeStatus(): ?string
	{
		return $this->dispute?->status;
	}

	/**
	 * Get the dispute amount if this transaction is disputed.
	 */
	public function getDisputeAmount(): ?float
	{
		return $this->dispute?->amount;
	}

	/**
	 * Get the dispute reason if this transaction is disputed.
	 */
	public function getDisputeReason(): ?string
	{
		return $this->dispute?->reason;
	}

	/**
	 * Check if this transaction's dispute requires attention.
	 */
	public function requiresDisputeAttention(): bool
	{
		return $this->dispute?->requiresAttention() ?? false;
	}

	/**
	 * Get dispute information for this transaction.
	 */
	public function getDisputeInfo(): ?array
	{
		if (!$this->dispute) {
			return null;
		}

		return [
			'id' => $this->dispute->stripe_dispute_id,
			'amount' => $this->dispute->amount,
			'currency' => $this->dispute->currency,
			'reason' => $this->dispute->reason_label,
			'status' => $this->dispute->status_label,
			'deadline' => $this->dispute->evidence_due_by?->format('M j, Y'),
			'requires_attention' => $this->dispute->requiresAttention()
		];
	}

	protected $casts = [
		'is_reallocated' => 'boolean',
	];

	protected static function booted()
	{
		static::creating(function($transaction) {

			$transaction->created_by = (auth()->id()) ? auth()->id() : 1;
			$transaction->created_by_user_type = (session('userType')) ? session('userType') : 'Tour Operator';
		});
	}

	public $import = [
        
        'fields' => [
            'via' 	=> ['displayName' => 'via',],
        ],

    ];
}
