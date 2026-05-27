<?php

declare(strict_types=1);

namespace App\Filters\PriorityLevels\Filters;

use App\Filters\EloquentFilter;
use Illuminate\Database\Eloquent\Builder;

final class IsActiveFilter extends EloquentFilter
{
    public function __construct(
        protected bool $isActive
    ) {}

    public function apply(Builder $query): Builder
    {
        return $query->where('is_active', $this->isActive);
    }
}
