<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\AffilaiteSeatsUpdate;
use Exception;

class UpdateAffiliateSeatsTable implements ShouldQueue
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
     * @param  mixed $data
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
            $data = (object) $this->data;
            $sortedDataArray = [];
            foreach ($data as $value) {
                $newFilteredData = [];
                if (isset($sortedDataArray[$value->tour_booking_id])) {
                    $checkInStatus = json_decode($value->check_in_status);
                    if ($checkInStatus > 0) {
                            foreach ($checkInStatus as $val) {
                                if ($val->status == '1') {
                                    $sortedDataArray[$value->tour_booking_id]['check_in_1st']++;
                                }

                                if ($val->status == '2') {
                                    $sortedDataArray[$value->tour_booking_id]['check_in_2nd']++;
                                }

                                if ($val->status == 'A') {
                                    $sortedDataArray[$value->tour_booking_id]['absent']++;
                                }

                            }

                    }
                 } else {
                    $newFilteredData['tour_booking_id'] = $value->tour_booking_id;
                    $newFilteredData['tour_package_id'] = $value->tour_package_id;
                    $newFilteredData['tour_slot_id'] = $value->tour_slot_id;
                    $newFilteredData['customer_id'] = $value->customer_id;
                    $newFilteredData['tourists'] = $value->tourists;
                    $newFilteredData['status'] = $value->status;
                    $newFilteredData['check_in_1st'] = 0;
                    $newFilteredData['check_in_2nd'] = 0;
                    $newFilteredData['absent'] = 0;
                    $checkInStatus = json_decode($value->check_in_status);

                    if ($checkInStatus > 0) {
                        foreach ($checkInStatus as $val) {
                            if ($val->status == '1') {
                                $newFilteredData['check_in_1st'] += 1;
                            }

                            if ($val->status == '2') {
                                $newFilteredData['check_in_2nd'] += 1;
                            }

                            if ($val->status == 'A') {
                                $newFilteredData['absent'] += 1;
                            }

                        }  
                    }

                    $sortedDataArray[$value->tour_booking_id] = $newFilteredData;
                }
            }

            foreach ($sortedDataArray as $sortedData) {
                AffilaiteSeatsUpdate::updateOrCreate([
                    'tour_booking_id' => $sortedData['tour_booking_id'],
                ],[
                    'tour_package_id' => $sortedData['tour_package_id'],
                    'tour_slot_id' => $sortedData['tour_slot_id'],
                    'customer_id' => $sortedData['customer_id'],
                    'tourists' => $sortedData['tourists'],
                    'check_in_1st' => $sortedData['check_in_1st'],
                    'check_in_2nd' => $sortedData['check_in_2nd'],
                    'absent' => $sortedData['absent'],
                    'check_in_not' => $sortedData['tourists'] - ($sortedData['check_in_1st'] + $sortedData['check_in_2nd'] + $sortedData['absent']),
                    'status' => $sortedData['status']
                ]);
            }
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
        }
    }
}
