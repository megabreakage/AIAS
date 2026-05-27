<?php

declare(strict_types=1);

namespace App\Filters\Central\SectionStyles;

use App\Filters\Central\SectionStyles\Filters\IsActiveFilter;
use App\Filters\Central\SectionStyles\Filters\IsFeaturedFilter;
use App\Filters\Central\SectionStyles\Filters\SearchTermFilter;
use App\Filters\EloquentFilter;

class SectionStyleFilters extends EloquentFilter
{
    protected array $filters = [
        'search' => SearchTermFilter::class,
        'is_active' => IsActiveFilter::class,
        'is_featured' => IsFeaturedFilter::class,
    ];
}
