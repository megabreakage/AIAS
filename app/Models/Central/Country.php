<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Support\Concerns\HasAuditTrail;
use App\Support\Concerns\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use OwenIt\Auditing\Contracts\Auditable;

final class Country extends Model implements Auditable
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

    public function getRouteKeyName(): string
    {
        return 'identifier';
    }

    protected static function booted(): void
    {
        self::creating(function (self $country): void {
            if (empty($country->slug)) {
                $country->slug = Str::slug($country->name);
            }
        });

        self::updating(function (self $country): void {
            if ($country->isDirty('name') && !$country->isDirty('slug')) {
                $country->slug = Str::slug($country->name);
            }
        });
    }

    public function continent(): BelongsTo
    {
        return $this->belongsTo(Continent::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'updated_by');
    }
}
