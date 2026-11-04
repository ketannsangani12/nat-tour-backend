<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Staff;
use App\Models\TourOperator;
use App\Models\TourPackage;
use App\Models\TourPromotion;
use App\Utils\BokunUtils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;

class ReportV2Controller extends Controller
{
    /**
     * Display the new report structure (v2)
     * This is a cleaner version that we'll gradually migrate reports to
     *
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        // Get the selected report from session (set when redirecting from v1)
        // Default to Payment Breakdown Report if not set
        $selectedReport = session('selected_report', 'Payment Breakdown Report');

        // Clear the session variable after reading it
        session()->forget('selected_report');

        $vars = [
            'page_title' => 'Payment Breakdown Report',
            'pageId' => 'reports',
            'slotTimes' => [],
            'rateGroups' => [],
            'tourOperatorId' => Cookie::has('tourOpId') ? Cookie::get('tourOpId') : 0,
            'tourPackageId' => 0,
            'date' => date('Y-m-d'),
            'uType' => session('userType'),
            'staicReportList' => staticReportList(),
            'adminStaicReportList' => adminStaicReportList(),
            'touristsGroups' => [],
            'timeSlotId' => 0,
            'promocodes' => [],
            'departmentPackage' => departmentPackage(),
            'selectedReport' => $selectedReport // Pass the selected report to the view
        ];

        $departmentPackage = $vars['departmentPackage'];

        // Get affiliates based on user type
        if (session('userType') == 'Admin') {
            $affiliates = DB::table('customers')
                ->where('is_affiliate', 'Yes')
                ->whereNotIn('name', ['TripAdvisor', 'Bokun'])
                ->where('bokun', 0)
                ->where('deleted_by', 0)
                ->orderBy('name')
                ->get(['id', 'name']);
        } else if (session('userType') == 'Affiliate') {
            $affiliates = DB::table('customers')
                ->where('is_affiliate', 'Yes')
                ->whereNotIn('name', ['TripAdvisor', 'Bokun'])
                ->where('bokun', 0)
                ->where('id', Auth::id())
                ->where('deleted_by', 0)
                ->orderBy('name')
                ->get(['id', 'name']);
        } else {
            $affiliates = DB::table('customers')
                ->where('is_affiliate', 'Yes')
                ->whereNotIn('name', ['TripAdvisor', 'Bokun'])
                ->where('bokun', 0)
                ->when($departmentPackage, function ($query) {
                    return $query->where('department_id', Auth::user()->department_id);
                })
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        $promocodes = TourPromotion::where('status', 'Active')->get(['id', 'code']);
        $vars['promocodes'] = $promocodes;
        $vars['affiliates'] = $affiliates;

        // Get user names for filters
        $admins = [];
        $userNames = [];
        if (userType() == "Tour Operator" && Auth::user()->department_id) {
            $staff = Staff::where('department_id', Auth::user()->department_id)->get();
            $customers = Customer::where('department_id', Auth::user()->department_id)->isAffiliate()->get(['id', 'name']);
        } else {
            $admins = Admin::all(['id', 'name']);
            $staff = Staff::all();
            $customers = Customer::isAffiliate()->get(['id', 'name']);
        }

        foreach ($admins as $admin) {
            $role = $admin->roles();
            $userNames[] = [
                'id' => $admin->id,
                'name' => $admin->name,
                'role' => isset($role->first()->display_name) ? $role->first()->display_name : null,
            ];
        }

        foreach ($staff as $staffMember) {
            $role = $staffMember->roles();
            $userNames[] = [
                'id' => $staffMember->id,
                'name' => $staffMember->first_name . ' ' . $staffMember->last_name,
                'role' => isset($role->first()->display_name) ? $role->first()->display_name : null,
            ];
        }

        foreach ($customers as $customer) {
            $role = $customer->roles();
            $userNames[] = [
                'id' => $customer->id,
                'name' => $customer->name,
                'role' => isset($role->first()->display_name) ? $role->first()->display_name : null,
            ];
        }

        $vars['userNames'] = $userNames;

        // Get tour operator and package data
        $list = [];
        if (session('userType') == 'Admin') {
            $tourOperators = TourOperator::where('status', 'Active')
                ->orderBy('name')
                ->get(['id', 'name','logo','timezone']);

            $vars['tourOperators'] = $tourOperators;

            // For Admin, get all tour packages or packages for the selected tour operator
            $selectedTourOperatorId = Cookie::has('tourOpId') ? Cookie::get('tourOpId') : ($tourOperators->first()->id ?? 0);

            if ($selectedTourOperatorId > 0) {
                $vars['tourPackages'] = TourPackage::where('tour_operator_id', $selectedTourOperatorId)
                    ->orderBy('name')
                    ->get(['id', 'name']);
            } else {
                $vars['tourPackages'] = [];
            }

            $vars['taxes'] = $vars['staff'] = [];
            $vars['tourOperatorName'] = '';
            $vars['tourOperatorLogo'] = ($tourOperators[0]->logo != '') ? tourOperatorLogo(null,$tourOperators[0]->logo) : '';
            $vars['tourOperatorTimezone'] = $tourOperators[0]->timezone ?? config('app.timezone');
        } else {
            $tourOperatorDetails = TourOperator::find(Auth::user()->tour_operator_id);
            $vars['tourOperatorName'] = $tourOperatorDetails->name;
            $vars['tourOperatorId'] = Auth::user()->tour_operator_id;
            $vars['tourOperatorLogo'] = ($tourOperatorDetails->logo != '') ? tourOperatorLogo(null,$tourOperatorDetails->logo) : '';
            $vars['tourOperatorTimezone'] = $tourOperatorDetails->timezone ?? config('app.timezone');

            if ($departmentPackage) {
                $vars['tourPackages'] = TourPackage::when(userType() == 'Affiliate', function ($q) {
                        return $q->whereHas('packageAffiliate', function ($q) {
                            $q->where('affiliate_id', Auth::id());
                        });
                    })
                    ->when($departmentPackage, function ($query) use ($departmentPackage) {
                        return $query->whereIn('id', $departmentPackage);
                    })
                    ->orderBy('name')
                    ->get(['id', 'name']);
            } else {
                $vars['tourPackages'] = TourPackage::when(userType() == 'Affiliate', function ($q) {
                        return $q->whereHas('packageAffiliate', function ($q) {
                            $q->where('affiliate_id', Auth::id());
                        });
                    })
                    ->orderBy('name')
                    ->get(['id', 'name']);
            }

            $vars['reports'] = TourOperator::select('reports')->where('id', $vars['tourOperatorId'])->first();
            $reportlist = json_decode($vars['reports']->reports) ?? [];
            $reportArray = [];
            foreach ($reportlist as $report) {
                if ($report != 'Financial Report') {
                    $reportArray[] = $report;
                }
            }

            if ($request->api == 1) {
                foreach ($reportArray as $key => $report) {
                    $list[$key]['name'] = $report;
                }
            } else {
                foreach ($reportlist as $key => $report) {
                    $list[$key]['name'] = $report;
                }
            }
        }

        // Get addon names
        $data = DB::table('tour_package_form as tpf')
            ->join('tour_packages as tp', 'tp.id',  '=', 'tpf.form_package_id')
            ->get(['tpf.form_fields', 'tpf.id as tour_package_form_id', 'tp.id as tour_package_id', 'tp.name as tour_package_name']);

        $addonsName = [];
        foreach ($data as $value) {
            $form_fields = json_decode($value->form_fields);
            if ($form_fields) {
                foreach ($form_fields as $form) {
                    $addonsName[] = $form->name;
                }
            }
        }

        // Also surface labels from the newer relational add-ons builder
        // (addon_fields), not just the legacy JSON form builder above -
        // otherwise every add-on created since the schema migration is
        // unfilterable in this dropdown.
        $addonsName = array_merge($addonsName, DB::table('addon_fields')->whereNull('deleted_at')->pluck('label')->all());

        $vars['addonsName'] = array_unique($addonsName);

        // Check permissions
        if (userType() != "Admin") {
            if (userType() == "Tour Operator") {
                $staffPermission = Staff::with('permissions')->where('id', Auth::user()->id)->first();
            } else {
                $staffPermission = Customer::with('permissions')->where('id', Auth::user()->id)->first();
            }
            $permissionsArray = [];
            foreach ($staffPermission->permissions as $permission) {
                $permissionsArray[] = $permission->display_name;
            }
            $reportsArray = json_decode($vars['reports']->reports) ?? [];
            $arrayIntersect = array_intersect($permissionsArray, $reportsArray);
            $vars['reports']->reports = json_encode($arrayIntersect);
        }

        // Get enabled third-party platforms from settings
        $vars['enabledThirdPartyPlatforms'] = $this->getEnabledThirdPartyPlatforms();

        // Return the new report view (v2)
        return view('users.common.report-v2', $vars);
    }

    /**
     * Get enabled third party platforms from settings
     *
     * @return array
     */
    private function getEnabledThirdPartyPlatforms(): array
    {
        $platforms = [];

        // Platform names are sourced dynamically from config('constants.otas') so a newly
        // added OTA becomes filterable without a code change. FareHarbor isn't in that
        // registry (it's detected via a comments marker, not an ota_reference_booking
        // prefix), so it's included alongside it as a fixed special case.
        $platformNames = array_merge(BokunUtils::getReportableOtaTitles(), ['FareHarbor']);

        $settingKeys = array_map(
            fn($name) => 'ENABLE_' . strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $name)),
            $platformNames
        );

        $settings = \App\Models\Setting::whereIn('key', $settingKeys)->get()->keyBy('key');

        foreach ($platformNames as $platformName) {
            $settingKey = 'ENABLE_' . strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $platformName));

            // Only show a platform when its settings row exists and is explicitly enabled.
            // No row = hidden, until a tenant admin explicitly turns it on.
            if (isset($settings[$settingKey]) && $settings[$settingKey]->value == 1) {
                $platforms[] = $platformName;
            }
        }

        return $platforms;
    }

    /**
     * Store the selected report in session
     * Used when switching between v1 and v2 report structures
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function setSelectedReport(Request $request)
    {
        $report = $request->input('report');
        
        if ($report) {
            session(['selected_report' => $report]);
            return response()->json(['success' => true]);
        }
        
        return response()->json(['success' => false], 400);
    }
}
