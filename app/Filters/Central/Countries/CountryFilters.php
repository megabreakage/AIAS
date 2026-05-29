<?php

declare(strict_types=1);

namespace App\Filters\Central\Countries;

use App\Filters\Central\Countries\Filters\ContinentFilter;
use App\Filters\Central\Countries\Filters\SearchTermFilter;
use App\Filters\Central\Countries\Filters\StatusFilter;
use App\Filters\EloquentFilter;

class CountryFilters extends EloquentFilter
{
    protected array $filters = [
        'search' => SearchTermFilter::class,
        'status' => StatusFilter::class,
        'continent_id' => ContinentFilter::class,
    ];
}
