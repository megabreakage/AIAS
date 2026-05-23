<?php

declare(strict_types=1);

namespace App\Models\Central;

// use App\Models\BaseModel;
use App\Models\BaseModel;
use App\Support\Concerns\HasAuditTrail;
use App\Support\Concerns\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

final class Continent extends BaseModel implements Auditable
{
    use HasAuditTrail;
    use HasFactory;
    use HasUuidIdentifier;
    use SoftDeletes;

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

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function countries(): HasMany
    {
        return $this->hasMany(Country::class);
    }
}
