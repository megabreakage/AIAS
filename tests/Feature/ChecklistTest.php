<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Tenant\ChecklistController;
use App\Http\Middleware\EnsureTokenMatchesTenant;
use App\Http\Middleware\InitializeTenancyByBodyParam;
use App\Http\Resources\Tenant\Checklist\ChecklistResource;
use App\Models\Concerns\TenantConnection;
use App\Models\Tenant\Checklist;
use App\Models\User;
use App\Policies\ChecklistPolicy;
use App\Repositories\Tenant\ChecklistRepository;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/**
 * Ensure the checklists table exists in the central testing database.
 * Required for Rule::unique validation queries in form requests.
 */
function ensureChecklistsTable(): void
{
    if (!Schema::hasTable('checklists')) {
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant/2026_05_27_000003_create_checklists_table.php',
            '--realpath' => false,
            '--force' => true,
        ]);
    }
}

// ---------------------------------------------------------------------------
// Route registration
// ---------------------------------------------------------------------------

describe('Checklist routes registration', function (): void {
    it('registers GET /v1/checklists (index)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/checklists' && in_array('GET', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers POST /v1/checklists (store)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/checklists' && in_array('POST', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers GET /v1/checklists/{identifier} (show)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/checklists/{identifier}' && in_array('GET', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers PUT /v1/checklists/{identifier} (update)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/checklists/{identifier}' && in_array('PUT', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers DELETE /v1/checklists/{identifier} (destroy)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/checklists/{identifier}' && in_array('DELETE', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers POST /v1/checklists/{identifier}/restore (restore)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/checklists/{identifier}/restore' && in_array('POST', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers exactly 6 checklist routes', function (): void {
        $count = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'v1/checklists'))
            ->count();

        expect($count)->toBe(6);
    });

    it('checklist routes use auth:api middleware', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'v1/checklists' && in_array('GET', $r->methods(), true));

        expect($route)->not->toBeNull();
        expect(implode(',', $route->middleware()))->toContain('auth:api');
    });

    it('checklist routes use tenant.token middleware', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'v1/checklists' && in_array('GET', $r->methods(), true));

        expect($route)->not->toBeNull();
        expect(implode(',', $route->middleware()))->toContain('tenant.token');
    });

    it('checklist routes use the Tenant ChecklistController', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'v1/checklists' && in_array('GET', $r->methods(), true));

        expect($route)->not->toBeNull();
        expect($route->getActionName())->toContain(ChecklistController::class);
    });
});

// ---------------------------------------------------------------------------
// Model
// ---------------------------------------------------------------------------

describe('Checklist model', function (): void {
    it('has correct fillable attributes', function (): void {
        $model = new Checklist;

        expect($model->getFillable())->toBe([
            'tenant_id',
            'reference_number',
            'name',
            'quality_controller_id',
            'preamble_id',
            'checklist_type_id',
            'is_active',
            'is_featured',
            'created_by',
            'updated_by',
        ]);
    });

    it('uses identifier as route key name', function (): void {
        $model = new Checklist;

        expect($model->getRouteKeyName())->toBe('identifier');
    });

    it('casts is_active to boolean', function (): void {
        $casts = (new Checklist)->getCasts();

        expect($casts)->toHaveKey('is_active', 'boolean');
    });

    it('casts is_featured to boolean', function (): void {
        $casts = (new Checklist)->getCasts();

        expect($casts)->toHaveKey('is_featured', 'boolean');
    });

    it('casts preamble_id to integer', function (): void {
        $casts = (new Checklist)->getCasts();

        expect($casts)->toHaveKey('preamble_id', 'integer');
    });

    it('casts checklist_type_id to integer', function (): void {
        $casts = (new Checklist)->getCasts();

        expect($casts)->toHaveKey('checklist_type_id', 'integer');
    });

    it('uses SoftDeletes trait', function (): void {
        $model = new Checklist;

        expect(in_array(SoftDeletes::class, class_uses_recursive($model), true))->toBeTrue();
    });

    it('uses TenantConnection trait', function (): void {
        $uses = class_uses_recursive(new Checklist);

        expect(in_array(TenantConnection::class, $uses, true))->toBeTrue();
    });

    it('has preamble relationship method', function (): void {
        expect(method_exists(Checklist::class, 'preamble'))->toBeTrue();
    });

    it('has checklistType relationship method', function (): void {
        expect(method_exists(Checklist::class, 'checklistType'))->toBeTrue();
    });

    it('has qualityController relationship method', function (): void {
        expect(method_exists(Checklist::class, 'qualityController'))->toBeTrue();
    });

    it('generates reference number with CL prefix', function (): void {
        $checklist = new Checklist;
        $checklist->id = 42;
        $checklist->preamble_id = null;

        $ref = $checklist->generateReferenceNumber();

        expect($ref)->toStartWith('CL-42-');
    });

    it('includes preamble_id in reference number when set', function (): void {
        $checklist = new Checklist;
        $checklist->id = 10;
        $checklist->preamble_id = 7;

        $ref = $checklist->generateReferenceNumber();

        expect($ref)->toStartWith('CL-7-');
    });
});

// ---------------------------------------------------------------------------
// Repository
// ---------------------------------------------------------------------------

describe('ChecklistRepository', function (): void {
    it('can be instantiated', function (): void {
        $repo = new ChecklistRepository;

        expect($repo)->toBeInstanceOf(ChecklistRepository::class);
    });
});

// ---------------------------------------------------------------------------
// Policy
// ---------------------------------------------------------------------------

describe('ChecklistPolicy', function (): void {
    it('is registered in the gate', function (): void {
        $policy = Gate::getPolicyFor(Checklist::class);

        expect($policy)->not->toBeNull();
        expect($policy)->toBeInstanceOf(ChecklistPolicy::class);
    });

    it('has before() method for super-admin bypass', function (): void {
        expect(method_exists(ChecklistPolicy::class, 'before'))->toBeTrue();
    });

    it('has viewAny() method', function (): void {
        expect(method_exists(ChecklistPolicy::class, 'viewAny'))->toBeTrue();
    });

    it('has view() method with tenant boundary', function (): void {
        expect(method_exists(ChecklistPolicy::class, 'view'))->toBeTrue();
    });

    it('has create() method', function (): void {
        expect(method_exists(ChecklistPolicy::class, 'create'))->toBeTrue();
    });

    it('has update() method with tenant boundary', function (): void {
        expect(method_exists(ChecklistPolicy::class, 'update'))->toBeTrue();
    });

    it('has delete() method with tenant boundary', function (): void {
        expect(method_exists(ChecklistPolicy::class, 'delete'))->toBeTrue();
    });

    it('has restore() method with tenant boundary', function (): void {
        expect(method_exists(ChecklistPolicy::class, 'restore'))->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// Resource
// ---------------------------------------------------------------------------

describe('ChecklistResource', function (): void {
    it('transforms checklist to correct array structure', function (): void {
        $checklist = new Checklist([
            'identifier' => (string) Str::uuid(),
            'tenant_id' => 'test-tenant',
            'reference_number' => 'CL-1-123456789',
            'name' => 'Q1 Audit Checklist',
            'quality_controller_id' => null,
            'preamble_id' => null,
            'checklist_type_id' => null,
            'is_active' => true,
            'is_featured' => false,
        ]);

        $resource = new ChecklistResource($checklist);
        $resolved = $resource->resolve();

        expect($resolved)->toHaveKeys([
            'identifier',
            'tenant_id',
            'reference_number',
            'name',
            'quality_controller_id',
            'preamble_id',
            'checklist_type_id',
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

describe('ChecklistFactory', function (): void {
    it('creates valid definition', function (): void {
        $factory = Checklist::factory();

        expect($factory)->toBeInstanceOf(Factory::class);

        $definition = $factory->definition();

        expect($definition)->toHaveKeys([
            'tenant_id',
            'name',
            'is_active',
            'is_featured',
        ]);
    });

    it('has inactive state', function (): void {
        $factory = Checklist::factory()->inactive();
        $definition = $factory->raw();

        expect($definition['is_active'])->toBeFalse();
    });

    it('has active state', function (): void {
        $factory = Checklist::factory()->active();
        $definition = $factory->raw();

        expect($definition['is_active'])->toBeTrue();
    });

    it('has featured state', function (): void {
        $factory = Checklist::factory()->featured();
        $definition = $factory->raw();

        expect($definition['is_featured'])->toBeTrue();
    });

    it('defaults quality_controller_id to null', function (): void {
        $definition = Checklist::factory()->definition();

        expect($definition['quality_controller_id'])->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// Unauthenticated access — returns 401
// ---------------------------------------------------------------------------

describe('Checklist routes unauthenticated', function (): void {
    beforeEach(function (): void {
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
        ]);
    });

    it('GET /v1/checklists returns 401 without auth', function (): void {
        $this->getJson('/v1/checklists')->assertUnauthorized();
    });

    it('POST /v1/checklists returns 401 without auth', function (): void {
        $this->postJson('/v1/checklists', [])->assertUnauthorized();
    });

    it('GET /v1/checklists/{identifier} returns 401 without auth', function (): void {
        $this->getJson('/v1/checklists/some-identifier')->assertUnauthorized();
    });

    it('PUT /v1/checklists/{identifier} returns 401 without auth', function (): void {
        $this->putJson('/v1/checklists/some-identifier', [])->assertUnauthorized();
    });

    it('DELETE /v1/checklists/{identifier} returns 401 without auth', function (): void {
        $this->deleteJson('/v1/checklists/some-identifier')->assertUnauthorized();
    });

    it('POST /v1/checklists/{identifier}/restore returns 401 without auth', function (): void {
        $this->postJson('/v1/checklists/some-identifier/restore')->assertUnauthorized();
    });
});

// ---------------------------------------------------------------------------
// Permission checks — authenticated user without permission returns 403
// ---------------------------------------------------------------------------

describe('Checklist routes permission enforcement', function (): void {
    beforeEach(function (): void {
        ensureChecklistsTable();

        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            InitializeTenancyByBodyParam::class,
            EnsureTokenMatchesTenant::class,
        ]);

        Gate::before(fn () => false);
    });

    it('GET /v1/checklists returns 403 when user lacks checklists.view', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->getJson('/v1/checklists')
            ->assertForbidden();
    });

    it('POST /v1/checklists returns 403 when user lacks checklists.create', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/checklists', ['name' => 'Test Checklist'])
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// Permission granted — authenticated user WITH permission succeeds (mocked repo)
// ---------------------------------------------------------------------------

describe('Checklist routes with permission granted', function (): void {
    beforeEach(function (): void {
        ensureChecklistsTable();

        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            InitializeTenancyByBodyParam::class,
            EnsureTokenMatchesTenant::class,
        ]);

        Gate::before(fn () => true);
    });

    it('GET /v1/checklists returns 200 with mocked paginator', function (): void {
        $checklist = new Checklist;
        $checklist->identifier = (string) Str::uuid();
        $checklist->tenant_id = 'test-tenant';
        $checklist->name = 'Q1 Audit Checklist';
        $checklist->reference_number = 'CL-1-123456789';
        $checklist->quality_controller_id = null;
        $checklist->preamble_id = null;
        $checklist->checklist_type_id = null;
        $checklist->is_active = true;
        $checklist->is_featured = false;

        $paginator = new LengthAwarePaginator(
            items: [$checklist],
            total: 1,
            perPage: 15,
            currentPage: 1,
        );

        $repo = Mockery::mock(ChecklistRepository::class);
        $repo->shouldReceive('browseChecklists')->once()->andReturn($paginator);

        $this->app->instance(ChecklistRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->getJson('/v1/checklists')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    });

    it('POST /v1/checklists returns 201 with valid data', function (): void {
        $checklist = new Checklist;
        $checklist->identifier = (string) Str::uuid();
        $checklist->tenant_id = 'test-tenant';
        $checklist->name = 'New Checklist';
        $checklist->reference_number = 'CL-1-123456789';
        $checklist->quality_controller_id = null;
        $checklist->preamble_id = null;
        $checklist->checklist_type_id = null;
        $checklist->is_active = true;
        $checklist->is_featured = false;

        $repo = Mockery::mock(ChecklistRepository::class);
        $repo->shouldReceive('createChecklist')->once()->andReturn($checklist);

        $this->app->instance(ChecklistRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/checklists', ['name' => 'New Checklist'])
            ->assertCreated();
    });

    it('GET /v1/checklists/{identifier} returns 200', function (): void {
        $identifier = 'test-checklist-abc';

        $checklist = new Checklist;
        $checklist->identifier = $identifier;
        $checklist->tenant_id = 'test-tenant';
        $checklist->name = 'Q2 Audit';
        $checklist->reference_number = 'CL-1-123456789';
        $checklist->is_active = true;
        $checklist->is_featured = false;

        $repo = Mockery::mock(ChecklistRepository::class);
        $repo->shouldReceive('readChecklist')->with($identifier)->once()->andReturn($checklist);

        $this->app->instance(ChecklistRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->getJson("/v1/checklists/{$identifier}")
            ->assertOk()
            ->assertJsonPath('data.identifier', $identifier);
    });

    it('PUT /v1/checklists/{identifier} returns 200', function (): void {
        $identifier = 'test-checklist-abc';

        $checklist = new Checklist;
        $checklist->identifier = $identifier;
        $checklist->tenant_id = 'test-tenant';
        $checklist->name = 'Updated Checklist';
        $checklist->reference_number = 'CL-1-123456789';
        $checklist->is_active = true;
        $checklist->is_featured = false;

        $repo = Mockery::mock(ChecklistRepository::class);
        $repo->shouldReceive('readChecklist')->with($identifier)->once()->andReturn($checklist);
        $repo->shouldReceive('updateChecklist')->with($identifier, Mockery::any())->once()->andReturn($checklist);

        $this->app->instance(ChecklistRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->putJson("/v1/checklists/{$identifier}", ['name' => 'Updated Checklist'])
            ->assertOk();
    });

    it('DELETE /v1/checklists/{identifier} returns 204', function (): void {
        $identifier = 'test-checklist-abc';

        $checklist = new Checklist;
        $checklist->identifier = $identifier;
        $checklist->tenant_id = 'test-tenant';
        $checklist->name = 'To Delete';

        $repo = Mockery::mock(ChecklistRepository::class);
        $repo->shouldReceive('readChecklist')->with($identifier)->once()->andReturn($checklist);
        $repo->shouldReceive('deleteChecklist')->with($identifier)->once();

        $this->app->instance(ChecklistRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->deleteJson("/v1/checklists/{$identifier}")
            ->assertNoContent();
    });

    it('POST /v1/checklists/{identifier}/restore returns 200', function (): void {
        $identifier = 'test-checklist-abc';

        $checklist = new Checklist;
        $checklist->identifier = $identifier;
        $checklist->tenant_id = 'test-tenant';
        $checklist->name = 'Restored Checklist';
        $checklist->reference_number = 'CL-1-123456789';
        $checklist->is_active = true;
        $checklist->is_featured = false;

        $repo = Mockery::mock(ChecklistRepository::class);
        $repo->shouldReceive('readTrashedChecklist')->with($identifier)->once()->andReturn($checklist);
        $repo->shouldReceive('restoreChecklist')->with($identifier)->once()->andReturn($checklist);

        $this->app->instance(ChecklistRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson("/v1/checklists/{$identifier}/restore")
            ->assertOk();
    });
});

// ---------------------------------------------------------------------------
// Validation — store request
// ---------------------------------------------------------------------------

describe('Checklist store validation', function (): void {
    beforeEach(function (): void {
        ensureChecklistsTable();

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
            ->postJson('/v1/checklists', [])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['name']]]);
    });

    it('rejects name exceeding 255 chars', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/checklists', ['name' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['name']]]);
    });

    it('rejects non-integer preamble_id', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/checklists', ['name' => 'Test', 'preamble_id' => 'not-an-integer'])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['preamble_id']]]);
    });

    it('rejects non-integer checklist_type_id', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/checklists', ['name' => 'Test', 'checklist_type_id' => 'not-an-integer'])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['checklist_type_id']]]);
    });

    it('rejects non-boolean is_active', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/checklists', ['name' => 'Test', 'is_active' => 'notabool'])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['is_active']]]);
    });

    it('accepts nullable preamble_id', function (): void {
        $checklist = new Checklist;
        $checklist->identifier = (string) Str::uuid();
        $checklist->tenant_id = 'test-tenant';
        $checklist->name = 'Test Checklist';
        $checklist->reference_number = 'CL-1-123456789';
        $checklist->preamble_id = null;
        $checklist->is_active = true;
        $checklist->is_featured = false;

        $repo = Mockery::mock(ChecklistRepository::class);
        $repo->shouldReceive('createChecklist')->once()->andReturn($checklist);

        $this->app->instance(ChecklistRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/checklists', ['name' => 'Test Checklist', 'preamble_id' => null])
            ->assertCreated();
    });

    it('accepts valid is_active boolean', function (): void {
        $checklist = new Checklist;
        $checklist->identifier = (string) Str::uuid();
        $checklist->tenant_id = 'test-tenant';
        $checklist->name = 'Test Checklist';
        $checklist->reference_number = 'CL-1-123456789';
        $checklist->is_active = false;
        $checklist->is_featured = false;

        $repo = Mockery::mock(ChecklistRepository::class);
        $repo->shouldReceive('createChecklist')->once()->andReturn($checklist);

        $this->app->instance(ChecklistRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/checklists', ['name' => 'Test Checklist', 'is_active' => false])
            ->assertCreated();
    });
});

// ---------------------------------------------------------------------------
// Validation — update request
// ---------------------------------------------------------------------------

describe('Checklist update validation', function (): void {
    beforeEach(function (): void {
        ensureChecklistsTable();

        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            InitializeTenancyByBodyParam::class,
            EnsureTokenMatchesTenant::class,
        ]);

        Gate::before(fn () => true);
    });

    it('rejects name exceeding 255 chars on update', function (): void {
        $identifier = 'test-checklist-abc';

        $repo = Mockery::mock(ChecklistRepository::class);
        $repo->shouldReceive('readChecklist')->never();

        $this->app->instance(ChecklistRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->putJson("/v1/checklists/{$identifier}", ['name' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['name']]]);
    });

    it('allows partial update without name', function (): void {
        $identifier = 'test-checklist-abc';

        $checklist = new Checklist;
        $checklist->identifier = $identifier;
        $checklist->tenant_id = 'test-tenant';
        $checklist->name = 'Existing Name';
        $checklist->reference_number = 'CL-1-123456789';
        $checklist->is_active = false;
        $checklist->is_featured = false;

        $repo = Mockery::mock(ChecklistRepository::class);
        $repo->shouldReceive('readChecklist')->with($identifier)->once()->andReturn($checklist);
        $repo->shouldReceive('updateChecklist')->with($identifier, Mockery::any())->once()->andReturn($checklist);

        $this->app->instance(ChecklistRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->putJson("/v1/checklists/{$identifier}", ['is_active' => false])
            ->assertOk();
    });
});
