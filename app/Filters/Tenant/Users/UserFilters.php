<?php

declare(strict_types=1);

namespace App\Filters\Tenant\Users;

use App\Filters\EloquentFilter;
use App\Filters\Tenant\Users\Filters\IsActiveFilter;
use App\Filters\Tenant\Users\Filters\RolesFilter;
use App\Filters\Tenant\Users\Filters\SearchTermFilter;

class UserFilters extends EloquentFilter
{
    protected array $filters = [
        'search' => SearchTermFilter::class,
        'is_active' => IsActiveFilter::class,
        'roles' => RolesFilter::class,
    ];
}
