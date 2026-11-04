<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BoardStationPackage extends Model
{
    protected $table = 'board_station_packages';

    protected $fillable = [
        'name',
        'assign_packages',
    ];

    protected $casts = [
        'assign_packages' => 'array',
    ];
}

