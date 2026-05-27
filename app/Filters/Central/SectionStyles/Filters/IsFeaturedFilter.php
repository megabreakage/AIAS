<?php

declare(strict_types=1);

namespace App\Filters\Central\SectionStyles\Filters;

use App\Filters\EloquentFilter;
use Illuminate\Database\Eloquent\Builder;

class IsFeaturedFilter extends EloquentFilter
{
    public function __construct(protected string $isFeatured) {}

    public function apply(Builder $query): Builder
    {
        return $query->where('is_featured', filter_var($this->isFeatured, FILTER_VALIDATE_BOOLEAN));
    }
}
