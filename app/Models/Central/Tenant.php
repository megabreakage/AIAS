<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

final class Tenant extends BaseTenant implements AuditableContract, TenantWithDatabase
{
    use Auditable, HasDatabase, HasDomains, HasFactory, SoftDeletes;

    protected $connection = 'central';

    protected $keyType = 'int';

    protected $fillable = [
        'identifier',
        'owner_id',
        'name',
        'domain',
        'logo',
        'country_id',
        'data_center',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'data' => 'array',
        'status' => TenantStatus::class,
        'owner_id' => 'integer',
        'country_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'identifier',
            'owner_id',
            'reference_number',
            'name',
            'domain',
            'logo',
            'country_id',
            'data_center',
            'created_by',
            'updated_by',
            'created_at',
            'updated_at',
            'deleted_at',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (Tenant $tenant): void {
            $tenant->identifier ??= (string) Str::uuid();

            if (auth()->check()) {
                $tenant->created_by ??= auth()->id();
                $tenant->updated_by ??= auth()->id();
            }
        });

        self::created(function (Tenant $tenant): void {
            if (empty($tenant->reference_number)) {
                $tenant->reference_number = 'AT-'.$tenant->id.'-'.time();
                $tenant->saveQuietly();
            }
        });

        self::updating(function (Tenant $tenant): void {
            if (auth()->check()) {
                $tenant->updated_by = auth()->id();
            }
        });
    }

    /**
     * Get the route key name for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'identifier';
    }

    /**
     * Get the user who created the record.
     */
    public function createdBy()
    {
        return $this->belongsTo(Tenant::class, 'created_by');
    }

    /**
     * Get the user who last updated the record.
     */
    public function updatedBy()
    {
        return $this->belongsTo(Tenant::class, 'updated_by');
    }

    /**
     * Get the user who created the record.
     */
    public function creator()
    {
        return $this->belongsTo(Tenant::class, 'created_by');
    }

    /**
     * Get the user who last updated the record.
     */
    public function updater()
    {
        return $this->belongsTo(Tenant::class, 'updated_by');
    }

    /**
     * Scope to get records created by a specific user.
     */
    public function scopeCreatedBy($query, $userId)
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Scope to get records updated by a specific user.
     */
    public function scopeUpdatedBy($query, $userId)
    {
        return $query->where('updated_by', $userId);
    }
}
