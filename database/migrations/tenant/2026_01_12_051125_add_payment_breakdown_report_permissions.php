<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create payment breakdown report permissions
        $permissions = [
            [
                'name' => 'view-payment-breakdown-report',
                'guard_name' => 'admin',
                'display_name' => 'View Payment Breakdown Report',
                'description' => 'View payment breakdown reports and analytics',
                'category' => 'Report',
                'created_from' => 'BACKEND',
                'created_by' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
            [
                'name' => 'export-payment-breakdown-report',
                'guard_name' => 'admin',
                'display_name' => 'Export Payment Breakdown Report',
                'description' => 'Export payment breakdown reports to PDF, CSV, and Excel formats',
                'category' => 'Report',
                'created_from' => 'BACKEND',
                'created_by' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
            [
                'name' => 'manage-payment-breakdown-filters',
                'guard_name' => 'admin',
                'display_name' => 'Manage Payment Breakdown Filters',
                'description' => 'Manage and reset payment breakdown report filters',
                'category' => 'Report',
                'created_from' => 'BACKEND',
                'created_by' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
            [
                'name' => 'view-payment-breakdown-report',
                'guard_name' => 'tour_operator_staff',
                'display_name' => 'View Payment Breakdown Report',
                'description' => 'View payment breakdown reports and analytics',
                'category' => 'Report',
                'created_from' => 'BACKEND',
                'created_by' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
            [
                'name' => 'export-payment-breakdown-report',
                'guard_name' => 'tour_operator_staff',
                'display_name' => 'Export Payment Breakdown Report',
                'description' => 'Export payment breakdown reports to PDF, CSV, and Excel formats',
                'category' => 'Report',
                'created_from' => 'BACKEND',
                'created_by' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
            [
                'name' => 'manage-payment-breakdown-filters',
                'guard_name' => 'tour_operator_staff',
                'display_name' => 'Manage Payment Breakdown Filters',
                'description' => 'Manage and reset payment breakdown report filters',
                'category' => 'Report',
                'created_from' => 'BACKEND',
                'created_by' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]
        ];

        foreach ($permissions as $permissionData) {
            Permission::firstOrCreate(
                [
                    'name' => $permissionData['name'],
                    'guard_name' => $permissionData['guard_name']
                ],
                [
                    'display_name' => $permissionData['display_name'],
                    'description' => $permissionData['description'],
                    'category' => $permissionData['category'],
                    'created_from' => $permissionData['created_from']
                ]
            );
        }

        // Assign permissions to existing roles
        // $this->assignPermissionsToRoles();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove payment breakdown report permissions
        $permissionNames = [
            'view-payment-breakdown-report',
            'export-payment-breakdown-report',
            'manage-payment-breakdown-filters'
        ];

        foreach ($permissionNames as $permissionName) {
            $permission = Permission::where('name', $permissionName)
                ->where('guard_name', 'tour_operator_staff')
                ->first();
            
            if ($permission) {
                $permission->delete();
            }
        }
    }

    /**
     * Assign permissions to existing roles
     */
    private function assignPermissionsToRoles(): void
    {
        // Get roles that should have access to payment breakdown reports
        $adminRoles = ['admin'];
        $staffRoles = ['staff', 'manager', 'tour-guide'];

        // Admin roles get all permissions
        foreach ($adminRoles as $roleName) {
            $role = Role::where('name', $roleName)
                ->where('guard_name', 'admin')
                ->first();

            if ($role) {
                $role->givePermissionTo([
                    'view-payment-breakdown-report',
                    'export-payment-breakdown-report',
                    'manage-payment-breakdown-filters'
                ]);
            }
        }

        // Staff roles get view and export permissions only
        foreach ($staffRoles as $roleName) {
            $role = Role::where('name', $roleName)
                ->where('guard_name', 'tour_operator_staff')
                ->first();

            if ($role) {
                $role->givePermissionTo([
                    'view-payment-breakdown-report',
                    'export-payment-breakdown-report'
                ]);
            }
        }
    }
};