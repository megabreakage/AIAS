# AIAS — AI Coding Agent Guide

Purpose: Enable agents to contribute productively to this Laravel 13, multi-tenant API (Stancl Tenancy, Passport, Spatie Permission with teams, Auditing) using the project's established patterns.

## Engineer Profile Alignment

Default contributor profile for this repository:

- Senior full-stack engineer workflows
- Backend-first default: Laravel/PHP
- Secondary backend: Node.js for real-time/event-driven services
- Frontend default (when requested): React.js + TypeScript
- Mobile default (when requested): Kotlin + Jetpack Compose
- Database default: MySQL
- Standards priority: SOLID, DRY, Clean Architecture, testability, security, scalability

For project architecture and implementation conventions, link and follow:

- `README.md`
- `CLAUDE.md`
- `docs/architecture/ARCH_CHECKLIST.md`
- `docs/architecture/TENANT_MODEL.md`
- `docs/architecture/CENTRAL_MODEL.md`

## Communication Contract

When responding in chat for this workspace:

- Use direct, concise statements
- Prefer short sentences where practical
- Remove filler and generic narration
- Execute tools first when verification required
- Report concrete outcomes, then stop

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
- Responses: custom Resource envelope — **STRICTLY DEFAULT**: `status`, `message`, `data`, optional `metadata`/pagination. Never deviate from this envelope.
- **Testing**: MySQL with unique test databases per run for production-accurate, isolated tests

## Key Architecture

- Tenancy lifecycle: see `config/tenancy.php` and `app/Providers/TenancyServiceProvider.php` (handles `TenantCreated` → DB create + migrate)
- Tenant model: `app/Models/Tenant.php` (soft deletes, auditing, `status` enum cast)
- Team scoping: `app/Http/Middleware/SetSpatieTeamFromTenant.php` sets Spatie team from the `{tenant}` path or current user
- Repositories + Filters: `app/Repositories` provide `browse/read/insert/update/delete`; filters live under `app/Filters` and compose small single-responsibility classes
- Resources: `app/Http/Resources` enforce the response envelope in controllers
- Permissions map: `config/permissions_map.php` defines module permissions and role assignments

## Model Conventions (Tenant-Scoped)

- **ALWAYS** extend `BaseModel` — **NEVER** `Model` directly
- Traits in order: `HasFactory`, `SoftDeletes`, `TenantConnection`
- `boot()` auto-populates: string `identifier`, `tenant_id` (set to `tenant()->getTenantKey()` = `tenants.identifier`), `created_by`, `updated_by`
- Route binding uses `identifier` (unique string, format `AT.{n}.{time}` for tenants), not `id`
- Casts defined in `casts()` **method**, not `$casts` property
- **DO NOT** add `Auditable` trait to tenant models — central only (User, Tenant)

## Layer Boundaries (Enforced)

- Production layers (`app/Http/Controllers`, `app/Jobs`, `app/Services`) MUST use repositories for all record access and mutations
- Direct model queries in production layers are **FORBIDDEN**: `Model::query()`, `Model::find()`, `Model::where()`, `Model::create()`, `Model::update()`, `Model::delete()`
- `tests/`, `database/factories/`, `database/seeders/` may use Eloquent directly
- Run `composer analyse` to enforce boundaries before committing

## Developer Workflows

- Start services: `php artisan serve` or `composer dev`
- Migrate/seed (central): `php artisan migrate --seed`
- Tenants migrate: `php artisan tenants:migrate`
- Passport keys/clients: `php artisan passport:install`
- **Tests**: `docs/scripts/test.sh` (ALWAYS use this script to avoid MySQL locks)
- Dev script: `composer dev` runs app, vite, schedule, queue, logs

## Testing Configuration

- **ALWAYS use `docs/scripts/test.sh`** to run tests - this ensures proper database isolation
- **Script**: Creates unique MySQL database per test run (e.g., `aias_test_<timestamp>_<pid>`)
- **Database**: MySQL (matching production) for accurate testing with automatic cleanup
- **Benefits**: Production-like environment, proper isolation, supports parallel execution
- **RefreshDatabaseWithTenancy**: Custom trait that handles both central and tenant migrations

### Running Tests

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
- Responses: **STRICTLY DEFAULT** envelope — `->setMessage()` and `->addMetadata()` on every resource before return
- Load relationships on every read/create/update: `->load(['creator', 'updater'])`
- Unique validation scoped to tenant: `Rule::unique('table')->where('tenant_id', auth()->user()->tenant_id)`
- Routes named: `api.{resource}.{action}`

## Adding a Tenant-Scoped Feature (Checklist)

1. Migration in `database/migrations/tenant/` — required fields: `id`, `string identifier` (unique), `string tenant_id` (references `tenants.identifier`, indexed, no FK), `created_by`/`updated_by` (`unsignedBigInteger()->nullable()`, no FK), `timestamps`, `softDeletes`
2. Create Form Request in `app/Http/Requests/{Domain}/` with rules + messages (unique rules scoped to tenant)
3. Model in `app/Models/Tenant/` — extends `BaseModel`, traits: `HasFactory`, `SoftDeletes`, `TenantConnection`, NO `Auditable`
4. Add repository in `app/Repositories/Tenant/`; enforce tenant filtering for non–`super-admin`; load relationships
5. Compose filters in `app/Filters/Tenant/{Domain}` and apply in `browse(...)`
6. Create Resource/Collection in `app/Http/Resources/Tenant/{Domain}/` extending `BaseResource`
7. Add Policy in `app/Policies` with `before()` super-admin bypass + `tenant_id` boundary check
8. Register policy in `AppServiceProvider`
9. Add routes to `routes/api.php` named `api.{resource}.{action}`
10. Update `config/permissions_map.php` with new permissions + role assignments

## Feature Development Workflow

When adding new features or updating existing ones, follow this comprehensive workflow:

### Required Steps

1. **Follow Existing Architectural Patterns** - Use established patterns as templates
2. **Create OpenAPI Documentation** - `storage/api-docs/{feature-name}.openapi.yaml` with comprehensive API specs
3. **Create Feature Documentation** - `docs/features/{feature-name}.md` with overview, API, permissions
4. **Update Features Index** - Add to `docs/features/README.md` with proper categorization
5. **Update Permissions** - Add to `config/permissions_map.php` if applicable
6. **Write Comprehensive Tests** - Unit, feature, edge cases, integration tests
7. **Update Postman Collection** - Add endpoints to `postman/collections/`
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
- [ ] **Postman collection updated** with new endpoints, test scripts, and examples
- [ ] **Postman documentation updated**: `COLLECTION_GUIDE.md` and `QUICK_REFERENCE.md`
- [ ] **Environment variables added** for any new variables required
- [ ] Breaking changes documented
- [ ] Code formatted: `vendor/bin/pint --dirty`
- [ ] Layer boundaries verified: `composer analyse`
- [ ] All tests passing: `docs/scripts/test.sh`

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

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- laravel/framework (LARAVEL) - v13
- laravel/mcp (MCP) - v0
- laravel/passport (PASSPORT) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/boost (BOOST) - v2
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>
