<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\TourSlotSummary;
use App\Models\TourSlotTime;
use Exception;

class InsertSlotSeatsSummary implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 0;

    /**
     * The data collection.
     *
     * @var \Illuminate\Support\Collection
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
            foreach ($this->data as $value) {
                $touristsDiff = $value->total_tourists;
                $slotId = $value->tour_slot_id;

                $slotTime = TourSlotTime::find($slotId);

                if (!$slotTime) {
                    Log::warning("Slot ID {$slotId} not found while processing summary.");
                    continue; // ✅ skip invalid slot IDs
                }

                $slotSummary = TourSlotSummary::firstOrCreate(
                    [
                        'tour_slot_id' => $slotTime->id,
                        'tour_package_id' => $slotTime->tour_package_id,
                    ],
                    [
                        'total_seats' => $slotTime->time_slot_seats,
                        'booked_tourists' => 0,
                        'available_seats' => 0,
                        'overbooked_count' => 0,
                        'date' => $slotTime->date,
                        'time_from' => $slotTime->time_from,
                        'time_to' => $slotTime->time_to,
                    ]
                );

                $slotSummary->booked_tourists = $touristsDiff;
                $slotSummary->available_seats = max(0, $slotSummary->total_seats - $slotSummary->booked_tourists);
                $slotSummary->overbooked_count = max(0, $slotSummary->booked_tourists - $slotSummary->total_seats);
                $slotSummary->save();
            }
        } catch (Exception $exception) {
            Log::error("InsertSlotSeatsSummary failed: " . $exception->getMessage());
        }
    }
}
