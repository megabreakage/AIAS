<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'super-admin',
                'display_name' => 'Super Admin',
                'description' => 'Has access to all system features and settings',
                'guard_name' => 'super_admin',
            ],
            [
                'name' => 'admin',
                'display_name' => 'Admin',
                'description' => 'Has access to standard admin features',
                'guard_name' => 'api',
            ],
            [
                'name' => 'tenant-admin',
                'display_name' => 'Tenant Admin',
                'description' => 'Has access to tenant-specific admin features',
                'guard_name' => 'api',
            ],
            [
                'name' => 'user',
                'display_name' => 'User',
                'description' => 'Has access to standard user features',
                'guard_name' => 'api',
            ],
        ];

        foreach ($roles as $roleData) {
            $role = Role::on('central')->firstOrCreate(
                ['name' => $roleData['name'], 'guard_name' => $roleData['guard_name']],
                ['display_name' => $roleData['display_name'], 'description' => $roleData['description']],
            );

            if ($role && $role->name !== 'tenant-admin') {
                $this->syncCentralPermissions($role);
            }

            if ($role && $role->name === 'tenant-admin') {
                $this->syncTenantAdminPermissions($role);
            }

            if ($role->wasRecentlyCreated) {
                $this->command->info("Created central role: {$roleData['name']}");
            } else {
                $this->command->line("Central role already exists: {$roleData['name']}");
            }
        }
    }

    /**
     * Assigns Central DB Users Permissions
     */
    private function syncCentralPermissions(Role $role): void
    {
        $centralPermissions = config('permissions_map.central', []);

        if (!empty($centralPermissions)) {
            $role->syncPermissions($centralPermissions);
        }
    }

    /**
     * Assigns TenantAdmin Permissions
     */
    private function syncTenantAdminPermissions(Role $role): void
    {
        $tenantPermissions = config('permissions_map.tenants', []);

        if (!empty($tenantPermissions)) {
            $role->syncPermissions($tenantPermissions);
        }
    }
}
