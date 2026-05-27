## Architecture Principles

- **Repository Pattern**: All database operations through repositories
- **Resource Pattern**: Consistent API responses using Laravel Resources
- **Policy-Based Authorization**: Gate-driven permission checks
- **Form Request Validation**: Dedicated validation classes
- **Filter Pattern**: Composable query filters
- **DRY Principle**: Reuse existing implementations, never duplicate code

## Layer Boundaries (Enforced)

- Production layers (`app/Http/Controllers`, `app/Livewire`, `app/Jobs`, `app/Services`) must use repositories for record access and mutations.
- Direct model queries in production layers are forbidden (`Model::query()`, `Model::find()`, `Model::where()`, `Model::create()`, `Model::update()`, `Model::delete()`).
- Privileged scripts (`tests/`, `database/factories/`, `database/seeders/`) may use Eloquent directly to keep tests and setup scripts simple and fast.
- Enforce this boundary with PHPStan/Larastan custom rules in CI.

## Key Characteristics

- **Central Database Entities**: Global reference data shared across the application
- **UUID Identifiers**: Each record has a unique UUID identifier
- **Audit Trail**: Automatic tracking of creator and updater
- **Soft Deletes**: Deleted records retained for recovery
- **Policy Authorization**: Permission-based access control

## Layer-by-Layer Breakdown

### 1. Migration Pattern

**Key Components:**

- UUID identifier field
- Business-specific fields
- Audit trail fields (created_by, updated_by)
- Timestamps and soft deletes
- Performance indexes
- Foreign key constraints to users table

### 2. Model Pattern

**Key Components:**

- Fillable attributes
- Type casting
- Boot method for automatic field population
- Relationship methods (creator, updater)

### 3. Repository Pattern

**Key Methods:**

- `browse{Entity}()` - Paginated listing with filters
- `read{Entity}()` - Single record retrieval
- `create{Entity}()` - Record creation
- `update{Entity}()` - Record updating
- `delete{Entity}()` - Soft deletion
- `restore{Entity}()` - Restoration

### 4. Filter Pattern

**Structure:**

- Main filter class extending `EloquentFilter`
- Individual filter classes for specific criteria
- Composable and reusable filter components

### 5. Validation Pattern

**Components:**

- Create and Update form request classes
- Comprehensive validation rules
- Custom error messages
- Field attribute mapping

### 6. Authorization Pattern

**Structure:**

- Policy class with permission-based checks
- Super-admin bypass functionality
- Granular permission methods

### 7. Resource Pattern

**Components:**

- Single resource class
- Collection resource class
- Consistent data transformation
- Metadata support

### 8. Controller Pattern

**Structure:**

- Repository dependency injection
- Authorization before database operations
- Transaction wrapping for writes
- Comprehensive error handling
- Structured logging

---

## Common Patterns

### 1. Relationship Patterns

```php
// One-to-many relationship
public function employees(): HasMany
{
    return $this->hasMany(Employee::class);
}

// Many-to-many relationship
public function roles(): BelongsToMany
{
    return $this->belongsToMany(Role::class);
}

// Polymorphic relationship
public function documentable(): MorphTo
{
    return $this->morphTo();
}
```

### 2. Scope Patterns

```php
// Status scope
public function scopeActive(Builder $query): void
{
    $query->where('is_active', true);
}

// Search scope
public function scopeSearch(Builder $query, string $term): void
{
    $query->where(function (Builder $q) use ($term) {
        $q->where('name', 'like', "%{$term}%")
          ->orWhere('description', 'like', "%{$term}%");
    });
}
```

### 3. Validation Patterns

```php
// Complex validation rules
'email' => [
    'required',
    'email',
    'max:255',
    Rule::unique('users')->ignore($this->route('user')),
],

// Conditional validation
'manager_id' => [
    'nullable',
    'integer',
    Rule::requiredIf($this->input('type') === 'team'),
    'exists:users,id',
],
```

---

## Step-by-Step Implementation

### Step 1: Database Migration

**Location**: `database/migrations/`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('continents', function (Blueprint $table) {
            $table->id();
            $table->uuid('identifier')->unique();

            // Business fields
            $table->string('name')->unique();
            $table->string('slug')->nullable();
            $table->string('short_code', 10)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            // Audit trail with FK to users table
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Performance indexes
            $table->index('is_active');
            $table->index('short_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('continents');
    }
};
```

### Step 2: Eloquent Model

**Location**: `app/Models/Continent.php`

```php
<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

/**
 * Continent model.
 *
 * Continents are reference data for organizational structure.
 */
class Continent extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'identifier',
        'name',
        'slug',
        'short_code',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];


    // Other model Relationships

    /**
     * Model Relationship.
     */
    public function model(): BelongsTo
    {
        return $this->belongsTo(Model::class);
    }

    // Other model Scopes

    /**
     * e.g getAttribute<AttributeName>, ...
     *
     */
    public function getAttributes(): array
    {
        return $this->attributesToArray();
    }

    // Other model Scopes

    /**
     * e.g getScopes<ScopeName>, ...
     *
     */
    public function getAttributes(): array
    {
        return $this->attributesToArray();
    }


}
```

### Step 3: Repository

**Location**: `app/Repositories/ContinentRepository.php`

```php
<?php

namespace App\Repositories;

use App\Filters\Continents\ContinentFilters;
use App\Models\Continent;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;

/**
 * Repository for Continent model operations.
 */
class ContinentRepository extends BaseRepository
{
    protected array $dispatchesEvents = [];

    public function getClassName(): Model|string
    {
        return Continent::class;
    }

    /**
     * Browse continents with filters, sorting and pagination.
     */
    public function browseContinents(
        ContinentFilters $filters,
        int $page = 1,
        int $perPage = 20,
        ?string $sortBy = null,
        bool $sortDesc = false
    ): Paginator {
        $query = $this->query()->with(['creator', 'updater']);

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
     * Read a single continent with relations.
     */
    public function readContinent(int|string $id, array $with = []): Model
    {
        return $this->read($id, array_merge($with, ['creator', 'updater']));
    }

    /**
     * Create a continent.
     */
    public function createContinent(array $data): Continent|Model|bool
    {
        $continent = self::make($data);

        if ($continent->save()) {
            return $continent->load(['creator', 'updater']);
        }

        return false;
    }

    /**
     * Update a continent.
     */
    public function updateContinent(int|string|Continent $id, array $data): Continent|Model|bool
    {
        $continent = $this->getModel($id);
        $continent->fill($data);

        if (!$continent->save()) {
            return false;
        }

        return $continent->load(['creator', 'updater']);
    }

    /**
     * Delete a continent (soft delete).
     */
    public function deleteContinent(int|string|Continent $id): bool
    {
        return $this->delete($id);
    }

    /**
     * Restore a soft-deleted continent.
     */
    public function restoreContinent(int|string $id): Continent|Model|bool
    {
        $continent = Continent::withTrashed()->findOrFail($id);

        if ($continent->restore()) {
            return $continent->load(['creator', 'updater']);
        }

        return false;
    }
}
```

### Step 4: Query Filters

**Main Filter Class**: `app/Filters/Continents/ContinentFilters.php`

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

**Individual Filter**: `app/Filters/Continents/Filters/SearchTermFilter.php`

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
              ->orWhere('short_code', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }
}
```

### Step 5: Form Requests

**Create Request**: `app/Http/Requests/Continent/CreateContinentRequest.php`

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
            'name' => [
                'required',
                'string',
                'max:255',
                'min:2',
                'unique:continents,name',
            ],
            'slug' => [
                'nullable',
                'string',
                'max:255',
            ],
            'short_code' => [
                'nullable',
                'string',
                'max:10',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The continent name is required.',
            'name.max' => 'The continent name must not exceed 255 characters.',
            'name.min' => 'The continent name must be at least 2 characters.',
            'name.unique' => 'This continent name is already in use.',
            'slug.max' => 'The slug must not exceed 255 characters.',
            'short_code.max' => 'The short code must not exceed 10 characters.',
            'is_active.boolean' => 'The status must be true or false.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'continent name',
            'slug' => 'slug',
            'short_code' => 'short code',
            'description' => 'description',
            'is_active' => 'is_active',
        ];
    }
}
```

**Update Request**: `app/Http/Requests/Continent/UpdateContinentRequest.php`

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
                'required',
                'string',
                'max:255',
                'min:2',
                Rule::unique('continents', 'name')->ignore($continentId),
            ],
            'slug' => [
                'nullable',
                'string',
                'max:255',
            ],
            'short_code' => [
                'nullable',
                'string',
                'max:10',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    // Same messages() and attributes() methods as CreateContinentRequest
}
```

### Step 6: Authorization Policy

**Location**: `app/Policies/ContinentPolicy.php`

```php
<?php

namespace App\Policies;

use App\Models\Continent;
use App\Models\User;

class ContinentPolicy
{
    /**
     * Super-admin can do anything.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return null;
    }

    /**
     * View continent list.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->can('continents.view');
    }

    /**
     * View a specific continent.
     */
    public function view(User $actor, Continent $continent): bool
    {
        return $actor->can('continents.view');
    }

    /**
     * Create continents.
     */
    public function create(User $actor): bool
    {
        return $actor->can('continents.create');
    }

    /**
     * Update continents.
     */
    public function update(User $actor, Continent $continent): bool
    {
        return $actor->can('continents.edit');
    }

    /**
     * Delete continents.
     */
    public function delete(User $actor, Continent $continent): bool
    {
        return $actor->can('continents.delete');
    }
}
```

### Step 7: API Resources

**Single Resource**: `app/Http/Resources/Continent/ContinentResource.php`

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
            // Primary identifier
            'id' => $this->id,
            'identifier' => $this->identifier,

            // Continent details
            'name' => $this->name,
            'slug' => $this->slug,
            'short_code' => $this->short_code,
            'description' => $this->description,
            'is_active' => $this->is_active,

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

**Collection Resource**: `app/Http/Resources/Continent/ContinentCollection.php`

```php
<?php

namespace App\Http\Resources\Continent;

use App\Http\Resources\BaseResourceCollection;

class ContinentCollection extends BaseResourceCollection
{
    public $collects = ContinentResource::class;
}
```

### Step 8: Controller

**Location**: `app/Http/Controllers/ContinentController.php`

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

    /**
     * Display a listing of continents.
     */
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
            Log::error('Failed to retrieve continents', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve continents',
                'data' => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created continent.
     */
    public function store(CreateContinentRequest $request): JsonResponse
    {
        // 1. Authorization BEFORE transaction
        Gate::authorize('create', Continent::class);

        try {
            // 2. Validation BEFORE transaction
            $data = $request->validated();

            // 3. Pre-transaction logging
            Log::info('Creating continent', ['name' => $data['name']]);

            // 4. Transaction wraps ONLY repository call
            $continent = DB::transaction(function () use ($data) {
                return $this->repository->createContinent($data);
            });

            // 5. Post-transaction logging
            Log::info('Continent created', ['id' => $continent->id]);

            return (new ContinentResource($continent))
                ->setMessage('Continent created successfully')
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);

        } catch (\Throwable $e) {
            Log::error('Failed to create continent', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create continent',
                'data' => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified continent.
     */
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
                'message' => 'Continent not found',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Update the specified continent.
     */
    public function update(UpdateContinentRequest $request, string $id): JsonResponse
    {
        try {
            $continent = $this->repository->readContinent($id);
            Gate::authorize('update', $continent);

            $data = $request->validated();

            Log::info('Updating continent', ['id' => $id]);

            $continent = DB::transaction(function () use ($id, $data) {
                return $this->repository->updateContinent($id, $data);
            });

            Log::info('Continent updated', ['id' => $continent->id]);

            return (new ContinentResource($continent))
                ->setMessage('Continent updated successfully')
                ->response();

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Continent not found',
            ], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Remove the specified continent (soft delete).
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $continent = $this->repository->readContinent($id);
            Gate::authorize('delete', $continent);

            Log::info('Deleting continent', ['id' => $id]);

            DB::transaction(function () use ($id) {
                $this->repository->deleteContinent($id);
            });

            Log::info('Continent deleted', ['id' => $id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Continent deleted successfully',
                'data' => ['id' => $continent->id],
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Continent not found',
            ], Response::HTTP_NOT_FOUND);
        }
    }
}
```

### Step 9: Routes

**Location**: `routes/api.php`

```php
Route::middleware(['auth:api'])->group(function () {
    // Continent Management Routes
    Route::prefix('continents')->group(function () {
        // List all continents (with search, sorting, pagination)
        Route::get('/', [ContinentController::class, 'index'])->name('api.continents.index');
        // Create a new continent
        Route::post('/', [ContinentController::class, 'store'])->name('api.continents.store');
        // Get a specific continent
        Route::get('/{continent}', [ContinentController::class, 'show'])->name('api.continents.show');
        // Update a continent
        Route::match(['put', 'patch'], '/{continent}', [ContinentController::class, 'update'])->name('api.continents.update');
        // Delete a continent
        Route::delete('/{continent}', [ContinentController::class, 'destroy'])->name('api.continents.destroy');
        // Restore a soft-deleted continent
        Route::post('/{id}/restore', [ContinentController::class, 'restore'])->name('api.continents.restore');
    });
});
```

### Step 10: Permissions Configuration

**Location**: `config/role-permission-map.php`

```php
'permissions' => [
    // ... existing permissions ...

    'continents' => ['view', 'create', 'edit', 'delete'],
],

'roles' => [
    'super-admin' => ['*'],

    'admin' => [
        // ... existing permissions ...
        'continents.*',
    ],

    'user' => [
        // ... existing permissions ...
        'continents.view',
    ],
],
```

### Step 11: Factory

**Location**: `database/factories/ContinentFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ContinentFactory extends Factory
{
    public function definition(): array
    {
        $continents = [
            ['name' => 'Human Resources', 'slug' => 'human-resources', 'short_code' => 'HR'],
            ['name' => 'Information Technology', 'slug' => 'information-technology', 'short_code' => 'IT'],
            ['name' => 'Finance', 'slug' => 'finance', 'short_code' => 'FIN'],
            ['name' => 'Marketing', 'slug' => 'marketing', 'short_code' => 'MKT'],
            ['name' => 'Operations', 'slug' => 'operations', 'short_code' => 'OPS'],
        ];

        $continent = $this->faker->randomElement($continents);

        return [
            'identifier' => (string) Str::uuid(),
            'name' => $continent['name'] . ' ' . $this->faker->unique()->randomNumber(4),
            'slug' => $continent['slug'] . '-' . $this->faker->unique()->randomNumber(3),
            'short_code' => $continent['short_code'],
            'description' => $this->faker->paragraph(),
            'is_active' => true,
            'created_by' => User::random()->first()->id ?? User::factory(),
            'updated_by' => null,
        ];
    }

    /**
     * Create continent with active status.
     */
    public function active(): static
    {
        return $this->state([
            'is_active' => true,
        ]);
    }

    /**
     * Create continent with inactive status.
     */
    public function inactive(): static
    {
        return $this->state([
            'is_active' => false,
        ]);
    }
}
```

### Step 12: Seeder

**Location**: `database/seeders/ContinentSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\Continent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ContinentSeeder extends Seeder
{
    public function run(): void
    {
        $continents = [
            [
                'identifier' => (string) Str::uuid(),
                'name' => 'Human Resources',
                'slug' => 'human-resources',
                'short_code' => 'HR',
                'description' => 'Manages employee relations, recruitment, and workplace policies',
                'is_active' => true,
                'created_by' => null,
            ],
            [
                'identifier' => (string) Str::uuid(),
                'name' => 'Information Technology',
                'slug' => 'information-technology',
                'short_code' => 'IT',
                'description' => 'Manages technology infrastructure and software development',
                'is_active' => true,
                'created_by' => null,
            ],
            [
                'identifier' => (string) Str::uuid(),
                'name' => 'Finance',
                'slug' => 'finance',
                'short_code' => 'FIN',
                'description' => 'Handles financial planning, accounting, and budget management',
                'is_active' => true,
                'created_by' => null,
            ],
        ];

        foreach ($continents as $continent) {
            Continent::create($continent);
        }

        $this->command->info('Created ' . count($continents) . ' continents');
    }
}
```

---
