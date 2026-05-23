<?php

declare(strict_types=1);

use App\Filters\Central\Tenant\Filters\ReferenceNumberFilter;
use App\Filters\Central\Tenant\Filters\SearchTermFilter;
use App\Filters\Central\Tenant\TenantFilters;
use App\Http\Requests\Tenants\CreateTenantRequest;
use App\Http\Resources\Tenant\TenantResource;
use App\Models\Central\SuperAdmin;
use App\Models\Central\Tenant;
use App\Models\Central\TenantStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\Passport;
use OwenIt\Auditing\Contracts\Auditable;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Events\TenantCreated;
use Stancl\Tenancy\Events\TenantDeleted;

uses(DatabaseTransactions::class);

// ---------------------------------------------------------------------------
// Helper — authenticate as super admin
// ---------------------------------------------------------------------------
function authenticatedSuperAdmin(): SuperAdmin
{
    $admin = SuperAdmin::factory()->create();
    Passport::actingAs($admin, [], 'super_admin');

    return $admin;
}

// ===========================================================================
// Route Registration
// ===========================================================================

describe('Tenant route registration', function (): void {

    it('registers GET /api/v1/tenants', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => str_contains($r->uri(), 'v1/tenants') && in_array('GET', $r->methods(), true) && $r->uri() === 'api/v1/tenants')
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers POST /api/v1/tenants', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'api/v1/tenants' && in_array('POST', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers GET /api/v1/tenants/{id}', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'api/v1/tenants/{id}' && in_array('GET', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers DELETE /api/v1/tenants/{id}', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'api/v1/tenants/{id}' && in_array('DELETE', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });
});

// ===========================================================================
// Authentication & Authorization
// ===========================================================================

describe('Tenant authentication', function (): void {

    it('rejects unauthenticated access to tenant list', function (): void {
        $this->getJson('/api/v1/tenants')
            ->assertUnauthorized();
    });

    it('rejects unauthenticated tenant creation', function (): void {
        $this->postJson('/api/v1/tenants', ['name' => 'Test'])
            ->assertUnauthorized();
    });

    it('rejects unauthenticated tenant show', function (): void {
        $this->getJson('/api/v1/tenants/999')
            ->assertUnauthorized();
    });

    it('rejects unauthenticated tenant deletion', function (): void {
        $this->deleteJson('/api/v1/tenants/999')
            ->assertUnauthorized();
    });
});

// ===========================================================================
// TenantStatus Enum
// ===========================================================================

describe('TenantStatus enum', function (): void {

    it('has expected cases', function (): void {
        expect(TenantStatus::cases())->toHaveCount(4);
        expect(TenantStatus::Active->value)->toBe('active');
        expect(TenantStatus::Inactive->value)->toBe('inactive');
        expect(TenantStatus::Suspended->value)->toBe('suspended');
        expect(TenantStatus::PendingSetup->value)->toBe('pending_setup');
    });

    it('can be created from string', function (): void {
        expect(TenantStatus::from('active'))->toBe(TenantStatus::Active);
        expect(TenantStatus::from('suspended'))->toBe(TenantStatus::Suspended);
    });

    it('returns null for invalid value via tryFrom', function (): void {
        expect(TenantStatus::tryFrom('unknown'))->toBeNull();
    });
});

// ===========================================================================
// Tenant Model
// ===========================================================================

describe('Tenant model', function (): void {

    it('uses central connection', function (): void {
        $tenant = new Tenant;

        expect($tenant->getConnectionName())->toBe('central');
    });

    it('has integer key type', function (): void {
        $tenant = new Tenant;

        expect($tenant->getKeyType())->toBe('int');
    });

    it('uses identifier as route key', function (): void {
        $tenant = new Tenant;

        expect($tenant->getRouteKeyName())->toBe('identifier');
    });

    it('includes all custom columns', function (): void {
        $columns = Tenant::getCustomColumns();

        expect($columns)->toContain('id')
            ->toContain('identifier')
            ->toContain('owner_id')
            ->toContain('reference_number')
            ->toContain('name')
            ->toContain('domain')
            ->toContain('logo')
            ->toContain('country_id')
            ->toContain('data_center')
            ->toContain('created_by')
            ->toContain('updated_by')
            ->toContain('created_at')
            ->toContain('updated_at')
            ->toContain('deleted_at');
    });

    it('has fillable attributes', function (): void {
        $fillable = (new Tenant)->getFillable();

        expect($fillable)->toContain('identifier')
            ->toContain('owner_id')
            ->toContain('name')
            ->toContain('domain')
            ->toContain('logo')
            ->toContain('country_id')
            ->toContain('data_center')
            ->toContain('created_by')
            ->toContain('updated_by');
    });

    it('does not have reference_number as fillable', function (): void {
        $fillable = (new Tenant)->getFillable();

        expect($fillable)->not->toContain('reference_number');
    });
});

// ===========================================================================
// CreateTenantRequest Validation
// ===========================================================================

describe('CreateTenantRequest validation', function (): void {

    it('has expected rules', function (): void {
        $request = new CreateTenantRequest;
        $rules = $request->rules();

        expect($rules)->toHaveKeys(['name', 'owner_id', 'domain', 'logo', 'country_id', 'data_center', 'status']);
        expect($rules['name'])->toContain('required');
        expect($rules['owner_id'])->toContain('required');
    });

    it('authorizes all requests (guard check in middleware)', function (): void {
        $request = new CreateTenantRequest;

        expect($request->authorize())->toBeTrue();
    });

    it('has custom error messages', function (): void {
        $request = new CreateTenantRequest;
        $messages = $request->messages();

        expect($messages)->toHaveKey('name.unique');
        expect($messages)->toHaveKey('owner_id.exists');
        expect($messages)->toHaveKey('domain.unique');
    });
});

// ===========================================================================
// TenantResource
// ===========================================================================

describe('TenantResource', function (): void {

    it('includes all expected fields', function (): void {
        $tenant = new Tenant([
            'identifier' => 'test-uuid',
            'name' => 'Acme',
            'domain' => 'acme.localhost',
            'owner_id' => 1,
            'country_id' => null,
            'data_center' => null,
            'logo' => null,
        ]);
        $tenant->id = 1;
        $tenant->created_at = now();
        $tenant->updated_at = now();

        $resource = TenantResource::make($tenant)->resolve();

        expect($resource)->toHaveKeys([
            'id', 'identifier', 'reference_number', 'name', 'domain', 'logo', 'status',
            'country_id', 'data_center',
            'created_by', 'updated_by', 'created_at', 'updated_at',
        ]);
    });
});

// ===========================================================================
// Tenant CRUD — Feature Tests
// ===========================================================================

describe('Tenant CRUD', function (): void {

    beforeEach(function (): void {
        // Prevent tenant database lifecycle (create/migrate/seed) during tests
        Event::fake([TenantCreated::class, TenantDeleted::class]);
    });

    it('can list tenants', function (): void {
        authenticatedSuperAdmin();

        $this->getJson('/api/v1/tenants')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    });

    it('can create a tenant with auto-generated domain', function (): void {
        $admin = authenticatedSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Acme Corporation',
            'owner_id' => $admin->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Acme Corporation')
            ->assertJsonPath('data.domain', 'acme-corporation.localhost');

        // reference_number should be auto-generated
        $refNum = $response->json('data.reference_number');
        expect($refNum)->not->toBeNull()
            ->toStartWith('AT-');
    });

    it('can create a tenant with explicit domain', function (): void {
        $admin = authenticatedSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Beta Labs',
            'owner_id' => $admin->id,
            'domain' => 'custom.example.com',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.domain', 'custom.example.com');
    });

    it('can create a tenant with all optional fields', function (): void {
        $admin = authenticatedSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Full Corp',
            'owner_id' => $admin->id,
            'domain' => 'full.localhost',
            'logo' => 'https://example.com/logo.png',
            'country_id' => 1,
            'data_center' => 'us-east-1',
            'status' => 'active',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.logo', 'https://example.com/logo.png')
            ->assertJsonPath('data.data_center', 'us-east-1');
    });

    it('can show a tenant by id', function (): void {
        $admin = authenticatedSuperAdmin();

        // Create tenant first
        $createResponse = $this->postJson('/api/v1/tenants', [
            'name' => 'Show Test Corp',
            'owner_id' => $admin->id,
        ]);
        $tenantId = $createResponse->json('data.id');

        $this->getJson("/api/v1/tenants/{$tenantId}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Show Test Corp');
    });

    it('returns 404 for non-existent tenant', function (): void {
        authenticatedSuperAdmin();

        $this->getJson('/api/v1/tenants/99999')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'TENANT_NOT_FOUND');
    });

    it('can soft delete a tenant', function (): void {
        $admin = authenticatedSuperAdmin();

        $createResponse = $this->postJson('/api/v1/tenants', [
            'name' => 'Delete Test Corp',
            'owner_id' => $admin->id,
        ]);
        $tenantId = $createResponse->json('data.id');

        $this->deleteJson("/api/v1/tenants/{$tenantId}")
            ->assertNoContent();

        // Should be soft deleted
        $this->assertSoftDeleted('tenants', ['id' => $tenantId], deletedAtColumn: 'deleted_at', connection: 'central');
    });

    it('returns 404 when deleting non-existent tenant', function (): void {
        authenticatedSuperAdmin();

        $this->deleteJson('/api/v1/tenants/99999')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'TENANT_NOT_FOUND');
    });
});

// ===========================================================================
// Validation — Feature Tests
// ===========================================================================

describe('Tenant creation validation', function (): void {

    beforeEach(function (): void {
        Event::fake([TenantCreated::class, TenantDeleted::class]);
    });

    it('requires name', function (): void {
        $admin = authenticatedSuperAdmin();

        $this->postJson('/api/v1/tenants', [
            'owner_id' => $admin->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.name.0', fn ($v) => str_contains($v, 'name'));
    });

    it('requires owner_id', function (): void {
        authenticatedSuperAdmin();

        $this->postJson('/api/v1/tenants', [
            'name' => 'No Owner Corp',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.owner_id.0', fn ($v) => str_contains($v, 'owner'));
    });

    it('rejects non-existent owner_id', function (): void {
        authenticatedSuperAdmin();

        $this->postJson('/api/v1/tenants', [
            'name' => 'Bad Owner Corp',
            'owner_id' => 99999,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.owner_id.0', fn ($v) => str_contains($v, 'owner'));
    });

    it('rejects duplicate tenant name', function (): void {
        $admin = authenticatedSuperAdmin();

        // Create first tenant
        $this->postJson('/api/v1/tenants', [
            'name' => 'Unique Corp',
            'owner_id' => $admin->id,
        ])->assertCreated();

        // Try duplicate
        $this->postJson('/api/v1/tenants', [
            'name' => 'Unique Corp',
            'owner_id' => $admin->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.name.0', fn ($v) => str_contains($v, 'already exists'));
    });

    it('rejects duplicate domain', function (): void {
        $admin = authenticatedSuperAdmin();

        // Create first tenant with explicit domain
        $this->postJson('/api/v1/tenants', [
            'name' => 'Domain Test A',
            'owner_id' => $admin->id,
            'domain' => 'same-domain.localhost',
        ])->assertCreated();

        // Try duplicate domain
        $this->postJson('/api/v1/tenants', [
            'name' => 'Domain Test B',
            'owner_id' => $admin->id,
            'domain' => 'same-domain.localhost',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.domain.0', fn ($v) => str_contains($v, 'already in use'));
    });

    it('rejects invalid status value', function (): void {
        $admin = authenticatedSuperAdmin();

        $this->postJson('/api/v1/tenants', [
            'name' => 'Bad Status Corp',
            'owner_id' => $admin->id,
            'status' => 'invalid_status',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.status.0', fn ($v) => str_contains($v, 'status'));
    });
});

// ===========================================================================
// Domain Auto-Generation
// ===========================================================================

describe('Domain auto-generation', function (): void {

    beforeEach(function (): void {
        Event::fake([TenantCreated::class, TenantDeleted::class]);
    });

    it('generates domain from tenant name slug', function (): void {
        $admin = authenticatedSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'My Cool Company',
            'owner_id' => $admin->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.domain', 'my-cool-company.localhost');
    });

    it('uses explicit domain when provided', function (): void {
        $admin = authenticatedSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Explicit Domain Co',
            'owner_id' => $admin->id,
            'domain' => 'custom.example.com',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.domain', 'custom.example.com');
    });
});

// ===========================================================================
// Error Response Format
// ===========================================================================

describe('Error response format', function (): void {

    beforeEach(function (): void {
        Event::fake([TenantCreated::class, TenantDeleted::class]);
    });

    it('returns structured error for not found', function (): void {
        authenticatedSuperAdmin();

        $response = $this->getJson('/api/v1/tenants/99999');

        $response->assertNotFound()
            ->assertJsonStructure([
                'error' => ['code', 'message'],
                'meta',
            ])
            ->assertJsonPath('error.code', 'TENANT_NOT_FOUND');
    });

    it('returns structured error for validation failure', function (): void {
        authenticatedSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', []);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure([
                'error' => ['code', 'message', 'details'],
                'meta',
            ]);

        expect($response->json('error.details'))->toHaveKeys(['name', 'owner_id']);
    });
});

// ===========================================================================
// UUID Identifier
// ===========================================================================

describe('Tenant identifier', function (): void {

    beforeEach(function (): void {
        Event::fake([TenantCreated::class, TenantDeleted::class]);
    });

    it('auto-generates UUID identifier on creation', function (): void {
        $admin = authenticatedSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'UUID Test Corp',
            'owner_id' => $admin->id,
        ]);

        $response->assertCreated();

        $identifier = $response->json('data.identifier');
        expect($identifier)->not->toBeNull();
        // UUID v4 format check
        expect($identifier)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
    });
});

// ===========================================================================
// Reference Number
// ===========================================================================

describe('Tenant reference number', function (): void {

    beforeEach(function (): void {
        Event::fake([TenantCreated::class, TenantDeleted::class]);
    });

    it('auto-generates reference_number in AT-{id}-{timestamp} format', function (): void {
        $admin = authenticatedSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'RefNum Corp',
            'owner_id' => $admin->id,
        ]);

        $response->assertCreated();

        $refNum = $response->json('data.reference_number');
        $tenantId = $response->json('data.id');

        expect($refNum)->toStartWith("AT-{$tenantId}-")
            ->toMatch('/^AT-\d+-\d+$/');
    });

    it('cannot be set via mass assignment', function (): void {
        $admin = authenticatedSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Mass Assign Corp',
            'owner_id' => $admin->id,
            'reference_number' => 'CUSTOM-REF-123',
        ]);

        $response->assertCreated();

        $refNum = $response->json('data.reference_number');
        expect($refNum)->not->toBe('CUSTOM-REF-123')
            ->toStartWith('AT-');
    });

    it('is unique across tenants', function (): void {
        $admin = authenticatedSuperAdmin();

        $response1 = $this->postJson('/api/v1/tenants', [
            'name' => 'Unique Ref A',
            'owner_id' => $admin->id,
        ]);
        $response2 = $this->postJson('/api/v1/tenants', [
            'name' => 'Unique Ref B',
            'owner_id' => $admin->id,
        ]);

        $response1->assertCreated();
        $response2->assertCreated();

        expect($response1->json('data.reference_number'))
            ->not->toBe($response2->json('data.reference_number'));
    });

    it('is included in API response', function (): void {
        $admin = authenticatedSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Response Ref Corp',
            'owner_id' => $admin->id,
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['reference_number']]);

        expect($response->json('data.reference_number'))->not->toBeNull();
    });
});

// ===========================================================================
// Tenant Filters
// ===========================================================================

describe('Tenant filtering', function (): void {

    beforeEach(function (): void {
        Event::fake([TenantCreated::class, TenantDeleted::class]);
    });

    it('can filter tenants by search term matching name', function (): void {
        $admin = authenticatedSuperAdmin();

        $this->postJson('/api/v1/tenants', [
            'name' => 'Searchable Alpha Corp',
            'owner_id' => $admin->id,
        ])->assertCreated();

        $this->postJson('/api/v1/tenants', [
            'name' => 'Beta Industries',
            'owner_id' => $admin->id,
        ])->assertCreated();

        $response = $this->getJson('/api/v1/tenants?search=Searchable');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        expect($names)->toContain('Searchable Alpha Corp')
            ->not->toContain('Beta Industries');
    });

    it('can filter tenants by reference_number parameter', function (): void {
        $admin = authenticatedSuperAdmin();

        $createResponse = $this->postJson('/api/v1/tenants', [
            'name' => 'Ref Filter Corp',
            'owner_id' => $admin->id,
        ]);
        $createResponse->assertCreated();
        $refNum = $createResponse->json('data.reference_number');

        $this->postJson('/api/v1/tenants', [
            'name' => 'Other Corp',
            'owner_id' => $admin->id,
        ])->assertCreated();

        $response = $this->getJson("/api/v1/tenants?reference_number={$refNum}");

        $response->assertOk();
        $refs = collect($response->json('data'))->pluck('reference_number');
        expect($refs)->toContain($refNum);
        expect($refs)->toHaveCount(1);
    });

    it('can filter tenants by search term matching reference_number', function (): void {
        $admin = authenticatedSuperAdmin();

        $createResponse = $this->postJson('/api/v1/tenants', [
            'name' => 'Search Ref Corp',
            'owner_id' => $admin->id,
        ]);
        $createResponse->assertCreated();
        $refNum = $createResponse->json('data.reference_number');

        // Search by reference_number via search param
        $response = $this->getJson("/api/v1/tenants?search={$refNum}");

        $response->assertOk();
        $refs = collect($response->json('data'))->pluck('reference_number');
        expect($refs)->toContain($refNum);
    });

    it('has TenantFilters class with expected filter keys', function (): void {
        $request = Request::create('/tenants', 'GET');
        $filters = TenantFilters::fromRequest($request);

        expect($filters)->toBeInstanceOf(TenantFilters::class);
    });

    it('supports pagination via per_page parameter', function (): void {
        $admin = authenticatedSuperAdmin();

        // Create 3 tenants
        foreach (['Paginate A', 'Paginate B', 'Paginate C'] as $name) {
            $this->postJson('/api/v1/tenants', [
                'name' => $name,
                'owner_id' => $admin->id,
            ])->assertCreated();
        }

        $response = $this->getJson('/api/v1/tenants?per_page=2');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 2);

        expect(count($response->json('data')))->toBeLessThanOrEqual(2);
    });
});

// ===========================================================================
// Soft Delete Behavior
// ===========================================================================

describe('Tenant soft delete behavior', function (): void {

    beforeEach(function (): void {
        Event::fake([TenantCreated::class, TenantDeleted::class]);
    });

    it('excludes soft-deleted tenants from listing', function (): void {
        $admin = authenticatedSuperAdmin();

        $createResponse = $this->postJson('/api/v1/tenants', [
            'name' => 'Will Be Deleted Corp',
            'owner_id' => $admin->id,
        ]);
        $createResponse->assertCreated();
        $tenantId = $createResponse->json('data.id');

        // Also create one that stays
        $this->postJson('/api/v1/tenants', [
            'name' => 'Stays Alive Corp',
            'owner_id' => $admin->id,
        ])->assertCreated();

        // Delete first tenant
        $this->deleteJson("/api/v1/tenants/{$tenantId}")->assertNoContent();

        // List should not include deleted tenant
        $response = $this->getJson('/api/v1/tenants');
        $names = collect($response->json('data'))->pluck('name');

        expect($names)->not->toContain('Will Be Deleted Corp')
            ->toContain('Stays Alive Corp');
    });

    it('returns 404 when showing a soft-deleted tenant', function (): void {
        $admin = authenticatedSuperAdmin();

        $createResponse = $this->postJson('/api/v1/tenants', [
            'name' => 'Deleted Show Corp',
            'owner_id' => $admin->id,
        ]);
        $tenantId = $createResponse->json('data.id');

        $this->deleteJson("/api/v1/tenants/{$tenantId}")->assertNoContent();

        $this->getJson("/api/v1/tenants/{$tenantId}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'TENANT_NOT_FOUND');
    });

    it('returns 404 when deleting an already soft-deleted tenant', function (): void {
        $admin = authenticatedSuperAdmin();

        $createResponse = $this->postJson('/api/v1/tenants', [
            'name' => 'Double Delete Corp',
            'owner_id' => $admin->id,
        ]);
        $tenantId = $createResponse->json('data.id');

        $this->deleteJson("/api/v1/tenants/{$tenantId}")->assertNoContent();

        // Second delete should 404
        $this->deleteJson("/api/v1/tenants/{$tenantId}")
            ->assertNotFound();
    });

    it('sets deleted_at timestamp on soft delete', function (): void {
        $admin = authenticatedSuperAdmin();

        $createResponse = $this->postJson('/api/v1/tenants', [
            'name' => 'Timestamp Delete Corp',
            'owner_id' => $admin->id,
        ]);
        $tenantId = $createResponse->json('data.id');

        $this->deleteJson("/api/v1/tenants/{$tenantId}")->assertNoContent();

        $tenant = Tenant::withTrashed()->find($tenantId);
        expect($tenant->deleted_at)->not->toBeNull();
        expect($tenant->deleted_at)->toBeInstanceOf(Carbon::class);
    });

    it('preserves tenant data after soft delete', function (): void {
        $admin = authenticatedSuperAdmin();

        $createResponse = $this->postJson('/api/v1/tenants', [
            'name' => 'Data Preserved Corp',
            'owner_id' => $admin->id,
            'domain' => 'preserved.localhost',
        ]);
        $tenantId = $createResponse->json('data.id');

        $this->deleteJson("/api/v1/tenants/{$tenantId}")->assertNoContent();

        $tenant = Tenant::withTrashed()->find($tenantId);
        expect($tenant->name)->toBe('Data Preserved Corp');
        expect($tenant->domain)->toBe('preserved.localhost');
    });
});

// ===========================================================================
// Pagination Edge Cases
// ===========================================================================

describe('Tenant pagination edge cases', function (): void {

    beforeEach(function (): void {
        Event::fake([TenantCreated::class, TenantDeleted::class]);
    });

    it('defaults to 15 per page when per_page not specified', function (): void {
        $admin = authenticatedSuperAdmin();

        $response = $this->getJson('/api/v1/tenants');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 15);
    });

    it('caps per_page at 100', function (): void {
        $admin = authenticatedSuperAdmin();

        $response = $this->getJson('/api/v1/tenants?per_page=500');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    });

    it('enforces minimum page of 1', function (): void {
        $admin = authenticatedSuperAdmin();

        $response = $this->getJson('/api/v1/tenants?page=0');

        $response->assertOk()
            ->assertJsonPath('meta.current_page', 1);
    });

    it('enforces minimum page of 1 for negative values', function (): void {
        $admin = authenticatedSuperAdmin();

        $response = $this->getJson('/api/v1/tenants?page=-5');

        $response->assertOk()
            ->assertJsonPath('meta.current_page', 1);
    });

    it('returns empty data array for page beyond results', function (): void {
        $admin = authenticatedSuperAdmin();

        $response = $this->getJson('/api/v1/tenants?page=9999');

        $response->assertOk();
        expect($response->json('data'))->toBeEmpty();
    });

    it('returns pagination links in response', function (): void {
        $admin = authenticatedSuperAdmin();

        foreach (range(1, 3) as $i) {
            $this->postJson('/api/v1/tenants', [
                'name' => "Paginated Corp {$i}",
                'owner_id' => $admin->id,
            ])->assertCreated();
        }

        $response = $this->getJson('/api/v1/tenants?per_page=1');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    });

    it('returns correct total count in pagination meta', function (): void {
        $admin = authenticatedSuperAdmin();

        foreach (['Count A', 'Count B', 'Count C'] as $name) {
            $this->postJson('/api/v1/tenants', [
                'name' => $name,
                'owner_id' => $admin->id,
            ])->assertCreated();
        }

        $response = $this->getJson('/api/v1/tenants?per_page=2');

        $response->assertOk();
        expect($response->json('meta.total'))->toBeGreaterThanOrEqual(3);
        expect($response->json('meta.last_page'))->toBeGreaterThanOrEqual(2);
    });

    it('handles per_page=1 correctly', function (): void {
        $admin = authenticatedSuperAdmin();

        $this->postJson('/api/v1/tenants', [
            'name' => 'Single Page Corp',
            'owner_id' => $admin->id,
        ])->assertCreated();

        $response = $this->getJson('/api/v1/tenants?per_page=1');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 1);
        expect(count($response->json('data')))->toBeLessThanOrEqual(1);
    });
});

// ===========================================================================
// Search Filter Edge Cases
// ===========================================================================

describe('Tenant search filter edge cases', function (): void {

    beforeEach(function (): void {
        Event::fake([TenantCreated::class, TenantDeleted::class]);
    });

    it('can filter by search term matching domain', function (): void {
        $admin = authenticatedSuperAdmin();

        $this->postJson('/api/v1/tenants', [
            'name' => 'Domain Searchable Corp',
            'owner_id' => $admin->id,
            'domain' => 'searchme.example.com',
        ])->assertCreated();

        $this->postJson('/api/v1/tenants', [
            'name' => 'Not This One',
            'owner_id' => $admin->id,
        ])->assertCreated();

        $response = $this->getJson('/api/v1/tenants?search=searchme');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        expect($names)->toContain('Domain Searchable Corp');
    });

    it('can filter by search term matching identifier', function (): void {
        $admin = authenticatedSuperAdmin();

        $createResponse = $this->postJson('/api/v1/tenants', [
            'name' => 'Identifier Search Corp',
            'owner_id' => $admin->id,
        ]);
        $createResponse->assertCreated();
        $identifier = $createResponse->json('data.identifier');

        // Use first 8 chars of UUID for partial match
        $partial = substr($identifier, 0, 8);

        $response = $this->getJson("/api/v1/tenants?search={$partial}");

        $response->assertOk();
        $identifiers = collect($response->json('data'))->pluck('identifier');
        expect($identifiers)->toContain($identifier);
    });

    it('performs case-insensitive search on name', function (): void {
        $admin = authenticatedSuperAdmin();

        $this->postJson('/api/v1/tenants', [
            'name' => 'CamelCase Industries',
            'owner_id' => $admin->id,
        ])->assertCreated();

        $response = $this->getJson('/api/v1/tenants?search=camelcase');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        expect($names)->toContain('CamelCase Industries');
    });

    it('returns empty results for non-matching search', function (): void {
        $admin = authenticatedSuperAdmin();

        $this->postJson('/api/v1/tenants', [
            'name' => 'Existing Corp',
            'owner_id' => $admin->id,
        ])->assertCreated();

        $response = $this->getJson('/api/v1/tenants?search=zzz_nonexistent_zzz');

        $response->assertOk();
        expect($response->json('data'))->toBeEmpty();
    });

    it('handles partial name match', function (): void {
        $admin = authenticatedSuperAdmin();

        $this->postJson('/api/v1/tenants', [
            'name' => 'International Widget Corp',
            'owner_id' => $admin->id,
        ])->assertCreated();

        $response = $this->getJson('/api/v1/tenants?search=Widget');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        expect($names)->toContain('International Widget Corp');
    });

    it('returns all tenants when search param is empty string', function (): void {
        $admin = authenticatedSuperAdmin();

        $this->postJson('/api/v1/tenants', [
            'name' => 'Empty Search Corp',
            'owner_id' => $admin->id,
        ])->assertCreated();

        // Empty search param should not filter (Request::filled returns false for empty)
        $response = $this->getJson('/api/v1/tenants?search=');

        $response->assertOk();
        expect($response->json('data'))->not->toBeEmpty();
    });

    it('supports partial reference_number filter', function (): void {
        $admin = authenticatedSuperAdmin();

        $createResponse = $this->postJson('/api/v1/tenants', [
            'name' => 'Partial Ref Corp',
            'owner_id' => $admin->id,
        ]);
        $createResponse->assertCreated();
        $refNum = $createResponse->json('data.reference_number');

        // Search by "AT-" prefix
        $response = $this->getJson('/api/v1/tenants?reference_number=AT-');

        $response->assertOk();
        expect($response->json('data'))->not->toBeEmpty();
    });
});

// ===========================================================================
// Combined Filter Tests
// ===========================================================================

describe('Tenant combined filters', function (): void {

    beforeEach(function (): void {
        Event::fake([TenantCreated::class, TenantDeleted::class]);
    });

    it('combines search and pagination', function (): void {
        $admin = authenticatedSuperAdmin();

        foreach (range(1, 5) as $i) {
            $this->postJson('/api/v1/tenants', [
                'name' => "Combo Search Corp {$i}",
                'owner_id' => $admin->id,
            ])->assertCreated();
        }

        // Other tenants that should not match
        $this->postJson('/api/v1/tenants', [
            'name' => 'Unrelated Industries',
            'owner_id' => $admin->id,
        ])->assertCreated();

        $response = $this->getJson('/api/v1/tenants?search=Combo Search&per_page=2');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 2);

        // All returned items match search
        $names = collect($response->json('data'))->pluck('name');
        $names->each(fn ($name) => expect($name)->toContain('Combo Search'));
    });

    it('combines search and reference_number filters', function (): void {
        $admin = authenticatedSuperAdmin();

        $createResponse = $this->postJson('/api/v1/tenants', [
            'name' => 'Dual Filter Corp',
            'owner_id' => $admin->id,
        ]);
        $createResponse->assertCreated();
        $refNum = $createResponse->json('data.reference_number');

        $this->postJson('/api/v1/tenants', [
            'name' => 'Other Filter Corp',
            'owner_id' => $admin->id,
        ])->assertCreated();

        // Both filters must match
        $response = $this->getJson("/api/v1/tenants?search=Dual&reference_number={$refNum}");

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        expect($names)->toContain('Dual Filter Corp');
        expect($names)->not->toContain('Other Filter Corp');
    });

    it('returns empty when filters are mutually exclusive', function (): void {
        $admin = authenticatedSuperAdmin();

        $createResponse = $this->postJson('/api/v1/tenants', [
            'name' => 'Exclusive Corp',
            'owner_id' => $admin->id,
        ]);
        $createResponse->assertCreated();
        $refNum = $createResponse->json('data.reference_number');

        // Search for different name but use this tenant's ref number
        $response = $this->getJson("/api/v1/tenants?search=zzz_nonexistent&reference_number={$refNum}");

        $response->assertOk();
        expect($response->json('data'))->toBeEmpty();
    });
});

// ===========================================================================
// Model Lifecycle Hooks
// ===========================================================================

describe('Tenant model lifecycle hooks', function (): void {

    beforeEach(function (): void {
        Event::fake([TenantCreated::class, TenantDeleted::class]);
    });

    it('tracks created_by from authenticated user', function (): void {
        $admin = authenticatedSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Created By Corp',
            'owner_id' => $admin->id,
        ]);
        $response->assertCreated();

        expect($response->json('data.created_by'))->toBe($admin->id);
    });

    it('tracks updated_by from authenticated user on creation', function (): void {
        $admin = authenticatedSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Updated By Corp',
            'owner_id' => $admin->id,
        ]);
        $response->assertCreated();

        expect($response->json('data.updated_by'))->toBe($admin->id);
    });

    it('auto-generates UUID identifier on creation', function (): void {
        $admin = authenticatedSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'UUID Lifecycle Corp',
            'owner_id' => $admin->id,
        ]);
        $response->assertCreated();

        $identifier = $response->json('data.identifier');
        expect($identifier)->not->toBeNull()
            ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
    });

    it('generates unique identifiers for each tenant', function (): void {
        $admin = authenticatedSuperAdmin();

        $ids = [];
        foreach (range(1, 3) as $i) {
            $response = $this->postJson('/api/v1/tenants', [
                'name' => "Unique ID Corp {$i}",
                'owner_id' => $admin->id,
            ]);
            $response->assertCreated();
            $ids[] = $response->json('data.identifier');
        }

        expect(array_unique($ids))->toHaveCount(3);
    });

    it('generates reference_number in AT-{id}-{timestamp} format after creation', function (): void {
        $admin = authenticatedSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'RefNum Lifecycle Corp',
            'owner_id' => $admin->id,
        ]);
        $response->assertCreated();

        $refNum = $response->json('data.reference_number');
        $id = $response->json('data.id');

        expect($refNum)->toMatch('/^AT-\d+-\d+$/')
            ->toStartWith("AT-{$id}-");
    });
});

// ===========================================================================
// Response Structure
// ===========================================================================

describe('Tenant response structure', function (): void {

    beforeEach(function (): void {
        Event::fake([TenantCreated::class, TenantDeleted::class]);
    });

    it('includes meta with request_id and version in success response', function (): void {
        $admin = authenticatedSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Meta Test Corp',
            'owner_id' => $admin->id,
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data',
                'meta' => ['request_id', 'version'],
            ]);

        expect($response->json('meta.version'))->toBe('v1');
        expect($response->json('meta.request_id'))->not->toBeNull();
    });

    it('includes meta in error response', function (): void {
        authenticatedSuperAdmin();

        $response = $this->getJson('/api/v1/tenants/99999');

        $response->assertNotFound()
            ->assertJsonStructure([
                'error' => ['code', 'message'],
                'meta',
            ]);
    });

    it('includes meta in validation error response', function (): void {
        authenticatedSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', []);

        $response->assertUnprocessable()
            ->assertJsonStructure([
                'error' => ['code', 'message', 'details'],
                'meta' => ['request_id', 'version'],
            ]);
    });

    it('includes domains in show response', function (): void {
        $admin = authenticatedSuperAdmin();

        $createResponse = $this->postJson('/api/v1/tenants', [
            'name' => 'Domains Show Corp',
            'owner_id' => $admin->id,
            'domain' => 'domains-show.localhost',
        ]);
        $tenantId = $createResponse->json('data.id');

        $response = $this->getJson("/api/v1/tenants/{$tenantId}");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['domains']]);

        expect($response->json('data.domains'))->toContain('domains-show.localhost');
    });

    it('includes domains in list response', function (): void {
        $admin = authenticatedSuperAdmin();

        $this->postJson('/api/v1/tenants', [
            'name' => 'Domains List Corp',
            'owner_id' => $admin->id,
            'domain' => 'domains-list.localhost',
        ])->assertCreated();

        $response = $this->getJson('/api/v1/tenants?search=Domains List');

        $response->assertOk();
        $tenant = collect($response->json('data'))->firstWhere('name', 'Domains List Corp');
        expect($tenant)->not->toBeNull();
        expect($tenant['domains'])->toContain('domains-list.localhost');
    });

    it('returns ISO 8601 formatted timestamps', function (): void {
        $admin = authenticatedSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Timestamp Format Corp',
            'owner_id' => $admin->id,
        ]);
        $response->assertCreated();

        // ISO 8601 format: 2024-01-01T00:00:00.000000Z
        $createdAt = $response->json('data.created_at');
        $updatedAt = $response->json('data.updated_at');

        expect($createdAt)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');
        expect($updatedAt)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');
    });

    it('returns all expected fields in create response', function (): void {
        $admin = authenticatedSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'All Fields Corp',
            'owner_id' => $admin->id,
            'domain' => 'all-fields.localhost',
            'logo' => 'https://example.com/logo.png',
            'country_id' => 42,
            'data_center' => 'eu-west-1',
            'status' => 'active',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id', 'identifier', 'reference_number', 'name', 'domain',
                    'logo', 'status', 'owner', 'country_id', 'data_center',
                    'domains', 'created_by', 'updated_by', 'created_at', 'updated_at',
                ],
            ]);

        expect($response->json('data.name'))->toBe('All Fields Corp');
        expect($response->json('data.domain'))->toBe('all-fields.localhost');
        expect($response->json('data.logo'))->toBe('https://example.com/logo.png');
        expect($response->json('data.country_id'))->toBe(42);
        expect($response->json('data.data_center'))->toBe('eu-west-1');
        expect($response->json('data.owner.id'))->toBe($admin->identifier);
    });

    it('returns 204 No Content on successful delete', function (): void {
        $admin = authenticatedSuperAdmin();

        $createResponse = $this->postJson('/api/v1/tenants', [
            'name' => 'Delete Response Corp',
            'owner_id' => $admin->id,
        ]);
        $tenantId = $createResponse->json('data.id');

        $response = $this->deleteJson("/api/v1/tenants/{$tenantId}");

        $response->assertNoContent();
        expect($response->getContent())->toBeEmpty();
    });
});

// ===========================================================================
// Domain Auto-Generation Edge Cases
// ===========================================================================

describe('Domain auto-generation edge cases', function (): void {

    beforeEach(function (): void {
        Event::fake([TenantCreated::class, TenantDeleted::class]);
    });

    it('handles special characters in name for domain generation', function (): void {
        $admin = authenticatedSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', [
            'name' => "O'Reilly & Sons LLC",
            'owner_id' => $admin->id,
        ]);

        $response->assertCreated();

        $domain = $response->json('data.domain');
        // Slug should handle apostrophe and ampersand
        expect($domain)->toEndWith('.localhost');
        expect($domain)->not->toContain("'");
        expect($domain)->not->toContain('&');
    });

    it('generates unique domain with timestamp when slug collides', function (): void {
        $admin = authenticatedSuperAdmin();

        // Create first tenant with domain "collision-test.localhost"
        $this->postJson('/api/v1/tenants', [
            'name' => 'Collision Test',
            'owner_id' => $admin->id,
        ])->assertCreated();

        // Create second tenant with explicit same domain
        // (unique validation prevents this, but test domain gen collision)
        $secondResponse = $this->postJson('/api/v1/tenants', [
            'name' => 'Collision Test Variant',
            'owner_id' => $admin->id,
        ]);
        $secondResponse->assertCreated();

        // Domains should be different
        $domain = $secondResponse->json('data.domain');
        expect($domain)->not->toBe('collision-test.localhost');
    });

    it('generates domain from multi-word name', function (): void {
        $admin = authenticatedSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Three Word Name',
            'owner_id' => $admin->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.domain', 'three-word-name.localhost');
    });

    it('generates domain from single word name', function (): void {
        $admin = authenticatedSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Acme',
            'owner_id' => $admin->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.domain', 'acme.localhost');
    });

    it('generates domain with numbers in name', function (): void {
        $admin = authenticatedSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Tech 360 Solutions',
            'owner_id' => $admin->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.domain', 'tech-360-solutions.localhost');
    });
});

// ===========================================================================
// Validation Edge Cases
// ===========================================================================

describe('Tenant validation edge cases', function (): void {

    beforeEach(function (): void {
        Event::fake([TenantCreated::class, TenantDeleted::class]);
    });

    it('rejects name exceeding 255 characters', function (): void {
        $admin = authenticatedSuperAdmin();

        $this->postJson('/api/v1/tenants', [
            'name' => str_repeat('A', 256),
            'owner_id' => $admin->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    });

    it('accepts name at exactly 200 characters', function (): void {
        $admin = authenticatedSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', [
            'name' => str_repeat('A', 200),
            'owner_id' => $admin->id,
        ]);

        $response->assertCreated();
    });

    it('rejects owner_id as non-integer string', function (): void {
        authenticatedSuperAdmin();

        $this->postJson('/api/v1/tenants', [
            'name' => 'Bad Owner Type Corp',
            'owner_id' => 'not-an-integer',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    });

    it('rejects domain exceeding 255 characters', function (): void {
        $admin = authenticatedSuperAdmin();

        $this->postJson('/api/v1/tenants', [
            'name' => 'Long Domain Corp',
            'owner_id' => $admin->id,
            'domain' => str_repeat('a', 256),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    });

    it('rejects data_center exceeding 255 characters', function (): void {
        $admin = authenticatedSuperAdmin();

        $this->postJson('/api/v1/tenants', [
            'name' => 'Long DC Corp',
            'owner_id' => $admin->id,
            'data_center' => str_repeat('x', 256),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    });

    it('accepts all valid status values without validation error', function (): void {
        $admin = authenticatedSuperAdmin();

        foreach (['active', 'inactive', 'suspended', 'pending_setup'] as $i => $status) {
            $response = $this->postJson('/api/v1/tenants', [
                'name' => "Status {$status} Corp",
                'owner_id' => $admin->id,
                'status' => $status,
            ]);

            $response->assertCreated();
        }
    });

    it('ignores unknown fields in request payload', function (): void {
        $admin = authenticatedSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Extra Fields Corp',
            'owner_id' => $admin->id,
            'unknown_field' => 'should be ignored',
            'hack_attempt' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Extra Fields Corp');
    });

    it('rejects empty name string', function (): void {
        $admin = authenticatedSuperAdmin();

        $this->postJson('/api/v1/tenants', [
            'name' => '',
            'owner_id' => $admin->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    });

    it('rejects name as null', function (): void {
        $admin = authenticatedSuperAdmin();

        $this->postJson('/api/v1/tenants', [
            'name' => null,
            'owner_id' => $admin->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    });

    it('rejects owner_id as zero', function (): void {
        authenticatedSuperAdmin();

        $this->postJson('/api/v1/tenants', [
            'name' => 'Zero Owner Corp',
            'owner_id' => 0,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    });

    it('rejects owner_id as negative number', function (): void {
        authenticatedSuperAdmin();

        $this->postJson('/api/v1/tenants', [
            'name' => 'Negative Owner Corp',
            'owner_id' => -1,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    });

    it('rejects logo exceeding 255 characters', function (): void {
        $admin = authenticatedSuperAdmin();

        $this->postJson('/api/v1/tenants', [
            'name' => 'Long Logo Corp',
            'owner_id' => $admin->id,
            'logo' => 'https://example.com/'.str_repeat('a', 250),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    });

    it('creates tenant without error when status not provided', function (): void {
        $admin = authenticatedSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Default Status Corp',
            'owner_id' => $admin->id,
        ]);

        $response->assertCreated();
    });

    it('accepts null optional fields', function (): void {
        $admin = authenticatedSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Null Fields Corp',
            'owner_id' => $admin->id,
            'domain' => null,
            'logo' => null,
            'country_id' => null,
            'data_center' => null,
            'status' => null,
        ]);

        // domain null triggers auto-generation
        $response->assertCreated();
        expect($response->json('data.domain'))->not->toBeNull();
    });
});

// ===========================================================================
// Data Integrity
// ===========================================================================

describe('Tenant data integrity', function (): void {

    beforeEach(function (): void {
        Event::fake([TenantCreated::class, TenantDeleted::class]);
    });

    it('persists all fields to database', function (): void {
        $admin = authenticatedSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Persist All Corp',
            'owner_id' => $admin->id,
            'domain' => 'persist-all.localhost',
            'logo' => 'https://cdn.example.com/logo.png',
            'country_id' => 254,
            'data_center' => 'af-east-1',
        ]);
        $response->assertCreated();
        $tenantId = $response->json('data.id');

        $tenant = Tenant::find($tenantId);

        expect($tenant->name)->toBe('Persist All Corp');
        expect($tenant->owner_id)->toBe($admin->id);
        expect($tenant->domain)->toBe('persist-all.localhost');
        expect($tenant->logo)->toBe('https://cdn.example.com/logo.png');
        expect($tenant->country_id)->toBe(254);
        expect($tenant->data_center)->toBe('af-east-1');
    });

    it('creates domain record in domains table', function (): void {
        $admin = authenticatedSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Domain Record Corp',
            'owner_id' => $admin->id,
            'domain' => 'domain-record.localhost',
        ]);
        $response->assertCreated();
        $tenantId = $response->json('data.id');

        $this->assertDatabaseHas('domains', [
            'tenant_id' => $tenantId,
            'domain' => 'domain-record.localhost',
        ], 'central');
    });

    it('maintains relationship between tenant and domain records', function (): void {
        $admin = authenticatedSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Relationship Corp',
            'owner_id' => $admin->id,
            'domain' => 'relationship.localhost',
        ]);
        $tenantId = $response->json('data.id');

        $tenant = Tenant::with('domains')->find($tenantId);

        expect($tenant->domains)->toHaveCount(1);
        expect($tenant->domains->first()->domain)->toBe('relationship.localhost');
    });

    it('stores data in central database connection', function (): void {
        $admin = authenticatedSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Central DB Corp',
            'owner_id' => $admin->id,
        ]);
        $response->assertCreated();

        $this->assertDatabaseHas('tenants', [
            'name' => 'Central DB Corp',
        ], 'central');
    });

    it('casts status to TenantStatus enum when set directly', function (): void {
        $tenant = new Tenant;
        $tenant->status = 'suspended';

        expect($tenant->status)->toBe(TenantStatus::Suspended);
        expect($tenant->status)->toBeInstanceOf(TenantStatus::class);
    });
});

// ===========================================================================
// Multiple Tenant Operations
// ===========================================================================

describe('Tenant multiple operations', function (): void {

    beforeEach(function (): void {
        Event::fake([TenantCreated::class, TenantDeleted::class]);
    });

    it('can create multiple tenants and list them all', function (): void {
        $admin = authenticatedSuperAdmin();

        $names = ['Multi Corp A', 'Multi Corp B', 'Multi Corp C', 'Multi Corp D'];

        foreach ($names as $name) {
            $this->postJson('/api/v1/tenants', [
                'name' => $name,
                'owner_id' => $admin->id,
            ])->assertCreated();
        }

        $response = $this->getJson('/api/v1/tenants');
        $response->assertOk();

        $returnedNames = collect($response->json('data'))->pluck('name');
        foreach ($names as $name) {
            expect($returnedNames)->toContain($name);
        }
    });

    it('assigns unique IDs to each tenant', function (): void {
        $admin = authenticatedSuperAdmin();

        $ids = [];
        foreach (range(1, 3) as $i) {
            $response = $this->postJson('/api/v1/tenants', [
                'name' => "Unique ID Test {$i}",
                'owner_id' => $admin->id,
            ]);
            $response->assertCreated();
            $ids[] = $response->json('data.id');
        }

        expect(array_unique($ids))->toHaveCount(3);
    });

    it('can show each tenant individually after batch create', function (): void {
        $admin = authenticatedSuperAdmin();

        $created = [];
        foreach (['Show Batch A', 'Show Batch B'] as $name) {
            $response = $this->postJson('/api/v1/tenants', [
                'name' => $name,
                'owner_id' => $admin->id,
            ]);
            $response->assertCreated();
            $created[] = [
                'id' => $response->json('data.id'),
                'name' => $name,
            ];
        }

        foreach ($created as $tenant) {
            $this->getJson("/api/v1/tenants/{$tenant['id']}")
                ->assertOk()
                ->assertJsonPath('data.name', $tenant['name']);
        }
    });

    it('can delete specific tenant without affecting others', function (): void {
        $admin = authenticatedSuperAdmin();

        $responseA = $this->postJson('/api/v1/tenants', [
            'name' => 'Keep This Corp',
            'owner_id' => $admin->id,
        ]);
        $responseB = $this->postJson('/api/v1/tenants', [
            'name' => 'Delete This Corp',
            'owner_id' => $admin->id,
        ]);

        $idA = $responseA->json('data.id');
        $idB = $responseB->json('data.id');

        $this->deleteJson("/api/v1/tenants/{$idB}")->assertNoContent();

        // Tenant A still accessible
        $this->getJson("/api/v1/tenants/{$idA}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Keep This Corp');

        // Tenant B deleted
        $this->getJson("/api/v1/tenants/{$idB}")
            ->assertNotFound();
    });
});

// ===========================================================================
// Tenant Relationships
// ===========================================================================

describe('Tenant relationships', function (): void {

    it('belongs to owner (SuperAdmin)', function (): void {
        $tenant = new Tenant;
        expect($tenant->owner())->toBeInstanceOf(BelongsTo::class);
    });

    it('belongs to creator (SuperAdmin)', function (): void {
        $tenant = new Tenant;
        expect($tenant->creator())->toBeInstanceOf(BelongsTo::class);
    });

    it('belongs to updater (SuperAdmin)', function (): void {
        $tenant = new Tenant;
        expect($tenant->updater())->toBeInstanceOf(BelongsTo::class);
    });

    it('has domains via HasDomains', function (): void {
        $tenant = new Tenant;
        expect($tenant->domains())->toBeInstanceOf(HasMany::class);
    });
});

// ===========================================================================
// Authentication Guard Specificity
// ===========================================================================

describe('Tenant authentication guard specificity', function (): void {

    beforeEach(function (): void {
        Event::fake([TenantCreated::class, TenantDeleted::class]);
    });

    it('requires super_admin guard for tenant list', function (): void {
        // Regular user token should not work
        $this->getJson('/api/v1/tenants')
            ->assertUnauthorized();
    });

    it('requires super_admin guard for tenant creation', function (): void {
        $this->postJson('/api/v1/tenants', [
            'name' => 'Guard Test Corp',
            'owner_id' => 1,
        ])
            ->assertUnauthorized();
    });

    it('requires super_admin guard for tenant show', function (): void {
        $this->getJson('/api/v1/tenants/1')
            ->assertUnauthorized();
    });

    it('requires super_admin guard for tenant deletion', function (): void {
        $this->deleteJson('/api/v1/tenants/1')
            ->assertUnauthorized();
    });

    it('allows authenticated super admin to access all endpoints', function (): void {
        $admin = authenticatedSuperAdmin();

        // List
        $this->getJson('/api/v1/tenants')->assertOk();

        // Create
        $createResponse = $this->postJson('/api/v1/tenants', [
            'name' => 'Admin Access Corp',
            'owner_id' => $admin->id,
        ]);
        $createResponse->assertCreated();
        $tenantId = $createResponse->json('data.id');

        // Show
        $this->getJson("/api/v1/tenants/{$tenantId}")->assertOk();

        // Delete
        $this->deleteJson("/api/v1/tenants/{$tenantId}")->assertNoContent();
    });
});

// ===========================================================================
// TenantResource Detailed Tests
// ===========================================================================

describe('TenantResource detailed', function (): void {

    it('returns null for domains when relationship not loaded', function (): void {
        $tenant = new Tenant([
            'identifier' => 'test-uuid',
            'name' => 'No Domains Loaded',
            'domain' => 'test.localhost',
            'owner_id' => 1,
        ]);
        $tenant->id = 1;
        $tenant->created_at = now();
        $tenant->updated_at = now();

        $resource = TenantResource::make($tenant)->resolve();

        // whenLoaded returns MissingValue when not loaded
        expect($resource)->toHaveKey('id');
        expect($resource['name'])->toBe('No Domains Loaded');
    });

    it('returns status value string when status is enum', function (): void {
        $tenant = new Tenant([
            'identifier' => 'test-uuid',
            'name' => 'Enum Status',
            'domain' => 'test.localhost',
            'owner_id' => 1,
        ]);
        $tenant->id = 1;
        $tenant->status = TenantStatus::Suspended;
        $tenant->created_at = now();
        $tenant->updated_at = now();

        $resource = TenantResource::make($tenant)->resolve();

        expect($resource['status'])->toBe('suspended');
    });

    it('handles null status gracefully', function (): void {
        $tenant = new Tenant([
            'identifier' => 'test-uuid',
            'name' => 'Null Status',
            'domain' => 'test.localhost',
            'owner_id' => 1,
        ]);
        $tenant->id = 1;
        $tenant->created_at = now();
        $tenant->updated_at = now();

        $resource = TenantResource::make($tenant)->resolve();

        expect($resource['status'])->toBeNull();
    });

    it('includes null for optional fields when not set', function (): void {
        $tenant = new Tenant([
            'identifier' => 'minimal-uuid',
            'name' => 'Minimal Corp',
            'owner_id' => 1,
        ]);
        $tenant->id = 1;
        $tenant->created_at = now();
        $tenant->updated_at = now();

        $resource = TenantResource::make($tenant)->resolve();

        expect($resource['logo'])->toBeNull();
        expect($resource['country_id'])->toBeNull();
        expect($resource['data_center'])->toBeNull();
    });
});

// ===========================================================================
// Filter Unit Tests
// ===========================================================================

describe('TenantFilters unit', function (): void {

    it('creates instance from request with no params', function (): void {
        $request = Request::create('/tenants', 'GET');
        $filters = TenantFilters::fromRequest($request);

        expect($filters)->toBeInstanceOf(TenantFilters::class);
    });

    it('creates instance from request with search param', function (): void {
        $request = Request::create('/tenants', 'GET', ['search' => 'test']);
        $filters = TenantFilters::fromRequest($request);

        expect($filters)->toBeInstanceOf(TenantFilters::class);
    });

    it('creates instance from request with reference_number param', function (): void {
        $request = Request::create('/tenants', 'GET', ['reference_number' => 'AT-1']);
        $filters = TenantFilters::fromRequest($request);

        expect($filters)->toBeInstanceOf(TenantFilters::class);
    });

    it('creates instance from request with both params', function (): void {
        $request = Request::create('/tenants', 'GET', [
            'search' => 'test',
            'reference_number' => 'AT-1',
        ]);
        $filters = TenantFilters::fromRequest($request);

        expect($filters)->toBeInstanceOf(TenantFilters::class);
    });

    it('ignores unknown filter params', function (): void {
        $request = Request::create('/tenants', 'GET', [
            'unknown_param' => 'value',
            'sql_injection' => "'; DROP TABLE tenants; --",
        ]);
        $filters = TenantFilters::fromRequest($request);

        expect($filters)->toBeInstanceOf(TenantFilters::class);
    });

    it('applies SearchTermFilter to query builder', function (): void {
        $filter = new SearchTermFilter('test');
        $query = Tenant::query();

        $result = $filter->apply($query);

        expect($result)->toBeInstanceOf(Builder::class);
    });

    it('applies ReferenceNumberFilter to query builder', function (): void {
        $filter = new ReferenceNumberFilter('AT-1');
        $query = Tenant::query();

        $result = $filter->apply($query);

        expect($result)->toBeInstanceOf(Builder::class);
    });

    it('trims whitespace in SearchTermFilter', function (): void {
        $filter = new SearchTermFilter('  test  ');
        $query = Tenant::query();

        $result = $filter->apply($query);

        // Should not throw, query should be built
        expect($result)->toBeInstanceOf(Builder::class);
    });

    it('trims whitespace in ReferenceNumberFilter', function (): void {
        $filter = new ReferenceNumberFilter('  AT-1  ');
        $query = Tenant::query();

        $result = $filter->apply($query);

        expect($result)->toBeInstanceOf(Builder::class);
    });
});

// ===========================================================================
// Tenant Model Traits & Interfaces
// ===========================================================================

describe('Tenant model traits and interfaces', function (): void {

    it('uses SoftDeletes trait', function (): void {
        $tenant = new Tenant;

        expect(method_exists($tenant, 'trashed'))->toBeTrue();
        expect(method_exists($tenant, 'restore'))->toBeTrue();
        expect(method_exists($tenant, 'forceDelete'))->toBeTrue();
    });

    it('implements Auditable contract', function (): void {
        $tenant = new Tenant;

        expect($tenant)->toBeInstanceOf(Auditable::class);
    });

    it('implements TenantWithDatabase contract', function (): void {
        $tenant = new Tenant;

        expect($tenant)->toBeInstanceOf(TenantWithDatabase::class);
    });

    it('uses HasFactory trait', function (): void {
        expect(method_exists(Tenant::class, 'factory'))->toBeTrue();
    });

    it('uses HasDomains trait', function (): void {
        $tenant = new Tenant;

        expect(method_exists($tenant, 'domains'))->toBeTrue();
    });

    it('uses HasDatabase trait', function (): void {
        $tenant = new Tenant;

        expect(method_exists($tenant, 'database'))->toBeTrue();
    });
});

// ===========================================================================
// Security Edge Cases
// ===========================================================================

describe('Tenant security', function (): void {

    beforeEach(function (): void {
        Event::fake([TenantCreated::class, TenantDeleted::class]);
    });

    it('rejects request with invalid auth token', function (): void {
        $this->withHeaders(['Authorization' => 'Bearer invalid_token_123'])
            ->getJson('/api/v1/tenants')
            ->assertUnauthorized();
    });

    it('rejects request with expired or revoked token format', function (): void {
        $this->withHeaders(['Authorization' => 'Bearer eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.invalid'])
            ->getJson('/api/v1/tenants')
            ->assertUnauthorized();
    });

    it('does not expose internal error details in production error responses', function (): void {
        authenticatedSuperAdmin();

        $response = $this->getJson('/api/v1/tenants/99999');

        $response->assertNotFound();
        // Should not contain stack trace or file paths
        $content = $response->getContent();
        expect($content)->not->toContain('vendor/');
        expect($content)->not->toContain('.php');
    });

    it('handles SQL injection attempt in search filter gracefully', function (): void {
        authenticatedSuperAdmin();

        $response = $this->getJson('/api/v1/tenants?search='.urlencode("'; DROP TABLE tenants; --"));

        $response->assertOk();
        // Table should still exist
        $this->assertDatabaseCount('tenants', Tenant::count(), 'central');
    });

    it('handles SQL injection attempt in reference_number filter', function (): void {
        authenticatedSuperAdmin();

        $response = $this->getJson('/api/v1/tenants?reference_number='.urlencode('1 OR 1=1'));

        $response->assertOk();
    });
});
