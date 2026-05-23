<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Central\TenantUserController;
use App\Http\Requests\Central\User\CreateTenantUserRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Validator;
use Laravel\Passport\Passport;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    ensurePassportPersonalAccessClient('users');
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function authenticatedSA(): User
{
    $admin = User::factory()->superAdmin()->create();
    Passport::actingAs($admin, [], 'api');

    return $admin;
}

function validTenantUserPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'username' => 'jane_doe_test',
        'email' => 'jane.doe@example.com',
        'password' => 'Password1',
        'role' => 'auditor',
    ], $overrides);
}

// ===========================================================================
// Route Registration
// ===========================================================================

describe('TenantUser route registration', function (): void {

    it('registers POST /api/v1/tenants/{id}/users', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'api/v1/tenants/{id}/users' && in_array('POST', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('maps to TenantUserController@store', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'api/v1/tenants/{id}/users' && in_array('POST', $r->methods(), true));

        expect($route)->not->toBeNull();
        expect($route->getActionName())->toContain('TenantUserController@store');
    });

    it('requires auth:super_admin middleware', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'api/v1/tenants/{id}/users' && in_array('POST', $r->methods(), true));

        expect($route->gatherMiddleware())->toContain('auth:api');
    });
});

// ===========================================================================
// Authentication
// ===========================================================================

describe('TenantUser authentication', function (): void {

    it('returns 401 when unauthenticated', function (): void {
        $this->postJson('/api/v1/tenants/999/users', validTenantUserPayload())
            ->assertUnauthorized();
    });

    it('returns 401 when using invalid bearer token', function (): void {
        $this->withHeader('Authorization', 'Bearer invalid_token_xyz')
            ->postJson('/api/v1/tenants/999/users', validTenantUserPayload())
            ->assertUnauthorized();
    });
});

// ===========================================================================
// Tenant Not Found
// ===========================================================================

describe('TenantUser tenant lookup', function (): void {

    it('returns 404 when tenant does not exist', function (): void {
        authenticatedSA();

        $this->postJson('/api/v1/tenants/nonexistent-id-999999/users', validTenantUserPayload())
            ->assertNotFound()
            ->assertJsonPath('error.code', 'TENANT_NOT_FOUND');
    });
});

// ===========================================================================
// Request Validation (unit-level)
// ===========================================================================

describe('CreateTenantUserRequest validation', function (): void {

    it('passes with all required fields', function (): void {
        $validator = Validator::make(validTenantUserPayload(), (new CreateTenantUserRequest)->rules());

        expect($validator->passes())->toBeTrue();
    });

    it('fails without first_name', function (): void {
        $validator = Validator::make(validTenantUserPayload(['first_name' => '']), (new CreateTenantUserRequest)->rules());

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('first_name'))->toBeTrue();
    });

    it('fails without last_name', function (): void {
        $validator = Validator::make(validTenantUserPayload(['last_name' => '']), (new CreateTenantUserRequest)->rules());

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('last_name'))->toBeTrue();
    });

    it('fails without username', function (): void {
        $validator = Validator::make(validTenantUserPayload(['username' => '']), (new CreateTenantUserRequest)->rules());

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('username'))->toBeTrue();
    });

    it('fails without email', function (): void {
        $validator = Validator::make(validTenantUserPayload(['email' => '']), (new CreateTenantUserRequest)->rules());

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('email'))->toBeTrue();
    });

    it('fails with invalid email format', function (): void {
        $validator = Validator::make(validTenantUserPayload(['email' => 'not-an-email']), (new CreateTenantUserRequest)->rules());

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('email'))->toBeTrue();
    });

    it('fails without password', function (): void {
        $validator = Validator::make(validTenantUserPayload(['password' => '']), (new CreateTenantUserRequest)->rules());

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('password'))->toBeTrue();
    });

    it('fails with password shorter than 8 characters', function (): void {
        $validator = Validator::make(validTenantUserPayload(['password' => 'Ab1']), (new CreateTenantUserRequest)->rules());

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('password'))->toBeTrue();
    });

    it('fails with password missing letters', function (): void {
        $validator = Validator::make(validTenantUserPayload(['password' => '12345678']), (new CreateTenantUserRequest)->rules());

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('password'))->toBeTrue();
    });

    it('fails with password missing numbers', function (): void {
        $validator = Validator::make(validTenantUserPayload(['password' => 'PasswordOnly']), (new CreateTenantUserRequest)->rules());

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('password'))->toBeTrue();
    });

    it('fails without role', function (): void {
        $validator = Validator::make(validTenantUserPayload(['role' => '']), (new CreateTenantUserRequest)->rules());

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('role'))->toBeTrue();
    });

    it('fails with invalid role value', function (): void {
        $validator = Validator::make(validTenantUserPayload(['role' => 'super-admin']), (new CreateTenantUserRequest)->rules());

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('role'))->toBeTrue();
    });

    it('accepts all valid roles', function (string $role): void {
        $validator = Validator::make(validTenantUserPayload(['role' => $role]), (new CreateTenantUserRequest)->rules());

        expect($validator->passes())->toBeTrue();
    })->with(['tenant', 'auditor', 'client', 'viewer']);
});

// ===========================================================================
// HTTP Validation (fires before tenancy initialization)
// ===========================================================================

describe('TenantUser HTTP validation', function (): void {

    it('returns 422 when required fields are missing', function (): void {
        authenticatedSA();

        $this->postJson('/api/v1/tenants/999/users', [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['code', 'message', 'details']]);
    });

    it('returns 422 with invalid role', function (): void {
        authenticatedSA();

        $this->postJson('/api/v1/tenants/999/users', validTenantUserPayload(['role' => 'super-admin']))
            ->assertUnprocessable()
            ->assertJsonPath('error.details.role.0', 'Role must be one of: tenant, auditor, client, viewer.');
    });

    it('returns 422 with invalid email', function (): void {
        authenticatedSA();

        $this->postJson('/api/v1/tenants/999/users', validTenantUserPayload(['email' => 'bad-email']))
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['email']]]);
    });
});

// ===========================================================================
// Controller Construction
// ===========================================================================

describe('TenantUserController construction', function (): void {

    it('can be resolved from the container', function (): void {
        $controller = app(TenantUserController::class);

        expect($controller)->toBeInstanceOf(TenantUserController::class);
    });
});
