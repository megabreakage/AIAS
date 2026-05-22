<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class TenantRolePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'tenant-admin',
                'display_name' => 'Tenant Admin',
                'description' => 'Has access to the specific tenant database features and settings',
                'guard_name' => 'api',
            ],
            [
                'name' => 'auditor',
                'display_name' => 'Auditor',
                'description' => 'Can create and manage audit engagements and findings',
                'guard_name' => 'api',
            ],
            [
                'name' => 'client',
                'display_name' => 'Client',
                'description' => 'Can access client-specific features and data',
                'guard_name' => 'api',
            ],
            [
                'name' => 'viewer',
                'display_name' => 'Viewer',
                'description' => 'Read-only access to audit data',
                'guard_name' => 'api',
            ],
        ];

        $permissions = config('permissions_map.tenants', []);

        foreach ($roles as $roleData) {
            $role = Role::firstOrCreate(
                ['name' => $roleData['name'], 'guard_name' => $roleData['guard_name']],
                ['display_name' => $roleData['display_name'], 'description' => $roleData['description']],
            );

            if ($role) {
                $role->syncPermissions($permissions);
            } else {
                $this->command->error("Failed to create central role: {$roleData['name']}");

                continue;
            }

            if ($role->wasRecentlyCreated) {
                $this->command->info("Created tenant role: {$roleData['name']}");
            } else {
                $this->command->line("Tenant role already exists: {$roleData['name']}");
            }
        }
    }
}
