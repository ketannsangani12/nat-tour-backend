<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $id
 * @property string      $uuid
 * @property int         $affiliate_id
 * @property string      $url
 * @property array|null  $headers
 * @property array       $events
 * @property bool        $is_active
 */
class OctoWebhook extends Model
{
    protected $table = 'octo_webhooks';

    protected $fillable = [
        'uuid',
        'affiliate_id',
        'url',
        'headers',
        'events',
        'is_active',
    ];

    protected $casts = [
        'headers'   => 'encrypted:array',
        'events'    => 'array',
        'is_active' => 'boolean',
    ];
}
