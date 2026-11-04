<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutoPayoutRule extends Model
{
	use HasFactory;

	protected $table = 'auto_payout_rules';

	protected $fillable = [
		'name',
		'frequency',
		'day_of_week',
		'day_of_month',
		'execution_time',
		'minimum_amount',
		'max_payout_amount',
		'buffer_days',
		'start_date',
		'notification_emails',
		'enabled',
		'requires_approval',
		'include_partial_payments',
		'last_executed_at',
		'next_execution_at'
	];

	/**
	 * Get the attributes that should be cast to native types.
	 *
	 * @return array
	 */
	protected $casts = [
		'buffer_days' => 'integer',
		// Intentionally NOT cast to 'date': a date cast parses on attribute
		// access, so a malformed value would throw before the service's guarded
		// Carbon::parse could catch it. Left as a raw string (DATE column +
		// nullable|date validation keep it valid) so reads can never throw and
		// fail a scheduler tick.
		'max_payout_amount' => 'decimal:2',
		'notification_emails' => 'array',
		'enabled' => 'boolean',
		'requires_approval' => 'boolean',
		'include_partial_payments' => 'boolean',
		'last_executed_at' => 'datetime',
		'next_execution_at' => 'datetime',
	];

	/**
	 * Get the attributes that should be hidden for arrays.
	 *
	 * @return array
	 */
	protected $hidden = [
		'created_at',
		'updated_at',
	];

	/**
	 * Get the payouts associated with this rule.
	 */
	public function payouts()
	{
		return $this->hasMany(AutoPayout::class, 'rule_id');
	}

	/**
	 * Get the pending payouts associated with this rule.
	 */
	public function pendingPayouts()
	{
		return $this->hasMany(PendingPayout::class, 'rule_id');
	}
}
