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
2. Model extending `BaseModel` with `SoftDeletes` — NO `Auditable` trait
3. Repository in `app/Repositories/Tenant/` with tenant filtering
4. Form Requests in `app/Http/Requests/Tenant/`
5. Resource in `app/Http/Resources/Tenant/`
6. Policy in `app/Policies/`
7. Controller in `app/Http/Controllers/Api/V1/Tenant/`
8. Routes in `routes/tenant.php` with `auth:api` + `tenant.token` middleware
9. Filters in `app/Filters/Tenant/`
10. Permissions in `config/role-permission-map.php`

## Critical Rules
- NEVER create FK constraints from tenant → central tables
- NEVER add `Auditable` to tenant models
- Tenant filtering: `where('tenant_id', tenant()?->id)` in repositories
- Super-admin bypasses tenant filters via `Gate::before()`
- Use `identifier` (UUID) for route model binding, not `id`

## Reference Patterns
- See [PreambleController](../../app/Http/Controllers/Api/V1/Tenant/PreambleController.php) for controller pattern
- See [PreambleRepository](../../app/Repositories/Tenant/PreambleRepository.php) for repository pattern
- See [Preamble](../../app/Models/Tenant/Preamble.php) for model pattern
