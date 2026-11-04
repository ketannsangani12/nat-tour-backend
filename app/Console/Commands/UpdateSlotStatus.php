<?php

namespace App\Console\Commands;

use App\Jobs\TimeSlot\IdentifyBookingPackageRelation;
use App\Models\TourBooking;
use App\Repositories\TourSlot\TourSlotRepository;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateSlotStatus extends Command
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'update_slot_status';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'slot:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update slot status for relational packages';

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
     *
     * @return mixed
     */
    public function handle()
    {
        try {
            if (isset(tenant()->id) && tenant()->id === 'acx' && hasSetting('PACKAGE_RELATION')) {
                $startDate = date('Y-m-d', strtotime('-1 day')) . " 00:00:00";
                $endDate = date('Y-m-d') . " 23:59:59";

                $bookings = TourBooking::whereBetween('created_at', [$startDate, $endDate])
                    ->where('status', 'Booked')
                    ->get();

                $tourSlotRepository = new TourSlotRepository();

                foreach ($bookings as $booking) {
                    dispatch(new IdentifyBookingPackageRelation($booking, $tourSlotRepository));
                }
            }
        } catch (Exception $exception) {
            Log::error($exception);
        }
    }
}
