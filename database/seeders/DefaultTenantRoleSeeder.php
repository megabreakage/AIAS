<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DefaultTenantRoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name'         => 'tenant-admin',
                'display_name' => 'Tenant Admin',
                'description'  => 'Has access to the specific tenant database features and settings',
                'guard_name'   => 'api',
            ],
            [
                'name'         => 'auditor',
                'display_name' => 'Auditor',
                'description'  => 'Can create and manage audit engagements and findings',
                'guard_name'   => 'api',
            ],
            [
                'name'         => 'viewer',
                'display_name' => 'Viewer',
                'description'  => 'Read-only access to audit data',
                'guard_name'   => 'api',
            ],
        ];

        foreach ($roles as $roleData) {
            $role = Role::firstOrCreate(
                ['name' => $roleData['name'], 'guard_name' => $roleData['guard_name']],
                ['display_name' => $roleData['display_name'], 'description' => $roleData['description']],
            );

            if ($role->wasRecentlyCreated) {
                $this->command->info("Created tenant role: {$roleData['name']}");
            } else {
                $this->command->line("Tenant role already exists: {$roleData['name']}");
            }
        }
    }
}
