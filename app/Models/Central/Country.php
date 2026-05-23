<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Models\BaseModel;
use App\Support\Concerns\HasAuditTrail;
use App\Support\Concerns\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

final class Country extends BaseModel implements Auditable
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
        'continent_id',
        'short_code',
        'iso_code',
        'currency',
        'currency_name',
        'currency_sign',
        'country_code',
        'phone_digits',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'continent_id' => 'integer',
            'phone_digits' => 'integer',
            'status' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function continent(): BelongsTo
    {
        return $this->belongsTo(Continent::class);
    }
}
