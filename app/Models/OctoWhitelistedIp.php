<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $id
 * @property string      $ip
 * @property string      $label
 * @property int|null    $created_by
 * @property \Illuminate\Support\Carbon|null $expires_at
 */
class OctoWhitelistedIp extends Model
{
    protected $connection = 'mysql'; // always central DB, never switched to tenant

    protected $table = 'octo_whitelisted_ips';

    protected $fillable = [
        'ip',
        'label',
        'created_by',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }
}
