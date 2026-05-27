<?php

declare(strict_types=1);

use App\Filters\Central\SectionStyles\SectionStyleFilters;
use App\Http\Controllers\Api\V1\Central\SectionStyleController;
use App\Http\Resources\Central\SectionStyle\SectionStyleResource;
use App\Models\Central\SectionStyle;
use App\Models\User;
use App\Policies\SectionStylePolicy;
use App\Repositories\Central\SectionStyleRepository;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use OwenIt\Auditing\Contracts\Auditable;

// ---------------------------------------------------------------------------
// Route registration
// ---------------------------------------------------------------------------

describe('SectionStyle routes registration', function (): void {
    it('registers GET /api/v1/section-styles (index)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'api/v1/section-styles' && in_array('GET', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers POST /api/v1/section-styles (store)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'api/v1/section-styles' && in_array('POST', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers GET /api/v1/section-styles/{identifier} (show)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'api/v1/section-styles/{identifier}' && in_array('GET', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers PUT /api/v1/section-styles/{identifier} (update)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'api/v1/section-styles/{identifier}' && in_array('PUT', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers DELETE /api/v1/section-styles/{identifier} (destroy)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'api/v1/section-styles/{identifier}' && in_array('DELETE', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers POST /api/v1/section-styles/{identifier}/restore (restore)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'api/v1/section-styles/{identifier}/restore' && in_array('POST', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers exactly 6 section-style routes', function (): void {
        $count = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'api/v1/section-styles'))
            ->count();

        expect($count)->toBe(6);
    });

    it('section-style routes use auth:api middleware', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'api/v1/section-styles' && in_array('GET', $r->methods(), true));

        expect($route)->not->toBeNull();
        expect(implode(',', $route->middleware()))->toContain('auth:api');
    });

    it('section-style routes use the Central SectionStyleController', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'api/v1/section-styles' && in_array('GET', $r->methods(), true));

        expect($route)->not->toBeNull();
        expect($route->getActionName())->toContain(SectionStyleController::class);
    });

    it('route names are correctly assigned', function (): void {
        $router = app('router')->getRoutes();

        expect($router->getByName('api.section-styles.index'))->not->toBeNull();
        expect($router->getByName('api.section-styles.store'))->not->toBeNull();
        expect($router->getByName('api.section-styles.show'))->not->toBeNull();
        expect($router->getByName('api.section-styles.update'))->not->toBeNull();
        expect($router->getByName('api.section-styles.destroy'))->not->toBeNull();
        expect($router->getByName('api.section-styles.restore'))->not->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// Model
// ---------------------------------------------------------------------------

describe('SectionStyle model', function (): void {
    it('has correct fillable attributes', function (): void {
        $sectionStyle = new SectionStyle;

        expect($sectionStyle->getFillable())->toBe([
            'name',
            'description',
            'columns',
            'is_active',
            'is_featured',
            'created_by',
            'updated_by',
        ]);
    });

    it('uses central connection', function (): void {
        $sectionStyle = new SectionStyle;

        expect($sectionStyle->getConnectionName())->toBe('central');
    });

    it('uses identifier as route key name', function (): void {
        $sectionStyle = new SectionStyle;

        expect($sectionStyle->getRouteKeyName())->toBe('identifier');
    });

    it('casts columns to integer', function (): void {
        $sectionStyle = new SectionStyle;
        $casts = $sectionStyle->getCasts();

        expect($casts)->toHaveKey('columns');
        expect($casts['columns'])->toBe('integer');
    });

    it('casts is_active to boolean', function (): void {
        $sectionStyle = new SectionStyle;
        $casts = $sectionStyle->getCasts();

        expect($casts)->toHaveKey('is_active');
        expect($casts['is_active'])->toBe('boolean');
    });

    it('casts is_featured to boolean', function (): void {
        $sectionStyle = new SectionStyle;
        $casts = $sectionStyle->getCasts();

        expect($casts)->toHaveKey('is_featured');
        expect($casts['is_featured'])->toBe('boolean');
    });

    it('uses soft deletes', function (): void {
        expect(in_array(SoftDeletes::class, class_uses_recursive(SectionStyle::class), true))->toBeTrue();
    });

    it('implements AuditableContract', function (): void {
        $sectionStyle = new SectionStyle;

        expect($sectionStyle)->toBeInstanceOf(Auditable::class);
    });
});

// ---------------------------------------------------------------------------
// Repository
// ---------------------------------------------------------------------------

describe('SectionStyleRepository', function (): void {
    it('can be instantiated', function (): void {
        $repository = app(SectionStyleRepository::class);

        expect($repository)->toBeInstanceOf(SectionStyleRepository::class);
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

    it('has create() method', function (): void {
        expect(method_exists(SectionStylePolicy::class, 'create'))->toBeTrue();
    });

    it('has update() method', function (): void {
        expect(method_exists(SectionStylePolicy::class, 'update'))->toBeTrue();
    });

    it('has delete() method', function (): void {
        expect(method_exists(SectionStylePolicy::class, 'delete'))->toBeTrue();
    });

    it('has restore() method', function (): void {
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

    it('returns correct values', function (): void {
        $identifier = (string) Str::uuid();

        $sectionStyle = new SectionStyle([
            'identifier' => $identifier,
            'name' => 'Three Column',
            'description' => 'Three equal columns',
            'columns' => 3,
            'is_active' => true,
            'is_featured' => true,
        ]);

        $resource = new SectionStyleResource($sectionStyle);
        $resolved = $resource->resolve();

        expect($resolved['identifier'])->toBe($identifier);
        expect($resolved['name'])->toBe('Three Column');
        expect($resolved['columns'])->toBe(3);
        expect($resolved['is_active'])->toBeTrue();
        expect($resolved['is_featured'])->toBeTrue();
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
            'identifier',
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

    it('generates uuid identifier', function (): void {
        $definition = SectionStyle::factory()->definition();

        expect($definition['identifier'])->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
    });
});

// ---------------------------------------------------------------------------
// Filters
// ---------------------------------------------------------------------------

describe('SectionStyleFilters', function (): void {
    it('can be instantiated from request', function (): void {
        $request = new Request;
        $filters = SectionStyleFilters::fromRequest($request);

        expect($filters)->toBeInstanceOf(SectionStyleFilters::class);
    });

    it('accepts search filter', function (): void {
        $request = new Request(['search' => 'two column']);
        $filters = SectionStyleFilters::fromRequest($request);

        expect($filters)->toBeInstanceOf(SectionStyleFilters::class);
    });

    it('accepts is_active filter', function (): void {
        $request = new Request(['is_active' => '1']);
        $filters = SectionStyleFilters::fromRequest($request);

        expect($filters)->toBeInstanceOf(SectionStyleFilters::class);
    });

    it('accepts is_featured filter', function (): void {
        $request = new Request(['is_featured' => '1']);
        $filters = SectionStyleFilters::fromRequest($request);

        expect($filters)->toBeInstanceOf(SectionStyleFilters::class);
    });
});

// ---------------------------------------------------------------------------
// Unauthenticated access — returns 401
// ---------------------------------------------------------------------------

describe('SectionStyle routes unauthenticated', function (): void {
    it('GET /api/v1/section-styles returns 401 without auth', function (): void {
        $this->getJson('/api/v1/section-styles')->assertUnauthorized();
    });

    it('POST /api/v1/section-styles returns 401 without auth', function (): void {
        $this->postJson('/api/v1/section-styles', [])->assertUnauthorized();
    });

    it('GET /api/v1/section-styles/{identifier} returns 401 without auth', function (): void {
        $this->getJson('/api/v1/section-styles/some-identifier')->assertUnauthorized();
    });

    it('PUT /api/v1/section-styles/{identifier} returns 401 without auth', function (): void {
        $this->putJson('/api/v1/section-styles/some-identifier', [])->assertUnauthorized();
    });

    it('DELETE /api/v1/section-styles/{identifier} returns 401 without auth', function (): void {
        $this->deleteJson('/api/v1/section-styles/some-identifier')->assertUnauthorized();
    });

    it('POST /api/v1/section-styles/{identifier}/restore returns 401 without auth', function (): void {
        $this->postJson('/api/v1/section-styles/some-identifier/restore')->assertUnauthorized();
    });
});

// ---------------------------------------------------------------------------
// Permission checks — authenticated user without permission returns 403
// ---------------------------------------------------------------------------

describe('SectionStyle routes permission enforcement', function (): void {
    beforeEach(function (): void {
        Gate::before(fn () => false);
    });

    it('GET /api/v1/section-styles returns 403 when user lacks section_styles.view', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->getJson('/api/v1/section-styles')
            ->assertForbidden();
    });

    it('POST /api/v1/section-styles returns 403 when user lacks section_styles.create', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/section-styles', ['name' => 'Test Style'])
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// Permission granted — authenticated user WITH permission succeeds (mocked repo)
// ---------------------------------------------------------------------------

describe('SectionStyle routes with permission granted', function (): void {
    beforeEach(function (): void {
        Gate::before(fn () => true);
    });

    it('GET /api/v1/section-styles returns 200 with mocked paginator', function (): void {
        $sectionStyle = new SectionStyle;
        $sectionStyle->identifier = (string) Str::uuid();
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
            ->getJson('/api/v1/section-styles')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    });

    it('POST /api/v1/section-styles returns 201 with valid data', function (): void {
        $sectionStyle = new SectionStyle;
        $sectionStyle->identifier = (string) Str::uuid();
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
            ->postJson('/api/v1/section-styles', ['name' => 'Three Column'])
            ->assertCreated();
    });

    it('GET /api/v1/section-styles/{identifier} returns 200', function (): void {
        $identifier = 'test-identifier-abc';

        $sectionStyle = new SectionStyle;
        $sectionStyle->identifier = $identifier;
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
            ->getJson("/api/v1/section-styles/{$identifier}")
            ->assertOk()
            ->assertJsonPath('data.identifier', $identifier);
    });

    it('PUT /api/v1/section-styles/{identifier} returns 200', function (): void {
        $identifier = 'test-identifier-abc';

        $sectionStyle = new SectionStyle;
        $sectionStyle->identifier = $identifier;
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
            ->putJson("/api/v1/section-styles/{$identifier}", ['name' => 'Updated Style'])
            ->assertOk();
    });

    it('DELETE /api/v1/section-styles/{identifier} returns 204', function (): void {
        $identifier = 'test-identifier-abc';

        $sectionStyle = new SectionStyle;
        $sectionStyle->identifier = $identifier;
        $sectionStyle->name = 'To Delete';

        $repo = Mockery::mock(SectionStyleRepository::class);
        $repo->shouldReceive('readSectionStyle')->with($identifier)->once()->andReturn($sectionStyle);
        $repo->shouldReceive('deleteSectionStyle')->with($identifier)->once();

        $this->app->instance(SectionStyleRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->deleteJson("/api/v1/section-styles/{$identifier}")
            ->assertNoContent();
    });

    it('POST /api/v1/section-styles/{identifier}/restore returns 200', function (): void {
        $identifier = 'test-identifier-abc';

        $sectionStyle = new SectionStyle;
        $sectionStyle->identifier = $identifier;
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
            ->postJson("/api/v1/section-styles/{$identifier}/restore")
            ->assertOk();
    });
});

// ---------------------------------------------------------------------------
// Validation — store request
// ---------------------------------------------------------------------------

describe('SectionStyle store validation', function (): void {
    beforeEach(function (): void {
        Gate::before(fn () => true);
    });

    it('rejects missing name', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/section-styles', [])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['name']]]);
    });

    it('rejects name exceeding 255 chars', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/section-styles', ['name' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['name']]]);
    });

    it('rejects columns below 1', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/section-styles', ['name' => 'Test', 'columns' => 0])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['columns']]]);
    });

    it('rejects columns above 12', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/section-styles', ['name' => 'Test', 'columns' => 13])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['columns']]]);
    });

    it('accepts optional description', function (): void {
        $sectionStyle = new SectionStyle;
        $sectionStyle->identifier = (string) Str::uuid();
        $sectionStyle->name = 'Test';
        $sectionStyle->description = 'A test layout';
        $sectionStyle->columns = 1;
        $sectionStyle->is_active = true;
        $sectionStyle->is_featured = false;

        $repo = Mockery::mock(SectionStyleRepository::class);
        $repo->shouldReceive('createSectionStyle')->once()->andReturn($sectionStyle);
        $this->app->instance(SectionStyleRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/section-styles', [
                'name' => 'Test',
                'description' => 'A test layout',
            ])
            ->assertCreated();
    });

    it('accepts is_active as boolean', function (): void {
        $sectionStyle = new SectionStyle;
        $sectionStyle->identifier = (string) Str::uuid();
        $sectionStyle->name = 'Inactive Style';
        $sectionStyle->columns = 1;
        $sectionStyle->is_active = false;
        $sectionStyle->is_featured = false;

        $repo = Mockery::mock(SectionStyleRepository::class);
        $repo->shouldReceive('createSectionStyle')->once()->andReturn($sectionStyle);
        $this->app->instance(SectionStyleRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/section-styles', ['name' => 'Inactive Style', 'is_active' => false])
            ->assertCreated();
    });

    it('accepts is_featured as boolean', function (): void {
        $sectionStyle = new SectionStyle;
        $sectionStyle->identifier = (string) Str::uuid();
        $sectionStyle->name = 'Featured Style';
        $sectionStyle->columns = 1;
        $sectionStyle->is_active = true;
        $sectionStyle->is_featured = true;

        $repo = Mockery::mock(SectionStyleRepository::class);
        $repo->shouldReceive('createSectionStyle')->once()->andReturn($sectionStyle);
        $this->app->instance(SectionStyleRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/section-styles', ['name' => 'Featured Style', 'is_featured' => true])
            ->assertCreated();
    });

    it('rejects non-boolean is_active', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/section-styles', ['name' => 'Test', 'is_active' => 'notabool'])
            ->assertUnprocessable();
    });
});

// ---------------------------------------------------------------------------
// Validation — update request
// ---------------------------------------------------------------------------

describe('SectionStyle update validation', function (): void {
    beforeEach(function (): void {
        Gate::before(fn () => true);
    });

    it('rejects columns below 1 on update', function (): void {
        $identifier = 'test-id';

        $sectionStyle = new SectionStyle;
        $sectionStyle->identifier = $identifier;
        $sectionStyle->name = 'Test';
        $sectionStyle->columns = 1;

        $repo = Mockery::mock(SectionStyleRepository::class);
        $repo->shouldReceive('readSectionStyle')->with($identifier)->once()->andReturn($sectionStyle);
        $this->app->instance(SectionStyleRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->putJson("/api/v1/section-styles/{$identifier}", ['columns' => 0])
            ->assertUnprocessable();
    });

    it('name is optional on update (sometimes rule)', function (): void {
        $identifier = 'test-id';

        $sectionStyle = new SectionStyle;
        $sectionStyle->identifier = $identifier;
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
            ->putJson("/api/v1/section-styles/{$identifier}", ['columns' => 4])
            ->assertOk();
    });
});
