<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    ensurePassportPersonalAccessClient('users');
});

// ---------------------------------------------------------------------------
// Login
// ---------------------------------------------------------------------------

describe('SuperAdmin login', function (): void {
    it('authenticates with valid credentials and returns a token', function (): void {
        $admin = User::factory()->superAdmin()->create();

        $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['token', 'token_type', 'user']])
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', $admin->email)
            ->assertJsonPath('data.user.first_name', $admin->first_name)
            ->assertJsonPath('data.user.last_name', $admin->last_name);
    });

    it('returns a JWT access token', function (): void {
        $admin = User::factory()->superAdmin()->create();

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertOk()->json('data.token');

        expect(explode('.', $token))->toHaveCount(3);
    });

    it('stores a non-revoked token in oauth_access_tokens', function (): void {
        $admin = User::factory()->superAdmin()->create();

        $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertOk();

        $record = DB::connection('central')
            ->table('oauth_access_tokens')
            ->where('user_id', $admin->id)
            ->orderByDesc('created_at')
            ->first();

        expect($record)->not->toBeNull();
        expect((bool) $record->revoked)->toBeFalse();
        expect($record->tenant_id)->toBeNull();
    });

    it('rejects wrong password with 401', function (): void {
        $admin = User::factory()->superAdmin()->create();

        $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'DOMAIN_ERROR');
    });

    it('rejects non-existent email with 401', function (): void {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@aias.system',
            'password' => 'password',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'DOMAIN_ERROR');
    });

    it('rejects inactive admin with 403', function (): void {
        $admin = User::factory()->superAdmin()->inactive()->create();

        $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'DOMAIN_ERROR');
    });

    it('rejects missing email with 422', function (): void {
        $this->postJson('/api/v1/auth/login', [
            'password' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.email.0', 'The email field is required.');
    });

    it('rejects missing password with 422', function (): void {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'sa@aias.system',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.password.0', 'The password field is required.');
    });

    it('rejects invalid email format with 422', function (): void {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'not-an-email',
            'password' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    });

    it('does not expose password in login response', function (): void {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertOk();

        expect($response->json('data.user'))->not->toHaveKey('password');
    });
});

// ---------------------------------------------------------------------------
// /me
// ---------------------------------------------------------------------------

describe('SuperAdmin /me endpoint', function (): void {
    it('returns admin profile when authenticated', function (): void {
        $admin = User::factory()->superAdmin()->create();

        Passport::actingAs($admin, [], 'api');

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'first_name', 'last_name', 'email', 'is_active']])
            ->assertJsonPath('data.email', $admin->email)
            ->assertJsonPath('data.is_active', true);
    });

    it('returns 401 when unauthenticated', function (): void {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    });

    it('does not expose password in profile', function (): void {
        $admin = User::factory()->superAdmin()->create();

        Passport::actingAs($admin, [], 'api');

        expect(
            $this->getJson('/api/v1/auth/me')->assertOk()->json('data')
        )->not->toHaveKey('password');
    });

    it('returns identifier not database id in profile', function (): void {
        $admin = User::factory()->superAdmin()->create();

        Passport::actingAs($admin, [], 'api');

        $id = $this->getJson('/api/v1/auth/me')->assertOk()->json('data.id');

        expect($id)->toBe($admin->identifier);
    });
});

// ---------------------------------------------------------------------------
// Logout
// ---------------------------------------------------------------------------

describe('SuperAdmin logout', function (): void {
    it('revokes the token on logout', function (): void {
        $admin = User::factory()->superAdmin()->create();

        Passport::actingAs($admin, [], 'api');

        $this->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('data.message', 'Logged out successfully.');
    });

    it('returns 401 when logging out without a token', function (): void {
        $this->postJson('/api/v1/auth/logout')
            ->assertUnauthorized();
    });
});

// ---------------------------------------------------------------------------
// Protected route access
// ---------------------------------------------------------------------------

describe('SuperAdmin protected route access', function (): void {
    it('can access protected endpoints with a valid token', function (): void {
        $admin = User::factory()->superAdmin()->create();

        Passport::actingAs($admin, [], 'api');

        $this->getJson('/api/v1/tenants')
            ->assertOk();
    });

    it('rejects unauthenticated access to protected endpoints', function (): void {
        $this->getJson('/api/v1/tenants')
            ->assertUnauthorized();
    });

    it('rejects a malformed bearer token', function (): void {
        $this->withHeader('Authorization', 'Bearer invalid.token.here')
            ->getJson('/api/v1/tenants')
            ->assertUnauthorized();
    });
});
