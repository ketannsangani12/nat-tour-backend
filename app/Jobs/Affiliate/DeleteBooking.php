<?php

namespace App\Jobs\Affiliate;

use App\Models\BookingTransact;
use App\Models\TourBooking;
use App\Models\TourBookingHistory;
use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeleteBooking implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 0;

    /**
     * The id of booking.
     *
     * @var $id
     */
    public $id;

    /**
     * Create a new job instance.
     *
     * @param  array $id
     * @return void
     */
    public function __construct($id)
    {
        $this->id = $id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        DB::beginTransaction();
        try {
            $id = $this->id;
            $booking = TourBooking::find($id);
            if ($booking) {
                $transactions = Transaction::where('tour_booking_id', $booking->id)->get();
                foreach ($transactions as $key => $transaction) {
                    $transaction->forceDelete();
                }

                $tourBookingHistories = TourBookingHistory::where('tour_booking_id', $booking->id)->get();
                foreach ($tourBookingHistories as $key => $tourBookingHistory) {
                    $tourBookingHistory->forceDelete();
                }

                $bookingTransacts = BookingTransact::where('tour_booking_id', $booking->id)->get();
                foreach ($bookingTransacts as $key => $bookingTransact) {
                    $bookingTransact->forceDelete();
                }

                $booking->forceDelete();
                DB::commit();
            }
        } catch (Exception $exception) {
            Log::error($exception);
            DB::rollBack();
        }
    }
}
