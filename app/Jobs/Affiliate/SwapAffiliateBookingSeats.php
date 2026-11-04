<?php

namespace App\Jobs\Affiliate;

use App\Models\BookingTransact;
use App\Models\TourBooking;
use App\Models\TourBookingHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SwapAffiliateBookingSeats implements ShouldQueue
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
     * The affiliateId of booking.
     *
     * @var $affiliateId
     */
    public $affiliateId;

    /**
     * Create a new job instance.
     *
     * @param  int $id
     * @param  int $affiliateId
     * @return void
     */
    public function __construct($id, $affiliateId)
    {
        $this->id = $id;
        $this->affiliateId = $affiliateId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $id = $this->id;
            $affiliateId = $this->affiliateId;

            $booking1 = DB::table('tour_bookings_master AS tbm')
                ->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
                ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
                ->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
                ->join('customers as cus', 'cus.id', '=', 'tbm.customer_id')
                ->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
                ->where('tbm.id', $id)
                ->where('tp.id', 1)
                ->where('tbm.is_reserved_for_affiliate', 1)
                ->where('tst.time_from', '11:30:00')
                ->where('tbm.status', 'Booked')
                ->where('tsd.status', 'Active')
                ->where('tsd.deleted_by', 0)
                ->where('tst.deleted_by', 0)
                ->whereNull('tbm.updated_by')
                ->first(['tbm.id', 'tbm.customer_id', 'tbm.tour_slot_id', 'tbm.tourists', 'tbm.created_at']);

            if ($booking1) {
                $booking2 = DB::table('tour_bookings_master AS tbm')
                    ->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
                    ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
                    ->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
                    ->join('customers as cus', 'cus.id', '=', 'tbm.customer_id')
                    ->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
                    ->where('tbm.tour_slot_id', $booking1->tour_slot_id)
                    ->where('cus.id', $affiliateId)
                    ->where('tp.id', 1)
                    ->where('tbm.tourists', 8)
                    ->where('tbm.is_reserved_for_affiliate', 1)
                    ->where('tst.time_from', '11:30:00')
                    ->where('tbm.status', 'Booked')
                    ->where('tsd.status', 'Active')
                    ->where('tsd.deleted_by', 0)
                    ->where('tst.deleted_by', 0)
                    ->whereNull('tbm.updated_by')
                    ->orderByDesc('tbm.id')
                    ->first(['tbm.id', 'tbm.customer_id', 'tbm.tour_slot_id', 'tbm.tourists', 'tbm.created_at']);

                if ($booking2) {
                    // sum of both booking tourists 1
                    $tourists1 = $booking1->tourists + $booking2->tourists;

                    // For booking transact 1
                    $swapBookingTransact1 = BookingTransact::where('tour_booking_id', $booking1->id)->first();
                    $swapBookingTransact1->tourists = $tourists1;
                    // calculation 1
                    $rate1 = $swapBookingTransact1->rate + $swapBookingTransact1->additional_charge + $swapBookingTransact1->permit_fee;
                    $total1 = $tourists1 * $rate1;
                    $swapBookingTransact1->total = $total1;
                    $swapBookingTransact1->save();

                    // For booking master 1
                    $swapBooking1 = TourBooking::find($booking1->id);
                    $swapBooking1->tourists = $tourists1;
                    $swapBooking1->total = $total1;
                    $swapBooking1->booking_total = $total1;
                    $swapBooking1->save();

                    // For booking history 1
                    $swapBookingHistory1 = TourBookingHistory::where('tour_booking_id', $booking1->id)
                        ->where('operation', 'Added')
                        ->first();
                    $swapBookingHistory1->tourists = $tourists1;
                    $swapBookingHistory1->ticket_cost = 0;
                    // modification 1
                    $jsonData1 = json_decode($swapBookingHistory1->details, true);
                    $jsonData1['newTourists'] = $tourists1;
                    if (isset($jsonData1['added'][0])) {
                        $currentValue1 = $jsonData1['added'][0];
                        $newValue1 = preg_replace('/(\d+)$/', $tourists1, $currentValue1);
                        $jsonData1['added'][0] = $newValue1;
                    }
                    $swapBookingHistory1->details = json_encode($jsonData1);
                    $swapBookingHistory1->save();

                    // ==============================================================================================================================

                    $slotTime = DB::table('tour_slot_times AS tst')->where('tst.id', $booking1->tour_slot_id)->first();
                    // Log::info("PARENT SLOT - $booking1->tour_slot_id - " . ($slotTime->id ?? 'PARENT SLOT NOT FOUND'));

                    if ($slotTime) {
                        $slotTime = DB::table('tour_slot_times AS tst')
                            ->where('tst.date', $slotTime->date)
                            ->where('tst.time_from', '12:00:00')
                            ->where('tst.tour_package_id', 1)
                            ->first();

                        // Log::info("CHILD SLOT - " . ($slotTime->id ?? 'CHILD SLOT NOT FOUND'));
                        if ($slotTime) {
                            $booking3 = DB::table('tour_bookings_master AS tbm')
                                ->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
                                ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
                                ->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
                                ->join('customers as cus', 'cus.id', '=', 'tbm.customer_id')
                                ->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
                                ->where('tbm.tour_slot_id', $slotTime->id)
                                ->where('cus.id', $affiliateId)
                                ->where('tp.id', 1)
                                ->where('tbm.is_reserved_for_affiliate', 1)
                                ->where('tst.time_from', '12:00:00')
                                ->where('tbm.status', 'Booked')
                                ->where('tsd.status', 'Active')
                                ->where('tsd.deleted_by', 0)
                                ->where('tst.deleted_by', 0)
                                ->whereNull('tbm.updated_by')
                                ->orderByDesc('tbm.id')
                                ->first(['tbm.id', 'tbm.customer_id', 'tbm.tour_slot_id', 'tbm.tourists', 'tbm.created_at']);

                            // Log::info("BOOKING 3 - " . ($booking3->id ?? 'BOOKING 3 NOT FOUND'));
                            if ($booking3) {
                                // sum of both booking tourists 2
                                $tourists2 = $booking2->tourists + $booking3->tourists;

                                // For booking transact 2
                                $swapBookingTransact2 = BookingTransact::where('tour_booking_id', $booking3->id)->first();
                                $swapBookingTransact2->tourists = $tourists2;
                                // calculation 2
                                $rate2 = $swapBookingTransact2->rate + $swapBookingTransact2->additional_charge + $swapBookingTransact2->permit_fee;
                                $total2 = $tourists2 * $rate2;
                                $swapBookingTransact2->total = $total2;
                                $swapBookingTransact2->save();

                                // For booking master 2
                                $swapBooking2 = TourBooking::find($booking3->id);
                                $swapBooking2->tourists = $tourists2;
                                $swapBooking2->total = $total2;
                                $swapBooking2->booking_total = $total2;
                                $swapBooking2->save();

                                // For booking history 2
                                $swapBookingHistory2 = TourBookingHistory::where('tour_booking_id', $booking3->id)
                                    ->where('operation', 'Added')
                                    ->first();
                                $swapBookingHistory2->tourists = $tourists2;
                                $swapBookingHistory2->ticket_cost = 0;
                                // modification 2
                                $jsonData2 = json_decode($swapBookingHistory2->details, true);
                                $jsonData2['newTourists'] = $tourists2;
                                if (isset($jsonData2['added'][0])) {
                                    $currentValue2 = $jsonData2['added'][0];
                                    $newValue2 = preg_replace('/(\d+)$/', $tourists2, $currentValue2);
                                    $jsonData2['added'][0] = $newValue2;
                                }
                                $swapBookingHistory2->details = json_encode($jsonData2);
                                $swapBookingHistory2->save();

                                // Log::info($booking1->customer_id .' - '. $slotTime->id);
                                $booking4 = DB::table('tour_bookings_master AS tbm')
                                    ->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
                                    ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
                                    ->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
                                    ->join('customers as cus', 'cus.id', '=', 'tbm.customer_id')
                                    ->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
                                    ->where('tbm.tour_slot_id', $slotTime->id)
                                    ->where('cus.id', $booking1->customer_id)
                                    ->where('tp.id', 1)
                                    ->where('tbm.tourists', '>', 8)
                                    ->where('tbm.is_reserved_for_affiliate', 1)
                                    ->where('tst.time_from', '12:00:00')
                                    ->where('tbm.status', 'Booked')
                                    ->where('tsd.status', 'Active')
                                    ->where('tsd.deleted_by', 0)
                                    ->where('tst.deleted_by', 0)
                                    ->whereNull('tbm.updated_by')
                                    ->orderBy('tbm.id')
                                    ->first(['tbm.id', 'tbm.customer_id', 'tbm.tour_slot_id', 'tbm.tourists', 'tbm.created_at']);

                                // Log::info("BOOKING 4 - " . ($booking4->id ?? 'BOOKING 4 NOT FOUND'));
                                if ($booking4) {
                                    // sum of both booking tourists 3
                                    $tourists3 = $booking4->tourists - $booking2->tourists;

                                    // For booking transact 3
                                    $swapBookingTransact3 = BookingTransact::where('tour_booking_id', $booking4->id)->first();
                                    $swapBookingTransact3->tourists = $tourists3;
                                    // calculation 2
                                    $rate2 = $swapBookingTransact3->rate + $swapBookingTransact3->additional_charge + $swapBookingTransact3->permit_fee;
                                    $total2 = $tourists3 * $rate2;
                                    $swapBookingTransact3->total = $total2;
                                    $swapBookingTransact3->save();

                                    // For booking master 3
                                    $swapBooking3 = TourBooking::find($booking4->id);
                                    $swapBooking3->tourists = $tourists3;
                                    $swapBooking3->total = $total2;
                                    $swapBooking3->booking_total = $total2;
                                    $swapBooking3->save();

                                    // For booking history 3
                                    $swapBookingHistory3 = TourBookingHistory::where('tour_booking_id', $booking4->id)
                                        ->where('operation', 'Added')
                                        ->first();
                                    $swapBookingHistory3->tourists = $tourists3;
                                    $swapBookingHistory3->ticket_cost = 0;
                                    // modification 3
                                    $jsonData2 = json_decode($swapBookingHistory3->details, true);
                                    $jsonData2['newTourists'] = $tourists3;
                                    if (isset($jsonData2['added'][0])) {
                                        $currentValue2 = $jsonData2['added'][0];
                                        $newValue2 = preg_replace('/(\d+)$/', $tourists3, $currentValue2);
                                        $jsonData2['added'][0] = $newValue2;
                                    }
                                    $swapBookingHistory3->details = json_encode($jsonData2);
                                    $swapBookingHistory3->save();
                                }
                            }
                        }
                    }

                    // delete the CHD booking after transfer the seats to Mei Tours
                    // $z = TourBookingHistory::where('tour_booking_id', $booking2->id)->first();
                    // if ($z) {
                    //     TourBookingHistory::where('tour_booking_id', $booking2->id)->delete();
                    // }

                    // $y = BookingTransact::where('tour_booking_id', $booking2->id)->first();
                    // $y->deleted_by = 1;
                    // $y->deleted_by_user_type = 'Admin';
                    // $y->save();
                    // BookingTransact::where('tour_booking_id', $booking2->id)->delete();

                    $x = TourBooking::find($booking2->id);
                    $x->status = 'Cancelled';
                    $x->updated_by = 1;
                    $x->updated_by_user_type = 'Admin';
                    // $x->deleted_by = 1;
                    // $x->deleted_by_user_type = 'Admin';
                    $x->save();
                    // TourBooking::where('id', $booking2->id)->delete();
                }
            }
        } catch (Exception $exception) {
            Log::error($exception);
        }
    }
}
