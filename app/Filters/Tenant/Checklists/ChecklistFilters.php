<?php

declare(strict_types=1);

namespace App\Filters\Tenant\Checklists;

use App\Filters\EloquentFilter;
use App\Filters\Tenant\Checklists\Filters\IsActiveFilter;
use App\Filters\Tenant\Checklists\Filters\IsFeaturedFilter;
use App\Filters\Tenant\Checklists\Filters\SearchTermFilter;

class ChecklistFilters extends EloquentFilter
{
    protected array $filters = [
        'search' => SearchTermFilter::class,
        'is_active' => IsActiveFilter::class,
        'is_featured' => IsFeaturedFilter::class,
    ];
}
