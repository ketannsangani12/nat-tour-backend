<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class Tenant extends BaseTenant implements TenantWithDatabase, Auditable
{
    use HasDatabase, HasDomains, AuditTrait;

    /**
     * Update the booking prefix from tenant.
     * This is used to keep the tenant settings and central db in sync.
     *
     * @param string $prefix The new prefix
     * @return void
     */
    public static function updatePrefixFromTenant($prefix)
    {
        $tenant = tenancy()->tenant->id;
        if (isset($tenant)) {
            $cdb = config('constants.database.' . app()->environment());
            $conn = DB::connection(env('DB_CONNECTION', 'mysql'));
            $conn->table("$cdb.domains")->where('tenant_id', $tenant)->update(['booking_prefix' => $prefix]);
        }
    }
}
