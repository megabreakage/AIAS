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
        $currentTenant = tenant();

        // Resolve owner from tenant.data (stored during tenant creation in TenantController)
        $ownerData = $this->resolveOwnerData($currentTenant);

        $admin = User::withoutEvents(function () use ($ownerData): User {
            return User::firstOrCreate(
                ['email' => $ownerData['email']],
                [
                    'identifier' => (string) Str::uuid(),
                    'title' => null,
                    'first_name' => $ownerData['first_name'],
                    'middle_name' => null,
                    'last_name' => $ownerData['last_name'],
                    'username' => $ownerData['username'],
                    'email_verified_at' => now(),
                    'country_code' => '+254',
                    'phone' => null,
                    'password' => $ownerData['password'],
                    'preferred_timezone' => 'Africa/Nairobi',
                    'office_location' => null,
                    'is_active' => true,
                    'avatar' => null,
                    'notes' => null,
                ],
            );
        });

        $tenantRole = Role::where('name', 'tenant')
            ->where('guard_name', 'api')
            ->first();

        if (!$tenantRole) {
            $this->command->error('tenant role not found. Run TenantRolePermissionsSeeder first.');

            return;
        }

        if (!$admin->hasRole('tenant', 'api')) {
            $admin->assignRole($tenantRole);
        }

        if ($currentTenant !== null) {
            $currentTenant->update([
                'data' => array_merge((array) ($currentTenant->data ?? []), [
                    'admin_user_id' => $admin->id,
                    'admin_email' => $admin->email,
                ]),
            ]);

            $admin->notify(new TenantAdminWelcomeNotification($currentTenant));
        }

        if ($admin->wasRecentlyCreated) {
            $this->command->info("Created tenant owner: {$ownerData['email']}");
        } else {
            $this->command->line("Tenant owner already exists: {$ownerData['email']}");
        }
    }

    /**
     * Resolve owner credentials from the tenant record.
     *
     * Priority:
     *   1. tenant.data['owner'] — set by TenantController during tenant creation
     *   2. Environment variable fallback for legacy / manual seeding
     *
     * @param  \Stancl\Tenancy\Database\Models\Tenant|null  $currentTenant
     * @return array{email: string, first_name: string, last_name: string, username: string, password: string}
     */
    private function resolveOwnerData(mixed $currentTenant): array
    {
        // 1. Tenant has owner data stored at creation time
        if ($currentTenant !== null) {
            $ownerPayload = (array) ($currentTenant->data['owner'] ?? []);

            if (!empty($ownerPayload['email'])) {
                return [
                    'email' => $ownerPayload['email'],
                    'first_name' => $ownerPayload['first_name'] ?? 'Tenant',
                    'last_name' => $ownerPayload['last_name'] ?? 'Owner',
                    'username' => $ownerPayload['username'] ?? 'tenant_owner_'.Str::random(4),
                    'password' => $ownerPayload['password'] ?? Hash::make('password'),
                ];
            }

            // 2. Look up User via owner_id
            if (!empty($currentTenant->owner_id)) {
                $centralUser = User::find($currentTenant->owner_id);

                if ($centralUser !== null) {
                    return [
                        'email' => $centralUser->email,
                        'first_name' => $centralUser->first_name,
                        'last_name' => $centralUser->last_name,
                        'username' => $centralUser->username,
                        'password' => $centralUser->getAttributes()['password'],
                    ];
                }
            }
        }

        // 3. Env-based fallback for legacy / manual seeding
        $tenantDomain = $currentTenant?->domain ?? $currentTenant?->domains?->first()?->domain ?? 'tenant.localhost';
        $email = (string) env('TEST_TENANT_ADMIN_EMAIL', "admin@{$tenantDomain}");

        return [
            'email' => $email,
            'first_name' => 'Tenant',
            'last_name' => 'Owner',
            'username' => 'tenant_owner_'.Str::random(4),
            'password' => Hash::make((string) env('TEST_TENANT_ADMIN_PASSWORD', 'password')),
        ];
    }
}
