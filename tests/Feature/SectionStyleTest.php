<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Tenant\SectionStyleController;
use App\Http\Middleware\EnsureTokenMatchesTenant;
use App\Http\Middleware\InitializeTenancyByBodyParam;
use App\Http\Resources\Tenant\SectionStyle\SectionStyleResource;
use App\Models\Concerns\TenantConnection;
use App\Models\Tenant\SectionStyle;
use App\Models\User;
use App\Policies\SectionStylePolicy;
use App\Repositories\Tenant\SectionStyleRepository;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

// ---------------------------------------------------------------------------
// Route registration
// ---------------------------------------------------------------------------

describe('SectionStyle routes registration', function (): void {
    it('registers GET /v1/section-styles (index)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/section-styles' && in_array('GET', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers POST /v1/section-styles (store)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/section-styles' && in_array('POST', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers GET /v1/section-styles/{identifier} (show)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/section-styles/{identifier}' && in_array('GET', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers PUT /v1/section-styles/{identifier} (update)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/section-styles/{identifier}' && in_array('PUT', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers DELETE /v1/section-styles/{identifier} (destroy)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/section-styles/{identifier}' && in_array('DELETE', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers POST /v1/section-styles/{identifier}/restore (restore)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/section-styles/{identifier}/restore' && in_array('POST', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers exactly 6 section-style routes', function (): void {
        $count = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'v1/section-styles'))
            ->count();

        expect($count)->toBe(6);
    });

    it('section-style routes use auth:api middleware', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'v1/section-styles' && in_array('GET', $r->methods(), true));

        expect($route)->not->toBeNull();
        expect(implode(',', $route->middleware()))->toContain('auth:api');
    });

    it('section-style routes use tenant.token middleware', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'v1/section-styles' && in_array('GET', $r->methods(), true));

        expect($route)->not->toBeNull();
        expect(implode(',', $route->middleware()))->toContain('tenant.token');
    });

    it('section-style routes use the Tenant SectionStyleController', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'v1/section-styles' && in_array('GET', $r->methods(), true));

        expect($route)->not->toBeNull();
        expect($route->getActionName())->toContain(SectionStyleController::class);
    });
});

// ---------------------------------------------------------------------------
// Model
// ---------------------------------------------------------------------------

describe('SectionStyle model', function (): void {
    it('has correct fillable attributes', function (): void {
        $model = new SectionStyle;

        expect($model->getFillable())->toBe([
            'tenant_id',
            'name',
            'description',
            'columns',
            'is_active',
            'is_featured',
            'created_by',
            'updated_by',
        ]);
    });

    it('uses identifier as route key name', function (): void {
        $model = new SectionStyle;

        expect($model->getRouteKeyName())->toBe('identifier');
    });

    it('casts columns to integer', function (): void {
        $casts = (new SectionStyle)->getCasts();

        expect($casts)->toHaveKey('columns', 'integer');
    });

    it('casts is_active to boolean', function (): void {
        $casts = (new SectionStyle)->getCasts();

        expect($casts)->toHaveKey('is_active', 'boolean');
    });

    it('casts is_featured to boolean', function (): void {
        $casts = (new SectionStyle)->getCasts();

        expect($casts)->toHaveKey('is_featured', 'boolean');
    });

    it('uses SoftDeletes trait', function (): void {
        $model = new SectionStyle;

        expect(in_array(SoftDeletes::class, class_uses_recursive($model), true))->toBeTrue();
    });

    it('uses TenantConnection trait', function (): void {
        $uses = class_uses_recursive(new SectionStyle);

        expect(in_array(TenantConnection::class, $uses, true))->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// Repository
// ---------------------------------------------------------------------------

describe('SectionStyleRepository', function (): void {
    it('can be instantiated', function (): void {
        $repo = new SectionStyleRepository;

        expect($repo)->toBeInstanceOf(SectionStyleRepository::class);
    });
});

// ---------------------------------------------------------------------------
// Policy
// ---------------------------------------------------------------------------

describe('SectionStylePolicy', function (): void {
    it('is registered in the gate', function (): void {
        $policy = Gate::getPolicyFor(SectionStyle::class);

        expect($policy)->not->toBeNull();
        expect($policy)->toBeInstanceOf(SectionStylePolicy::class);
    });

    it('has before() method for super-admin bypass', function (): void {
        expect(method_exists(SectionStylePolicy::class, 'before'))->toBeTrue();
    });

    it('has viewAny() method', function (): void {
        expect(method_exists(SectionStylePolicy::class, 'viewAny'))->toBeTrue();
    });

    it('has view() method with tenant boundary', function (): void {
        expect(method_exists(SectionStylePolicy::class, 'view'))->toBeTrue();
    });

    it('has create() method', function (): void {
        expect(method_exists(SectionStylePolicy::class, 'create'))->toBeTrue();
    });

    it('has update() method with tenant boundary', function (): void {
        expect(method_exists(SectionStylePolicy::class, 'update'))->toBeTrue();
    });

    it('has delete() method with tenant boundary', function (): void {
        expect(method_exists(SectionStylePolicy::class, 'delete'))->toBeTrue();
    });

    it('has restore() method with tenant boundary', function (): void {
        expect(method_exists(SectionStylePolicy::class, 'restore'))->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// Resource
// ---------------------------------------------------------------------------

describe('SectionStyleResource', function (): void {
    it('transforms section style to correct array structure', function (): void {
        $sectionStyle = new SectionStyle([
            'identifier' => (string) Str::uuid(),
            'tenant_id' => 'test-tenant',
            'name' => 'Two Column',
            'description' => 'A two column layout',
            'columns' => 2,
            'is_active' => true,
            'is_featured' => false,
        ]);

        $resource = new SectionStyleResource($sectionStyle);
        $resolved = $resource->resolve();

        expect($resolved)->toHaveKeys([
            'identifier',
            'tenant_id',
            'name',
            'description',
            'columns',
            'is_active',
            'is_featured',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);
    });
});

// ---------------------------------------------------------------------------
// Factory
// ---------------------------------------------------------------------------

describe('SectionStyleFactory', function (): void {
    it('creates valid definition', function (): void {
        $factory = SectionStyle::factory();

        expect($factory)->toBeInstanceOf(Factory::class);

        $definition = $factory->definition();

        expect($definition)->toHaveKeys([
            'tenant_id',
            'name',
            'description',
            'columns',
            'is_active',
            'is_featured',
        ]);
    });

    it('has inactive state', function (): void {
        $factory = SectionStyle::factory()->inactive();
        $definition = $factory->raw();

        expect($definition['is_active'])->toBeFalse();
    });

    it('has active state', function (): void {
        $factory = SectionStyle::factory()->active();
        $definition = $factory->raw();

        expect($definition['is_active'])->toBeTrue();
    });

    it('has featured state', function (): void {
        $factory = SectionStyle::factory()->featured();
        $definition = $factory->raw();

        expect($definition['is_featured'])->toBeTrue();
    });

    it('defaults columns to range 1-4', function (): void {
        $definition = SectionStyle::factory()->definition();

        expect($definition['columns'])->toBeGreaterThanOrEqual(1);
        expect($definition['columns'])->toBeLessThanOrEqual(4);
    });
});

// ---------------------------------------------------------------------------
// Unauthenticated access — returns 401
// ---------------------------------------------------------------------------

describe('SectionStyle routes unauthenticated', function (): void {
    beforeEach(function (): void {
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
        ]);
    });

    it('GET /v1/section-styles returns 401 without auth', function (): void {
        $this->getJson('/v1/section-styles')->assertUnauthorized();
    });

    it('POST /v1/section-styles returns 401 without auth', function (): void {
        $this->postJson('/v1/section-styles', [])->assertUnauthorized();
    });

    it('GET /v1/section-styles/{identifier} returns 401 without auth', function (): void {
        $this->getJson('/v1/section-styles/some-identifier')->assertUnauthorized();
    });

    it('PUT /v1/section-styles/{identifier} returns 401 without auth', function (): void {
        $this->putJson('/v1/section-styles/some-identifier', [])->assertUnauthorized();
    });

    it('DELETE /v1/section-styles/{identifier} returns 401 without auth', function (): void {
        $this->deleteJson('/v1/section-styles/some-identifier')->assertUnauthorized();
    });

    it('POST /v1/section-styles/{identifier}/restore returns 401 without auth', function (): void {
        $this->postJson('/v1/section-styles/some-identifier/restore')->assertUnauthorized();
    });
});

// ---------------------------------------------------------------------------
// Permission checks — authenticated user without permission returns 403
// ---------------------------------------------------------------------------

describe('SectionStyle routes permission enforcement', function (): void {
    beforeEach(function (): void {
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            InitializeTenancyByBodyParam::class,
            EnsureTokenMatchesTenant::class,
        ]);

        Gate::before(fn () => false);
    });

    it('GET /v1/section-styles returns 403 when user lacks section-styles.view', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->getJson('/v1/section-styles')
            ->assertForbidden();
    });

    it('POST /v1/section-styles returns 403 when user lacks section-styles.create', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/section-styles', ['name' => 'Test Style'])
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// Permission granted — authenticated user WITH permission succeeds (mocked repo)
// ---------------------------------------------------------------------------

describe('SectionStyle routes with permission granted', function (): void {
    beforeEach(function (): void {
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            InitializeTenancyByBodyParam::class,
            EnsureTokenMatchesTenant::class,
        ]);

        Gate::before(fn () => true);
    });

    it('GET /v1/section-styles returns 200 with mocked paginator', function (): void {
        $sectionStyle = new SectionStyle;
        $sectionStyle->identifier = (string) Str::uuid();
        $sectionStyle->tenant_id = 'test-tenant';
        $sectionStyle->name = 'Two Column';
        $sectionStyle->description = null;
        $sectionStyle->columns = 2;
        $sectionStyle->is_active = true;
        $sectionStyle->is_featured = false;

        $paginator = new LengthAwarePaginator(
            items: [$sectionStyle],
            total: 1,
            perPage: 15,
            currentPage: 1,
        );

        $repo = Mockery::mock(SectionStyleRepository::class);
        $repo->shouldReceive('browseSectionStyles')->once()->andReturn($paginator);

        $this->app->instance(SectionStyleRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->getJson('/v1/section-styles')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    });

    it('POST /v1/section-styles returns 201 with valid data', function (): void {
        $sectionStyle = new SectionStyle;
        $sectionStyle->identifier = (string) Str::uuid();
        $sectionStyle->tenant_id = 'test-tenant';
        $sectionStyle->name = 'Three Column';
        $sectionStyle->description = 'Three equal columns';
        $sectionStyle->columns = 3;
        $sectionStyle->is_active = true;
        $sectionStyle->is_featured = false;

        $repo = Mockery::mock(SectionStyleRepository::class);
        $repo->shouldReceive('createSectionStyle')->once()->andReturn($sectionStyle);

        $this->app->instance(SectionStyleRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/section-styles', ['name' => 'Three Column'])
            ->assertCreated();
    });

    it('GET /v1/section-styles/{identifier} returns 200', function (): void {
        $identifier = 'test-identifier-abc';

        $sectionStyle = new SectionStyle;
        $sectionStyle->identifier = $identifier;
        $sectionStyle->tenant_id = 'test-tenant';
        $sectionStyle->name = 'Single Column';
        $sectionStyle->columns = 1;
        $sectionStyle->is_active = true;
        $sectionStyle->is_featured = false;

        $repo = Mockery::mock(SectionStyleRepository::class);
        $repo->shouldReceive('readSectionStyle')->with($identifier)->once()->andReturn($sectionStyle);

        $this->app->instance(SectionStyleRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->getJson("/v1/section-styles/{$identifier}")
            ->assertOk()
            ->assertJsonPath('data.identifier', $identifier);
    });

    it('PUT /v1/section-styles/{identifier} returns 200', function (): void {
        $identifier = 'test-identifier-abc';

        $sectionStyle = new SectionStyle;
        $sectionStyle->identifier = $identifier;
        $sectionStyle->tenant_id = 'test-tenant';
        $sectionStyle->name = 'Updated Style';
        $sectionStyle->columns = 4;
        $sectionStyle->is_active = true;
        $sectionStyle->is_featured = true;

        $repo = Mockery::mock(SectionStyleRepository::class);
        $repo->shouldReceive('readSectionStyle')->with($identifier)->once()->andReturn($sectionStyle);
        $repo->shouldReceive('updateSectionStyle')->with($identifier, Mockery::any())->once()->andReturn($sectionStyle);

        $this->app->instance(SectionStyleRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->putJson("/v1/section-styles/{$identifier}", ['name' => 'Updated Style'])
            ->assertOk();
    });

    it('DELETE /v1/section-styles/{identifier} returns 204', function (): void {
        $identifier = 'test-identifier-abc';

        $sectionStyle = new SectionStyle;
        $sectionStyle->identifier = $identifier;
        $sectionStyle->tenant_id = 'test-tenant';
        $sectionStyle->name = 'To Delete';

        $repo = Mockery::mock(SectionStyleRepository::class);
        $repo->shouldReceive('readSectionStyle')->with($identifier)->once()->andReturn($sectionStyle);
        $repo->shouldReceive('deleteSectionStyle')->with($identifier)->once();

        $this->app->instance(SectionStyleRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->deleteJson("/v1/section-styles/{$identifier}")
            ->assertNoContent();
    });

    it('POST /v1/section-styles/{identifier}/restore returns 200', function (): void {
        $identifier = 'test-identifier-abc';

        $sectionStyle = new SectionStyle;
        $sectionStyle->identifier = $identifier;
        $sectionStyle->tenant_id = 'test-tenant';
        $sectionStyle->name = 'Restored Style';
        $sectionStyle->columns = 2;
        $sectionStyle->is_active = true;
        $sectionStyle->is_featured = false;

        $repo = Mockery::mock(SectionStyleRepository::class);
        $repo->shouldReceive('readTrashedSectionStyle')->with($identifier)->once()->andReturn($sectionStyle);
        $repo->shouldReceive('restoreSectionStyle')->with($identifier)->once()->andReturn($sectionStyle);

        $this->app->instance(SectionStyleRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson("/v1/section-styles/{$identifier}/restore")
            ->assertOk();
    });
});

// ---------------------------------------------------------------------------
// Validation — store request
// ---------------------------------------------------------------------------

describe('SectionStyle store validation', function (): void {
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
            ->postJson('/v1/section-styles', [])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['name']]]);
    });

    it('rejects name exceeding 255 chars', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/section-styles', ['name' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['name']]]);
    });

    it('rejects columns below 1', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/section-styles', ['name' => 'Test', 'columns' => 0])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['columns']]]);
    });

    it('rejects columns above 12', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/section-styles', ['name' => 'Test', 'columns' => 13])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['columns']]]);
    });

    it('rejects non-boolean is_active', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/section-styles', ['name' => 'Test', 'is_active' => 'notabool'])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['is_active']]]);
    });

    it('accepts is_active boolean', function (): void {
        $sectionStyle = new SectionStyle;
        $sectionStyle->identifier = (string) Str::uuid();
        $sectionStyle->tenant_id = 'test-tenant';
        $sectionStyle->name = 'Test';
        $sectionStyle->is_active = false;
        $sectionStyle->is_featured = false;

        $repo = Mockery::mock(SectionStyleRepository::class);
        $repo->shouldReceive('createSectionStyle')->once()->andReturn($sectionStyle);

        $this->app->instance(SectionStyleRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/section-styles', ['name' => 'Test', 'is_active' => false])
            ->assertCreated();
    });

    it('accepts is_featured boolean', function (): void {
        $sectionStyle = new SectionStyle;
        $sectionStyle->identifier = (string) Str::uuid();
        $sectionStyle->tenant_id = 'test-tenant';
        $sectionStyle->name = 'Test';
        $sectionStyle->is_active = true;
        $sectionStyle->is_featured = true;

        $repo = Mockery::mock(SectionStyleRepository::class);
        $repo->shouldReceive('createSectionStyle')->once()->andReturn($sectionStyle);

        $this->app->instance(SectionStyleRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/section-styles', ['name' => 'Test', 'is_featured' => true])
            ->assertCreated();
    });

    it('accepts valid columns value', function (): void {
        $sectionStyle = new SectionStyle;
        $sectionStyle->identifier = (string) Str::uuid();
        $sectionStyle->tenant_id = 'test-tenant';
        $sectionStyle->name = 'Test';
        $sectionStyle->columns = 6;
        $sectionStyle->is_active = true;
        $sectionStyle->is_featured = false;

        $repo = Mockery::mock(SectionStyleRepository::class);
        $repo->shouldReceive('createSectionStyle')->once()->andReturn($sectionStyle);

        $this->app->instance(SectionStyleRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/section-styles', ['name' => 'Test', 'columns' => 6])
            ->assertCreated();
    });
});

// ---------------------------------------------------------------------------
// Validation — update request
// ---------------------------------------------------------------------------

describe('SectionStyle update validation', function (): void {
    beforeEach(function (): void {
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            InitializeTenancyByBodyParam::class,
            EnsureTokenMatchesTenant::class,
        ]);

        Gate::before(fn () => true);
    });

    it('rejects columns below 1 on update', function (): void {
        $identifier = 'test-id';

        $repo = Mockery::mock(SectionStyleRepository::class);
        $repo->shouldReceive('readSectionStyle')->never();
        $this->app->instance(SectionStyleRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->putJson("/v1/section-styles/{$identifier}", ['columns' => 0])
            ->assertUnprocessable();
    });

    it('name is optional on update (sometimes rule)', function (): void {
        $identifier = 'test-id';

        $sectionStyle = new SectionStyle;
        $sectionStyle->identifier = $identifier;
        $sectionStyle->tenant_id = 'test-tenant';
        $sectionStyle->name = 'Original Name';
        $sectionStyle->columns = 2;
        $sectionStyle->is_active = true;
        $sectionStyle->is_featured = false;

        $repo = Mockery::mock(SectionStyleRepository::class);
        $repo->shouldReceive('readSectionStyle')->with($identifier)->once()->andReturn($sectionStyle);
        $repo->shouldReceive('updateSectionStyle')->with($identifier, Mockery::any())->once()->andReturn($sectionStyle);
        $this->app->instance(SectionStyleRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->putJson("/v1/section-styles/{$identifier}", ['columns' => 4])
            ->assertOk();
    });
});
