<?php

declare(strict_types=1);

namespace App\Filters\Tenant\Users\Filters;

use App\Filters\EloquentFilter;
use Illuminate\Database\Eloquent\Builder;

class RolesFilter extends EloquentFilter
{
    /** @var list<string> */
    protected array $roles;

    public function __construct(string|array $roles)
    {
        $this->roles = array_values(array_filter(
            is_array($roles) ? $roles : explode(',', $roles),
            static fn (mixed $r): bool => is_string($r) && $r !== '',
        ));
    }

    public function apply(Builder $query): Builder
    {
        if (empty($this->roles)) {
            return $query;
        }

        return $query->whereHas('roles', function (Builder $q): void {
            $q->whereIn('name', $this->roles);
        });
    }
}
