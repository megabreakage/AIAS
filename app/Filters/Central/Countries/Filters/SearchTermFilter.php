<?php

declare(strict_types=1);

namespace App\Filters\Central\Countries\Filters;

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
                ->orWhere('slug', 'like', "%{$search}%")
                ->orWhere('short_code', 'like', "%{$search}%")
                ->orWhere('iso_code', 'like', "%{$search}%")
                ->orWhere('currency', 'like', "%{$search}%")
                ->orWhere('currency_name', 'like', "%{$search}%")
                ->orWhere('country_code', 'like', "%{$search}%");
        });
    }
}
