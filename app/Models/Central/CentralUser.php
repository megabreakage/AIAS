<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Support\Concerns\HasAuditTrail;
use App\Support\Concerns\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Permission\Traits\HasRoles;

final class CentralUser extends Model implements Auditable
{
    use HasAuditTrail;
    use HasFactory;
    use HasRoles;
    use HasUuidIdentifier;
    use Notifiable;
    use SoftDeletes;

    protected $connection = 'central';

    protected $table = 'users';

    protected $fillable = [
        'identifier',
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

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'is_active'         => 'boolean',
            'password'          => 'hashed',
            'created_at'        => 'datetime',
            'updated_at'        => 'datetime',
            'deleted_at'        => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'identifier';
    }

    public function guardName(): string
    {
        return 'api';
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name])));
    }

    public function tenant(): HasOne
    {
        return $this->hasOne(Tenant::class, 'owner_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'updated_by');
    }
}
