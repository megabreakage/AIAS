<?php

declare(strict_types=1);

namespace App\Filters\Tenant\Companies\Filters;

use App\Enums\LevelOfOperations;
use App\Filters\EloquentFilter;
use Illuminate\Database\Eloquent\Builder;

class LevelOfOperationsFilter extends EloquentFilter
{
    public function __construct(protected string $levelOfOperations) {}

    public function apply(Builder $query): Builder
    {
        $value = LevelOfOperations::tryFrom($this->levelOfOperations);

        if (! $value) {
            return $query;
        }

        return $query->where('level_of_operations', $value->value);
    }
}
