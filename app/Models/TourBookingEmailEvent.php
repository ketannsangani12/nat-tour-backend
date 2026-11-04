<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use jdavidbakr\MailTracker\Model\SentEmail;

class TourBookingEmailEvent extends Model
{
    use HasFactory;

    protected $table = 'tour_booking_email_events';

    protected $fillable = [
        'tour_booking_id',
        'tour_booking_history_id',
        'sent_email_id',
        'status',
        'opens',
        'clicks',
        'reason',
    ];

    public function tourBooking()
    {
        return $this->belongsTo(TourBooking::class, 'tour_booking_id', 'id');
    }

    public function tourBookingHistory()
    {
        return $this->belongsTo(TourBookingHistory::class, 'tour_booking_history_id', 'id');
    }

    public function sentEmail()
    {
        return $this->belongsTo(SentEmail::class, 'sent_email_id', 'id');
    }
}
