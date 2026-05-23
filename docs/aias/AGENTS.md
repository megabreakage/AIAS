# AIAS — AI Coding Agent Guide

Purpose: Enable agents to contribute productively to this Laravel 13, multi-tenant API (Stancl Tenancy, Passport, Spatie Permission with teams, Auditing) using the project's established patterns.

## CRITICAL: DRY Principles Enforcement

**Before writing ANY code**, review and follow the DRY (Don't Repeat Yourself) principles hook:

**Hook Location:** `.claude/hooks/pre-execution-dry-principles.md`

**Key Rules:**

1. Search for existing implementations before creating new code
2. Inject dependencies (repositories, services) instead of duplicating logic
3. **Tenant Filtering:** Use repository methods, never duplicate tenant filtering
4. Verify no code duplication before submitting

**Critical Violation Example:**

```php
// ❌ NEVER - Duplicating repository logic
AuditEngagement::create(['title' => $data['title'], ...]);

// ✅ ALWAYS - Inject and reuse repository
public function __construct(protected AuditEngagementRepository $engagementRepository) {}
$this->engagementRepository->createEngagement(['title' => $data['title'], ...]);
```

## Big Picture

- Backend-first Laravel 13 API (local PHP, not Docker/Sail)
- Multi-tenancy (stancl/tenancy v3): each tenant has its own MySQL database; created and migrated on tenant creation
- Auth: Laravel Passport (password grant) under the `api` guard
- RBAC: Spatie Permission with teams; team key is `tenant_id`
- Responses: custom Resource envelope with `status`, `message`, `data`, optional `metadata`/pagination
- **Testing**: MySQL with unique test databases per run for production-accurate, isolated tests

## Key Architecture

- Tenancy lifecycle: see `config/tenancy.php` and `app/Providers/TenancyServiceProvider.php` (handles `TenantCreated` → DB create + migrate)
- Tenant model: `app/Models/Tenant.php` (soft deletes, auditing, `status` enum cast)
- Team scoping: `app/Http/Middleware/SetSpatieTeamFromTenant.php` sets Spatie team from the `{tenant}` path or current user
- Repositories + Filters: `app/Repositories` provide `browse/read/insert/update/delete`; filters live under `app/Filters` and compose small single-responsibility classes
- Resources: `app/Http/Resources` enforce the response envelope in controllers
- Permissions map: `config/role-permission-map.php` defines module permissions and role assignments

## Developer Workflows

- Start services: `php artisan serve` or `composer dev`
- Migrate/seed (central): `php artisan migrate --seed`
- Tenants migrate: `php artisan tenants:migrate`
- Passport keys/clients: `php artisan passport:install`
- **Tests**: `./test.sh` (ALWAYS use this script to avoid MySQL locks)
- Dev script: `composer dev` runs app, vite, schedule, queue, logs

## Testing Configuration

- **ALWAYS use `./test.sh`** to run tests - this ensures proper database isolation
- **Script**: Creates unique MySQL database per test run (e.g., `aias_test_<timestamp>_<pid>`)
- **Database**: MySQL (matching production) for accurate testing with automatic cleanup
- **Benefits**: Production-like environment, proper isolation, supports parallel execution
- **RefreshDatabaseWithTenancy**: Custom trait that handles both central and tenant migrations

### Running Tests

```bash
# Run all tests
./test.sh

# Run specific test
./test.sh --filter=test_can_create_audit_engagement

# Run specific test file
./test.sh tests/Feature/AuditEngagementTest.php

# Run tests in parallel (requires paratest)
./test.sh --parallel
```

### Test Class Requirements

All test classes MUST use the `RefreshDatabaseWithTenancy` trait:

```php
use Tests\Traits\RefreshDatabaseWithTenancy;

class MyTest extends TestCase
{
    use RefreshDatabaseWithTenancy;  // ✅ CORRECT - handles central + tenant migrations
}
```

## Conventions & Patterns

- Tenancy is path-first; prefer `{tenant}` segments and apply `SetSpatieTeamFromTenant`
- Controllers defer to repositories; do not query models directly in controllers
- List endpoints: call `browse(...)` with a dedicated `{Domain}Filters` composing filter classes (e.g., `search`, `status`)
- Authorization: use Policies/Gates; non–`super-admin` users are always tenant-scoped in repositories
- Responses: return `{Domain}Resource`/`{Domain}Collection`, set messages with `->setMessage()` and metadata via `->addMetadata()`

## Adding a Tenant-Scoped Feature (Checklist)

1. Routes in `routes/api.php` (use `auth:api` and `{tenant}` when relevant)
2. Create Form Request in `app/Http/Requests` with rules + messages
3. Add repository in `app/Repositories`; enforce tenant filtering for non–`super-admin`
4. Compose filters in `app/Filters/{Domain}` and apply in `browse(...)`
5. Create Resource/Collection in `app/Http/Resources` for response envelope
6. Add Policy in `app/Policies` and register if needed
7. Update `config/role-permission-map.php` if new permissions are required

## Feature Development Workflow

When adding new features or updating existing ones, follow this comprehensive workflow:

### Required Steps

1. **Follow Existing Architectural Patterns** - Use established patterns as templates
2. **Create OpenAPI Documentation** - `storage/api-docs/{feature-name}.openapi.yaml` with comprehensive API specs
3. **Create Feature Documentation** - `docs/features/{feature-name}.md` with overview, API, permissions
4. **Update Features Index** - Add to `docs/features/README.md` with proper categorization
5. **Update Permissions** - Add to `config/role-permission-map.php` if applicable
6. **Write Comprehensive Tests** - Unit, feature, edge cases, integration tests
7. **Update Postman Collection** - Add endpoints to `postman/collections/`
8. **Document Breaking Changes** - API changes, permissions, database, config

### Completion Checklist

- [ ] Architectural patterns followed
- [ ] OpenAPI YAML documentation created in `storage/api-docs/`
- [ ] Feature documentation created/updated
- [ ] Features README.md updated
- [ ] Permissions added/updated in config
- [ ] Tests written and passing (using MySQL via `./test.sh`)
- [ ] Tests use `RefreshDatabaseWithTenancy` trait (not `RefreshDatabase`)
- [ ] **Postman collection updated** with new endpoints, test scripts, and examples
- [ ] **Postman documentation updated**: `COLLECTION_GUIDE.md` and `QUICK_REFERENCE.md`
- [ ] **Environment variables added** for any new variables required
- [ ] Breaking changes documented
- [ ] Code formatted: `vendor/bin/pint --dirty`
- [ ] All tests passing: `./test.sh`

**See `CLAUDE.md` for detailed workflow and examples.**

## Postman Collection Maintenance

When adding or updating features, **ALWAYS** maintain the Postman collection and documentation:

### Required Updates

1. **Collection File**: Add new endpoints with proper folder structure, test scripts, auth, and examples
2. **Environment Configuration**: Add new resource ID variables and configuration variables
3. **Documentation Updates**: Update `COLLECTION_GUIDE.md` and `QUICK_REFERENCE.md`

## Gotchas

- Passport password grant requires `PASSPORT_PASSWORD_CLIENT_ID` and `PASSPORT_PASSWORD_CLIENT_SECRET` in `.env`
- Tenancy DB work is synchronous in dev; consider queuing in prod
- Always ensure Spatie's team is set before permission checks (middleware)
- **NEVER create foreign key constraints** from tenant tables to central database tables
- Store central database references as regular integer/string fields, not foreign keys
- Tests use MySQL with unique databases per run to ensure isolation and production-like behavior
- **ALWAYS use `RefreshDatabaseWithTenancy` trait** in test classes to handle tenant migrations
- **DO NOT add auditing to tenant models** - only central models (User, Tenant) use `Auditable` trait

## Pointers & Examples

- Example tenant filter: non–`super-admin` queries add `where('tenant_id', auth()->user()->tenant_id)` in repositories
- Example filter usage: `GET /api/audit-engagements?search=SOX&status=active&per_page=10` → `{Domain}Filters` apply `search` + `status`
- Resource usage: `return AuditEngagementResource::make($engagement)->setMessage('Engagement retrieved')->addMetadata('findings_count', $count);`

For deeper details and commands, see `README.md` and the extended guidance in `CLAUDE.md`.

---

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- laravel/framework (LARAVEL) - v13
- laravel/nightwatch (NIGHTWATCH) - v1
- laravel/passport (PASSPORT) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/reverb (REVERB) - v1
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v3
- react (REACT) - v19
- tailwindcss (TAILWINDCSS) - v4

## Conventions

- Follow all existing code conventions. When creating or editing a file, check sibling files for correct structure, approach, and naming.
- Use descriptive names for variables and methods.
- Check for existing components to reuse before writing new ones.

## Verification Scripts

- Don't create verification scripts or tinker when tests cover that functionality. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Don't change dependencies without approval.

## Replies

- Be concise — focus on what's important, not obvious details.

## Documentation Files

- Only create documentation files if explicitly requested.

=== boost rules ===

## Laravel Boost

- Laravel Boost is an MCP server with powerful tools for this application. Use them.

## Artisan

- Use `list-artisan-commands` tool when calling Artisan commands to verify available parameters.

## URLs

- When sharing project URLs, use `get-absolute-url` tool for correct scheme, domain/IP, and port.

## Tinker / Debugging

- Use `tinker` tool to execute PHP for debugging or querying Eloquent models.
- Use `database-query` tool when only reading from database.

## Searching Documentation (Critically Important)

- Use `search-docs` tool before any other approaches for Laravel or Laravel ecosystem packages.
- Use multiple, broad, simple, topic-based queries.
- Don't add package names to queries — package info already shared.

=== php rules ===

## PHP

- Always use curly braces for control structures, even if one line.

### Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
- Don't allow empty `__construct()` with zero parameters unless constructor is private.

### Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within code unless something very complex.

## Enums

- Keys in Enum should be TitleCase.

=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write or update a test, then run affected tests to verify passing.
- Run minimum tests needed for quality and speed. Use `php artisan test --compact` with specific filename or filter.

=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (migrations, controllers, models, etc.).
- Pass `--no-interaction` to all Artisan commands.

### Database

- Always use proper Eloquent relationship methods with return type hints.
- Use Eloquent models and relationships before raw database queries.
- Avoid `DB::`; prefer `Model::query()`.
- Prevent N+1 query problems using eager loading.

### Model Creation

- When creating new models, create useful factories and seeders too.

### APIs & Eloquent Resources

- Default to Eloquent API Resources and API versioning for APIs.

### Controllers & Validation

- Always create Form Request classes for validation, not inline validation in controllers.
- Include both validation rules and custom error messages.

### Queues

- Use queued jobs for time-consuming operations with `ShouldQueue` interface.

### Authentication & Authorization

- Use Laravel's built-in auth and authorization (gates, policies, Passport).

### URL Generation

- Prefer named routes and `route()` function.

### Configuration

- Use env vars only in config files — never use `env()` outside config files.

### Testing

- Use factories when creating models for tests.
- Use `php artisan make:test {name}` to create tests (Pest format).
- Most tests should be feature tests.

=== laravel/v12 rules ===

## Laravel 12

### Laravel 12 Structure

- Middleware configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` registers middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains app-specific service providers.
- Console commands in `app/Console/Commands/` are auto-available.

### Database

- When modifying a column, migration must include all previously defined attributes.
- Laravel 12 allows limiting eagerly loaded records natively.

### Models

- Casts should be set in `casts()` method, not `$casts` property. Follow existing conventions.

=== pint/core rules ===

## Laravel Pint Code Formatter

- Run `vendor/bin/pint --dirty` before finalizing changes.
- Don't run `vendor/bin/pint --test` — run `vendor/bin/pint` to fix formatting.

=== pest/core rules ===

## Pest

- App uses Pest v3 for testing. All tests must be written in Pest syntax.
- Use `php artisan make:test {name}` to create tests (Pest format).
- If test uses PHPUnit class syntax, convert to Pest.
- Every time a test is updated, run that singular test.
- When feature tests pass, ask user if they'd like to run entire test suite.
- Tests should cover happy paths, failure paths, and edge cases.
- Don't remove tests or test files without approval.

### Running Tests

- All tests: `php artisan test --compact`.
- All tests in file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- Filter by test name: `php artisan test --compact --filter=testName`.

=== tailwindcss/v4 rules ===

## Tailwind CSS 4

- Always use Tailwind CSS v4; don't use deprecated utilities.
- Configuration is CSS-first using `@theme` directive.
- Import using `@import "tailwindcss"` not `@tailwind` directives.
</laravel-boost-guidelines>
