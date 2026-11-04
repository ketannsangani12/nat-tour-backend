<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Permissions for the Tour Slots Bulk Manager, added for the
     * "admin" and "tour_operator_staff" guards only (not affiliates).
     */
    private array $permissions = [
        [
            'name' => 'tour-slots-bulk-manager',
            'display_name' => 'Access Tour Slots Bulk Manager',
            'description' => 'Access the Tour Slots Bulk Manager',
        ],
        [
            'name' => 'tour-slots-bulk-create-slots',
            'display_name' => 'Create Slots',
            'description' => 'Bulk create new slots across a date range',
        ],
        [
            'name' => 'tour-slots-bulk-status-visibility',
            'display_name' => 'Status & Visibility',
            'description' => 'Bulk update slot status and visibility',
        ],
        [
            'name' => 'tour-slots-bulk-update-seats',
            'display_name' => 'Update Seats',
            'description' => 'Bulk update per-slot seat counts',
        ],
        [
            'name' => 'tour-slots-bulk-convert-times',
            'display_name' => 'Convert Times',
            'description' => 'Bulk convert slot times and move bookings',
        ],
    ];

    private array $guards = ['admin', 'tour_operator_staff'];

    /**
     * Run the migrations.
     *
     * Only creates the permissions. They are intentionally left unassigned —
     * operators grant them per user/role via the permission management UI.
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
                        'category'     => 'Tour Slots Bulk Manager',
                        'created_from' => 'BACKEND',
                        'created_by'   => null,
                        'created_at'   => Carbon::now(),
                        'updated_at'   => Carbon::now(),
                    ]
                );
            }
        }
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
