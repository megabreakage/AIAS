<?php

declare(strict_types=1);

namespace App\Filters\Central\Tenant\Filters;

use App\Filters\EloquentFilter;
use Illuminate\Database\Eloquent\Builder;

class ReferenceNumberFilter extends EloquentFilter
{
    public function __construct(protected string $referenceNumber) {}

    public function apply(Builder $query): Builder
    {
        $referenceNumber = trim($this->referenceNumber);

        return $query->where('reference_number', 'like', "%{$referenceNumber}%");
    }
}
