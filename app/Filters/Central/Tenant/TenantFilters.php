<?php

declare(strict_types=1);

namespace App\Filters\Central\Tenant;

use App\Filters\Central\Tenant\Filters\ReferenceNumberFilter;
use App\Filters\Central\Tenant\Filters\SearchTermFilter;
use App\Filters\EloquentFilter;

class TenantFilters extends EloquentFilter
{
    protected array $filters = [
        'search' => SearchTermFilter::class,
        'reference_number' => ReferenceNumberFilter::class,
    ];
}
