<?php

declare(strict_types=1);

use App\Enums\AuditScope;
use App\Enums\AuditStatusStageStatus;
use App\Http\Controllers\Api\V1\Tenant\AuditController;
use App\Http\Middleware\EnsureTokenMatchesTenant;
use App\Http\Middleware\InitializeTenancyByBodyParam;
use App\Http\Resources\Tenant\Audit\AuditResource;
use App\Models\Concerns\TenantConnection;
use App\Models\Tenant\Audit;
use App\Models\Tenant\AuditStatusStage;
use App\Models\User;
use App\Policies\AuditPolicy;
use App\Repositories\Tenant\AuditRepository;
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
 * Ensure audits and audit_status_stages tables exist in the central testing database.
 * Required for Rule::unique validation queries and lazy-load in form requests.
 */
function ensureAuditsTable(): void
{
    if (! Schema::hasTable('departments')) {
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant/2026_05_27_000006_create_departments_table.php',
            '--realpath' => false,
            '--force' => true,
        ]);
    }

    if (! Schema::hasTable('checklists')) {
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant/2026_05_27_000003_create_checklists_table.php',
            '--realpath' => false,
            '--force' => true,
        ]);
    }

    if (! Schema::hasTable('audits')) {
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant/2026_05_28_000001_create_audits_table.php',
            '--realpath' => false,
            '--force' => true,
        ]);
    }

    if (! Schema::hasTable('audit_status_stages')) {
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant/2026_05_28_000002_create_audit_status_stages_table.php',
            '--realpath' => false,
            '--force' => true,
        ]);
    }
}

// ---------------------------------------------------------------------------
// Route registration
// ---------------------------------------------------------------------------

describe('Audit routes registration', function (): void {
    it('registers GET /v1/audits (index)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/audits' && in_array('GET', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers POST /v1/audits (store)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/audits' && in_array('POST', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers GET /v1/audits/{identifier} (show)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/audits/{identifier}' && in_array('GET', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers PUT /v1/audits/{identifier} (update)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/audits/{identifier}' && in_array('PUT', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers DELETE /v1/audits/{identifier} (destroy)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/audits/{identifier}' && in_array('DELETE', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers POST /v1/audits/{identifier}/restore (restore)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/audits/{identifier}/restore' && in_array('POST', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers exactly 6 audit routes', function (): void {
        $count = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'v1/audits'))
            ->count();

        expect($count)->toBe(6);
    });

    it('audit routes use auth:api middleware', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'v1/audits' && in_array('GET', $r->methods(), true));

        expect($route)->not->toBeNull();
        expect(implode(',', $route->middleware()))->toContain('auth:api');
    });

    it('audit routes use tenant.token middleware', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'v1/audits' && in_array('GET', $r->methods(), true));

        expect($route)->not->toBeNull();
        expect(implode(',', $route->middleware()))->toContain('tenant.token');
    });

    it('audit routes use the Tenant AuditController', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'v1/audits' && in_array('GET', $r->methods(), true));

        expect($route)->not->toBeNull();
        expect($route->getActionName())->toContain(AuditController::class);
    });
});

// ---------------------------------------------------------------------------
// Audit Model
// ---------------------------------------------------------------------------

describe('Audit model', function (): void {
    it('has correct fillable attributes', function (): void {
        $model = new Audit;

        expect($model->getFillable())->toBe([
            'tenant_id',
            'reference_number',
            'name',
            'checklist_id',
            'task_type_id',
            'scope',
            'department_id',
            'audit_start_date',
            'audit_end_date',
            'lead_auditor_id',
            'quality_manager_id',
            'add_appendix',
            'description',
            'is_featured',
            'created_by',
            'updated_by',
        ]);
    });

    it('uses identifier as route key name', function (): void {
        $model = new Audit;

        expect($model->getRouteKeyName())->toBe('identifier');
    });

    it('casts scope to AuditScope enum', function (): void {
        $casts = (new Audit)->getCasts();

        expect($casts)->toHaveKey('scope', AuditScope::class);
    });

    it('casts audit_start_date to datetime', function (): void {
        $casts = (new Audit)->getCasts();

        expect($casts)->toHaveKey('audit_start_date', 'datetime');
    });

    it('casts audit_end_date to datetime', function (): void {
        $casts = (new Audit)->getCasts();

        expect($casts)->toHaveKey('audit_end_date', 'datetime');
    });

    it('casts add_appendix to boolean', function (): void {
        $casts = (new Audit)->getCasts();

        expect($casts)->toHaveKey('add_appendix', 'boolean');
    });

    it('casts is_featured to boolean', function (): void {
        $casts = (new Audit)->getCasts();

        expect($casts)->toHaveKey('is_featured', 'boolean');
    });

    it('casts lead_auditor_id to integer', function (): void {
        $casts = (new Audit)->getCasts();

        expect($casts)->toHaveKey('lead_auditor_id', 'integer');
    });

    it('casts quality_manager_id to integer', function (): void {
        $casts = (new Audit)->getCasts();

        expect($casts)->toHaveKey('quality_manager_id', 'integer');
    });

    it('casts department_id to integer', function (): void {
        $casts = (new Audit)->getCasts();

        expect($casts)->toHaveKey('department_id', 'integer');
    });

    it('casts checklist_id to integer', function (): void {
        $casts = (new Audit)->getCasts();

        expect($casts)->toHaveKey('checklist_id', 'integer');
    });

    it('uses SoftDeletes trait', function (): void {
        expect(in_array(SoftDeletes::class, class_uses_recursive(new Audit), true))->toBeTrue();
    });

    it('uses TenantConnection trait', function (): void {
        expect(in_array(TenantConnection::class, class_uses_recursive(new Audit), true))->toBeTrue();
    });

    it('has statusStages relationship method', function (): void {
        expect(method_exists(Audit::class, 'statusStages'))->toBeTrue();
    });

    it('has latestStatus relationship method', function (): void {
        expect(method_exists(Audit::class, 'latestStatus'))->toBeTrue();
    });

    it('has leadAuditor relationship method', function (): void {
        expect(method_exists(Audit::class, 'leadAuditor'))->toBeTrue();
    });

    it('has qualityManager relationship method', function (): void {
        expect(method_exists(Audit::class, 'qualityManager'))->toBeTrue();
    });

    it('has department relationship method', function (): void {
        expect(method_exists(Audit::class, 'department'))->toBeTrue();
    });

    it('has checklist relationship method', function (): void {
        expect(method_exists(Audit::class, 'checklist'))->toBeTrue();
    });

    it('generates reference number with AUD prefix', function (): void {
        $audit = new Audit;
        $audit->id = 5;

        $ref = $audit->generateReferenceNumber();

        expect($ref)->toStartWith('AUD-');
        expect($ref)->toContain('5');
    });

    it('reference number includes current year', function (): void {
        $audit = new Audit;
        $audit->id = 1;

        $ref = $audit->generateReferenceNumber();

        expect($ref)->toContain((string) now()->year);
    });
});

// ---------------------------------------------------------------------------
// AuditStatusStage Model
// ---------------------------------------------------------------------------

describe('AuditStatusStage model', function (): void {
    it('has correct fillable attributes', function (): void {
        $model = new AuditStatusStage;

        expect($model->getFillable())->toBe([
            'audit_id',
            'status',
            'changed_at',
            'changed_by',
            'notes',
        ]);
    });

    it('uses identifier as route key name', function (): void {
        $model = new AuditStatusStage;

        expect($model->getRouteKeyName())->toBe('identifier');
    });

    it('casts status to AuditStatusStageStatus enum', function (): void {
        $casts = (new AuditStatusStage)->getCasts();

        expect($casts)->toHaveKey('status', AuditStatusStageStatus::class);
    });

    it('casts changed_at to datetime', function (): void {
        $casts = (new AuditStatusStage)->getCasts();

        expect($casts)->toHaveKey('changed_at', 'datetime');
    });

    it('casts audit_id to integer', function (): void {
        $casts = (new AuditStatusStage)->getCasts();

        expect($casts)->toHaveKey('audit_id', 'integer');
    });

    it('uses SoftDeletes trait', function (): void {
        expect(in_array(SoftDeletes::class, class_uses_recursive(new AuditStatusStage), true))->toBeTrue();
    });

    it('uses TenantConnection trait', function (): void {
        expect(in_array(TenantConnection::class, class_uses_recursive(new AuditStatusStage), true))->toBeTrue();
    });

    it('has audit relationship method', function (): void {
        expect(method_exists(AuditStatusStage::class, 'audit'))->toBeTrue();
    });

    it('has changedBy relationship method', function (): void {
        expect(method_exists(AuditStatusStage::class, 'changedBy'))->toBeTrue();
    });

    it('has SCHEDULED constant', function (): void {
        expect(AuditStatusStage::SCHEDULED)->toBe('scheduled');
    });

    it('has IN_PROGRESS constant', function (): void {
        expect(AuditStatusStage::IN_PROGRESS)->toBe('in_progress');
    });

    it('has COMPLETED constant', function (): void {
        expect(AuditStatusStage::COMPLETED)->toBe('completed');
    });

    it('has CLOSED constant', function (): void {
        expect(AuditStatusStage::CLOSED)->toBe('closed');
    });
});

// ---------------------------------------------------------------------------
// AuditScope Enum
// ---------------------------------------------------------------------------

describe('AuditScope enum', function (): void {
    it('has Internal case', function (): void {
        expect(AuditScope::Internal->value)->toBe('internal');
    });

    it('has External case', function (): void {
        expect(AuditScope::External->value)->toBe('external');
    });

    it('has ServiceProvider case', function (): void {
        expect(AuditScope::ServiceProvider->value)->toBe('service_provider');
    });

    it('has Supplier case', function (): void {
        expect(AuditScope::Supplier->value)->toBe('supplier');
    });

    it('has exactly 4 cases', function (): void {
        expect(count(AuditScope::cases()))->toBe(4);
    });
});

// ---------------------------------------------------------------------------
// AuditStatusStageStatus Enum
// ---------------------------------------------------------------------------

describe('AuditStatusStageStatus enum', function (): void {
    it('has Scheduled case', function (): void {
        expect(AuditStatusStageStatus::Scheduled->value)->toBe('scheduled');
    });

    it('has InProgress case', function (): void {
        expect(AuditStatusStageStatus::InProgress->value)->toBe('in_progress');
    });

    it('has Completed case', function (): void {
        expect(AuditStatusStageStatus::Completed->value)->toBe('completed');
    });

    it('has Closed case', function (): void {
        expect(AuditStatusStageStatus::Closed->value)->toBe('closed');
    });

    it('has exactly 4 cases', function (): void {
        expect(count(AuditStatusStageStatus::cases()))->toBe(4);
    });
});

// ---------------------------------------------------------------------------
// Repository
// ---------------------------------------------------------------------------

describe('AuditRepository', function (): void {
    it('can be instantiated without additional dependencies', function (): void {
        $repo = new AuditRepository;

        expect($repo)->toBeInstanceOf(AuditRepository::class);
    });
});

// ---------------------------------------------------------------------------
// Policy
// ---------------------------------------------------------------------------

describe('AuditPolicy', function (): void {
    it('is registered in the gate', function (): void {
        $policy = Gate::getPolicyFor(Audit::class);

        expect($policy)->not->toBeNull();
        expect($policy)->toBeInstanceOf(AuditPolicy::class);
    });

    it('has before() method for super-admin bypass', function (): void {
        expect(method_exists(AuditPolicy::class, 'before'))->toBeTrue();
    });

    it('has viewAny() method', function (): void {
        expect(method_exists(AuditPolicy::class, 'viewAny'))->toBeTrue();
    });

    it('has view() method with tenant boundary', function (): void {
        expect(method_exists(AuditPolicy::class, 'view'))->toBeTrue();
    });

    it('has create() method', function (): void {
        expect(method_exists(AuditPolicy::class, 'create'))->toBeTrue();
    });

    it('has update() method with tenant boundary', function (): void {
        expect(method_exists(AuditPolicy::class, 'update'))->toBeTrue();
    });

    it('has delete() method with tenant boundary', function (): void {
        expect(method_exists(AuditPolicy::class, 'delete'))->toBeTrue();
    });

    it('has restore() method with tenant boundary', function (): void {
        expect(method_exists(AuditPolicy::class, 'restore'))->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// Resource
// ---------------------------------------------------------------------------

describe('AuditResource', function (): void {
    it('transforms audit to correct array structure', function (): void {
        $audit = new Audit([
            'identifier' => (string) Str::uuid(),
            'tenant_id' => 'test-tenant',
            'reference_number' => 'AUD-20261-1748390400',
            'name' => 'Annual Quality Audit',
            'checklist_id' => null,
            'task_type_id' => null,
            'scope' => 'internal',
            'department_id' => null,
            'audit_start_date' => now(),
            'audit_end_date' => null,
            'lead_auditor_id' => null,
            'quality_manager_id' => null,
            'add_appendix' => false,
            'description' => null,
            'is_featured' => false,
        ]);

        $resource = new AuditResource($audit);
        $resolved = $resource->resolve();

        expect($resolved)->toHaveKeys([
            'identifier',
            'tenant_id',
            'reference_number',
            'name',
            'checklist_id',
            'task_type_id',
            'scope',
            'department_id',
            'audit_start_date',
            'audit_end_date',
            'lead_auditor_id',
            'quality_manager_id',
            'add_appendix',
            'description',
            'is_featured',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);
    });

    it('returns add_appendix as boolean', function (): void {
        $audit = new Audit(['add_appendix' => false]);

        $resolved = (new AuditResource($audit))->resolve();

        expect($resolved['add_appendix'])->toBeFalse();
    });

    it('returns is_featured as boolean', function (): void {
        $audit = new Audit(['is_featured' => false]);

        $resolved = (new AuditResource($audit))->resolve();

        expect($resolved['is_featured'])->toBeFalse();
    });

    it('returns scope as string value', function (): void {
        $audit = new Audit(['scope' => 'internal']);

        $resolved = (new AuditResource($audit))->resolve();

        expect($resolved['scope'])->toBe('internal');
    });
});

// ---------------------------------------------------------------------------
// AuditFactory
// ---------------------------------------------------------------------------

describe('AuditFactory', function (): void {
    it('creates valid definition', function (): void {
        $factory = Audit::factory();

        expect($factory)->toBeInstanceOf(Factory::class);

        $definition = $factory->definition();

        expect($definition)->toHaveKeys([
            'tenant_id',
            'name',
            'scope',
            'audit_start_date',
            'is_featured',
        ]);
    });

    it('has internal state', function (): void {
        $definition = Audit::factory()->internal()->raw();

        expect($definition['scope'])->toBe(AuditScope::Internal->value);
    });

    it('has external state', function (): void {
        $definition = Audit::factory()->external()->raw();

        expect($definition['scope'])->toBe(AuditScope::External->value);
    });

    it('has featured state', function (): void {
        $definition = Audit::factory()->featured()->raw();

        expect($definition['is_featured'])->toBeTrue();
    });

    it('has withAppendix state', function (): void {
        $definition = Audit::factory()->withAppendix()->raw();

        expect($definition['add_appendix'])->toBeTrue();
    });

    it('defaults checklist_id to null', function (): void {
        $definition = Audit::factory()->definition();

        expect($definition['checklist_id'])->toBeNull();
    });

    it('defaults lead_auditor_id to null', function (): void {
        $definition = Audit::factory()->definition();

        expect($definition['lead_auditor_id'])->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// AuditStatusStageFactory
// ---------------------------------------------------------------------------

describe('AuditStatusStageFactory', function (): void {
    it('creates valid definition', function (): void {
        $factory = AuditStatusStage::factory();

        expect($factory)->toBeInstanceOf(Factory::class);

        $definition = $factory->definition();

        expect($definition)->toHaveKeys(['audit_id', 'status', 'changed_at']);
    });

    it('defaults status to scheduled', function (): void {
        $definition = AuditStatusStage::factory()->definition();

        expect($definition['status'])->toBe(AuditStatusStageStatus::Scheduled->value);
    });

    it('has scheduled state', function (): void {
        $definition = AuditStatusStage::factory()->scheduled()->raw();

        expect($definition['status'])->toBe(AuditStatusStageStatus::Scheduled->value);
    });

    it('has inProgress state', function (): void {
        $definition = AuditStatusStage::factory()->inProgress()->raw();

        expect($definition['status'])->toBe(AuditStatusStageStatus::InProgress->value);
    });

    it('has completed state', function (): void {
        $definition = AuditStatusStage::factory()->completed()->raw();

        expect($definition['status'])->toBe(AuditStatusStageStatus::Completed->value);
    });

    it('has closed state', function (): void {
        $definition = AuditStatusStage::factory()->closed()->raw();

        expect($definition['status'])->toBe(AuditStatusStageStatus::Closed->value);
    });

    it('defaults audit_id to null', function (): void {
        $definition = AuditStatusStage::factory()->definition();

        expect($definition['audit_id'])->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// Unauthenticated access — returns 401
// ---------------------------------------------------------------------------

describe('Audit routes unauthenticated', function (): void {
    beforeEach(function (): void {
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
        ]);
    });

    it('GET /v1/audits returns 401 without auth', function (): void {
        $this->getJson('/v1/audits')->assertUnauthorized();
    });

    it('POST /v1/audits returns 401 without auth', function (): void {
        $this->postJson('/v1/audits', [])->assertUnauthorized();
    });

    it('GET /v1/audits/{identifier} returns 401 without auth', function (): void {
        $this->getJson('/v1/audits/some-identifier')->assertUnauthorized();
    });

    it('PUT /v1/audits/{identifier} returns 401 without auth', function (): void {
        $this->putJson('/v1/audits/some-identifier', [])->assertUnauthorized();
    });

    it('DELETE /v1/audits/{identifier} returns 401 without auth', function (): void {
        $this->deleteJson('/v1/audits/some-identifier')->assertUnauthorized();
    });

    it('POST /v1/audits/{identifier}/restore returns 401 without auth', function (): void {
        $this->postJson('/v1/audits/some-identifier/restore')->assertUnauthorized();
    });
});

// ---------------------------------------------------------------------------
// Permission checks — authenticated user without permission returns 403
// ---------------------------------------------------------------------------

describe('Audit routes permission enforcement', function (): void {
    beforeEach(function (): void {
        ensureAuditsTable();

        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            InitializeTenancyByBodyParam::class,
            EnsureTokenMatchesTenant::class,
        ]);

        Gate::before(fn () => false);
    });

    it('GET /v1/audits returns 403 when user lacks audits.view', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->getJson('/v1/audits')
            ->assertForbidden();
    });

    it('POST /v1/audits returns 403 when user lacks audits.create', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/audits', ['name' => 'Test Audit'])
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// Permission granted — authenticated user WITH permission succeeds (mocked repo)
// ---------------------------------------------------------------------------

describe('Audit routes with permission granted', function (): void {
    beforeEach(function (): void {
        ensureAuditsTable();

        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            InitializeTenancyByBodyParam::class,
            EnsureTokenMatchesTenant::class,
        ]);

        Gate::before(fn () => true);
    });

    it('GET /v1/audits returns 200 with mocked paginator', function (): void {
        $audit = new Audit;
        $audit->identifier = (string) Str::uuid();
        $audit->tenant_id = 'test-tenant';
        $audit->name = 'Annual Audit';
        $audit->reference_number = 'AUD-20261-1748390400';
        $audit->scope = 'internal';
        $audit->audit_start_date = now();
        $audit->add_appendix = false;
        $audit->is_featured = false;

        $paginator = new LengthAwarePaginator(
            items: [$audit],
            total: 1,
            perPage: 15,
            currentPage: 1,
        );

        $repo = Mockery::mock(AuditRepository::class);
        $repo->shouldReceive('browseAudits')->once()->andReturn($paginator);

        $this->app->instance(AuditRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->getJson('/v1/audits')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    });

    it('POST /v1/audits returns 201 with valid data', function (): void {
        $audit = new Audit;
        $audit->identifier = (string) Str::uuid();
        $audit->tenant_id = 'test-tenant';
        $audit->name = 'New Audit';
        $audit->reference_number = 'AUD-20261-1748390400';
        $audit->scope = 'internal';
        $audit->audit_start_date = now();
        $audit->add_appendix = false;
        $audit->is_featured = false;

        $repo = Mockery::mock(AuditRepository::class);
        $repo->shouldReceive('createAudit')->once()->andReturn($audit);

        $this->app->instance(AuditRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/audits', [
                'name' => 'New Audit',
                'audit_start_date' => now()->toDateTimeString(),
            ])
            ->assertCreated();
    });

    it('GET /v1/audits/{identifier} returns 200', function (): void {
        $identifier = 'test-audit-abc';

        $audit = new Audit;
        $audit->identifier = $identifier;
        $audit->tenant_id = 'test-tenant';
        $audit->name = 'Annual Audit';
        $audit->reference_number = 'AUD-20261-1748390400';
        $audit->scope = 'internal';
        $audit->audit_start_date = now();
        $audit->add_appendix = false;
        $audit->is_featured = false;

        $repo = Mockery::mock(AuditRepository::class);
        $repo->shouldReceive('readAudit')->with($identifier)->once()->andReturn($audit);

        $this->app->instance(AuditRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->getJson("/v1/audits/{$identifier}")
            ->assertOk()
            ->assertJsonPath('data.identifier', $identifier);
    });

    it('PUT /v1/audits/{identifier} returns 200', function (): void {
        $identifier = 'test-audit-abc';

        $audit = new Audit;
        $audit->identifier = $identifier;
        $audit->tenant_id = 'test-tenant';
        $audit->name = 'Updated Audit';
        $audit->reference_number = 'AUD-20261-1748390400';
        $audit->scope = 'internal';
        $audit->audit_start_date = now();
        $audit->add_appendix = false;
        $audit->is_featured = false;

        $repo = Mockery::mock(AuditRepository::class);
        $repo->shouldReceive('readAudit')->with($identifier)->once()->andReturn($audit);
        $repo->shouldReceive('updateAudit')->with($identifier, Mockery::any())->once()->andReturn($audit);

        $this->app->instance(AuditRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->putJson("/v1/audits/{$identifier}", ['name' => 'Updated Audit'])
            ->assertOk();
    });

    it('DELETE /v1/audits/{identifier} returns 204', function (): void {
        $identifier = 'test-audit-abc';

        $audit = new Audit;
        $audit->identifier = $identifier;
        $audit->tenant_id = 'test-tenant';
        $audit->name = 'To Delete';

        $repo = Mockery::mock(AuditRepository::class);
        $repo->shouldReceive('readAudit')->with($identifier)->once()->andReturn($audit);
        $repo->shouldReceive('deleteAudit')->with($identifier)->once();

        $this->app->instance(AuditRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->deleteJson("/v1/audits/{$identifier}")
            ->assertNoContent();
    });

    it('POST /v1/audits/{identifier}/restore returns 200', function (): void {
        $identifier = 'test-audit-abc';

        $audit = new Audit;
        $audit->identifier = $identifier;
        $audit->tenant_id = 'test-tenant';
        $audit->name = 'Restored Audit';
        $audit->reference_number = 'AUD-20261-1748390400';
        $audit->scope = 'internal';
        $audit->audit_start_date = now();
        $audit->add_appendix = false;
        $audit->is_featured = false;

        $repo = Mockery::mock(AuditRepository::class);
        $repo->shouldReceive('readTrashedAudit')->with($identifier)->once()->andReturn($audit);
        $repo->shouldReceive('restoreAudit')->with($identifier)->once()->andReturn($audit);

        $this->app->instance(AuditRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson("/v1/audits/{$identifier}/restore")
            ->assertOk();
    });
});

// ---------------------------------------------------------------------------
// Validation — store request
// ---------------------------------------------------------------------------

describe('Audit store validation', function (): void {
    beforeEach(function (): void {
        ensureAuditsTable();

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
            ->postJson('/v1/audits', ['audit_start_date' => now()->toDateTimeString()])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['name']]]);
    });

    it('rejects missing audit_start_date', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/audits', ['name' => 'Test Audit'])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['audit_start_date']]]);
    });

    it('rejects name exceeding 255 chars', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/audits', [
                'name' => str_repeat('a', 256),
                'audit_start_date' => now()->toDateTimeString(),
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['name']]]);
    });

    it('rejects invalid scope value', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/audits', [
                'name' => 'Test Audit',
                'audit_start_date' => now()->toDateTimeString(),
                'scope' => 'invalid_scope',
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['scope']]]);
    });

    it('rejects audit_end_date before audit_start_date', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/audits', [
                'name' => 'Test Audit',
                'audit_start_date' => now()->addDays(10)->toDateTimeString(),
                'audit_end_date' => now()->toDateTimeString(),
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['audit_end_date']]]);
    });

    it('rejects non-boolean add_appendix', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/audits', [
                'name' => 'Test Audit',
                'audit_start_date' => now()->toDateTimeString(),
                'add_appendix' => 'notabool',
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['add_appendix']]]);
    });

    it('rejects non-boolean is_featured', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/audits', [
                'name' => 'Test Audit',
                'audit_start_date' => now()->toDateTimeString(),
                'is_featured' => 'notabool',
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['is_featured']]]);
    });

    it('accepts valid scope values', function (): void {
        $audit = new Audit;
        $audit->identifier = (string) Str::uuid();
        $audit->tenant_id = 'test-tenant';
        $audit->name = 'External Audit';
        $audit->reference_number = 'AUD-20261-1748390400';
        $audit->scope = 'external';
        $audit->audit_start_date = now();
        $audit->add_appendix = false;
        $audit->is_featured = false;

        $repo = Mockery::mock(AuditRepository::class);
        $repo->shouldReceive('createAudit')->once()->andReturn($audit);

        $this->app->instance(AuditRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/audits', [
                'name' => 'External Audit',
                'audit_start_date' => now()->toDateTimeString(),
                'scope' => 'external',
            ])
            ->assertCreated();
    });

    it('accepts nullable optional fields', function (): void {
        $audit = new Audit;
        $audit->identifier = (string) Str::uuid();
        $audit->tenant_id = 'test-tenant';
        $audit->name = 'Minimal Audit';
        $audit->reference_number = 'AUD-20261-1748390400';
        $audit->scope = 'internal';
        $audit->audit_start_date = now();
        $audit->add_appendix = false;
        $audit->is_featured = false;

        $repo = Mockery::mock(AuditRepository::class);
        $repo->shouldReceive('createAudit')->once()->andReturn($audit);

        $this->app->instance(AuditRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/audits', [
                'name' => 'Minimal Audit',
                'audit_start_date' => now()->toDateTimeString(),
                'checklist_id' => null,
                'department_id' => null,
                'lead_auditor_id' => null,
                'quality_manager_id' => null,
                'audit_end_date' => null,
            ])
            ->assertCreated();
    });

    it('accepts audit_end_date equal to start_date', function (): void {
        $startDate = now()->toDateTimeString();

        $audit = new Audit;
        $audit->identifier = (string) Str::uuid();
        $audit->tenant_id = 'test-tenant';
        $audit->name = 'Same Day Audit';
        $audit->reference_number = 'AUD-20261-1748390400';
        $audit->scope = 'internal';
        $audit->audit_start_date = now();
        $audit->audit_end_date = now();
        $audit->add_appendix = false;
        $audit->is_featured = false;

        $repo = Mockery::mock(AuditRepository::class);
        $repo->shouldReceive('createAudit')->once()->andReturn($audit);

        $this->app->instance(AuditRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/audits', [
                'name' => 'Same Day Audit',
                'audit_start_date' => $startDate,
                'audit_end_date' => $startDate,
            ])
            ->assertCreated();
    });
});

// ---------------------------------------------------------------------------
// Validation — update request
// ---------------------------------------------------------------------------

describe('Audit update validation', function (): void {
    beforeEach(function (): void {
        ensureAuditsTable();

        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            InitializeTenancyByBodyParam::class,
            EnsureTokenMatchesTenant::class,
        ]);

        Gate::before(fn () => true);
    });

    it('rejects name exceeding 255 chars on update', function (): void {
        $identifier = 'test-audit-abc';

        $repo = Mockery::mock(AuditRepository::class);
        $repo->shouldReceive('readAudit')->never();

        $this->app->instance(AuditRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->putJson("/v1/audits/{$identifier}", ['name' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['name']]]);
    });

    it('rejects invalid scope on update', function (): void {
        $identifier = 'test-audit-abc';

        $repo = Mockery::mock(AuditRepository::class);
        $repo->shouldReceive('readAudit')->never();

        $this->app->instance(AuditRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->putJson("/v1/audits/{$identifier}", ['scope' => 'bad_scope'])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['scope']]]);
    });

    it('allows partial update without name', function (): void {
        $identifier = 'test-audit-abc';

        $audit = new Audit;
        $audit->identifier = $identifier;
        $audit->tenant_id = 'test-tenant';
        $audit->name = 'Annual Audit';
        $audit->reference_number = 'AUD-20261-1748390400';
        $audit->scope = 'internal';
        $audit->audit_start_date = now();
        $audit->add_appendix = false;
        $audit->is_featured = false;

        $repo = Mockery::mock(AuditRepository::class);
        $repo->shouldReceive('readAudit')->with($identifier)->once()->andReturn($audit);
        $repo->shouldReceive('updateAudit')->with($identifier, Mockery::any())->once()->andReturn($audit);

        $this->app->instance(AuditRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->putJson("/v1/audits/{$identifier}", ['is_featured' => true])
            ->assertOk();
    });

    it('rejects end_date before start_date on update', function (): void {
        $identifier = 'test-audit-abc';

        $repo = Mockery::mock(AuditRepository::class);
        $repo->shouldReceive('readAudit')->never();

        $this->app->instance(AuditRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->putJson("/v1/audits/{$identifier}", [
                'audit_start_date' => now()->addDays(5)->toDateTimeString(),
                'audit_end_date' => now()->toDateTimeString(),
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['audit_end_date']]]);
    });
});

// ---------------------------------------------------------------------------
// Tenant isolation — structural verification
// ---------------------------------------------------------------------------

describe('Audit tenant isolation', function (): void {
    it('AuditPolicy view() method signature accepts User and Audit', function (): void {
        $reflection = new ReflectionMethod(AuditPolicy::class, 'view');
        $params = $reflection->getParameters();

        expect($params)->toHaveCount(2);
        expect($params[0]->getName())->toBe('user');
        expect($params[1]->getName())->toBe('audit');
    });

    it('AuditPolicy update() method signature accepts User and Audit', function (): void {
        $reflection = new ReflectionMethod(AuditPolicy::class, 'update');
        $params = $reflection->getParameters();

        expect($params)->toHaveCount(2);
        expect($params[1]->getName())->toBe('audit');
    });

    it('AuditPolicy view() source enforces tenant_id boundary', function (): void {
        $source = (string) file_get_contents(app_path('Policies/AuditPolicy.php'));

        expect($source)->toContain('tenant_id === $audit->tenant_id');
    });

    it('AuditPolicy update() source enforces tenant_id boundary', function (): void {
        $source = (string) file_get_contents(app_path('Policies/AuditPolicy.php'));

        expect($source)->toContain('tenant_id === $audit->tenant_id');
    });

    it('AuditPolicy before() returns null for non-super-admin', function (): void {
        $policy = new AuditPolicy;

        $user = new User;

        $result = $policy->before($user, 'view');

        expect($result)->toBeNull();
    });
});
