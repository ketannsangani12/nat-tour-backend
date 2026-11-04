<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TourSlotSummary extends Model
{
    protected $table = 'tour_slot_summaries';

    protected $fillable = [
        'tour_slot_id',
        'tour_package_id',
        'date',
        'time_from',
        'time_to',
        'total_seats',
        'booked_tourists',
        'available_seats',
        'overbooked_count',
    ];

    public $timestamps = true;
}
