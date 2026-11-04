<?php

namespace App\Jobs\TimeSlot;

use App\Models\TourSlotTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateTimeSlotSeats implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 0;

    /**
     * The date string.
     *
     * @var $date
     */
    public $date;

    /**
     * Create a new job instance.
     *
     * @param  string  $date
     * @return void
     */
    public function __construct($date)
    {
        $this->date = $date;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $package6 = DB::selectOne("SELECT
                tst.date AS scheduled_date,
                (IF(tst.time_slot_seats = 0, 6, tst.time_slot_seats) -
                IFNULL((SELECT SUM(tbmm.tourists)
                        FROM tour_bookings_master AS tbmm
                            JOIN tour_slot_times AS tstt ON tstt.id=tbmm.tour_slot_id
                        WHERE tbmm.status != 'Cancelled'
                            AND tbmm.tour_package_id = 6
                            AND tstt.time_from = '09:00:00'
                            AND tstt.date = tst.date
                        GROUP BY tbmm.tour_slot_id, tbmm.tour_package_id), 0)) AS available_seats
            FROM `tour_slot_times` AS tst
            LEFT JOIN `tour_bookings_master` AS tbm ON tst.id = tbm.tour_slot_id
            WHERE tst.tour_package_id = 6
                AND tst.time_from = '09:00:00'
                AND tst.deleted_by = 0
                AND tst.date = '" . $this->date . "'
            GROUP BY tst.id
            ORDER BY tst.date");

            $package1 = TourSlotTime::where('date', $this->date)
                ->where('time_from', '09:00:00')
                ->where('tour_package_id', 1)
                ->where('deleted_by', 0)
                ->first();

            $timeSlotSeats = (int) $package1->time_slot_seats;
            $availableSeats = (int) $package6->available_seats;
            if ($availableSeats > 0) {
                $package1->time_slot_seats = $timeSlotSeats + $availableSeats;
                $package1->save();
            }

            //-----------------------------------------------------------------------------------------------//

            $package6 = DB::selectOne("SELECT
                tst.date AS scheduled_date,
                (IF(tst.time_slot_seats = 0, 6, tst.time_slot_seats) -
                IFNULL((SELECT SUM(tbmm.tourists)
                        FROM tour_bookings_master AS tbmm
                            JOIN tour_slot_times AS tstt ON tstt.id=tbmm.tour_slot_id
                        WHERE tbmm.status != 'Cancelled'
                            AND tbmm.tour_package_id = 6
                            AND tstt.time_from = '09:30:00'
                            AND tstt.date = tst.date
                        GROUP BY tbmm.tour_slot_id, tbmm.tour_package_id), 0)) AS available_seats
            FROM `tour_slot_times` AS tst
            LEFT JOIN `tour_bookings_master` AS tbm ON tst.id = tbm.tour_slot_id
            WHERE tst.tour_package_id = 6
                AND tst.time_from = '09:30:00'
                AND tst.deleted_by = 0
                AND tst.date = '" . $this->date . "'
            GROUP BY tst.id
            ORDER BY tst.date");

            $package1 = TourSlotTime::where('date', $this->date)
                ->where('time_from', '09:30:00')
                ->where('tour_package_id', 1)
                ->where('deleted_by', 0)
                ->first();

            $timeSlotSeats = (int) $package1->time_slot_seats;
            $availableSeats = (int) $package6->available_seats;
            if ($availableSeats > 0) {
                $package1->time_slot_seats = $timeSlotSeats + $availableSeats;
                $package1->save();
            }
        } catch (Exception $exception) {
            Log::error($exception);
        }
    }
}
