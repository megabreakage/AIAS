<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

final class Tenant extends BaseTenant implements AuditableContract, TenantWithDatabase
{
    use Auditable, HasDatabase, HasDomains, HasFactory, SoftDeletes;

    protected $fillable = [
        'id',
        'owner_id',
        'name',
        'logo',
        'status',
    ];

    protected $casts = [
        'data' => 'array',
        'status' => TenantStatus::class,
    ];

    protected $connection = 'central';

    public static function getCustomColumns(): array
    {
        return ['id', 'name', 'plan', 'status', 'created_at', 'updated_at', 'deleted_at'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
