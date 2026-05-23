# AIAS — WARP Development Guide

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

AIAS (Adaptive Intelligent Audit System) is a multi-tenant Laravel 13 application with database-per-tenant architecture. Uses Laravel Passport for OAuth2 authentication, Stancl/Tenancy for multi-tenancy, Spatie Permissions for RBAC, and Owen-it/Auditing for audit trails on central models.

## Core Stack

- PHP 8.4.16
- Laravel 13 (streamlined L11+ structure)
- Laravel Passport v13 (OAuth2)
- **Local PHP + MySQL** (not Docker/Sail)
- Stancl/Tenancy v3.10 (multi-tenant architecture)
- Spatie/Laravel-Permission v7.4 (roles & permissions)
- Owen-it/Laravel-Auditing v14 (audit trails — central models only)
- MySQL 9.0+ database
- Vite + Tailwind CSS v4

## DRY Principle (Don't Repeat Yourself) - MANDATORY

**CRITICAL**: All code MUST strictly follow the DRY principle for maintainability.

### Core DRY Rules

1. **NEVER duplicate logic** across repositories, services, or controllers
2. **Always reuse existing repository methods** instead of creating duplicate functionality
3. **Use dependency injection** to access existing repositories and their methods
4. **Before writing new code**, search for existing implementations that can be reused
5. **Refactor immediately** if you find yourself copying code from another file
6. **Extract common patterns** into shared traits, services, or base classes
7. **Use BaseRepository methods** (browse, read, insert, update, delete) consistently

### Implementation Patterns

**CORRECT - Repository Dependency Injection**:

```php
class AuditEngagementRepository extends BaseRepository
{
    public function __construct(
        protected FindingRepository $findingRepository
    ) {}

    public function createEngagementWithFindings(array $data): AuditEngagement
    {
        return DB::transaction(function () use ($data) {
            // Reuse BaseRepository::insert()
            $engagement = $this->insert($data);

            // Reuse FindingRepository instead of duplicating
            if ($findingsData = $data['findings'] ?? null) {
                foreach ($findingsData as $finding) {
                    $this->findingRepository->createFinding([
                        'engagement_id' => $engagement->id,
                        ...$finding,
                    ]);
                }
            }

            return $engagement;
        });
    }
}
```

**INCORRECT - Logic Duplication**:

```php
class AuditEngagementRepository extends BaseRepository
{
    public function createEngagementWithFindings(array $data): AuditEngagement
    {
        // DON'T DO THIS! Duplicates BaseRepository::insert()
        $engagement = AuditEngagement::create($data);

        // DON'T DO THIS! Duplicates FindingRepository logic
        Finding::create([...]);

        return $engagement;
    }
}
```

### DRY Enforcement Checklist

Before implementing any feature:

- [ ] Search existing repositories for similar functionality
- [ ] Identify which BaseRepository methods can be reused
- [ ] Use dependency injection to access other repositories
- [ ] Check for existing filters, resources, and form requests to reuse
- [ ] Extract shared logic into traits or services
- [ ] Follow existing patterns for tenant filtering and eager loading

## Commands

**All commands run using local PHP** (not Docker/Sail).

### Starting & Stopping

```bash
php artisan serve                         # Start development server
composer dev                              # Start server, queue, pail logs, and Vite concurrently
```

### Development

```bash
composer dev                              # Start server, queue, pail logs, and Vite concurrently
npm run dev                               # Start Vite dev server only
npm run build                             # Build frontend assets
```

### Database & Migrations

```bash
php artisan migrate                       # Run central migrations
php artisan migrate:fresh --seed          # Fresh migration with seeds
php artisan tenants:migrate               # Run migrations for all tenant databases
php artisan db:seed                       # Seed central database
```

### Passport (OAuth2)

```bash
php artisan passport:install              # Generate encryption keys & OAuth clients
```

### Testing

**ALWAYS use `./test.sh`** — never raw `php artisan test` or `composer test`. Creates unique MySQL databases per run, ensuring isolation.

```bash
./test.sh                                          # Run all tests (REQUIRED)
./test.sh tests/Feature/AuditEngagementTest.php   # Run specific test file
./test.sh --filter=testName                       # Run specific test by name
./test.sh --parallel                              # Parallel execution
```

**Why use `./test.sh`?**

- Creates unique MySQL database per test run (e.g., `aias_test_<timestamp>_<pid>`)
- Ensures complete isolation between test runs
- Auto-cleans test databases after completion
- Matches production MySQL environment

### Code Quality

```bash
vendor/bin/pint --dirty                   # Format only changed files (run before finalizing)
composer analyse                          # Enforce repository-only layer boundaries
```

## Multi-Tenancy Architecture

### Database-Per-Tenant Model

**Key Components:**

- **Tenant Model**: `app/Models/Tenant.php` — Uses custom ID generator
- **Tenant ID Generator**: `app/Tenancy/TenantIdGenerator.php` — Short alphanumeric IDs
- **Database Naming**: `aias_tenant_{tenant_id}_db`
- **Central Database**: Stores `tenants`, `users`, `domains`, OAuth clients, roles, permissions, audits
- **Tenant Databases**: Created dynamically per tenant, isolated data storage

### Tenancy Configuration

**Bootstrappers** (in `config/tenancy.php`):

- `DatabaseTenancyBootstrapper` — Switches database connections
- `CacheTenancyBootstrapper` — Scopes cache by tenant
- `FilesystemTenancyBootstrapper` — Isolates file storage
- `QueueTenancyBootstrapper` — Tenant-aware queues

**Middleware**: Uses `InitializeTenancyByPath` (configured in `TenancyServiceProvider`)

**Migrations**:

- Central migrations: `database/migrations/`
- Tenant migrations: `database/migrations/tenant/`

### Tenant Lifecycle Events

**On Tenant Creation** (`TenancyServiceProvider`):

1. Creates tenant database
2. Runs tenant migrations
3. (Optional seeding disabled by default)

**On Tenant Deletion**:

1. Deletes tenant database

### Spatie Permissions Integration

- Custom middleware `SetSpatieTeamFromTenant` bridges tenancy with Spatie permissions
- Roles scoped per tenant using `tenant_id` field
- Permissions are global but role assignments are tenant-specific
- Users belong to tenants via `users.tenant_id`
- Tenants have owners via `tenants.owner_id` → `users.id`

## Authentication & Authorization

### Laravel Passport (OAuth2)

- Password grant and client credentials flows
- API guard uses Passport driver (`config/auth.php`)
- OAuth tables stored in central database

### Protected Routes

Use `auth:api` middleware for authenticated API routes (see `routes/api.php`).

### Auditing

Model changes tracked via `audits` table — **central models only** (User, Tenant). Tenant models do NOT use Auditable.

## Laravel 13 Structure

- **Middleware registration**: In `bootstrap/app.php`
- **Service providers**: Listed in `bootstrap/providers.php`
- **No `app/Console/Kernel.php`**: Commands auto-register from `app/Console/Commands/`
- **Model casts**: Use `casts()` **method** — **NEVER** `$casts` property

## Layer Boundaries (Enforced)

- Production layers (`app/Http/Controllers`, `app/Jobs`, `app/Services`) **MUST** use repositories for all DB access
- Direct model queries in production layers are **FORBIDDEN**: `Model::query()`, `Model::find()`, `Model::where()`, `Model::create()`, `Model::update()`, `Model::delete()`
- `tests/`, `database/factories/`, `database/seeders/` may use Eloquent directly
- Run `composer analyse` to verify layer boundaries before committing

## Code Conventions

### PHP Standards

- Use PHP 8 constructor property promotion
- Always use explicit return types on methods/functions
- Always use curly braces for control structures (even single-line)
- PHPDoc blocks over inline comments; use array shape type definitions in PHPDoc
- Enum keys in TitleCase (e.g., `AuditStatus`, `RiskLevel`)
- Run `vendor/bin/pint --dirty` before finalizing any PHP changes
- Run `composer analyse` to enforce layer boundaries

### Laravel Best Practices

- Use `php artisan make:*` commands for file generation
- Pass `--no-interaction` to Artisan commands
- Prefer Eloquent over `DB::` facade
- Use Form Request classes for validation
- Relationship methods must have return type hints
- Eager load relationships to prevent N+1 queries
- Use `config()` not `env()` outside config files
- Use named routes and `route()` function

### Transaction Pattern

- Use `DB::transaction(function () { ... })` closures — NEVER manual begin/commit/rollback
- Authorization BEFORE transactions
- Logging AFTER transactions
- Transaction wraps ONLY repository calls

### Testing Standards

- **ALWAYS use `./test.sh`** — never `php artisan test` or `composer test`
- Use `RefreshDatabaseWithTenancy` trait — **NEVER** `RefreshDatabase`
- Most tests should be feature tests
- Use factories for model creation — check existing states before manual setup
- Tenant resources created inside `$this->tenant->run(fn)` context
- Set Spatie team before role assignment in test setup
- Test: happy paths, failure paths, tenant isolation (cross-tenant leakage)

## Database Schema

### Central Tables

- `users` — All users across tenants (with `tenant_id`)
- `tenants` — Tenant records (owner, status, metadata in `data` JSON column)
- `domains` — Tenant domain mappings
- `oauth_*` — Passport tables (clients, tokens, auth codes)
- `roles`, `permissions`, `model_has_roles`, `model_has_permissions` — Spatie RBAC
- `audits` — Audit trail records

### Tenant Tables (in each tenant database)

- Audit domain models: engagements, findings, risks, controls, etc.
- **NO foreign key constraints** from tenant tables to central database tables
- Store central DB references as regular integer/string fields

### Schema Patterns

- Soft deletes on all models
- `created_by` / `updated_by` for tracking
- `status` enum casts on relevant models
- `identifier` UUID field on all models
- JSON `data` column on `tenants` for flexible metadata

## Common Patterns

### Creating Tenant-Scoped Models

When creating models that belong to tenants:

1. **ALWAYS** extend `BaseModel` — **NEVER** `Model` directly
2. Traits in order: `HasFactory`, `SoftDeletes`, `TenantConnection`
3. `boot()` auto-populates: UUID `identifier`, `tenant_id`, `created_by`, `updated_by`
4. Casts defined in `casts()` **method** — **NEVER** `$casts` property
5. Override `getRouteKeyName()` to return `'identifier'` (UUID route binding)
6. **DO NOT** add `Auditable` trait — only central models use auditing
7. **NO foreign key constraints** to central database tables
8. `created_by`/`updated_by` = `unsignedBigInteger()->nullable()` — no FK
9. Tenant-unique constraints: `unique(['tenant_id', 'field'])`

### Repository Pattern for Tenant Models

- Non-super-admin queries: always `where('tenant_id', auth()->user()->tenant_id)`
- Load relationships on every read/create/update: `->load(['creator', 'updater'])`
- Inject other repositories for DRY compliance — never duplicate logic

### API Response Envelope

**STRICTLY DEFAULT** envelope — never deviate:
```json
{
  "status": "success",
  "message": "Resource retrieved successfully",
  "data": { ... },
  "metadata": { ... }
}
```
- Always call `->setMessage()` on resource before returning
- Use `->addMetadata()` for additional context
- Unique validation scoped to tenant: `Rule::unique('table')->where('tenant_id', ...)`

### Creating Central Models

When creating central/shared models:

1. Use `$connection = 'central'` or `CentralConnection` trait
2. Add `Auditable` trait and implement `AuditableContract`
3. No `tenant_id` — data is shared across all tenants
4. Standard foreign key constraints OK between central tables

## Feature Development Workflow

### Required Steps

1. **Follow Existing Architectural Patterns** — Use established patterns as templates
2. **Create OpenAPI Documentation** — `storage/api-docs/{feature-name}.openapi.yaml`
3. **Create Feature Documentation** — `docs/features/{feature-name}.md`
4. **Update Features Index** — Add to `docs/features/README.md`
5. **Update Permissions** — Add to `config/role-permission-map.php` if applicable
6. **Write Comprehensive Tests** — Unit, feature, edge cases
7. **Update Postman Collection**
8. **Document Breaking Changes**

### Completion Checklist

- [ ] Architectural patterns followed
- [ ] OpenAPI YAML documentation created
- [ ] Feature documentation created/updated
- [ ] Permissions added/updated
- [ ] Tests written and passing (`./test.sh`)
- [ ] Tests use `RefreshDatabaseWithTenancy` trait
- [ ] Postman collection updated
- [ ] Breaking changes documented
- [ ] Code formatted: `vendor/bin/pint --dirty`
- [ ] All tests passing: `./test.sh`

## Project-Specific Notes

- **Passport**: Requires `PASSPORT_PASSWORD_CLIENT_ID` and `PASSPORT_PASSWORD_CLIENT_SECRET` in `.env`
- **Tenant DB work**: Synchronous in dev; consider queuing in prod
- **Spatie team**: Must be set before permission checks (via middleware)
- **Custom tenant IDs**: Short alphanumeric format based on creation date
