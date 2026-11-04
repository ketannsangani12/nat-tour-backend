<?php

namespace App\Jobs;

use App\Models\TourDateSlot;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateBulkSlots implements ShouldQueue
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

			if (isset($data['operation_type']) && $data['operation_type'] == 'Update Seats') {
				$dateSlot = TourDateSlot::where('tour_package_id', $data['tour_package'])
					->where('date',$data['date'])
					->first();

				if ($dateSlot) {
					$dateSlot->seats = $data['seats'];
					$dateSlot->status = $data['status'];
					$dateSlot->updated_by = $data['operation_by'];
					$dateSlot->updated_by_user_type	 = $data['operation_by_user_type'];
					$dateSlot->save();
				} else {
					$slotDateTable = [
						'tour_package_id' => $data['tour_package'],
						'date' => $data['date'],
						'seats' => $data['seats'],
						'status' => $data['status'],
						'created_by' => $data['operation_by'],
						'created_by_user_type' => $data['operation_by_user_type']
					];

					$tourSlot = TourDateSlot::create($slotDateTable);
				}
			} else {
				if (isset($data['time_from']) && !empty($data['time_from']) && isset($data['time_to']) && !empty($data['time_to'])) {
					$slotDateTable = [
						'tour_package_id' => $data['tour_package'],
						'date' => $data['date'],
						'seats' => 0,
						'status' => $data['status'],
						'created_by' => $data['created_by'],
						'created_by_user_type' => $data['created_by_user_type']
					];

					// From the Tour Slots Bulk Manager: some dates in the range may already have a
					// TourDateSlot — reuse it (add the new time slots under it) instead of creating a
					// duplicate date row. For every other caller the behaviour is unchanged.
					if (!empty($data['from_bulk_manager'])) {
						$tourSlot = TourDateSlot::where('tour_package_id', $data['tour_package'])
							->where('date', $data['date'])
							->first()
							?: TourDateSlot::create($slotDateTable);
					} else {
						$tourSlot = TourDateSlot::create($slotDateTable);
					}

					// From the Bulk Manager: collect the date's existing slot times so duplicates are skipped.
					$existingTimes = [];
					if (!empty($data['from_bulk_manager'])) {
						$existingTimes = array_flip(
							DB::table('tour_slot_times')
								->where('tour_package_id', $data['tour_package'])
								->where('date', $data['date'])
								->where('deleted_by', 0)
								->whereNotNull('time_from')
								->pluck('time_from')
								->map(fn ($t) => date('H:i:s', strtotime((string) $t)))
								->all()
						);
					}

					for ($i = 0, $timeSlots = $affiliateTimeSlots = []; $i < count($data['time_from']); $i++) {
						// Ignore a time that already exists for this date → never create a duplicate time slot.
						if (!empty($data['from_bulk_manager'])
							&& isset($existingTimes[date('H:i:s', strtotime((string) $data['time_from'][$i]))])) {
							continue;
						}
						if (!isset($data['hide_slot'][$i])) {
							$data['hide_slot'][$i] = 0;
						}
						$timeSlots[] = [
							'tour_slot_id' => $tourSlot->id,
							'tour_package_id' => $data['tour_package'],
							'time_slot_seats' => $data['time_slot_seats'][$i],
							'date' => $data['date'],
							'time_from' => $data['time_from'][$i],
							'time_to' => $data['time_to'][$i],
							'bookable_status' => $data['bookable_status'][$i],
							'hide_slot' => $data['hide_slot'][$i],
							'created_at' => Carbon::now(),
							'created_by' => $data['created_by'],
							'created_by_user_type' => $data['created_by_user_type']
						];

						// Check timeslot added for affiliates in tour_slot_times_affiliate table
						$tourPackageId = $data['tour_package'];
						$timeFrom = $data['time_from'][$i];
						$timeTo = $data['time_to'][$i];
						$currentDateTime = Carbon::now(); // Assuming you're using Carbon for date/time operations
						$createdBy = $data['created_by'];
						$createdByUserType = $data['created_by_user_type'];
						
						$affiliateTimeSlots = affiliateTimeSlots($tourPackageId, $timeFrom, $timeTo, $currentDateTime, $createdBy, $createdByUserType, $affiliateTimeSlots);
					}

					if (!empty($timeSlots)) {
						DB::table('tour_slot_times')->insert($timeSlots);
					}

					// Store affiliate timeslots in tour_slot_times_affiliate table
					if (!empty($affiliateTimeSlots)) {
						DB::table('tour_slot_times_affiliate')->insert($affiliateTimeSlots);
					}
				}
			}
        } catch (Exception $exception) {
			Log::error($exception);
        }
    }
}
