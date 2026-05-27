<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Models\BaseModel;
use Database\Factories\Central\SectionStyleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

final class SectionStyle extends BaseModel implements AuditableContract
{
    use Auditable;

    /** @use HasFactory<SectionStyleFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $connection = 'central';

    /** @var list<string> */
    protected $fillable = [
        'identifier',
        'name',
        'description',
        'columns',
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
            'columns' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }
}
