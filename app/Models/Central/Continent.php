<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Support\Concerns\HasAuditTrail;
use App\Support\Concerns\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use OwenIt\Auditing\Contracts\Auditable;

final class Continent extends Model implements Auditable
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

    public function getRouteKeyName(): string
    {
        return 'identifier';
    }

    protected static function booted(): void
    {
        self::creating(function (self $continent): void {
            if (empty($continent->slug)) {
                $continent->slug = Str::slug($continent->name);
            }
        });

        self::updating(function (self $continent): void {
            if ($continent->isDirty('name') && !$continent->isDirty('slug')) {
                $continent->slug = Str::slug($continent->name);
            }
        });
    }

    public function countries(): HasMany
    {
        return $this->hasMany(Country::class);
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
