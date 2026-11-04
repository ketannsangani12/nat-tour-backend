<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\UpdatePaymentlinkExpiryDate;
use Carbon\Carbon;
use Exception;

class UpdatePaymentLinkStatus extends Command
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'payment_link_status_update';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment-link-status:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Payment link status for expiry date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            if (isset(tenant()->id) && tenant()->id !== 'live' && !empty(tenant()->id)) {

                $today = today();
                DB::table('tour_booking_payment_links')
                        ->where('status', 'Unpaid')
                        ->whereDate('expired_at','<', $today)
                        ->orderBy('id')
                        ->chunk(2000, function ($data) {
                            dispatch(new UpdatePaymentlinkExpiryDate($data));
                        });
            }
        } catch (Exception $exception) {
            Log::error($exception);
        }
    
    }
}
