<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Facades\Hash;

class PermissionsDemoSeeder extends Seeder
{
    /**
     * Create the initial roles and permissions.
     */
    public function run(): void
    {
        /*// Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // create permissions
        Permission::create(['guard_name' => 'tour_operator_staff', 'name' => 'edit articles']);
        Permission::create(['guard_name' => 'tour_operator_staff', 'name' => 'delete articles']);
        Permission::create(['guard_name' => 'tour_operator_staff', 'name' => 'publish articles']);
        Permission::create(['guard_name' => 'tour_operator_staff', 'name' => 'unpublish articles']);

        // create roles and assign existing permissions
        $role1 = Role::create(['guard_name' => 'tour_operator_staff', 'name' => 'writer']);
        $role1->givePermissionTo('edit articles');
        $role1->givePermissionTo('delete articles');

        $role2 = Role::create(['guard_name' => 'tour_operator_staff', 'name' => 'admin']);
        $role2->givePermissionTo('publish articles');
        $role2->givePermissionTo('unpublish articles');

        $role3 = Role::create(['guard_name' => 'tour_operator_staff', 'name' => 'Super-Admin']);
        // gets all permissions via Gate::before rule; see AuthServiceProvider

        // create demo users
        $user = \App\Models\Staff::create([
            'tour_operator_id' => 1,
            'first_name' => 'Example User',
            'last_name' => 'Example User',
            'email' => 'test@example.com',
            'password' => Hash::make("N@t!^3@dm!n"),
            'created_by' => '2',
            'phone_number' => 1234567890,
            
        ]);
        $user->assignRole($role1);

        $user = \App\Models\Staff::create([
            'tour_operator_id' => 1,
            'first_name' => 'Example Admin User',
            'last_name' => 'Example Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make("N@t!^3@dm!n"),
            'created_by' => '2',
            'phone_number' => 1234567890,
            
        ]);
        $user->assignRole($role2);

        $user = \App\Models\Staff::create([
            'tour_operator_id' => 1,
            'first_name' => 'Example Super-Admin User',
            'last_name' => 'Example Super-Admin User',
            'email' => 'superadmin@example.com',
            'password' => Hash::make("N@t!^3@dm!n"),
            'created_by' => '2',
            'phone_number' => 1234567890,
            
        ]);
        $user->assignRole($role3);*/
    }
}
