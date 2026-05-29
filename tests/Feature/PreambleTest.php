<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Tenant\PreambleController;
use App\Http\Middleware\EnsureTokenMatchesTenant;
use App\Models\Tenant\Preamble;
use App\Models\User;
use App\Repositories\Tenant\PreambleRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

// ---------------------------------------------------------------------------
// Route registration
// ---------------------------------------------------------------------------

describe('Preamble routes registration', function (): void {
    it('registers GET /v1/preambles (index)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/preambles' && in_array('GET', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers POST /v1/preambles (store)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/preambles' && in_array('POST', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers GET /v1/preambles/{identifier} (show)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/preambles/{identifier}' && in_array('GET', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers PUT /v1/preambles/{identifier} (update)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/preambles/{identifier}' && in_array('PUT', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers DELETE /v1/preambles/{identifier} (destroy)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/preambles/{identifier}' && in_array('DELETE', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers POST /v1/preambles/{identifier}/restore (restore)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/preambles/{identifier}/restore' && in_array('POST', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers exactly 6 preamble routes', function (): void {
        $count = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'v1/preambles'))
            ->count();

        expect($count)->toBe(6);
    });

    it('preamble routes use auth:api middleware', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'v1/preambles' && in_array('GET', $r->methods(), true));

        expect($route)->not->toBeNull();
        expect(implode(',', $route->middleware()))->toContain('auth:api');
    });

    it('preamble routes use tenant.token middleware', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'v1/preambles' && in_array('GET', $r->methods(), true));

        expect($route)->not->toBeNull();
        expect(implode(',', $route->middleware()))->toContain('tenant.token');
    });

    it('preamble routes use the Tenant PreambleController', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'v1/preambles' && in_array('GET', $r->methods(), true));

        expect($route)->not->toBeNull();
        expect($route->getActionName())->toContain(PreambleController::class);
    });
});

// ---------------------------------------------------------------------------
// Unauthenticated access — returns 401
// ---------------------------------------------------------------------------

describe('Preamble routes unauthenticated', function (): void {
    beforeEach(function (): void {
        // Bypass domain-based tenancy middleware (we're on localhost in tests)
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
        ]);
    });

    it('GET /v1/preambles returns 401 without auth', function (): void {
        $this->getJson('/v1/preambles')->assertUnauthorized();
    });

    it('POST /v1/preambles returns 401 without auth', function (): void {
        $this->postJson('/v1/preambles', [])->assertUnauthorized();
    });

    it('GET /v1/preambles/{identifier} returns 401 without auth', function (): void {
        $this->getJson('/v1/preambles/some-uuid')->assertUnauthorized();
    });

    it('PUT /v1/preambles/{identifier} returns 401 without auth', function (): void {
        $this->putJson('/v1/preambles/some-uuid', [])->assertUnauthorized();
    });

    it('DELETE /v1/preambles/{identifier} returns 401 without auth', function (): void {
        $this->deleteJson('/v1/preambles/some-uuid')->assertUnauthorized();
    });

    it('POST /v1/preambles/{identifier}/restore returns 401 without auth', function (): void {
        $this->postJson('/v1/preambles/some-uuid/restore')->assertUnauthorized();
    });
});

// ---------------------------------------------------------------------------
// Permission checks — authenticated user without permission returns 403
// ---------------------------------------------------------------------------

describe('Preamble routes permission enforcement', function (): void {
    beforeEach(function (): void {
        // Bypass tenancy + token middleware — we test permission logic only
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            EnsureTokenMatchesTenant::class,
        ]);

        // Force all Gate checks to deny — simulates user without any permissions
        Gate::before(fn () => false);
    });

    it('GET /v1/preambles returns 403 when user lacks preamble.view', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->getJson('/v1/preambles')
            ->assertForbidden();
    });

    it('POST /v1/preambles returns 403 when user lacks preamble.create', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/preambles', ['name' => 'Test Preamble'])
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// Permission granted — authenticated user WITH permission succeeds (mocked repo)
// ---------------------------------------------------------------------------

describe('Preamble routes with permission granted', function (): void {
    beforeEach(function (): void {
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            EnsureTokenMatchesTenant::class,
        ]);

        // Grant all Gate checks
        Gate::before(fn () => true);
    });

    it('GET /v1/preambles returns 200 with mocked empty paginator', function (): void {
        $preamble = new Preamble;
        $preamble->identifier = (string) Str::uuid();
        $preamble->tenant_id = 'test-tenant';
        $preamble->name = 'Audit Policy';
        $preamble->status = Preamble::STATUS_DRAFT;
        $preamble->is_featured = false;

        $paginator = new LengthAwarePaginator(
            items: collect([$preamble]),
            total: 1,
            perPage: 15,
            currentPage: 1,
        );

        $repo = Mockery::mock(PreambleRepository::class);
        $repo->shouldReceive('browsePreambles')->once()->andReturn($paginator);

        $this->app->instance(PreambleRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->getJson('/v1/preambles')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    });

    it('POST /v1/preambles returns 201 with mocked created preamble', function (): void {
        $preamble = new Preamble;
        $preamble->identifier = (string) Str::uuid();
        $preamble->tenant_id = null; // tenant() returns null in unit context
        $preamble->name = 'SOX Compliance';
        $preamble->status = Preamble::STATUS_DRAFT;
        $preamble->is_featured = false;
        $preamble->reference_number = 'PR-1-'.now()->timestamp;

        $repo = Mockery::mock(PreambleRepository::class);
        $repo->shouldReceive('createPreamble')->once()->andReturn($preamble);

        $this->app->instance(PreambleRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/preambles', [
                'name' => 'SOX Compliance',
                'status' => 'draft',
            ])
            ->assertCreated()
            ->assertJsonStructure(['data', 'meta']);
    });

    it('POST /v1/preambles returns 422 when name is missing', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/preambles', [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    });

    it('POST /v1/preambles returns 422 when status is invalid', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/preambles', [
                'name' => 'Test',
                'status' => 'published', // invalid
            ])
            ->assertUnprocessable();
    });
});

// ---------------------------------------------------------------------------
// Validation rules
// ---------------------------------------------------------------------------

describe('Preamble request validation', function (): void {
    beforeEach(function (): void {
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            EnsureTokenMatchesTenant::class,
        ]);

        Gate::before(fn () => true);
    });

    it('rejects name longer than 255 characters', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/preambles', [
                'name' => str_repeat('a', 256),
            ])
            ->assertUnprocessable();
    });

    it('accepts all valid statuses', function (): void {
        $repo = Mockery::mock(PreambleRepository::class);
        $repo->shouldReceive('createPreamble')->times(3)->andReturn(new Preamble);

        $this->app->instance(PreambleRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        foreach (Preamble::STATUSES as $status) {
            $this->actingAs($user, 'api')
                ->postJson('/v1/preambles', ['name' => 'Test', 'status' => $status])
                ->assertCreated();
        }
    });

    it('accepts valid date for effective_date', function (): void {
        $preamble = new Preamble;
        $preamble->identifier = (string) Str::uuid();
        $preamble->name = 'Test';

        $repo = Mockery::mock(PreambleRepository::class);
        $repo->shouldReceive('createPreamble')->once()->andReturn($preamble);

        $this->app->instance(PreambleRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/preambles', [
                'name' => 'Test',
                'effective_date' => '2026-12-31',
            ])
            ->assertCreated();
    });

    it('rejects invalid date for effective_date', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/preambles', [
                'name' => 'Test',
                'effective_date' => 'not-a-date',
            ])
            ->assertUnprocessable();
    });
});
