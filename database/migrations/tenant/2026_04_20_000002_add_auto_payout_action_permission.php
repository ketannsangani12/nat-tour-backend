<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
	public function up(): void
	{
		$now = now();
		$guards = ['admin'];

		foreach ($guards as $guard) {
			$exists = DB::table('permissions')
				->where('name', 'auto-payout')
				->where('guard_name', $guard)
				->exists();

			if (!$exists) {
				DB::table('permissions')->insert([
					'name' => 'auto-payout',
					'guard_name' => $guard,
					'display_name' => 'Auto Payouts',
					'category' => 'Action',
					'created_from' => 'BACKEND',
					'created_at' => $now,
					'updated_at' => $now,
				]);
			}
		}

		// Grant to existing admin and super-admin admin-guard roles so current
		// admins are not locked out of the auto-payout page after this deploys.
		// Operators can revoke from the permission UI later as desired.
		$adminPermissionId = DB::table('permissions')
			->where('name', 'auto-payout')
			->where('guard_name', 'admin')
			->value('id');

		if ($adminPermissionId) {
			$roleIds = DB::table('roles')
				->whereIn('name', ['admin', 'super-admin'])
				->where('guard_name', 'admin')
				->pluck('id');

			foreach ($roleIds as $roleId) {
				$already = DB::table('role_has_permissions')
					->where('permission_id', $adminPermissionId)
					->where('role_id', $roleId)
					->exists();

				if (!$already) {
					DB::table('role_has_permissions')->insert([
						'permission_id' => $adminPermissionId,
						'role_id' => $roleId,
					]);
				}
			}
		}
	}

	public function down(): void
	{
		$permissionIds = DB::table('permissions')
			->where('name', 'auto-payout')
			->pluck('id');

		if ($permissionIds->isNotEmpty()) {
			DB::table('role_has_permissions')
				->whereIn('permission_id', $permissionIds)
				->delete();

			DB::table('model_has_permissions')
				->whereIn('permission_id', $permissionIds)
				->delete();

			DB::table('permissions')
				->whereIn('id', $permissionIds)
				->delete();
		}
	}
};
