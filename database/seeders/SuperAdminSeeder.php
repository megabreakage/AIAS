<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('SUPER_ADMIN_EMAIL', 'sa@aias.system');
        $password = (string) env('SUPER_ADMIN_PASSWORD', 'password');
        $name = (string) env('SUPER_ADMIN_NAME', 'Super Admin');

        $nameParts = explode(' ', $name, 2);
        $firstName = $nameParts[0];
        $lastName = $nameParts[1] ?? 'Admin';

        $superAdmin = User::withoutEvents(function () use ($email, $password, $firstName, $lastName): User {
            return User::firstOrCreate(
                ['email' => $email],
                [
                    'identifier' => (string) Str::uuid(),
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'username' => Str::slug($firstName.'_'.$lastName.'_'.Str::random(4)),
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'country_code' => '+254',
                ],
            );
        });

        $superAdminRole = Role::on('central')
            ->where('name', 'super-admin')
            ->where('guard_name', 'api')
            ->first();

        if ($superAdminRole !== null && !$superAdmin->hasRole('super-admin', 'api')) {
            $superAdmin->assignRole($superAdminRole);
            $this->command->info("Assigned super-admin role to: {$email}");
        }

        $centralPermissions = [];
        foreach (config('permissions_map.central', []) as $module => $actions) {
            foreach ($actions as $action) {
                $centralPermissions[] = "{$module}.{$action}";
            }
        }

        if (!empty($centralPermissions)) {
            $permissions = Permission::on('central')
                ->where('guard_name', 'api')
                ->whereIn('name', $centralPermissions)
                ->get();

            if ($permissions->isNotEmpty()) {
                $superAdmin->syncPermissions($permissions);
            }
        }

        if ($superAdmin->wasRecentlyCreated) {
            $this->command->info("Created super admin: {$email}");
        } else {
            $this->command->line("Super admin already exists: {$email}");
        }
    }
}
