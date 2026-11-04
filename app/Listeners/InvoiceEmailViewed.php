<?php

namespace App\Listeners;

use App\Models\Invoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class InvoiceEmailViewed
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     * Perform your tracking/linking tasks on $invoice knowing the SentEmail object
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        $tracker = $event->sent_email;
        $uuid = $event->sent_email->getHeader('X-Model-UUID');

        if ($uuid) {
            $invoice = Invoice::uuid($uuid)->first();
            $invoice->status = 'viewed';
            $invoice->save();
        }
    }
}
