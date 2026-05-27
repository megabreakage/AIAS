<?php

declare(strict_types=1);

namespace App\Filters\Tenant\SectionStyles\Filters;

use App\Filters\EloquentFilter;
use Illuminate\Database\Eloquent\Builder;

class IsActiveFilter extends EloquentFilter
{
    public function __construct(protected string $isActive) {}

    public function apply(Builder $query): Builder
    {
        return $query->where('is_active', filter_var($this->isActive, FILTER_VALIDATE_BOOLEAN));
    }
}
