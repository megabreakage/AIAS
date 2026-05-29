<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureTokenMatchesTenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->withoutMiddleware([
        InitializeTenancyByDomain::class,
        PreventAccessFromCentralDomains::class,
        EnsureTokenMatchesTenant::class,
    ]);
});

// ---------------------------------------------------------------------------
// Register
// ---------------------------------------------------------------------------

describe('Tenant user registration', function (): void {
    it('registers a new user and returns a token', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);
        app('auth')->forgetGuards();

        $password = 'SecurePass123!';

        $this->postJson('/v1/auth/register', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane.doe@example.test',
            'password' => $password,
            'password_confirmation' => $password,
        ])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['token', 'token_type', 'user']])
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'jane.doe@example.test');

        tenancy()->end();
    });

    it('creates the user record in the tenant database', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);
        app('auth')->forgetGuards();

        $password = 'SecurePass123!';

        $this->postJson('/v1/auth/register', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane.doe@example.test',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertCreated();

        expect(User::query()->where('email', 'jane.doe@example.test')->exists())->toBeTrue();

        tenancy()->end();
    });

    it('rejects duplicate email with 422', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);
        app('auth')->forgetGuards();

        $password = 'SecurePass123!';
        $payload = [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane.doe@example.test',
            'password' => $password,
            'password_confirmation' => $password,
        ];

        $this->postJson('/v1/auth/register', $payload)->assertCreated();

        $this->postJson('/v1/auth/register', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        tenancy()->end();
    });

    it('rejects missing first_name with 422', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);
        app('auth')->forgetGuards();

        $this->postJson('/v1/auth/register', [
            'last_name' => 'Doe',
            'email' => 'jane@example.test',
            'password' => 'Pass123!',
            'password_confirmation' => 'Pass123!',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.first_name.0', 'The first name field is required.');

        tenancy()->end();
    });

    it('rejects mismatched password confirmation with 422', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);
        app('auth')->forgetGuards();

        $this->postJson('/v1/auth/register', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.test',
            'password' => 'Pass123!',
            'password_confirmation' => 'Different999!',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        tenancy()->end();
    });

    it('rejects a password shorter than 8 characters', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);
        app('auth')->forgetGuards();

        $this->postJson('/v1/auth/register', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.test',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        tenancy()->end();
    });

    it('does not expose password in registration response', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        $password = 'SecurePass123!';
        tenancy()->initialize($ctx['tenant']);        app('auth')->forgetGuards();
        $response = $this->postJson('/v1/auth/register', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.test',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertCreated();

        expect($response->json('data.user'))->not->toHaveKey('password');

        tenancy()->end();
    });
});

// ---------------------------------------------------------------------------
// Login
// ---------------------------------------------------------------------------

describe('Tenant user login', function (): void {
    it('authenticates with valid credentials and returns a token', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);

        $this->postJson('/v1/auth/login', [
            'email' => $ctx['email'],
            'password' => $ctx['password'],
        ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['token', 'token_type', 'user']])
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', $ctx['email']);

        tenancy()->end();
    });

    it('returns a JWT access token', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);

        $token = $this->postJson('/v1/auth/login', [
            'email' => $ctx['email'],
            'password' => $ctx['password'],
        ])->assertOk()->json('data.token');

        expect(explode('.', $token))->toHaveCount(3);

        tenancy()->end();
    });

    it('stores a token scoped to the correct tenant', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);

        $this->postJson('/v1/auth/login', [
            'email' => $ctx['email'],
            'password' => $ctx['password'],
        ])->assertOk();

        /** @var User $user */
        $user = User::query()->where('email', $ctx['email'])->firstOrFail();

        tenancy()->end();

        $record = DB::connection('central')
            ->table('oauth_access_tokens')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->first();

        expect($record)->not->toBeNull();
        expect((bool) $record->revoked)->toBeFalse();
        expect($record->tenant_id)->toBe((string) $ctx['tenant']->id);
    });

    it('rejects wrong password with 401', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);

        $this->postJson('/v1/auth/login', [
            'email' => $ctx['email'],
            'password' => 'WrongPassword!',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'DOMAIN_ERROR');

        tenancy()->end();
    });

    it('rejects non-existent email with 401', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);

        $this->postJson('/v1/auth/login', [
            'email' => 'nobody@nowhere.test',
            'password' => 'SomePass123!',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'DOMAIN_ERROR');

        tenancy()->end();
    });

    it('rejects inactive user with 403', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);

        /** @var User $user */
        $user = User::query()->where('email', $ctx['email'])->firstOrFail();
        User::withoutEvents(fn () => $user->update(['is_active' => false]));

        $this->postJson('/v1/auth/login', [
            'email' => $ctx['email'],
            'password' => $ctx['password'],
        ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'DOMAIN_ERROR');

        tenancy()->end();
    });

    it('rejects missing email field with 422', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);

        $this->postJson('/v1/auth/login', ['password' => 'Pass123!'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.email.0', 'The email field is required.');

        tenancy()->end();
    });

    it('rejects missing password field with 422', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);

        $this->postJson('/v1/auth/login', ['email' => $ctx['email']])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.password.0', 'The password field is required.');

        tenancy()->end();
    });

    it('does not expose password in login response', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);

        $response = $this->postJson('/v1/auth/login', [
            'email' => $ctx['email'],
            'password' => $ctx['password'],
        ])->assertOk();

        expect($response->json('data.user'))->not->toHaveKey('password');

        tenancy()->end();
    });
});

// ---------------------------------------------------------------------------
// /me
// ---------------------------------------------------------------------------

describe('Tenant user /me endpoint', function (): void {
    it('returns authenticated user profile', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);

        $token = loginTenantAdmin($ctx['email'], $ctx['password']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/v1/auth/me')
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'first_name', 'last_name', 'email']])
            ->assertJsonPath('data.email', $ctx['email']);

        tenancy()->end();
    });

    it('returns 401 when unauthenticated', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);

        $this->getJson('/v1/auth/me')
            ->assertUnauthorized();

        tenancy()->end();
    });

    it('does not expose password in profile', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);

        $token = loginTenantAdmin($ctx['email'], $ctx['password']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/v1/auth/me')
            ->assertOk();

        expect($response->json('data'))->not->toHaveKey('password');

        tenancy()->end();
    });

    it('returns identifier not database id in profile', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);

        $token = loginTenantAdmin($ctx['email'], $ctx['password']);

        /** @var User $user */
        $user = User::query()->where('email', $ctx['email'])->firstOrFail();

        $id = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/v1/auth/me')
            ->assertOk()
            ->json('data.id');

        expect($id)->toBe($user->identifier);

        tenancy()->end();
    });
});

// ---------------------------------------------------------------------------
// Logout
// ---------------------------------------------------------------------------

describe('Tenant user logout', function (): void {
    it('revokes the token on logout', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);

        $token = loginTenantAdmin($ctx['email'], $ctx['password']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('data.message', 'Logged out successfully.');

        tenancy()->end();
    });

    it('returns 401 when logging out without a token', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);

        $this->postJson('/v1/auth/logout')
            ->assertUnauthorized();

        tenancy()->end();
    });

    it('marks the oauth token as revoked in the database', function (): void {
        $ctx = provisionTenantWithSeededAdmin();
        tenancy()->initialize($ctx['tenant']);

        $token = loginTenantAdmin($ctx['email'], $ctx['password']);

        /** @var User $user */
        $user = User::query()->where('email', $ctx['email'])->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/v1/auth/logout')
            ->assertOk();

        tenancy()->end();

        $record = DB::connection('central')
            ->table('oauth_access_tokens')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->first();

        expect($record)->not->toBeNull();
        expect((bool) $record->revoked)->toBeTrue();
    });
});
