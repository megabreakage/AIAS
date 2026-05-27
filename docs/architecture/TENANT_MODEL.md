# Tenant Model Architecture Guide

**AIAS Multi-Tenant Laravel API**

This document defines the complete architecture for creating fully featured **tenant-scoped database models** in the AIAS system. Use `PracticeArea` as the canonical reference implementation throughout.

## Overview

Tenant models are entities scoped to a single tenant, residing in isolated per-tenant databases. They include:

- **Case management**: Matters, practice areas, case stages
- **Client data**: Contacts, clients, client groups
- **Configuration**: Numbering types, trust account types, billing rates
- **Operational data**: Tasks, time entries, notes, documents

## Key Architectural Principles

### Central vs Tenant Models Comparison

| Aspect | Central Models | Tenant Models |
|--------|----------------|---------------|
| **Database** | `central` connection | Tenant DB via `TenantConnection` trait |
| **Connection** | `protected $connection = 'central'` | No explicit connection — `TenantConnection` handles it |
| **Auditing** | ✅ **YES** — `Auditable` trait | ❌ **NO** — avoid cross-database complexity |
| **Tenant ID** | ❌ **NO** — global data | ✅ **YES** — required for isolation |
| **Repository Filtering** | Role/permission-based | **Mandatory** tenant filtering for non-super-admin |
| **Middleware** | None | `InitializeTenancyFromUser` required |
| **Migration Location** | `database/migrations/` | `database/migrations/tenant/` |
| **Policy Checks** | Permission only | Permission **+** tenant boundary check |
| **Shared Across** | All tenants | Single tenant only |
| **Examples** | Users, Countries, Settings | Contacts, Clients, PracticeAreas |

### Security Boundaries

- **Database isolation**: Each tenant has its own MySQL database (`aias_tenant_<id>_db`)
- **Mandatory tenant filtering**: Non-super-admin repository queries always filter by `tenant_id`
- **No cross-database FK constraints**: `created_by`/`updated_by` are plain integer fields — no foreign keys to central DB
- **Authorization layers**: Policy (Gate) → Repository (tenant filter) → Model (relationships)
- **Soft deletes**: All tenant models use soft deletes for data recovery

## Step-by-Step Implementation

### 1. Migration Structure

**Location**: `database/migrations/tenant/` (NOT `database/migrations/`)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practice_areas', function (Blueprint $table) {
            $table->id();
            $table->uuid('identifier')->unique();

            // Tenant scoping — REQUIRED on all tenant models
            $table->string('tenant_id')->index();

            // Business fields
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();

            // Creator tracking — NO FK constraints to central DB
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            // Standard Laravel fields
            $table->timestamps();
            $table->softDeletes();

            // Tenant-scoped unique constraints
            $table->unique(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_areas');
    }
};
```

**Key Migration Patterns:**

- **`tenant_id` field required** — string type, indexed, not a FK constraint
- **UUID identifier** — for external API references and route binding
- **No FK constraints** — `created_by`/`updated_by` are plain `unsignedBigInteger` fields
- **Tenant-scoped unique constraints** — `unique(['tenant_id', 'field'])` not global unique
- **Soft deletes** — always include `softDeletes()`

### 2. Model Structure

**Location**: `app/Models/PracticeArea.php`

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

/**
 * PracticeArea model — Tenant-scoped database entity.
 *
 * Practice areas belong to a single tenant and are isolated in the tenant's
 * own database. This model uses TenantConnection to automatically connect
 * to the correct tenant database. It does NOT use the Auditable trait.
 */
class PracticeArea extends Model
{
    use HasFactory, SoftDeletes, TenantConnection; // ✅ TenantConnection — NOT 'central' connection

    // ❌ DO NOT set: protected $connection = 'central';
    // ❌ DO NOT use: Auditable trait on tenant models

    protected $fillable = [
        'identifier',
        'tenant_id',
        'name',
        'code',
        'description',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'identifier';
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (PracticeArea $practiceArea) {
            if (is_null($practiceArea->identifier)) {
                $practiceArea->identifier = (string) Str::uuid();
            }

            // Auto-set tenant_id and created_by from tenancy context
            if (tenancy()->tenant) {
                $practiceArea->tenant_id = tenancy()->tenant->getTenantKey();

                if (is_null($practiceArea->created_by) && auth()->check()) {
                    $practiceArea->created_by = auth()->id();
                }
            }
        });

        static::updating(function (PracticeArea $practiceArea) {
            if (auth()->check()) {
                $practiceArea->updated_by = auth()->id();
            }
        });
    }

    /**
     * Relationship: The tenant this practice area belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relationship: Practice area creator.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship: Practice area last updater.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
```

**Key Model Patterns:**

- **`TenantConnection` trait** — routes the model to the correct tenant DB automatically
- **No explicit connection** — do not set `protected $connection = 'central'`
- **No `Auditable` trait** — only central models (User, Tenant) use auditing
- **`boot()` auto-populates** — `identifier` (UUID), `tenant_id`, `created_by`, `updated_by`
- **Route key uses `identifier`** — UUID for external API references, not `id`

### 3. Repository Pattern

**Location**: `app/Repositories/PracticeAreaRepository.php`

```php
<?php

namespace App\Repositories;

use App\Filters\PracticeAreas\PracticeAreaFilters;
use App\Models\PracticeArea;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;

/**
 * Repository for PracticeArea model operations.
 *
 * PracticeAreas are tenant-scoped. All queries MUST filter by tenant_id
 * for non-super-admin users to enforce database isolation at the query level.
 */
class PracticeAreaRepository extends BaseRepository
{
    /**
     * Map repository actions to domain events.
     */
    protected array $dispatchesEvents = [];

    /**
     * Return the model class handled by this repository.
     */
    public function getClassName(): Model|string
    {
        return PracticeArea::class;
    }

    /**
     * Browse practice areas with filters, sorting and pagination.
     *
     * Tenant filtering is mandatory for non-super-admin users.
     */
    public function browsePracticeAreas(
        PracticeAreaFilters $filters,
        int $page = 1,
        int $perPage = 20,
        ?string $sortBy = null,
        bool $sortDesc = false
    ): Paginator {
        $query = $this->query()->with(['creator', 'updater']);

        // MANDATORY: Tenant filtering for non-super-admin
        if (!auth()->user()->hasRole('super-admin')) {
            $query->where('tenant_id', auth()->user()->tenant_id);
        }

        // Apply filters
        $filters->apply($query);

        // Apply sorting
        if ($sortBy) {
            $query->orderBy($sortBy, $sortDesc ? 'desc' : 'asc');
        } else {
            $query->orderBy('name', 'asc');
        }

        return $query->paginate(
            perPage: min($perPage, 100),
            page: max($page, 1)
        );
    }

    /**
     * Read a single practice area by ID with tenant isolation.
     */
    public function readPracticeArea(int|string $id, array $with = []): Model
    {
        $query = $this->query()->with(array_merge($with, ['creator', 'updater', 'tenant']));

        // MANDATORY: Tenant filtering for non-super-admin
        if (!auth()->user()->hasRole('super-admin')) {
            $query->where('tenant_id', auth()->user()->tenant_id);
        }

        return $query->findOrFail($id);
    }

    /**
     * Create a practice area.
     */
    public function createPracticeArea(array $data): PracticeArea|Model|bool
    {
        $practiceArea = self::make($data);

        if ($practiceArea->save()) {
            return $practiceArea->load(['creator', 'updater']);
        }

        return false;
    }

    /**
     * Update a practice area.
     */
    public function updatePracticeArea(int|string|PracticeArea $id, array $data): PracticeArea|Model|bool
    {
        $practiceArea = $this->getModel($id);
        $practiceArea->fill($data);

        if (!$practiceArea->save()) {
            return false;
        }

        return $practiceArea->load(['creator', 'updater']);
    }

    /**
     * Delete a practice area (soft delete).
     */
    public function deletePracticeArea(int|string|PracticeArea $id): bool
    {
        return $this->delete($id);
    }

    /**
     * Restore a soft-deleted practice area.
     */
    public function restorePracticeArea(int|string $id): PracticeArea|Model|bool
    {
        $query = PracticeArea::withTrashed();

        if (!auth()->user()->hasRole('super-admin')) {
            $query->where('tenant_id', auth()->user()->tenant_id);
        }

        $practiceArea = $query->findOrFail($id);
        $practiceArea->restore();

        return $practiceArea->load(['creator', 'updater']);
    }
}
```

**Key Repository Patterns:**

- **Mandatory tenant filtering** — every query adds `where('tenant_id', auth()->user()->tenant_id)` for non-super-admin
- **Super-admin bypass** — `!auth()->user()->hasRole('super-admin')` check before applying tenant filter
- **Eager loading** — `->with(['creator', 'updater'])` prevents N+1 queries
- **No direct model queries** — controllers never bypass the repository

### 4. Filter Pattern

**Location**: `app/Filters/PracticeAreas/PracticeAreaFilters.php`

```php
<?php

namespace App\Filters\PracticeAreas;

use App\Filters\EloquentFilter;
use App\Filters\PracticeAreas\Filters\SearchTermFilter;

class PracticeAreaFilters extends EloquentFilter
{
    protected array $filters = [
        'search' => SearchTermFilter::class,
    ];
}
```

**Location**: `app/Filters/PracticeAreas/Filters/SearchTermFilter.php`

```php
<?php

namespace App\Filters\PracticeAreas\Filters;

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
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }
}
```

**Filter Usage Examples:**

- `GET /api/practice-areas?search=Corporate`
- `GET /api/practice-areas?search=law&per_page=10&sort_by=code`
- `GET /api/practice-areas?sort_by=name&sort_order=desc`

### 5. Form Requests

**Location**: `app/Http/Requests/PracticeArea/CreatePracticeAreaRequest.php`

```php
<?php

namespace App\Http\Requests\PracticeArea;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePracticeAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by Gate in controller
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'min:2',
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                'uppercase',
                // Unique constraint scoped to tenant — NOT global
                Rule::unique('practice_areas', 'code')
                    ->where('tenant_id', auth()->user()->tenant_id),
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The practice area name is required.',
            'name.max' => 'The practice area name must not exceed 255 characters.',
            'name.min' => 'The practice area name must be at least 2 characters.',
            'code.required' => 'The practice area code is required.',
            'code.max' => 'The practice area code must not exceed 50 characters.',
            'code.uppercase' => 'The practice area code must be in uppercase.',
            'code.unique' => 'This practice area code is already in use for this tenant.',
            'description.max' => 'The description must not exceed 1000 characters.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'practice area name',
            'code' => 'practice area code',
            'description' => 'description',
        ];
    }
}
```

**Location**: `app/Http/Requests/PracticeArea/UpdatePracticeAreaRequest.php`

```php
<?php

namespace App\Http\Requests\PracticeArea;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePracticeAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by Gate in controller
    }

    public function rules(): array
    {
        $practiceAreaId = $this->route('practiceArea');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'min:2',
            ],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                'uppercase',
                // Ignore current record in unique check, scoped to tenant
                Rule::unique('practice_areas', 'code')
                    ->where('tenant_id', auth()->user()->tenant_id)
                    ->ignore($practiceAreaId),
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The practice area name is required.',
            'code.unique' => 'This practice area code is already in use for this tenant.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'practice area name',
            'code' => 'practice area code',
        ];
    }
}
```

**Key Form Request Patterns:**

- **`authorize()` returns `true`** — policy authorization handled by `Gate::authorize()` in the controller
- **Tenant-scoped unique rules** — `Rule::unique()->where('tenant_id', auth()->user()->tenant_id)`
- **Update ignores self** — `->ignore($id)` on unique rules for update requests
- **`sometimes` on updates** — allows partial updates without requiring all fields

### 6. Policy Authorization

**Location**: `app/Policies/PracticeAreaPolicy.php`

```php
<?php

namespace App\Policies;

use App\Models\PracticeArea;
use App\Models\User;

class PracticeAreaPolicy
{
    /**
     * Super-admin bypasses all policy checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return null;
    }

    /**
     * View practice area list.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->can('practice_areas.view');
    }

    /**
     * View a specific practice area.
     */
    public function view(User $actor, PracticeArea $practiceArea): bool
    {
        if (!$actor->can('practice_areas.view')) {
            return false;
        }

        // MANDATORY: Enforce tenant boundary
        return $actor->tenant_id === $practiceArea->tenant_id;
    }

    /**
     * Create practice areas.
     */
    public function create(User $actor): bool
    {
        return $actor->can('practice_areas.create');
    }

    /**
     * Update a practice area.
     */
    public function update(User $actor, PracticeArea $practiceArea): bool
    {
        if (!$actor->can('practice_areas.edit')) {
            return false;
        }

        // MANDATORY: Prevent cross-tenant updates
        return $actor->tenant_id === $practiceArea->tenant_id;
    }

    /**
     * Delete a practice area.
     */
    public function delete(User $actor, PracticeArea $practiceArea): bool
    {
        if (!$actor->can('practice_areas.delete')) {
            return false;
        }

        // MANDATORY: Prevent cross-tenant deletion
        return $actor->tenant_id === $practiceArea->tenant_id;
    }

    /**
     * Restore a soft-deleted practice area.
     */
    public function restore(User $actor, PracticeArea $practiceArea): bool
    {
        if (!$actor->can('practice_areas.delete')) {
            return false;
        }

        return $actor->tenant_id === $practiceArea->tenant_id;
    }
}
```

**Register Policy in AppServiceProvider:**

```php
// app/Providers/AppServiceProvider.php
public function boot(): void
{
    Gate::policy(\App\Models\PracticeArea::class, \App\Policies\PracticeAreaPolicy::class);
}
```

**Key Policy Patterns:**

- **`before()` for super-admin** — returns `true` unconditionally, bypassing all other checks
- **Tenant boundary check mandatory** — `$actor->tenant_id === $model->tenant_id` in every instance method
- **Permission check first** — check `$actor->can('module.action')` before tenant boundary
- **No global data access** — tenant users can only access their own tenant's records

### 7. API Resources

**Location**: `app/Http/Resources/PracticeArea/PracticeAreaResource.php`

```php
<?php

namespace App\Http\Resources\PracticeArea;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class PracticeAreaResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function resourceData(Request $request): array
    {
        return [
            // Primary identifiers
            'id' => $this->id,
            'identifier' => $this->identifier,

            // Tenant association
            'tenant_id' => $this->tenant_id,

            // Business fields
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,

            // Audit trail
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
```

**Location**: `app/Http/Resources/PracticeArea/PracticeAreaCollection.php`

```php
<?php

namespace App\Http\Resources\PracticeArea;

use App\Http\Resources\BaseResourceCollection;

class PracticeAreaCollection extends BaseResourceCollection
{
    public $collects = PracticeAreaResource::class;
}
```

**Key Resource Patterns:**

- **Extend `BaseResource`** — provides the standard `status/message/data/metadata` envelope
- **Implement `resourceData()`** — not `toArray()` — `BaseResource` wraps this in the envelope
- **Include `tenant_id`** — expose tenant association in the response
- **Use `toIso8601String()`** — consistent ISO 8601 date format across all resources
- **`whenLoaded()`** for relationships — prevents N+1 when relations are not eager-loaded

### 8. Controller

**Location**: `app/Http/Controllers/PracticeAreaController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Filters\PracticeAreas\PracticeAreaFilters;
use App\Http\Requests\PracticeArea\CreatePracticeAreaRequest;
use App\Http\Requests\PracticeArea\UpdatePracticeAreaRequest;
use App\Http\Resources\PracticeArea\PracticeAreaCollection;
use App\Http\Resources\PracticeArea\PracticeAreaResource;
use App\Models\PracticeArea;
use App\Repositories\PracticeAreaRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PracticeAreaController extends Controller
{
    public function __construct(
        protected PracticeAreaRepository $repository
    ) {}

    /**
     * List practice areas with search, filtering, sorting, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        // Authorization BEFORE any data access
        Gate::authorize('viewAny', PracticeArea::class);

        try {
            $filters = PracticeAreaFilters::fromRequest($request);

            $practiceAreas = $this->repository->browsePracticeAreas(
                filters: $filters,
                page: $request->integer('page', 1),
                perPage: $request->integer('per_page', 15),
                sortBy: $request->input('sort_by'),
                sortDesc: $request->input('sort_order') === 'desc'
            );

            return (new PracticeAreaCollection($practiceAreas))
                ->setMessage('Practice areas retrieved successfully')
                ->addMetadata('filters_applied', $request->only(['search', 'sort_by', 'sort_order']))
                ->response()
                ->setStatusCode(Response::HTTP_OK);

        } catch (\Throwable $e) {
            Log::error('Failed to retrieve practice areas list', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve practice areas. Please try again later.',
                'data' => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create a new practice area.
     */
    public function store(CreatePracticeAreaRequest $request): JsonResponse
    {
        // Authorization BEFORE transaction
        Gate::authorize('create', PracticeArea::class);

        try {
            // Validation BEFORE transaction
            $data = $request->validated();

            Log::info('Creating new practice area', [
                'name' => $data['name'],
                'code' => $data['code'],
                'created_by' => $request->user()?->id,
            ]);

            // Transaction wraps ONLY repository call
            $practiceArea = DB::transaction(function () use ($data) {
                return $this->repository->createPracticeArea($data);
            });

            // Logging AFTER transaction
            Log::info('Practice area created successfully', [
                'practice_area_id' => $practiceArea->id,
                'name' => $practiceArea->name,
            ]);

            return (new PracticeAreaResource($practiceArea))
                ->setMessage('Practice area created successfully')
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);

        } catch (\Throwable $e) {
            Log::error('Failed to create practice area', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'name' => $request->input('name'),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create practice area. Please try again later.',
                'data' => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get a specific practice area.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            // Fetch BEFORE authorization (authorization needs the model)
            $practiceArea = $this->repository->readPracticeArea($id);

            Gate::authorize('view', $practiceArea);

            return (new PracticeAreaResource($practiceArea))
                ->setMessage('Practice area retrieved successfully')
                ->response()
                ->setStatusCode(Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('Practice area not found', [
                'practice_area_id' => $id,
                'requested_by' => $request->user()?->id,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Practice area not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Log::warning('Unauthorized practice area access attempt', [
                'practice_area_id' => $id,
                'requested_by' => $request->user()?->id,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to view this practice area.',
                'data' => null,
            ], Response::HTTP_FORBIDDEN);

        } catch (\Throwable $e) {
            Log::error('Failed to retrieve practice area', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'practice_area_id' => $id,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve practice area. Please try again later.',
                'data' => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update a practice area.
     */
    public function update(UpdatePracticeAreaRequest $request, string $id): JsonResponse
    {
        try {
            // Fetch BEFORE authorization
            $practiceArea = $this->repository->readPracticeArea($id);

            // Authorization BEFORE transaction
            Gate::authorize('update', $practiceArea);

            // Validation BEFORE transaction
            $data = $request->validated();

            Log::info('Updating practice area', [
                'practice_area_id' => $id,
                'updated_by' => $request->user()?->id,
            ]);

            // Transaction wraps ONLY repository call
            $practiceArea = DB::transaction(function () use ($id, $data) {
                return $this->repository->updatePracticeArea($id, $data);
            });

            Log::info('Practice area updated successfully', [
                'practice_area_id' => $practiceArea->id,
                'updated_by' => $request->user()?->id,
            ]);

            return (new PracticeAreaResource($practiceArea))
                ->setMessage('Practice area updated successfully')
                ->response()
                ->setStatusCode(Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Practice area not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to update this practice area.',
                'data' => null,
            ], Response::HTTP_FORBIDDEN);

        } catch (\Throwable $e) {
            Log::error('Failed to update practice area', [
                'practice_area_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update practice area. Please try again later.',
                'data' => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a practice area.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $practiceArea = $this->repository->readPracticeArea($id);

            Gate::authorize('delete', $practiceArea);

            Log::info('Deleting practice area', [
                'practice_area_id' => $id,
                'deleted_by' => $request->user()?->id,
            ]);

            DB::transaction(function () use ($id) {
                $this->repository->deletePracticeArea($id);
            });

            Log::info('Practice area deleted successfully', ['practice_area_id' => $id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Practice area deleted successfully',
                'data' => null,
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Practice area not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to delete this practice area.',
                'data' => null,
            ], Response::HTTP_FORBIDDEN);

        } catch (\Throwable $e) {
            Log::error('Failed to delete practice area', [
                'practice_area_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete practice area. Please try again later.',
                'data' => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
```

**Key Controller Patterns:**

- **Inject repository** — never use direct model queries in controllers
- **`Gate::authorize()` before transaction** — authorization must happen before any DB write
- **`$request->validated()` before transaction** — data preparation outside transaction scope
- **`DB::transaction()` wraps ONLY repository calls** — minimal lock time
- **Logging after transaction** — not inside the transaction closure
- **No manual rollback** — let exceptions bubble; Laravel auto-rollbacks

### 9. Factory & Seeder

**Location**: `database/factories/PracticeAreaFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PracticeAreaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'identifier' => (string) Str::uuid(),
            // Handle both initialized tenancy (tests) and standalone context
            'tenant_id' => function () {
                if (tenancy()->initialized) {
                    return tenancy()->tenant->getTenantKey();
                }
                return Tenant::factory()->create()->id;
            },
            'name' => $this->faker->words(2, true),
            'code' => strtoupper($this->faker->unique()->lexify('???')) . substr(uniqid(), -3),
            'description' => $this->faker->sentence(),
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    /**
     * Set practice area for a specific tenant.
     */
    public function forTenant(string $tenantId): static
    {
        return $this->state(['tenant_id' => $tenantId]);
    }
}
```

**Location**: `database/seeders/PracticeAreaSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\PracticeArea;
use Illuminate\Database\Seeder;

class PracticeAreaSeeder extends Seeder
{
    public function run(): void
    {
        // MANDATORY: Verify tenant context before seeding
        $tenantId = tenancy()->tenant?->getTenantKey();

        if (!$tenantId) {
            $this->command->error('PracticeAreaSeeder must run in tenant context.');
            $this->command->line('Run: php artisan tenants:seed --class=PracticeAreaSeeder');
            return;
        }

        $practiceAreas = [
            ['name' => 'Corporate Law', 'code' => 'CORP', 'description' => 'Corporate and commercial law'],
            ['name' => 'Family Law', 'code' => 'FAM', 'description' => 'Family and domestic relations'],
            ['name' => 'Criminal Law', 'code' => 'CRIM', 'description' => 'Criminal defense and prosecution'],
            ['name' => 'Real Estate', 'code' => 'REAL', 'description' => 'Property and real estate matters'],
            ['name' => 'Employment Law', 'code' => 'EMP', 'description' => 'Employment and labor law'],
        ];

        foreach ($practiceAreas as $area) {
            PracticeArea::firstOrCreate(
                ['code' => $area['code'], 'tenant_id' => $tenantId],
                array_merge($area, ['tenant_id' => $tenantId])
            );
        }
    }
}
```

**Run Seeders:**

```bash
# Seed all tenants
php artisan tenants:seed --class=PracticeAreaSeeder

# Seed specific tenant
php artisan tenants:seed --class=PracticeAreaSeeder --tenants=tenant-uuid-here
```

### 10. Routes & Permissions

**Location**: `routes/api.php` (inside the `auth:api` middleware group)

```php
// Practice Area Management Routes — tenant context required
Route::prefix('practice-areas')->middleware([
    \App\Http\Middleware\InitializeTenancyFromUser::class, // REQUIRED for tenant models
])->group(function () {
    // List all practice areas (with search, sorting, pagination)
    Route::get('/', [PracticeAreaController::class, 'index'])
        ->name('api.practice-areas.index');
    // Create a new practice area
    Route::post('/', [PracticeAreaController::class, 'store'])
        ->name('api.practice-areas.store');
    // Get a specific practice area
    Route::get('/{practiceArea}', [PracticeAreaController::class, 'show'])
        ->name('api.practice-areas.show');
    // Update a practice area
    Route::match(['put', 'patch'], '/{practiceArea}', [PracticeAreaController::class, 'update'])
        ->name('api.practice-areas.update');
    // Delete a practice area
    Route::delete('/{practiceArea}', [PracticeAreaController::class, 'destroy'])
        ->name('api.practice-areas.destroy');
});
```

**Location**: `config/role-permission-map.php`

```php
'permissions' => [
    // Add to existing permissions array
    'practice_areas' => ['view', 'create', 'edit', 'delete'],
],

'roles' => [
    'super-admin' => [
        'practice_areas.*',
        // ... existing permissions
    ],

    'tenant-admin' => [
        'practice_areas.*',
        // ... existing permissions
    ],

    'tenant-user' => [
        'practice_areas.view',
        'practice_areas.create',
        // ... existing permissions
    ],
],
```

## Testing Structure

**Location**: `tests/Feature/PracticeAreaTest.php`

```php
<?php

namespace Tests\Feature;

use App\Models\PracticeArea;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenancy;

class PracticeAreaTest extends TestCase
{
    use RefreshDatabaseWithTenancy; // MANDATORY — handles tenant + central migrations

    protected Tenant $tenant;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles and permissions
        $this->artisan('db:seed', ['--class' => 'RolePermissionsSeeder', '--no-interaction' => true]);

        // Create tenant
        $this->tenant = Tenant::factory()->create();

        // Create user and assign role INSIDE tenant context
        $this->tenant->run(function () {
            $this->user = User::factory()->create([
                'tenant_id' => $this->tenant->id,
                'is_active' => true,
            ]);

            // Set Spatie's team ID BEFORE assigning role
            app(\Spatie\Permission\PermissionRegistrar::class)
                ->setPermissionsTeamId($this->tenant->id);

            $this->user->assignRole('tenant-admin');
        });
    }

    public function test_can_list_practice_areas(): void
    {
        Passport::actingAs($this->user);

        // Create tenant data inside tenant context
        $this->tenant->run(function () {
            PracticeArea::factory()->count(3)->create();
        });

        $response = $this->getJson('/api/practice-areas');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => ['id', 'identifier', 'tenant_id', 'name', 'code'],
                ],
                'meta' => ['total', 'current_page', 'per_page'],
                'links',
                'metadata',
            ])
            ->assertJsonPath('meta.total', 3);
    }

    public function test_can_create_practice_area(): void
    {
        Passport::actingAs($this->user);

        $data = [
            'name' => 'Corporate Law',
            'code' => 'CORP',
            'description' => 'Corporate and business law matters',
        ];

        $response = $this->postJson('/api/practice-areas', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Corporate Law')
            ->assertJsonPath('data.code', 'CORP');

        // Assert inside tenant context
        $this->tenant->run(function () use ($data) {
            $this->assertDatabaseHas('practice_areas', [
                'name' => $data['name'],
                'code' => $data['code'],
                'tenant_id' => $this->tenant->id,
            ]);
        });
    }

    public function test_can_show_practice_area(): void
    {
        Passport::actingAs($this->user);

        $practiceArea = $this->tenant->run(function () {
            return PracticeArea::factory()->create(['name' => 'Family Law', 'code' => 'FAM']);
        });

        $response = $this->getJson("/api/practice-areas/{$practiceArea->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Family Law')
            ->assertJsonPath('data.code', 'FAM');
    }

    public function test_can_update_practice_area(): void
    {
        Passport::actingAs($this->user);

        $practiceArea = $this->tenant->run(function () {
            return PracticeArea::factory()->create(['name' => 'Criminal Law', 'code' => 'CRIM']);
        });

        $response = $this->putJson("/api/practice-areas/{$practiceArea->id}", [
            'name' => 'Criminal Defense Law',
            'code' => 'CRIMDEF',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Criminal Defense Law');

        $this->tenant->run(function () {
            $this->assertDatabaseHas('practice_areas', [
                'name' => 'Criminal Defense Law',
                'tenant_id' => $this->tenant->id,
            ]);
        });
    }

    public function test_can_delete_practice_area(): void
    {
        Passport::actingAs($this->user);

        $practiceArea = $this->tenant->run(function () {
            return PracticeArea::factory()->create();
        });

        $response = $this->deleteJson("/api/practice-areas/{$practiceArea->id}");

        $response->assertStatus(200);

        $this->tenant->run(function () use ($practiceArea) {
            $this->assertSoftDeleted('practice_areas', ['id' => $practiceArea->id]);
        });
    }

    public function test_cannot_access_other_tenant_practice_areas(): void
    {
        Passport::actingAs($this->user);

        $otherTenant = Tenant::factory()->create();

        $otherPracticeArea = $otherTenant->run(function () use ($otherTenant) {
            return PracticeArea::factory()->forTenant($otherTenant->id)->create();
        });

        // Attempting to access another tenant's record returns 404
        $response = $this->getJson("/api/practice-areas/{$otherPracticeArea->id}");

        $response->assertStatus(404);
    }

    public function test_validates_required_fields_on_create(): void
    {
        Passport::actingAs($this->user);

        $response = $this->postJson('/api/practice-areas', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'code']);
    }

    public function test_validates_unique_code_per_tenant(): void
    {
        Passport::actingAs($this->user);

        $this->tenant->run(function () {
            PracticeArea::factory()->create(['code' => 'UNIQUE']);
        });

        $response = $this->postJson('/api/practice-areas', [
            'name' => 'Duplicate Code Test',
            'code' => 'UNIQUE',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_can_filter_by_search(): void
    {
        Passport::actingAs($this->user);

        $this->tenant->run(function () {
            PracticeArea::factory()->create(['name' => 'Corporate Mergers', 'code' => 'CORP_M']);
            PracticeArea::factory()->create(['name' => 'Real Estate Law', 'code' => 'REAL']);
        });

        $response = $this->getJson('/api/practice-areas?search=Corporate');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Corporate Mergers');
    }

    public function test_unauthorized_user_cannot_access(): void
    {
        $unauthorizedUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        // No roles/permissions assigned

        Passport::actingAs($unauthorizedUser);

        $response = $this->getJson('/api/practice-areas');

        $response->assertStatus(403);
    }
}
```

## Best Practices

### 1. Database Design

- **`tenant_id` on every table** — string column, indexed, not a foreign key
- **UUID identifiers** — for external API references and route binding
- **Soft deletes always** — `$table->softDeletes()` on every tenant model
- **Tenant-scoped unique constraints** — `unique(['tenant_id', 'field'])`, never global unique
- **No FK constraints to central DB** — `created_by`/`updated_by` are plain integers

### 2. Security

- **Tenant filtering in every repository query** — mandatory for non-super-admin
- **Tenant boundary in every policy method** — `$actor->tenant_id === $model->tenant_id`
- **`InitializeTenancyFromUser` middleware** — required on all tenant-scoped route groups
- **Form Request validation** — never inline `$request->validate()` in controllers
- **Log all operations** — creation, updates, deletions with user and resource context

### 3. Performance

- **Eager loading in repositories** — `->with(['creator', 'updater'])` prevents N+1
- **Index `tenant_id`** — every tenant query filters by this column
- **Paginate with limits** — `min($perPage, 100)` caps result sets
- **Minimal transactions** — wrap only repository calls, not authorization or logging

### 4. Testing

- **`RefreshDatabaseWithTenancy` trait** — never use `RefreshDatabase`
- **Create tenant data inside `$this->tenant->run()`** — ensures correct tenant DB context
- **Assert inside tenant context** — `$this->tenant->run(fn() => $this->assertDatabaseHas(...))`
- **Test cross-tenant isolation** — verify 404 when accessing another tenant's resource
- **Set Spatie team before role assignment** — `PermissionRegistrar::setPermissionsTeamId()`
- **Test all paths** — happy path, 404, 403, 422 validation failures

### 5. Anti-Patterns to Avoid

```php
// ❌ WRONG — Direct model query in controller
$areas = PracticeArea::where('tenant_id', auth()->user()->tenant_id)->get();

// ✅ CORRECT — Use repository
$areas = $this->repository->browsePracticeAreas($filters, $page, $perPage);

// ❌ WRONG — Authorization inside transaction
DB::transaction(function () {
    Gate::authorize('create', PracticeArea::class); // Wrong place
    return $this->repository->createPracticeArea($data);
});

// ✅ CORRECT — Authorization before transaction
Gate::authorize('create', PracticeArea::class);
DB::transaction(fn() => $this->repository->createPracticeArea($data));

// ❌ WRONG — Auditable trait on tenant model
class PracticeArea extends Model implements AuditableContract
{
    use Auditable; // ❌ Never on tenant models
}

// ✅ CORRECT — No auditing on tenant models
class PracticeArea extends Model
{
    use HasFactory, SoftDeletes, TenantConnection; // ✅
}

// ❌ WRONG — FK constraint from tenant to central DB
$table->foreignId('created_by')->constrained('users'); // ❌ Cross-DB FK

// ✅ CORRECT — Plain integer field, no FK constraint
$table->unsignedBigInteger('created_by')->nullable(); // ✅
```

## Migration Commands

```bash
# Create tenant model migration (in tenant/ subdirectory)
php artisan make:migration create_practice_areas_table

# Move to correct location
mv database/migrations/*create_practice_areas_table.php database/migrations/tenant/

# Create model with factory and seeder
php artisan make:model PracticeArea -fs

# Run tenant migrations
php artisan tenants:migrate

# Seed tenant data
php artisan tenants:seed --class=PracticeAreaSeeder

# Run tests (always use test.sh)
./test.sh tests/Feature/PracticeAreaTest.php
./test.sh --filter=test_can_create_practice_area
```

## Implementation Checklist

When adding a new tenant-scoped feature, complete every step:

- [ ] Migration in `database/migrations/tenant/` with `tenant_id`, UUID `identifier`, `created_by`/`updated_by` (no FK), soft deletes
- [ ] Model with `TenantConnection`, `SoftDeletes`, `HasFactory` — **no** `Auditable`
- [ ] `boot()` auto-populates `identifier`, `tenant_id`, `created_by`, `updated_by`
- [ ] Repository extends `BaseRepository` with mandatory tenant filtering in all query methods
- [ ] `{Domain}Filters` class + individual filter classes in `Filters/` subdirectory
- [ ] `Create{Domain}Request` with tenant-scoped `Rule::unique()`
- [ ] `Update{Domain}Request` with `sometimes` + `->ignore($id)` on unique rules
- [ ] Policy with `before()` super-admin bypass + `tenant_id` boundary check in every method
- [ ] Policy registered in `AppServiceProvider`
- [ ] Resource extends `BaseResource` implementing `resourceData()` — includes `tenant_id`
- [ ] Collection extends `BaseResourceCollection`
- [ ] Controller injects repository, `Gate::authorize()` before transaction, `DB::transaction()` wraps only repository calls
- [ ] Routes inside `auth:api` group with `InitializeTenancyFromUser` middleware
- [ ] Permissions added to `config/role-permission-map.php`
- [ ] Feature tests using `RefreshDatabaseWithTenancy`, tenant context via `$this->tenant->run()`
- [ ] Cross-tenant isolation test (assert 404 on other tenant's resources)
- [ ] Code formatted: `vendor/bin/pint --dirty`
- [ ] Tests passing: `./test.sh tests/Feature/{Domain}Test.php`
