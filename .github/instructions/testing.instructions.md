---
description: "Use when creating or editing tests, running test suites, writing assertions, or debugging test failures. Covers PEST, parallel testing, MySQL isolation, and tenant-aware testing."
applyTo: "tests/**"
---
# Testing Standards

## Framework
- PEST v4 + PHPUnit v12 on MySQL (not SQLite)
- **ALWAYS use `docs/scripts/test.sh`** — never raw `php artisan test` or `./vendor/bin/pest`
- Script creates a unique MySQL database per run (`aias_test_<timestamp>_<pid>`) — prevents lock conflicts and ensures isolation

## Running Tests
```bash
docs/scripts/test.sh                                   # All tests (REQUIRED)
docs/scripts/test.sh tests/Feature/SomeTest.php        # Specific file
docs/scripts/test.sh --filter=testMethodName           # Specific test
docs/scripts/test.sh --parallel                        # Parallel (requires paratest)
```

## Test Class Requirements
- All tests use `RefreshDatabaseWithTenancy` — **NEVER** `RefreshDatabase`
- Use factories for model creation — check factory states before manual setup
- Use `fake()` for faker (project convention)
- Create test files: `php artisan make:test --pest {Name}` (no directory prefix)

## Structure
- Feature tests: `tests/Feature/` — most tests go here
- Unit tests: `tests/Unit/` — pure logic, no DB

## Tenant-Aware Test Setup
```php
use Tests\Traits\RefreshDatabaseWithTenancy;

class MyFeatureTest extends TestCase
{
    use RefreshDatabaseWithTenancy;  // ✅ CORRECT

    protected function setUp(): void
    {
        parent::setUp();

        // Set Spatie team BEFORE role assignment
        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole('admin');

        // Create tenant resources INSIDE tenant context
        $this->tenant->run(function () {
            $this->record = MyModel::factory()->create(['tenant_id' => $this->tenant->id]);
        });
    }
}
```

## Required Test Coverage
- **Happy paths** — successful CRUD operations
- **Failure paths** — 422 validation errors, 404 not found
- **Authorization** — 403 for wrong roles; 403/404 for wrong tenant
- **Tenant isolation** — assert cross-tenant data is **never** leaked (another tenant's resource returns 404)
- **Soft deletes** — delete and restore operations
- **Filter combinations** — search, status, pagination

## Assertions in Tenant Context
```php
// Assert database state inside tenant context
$this->tenant->run(fn() =>
    $this->assertDatabaseHas('my_models', ['identifier' => $record->identifier])
);

// Assert cross-tenant isolation
$otherTenantRecord = $this->otherTenant->run(fn() =>
    MyModel::factory()->create()
);
$this->actingAs($this->user)
    ->getJson("/api/my-models/{$otherTenantRecord->identifier}")
    ->assertStatus(404);
```

## Anti-Patterns
```php
// ❌ WRONG
use Illuminate\Foundation\Testing\RefreshDatabase;  // not tenant-aware

// ❌ WRONG — creates in wrong DB context
$record = MyModel::factory()->create();

// ✅ CORRECT
$this->tenant->run(fn() => MyModel::factory()->create(['tenant_id' => $this->tenant->id]));
```
