<?php

declare(strict_types=1);

use App\Http\Requests\Central\User\RegisterCentralUserRequest;
use App\Models\Central\CentralUser;
use App\Models\Central\SuperAdmin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;
use Laravel\Passport\Passport;
use Stancl\Tenancy\Events\TenantCreated;
use Stancl\Tenancy\Events\TenantDeleted;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    ensurePassportPersonalAccessClient('super_admins');
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function authenticatedSuperAdminForUserTest(): SuperAdmin
{
    $admin = SuperAdmin::factory()->create();
    Passport::actingAs($admin, [], 'super_admin');

    return $admin;
}

function validCentralUserPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Jane',
        'last_name' => 'Mwangi',
        'username' => 'jane_mwangi_owner',
        'email' => 'jane.owner@example.com',
        'password' => 'Password1',
    ], $overrides);
}

// ===========================================================================
// Route Registration
// ===========================================================================

describe('CentralUser route registration', function (): void {

    it('registers POST /api/v1/users', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'api/v1/users' && in_array('POST', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('maps to CentralUserController@store', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'api/v1/users' && in_array('POST', $r->methods(), true));

        expect($route)->not->toBeNull();
        expect($route->getActionName())->toContain('CentralUserController@store');
    });

    it('requires auth:super_admin middleware', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'api/v1/users' && in_array('POST', $r->methods(), true));

        expect($route->gatherMiddleware())->toContain('auth:super_admin');
    });
});

// ===========================================================================
// Authentication
// ===========================================================================

describe('CentralUser authentication', function (): void {

    it('returns 401 when unauthenticated', function (): void {
        $this->postJson('/api/v1/users', validCentralUserPayload())
            ->assertUnauthorized();
    });

    it('returns 401 with invalid bearer token', function (): void {
        $this->withHeader('Authorization', 'Bearer invalid_token')
            ->postJson('/api/v1/users', validCentralUserPayload())
            ->assertUnauthorized();
    });
});

// ===========================================================================
// Request Validation (unit)
// ===========================================================================

describe('RegisterCentralUserRequest validation', function (): void {

    it('passes with all required fields', function (): void {
        $validator = Validator::make(validCentralUserPayload(), (new RegisterCentralUserRequest)->rules());

        expect($validator->passes())->toBeTrue();
    });

    it('fails without first_name', function (): void {
        $validator = Validator::make(validCentralUserPayload(['first_name' => '']), (new RegisterCentralUserRequest)->rules());

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('first_name'))->toBeTrue();
    });

    it('fails without last_name', function (): void {
        $validator = Validator::make(validCentralUserPayload(['last_name' => '']), (new RegisterCentralUserRequest)->rules());

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('last_name'))->toBeTrue();
    });

    it('fails without username', function (): void {
        $validator = Validator::make(validCentralUserPayload(['username' => '']), (new RegisterCentralUserRequest)->rules());

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('username'))->toBeTrue();
    });

    it('fails without email', function (): void {
        $validator = Validator::make(validCentralUserPayload(['email' => '']), (new RegisterCentralUserRequest)->rules());

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('email'))->toBeTrue();
    });

    it('fails with invalid email format', function (): void {
        $validator = Validator::make(validCentralUserPayload(['email' => 'not-an-email']), (new RegisterCentralUserRequest)->rules());

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('email'))->toBeTrue();
    });

    it('fails without password', function (): void {
        $validator = Validator::make(validCentralUserPayload(['password' => '']), (new RegisterCentralUserRequest)->rules());

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('password'))->toBeTrue();
    });

    it('fails with weak password (no numbers)', function (): void {
        $validator = Validator::make(validCentralUserPayload(['password' => 'PasswordOnly']), (new RegisterCentralUserRequest)->rules());

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('password'))->toBeTrue();
    });

    it('fails with weak password (no letters)', function (): void {
        $validator = Validator::make(validCentralUserPayload(['password' => '12345678']), (new RegisterCentralUserRequest)->rules());

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('password'))->toBeTrue();
    });
});

// ===========================================================================
// Step 1 — Register Tenant Owner (Feature)
// ===========================================================================

describe('Step 1 — Register central user (tenant owner)', function (): void {

    it('creates central user and returns 201', function (): void {
        authenticatedSuperAdminForUserTest();

        $response = $this->postJson('/api/v1/users', validCentralUserPayload());

        $response->assertCreated()
            ->assertJsonPath('data.email', 'jane.owner@example.com')
            ->assertJsonPath('data.first_name', 'Jane')
            ->assertJsonPath('data.last_name', 'Mwangi');

        $identifier = $response->json('data.identifier');
        expect($identifier)->not->toBeNull();
        expect($identifier)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
    });

    it('persists user in central database users table', function (): void {
        authenticatedSuperAdminForUserTest();

        $this->postJson('/api/v1/users', validCentralUserPayload());

        $this->assertDatabaseHas('users', [
            'email' => 'jane.owner@example.com',
        ], 'central');
    });

    it('assigns tenant role to the created user', function (): void {
        authenticatedSuperAdminForUserTest();

        $response = $this->postJson('/api/v1/users', validCentralUserPayload());

        $response->assertCreated();

        $roles = $response->json('data.roles');
        expect($roles)->toContain('tenant');
    });

    it('returns user with expected fields', function (): void {
        authenticatedSuperAdminForUserTest();

        $response = $this->postJson('/api/v1/users', validCentralUserPayload());

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'identifier', 'first_name', 'last_name', 'email',
                    'username', 'is_active', 'roles',
                ],
            ]);
    });

    it('rejects duplicate email', function (): void {
        authenticatedSuperAdminForUserTest();

        $this->postJson('/api/v1/users', validCentralUserPayload())->assertCreated();

        $this->postJson('/api/v1/users', validCentralUserPayload(['username' => 'different_user']))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.email.0', fn ($v) => str_contains($v, 'already'));
    });

    it('rejects duplicate username', function (): void {
        authenticatedSuperAdminForUserTest();

        $this->postJson('/api/v1/users', validCentralUserPayload())->assertCreated();

        $this->postJson('/api/v1/users', validCentralUserPayload(['email' => 'other@example.com']))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.username.0', fn ($v) => str_contains($v, 'taken'));
    });

    it('hashes the password (not stored in plaintext)', function (): void {
        authenticatedSuperAdminForUserTest();

        $response = $this->postJson('/api/v1/users', validCentralUserPayload());
        $response->assertCreated();

        $identifier = $response->json('data.identifier');
        $user = CentralUser::on('central')->where('identifier', $identifier)->first();

        expect($user)->not->toBeNull();
        expect($user->getAttributes()['password'])->not->toBe('Password1');
        expect(password_verify('Password1', $user->getAttributes()['password']))->toBeTrue();
    });
});

// ===========================================================================
// Step 1+2 — Register User then Create Tenant
// ===========================================================================

describe('Steps 1+2 — Register owner then create tenant', function (): void {

    beforeEach(function (): void {
        // Suppress TenantCreated lifecycle to avoid provisioning real DBs in tests
        Event::fake([
            TenantCreated::class,
            TenantDeleted::class,
        ]);
    });

    it('can create a tenant using the registered central user as owner', function (): void {
        authenticatedSuperAdminForUserTest();

        // Step 1: Register tenant owner
        $userResponse = $this->postJson('/api/v1/users', validCentralUserPayload());
        $userResponse->assertCreated();

        $ownerId = CentralUser::on('central')
            ->where('email', 'jane.owner@example.com')
            ->value('id');

        // Step 2: Create tenant using the central user's ID
        $tenantResponse = $this->postJson('/api/v1/tenants', [
            'name' => 'Jane Mwangi Holdings',
            'owner_id' => $ownerId,
        ]);

        $tenantResponse->assertCreated()
            ->assertJsonPath('data.name', 'Jane Mwangi Holdings');
    });

    it('rejects tenant creation when owner_id is a super_admin id', function (): void {
        $admin = SuperAdmin::factory()->create();
        Passport::actingAs($admin, [], 'super_admin');

        // Attempt to use the SuperAdmin's id as owner_id (no longer valid)
        $this->postJson('/api/v1/tenants', [
            'name' => 'Invalid Owner Corp',
            'owner_id' => $admin->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.owner_id.0', fn ($v) => str_contains($v, 'owner'));
    });
});
