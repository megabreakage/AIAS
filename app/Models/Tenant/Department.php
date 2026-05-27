<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\BaseModel;
use App\Models\Concerns\TenantConnection;
use App\Models\User;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Department extends BaseModel
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    use SoftDeletes;
    use TenantConnection;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'reference_number',
        'name',
        'address',
        'office_location',
        'latitude',
        'longitude',
        'postal_code',
        'country_id',
        'department_head',
        'description',
        'is_active',
        'is_featured',
        'created_by',
        'updated_by',
    ];

    /** @return array<string, string|class-string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'latitude' => 'float',
            'longitude' => 'float',
            'country_id' => 'integer',
            'department_head' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function generateReferenceNumber(): string
    {
        return 'DP-'.$this->id.'-'.now()->timestamp;
    }

    protected static function booted(): void
    {
        parent::booted();

        self::created(function (self $department): void {
            $department->updateQuietly([
                'reference_number' => $department->generateReferenceNumber(),
            ]);
        });
    }

    protected static function newFactory(): DepartmentFactory
    {
        return DepartmentFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'identifier';
    }

    /** @return HasMany<DepartmentMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(DepartmentMember::class);
    }

    /** @return BelongsTo<User, $this> */
    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'department_head');
    }
}
