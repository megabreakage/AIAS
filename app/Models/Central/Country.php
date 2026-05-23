<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

final class Country extends BaseModel implements AuditableContract
{
    use Auditable;

    protected $connection = 'central';

    /** @var list<string> */
    protected $fillable = [
        'identifier',
        'name',
        'slug',
        'continent_id',
        'short_code',
        'iso_code',
        'currency',
        'currency_name',
        'currency_sign',
        'country_code',
        'phone_digits',
        'status',
        'created_by',
        'updated_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'continent_id' => 'integer',
            'phone_digits' => 'integer',
            'status' => 'boolean',
        ];
    }

    public function continent(): BelongsTo
    {
        return $this->belongsTo(Continent::class);
    }
}
