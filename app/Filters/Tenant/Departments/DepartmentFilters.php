<?php

declare(strict_types=1);

namespace App\Filters\Tenant\Departments;

use App\Filters\EloquentFilter;
use App\Filters\Tenant\Departments\Filters\IsActiveFilter;
use App\Filters\Tenant\Departments\Filters\IsFeaturedFilter;
use App\Filters\Tenant\Departments\Filters\SearchTermFilter;

class DepartmentFilters extends EloquentFilter
{
    protected array $filters = [
        'search' => SearchTermFilter::class,
        'is_active' => IsActiveFilter::class,
        'is_featured' => IsFeaturedFilter::class,
    ];
}
