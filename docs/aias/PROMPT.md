# AI Prompt: Build AIAS (Adaptive Intelligent Audit System)

## Project Overview

Build **AIAS** — an **Adaptive Intelligent Audit System** — a multi-tenant Laravel 13 API application for managing audit engagements, compliance tracking, risk assessments, and audit workflows across organizations. AIAS enables audit firms and internal audit departments to manage their entire audit lifecycle with intelligent automation, real-time collaboration, and comprehensive reporting.

Use the exact same architectural patterns, conventions, package versions, and steering documents defined below. This is a backend-first API application with database-per-tenant isolation.

---

## Technology Stack (Exact Versions)

### PHP & Laravel

```json
{
    "require": {
        "php": "^8.4",
        "laravel/framework": "^13.0",
        "laravel/nightwatch": "^1.26",
        "laravel/passport": "^13.0",
        "laravel/reverb": "^1.6",
        "laravel/tinker": "^2.10.1",
        "owen-it/laravel-auditing": "^14.0",
        "sentry/sentry-laravel": "^4.20",
        "spatie/laravel-permission": "^7.4",
        "stancl/tenancy": "^3.10",
        "barryvdh/laravel-dompdf": "*",
        "symfony/options-resolver": "^8.0"
    },
    "require-dev": {
        "brianium/paratest": "^8.0",
        "fakerphp/faker": "^1.23",
        "laravel/boost": "^1.8",
        "laravel/mcp": "^0.5.9",
        "laravel/pail": "^1.2.2",
        "laravel/pint": "^1.24",
        "laravel/sail": "^1.50",
        "mockery/mockery": "^1.6",
        "nunomaduro/collision": "^8.6",
        "pestphp/pest": "^3.0",
        "pestphp/pest-plugin-laravel": "^3.0"
    }
}
```

### Frontend (Minimal — API-first)

```json
{
    "devDependencies": {
        "@tailwindcss/vite": "^4.0.0",
        "axios": "^1.11.0",
        "concurrently": "^9.0.1",
        "laravel-vite-plugin": "^2.0.0",
        "tailwindcss": "^4.0.0",
        "vite": "^7.0.7"
    },
    "dependencies": {
        "react": "^19.2.5",
        "react-dom": "^19.2.5"
    }
}
```

---

## Multi-Tenancy Architecture

### Database Isolation Strategy

- **Central Database**: Users, tenants, OAuth tokens, roles/permissions, audit logs, global reference data (countries, currencies, audit standards, regulation frameworks)
- **Tenant Databases**: Dynamically created per-tenant databases (`aias_tenant_<uuid>_db`) containing tenant-specific data (audit engagements, findings, workpapers, risk assessments)
- **Package**: `stancl/tenancy` v3.10 with `DatabaseTenancyBootstrapper`

### Tenancy Configuration

- Path-based tenancy: `InitializeTenancyByPath` middleware
- Tenant ID format: UUID generated via `TenantIdGenerator`
- Tenant model with soft deletes, auditing, status enum cast
- `TenancyServiceProvider` handles `TenantCreated` → DB create + migrate

### Central vs Tenant Model Rules

| Aspect | Central Models | Tenant Models |
|--------|----------------|---------------|
| Database | `central` connection / `CentralConnection` trait | `tenant` connection via `TenantConnection` trait |
| Auditing | YES — Use `Auditable` trait | NO — Avoid cross-database complexity |
| Tenant ID | NO — Not tenant-scoped | YES — Required for isolation |
| Repository Filtering | Access control by role/permission | Mandatory tenant filtering |
| Migration Location | `database/migrations/` | `database/migrations/tenant/` |
| FK Constraints | Standard FK to central tables | NEVER FK to central database tables |

---

## Authentication & Authorization

### Authentication

- **Laravel Passport** v13 (OAuth2 password grant)
- `api` guard for all API routes
- 15-day token expiry
- MFA support (TOTP-based)

### Authorization

- **Spatie Permission** v7 with teams enabled
- Team key is `tenant_id`
- `SetSpatieTeamFromTenant` middleware sets Spatie team from `{tenant}` path or current user
- Permission checks use Gates/Policies, never manual role checks

### Suggested AIAS Roles

```php
'roles' => [
    'super-admin' => ['*'],  // Full system access (Central + all tenants)
    'admin' => [...], // Manage users, tenants, api-keys, service-users, settings e.t.c (Central access only)
    'staff' => [...], // Access to assigned central permissions (Default central models: Users:view only their details, Tenants:`view only` unless specified otherwise, plus any additional central permissions assigned)
    'tenant-owner' => [...], // Owner of the Firm - Full tenant access (All Tenant Permissions, Cannot manage other tenants or system settings)
    'tenant-admin' => [...], // Tenant admin access with some restrictions (Cannot manage tenant-owner user details, cannot create super-admin, tenant-owner, another tenant-admin or edit their tenant-admin role)
    'audit-manager' => [...], // Manage audit engagements, assign auditors
    'senior-auditor' => [...], // Lead audits, review workpapers
    'auditor' => [...],       // Execute audit procedures, create findings
    'auditee' => [...], // The contact person of the client/individual being audited. Views Audit, respond to findings, e.t.c
    'reviewer' => [...],      // Review-only access to completed audits
    'client-liaison' => [...], // Client-facing portal access
]
```

### Suggested AIAS Permission Modules

```php
'permissions' => [
    'dashboard' => ['view'],
    'audit-engagements' => ['view', 'create', 'edit', 'delete', 'assign', 'approve', 'close'],
    'audit-plans' => ['view', 'create', 'edit', 'delete', 'approve'],
    'audit-procedures' => ['view', 'create', 'edit', 'delete', 'execute', 'review'],
    'workpapers' => ['view', 'create', 'edit', 'delete', 'review', 'sign-off'],
    'findings' => ['view', 'create', 'edit', 'delete', 'escalate', 'resolve'],
    'risk-assessments' => ['view', 'create', 'edit', 'delete', 'approve'],
    'compliance' => ['view', 'create', 'edit', 'delete', 'track'],
    'reports' => ['view', 'create', 'edit', 'delete', 'export', 'schedule'],
    'clients' => ['view', 'create', 'edit', 'delete'],
    'audit-standards' => ['view', 'create', 'edit', 'delete'],
    'regulation-frameworks' => ['view', 'create', 'edit', 'delete'],
    'users' => ['view', 'create', 'edit', 'delete', 'assign-roles'],
    'roles' => ['view', 'create', 'edit', 'delete', 'assign-permissions'],
    'settings' => ['view', 'edit'],
    'hr-departments' => ['view', 'create', 'edit', 'delete'],
    'groups' => ['view', 'create', 'edit', 'delete'],
    'mfa' => ['view-status', 'setup', 'enable', 'disable'],
]
```

---

## Application Architecture Patterns

### Repository Pattern (MANDATORY)

All controllers use repositories. Never query models directly in controllers.

```php
// Controller constructor
public function __construct(protected AuditEngagementRepository $repository) {}

// Repository with tenant filtering
public function browseEngagements(
    AuditEngagementFilters $filters,
    int $page = 1,
    int $perPage = 20,
    ?string $sortBy = null,
    bool $sortDesc = false
): Paginator {
    $query = $this->query()->with(['creator', 'assignedAuditors']);

    if (!auth()->user()->hasRole('super-admin')) {
        $query->where('tenant_id', auth()->user()->tenant_id);
    }

    $filters->apply($query);

    if ($sortBy) {
        $query->orderBy($sortBy, $sortDesc ? 'desc' : 'asc');
    }

    return $query->paginate(perPage: min($perPage, 100), page: max($page, 1));
}
```

### Transaction Pattern (CRITICAL)

Always use `DB::transaction(function () { ... })` closures:

1. Authorization BEFORE transactions
2. Validation BEFORE transactions
3. Wrap ONLY database writes in transaction
4. Logging AFTER transactions
5. Let exceptions bubble — no manual rollback

```php
public function store(CreateEngagementRequest $request): JsonResponse
{
    Gate::authorize('create', AuditEngagement::class);

    try {
        $data = $request->validated();
        Log::info('Creating audit engagement', ['title' => $data['title']]);

        $engagement = DB::transaction(function () use ($data) {
            return $this->repository->createEngagement($data);
        });

        Log::info('Audit engagement created', ['id' => $engagement->id]);

        return (new AuditEngagementResource($engagement))
            ->setMessage('Audit engagement created successfully')
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);

    } catch (\Throwable $e) {
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

### Filter Pattern

```
app/Filters/
  ├── EloquentFilter.php (base class)
  └── {Module}/
      ├── {Module}Filters.php (main filter class)
      └── Filters/
          ├── SearchTermFilter.php
          ├── StatusFilter.php
          └── DateRangeFilter.php
```

### Resource Pattern

API responses use custom `BaseResource` envelope:

```php
return AuditEngagementResource::make($engagement)
    ->setMessage('Engagement retrieved successfully')
    ->addMetadata('total_findings', $engagement->findings_count);
```

Response envelope: `{ status, message, data, metadata }`

### Request Validation

Form Request classes for all validation. Never inline `$request->validate()`.

```php
class CreateAuditEngagementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', AuditEngagement::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'audit_type' => ['required', 'string', Rule::in(AuditType::values())],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Engagement title is required.',
            'client_id.exists' => 'Selected client does not exist.',
            'end_date.after' => 'End date must be after start date.',
        ];
    }
}
```

---

## Suggested AIAS Domain Models

### Central Database Models

| Model | Purpose |
|-------|---------|
| User | System users with tenant relationships |
| Tenant | Organizations (audit firms, internal audit departments) |
| AuditStandard | Global audit standards (ISA, IIA, GAAS, PCAOB) |
| RegulationFramework | Regulatory frameworks (SOX, GDPR, HIPAA, Basel III) |
| RiskCategory | Global risk category definitions |
| Country / Continent | Geographic reference data |

### Tenant Database Models

| Model | Purpose |
|-------|---------|
| AuditEngagement | Audit projects/engagements |
| AuditPlan | Detailed audit plans for engagements |
| AuditProcedure | Individual audit steps/procedures |
| Workpaper | Audit evidence and documentation |
| Finding | Audit observations and findings |
| FindingResponse | Management responses to findings |
| RiskAssessment | Entity/process risk assessments |
| RiskMatrix | Risk scoring matrices |
| ComplianceRequirement | Compliance tracking items |
| ComplianceEvidence | Evidence for compliance requirements |
| Client | Audit client entities |
| Department | Organizational departments |
| Group | User groups for assignment |
| AuditTemplate | Reusable audit program templates |
| ControlObjective | Internal control objectives |
| ControlTest | Tests of controls |

---

## Directory Structure

```
app/
  Auth/
  Console/
  Contracts/
  Enums/
  Events/
  Exceptions/
  Filters/
    EloquentFilter.php
    AuditEngagements/
      AuditEngagementFilters.php
      Filters/
        SearchTermFilter.php
        StatusFilter.php
        DateRangeFilter.php
    Findings/
      FindingFilters.php
      Filters/
        SeverityFilter.php
        StatusFilter.php
  Http/
    Controllers/
    Middleware/
      InitializeTenancyFromUser.php
      SetSpatieTeamFromTenant.php
    Requests/
      AuditEngagement/
        CreateAuditEngagementRequest.php
        UpdateAuditEngagementRequest.php
    Resources/
      BaseResource.php
      AuditEngagements/
        AuditEngagementResource.php
        AuditEngagementCollection.php
  Jobs/
  Listeners/
  Mail/
  Models/
  Notifications/
  Policies/
  Providers/
    TenancyServiceProvider.php
  Repositories/
    BaseRepository.php
    AuditEngagementRepository.php
  Services/
  Tenancy/
    TenantIdGenerator.php
  Traits/
bootstrap/
  app.php
  providers.php
config/
  tenancy.php
  role-permission-map.php
  passport.php
  permission.php
  audit.php
database/
  factories/
  migrations/          # Central database migrations
  migrations/tenant/   # Tenant database migrations
  seeders/
routes/
  api.php
  tenant.php
  web.php
  console.php
  channels.php
storage/
  api-docs/
tests/
  Feature/
  Unit/
  Traits/
    RefreshDatabaseWithTenancy.php
postman/
  collections/
  environments/
docs/
  features/
  architecture/
```

---

## DRY Principles (MANDATORY)

1. **Search Before Creating**: Check existing repositories, services, helpers, traits before writing new code
2. **Inject Dependencies**: Never copy-paste logic — always inject repositories/services via constructor
3. **Reuse Repository Methods**: If functionality exists in another repository, inject and call it
4. **Tenant Filtering**: Use repository methods for tenant filtering, never duplicate `where('tenant_id', ...)` logic
5. **Refactor Immediately**: If you find yourself copying code from another file, refactor to a shared service

---

## Testing Requirements

### Test Infrastructure

- **MySQL** with unique test databases per run (production-like)
- **`./test.sh`** script creates `aias_test_<timestamp>_<pid>` databases
- All test classes use `RefreshDatabaseWithTenancy` trait
- Pest v3 (NOT PHPUnit directly — Pest wraps PHPUnit under the hood)

### Test Coverage Requirements

- All CRUD operations (happy path + failure path)
- Authorization/permission checks
- Tenant isolation verification
- Filter functionality
- Validation rules
- Edge cases (duplicate keys, invalid data, unauthorized access)

```php
uses(RefreshDatabaseWithTenancy::class);

it('can list audit engagements', function () { ... });
it('cannot access other tenant engagements', function () { ... });
it('can create audit engagement', function () { ... });
it('can update audit engagement', function () { ... });
it('can delete audit engagement', function () { ... });
it('filters work correctly', function () { ... });
it('unauthorized user cannot access', function () { ... });
```

---

## Development Commands

```bash
# Development
php artisan serve                    # Start server
composer dev                         # Start all services (server, vite, schedule, queue, logs)

# Database
php artisan migrate --seed           # Central DB
php artisan tenants:migrate          # All tenant DBs

# Auth
php artisan passport:install         # Generate Passport keys/clients

# Testing (ALWAYS use ./test.sh)
./test.sh                           # All tests
./test.sh tests/Feature/AuditEngagementTest.php  # Specific file
./test.sh --filter=testMethodName   # Specific test

# Code formatting
vendor/bin/pint --dirty              # Format changed files
```

---

## Feature Development Checklist

When adding or updating features:

- [ ] Follow existing architectural patterns (repository, filter, resource, policy)
- [ ] Create migration (central or tenant as appropriate)
- [ ] Create model with proper traits and relationships
- [ ] Create repository with tenant filtering (if tenant-scoped)
- [ ] Create filter classes for list endpoints
- [ ] Create Form Request classes for validation
- [ ] Create Resource/Collection for API responses
- [ ] Create Policy for authorization
- [ ] Add permissions to `config/role-permission-map.php`
- [ ] Add routes to `routes/api.php`
- [ ] Create OpenAPI documentation in `storage/api-docs/`
- [ ] Create feature documentation in `docs/features/`
- [ ] Write comprehensive tests (feature + unit)
- [ ] Tests use `RefreshDatabaseWithTenancy` trait
- [ ] Update Postman collection
- [ ] Code formatted with `vendor/bin/pint --dirty`
- [ ] All tests passing via `./test.sh`

---

## Security Boundaries

- Database isolation: Tenants cannot query each other's databases
- No FK constraints from tenant tables to central database tables
- Central references stored as regular integer/string fields
- Authorization layers: Policy (Gate) → Repository (tenant filter) → Model (relationships)
- Audit trail via `owen-it/laravel-auditing` for central models only
- Soft deletes on all models for data recovery
- Sentry integration for error tracking

---

## Key Gotchas

1. Passport password grant requires `PASSPORT_PASSWORD_CLIENT_ID` and `PASSPORT_PASSWORD_CLIENT_SECRET` in `.env`
2. Tenancy DB work is synchronous in dev; consider queuing in prod
3. Always ensure Spatie's team is set before permission checks (middleware)
4. NEVER create foreign key constraints from tenant tables to central database tables
5. Store central database references as regular integer/string fields, not foreign keys
6. ALWAYS use `RefreshDatabaseWithTenancy` trait in test classes
7. DO NOT add auditing to tenant models — only central models use `Auditable` trait
8. Use `DB::transaction(function () { ... })` closures — never manual `DB::beginTransaction/commit/rollBack`

---

## Steering Documents

The following steering documents are provided with this prompt and MUST be used when building the application:

1. **`CLAUDE.md`** — Primary dev guidance for AI agents (Claude)
2. **`AGENTS.md`** — Agent configuration and boost guidelines
3. **`copilot-instructions.md`** — GitHub Copilot agent guide (save to `.github/`)
4. **`architectural-patterns.md`** — Detailed architectural patterns with code examples
5. **`references.md`** — Complete file structure examples for both central and tenant models

Start by setting up the Laravel 13 project with all packages installed, then implement features following the architectural patterns defined in the steering documents.
