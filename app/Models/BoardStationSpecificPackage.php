<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BoardStationSpecificPackage extends Model
{
    protected $table = 'board_station_specific_packages';

    protected $fillable = [
        'station_id',
        'packages',
        'time_from',
        'date',
    ];

    protected $casts = [
        'packages' => 'array',
    ];
}
