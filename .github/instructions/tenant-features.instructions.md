---
description: "Use when creating tenant-scoped features, models, migrations, controllers, repositories, or routes. Covers multi-tenancy architecture with Stancl Tenancy, database isolation, and tenant filtering."
applyTo: ["app/Models/Tenant/**", "app/Repositories/Tenant/**", "app/Http/Controllers/Api/V1/Tenant/**", "app/Http/Requests/Tenant/**", "app/Http/Resources/Tenant/**", "app/Filters/Tenant/**", "database/migrations/tenant/**"]
---
# Tenant Feature Development

## Architecture
- Database isolation via Stancl Tenancy v3 — each tenant gets own MySQL DB (`aias_tenant_<id>_db`)
- Central DB holds: Users, Tenants, OAuth tokens, permissions, continents, countries
- Tenant DB holds: domain models (preambles, engagements, findings, etc.)
- Tenant context set by middleware — **never** manually switch databases in controllers
- `InitializeTenancyFromUser` middleware **required** on all tenant-scoped route groups

## Checklist — New Tenant Feature
1. Migration in `database/migrations/tenant/` — **NO FK constraints to central DB**
2. Model in `app/Models/Tenant/` extending `BaseModel` with `HasFactory`, `SoftDeletes`, `TenantConnection` — **NO `Auditable` trait**
3. Repository in `app/Repositories/Tenant/` with mandatory tenant filtering
4. Filters in `app/Filters/Tenant/{Domain}/` — main class + individual filter classes
5. Form Requests in `app/Http/Requests/Tenant/{Domain}/` — Create + Update
6. Resource/Collection in `app/Http/Resources/Tenant/{Domain}/` extending `BaseResource`
7. Policy in `app/Policies/` — `before()` for super-admin bypass + tenant boundary check
8. Register policy in `AppServiceProvider`
9. Controller in `app/Http/Controllers/Api/V1/Tenant/` using repository injection
10. Routes in `routes/api.php` with `auth:api` + `InitializeTenancyFromUser` middleware, named `api.{resource}.{action}`
11. Permissions in `config/permissions_map.php`

## Critical Rules
- **NEVER** create FK constraints from tenant tables → central tables
- **NEVER** add `Auditable` trait to tenant models — central only (User, Tenant)
- **ALWAYS** extend `BaseModel` — **NEVER** `Model` directly
- `created_by`/`updated_by` = `unsignedBigInteger()->nullable()` — **no FK constraint**
- `tenant_id` = plain string field, indexed, no FK
- Tenant-unique constraints: `unique(['tenant_id', 'field'])` — never global unique
- Tenant filtering: `where('tenant_id', auth()->user()->tenant_id)` for non-super-admin
- Super-admin bypasses via `before()` in Policy — return `true` unconditionally
- Use `identifier` (UUID) for route model binding, not `id`

## Migration Required Fields
```php
$table->id();
$table->uuid('identifier')->unique();
$table->string('tenant_id');                            // plain field — NO FK
$table->unsignedBigInteger('created_by')->nullable();  // NO FK constraint
$table->unsignedBigInteger('updated_by')->nullable();  // NO FK constraint
$table->timestamps();
$table->softDeletes();
$table->index('tenant_id');
$table->unique(['tenant_id', 'name']);                 // tenant-scoped unique
```

## Model Requirements
```php
namespace App\Models\Tenant;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class MyModel extends BaseModel  // ✅ BaseModel — NEVER Model
{
    use HasFactory, SoftDeletes, TenantConnection;  // ✅ no Auditable

    protected function casts(): array  // ✅ method — NOT $casts property
    {
        return ['status' => MyStatusEnum::class, ...];
    }

    public function getRouteKeyName(): string
    {
        return 'identifier';  // ✅ UUID — NOT id
    }
}
```
- `boot()` auto-populates via `BaseModel`: UUID `identifier`, `tenant_id`, `created_by`, `updated_by`

## Repository Requirements
```php
class MyModelRepository extends BaseRepository
{
    public function browseRecords(MyModelFilters $filters, int $page = 1, int $perPage = 20): Paginator
    {
        $query = $this->query()->with(['creator', 'updater']);

        if (!auth()->user()->hasRole('super-admin')) {
            $query->where('tenant_id', auth()->user()->tenant_id);  // ✅ mandatory
        }

        $filters->apply($query);
        return $query->paginate(perPage: min($perPage, 100), page: max($page, 1));
    }
}
```
- Extend `BaseRepository` — use `browse/read/insert/update/delete` base methods
- Inject other repositories (DRY) — never duplicate logic
- Load `['creator', 'updater']` on every read/create/update

## Transaction Pattern (Strictly Enforced)
```php
Gate::authorize('create', MyModel::class);    // 1. BEFORE transaction
$data = $request->validated();                // 2. BEFORE transaction
$record = DB::transaction(fn() =>            // 3. ONLY repository call inside
    $this->repository->createRecord($data)
);
Log::info('Created', ['id' => $record->id]); // 4. AFTER transaction
```

## Policy Requirements
```php
class MyModelPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin')) { return true; }  // ✅ unconditional bypass
        return null;
    }

    public function view(User $actor, MyModel $model): bool
    {
        return $actor->can('mymodels.view')                   // ✅ permission check
            && $actor->tenant_id === $model->tenant_id;       // ✅ tenant boundary
    }
}
```

## Filter Structure
```
app/Filters/Tenant/{Domain}/
├── {Domain}Filters.php          # main class — lists filter map
└── Filters/
    ├── SearchTermFilter.php     # single responsibility
    └── StatusFilter.php
```

## API Layer Requirements
- **Strictly default** envelope: `status`, `message`, `data`, optional `metadata`
- Always call `->setMessage()` and optionally `->addMetadata()` before returning
- Unique validation scoped to tenant: `Rule::unique('table')->where('tenant_id', auth()->user()->tenant_id)`
- No inline `$request->validate()` — always Form Request classes
- Routes named: `api.{resource}.{action}`

## Reference Implementations
- Model: [app/Models/Tenant/Preamble.php](../../app/Models/Tenant/Preamble.php)
- Repository: [app/Repositories/Tenant/PreambleRepository.php](../../app/Repositories/Tenant/PreambleRepository.php)
- Controller: [app/Http/Controllers/Api/V1/Tenant/PreambleController.php](../../app/Http/Controllers/Api/V1/Tenant/PreambleController.php)
