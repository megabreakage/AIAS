<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Concerns\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class SectionStyle extends Model
{
    use HasFactory;
    use HasUuidIdentifier;
    use SoftDeletes;

    protected $table = 'section_styles';

    protected $fillable = [
        'identifier',
        'tenant_id',
        'name',
        'description',
        'style_code',
        'font_size',
        'font_weight',
        'color',
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

    public function checklistSectionStyles(): HasMany
    {
        return $this->hasMany(ChecklistSectionStyle::class, 'section_style_id');
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
