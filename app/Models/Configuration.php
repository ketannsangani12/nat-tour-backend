<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuration extends Model
{
    protected $fillable = [
        'tenant_id',
        'network_id',
        'calendar_switch',
        'package_assignments',
    ];
}
