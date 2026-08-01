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

    /**
     * Every permission the command references, using the `_` separator that
     * Shield 4 generates. Keep this in step with SetupRolePermissions::getDefaultPermissions().
     */
    private const PERMISSIONS = [
        // Links
        'view_any_link', 'view_link', 'create_link', 'update_link', 'delete_link', 'delete_any_link',
        // Link groups
        'view_any_link_group', 'view_link_group', 'create_link_group', 'update_link_group',
        'delete_link_group', 'delete_any_link_group',
        // API keys
        'view_any_api_key', 'view_api_key', 'create_api_key', 'update_api_key', 'delete_api_key',
        // Reports
        'view_any_report', 'view_report', 'create_report', 'update_report', 'delete_report', 'delete_any_report',
        // Widgets and pages - auto-included for every role
        'widget_OverviewStatsWidget', 'widget_LinkHealthWidget', 'widget_GeographicStatsWidget',
        'widget_ClickTrendsChart', 'widget_TopLinksWidget',
        'page_UserProfile', 'page_CsvImport',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin']);
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'user']);

        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $this->forgetPermissionCache();
    }

    private function forgetPermissionCache(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function test_setup_command_assigns_default_permissions(): void
    {
        $this->artisan('roles:setup')
            ->expectsOutput('Setting up role permissions...')
            ->expectsOutput('Role permissions setup complete!')
            ->assertExitCode(0);

        $this->forgetPermissionCache();

        $adminRole = Role::findByName('admin');
        $this->assertTrue($adminRole->hasPermissionTo('view_link'));
        $this->assertTrue($adminRole->hasPermissionTo('create_link'));
        $this->assertTrue($adminRole->hasPermissionTo('view_any_api_key'));
        $this->assertTrue($adminRole->hasPermissionTo('view_any_link_group'));
        $this->assertTrue($adminRole->hasPermissionTo('view_any_report'));

        $userRole = Role::findByName('user');
        $this->assertTrue($userRole->hasPermissionTo('view_link'));
        $this->assertTrue($userRole->hasPermissionTo('create_link'));
        $this->assertTrue($userRole->hasPermissionTo('view_any_api_key'));

        // Admin-only: bulk deletion and reports are not part of the user defaults
        $this->assertFalse($userRole->hasPermissionTo('delete_any_link'));
        $this->assertFalse($userRole->hasPermissionTo('view_any_report'));
        $this->assertFalse($userRole->hasPermissionTo('create_link_group'));
    }

    public function test_page_and_widget_permissions_are_granted_to_every_role(): void
    {
        $this->artisan('roles:setup')->assertExitCode(0);

        $this->forgetPermissionCache();

        foreach (['admin', 'user'] as $roleName) {
            $role = Role::findByName($roleName);

            $this->assertTrue($role->hasPermissionTo('widget_OverviewStatsWidget'));
            $this->assertTrue($role->hasPermissionTo('widget_LinkHealthWidget'));
            $this->assertTrue($role->hasPermissionTo('page_UserProfile'));
            $this->assertTrue($role->hasPermissionTo('page_CsvImport'));
        }
    }

    public function test_setup_command_with_specific_role(): void
    {
        $this->artisan('roles:setup', ['--role' => ['admin']])
            ->expectsOutputToContain('permissions to admin role')
            ->assertExitCode(0);

        $this->forgetPermissionCache();

        // Only the admin role should have been configured
        $this->assertTrue(Role::findByName('admin')->hasPermissionTo('view_link'));
        $this->assertFalse(Role::findByName('user')->hasPermissionTo('view_link'));
    }

    public function test_setup_command_is_additive_by_default(): void
    {
        $adminRole = Role::findByName('admin');
        $adminRole->givePermissionTo('view_link');

        // A permission outside the defaults must survive a default (non-reset) run
        Permission::firstOrCreate(['name' => 'custom_extra_permission']);
        $adminRole->givePermissionTo('custom_extra_permission');

        $this->forgetPermissionCache();

        $this->artisan('roles:setup')->assertExitCode(0);

        $this->forgetPermissionCache();

        $adminRole = Role::findByName('admin');
        $this->assertTrue($adminRole->hasPermissionTo('custom_extra_permission'));
        $this->assertTrue($adminRole->hasPermissionTo('view_link'));
        $this->assertTrue($adminRole->hasPermissionTo('view_any_api_key'));
    }

    public function test_setup_command_with_reset_option(): void
    {
        $adminRole = Role::findByName('admin');

        Permission::firstOrCreate(['name' => 'custom_extra_permission']);
        $adminRole->givePermissionTo('custom_extra_permission');

        $this->forgetPermissionCache();

        $this->artisan('roles:setup', ['--reset' => true])
            ->expectsOutputToContain('Reset and set admin role with')
            ->assertExitCode(0);

        $this->forgetPermissionCache();

        $adminRole = Role::findByName('admin');

        // Defaults reapplied, non-default permission dropped
        $this->assertTrue($adminRole->hasPermissionTo('view_link'));
        $this->assertFalse($adminRole->hasPermissionTo('custom_extra_permission'));
    }

    public function test_command_fails_when_no_permissions_exist(): void
    {
        Permission::query()->delete();
        $this->forgetPermissionCache();

        $this->artisan('roles:setup')
            ->expectsOutput('No permissions found! Please run "php artisan shield:generate --all" first.')
            ->assertExitCode(1);
    }

    public function test_command_creates_a_missing_role(): void
    {
        Role::findByName('admin')->delete();
        $this->forgetPermissionCache();

        $this->artisan('roles:setup')
            ->expectsOutput("Created 'admin' role")
            ->assertExitCode(0);

        $this->forgetPermissionCache();

        $adminRole = Role::findByName('admin');
        $this->assertTrue($adminRole->hasPermissionTo('view_link'));
    }

    public function test_command_handles_missing_permissions_gracefully(): void
    {
        // A permission from the hardcoded defaults, not the dynamic page/widget lookup
        Permission::where('name', 'delete_any_report')->delete();
        $this->forgetPermissionCache();

        $this->artisan('roles:setup')
            ->expectsOutputToContain("Some permissions don't exist for admin")
            ->assertExitCode(0);

        $this->forgetPermissionCache();

        // The remaining permissions are still assigned
        $adminRole = Role::findByName('admin');
        $this->assertTrue($adminRole->hasPermissionTo('view_link'));
        $this->assertTrue($adminRole->hasPermissionTo('view_report'));

        $this->assertFalse(Permission::where('name', 'delete_any_report')->exists());
    }

    public function test_command_displays_role_summary(): void
    {
        $this->artisan('roles:setup')
            ->expectsOutput('Summary:')
            ->expectsOutputToContain('permissions')
            ->assertExitCode(0);
    }
}
