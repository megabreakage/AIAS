<?php

declare(strict_types=1);

namespace Database\Seeders\Central;

use App\Models\Central\SuperAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

        $superAdmin = SuperAdmin::withoutEvents(function () use ($email, $password, $firstName, $lastName): SuperAdmin {
            return SuperAdmin::on('central')->firstOrCreate(
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

        if (!$superAdmin->hasRole('super-admin')) {
            $superAdmin->assignRole('super-admin');
            $this->command->info("Assigned super-admin role to: {$email}");
        }

        if ($superAdmin->wasRecentlyCreated) {
            $this->command->info("Created super admin: {$email}");
        } else {
            $this->command->line("Super admin already exists: {$email}");
        }
    }
}
