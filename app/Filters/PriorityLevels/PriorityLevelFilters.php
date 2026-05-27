<?php

declare(strict_types=1);

namespace App\Filters\PriorityLevels;

use App\Filters\EloquentFilter;
use App\Filters\PriorityLevels\Filters\IsActiveFilter;
use App\Filters\PriorityLevels\Filters\SearchTermFilter;

final class PriorityLevelFilters extends EloquentFilter
{
    protected array $filters = [
        'search' => SearchTermFilter::class,
        'is_active' => IsActiveFilter::class,
    ];
}
