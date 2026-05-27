
# AIAS Production Architecture Checklist & Guidelines

## Multi-Tenancy

- [ ] Each tenant = isolated MySQL DB (`aias_tenant_<id>_db`)
- [ ] Central DB holds: Users, Tenants, OAuth tokens(for central db users only), continents, countries, roles(for central db users only) permissions(for central db users only) only
- [ ] **NO FK constraints** from tenant tables → central tables (plain fields)
- [ ] `tenant_id` stored as regular string/int field, not FK
- [ ] `InitializeTenancyFromUser` middleware on all tenant-scoped routes
- [ ] `SetSpatieTeamFromTenant` middleware sets Spatie team before permission checks
- [ ] **DO NOT** add `Auditable` trait to tenant models — central only (User, Tenant)

## Repository Pattern

- [ ] Controllers inject repository, zero direct model queries
- [ ] All repos extend `BaseRepository` (`browse/read/insert/update/delete`)
- [ ] Non-super-admin queries ALWAYS add `where('tenant_id', auth()->user()->tenant_id)`
- [ ] Reuse existing repos (DRY) — inject `ContactRepository` instead of duplicating
- [ ] Load relationships on read/create/update: `->load(['creator', 'updater'])`

## Transaction Pattern

- [ ] `Gate::authorize()` BEFORE transaction
- [ ] `$request->validated()` BEFORE transaction
- [ ] `DB::transaction(fn)` wraps ONLY repository calls
- [ ] Logging AFTER transaction
- [ ] No manual `beginTransaction/commit/rollBack` — let exceptions bubble
- [ ] No authorization or logging inside transaction closure

## Authorization

- [ ] Policies in Policies — `before()` method for super-admin bypass
- [ ] Tenant boundary check: `$actor->tenant_id === $model->tenant_id`
- [ ] Permission check: `$actor->can('module.action')`
- [ ] `Gate::authorize()` called in controller before any DB work

## Models (Tenant-Scoped)

- [ ] Location: Tenant
- [ ] **ALWAYS** extends `BaseModel` **NEVER** `Model`
- [ ] Traits: `HasFactory`, `SoftDeletes`, `TenantConnection`
- [ ] `boot()` auto-populates: UUID `identifier`, `tenant_id`, `created_by`, `updated_by`
- [ ] Route binding uses `identifier` (UUID), not `id`
- [ ] Casts defined in `casts()` method, not `$casts` property

## Migrations (Tenant)

- [ ] Location: tenant
- [ ] Required fields: `id`, `uuid identifier`, `tenant_id`, `created_by`, `updated_by`, `timestamps`, `softDeletes`
- [ ] `created_by`/`updated_by` = `unsignedBigInteger()->nullable()` — **no FK**
- [ ] Tenant-unique constraints: `unique(['tenant_id', 'field'])`

## API Layer

- [ ] All responses via Resource classes extending `BaseResource`
- [ ] **STRICTLY** DEFAULT Envelope: `status`, `message`, `data`, optional `metadata`
- [ ] `->setMessage()`, `->addMetadata()` on resource before return
- [ ] Form Requests in `app/Http/Requests/{Domain}/` — no inline validation
- [ ] Unique rules scoped to tenant: `Rule::unique()->where('tenant_id', ...)`
- [ ] Routes named: `api.{resource}.{action}`

## Filter Pattern

- [ ] Main `{Domain}Filters` extends `EloquentFilter`
- [ ] Individual filter classes in `app/Filters/{Domain}/Filters/`
- [ ] Single-responsibility per filter class
- [ ] Controller: `Filters::fromRequest($request)` → pass to repository

## Permissions

- [ ] All permissions defined in `permission-map.php`
- [ ] New feature adds module permissions + role assignments
- [ ] Clear cache after changes: `php artisan cache:clear`

## Testing

- [ ] **ALWAYS** `docs/scripts/test.sh` — never raw `php artisan test`
- [ ] All test classes use `RefreshDatabaseWithTenancy`, not `RefreshDatabase`
- [ ] Tenant resources created inside `$this->tenant->run(fn)` context
- [ ] Test: happy paths + failure paths + tenant isolation (cross-tenant leakage)
- [ ] Set Spatie team before role assignment in test setup
- [ ] Use factories — check for existing states before manual setup

## Code Quality

- [ ] `vendor/bin/pint --dirty` before finalizing
- [ ] PHP 8 constructor property promotion
- [ ] Code follows PSR-12 and Laravel best practices (e.g. no `dd()`, use `Log::debug()`, Eloquent over Query Builder, etc.)
- [ ] Run `composer analyse` to enforce repository-only boundaries in production layers
- [ ] Run `vendor/bin/pint --dirty` before commits
- [ ] Review code for security vulnerabilities and performance optimizations
- [ ] Production-ready code only
- [ ] Explicit return types on all methods
- [ ] Curly braces on all control structures
- [ ] PHPDoc blocks over inline comments

## Feature Completion

- [ ] OpenAPI YAML in `storage/api-docs/`
- [ ] Feature doc in `storage/features/`
- [ ] README.md updated
- [ ] Postman collection updated (`docs/postman/AIAS-API.postman_collection.json`)
- [ ] Postman `docs/postman/COLLECTION_GUIDE.md` + `docs/postman/QUICK_REFERENCE.md` updated
- [ ] New .env vars documented

---

## References

1. [GUIDELINES](GUIDELINES.md)
2. [CENTRAL MODEL ARCHITECTURE](CENTRAL_MODEL.md)
3. [TENANT MODEL ARCHITECTURE](TENANT_MODEL.md)
