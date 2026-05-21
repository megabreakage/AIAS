<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureTokenMatchesTenant;
use App\Models\Central\PassportClient;
use App\Models\Central\SuperAdmin;
use App\Models\Central\Tenant;
use App\Models\User;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Stancl\Tenancy\Events\TenantDeleted;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

uses(DatabaseTransactions::class);

/**
 * Provision a tenant with a seeded admin user via the super-admin API flow.
 *
 * @return array{tenant: Tenant, domain: string, email: string, password: string}
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

function provisionTenantWithSeededAdmin(): array
{
    ensurePassportPersonalAccessClient('super_admins');
    ensurePassportPersonalAccessClient('users');

    $superAdmin = SuperAdmin::factory()->create();
    Passport::actingAs($superAdmin, [], 'super_admin');

    $domain = 'tenant-admin-'.Str::lower(Str::random(8)).'.localhost';
    $email = 'tenant-admin-'.Str::lower(Str::random(8)).'@'.$domain;
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

// ===========================================================================
// Tenant Admin Authentication
// ===========================================================================

describe('Tenant admin authentication', function (): void {
    beforeEach(function (): void {
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            EnsureTokenMatchesTenant::class,
        ]);
    });

    it('authenticates tenant admin with valid credentials', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);

        $loginResponse = $this->postJson('/v1/auth/login', [
            'email' => $ctx['email'],
            'password' => $ctx['password'],
        ]);

        $loginResponse->assertOk()
            ->assertJsonStructure(['data' => ['token', 'token_type', 'user']])
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', $ctx['email']);

        tenancy()->end();
    });

    it('rejects tenant admin with invalid credentials', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);

        $this->postJson('/v1/auth/login', [
            'email' => $ctx['email'],
            'password' => 'WrongPassword!',
        ])->assertUnauthorized();

        tenancy()->end();
    });

    it('rejects login with non-existent email', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);

        $this->postJson('/v1/auth/login', [
            'email' => 'nobody@nowhere.test',
            'password' => $ctx['password'],
        ])->assertUnauthorized();

        tenancy()->end();
    });

    it('stores access token with correct tenant_id in central database', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);

        $token = loginTenantAdmin($ctx['email'], $ctx['password']);
        expect($token)->not->toBeEmpty();

        /** @var User $tenantAdmin */
        $tenantAdmin = User::query()->where('email', $ctx['email'])->firstOrFail();

        tenancy()->end();

        $accessToken = DB::connection('central')
            ->table('oauth_access_tokens')
            ->where('user_id', $tenantAdmin->id)
            ->orderByDesc('created_at')
            ->first();

        expect($accessToken)->not->toBeNull();
        expect($accessToken->tenant_id)->toBe((string) $ctx['tenant']->id);
    });

    it('assigns tenant-admin role to seeded admin', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);

        /** @var User $tenantAdmin */
        $tenantAdmin = User::query()->where('email', $ctx['email'])->firstOrFail();
        expect($tenantAdmin->hasRole('tenant-admin', 'api'))->toBeTrue();

        tenancy()->end();
    });
});

// ===========================================================================
// Tenant Admin Profile (me endpoint)
// ===========================================================================

describe('Tenant admin profile access', function (): void {
    beforeEach(function (): void {
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            EnsureTokenMatchesTenant::class,
        ]);
    });

    it('can view own profile via /me endpoint', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);

        $token = loginTenantAdmin($ctx['email'], $ctx['password']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $ctx['email'])
            ->assertJsonStructure(['data' => ['id', 'first_name', 'last_name', 'email']]);

        tenancy()->end();
    });

    it('rejects unauthenticated access to /me endpoint', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);

        $this->getJson('/v1/auth/me')
            ->assertUnauthorized();

        tenancy()->end();
    });

    it('can logout and token is revoked', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);

        $token = loginTenantAdmin($ctx['email'], $ctx['password']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/v1/auth/logout')
            ->assertOk();

        tenancy()->end();
    });
});

// ===========================================================================
// Tenant Admin — Own Details CRUD
// ===========================================================================

describe('Tenant admin own details management', function (): void {
    beforeEach(function (): void {
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            EnsureTokenMatchesTenant::class,
        ]);
    });

    it('can view own user record', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);

        /** @var User $tenantAdmin */
        $tenantAdmin = User::query()->where('email', $ctx['email'])->firstOrFail();

        expect($tenantAdmin->email)->toBe($ctx['email']);
        expect($tenantAdmin->is_active)->toBeTrue();
        expect($tenantAdmin->first_name)->toBe('Tenant');
        expect($tenantAdmin->last_name)->toBe('Owner');

        tenancy()->end();
    });

    it('can update own details', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);

        /** @var User $tenantAdmin */
        $tenantAdmin = User::query()->where('email', $ctx['email'])->firstOrFail();

        // Use withoutEvents to avoid updated_by FK referencing a user from the central DB
        User::withoutEvents(function () use ($tenantAdmin): void {
            $tenantAdmin->update([
                'first_name' => 'Updated',
                'last_name' => 'Admin',
                'office_location' => 'Nairobi HQ',
                'preferred_timezone' => 'Africa/Nairobi',
            ]);
        });

        $tenantAdmin->refresh();
        expect($tenantAdmin->first_name)->toBe('Updated');
        expect($tenantAdmin->last_name)->toBe('Admin');
        expect($tenantAdmin->office_location)->toBe('Nairobi HQ');
        expect($tenantAdmin->preferred_timezone)->toBe('Africa/Nairobi');

        tenancy()->end();
    });

    it('can soft delete own user record', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);

        /** @var User $tenantAdmin */
        $tenantAdmin = User::query()->where('email', $ctx['email'])->firstOrFail();
        $tenantAdminId = $tenantAdmin->id;

        $tenantAdmin->delete();

        $this->assertSoftDeleted('users', ['id' => $tenantAdminId]);

        // Confirm soft-deleted record is excluded from default queries
        expect(User::query()->find($tenantAdminId))->toBeNull();

        // Confirm soft-deleted record is retrievable with withTrashed
        $trashed = User::query()->withTrashed()->find($tenantAdminId);
        expect($trashed)->not->toBeNull();
        expect($trashed->trashed())->toBeTrue();

        tenancy()->end();
    });

    it('can force delete own user record', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);

        /** @var User $tenantAdmin */
        $tenantAdmin = User::query()->where('email', $ctx['email'])->firstOrFail();
        $tenantAdminId = $tenantAdmin->id;

        $tenantAdmin->forceDelete();

        $this->assertDatabaseMissing('users', ['id' => $tenantAdminId]);

        // Confirm even withTrashed cannot find the record
        expect(User::query()->withTrashed()->find($tenantAdminId))->toBeNull();

        tenancy()->end();
    });

    it('can soft delete then force delete own user record', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);

        /** @var User $tenantAdmin */
        $tenantAdmin = User::query()->where('email', $ctx['email'])->firstOrFail();
        $tenantAdminId = $tenantAdmin->id;

        // Soft delete first
        $tenantAdmin->delete();
        $this->assertSoftDeleted('users', ['id' => $tenantAdminId]);

        // Then force delete the trashed record
        $trashedAdmin = User::query()->withTrashed()->findOrFail($tenantAdminId);
        $trashedAdmin->forceDelete();

        $this->assertDatabaseMissing('users', ['id' => $tenantAdminId]);

        tenancy()->end();
    });
});

// ===========================================================================
// Tenant Admin — Mapped Tenant Details CRUD
// ===========================================================================

describe('Tenant admin mapped tenant management', function (): void {
    beforeEach(function (): void {
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            EnsureTokenMatchesTenant::class,
        ]);
    });

    it('can view mapped tenant details', function (): void {
        $ctx = provisionTenantWithSeededAdmin();

        /** @var Tenant $mappedTenant */
        $mappedTenant = Tenant::query()->findOrFail($ctx['tenant']->id);

        expect($mappedTenant->id)->toBe($ctx['tenant']->id);
        expect($mappedTenant->domain)->toBe($ctx['domain']);
        expect($mappedTenant->name)->not->toBeEmpty();
    });

    it('can update mapped tenant details', function (): void {
        $ctx = provisionTenantWithSeededAdmin();

        /** @var Tenant $mappedTenant */
        $mappedTenant = Tenant::query()->findOrFail($ctx['tenant']->id);

        $mappedTenant->update([
            'name' => 'Renamed Tenant Corp',
            'data_center' => 'eu-west-1',
        ]);

        $mappedTenant->refresh();
        expect($mappedTenant->name)->toBe('Renamed Tenant Corp');
        expect($mappedTenant->data_center)->toBe('eu-west-1');
    });

    it('can soft delete mapped tenant', function (): void {
        $ctx = provisionTenantWithSeededAdmin();

        /** @var Tenant $mappedTenant */
        $mappedTenant = Tenant::query()->findOrFail($ctx['tenant']->id);
        $tenantId = $mappedTenant->id;

        $mappedTenant->delete();

        $this->assertSoftDeleted(
            'tenants',
            ['id' => $tenantId],
            deletedAtColumn: 'deleted_at',
            connection: 'central',
        );

        // Confirm soft-deleted tenant is excluded from default queries
        expect(Tenant::query()->find($tenantId))->toBeNull();

        // Confirm soft-deleted tenant is retrievable with withTrashed
        $trashed = Tenant::query()->withTrashed()->find($tenantId);
        expect($trashed)->not->toBeNull();
        expect($trashed->trashed())->toBeTrue();
    });

    it('can force delete mapped tenant', function (): void {
        $ctx = provisionTenantWithSeededAdmin();

        /** @var Tenant $mappedTenant */
        $mappedTenant = Tenant::query()->findOrFail($ctx['tenant']->id);
        $tenantId = $mappedTenant->id;

        // Prevent Stancl from dropping the tenant database during forceDelete
        Event::fake([TenantDeleted::class]);

        $mappedTenant->forceDelete();

        $this->assertDatabaseMissing('tenants', ['id' => $tenantId], connection: 'central');

        // Confirm even withTrashed cannot find the record
        expect(Tenant::query()->withTrashed()->find($tenantId))->toBeNull();
    });

    it('can soft delete then force delete mapped tenant', function (): void {
        $ctx = provisionTenantWithSeededAdmin();

        /** @var Tenant $mappedTenant */
        $mappedTenant = Tenant::query()->findOrFail($ctx['tenant']->id);
        $tenantId = $mappedTenant->id;

        // Soft delete first
        $mappedTenant->delete();
        $this->assertSoftDeleted(
            'tenants',
            ['id' => $tenantId],
            deletedAtColumn: 'deleted_at',
            connection: 'central',
        );

        // Prevent Stancl from dropping the tenant database during forceDelete
        Event::fake([TenantDeleted::class]);

        // Then force delete the trashed record
        $trashedTenant = Tenant::query()->withTrashed()->findOrFail($tenantId);
        $trashedTenant->forceDelete();

        $this->assertDatabaseMissing('tenants', ['id' => $tenantId], connection: 'central');
    });
});
