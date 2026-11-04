<?php

namespace App\Console\Commands;

use App\Models\OctoKey;
use App\Utils\BokunUtils;
use Illuminate\Console\Command;

class OctoIssueApiKey extends Command
{
    protected $signature = 'octo:issue-key
                            {tenant_id : Tenant slug, e.g. dixies or kens}
                            {reseller* : OTA reseller name — words separated by spaces, e.g. national park express chd}
                            {--key=    : Override the auto-generated key (e.g. to import an existing one)}';

    protected $description = 'Generate and store a new OCTO bearer API key for a reseller (shown once)';

    public function handle(): int
    {
        $tenantId = $this->argument('tenant_id');
        $reseller = BokunUtils::toResellerSlug(implode(' ', $this->argument('reseller')));

        if (!$reseller) {
            $this->error('Reseller name is empty after normalisation.');
            return self::FAILURE;
        }

        if (OctoKey::where('tenant_id', $tenantId)->where('reseller', $reseller)->where('is_active', true)->exists()) {
            $this->error("An active key already exists for {$tenantId} / {$reseller}. Revoke it first with octo:revoke-key.");
            return self::FAILURE;
        }

        // Remove any previously revoked key so the unique constraint allows the new record
        OctoKey::where('tenant_id', $tenantId)->where('reseller', $reseller)->where('is_active', false)->delete();

        $plaintext = $this->option('key') ?: $this->buildKey($tenantId, $reseller);
        $hash      = hash('sha256', $plaintext);
        $prefix    = substr($plaintext, 0, 20);

        OctoKey::create([
            'tenant_id'    => $tenantId,
            'reseller'     => $reseller,
            'api_key_hash' => $hash,
            'key_prefix'   => $prefix,
            'is_active'    => true,
        ]);

        $env = app()->environment();

        $this->newLine();
        $this->line("  API key issued for <fg=cyan>{$tenantId}</> / <fg=cyan>{$reseller}</> <fg=gray>[{$env}]</>");
        $this->line('  ' . str_repeat('─', 60));
        $this->line("  Key: <fg=yellow>{$plaintext}</>");
        $this->line('  ' . str_repeat('─', 60));
        $this->warn('  Copy this key now. It will NOT be shown again.');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Build the key from the convention:
     *   {OCTO_KEY_PREFIX}-{tenant_id}-{reseller}-{environment}
     *
     * Set OCTO_KEY_PREFIX in .env, e.g. "native-american-tours"
     */
    private function buildKey(string $tenantId, string $reseller): string
    {
        $prefix = str_replace(' ', '-', strtolower(env('OCTO_KEY_PREFIX', config('octo.key_prefix'))));
        $env    = app()->environment('production') ? 'production' : 'test';

        return "{$prefix}-{$tenantId}-" . BokunUtils::toResellerSlug($reseller) . "-{$env}";
    }
}
