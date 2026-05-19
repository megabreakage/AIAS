<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\BaseModel;
use App\Support\Concerns\HasUuidIdentifier;

class Preamble extends BaseModel
{
    use HasUuidIdentifier;

    // Status constants
    public const string STATUS_DRAFT = 'draft';

    public const string STATUS_ACTIVE = 'active';

    public const string STATUS_ARCHIVED = 'archived';

    public const array STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_ARCHIVED,
    ];

    // id and identifier are not fillable — id is DB-generated, identifier is set by HasUuidIdentifier
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

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'is_featured' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Generate the reference number for this preamble.
     * Format: PR-{id}-{unix_timestamp}
     */
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
