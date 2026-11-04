<?php

namespace App\Repositories\TipCenter;

use App\Constants\Constants;
use App\Exceptions\Core\GeneralCoreException;
use App\Jobs\ProcessBulkAcceptTips;
use App\Jobs\ProcessBulkCompleteTips;
use App\Mail\TourBooking\TipPaymentLink;
use App\Models\BookingTransact;
use App\Models\Customer;
use App\Models\GratuitySetting;
use App\Models\Staff;
use App\Models\TourBooking;
use App\Models\TourOperator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Repositories\Repository;
use App\Models\TourPackageRate;
use App\Models\Transaction;
use App\Services\Booking\EmailService;
use App\Services\Booking\TipService;
use App\Services\TipCenter\TipCenterService;
use App\Services\TourOperator\TourOperatorService;
use App\Utils\StripeUtils;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;
use Stripe\PaymentIntent;
use App\Contracts\Payments\PaymentGatewayInterface;

class GratuityRepository extends Repository
{

    public $gratuitySettings = GratuitySetting::class;

    public $customer = Customer::class;

    public $tourPackageRate = TourPackageRate::class;

    public $tourBooking = TourBooking::class;

    public $bookingTransact = BookingTransact::class;

    public $transaction = Transaction::class;

    /**
     * Tip center dashboard
     * @return array
     */
    public function dashboard(): array
    {
        try {
            $userRole = checkUserRole();
            $guideId = ($userRole == 'tour-guide') ? Auth::id() : null;
            $gratuities = DB::table('gratuities')
                ->when($guideId, function ($query) use ($guideId) {
                    $query->join('booking_tour_guides as btg', 'btg.booking_id', '=', 'gratuities.booking_id')
                        ->where('btg.guide_id', $guideId);
                })
                ->selectRaw('
                    SUM(gratuities.amount) as totalGratuity,
                    SUM(CASE WHEN gratuities.payment_method = "Pay Later" THEN gratuities.amount ELSE 0 END) as totalUnpaidGratuity
                ')
                ->first();
            $totalsByGratuityType = DB::table('gratuities as g')
                ->join('gratuity_settings as gs', 'gs.id', '=', 'g.gratuity_setting_id')
                ->select(
                    'gs.gratuity_type',
                    DB::raw('SUM(g.amount) as total_gratuity')
                )
                ->when($guideId, function ($query) use ($guideId) {
                    $query->join('booking_tour_guides as btg', 'btg.booking_id', '=', 'g.booking_id')
                        ->where('btg.guide_id', $guideId);
                })
                ->whereNotNull('g.amount')
                ->groupBy('gs.gratuity_type')
                ->get();
            $fixedGratuity = $totalsByGratuityType->firstWhere('gratuity_type', 'fixed_amount');
            $fixedAmountGrauityTotal = $fixedGratuity ? $fixedGratuity->total_gratuity : 0;
            $fixedPercentageGratuity = $totalsByGratuityType->firstWhere('gratuity_type', 'fixed_percentage');
            $fixedPercentageGratuityTotal = $fixedPercentageGratuity ? $fixedPercentageGratuity->total_gratuity : 0;
            if (userType() == 'Admin') {
                $totalBookings = DB::table('tour_bookings_master')->count();
            } else {
                $totalBookings = DB::table('booking_tour_guides as btg')
                    ->when($guideId, function ($query) use ($guideId) {
                        $query->where('btg.guide_id', $guideId);
                    })
                    ->distinct()
                    ->count();
            }
            $today = '2025-09-03'; //Carbon::now()->format('Y-m-d');

            $tipAmountsOfToday = DB::table('gratuities as g')
                ->leftJoin('tour_bookings_master AS tbm', 'tbm.id', '=', 'g.booking_id')
                ->leftJoin('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
                ->leftJoin('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
                ->when($guideId, function ($query) use ($guideId) {
                    $query->join('booking_tour_guides as btg', 'btg.booking_id', '=', 'g.booking_id')
                        ->where('btg.guide_id', $guideId);
                })
                ->where(function ($query) use ($today) {
                    $query->where(function ($q) use ($today) {
                        // gratuities linked with booking → use tour date
                        $q->whereNotNull('g.booking_id')
                            ->whereDate('tsd.date', $today);
                    })
                        ->orWhere(function ($q) use ($today) {
                            // gratuities direct (no booking) → use gratuities.created_at
                            $q->whereNull('g.booking_id')
                                ->whereDate('g.created_at', $today);
                        });
                })
                ->groupBy('g.gratuity_type')
                ->select(
                    DB::raw('SUM(g.amount) as totalTipAmount'),
                    DB::raw('CONCAT(UCASE(LEFT(g.gratuity_type, 1)), LCASE(SUBSTRING(g.gratuity_type, 2))) as tipType')
                )
                ->get()
                ->toArray() ?? [];

            // Get current week date range (Monday to Sunday)    
            $fromDate = Carbon::now()->startOfWeek()->toDateString(); // Monday
            $toDate   = Carbon::now()->endOfWeek()->toDateString();   // Sunday

            $tipAmountsOfWeek = TipCenterService::getTipAmountsByDateRange($fromDate, $toDate, $guideId);

            // Get current month date range
            $fromDate = Carbon::now()->startOfMonth()->toDateString();
            $toDate   = Carbon::now()->endOfMonth()->toDateString();
            $tipAmountsOfMonth = TipCenterService::getTipAmountsByDateRange($fromDate, $toDate, $guideId);
            //
            // Get current year date range
            $fromDate = Carbon::now()->startOfYear()->toDateString();
            $toDate   = Carbon::now()->endOfYear()->toDateString();
            $tipAmountsOfYear = TipCenterService::getTipAmountsByDateRange($fromDate, $toDate, $guideId);


            $vars = [
                'page_title' => 'Dashboard',
                'totalGratuity' => $gratuities->totalGratuity ?? 0,
                'totalUnpaidGratuity' => $gratuities->totalUnpaidGratuity ?? 0,
                'fixedAmountGrauityTotal' => $fixedAmountGrauityTotal,
                'fixedPercentageGratuityTotal' => $fixedPercentageGratuityTotal,
                'totalBookings' => $totalBookings,
                'tipAmountsOfToday' => $tipAmountsOfToday,
                'tipAmountsOfWeek' => $tipAmountsOfWeek,
                'tipAmountsOfMonth' => $tipAmountsOfMonth,
                'tipAmountsOfYear' => $tipAmountsOfYear
            ];
            return $vars;
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Gratuity Settings List
     * @param $dt
     * 
     * 
     * @return mixed
     */
    public function settingsList($dt): mixed
    {
        try {
            $routePrefix =  routePrefix();
            $tipCenterService = new TipCenterService();
            $settingsList = $tipCenterService->fetchGratuitySettings($dt, $routePrefix);
            return $settingsList;
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Edit Gratuity Setting
     * @param $id
     * 
     * 
     * @return mixed
     */
    public function editGratuitySetting($id): mixed
    {
        try {
            $gratuitySetting = DB::table('gratuity_settings')
                ->where('id', $id)
                ->first();
            $tourPackages = TipCenterService::fetchTourPackages();
            $assignedPackages = DB::table('gratuity_package_assignments')
                ->where('gratuity_setting_id', $id)
                ->pluck('package_id')
                ->toArray();
            return [
                'page_title' => 'Edit Gratuity Setting',
                'gratuitySetting' => $gratuitySetting,
                'tourPackages' => $tourPackages,
                'assignedPackages' => $assignedPackages
            ];
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Save gratuity setting
     * @return mixed
     */
    public function saveGratuitySetting($request): mixed
    {
        try {
            $validated = $this->validateRequest($request);
            DB::beginTransaction();

            try {
                $gratuitySettingId = ($request->has('gratuitySettingId')) ? $request->gratuitySettingId : null;
                //Validate one gratuity setting to one package 
                if ($gratuitySettingId) {
                    // First, get packages already assigned to this gratuity setting (self)
                    $alreadyAssignedPackages = DB::table('gratuity_package_assignments')
                        ->where('gratuity_setting_id', $gratuitySettingId)
                        ->pluck('package_id')
                        ->toArray();

                    // Take only *new* packages (not already linked to this setting)
                    $newPackages = array_diff($request->input('tourPackages', []), $alreadyAssignedPackages);

                    if (!empty($newPackages)) {
                        $packageAssignmentExist = DB::table('gratuity_package_assignments')
                            ->where('gratuity_setting_id', '!=', $gratuitySettingId)
                            ->whereIn('package_id', $newPackages)
                            ->exists();

                        if ($packageAssignmentExist) {
                            DB::rollBack();
                            $errors[] = 'You can assign only one gratuity setting to a package.';
                            return sendJsonErrMsg($errors, 500);
                        }
                    }
                } else {
                    $packageAssignmentExist = DB::table('gratuity_package_assignments')
                        ->whereIn('package_id', $request->input('tourPackages', []))
                        ->exists();
                    if ($packageAssignmentExist) {
                        DB::rollBack();
                        $errors[] = 'Package is already assigned to a gratuity setting.';
                        return sendJsonErrMsg($errors, 500);
                    }
                }
                if ($gratuitySettingId) {
                    // Update existing gratuity setting
                    TipCenterService::updateGratuitySetting($gratuitySettingId, $validated);
                    // Handle tour package assignments
                    $tourPackages = $request->input('tourPackages', []);
                    if (!empty($tourPackages)) {
                        // Delete existing assignments that are not in the new list
                        DB::table('gratuity_package_assignments')
                            ->where('gratuity_setting_id', $gratuitySettingId)
                            ->whereNotIn('package_id', $tourPackages)
                            ->delete();
                        // Insert new assignments
                        foreach ($tourPackages as $packageId) {
                            $packageAssignmentExist = DB::table('gratuity_package_assignments')
                                ->where('gratuity_setting_id', $gratuitySettingId)
                                ->where('package_id', $packageId)
                                ->exists();
                            if (in_array($packageId, $request->input('assignedPackages', []))) {
                                continue; // Skip if already assigned
                            }
                            // If package assignment does not exist, insert it
                            if (!$packageAssignmentExist) {
                                DB::table('gratuity_package_assignments')->insert([
                                    'gratuity_setting_id' => $gratuitySettingId,
                                    'package_id' => $packageId,
                                    'created_at' => Carbon::now(),
                                    'updated_at' => Carbon::now(),
                                ]);
                            }
                        }
                    } else {
                        // If no packages are assigned, delete all assignments for this gratuity setting
                        DB::table('gratuity_package_assignments')
                            ->where('gratuity_setting_id', $gratuitySettingId)
                            ->delete();
                    }
                } else {
                    // Create new gratuity setting
                    $gratuitySettingId = TipCenterService::insertGratuitySetting($validated);
                    $tourPackages = $request->input('tourPackages', []);
                    if (!empty($tourPackages)) {
                        foreach ($tourPackages as $packageId) {
                            DB::table('gratuity_package_assignments')->insert([
                                'gratuity_setting_id' => $gratuitySettingId,
                                'package_id' => $packageId,
                                'created_at' => Carbon::now(),
                                'updated_at' => Carbon::now(),
                            ]);
                        }
                    }
                }
                DB::commit();

                return [
                    'success' => true,
                    'message' => 'Gratuity setting saved successfully',
                    'gratuitySettingId' => $gratuitySettingId
                ];
            } catch (Exception $e) {
                DB::rollBack();
                Log::error('Error saving gratuity setting: ' . $e->getMessage(), ['exception' => $e]);
                return response()->json(['error' => 'Failed to save gratuity setting'], 500);
            }
        } catch (Exception $exception) {
            Log::error('Error in saveGratuitySetting: ' . $exception->getMessage(), ['exception' => $exception]);
            return response()->json(['error' => 'An error occurred while saving gratuity setting'], 500);
        }
    }
    /**
     * List of guides of tip center
     * 
     * 
     * @return mixed
     */
    public function guides(): mixed
    {
        try {
            $guides = TipCenterService::fetchGuides();
            $tourPackages = TipCenterService::fetchTourPackages();
            $date =  date('Y-m-d');
            return [
                'page_title' => 'Guide Management',
                'guides' => $guides,
                'tourPackages' => $tourPackages,
                'date' => $date
            ];
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
    /**
     * Guide bookings page
     * 
     * 
     * @return mixed
     */
    public function bookings(): mixed
    {
        try {
            $guides = TipCenterService::fetchGuides();
            $tourPackages = TipCenterService::fetchTourPackages();
            $date =  date('Y-m-d');
            $userRole = checkUserRole();
            return [
                'page_title' => 'Bookings',
                'guides' => $guides,
                'tourPackages' => $tourPackages,
                'date' => $date,
                'userRole' => $userRole
            ];
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
    /* Search guide
    * 
    * 
    * @return mixed
    */
    public function searchGuide($request): mixed
    {
        try {
            $searchTerm = $request->input('searchTerm');
            $guides = TipCenterService::fetchGuides($searchTerm);
            return [
                'guides' => $guides
            ];
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
    /**
     * Current bookings of guides of tip center
     * @param $dt
     * 
     * 
     * @return mixed
     */
    public function guideBookings($request): mixed
    {
        try {
            $data = TipCenterService::getGuideBookings($request);
            $totalAssignedBookingsCount = $data['summary']['totalAssignedBookingsCount'];
            $totalUnAssignedBookingsCount = $data['summary']['totalUnAssignedBookingsCount'];
            $totalBookingsCount = $data['summary']['totalBookingsCount'];
            $slotsBookings = $data['slotsBookings'];

            $slots = [];
            foreach ($slotsBookings as $slot) {
                $slots[] = [
                    'tour_slot_id' => $slot['tour_slot_id'],
                    'time_from' => $slot['time_from'],
                    'time_to' => $slot['time_to']
                ];
            }
            return [
                'slotsBookings' => $slotsBookings,
                'totalBookingsCount' => $totalBookingsCount,
                'totalAssignedBookingsCount' => $totalAssignedBookingsCount,
                'totalUnAssignedBookingsCount' => $totalUnAssignedBookingsCount,
                'slots' => $slots,
            ];
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Profile of guide
     * @return mixed
     */
    public function profile(): mixed
    {
        try {
            $guideId = Auth::id();
            $guide = DB::table('tour_operator_staff')
                ->where('id', $guideId)
                ->first();
            $tourOperator = TourOperator::first();
            return [
                'page_title' => 'My Profile',
                'guide' => $guide,
                'tourOperator' => $tourOperator,
                'guideId' => Crypt::encrypt(Auth::id())
            ];
        } catch (Exception $exception) {
            throw new GeneralCoreException($exception);
        }
    }
    /**
     * Update guide general information
     * @return mixed
     */
    public function updateProfile($request): mixed
    {
        try {
            DB::beginTransaction();

            $guideId = Auth::id();
            $tourOperator = TourOperator::first();
            $photo = $request->file('profile_picture');
            $fileNmExt = fileNameExt($photo->getClientOriginalName());
            $ext = strtolower($fileNmExt->ext);
            if (in_array($ext, ['jpg', 'jpeg', 'jpe', 'png', 'webp', 'gif'])) {
                $fileName = $fileNmExt->name . ".$ext";
                $imageNormal = Image::make($photo)->stream();
                $path = "images/tour-operator/" . $tourOperator->tenant_id . "/users/";
                Storage::disk('s3')->put($path . $fileName, $imageNormal->__toString());

                DB::table('tour_operator_staff')
                    ->where('id', $guideId)
                    ->update(
                        [
                            'profile_photo_path' => $fileName,
                            'updated_at' => now()
                        ]
                    );
            }
            DB::commit();
            return redirect()
                ->route(routePrefix() . '.tip-center.profile')
                ->with('success', 'Profile updated successfully!');
        } catch (Exception $exception) {
            DB::rollBack();
            throw new GeneralCoreException($exception);
        }
    }
    /**
     * Profile of guide
     * @return mixed
     */
    public function createTip(): mixed
    {
        try {
            $guideId = Auth::id();
            $guide = DB::table('tour_operator_staff')
                ->where('id', $guideId)
                ->first();
            $tourOperator = TourOperator::first();
            return [
                'page_title' => 'Create Tip',
                'guide' => $guide,
                'tourOperator' => $tourOperator,
                'guideId' => Crypt::encrypt(Auth::id())
            ];
        } catch (Exception $exception) {
            throw new GeneralCoreException($exception);
        }
    }
    /**
     * Make tip payment from guide dashboard
     * @return mixed
     */
    public function makeTipPayment($request): mixed
    {
        DB::beginTransaction();
        try {
            $guideId = Auth::id();
            $guide = DB::table('tour_operator_staff')
                ->where('id', $guideId)
                ->first();
            $tourOperator = TourOperator::first();
            $paymentIntentId = $request->input('payment_intent_id');
            $retrieveOptions = ['expand' => ['charges.data.transfer']];
            $paymentIntent = app(PaymentGatewayInterface::class)->retrievePayment($paymentIntentId, $retrieveOptions);

            $gratuityCount = DB::table('gratuities')
                ->where('transaction_number', $paymentIntentId)
                ->count();
            if ($gratuityCount == 0) {
                $guideId = $paymentIntent->metadata['guide_id'];
                $tipAmount = $paymentIntent->metadata['tip_amount'];
                $fees = $paymentIntent->metadata['fee'];

                DB::table('gratuities')->insertGetId([
                    'booking_id' => NULL,
                    'customer_id' => NULL,
                    'gratuity_setting_id' => NULL,
                    'guide_id' => $guideId,
                    'amount' => $tipAmount,
                    'fees' => $fees,
                    'transaction_number' => $paymentIntentId,
                    'application_fee_id' => $paymentIntent->charges->data[0]->application_fee ?? null,
                    'gratuity_type' => 'direct',
                    'payment_method' => 'Card',
                    'status' => 'paid',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    'created_by' => $guideId,
                    'created_by_user_type' => userType(),
                ]);
                DB::commit();
                return [
                    'status' => "Success"
                ];
            } else {
                throw new Exception("This payment has already been processed.");
            }
        } catch (Exception $exception) {
            DB::rollBack();
            throw new GeneralCoreException($exception);
        }
    }
    /**
     * Update guide general information
     * @return mixed
     */
    public function updatGuideGeneralInfo($request): mixed
    {
        try {
            $validated = $request->validate([
                'capacity' => 'required|integer'
            ]);
            DB::beginTransaction();
            try {
                $tourGuideId = ($request->has('tourGuideId')) ? $request->tourGuideId : null;
                // Update existing tour guide information
                DB::table('tour_operator_staff')
                    ->where('id', $tourGuideId)
                    ->update([
                        'capacity' => $validated['capacity'],
                        'updated_at' => now(),
                    ]);
                DB::commit();

                return [
                    'success' => true,
                    'message' => 'General information updated successfully',
                    'tourGuideId' => $tourGuideId
                ];
            } catch (Exception $e) {
                DB::rollBack();
                Log::error('Error saving general information: ' . $e->getMessage(), ['exception' => $e]);
                return response()->json(['error' => 'Failed to save general information'], 500);
            }
        } catch (Exception $exception) {
            Log::error('Error in updatGuideGeneralInfo: ' . $exception->getMessage(), ['exception' => $exception]);
            return response()->json(['error' => 'An error occurred while saving general information'], 500);
        }
    }

    /**
     * Update guide stripe account information
     * @return mixed
     */
    public function updatGuideStripeAccountInfo($request): mixed
    {
        try {
            $validated = $request->validate([
                'stripeAccountId' => 'required'
            ]);
            DB::beginTransaction();
            try {
                $tourGuideId = ($request->has('tourGuideId')) ? $request->tourGuideId : null;
                $primaryMethod = ($request->has('primaryMethod') && $request->primaryMethod) ? 'Connected Account' : 'Bank Account';
                // Update existing tour guide information
                DB::table('tour_operator_staff')
                    ->where('id', $tourGuideId)
                    ->update([
                        'stripe_account_id' => $validated['stripeAccountId'],
                        'payout_primary_method' => $primaryMethod,
                        'updated_at' => now(),
                    ]);
                DB::commit();

                return [
                    'success' => true,
                    'message' => 'Stripe Account information updated successfully',
                    'tourGuideId' => $tourGuideId
                ];
            } catch (Exception $e) {
                DB::rollBack();
                Log::error('Error saving stripe account information: ' . $e->getMessage(), ['exception' => $e]);
                return response()->json(['error' => 'Failed to save stripe account information'], 500);
            }
        } catch (Exception $exception) {
            Log::error('Error in updatGuideStripeAccountInfo: ' . $exception->getMessage(), ['exception' => $exception]);
            return response()->json(['error' => 'An error occurred while saving stripe account information'], 500);
        }
    }
    /**
     * Update guide bank account information
     * @return mixed
     */
    public function updatGuideBankAccountInfo($request): mixed
    {
        try {
            $validated = $request->validate([
                'bankAccountNumber' => 'required',
                'bankRoutingNumber' => 'required',
                'bankAccountType' => 'required|in:Savings,Current',
                'accountHolderName' => 'required|string',
            ]);
            DB::beginTransaction();
            try {
                $tourGuideId = ($request->has('tourGuideId')) ? $request->tourGuideId : null;
                $tourGuide = DB::table('tour_operator_staff')
                    ->where('id', $tourGuideId)
                    ->first();
                $primaryMethod = ($request->has('primaryMethod') && $request->primaryMethod) ? 'Bank Account' : 'Connected Account';
                if ($primaryMethod == 'Bank Account') {
                    $accountId = ($tourGuide->stripe_account_id) ? $tourGuide->stripe_account_id : '';
                    // Create a new account if primary method is Bank Account
                    if ($tourGuide->stripe_account_id == '') {
                        $accountData = [
                            'type'          => 'express',
                            'country'       => 'US',
                            'email'         => $tourGuide->email,
                            'business_type' => 'individual',
                            'capabilities'  => [
                                'transfers' => ['requested' => true],
                            ],
                        ];
                        $account = app(PaymentGatewayInterface::class)->createAccount($accountData);

                        // Update the tour guide with the new stripe account ID
                        DB::table('tour_operator_staff')
                            ->where('id', $tourGuideId)
                            ->update(['stripe_account_id' => $account->id]);
                        $accountId = $account->id;
                    }
                    if ($tourGuide->bank_account_number != $validated['bankAccountNumber']) {

                        $tokenParams = [
                            'bank_account' => [
                                'country'              => 'US',
                                'currency'             => 'usd',
                                'account_holder_name'  => $validated['accountHolderName'],
                                'account_holder_type'  => 'individual',
                                'routing_number'       => $validated['bankRoutingNumber'],
                                'account_number'       => $validated['bankAccountNumber'],
                            ],
                        ];
                        $bankToken = app(PaymentGatewayInterface::class)->createToken($tokenParams);

                        $externalAccountParams = ['external_account' => $bankToken->id];
                        app(PaymentGatewayInterface::class)->createAccountExternalAccount($accountId, $externalAccountParams);

                        // Update existing tour guide information
                        DB::table('tour_operator_staff')
                            ->where('id', $tourGuideId)
                            ->update([
                                'bank_account_number' => $validated['bankAccountNumber'],
                                'bank_routing_number' => $validated['bankRoutingNumber'],
                                'bank_account_type' => $validated['bankAccountType'],
                                'account_holder_name' => $validated['accountHolderName'],
                                'payout_primary_method' => $primaryMethod,
                                'updated_at' => now(),
                            ]);
                        DB::commit();
                    }
                }

                return [
                    'success' => true,
                    'message' => 'Bank Account information updated successfully',
                    'tourGuideId' => $tourGuideId
                ];
            } catch (Exception $e) {
                DB::rollBack();
                Log::error('Error saving bank account information: ' . $e->getMessage(), ['exception' => $e]);
                return response()->json(['error' => 'Failed to save bank account information'], 500);
            }
        } catch (Exception $exception) {
            Log::error('Error in updatGuideBankAccountInfo: ' . $exception->getMessage(), ['exception' => $exception]);
            return response()->json(['error' => 'An error occurred while saving bank account information'], 500);
        }
    }

    /**
     * Tip distributions of tip center
     * @return array
     */
    public function distributions(): array
    {
        try {
            $guides = TipCenterService::fetchGuides();
            $tourPackages = TipCenterService::fetchTourPackages();
            $tourOperator = TourOperatorService::getTourOperator();
            $date =  Carbon::now($tourOperator->timezone)->format('Y-m-d');
            $startOfYear = Carbon::now()->startOfYear()->toDateString();
            $endOfYear   = Carbon::now()->endOfYear()->toDateString();
            $distributionSchedules = DB::table('gratuity_payroll_schedules as gps')->where('gps.start_date', '<=', $endOfYear)
                ->where('gps.end_date', '>=', $startOfYear)
                ->select(['gps.id', 'gps.start_date', 'gps.end_date', 'gps.payroll_date', 'gps.status'])
                ->get()
                ->toArray();
            $userRole = checkUserRole();
            $vars = [
                'page_title' => 'Distribution Management',
                'guides' => $guides,
                'tourPackages' => $tourPackages,
                'date' => $date,
                'distributionSchedules' => $distributionSchedules,
                'userRole' => $userRole
            ];
            return $vars;
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
    /**
     * Distributions list of tip center
     * @param $dt
     * 
     * 
     * @return mixed
     */
    public function distributionsList($request): mixed
    {
        try {
            $distributions = TipCenterService::fetchDistributions($request);
            $totalBookings = 0;
            $totalPendingBookings = 0;
            $totalReadyBookingsCount = 0;
            $totalCompletedBookingsCount = 0;
            $totalPaidDistributionsCount = 0;
            $totalGratuity = 0;
            $totalTransferredGratuity = 0;
            $slots = [];
            foreach ($distributions as $slot) {
                $slots[] = [
                    'tour_slot_id' => $slot['tour_slot_id'],
                    'time_from' => $slot['time_from'],
                    'time_to' => $slot['time_to']
                ];
                //$totalBookings += $slot['bookings']->count();

                foreach ($slot['bookings'] as $booking) {
                    $totalBookings++;
                    $totalGratuity += $booking['total_gratuity'] ?? 0;

                    if ($booking['distribution_status'] == '') {
                        $totalPendingBookings++;
                    } else if ($booking['distribution_status'] == 'Ready') {
                        $totalReadyBookingsCount++;
                    } else if ($booking['distribution_status'] == 'Schedule' || $booking['distribution_status'] == 'Paid') {
                        $totalCompletedBookingsCount++;
                        if ($booking['distribution_status'] == 'Paid') {
                            $totalPaidDistributionsCount++;
                        }
                    }
                }
            }
            return [
                'distributions' => $distributions,
                'slots' => $slots,
                'totalBookingsCount'            => $totalBookings,
                'totalPendingBookingsCount'    => $totalPendingBookings,
                'totalReadyBookingsCount'  => $totalReadyBookingsCount,
                'totalCompletedBookingsCount'  => $totalCompletedBookingsCount,
                'totalPaidDistributionsCount'  => $totalPaidDistributionsCount,
                'totalGratuity' => $totalGratuity
            ];
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
    /**
     * Tip distribution info
     * @return array
     */
    public function distributionInfo($id): array
    {
        try {
            $tourBooking = DB::table('tour_bookings_master as tbm')
                ->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
                ->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
                ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
                ->select('tbm.id as booking_id', 'tbm.tourists', 'tp.name as package_name', 'tsd.date')
                ->where('tbm.id', $id)
                ->select(
                    'tbm.id as booking_id',
                    'tbm.tourists',
                    'tbm.name as customer_name',
                    'tp.name as package_name',
                    'tsd.date'
                )
                ->first();

            $selectFields = [
                'btg.guide_id',
                'btg.id as booking_guide_id',
                DB::raw("CONCAT(tos.first_name, ' ', tos.last_name) as guide_name"),
                DB::raw("SUM(gd.amount) as total_distributed"),
            ];

            $query = DB::table('booking_tour_guides as btg')
                ->leftJoin('tour_operator_staff as tos', 'tos.id', '=', 'btg.guide_id')
                ->leftJoin('gratuities as g', 'g.booking_id', '=', 'btg.booking_id')
                ->leftJoin('gratuity_distributions as gd', function ($join) {
                    $join->on('gd.gratuity_id', '=', 'g.id')
                        ->on('gd.guide_id', '=', 'btg.guide_id');
                })
                ->whereNotNull('g.amount')
                ->where('btg.booking_id', $id)
                ->groupBy('btg.guide_id', 'tos.first_name', 'tos.last_name');

            $query->select($selectFields);

            $guidesInfo = $query->get();
            // Get total gratuity amount for the booking
            $gratuity = DB::table('gratuities')
                ->where('booking_id', $id)
                ->select('amount', 'id')
                ->first();
            $totalGratuity = $gratuity->amount ?? 0;
            $guideCount = $guidesInfo->count();
            $expectedShare = $guideCount > 0 ? round($totalGratuity / $guideCount, 2) : 0;
            $totalDistributed = $guidesInfo->sum('total_distributed');
            // Add expected_share and has_distribution flag
            $guidesWithShare = $guidesInfo->map(function ($guide) use ($expectedShare) {
                return [
                    'booking_guide_id' => $guide->booking_guide_id,
                    'guide_id' => $guide->guide_id,
                    'guide_name' => $guide->guide_name,
                    'total_distributed' => (float) $guide->total_distributed,
                    'expected_share' => $expectedShare,
                    'has_distribution' => round($guide->total_distributed, 2) == $expectedShare,
                ];
            });

            return [
                'tourBooking' => $tourBooking,
                'guidesInfo' => $guidesWithShare,
                'gratuity' => $gratuity,
                'totalDistributed' => $totalDistributed
            ];
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
    /**
     * Save distribution amounts for booking
     * @return mixed
     */
    public function saveDistributionAmounts($request): mixed
    {
        DB::beginTransaction();
        try {
            $bookingId = $request->input('bookingId');
            $percent = $request->has('percent') ? $request->input('percent') : 0;
            $guideId = $request->input('guideId');
            $amount = $request->has('amount') ? $request->input('amount') : 0;

            $gratuity = DB::table('gratuities as g')
                ->join('gratuity_settings as gs', 'gs.id', '=', 'g.gratuity_setting_id')
                ->where('g.booking_id', $bookingId)
                ->first(['g.id', 'g.gratuity_setting_id', 'g.amount', 'g.gratuity_type', 'g.amount', 'g.fees', 'gs.distribution_type']);
            $tipAmount = ($gratuity) ? $gratuity->amount : 0;

            if ($amount > 0) {
                $tipAmountForFirstGuide = $amount;
                $percent = roundout($amount / $tipAmount * 100, 2);
            } else {
                $tipAmountForFirstGuide = roundout($tipAmount / 100 * $percent, 2);
            }

            if ($tipAmountForFirstGuide > $tipAmount) {
                DB::rollBack();
                return sendJsonErrMsg('Distribution amount cannot be greater than total gratuity amount.');
            }
            $remainingPercentage = 100 - $percent;
            DB::table('booking_tour_guides')
                ->where('booking_id', $bookingId)
                ->where('guide_id', $guideId)
                ->update([
                    'distribution_percentage' => $percent,
                    'distribution_amount' => $tipAmountForFirstGuide,
                    'updated_at' => Carbon::now()
                ]);

            $otherTourGuides = DB::table('booking_tour_guides')
                ->where('booking_id', $bookingId)
                ->where('guide_id', '!=', $guideId)
                ->get();
            $otherGuidesCount = $otherTourGuides->count();
            if ($otherGuidesCount > 0) {
                $amountForOtherGuides = $tipAmount - $tipAmountForFirstGuide;
                $amountForEachGuide = roundout($amountForOtherGuides / $otherGuidesCount, 2);
                foreach ($otherTourGuides as $otherGuide) {
                    DB::table('booking_tour_guides')
                        ->where('id', $otherGuide->id)
                        ->update([
                            'distribution_percentage' => $remainingPercentage,
                            'distribution_amount' => $amountForEachGuide,
                            'updated_at' => Carbon::now()
                        ]);
                }
            }
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Distribution amounts updated successfully.']);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error processing transfer of distribution: ' . $e->getMessage(), ['exception' => $e]);
            return sendJsonErrMsg('Failed to update distribution amount.');
        }
    }
    /**
     * Export distributions in excel 
     * @return mixed
     */
    public function distributionsExport($fromDate = '', $toDate = '', $payrollScheduleId = ''): mixed
    {
        try {
            $request = new Request();
            $request->merge([
                'fromDate' => $fromDate,
                'toDate' => $toDate,
                'payrollScheduleId' => $payrollScheduleId,
                'export' => true
            ]);
            $distributions = TipCenterService::fetchDistributions($request);
            if ($distributions->isEmpty()) {
                return response()->json(['error' => 'No distributions found for the selected criteria.'], 404);
            }
            $fileName = 'tip_distributions_report_' . date("m-d-Y", strtotime($fromDate)) . '_' . date("m-d-Y", strtotime($toDate)) . '.csv';

            $headers = csvHeaders($fileName);
            $columnsHeader = csvColumns('tip_distributions_report_columns_headers');
            $columns = csvColumns('tip_distributions_report_columns');


            $callback = function () use ($request, $distributions, $columnsHeader, $columns, $fromDate, $toDate) {

                $file = fopen('php://output', 'w');
                $blankLine = '';
                $dateRange = date("m-d-Y", strtotime($fromDate)) . ' - ' . date("m-d-Y", strtotime($toDate));
                $createdDate = date("m-d-Y");
                foreach ($distributions as $distribution) {

                    if (isset($distribution['guide_id'])) {

                        fputcsv($file, $columnsHeader);

                        fputcsv($file, array(
                            $distribution['guide_name'],
                            $distribution['total_payout'],
                        ));

                        fputcsv($file, $columns);

                        $bookings = $distribution['bookings'] ?? [];
                        foreach ($bookings as $booking) {
                            $date = ($booking['booking_id'] != '' && isset($booking['date']) && $booking['time_from'] != '') ? date("m-d-Y", strtotime($booking['date'])) . " @ " . date("g:i A", strtotime($booking['time_from'])) : date("m-d-Y", strtotime($booking['date']));
                            $row = array(
                                $booking['booking_id'],
                                $date,
                                $booking['package_name'],
                                $booking['customer_name'],
                                $booking['distribution_amount'],
                                $booking['distribution_status']
                            );

                            fputcsv($file, $row);
                        }

                        fputcsv($file, array(
                            $blankLine,
                            $blankLine,
                            $blankLine,
                            $blankLine,
                            $blankLine,
                            $blankLine
                        ));
                    }
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (Exception $e) {
            throw new GeneralCoreException($e);
        }
    }
    /**
     * Transfer gratuity to guide
     * @return mixed
     */
    public function processAcceptTips($request): mixed
    {
        DB::beginTransaction();
        try {
            $bookingId = ($request->has('bookingId')) ? $request->bookingId : null;
            $type = ($request->has('type')) ? $request->type : null;
            if ($type == 'direct') {
                $gratuity = DB::table('gratuities as g')
                    ->where('g.id', $bookingId)
                    ->first(['g.id', 'g.guide_id', 'g.amount']);
                TipCenterService::insertDirectTipDistribution($gratuity->id, $gratuity->guide_id, $gratuity->amount);
            } else {
                $assignedGuides = TipService::getBookingAssignedGuides($bookingId);
                if (empty($assignedGuides)) {
                    DB::rollBack();
                    return sendJsonErrMsg('No guides assigned for this booking.');
                }
                $gratuity = DB::table('gratuities as g')
                    ->where('g.booking_id', $bookingId)
                    ->first(['g.id', 'g.gratuity_setting_id', 'g.amount', 'g.gratuity_type', 'g.amount', 'g.fees']);

                foreach ($assignedGuides as $bookingGuide) {
                    TipCenterService::insertDistribution($bookingId, $gratuity->id, $bookingGuide);
                }
            }
            DB::commit();

            return response()->json(['success' => true, 'message' => 'Tip accepted successfully.']);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error processing transfer of tips: ' . $e->getMessage(), ['exception' => $e]);
            return sendJsonErrMsg('Failed to process transfer of tips.');
        }
    }

    /**
     * Transfer bulk gratuity of bookings to guides
     * @return mixed
     */
    public function processBulkAcceptTips($request): mixed
    {
        DB::beginTransaction();
        try {

            $bookings = $request->input('bookings');
            $directTips = $request->input('directTips');
            $createdBy = Auth::id();
            $createdByUserType = userType();
            //dispatch(new ProcessBulkAcceptTips($data));
            $distributions = [];
            $bookingDistributions = [];
            $directTipsDistributions = [];

            if (!empty($bookings)) {
                foreach ($bookings as $booking) {
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
                                $bookingDistributions[] = [
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
            }
            if (!empty($directTips)) {
                foreach ($directTips as $directTip) {
                    $gratuity = DB::table('gratuities as g')
                        ->where('g.id', $directTip)
                        ->first(['g.id', 'g.guide_id', 'g.amount']);
                    if (!$gratuity) {
                        continue; // skip if no gratuity found for booking
                    }
                    $gratuityId = $gratuity->id;
                    $guideId = $gratuity->guide_id;
                    $amount = $gratuity->amount;
                    $exists = DB::table('gratuity_distributions')
                        ->where('gratuity_id', $gratuityId)
                        ->where('guide_id', $guideId)
                        ->exists();
                    if (!$exists) {
                        $directTipsDistributions[] = [
                            'booking_id' => null,
                            'guide_id' => $guideId,
                            'gratuity_id' => $gratuityId,
                            'amount' => $amount,
                            'status' => 'Ready',
                            'created_at' => now(),
                            'created_by' => $createdBy,
                            'created_by_user_type' => $createdByUserType
                        ];
                    }
                }
            }
            $distributions = array_merge($bookingDistributions, $directTipsDistributions);
            if (!empty($distributions)) {
                DB::table('gratuity_distributions')->insert($distributions);
            }
            DB::commit();

            return response()->json(['success' => true, 'message' => 'Tips has been accepted successfully.']);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error processing transfer of tips: ' . $e->getMessage(), ['exception' => $e]);
            return sendJsonErrMsg('Failed to process transfer of tips.');
        }
    }

    /* Transfer bulk gratuity of bookings to guides
    * @return mixed
    */
    public function processBulkCompleteTips($request): mixed
    {
        DB::beginTransaction();
        try {
            $bookings = $request->input('bookings');
            $directTips = $request->input('directTips');
            $payRollDate = $request->input('payrollDate');
            $startDate = $request->input('startDate');
            $endDate = $request->input('endDate');
            $createdBy = Auth::id();
            $createdByUserType = userType();
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
            if (!empty($bookings)) {
                $distributions = [];
                foreach ($bookings as $booking) {
                    $bookingId = $booking;
                    $distributions = TipCenterService::getAcceptedDistributions($bookingId);

                    if (!empty($distributions)) {
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
            if (!empty($directTips)) {
                foreach ($directTips as $directTip) {
                    DB::table('gratuity_distributions')
                        ->where('gratuity_id', $directTip)
                        ->where('status', 'Ready')
                        ->update([
                            'status' => 'Schedule',
                            'payroll_schedule_id' => $payrollScheduleId,
                            'updated_at' => Carbon::now()
                        ]);
                }
            }
            //dispatch(new ProcessBulkCompleteTips($data));
            DB::commit();

            return response()->json(['success' => true, 'message' => 'Transfer of tips processed successfully.']);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error processing transfer of tips: ' . $e->getMessage(), ['exception' => $e]);
            return sendJsonErrMsg('Failed to process transfer of tips.');
        }
    }


    /**
     * Send tip payment link to customer from the app
     * @return mixed
     */
    public function sendTipPaymentLink($request, $api = ''): mixed
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email'
            ]);

            $tourOperator = TourOperator::first();

            $arEmailData = [
                'tourOperatorId' => $tourOperator->id,
                'tourOperator' => $tourOperator->name,
                'tourOpPhone' => $tourOperator->phone_number,
                'tourOpEmail' => $tourOperator->email,
                'tourOpWebsite' => $tourOperator->website,
                'custEmail' =>  $validated['email'],
                'emailType' => config('constants.email_types.tip_payment_link'),
                'operationBy' => Auth::id(),
                'operationByUserType' => userType(),
                'tenantId' => $tourOperator->tenant_id,
                'paymentLinkUrlInfo' => route('customer.pay-tip', ['guideId' => Crypt::encrypt(Auth::id())])
            ];

            if (!empty($tourOperator->logo)) {
                $tourOperatorLogo = tourOperatorLogo($tourOperator->tenant_id, $tourOperator->logo);
                $arEmailData['tourOpLogo'] = $tourOperatorLogo;
            }

            if (in_array(Auth::user()->email, config('constants.prevent_sending_emails')) == false) {
                Mail::send(new TipPaymentLink($arEmailData, 'tip_payment_link'));
            }

            if ($api == 1) {
                return [
                    'status' => "Success",
                    'message' => 'Tip payment link sent successfully.'
                ];
            } else {
                return redirect()
                    ->route(routePrefix() . '.tip-center.create-tip')
                    ->with('success-payment-link', 'Tip payment link sent successfully!');
            }
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
    /**
     * Create stripe payment intent for tip payment (Tap to pay)
     * @return mixed
     */
    public function stripeCreateTipPaymentIntent($request): mixed
    {
        try {
            $validated = $request->validate([
                'tip_amount' => 'required|numeric|min:1',
            ]);

            $tourOperator = TourOperator::first();
            $tourOperatorName = $tourOperator->name;
            $tipAmount = $validated['tip_amount'];
            $guideId = Auth::id();
            $guideInfo = DB::table('tour_operator_staff')
                ->where('id', $guideId)
                ->first();
            $feesAmount = round($tipAmount * $tourOperator->service_commission_percentage / 100, 2);
            $totalAmount = round($tipAmount + $feesAmount, 2);
            $stripeAttributes = StripeUtils::getStripeAttributesForAppTapToPay(
                $totalAmount,
                $feesAmount,
                $tourOperatorName,
                $tourOperator->stripe_destination_id
            );

            $stripeMetadata = StripeUtils::getStripeMetadataForAppTapToPay(
                $guideInfo,
                $totalAmount,
                $feesAmount,
                $tipAmount,
            );

            $paymentIntentParams = $stripeAttributes + $stripeMetadata;
            $paymentIntent = app(PaymentGatewayInterface::class)->createPayment($paymentIntentParams);

            return [
                'clientSecret' => $paymentIntent->client_secret,
                'paymentIntentId' => $paymentIntent->id,
            ];
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
    /**
     * After tip payment process for tap to pay process send mail to customer
     * @return mixed
     */
    public function sendTapToPayPaymentMail($request): mixed
    {
        try {
            $validated = $request->validate([
                'payment_intent_id' => 'required',
                'customer_email' => 'required|email'
            ]);

            $paymentIntentId = $validated['payment_intent_id'];
            // Capture the payment
            $retrieveOptions = ['expand' => ['charges.data.transfer']];
            $paymentIntent = app(PaymentGatewayInterface::class)->retrievePayment($paymentIntentId, $retrieveOptions);
            // Check status and send email
            $status = $paymentIntent->status;
            $guideId = Auth::id();
            $guideInfo = Staff::find($guideId);
            $guideName = $guideInfo->first_name . " " . $guideInfo->last_name;
            $amount = $paymentIntent->amount / 100;
            $arEmailData = [
                'custEmail' => $request->customer_email,
                'paymentStatus' => $status,
                'guideName' => $guideName,
                'custPayAmount' => $amount,
            ];
            $info = DB::table('tour_operators AS t_o')
                ->select([
                    't_o.tenant_id',
                    't_o.name AS tour_operator',
                    't_o.phone_number AS tour_op_phone',
                    't_o.email AS tour_op_email',
                    't_o.website AS tour_op_website',
                    't_o.logo AS tour_op_logo'
                ])
                ->first();
            if (in_array(Auth::user()->email, config('constants.prevent_sending_emails')) == false) {
                EmailService::sendTapToPayPaymentMail($info, $arEmailData);
            }
            return [
                'status' => "Success"
            ];
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
    /**
     * After tip payment process for tap to pay process save data
     * @return mixed
     */
    public function afterTipPayment($request): mixed
    {
        try {
            $validated = $request->validate([
                'payment_intent_id' => 'required',
            ]);

            $paymentIntentId = $validated['payment_intent_id'];
            // Capture the payment
            $paymentIntent = app(PaymentGatewayInterface::class)->capturePayment($paymentIntentId);

            // Check status and update DB
            $status = $paymentIntent->status;
            if ($status == 'succeeded') {
                $retrieveOptions = ['expand' => ['charges.data.transfer']];
                $paymentIntentRelational = app(PaymentGatewayInterface::class)->retrievePayment($paymentIntentId, $retrieveOptions);
                $gratuityCount = DB::table('gratuities')
                    ->where('transaction_number', $paymentIntentId)
                    ->count();
                if ($gratuityCount == 0) {
                    $guideId = $paymentIntent->metadata['guide_id'];
                    $tipAmount = $paymentIntent->metadata['tip_amount'];
                    $fees = $paymentIntent->metadata['fee'];
                    $amount = $paymentIntent->metadata['amount'];

                    DB::table('gratuities')->insertGetId([
                        'booking_id' => NULL,
                        'customer_id' => NULL,
                        'gratuity_setting_id' => NULL,
                        'guide_id' => $guideId,
                        'amount' => $tipAmount,
                        'fees' => $fees,
                        'transaction_number' => $paymentIntentId,
                        'application_fee_id' => $paymentIntent->charges->data[0]->application_fee ?? null,
                        'gratuity_type' => 'direct',
                        'payment_method' => 'Card',
                        'status' => 'paid',
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                        'created_by_user_type' => 'Customer'
                    ]);

                    if (isset($paymentIntentRelational->charges->data[0]->transfer->destination_payment)) {

                        $guideInfo = Staff::find($guideId);

                        $From = 'iOS Tap to Pay';

                        $stripeMetadata = [
                            'description' => 'Guide Id: ' . $guideId .
                                "\nGuide Name: " . $guideInfo->first_name . " " . $guideInfo->last_name .
                                "\nFrom: " . $From,
                            'metadata' => [
                                'guide_id' => $guideInfo->id,
                                'guide_name' => $guideInfo->first_name . " " . $guideInfo->last_name,
                                'tip_amount' => $tipAmount,
                                'fee' => $fees,
                                'amount' => $amount,
                                'from' => $From,
                                'app' => Constants::STATUS_ONE,
                                'tenant_id' => tenant()->id,
                            ],
                        ];

                        $chargeParams = $stripeMetadata;
                        app(PaymentGatewayInterface::class)->updateConnectedPayment(
                            $paymentIntentRelational->charges->data[0]->transfer->destination_payment,
                            $chargeParams,
                            $paymentIntentRelational->charges->data[0]->transfer->destination
                        );
                    }
                    return [
                        'status' => "Success"
                    ];
                } else {
                    throw new Exception("This payment has already been processed.");
                }
            } else {
                throw new Exception("Payment not successful.");
            }
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
    /**
     * Register internet card readers
     * @return mixed
     */
    public function registerInternetReader($request): mixed
    {
        try {
            $validated = $request->validate([
                'pairing_code' => 'required|string',
                'label' => 'required|string',
            ]);


            $tourOperator = TourOperator::first();
            $opts = [];
            if (!empty($tourOperator->stripe_terminal_location_id)) {
                $input = $request->all();
                $readerParams = [
                    'registration_code' => $input["pairing_code"],
                    'location'          => $tourOperator->stripe_terminal_location_id,
                    'label'             => $input["label"],
                ];
                $reader = app(PaymentGatewayInterface::class)->createTerminalReader($readerParams, $opts);
                return [
                    'reader_id' => $reader->id ?? null,
                    'reader_label' => $reader->label ?? null,
                    'error' => null
                ];
            } else {
                throw new Exception("Terminal location not configured.");
            }
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
    /**
     * Validate the request for creating gratuity setting.
     *
     * @param Request $request
     * @return array
     * @throws Exception
     */
    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'gratuityName' => 'required|string',
            'gratuityType' => 'required|in:fixed_percentage,fixed_amount,flexible',
            'gratuityDistribution' => 'required|in:equal,custom',
            'gratuityAmounts' => 'required_if:gratuityType,percentage|required_if:gratuityType,fixed|array',
            'gratuityAmounts.*.percentage' => 'required_if:gratuityType,percentage',
            'gratuityAmounts.*.description' => 'required_if:gratuityType,percentage|required_if:gratuityType,fixed|string',
            'gratuityAmounts.*.amount' => 'required_if:gratuityType,fixed',
            'minGratuity' => 'required_if:gratuityType,flexible|numeric|min:0',
            'maxGratuity' => 'required_if:gratuityType,flexible|numeric|gte:minGratuity|min:0',
            'defaultGratuity' => 'required_if:gratuityType,flexible|numeric|min:0',
            'postGratuity' => 'nullable|boolean',
            'preGratuity' => 'nullable|boolean',
            'customGratuityPre' => 'nullable|boolean',
            'customGratuityPost' => 'nullable|boolean',
            'gratuityMandatory' => 'nullable|boolean',
            'serviceProviderCut' => 'nullable|numeric|min:0|max:100'
        ]);
    }

     /**
     * Update the guide status
     * @param Request $request
     */
    public function updateGuideStatus(Request $request)
    {
        try {
            // Guide id
            $id = $request->id;

            $staff = Staff::find($id);
            $staff->guide_status = $request->guide_status;
            $staff->save();

            return [
                'status' => "Success"
            ];
            
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
}
