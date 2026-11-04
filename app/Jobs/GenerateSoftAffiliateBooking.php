<?php

namespace App\Jobs;

use App\Constants\Constants;
use App\Models\Admin;
use App\Models\BookingTransact;
use App\Models\Customer;
use App\Models\SurchargeDate;
use App\Models\TourBooking;
use App\Services\TimeSlot\TimeSlotAvailabilityService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateSoftAffiliateBooking implements ShouldQueue
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

            $tourSlot = DB::table('tour_slot_times as tst')
                ->where('tst.tour_package_id', '=', $data['package_id'])
                ->where('tst.date', '=', $data['date'])
                ->where('tst.time_from', '=', $data['time'])
                ->where('tst.bookable_status', '=', 'Open')
                ->select('tst.id', 'tst.date')
                ->first();

            if ($tourSlot) {
                $checkBooking = TourBooking::where('tour_package_id', $data['package_id'])
                    ->where('tour_slot_id', $tourSlot->id)
                    ->where('customer_id', $data['affiliate_id'])
                    ->where('is_reserved_for_affiliate', $data['is_reserved_for_affiliate'])
                    ->where('status', 'Booked')
                    ->first();

                if ($checkBooking == null) {
                    $data['tour_slot_id'] = $tourSlot->id;
                    if ($data['seat_preference']) {
                        $data['spots'] = $this->availableSeats($data);
                    }

                    if ($data['spots']) {
                        $isBlackoutDate = isBlackoutDateEnabled($data['date']);
                        $affiliateIdforRate = $data['affiliate_id'] && !$isBlackoutDate ? $data['affiliate_id'] : NULL;
                        /* START: Custom Date Rate Validation */
                        $standardRateDate = SurchargeDate::where('affiliate_id', $affiliateIdforRate)
                            ->where('tour_package_id', $data['package_id'])
                            ->where('date_range_start', '<=', $data['date'])
                            ->where('date_range_end', '>=', $data['date'])
                            ->orderBy('priority', 'DESC')
                            ->first();

                        if (isset($standardRateDate) && $standardRateDate->apply_on_slots) {
                            $customRateDate = isCustomDateRateEnabled($data['date'], $data['package_id'], $affiliateIdforRate, $tourSlot->id);
                        } else {
                            $customRateDate = isCustomDateRateEnabled($data['date'], $data['package_id'], $affiliateIdforRate);
                        }
                        /* END */

                        $packageRate = DB::table('tour_package_rates as tpr')
                            ->where('tpr.tour_package_id', '=', $data['package_id'])
                            ->where('tpr.affiliate_id', $affiliateIdforRate)
                            ->when($customRateDate, function ($query) use ($customRateDate) {
                                return $query->where('tpr.custom_date_id', $customRateDate);
                            }, function ($query): mixed {
                                return $query->whereNull('tpr.custom_date_id');
                            })
                            ->where('tpr.scheduling_year', 2025)
                            ->where('tpr.orderby', 1)
                            ->first();

                        if ($packageRate) {
                            $adminEmail = $data['admin_email'] ?? 'a@b.co';
                            $admin = Admin::where('email', $adminEmail)->first();

                            $customer = Customer::find($data['affiliate_id']);


                            if (getNextYearCommissionPercentage($tourSlot->date)) {
                                if ($isBlackoutDate) {
                                    $serviceCommission = getNormalCommissionPercentage($data['package_id']);
                                    $customer->affiliate_processing_percentage = $serviceCommission['service_commission_percentage'];
                                    $customer->stripe_affiliate_percentage = $serviceCommission['stripe_commission_percentage'];
                                } else {
                                    $affiliateCommission = getAffiliateCommissionPercentage($customer->id);
                                    $customer->affiliate_processing_percentage = $affiliateCommission['affiliate_processing_percentage'];
                                    $customer->stripe_affiliate_percentage = $affiliateCommission['stripe_affiliate_percentage'];
                                }
                            } else {
                                if ($isBlackoutDate) {
                                    $tourPackageInfo = DB::table('tour_packages')->where('id', $data['package_id'])->first();
                                    $customer->affiliate_processing_percentage = $tourPackageInfo->service_commission_percentage;
                                    $customer->stripe_affiliate_percentage = $tourPackageInfo->stripe_commission_percentage;
                                }
                            }

                            $booking = new TourBooking();
                            $booking->is_reserved_for_affiliate = Constants::STATUS_ONE;
                            $booking->tour_package_id = $data['package_id'];
                            $booking->tour_slot_id = $tourSlot->id;
                            $booking->customer_id = $customer->id;
                            $booking->name = $customer->name;
                            $booking->email = $customer->email;
                            $booking->phone_number = $customer->phone_number;
                            $booking->tourists = $data['spots'];
                            $booking->status = 'Booked';
                            $booking->visitor = '127.0.0.1';
                            $booking->request_from = 'BACKEND';
                            $booking->created_by = $admin->id;
                            $booking->created_by_user_type = 'Admin';

                            if ($booking->save()) {
                                $package = DB::table('tour_packages as tp')
                                    ->join('tour_operators as to', 'to.id', '=', 'tp.tour_operator_id')
                                    ->join('cities as c', 'c.id', '=', 'to.city_id')
                                    ->join('states as s', 's.id', '=', 'c.state_id')
                                    ->join('taxes as t', 't.state_id', '=', 's.id')
                                    ->where('tp.id', '=', $data['package_id'])
                                    ->select('s.permit_fee', 't.value as tax', 'tp.service_commission_percentage', 'tp.stripe_commission_percentage', 'tp.name', 'tp.email')
                                    ->first();

                                $rate = $packageRate->rate + $packageRate->additional_charge + $packageRate->permit_fee + $package->tax;
                                $subtotal = number_format((float) $rate, 2, '.', '');
                                $totalCost = $data['spots'] * $subtotal;

                                $transact = new BookingTransact();
                                $transact->tour_booking_id = $booking->id;
                                $transact->tour_package_rate_id = $packageRate->id;
                                $transact->tourists = $data['spots'];
                                $transact->additional_tax_percentage = $packageRate->additional_tax_percentage;
                                $transact->additional_charge = $packageRate->additional_charge;
                                $transact->rate = $packageRate->rate;
                                $transact->permit_fee = $packageRate->permit_fee;
                                $transact->state_tax = $package->tax;
                                $transact->total = number_format((float)$totalCost, 2, '.', '');
                                $transact->nat_fees_percentage = $customer->affiliate_processing_percentage;
                                $transact->stripe_fees_percentage = $customer->stripe_affiliate_percentage;
                                $transact->created_by = $admin->id;
                                $transact->created_by_user_type = 'Admin';
                                $transact->save();

                                $transactBookingTotal = DB::table('tour_bookings_transact as tpr')
                                    ->where('tpr.tour_booking_id', '=', $booking->id)
                                    ->where('tpr.deleted_by', 0)
                                    ->get()
                                    ->sum("total");

                                $bookingTotal = number_format((float) $transactBookingTotal, 2, '.', '');

                                TourBooking::where('id', $booking->id)
                                    ->update([
                                        'total' => $bookingTotal,
                                        'booking_total' => $bookingTotal,
                                    ]);

                                $addedPeoplesText[] = $packageRate->rate_for . " person " . $data['spots'];
                                $created_by_with_name = $admin->name . ': (' . "Admin" . ')';

                                DB::table('tour_booking_history')
                                    ->insert([
                                        [
                                            'tour_booking_id' => $booking->id,
                                            'tourists' => $booking->tourists,
                                            'operation' => 'Added',
                                            'details' => json_encode([
                                                'sub_operation' => 'Tourists Added',
                                                'discount_percentage' => 0,
                                                'discount2_value' => 0,
                                                'added' => $addedPeoplesText,
                                                'newTourists' => $data['spots'],
                                                'created_by_with_name' => $created_by_with_name
                                            ], JSON_NUMERIC_CHECK),
                                            'operation_at' => mysqlDT(),
                                            'operation_by' => $admin->id,
                                            'operation_by_user_type' => 'Admin',
                                            'updated_at' => Carbon::now(),
                                            'created_at' => Carbon::now(),
                                        ]
                                    ]);

                                TimeSlotAvailabilityService::updateSlotSummary($booking->tour_slot_id, $booking->tourists);
                            }
                        }
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
