<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * The 'booking-after-duration' permission already exists for the
     * "affiliate" guard. It was never created for "admin" / "tour_operator_staff",
     * so admins/managers can never hold it themselves and it's filtered out of
     * the affiliate permission editor for them (AffiliateController::affiliateUserPermissionEdit
     * only shows affiliate-guard permissions whose name the editing admin/manager
     * already has on their own account).
     */
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
            Permission::firstOrCreate(
                ['name' => 'booking-after-duration', 'guard_name' => $guard],
                [
                    'display_name' => 'Booking After Duration',
                    'description'  => 'Booking After Duration',
                    'category'     => 'Action',
                    'created_from' => 'BACKEND',
                    'created_by'   => null,
                    'created_at'   => Carbon::now(),
                    'updated_at'   => Carbon::now(),
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $ids = DB::table('permissions')
            ->where('name', 'booking-after-duration')
            ->whereIn('guard_name', $this->guards)
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }
    }
};
