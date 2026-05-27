<?php

declare(strict_types=1);

namespace App\Filters\Central\Continents;

use App\Filters\Central\Continents\Filters\SearchTermFilter;
use App\Filters\Central\Continents\Filters\StatusFilter;
use App\Filters\EloquentFilter;

class ContinentFilters extends EloquentFilter
{
    protected array $filters = [
        'search' => SearchTermFilter::class,
        'status' => StatusFilter::class,
    ];
}
