<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Support\Facades\DB;

class UpdateTimeSoltSeat implements ShouldQueue
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
            foreach ($data as $value) {
                if (!empty($value)) {
                    $tourSlotTime = DB::table('tour_slot_times')
                        ->where('tour_slot_id', $value->id)
                        ->whereNull('tour_package_id')
                        ->get();

                    if ($tourSlotTime->count()) {
                        foreach ($tourSlotTime as $slotTime) {
                            DB::table('tour_slot_times')
                                ->where('id', $slotTime->id)
                                ->update([
                                    'time_slot_seats' => $value->seats,
                                    'tour_package_id' => $value->tour_package_id,
                                    'date' => $value->date
                                ]);
                        }
                    }
                }
            }
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
        }
    }
}
