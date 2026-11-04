<?php

namespace App\Jobs;

use App\Services\Booking\TipService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessBulkAcceptTips implements ShouldQueue
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
            $createdBy = $data['created_by'] ?? NULL;
            $createdByUserType = $data['created_by_user_type'] ?? NULL;

            if (isset($data['bookings']) && !empty($data['bookings'])) {
                $distributions = [];
                foreach ($data['bookings'] as $booking) {
                    $bookingId = $booking;
                    $assignedGuides = TipService::getBookingAssignedGuides($bookingId);
                    if (!empty($assignedGuides)) {
                        $gratuity = DB::table('gratuities as g')
                            ->where('g.booking_id', $bookingId)
                            ->first(['g.id', 'g.gratuity_setting_id', 'g.amount', 'g.gratuity_type', 'g.amount', 'g.fees']);
                        if (!$gratuity) {
                            continue; // skip if no gratuity found for booking
                        }
                        foreach ($assignedGuides as $bookingGuide) {
                            $exists = DB::table('gratuity_distributions')
                                ->where('booking_id', $bookingId)
                                ->where('guide_id', $bookingGuide->guide_id)
                                ->where('gratuity_id', $gratuity->id)                       
                                ->exists();
                            if (!$exists) {
                                $distributions[] = [
                                    'booking_id' => $bookingId,
                                    'guide_id' => $bookingGuide->guide_id,
                                    'gratuity_id' => $gratuity->id,
                                    'amount' => $bookingGuide->distribution_amount,
                                    'status' => 'Ready',
                                    'created_at' => now(),
                                    'created_by' => $createdBy,
                                    'created_by_user_type' => $createdByUserType
                                ];
                            }
                        }
                    }
                }
                if (!empty($distributions)) {
                    DB::table('gratuity_distributions')->insert($distributions);
                }
            }
        } catch (Exception $exception) {
            Log::error($exception);
        }
    }
}
