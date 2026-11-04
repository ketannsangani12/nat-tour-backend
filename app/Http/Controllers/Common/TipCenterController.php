<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Repositories\Customer\CustomerRepository;
use App\Repositories\TipCenter\GratuityRepository;
use Exception;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class TipCenterController extends Controller
{
    protected $customerRepository;

    protected $gratuityRepository;

    /**
     * @param  CustomerRepository  $customerRepository
     * @param  GratuityRepository  $gratuityRepository
     */
    public function __construct(
        CustomerRepository $customerRepository,
        GratuityRepository $gratuityRepository,
    ) {
        parent::__construct();

        $this->customerRepository = $customerRepository;
        $this->gratuityRepository = $gratuityRepository;

        Stripe::setApiKey(getStripeSecretKey());
    }

    /**
     * Show Dashboard of tip center
     *
     * 
     * @return mixed
     */
    public function dashboard()
    {
        try {
            $vars = $this->gratuityRepository->dashboard();
            return view('users.common.tip-center.dashboard', $vars);
        } catch (Exception $exception) {
            Log::error($exception);
            return back()
                ->withErrors(['errors' => $exception->getMessage()])
                ->withInput();
        }
    }

    /**
     * Show profile of guide
     *  
     *  
     * @return mixed
     */
    public function profile()
    {
        try {
            $vars = $this->gratuityRepository->profile();
            return view('users.common.tip-center.profile', $vars);
        } catch (Exception $exception) {
            Log::error($exception);
            return back()
                ->withErrors(['errors' => $exception->getMessage()])
                ->withInput();
        }
    }
    /**
     * Update profile of guide
     *
     * @param $request
     * @return mixed
     */
    public function updateProfile(Request $request)
    {
        try {
            return $this->gratuityRepository->updateProfile($request);
        } catch (Exception $exception) {
            Log::error($exception);
            return sendJsonErrMsg($exception->getMessage());
        }
    }
    /**
     * Show profile of guide
     *  
     *  
     * @return mixed
     */
    public function createTip()
    {
        try {
            $vars = $this->gratuityRepository->createTip();
            return view('users.common.tip-center.create-tip', $vars);
        } catch (Exception $exception) {
            Log::error($exception);
            return back()
                ->withErrors(['errors' => $exception->getMessage()])
                ->withInput();
        }
    }
    /**
     * Make tip payment from guide dashboard
     *
     * @param $request
     * @return mixed
     */
    public function makeTipPayment(Request $request)
    {
        try {
            return $this->gratuityRepository->makeTipPayment($request);
        } catch (Exception $exception) {
            Log::error($exception);
            return sendJsonErrMsg($exception->getMessage());
        }
    }
    /**
     * Send tip payment link to customer
     *
     * @param $request
     * @return mixed
     */
    public function sendTipPaymentLink(Request $request)
    {
        try {
            return $this->gratuityRepository->sendTipPaymentLink($request);
        } catch (Exception $exception) {
            Log::error($exception);
            return sendJsonErrMsg($exception->getMessage());
        }
    }
    /**
     * Show List of Settings of tip center
     *
     * 
     * @return mixed
     */
    public function settings()
    {
        try {
            $vars['tourPackages'] = DB::table('tour_packages')
                ->select('id', 'name')
                ->where('status', 'Active')
                ->get();
            $vars['page_title'] = 'Gratuity Settings';
            return view('users.common.tip-center.settings', $vars);
        } catch (Exception $exception) {
            Log::error($exception);
            return back()
                ->withErrors(['errors' => $exception->getMessage()])
                ->withInput();
        }
    }

    /**
     * Get settings list of gratuity
     *
     * @param \Yajra\DataTables\DataTables $dt
     * @return mixed
     */
    public function settingsList(DataTables $dt)
    {
        try {
            return $this->gratuityRepository->settingsList($dt);
        } catch (Exception $exception) {
            Log::error($exception);
            return back()
                ->withErrors(['errors' => $exception->getMessage()])
                ->withInput();
        }
    }
    /**
     * Summary of edit gratuity setting
     * @param int $id
     * @return mixed
     */
    public function editGratuitySetting(int $id)
    {
        try {
            $vars = $this->gratuityRepository->editGratuitySetting($id);
            return view('users.common.tip-center.gratuity-setting-edit', $vars);
        } catch (Exception $exception) {
            Log::error($exception);
            return redirect()->back()->with('error', $exception->getMessage());
        }
    }
    /**
     * Store a gratuity setting
     *
     * @param $request
     * @return mixed
     */
    public function saveGratuitySetting(Request $request)
    {
        try {
            return $this->gratuityRepository->saveGratuitySetting($request);
        } catch (Exception $exception) {
            Log::error($exception);
            return sendJsonErrMsg($exception->getMessage());
        }
    }

    /**
     * Show list of guides
     *  
     *  
     * @return mixed
     */
    public function guides()
    {
        try {
            $vars = $this->gratuityRepository->guides();
            return view('users.common.tip-center.guides', $vars);
        } catch (Exception $exception) {
            Log::error($exception);
            return back()
                ->withErrors(['errors' => $exception->getMessage()])
                ->withInput();
        }
    }
    /**
     * Search guide
     *  
     *  
     * @return mixed
     */
    public function searchGuide(Request $request)
    {
        try {
            return $this->gratuityRepository->searchGuide($request);
        } catch (Exception $exception) {
            Log::error($exception);
            return back()
                ->withErrors(['errors' => $exception->getMessage()])
                ->withInput();
        }
    }
    /**
     * Show bookings page
     *  
     *  
     * @return mixed
     */
    public function bookings()
    {
        try {
            $vars = $this->gratuityRepository->bookings();
            return view('users.common.tip-center.bookings', $vars);
        } catch (Exception $exception) {
            Log::error($exception);
            return back()
                ->withErrors(['errors' => $exception->getMessage()])
                ->withInput();
        }
    }
    /**
     * Get settings list of gratuity
     *
     * @param $request
     * @return mixed
     */
    public function guideBookings(Request $request)
    {
        try {
            return $this->gratuityRepository->guideBookings($request);
        } catch (Exception $exception) {
            Log::error($exception);
            return back()
                ->withErrors(['errors' => $exception->getMessage()])
                ->withInput();
        }
    }
    /**
     * Summary of edit guide information
     * @param int $id
     * @return mixed
     */
    public function editGuide(int $id)
    {
        try {
            $tourGuideInfo = Staff::find($id);
            return view('users.common.tip-center.guide-edit', [
                'page_title' => 'Edit Guide Information',
                'tourGuideInfo' => $tourGuideInfo
            ]);
        } catch (Exception $exception) {
            Log::error($exception);
            return redirect()->back()->with('error', $exception->getMessage());
        }
    }
    /**
     * Update guide general information
     *
     * @param $request
     * @return mixed
     */
    public function updatGuideGeneralInfo(Request $request)
    {
        try {
            return $this->gratuityRepository->updatGuideGeneralInfo($request);
        } catch (Exception $exception) {
            Log::error($exception);
            return sendJsonErrMsg($exception->getMessage());
        }
    }
    /**
     * Update guide stripe account information
     *
     * @param $request
     * @return mixed
     */
    public function updatGuideStripeAccountInfo(Request $request)
    {
        try {
            return $this->gratuityRepository->updatGuideStripeAccountInfo($request);
        } catch (Exception $exception) {
            Log::error($exception);
            return sendJsonErrMsg($exception->getMessage());
        }
    }
    /**
     * Update guide bank account information
     *
     * @param $request
     * @return mixed
     */
    public function updatGuideBankAccountInfo(Request $request)
    {
        try {
            return $this->gratuityRepository->updatGuideBankAccountInfo($request);
        } catch (Exception $exception) {
            Log::error($exception);
            return sendJsonErrMsg($exception->getMessage());
        }
    }
    /**
     * Show Dashboard of tip center
     *
     * 
     * @return mixed
     */
    public function timeline()
    {
        try {
            return view('users.common.tip-center.timeline', ['page_title' => 'Gratuity Timeline']);
        } catch (Exception $exception) {
            Log::error($exception);
            return back()
                ->withErrors(['errors' => $exception->getMessage()])
                ->withInput();
        }
    }
    /**
     * Show distribution page
     *  
     * 
     * @return mixed
     */
    public function distributions()
    {
        try {
            $vars = $this->gratuityRepository->distributions();
            return view('users.common.tip-center.timeline', $vars);
        } catch (Exception $exception) {
            Log::error($exception);
            return back()
                ->withErrors(['errors' => $exception->getMessage()])
                ->withInput();
        }
    }
    /**
     * Show list of distributions
     *  
     * 
     * @return mixed
     */
    public function distributionsList(Request $request)
    {
        try {
            return $this->gratuityRepository->distributionsList($request);
        } catch (Exception $exception) {
            Log::error($exception);
            return back()
                ->withErrors(['errors' => $exception->getMessage()])
                ->withInput();
        }
    }
    /**
     * Summary of edit guide information
     * @param int $id
     * @return mixed
     */
    public function distributionInfo(int $id)
    {
        try {
            return $this->gratuityRepository->distributionInfo($id);
        } catch (Exception $exception) {
            Log::error($exception);
            return redirect()->back()->with('error', $exception->getMessage());
        }
    }
    /**
     * Save distribution amounts for booking
     *
     * @param $request
     * @return mixed
     */
    public function saveDistributionAmounts(Request $request)
    {
        try {
            return $this->gratuityRepository->saveDistributionAmounts($request);
        } catch (Exception $exception) {
            Log::error($exception);
            return sendJsonErrMsg($exception->getMessage());
        }
    }
    /**
     * Distributions Export
     */
    public function distributionsExport($fromDate = "", $toDate = "", $payrollScheduleId = '')
    {
        try {
            return $this->gratuityRepository->distributionsExport($fromDate, $toDate, $payrollScheduleId);
        } catch (Exception $exception) {
            Log::error($exception);
            return sendJsonErrMsg($exception->getMessage());
        }
    }
    /**
     * Accept tips 
     *
     * @param $request
     * @return mixed
     */
    public function processAcceptTips(Request $request)
    {
        try {
            return $this->gratuityRepository->processAcceptTips($request);
        } catch (Exception $exception) {
            Log::error($exception);
            return sendJsonErrMsg($exception->getMessage());
        }
    }
    /**
     * Accept bulk tips
     *
     * @param $request
     * @return mixed
     */
    public function processBulkAcceptTips(Request $request)
    {
        try {
            return $this->gratuityRepository->processBulkAcceptTips($request);
        } catch (Exception $exception) {
            Log::error($exception);
            return sendJsonErrMsg($exception->getMessage());
        }
    }
    /**
     * Complete bulk tips
     *
     * @param $request
     * @return mixed
     */
    public function processBulkCompleteTips(Request $request)
    {
        try {
            return $this->gratuityRepository->processBulkCompleteTips($request);
        } catch (Exception $exception) {
            Log::error($exception);
            return sendJsonErrMsg($exception->getMessage());
        }
    }
}
