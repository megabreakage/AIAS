<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

final class Continent extends BaseModel implements AuditableContract
{
    use Auditable;

    protected $connection = 'central';

    /** @var list<string> */
    protected $fillable = [
        'identifier',
        'name',
        'slug',
        'short_code',
        'iso_code',
        'status',
        'created_by',
        'updated_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'status' => 'boolean',
        ];
    }

    public function countries(): HasMany
    {
        return $this->hasMany(Country::class);
    }
}
