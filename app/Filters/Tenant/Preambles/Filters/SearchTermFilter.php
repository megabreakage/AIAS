<?php

declare(strict_types=1);

namespace App\Filters\Preambles\Filters;

use App\Filters\EloquentFilter;
use Illuminate\Database\Eloquent\Builder;

class SearchTermFilter extends EloquentFilter
{
    public function __construct(protected string $search) {}

    public function apply(Builder $query): Builder
    {
        $search = trim($this->search);

        return $query->where(function (Builder $q) use ($search): void {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('reference_number', 'like', "%{$search}%");
        });
    }
}
