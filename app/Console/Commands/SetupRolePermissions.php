<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SetupRolePermissions extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'roles:setup
                            {--reset : Reset all role permissions before setting up defaults (destructive)}
                            {--role=* : Only setup specific roles (admin, user)}';

    /**
     * The console command description.
     */
    protected $description = 'Set up default permissions for roles (additive — won\'t remove existing permissions unless --reset is used)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Setting up role permissions...');

        // Check if Shield permissions exist
        if (Permission::count() === 0) {
            $this->error('No permissions found! Please run "php artisan shield:generate --all" first.');

            return 1;
        }

        $rolesToSetup = $this->option('role');
        if (empty($rolesToSetup)) {
            $rolesToSetup = ['admin', 'user'];
        }

        $isReset = $this->option('reset');

        foreach ($rolesToSetup as $roleName) {
            $this->setupRolePermissions($roleName, $isReset);
        }

        $this->info('Role permissions setup complete!');
        $this->newLine();
        $this->info('Summary:');
        $this->displayRoleSummary();

        return 0;
    }

    /**
     * Set up permissions for a specific role
     */
    private function setupRolePermissions(string $roleName, bool $reset): void
    {
        try {
            $role = Role::findByName($roleName);
        } catch (\Exception) {
            $role = Role::create(['name' => $roleName, 'guard_name' => 'web']);
            $this->info("Created '{$roleName}' role");
        }

        $permissions = $this->getDefaultPermissions($roleName);

        if (empty($permissions)) {
            $this->warn("No default permissions defined for '{$roleName}' role");

            return;
        }

        // Filter to only existing permissions
        $defaultPermissions = Permission::whereIn('name', $permissions)->pluck('name')->toArray();
        $missingPermissions = array_diff($permissions, $defaultPermissions);

        if (! empty($missingPermissions)) {
            $this->warn("Some permissions don't exist for {$roleName}: ".implode(', ', $missingPermissions));
        }

        if ($reset) {
            $role->syncPermissions($defaultPermissions);
            $this->info("Reset and set {$roleName} role with ".count($defaultPermissions).' permissions');
        } else {
            // Additive: only add permissions the role doesn't already have
            $existing = $role->permissions->pluck('name')->toArray();
            $toAdd = array_diff($defaultPermissions, $existing);

            if (empty($toAdd)) {
                $this->info("{$roleName} role already has all default permissions (".count($existing).' total)');
            } else {
                $role->givePermissionTo($toAdd);
                $this->info("Added ".count($toAdd)." permissions to {$roleName} role (now ".($role->permissions()->count()).' total)');
            }
        }
    }

    /**
     * Get default permissions for each role.
     * All page_ and widget_ permissions are auto-included for both roles.
     */
    private function getDefaultPermissions(string $roleName): array
    {
        // All page and widget permissions are included for all roles
        $pageAndWidgetPermissions = Permission::where('name', 'like', 'page_%')
            ->orWhere('name', 'like', 'widget_%')
            ->pluck('name')
            ->toArray();

        $rolePermissions = match ($roleName) {
            'admin' => [
                // Link Management (Full Access)
                'view_any_link',
                'view_link',
                'create_link',
                'update_link',
                'delete_link',
                'delete_any_link',

                // Link Groups (Full Access)
                'view_any_link_group',
                'view_link_group',
                'create_link_group',
                'update_link_group',
                'delete_link_group',
                'delete_any_link_group',

                // API Keys
                'view_any_api_key',
                'view_api_key',
                'create_api_key',
                'update_api_key',
                'delete_api_key',

                // Reports (Full Access)
                'view_any_report',
                'view_report',
                'create_report',
                'update_report',
                'delete_report',
                'delete_any_report',
            ],

            'user' => [
                // Basic Link Access
                'view_any_link',
                'view_link',
                'create_link',
                'update_link',
                'delete_link',

                // View Groups (for categorization)
                'view_any_link_group',
                'view_link_group',

                // Basic API Access
                'view_any_api_key',
                'view_api_key',
                'create_api_key',
                'update_api_key',
                'delete_api_key',
            ],

            default => []
        };

        return array_merge($rolePermissions, $pageAndWidgetPermissions);
    }

    /**
     * Display summary of role permissions
     */
    private function displayRoleSummary(): void
    {
        $roles = ['super_admin', 'admin', 'user'];

        foreach ($roles as $roleName) {
            try {
                $role = Role::findByName($roleName);
            } catch (\Exception) {
                continue;
            }

            $permissionCount = $role->permissions->count();

            $description = match ($roleName) {
                'super_admin' => 'Unrestricted access to everything (automatic)',
                'admin' => 'Full link management + CSV import + dashboard access',
                'user' => 'Basic link management + limited dashboard (no CSV import)',
                default => 'Unknown role'
            };

            $this->line("  {$roleName}: {$permissionCount} permissions - {$description}");
        }

        $this->newLine();
        $this->info('Tip: You can customize these permissions anytime in the admin panel at Settings > Roles');
    }
}
