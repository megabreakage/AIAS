<?php

declare(strict_types=1);

use App\Enums\ContactType;
use App\Enums\LevelOfOperations;
use App\Http\Controllers\Api\V1\Tenant\CompanyController;
use App\Http\Middleware\EnsureTokenMatchesTenant;
use App\Http\Middleware\InitializeTenancyByBodyParam;
use App\Http\Resources\Tenant\Company\CompanyResource;
use App\Models\Concerns\TenantConnection;
use App\Models\Tenant\Company;
use App\Models\Tenant\CompanyContact;
use App\Models\User;
use App\Policies\CompanyPolicy;
use App\Repositories\Tenant\CompanyRepository;
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
 * Ensure the companies and company_contacts tables exist in the central testing database.
 * Required for Rule::unique validation queries in form requests.
 */
function ensureCompaniesTable(): void
{
    if (!Schema::hasTable('companies')) {
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant/2026_05_27_000004_create_companies_table.php',
            '--realpath' => false,
            '--force' => true,
        ]);
    }

    if (!Schema::hasTable('company_contacts')) {
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant/2026_05_27_000005_create_company_contacts_table.php',
            '--realpath' => false,
            '--force' => true,
        ]);
    }
}

// ---------------------------------------------------------------------------
// Route registration
// ---------------------------------------------------------------------------

describe('Company routes registration', function (): void {
    it('registers GET /v1/companies (index)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/companies' && in_array('GET', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers POST /v1/companies (store)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/companies' && in_array('POST', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers GET /v1/companies/{identifier} (show)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/companies/{identifier}' && in_array('GET', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers PUT /v1/companies/{identifier} (update)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/companies/{identifier}' && in_array('PUT', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers DELETE /v1/companies/{identifier} (destroy)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/companies/{identifier}' && in_array('DELETE', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers POST /v1/companies/{identifier}/restore (restore)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'v1/companies/{identifier}/restore' && in_array('POST', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers exactly 6 company routes', function (): void {
        $count = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'v1/companies'))
            ->count();

        expect($count)->toBe(6);
    });

    it('company routes use auth:api middleware', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'v1/companies' && in_array('GET', $r->methods(), true));

        expect($route)->not->toBeNull();
        expect(implode(',', $route->middleware()))->toContain('auth:api');
    });

    it('company routes use tenant.token middleware', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'v1/companies' && in_array('GET', $r->methods(), true));

        expect($route)->not->toBeNull();
        expect(implode(',', $route->middleware()))->toContain('tenant.token');
    });

    it('company routes use the Tenant CompanyController', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'v1/companies' && in_array('GET', $r->methods(), true));

        expect($route)->not->toBeNull();
        expect($route->getActionName())->toContain(CompanyController::class);
    });
});

// ---------------------------------------------------------------------------
// Company Model
// ---------------------------------------------------------------------------

describe('Company model', function (): void {
    it('has correct fillable attributes', function (): void {
        $model = new Company;

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
            'level_of_operations',
            'trading_name',
            'website',
            'email',
            'phone',
            'logo',
            'description',
            'is_active',
            'is_featured',
            'created_by',
            'updated_by',
        ]);
    });

    it('uses identifier as route key name', function (): void {
        $model = new Company;

        expect($model->getRouteKeyName())->toBe('identifier');
    });

    it('casts is_active to boolean', function (): void {
        $casts = (new Company)->getCasts();

        expect($casts)->toHaveKey('is_active', 'boolean');
    });

    it('casts is_featured to boolean', function (): void {
        $casts = (new Company)->getCasts();

        expect($casts)->toHaveKey('is_featured', 'boolean');
    });

    it('casts country_id to integer', function (): void {
        $casts = (new Company)->getCasts();

        expect($casts)->toHaveKey('country_id', 'integer');
    });

    it('casts level_of_operations to LevelOfOperations enum', function (): void {
        $casts = (new Company)->getCasts();

        expect($casts)->toHaveKey('level_of_operations', LevelOfOperations::class);
    });

    it('casts latitude to float', function (): void {
        $casts = (new Company)->getCasts();

        expect($casts)->toHaveKey('latitude', 'float');
    });

    it('casts longitude to float', function (): void {
        $casts = (new Company)->getCasts();

        expect($casts)->toHaveKey('longitude', 'float');
    });

    it('uses SoftDeletes trait', function (): void {
        expect(in_array(SoftDeletes::class, class_uses_recursive(new Company), true))->toBeTrue();
    });

    it('uses TenantConnection trait', function (): void {
        expect(in_array(TenantConnection::class, class_uses_recursive(new Company), true))->toBeTrue();
    });

    it('has contacts relationship method', function (): void {
        expect(method_exists(Company::class, 'contacts'))->toBeTrue();
    });

    it('generates reference number with CO prefix', function (): void {
        $company = new Company;
        $company->id = 5;

        $ref = $company->generateReferenceNumber();

        expect($ref)->toStartWith('CO-5-');
    });
});

// ---------------------------------------------------------------------------
// CompanyContact Model
// ---------------------------------------------------------------------------

describe('CompanyContact model', function (): void {
    it('has correct fillable attributes', function (): void {
        $model = new CompanyContact;

        expect($model->getFillable())->toBe([
            'company_id',
            'user_id',
            'contact_type',
        ]);
    });

    it('casts contact_type to ContactType enum', function (): void {
        $casts = (new CompanyContact)->getCasts();

        expect($casts)->toHaveKey('contact_type', ContactType::class);
    });

    it('casts company_id to integer', function (): void {
        $casts = (new CompanyContact)->getCasts();

        expect($casts)->toHaveKey('company_id', 'integer');
    });

    it('uses SoftDeletes trait', function (): void {
        expect(in_array(SoftDeletes::class, class_uses_recursive(new CompanyContact), true))->toBeTrue();
    });

    it('has company relationship method', function (): void {
        expect(method_exists(CompanyContact::class, 'company'))->toBeTrue();
    });

    it('has contactUser relationship method', function (): void {
        expect(method_exists(CompanyContact::class, 'contactUser'))->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// Repository
// ---------------------------------------------------------------------------

describe('CompanyRepository', function (): void {
    it('can be instantiated with GeocodingService', function (): void {
        $service = new GeocodingService;
        $repo = new CompanyRepository($service);

        expect($repo)->toBeInstanceOf(CompanyRepository::class);
    });
});

// ---------------------------------------------------------------------------
// GeocodingService
// ---------------------------------------------------------------------------

describe('GeocodingService', function (): void {
    it('returns null values when API key not set', function (): void {
        config(['services.google_maps.key' => '']);

        $service = new GeocodingService;
        $result = $service->geocode('Nairobi, Kenya');

        expect($result['latitude'])->toBeNull();
        expect($result['longitude'])->toBeNull();
        expect($result['country_id'])->toBeNull();
    });

    it('has geocode method returning correct keys', function (): void {
        $service = new GeocodingService;

        expect(method_exists($service, 'geocode'))->toBeTrue();

        config(['services.google_maps.key' => '']);
        $result = $service->geocode('any location');

        expect($result)->toHaveKeys(['latitude', 'longitude', 'country_id']);
    });
});

// ---------------------------------------------------------------------------
// Policy
// ---------------------------------------------------------------------------

describe('CompanyPolicy', function (): void {
    it('is registered in the gate', function (): void {
        $policy = Gate::getPolicyFor(Company::class);

        expect($policy)->not->toBeNull();
        expect($policy)->toBeInstanceOf(CompanyPolicy::class);
    });

    it('has before() method for super-admin bypass', function (): void {
        expect(method_exists(CompanyPolicy::class, 'before'))->toBeTrue();
    });

    it('has viewAny() method', function (): void {
        expect(method_exists(CompanyPolicy::class, 'viewAny'))->toBeTrue();
    });

    it('has view() method with tenant boundary', function (): void {
        expect(method_exists(CompanyPolicy::class, 'view'))->toBeTrue();
    });

    it('has create() method', function (): void {
        expect(method_exists(CompanyPolicy::class, 'create'))->toBeTrue();
    });

    it('has update() method with tenant boundary', function (): void {
        expect(method_exists(CompanyPolicy::class, 'update'))->toBeTrue();
    });

    it('has delete() method with tenant boundary', function (): void {
        expect(method_exists(CompanyPolicy::class, 'delete'))->toBeTrue();
    });

    it('has restore() method with tenant boundary', function (): void {
        expect(method_exists(CompanyPolicy::class, 'restore'))->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// Resource
// ---------------------------------------------------------------------------

describe('CompanyResource', function (): void {
    it('transforms company to correct array structure', function (): void {
        $company = new Company([
            'identifier' => (string) Str::uuid(),
            'tenant_id' => 'test-tenant',
            'reference_number' => 'CO-1-123456789',
            'name' => 'Apex Ltd',
            'address' => '123 Main St',
            'office_location' => 'Nairobi, Kenya',
            'latitude' => -1.2921,
            'longitude' => 36.8219,
            'postal_code' => '00100',
            'country_id' => null,
            'level_of_operations' => LevelOfOperations::Local,
            'trading_name' => null,
            'website' => null,
            'email' => null,
            'phone' => null,
            'logo' => null,
            'description' => null,
            'is_active' => true,
            'is_featured' => false,
        ]);

        $resource = new CompanyResource($company);
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
            'level_of_operations',
            'trading_name',
            'website',
            'email',
            'phone',
            'logo',
            'description',
            'is_active',
            'is_featured',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);
    });

    it('returns level_of_operations as enum value string', function (): void {
        $company = new Company(['level_of_operations' => LevelOfOperations::International]);

        $resolved = (new CompanyResource($company))->resolve();

        expect($resolved['level_of_operations'])->toBe('international');
    });
});

// ---------------------------------------------------------------------------
// Factory
// ---------------------------------------------------------------------------

describe('CompanyFactory', function (): void {
    it('creates valid definition', function (): void {
        $factory = Company::factory();

        expect($factory)->toBeInstanceOf(Factory::class);

        $definition = $factory->definition();

        expect($definition)->toHaveKeys([
            'tenant_id',
            'name',
            'is_active',
            'is_featured',
            'level_of_operations',
        ]);
    });

    it('has inactive state', function (): void {
        $definition = Company::factory()->inactive()->raw();

        expect($definition['is_active'])->toBeFalse();
    });

    it('has active state', function (): void {
        $definition = Company::factory()->active()->raw();

        expect($definition['is_active'])->toBeTrue();
    });

    it('has featured state', function (): void {
        $definition = Company::factory()->featured()->raw();

        expect($definition['is_featured'])->toBeTrue();
    });

    it('has international state', function (): void {
        $definition = Company::factory()->international()->raw();

        expect($definition['level_of_operations'])->toBe(LevelOfOperations::International);
    });

    it('has regional state', function (): void {
        $definition = Company::factory()->regional()->raw();

        expect($definition['level_of_operations'])->toBe(LevelOfOperations::Regional);
    });

    it('defaults latitude and longitude to null', function (): void {
        $definition = Company::factory()->definition();

        expect($definition['latitude'])->toBeNull();
        expect($definition['longitude'])->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// CompanyContactFactory
// ---------------------------------------------------------------------------

describe('CompanyContactFactory', function (): void {
    it('creates valid definition', function (): void {
        $factory = CompanyContact::factory();

        expect($factory)->toBeInstanceOf(Factory::class);

        $definition = $factory->definition();

        expect($definition)->toHaveKeys(['company_id', 'user_id', 'contact_type']);
    });

    it('has primary state', function (): void {
        $definition = CompanyContact::factory()->primary()->raw();

        expect($definition['contact_type'])->toBe(ContactType::Primary);
    });

    it('has billing state', function (): void {
        $definition = CompanyContact::factory()->billing()->raw();

        expect($definition['contact_type'])->toBe(ContactType::Billing);
    });

    it('has technical state', function (): void {
        $definition = CompanyContact::factory()->technical()->raw();

        expect($definition['contact_type'])->toBe(ContactType::Technical);
    });
});

// ---------------------------------------------------------------------------
// Unauthenticated access — returns 401
// ---------------------------------------------------------------------------

describe('Company routes unauthenticated', function (): void {
    beforeEach(function (): void {
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
        ]);
    });

    it('GET /v1/companies returns 401 without auth', function (): void {
        $this->getJson('/v1/companies')->assertUnauthorized();
    });

    it('POST /v1/companies returns 401 without auth', function (): void {
        $this->postJson('/v1/companies', [])->assertUnauthorized();
    });

    it('GET /v1/companies/{identifier} returns 401 without auth', function (): void {
        $this->getJson('/v1/companies/some-identifier')->assertUnauthorized();
    });

    it('PUT /v1/companies/{identifier} returns 401 without auth', function (): void {
        $this->putJson('/v1/companies/some-identifier', [])->assertUnauthorized();
    });

    it('DELETE /v1/companies/{identifier} returns 401 without auth', function (): void {
        $this->deleteJson('/v1/companies/some-identifier')->assertUnauthorized();
    });

    it('POST /v1/companies/{identifier}/restore returns 401 without auth', function (): void {
        $this->postJson('/v1/companies/some-identifier/restore')->assertUnauthorized();
    });
});

// ---------------------------------------------------------------------------
// Permission checks — authenticated user without permission returns 403
// ---------------------------------------------------------------------------

describe('Company routes permission enforcement', function (): void {
    beforeEach(function (): void {
        ensureCompaniesTable();

        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            InitializeTenancyByBodyParam::class,
            EnsureTokenMatchesTenant::class,
        ]);

        Gate::before(fn () => false);
    });

    it('GET /v1/companies returns 403 when user lacks companies.view', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->getJson('/v1/companies')
            ->assertForbidden();
    });

    it('POST /v1/companies returns 403 when user lacks companies.create', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/companies', ['name' => 'Test Company'])
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// Permission granted — authenticated user WITH permission succeeds (mocked repo)
// ---------------------------------------------------------------------------

describe('Company routes with permission granted', function (): void {
    beforeEach(function (): void {
        ensureCompaniesTable();

        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            InitializeTenancyByBodyParam::class,
            EnsureTokenMatchesTenant::class,
        ]);

        Gate::before(fn () => true);
    });

    it('GET /v1/companies returns 200 with mocked paginator', function (): void {
        $company = new Company;
        $company->identifier = (string) Str::uuid();
        $company->tenant_id = 'test-tenant';
        $company->name = 'Apex Ltd';
        $company->reference_number = 'CO-1-123456789';
        $company->level_of_operations = LevelOfOperations::Local;
        $company->is_active = true;
        $company->is_featured = false;

        $paginator = new LengthAwarePaginator(
            items: [$company],
            total: 1,
            perPage: 15,
            currentPage: 1,
        );

        $repo = Mockery::mock(CompanyRepository::class);
        $repo->shouldReceive('browseCompanies')->once()->andReturn($paginator);

        $this->app->instance(CompanyRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->getJson('/v1/companies')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    });

    it('POST /v1/companies returns 201 with valid data', function (): void {
        $company = new Company;
        $company->identifier = (string) Str::uuid();
        $company->tenant_id = 'test-tenant';
        $company->name = 'New Company';
        $company->reference_number = 'CO-1-123456789';
        $company->level_of_operations = LevelOfOperations::Local;
        $company->is_active = true;
        $company->is_featured = false;

        $repo = Mockery::mock(CompanyRepository::class);
        $repo->shouldReceive('createCompany')->once()->andReturn($company);

        $this->app->instance(CompanyRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/companies', ['name' => 'New Company'])
            ->assertCreated();
    });

    it('GET /v1/companies/{identifier} returns 200', function (): void {
        $identifier = 'test-company-abc';

        $company = new Company;
        $company->identifier = $identifier;
        $company->tenant_id = 'test-tenant';
        $company->name = 'Apex Ltd';
        $company->reference_number = 'CO-1-123456789';
        $company->level_of_operations = LevelOfOperations::Local;
        $company->is_active = true;
        $company->is_featured = false;

        $repo = Mockery::mock(CompanyRepository::class);
        $repo->shouldReceive('readCompany')->with($identifier)->once()->andReturn($company);

        $this->app->instance(CompanyRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->getJson("/v1/companies/{$identifier}")
            ->assertOk()
            ->assertJsonPath('data.identifier', $identifier);
    });

    it('PUT /v1/companies/{identifier} returns 200', function (): void {
        $identifier = 'test-company-abc';

        $company = new Company;
        $company->identifier = $identifier;
        $company->tenant_id = 'test-tenant';
        $company->name = 'Updated Company';
        $company->reference_number = 'CO-1-123456789';
        $company->level_of_operations = LevelOfOperations::Local;
        $company->is_active = true;
        $company->is_featured = false;

        $repo = Mockery::mock(CompanyRepository::class);
        $repo->shouldReceive('readCompany')->with($identifier)->once()->andReturn($company);
        $repo->shouldReceive('updateCompany')->with($identifier, Mockery::any())->once()->andReturn($company);

        $this->app->instance(CompanyRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->putJson("/v1/companies/{$identifier}", ['name' => 'Updated Company'])
            ->assertOk();
    });

    it('DELETE /v1/companies/{identifier} returns 204', function (): void {
        $identifier = 'test-company-abc';

        $company = new Company;
        $company->identifier = $identifier;
        $company->tenant_id = 'test-tenant';
        $company->name = 'To Delete';

        $repo = Mockery::mock(CompanyRepository::class);
        $repo->shouldReceive('readCompany')->with($identifier)->once()->andReturn($company);
        $repo->shouldReceive('deleteCompany')->with($identifier)->once();

        $this->app->instance(CompanyRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->deleteJson("/v1/companies/{$identifier}")
            ->assertNoContent();
    });

    it('POST /v1/companies/{identifier}/restore returns 200', function (): void {
        $identifier = 'test-company-abc';

        $company = new Company;
        $company->identifier = $identifier;
        $company->tenant_id = 'test-tenant';
        $company->name = 'Restored Company';
        $company->reference_number = 'CO-1-123456789';
        $company->level_of_operations = LevelOfOperations::Local;
        $company->is_active = true;
        $company->is_featured = false;

        $repo = Mockery::mock(CompanyRepository::class);
        $repo->shouldReceive('readTrashedCompany')->with($identifier)->once()->andReturn($company);
        $repo->shouldReceive('restoreCompany')->with($identifier)->once()->andReturn($company);

        $this->app->instance(CompanyRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson("/v1/companies/{$identifier}/restore")
            ->assertOk();
    });

    it('POST /v1/companies creates company with company_contacts', function (): void {
        $company = new Company;
        $company->identifier = (string) Str::uuid();
        $company->tenant_id = 'test-tenant';
        $company->name = 'Company With Contacts';
        $company->reference_number = 'CO-2-123456789';
        $company->level_of_operations = LevelOfOperations::Local;
        $company->is_active = true;
        $company->is_featured = false;

        $repo = Mockery::mock(CompanyRepository::class);
        $repo->shouldReceive('createCompany')
            ->once()
            ->with(Mockery::on(fn ($data) => isset($data['company_contacts']) && count($data['company_contacts']) === 1))
            ->andReturn($company);

        $this->app->instance(CompanyRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/companies', [
                'name' => 'Company With Contacts',
                'company_contacts' => [
                    ['user_id' => null, 'contact_type' => 'primary'],
                ],
            ])
            ->assertCreated();
    });

    it('PUT /v1/companies/{identifier} updates company_contacts when provided', function (): void {
        $identifier = 'test-company-abc';

        $company = new Company;
        $company->identifier = $identifier;
        $company->tenant_id = 'test-tenant';
        $company->name = 'Updated Company';
        $company->reference_number = 'CO-1-123456789';
        $company->level_of_operations = LevelOfOperations::Local;
        $company->is_active = true;
        $company->is_featured = false;

        $repo = Mockery::mock(CompanyRepository::class);
        $repo->shouldReceive('readCompany')->with($identifier)->once()->andReturn($company);
        $repo->shouldReceive('updateCompany')
            ->once()
            ->with($identifier, Mockery::on(fn ($data) => array_key_exists('company_contacts', $data)))
            ->andReturn($company);

        $this->app->instance(CompanyRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->putJson("/v1/companies/{$identifier}", [
                'name' => 'Updated Company',
                'company_contacts' => [
                    ['user_id' => null, 'contact_type' => 'billing'],
                ],
            ])
            ->assertOk();
    });
});

// ---------------------------------------------------------------------------
// Validation — store request
// ---------------------------------------------------------------------------

describe('Company store validation', function (): void {
    beforeEach(function (): void {
        ensureCompaniesTable();

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
            ->postJson('/v1/companies', [])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['name']]]);
    });

    it('rejects name exceeding 255 chars', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/companies', ['name' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['name']]]);
    });

    it('rejects invalid website URL', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/companies', ['name' => 'Test', 'website' => 'not-a-url'])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['website']]]);
    });

    it('rejects invalid email', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/companies', ['name' => 'Test', 'email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['email']]]);
    });

    it('rejects invalid level_of_operations enum value', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/companies', ['name' => 'Test', 'level_of_operations' => 'galaxy'])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['level_of_operations']]]);
    });

    it('rejects non-boolean is_active', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/companies', ['name' => 'Test', 'is_active' => 'notabool'])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['is_active']]]);
    });

    it('rejects invalid contact_type in company_contacts', function (): void {
        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/companies', [
                'name' => 'Test',
                'company_contacts' => [
                    ['contact_type' => 'invalid-type'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['company_contacts.0.contact_type']]]);
    });

    it('accepts valid level_of_operations values', function (): void {
        $company = new Company;
        $company->identifier = (string) Str::uuid();
        $company->tenant_id = 'test-tenant';
        $company->name = 'International Corp';
        $company->reference_number = 'CO-1-123456789';
        $company->level_of_operations = LevelOfOperations::International;
        $company->is_active = true;
        $company->is_featured = false;

        $repo = Mockery::mock(CompanyRepository::class);
        $repo->shouldReceive('createCompany')->once()->andReturn($company);

        $this->app->instance(CompanyRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/companies', ['name' => 'International Corp', 'level_of_operations' => 'international'])
            ->assertCreated();
    });

    it('accepts nullable optional fields', function (): void {
        $company = new Company;
        $company->identifier = (string) Str::uuid();
        $company->tenant_id = 'test-tenant';
        $company->name = 'Minimal Company';
        $company->reference_number = 'CO-1-123456789';
        $company->level_of_operations = LevelOfOperations::Local;
        $company->is_active = true;
        $company->is_featured = false;

        $repo = Mockery::mock(CompanyRepository::class);
        $repo->shouldReceive('createCompany')->once()->andReturn($company);

        $this->app->instance(CompanyRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->postJson('/v1/companies', [
                'name' => 'Minimal Company',
                'address' => null,
                'office_location' => null,
                'country_id' => null,
            ])
            ->assertCreated();
    });
});

// ---------------------------------------------------------------------------
// Validation — update request
// ---------------------------------------------------------------------------

describe('Company update validation', function (): void {
    beforeEach(function (): void {
        ensureCompaniesTable();

        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            InitializeTenancyByBodyParam::class,
            EnsureTokenMatchesTenant::class,
        ]);

        Gate::before(fn () => true);
    });

    it('rejects name exceeding 255 chars on update', function (): void {
        $identifier = 'test-company-abc';

        $repo = Mockery::mock(CompanyRepository::class);
        $repo->shouldReceive('readCompany')->never();

        $this->app->instance(CompanyRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->putJson("/v1/companies/{$identifier}", ['name' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['name']]]);
    });

    it('rejects invalid level_of_operations on update', function (): void {
        $identifier = 'test-company-abc';

        $repo = Mockery::mock(CompanyRepository::class);
        $repo->shouldReceive('readCompany')->never();

        $this->app->instance(CompanyRepository::class, $repo);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user, 'api')
            ->putJson("/v1/companies/{$identifier}", ['level_of_operations' => 'galaxy'])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['level_of_operations']]]);
    });
});
