<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Permission for the Mass Cancellation page, added for the
     * "admin" and "tour_operator_staff" guards only (not affiliates).
     * The refund types available on the page still follow the existing
     * cancel-*-refund permissions.
     */
    private array $permissions = [
        [
            'name' => 'mass-cancellation',
            'display_name' => 'Mass Cancellation',
            'description' => 'Cancel multiple bookings of a date / time slot at once',
        ],
    ];

    private array $guards = ['admin', 'tour_operator_staff'];

    /**
     * Run the migrations.
     *
     * Only creates the permission. It is intentionally left unassigned —
     * operators grant it per user/role via the permission management UI.
     */
    public function up(): void
    {
        foreach ($this->guards as $guard) {
            foreach ($this->permissions as $permission) {
                Permission::firstOrCreate(
                    ['name' => $permission['name'], 'guard_name' => $guard],
                    [
                        'display_name' => $permission['display_name'],
                        'description'  => $permission['description'],
                        'category'     => 'Sub Menu',
                        'created_from' => 'BACKEND',
                        'created_by'   => null,
                        'created_at'   => Carbon::now(),
                        'updated_at'   => Carbon::now(),
                    ]
                );
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $ids = DB::table('permissions')
            ->whereIn('name', array_column($this->permissions, 'name'))
            ->whereIn('guard_name', $this->guards)
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }
    }
};
