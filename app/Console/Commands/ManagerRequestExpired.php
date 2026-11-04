<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class ManagerRequestExpired extends Command
{
     /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'manager_request_expired';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'manager:request-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manager request expire in next date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            if (isset(tenant()->id) && tenant()->id !== 'live' && !empty(tenant()->id)) {
                DB::table('booking_requests as br')
                ->where('status', '=', 'Pending')
                ->whereDate('created_at', '<', Carbon::today())
                ->update(['status' => 'Expired']);
            }

            
        } catch (Exception $exception) {
            Log::error($exception);
        }
    }
}
