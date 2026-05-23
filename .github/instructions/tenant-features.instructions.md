---
description: "Use when creating tenant-scoped features, models, migrations, controllers, repositories, or routes. Covers multi-tenancy architecture with Stancl Tenancy, database isolation, and tenant filtering."
applyTo: ["app/Models/Tenant/**", "app/Repositories/Tenant/**", "app/Http/Controllers/Api/V1/Tenant/**", "database/migrations/tenant/**", "routes/tenant.php"]
---
# Tenant Feature Development

## Architecture
- Database isolation via Stancl Tenancy v3 — each tenant gets own MySQL DB
- Tenant context set by middleware — never manually switch databases
- Central DB: users (super-admin), tenants, OAuth tokens, permissions
- Tenant DB: domain models (preambles, engagements, findings, etc.)

## Checklist for New Tenant Feature
1. Migration in `database/migrations/tenant/` — NO foreign keys to central DB
2. Model in `app/Models/Tenant/` extending `BaseModel` with `HasFactory`, `SoftDeletes`, `TenantConnection` — NO `Auditable` trait
3. Repository in `app/Repositories/Tenant/` with tenant filtering
4. Form Requests in `app/Http/Requests/Tenant/{Domain}/`
5. Resource/Collection in `app/Http/Resources/Tenant/{Domain}/` extending `BaseResource`
6. Policy in `app/Policies/`
7. Controller in `app/Http/Controllers/Api/V1/Tenant/` using repository injection
8. Routes in `routes/tenant.php` with `auth:api` middleware, named `api.{resource}.{action}`
9. Filters in `app/Filters/Tenant/{Domain}/`
10. Permissions in `config/permissions_map.php`

## Critical Rules
- NEVER create FK constraints from tenant → central tables
- NEVER add `Auditable` to tenant models
- ALWAYS extend `BaseModel` — **NEVER** `Model` directly
- `created_by`/`updated_by` = `unsignedBigInteger()->nullable()` — **no FK constraint**
- Tenant-unique constraints: `unique(['tenant_id', 'field'])`
- Tenant filtering: `where('tenant_id', auth()->user()->tenant_id)` for non-super-admin
- Super-admin bypasses tenant filters via `Gate::before()` in Policy
- Use `identifier` (UUID) for route model binding, not `id`

## Model Requirements
- Traits: `HasFactory`, `SoftDeletes`, `TenantConnection` (in that order)
- `boot()` auto-populates: UUID `identifier`, `tenant_id`, `created_by`, `updated_by`
- Casts defined in `casts()` **method**, not `$casts` property
- Route key: override `getRouteKeyName()` to return `'identifier'`

## Migration Required Fields
- `$table->id()`
- `$table->uuid('identifier')->unique()`
- `$table->string('tenant_id')` — plain field, NO FK
- `$table->unsignedBigInteger('created_by')->nullable()` — NO FK
- `$table->unsignedBigInteger('updated_by')->nullable()` — NO FK
- `$table->timestamps()`
- `$table->softDeletes()`

## Repository Requirements
- Extend `BaseRepository` — use `browse/read/insert/update/delete` base methods
- Non-super-admin: always add `where('tenant_id', auth()->user()->tenant_id)`
- Load relationships on every read/create/update: `->load(['creator', 'updater'])`
- Inject other repositories (DRY) — never duplicate logic

## Transaction Pattern (STRICTLY ENFORCED)
1. `Gate::authorize()` **BEFORE** transaction
2. `$request->validated()` **BEFORE** transaction
3. `DB::transaction(fn)` wraps **ONLY** repository calls
4. Logging **AFTER** transaction
5. No manual `beginTransaction/commit/rollBack` — let exceptions bubble

## API Layer Requirements
- **STRICTLY DEFAULT** envelope: `status`, `message`, `data`, optional `metadata`
- Always call `->setMessage()` and `->addMetadata()` on resource before return
- Unique validation rules scoped to tenant: `Rule::unique('table')->where('tenant_id', ...)`
- No inline `$request->validate()` — always use Form Request classes
- Routes named: `api.{resource}.{action}`

## Reference Patterns
- See [PreambleController](../../app/Http/Controllers/Api/V1/Tenant/PreambleController.php) for controller pattern
- See [PreambleRepository](../../app/Repositories/Tenant/PreambleRepository.php) for repository pattern
- See [Preamble](../../app/Models/Tenant/Preamble.php) for model pattern
