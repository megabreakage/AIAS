# AIAS — AI Coding Agent Guide (GitHub Copilot)

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
- Permissions map: `config/permissions_map.php` defines module permissions and role assignments

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
- **RefreshDatabaseWithTenancy**: Custom trait that handles both central and tenant migrations

### Running Tests
```bash
docs/scripts/test.sh                                          # All tests
docs/scripts/test.sh --filter=test_can_create_audit_engagement # Specific test
docs/scripts/test.sh tests/Feature/AuditEngagementTest.php    # Specific file
docs/scripts/test.sh --parallel                               # Parallel execution
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
- List endpoints: call `browse(...)` with a dedicated `{Domain}Filters` composing filter classes
- Authorization: use Policies/Gates; non–`super-admin` users are always tenant-scoped in repositories
- Responses: return `{Domain}Resource`/`{Domain}Collection`, set messages with `->setMessage()` and metadata via `->addMetadata()`

## Adding a Tenant-Scoped Feature (Checklist)
1. Routes in `routes/api.php` (use `auth:api` and `{tenant}` when relevant)
2. Create Form Request in `app/Http/Requests` with rules + messages
3. Add repository in `app/Repositories`; enforce tenant filtering for non–`super-admin`
4. Compose filters in `app/Filters/{Domain}` and apply in `browse(...)`
5. Create Resource/Collection in `app/Http/Resources` for response envelope
6. Add Policy in `app/Policies` and register if needed
7. Update `config/permissions_map.php` if new permissions are required

## Feature Development Workflow

### Required Steps:
1. **Follow Existing Architectural Patterns** - Use established patterns as templates
2. **Create OpenAPI Documentation** - `storage/api-docs/{feature-name}.openapi.yaml`
3. **Create Feature Documentation** - `docs/features/{feature-name}.md`
4. **Update Features Index** - Add to `docs/features/README.md`
5. **Update Permissions** - Add to `config/permissions_map.php` if applicable
6. **Write Comprehensive Tests** - Unit, feature, edge cases
7. **Update Postman Collection**
8. **Document Breaking Changes**

### Completion Checklist:
- [ ] Architectural patterns followed
- [ ] OpenAPI YAML documentation created in `storage/api-docs/`
- [ ] Feature documentation created/updated
- [ ] Permissions added/updated in config
- [ ] Tests written and passing (using MySQL via `docs/scripts/test.sh`)
- [ ] Tests use `RefreshDatabaseWithTenancy` trait (not `RefreshDatabase`)
- [ ] Postman collection updated
- [ ] Breaking changes documented
- [ ] Code formatted: `vendor/bin/pint --dirty`
- [ ] All tests passing: `docs/scripts/test.sh`

## Gotchas
- Passport password grant requires `PASSPORT_PASSWORD_CLIENT_ID` and `PASSPORT_PASSWORD_CLIENT_SECRET` in `.env`
- Tenancy DB work is synchronous in dev; consider queuing in prod
- Always ensure Spatie's team is set before permission checks (middleware)
- **NEVER create foreign key constraints** from tenant tables to central database tables
- Store central database references as regular integer/string fields, not foreign keys
- **ALWAYS use `RefreshDatabaseWithTenancy` trait** in test classes
- **DO NOT add auditing to tenant models** - only central models use `Auditable` trait
- Use `DB::transaction(function () { ... })` closures — never manual begin/commit/rollback
