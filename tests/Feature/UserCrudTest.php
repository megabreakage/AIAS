<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Tenant\UserController;
use App\Http\Middleware\EnsureTokenMatchesTenant;
use App\Models\User;
use App\Repositories\Tenant\UserRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\DatabasePresenceVerifierInterface;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

// ---------------------------------------------------------------------------
// Route registration
// ---------------------------------------------------------------------------

describe('User routes registration', function (): void {
    it('registers GET /v1/users (index)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/users' && in_array('GET', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers POST /v1/users (store)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/users' && in_array('POST', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers GET /v1/users/{identifier} (show)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/users/{identifier}' && in_array('GET', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers PUT /v1/users/{identifier} (update)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/users/{identifier}' && in_array('PUT', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers DELETE /v1/users/{identifier} (destroy)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/users/{identifier}' && in_array('DELETE', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers POST /v1/users/{identifier}/restore (restore)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/users/{identifier}/restore' && in_array('POST', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers exactly 6 user routes', function (): void {
        $count = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'v1/users'))
            ->count();

        expect($count)->toBe(6);
    });

    it('user routes use auth:api middleware', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'v1/users' && in_array('GET', $r->methods(), true));

        expect($route)->not->toBeNull();
        expect(implode(',', $route->middleware()))->toContain('auth:api');
    });

    it('user routes use tenant.token middleware', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'v1/users' && in_array('GET', $r->methods(), true));

        expect($route)->not->toBeNull();
        expect(implode(',', $route->middleware()))->toContain('tenant.token');
    });

    it('user routes use the Tenant UserController', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'v1/users' && in_array('GET', $r->methods(), true));

        expect($route)->not->toBeNull();
        expect($route->getActionName())->toContain(UserController::class);
    });
});

// ---------------------------------------------------------------------------
// Unauthenticated access — returns 401
// ---------------------------------------------------------------------------

describe('User routes unauthenticated', function (): void {
    beforeEach(function (): void {
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
        ]);
    });

    it('GET /v1/users returns 401 without auth', function (): void {
        $this->getJson('/v1/users')->assertUnauthorized();
    });

    it('POST /v1/users returns 401 without auth', function (): void {
        $this->postJson('/v1/users', [])->assertUnauthorized();
    });

    it('GET /v1/users/{identifier} returns 401 without auth', function (): void {
        $this->getJson('/v1/users/some-uuid')->assertUnauthorized();
    });

    it('PUT /v1/users/{identifier} returns 401 without auth', function (): void {
        $this->putJson('/v1/users/some-uuid', [])->assertUnauthorized();
    });

    it('DELETE /v1/users/{identifier} returns 401 without auth', function (): void {
        $this->deleteJson('/v1/users/some-uuid')->assertUnauthorized();
    });

    it('POST /v1/users/{identifier}/restore returns 401 without auth', function (): void {
        $this->postJson('/v1/users/some-uuid/restore')->assertUnauthorized();
    });
});

// ---------------------------------------------------------------------------
// Permission checks — authenticated user without permission returns 403
// ---------------------------------------------------------------------------

describe('User routes permission enforcement', function (): void {
    beforeEach(function (): void {
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            EnsureTokenMatchesTenant::class,
        ]);

        Gate::before(fn () => false);
    });

    it('GET /v1/users returns 403 when user lacks user.view', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->getJson('/v1/users')
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// Permission granted — authenticated user WITH permission succeeds (mocked repo)
// ---------------------------------------------------------------------------

describe('User routes with permission granted', function (): void {
    beforeEach(function (): void {
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            EnsureTokenMatchesTenant::class,
        ]);

        Gate::before(fn () => true);

        // Stub unique/exists DB checks so they pass without a real tenant DB
        $presenceVerifier = Mockery::mock(DatabasePresenceVerifierInterface::class);
        $presenceVerifier->shouldReceive('setConnection')->andReturnSelf();
        $presenceVerifier->shouldReceive('getCount')->andReturn(0);
        $presenceVerifier->shouldReceive('getMultiCount')->andReturn(0);
        Validator::setPresenceVerifier($presenceVerifier);
    });

    it('GET /v1/users returns 200 with mocked paginator', function (): void {
        $user = new User;
        $user->identifier = (string) Str::uuid();
        $user->first_name = 'Jane';
        $user->last_name = 'Doe';
        $user->email = 'jane@example.com';
        $user->username = 'jane.doe';
        $user->is_active = true;

        $paginator = new LengthAwarePaginator(
            items: collect([$user]),
            total: 1,
            perPage: 15,
            currentPage: 1,
        );

        $repo = Mockery::mock(UserRepository::class);
        $repo->shouldReceive('browseUsers')->once()->andReturn($paginator);

        $this->app->instance(UserRepository::class, $repo);

        $actor = new User;
        $actor->id = 1;

        $this->actingAs($actor, 'api')
            ->getJson('/v1/users')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    });

    it('POST /v1/users returns 201 with mocked created user', function (): void {
        $created = new User;
        $created->identifier = (string) Str::uuid();
        $created->first_name = 'John';
        $created->last_name = 'Smith';
        $created->email = 'john.smith@example.com';
        $created->username = 'john.smith';
        $created->is_active = true;

        $repo = Mockery::mock(UserRepository::class);
        $repo->shouldReceive('createUser')->once()->andReturn($created);

        $this->app->instance(UserRepository::class, $repo);

        $actor = new User;
        $actor->id = 1;

        $this->actingAs($actor, 'api')
            ->postJson('/v1/users', [
                'first_name' => 'John',
                'last_name' => 'Smith',
                'username' => 'john.smith',
                'email' => 'john.smith@example.com',
                'password' => 'Password1',
            ])
            ->assertCreated()
            ->assertJsonStructure(['data', 'meta']);
    });

    it('POST /v1/users returns 422 when required fields are missing', function (): void {
        $actor = new User;
        $actor->id = 1;

        $this->actingAs($actor, 'api')
            ->postJson('/v1/users', [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    });

    it('POST /v1/users returns 422 when password is too short', function (): void {
        $actor = new User;
        $actor->id = 1;

        $this->actingAs($actor, 'api')
            ->postJson('/v1/users', [
                'first_name' => 'John',
                'last_name' => 'Smith',
                'username' => 'john.smith',
                'email' => 'john.smith@example.com',
                'password' => 'abc',
            ])
            ->assertUnprocessable();
    });
});
