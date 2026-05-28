<?php

declare(strict_types=1);

namespace App\Filters\PriorityLevels\Filters;

use App\Filters\EloquentFilter;
use Illuminate\Database\Eloquent\Builder;

final class SearchTermFilter extends EloquentFilter
{
    public function __construct(
        protected string $search
    ) {}

    public function apply(Builder $query): Builder
    {
        $search = str_replace(
            ['%', '_'],
            ['\%', '\_'],
            trim($this->search)
        );

        return $query->where(function (Builder $q) use ($search): void {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }
}
