<?php

namespace App\Http\Controllers;

use App\Jobs\Mail\BulkBookingConfirmation;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BulkEmailController extends Controller
{
    /**
     * Send bulk emails to the customers for the bookings
     * 
     * @param string $startDate
     * @param string $endDate
     */
    public function run($startDate, $endDate)
    {
        try {
            $userId = auth()->id();
            $userType = userType();

            $bookingsCount =  DB::table('tour_bookings_master AS tbm')
                ->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
                ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
                ->whereBetween('date', [$startDate, $endDate])
                ->where('tbm.status', 'Booked')
                ->count();

            if ($bookingsCount == 0) {
                echo 'No bookings found.';
                exit;
            }

            $startDate = strtotime($startDate);

            while ($startDate <= strtotime($endDate)) {
                $date = date("Y-m-d", $startDate);

                dispatch(new BulkBookingConfirmation($date, $userId, $userType));

                $startDate = strtotime('+1 day', $startDate);
            }

            echo 'Bulk email process started in background.';
            http_response_code(200);
        } catch (Exception $exception) {
            Log::error($exception);

            echo 'Errors in bulk email process.';
            http_response_code(200);
        }
    }
}
