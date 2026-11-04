<?php

namespace App\Jobs;

use App\Services\Booking\TipService;
use App\Services\TipCenter\TipCenterService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessBulkCompleteTips implements ShouldQueue
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
            $bookings = $data['bookings'] ?? [];
            $payRollDate = $data['payroll_date'] ?? NULL;
            $startDate = $data['start_date'] ?? NULL;
            $endDate = $data['end_date'] ?? NULL;
            $createdBy = $data['created_by'] ?? NULL;
            $createdBy = $data['created_by'] ?? NULL;
            $createdByUserType = $data['created_by_user_type'] ?? NULL;

            if (!empty($bookings)) {
                $distributions = [];
                $payrollScheduleId = DB::table('gratuity_payroll_schedules')
                    ->insertGetId([
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'payroll_date' => $payRollDate,
                        'status' => 'Schedule',
                        'created_by' => $createdBy,
                        'created_by_user_type' => $createdByUserType,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ]);
                foreach ($bookings as $booking) {
                    $bookingId = $booking;
                    $distributions = TipCenterService::getAcceptedDistributions($bookingId);
                    
                    if (!empty($distributions)) {
                        $gratuity = DB::table('gratuities as g')
                            ->where('g.booking_id', $bookingId)
                            ->first(['g.id', 'g.gratuity_setting_id', 'g.amount', 'g.gratuity_type', 'g.amount', 'g.fees']);
                        if (!$gratuity) {
                            continue; // skip if no gratuity found for booking
                        }
                        foreach ($distributions as $distribution) {
                            DB::table('gratuity_distributions')
                                ->where('id', $distribution->id)
                                ->update([
                                    'status' => 'Schedule',
                                    'payroll_schedule_id' => $payrollScheduleId,
                                    'updated_at' => Carbon::now()
                                ]);
                        }
                    }
                }
            }
        } catch (Exception $exception) {
            Log::error($exception);
        }
    }
}
