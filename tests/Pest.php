<?php

declare(strict_types=1);

use App\Models\Central\PassportClient;
use App\Models\Central\SuperAdmin;
use App\Models\Central\Tenant;
use App\Models\User;
use Database\Seeders\Tenant\TenantDatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOk', function () {
    return $this->toBe(200);
});

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Shared Helpers
|--------------------------------------------------------------------------
*/

/**
 * Ensure a Passport personal access client exists for the given provider.
 */
function ensurePassportPersonalAccessClient(string $provider = 'users'): void
{
    $exists = PassportClient::query()
        ->where('provider', $provider)
        ->whereJsonContains('grant_types', 'personal_access')
        ->exists();

    if (!$exists) {
        PassportClient::forceCreate([
            'id' => Str::uuid()->toString(),
            'name' => "{$provider} Personal Access Client",
            'secret' => Str::random(40),
            'provider' => $provider,
            'redirect_uris' => ['http://localhost'],
            'grant_types' => ['personal_access'],
            'revoked' => false,
        ]);
    }
}

/**
 * Provision a tenant with a seeded admin user via the super-admin API flow.
 *
 * @return array{tenant: Tenant, domain: string, email: string, password: string}
 */
function provisionTenantWithSeededAdmin(): array
{
    ensurePassportPersonalAccessClient('super_admins');
    ensurePassportPersonalAccessClient('users');

    $superAdmin = SuperAdmin::factory()->create();
    Passport::actingAs($superAdmin, [], 'super_admin');

    $domain = 'tenant-'.Str::lower(Str::random(8)).'.localhost';
    $email = 'tenant-'.Str::lower(Str::random(8)).'@'.$domain;
    $password = 'StrongPass123!';

    putenv('TEST_TENANT_ADMIN_EMAIL='.$email);
    putenv('TEST_TENANT_ADMIN_PASSWORD='.$password);
    $_ENV['TEST_TENANT_ADMIN_EMAIL'] = $email;
    $_ENV['TEST_TENANT_ADMIN_PASSWORD'] = $password;
    $_SERVER['TEST_TENANT_ADMIN_EMAIL'] = $email;
    $_SERVER['TEST_TENANT_ADMIN_PASSWORD'] = $password;

    $response = test()->postJson('/api/v1/tenants', [
        'name' => 'Tenant Admin Auth '.Str::upper(Str::random(4)),
        'owner_id' => $superAdmin->id,
        'domain' => $domain,
    ]);

    $response->assertCreated();

    /** @var Tenant $tenant */
    $tenant = Tenant::query()->findOrFail((int) $response->json('data.id'));

    Artisan::call('tenants:migrate', [
        '--tenants' => [$tenant->id],
        '--force' => true,
    ]);

    Artisan::call('tenants:seed', [
        '--tenants' => [$tenant->id],
        '--class' => TenantDatabaseSeeder::class,
    ]);

    return [
        'tenant' => $tenant,
        'domain' => $domain,
        'email' => $email,
        'password' => $password,
    ];
}

/**
 * Authenticate a tenant admin via login and return the bearer token.
 */
function loginTenantAdmin(string $email, string $password): string
{
    $loginResponse = test()->postJson('/v1/auth/login', [
        'email' => $email,
        'password' => $password,
    ]);

    $loginResponse->assertOk()
        ->assertJsonPath('data.token_type', 'Bearer');

    return (string) $loginResponse->json('data.token');
}

/**
 * Register a new user within the current tenant context.
 *
 * @return array{email: string, password: string, user: User}
 */
function registerTenantUser(string $firstName = 'Test', string $lastName = 'User'): array
{    // Reset auth so the unauthenticated register endpoint has no created_by leak.
    app('auth')->forgetGuards();
    $email = fake()->unique()->safeEmail();
    $password = 'SecurePass123!';

    $response = test()->postJson('/v1/auth/register', [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'password' => $password,
        'password_confirmation' => $password,
    ]);

    $response->assertCreated();

    /** @var User $user */
    $user = User::query()->where('email', $email)->firstOrFail();

    return ['email' => $email, 'password' => $password, 'user' => $user];
}
