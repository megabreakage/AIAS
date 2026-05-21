<?php

declare(strict_types=1);

use App\Models\Central\SuperAdmin;
use App\Models\Central\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

uses(DatabaseTransactions::class);

function provisionTenantWithSeededAdmin(): array
{
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

    return [
        'tenant' => $tenant,
        'domain' => $domain,
        'email' => $email,
        'password' => $password,
    ];
}

describe('Tenant admin authentication and ownership lifecycle', function (): void {
    it('authenticates tenant admin and exposes own profile', function (): void {
        $ctx = provisionTenantWithSeededAdmin();

        $loginResponse = $this->withServerVariables(['HTTP_HOST' => $ctx['domain']])
            ->postJson('/v1/auth/login', [
                'email' => $ctx['email'],
                'password' => $ctx['password'],
            ]);

        $loginResponse->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', $ctx['email']);

        $token = (string) $loginResponse->json('data.token');

        $this->withServerVariables(['HTTP_HOST' => $ctx['domain']])
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $ctx['email']);

        tenancy()->initialize($ctx['tenant']);

        /** @var User $tenantAdmin */
        $tenantAdmin = User::query()->where('email', $ctx['email'])->firstOrFail();
        expect($tenantAdmin->hasRole('tenant-admin', 'api'))->toBeTrue();

        tenancy()->end();

        $accessToken = DB::connection('central')
            ->table('oauth_access_tokens')
            ->where('user_id', $tenantAdmin->id)
            ->orderByDesc('created_at')
            ->first();

        expect($accessToken)->not->toBeNull();
        expect($accessToken->tenant_id)->toBe((string) $ctx['tenant']->id);
    });

    it('can view update delete and force delete own details and mapped tenant details', function (): void {
        $ctx = provisionTenantWithSeededAdmin();

        tenancy()->initialize($ctx['tenant']);

        /** @var User $tenantAdmin */
        $tenantAdmin = User::query()->where('email', $ctx['email'])->firstOrFail();
        expect($tenantAdmin->email)->toBe($ctx['email']);

        $tenantAdmin->update([
            'first_name' => 'Updated',
            'last_name' => 'Owner',
            'office_location' => 'HQ',
        ]);

        $tenantAdmin->refresh();
        expect($tenantAdmin->first_name)->toBe('Updated');
        expect($tenantAdmin->last_name)->toBe('Owner');

        $tenantAdminId = $tenantAdmin->id;
        $tenantAdmin->delete();

        $this->assertSoftDeleted('users', ['id' => $tenantAdminId]);

        $trashedAdmin = User::query()->withTrashed()->findOrFail($tenantAdminId);
        $trashedAdmin->forceDelete();

        $this->assertDatabaseMissing('users', ['id' => $tenantAdminId]);

        tenancy()->end();

        /** @var Tenant $mappedTenant */
        $mappedTenant = Tenant::query()->findOrFail($ctx['tenant']->id);
        expect($mappedTenant->id)->toBe($ctx['tenant']->id);

        $mappedTenant->update(['name' => 'Renamed Tenant']);
        $mappedTenant->refresh();
        expect($mappedTenant->name)->toBe('Renamed Tenant');

        $tenantId = $mappedTenant->id;
        $mappedTenant->delete();

        $this->assertSoftDeleted('tenants', ['id' => $tenantId], deletedAtColumn: 'deleted_at', connection: 'central');

        $trashedTenant = Tenant::query()->withTrashed()->findOrFail($tenantId);
        $trashedTenant->forceDelete();

        $this->assertDatabaseMissing('tenants', ['id' => $tenantId], connection: 'central');
    });
});
