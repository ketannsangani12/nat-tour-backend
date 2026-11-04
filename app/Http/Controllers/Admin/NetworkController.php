<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Network;
use App\Models\NetworkTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Yajra\DataTables\DataTables;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use App\Models\Admin;
use App\Models\Tenant;
use App\Models\SwitchCompany;
use App\Models\Staff;
use App\Models\Customer;
use App\Models\Configuration;
use Exception;
use App\Exceptions\Core\GeneralCoreException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class NetworkController extends Controller
{
    /**
     * Display the network listing page.
     *
     * @return View
     */
    public function index(): View
    {
        $page_title = "Networks";

        return view('users.admin.networks.index', compact('page_title'));
    }

    /**
     * Return JSON for DataTables listing of networks.
     *
     * @param DataTables $dataTables
     * @return mixed
     */
    public function indexAHR(DataTables $dataTables)
    {
        $query = DB::table('networks AS n')
            ->select(['n.id', 'n.name', 'n.slug', 'n.frontend_url', 'n.status']);

        return $dataTables->of($query)
            ->addIndexColumn()
            ->editColumn('status', function ($network) {
                $statusText = $network->status == 1 ? 'Active' : 'Inactive';
                return '<span class="status-' . $network->status . '">' . $statusText . '</span>';
            })
            ->addColumn('action', function ($network) {
                $editUrl = route(routePrefix() . '.networks.edit', ['id' => $network->id]);
                $deleteUrl = route(routePrefix() . '.networks.destroy', ['id' => $network->id]);

                return '
                    <a href="' . $editUrl . '" class="btn btn-edit btn-sm mr-2 btn-primary-small"><i class="far fa-edit mr-2"></i> Edit</a>
                    <button data-url="' . $deleteUrl . '" data-method="delete" class="btn btn-sm btn-delete btn-danger-small">
                        <i class="far fa-trash-alt mr-2"></i> Delete
                    </button>';
            })
            ->rawColumns(['action', 'status'])
            ->make(true);
    }

    /**
     * Show form to create a new network.
     *
     * @return View
     */
    public function create(): View
    {
        $page_title = "Create Network";

        return view('users.admin.networks.create', compact('page_title'));
    }

    /**
     * Store a new network record.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'frontend_url' => 'required|url',
        ]);

        Network::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'frontend_url' => $request->frontend_url,
            'status' => $request->status ?? 1,
        ]);

        return redirect()->route(routePrefix() . '.networks.index')->with('success', 'Network created successfully.');
    }

    /**
     * Show form to edit an existing network.
     *
     * @param int $id
     * @return View
     */
    public function edit(int $id): View
    {
        $network = Network::findOrFail($id);
        $networkTenants = NetworkTenant::where('network_id', $id)->get();
        $allTenants = DB::table('tenants')->get();
        $page_title = "Edit Network";

        return view('users.admin.networks.edit', compact('network', 'networkTenants', 'allTenants', 'page_title'));
    }

    /**
     * Update an existing network record.
     *
     * @param Request $request
     * @param int $id
     * @return RedirectResponse
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'frontend_url' => 'required|url',
        ]);

        $network = Network::findOrFail($id);

        $network->update([
            'name' => $request->name,
            'frontend_url' => $request->frontend_url,
            'status' => $request->status ?? 1,
        ]);

        return redirect()->route(routePrefix() . '.networks.index')->with('success', 'Network updated successfully.');
    }

    /**
     * Delete a network record.
     *
     * @param int $id
     * @return RedirectResponse
     */
    public function destroy(int $id): RedirectResponse
    {
        $network = Network::findOrFail($id);
        $network->delete();

        return redirect()->route(routePrefix() . '.networks.index')->with('success', 'Network deleted successfully.');
    }

    /**
     * Assign a tenant to a network.
     *
     * @param Request $request
     * @param int $networkId
     * @return RedirectResponse
     */
    public function assignTenant(Request $request, int $networkId): RedirectResponse
    {
        $request->validate([
            'tenant_id' => 'required',
        ]);

        NetworkTenant::create([
            'network_id' => $networkId,
            'tenant_id' => $request->tenant_id,
            'status' => 1,
        ]);

        return redirect()->back()->with('success', 'Tenant assigned successfully.');
    }

    /**
     * Remove a tenant from a network.
     *
     * @param int $id
     * @return RedirectResponse
     */
    public function removeTenant(int $id): RedirectResponse
    {
        $networkTenant = NetworkTenant::findOrFail($id);
        $configuration = Configuration::where('tenant_id', $networkTenant->tenant_id)->first();
        $configuration->delete();
        $networkTenant->delete();

        return redirect()->back()->with('success', 'Tenant removed from network.');
    }

     /**
     * Assign user a tenant from a network nester dropdown with list of assign user.
     *
     * @param int $id
     * @return view
     */
    public function assignUser($networkId)
    {
        try {
            $page_title = "Assign User";
            $tenantIds = DB::table('networks as n')
                        ->join('network_tenants as nt', 'nt.network_id', '=', 'n.id')
                        ->where('n.id', $networkId)
                        ->pluck('nt.tenant_id');
        
            // Fetch the tenants
            $tenants = Tenant::whereIn('id', $tenantIds)->get();
            // Create back url
            $backUrl = route(routePrefix() . '.networks.edit', ['id' => $networkId]);
            
            $users = [];
            
            foreach ($tenants as $tenant) {
                // Switch master connection for this tenant
                config(['database.connections.master.database' => $tenant->tenancy_db_name]);
                DB::purge('master');
                DB::reconnect('master');

                $domain = $tenant->domains->first()->domain ?? '';

                // Fetch Admins
                $admins = DB::connection('master')->table('admins')
                    ->where('status', 'Active')
                    ->whereNull('deleted_at')
                    ->get();

                // Fetch Staff
                $staffUsers = DB::connection('master')->table('tour_operator_staff')
                    ->where('status', 'Active')
                    ->whereNull('deleted_at')
                    ->get();

                // Fetch Affiliates (from customers table where is_affiliate = Yes)
                $affiliates = DB::connection('master')->table('customers')
                    ->where('status', 'Active')
                    ->where('is_affiliate', 'Yes')
                    ->whereNull('deleted_at')
                    ->get();

                $users[] = [
                    // 'tenant' => $tenant,
                    'domain' => $domain,
                    'admins' => $admins,
                    'staffUsers' => $staffUsers,
                    'affiliates' => $affiliates
                ];
            }               
           
            $tenantWithUser = [];

            foreach ($users as $tenant) {
                $domain = $tenant['domain'];
                $displayName = ucfirst(explode('.', $domain)[0]); // "Dixies", "Kens", etc.

                $tenantWithUser[$displayName] = [
                    'Admin' => collect($tenant['admins'] ?? [])->map(fn($user) => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'status' => $user->status
                    ])->toArray(),

                    'Tour Operator' => collect($tenant['staffUsers'] ?? [])->map(fn($user) => [
                        'id' => $user->id,
                        'name' => $user->first_name. ' '. $user->last_name,
                        'email' => $user->email,
                        'status' => $user->status
                    ])->toArray(),

                    'Affiliate' => collect($tenant['affiliates'] ?? [])->map(fn($user) => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'status' => $user->status
                    ])->toArray(),
                ];
            }

            $allAssignments = self::assignments($networkId);

            $perPage      = 5; // groups per page
            $currentPage  = LengthAwarePaginator::resolveCurrentPage();
            $collection   = collect($allAssignments);                 
            $currentItems = $collection->slice(
                ($currentPage - 1) * $perPage,
                $perPage,
                true                    // preserve keys (group‑id)
            );

            $assignments = new LengthAwarePaginator(
                $currentItems,
                $collection->count(),
                $perPage,
                $currentPage,
                [
                    'path' => url()->current(), 
                ]
            );

            $vars = [
                 'tenantWithUser' => $tenantWithUser, 
                 'page_title' => $page_title, 
                 'backUrl' => $backUrl, 
                 'networkId' => $networkId, 
                 'assignments' => $assignments
            ];
            
            return view('users.admin.networks.assign-user', $vars);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Assign user a tenant from a network save functionality.
     *
     * @param Request $request
     * @return back
     */
    
    public function assignTenantsUser(Request $request)
    {
        try {

            DB::beginTransaction();

            $groupId = $request->input('group_id');
            
            // If no group_id provided, create new group_id
            if (empty($groupId)) {
                $lastGroup = SwitchCompany::orderByDesc('group_id')->first();
                $groupId = $lastGroup ? $lastGroup->group_id + 1 : 1;
            } else {
                // Remove existing records for this group
                SwitchCompany::where('group_id', $groupId)->delete();
            }

            $tenantUsers = $request->input('tenant_users', []);

            $recordsToInsert = [];

            foreach ($tenantUsers as $tenantId => $userDataJson) {
                $userData = json_decode($userDataJson, true);

                if (!empty($userData['id'])) {
                    // Get tenant name
                    $tenant = Tenant::find($tenantId);
                    $tenantName = $tenant ? $tenant->name : null;

                    $recordsToInsert[] = [
                        'network_id' => $request->network_id,
                        'group_id'   => $groupId,
                        'tenant_id'  => $tenantId,
                        'tenant_name' => $tenantName,
                        'user_id'    => $userData['id'],
                        'user_type'  => $userData['type'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if (!empty($recordsToInsert)) {
                SwitchCompany::insert($recordsToInsert);
            }

            DB::commit();

            return redirect()->back()->with('success', 'User assigned successfully.');
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }


    /**
     * Fetching the assigned user list with name wise from the respective tenants.
     *
     * @param int $networkId
     * @return array
     */
    public function assignments($networkId)
    {
        $assignments = SwitchCompany::where('network_id', $networkId)->orderBy('group_id', 'DESC')->get();
        $groupedAssignments = $assignments->groupBy('group_id');
        $result = [];

        foreach ($groupedAssignments as $groupId => $records) {
            $groupData = [];

            foreach ($records as $record) {
                // Get tenant model using tenant_id
                $tenant = Tenant::find($record->tenant_id);

                if (!$tenant) continue;

                // Switch DB to this tenant's DB
                config(['database.connections.master.database' => $tenant->tenancy_db_name]);
                DB::purge('master');
                DB::reconnect('master');

                // Get domain name 
                $domain = $tenant->domains->first()->domain ?? 'unknown';
                $tenantName = ucfirst(Str::before($domain, '.'));

                // Fetch user name based on user_type
                $userName = 'N/A';

                switch ($record->user_type) {
                    case 'Admin':
                        $user = DB::connection('master')->table('admins')->find($record->user_id);
                        break;
                    case 'Affiliate':
                        $user = DB::connection('master')->table('customers')->find($record->user_id);
                        break;
                    case 'Tour Operator':
                        $user = DB::connection('master')->table('tour_operator_staff')->find($record->user_id);
                        break;
                }

                $userName = $user?->name ?? $user->first_name. ' '. $user->last_name;

                $groupData[] = [
                    'id'         => $record->id,
                    'tenant'     => $tenantName,
                    'tenant_id'  => $record->tenant_id,
                    'user_id'    => $record->user_id,
                    'user_type'  => $record->user_type,
                    'user_name'  => $userName,
                ];
            }

            $result[$groupId] = $groupData;
        }

        return $result;
    }


    /**
     * Delete the group of assign users for tenant.
     *
     * @param Request $request
     * @return back
     */
    public function deleteAssignTenantsUser(Request $request)
    {
        try {

                SwitchCompany::where('group_id', $request->group_id)->delete();
                return back()->with('success', 'Group Deleted.');

            } catch (Exception $exception) {
                Log::error($exception);
                throw new GeneralCoreException($exception);
        }
    }

    /**
     * Switch tenant.
     *
     * @param Request $request
     * @return back
     */
    public function switchTenant(Request $request)
    {
        try {
                $tenantId = $request->tenant['tenant_id'];
                $userId = $request->tenant['user_id'];
                $userType = $request->tenant['user_type'];
                $networkId = $request->tenant['network_id'];
                $userId = Crypt::encrypt($userId);

                $domain = $tenantId . config('constants.environment.' . config('app.env'));

                return response()->json([
                    'url' => 'https://' . $domain . '/' . 'accept-tenant' . '/' . $userId . '/' . $userType . '/' . $tenantId . '/' . $networkId
                ]);

            } catch (Exception $exception) {
                Log::error($exception);
                throw new GeneralCoreException($exception);
        }
    }

    /**
     * Login to another tenant using switch company
     * 
     * @param string $userId
     * @param string $userType
     * @param string $tenantId
     * @param string $networkId
     */
    public function acceptTenant($userId, $userType, $tenantId, $networkId)
    {
        try {
            $userId   = Crypt::decrypt($userId);

            $connection = DB::connection('mysql')
                ->table('switch_companies')
                ->where([
                    ['user_type', '=', $userType],
                    ['user_id', '=', $userId],
                    ['tenant_id', '=', $tenantId],
                    ['network_id', '=', $networkId],
                ])
                ->first();

            if (!$connection) {
                return redirect()->back();
            }

            session([
                'guard'    => getGuard($userType),
                'userType' => $userType,
            ]);

            $guard = getGuard($userType);

            $userModel = match ($userType) {
                'Admin'         => Admin::class,
                'Tour Operator' => Staff::class,
                default         => Customer::class,
            };

            $user = $userModel::find($userId);
            Auth::guard($guard)->loginUsingId($user->id);

            $redirectRoute = match ($userType) {
                'Admin'         => 'admin.tour-operator.booking.calendar',
                'Tour Operator' => 'tour-operator.booking.calendar',
                default         => 'affiliate.booking.calendar',
            };

            return redirect()->route($redirectRoute);
        } catch (Exception $exception) {
                Log::error($exception);
                throw new GeneralCoreException($exception);
        }
    }

    /**
     * Check the exit users
     * 
     * @param Request $request
     */
    public function checkUserExists(Request $request)
    {
        try {

            $userId = $request->input('user_id');
            $userType = $request->input('user_type');
            $tenantId = $request->input('tenant_id');

            $exists = SwitchCompany::where('user_id', $userId)
                ->where('user_type', $userType)
                ->where('tenant_id', $tenantId) 
                ->first();

            if ($exists) {
                // Get tenant model using tenant_id
                $tenant = Tenant::find($tenantId);

                // Switch DB to this tenant's DB
                config(['database.connections.master.database' => $tenant->tenancy_db_name]);
                DB::purge('master');
                DB::reconnect('master');
                switch ($userType) {
                    case 'Admin':
                        $user = DB::connection('master')->table('admins')->find($userId);
                        break;
                    case 'Affiliate':
                        $user = DB::connection('master')->table('customers')->find($userId);
                        break;
                    case 'Tour Operator':
                        $user = DB::connection('master')->table('tour_operator_staff')->find($userId);
                        break;
                }

                $userName = $user?->name ?? $user->first_name. ' '. $user->last_name;
                return response()->json([
                    'exists' => true,
                    'group_id' => $exists->group_id ?? null,
                    'name' => $userName,
                ]);
            }

            return response()->json(['exists' => false]);
        } catch (Exception $exception) {
                Log::error($exception);
                throw new GeneralCoreException($exception);
        }
    }

    public function getTenantTourPackage(Request $request)
    {
        $tenantId = $request->get('tenant_id');
        $networkId = $request->get('network_id');

        $config = Configuration::where('tenant_id', $tenantId)
            ->where('network_id', $networkId)
            ->first();

        // Get tenant model using tenant_id
        $tenant = Tenant::find($tenantId);

        // Switch DB to this tenant's DB
        config(['database.connections.master.database' => $tenant->tenancy_db_name]);
        DB::purge('master');
        DB::reconnect('master');
        
        $packages = DB::connection('master')->table('tour_packages')->select('id', 'name')->get();
                

        return response()->json([
            'calendar_switch' => $config?->calendar_switch ?? false,
            'selected_package_ids' => $config ? json_decode($config->package_assignments, true) : [],
            'packages' => $packages,
        ]);
    }

    public function saveConfiguration(Request $request)
    {
        $validated = $request->validate([
            'tenant_id' => 'required|string',
            'network_id' => 'required|integer',
            'package_assignments' => 'required|array',
        ]);

        // Determine if switch is checked
        $calendarSwitch = $request->has('calendar_switch');

        Configuration::updateOrCreate(
            [
                'tenant_id' => $validated['tenant_id'],
                'network_id' => $validated['network_id'],
            ],
            [
                'calendar_switch' => $calendarSwitch,
                'package_assignments' => isset($validated['package_assignments'])
                    ? json_encode($validated['package_assignments'])
                    : json_encode([]),
            ]
        );

        return back()->with('success', 'Configuration saved successfully.');
    }

}