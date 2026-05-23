<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\PreambleStatus;
use App\Models\BaseModel;
use App\Models\Concerns\TenantConnection;

final class Preamble extends BaseModel
{
    use TenantConnection;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'reference_number',
        'name',
        'description',
        'status',
        'effective_date',
        'is_featured',
        'created_by',
        'updated_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'status' => PreambleStatus::class,
            'effective_date' => 'date',
            'is_featured' => 'boolean',
        ];
    }

    public function generateReferenceNumber(): string
    {
        return 'PR-'.$this->id.'-'.now()->timestamp;
    }

    protected static function booted(): void
    {
        parent::booted();

        static::created(function (self $preamble): void {
            $preamble->updateQuietly([
                'reference_number' => $preamble->generateReferenceNumber(),
            ]);
        });
    }
}
