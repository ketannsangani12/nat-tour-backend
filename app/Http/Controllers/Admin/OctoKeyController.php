<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OctoKey;
use App\Utils\BokunUtils;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OctoKeyController extends Controller
{
    /**
     * List all keys (active + inactive) for a tenant.
     */
    public function index(string $tenantId): JsonResponse
    {
        $keys = OctoKey::on('mysql')
            ->where('tenant_id', $tenantId)
            ->orderBy('reseller')
            ->get(['id', 'tenant_id', 'reseller', 'key_prefix', 'is_active', 'created_at']);

        return response()->json($keys->map(fn ($key) => [
            'id'         => $key->id,
            'reseller'   => $key->reseller,
            'masked_key' => $key->masked_key,
            'is_active'  => $key->is_active,
            'created_at' => $key->created_at->toDateString(),
        ]));
    }

    /**
     * Issue a new key for a reseller.
     * Blocked if an active key already exists — admin must revoke first.
     * Any previously revoked key for the same pair is cleaned up automatically.
     */
    public function store(Request $request, string $tenantId): JsonResponse
    {
        $request->validate([
            'reseller' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9\s]+$/'],
        ], [
            'reseller.regex' => 'Reseller name may only contain letters, numbers and spaces — no hyphens or underscores.',
        ]);

        $reseller = BokunUtils::toResellerSlug($request->reseller);

        // Block if active key already exists
        $activeExists = OctoKey::on('mysql')
            ->where('tenant_id', $tenantId)
            ->where('reseller', $reseller)
            ->where('is_active', true)
            ->exists();

        if ($activeExists) {
            return sendJsonErrMsg('An active key already exists for this reseller. Revoke it first before issuing a new one.', 409);
        }

        // Remove any revoked key so the unique constraint allows the new record
        OctoKey::on('mysql')
            ->where('tenant_id', $tenantId)
            ->where('reseller', $reseller)
            ->where('is_active', false)
            ->delete();

        $plaintext = $this->buildKey($tenantId, $reseller);

        OctoKey::on('mysql')->create([
            'tenant_id'    => $tenantId,
            'reseller'     => $reseller,
            'api_key_hash' => hash('sha256', $plaintext),
            'key_prefix'   => substr($plaintext, 0, 20),
            'is_active'    => true,
        ]);

        return response()->json([
            'key'     => $plaintext,
            'message' => 'Copy this key now and pass it to the reseller. It will never be shown again.',
        ], 201);
    }

    /**
     * Revoke a key — disables access without deleting the record.
     */
    public function revoke(string $tenantId, OctoKey $octoKey): JsonResponse
    {
        $octoKey->update(['is_active' => false]);

        return response()->json([]);
    }

    /**
     * Permanently delete a key record (for cleanup of revoked keys).
     */
    public function destroy(string $tenantId, OctoKey $octoKey): JsonResponse
    {
        $octoKey->delete();

        return response()->json([]);
    }

    private function buildKey(string $tenantId, string $reseller): string
    {
        $prefix = str_replace(' ', '-', strtolower(env('OCTO_KEY_PREFIX', config('octo.key_prefix'))));
        $env    = app()->environment('production') ? 'production' : 'test';

        return "{$prefix}-{$tenantId}-" . BokunUtils::toResellerSlug($reseller) . "-{$env}";
    }
}
