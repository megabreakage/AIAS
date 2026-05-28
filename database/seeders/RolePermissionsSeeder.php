<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
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
                'guard_name' => 'api',
            ],
            [
                'name' => 'admin',
                'display_name' => 'Admin',
                'description' => 'Has access to standard admin features',
                'guard_name' => 'api',
            ],
            [
                'name' => 'tenant',
                'display_name' => 'Tenant Admin',
                'description' => 'Has access to tenant-specific admin features',
                'guard_name' => 'api',
            ],
            [
                'name' => 'auditor',
                'display_name' => 'Auditor',
                'description' => 'Has access to Audit user features',
                'guard_name' => 'api',
            ],
            [
                'name' => 'user',
                'display_name' => 'User',
                'description' => 'Has access to standard user features',
                'guard_name' => 'api',
            ],
        ];

        // Create super-admin role under api guard
        Role::on('central')->firstOrCreate(
            ['name' => 'super-admin', 'guard_name' => 'api'],
            ['display_name' => 'Super Admin', 'description' => 'Has access to all system features and settings'],
        );

        foreach ($roles as $roleData) {
            $role = Role::on('central')->firstOrCreate(
                ['name' => $roleData['name'], 'guard_name' => $roleData['guard_name']],
                ['display_name' => $roleData['display_name'], 'description' => $roleData['description']],
            );

            if ($role?->name !== 'tenant') {
                $this->syncCentralPermissions($role, $roleData['guard_name']);
            }

            if ($role?->name === 'tenant') {
                $this->syncTenantAdminPermissions($role, $roleData['guard_name']);
            }

            if ($role?->wasRecentlyCreated) {
                $this->command->info("Created central role: {$roleData['name']}");
            } else {
                $this->command->line("Central role already exists: {$roleData['name']}");
            }
        }
    }

    /**
     * Flatten a nested module => [actions] map into "module.action" permission names,
     * create any missing permissions, and return the flat list.
     *
     * @param  array<string, list<string>>  $map
     * @return list<string>
     */
    private function resolvePermissions(array $map, string $guardName = 'api'): array
    {
        $names = [];

        foreach ($map as $module => $actions) {
            foreach ($actions as $action) {
                $names[] = "{$module}.{$action}";
            }
        }

        foreach ($names as $name) {
            Permission::on('central')->firstOrCreate(
                ['name' => $name, 'guard_name' => $guardName],
            );
        }

        return $names;
    }

    /**
     * Assigns Central DB Users Permissions
     */
    private function syncCentralPermissions(Role $role, string $guardName = 'api'): void
    {
        $centralPermissions = $this->resolvePermissions(
            config('permissions_map.central', []),
            $guardName,
        );

        if (!empty($centralPermissions)) {
            $role->syncPermissions($centralPermissions);
        }
    }

    /**
     * Assigns TenantAdmin Permissions
     */
    private function syncTenantAdminPermissions(Role $role, string $guardName = 'api'): void
    {
        $tenantPermissions = $this->resolvePermissions(
            config('permissions_map.tenants', []),
            $guardName,
        );

        if (!empty($tenantPermissions)) {
            $role->syncPermissions($tenantPermissions);
        }
    }
}
