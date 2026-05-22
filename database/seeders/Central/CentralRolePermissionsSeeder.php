<?php

declare(strict_types=1);

namespace Database\Seeders\Central;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class CentralRolePermissionsSeeder extends Seeder
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
        ];

        foreach ($roles as $roleData) {
            $role = Role::on('central')->firstOrCreate(
                ['name' => $roleData['name'], 'guard_name' => $roleData['guard_name']],
                ['display_name' => $roleData['display_name'], 'description' => $roleData['description']],
            );

            if ($role->wasRecentlyCreated) {
                $this->command->info("Created central role: {$roleData['name']}");
            } else {
                $this->command->line("Central role already exists: {$roleData['name']}");
            }
        }
    }
}
