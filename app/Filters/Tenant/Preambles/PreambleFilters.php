<?php

declare(strict_types=1);

namespace App\Filters\Tenant\Preambles;

use App\Filters\EloquentFilter;
use App\Filters\Tenant\Preambles\Filters\IsFeaturedFilter;
use App\Filters\Tenant\Preambles\Filters\SearchTermFilter;
use App\Filters\Tenant\Preambles\Filters\StatusFilter;

class PreambleFilters extends EloquentFilter
{
    protected array $filters = [
        'search' => SearchTermFilter::class,
        'status' => StatusFilter::class,
        'is_featured' => IsFeaturedFilter::class,
    ];
}
