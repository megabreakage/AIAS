# AIAS Architectural Patterns

Complete architectural patterns for the Adaptive Intelligent Audit System (AIAS). All code MUST follow these patterns exactly.

---

## 1. Model Patterns

### 1a. Central Database Model

Central models reside in the central database, are shared across all tenants, and **DO** use the `Auditable` trait.

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

class AuditStandard extends Model implements AuditableContract
{
    use Auditable, HasFactory, SoftDeletes;

    protected $connection = 'central';

    protected $fillable = [
        'identifier',
        'name',
        'code',
        'description',
        'issuing_body',
        'version',
        'effective_date',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'effective_date' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'identifier';
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->identifier)) {
                $model->identifier = Str::uuid()->toString();
            }
            if (Auth::check()) {
                $model->created_by = Auth::id();
            }
        });

        static::updating(function (self $model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
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
}
```

**Key Rules for Central Models:**

- `protected $connection = 'central';` — explicit central connection
- `Auditable` trait — YES for central models
- NO `tenant_id` field — not tenant-scoped
- UUID `identifier` for external API references
- `created_by` / `updated_by` for creator tracking
- `SoftDeletes` for data recovery
- Migration location: `database/migrations/`

### 1b. Tenant Database Model

Tenant models reside in per-tenant databases and **DO NOT** use the `Auditable` trait.

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

class AuditEngagement extends Model
{
    use HasFactory, SoftDeletes, TenantConnection;

    protected $fillable = [
        'identifier',
        'tenant_id',
        'title',
        'description',
        'engagement_number',
        'client_id',
        'lead_auditor_id',
        'audit_type',
        'status',
        'start_date',
        'end_date',
        'risk_level',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => \App\Enums\EngagementStatus::class,
            'audit_type' => \App\Enums\AuditType::class,
            'risk_level' => \App\Enums\RiskLevel::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (is_null($model->identifier)) {
                $model->identifier = (string) Str::uuid();
            }

            if (tenancy()->tenant) {
                $model->tenant_id = tenancy()->tenant->getTenantKey();

                if (is_null($model->created_by) && auth()->check()) {
                    $model->created_by = auth()->id();
                }
            }
        });

        static::updating(function (self $model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
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

    public function leadAuditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_auditor_id');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class, 'engagement_id');
    }

    public function workpapers(): HasMany
    {
        return $this->hasMany(Workpaper::class, 'engagement_id');
    }

    public function auditProcedures(): HasMany
    {
        return $this->hasMany(AuditProcedure::class, 'engagement_id');
    }
}
```

**Key Rules for Tenant Models:**

- `TenantConnection` trait — uses tenant database connection
- NO `Auditable` trait — never for tenant models
- `tenant_id` field — auto-set from tenancy context in `creating` boot
- UUID `identifier` for external API references
- `created_by` / `updated_by` for creator tracking (references central users as plain integers, NO FK constraints)
- `SoftDeletes` for data recovery
- Migration location: `database/migrations/tenant/`
- **NEVER** create FK constraints from tenant tables to central database tables

### 1c. User Model (Central, Special Case)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
            if (!app()->runningInConsole() && Auth::check()) {
                $user->created_by = Auth::id();
            }
        });

        static::updating(function (User $user) {
            if (!app()->runningInConsole() && Auth::check()) {
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

---

## 2. Migration Patterns

### 2a. Central Migration

Location: `database/migrations/`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_standards', function (Blueprint $table) {
            $table->id();
            $table->uuid('identifier')->unique();

            // Business fields
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('issuing_body');
            $table->string('version')->nullable();
            $table->date('effective_date')->nullable();
            $table->boolean('status')->default(true);

            // NO tenant_id — this is central data
            // Creator tracking — references central users table
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Performance indexes
            $table->index('code');
            $table->index('issuing_body');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_standards');
    }
};
```

### 2b. Tenant Migration

Location: `database/migrations/tenant/`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_engagements', function (Blueprint $table) {
            $table->id();
            $table->uuid('identifier')->unique();

            // Tenant reference — stored as plain string, NO FK to central DB
            $table->string('tenant_id');

            // Business fields
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('engagement_number')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('lead_auditor_id')->nullable();
            $table->string('audit_type');
            $table->string('status')->default('planning');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('risk_level')->nullable();

            // Creator tracking — plain integers, NO FK to central users table
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // ⚠️ NEVER create FK constraints to central database tables
            // $table->foreign('created_by')->references('id')->on('users'); // ❌ NEVER

            // Performance indexes
            $table->index('tenant_id');
            $table->index('status');
            $table->index('audit_type');
            $table->index('client_id');
            $table->index('lead_auditor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_engagements');
    }
};
```

---

## 3. Repository Pattern

### 3a. Base Repository

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
            if ($fail && !$model) {
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

### 3b. Central Repository (No Tenant Filtering)

```php
<?php

namespace App\Repositories;

use App\Filters\AuditStandards\AuditStandardFilters;
use App\Models\AuditStandard;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;

class AuditStandardRepository extends BaseRepository
{
    protected array $dispatchesEvents = [];

    public function getClassName(): Model|string
    {
        return AuditStandard::class;
    }

    /**
     * Browse audit standards — central data, no tenant filtering.
     */
    public function browseAuditStandards(
        AuditStandardFilters $filters,
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

    public function readAuditStandard(int|string $id, array $with = []): Model
    {
        return $this->read($id, array_merge($with, ['creator', 'updater']));
    }

    public function createAuditStandard(array $data): AuditStandard|Model|bool
    {
        $standard = self::make($data);
        if ($standard->save()) {
            return $standard->load(['creator', 'updater']);
        }
        return false;
    }
}
```

### 3c. Tenant Repository (Mandatory Tenant Filtering)

```php
<?php

namespace App\Repositories;

use App\Filters\AuditEngagements\AuditEngagementFilters;
use App\Models\AuditEngagement;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;

class AuditEngagementRepository extends BaseRepository
{
    protected array $dispatchesEvents = [];

    public function getClassName(): Model|string
    {
        return AuditEngagement::class;
    }

    /**
     * Browse audit engagements with tenant filtering.
     */
    public function browseEngagements(
        AuditEngagementFilters $filters,
        int $page = 1,
        int $perPage = 20,
        ?string $sortBy = null,
        bool $sortDesc = false
    ): Paginator {
        $query = $this->query()->with(['creator', 'leadAuditor']);

        // Mandatory tenant filtering for non-super-admin users
        if (!auth()->user()->hasRole('super-admin')) {
            $query->where('tenant_id', auth()->user()->tenant_id);
        }

        $filters->apply($query);

        if ($sortBy) {
            $query->orderBy($sortBy, $sortDesc ? 'desc' : 'asc');
        }

        return $query->paginate(perPage: min($perPage, 100), page: max($page, 1));
    }

    public function readEngagement(int|string $id, array $with = []): Model
    {
        return $this->read($id, array_merge($with, ['creator', 'leadAuditor', 'findings']));
    }

    public function createEngagement(array $data): AuditEngagement|Model|bool
    {
        $engagement = self::make($data);
        if ($engagement->save()) {
            return $engagement->load(['creator', 'leadAuditor']);
        }
        return false;
    }

    public function updateEngagement(int|string $id, array $data): AuditEngagement|Model|bool
    {
        return $this->update($id, $data);
    }
}
```

---

## 4. Filter Pattern

### 4a. Base Filter

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
            if (!array_key_exists($key, $params) || $params[$key] === null || $params[$key] === '') {
                continue;
            }
            $this->active[] = new $filterClass($params[$key]);
        }
    }

    public static function fromRequest(Request $request): static
    {
        $instance = new static;
        foreach ($instance->filters as $key => $filterClass) {
            if (!$request->filled($key)) {
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

### 4b. Module Filter Set

```php
<?php

namespace App\Filters\AuditEngagements;

use App\Filters\EloquentFilter;
use App\Filters\AuditEngagements\Filters\SearchTermFilter;
use App\Filters\AuditEngagements\Filters\StatusFilter;

class AuditEngagementFilters extends EloquentFilter
{
    protected array $filters = [
        'search' => SearchTermFilter::class,
        'status' => StatusFilter::class,
    ];
}
```

### 4c. Individual Filter

```php
<?php

namespace App\Filters\AuditEngagements\Filters;

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
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhere('engagement_number', 'like', "%{$search}%");
        });
    }
}
```

---

## 5. Resource Pattern

### 5a. Base Resource

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

### 5b. Domain Resource

```php
<?php

namespace App\Http\Resources\AuditEngagements;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class AuditEngagementResource extends BaseResource
{
    protected function resourceData(Request $request): array
    {
        return [
            'id' => $this->identifier,
            'title' => $this->title,
            'description' => $this->description,
            'engagement_number' => $this->engagement_number,
            'audit_type' => $this->audit_type,
            'status' => $this->status,
            'risk_level' => $this->risk_level,
            'start_date' => $this->start_date?->toISOString(),
            'end_date' => $this->end_date?->toISOString(),
            'lead_auditor' => $this->whenLoaded('leadAuditor', fn() => [
                'id' => $this->leadAuditor->identifier,
                'name' => $this->leadAuditor->first_name . ' ' . $this->leadAuditor->last_name,
            ]),
            'findings_count' => $this->whenCounted('findings'),
            'creator' => $this->whenLoaded('creator', fn() => [
                'id' => $this->creator->identifier,
                'name' => $this->creator->first_name . ' ' . $this->creator->last_name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
```

### 5c. Collection Resource

```php
<?php

namespace App\Http\Resources\AuditEngagements;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class AuditEngagementCollection extends ResourceCollection
{
    public $collects = AuditEngagementResource::class;

    protected string $response_status = 'success';
    protected ?string $message = null;
    protected array $metadata = [];

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

    public function with(Request $request): array
    {
        return array_merge([
            'status' => $this->response_status,
            'message' => $this->message,
        ], $this->metadata);
    }
}
```

---

## 6. Form Request Pattern

### 6a. Create Request

```php
<?php

namespace App\Http\Requests\AuditEngagement;

use App\Enums\AuditType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CreateAuditEngagementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', \App\Models\AuditEngagement::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'client_id' => ['required', 'integer'],
            'lead_auditor_id' => ['nullable', 'integer'],
            'audit_type' => ['required', 'string', Rule::in(AuditType::values())],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'risk_level' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Engagement title is required.',
            'client_id.required' => 'Client selection is required.',
            'audit_type.required' => 'Audit type is required.',
            'start_date.required' => 'Start date is required.',
            'end_date.after' => 'End date must be after start date.',
        ];
    }
}
```

### 6b. Update Request

```php
<?php

namespace App\Http\Requests\AuditEngagement;

use App\Enums\AuditType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateAuditEngagementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $engagement = $this->route('audit_engagement');
        return Gate::allows('update', $engagement);
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'client_id' => ['sometimes', 'integer'],
            'lead_auditor_id' => ['nullable', 'integer'],
            'audit_type' => ['sometimes', 'string', Rule::in(AuditType::values())],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'risk_level' => ['nullable', 'string'],
            'status' => ['sometimes', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_date.after' => 'End date must be after start date.',
        ];
    }
}
```

---

## 7. Policy Pattern

```php
<?php

namespace App\Policies;

use App\Models\AuditEngagement;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AuditEngagementPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('audit-engagements.view');
    }

    public function view(User $user, AuditEngagement $engagement): bool
    {
        return $user->hasPermissionTo('audit-engagements.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('audit-engagements.create');
    }

    public function update(User $user, AuditEngagement $engagement): bool
    {
        return $user->hasPermissionTo('audit-engagements.edit');
    }

    public function delete(User $user, AuditEngagement $engagement): bool
    {
        return $user->hasPermissionTo('audit-engagements.delete');
    }

    public function restore(User $user, AuditEngagement $engagement): bool
    {
        return $user->hasPermissionTo('audit-engagements.delete');
    }
}
```

---

## 8. Controller Pattern

```php
<?php

namespace App\Http\Controllers;

use App\Filters\AuditEngagements\AuditEngagementFilters;
use App\Http\Requests\AuditEngagement\CreateAuditEngagementRequest;
use App\Http\Requests\AuditEngagement\UpdateAuditEngagementRequest;
use App\Http\Resources\AuditEngagements\AuditEngagementCollection;
use App\Http\Resources\AuditEngagements\AuditEngagementResource;
use App\Models\AuditEngagement;
use App\Repositories\AuditEngagementRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class AuditEngagementController extends Controller
{
    public function __construct(
        protected AuditEngagementRepository $repository
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', AuditEngagement::class);

        $filters = AuditEngagementFilters::fromRequest($request);

        $engagements = $this->repository->browseEngagements(
            filters: $filters,
            page: $request->integer('page', 1),
            perPage: $request->integer('per_page', 15),
            sortBy: $request->input('sort_by'),
            sortDesc: $request->input('sort_order') === 'desc'
        );

        return (new AuditEngagementCollection($engagements))
            ->setMessage('Audit engagements retrieved successfully')
            ->addMetadata('filters_applied', $request->only(['search', 'status', 'sort_by', 'sort_order']))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function store(CreateAuditEngagementRequest $request): JsonResponse
    {
        Gate::authorize('create', AuditEngagement::class);

        try {
            $data = $request->validated();

            Log::info('Creating audit engagement', ['title' => $data['title']]);

            $engagement = DB::transaction(function () use ($data) {
                return $this->repository->createEngagement($data);
            });

            Log::info('Audit engagement created', ['id' => $engagement->identifier]);

            return (new AuditEngagementResource($engagement))
                ->setMessage('Audit engagement created successfully')
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);

        } catch (\Throwable $e) {
            Log::error('Failed to create audit engagement', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create audit engagement',
                'data' => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $engagement = $this->repository->readEngagement($id);
            Gate::authorize('view', $engagement);

            return (new AuditEngagementResource($engagement))
                ->setMessage('Audit engagement retrieved successfully')
                ->response()
                ->setStatusCode(Response::HTTP_OK);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Audit engagement not found',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }
    }

    public function update(UpdateAuditEngagementRequest $request, string $id): JsonResponse
    {
        try {
            $engagement = $this->repository->readEngagement($id);
            Gate::authorize('update', $engagement);

            $data = $request->validated();

            Log::info('Updating audit engagement', ['id' => $id]);

            $engagement = DB::transaction(function () use ($id, $data) {
                return $this->repository->updateEngagement($id, $data);
            });

            Log::info('Audit engagement updated', ['id' => $engagement->identifier]);

            return (new AuditEngagementResource($engagement))
                ->setMessage('Audit engagement updated successfully')
                ->response()
                ->setStatusCode(Response::HTTP_OK);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Audit engagement not found',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        } catch (\Throwable $e) {
            Log::error('Failed to update audit engagement', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update audit engagement',
                'data' => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $engagement = $this->repository->readEngagement($id);
            Gate::authorize('delete', $engagement);

            Log::info('Deleting audit engagement', ['id' => $id]);

            DB::transaction(function () use ($id) {
                $this->repository->delete($id);
            });

            Log::info('Audit engagement deleted', ['id' => $id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Audit engagement deleted successfully',
                'data' => null,
            ], Response::HTTP_OK);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Audit engagement not found',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        } catch (\Throwable $e) {
            Log::error('Failed to delete audit engagement', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete audit engagement',
                'data' => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
```

---

## 9. Factory Pattern

```php
<?php

namespace Database\Factories;

use App\Models\AuditEngagement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditEngagementFactory extends Factory
{
    protected $model = AuditEngagement::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'engagement_number' => 'AE-' . $this->faker->unique()->numerify('####'),
            'audit_type' => $this->faker->randomElement(['financial', 'compliance', 'operational', 'it']),
            'status' => 'planning',
            'risk_level' => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            'start_date' => $this->faker->dateTimeBetween('now', '+1 month'),
            'end_date' => $this->faker->dateTimeBetween('+2 months', '+6 months'),
            'created_by' => User::factory(),
        ];
    }

    public function inProgress(): static
    {
        return $this->state(['status' => 'in_progress']);
    }

    public function completed(): static
    {
        return $this->state(['status' => 'completed']);
    }

    public function highRisk(): static
    {
        return $this->state(['risk_level' => 'high']);
    }
}
```

---

## 10. Test Pattern

```php
<?php

namespace Tests\Feature;

use App\Models\AuditEngagement;
use App\Models\User;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenancy;

class AuditEngagementTest extends TestCase
{
    use RefreshDatabaseWithTenancy;

    public function test_can_list_audit_engagements(): void
    {
        $user = User::factory()->create();
        $user->assignRole('tenant-admin');

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/audit-engagements');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'message', 'data']);
    }

    public function test_can_create_audit_engagement(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $data = [
            'title' => 'Annual Financial Audit 2026',
            'description' => 'Year-end financial statement audit',
            'audit_type' => 'financial',
            'start_date' => '2026-01-15',
            'end_date' => '2026-03-31',
            'client_id' => 1,
        ];

        $response = $this->actingAs($admin, 'api')
            ->postJson('/api/audit-engagements', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Annual Financial Audit 2026');
    }

    public function test_cannot_access_other_tenant_engagements(): void
    {
        // Verify tenant isolation
    }

    public function test_unauthorized_user_cannot_create(): void
    {
        $user = User::factory()->create();
        // No permissions assigned

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/audit-engagements', ['title' => 'Test']);

        $response->assertStatus(403);
    }

    public function test_filters_work_correctly(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        AuditEngagement::factory()->create(['title' => 'SOX Compliance Audit']);
        AuditEngagement::factory()->create(['title' => 'IT Security Review']);

        $response = $this->actingAs($admin, 'api')
            ->getJson('/api/audit-engagements?search=SOX');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data');
    }
}
```

---

## 11. Route Pattern

```php
// routes/api.php

// Public routes
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

// Protected routes
Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Central resources (no tenant context)
    Route::apiResource('audit-standards', AuditStandardController::class);
    Route::apiResource('regulation-frameworks', RegulationFrameworkController::class);

    // Tenant-scoped resources
    Route::apiResource('audit-engagements', AuditEngagementController::class);
    Route::post('audit-engagements/{id}/close', [AuditEngagementController::class, 'close']);

    Route::apiResource('findings', FindingController::class);
    Route::post('findings/{id}/escalate', [FindingController::class, 'escalate']);

    Route::apiResource('risk-assessments', RiskAssessmentController::class);
    Route::apiResource('workpapers', WorkpaperController::class);

    // Admin routes
    Route::apiResource('tenants', TenantController::class);
    Route::apiResource('users', UserController::class);
    Route::apiResource('roles', RoleController::class);
});
```

---

## 12. Bootstrap/App Configuration

```php
// bootstrap/app.php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [
            SetSpatieTeamFromTenant::class,
            SentryContext::class,
        ]);

        $middleware->redirectGuestsTo(function ($request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                abort(401, 'Unauthenticated');
            }
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);
    })->create();
```

---

## Summary of Patterns

| Layer | Pattern | Location |
|-------|---------|----------|
| Model | Central / Tenant with traits | `app/Models/` |
| Migration | Central / Tenant directories | `database/migrations/` or `database/migrations/tenant/` |
| Repository | Base + domain repos | `app/Repositories/` |
| Filter | Composable filter classes | `app/Filters/{Domain}/` |
| Resource | BaseResource envelope | `app/Http/Resources/` |
| Request | Form Request classes | `app/Http/Requests/{Domain}/` |
| Policy | Permission-based policies | `app/Policies/` |
| Controller | Repository + Transaction pattern | `app/Http/Controllers/` |
| Factory | Model factories with states | `database/factories/` |
| Test | PHPUnit + RefreshDatabaseWithTenancy | `tests/Feature/` and `tests/Unit/` |
| Routes | API resource routes | `routes/api.php` |
| Config | Role-permission map | `config/permissions_map.php` |
