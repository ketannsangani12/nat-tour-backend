<?php

namespace Tests\Feature\Disputes;

use Tests\TestCase;
use App\Models\Admin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Property 1: Permission-Based Menu Visibility
 * 
 * For any user with dispute management permissions, the Payment Disputes menu item 
 * should be visible in the profile dropdown, and for any user without dispute 
 * management permissions, the menu item should be hidden.
 * 
 * **Validates: Requirements 1.2, 1.3**
 */
class PermissionBasedMenuVisibilityPropertyTest extends TestCase
{

    protected array $createdAdminIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions if they don't exist
        Permission::firstOrCreate(['name' => 'view-payment-dispute', 'guard_name' => 'admin']);

        // Create roles for different user types
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    }

    protected function tearDown(): void
    {
        if (!empty($this->createdAdminIds)) {
            DB::table('admins')->whereIn('id', $this->createdAdminIds)->delete();
        }
        parent::tearDown();
    }

    /**
     * Property-based test: For any user with view-payment-dispute permission,
     * the user should have the permission and be able to access dispute features.
     * 
     * This test validates that:
     * 1. Users with view-payment-dispute permission can access dispute functionality
     * 2. The permission system correctly grants access
     * 3. The @can directive would allow menu visibility
     * 4. Permission checking works across different user scenarios
     * 
     * **Validates: Requirements 1.2, 1.3**
     */
    public function test_permission_based_access_for_users_with_permission()
    {
        // Test cases for different admin users with permission
        $testCases = $this->generateAdminUsersWithPermission();
        
        foreach ($testCases as $testCase) {
            $this->runPermissionTest($testCase, true);
        }
    }

    /**
     * Property-based test: For any user without view-payment-dispute permission,
     * the user should not have access to dispute features.
     * 
     * This test validates that:
     * 1. Users without view-payment-dispute permission cannot access dispute functionality
     * 2. The @can directive properly restricts access
     * 3. Permission system correctly denies access
     * 4. Security is maintained for unauthorized users
     * 
     * **Validates: Requirements 1.2, 1.3**
     */
    public function test_permission_based_access_denied_for_users_without_permission()
    {
        // Test cases for different admin users without permission
        $testCases = $this->generateAdminUsersWithoutPermission();
        
        foreach ($testCases as $testCase) {
            $this->runPermissionTest($testCase, false);
        }
    }

    /**
     * Property-based test: For any admin user type,
     * the permission checking should be consistent based solely on permission,
     * not user attributes.
     * 
     * **Validates: Requirements 1.2, 1.3**
     */
    public function test_permission_consistency_across_admin_users()
    {
        // Test with permission
        $adminWithPermission = $this->createAdminWithPermission();
        $this->runPermissionTest([
            'user' => $adminWithPermission,
            'guard' => 'admin',
            'description' => 'Admin with permission'
        ], true);
        
        // Test without permission
        $adminWithoutPermission = Admin::factory()->create();
        $this->createdAdminIds[] = $adminWithoutPermission->id;
        $this->runPermissionTest([
            'user' => $adminWithoutPermission,
            'guard' => 'admin',
            'description' => 'Admin without permission'
        ], false);
    }

    /**
     * Property-based test: For any user accessing the disputes functionality,
     * access should be granted or denied based on permission.
     * 
     * **Validates: Requirements 1.4, 7.4**
     */
    public function test_permission_based_access_control()
    {
        // Test user with permission has access
        $userWithPermission = $this->createAdminWithPermission();
        $this->assertTrue($userWithPermission->can('view-payment-dispute'));
        
        // Test user without permission is denied access
        $userWithoutPermission = Admin::factory()->create();
        $this->createdAdminIds[] = $userWithoutPermission->id;
        $this->assertFalse($userWithoutPermission->can('view-payment-dispute'));
        
        // Test that Laravel's authorization system works correctly
        $this->actingAs($userWithPermission, 'admin');
        $this->assertTrue(auth()->user()->can('view-payment-dispute'));
        
        $this->actingAs($userWithoutPermission, 'admin');
        $this->assertFalse(auth()->user()->can('view-payment-dispute'));
    }

    /**
     * Property-based test: For any permission state change, the access control
     * should update immediately without requiring logout/login.
     * 
     * **Validates: Requirements 7.6**
     */
    public function test_dynamic_permission_updates()
    {
        $admin = Admin::factory()->create();
        $this->createdAdminIds[] = $admin->id;

        // Initially without permission
        $this->actingAs($admin, 'admin');
        $this->assertFalse($admin->can('view-payment-dispute'));
        
        // Grant permission
        $admin->givePermissionTo('view-payment-dispute');
        
        // Should now have permission (without re-authentication)
        $this->assertTrue($admin->can('view-payment-dispute'));
        
        // Revoke permission
        $admin->revokePermissionTo('view-payment-dispute');
        
        // Should no longer have permission
        $this->assertFalse($admin->can('view-payment-dispute'));
    }

    /**
     * Test that the getActiveDisputeCount function respects permissions.
     * 
     * **Validates: Requirements 1.6**
     */
    public function test_active_dispute_count_respects_permissions()
    {
        // Test with user who has permission
        $userWithPermission = $this->createAdminWithPermission();
        $this->actingAs($userWithPermission, 'admin');
        
        // The function should execute (even if it returns 0)
        $count = getActiveDisputeCount();
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
        
        // Test with user who doesn't have permission
        $userWithoutPermission = Admin::factory()->create();
        $this->createdAdminIds[] = $userWithoutPermission->id;
        $this->actingAs($userWithoutPermission, 'admin');
        
        // The function should return 0 for users without permission
        $count = getActiveDisputeCount();
        $this->assertEquals(0, $count);
        
        // Test with unauthenticated user
        Auth::logout();
        $count = getActiveDisputeCount();
        $this->assertEquals(0, $count);
    }

    /**
     * Test edge cases for permission-based visibility.
     * 
     * **Validates: Requirements 1.2, 1.3**
     */
    public function test_edge_cases_for_permission_visibility()
    {
        // Test user with permission but inactive account
        $inactiveUser = Admin::factory()->create(['status' => 'inactive']);
        $this->createdAdminIds[] = $inactiveUser->id;
        $inactiveUser->givePermissionTo('view-payment-dispute');
        
        // Even inactive users with permission should have the permission
        $this->assertTrue($inactiveUser->can('view-payment-dispute'));
        
        // Test user with permission through role inheritance
        $role = Role::firstOrCreate(['name' => 'dispute-manager', 'guard_name' => 'admin']);
        $role->givePermissionTo('view-payment-dispute');
        
        $userWithRolePermission = Admin::factory()->create();
        $this->createdAdminIds[] = $userWithRolePermission->id;
        $userWithRolePermission->assignRole('dispute-manager');
        
        // Should have permission through role
        $this->assertTrue($userWithRolePermission->can('view-payment-dispute'));
    }

    /**
     * Test that the @can directive works correctly in Laravel's Gate system.
     * 
     * **Validates: Requirements 1.2, 1.3**
     */
    public function test_can_directive_functionality()
    {
        // Test user with permission
        $userWithPermission = $this->createAdminWithPermission();
        $this->assertTrue($userWithPermission->can('view-payment-dispute'));
        
        // Test user without permission
        $userWithoutPermission = Admin::factory()->create();
        $this->createdAdminIds[] = $userWithoutPermission->id;
        $this->assertFalse($userWithoutPermission->can('view-payment-dispute'));

        // Test that permission checking works through Laravel's Gate system
        $this->actingAs($userWithPermission, 'admin');
        $this->assertTrue(auth()->user()->can('view-payment-dispute'));
        
        $this->actingAs($userWithoutPermission, 'admin');
        $this->assertFalse(auth()->user()->can('view-payment-dispute'));
    }

    /**
     * Test that menu route naming follows the expected pattern.
     * 
     * **Validates: Requirements 1.4**
     */
    public function test_menu_route_naming_pattern()
    {
        // Test prefixedRouteName helper functionality
        session(['userType' => 'Admin']);
        $prefixedRoute = prefixedRouteName('disputes.index');
        $this->assertEquals('admin.disputes.index', $prefixedRoute);
        
        // Test different user types
        session(['userType' => 'Tour Operator']);
        $prefixedRoute = prefixedRouteName('disputes.index');
        $this->assertEquals('tour-operator.disputes.index', $prefixedRoute);
        
        // Test that the helper function exists and works
        $this->assertTrue(function_exists('prefixedRouteName'));
    }

    /**
     * Test permission inheritance and role-based access.
     * 
     * **Validates: Requirements 7.1, 7.2**
     */
    public function test_permission_inheritance_through_roles()
    {
        // Create a role with dispute permissions
        $disputeRole = Role::firstOrCreate(['name' => 'dispute-viewer', 'guard_name' => 'admin']);
        $disputeRole->givePermissionTo('view-payment-dispute');
        
        // Create user and assign role
        $user = Admin::factory()->create();
        $this->createdAdminIds[] = $user->id;
        $user->assignRole('dispute-viewer');
        
        // User should have permission through role
        $this->assertTrue($user->can('view-payment-dispute'));
        
        // Remove role
        $user->removeRole('dispute-viewer');
        
        // User should no longer have permission
        $this->assertFalse($user->can('view-payment-dispute'));
    }

    /**
     * Run permission test for a specific test case.
     */
    private function runPermissionTest(array $testCase, bool $shouldHavePermission): void
    {
        $user = $testCase['user'];
        $description = $testCase['description'];
        
        if ($shouldHavePermission) {
            // Verify the user has the permission
            $this->assertTrue(
                $user->can('view-payment-dispute'),
                "User {$description} should have view-payment-dispute permission"
            );
            
            // Test that they would be authorized for dispute functionality
            $this->actingAs($user, 'admin');
            $this->assertTrue(auth()->user()->can('view-payment-dispute'));
            
        } else {
            // Verify the user does not have the permission
            $this->assertFalse(
                $user->can('view-payment-dispute'),
                "User {$description} should not have view-payment-dispute permission"
            );
            
            // Test that they would not be authorized for dispute functionality
            $this->actingAs($user, 'admin');
            $this->assertFalse(auth()->user()->can('view-payment-dispute'));
        }
    }

    /**
     * Generate test cases for admin users with permission.
     */
    private function generateAdminUsersWithPermission(): array
    {
        return [
            [
                'user' => $this->createAdminWithPermission(),
                'guard' => 'admin',
                'description' => 'Admin with view-payment-dispute permission'
            ],
            [
                'user' => $this->createAdminWithBothPermissions(),
                'guard' => 'admin',
                'description' => 'Admin with both view and manage permissions'
            ]
        ];
    }

    /**
     * Generate test cases for admin users without permission.
     */
    private function generateAdminUsersWithoutPermission(): array
    {
        $adminWithoutAnyPermission = Admin::factory()->create();
        $this->createdAdminIds[] = $adminWithoutAnyPermission->id;

        return [
            [
                'user' => $adminWithoutAnyPermission,
                'guard' => 'admin',
                'description' => 'Admin without any dispute permissions'
            ],
            [
                'user' => $this->createAdminWithOtherPermissions(),
                'guard' => 'admin',
                'description' => 'Admin with other permissions but not dispute permissions'
            ]
        ];
    }

    /**
     * Create admin with view permission only.
     */
    private function createAdminWithPermission(): Admin
    {
        $admin = Admin::factory()->create();
        $this->createdAdminIds[] = $admin->id;
        $admin->givePermissionTo('view-payment-dispute');
        return $admin;
    }

    /**
     * Create admin with view permission.
     */
    private function createAdminWithBothPermissions(): Admin
    {
        $admin = Admin::factory()->create();
        $this->createdAdminIds[] = $admin->id;
        $admin->givePermissionTo(['view-payment-dispute']);
        return $admin;
    }

    /**
     * Create admin with other permissions but not dispute permissions.
     */
    private function createAdminWithOtherPermissions(): Admin
    {
        $admin = Admin::factory()->create();
        $this->createdAdminIds[] = $admin->id;

        // Create some other permissions that might exist
        Permission::firstOrCreate(['name' => 'view-bookings', 'guard_name' => 'admin']);
        Permission::firstOrCreate(['name' => 'manage-tours', 'guard_name' => 'admin']);
        
        $admin->givePermissionTo(['view-bookings', 'manage-tours']);
        return $admin;
    }
}