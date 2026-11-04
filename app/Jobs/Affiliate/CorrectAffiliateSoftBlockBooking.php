<?php

namespace App\Jobs\Affiliate;

use App\Models\BookingTransact;
use App\Models\Customer;
use App\Models\SurchargeDate;
use App\Models\TourBooking;
use App\Models\TourBookingHistory;
use App\Models\TourPackageRate;
use App\Models\TourSlotTime;
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

class CorrectAffiliateSoftBlockBooking implements ShouldQueue
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
     * The request data.
     *
     * @var $data
     */
    public $data;

    /**
     * Create a new job instance.
     *
     * @param  int $id
     * @param  array $data
     * @return void
     */
    public function __construct($id, $data)
    {
        $this->id = $id;
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
            $id = $this->id;
            $booking = TourBooking::find($id);
            if ($booking) {
                $transaction = Transaction::where('tour_booking_id', $booking->id)->count();
                // Also skip a booking that's ever had a split or a pax removed --
                // overwriting its tourists/rate here would corrupt that state the
                // same way a blind mass-update would (see SoftBlockController).
                if ($transaction == 0 && !hasSplitOrRemovedPax($booking->id)) {
                    $bookingRate = DB::table('tour_bookings_transact AS tbt')
                        ->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
                        ->where('tbt.tour_booking_id', $booking->id)
                        ->orderByDesc('tpr.age_from')
                        ->orderByDesc('tpr.age_to')
                        ->first([
                            'tbt.id',
                            'tbt.tour_package_rate_id'
                        ]);

                    $customer = Customer::find($booking->customer_id);

                    $slot = TourSlotTime::find($booking->tour_slot_id);
                    $isBlackoutDate = isBlackoutDateEnabled($slot->date);
                    $affiliateIdforRate = $customer->id && !$isBlackoutDate ? $customer->id : NULL;
                    /* START: Custom Date Rate Validation */
                    $standardRateDate = SurchargeDate::where('affiliate_id', $affiliateIdforRate)
                        ->where('tour_package_id', $slot->tour_package_id)
                        ->where('date_range_start', '<=', $slot->date)
                        ->where('date_range_end', '>=', $slot->date)
                        ->orderBy('priority', 'DESC')
                        ->first();

                    if (isset($standardRateDate) && $standardRateDate->apply_on_slots) {
                        $customRateDate = isCustomDateRateEnabled($slot->date, $slot->tour_package_id, $affiliateIdforRate, $slot->id);
                    } else {
                        $customRateDate = isCustomDateRateEnabled($slot->date, $slot->tour_package_id, $affiliateIdforRate);
                    }
                    /* END */

                    $tourPackageRate = TourPackageRate::where('tour_package_id', $booking->tour_package_id)
                        ->where('status', 'Active')
                        ->when($customer->is_affiliate == 'Yes' && !$isBlackoutDate, function ($query) use ($customer) {
                            return $query->where('affiliate_id', $customer->id);
                        }, function ($query): mixed {
                            return $query->whereNull('affiliate_id');
                        })
                        ->when($customRateDate, function ($query) use ($customRateDate) {
                            return $query->where('custom_date_id', $customRateDate);
                        }, function ($query): mixed {
                            return $query->whereNull('custom_date_id');
                        })
                        ->where('scheduling_year', date('Y', strtotime($slot->date)))
                        ->where('orderby', 1)
                        ->first();

                    if ($tourPackageRate) {
                        if (getNextYearCommissionPercentage($slot->date)) {
                            if ($isBlackoutDate) {
                                $serviceCommission = getNormalCommissionPercentage($booking->tour_package_id);
                                $customer->affiliate_processing_percentage = $serviceCommission['service_commission_percentage'];
                                $customer->stripe_affiliate_percentage = $serviceCommission['stripe_commission_percentage'];
                            } else {
                                $affiliateCommission = getAffiliateCommissionPercentage($customer->id);
                                $customer->affiliate_processing_percentage = $affiliateCommission['affiliate_processing_percentage'];
                                $customer->stripe_affiliate_percentage = $affiliateCommission['stripe_affiliate_percentage'];
                            }
                        } else {
                            if ($isBlackoutDate) {
                                $tourPackageInfo = DB::table('tour_packages')->where('id', $booking->tour_package_id)->first();
                                $customer->affiliate_processing_percentage = $tourPackageInfo->service_commission_percentage;
                                $customer->stripe_affiliate_percentage = $tourPackageInfo->stripe_commission_percentage;
                            }
                        }

                        $natFeesPercentage = $customer->affiliate_processing_percentage;
                        $stripeFeesPercentage = $customer->stripe_affiliate_percentage;

                        $transact = BookingTransact::find($bookingRate->id);

                        $tourists = $transact->tourists;
                        if (isset($this->data['spots']) && isset($this->data['seat_preference']) && $this->data['seat_preference'] == 1) {
                            $this->data['tour_slot_id'] = $booking->tour_slot_id;
                            $availableSeats = $this->availableSeats($this->data);
                            if ($availableSeats > 0) {
                                $tourists = $availableSeats + $tourists;
                            }
                        } else if (isset($this->data['spots']) && isset($this->data['seat_preference']) && $this->data['seat_preference'] == 0) {
                            $tourists = $this->data['spots'];
                        }

                        $rate = $tourPackageRate->rate + $tourPackageRate->additional_charge + $tourPackageRate->permit_fee + $tourPackageRate->tax;
                        $total = $tourists * $rate;

                        $transact->tourists = $tourists;
                        $transact->tour_package_rate_id = $tourPackageRate->id;
                        $transact->additional_tax_percentage = $tourPackageRate->additional_tax_percentage;
                        $transact->additional_charge = $tourPackageRate->additional_charge;
                        $transact->rate = $tourPackageRate->rate;
                        $transact->permit_fee = $tourPackageRate->permit_fee;
                        $transact->state_tax = $tourPackageRate->tax;
                        $transact->total = number_format((float) $total, 2, '.', '');
                        $transact->nat_fees_percentage = $natFeesPercentage;
                        $transact->stripe_fees_percentage = $stripeFeesPercentage;
                        $transact->save();

                        $booking->tourists = $tourists;
                        $booking->service_commission = 0;
                        $booking->total = $total;
                        $booking->booking_total = $total;
                        $booking->save();

                        $tourBookingHistory = TourBookingHistory::where('tour_booking_id', $id)
                            ->where('operation', 'Added')
                            ->first();

                        $tourBookingHistory->tourists = $tourists;
                        $jsonData = json_decode($tourBookingHistory->details, true);
                        $jsonData['newTourists'] = $tourists;
                        if (isset($jsonData['added'][0])) {
                            $currentValue = $jsonData['added'][0];
                            $newValue = preg_replace('/(\d+)$/', $tourists, $currentValue);
                            $jsonData['added'][0] = $newValue;
                        }
                        $tourBookingHistory->details = json_encode($jsonData);
                        $tourBookingHistory->save();
                    }
                }
            }
        } catch (Exception $exception) {
            Log::error($exception);
        }
    }

    /**
     * Get the remaining seats from the slot to create a soft block
     * 
     * @param array $data
     */
    public function availableSeats($data)
    {
        $slotTimes = DB::table('tour_slot_times AS tst')
            ->where('tst.id', $data['tour_slot_id'])
            ->where('tst.tour_package_id', $data['package_id'])
            ->where('tst.bookable_status', 'Open')
            ->where('tst.deleted_by', '0')
            ->first([
                'tst.time_slot_seats as seats',
                DB::raw("IFNULL((SELECT SUM(tbm.tourists)
                FROM tour_bookings_master AS tbm
                WHERE tbm.tour_slot_id = tst.id
                    AND tbm.status != 'Cancelled'
                    AND tbm.tour_package_id = " . $data['package_id'] . "
                    GROUP BY tbm.tour_slot_id, tbm.tour_package_id), 0) AS tourists")
            ]);

        $availableSeats = 0;

        if ($slotTimes) {
            $availableSeats = max(0, $slotTimes->seats - $slotTimes->tourists);
        }

        return min($availableSeats, $data['spots']);
    }
}
