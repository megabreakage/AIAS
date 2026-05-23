# AIAS Feature File Structure References

Complete reference examples showing the exact file structure for each layer of the application. These are adapted from the MatterMiner codebase — **User** and **Continent** models for central database models, and **Department** and **Group** models for tenant-scoped database models.

---

## Table of Contents

1. [Central Database Model: User (Special Case)](#1-central-database-model-user-special-case)
2. [Central Database Model: Continent (Reference Data)](#2-central-database-model-continent-reference-data)
3. [Tenant-Scoped Database Model: Department (Full Feature)](#3-tenant-scoped-database-model-department-full-feature)
4. [Tenant-Scoped Database Model: Group (Minimal Feature)](#4-tenant-scoped-database-model-group-minimal-feature)
5. [Shared Base Classes](#5-shared-base-classes)
6. [Central Model Architecture Guide](#6-central-model-architecture-guide)

---

## 1. Central Database Model: User (Special Case)

The User model is a special central model — it uses `CentralConnection`, `Auditable`, `HasApiTokens`, `HasRoles`, and belongs to a tenant while being stored centrally.

### Model: `app/Models/User.php`

```php
<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\Permission\Traits\HasRoles;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class User extends Authenticatable implements AuditableContract, OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, CentralConnection, HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'identifier',
        'tenant_id',
        'title',
        'first_name',
        'last_name',
        'middle_name',
        'country_id',
        'phone',
        'email',
        'password',
        'secondary_email',
        'preferred_timezone',
        'office_location',
        'is_active',
        'status',
        'profile_photo',
        'notes',
        'last_login_at',
        'created_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'status' => \App\Enums\UserStatus::class,
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (User $user): void {
            if (empty($user->identifier)) {
                $user->identifier = Str::uuid()->toString();
            }
            if (! app()->runningInConsole() && Auth::check()) {
                $user->created_by = Auth::id();
            }
        });

        static::updating(function (User $user) {
            if (! app()->runningInConsole() && Auth::check()) {
                $user->updated_by = Auth::id();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
```

**Key Traits for User Model:**

- `CentralConnection` — ensures model uses central database
- `Auditable` — full audit trail (central models only)
- `HasApiTokens` — Passport OAuth
- `HasRoles` — Spatie Permission
- `SoftDeletes` — soft delete support

---

## 2. Central Database Model: Continent (Reference Data)

Continent is a global reference data model. It resides in the central database and is shared across all tenants. No `tenant_id`, no `TenantConnection`.

### Model: `app/Models/Continent.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Continent extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'central';

    protected $fillable = [
        'identifier',
        'name',
        'slug',
        'short_code',
        'iso_code',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Continent $continent) {
            $continent->identifier = (string) Str::uuid();

            if (is_null($continent->created_by) && \Illuminate\Support\Facades\Auth::check()) {
                $continent->created_by = \Illuminate\Support\Facades\Auth::id();
            }
        });

        static::updating(function (Continent $continent) {
            if (\Illuminate\Support\Facades\Auth::check()) {
                $continent->updated_by = \Illuminate\Support\Facades\Auth::id();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function countries(): HasMany
    {
        return $this->hasMany(Country::class);
    }
}
```

### Repository: `app/Repositories/ContinentRepository.php`

```php
<?php

namespace App\Repositories;

use App\Filters\Continents\ContinentFilters;
use App\Models\Continent;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;

class ContinentRepository extends BaseRepository
{
    protected array $dispatchesEvents = [];

    public function getClassName(): Model|string
    {
        return Continent::class;
    }

    public function browseContinents(
        ContinentFilters $filters,
        int $page = 1,
        int $perPage = 20,
        ?string $sortBy = null,
        bool $sortDesc = false
    ): Paginator {
        $query = $this->query()->with(['creator', 'updater']);

        // No tenant filtering — central reference data
        $filters->apply($query);

        if ($sortBy) {
            $query->orderBy($sortBy, $sortDesc ? 'desc' : 'asc');
        } else {
            $query->orderBy('name', 'asc');
        }

        return $query->paginate(perPage: min($perPage, 100), page: max($page, 1));
    }

    public function readContinent(int|string $id, array $with = []): Model
    {
        return $this->read($id, array_merge($with, ['creator', 'updater']));
    }

    public function createContinent(array $data): Continent|Model|bool
    {
        $continent = self::make($data);
        if ($continent->save()) {
            return $continent->load(['creator', 'updater']);
        }
        return false;
    }
}
```

### Filters: `app/Filters/Continents/`

**ContinentFilters.php:**

```php
<?php

namespace App\Filters\Continents;

use App\Filters\Continents\Filters\SearchTermFilter;
use App\Filters\EloquentFilter;

class ContinentFilters extends EloquentFilter
{
    protected array $filters = [
        'search' => SearchTermFilter::class,
    ];
}
```

**Filters/SearchTermFilter.php:**

```php
<?php

namespace App\Filters\Continents\Filters;

use App\Filters\EloquentFilter;
use Illuminate\Database\Eloquent\Builder;

class SearchTermFilter extends EloquentFilter
{
    public function __construct(
        protected string $search
    ) {}

    public function apply(Builder $query): Builder
    {
        $search = trim($this->search);

        return $query->where(function (Builder $q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%")
                ->orWhere('short_code', 'like', "%{$search}%")
                ->orWhere('iso_code', 'like', "%{$search}%");
        });
    }
}
```

### Resource: `app/Http/Resources/Continent/ContinentResource.php`

```php
<?php

namespace App\Http\Resources\Continent;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class ContinentResource extends BaseResource
{
    public function resourceData(Request $request): array
    {
        return [
            'id' => $this->id,
            'identifier' => $this->identifier,
            'name' => $this->name,
            'slug' => $this->slug,
            'short_code' => $this->short_code,
            'iso_code' => $this->iso_code,
            'status' => $this->status,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
```

### Collection: `app/Http/Resources/Continent/ContinentCollection.php`

```php
<?php

namespace App\Http\Resources\Continent;

use App\Http\Resources\BaseResourceCollection;

class ContinentCollection extends BaseResourceCollection
{
    public $collects = ContinentResource::class;
}
```

### Form Requests

**CreateContinentRequest.php:**

```php
<?php

namespace App\Http\Requests\Continent;

use Illuminate\Foundation\Http\FormRequest;

class CreateContinentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by policy
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'min:2', 'unique:central.continents,name'],
            'slug' => ['nullable', 'string', 'max:255'],
            'short_code' => ['nullable', 'string', 'max:10'],
            'iso_code' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The continent name is required.',
            'name.max' => 'The continent name must not exceed 255 characters.',
            'name.min' => 'The continent name must be at least 2 characters.',
            'name.unique' => 'This continent name is already in use.',
        ];
    }
}
```

**UpdateContinentRequest.php:**

```php
<?php

namespace App\Http\Requests\Continent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContinentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by policy
    }

    public function rules(): array
    {
        $continentId = $this->route('continent');

        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:255', 'min:2',
                Rule::unique('central.continents', 'name')->ignore($continentId),
            ],
            'slug' => ['nullable', 'string', 'max:255'],
            'short_code' => ['nullable', 'string', 'max:10'],
            'iso_code' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The continent name is required.',
            'name.unique' => 'This continent name is already in use.',
        ];
    }
}
```

### Policy: `app/Policies/ContinentPolicy.php`

```php
<?php

namespace App\Policies;

use App\Models\Continent;
use App\Models\User;

class ContinentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }
        return null;
    }

    public function viewAny(User $actor): bool
    {
        return $actor->can('continents.view');
    }

    public function view(User $actor, Continent $continent): bool
    {
        return $actor->can('continents.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('continents.create');
    }

    public function update(User $actor, Continent $continent): bool
    {
        return $actor->can('continents.edit');
    }

    public function delete(User $actor, Continent $continent): bool
    {
        return $actor->can('continents.delete');
    }

    public function restore(User $actor, Continent $continent): bool
    {
        return $actor->can('continents.delete');
    }
}
```

### Controller: `app/Http/Controllers/ContinentController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Filters\Continents\ContinentFilters;
use App\Http\Requests\Continent\CreateContinentRequest;
use App\Http\Requests\Continent\UpdateContinentRequest;
use App\Http\Resources\Continent\ContinentCollection;
use App\Http\Resources\Continent\ContinentResource;
use App\Models\Continent;
use App\Repositories\ContinentRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ContinentController extends Controller
{
    public function __construct(
        protected ContinentRepository $repository
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Continent::class);

        try {
            $filters = ContinentFilters::fromRequest($request);

            $continents = $this->repository->browseContinents(
                filters: $filters,
                page: $request->integer('page', 1),
                perPage: $request->integer('per_page', 15),
                sortBy: $request->input('sort_by'),
                sortDesc: $request->input('sort_order') === 'desc'
            );

            return (new ContinentCollection($continents))
                ->setMessage('Continents retrieved successfully')
                ->addMetadata('filters_applied', $request->only(['search', 'sort_by', 'sort_order']))
                ->response()
                ->setStatusCode(Response::HTTP_OK);

        } catch (\Throwable $e) {
            Log::error('Failed to retrieve continents list', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve continents.',
                'data' => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(CreateContinentRequest $request): JsonResponse
    {
        Gate::authorize('create', Continent::class);

        try {
            $data = $request->validated();

            Log::info('Creating new continent', ['name' => $data['name']]);

            $continent = DB::transaction(function () use ($data) {
                return $this->repository->createContinent($data);
            });

            Log::info('Continent created successfully', ['continent_id' => $continent->id]);

            return (new ContinentResource($continent))
                ->setMessage('Continent created successfully')
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);

        } catch (\Throwable $e) {
            Log::error('Failed to create continent', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create continent.',
                'data' => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $continent = $this->repository->readContinent($id);
            Gate::authorize('view', $continent);

            return (new ContinentResource($continent))
                ->setMessage('Continent retrieved successfully')
                ->response()
                ->setStatusCode(Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Continent not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }
    }

    public function update(UpdateContinentRequest $request, string $id): JsonResponse
    {
        try {
            $continent = $this->repository->readContinent($id);
            Gate::authorize('update', $continent);

            $data = $request->validated();

            Log::info('Updating continent', ['continent_id' => $id]);

            $continent = DB::transaction(function () use ($id, $data) {
                return $this->repository->updateContinent($id, $data);
            });

            Log::info('Continent updated successfully', ['continent_id' => $continent->id]);

            return (new ContinentResource($continent))
                ->setMessage('Continent updated successfully')
                ->response()
                ->setStatusCode(Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Continent not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        } catch (\Throwable $e) {
            Log::error('Failed to update continent', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update continent.',
                'data' => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $continent = $this->repository->readContinent($id);
            Gate::authorize('delete', $continent);

            Log::info('Deleting continent', ['continent_id' => $id]);

            DB::transaction(function () use ($id) {
                $this->repository->delete($id);
            });

            Log::info('Continent deleted successfully', ['continent_id' => $id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Continent deleted successfully',
                'data' => null,
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Continent not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        } catch (\Throwable $e) {
            Log::error('Failed to delete continent', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete continent.',
                'data' => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
```

### File Structure Summary — Central Model (Continent)

```
app/
├── Models/
│   └── Continent.php                              # Model: $connection = 'central', SoftDeletes, HasFactory
├── Repositories/
│   └── ContinentRepository.php                    # No tenant filtering, role-based access
├── Filters/
│   └── Continents/
│       ├── ContinentFilters.php                   # Filter registry
│       └── Filters/
│           └── SearchTermFilter.php               # Individual filter
├── Http/
│   ├── Controllers/
│   │   └── ContinentController.php                # CRUD controller with repository
│   ├── Requests/
│   │   └── Continent/
│   │       ├── CreateContinentRequest.php          # Create validation
│   │       └── UpdateContinentRequest.php          # Update validation
│   └── Resources/
│       └── Continent/
│           ├── ContinentResource.php              # Single resource
│           └── ContinentCollection.php            # Collection resource
├── Policies/
│   └── ContinentPolicy.php                        # Permission-based authorization
database/
├── migrations/
│   └── xxxx_create_continents_table.php           # Central migration (NOT in tenant/)
└── factories/
    └── ContinentFactory.php                       # Test factory
```

---

## 3. Tenant-Scoped Database Model: Department (Full Feature)

Department is a tenant-scoped model with full CRUD, members relationship, and comprehensive validation.

### Model: `app/Models/Department.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class Department extends Model
{
    use HasFactory, SoftDeletes, TenantConnection;

    protected $fillable = [
        'identifier',
        'tenant_id',
        'name',
        'slug',
        'description',
        'head_of_department',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $hidden = ['users'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Department $department) {
            if (is_null($department->identifier)) {
                $department->identifier = (string) Str::uuid();
            }

            if (tenancy()->tenant) {
                $department->tenant_id = tenancy()->tenant->getTenantKey();

                if (empty($department->slug)) {
                    $department->slug = Str::slug($department->name);
                }

                if (is_null($department->created_by) && auth()->check()) {
                    $department->created_by = auth()->id();
                }
            }
        });

        static::updating(function (Department $department) {
            if ($department->isDirty('name') && ! $department->isDirty('slug')) {
                $department->slug = Str::slug($department->name);
            }

            if (auth()->check()) {
                $department->updated_by = auth()->id();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function headOfDepartment(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_of_department');
    }

    public function members(): HasMany
    {
        return $this->hasMany(DepartmentUser::class);
    }
}
```

### Repository: `app/Repositories/DepartmentRepository.php`

```php
<?php

namespace App\Repositories;

use App\Filters\Departments\DepartmentFilters;
use App\Models\Department;
use App\Models\DepartmentUser;
use App\Models\User;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class DepartmentRepository extends BaseRepository
{
    protected array $dispatchesEvents = [];

    public function getClassName(): Model|string
    {
        return Department::class;
    }

    public function browseDepartments(
        DepartmentFilters $filters,
        int $page = 1,
        int $perPage = 20,
        ?string $sortBy = null,
        bool $sortDesc = false
    ): Paginator {
        $query = $this->query()->withCount('members')->with(['members']);

        // Mandatory tenant filtering for non-super-admin users
        if (! auth()->user()->hasRole('super-admin')) {
            $query->where('tenant_id', auth()->user()->tenant_id);
        }

        $filters->apply($query);

        if ($sortBy) {
            $query->orderBy($sortBy, $sortDesc ? 'desc' : 'asc');
        }

        $paginator = $query->paginate(
            perPage: min($perPage, 100),
            page: max($page, 1)
        );

        return $paginator;
    }

    public function readDepartment(int|string $id, array $with = []): Model
    {
        $department = $this->read($id, array_merge($with, ['members']));
        return $department;
    }

    public function createDepartment(array $data): Department|Model|bool
    {
        $department = self::make($data);
        if ($department->save()) {
            return $department;
        }
        return false;
    }

    public function updateDepartment(int|string $id, array $data): Department|Model|bool
    {
        return $this->update($id, $data);
    }

    public function deleteDepartment(Department|int|string $department): bool
    {
        if ($department instanceof Department) {
            return $department->delete();
        }
        return $this->delete($department);
    }
}
```

### Filters: `app/Filters/Departments/`

**DepartmentFilters.php:**

```php
<?php

namespace App\Filters\Departments;

use App\Filters\Departments\Filters\IsActiveFilter;
use App\Filters\Departments\Filters\SearchTermFilter;
use App\Filters\EloquentFilter;

class DepartmentFilters extends EloquentFilter
{
    protected array $filters = [
        'search' => SearchTermFilter::class,
        'is_active' => IsActiveFilter::class,
    ];
}
```

**Filters/SearchTermFilter.php:**

```php
<?php

namespace App\Filters\Departments\Filters;

use App\Filters\EloquentFilter;
use Illuminate\Database\Eloquent\Builder;

class SearchTermFilter extends EloquentFilter
{
    public function __construct(
        protected string $search
    ) {}

    public function apply(Builder $query): Builder
    {
        $search = trim($this->search);

        return $query->where(function (Builder $q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%");
        });
    }
}
```

### Resource: `app/Http/Resources/Department/DepartmentResource.php`

```php
<?php

namespace App\Http\Resources\Department;

use App\Http\Resources\BaseResource;
use App\Http\Resources\User\UserResource;
use Illuminate\Http\Request;

class DepartmentResource extends BaseResource
{
    protected function resourceData(Request $request): array
    {
        return [
            'id' => $this->id,
            'identifier' => $this->identifier,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'head_of_department' => $this->head_of_department,
            'is_active' => $this->is_active,
            'members_count' => $this->members_count ?? $this->members()->count(),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

### Collection: `app/Http/Resources/Department/DepartmentCollection.php`

```php
<?php

namespace App\Http\Resources\Department;

use App\Http\Resources\BaseResourceCollection;

class DepartmentCollection extends BaseResourceCollection
{
    public $collects = DepartmentResource::class;
}
```

### Form Requests

**CreateDepartmentRequest.php:**

```php
<?php

namespace App\Http\Requests\Department;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // handled by policies
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('departments', 'name')
                    ->where('tenant_id', auth()->user()->tenant_id),
            ],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('departments', 'slug')],
            'description' => ['nullable', 'string', 'max:2000'],
            'head_of_department' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'members' => ['nullable', 'array'],
            'members.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The department name is required.',
            'name.unique' => 'A department with this name already exists for your organization.',
        ];
    }
}
```

**UpdateDepartmentRequest.php:**

```php
<?php

namespace App\Http\Requests\Department;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // handled by policies
    }

    public function rules(): array
    {
        $departmentId = $this->route('department');

        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('departments', 'name')
                    ->where('tenant_id', auth()->user()->tenant_id)
                    ->ignore($departmentId),
            ],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('departments', 'slug')->ignore($departmentId)],
            'description' => ['nullable', 'string', 'max:2000'],
            'head_of_department' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'members' => ['nullable', 'array'],
            'members.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A department with this name already exists for your organization.',
        ];
    }
}
```

### Policy: `app/Policies/DepartmentPolicy.php`

```php
<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }
        return null;
    }

    public function viewAny(User $actor): bool
    {
        return $actor->can('hr-departments.view');
    }

    public function view(User $actor, Department $department): bool
    {
        return $actor->can('hr-departments.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('hr-departments.create');
    }

    public function update(User $actor, Department $department): bool
    {
        return $actor->can('hr-departments.edit');
    }

    public function delete(User $actor, Department $department): bool
    {
        return $actor->can('hr-departments.delete');
    }
}
```

### Controller: `app/Http/Controllers/DepartmentController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Filters\Departments\DepartmentFilters;
use App\Http\Requests\Department\CreateDepartmentRequest;
use App\Http\Requests\Department\UpdateDepartmentRequest;
use App\Http\Resources\Department\DepartmentCollection;
use App\Http\Resources\Department\DepartmentResource;
use App\Models\Department;
use App\Repositories\DepartmentRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class DepartmentController extends Controller
{
    public function __construct(
        protected DepartmentRepository $repository
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Department::class);

        try {
            $filters = DepartmentFilters::fromRequest($request);

            $departments = $this->repository->browseDepartments(
                filters: $filters,
                page: $request->integer('page', 1),
                perPage: $request->integer('per_page', 20),
                sortBy: $request->input('sort_by'),
                sortDesc: $request->boolean('sort_desc')
            );

            return (new DepartmentCollection($departments))
                ->setMessage('Departments retrieved successfully')
                ->addMetadata('filters_applied', $request->only(['search', 'is_active']))
                ->response()
                ->setStatusCode(Response::HTTP_OK);

        } catch (\Throwable $e) {
            Log::error('Failed to retrieve Departments list', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve departments.',
                'data' => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(CreateDepartmentRequest $request): JsonResponse
    {
        Gate::authorize('create', Department::class);

        try {
            $data = $request->validated();

            Log::info('Creating department', ['name' => $data['name']]);

            $department = DB::transaction(function () use ($data) {
                return $this->repository->createDepartment($data);
            });

            Log::info('Department created', ['id' => $department->id]);

            return (new DepartmentResource($department))
                ->setMessage('Department created successfully')
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);

        } catch (\Throwable $e) {
            Log::error('Failed to create department', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create department.',
                'data' => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(string|int $department): JsonResponse
    {
        try {
            $department = $this->repository->readDepartment($department, ['members']);
            Gate::authorize('view', $department);

            return (new DepartmentResource($department))
                ->setMessage('Department retrieved successfully')
                ->response()
                ->setStatusCode(Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Department not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }
    }

    public function update(UpdateDepartmentRequest $request, string|int $department): JsonResponse
    {
        try {
            $departmentModel = $this->repository->readDepartment($department);
            Gate::authorize('update', $departmentModel);

            $data = $request->validated();

            Log::info('Updating department', ['id' => $department]);

            $department = DB::transaction(function () use ($department, $data) {
                return $this->repository->updateDepartment($department, $data);
            });

            Log::info('Department updated', ['id' => $department->id]);

            return (new DepartmentResource($department))
                ->setMessage('Department updated successfully')
                ->response()
                ->setStatusCode(Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Department not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        } catch (\Throwable $e) {
            Log::error('Failed to update department', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update department.',
                'data' => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(string|int $department): JsonResponse
    {
        try {
            $departmentModel = $this->repository->readDepartment($department);
            Gate::authorize('delete', $departmentModel);

            if ($departmentModel->members()->count() > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Department cannot be deleted as it has assigned members.',
                    'data' => null,
                ], Response::HTTP_BAD_REQUEST);
            }

            Log::info('Deleting department', ['id' => $department]);

            DB::transaction(function () use ($departmentModel) {
                $this->repository->deleteDepartment($departmentModel);
            });

            Log::info('Department deleted', ['id' => $department]);

            return response()->json([
                'status' => 'success',
                'message' => 'Department deleted successfully',
                'data' => null,
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Department not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        } catch (\Throwable $e) {
            Log::error('Failed to delete department', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete department.',
                'data' => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
```

### File Structure Summary — Tenant Model (Department)

```
app/
├── Models/
│   └── Department.php                             # Model: TenantConnection, SoftDeletes, HasFactory
├── Repositories/
│   └── DepartmentRepository.php                   # Mandatory tenant filtering in browse()
├── Filters/
│   └── Departments/
│       ├── DepartmentFilters.php                  # Filter registry (search, is_active)
│       └── Filters/
│           ├── SearchTermFilter.php               # Search filter
│           └── IsActiveFilter.php                 # Status filter
├── Http/
│   ├── Controllers/
│   │   └── DepartmentController.php               # CRUD controller with repository
│   ├── Requests/
│   │   └── Department/
│   │       ├── CreateDepartmentRequest.php         # Create validation (tenant-scoped unique)
│   │       └── UpdateDepartmentRequest.php         # Update validation (tenant-scoped unique)
│   └── Resources/
│       └── Department/
│           ├── DepartmentResource.php             # Single resource (includes tenant_id)
│           └── DepartmentCollection.php           # Collection resource
├── Policies/
│   └── DepartmentPolicy.php                       # Permission-based authorization
database/
├── migrations/
│   └── tenant/
│       └── xxxx_create_departments_table.php      # Tenant migration (IN tenant/ directory)
└── factories/
    └── DepartmentFactory.php                      # Test factory
```

---

## 4. Tenant-Scoped Database Model: Group (Minimal Feature)

Group is a minimal tenant-scoped model — demonstrates the simplest possible tenant feature.

### Model: `app/Models/Group.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class Group extends Model
{
    use SoftDeletes, TenantConnection;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'created_by',
    ];

    protected $hidden = ['users'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public static function boot(): void
    {
        parent::boot();

        static::creating(function (Group $group) {
            if (tenancy()->tenant) {
                $group->tenant_id = tenancy()->tenant->getTenantKey();

                if (is_null($group->created_by) && ! app()->runningInConsole()) {
                    $group->created_by = auth()->id();
                }
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }
}
```

**Key Differences from Department:**

- No `HasFactory` trait (simpler model)
- No `identifier` UUID field
- No `updated_by` tracking
- No slug auto-generation
- Minimal fillable fields
- Still uses `TenantConnection` and `SoftDeletes`
- Still auto-sets `tenant_id` from tenancy context

---

## 5. Shared Base Classes

### BaseResource: `app/Http/Resources/BaseResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class BaseResource extends JsonResource
{
    protected string $response_status = 'success';
    protected ?string $message = null;
    protected array $metadata = [];

    public function setStatus(string $status): self
    {
        $this->response_status = $status;
        return $this;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function addMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    public function toArray(Request $request): array
    {
        return $this->resourceData($request);
    }

    public function with(Request $request): array
    {
        return array_merge([
            'status' => $this->response_status,
            'message' => $this->message,
        ], $this->metadata);
    }

    abstract protected function resourceData(Request $request): array;
}
```

### BaseRepository: `app/Repositories/BaseRepository.php`

```php
<?php

namespace App\Repositories;

use App\Filters\EloquentFilter;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

abstract class BaseRepository
{
    protected ?Model $model = null;
    protected array $dispatchesEvents = [];

    abstract public function getClassName(): Model|string;

    public function model(): ?Model
    {
        if ($this->model === null) {
            $this->model = new ($this->getClassName());
        }
        return $this->model;
    }

    public function query(): Builder
    {
        return $this->getClassName()::query();
    }

    protected function getModel(Model|int|string $id, bool $fail = true): Model|Collection|null
    {
        if ($id instanceof Model) {
            return $id;
        } elseif (is_int($id) || is_string($id)) {
            $model = $this->query()->find($id);
            if ($fail && ! $model) {
                throw new NotFoundHttpException(__('Not found'));
            }
            return $model;
        }
        throw new \Exception('Invalid id value passed to findOrAbort');
    }

    public function browse(EloquentFilter $filters, int $page = 1, int $perPage = 20, ?string $sortBy = null, bool $sortDesc = false): Paginator
    {
        $query = $this->query();
        $filters->apply($query);
        if ($sortBy) {
            $query->orderBy($sortBy, $sortDesc ? 'desc' : 'asc');
        }
        return $query->paginate(perPage: min($perPage, 100), page: max($page, 1));
    }

    public function read(int|string $id, array $with = []): Model
    {
        return $this->query()->with($with)->findOrFail($id);
    }

    public static function make($values): mixed
    {
        $class = app(static::class)->getClassName();
        return new $class($values);
    }

    public function insert(array $data): Model|bool
    {
        $model = self::make($data);
        if ($model->save()) {
            $this->dispatch('inserted', $model);
            return $model;
        }
        return false;
    }

    public function update($id, array $data): Model|bool
    {
        $model = $this->getModel($id);
        $model->fill($data);
        if ($model->save()) {
            $this->dispatch('updated', $model);
            return $model;
        }
        return false;
    }

    public function delete($id): bool
    {
        $model = $this->getModel($id);
        $this->dispatch('deleted', $model);
        return $model->delete();
    }

    public function dispatch($event, $model): void
    {
        if (isset($this->dispatchesEvents[$event])) {
            $event = $this->dispatchesEvents[$event];
            if (class_exists($event)) {
                event(new $event($model));
            }
        }
    }

    public static function singleton(): static
    {
        return app(static::class);
    }
}
```

### EloquentFilter: `app/Filters/EloquentFilter.php`

```php
<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

abstract class EloquentFilter
{
    protected array $filters = [];
    protected array $active = [];

    public function __construct(array $params = [])
    {
        foreach ($this->filters as $key => $filterClass) {
            if (! array_key_exists($key, $params) || $params[$key] === null || $params[$key] === '') {
                continue;
            }
            $this->active[] = new $filterClass($params[$key]);
        }
    }

    public static function fromRequest(Request $request): static
    {
        $instance = new static;
        foreach ($instance->filters as $key => $filterClass) {
            if (! $request->filled($key)) {
                continue;
            }
            $instance->active[] = new $filterClass($request->input($key));
        }
        return $instance;
    }

    public function apply(Builder $query): Builder
    {
        foreach ($this->active as $filter) {
            $filter->apply($query);
        }
        return $query;
    }
}
```

---

## 6. Central Model Architecture Guide

For a comprehensive guide to creating central database models, refer to the [Central Model Architecture](../../architecture/central-model-architecture.md) document which covers:

- Step-by-step implementation (migration, model, repository, filters, requests, policy, resources, controller, factory, seeder, routes, permissions)
- Complete working example using a `SystemSetting` model
- Best practices for database design, security, performance, testing, and documentation
- Common patterns (settings, reference data, user management)
- Testing structure with PHPUnit and `RefreshDatabaseWithTenancy`

---

## Quick Reference: Central vs Tenant Feature Differences

| Component | Central Model | Tenant Model |
|-----------|---------------|--------------|
| **Model trait** | `$connection = 'central'` or `CentralConnection` | `TenantConnection` |
| **Auditable** | YES | NO |
| **tenant_id** | NO | YES (auto-set in `creating`) |
| **Migration location** | `database/migrations/` | `database/migrations/tenant/` |
| **Repository filtering** | No tenant filter | `where('tenant_id', auth()->user()->tenant_id)` |
| **Unique validation** | `unique:central.table_name,column` | `Rule::unique('table', 'col')->where('tenant_id', ...)` |
| **FK constraints** | Standard FK to central tables OK | NEVER FK to central database |
| **Policy pattern** | Permission-based only | Permission-based (tenant isolation via repository) |
