<?php

namespace App\Repositories\Customer;

use App\Models\Customer;
use App\Models\Admin;
use App\Models\Staff;
use App\Models\TourBooking;
use App\Repositories\Repository;
use App\Exceptions\Core\GeneralCoreException;
use App\Models\TourBookingHistory;
use App\Services\Booking\BookingService;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\DetectsConcurrencyErrors;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class CustomerRepository extends Repository
{
    use DetectsConcurrencyErrors;

    /**
     * @var string
     */
    public $customer = Customer::class;

    public $tourBooking = TourBooking::class;

    public $staff = Staff::class;

    public $admin = Admin::class;

    /**
     * @param  object $request, int $id
     * @return mixed
     *
     * @throws Exception
     */
    public function updateByAdminOrTourOp(object $request, int $id)
    {
        DB::beginTransaction();
        try {
            $userType = userType();

            $booking = DB::table('tour_bookings_master AS tbm')
                ->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
                ->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
                ->where('tbm.id', '=', $id)
                ->first(['tbm.customer_id', 'tbm.name', 'tbm.email', 'tbm.phone_number','tst.date', 'tst.time_from']);
            
            // Affiliate restriction check
            $affiliate = $this->customer::findOrFail($booking->customer_id);
            $isAffiliate = $affiliate ? $affiliate->is_affiliate : 'No';

            if ($userType == 'Affiliate' && $isAffiliate == 'Yes' && $affiliate->duration > 0 && $booking->name != $request->name) {
                $bookingDate = $booking->date;
				$bookingTime = $booking->time_from;
				$bookingSlotTime = Carbon::parse($bookingDate . ' ' . $bookingTime);
				$carbonIns = Carbon::parse($bookingDate . ' ' . $bookingTime);
                if ($affiliate->day_hours == 1) {
					$carbonIns->subDays($affiliate->duration);
				} else if ($affiliate->day_hours == 2) {
					$carbonIns->subHours($affiliate->duration);
				}
                
                if (($carbonIns <= $bookingSlotTime) && (Carbon::now() >= $carbonIns)) {
                    throw new Exception('Cannot update customer info. Affiliate duration restriction applied.');
                }
            }

            $name = '';
            $email = '';
            $phone_number = '';
            if ($booking->name != $request->name) {
                $name = $booking->name;
            }
            if ($booking->email != $request->email) {
                $email = $booking->email;
            }
            if ($booking->phone_number != $request->phone_number) {
                $phone_number = $booking->phone_number;
            }

            $data = [
                'tbm.name' => $request->name,
                'tbm.email' => $request->email,
                'tbm.phone_number' => $request->phone_number,
            ];

            if (isset($email) && !empty($email)) {
                $affiliate = $this->customer::where('email', $email)->where('is_affiliate', 'Yes')->first();
                if ($affiliate && $booking->customer_id != $affiliate->id) {
                    $data['tbm.customer_id'] = $affiliate->id;
                }
            }

            DB::table('tour_bookings_master as tbm')
                ->where('tbm.id', $id)
                ->update($data);
            if (
                $name || $email || $phone_number
            ) {
                $TourBookingHistory = new TourBookingHistory();
                $TourBookingHistory->tour_booking_id = $id;
                $TourBookingHistory->operation = 'Customer Info Update';
                $TourBookingHistory->details = json_encode([
                    'name' => $name, 'email' => $email, 'phone_number' => $phone_number,
                    'new_name' => $request->name, 'new_email' => $request->email, 'new_phone' => $request->phone_number,
                ], JSON_NUMERIC_CHECK);
                $TourBookingHistory->operation_at = Carbon::now();
                $TourBookingHistory->operation_by = Auth::id();
                $TourBookingHistory->operation_by_user_type = $userType;
                $TourBookingHistory->save();
            }
            DB::commit();
            if ($request->api) {
                return ['name' => $request->name, 'email' => $request->email, 'phone_number' => $request->phone_number];
            } else {
                return response()->json([]);
            }
        } catch (Exception $exception) {
            Log::error($exception);
            DB::rollBack();
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Get all the list of customers with filter of affiliate
     *
     * @param object $request
     * @return object
     */
    public function getCustomers(object $request): object
    {
        try {
            return $this->customer::where('is_affiliate', $request->is_affiliate)
                ->where('tour_operator_id', $request->tour_operator_id)
                ->get(['id', 'name', 'email', 'phone_number']);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Get customer liability waiver
     *
     * @param int $bookingId, int $touristNum, int $isAdult
     * @return mixed
     */
    public function getLiabilityWaiver(int $bookingId, int $touristNum, int $isAdult)
    {
        try {
            $vars = [
                'page_title' => 'Liability Waiver Form',
                'touristNum' => $touristNum,
                'isAdult' => $isAdult,
                'signingInfo' => [],
            ];

            $userType = userType();

            $bookingInfo = DB::table('tour_bookings_master AS tbm')
                ->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
                ->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
                ->join('cities AS ct', 'ct.id', '=', 't_o.city_id')
                ->join('states AS st', 'st.id', '=', 'ct.state_id')
                ->join('countries AS cn', 'cn.id', '=', 'st.country_id')
                ->where('tbm.id', $bookingId);

            if ($userType == "Tour Operator")
                $bookingInfo = $bookingInfo->where('t_o.id', auth()->user()->tour_operator_id);

            $bookingInfo = $bookingInfo->first([
                't_o.name AS tour_operator', 't_o.name', 't_o.email', 't_o.phone_number', 't_o.address', 't_o.website', 'tp.waiver_content',
                't_o.postal_code', 'ct.name AS city', 'st.name AS state', 'cn.name AS country',
                'tp.name AS tour_package', 'tbm.liability_waiver_signing', 'tbm.id AS booking_id',
            ]);

            if (!$bookingInfo) {
                throw new Exception('No booking found having the supplied booking id.');
            }

            $vars['bookingInfo'] = $bookingInfo;
            $vars['rateGroups'] = DB::table('tour_bookings_transact AS tst')
                ->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tst.tour_package_rate_id')
                ->where('tst.tour_booking_id', $bookingId)
                ->where('tst.deleted_at', null)
				->orderBy('tpr.orderby', 'asc')
                ->get(['tst.id','tst.tour_package_rate_id','tst.tourists', DB::raw('IFNULL(tst.infants, 0) AS infants'), 'tpr.rate_for', 'tpr.age_from', 'tpr.age_to',
					'tst.processing_charge_percentage', 'tst.additional_tax_percentage',
					'tst.permit_fee', 'tst.additional_charge', 'tst.state_tax', 'tst.rate', 'tst.total']);
            if(request()->is('api/*')){
				if($bookingInfo->liability_waiver_signing != null) {
					$waiverData = json_decode($bookingInfo->liability_waiver_signing, true);
					$modifiedWaiverData = array();

					foreach ($waiverData as $key => $waiver) {
						if (($waiver[1] == "-1") || ($waiver[0]=='' && $waiver[1] == '')) {
							$modifiedWaiverData[$key]['liability_waiver_signing'] = '';
							$modifiedWaiverData[$key]['name'] = '';
							$modifiedWaiverData[$key]['age'] = '';
						} else {
							$modifiedWaiverData[$key]['liability_waiver_signing'] = isset($waiver[2])?$waiver[2]:'';
							$modifiedWaiverData[$key]['name'] = (isset($waiver[0]))?(string)$waiver[0]:'';
							$modifiedWaiverData[$key]['age'] = (isset($waiver[1]) && $waiver[1]>0)?(string)$waiver[1]:'';
						}
					}

					$vars['signingInfo'] = $modifiedWaiverData;
				} else {
					$vars['signingInfo'] = array();
				}
			} else {
				if ($bookingInfo->liability_waiver_signing != '')
					$vars['signingInfo'] = json_decode($bookingInfo->liability_waiver_signing);

				for ($a = $b = 0, $ac = count($vars['rateGroups']); $a < $ac; $a++) {
                    $touristsCount = ($vars['rateGroups'][$a]->infants > 0) ? $vars['rateGroups'][$a]->infants : $vars['rateGroups'][$a]->tourists;
					for ($c = 0; $c < $touristsCount; $c++) {
						if (!isset($vars['signingInfo'][$b]))
							$vars['signingInfo'][$b] = ['', -1, '']; // name, age, date-time

						$b++;
					}
				}
			}
            return $vars;
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Customer liabilty waiver submission form
     *
     * @param object $request
     * @return mixed
     */
    public function updateLiabilityWaiver(object $r, int $bookingId, $deadlockAttempt = 1): mixed
    {
        // Validate before opening the transaction. A ValidationException thrown while
        // beginTransaction() was already called (the previous order here) never reaches
        // the catch block below, so the transaction was left open for the rest of the
        // request/connection -- any later query on this connection (e.g. the check-in
        // calls that follow signWaiver) then runs nested inside it via a SAVEPOINT
        // instead of a fresh transaction, which is what let a downstream deadlock's
        // rollback fail with "SAVEPOINT transN does not exist".
        $r->validate([
            'name' => 'required|array',
            'age' => 'required|array',
            'age.*' => 'integer',
            'agree' => 'required',
        ]);

        DB::beginTransaction();
        try {
            if (count($r->name) != count($r->age)) {
                throw new Exception("Count of Names and Ages aren't matching.");
            }

            $signingInfo = [];
            $dateTime = mysqlDT();

            for ($a = 0, $ac = count($r->name); $a < $ac; $a++) {
                $signingInfo[] = [$r->name[$a], $r->age[$a], $dateTime];
            }

            $cd = DB::table('tour_bookings_master')
                ->where('id', $bookingId)
                ->update(['liability_waiver_signing' => json_encode($signingInfo, JSON_NUMERIC_CHECK)]);

            if ($cd) {

                if (($bookingId)) {
                    for ($a = 0, $ac = count($r->name); $a < $ac; $a++) {
                        $signingInfo[] = [$r->name[$a], $r->age[$a], $dateTime];
                    }

                    $whoSignIn = [$r->age[0], $r->name[0]];
                }

                $userType = userType();
                $createdByWithName = BookingService::createdUserInfo();

                DB::table('tour_booking_history')
                    ->insert([
                        'tour_booking_id' => $bookingId,
                        'operation' => 'Waiver',
                        'details' => json_encode([
                            'status' => $signingInfo,
                            'whoSignIn' => $whoSignIn,
                            'created_by_with_name' => $createdByWithName
                        ]),
                        'operation_at' => Carbon::now(),
                        'operation_by' => auth()->id(),
                        'operation_by_user_type' => $userType,
                        'updated_at' => Carbon::now(),
                        'created_at' => Carbon::now(),
                    ]);
                DB::commit();

                return [
                    'booking_id' => $bookingId,
                    'status' => $signingInfo,
                    'whoSignIn' => $whoSignIn,
                    'checkInDateTime' => $dateTime,
                ];
                
            }
            return response()->json([]);
        } catch (Exception $exception) {
            // Force a full rollback to level 0 rather than the implicit target level --
            // see the note in TourBookingRepository::updateCheckInStatus() for why a
            // plain DB::rollBack() can itself throw here and mask the real error.
            try {
                DB::rollBack(0);
            } catch (Exception $rollbackException) {
                Log::error($rollbackException);
            }

            if ($this->causedByConcurrencyError($exception) && $deadlockAttempt < 3) {
                usleep(100000 * $deadlockAttempt); // 100ms, 200ms
                return $this->updateLiabilityWaiver($r, $bookingId, $deadlockAttempt + 1);
            }

            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
}
