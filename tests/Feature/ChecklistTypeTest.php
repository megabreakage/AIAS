<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Tenant\ChecklistTypeController;
use App\Http\Middleware\EnsureTokenMatchesTenant;
use App\Http\Middleware\InitializeTenancyByBodyParam;
use App\Models\Tenant\ChecklistType;
use App\Models\User;
use App\Repositories\Tenant\ChecklistTypeRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

// ---------------------------------------------------------------------------
// Route registration
// ---------------------------------------------------------------------------

describe('ChecklistType routes registration', function (): void {
    it('registers GET /v1/checklist-types (index)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/checklist-types' && in_array('GET', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers POST /v1/checklist-types (store)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/checklist-types' && in_array('POST', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers GET /v1/checklist-types/{identifier} (show)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/checklist-types/{identifier}' && in_array('GET', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers PUT /v1/checklist-types/{identifier} (update)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/checklist-types/{identifier}' && in_array('PUT', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers DELETE /v1/checklist-types/{identifier} (destroy)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/checklist-types/{identifier}' && in_array('DELETE', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers POST /v1/checklist-types/{identifier}/restore (restore)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/checklist-types/{identifier}/restore' && in_array('POST', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers exactly 6 checklist-type routes', function (): void {
        $count = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'v1/checklist-types'))
            ->count();

        expect($count)->toBe(6);
    });

    it('checklist-type routes use auth:api middleware', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'v1/checklist-types' && in_array('GET', $r->methods(), true));

        expect($route)->not->toBeNull();
        expect(implode(',', $route->middleware()))->toContain('auth:api');
    });

    it('checklist-type routes use tenant.token middleware', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'v1/checklist-types' && in_array('GET', $r->methods(), true));

        expect($route)->not->toBeNull();
        expect(implode(',', $route->middleware()))->toContain('tenant.token');
    });

    it('checklist-type routes use the Tenant ChecklistTypeController', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'v1/checklist-types' && in_array('GET', $r->methods(), true));

        expect($route)->not->toBeNull();
        expect($route->getActionName())->toContain(ChecklistTypeController::class);
    });
});

// ---------------------------------------------------------------------------
// Unauthenticated access — returns 401
// ---------------------------------------------------------------------------

describe('ChecklistType routes unauthenticated', function (): void {
    beforeEach(function (): void {
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
        ]);
    });

    it('GET /v1/checklist-types returns 401 without auth', function (): void {
        $this->getJson('/v1/checklist-types')->assertUnauthorized();
    });

    it('POST /v1/checklist-types returns 401 without auth', function (): void {
        $this->postJson('/v1/checklist-types', [])->assertUnauthorized();
    });

    it('GET /v1/checklist-types/{identifier} returns 401 without auth', function (): void {
        $this->getJson('/v1/checklist-types/some-identifier')->assertUnauthorized();
    });

    it('PUT /v1/checklist-types/{identifier} returns 401 without auth', function (): void {
        $this->putJson('/v1/checklist-types/some-identifier', [])->assertUnauthorized();
    });

    it('DELETE /v1/checklist-types/{identifier} returns 401 without auth', function (): void {
        $this->deleteJson('/v1/checklist-types/some-identifier')->assertUnauthorized();
    });

    it('POST /v1/checklist-types/{identifier}/restore returns 401 without auth', function (): void {
        $this->postJson('/v1/checklist-types/some-identifier/restore')->assertUnauthorized();
    });
});

// ---------------------------------------------------------------------------
// Permission checks — authenticated user without permission returns 403
// ---------------------------------------------------------------------------

describe('ChecklistType routes permission enforcement', function (): void {
    beforeEach(function (): void {
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            InitializeTenancyByBodyParam::class,
            EnsureTokenMatchesTenant::class,
        ]);

        Gate::before(fn () => false);
    });

    it('GET /v1/checklist-types returns 403 when user lacks checklist-types.view', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->getJson('/v1/checklist-types')
            ->assertForbidden();
    });

    it('POST /v1/checklist-types returns 403 when user lacks checklist-types.create', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/checklist-types', ['name' => 'Test Type'])
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// Permission granted — authenticated user WITH permission succeeds (mocked repo)
// ---------------------------------------------------------------------------

describe('ChecklistType routes with permission granted', function (): void {
    beforeEach(function (): void {
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            InitializeTenancyByBodyParam::class,
            EnsureTokenMatchesTenant::class,
        ]);

        Gate::before(fn () => true);
    });

    it('GET /v1/checklist-types returns 200 with mocked paginator', function (): void {
        $checklistType = new ChecklistType;
        $checklistType->identifier = (string) Str::uuid();
        $checklistType->tenant_id = 'test-tenant';
        $checklistType->name = 'Risk Assessment';
        $checklistType->is_active = true;
        $checklistType->is_featured = false;

        $paginator = new LengthAwarePaginator(
            items: [$checklistType],
            total: 1,
            perPage: 15,
            currentPage: 1,
        );

        $repo = Mockery::mock(ChecklistTypeRepository::class);
        $repo->shouldReceive('browseChecklistTypes')->once()->andReturn($paginator);

        $this->app->instance(ChecklistTypeRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->getJson('/v1/checklist-types')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    });

    it('POST /v1/checklist-types returns 201 with valid data', function (): void {
        $checklistType = new ChecklistType;
        $checklistType->identifier = (string) Str::uuid();
        $checklistType->tenant_id = 'test-tenant';
        $checklistType->name = 'Quality Control';
        $checklistType->is_active = true;
        $checklistType->is_featured = false;

        $repo = Mockery::mock(ChecklistTypeRepository::class);
        $repo->shouldReceive('createChecklistType')->once()->andReturn($checklistType);

        $this->app->instance(ChecklistTypeRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/checklist-types', ['name' => 'Quality Control'])
            ->assertCreated();
    });

    it('GET /v1/checklist-types/{identifier} returns 200', function (): void {
        $checklistType = new ChecklistType;
        $checklistType->identifier = 'test-identifier-123';
        $checklistType->tenant_id = 'test-tenant';
        $checklistType->name = 'Quality Control';
        $checklistType->is_active = true;
        $checklistType->is_featured = false;

        $repo = Mockery::mock(ChecklistTypeRepository::class);
        $repo->shouldReceive('readChecklistType')->with('test-identifier-123')->once()->andReturn($checklistType);

        $this->app->instance(ChecklistTypeRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->getJson('/v1/checklist-types/test-identifier-123')
            ->assertOk();
    });

    it('PUT /v1/checklist-types/{identifier} returns 200', function (): void {
        $checklistType = new ChecklistType;
        $checklistType->identifier = 'test-identifier-123';
        $checklistType->tenant_id = 'test-tenant';
        $checklistType->name = 'Updated Name';
        $checklistType->is_active = true;
        $checklistType->is_featured = true;

        $repo = Mockery::mock(ChecklistTypeRepository::class);
        $repo->shouldReceive('readChecklistType')->with('test-identifier-123')->once()->andReturn($checklistType);
        $repo->shouldReceive('updateChecklistType')->with('test-identifier-123', Mockery::any())->once()->andReturn($checklistType);

        $this->app->instance(ChecklistTypeRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->putJson('/v1/checklist-types/test-identifier-123', ['name' => 'Updated Name'])
            ->assertOk();
    });

    it('DELETE /v1/checklist-types/{identifier} returns 204', function (): void {
        $checklistType = new ChecklistType;
        $checklistType->identifier = 'test-identifier-123';
        $checklistType->tenant_id = 'test-tenant';
        $checklistType->name = 'To Delete';

        $repo = Mockery::mock(ChecklistTypeRepository::class);
        $repo->shouldReceive('readChecklistType')->with('test-identifier-123')->once()->andReturn($checklistType);
        $repo->shouldReceive('deleteChecklistType')->with('test-identifier-123')->once();

        $this->app->instance(ChecklistTypeRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->deleteJson('/v1/checklist-types/test-identifier-123')
            ->assertNoContent();
    });

    it('POST /v1/checklist-types/{identifier}/restore returns 200', function (): void {
        $checklistType = new ChecklistType;
        $checklistType->identifier = 'test-identifier-123';
        $checklistType->tenant_id = 'test-tenant';
        $checklistType->name = 'Restored Type';
        $checklistType->is_active = true;
        $checklistType->is_featured = false;

        $repo = Mockery::mock(ChecklistTypeRepository::class);
        $repo->shouldReceive('readTrashedChecklistType')->with('test-identifier-123')->once()->andReturn($checklistType);
        $repo->shouldReceive('restoreChecklistType')->with('test-identifier-123')->once()->andReturn($checklistType);

        $this->app->instance(ChecklistTypeRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/checklist-types/test-identifier-123/restore')
            ->assertOk();
    });
});

// ---------------------------------------------------------------------------
// Validation — store request
// ---------------------------------------------------------------------------

describe('ChecklistType store validation', function (): void {
    beforeEach(function (): void {
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            InitializeTenancyByBodyParam::class,
            EnsureTokenMatchesTenant::class,
        ]);

        Gate::before(fn () => true);
    });

    it('rejects missing name', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/checklist-types', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('rejects name exceeding 255 chars', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/checklist-types', ['name' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('accepts is_active boolean', function (): void {
        $checklistType = new ChecklistType;
        $checklistType->identifier = (string) Str::uuid();
        $checklistType->name = 'Test';
        $checklistType->is_active = false;
        $checklistType->is_featured = false;

        $repo = Mockery::mock(ChecklistTypeRepository::class);
        $repo->shouldReceive('createChecklistType')->once()->andReturn($checklistType);

        $this->app->instance(ChecklistTypeRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/checklist-types', ['name' => 'Test', 'is_active' => false])
            ->assertCreated();
    });

    it('accepts is_featured boolean', function (): void {
        $checklistType = new ChecklistType;
        $checklistType->identifier = (string) Str::uuid();
        $checklistType->name = 'Test';
        $checklistType->is_active = true;
        $checklistType->is_featured = true;

        $repo = Mockery::mock(ChecklistTypeRepository::class);
        $repo->shouldReceive('createChecklistType')->once()->andReturn($checklistType);

        $this->app->instance(ChecklistTypeRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/checklist-types', ['name' => 'Test', 'is_featured' => true])
            ->assertCreated();
    });
});
