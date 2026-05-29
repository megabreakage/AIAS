<?php

declare(strict_types=1);

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

abstract class EloquentFilter
{
    /**
     * Map of request parameter keys to filter class names.
     *
     * @var array<string, class-string<EloquentFilter>>
     */
    protected array $filters = [];

    /** @var array<EloquentFilter> */
    private array $activeFilters = [];

    public static function fromRequest(Request $request): static
    {
        $instance = new static;

        foreach ($instance->filters as $key => $filterClass) {
            if ($request->filled($key)) {
                $instance->activeFilters[] = new $filterClass($request->input($key));
            }
        }

        return $instance;
    }

    public function apply(Builder $query): Builder
    {
        foreach ($this->activeFilters as $filter) {
            $filter->apply($query);
        }

        return $query;
    }
}
