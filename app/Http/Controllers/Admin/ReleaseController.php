<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Admin\ReleaseRequest;
use App\Mail\ReleaseNotification;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Release;
use App\Models\Staff;
use App\Models\Tenant;
use App\Notifications\ReleaseNoteNotification;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Intervention\Image\ImageManagerStatic as Image;
use Illuminate\Support\Facades\Storage;

class ReleaseController extends Controller
{
    /**
     * Show the number of release when page load
     */
    public $rowperpage = 5;

    /**
     * Get the list of release notes added in the platform
     */
    public function index()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $unreadReleaseQuery = $user->unreadNotifications()
            ->where('type', ReleaseNoteNotification::class);

        $latestUnreadReleaseId = (clone $unreadReleaseQuery)->latest()->first()?->data['id'] ?? null;

        $unreadReleaseQuery->update(['read_at' => now()]);

        $release['rowperpage'] = $this->rowperpage;

        if (isCentralDomain()) {
            $release['totalrecords'] = Release::count();

            $release['release'] = Release::take($this->rowperpage)
                ->orderByDesc('id')
                ->get();
        } else {
            $release['totalrecords'] = DB::connection('master')->table('releases')->whereNull('deleted_at')->count();

            $release['release'] = DB::connection('master')
                ->table('releases')
                ->take($this->rowperpage)
                ->whereNull('deleted_at')
                ->orderByDesc('id')
                ->get();
        }

        $routePrefix = userType() == 'Admin' ? 'admin.' : 'tour-operator.';

        $vars = [
            'page_title' => config('constants.release.page_title'),
            'release' => $release,
            'routePrefix' => $routePrefix,
            'latestUnreadReleaseId' => $latestUnreadReleaseId
        ];

        return view('users.admin.release.index', $vars);
    }

    /**
     * Get the release data based on times
     * 
     * @param Request $request
     */
    public function getRelease(Request $request)
    {
        $start = $request->get("start");

        if (isCentralDomain()) {
            $release = Release::skip($start)
                ->take($this->rowperpage)
                ->orderByDesc('id')
                ->get();
        } else {
            $release =  DB::connection('master')
                ->table('releases')
                ->skip($start)
                ->take($this->rowperpage)
                ->whereNull('deleted_at')
                ->orderByDesc('id')
                ->get();
        }

        $routePrefix = userType() == 'Admin' ? 'admin' : 'tour-operator';

        $html = view('users.admin.release.release-list', compact('release', 'routePrefix'))->render();

        $data['html'] = $html;

        return response()->json($data);
    }

    /**
     * Create the release note view
     */
    public function create()
    {
        $vars = [
            'page_title' => config('constants.release.page_title')
        ];

        return view('users.admin.release.create', $vars);
    }

    /**
     * Store a newly created resource in storage.
     * 
     * @param ReleaseRequest $request
     */
    public function store(ReleaseRequest $request)
    {
        try {
            DB::beginTransaction();
            $data = $request->all();
            $data['body'] = $this->processBase64Images($data['body'] ?? '');
            Release::create($data);
            DB::commit();

            $routePrefix = routePrefix();

            return redirect()
                ->route("{$routePrefix}.release.index")
                ->with('message', 'New release has been added.');
        } catch (Exception $exception) {
            DB::rollback();
            Log::error($exception->getMessage());

            return back()
                ->withErrors(['errors' => 'Unable to add new release.'])
                ->withInput();
        }
    }

    /**
     * Display the specified resource.
     * 
     * @param int $id
     */
    public function edit($id)
    {
        $release = Release::findOrFail($id);

        $vars = [
            'page_title' => config('constants.release.page_title'),
            'release' => $release
        ];

        return view('users.admin.release.edit', $vars);
    }

    /**
     * View the release note information
     * 
     * @param int $id
     */
    public function view($id)
    {
        if (isCentralDomain()) {
            $release = Release::findOrFail($id);
        } else {
            $release = DB::connection('master')
                ->table('releases')
                ->where('id', $id)
                ->whereNull('deleted_at')
                ->first();
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $notification = $user->unreadNotifications()->latest()->first();

        if ($notification && $notification->data['id'] == $id) {
            $notification->markAsRead();
        }

        $vars = [
            'page_title' => config('constants.release.page_title'),
            'release' => $release
        ];

        return view('users.admin.release.view', $vars);
    }

    /**
     * Update method to change the data of release note
     * 
     * @param ReleaseRequest $request
     * @param $id
     */
    public function update(ReleaseRequest $request, $id)
    {
        try {
            DB::beginTransaction();
            $release = Release::find($id);
            $data = $request->all();
            $data['body'] = $this->processBase64Images($data['body'] ?? '');
            $release->update($data);
            DB::commit();

            $routePrefix = routePrefix();

            return redirect()
                ->route("{$routePrefix}.release.index")
                ->with('message', 'Release has been updated.');
        } catch (Exception $exception) {
            DB::rollback();
            Log::error($exception->getMessage());

            return back()
                ->withErrors(['errors' => 'Unable to update release.'])
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     * 
     * @param string $id
     */
    public function destroy(string $id)
    {
        $routePrefix = routePrefix();

        Release::find($id)->update(['deleted_at' => date('Y-m-d H:i:s')]);

        return redirect()
            ->route("{$routePrefix}.release.index")
            ->with('message', 'Release has been deleted.');
    }

    /**
     * Store image for the release note
     * 
     * @param Request $request
     */
    public function storeImage(Request $request)
    {
        if ($request->hasFile('upload')) {
            $photo = $request->file('upload');
            $name = time() . $photo->getClientOriginalName();
            $imageThumb = Image::make($photo->path())->stream();
            $filePath = "images/release/$name";

            Storage::disk('s3')->put($filePath, $imageThumb);
            $url = Storage::disk('s3')->url($filePath);

            return response()->json([
                'fileName' => $name,
                'uploaded' => 1,
                'url' => $url
            ]);
        }
    }

    /**
     * Get the users who have received the release notification
     * 
     * @param int $id
     */
    public function releaseUsers($id)
    {
        $vars = [
            'page_title' => config('constants.release.page_title'),
            'release' => Release::findOrFail($id)
        ];

        // Fetch all tenants
        $tenants = Tenant::all();

        $users = [];
        // Send notifications to each tenant users
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
                'tenant' => $tenant,
                'domain' => $domain,
                'admins' => $admins,
                'staffUsers' => $staffUsers,
                'affiliates' => $affiliates
            ];
        }

        $vars['users'] = $users;

        return view('users.admin.release.users', $vars);
    }

    /**
     * Send release notifications to all tenant specific users
     * 
     * @param Request $request
     * @param int $id
     */
    public function sendNotifications(Request $request, $id)
    {
        $release = Release::findOrFail($id);
        $tenants = Tenant::all();
        $selectedUserIds = $request->input('user_ids', []);
        $from = $request->input('from', 'all'); // 'all' or 'notall'

        foreach ($tenants as $tenant) {
            config(['database.connections.master.database' => $tenant->tenancy_db_name]);
            DB::purge('master');
            DB::reconnect('master');

            $domain = $tenant->domains->first()->domain ?? '';

            // Fetch Admins
            $adminQuery = Admin::on('master')
                ->where('status', 'Active')
                ->whereNull('deleted_at');

            if ($from === 'notall') {
                if (!isset($selectedUserIds[$tenant->id]['admin']) || empty($selectedUserIds[$tenant->id]['admin'])) {
                    // No selected admins — skip this type
                    $admins = collect();
                } else {
                    $adminQuery->whereIn('id', $selectedUserIds[$tenant->id]['admin']);
                    $admins = $adminQuery->get();
                }
            } else {
                $admins = $adminQuery->get();
            }

            if ($admins->isNotEmpty()) {
                $routePrefix = 'admin.tour-operator';
                foreach ($admins as $admin) {
                    $admin->notify(new ReleaseNoteNotification($release, $admin, $tenant, $domain, $routePrefix));
                    Mail::to($admin->email)->queue(new ReleaseNotification($release, $admin, $tenant, $domain, $routePrefix));
                }
            }

            // Fetch Staff
            $staffQuery = Staff::on('master')
                ->where('status', 'Active')
                ->whereNull('deleted_at');

            if ($from === 'notall') {
                if (!isset($selectedUserIds[$tenant->id]['staff']) || empty($selectedUserIds[$tenant->id]['staff'])) {
                    $staffUsers = collect();
                } else {
                    $staffQuery->whereIn('id', $selectedUserIds[$tenant->id]['staff']);
                    $staffUsers = $staffQuery->get();
                }
            } else {
                $staffUsers = $staffQuery->get();
            }

            if ($staffUsers->isNotEmpty()) {
                $routePrefix = 'tour-operator';
                foreach ($staffUsers as $staffUser) {
                    $staffUser->notify(new ReleaseNoteNotification($release, $staffUser, $tenant, $domain, $routePrefix));
                    Mail::to($staffUser->email)->queue(new ReleaseNotification($release, $staffUser, $tenant, $domain, $routePrefix));
                }
            }

            // Fetch Affiliates
            $affiliateQuery = Customer::on('master')
                ->where('status', 'Active')
                ->where('is_affiliate', 'Yes')
                ->whereNull('deleted_at');

            if ($from === 'notall') {
                if (!isset($selectedUserIds[$tenant->id]['affiliate']) || empty($selectedUserIds[$tenant->id]['affiliate'])) {
                    $affiliates = collect();
                } else {
                    $affiliateQuery->whereIn('id', $selectedUserIds[$tenant->id]['affiliate']);
                    $affiliates = $affiliateQuery->get();
                }
            } else {
                $affiliates = $affiliateQuery->get();
            }

            if ($affiliates->isNotEmpty()) {
                $routePrefix = 'affiliate';
                foreach ($affiliates as $affiliate) {
                    $affiliate->notify(new ReleaseNoteNotification($release, $affiliate, $tenant, $domain, $routePrefix));
                    Mail::to($affiliate->email)->queue(new ReleaseNotification($release, $affiliate, $tenant, $domain, $routePrefix));
                }
            }
        }

        return response()->json(['message' => 'Emails sent successfully!']);
    }

    private function processBase64Images(string $html): string
    {
        return preg_replace_callback(
            '/src="data:(image\/[a-zA-Z+]+);base64,([^"]+)"/i',
            function ($matches) {
                $mime = $matches[1];
                $data = $matches[2];

                $ext = match (true) {
                    str_contains($mime, 'jpeg') || str_contains($mime, 'jpg') => 'jpg',
                    str_contains($mime, 'gif') => 'gif',
                    str_contains($mime, 'webp') => 'webp',
                    default => 'png',
                };

                $filename = uniqid('img_', true) . '.' . $ext;
                $filePath = 'images/release/' . $filename;
                Storage::disk('s3')->put($filePath, base64_decode($data));

                return 'src="' . Storage::disk('s3')->url($filePath) . '"';
            },
            $html
        );
    }
}
