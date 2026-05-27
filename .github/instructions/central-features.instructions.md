---
description: "Use when creating central (non-tenant-scoped) features, models, migrations, controllers, or repositories. Covers global reference data, user management, and shared system entities in the central database."
applyTo: ["app/Models/Central/**", "app/Repositories/Central/**", "app/Http/Controllers/Api/V1/Central/**", "app/Http/Requests/Central/**", "app/Http/Resources/Central/**", "app/Filters/Central/**", "database/migrations/*.php"]
---
# Central Feature Development

## Architecture
- Central DB holds: Users, Tenants, OAuth tokens, Permissions, Continents, Countries, system-wide config
- Central models are **shared across all tenants** — no `tenant_id`, no `TenantConnection`
- `Auditable` trait **allowed** and encouraged on central models
- No `InitializeTenancyFromUser` middleware — central routes require only `auth:api`
- Access control is role/permission-based (not tenant-scoped)

## Checklist — New Central Feature
1. Migration in `database/migrations/` (NOT `database/migrations/tenant/`)
2. Model in `app/Models/Central/` with `protected $connection = 'central'` + `Auditable` + `SoftDeletes`
3. Repository in `app/Repositories/Central/` with role-based filtering (no tenant filter)
4. Filters in `app/Filters/Central/{Domain}/` — main class + individual filter classes
5. Form Requests in `app/Http/Requests/Central/{Domain}/` — Create + Update
6. Resource/Collection in `app/Http/Resources/Central/{Domain}/` extending `BaseResource`
7. Policy in `app/Policies/` — `before()` super-admin bypass + permission check
8. Register policy in `AppServiceProvider`
9. Controller in `app/Http/Controllers/Api/V1/Central/` using repository injection
10. Routes in `routes/api.php` under `auth:api` middleware, named `api.{resource}.{action}`
11. Permissions in `config/permissions_map.php`

## Critical Rules
- **ALWAYS** set `protected $connection = 'central'`
- **ALWAYS** implement `AuditableContract` and use `Auditable` trait
- **NEVER** add `TenantConnection` trait — that's for tenant models only
- **NEVER** add `tenant_id` field to central models
- FK constraints to `users` table **allowed** from central models
- `created_by`/`updated_by` reference central users — FK constraint acceptable
- Use `identifier` (UUID) for route model binding

## Migration Pattern
```php
// Location: database/migrations/ (NOT database/migrations/tenant/)
Schema::create('continents', function (Blueprint $table) {
    $table->id();
    $table->uuid('identifier')->unique();
    $table->string('name');
    $table->string('code', 10)->unique();
    $table->boolean('is_active')->default(true);
    $table->unsignedBigInteger('created_by')->nullable();
    $table->unsignedBigInteger('updated_by')->nullable();
    $table->timestamps();
    $table->softDeletes();
    $table->index('code');
});
```

## Model Pattern
```php
namespace App\Models\Central;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Continent extends BaseModel implements AuditableContract
{
    use Auditable, HasFactory, SoftDeletes;  // ✅ Auditable allowed on central models

    protected $connection = 'central';       // ✅ explicit central connection

    protected function casts(): array        // ✅ method — NOT $casts property
    {
        return ['is_active' => 'boolean', ...];
    }

    public function getRouteKeyName(): string
    {
        return 'identifier';
    }
}
```

## Repository Pattern
```php
class ContinentRepository extends BaseRepository
{
    public function browseContinents(ContinentFilters $filters, int $page = 1, int $perPage = 20): Paginator
    {
        $query = $this->query()->with(['creator', 'updater']);
        // No tenant filtering — central models are globally accessible
        // Role-based access control handled by Policy
        $filters->apply($query);
        return $query->paginate(perPage: min($perPage, 100), page: max($page, 1));
    }
}
```

## Policy Pattern (No Tenant Boundary)
```php
class ContinentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin')) { return true; }
        return null;
    }

    public function viewAny(User $actor): bool  { return $actor->can('continents.view'); }
    public function view(User $actor, Continent $continent): bool  { return $actor->can('continents.view'); }
    public function create(User $actor): bool  { return $actor->can('continents.create'); }
    public function update(User $actor, Continent $continent): bool  { return $actor->can('continents.edit'); }
    public function delete(User $actor, Continent $continent): bool  { return $actor->can('continents.delete'); }
    // ✅ NO tenant boundary check — central models are global
}
```

## Transaction Pattern (Same as Tenant)
```php
Gate::authorize('create', Continent::class);    // 1. BEFORE transaction
$data = $request->validated();                  // 2. BEFORE transaction
$record = DB::transaction(fn() =>              // 3. ONLY repository call inside
    $this->repository->createContinent($data)
);
Log::info('Created continent', ['id' => $record->id]);  // 4. AFTER transaction
```

## Filter Structure
```
app/Filters/Central/{Domain}/
├── {Domain}Filters.php          # main class — lists filter map
└── Filters/
    ├── SearchTermFilter.php
    └── IsActiveFilter.php
```

## API Layer Requirements
- **Strictly default** envelope: `status`, `message`, `data`, optional `metadata`
- Always call `->setMessage()` and optionally `->addMetadata()` before returning
- Routes named: `api.{resource}.{action}`

## Reference Implementations
- Model: [app/Models/Central/Continent.php](../../app/Models/Central/Continent.php)
- Repository: [app/Repositories/Central/ContinentRepository.php](../../app/Repositories/Central/ContinentRepository.php)
- Controller: [app/Http/Controllers/Api/V1/Central/ContinentController.php](../../app/Http/Controllers/Api/V1/Central/ContinentController.php)
