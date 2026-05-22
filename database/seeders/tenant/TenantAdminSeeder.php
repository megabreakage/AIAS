<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Models\Central\Tenant;
use App\Models\User;
use App\Notifications\TenantAdminWelcomeNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class TenantAdminSeeder extends Seeder
{
    public function run(): void
    {
        $tenantDomain = tenant()?->domain ?? tenant()?->domains?->first()?->domain ?? 'tenant.localhost';
        $email = (string) env('TEST_TENANT_ADMIN_EMAIL', "admin@{$tenantDomain}");
        $password = (string) env('TEST_TENANT_ADMIN_PASSWORD', 'password');
        $firstName = 'Tenant';
        $lastName = 'Owner';

        $admin = User::withoutEvents(function () use ($email, $password, $firstName, $lastName): User {
            return User::firstOrCreate(
                ['email' => $email],
                [
                    'identifier' => (string) Str::uuid(),
                    'title' => null,
                    'first_name' => $firstName,
                    'middle_name' => null,
                    'last_name' => $lastName,
                    'username' => 'tenant_owner_'.Str::random(4),
                    'email_verified_at' => now(),
                    'country_code' => '+254',
                    'phone' => null,
                    'password' => Hash::make($password),
                    'preferred_timezone' => 'Africa/Nairobi',
                    'office_location' => null,
                    'is_active' => true,
                    'avatar' => null,
                    'notes' => null,
                ],
            );
        });

        $this->createTenant($admin);

        $tenantAdminRole = Role::where('name', 'tenant-admin')
            ->where('guard_name', 'api')
            ->first();

        if (!$tenantAdminRole) {
            $this->command->error('tenant-admin role not found. Run TenantRolePermissionsSeeder first.');

            return;
        }

        if (!$admin->hasRole('tenant-admin', 'api')) {
            $admin->assignRole($tenantAdminRole);
        }

        if ($admin->wasRecentlyCreated) {
            $this->command->info("Created default tenant admin: {$email}");
        } else {
            $this->command->line("Default tenant admin already exists: {$email}");
        }
    }

    private function createTenant(User $admin): void
    {
        $currentTenant = tenant();

        // In tenant context: DB, migrations, and seeding were already completed by the
        // TenantCreated event pipeline (CreateDatabase → MigrateDatabase → SeedDatabase).
        // Associate the admin user with the existing tenant record and send a welcome notification.
        if ($currentTenant !== null) {
            $currentTenant->update([
                'data' => array_merge((array) ($currentTenant->data ?? []), [
                    'admin_user_id' => $admin->id,
                    'admin_email'   => $admin->email,
                ]),
            ]);

            $admin->notify(new TenantAdminWelcomeNotification($currentTenant));

            return;
        }

        // Central context: create the tenant record in the central database.
        // Stancl fires TenantCreated automatically after create(), which triggers the job pipeline:
        // CreateDatabase → MigrateDatabase → SeedDatabase (re-runs this seeder in tenant context).
        $emailDomain = Str::after($admin->email, '@');
        $tenantId    = Str::slug(Str::before($emailDomain, '.'));

        /** @var Tenant $tenant */
        $tenant = Tenant::create([
            'id'     => $tenantId,
            'name'   => Str::title(Str::replace(['.', '-', '_'], ' ', Str::before($emailDomain, '.'))).' Organization',
            'plan'   => 'starter',
            'status' => 'active',
        ]);

        $tenant->domains()->create(['domain' => $emailDomain]);

        $this->command->info("Tenant '{$tenant->name}' created: {$tenant->id}");
    }
}
