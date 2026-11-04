<?php

namespace App\Jobs\Affiliate;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FetchAffiliateBookingByCustomer implements ShouldQueue
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
     *
     * @return void
     */
    public function handle()
    {
        try {
            $data = $this->data;

			$bookings = DB::table('tour_bookings_master AS tbm')
                ->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
                ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
                ->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
                ->join('customers as cus', 'cus.id', '=', 'tbm.customer_id')
                ->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
                ->where('cus.id', $data['id1'])
                ->where('tp.id', 1)
                ->where('tbm.is_reserved_for_affiliate', 1)
                ->where('tbm.status', 'Booked')
                ->where('tsd.date', '>', '2024-01-14')
                ->where('tst.time_from', '11:30:00')
                ->where('tsd.status', 'Active')
                ->where('tsd.deleted_by', 0)
                ->where('tst.deleted_by', 0)
                ->whereNull('tbm.updated_by')
                ->get('tbm.id');

            if ($bookings->count()) {
                foreach ($bookings as $booking) {
                    dispatch(new SwapAffiliateBookingSeats($booking->id, $data['id2']));
                }
            }
        } catch (Exception $exception) {
            Log::error($exception);
        }
    }
}
