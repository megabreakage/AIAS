<?php

declare(strict_types=1);

namespace App\Filters\Tenant\SectionStyles;

use App\Filters\EloquentFilter;
use App\Filters\Tenant\SectionStyles\Filters\IsActiveFilter;
use App\Filters\Tenant\SectionStyles\Filters\IsFeaturedFilter;
use App\Filters\Tenant\SectionStyles\Filters\SearchTermFilter;

class SectionStyleFilters extends EloquentFilter
{
    protected array $filters = [
        'search' => SearchTermFilter::class,
        'is_active' => IsActiveFilter::class,
        'is_featured' => IsFeaturedFilter::class,
    ];
}
