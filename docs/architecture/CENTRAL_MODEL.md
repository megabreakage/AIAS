# Central Model Architecture Guide

**AIAS Multi-Tenant Laravel API**

This document defines the complete architecture for creating fully featured **central database models** that are **not tenant-scoped** in the AIAS system.

## Overview

Central models are global entities shared across all tenants, residing in the central database. They include:

- **Global reference data**: Countries, currencies, system configurations
- **User management**: Users, roles, permissions, OAuth clients
- **System entities**: Tenants, audit logs, system settings
- **Shared resources**: Global lookup tables, system-wide configurations

## Key Architectural Principles

### Central vs Tenant Models Comparison

| Aspect | Central Models | Tenant Models |
|--------|----------------|---------------|
| **Database** | `central` connection | `tenant` connection via `TenantConnection` trait |
| **Auditing** | ✅ **YES** - Use `Auditable` trait | ❌ **NO** - Avoid cross-database complexity |
| **Tenant ID** | ❌ **NO** - Not tenant-scoped | ✅ **YES** - Required for isolation |
| **Repository Filtering** | Access control by role/permission | Mandatory tenant filtering |
| **Shared Across** | All tenants | Single tenant only |
| **Migration Location** | `database/migrations/` | `database/migrations/tenant/` |
| **Examples** | Users, Countries, Settings | Contacts, Clients, Matters |

### Security Boundaries

- **Database isolation**: Central models accessible across all tenants
- **No tenant filtering**: Repository methods focus on role/permission-based access
- **Auditing enabled**: Full audit trail using `owen-it/laravel-auditing`
- **Authorization layers**: Policy (Gate) → Repository (role filter) → Model (relationships)
- **Soft deletes**: All models use soft deletes for data recovery

## Step-by-Step Implementation

### 1. Migration Structure

**Location**: `database/migrations/` (NOT `database/migrations/tenant/`)

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->uuid('identifier')->unique();

            // Business fields
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('category')->default('general');
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);

            // NO tenant_id - this is central data
            // Creator tracking - references central users table
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            // Standard Laravel fields
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index('key');
            $table->index('category');
            $table->index(['category', 'is_public']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
```

**Key Migration Patterns:**

- **No `tenant_id` field** - central models are not tenant-scoped
- **UUID identifier** - for external API references
- **Creator tracking** - `created_by` and `updated_by` referencing central users
- **Soft deletes** - for data recovery and audit trail
- **Performance indexes** - on commonly queried fields

### 2. Model Structure

**Location**: `app/Models/SystemSetting.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * SystemSetting model - Central database entity.
 *
 * System settings are global configuration values shared across all tenants.
 * This model does NOT use TenantConnection as it resides in the central database.
 */
class SystemSetting extends Model implements AuditableContract
{
    use Auditable, HasFactory, SoftDeletes; // ✅ Auditable trait allowed for central models

    /**
     * The database connection that should be used by the model.
     * SystemSetting is a central database model.
     */
    protected $connection = 'central';

    protected $fillable = [
        'identifier',
        'key',
        'value',
        'category',
        'description',
        'is_public',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_public' => 'boolean',
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

        // Auto-generate UUID identifier
        static::creating(function (SystemSetting $setting) {
            if (!$setting->identifier) {
                $setting->identifier = Str::uuid()->toString();
            }

            // Auto-set created_by (only in web/API context)
            if (Auth::check()) {
                $setting->created_by = Auth::id();
            }
        });

        // Auto-set updated_by on update
        static::updating(function (SystemSetting $setting) {
            if (Auth::check()) {
                $setting->updated_by = Auth::id();
            }
        });
    }

    /**
     * Relationship: Setting creator
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship: Setting updater
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope: Public settings only
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope: Settings by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Get decoded JSON value if applicable
     */
    public function getDecodedValueAttribute()
    {
        $decoded = json_decode($this->value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $this->value;
    }
}
```

**Key Model Patterns:**

- **Explicit central connection**: `protected $connection = 'central'`
- **Auditable trait**: Full audit trail for central models
- **UUID generation**: Auto-generated in `creating` event
- **Creator tracking**: Auto-set `created_by` and `updated_by`
- **Route key**: Use UUID identifier for external API access
- **Eloquent scopes**: Reusable query filters
- **Accessor methods**: Transform data for API consumption

### 3. Repository Pattern

**Location**: `app/Repositories/SystemSettingRepository.php`

```php
<?php

namespace App\Repositories;

use App\Filters\SystemSettings\SystemSettingFilters;
use App\Models\SystemSetting;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;

/**
 * Repository for SystemSetting model operations.
 *
 * SystemSettings are central database entities - no tenant scoping required.
 * All authenticated users can access public settings, admins can access all.
 */
class SystemSettingRepository extends BaseRepository
{
    /**
     * Map repository actions to domain events.
     */
    protected array $dispatchesEvents = [
        'created' => \App\Events\SystemSettingCreated::class,
        'updated' => \App\Events\SystemSettingUpdated::class,
        'deleted' => \App\Events\SystemSettingDeleted::class,
    ];

    /**
     * Return the model class handled by this repository.
     */
    public function getClassName(): Model|string
    {
        return SystemSetting::class;
    }

    /**
     * Browse system settings with filters, sorting and pagination.
     *
     * Since system settings are central data:
     * - Super-admin users see all settings
     * - Regular users see only public settings
     */
    public function browseSystemSettings(
        SystemSettingFilters $filters,
        int $page = 1,
        int $perPage = 20,
        ?string $sortBy = null,
        bool $sortDesc = false
    ): Paginator {
        $query = $this->query()->with(['creator', 'updater']);

        // Access control: Non-admins see only public settings
        if (!auth()->user()->hasRole('super-admin')) {
            $query->where('is_public', true);
        }

        // Apply filters
        $filters->apply($query);

        // Apply sorting
        if ($sortBy) {
            $query->orderBy($sortBy, $sortDesc ? 'desc' : 'asc');
        } else {
            $query->orderBy('category')->orderBy('key');
        }

        return $query->paginate(
            perPage: min($perPage, 100),
            page: max($page, 1)
        );
    }

    /**
     * Read a system setting by identifier.
     */
    public function readSystemSetting(string $identifier, array $relations = []): ?SystemSetting
    {
        $query = $this->query();

        if (!empty($relations)) {
            $query->with($relations);
        }

        $setting = $query->where('identifier', $identifier)->first();

        // Access control for non-public settings
        if ($setting && !$setting->is_public && !auth()->user()->hasRole('super-admin')) {
            return null;
        }

        return $setting;
    }

    /**
     * Get setting value by key
     */
    public function getSettingValue(string $key, $default = null)
    {
        $setting = $this->query()
            ->where('key', $key)
            ->first();

        if (!$setting) {
            return $default;
        }

        // Access control
        if (!$setting->is_public && !auth()->user()->hasRole('super-admin')) {
            return $default;
        }

        return $setting->decoded_value ?? $default;
    }

    /**
     * Set setting value by key
     */
    public function setSetting(string $key, $value, string $category = 'general', bool $isPublic = false): SystemSetting
    {
        return $this->query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => is_array($value) ? json_encode($value) : $value,
                'category' => $category,
                'is_public' => $isPublic,
            ]
        );
    }
}
```

**Key Repository Patterns:**

- **Role-based filtering**: Access control by user permissions, not tenant
- **Event dispatching**: Domain events for business logic
- **Eager loading**: Prevent N+1 queries with relationship loading
- **Helper methods**: Domain-specific operations like `getSettingValue()`
- **Access control**: Privacy filtering based on user roles

### 4. Filter Pattern

**Location**: `app/Filters/SystemSettings/SystemSettingFilters.php`

```php
<?php

namespace App\Filters\SystemSettings;

use App\Filters\EloquentFilter;
use App\Filters\SystemSettings\Filters\CategoryFilter;
use App\Filters\SystemSettings\Filters\PublicFilter;
use App\Filters\SystemSettings\Filters\SearchTermFilter;

class SystemSettingFilters extends EloquentFilter
{
    protected array $filters = [
        'search' => SearchTermFilter::class,
        'category' => CategoryFilter::class,
        'is_public' => PublicFilter::class,
    ];
}
```

**Location**: `app/Filters/SystemSettings/Filters/SearchTermFilter.php`

```php
<?php

namespace App\Filters\SystemSettings\Filters;

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
            $q->where('key', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhere('value', 'like', "%{$search}%");
        });
    }
}
```

**Filter Usage Examples:**

- `GET /api/system-settings?search=email`
- `GET /api/system-settings?category=security&is_public=true`
- `GET /api/system-settings?search=smtp&category=email&per_page=10`

### 5. Form Requests

**Location**: `app/Http/Requests/SystemSetting/CreateSystemSettingRequest.php`

```php
<?php

namespace App\Http\Requests\SystemSetting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CreateSystemSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', \App\Models\SystemSetting::class);
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:255', 'unique:system_settings,key'],
            'value' => ['nullable', 'string'],
            'category' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_public' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'key.required' => 'Setting key is required.',
            'key.unique' => 'This setting key already exists.',
            'category.required' => 'Category is required.',
        ];
    }
}
```

**Location**: `app/Http/Requests/SystemSetting/UpdateSystemSettingRequest.php`

```php
<?php

namespace App\Http\Requests\SystemSetting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateSystemSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $setting = $this->route('identifier')
            ? \App\Models\SystemSetting::where('identifier', $this->route('identifier'))->first()
            : null;

        return Gate::allows('update', $setting);
    }

    public function rules(): array
    {
        $settingId = \App\Models\SystemSetting::where('identifier', $this->route('identifier'))->value('id');

        return [
            'key' => ['sometimes', 'string', 'max:255', Rule::unique('system_settings')->ignore($settingId)],
            'value' => ['nullable', 'string'],
            'category' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_public' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'key.unique' => 'This setting key already exists.',
        ];
    }
}
```

### 6. Policy Authorization

**Location**: `app/Policies/SystemSettingPolicy.php`

```php
<?php

namespace App\Policies;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SystemSettingPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('system.view');
    }

    public function view(User $user, SystemSetting $setting): bool
    {
        // Public settings can be viewed by anyone with basic permission
        if ($setting->is_public) {
            return $user->hasPermissionTo('system.view');
        }

        // Non-public settings require admin permission
        return $user->hasPermissionTo('system.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('system.create');
    }

    public function update(User $user, SystemSetting $setting): bool
    {
        return $user->hasPermissionTo('system.edit');
    }

    public function delete(User $user, SystemSetting $setting): bool
    {
        return $user->hasPermissionTo('system.delete');
    }

    public function restore(User $user, SystemSetting $setting): bool
    {
        return $user->hasPermissionTo('system.delete');
    }
}
```

**Register Policy in AppServiceProvider:**

```php
// app/Providers/AppServiceProvider.php
protected $policies = [
    \App\Models\SystemSetting::class => \App\Policies\SystemSettingPolicy::class,
];
```

### 7. API Resources

**Location**: `app/Http/Resources/SystemSettings/SystemSettingResource.php`

```php
<?php

namespace App\Http\Resources\SystemSettings;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Users\UserResource;
use Illuminate\Http\Request;

class SystemSettingResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->identifier,
            'key' => $this->key,
            'value' => $this->when(
                $this->shouldShowValue($request),
                $this->decoded_value
            ),
            'category' => $this->category,
            'description' => $this->description,
            'is_public' => $this->is_public,
            'creator' => UserResource::make($this->whenLoaded('creator')),
            'updater' => UserResource::make($this->whenLoaded('updater')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function shouldShowValue(Request $request): bool
    {
        // Always show public values
        if ($this->is_public) {
            return true;
        }

        // Show private values only to admins
        return $request->user()->hasRole('super-admin');
    }
}
```

**Location**: `app/Http/Resources/SystemSettings/SystemSettingCollection.php`

```php
<?php

namespace App\Http\Resources\SystemSettings;

use App\Http\Resources\BaseCollection;

class SystemSettingCollection extends BaseCollection
{
    public $collects = SystemSettingResource::class;
}
```

### 8. Controller

**Location**: `app/Http/Controllers/SystemSettingController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Filters\SystemSettings\SystemSettingFilters;
use App\Http\Requests\SystemSetting\CreateSystemSettingRequest;
use App\Http\Requests\SystemSetting\UpdateSystemSettingRequest;
use App\Http\Resources\SystemSettings\SystemSettingCollection;
use App\Http\Resources\SystemSettings\SystemSettingResource;
use App\Models\SystemSetting;
use App\Repositories\SystemSettingRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class SystemSettingController extends Controller
{
    public function __construct(
        protected SystemSettingRepository $repository
    ) {}

    /**
     * List system settings with search, filtering, sorting, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', SystemSetting::class);

        $filters = SystemSettingFilters::fromRequest($request);

        $settings = $this->repository->browseSystemSettings(
            filters: $filters,
            page: $request->integer('page', 1),
            perPage: $request->integer('per_page', 15),
            sortBy: $request->input('sort_by'),
            sortDesc: $request->input('sort_order') === 'desc'
        );

        return (new SystemSettingCollection($settings))
            ->setMessage('System settings retrieved successfully')
            ->addMetadata('filters_applied', $request->only(['search', 'category', 'is_public']))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Create a new system setting.
     */
    public function store(CreateSystemSettingRequest $request): JsonResponse
    {
        Gate::authorize('create', SystemSetting::class);

        try {
            $data = $request->validated();

            Log::info('Creating system setting', ['key' => $data['key']]);

            $setting = DB::transaction(function () use ($data) {
                return $this->repository->insert($data);
            });

            Log::info('System setting created successfully', [
                'id' => $setting->identifier,
                'key' => $setting->key
            ]);

            return (new SystemSettingResource($setting))
                ->setMessage('System setting created successfully')
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);

        } catch (\Throwable $e) {
            Log::error('Failed to create system setting', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create system setting',
                'data' => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get a specific system setting.
     */
    public function show(string $identifier): JsonResponse
    {
        try {
            $setting = $this->repository->readSystemSetting($identifier, ['creator', 'updater']);

            if (!$setting) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'System setting not found',
                    'data' => null,
                ], Response::HTTP_NOT_FOUND);
            }

            Gate::authorize('view', $setting);

            return (new SystemSettingResource($setting))
                ->setMessage('System setting retrieved successfully')
                ->response();

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'System setting not found',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Update a system setting.
     */
    public function update(UpdateSystemSettingRequest $request, string $identifier): JsonResponse
    {
        try {
            $setting = $this->repository->readSystemSetting($identifier);

            if (!$setting) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'System setting not found',
                    'data' => null,
                ], Response::HTTP_NOT_FOUND);
            }

            Gate::authorize('update', $setting);

            $data = $request->validated();

            Log::info('Updating system setting', ['id' => $identifier]);

            $setting = DB::transaction(function () use ($identifier, $data) {
                return $this->repository->update($identifier, $data);
            });

            Log::info('System setting updated successfully', [
                'id' => $setting->identifier,
                'key' => $setting->key
            ]);

            return (new SystemSettingResource($setting->load(['creator', 'updater'])))
                ->setMessage('System setting updated successfully')
                ->response();

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'System setting not found',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        } catch (\Throwable $e) {
            Log::error('Failed to update system setting', [
                'id' => $identifier,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update system setting',
                'data' => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a system setting.
     */
    public function destroy(string $identifier): JsonResponse
    {
        try {
            $setting = $this->repository->readSystemSetting($identifier);

            if (!$setting) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'System setting not found',
                    'data' => null,
                ], Response::HTTP_NOT_FOUND);
            }

            Gate::authorize('delete', $setting);

            Log::info('Deleting system setting', ['id' => $identifier]);

            DB::transaction(function () use ($identifier) {
                $this->repository->delete($identifier);
            });

            Log::info('System setting deleted successfully', ['id' => $identifier]);

            return response()->json([
                'status' => 'success',
                'message' => 'System setting deleted successfully',
                'data' => null,
            ], Response::HTTP_OK);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'System setting not found',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        } catch (\Throwable $e) {
            Log::error('Failed to delete system setting', [
                'id' => $identifier,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete system setting',
                'data' => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
```

### 9. Factory & Seeder

**Location**: `database/factories/SystemSettingFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SystemSettingFactory extends Factory
{
    protected $model = SystemSetting::class;

    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->slug(2),
            'value' => $this->faker->sentence(),
            'category' => $this->faker->randomElement(['general', 'email', 'security', 'billing']),
            'description' => $this->faker->sentence(),
            'is_public' => $this->faker->boolean(30), // 30% chance of being public
            'created_by' => User::factory(),
        ];
    }

    public function public(): static
    {
        return $this->state(['is_public' => true]);
    }

    public function private(): static
    {
        return $this->state(['is_public' => false]);
    }

    public function category(string $category): static
    {
        return $this->state(['category' => $category]);
    }
}
```

**Location**: `database/seeders/SystemSettingSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();

        $settings = [
            // Email settings
            [
                'key' => 'email.driver',
                'value' => 'smtp',
                'category' => 'email',
                'description' => 'Email driver configuration',
                'is_public' => false,
            ],
            [
                'key' => 'email.from_address',
                'value' => 'noreply@aias.com',
                'category' => 'email',
                'description' => 'Default from email address',
                'is_public' => true,
            ],

            // Security settings
            [
                'key' => 'security.session_timeout',
                'value' => '3600',
                'category' => 'security',
                'description' => 'Session timeout in seconds',
                'is_public' => false,
            ],
            [
                'key' => 'security.password_min_length',
                'value' => '8',
                'category' => 'security',
                'description' => 'Minimum password length',
                'is_public' => true,
            ],

            // General settings
            [
                'key' => 'app.name',
                'value' => 'AIAS',
                'category' => 'general',
                'description' => 'Application name',
                'is_public' => true,
            ],
            [
                'key' => 'app.timezone',
                'value' => 'UTC',
                'category' => 'general',
                'description' => 'Default application timezone',
                'is_public' => true,
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::create(array_merge($setting, [
                'created_by' => $admin?->id,
            ]));
        }
    }
}
```

### 10. Routes & Permissions

**Location**: `routes/api.php` (Add to existing routes)

```php
// System Settings Management Routes (Central - No tenant context required)
Route::prefix('system-settings')->group(function () {
    // List all system settings
    Route::get('/', [SystemSettingController::class, 'index'])
        ->name('api.system-settings.index');
    // Create a new system setting
    Route::post('/', [SystemSettingController::class, 'store'])
        ->name('api.system-settings.store');
    // Get a specific system setting
    Route::get('/{identifier}', [SystemSettingController::class, 'show'])
        ->name('api.system-settings.show');
    // Update a system setting
    Route::match(['put', 'patch'], '/{identifier}', [SystemSettingController::class, 'update'])
        ->name('api.system-settings.update');
    // Delete a system setting
    Route::delete('/{identifier}', [SystemSettingController::class, 'destroy'])
        ->name('api.system-settings.destroy');
});
```

**Location**: `config/role-permission-map.php` (Update existing configuration)

```php
'permissions' => [
    // Add to existing permissions array
    'system' => ['view', 'create', 'edit', 'delete', 'manage'],

    // ... existing permissions
],

'roles' => [
    'super-admin' => [
        // Add to existing permissions
        'system.*',
        // ... existing permissions
    ],

    'tenant-admin' => [
        // Add to existing permissions
        'system.view', // Can view public settings only
        // ... existing permissions
    ],

    // ... existing roles
],
```

## Testing Structure

**Location**: `tests/Feature/SystemSettingTest.php`

```php
<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenancy;

class SystemSettingTest extends TestCase
{
    use RefreshDatabaseWithTenancy;

    public function test_super_admin_can_view_all_settings(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $publicSetting = SystemSetting::factory()->public()->create();
        $privateSetting = SystemSetting::factory()->private()->create();

        $response = $this->actingAs($admin, 'api')
            ->getJson('/api/system-settings');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.data');
    }

    public function test_regular_user_can_only_view_public_settings(): void
    {
        $user = User::factory()->create();
        $user->assignRole('tenant-user');
        $user->givePermissionTo('system.view');

        SystemSetting::factory()->public()->create();
        SystemSetting::factory()->private()->create();

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/system-settings');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data'); // Only public setting visible
    }

    public function test_can_create_system_setting(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $data = [
            'key' => 'test.setting',
            'value' => 'test value',
            'category' => 'test',
            'description' => 'Test setting description',
            'is_public' => true,
        ];

        $response = $this->actingAs($admin, 'api')
            ->postJson('/api/system-settings', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.key', 'test.setting')
            ->assertJsonPath('data.value', 'test value');

        $this->assertDatabaseHas('system_settings', [
            'key' => 'test.setting',
            'value' => 'test value',
            'created_by' => $admin->id,
        ]);
    }

    public function test_can_update_system_setting(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $setting = SystemSetting::factory()->create([
            'key' => 'original.key',
            'value' => 'original value',
        ]);

        $data = [
            'value' => 'updated value',
            'description' => 'Updated description',
        ];

        $response = $this->actingAs($admin, 'api')
            ->patchJson("/api/system-settings/{$setting->identifier}", $data);

        $response->assertStatus(200)
            ->assertJsonPath('data.value', 'updated value');

        $this->assertDatabaseHas('system_settings', [
            'identifier' => $setting->identifier,
            'value' => 'updated value',
            'updated_by' => $admin->id,
        ]);
    }

    public function test_can_delete_system_setting(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $setting = SystemSetting::factory()->create();

        $response = $this->actingAs($admin, 'api')
            ->deleteJson("/api/system-settings/{$setting->identifier}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('system_settings', [
            'identifier' => $setting->identifier,
        ]);
    }

    public function test_filters_work_correctly(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        SystemSetting::factory()->create(['category' => 'email', 'key' => 'smtp.host']);
        SystemSetting::factory()->create(['category' => 'security', 'key' => 'session.timeout']);
        SystemSetting::factory()->create(['category' => 'email', 'key' => 'smtp.port']);

        // Test category filter
        $response = $this->actingAs($admin, 'api')
            ->getJson('/api/system-settings?category=email');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.data');

        // Test search filter
        $response = $this->actingAs($admin, 'api')
            ->getJson('/api/system-settings?search=smtp');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.data');
    }

    public function test_unauthorized_user_cannot_access_settings(): void
    {
        $user = User::factory()->create();
        // No permissions assigned

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/system-settings');

        $response->assertStatus(403);
    }

    public function test_cannot_create_duplicate_key(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        SystemSetting::factory()->create(['key' => 'duplicate.key']);

        $data = [
            'key' => 'duplicate.key',
            'value' => 'different value',
            'category' => 'test',
        ];

        $response = $this->actingAs($admin, 'api')
            ->postJson('/api/system-settings', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['key']);
    }
}
```

**Location**: `tests/Unit/SystemSettingTest.php`

```php
<?php

namespace Tests\Unit;

use App\Models\SystemSetting;
use App\Models\User;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenancy;

class SystemSettingTest extends TestCase
{
    use RefreshDatabaseWithTenancy;

    public function test_auto_generates_identifier_on_creation(): void
    {
        $setting = SystemSetting::factory()->create();

        $this->assertNotNull($setting->identifier);
        $this->assertIsString($setting->identifier);
    }

    public function test_decoded_value_accessor_works_with_json(): void
    {
        $jsonData = ['key' => 'value', 'nested' => ['data' => true]];

        $setting = SystemSetting::factory()->create([
            'value' => json_encode($jsonData)
        ]);

        $this->assertEquals($jsonData, $setting->decoded_value);
    }

    public function test_decoded_value_accessor_returns_string_for_non_json(): void
    {
        $setting = SystemSetting::factory()->create([
            'value' => 'simple string value'
        ]);

        $this->assertEquals('simple string value', $setting->decoded_value);
    }

    public function test_route_key_uses_identifier(): void
    {
        $setting = SystemSetting::factory()->create();

        $this->assertEquals('identifier', $setting->getRouteKeyName());
        $this->assertEquals($setting->identifier, $setting->getRouteKey());
    }

    public function test_scopes_work_correctly(): void
    {
        $publicSetting = SystemSetting::factory()->public()->create();
        $privateSetting = SystemSetting::factory()->private()->create();
        $emailSetting = SystemSetting::factory()->category('email')->create();

        $this->assertCount(1, SystemSetting::public()->get());
        $this->assertCount(1, SystemSetting::byCategory('email')->get());
    }

    public function test_relationships_are_properly_defined(): void
    {
        $user = User::factory()->create();
        $setting = SystemSetting::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->assertInstanceOf(User::class, $setting->creator);
        $this->assertInstanceOf(User::class, $setting->updater);
        $this->assertEquals($user->id, $setting->creator->id);
        $this->assertEquals($user->id, $setting->updater->id);
    }
}
```

## Best Practices

### 1. Database Design

- **Use UUID identifiers** for external API references
- **Include creator tracking** with `created_by` and `updated_by` fields
- **Add soft deletes** for data recovery and audit compliance
- **Create performance indexes** on frequently queried fields
- **Avoid foreign key constraints** to central database from tenant databases

### 2. Security

- **Implement role-based access control** using Spatie Permission
- **Use policy authorization** for all CRUD operations
- **Protect sensitive data** with `is_public` flags or similar mechanisms
- **Validate all input** using Form Request classes
- **Log all operations** for audit trails

### 3. Performance

- **Use eager loading** to prevent N+1 queries
- **Implement repository pattern** for consistent data access
- **Add database indexes** for common query patterns
- **Paginate large datasets** with reasonable limits
- **Cache frequently accessed settings** when appropriate

### 4. Testing

- **Use the `RefreshDatabaseWithTenancy` trait** for proper test database handling
- **Test both unit and feature scenarios** for comprehensive coverage
- **Verify access control** works correctly for different user roles
- **Test filter functionality** with various query combinations
- **Include edge cases** like duplicate keys, invalid data, unauthorized access

### 5. Documentation

- **Document all API endpoints** with comprehensive examples
- **Include permission requirements** for each operation
- **Provide clear error messages** for validation failures
- **Document business logic** in model and repository methods
- **Maintain up-to-date OpenAPI specifications**

## Common Patterns

### Settings Pattern

For key-value configuration data shared across tenants:

```php
// Get setting value with default
$timeout = app(SystemSettingRepository::class)->getSettingValue('session.timeout', 3600);

// Set setting value
app(SystemSettingRepository::class)->setSetting('app.maintenance', true, 'general', false);
```

### Reference Data Pattern

For global lookup tables (countries, currencies, etc.):

```php
// In Model
protected $connection = 'central';

// In Repository - no tenant filtering
public function browseCountries(CountryFilters $filters, ...): Paginator
{
    $query = $this->query();
    // No tenant filtering needed
    $filters->apply($query);
    return $query->paginate(...);
}
```

### User Management Pattern

For central user data with tenant relationships:

```php
// User belongs to tenant but stored centrally
class User extends Model
{
    protected $connection = 'central';

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
```

## Migration Commands

```bash
# Create central model migration
php artisan make:migration create_system_settings_table

# Create central model with factory and seeder
php artisan make:model SystemSetting -mfs

# Run central migrations
php artisan migrate

# Seed central data
php artisan db:seed --class=SystemSettingSeeder
```

## Summary

Central models in AIAS provide:

- **Global shared data** accessible across all tenants
- **Role-based access control** instead of tenant isolation
- **Full audit trails** using the Auditable trait
- **Consistent API patterns** following repository and filter patterns
- **Comprehensive testing** ensuring security and functionality

This architecture ensures proper separation between global shared data and tenant-specific data while maintaining security, performance, and maintainability.
