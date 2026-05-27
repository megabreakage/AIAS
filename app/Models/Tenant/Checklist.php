<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\BaseModel;
use App\Models\Concerns\TenantConnection;
use App\Models\User;
use Database\Factories\ChecklistFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Checklist extends BaseModel
{
    /** @use HasFactory<ChecklistFactory> */
    use HasFactory;

    use SoftDeletes;
    use TenantConnection;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'reference_number',
        'name',
        'quality_controller_id',
        'preamble_id',
        'checklist_type_id',
        'is_active',
        'is_featured',
        'created_by',
        'updated_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'quality_controller_id' => 'integer',
            'preamble_id' => 'integer',
            'checklist_type_id' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function generateReferenceNumber(): string
    {
        return 'CL-'.($this->preamble_id ?? $this->id).'-'.now()->timestamp;
    }

    protected static function booted(): void
    {
        parent::booted();

        self::created(function (self $checklist): void {
            $checklist->updateQuietly([
                'reference_number' => $checklist->generateReferenceNumber(),
            ]);
        });
    }

    protected static function newFactory(): ChecklistFactory
    {
        return ChecklistFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'identifier';
    }

    /** @return BelongsTo<Preamble, $this> */
    public function preamble(): BelongsTo
    {
        return $this->belongsTo(Preamble::class);
    }

    /** @return BelongsTo<ChecklistType, $this> */
    public function checklistType(): BelongsTo
    {
        return $this->belongsTo(ChecklistType::class);
    }

    /** @return BelongsTo<User, $this> */
    public function qualityController(): BelongsTo
    {
        return $this->belongsTo(User::class, 'quality_controller_id');
    }
}
