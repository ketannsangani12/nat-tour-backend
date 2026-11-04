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

class FetchAffiliateSoftBlockBookingByDate implements ShouldQueue
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
                ->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
                ->join('customers as cus', 'cus.id', '=', 'tbm.customer_id')
                ->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
                ->where('tst.date', $data['date'])
                ->when($data['time'] != '', function ($query) use ($data) {
                    $query->where('tst.time_from', $data['time']);
                })
                ->where('cus.id', $data['affiliate_id'])
                ->where('tp.id', $data['package_id'])
                ->where('tbm.status', 'Booked')
                ->when(isset($data['soft_blocks']) && $data['soft_blocks'] !== '', function ($query) use ($data) {
                    // Apply soft blocks filter only if it's set (not empty string)
                    $query->where('tbm.is_reserved_for_affiliate', $data['soft_blocks']);
                })
                ->get('tbm.id');

            if ($bookings->count()) {
                foreach ($bookings as $booking) {
                    dispatch(new CorrectAffiliateSoftBlockBooking($booking->id, $data));
                }
            }
        } catch (Exception $exception) {
            Log::error($exception);
        }
    }
}
