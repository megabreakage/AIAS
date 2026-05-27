<?php

declare(strict_types=1);

namespace App\Filters\Tenant\ChecklistTypes;

use App\Filters\EloquentFilter;
use App\Filters\Tenant\ChecklistTypes\Filters\IsActiveFilter;
use App\Filters\Tenant\ChecklistTypes\Filters\IsFeaturedFilter;
use App\Filters\Tenant\ChecklistTypes\Filters\SearchTermFilter;

class ChecklistTypeFilters extends EloquentFilter
{
    protected array $filters = [
        'search' => SearchTermFilter::class,
        'is_active' => IsActiveFilter::class,
        'is_featured' => IsFeaturedFilter::class,
    ];
}
