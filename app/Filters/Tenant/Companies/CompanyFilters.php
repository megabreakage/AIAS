<?php

declare(strict_types=1);

namespace App\Filters\Tenant\Companies;

use App\Filters\EloquentFilter;
use App\Filters\Tenant\Companies\Filters\IsActiveFilter;
use App\Filters\Tenant\Companies\Filters\IsFeaturedFilter;
use App\Filters\Tenant\Companies\Filters\LevelOfOperationsFilter;
use App\Filters\Tenant\Companies\Filters\SearchTermFilter;

class CompanyFilters extends EloquentFilter
{
    protected array $filters = [
        'search' => SearchTermFilter::class,
        'is_active' => IsActiveFilter::class,
        'is_featured' => IsFeaturedFilter::class,
        'level_of_operations' => LevelOfOperationsFilter::class,
    ];
}
