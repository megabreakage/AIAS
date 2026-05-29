<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\LevelOfOperations;
use App\Models\BaseModel;
use App\Models\Concerns\TenantConnection;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Company extends BaseModel
{
    /** @use HasFactory<CompanyFactory> */
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
        'level_of_operations',
        'trading_name',
        'website',
        'email',
        'phone',
        'logo',
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
            'level_of_operations' => LevelOfOperations::class,
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function generateReferenceNumber(): string
    {
        return 'CO-'.$this->id.'-'.now()->timestamp;
    }

    protected static function booted(): void
    {
        parent::booted();

        self::created(function (self $company): void {
            $company->updateQuietly([
                'reference_number' => $company->generateReferenceNumber(),
            ]);
        });
    }

    protected static function newFactory(): CompanyFactory
    {
        return CompanyFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'identifier';
    }

    /** @return HasMany<CompanyContact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(CompanyContact::class);
    }
}
