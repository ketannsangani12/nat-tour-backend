<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\TourBookingPaymentLink;
use App\Models\TourBookingPaymentLinkItem;
use Exception;

class UpdatePaymentlinkExpiryDate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 0;

    /**
     * The data array.
     *
     * @var $data
     */
    public $data;

    /**
     * Create a new job instance.
     *
     * @param  array $data
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $data = $this->data;
            foreach ($data as $value) {
               TourBookingPaymentLink::where('id', $value->id)->update(['status' => 'Expired']);
               TourBookingPaymentLinkItem::where('payment_link_id', $value->id)
                   ->where('status', 'Unpaid')
                   ->update(['status' => 'Expired', 'updated_at' => now()]);
            }
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
        }
    }
}
