<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\UpdateAffiliateSeatsTable;
use Exception;

class UpdateAffiliateSeat extends Command
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'affiliate_seat_update';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'affiliate:seat-update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Affilaite Seats Updates table update after 48 hours';


    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            if (isset(tenant()->id) && tenant()->id !== 'live' && !empty(tenant()->id)) {
                $today = date('Y-m-d');
                DB::table('tour_bookings_transact as tbt')
                    ->join('tour_bookings_master as tbm', 'tbm.id', '=', 'tbt.tour_booking_id')
                    ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
                    ->join('customers as c', 'c.id', '=', 'tbm.customer_id')
                    ->where('c.is_affiliate', 'Yes')
                    ->whereDate('tbm.created_at', $today)
                    ->select([
                        'tbt.tour_booking_id',
                        'tbm.tour_package_id',
                        'tbm.tour_slot_id',
                        'tbm.customer_id',
                        'tbm.tourists',
                        'tbt.check_in_status',
                        'tbm.status'
                    ])
                    ->orderBy('tbt.tour_booking_id')
                    ->chunk(100, function ($data) {
                        dispatch(new UpdateAffiliateSeatsTable($data));
                    });
            }
        } catch (Exception $exception) {
            Log::error($exception);
        }
    }
}
