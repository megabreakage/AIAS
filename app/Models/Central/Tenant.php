<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\TenantStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    /** @var list<string> */
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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'status' => TenantStatus::class,
            'owner_id' => 'integer',
            'country_id' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    /** @return list<string> */
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

    public function getRouteKeyName(): string
    {
        return 'identifier';
    }

    public function getTenantKeyName(): string
    {
        return 'identifier';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
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
