<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Central\Tenant;
use App\Models\Concerns\CentralConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Passport\HasApiTokens;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\Permission\Traits\HasRoles;

final class User extends Authenticatable implements AuditableContract
{
    use Auditable;
    use CentralConnection;
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    protected $table = 'users';

    /** @var list<string> */
    protected $fillable = [
        'identifier',
        'tenant_id',
        'title',
        'first_name',
        'middle_name',
        'last_name',
        'username',
        'email',
        'email_verified_at',
        'country_code',
        'phone',
        'password',
        'preferred_timezone',
        'office_location',
        'is_active',
        'avatar',
        'notes',
        'last_login_at',
        'created_by',
        'updated_by',
    ];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
            'tenant_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            if (empty($model->identifier)) {
                $model->identifier = (string) Str::uuid();
            }

            if (empty($model->created_by) && Auth::check()) {
                $model->created_by = Auth::id();
            }
        });

        self::updating(function (self $model): void {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'identifier';
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name])));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function ownedTenant(): HasOne
    {
        return $this->hasOne(Tenant::class, 'owner_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->morphToMany(
            config('permission.models.role'),
            'model',
            config('permission.table_names.model_has_roles'),
            config('permission.column_names.model_morph_key'),
            'role_id'
        )->withPivot(config('permission.column_names.team_foreign_key'))
            ->wherePivot(config('permission.column_names.team_foreign_key'), getPermissionsTeamId());
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(self::class, 'updated_by');
    }
}
