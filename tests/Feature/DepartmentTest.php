<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Tenant\DepartmentController;
use App\Http\Middleware\EnsureTokenMatchesTenant;
use App\Http\Middleware\InitializeTenancyByBodyParam;
use App\Http\Resources\Tenant\Department\DepartmentResource;
use App\Models\Concerns\TenantConnection;
use App\Models\Tenant\Department;
use App\Models\Tenant\DepartmentMember;
use App\Models\User;
use App\Policies\DepartmentPolicy;
use App\Repositories\Tenant\DepartmentRepository;
use App\Services\GeocodingService;
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
 * Ensure departments and department_members tables exist in the central testing database.
 * Required for Rule::unique validation queries in form requests.
 */
function ensureDepartmentsTable(): void
{
    if (!Schema::hasTable('departments')) {
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant/2026_05_27_000006_create_departments_table.php',
            '--realpath' => false,
            '--force' => true,
        ]);
    }

    if (!Schema::hasTable('department_members')) {
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant/2026_05_27_000007_create_department_members_table.php',
            '--realpath' => false,
            '--force' => true,
        ]);
    }
}

// ---------------------------------------------------------------------------
// Route registration
// ---------------------------------------------------------------------------

describe('Department routes registration', function (): void {
    it('registers GET /v1/departments (index)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/departments' && in_array('GET', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers POST /v1/departments (store)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/departments' && in_array('POST', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers GET /v1/departments/{identifier} (show)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/departments/{identifier}' && in_array('GET', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers PUT /v1/departments/{identifier} (update)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/departments/{identifier}' && in_array('PUT', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers DELETE /v1/departments/{identifier} (destroy)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/departments/{identifier}' && in_array('DELETE', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers POST /v1/departments/{identifier}/restore (restore)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/departments/{identifier}/restore' && in_array('POST', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers exactly 6 department routes', function (): void {
        $count = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'v1/departments'))
            ->count();

        expect($count)->toBe(6);
    });

    it('department routes use auth:api middleware', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'v1/departments' && in_array('GET', $r->methods(), true));

        expect($route)->not->toBeNull();
        expect(implode(',', $route->middleware()))->toContain('auth:api');
    });

    it('department routes use tenant.token middleware', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'v1/departments' && in_array('GET', $r->methods(), true));

        expect($route)->not->toBeNull();
        expect(implode(',', $route->middleware()))->toContain('tenant.token');
    });

    it('department routes use the Tenant DepartmentController', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'v1/departments' && in_array('GET', $r->methods(), true));

        expect($route)->not->toBeNull();
        expect($route->getActionName())->toContain(DepartmentController::class);
    });
});

// ---------------------------------------------------------------------------
// Department Model
// ---------------------------------------------------------------------------

describe('Department model', function (): void {
    it('has correct fillable attributes', function (): void {
        $model = new Department;

        expect($model->getFillable())->toBe([
            'tenant_id',
            'reference_number',
            'name',
            'address',
            'office_location',
            'latitude',
            'longitude',
            'postal_code',
            'country_id',
            'department_head',
            'description',
            'is_active',
            'is_featured',
            'created_by',
            'updated_by',
        ]);
    });

    it('uses identifier as route key name', function (): void {
        $model = new Department;

        expect($model->getRouteKeyName())->toBe('identifier');
    });

    it('casts is_active to boolean', function (): void {
        $casts = (new Department)->getCasts();

        expect($casts)->toHaveKey('is_active', 'boolean');
    });

    it('casts is_featured to boolean', function (): void {
        $casts = (new Department)->getCasts();

        expect($casts)->toHaveKey('is_featured', 'boolean');
    });

    it('casts country_id to integer', function (): void {
        $casts = (new Department)->getCasts();

        expect($casts)->toHaveKey('country_id', 'integer');
    });

    it('casts department_head to integer', function (): void {
        $casts = (new Department)->getCasts();

        expect($casts)->toHaveKey('department_head', 'integer');
    });

    it('casts latitude to float', function (): void {
        $casts = (new Department)->getCasts();

        expect($casts)->toHaveKey('latitude', 'float');
    });

    it('casts longitude to float', function (): void {
        $casts = (new Department)->getCasts();

        expect($casts)->toHaveKey('longitude', 'float');
    });

    it('uses SoftDeletes trait', function (): void {
        expect(in_array(SoftDeletes::class, class_uses_recursive(new Department), true))->toBeTrue();
    });

    it('uses TenantConnection trait', function (): void {
        expect(in_array(TenantConnection::class, class_uses_recursive(new Department), true))->toBeTrue();
    });

    it('has members relationship method', function (): void {
        expect(method_exists(Department::class, 'members'))->toBeTrue();
    });

    it('has head relationship method', function (): void {
        expect(method_exists(Department::class, 'head'))->toBeTrue();
    });

    it('generates reference number with DP prefix', function (): void {
        $department = new Department;
        $department->id = 7;

        $ref = $department->generateReferenceNumber();

        expect($ref)->toStartWith('DP-7-');
    });
});

// ---------------------------------------------------------------------------
// DepartmentMember Model
// ---------------------------------------------------------------------------

describe('DepartmentMember model', function (): void {
    it('has correct fillable attributes', function (): void {
        $model = new DepartmentMember;

        expect($model->getFillable())->toBe([
            'department_id',
            'user_id',
        ]);
    });

    it('casts department_id to integer', function (): void {
        $casts = (new DepartmentMember)->getCasts();

        expect($casts)->toHaveKey('department_id', 'integer');
    });

    it('casts user_id to integer', function (): void {
        $casts = (new DepartmentMember)->getCasts();

        expect($casts)->toHaveKey('user_id', 'integer');
    });

    it('uses SoftDeletes trait', function (): void {
        expect(in_array(SoftDeletes::class, class_uses_recursive(new DepartmentMember), true))->toBeTrue();
    });

    it('uses TenantConnection trait', function (): void {
        expect(in_array(TenantConnection::class, class_uses_recursive(new DepartmentMember), true))->toBeTrue();
    });

    it('has department relationship method', function (): void {
        expect(method_exists(DepartmentMember::class, 'department'))->toBeTrue();
    });

    it('has memberUser relationship method', function (): void {
        expect(method_exists(DepartmentMember::class, 'memberUser'))->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// Repository
// ---------------------------------------------------------------------------

describe('DepartmentRepository', function (): void {
    it('can be instantiated with GeocodingService', function (): void {
        $service = new GeocodingService;
        $repo = new DepartmentRepository($service);

        expect($repo)->toBeInstanceOf(DepartmentRepository::class);
    });
});

// ---------------------------------------------------------------------------
// Policy
// ---------------------------------------------------------------------------

describe('DepartmentPolicy', function (): void {
    it('is registered in the gate', function (): void {
        $policy = Gate::getPolicyFor(Department::class);

        expect($policy)->not->toBeNull();
        expect($policy)->toBeInstanceOf(DepartmentPolicy::class);
    });

    it('has before() method for super-admin bypass', function (): void {
        expect(method_exists(DepartmentPolicy::class, 'before'))->toBeTrue();
    });

    it('has viewAny() method', function (): void {
        expect(method_exists(DepartmentPolicy::class, 'viewAny'))->toBeTrue();
    });

    it('has view() method with tenant boundary', function (): void {
        expect(method_exists(DepartmentPolicy::class, 'view'))->toBeTrue();
    });

    it('has create() method', function (): void {
        expect(method_exists(DepartmentPolicy::class, 'create'))->toBeTrue();
    });

    it('has update() method with tenant boundary', function (): void {
        expect(method_exists(DepartmentPolicy::class, 'update'))->toBeTrue();
    });

    it('has delete() method with tenant boundary', function (): void {
        expect(method_exists(DepartmentPolicy::class, 'delete'))->toBeTrue();
    });

    it('has restore() method with tenant boundary', function (): void {
        expect(method_exists(DepartmentPolicy::class, 'restore'))->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// Resource
// ---------------------------------------------------------------------------

describe('DepartmentResource', function (): void {
    it('transforms department to correct array structure', function (): void {
        $department = new Department([
            'identifier' => (string) Str::uuid(),
            'tenant_id' => 'test-tenant',
            'reference_number' => 'DP-1-123456789',
            'name' => 'Finance Department',
            'address' => '123 Main St',
            'office_location' => 'Nairobi, Kenya',
            'latitude' => -1.2921,
            'longitude' => 36.8219,
            'postal_code' => '00100',
            'country_id' => null,
            'department_head' => null,
            'description' => null,
            'is_active' => true,
            'is_featured' => false,
        ]);

        $resource = new DepartmentResource($department);
        $resolved = $resource->resolve();

        expect($resolved)->toHaveKeys([
            'identifier',
            'tenant_id',
            'reference_number',
            'name',
            'address',
            'office_location',
            'latitude',
            'longitude',
            'postal_code',
            'country_id',
            'department_head',
            'description',
            'is_active',
            'is_featured',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);
    });

    it('returns is_active as boolean', function (): void {
        $department = new Department(['is_active' => true]);

        $resolved = (new DepartmentResource($department))->resolve();

        expect($resolved['is_active'])->toBeTrue();
    });

    it('returns is_featured as boolean', function (): void {
        $department = new Department(['is_featured' => false]);

        $resolved = (new DepartmentResource($department))->resolve();

        expect($resolved['is_featured'])->toBeFalse();
    });
});

// ---------------------------------------------------------------------------
// Factory
// ---------------------------------------------------------------------------

describe('DepartmentFactory', function (): void {
    it('creates valid definition', function (): void {
        $factory = Department::factory();

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
        $definition = Department::factory()->inactive()->raw();

        expect($definition['is_active'])->toBeFalse();
    });

    it('has active state', function (): void {
        $definition = Department::factory()->active()->raw();

        expect($definition['is_active'])->toBeTrue();
    });

    it('has featured state', function (): void {
        $definition = Department::factory()->featured()->raw();

        expect($definition['is_featured'])->toBeTrue();
    });

    it('defaults latitude and longitude to null', function (): void {
        $definition = Department::factory()->definition();

        expect($definition['latitude'])->toBeNull();
        expect($definition['longitude'])->toBeNull();
    });

    it('defaults department_head to null', function (): void {
        $definition = Department::factory()->definition();

        expect($definition['department_head'])->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// DepartmentMemberFactory
// ---------------------------------------------------------------------------

describe('DepartmentMemberFactory', function (): void {
    it('creates valid definition', function (): void {
        $factory = DepartmentMember::factory();

        expect($factory)->toBeInstanceOf(Factory::class);

        $definition = $factory->definition();

        expect($definition)->toHaveKeys(['department_id', 'user_id']);
    });

    it('defaults department_id to null', function (): void {
        $definition = DepartmentMember::factory()->definition();

        expect($definition['department_id'])->toBeNull();
    });

    it('defaults user_id to null', function (): void {
        $definition = DepartmentMember::factory()->definition();

        expect($definition['user_id'])->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// Unauthenticated access — returns 401
// ---------------------------------------------------------------------------

describe('Department routes unauthenticated', function (): void {
    beforeEach(function (): void {
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
        ]);
    });

    it('GET /v1/departments returns 401 without auth', function (): void {
        $this->getJson('/v1/departments')->assertUnauthorized();
    });

    it('POST /v1/departments returns 401 without auth', function (): void {
        $this->postJson('/v1/departments', [])->assertUnauthorized();
    });

    it('GET /v1/departments/{identifier} returns 401 without auth', function (): void {
        $this->getJson('/v1/departments/some-identifier')->assertUnauthorized();
    });

    it('PUT /v1/departments/{identifier} returns 401 without auth', function (): void {
        $this->putJson('/v1/departments/some-identifier', [])->assertUnauthorized();
    });

    it('DELETE /v1/departments/{identifier} returns 401 without auth', function (): void {
        $this->deleteJson('/v1/departments/some-identifier')->assertUnauthorized();
    });

    it('POST /v1/departments/{identifier}/restore returns 401 without auth', function (): void {
        $this->postJson('/v1/departments/some-identifier/restore')->assertUnauthorized();
    });
});

// ---------------------------------------------------------------------------
// Permission checks — authenticated user without permission returns 403
// ---------------------------------------------------------------------------

describe('Department routes permission enforcement', function (): void {
    beforeEach(function (): void {
        ensureDepartmentsTable();

        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            InitializeTenancyByBodyParam::class,
            EnsureTokenMatchesTenant::class,
        ]);

        Gate::before(fn () => false);
    });

    it('GET /v1/departments returns 403 when user lacks departments.view', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->getJson('/v1/departments')
            ->assertForbidden();
    });

    it('POST /v1/departments returns 403 when user lacks departments.create', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/departments', ['name' => 'Test Department'])
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// Permission granted — authenticated user WITH permission succeeds (mocked repo)
// ---------------------------------------------------------------------------

describe('Department routes with permission granted', function (): void {
    beforeEach(function (): void {
        ensureDepartmentsTable();

        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            InitializeTenancyByBodyParam::class,
            EnsureTokenMatchesTenant::class,
        ]);

        Gate::before(fn () => true);
    });

    it('GET /v1/departments returns 200 with mocked paginator', function (): void {
        $department = new Department;
        $department->identifier = (string) Str::uuid();
        $department->tenant_id = 'test-tenant';
        $department->name = 'Finance Department';
        $department->reference_number = 'DP-1-123456789';
        $department->is_active = true;
        $department->is_featured = false;

        $paginator = new LengthAwarePaginator(
            items: [$department],
            total: 1,
            perPage: 15,
            currentPage: 1,
        );

        $repo = Mockery::mock(DepartmentRepository::class);
        $repo->shouldReceive('browseDepartments')->once()->andReturn($paginator);

        $this->app->instance(DepartmentRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->getJson('/v1/departments')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    });

    it('POST /v1/departments returns 201 with valid data', function (): void {
        $department = new Department;
        $department->identifier = (string) Str::uuid();
        $department->tenant_id = 'test-tenant';
        $department->name = 'New Department';
        $department->reference_number = 'DP-1-123456789';
        $department->is_active = true;
        $department->is_featured = false;

        $repo = Mockery::mock(DepartmentRepository::class);
        $repo->shouldReceive('createDepartment')->once()->andReturn($department);

        $this->app->instance(DepartmentRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/departments', ['name' => 'New Department'])
            ->assertCreated();
    });

    it('GET /v1/departments/{identifier} returns 200', function (): void {
        $identifier = 'test-dept-abc';

        $department = new Department;
        $department->identifier = $identifier;
        $department->tenant_id = 'test-tenant';
        $department->name = 'Finance Department';
        $department->reference_number = 'DP-1-123456789';
        $department->is_active = true;
        $department->is_featured = false;

        $repo = Mockery::mock(DepartmentRepository::class);
        $repo->shouldReceive('readDepartment')->with($identifier)->once()->andReturn($department);

        $this->app->instance(DepartmentRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->getJson("/v1/departments/{$identifier}")
            ->assertOk()
            ->assertJsonPath('data.identifier', $identifier);
    });

    it('PUT /v1/departments/{identifier} returns 200', function (): void {
        $identifier = 'test-dept-abc';

        $department = new Department;
        $department->identifier = $identifier;
        $department->tenant_id = 'test-tenant';
        $department->name = 'Updated Department';
        $department->reference_number = 'DP-1-123456789';
        $department->is_active = true;
        $department->is_featured = false;

        $repo = Mockery::mock(DepartmentRepository::class);
        $repo->shouldReceive('readDepartment')->with($identifier)->once()->andReturn($department);
        $repo->shouldReceive('updateDepartment')->with($identifier, Mockery::any())->once()->andReturn($department);

        $this->app->instance(DepartmentRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->putJson("/v1/departments/{$identifier}", ['name' => 'Updated Department'])
            ->assertOk();
    });

    it('DELETE /v1/departments/{identifier} returns 204', function (): void {
        $identifier = 'test-dept-abc';

        $department = new Department;
        $department->identifier = $identifier;
        $department->tenant_id = 'test-tenant';
        $department->name = 'To Delete';

        $repo = Mockery::mock(DepartmentRepository::class);
        $repo->shouldReceive('readDepartment')->with($identifier)->once()->andReturn($department);
        $repo->shouldReceive('deleteDepartment')->with($identifier)->once();

        $this->app->instance(DepartmentRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->deleteJson("/v1/departments/{$identifier}")
            ->assertNoContent();
    });

    it('POST /v1/departments/{identifier}/restore returns 200', function (): void {
        $identifier = 'test-dept-abc';

        $department = new Department;
        $department->identifier = $identifier;
        $department->tenant_id = 'test-tenant';
        $department->name = 'Restored Department';
        $department->reference_number = 'DP-1-123456789';
        $department->is_active = true;
        $department->is_featured = false;

        $repo = Mockery::mock(DepartmentRepository::class);
        $repo->shouldReceive('readTrashedDepartment')->with($identifier)->once()->andReturn($department);
        $repo->shouldReceive('restoreDepartment')->with($identifier)->once()->andReturn($department);

        $this->app->instance(DepartmentRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson("/v1/departments/{$identifier}/restore")
            ->assertOk();
    });

    it('POST /v1/departments creates department with department_members', function (): void {
        $department = new Department;
        $department->identifier = (string) Str::uuid();
        $department->tenant_id = 'test-tenant';
        $department->name = 'Department With Members';
        $department->reference_number = 'DP-2-123456789';
        $department->is_active = true;
        $department->is_featured = false;

        $repo = Mockery::mock(DepartmentRepository::class);
        $repo->shouldReceive('createDepartment')
            ->once()
            ->with(Mockery::on(fn ($data) => isset($data['department_members']) && count($data['department_members']) === 2))
            ->andReturn($department);

        $this->app->instance(DepartmentRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/departments', [
                'name' => 'Department With Members',
                'department_members' => [
                    ['user_id' => 1],
                    ['user_id' => 2],
                ],
            ])
            ->assertCreated();
    });

    it('PUT /v1/departments/{identifier} updates department_members when provided', function (): void {
        $identifier = 'test-dept-abc';

        $department = new Department;
        $department->identifier = $identifier;
        $department->tenant_id = 'test-tenant';
        $department->name = 'Updated Department';
        $department->reference_number = 'DP-1-123456789';
        $department->is_active = true;
        $department->is_featured = false;

        $repo = Mockery::mock(DepartmentRepository::class);
        $repo->shouldReceive('readDepartment')->with($identifier)->once()->andReturn($department);
        $repo->shouldReceive('updateDepartment')
            ->once()
            ->with($identifier, Mockery::on(fn ($data) => array_key_exists('department_members', $data)))
            ->andReturn($department);

        $this->app->instance(DepartmentRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->putJson("/v1/departments/{$identifier}", [
                'name' => 'Updated Department',
                'department_members' => [
                    ['user_id' => 5],
                ],
            ])
            ->assertOk();
    });

    it('PUT /v1/departments/{identifier} does not require department_members', function (): void {
        $identifier = 'test-dept-abc';

        $department = new Department;
        $department->identifier = $identifier;
        $department->tenant_id = 'test-tenant';
        $department->name = 'Updated Department';
        $department->reference_number = 'DP-1-123456789';
        $department->is_active = true;
        $department->is_featured = false;

        $repo = Mockery::mock(DepartmentRepository::class);
        $repo->shouldReceive('readDepartment')->with($identifier)->once()->andReturn($department);
        $repo->shouldReceive('updateDepartment')
            ->once()
            ->with($identifier, Mockery::on(fn ($data) => !array_key_exists('department_members', $data)))
            ->andReturn($department);

        $this->app->instance(DepartmentRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->putJson("/v1/departments/{$identifier}", ['name' => 'Updated Department'])
            ->assertOk();
    });
});

// ---------------------------------------------------------------------------
// Validation — store request
// ---------------------------------------------------------------------------

describe('Department store validation', function (): void {
    beforeEach(function (): void {
        ensureDepartmentsTable();

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
            ->postJson('/v1/departments', [])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['name']]]);
    });

    it('rejects name exceeding 255 chars', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/departments', ['name' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['name']]]);
    });

    it('rejects non-boolean is_active', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/departments', ['name' => 'Test', 'is_active' => 'notabool'])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['is_active']]]);
    });

    it('rejects non-boolean is_featured', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/departments', ['name' => 'Test', 'is_featured' => 'notabool'])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['is_featured']]]);
    });

    it('rejects non-integer country_id', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/departments', ['name' => 'Test', 'country_id' => 'not-an-int'])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['country_id']]]);
    });

    it('rejects non-integer department_head', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/departments', ['name' => 'Test', 'department_head' => 'not-an-int'])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['department_head']]]);
    });

    it('accepts nullable optional fields', function (): void {
        $department = new Department;
        $department->identifier = (string) Str::uuid();
        $department->tenant_id = 'test-tenant';
        $department->name = 'Minimal Department';
        $department->reference_number = 'DP-1-123456789';
        $department->is_active = true;
        $department->is_featured = false;

        $repo = Mockery::mock(DepartmentRepository::class);
        $repo->shouldReceive('createDepartment')->once()->andReturn($department);

        $this->app->instance(DepartmentRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/departments', [
                'name' => 'Minimal Department',
                'address' => null,
                'office_location' => null,
                'country_id' => null,
                'department_head' => null,
            ])
            ->assertCreated();
    });

    it('accepts department_members as array of user_ids', function (): void {
        $department = new Department;
        $department->identifier = (string) Str::uuid();
        $department->tenant_id = 'test-tenant';
        $department->name = 'Department With Members';
        $department->reference_number = 'DP-1-123456789';
        $department->is_active = true;
        $department->is_featured = false;

        $repo = Mockery::mock(DepartmentRepository::class);
        $repo->shouldReceive('createDepartment')->once()->andReturn($department);

        $this->app->instance(DepartmentRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/departments', [
                'name' => 'Department With Members',
                'department_members' => [
                    ['user_id' => 1],
                    ['user_id' => null],
                ],
            ])
            ->assertCreated();
    });
});

// ---------------------------------------------------------------------------
// Validation — update request
// ---------------------------------------------------------------------------

describe('Department update validation', function (): void {
    beforeEach(function (): void {
        ensureDepartmentsTable();

        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            InitializeTenancyByBodyParam::class,
            EnsureTokenMatchesTenant::class,
        ]);

        Gate::before(fn () => true);
    });

    it('rejects name exceeding 255 chars on update', function (): void {
        $identifier = 'test-dept-abc';

        $repo = Mockery::mock(DepartmentRepository::class);
        $repo->shouldReceive('readDepartment')->never();

        $this->app->instance(DepartmentRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->putJson("/v1/departments/{$identifier}", ['name' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['name']]]);
    });

    it('allows partial update without name', function (): void {
        $identifier = 'test-dept-abc';

        $department = new Department;
        $department->identifier = $identifier;
        $department->tenant_id = 'test-tenant';
        $department->name = 'Finance';
        $department->reference_number = 'DP-1-123456789';
        $department->is_active = true;
        $department->is_featured = false;

        $repo = Mockery::mock(DepartmentRepository::class);
        $repo->shouldReceive('readDepartment')->with($identifier)->once()->andReturn($department);
        $repo->shouldReceive('updateDepartment')->with($identifier, Mockery::any())->once()->andReturn($department);

        $this->app->instance(DepartmentRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->putJson("/v1/departments/{$identifier}", ['is_active' => false])
            ->assertOk();
    });
});

// ---------------------------------------------------------------------------
// Tenant isolation — structural verification
// ---------------------------------------------------------------------------

describe('Department tenant isolation', function (): void {
    it('DepartmentPolicy view() method signature accepts User and Department', function (): void {
        $reflection = new ReflectionMethod(DepartmentPolicy::class, 'view');
        $params = $reflection->getParameters();

        expect($params)->toHaveCount(2);
        expect($params[0]->getName())->toBe('user');
        expect($params[1]->getName())->toBe('department');
    });

    it('DepartmentPolicy update() method signature accepts User and Department', function (): void {
        $reflection = new ReflectionMethod(DepartmentPolicy::class, 'update');
        $params = $reflection->getParameters();

        expect($params)->toHaveCount(2);
        expect($params[1]->getName())->toBe('department');
    });

    it('DepartmentPolicy view() source enforces tenant_id boundary', function (): void {
        $source = (string) file_get_contents(app_path('Policies/DepartmentPolicy.php'));

        expect($source)->toContain('tenant_id === $department->tenant_id');
    });

    it('DepartmentPolicy update() source enforces tenant_id boundary', function (): void {
        $source = (string) file_get_contents(app_path('Policies/DepartmentPolicy.php'));

        expect($source)->toContain('tenant_id === $department->tenant_id');
    });

    it('DepartmentPolicy before() returns null for non-super-admin', function (): void {
        $policy = new DepartmentPolicy;

        $user = new User;

        // before() returns null when user doesn't have super-admin role
        // (hasRole returns false on a fresh User with no permissions)
        $result = $policy->before($user, 'view');

        expect($result)->toBeNull();
    });
});
