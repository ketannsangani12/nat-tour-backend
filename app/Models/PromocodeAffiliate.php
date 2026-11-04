<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class PromocodeAffiliate extends Model implements Auditable
{
    use HasFactory, AuditTrait;

    public function affiliate(): BelongsTo
	{
		return $this->belongsTo(Customer::class, 'affiliate_id');
	}
}
