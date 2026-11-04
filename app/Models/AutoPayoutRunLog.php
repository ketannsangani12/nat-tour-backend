<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoPayoutRunLog extends Model
{
    public const OUTCOME_SKIPPED_DISABLED = 'skipped_disabled';
    public const OUTCOME_SKIPPED_NO_RULE = 'skipped_no_rule';
    public const OUTCOME_SKIPPED_NO_ENABLED_RULE = 'skipped_no_enabled_rule';
    public const OUTCOME_SKIPPED_LOCK_HELD = 'skipped_lock_held';
    public const OUTCOME_PROCESSED = 'processed';
    public const OUTCOME_ERROR = 'error';

    protected $table = 'auto_payout_run_logs';

    public $timestamps = false;

    protected $fillable = [
        'rule_id',
        'outcome',
        'message',
        'payouts_created',
        'payouts_executed',
        'duration_ms',
        'created_at',
    ];

    protected $casts = [
        'rule_id' => 'integer',
        'payouts_created' => 'integer',
        'payouts_executed' => 'integer',
        'duration_ms' => 'integer',
        'created_at' => 'datetime',
    ];

    public function rule()
    {
        return $this->belongsTo(AutoPayoutRule::class, 'rule_id');
    }
}
