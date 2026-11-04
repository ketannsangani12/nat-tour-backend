<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuideSpecificCapacity extends Model
{
    protected $table = "guide_specific_capacity";

    protected $fillable = [
        'guide_id',
        'station_id',
        'time_from',
        'date',
        'capacity',
    ];
}
