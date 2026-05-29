<?php

declare(strict_types=1);

namespace App\Filters\Tenant\Audits;

use App\Filters\EloquentFilter;
use App\Filters\Tenant\Audits\Filters\IsFeaturedFilter;
use App\Filters\Tenant\Audits\Filters\ScopeFilter;
use App\Filters\Tenant\Audits\Filters\SearchTermFilter;

class AuditFilters extends EloquentFilter
{
    protected array $filters = [
        'search' => SearchTermFilter::class,
        'scope' => ScopeFilter::class,
        'is_featured' => IsFeaturedFilter::class,
    ];
}
