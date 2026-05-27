# AIAS Module Generator Script — Implementation Summary

**File**: `docs/prompts/aias/scripts/generate-module.py`
**Requires**: Python 3.9+, no external dependencies (stdlib only)

---

## What It Does

Scaffolds a complete, production-ready Laravel module in one command, following every AIAS
architectural pattern. Supports both **central** and **tenant-scoped** model types.
All generated files contain real, working code — no TODOs or placeholders.

---

## Usage

Run from the AIAS project root:

```bash
python3 docs/prompts/aias/scripts/generate-module.py <ModuleName> [options]
```

`<ModuleName>` must be **PascalCase** — e.g. `AuditStandard`, `RiskAssessment`, `Finding`.

### Options

| Flag | Description |
|---|---|
| `--central` | Generate as a central database model (default: tenant-scoped) |
| `--fields "specs"` | Comma-separated field definitions (see Field Specs below) |
| `--dry-run` | Preview all files that would be created — writes nothing |
| `--skip-tests` | Skip Feature and Unit test generation |
| `--overwrite` | Overwrite existing files (default: skip existing) |
| `--base-dir <path>` | AIAS project root (default: current directory) |
| `--verbose` / `-v` | Enable DEBUG-level logging |

### Examples

```bash
# Tenant-scoped module (default)
python3 docs/prompts/aias/scripts/generate-module.py RiskAssessment

# Central reference data module
python3 docs/prompts/aias/scripts/generate-module.py AuditStandard --central

# With custom fields
python3 docs/prompts/aias/scripts/generate-module.py Finding \
  --fields "severity:string:required,status:string:required,resolution:text:nullable"

# Preview only — safe, writes no files
python3 docs/prompts/aias/scripts/generate-module.py AuditPlan --dry-run

# API-only regeneration (no tests)
python3 docs/prompts/aias/scripts/generate-module.py Country --central --skip-tests

# Overwrite existing files
python3 docs/prompts/aias/scripts/generate-module.py Continent --central --overwrite

# Full spec with verbose logging
python3 docs/prompts/aias/scripts/generate-module.py ControlObjective \
  --fields "code:string:required,unique,objective:text:required,risk_level:string:nullable" \
  --verbose
```

---

## Field Spec Format

```
--fields "fieldName:type:modifiers,fieldName:type:modifiers,..."
```

### Supported Types

| Type | PHP / Migration | Notes |
|---|---|---|
| `string` | `VARCHAR(255)` | Default type |
| `text` | `TEXT` | Long-form content |
| `integer` | `INT` | |
| `boolean` | `TINYINT(1)` | Cast to `boolean` |
| `date` | `DATE` | Cast to `date` |
| `datetime` | `DATETIME` | Cast to `datetime` |
| `decimal` | `DECIMAL(15,2)` | Cast to `float` |

### Supported Modifiers (comma-separated after type)

| Modifier | Effect |
|---|---|
| `required` | Adds `required` validation rule |
| `nullable` | Adds `->nullable()` on column + `nullable` validation rule |
| `unique` | Adds `->unique()` on column + `Rule::unique(...)` in requests |

### Field Spec Examples

```bash
# Required unique string
code:string:required,unique

# Nullable long text
notes:text:nullable

# Required integer
capacity:integer:required

# Nullable decimal score
score:decimal:nullable

# Required date
effective_date:date:required
```

---

## What Gets Generated — 10 Stages, 14+ Files

| # | Stage | File(s) |
|---|---|---|
| 1 | **Migration** | `database/migrations/{timestamp}_create_{table}_table.php` (central) OR `database/migrations/tenant/` (tenant) |
| 2 | **Model** | `app/Models/{Model}.php` |
| 3 | **Repository** | `app/Repositories/{Model}Repository.php` |
| 4 | **Filters** | `app/Filters/{Models}/{Model}Filters.php` + `Filters/SearchTermFilter.php` + `IsActiveFilter.php` |
| 5 | **Form Requests** | `app/Http/Requests/{Model}/Create{Model}Request.php` + `Update{Model}Request.php` |
| 6 | **API Resources** | `app/Http/Resources/{Models}/{Model}Resource.php` + `{Model}Collection.php` |
| 7 | **Policy** | `app/Policies/{Model}Policy.php` |
| 8 | **Controller** | `app/Http/Controllers/{Model}Controller.php` |
| 9 | **Factory & Seeder** | `database/factories/{Model}Factory.php` + `database/seeders/{Model}Seeder.php` |
| 10 | **Pest v3 Tests** | `tests/Feature/{Model}Test.php` + `tests/Unit/{Model}UnitTest.php` |

All files skipped cleanly (with a `SKIP (exists)` warning) if they already exist, unless `--overwrite` is passed.

---

## Central vs Tenant Model Differences

| Aspect | `--central` | Default (tenant-scoped) |
|---|---|---|
| Model trait | `CentralConnection` + `Auditable` | `TenantConnection` |
| `$connection` | `'central'` (explicit) | Tenant DB (dynamic) |
| `Auditable` trait | YES | NO |
| `tenant_id` field | NO | YES (auto-set from tenancy context) |
| `boot()` UUID | `Auth::check()` | `tenancy()->tenant` context |
| Migration location | `database/migrations/` | `database/migrations/tenant/` |
| Repository filtering | None (role/permission only) | `where('tenant_id', ...)` for non-super-admin |
| Run migration with | `php artisan migrate` | `php artisan tenants:migrate` |
| Tenant isolation test | Not generated | Generated (`cannot access other tenant`) |

---

## AIAS Architectural Patterns Applied

Every generated file strictly follows the patterns defined in `docs/prompts/aias/architectural-patterns.md`:

### Model

- **Central**: extends `Model`, implements `AuditableContract`, uses `Auditable`, `CentralConnection`, `HasFactory`, `SoftDeletes`, explicit `$connection = 'central'`
- **Tenant**: extends `Model`, uses `HasFactory`, `SoftDeletes`, `TenantConnection`, includes `tenant_id`, NO `Auditable`
- Both: UUID `identifier` in `boot()`, `getRouteKeyName()` returning `'identifier'`, `created_by`/`updated_by` audit fields, `creator()`/`updater()` relationships

### Repository

- Extends `BaseRepository`, named methods (`browse{Models}`, `read{Model}`, `create{Model}`, `update{Model}`, `delete{Model}`, `restore{Model}`)
- Looks up by `identifier` (UUID) not by primary key `id`
- Tenant repos: mandatory `where('tenant_id', ...)` filter for non-super-admin users
- Eager-loads `creator`/`updater` in all read operations
- `max 100` per-page cap enforced in `paginate()`

### Filters

- `EloquentFilter` base class with composable filter set
- SQL wildcard escaping in `SearchTermFilter`
- Separate `IsActiveFilter` for status filtering
- `fromRequest()` factory method on the main filter class

### Form Requests

- `authorize()` uses `Gate::allows()` — never inline permission checks
- `Create`: `Rule::unique(table, column)` for unique fields
- `Update`: `Rule::unique(...)->ignore($id, 'identifier')` for UUID-keyed uniqueness
- Custom `messages()` for user-facing validation errors

### Policy

- Uses `hasPermissionTo()` (NOT `can()`) for permission checks
- Permission format: `{plural-kebab}.view`, `.create`, `.edit`, `.delete`
- `HandlesAuthorization` trait included

### Controller

- Strict transaction pattern: **Gate → validate → pre-log → `DB::transaction(repo only)` → post-log**
- Route parameter is `string $id` — manual lookup via repository (not route model binding)
- Read before authorize on update/delete (fetch model first, then `Gate::authorize`)
- `ModelNotFoundException` handled with 404; `\Throwable` handled with 500
- Response via `{Model}Resource` / `{Model}Collection` with `setMessage()` + `addMetadata()`

### Resource

- Extends `BaseResource`, implements `resourceData()` abstract method
- Response envelope: `{ status, message, data }`
- `{Model}Collection` is a standalone class extending `ResourceCollection` with chainable `setMessage()` and `addMetadata()`
- Creator/updater returned via `whenLoaded()` with `identifier` + `name`

### Tests (Pest v3)

- `uses(RefreshDatabaseWithTenancy::class)` — NEVER plain `RefreshDatabase`
- `beforeEach()` for user setup + role assignment + `RolePermissionsSeeder`
- `it()` function syntax — NOT PHPUnit class methods
- Covers: list, unauthenticated rejection, search filter, status filter, show, 404, create, duplicate name, unauthorized create, update, same-name update, update 404, delete (soft), restore
- Tenant modules also include: tenant isolation test (`cannot access other tenant {models}`)

---

## Rollback on Failure

If any stage throws an exception (including `Ctrl+C`), the `RollbackManager` automatically deletes every file and empty directory created during that run. The project is left in exactly the same state as before the script ran.

```
08:14:33  ERROR    Stage '_stage_controller' failed: ...
08:14:33  WARNING  ⏪  Rolling back…
08:14:33  WARNING  ✔  Rollback complete — no partial files left.
```

---

## Required Manual Steps After Generation

The script prints these at the end of every successful run:

1. **Register the policy** in `app/Providers/AppServiceProvider.php`:

   ```php
   Gate::policy(\App\Models\{Model}::class, \App\Policies\{Model}Policy::class);
   ```

2. **Add permissions** to `config/permissions_map.php`:

   ```php
   '{plural-kebab}.view', '{plural-kebab}.create', '{plural-kebab}.edit', '{plural-kebab}.delete'
   ```

3. **Add routes** inside the `auth:api` group in `routes/api.php`:

   ```php
   Route::apiResource('{plural-kebab}', {Model}Controller::class);
   Route::post('{plural-kebab}/{id}/restore', [{Model}Controller::class, 'restore'])
       ->name('{plural_snake}.restore');
   ```

4. **Run migration and seeder**:

   ```bash
   # Central module
   php artisan migrate
   php artisan db:seed --class={Model}Seeder

   # Tenant module
   php artisan tenants:migrate
   ```

5. **Run tests**:

   ```bash
   docs/scripts/test.sh tests/Feature/{Model}Test.php
   docs/scripts/test.sh tests/Unit/{Model}UnitTest.php
   ```

6. **Format**:

   ```bash
   vendor/bin/pint --dirty
   ```

---

## Naming Convention Derivation

Given input `AuditStandard`:

| Variant | Value |
|---|---|
| PascalCase | `AuditStandard` |
| camelCase | `auditStandard` |
| snake_case | `audit_standard` |
| kebab-case | `audit-standard` |
| plural snake | `audit_standards` |
| plural Pascal | `AuditStandards` |
| plural kebab | `audit-standards` |
| DB table | `audit_standards` |

The script accepts any casing for input — `audit_standard`, `AuditStandard`, and `audit-standard` all produce the same output.

---

## Key Differences from MatterMiner Generator

| Aspect | MatterMiner | AIAS |
|---|---|---|
| Model base | `BaseModel` | `Model` (Laravel's base) |
| Central model support | No (all tenant-scoped) | Yes (`--central` flag) |
| Tenant model connection | Custom `TenantConnection` | Stancl `TenantConnection` trait |
| Auditing | Not used | Central models use `Auditable` + `AuditableContract` |
| Test format | PHPUnit classes (`TestCase`) | Pest v3 (`it()` / `uses()` functions) |
| Test runner | `docs/scripts/test.sh` → `php artisan test` | `docs/scripts/test.sh` → `vendor/bin/pest` |
| Livewire | Generated (List, Create, Edit, View) | Not generated (API-only) |
| Collection class | `BaseResourceCollection` | Custom `ResourceCollection` subclass |
| Policy method | `can()` | `hasPermissionTo()` |
| Route model binding | Via `identifier` route key | String `$id` → manual repository lookup |
