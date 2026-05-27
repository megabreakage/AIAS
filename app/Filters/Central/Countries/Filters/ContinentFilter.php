<?php

declare(strict_types=1);

namespace App\Filters\Central\Countries\Filters;

use App\Filters\EloquentFilter;
use Illuminate\Database\Eloquent\Builder;

class ContinentFilter extends EloquentFilter
{
    public function __construct(protected string $continentId) {}

    public function apply(Builder $query): Builder
    {
        return $query->where('continent_id', (int) $this->continentId);
    }
}
