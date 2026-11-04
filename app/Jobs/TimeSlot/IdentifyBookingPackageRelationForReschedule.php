<?php

namespace App\Jobs\TimeSlot;

use App\Models\Customer;
use App\Models\TourPackageRate;
use App\Models\TourSlotTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IdentifyBookingPackageRelationForReschedule implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 0;

    /**
     * The booking object.
     *
     * @var object
     */
    public object $booking;

    /**
     * The tourSlotRepository object.
     *
     * @var object
     */
    public object $tourSlotRepository;

    /**
     * The slotId object.
     *
     * @var int
     */
    public int $slotId;

    /**
     * Create a new job instance.
     *
     * @param  object  $booking
     * @param  object  $tourSlotRepository
     * @param  int  $slotId
     * @return void
     */
    public function __construct(object $booking, object $tourSlotRepository, int $slotId)
    {
        $this->booking = $booking;
        $this->tourSlotRepository = $tourSlotRepository;
        $this->slotId = $slotId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $packageRelationSetting = hasSetting('PACKAGE_RELATION');

            if ($packageRelationSetting) {
                $tourOperator = tourOperator();

                $customer = Customer::find($this->booking->customer_id);

                $tourSlot = TourSlotTime::find($this->slotId);

                if ($tourSlot) {
                    $year = date('Y', strtotime($tourSlot->date));

                    $tourPackageFamily = DB::select("SELECT *
                        FROM tour_package_families
                        WHERE relation_year = ?
                        AND parent_package_id IN (
                            SELECT parent_package_id
                            FROM tour_package_families
                            WHERE tour_package_id = ?
                                AND relation_year = ?
                        )", [$year, $this->booking->tour_package_id, $year]);

                    foreach ($tourPackageFamily as $relation) {
                        if ($relation->is_parent == 0 && $tourSlot->time_from == $relation->time_from) {
                            $request = new Request([
                                'tourOperatorId' => $tourOperator->id,
                                'tourPackageId' => $relation->tour_package_id,
                                'date' => $tourSlot->date,
                                'getRateGroups' => 0,
                                'affiliateId' => 0,
                                'fromCreateBooking' => 1
                            ]);

                            $slotsData = $this->tourSlotRepository->getTourSlotTimesByDate($request);

                            $slotTime = TourSlotTime::where('tour_package_id', $relation->tour_package_id)
                                ->where('date', $tourSlot->date)
                                ->where('time_from', $relation->time_from)
                                ->first();

                            if (isset($slotsData['slotTimes']) && $slotTime) {
                                $isBlackoutDate = isBlackoutDateEnabled($slotTime->date);
                                $customRateDate = isCustomDateRateEnabled($slotTime->date, $slotTime->tour_package_id, $customer->is_affiliate);

                                $tourPackageRate = TourPackageRate::where('affiliate_id', $customer->id)
                                    ->where('scheduling_year', $year)
                                    ->when($customRateDate, function ($query) use ($customRateDate) {
                                        return $query->where('custom_date_id', $customRateDate);
                                    }, function ($query): mixed {
                                        return $query->whereNull('custom_date_id');
                                    })
                                    ->where('status', 'Active')
                                    ->where('deleted_by', 0)
                                    ->where('orderby', 1)
                                    ->first();

                                $rateGroup = TourPackageRate::where('tour_package_id', $slotTime->tour_package_id)
                                    ->when($customer->is_affiliate == 'Yes' && $tourPackageRate && !$isBlackoutDate, function ($query) use ($customer) {
                                        return $query->where('affiliate_id', $customer->id);
                                    }, function ($query): mixed {
                                        return $query->whereNull('affiliate_id');
                                    })
                                    ->when($customRateDate, function ($query) use ($customRateDate) {
                                        return $query->where('custom_date_id', $customRateDate);
                                    }, function ($query): mixed {
                                        return $query->whereNull('custom_date_id');
                                    })
                                    ->where('scheduling_year', $year)
                                    ->where('status', 'Active')
                                    ->where('deleted_by', 0)
                                    ->where('orderby', 1)
                                    ->first();

                                $arrColumn = array_column($slotsData['slotTimes'], 'time_from');
                                $arrSearch = array_search($relation->time_from, $arrColumn);
                                if (isset($arrSearch) && isset($rateGroup)) {
                                    $data = $slotsData['slotTimes'][$arrSearch];
                                    $availableSeats = $data['seats'] - $data['tourists'];
                                    if ($slotTime->bookable_status != 'Only by Phone') {
                                        $slotCloseOnBooking = DB::table('tour_packages')
                                            ->where('id', $relation->tour_package_id)
                                            ->value('slot_close_on_booking');

                                        if (!$slotCloseOnBooking) {
                                            if ($availableSeats < $rateGroup->min_pax_allowed) {
                                                $slotTime->bookable_status = 'Closed';
                                            } else {
                                                $slotTime->bookable_status = 'Open';
                                            }
                                            $slotTime->save();
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } catch (Exception $exception) {
            Log::error($exception);
        }
    }
}
