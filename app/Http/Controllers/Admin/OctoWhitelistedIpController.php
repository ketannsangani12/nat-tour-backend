<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\OctoIpWhitelist;
use App\Models\OctoWhitelistedIp;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class OctoWhitelistedIpController extends Controller
{
    /**
     * Admin page for managing whitelisted IPs (list loads via the index endpoint below).
     */
    public function page(): View
    {
        return view('users.admin.octo-whitelisted-ips', ['page_title' => 'OCTO IP Whitelist']);
    }

    /**
     * List all whitelisted IPs (including expired ones, for visibility).
     */
    public function index(): JsonResponse
    {
        $ips = OctoWhitelistedIp::on('mysql')
            ->orderByDesc('created_at')
            ->get(['id', 'ip', 'label', 'created_by', 'expires_at', 'created_at']);

        return response()->json($ips);
    }

    /**
     * Whitelist an IP.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'ip'    => ['required', 'string', 'regex:/^(\d{1,3}\.){3}\d{1,3}(\/\d{1,2})?$/'],
            'label' => ['required', 'string', 'max:255'],
        ]);

        $ip = $request->input('ip');

        [$address, $bits] = array_pad(explode('/', $ip, 2), 2, null);

        if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return sendJsonErrMsg("'{$ip}' is not a valid IPv4 address or CIDR range.", 422);
        }

        if ($bits !== null && ((int) $bits < 0 || (int) $bits > 32)) {
            return sendJsonErrMsg("'{$ip}' has an invalid CIDR suffix.", 422);
        }

        // Always expires 48 hours after being added — no way for the admin to
        // override this, so a whitelisted IP never lingers indefinitely.
        $entry = OctoWhitelistedIp::on('mysql')->updateOrCreate(
            ['ip' => $ip],
            [
                'label'      => $request->input('label'),
                'created_by' => auth('admin')->id(),
                'expires_at' => now()->addHours(48),
            ]
        );

        Cache::forget(OctoIpWhitelist::CACHE_KEY);

        return response()->json($entry, 201);
    }

    /**
     * Remove a whitelisted IP.
     */
    public function destroy(OctoWhitelistedIp $octoWhitelistedIp): JsonResponse
    {
        $octoWhitelistedIp->delete();

        Cache::forget(OctoIpWhitelist::CACHE_KEY);

        return response()->json([]);
    }
}
