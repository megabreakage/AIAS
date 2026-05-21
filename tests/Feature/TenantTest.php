<?php

declare(strict_types=1);

use App\Http\Requests\Tenants\CreateTenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\Central\SuperAdmin;
use App\Models\Central\Tenant;
use App\Models\Central\TenantStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\Passport;
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
            'id', 'identifier', 'name', 'domain', 'logo', 'status',
            'owner_id', 'country_id', 'data_center',
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
            ->assertJsonStructure(['data', 'meta']);
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
            ->assertJsonValidationErrors(['name']);
    });

    it('requires owner_id', function (): void {
        authenticatedSuperAdmin();

        $this->postJson('/api/v1/tenants', [
            'name' => 'No Owner Corp',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['owner_id']);
    });

    it('rejects non-existent owner_id', function (): void {
        authenticatedSuperAdmin();

        $this->postJson('/api/v1/tenants', [
            'name' => 'Bad Owner Corp',
            'owner_id' => 99999,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['owner_id']);
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
            ->assertJsonValidationErrors(['name']);
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
            ->assertJsonValidationErrors(['domain']);
    });

    it('rejects invalid status value', function (): void {
        $admin = authenticatedSuperAdmin();

        $this->postJson('/api/v1/tenants', [
            'name' => 'Bad Status Corp',
            'owner_id' => $admin->id,
            'status' => 'invalid_status',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
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
            ->assertJsonValidationErrors(['name', 'owner_id']);
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
