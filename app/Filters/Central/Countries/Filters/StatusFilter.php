<?php

declare(strict_types=1);

namespace App\Filters\Central\Countries\Filters;

use App\Filters\EloquentFilter;
use Illuminate\Database\Eloquent\Builder;

class StatusFilter extends EloquentFilter
{
    public function __construct(protected string $status) {}

    public function apply(Builder $query): Builder
    {
        return $query->where('status', filter_var($this->status, FILTER_VALIDATE_BOOLEAN));
    }
}
