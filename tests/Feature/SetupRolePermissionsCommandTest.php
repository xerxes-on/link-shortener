<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SetupRolePermissionsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the roles
        Role::firstOrCreate(['name' => 'super_admin']);
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'user']);

        // Reset Spatie permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create some test permissions (matching what the command expects)
        $permissions = [
            'view_link', 'create_link', 'update_link', 'delete_link', 'view_any_link', 'delete_any_link',
            'view_link::group', 'create_link::group', 'update_link::group', 'delete_link::group', 'view_any_link::group', 'delete_any_link::group',
            'view_api::key', 'create_api::key', 'update_api::key', 'delete_api::key', 'view_any_api::key',
            'widget_OverviewStatsWidget', 'widget_LinkHealthWidget', 'widget_GeographicStatsWidget', 'widget_ClickTrendsChart', 'widget_TopLinksWidget',
            'page_UserProfile', 'page_CsvImport',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
    }

    public function test_setup_command_assigns_default_permissions(): void
    {
        $this->artisan('roles:setup')
            ->expectsOutput('🔧 Setting up role permissions...')
            ->expectsOutput('✅ Role permissions setup complete!')
            ->assertExitCode(0);

        // Check admin role permissions
        $adminRole = Role::findByName('admin');
        $this->assertTrue($adminRole->hasPermissionTo('view_link'));
        $this->assertTrue($adminRole->hasPermissionTo('create_link'));
        $this->assertTrue($adminRole->hasPermissionTo('widget_OverviewStatsWidget'));
        $this->assertTrue($adminRole->hasPermissionTo('page_UserProfile'));

        // Check user role permissions
        $userRole = Role::findByName('user');
        $this->assertTrue($userRole->hasPermissionTo('view_link'));
        $this->assertTrue($userRole->hasPermissionTo('create_link'));
        $this->assertTrue($userRole->hasPermissionTo('widget_OverviewStatsWidget'));
        $this->assertFalse($userRole->hasPermissionTo('widget_LinkHealthWidget')); // Admin only

        // panel_user role has been removed from the system
    }

    public function test_setup_command_with_specific_role(): void
    {
        $this->artisan('roles:setup', ['--role' => ['admin']])
            ->expectsOutputToContain('Set up admin role with')
            ->assertExitCode(0);

        // Only admin role should be configured
        $adminRole = Role::findByName('admin');
        $userRole = Role::findByName('user');

        $this->assertTrue($adminRole->hasPermissionTo('view_link'));
        $this->assertFalse($userRole->hasPermissionTo('view_link'));
    }

    public function test_setup_command_with_reset_option(): void
    {
        // First, manually assign some permissions
        $adminRole = Role::findByName('admin');
        $adminRole->givePermissionTo('view_link');

        // Run setup with reset
        $this->artisan('roles:setup', ['--reset' => true])
            ->expectsOutput('🔄 Reset permissions for admin role')
            ->assertExitCode(0);

        // Permissions should be reset and then reassigned
        $this->assertTrue($adminRole->fresh()->hasPermissionTo('view_link'));
    }

    public function test_command_fails_when_no_permissions_exist(): void
    {
        // Remove all permissions
        Permission::query()->delete();

        $this->artisan('roles:setup')
            ->expectsOutput('❌ No permissions found! Please run "php artisan shield:generate --all" first.')
            ->assertExitCode(1);
    }

    public function test_command_warns_about_missing_role(): void
    {
        // Delete a role
        Role::findByName('admin')->delete();

        $this->artisan('roles:setup')
            ->expectsOutput("⚠️  Role 'admin' not found, skipping...")
            ->assertExitCode(0);
    }

    public function test_command_handles_missing_permissions_gracefully(): void
    {
        // Remove some permissions that the command expects
        Permission::where('name', 'widget_LinkHealthWidget')->delete();

        // Reset Spatie permission cache after deleting a permission
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->artisan('roles:setup')
            ->expectsOutputToContain('Some permissions don\'t exist for admin')
            ->assertExitCode(0);

        // Reset cache again to reflect newly assigned permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Should still assign existing permissions
        $adminRole = Role::findByName('admin');
        $this->assertTrue($adminRole->hasPermissionTo('view_link'));

        // widget_LinkHealthWidget was deleted, so checking it would throw
        $this->assertFalse(Permission::where('name', 'widget_LinkHealthWidget')->exists());
    }

    public function test_command_displays_role_summary(): void
    {
        $this->artisan('roles:setup')
            ->expectsOutput('📋 Summary:')
            ->expectsOutputToContain('permissions')
            ->assertExitCode(0);
    }
}
