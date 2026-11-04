<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SwitchCompany extends Model
{
    protected $table = 'switch_companies';

    protected $fillable = [
        'network_id',
        'group_id',
        'tenant_id',
        'tenant_name',
        'user_id',
        'user_type',
    ];
}
