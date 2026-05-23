# CLAUDE.md

Dev guidance for AIAS (Adaptive Intelligent Audit System) multi-tenant Laravel 13 API.

## Quick Start

**Stack**: Laravel 13, PHP 8.4+, MySQL 9.0+, Stancl Tenancy v3.10, Passport v13, Spatie Permissions v7

**Key Commands**:

```bash
# Development
php artisan serve                    # Start server
composer dev                         # Start all services

# Database
php artisan migrate --seed           # Central DB
php artisan tenants:migrate          # All tenant DBs

# Testing (ALWAYS use docs/scripts/test.sh - prevents MySQL locks)
docs/scripts/test.sh                           # All tests
docs/scripts/test.sh tests/Feature/AuditEngagementTest.php # Specific file
docs/scripts/test.sh --filter=testMethodName   # Specific test

# Code Quality
vendor/bin/pint --dirty             # Format changed PHP files
composer analyse                    # Enforce layer boundaries
```

## CRITICAL: DRY Principles - MANDATORY

**Before writing ANY code, review `.claude/hooks/pre-execution-dry-principles.md`**

### Core Rules

1. **Search Before Creating**: Check existing repositories, services, helpers, traits
2. **Inject Dependencies**: Never copy-paste, always inject repositories/services
3. **Reuse Repository Methods**: If functionality exists, inject and call it — don't duplicate
4. **Tenant Filtering**: Use repository methods, never duplicate filtering logic

```php
// ❌ NEVER - Duplicating repository logic
AuditEngagement::create(['title' => $data['title']]);

// ✅ ALWAYS - Inject and reuse repository
$this->engagementRepository->createEngagement(['title' => $data['title']]);
```

## Architecture Essentials

### Multi-Tenancy (Database Isolation)

- **Central DB**: Users, tenants, OAuth tokens, permissions, audit standards, regulation frameworks
- **Tenant DBs**: Dynamic per-tenant databases (`aias_tenant_<uuid>_db`)
- **Middleware**: `InitializeTenancyByPath` + `SetSpatieTeamFromTenant` (auto-applied)

### Authentication & Authorization

- **Auth**: Laravel Passport (OAuth2 password grant, 15-day tokens)
- **Permissions**: Spatie with teams (tenant-scoped via `tenant_id`)
- **Flow**: All `Gate::authorize()` calls BEFORE `DB::transaction`

### Repository Pattern (Mandatory)

All controllers use repositories, never direct model queries:

```php
// In Controller constructor
public function __construct(protected AuditEngagementRepository $repository) {}

// Usage with tenant filtering built-in
$engagements = $this->repository->browseEngagements($filters, $page, $perPage);
```

**Repository rules:**

- Non-super-admin: always add `where('tenant_id', auth()->user()->tenant_id)`
- Load relationships on every read/create/update: `->load(['creator', 'updater'])`
- Inject other repositories (DRY) — never duplicate logic

### Transaction Pattern (CRITICAL)

**Always use `DB::transaction(function () { ... })` closures for database write operations.**

Laravel auto-handles rollback on exceptions — never use manual `DB::beginTransaction`, `DB::commit`, or `DB::rollBack`.

#### Core Rules

1. **Authorization BEFORE transactions** - `Gate::authorize()` must execute before any transaction
2. **Validation BEFORE transactions** - Request validation and data preparation before transactions
3. **Wrap ONLY database writes** - Transaction closures contain only repository calls
4. **Logging AFTER transactions** - Log success/failure outside transaction scope
5. **Let exceptions bubble** - No manual rollback, Laravel handles it automatically
6. **No transaction state checks** - Avoid checking if transaction is active

#### Correct Pattern

```php
public function store(CreateAuditEngagementRequest $request): JsonResponse
{
    // 1. Authorization BEFORE transaction
    Gate::authorize('create', AuditEngagement::class);

    try {
        // 2. Validation and data preparation BEFORE transaction
        $data = $request->validated();

        // 3. Pre-transaction logging (optional)
        Log::info('Creating audit engagement', ['title' => $data['title']]);

        // 4. Transaction wraps ONLY repository call
        $engagement = DB::transaction(function () use ($data) {
            return $this->repository->createEngagement($data);
        });

        // 5. Post-transaction logging and events
        Log::info('Audit engagement created', ['id' => $engagement->id]);

        return (new AuditEngagementResource($engagement))
            ->setMessage('Audit engagement created successfully')
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);

    } catch (\Throwable $e) {
        // 6. Exception handling - NO manual rollback needed
        Log::error('Failed to create audit engagement', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to create audit engagement',
            'data' => null,
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
```

#### Anti-Patterns (NEVER DO THIS)

```php
// ❌ WRONG - Manual transaction management
DB::beginTransaction();
try {
    $engagement = $this->repository->createEngagement($data);
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    throw $e;
}

// ❌ WRONG - Authorization inside transaction
DB::transaction(function () use ($data) {
    Gate::authorize('create', AuditEngagement::class);
    return $this->repository->createEngagement($data);
});

// ❌ WRONG - Logging inside transaction
DB::transaction(function () use ($data) {
    $engagement = $this->repository->createEngagement($data);
    Log::info('Created engagement');
    return $engagement;
});
```

#### Update/Delete Pattern

```php
public function update(UpdateAuditEngagementRequest $request, string $id): JsonResponse
{
    try {
        // 1. Fetch resource BEFORE authorization
        $engagement = $this->repository->readEngagement($id);

        // 2. Authorization BEFORE transaction
        Gate::authorize('update', $engagement);

        // 3. Validation BEFORE transaction
        $data = $request->validated();

        Log::info('Updating audit engagement', ['id' => $id]);

        // 4. Transaction wraps ONLY repository call
        $engagement = DB::transaction(function () use ($id, $data) {
            return $this->repository->updateEngagement($id, $data);
        });

        Log::info('Audit engagement updated', ['id' => $engagement->id]);

        return (new AuditEngagementResource($engagement))
            ->setMessage('Audit engagement updated successfully')
            ->response();

    } catch (\ModelNotFoundException $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Audit engagement not found',
        ], Response::HTTP_NOT_FOUND);
    }
}
```

#### Why This Pattern?

- **Automatic Rollback**: Laravel rolls back on any exception — no manual handling needed
- **Minimal Lock Time**: Transactions hold locks only during actual database writes
- **Clean Separation**: Authorization, validation, logging clearly separated from data persistence
- **Exception Safety**: Uncaught exceptions auto-rollback — no data corruption
- **Performance**: Shorter transactions = less lock contention = better performance
- **Testability**: Each concern (auth, validation, persistence) tested independently

### Filter Pattern

Query filters use factory pattern via `EloquentFilter`. All filters extend `EloquentFilter` base class.

**Directory Structure**:

```
app/Filters/
  ├── EloquentFilter.php (base class)
  └── {Module}/
      ├── {Module}Filters.php (main filter class)
      └── Filters/
          ├── SearchTermFilter.php
          └── StatusFilter.php
```

**Creating New Filter**:

1. Create main filter class extending `EloquentFilter`:

```php
// app/Filters/AuditEngagements/AuditEngagementFilters.php
namespace App\Filters\AuditEngagements;

use App\Filters\EloquentFilter;
use App\Filters\AuditEngagements\Filters\SearchTermFilter;
use App\Filters\AuditEngagements\Filters\StatusFilter;

class AuditEngagementFilters extends EloquentFilter
{
    protected array $filters = [
        'search' => SearchTermFilter::class,
        'status' => StatusFilter::class,
    ];
}
```

1. Create individual filter classes:

```php
// app/Filters/AuditEngagements/Filters/SearchTermFilter.php
namespace App\Filters\AuditEngagements\Filters;

use App\Filters\EloquentFilter;
use Illuminate\Database\Eloquent\Builder;

class SearchTermFilter extends EloquentFilter
{
    public function __construct(
        protected string $search
    ) {}

    public function apply(Builder $query): Builder
    {
        $search = trim($this->search);

        return $query->where(function (Builder $q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhere('engagement_number', 'like', "%{$search}%");
        });
    }
}
```

1. Integrate in Repository:

```php
public function browseEngagements(
    AuditEngagementFilters $filters,
    int $page = 1,
    int $perPage = 20,
    ?string $sortBy = null,
    bool $sortDesc = false
): Paginator {
    $query = $this->query()->with(['creator', 'leadAuditor']);

    // Tenant filtering
    if (!auth()->user()->hasRole('super-admin')) {
        $query->where('tenant_id', auth()->user()->tenant_id);
    }

    // Apply filters
    $filters->apply($query);

    // Apply sorting & pagination
    if ($sortBy) {
        $query->orderBy($sortBy, $sortDesc ? 'desc' : 'asc');
    }

    return $query->paginate(perPage: min($perPage, 100), page: max($page, 1));
}
```

1. Use in Controller:

```php
public function index(Request $request): JsonResponse
{
    $filters = AuditEngagementFilters::fromRequest($request);

    $engagements = $this->repository->browseEngagements(
        filters: $filters,
        page: $request->integer('page', 1),
        perPage: $request->integer('per_page', 15),
        sortBy: $request->input('sort_by'),
        sortDesc: $request->input('sort_order') === 'desc'
    );

    return (new AuditEngagementCollection($engagements))
        ->setMessage('Audit engagements retrieved successfully')
        ->addMetadata('filters_applied', $request->only(['search', 'status', 'sort_by', 'sort_order']))
        ->response()
        ->setStatusCode(Response::HTTP_OK);
}
```

**Filter Usage in API**:

- `GET /api/audit-engagements?search=SOX`
- `GET /api/audit-engagements?status=in_progress&per_page=10&sort_by=start_date`

### Resource Pattern

API responses use custom `BaseResource` (extends Laravel's `JsonResource`):

**STRICTLY DEFAULT** envelope — never deviate:

```php
return AuditEngagementResource::make($engagement)
    ->setMessage('Engagement retrieved successfully')
    ->addMetadata('findings_count', $engagement->findings_count);
```

Wraps responses with:

- `status` - HTTP status code
- `message` - Human-readable message
- `data` - Resource data
- `metadata` - Additional context (optional)

Always call `->setMessage()` before returning. Use `->addMetadata()` for additional context.

### Request Validation

Form requests extend `Illuminate\Foundation\Http\FormRequest`:

- `CreateAuditEngagementRequest`, `UpdateAuditEngagementRequest`
- `CreateFindingRequest`, `UpdateFindingRequest`
- `CreateRiskAssessmentRequest`, `UpdateRiskAssessmentRequest`

Authorization checks in `authorize()` method.

## API Endpoints

**Public**:

- `POST /login` - Authenticate and get token

**Protected** (requires `auth:api`):

- `POST /logout` - Revoke token
- `GET /me` - Get current user with roles/tenant

**Tenant Management** (super-admin only):

- `GET /tenants` - List all tenants (paginated, filtered)
- `POST /tenants` - Create tenant + owner
- `GET /tenants/{id}` - View tenant
- `PUT /tenants/{id}` - Update tenant
- `DELETE /tenants/{id}` - Soft delete
- `POST /tenants/{id}/restore` - Restore

**User Management** (tenant-scoped):

- `GET /users` - List users (with search/status filters)
- `POST /users` - Create user
- `GET /users/{user}` - View user
- `PUT /users/{user}` - Update user
- `DELETE /users/{user}` - Soft delete
- `POST /users/{user}/restore` - Restore

**Audit Engagements** (tenant-scoped):

- `GET /audit-engagements` - List engagements
- `POST /audit-engagements` - Create engagement
- `GET /audit-engagements/{id}` - View engagement
- `PUT /audit-engagements/{id}` - Update engagement
- `DELETE /audit-engagements/{id}` - Soft delete
- `POST /audit-engagements/{id}/close` - Close engagement

**Findings** (tenant-scoped):

- `GET /findings` - List findings
- `POST /findings` - Create finding
- `GET /findings/{id}` - View finding
- `PUT /findings/{id}` - Update finding
- `DELETE /findings/{id}` - Soft delete
- `POST /findings/{id}/escalate` - Escalate finding

**Risk Assessments** (tenant-scoped):

- `GET /risk-assessments` - List assessments
- `POST /risk-assessments` - Create assessment
- `GET /risk-assessments/{id}` - View assessment
- `PUT /risk-assessments/{id}` - Update assessment
- `DELETE /risk-assessments/{id}` - Soft delete

## Important Architectural Constraints

### When Creating New Features

1. **Always use repositories** for database operations, never direct model queries in controllers
2. **DRY Principle** - MANDATORY:
   - **NEVER duplicate logic** across repositories, services, or controllers
   - **Reuse existing repository methods** instead of creating duplicate functionality
   - **Use dependency injection** to access existing repositories and their methods
   - **Before writing new code**, search for existing implementations to reuse
   - **Refactor immediately** if copying code from another file
3. **Model conventions** — tenant models **ALWAYS** extend `BaseModel` (**NEVER** `Model`); traits: `HasFactory`, `SoftDeletes`, `TenantConnection`; casts in `casts()` **method** not `$casts` property; route binding via `identifier`
4. **Tenant filtering mandatory** for non-super-admin users — see repository patterns
5. **Permission checks** must use Gate/Policy, not manual role checks
6. **All API responses** must use Resource classes (extend `BaseResource`) with **STRICTLY DEFAULT** envelope
7. **Request validation** must use Form Request classes, not inline `$request->validate()`
8. **Dispatch events** from repositories for significant operations (create/update/delete)
9. **New tenant migrations** go in `/database/migrations/tenant/`
10. **NEVER create foreign key constraints** from tenant tables to central database tables
11. **Layer boundaries**: run `composer analyse` to verify no direct model queries in production layers

### Security Boundaries

- **Database isolation**: Tenants can't query each other's databases by design
- **No foreign key constraints**: Tenant databases MUST NOT have FK constraints to central database tables
- **Central references**: Store central database IDs as regular fields without FK constraints
- **Authorization layers**: Policy (Gate) → Repository (tenant filter) → Model (relationships)
- **Audit trail**: `owen-it/laravel-auditing` tracks model changes for central models only (User, Tenant)
  - **Tenant models DO NOT use auditing** to avoid database complexity
- **Soft deletes**: All models use soft deletes for data recovery

### Multi-Tenancy Gotchas

1. **Never bypass tenant filters** unless implementing super-admin functionality
2. **Run `tenants:migrate`** after creating new tenant migrations
3. **Tenant context set by middleware** — don't manually switch databases in controllers
4. **Permission checks auto-scoped** via `SetSpatieTeamFromTenant` middleware
5. **User's `tenant_id` is source of truth** for tenant context (stored in central DB)
6. **NEVER create foreign key constraints** from tenant tables to central database tables
7. **Store central database references as regular integer/string fields, not foreign keys**
8. **DO NOT add auditing to tenant models** — only central models (User, Tenant) use `Auditable` trait

## Testing

**Always use `docs/scripts/test.sh`** to run tests. Creates unique MySQL test databases per run, ensuring isolation and preventing conflicts.

```bash
# Run all tests
docs/scripts/test.sh

# Run specific test
docs/scripts/test.sh --filter=test_can_create_audit_engagement

# Run specific test file
docs/scripts/test.sh tests/Feature/AuditEngagementTest.php

# Run tests in parallel (requires paratest)
docs/scripts/test.sh --parallel
```

**Why `docs/scripts/test.sh`?**

- Creates unique MySQL database per run to avoid conflicts
- Ensures complete isolation between test runs
- Auto-cleans test databases after completion
- Matches production MySQL environment
- Supports sequential and parallel execution

**Test Trait Requirements:**

All test classes MUST use `RefreshDatabaseWithTenancy` trait instead of Laravel's `RefreshDatabase`:

```php
use Tests\Traits\RefreshDatabaseWithTenancy;

class MyTest extends TestCase
{
    use RefreshDatabaseWithTenancy;  // ✅ CORRECT
    // NOT: use RefreshDatabase;      // ❌ WRONG
}
```

## Feature Development Workflow

### Required Steps

1. **Follow Existing Architectural Patterns** - Use established patterns as templates
2. **Create OpenAPI Documentation** - `storage/api-docs/{feature-name}.openapi.yaml`
3. **Create Feature Documentation** - `docs/features/{feature-name}.md`
4. **Update Features Index** - Add to `docs/features/README.md`
5. **Update Permissions** - Add to `config/permissions_map.php` if applicable
6. **Write Comprehensive Tests** - Unit, feature, edge cases, integration tests
7. **Update Postman Collection** - Add endpoints to collection
8. **Document Breaking Changes** - API changes, permissions, database, config

### Completion Checklist

- [ ] Architectural patterns followed
- [ ] OpenAPI YAML documentation created in `storage/api-docs/`
- [ ] Feature documentation created/updated
- [ ] Features README.md updated
- [ ] Permissions added/updated in config
- [ ] Tests written and passing (using MySQL via `docs/scripts/test.sh`)
- [ ] Tests use `RefreshDatabaseWithTenancy` trait (not `RefreshDatabase`)
- [ ] Tenant resources created inside `$this->tenant->run(fn)` in test setup
- [ ] Tests cover: happy paths, failure paths, tenant isolation (cross-tenant leakage)
- [ ] Postman collection updated
- [ ] Breaking changes documented
- [ ] Code formatted: `vendor/bin/pint --dirty`
- [ ] Layer boundaries verified: `composer analyse`
- [ ] All tests passing: `docs/scripts/test.sh`

## Common Scenarios

### Adding New Tenant-Scoped Resource

1. Create migration in `/database/migrations/tenant/` — required fields: `id`, `uuid identifier`, `tenant_id`, `created_by`/`updated_by` (`unsignedBigInteger()->nullable()`, no FK), `timestamps`, `softDeletes`
2. Create model in `app/Models/Tenant/` — extends `BaseModel`, traits: `HasFactory`, `SoftDeletes`, `TenantConnection`, NO `Auditable`; casts in `casts()` method; route binding via `identifier`
3. Create repository extending `BaseRepository` with tenant filtering; load `['creator', 'updater']`
4. Create form requests with unique rules scoped to tenant
5. Create resource/collection extending `BaseResource`
6. Create policy with `before()` super-admin bypass + `tenant_id` boundary check
7. Create controller using repository injection; `Gate::authorize()` BEFORE `DB::transaction()`
8. Register policy in `AppServiceProvider`
9. Add permissions to `config/permissions_map.php`
10. Add routes to `/routes/api.php` named `api.{resource}.{action}`

### Adding New Central Resource

1. Create migration in `/database/migrations/`
2. Create model with `protected $connection = 'central'` + `Auditable` trait + `SoftDeletes` (NO `tenant_id`)
3. Create repository extending `BaseRepository` with role-based access control
4. Create form requests for validation
5. Create resource/collection for responses
6. Create policy for authorization
7. Create controller using repository pattern
8. Add routes to `/routes/api.php`

### Adding New Permission

1. Add to relevant module in `/config/permissions_map.php`
2. Update role assignments if needed
3. Run `php artisan cache:clear` to clear permission cache
4. Update policies to check new permission
5. Reseed roles/permissions if needed

### Debugging Tenant Issues

1. Check user's `tenant_id` in database
2. Verify `SetSpatieTeamFromTenant` middleware is applied
3. Check if query has `where('tenant_id', ...)` filter
4. Verify tenant database exists: `aias_tenant_<id>_db`
5. Check tenancy bootstrappers in `config/tenancy.php`

## Key Files to Reference

**Multi-tenancy**:

- `config/tenancy.php` - Tenancy configuration
- `app/Providers/TenancyServiceProvider.php` - Event handlers
- `app/Models/Tenant.php` - Tenant model with lifecycle hooks
- `app/Tenancy/TenantIdGenerator.php` - UUID-based ID generation

**Authorization**:

- `config/permissions_map.php` - All permissions and role assignments
- `app/Http/Middleware/SetSpatieTeamFromTenant.php` - Permission scoping
- `app/Policies/` - Authorization logic

**Data Layer**:

- `app/Repositories/BaseRepository.php` - Generic CRUD pattern
- `app/Filters/EloquentFilter.php` - Query filter factory

**API Layer**:

- `app/Http/Resources/BaseResource.php` - Response wrapper
- `app/Http/Requests/` - Validation classes
- `app/Http/Controllers/` - Controller pattern

