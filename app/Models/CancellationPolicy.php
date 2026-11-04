<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class CancellationPolicy extends Model implements Auditable
{
	use HasFactory, AuditTrait;

	protected $table = 'cancellation_policies';

	protected $fillable = [
		'package_id',
		'title',
        'description',
        'days_hours_before',
        'type',
        'refund_basis',
        'refund_percent',
        'status'
	];

    /**
     * @param $query
     * @return object
     */
    public function scopeActive($query): object
    {
        return $query->where('status', '=', 'active');
    }
}
