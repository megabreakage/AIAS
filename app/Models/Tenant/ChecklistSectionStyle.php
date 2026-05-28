<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\BaseModel;
use App\Models\Concerns\TenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ChecklistSectionStyle extends BaseModel
{
    use HasFactory;
    use SoftDeletes;
    use TenantConnection;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'checklist_id',
        'section_style_id',
        'section_title',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'checklist_id' => 'integer',
            'section_style_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'identifier';
    }

    /** @return BelongsTo<Checklist, $this> */
    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }

    /** @return BelongsTo<SectionStyle, $this> */
    public function sectionStyle(): BelongsTo
    {
        return $this->belongsTo(SectionStyle::class);
    }
}
