<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Concerns\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Checklist extends Model
{
    use HasFactory;
    use HasUuidIdentifier;
    use SoftDeletes;

    protected $table = 'checklists';

    protected $fillable = [
        'identifier',
        'tenant_id',
        'checklist_type_id',
        'preamble_id',
        'title',
        'description',
        'version',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'identifier';
    }

    public function checklistType(): BelongsTo
    {
        return $this->belongsTo(ChecklistType::class, 'checklist_type_id');
    }

    public function preamble(): BelongsTo
    {
        return $this->belongsTo(Preamble::class, 'preamble_id');
    }

    public function sectionStyles(): HasMany
    {
        return $this->hasMany(ChecklistSectionStyle::class, 'checklist_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(Audit::class, 'checklist_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
