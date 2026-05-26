<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\BaseModel;
use App\Models\Concerns\TenantConnection;
use Database\Factories\ChecklistTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ChecklistType extends BaseModel
{
    /** @use HasFactory<ChecklistTypeFactory> */
    use HasFactory;
    use SoftDeletes;
    use TenantConnection;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'name',
        'description',
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
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'identifier';
    }
}
