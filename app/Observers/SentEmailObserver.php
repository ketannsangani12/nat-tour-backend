<?php
  
namespace App\Observers;

use Illuminate\Support\Facades\Log;
use jdavidbakr\MailTracker\Model\SentEmail;
  
class SentEmailObserver
{
  
    /**
     * Handle the SentEmail "created" event.
     *
     * @param  \jdavidbakr\MailTracker\Model\SentEmail  $sentEmail
     * @return void
     */
    public function creating(SentEmail $sentEmail)
    {
        // Log::info('creating');
        // Log::info($sentEmail->getHeader('X-Model-ID'));
        // Log::info($sentEmail->getHeader('X-Model-TP'));
    }
  
    /**
     * Handle the SentEmail "created" event.
     *
     * @param  \jdavidbakr\MailTracker\Model\SentEmail  $sentEmail
     * @return void
     */
    public function created(SentEmail $sentEmail)
    {
        // Log::info('created');
        // Log::info($sentEmail->getHeader('X-Model-ID'));
        // Log::info($sentEmail->getHeader('X-Model-TP'));
        // Log::info($sentEmail);
        // Log::info(json_decode($sentEmail, true));
        // Log::info($sentEmail->id);

        $sentEmail->tour_booking_id = $sentEmail->getHeader('X-Model-ID') ?? null;
        $sentEmail->email_type = $sentEmail->getHeader('X-Model-TP') ?? null;
        $sentEmail->save();
    }
  
    /**
     * Handle the SentEmail "updated" event.
     *
     * @param  \jdavidbakr\MailTracker\Model\SentEmail  $sentEmail
     * @return void
     */
    public function updated(SentEmail $sentEmail)
    {
        // Log::info('updated');
        // Log::info($sentEmail->getHeader('X-Model-ID'));
        // Log::info($sentEmail->getHeader('X-Model-TP'));
        // Log::info($sentEmail);
        // Log::info(json_decode($sentEmail, true));
        // Log::info($sentEmail->id);

        // $sentEmail->tour_booking_id = $sentEmail->getHeader('X-Model-ID') ?? $sentEmail->tour_booking_id;
        // $sentEmail->email_type = $sentEmail->getHeader('X-Model-TP') ?? $sentEmail->email_type;
        // $sentEmail->save();
    }
  
    /**
     * Handle the SentEmail "deleted" event.
     *
     * @param  \jdavidbakr\MailTracker\Model\SentEmail  $sentEmail
     * @return void
     */
    public function deleted(SentEmail $sentEmail)
    {
        // Log::info('deleted');
        // Log::info($sentEmail->getHeader('X-Model-ID'));
        // Log::info($sentEmail->getHeader('X-Model-TP'));
    }
  
    /**
     * Handle the SentEmail "restored" event.
     *
     * @param  \jdavidbakr\MailTracker\Model\SentEmail  $sentEmail
     * @return void
     */
    public function restored(SentEmail $sentEmail)
    {
        // Log::info('restored');
        // Log::info($sentEmail->getHeader('X-Model-ID'));
        // Log::info($sentEmail->getHeader('X-Model-TP'));
    }
  
    /**
     * Handle the SentEmail "force deleted" event.
     *
     * @param  \jdavidbakr\MailTracker\Model\SentEmail  $sentEmail
     * @return void
     */
    public function forceDeleted(SentEmail $sentEmail)
    {
        // Log::info('forceDeleted');
        // Log::info($sentEmail->getHeader('X-Model-ID'));
        // Log::info($sentEmail->getHeader('X-Model-TP'));
    }
}
