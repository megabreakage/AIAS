<?php

declare(strict_types=1);

namespace App\Filters\Tenant\Audits\Filters;

use App\Enums\AuditScope;
use App\Filters\EloquentFilter;
use Illuminate\Database\Eloquent\Builder;

class ScopeFilter extends EloquentFilter
{
    public function __construct(protected string $scope) {}

    public function apply(Builder $query): Builder
    {
        $validScopes = array_map(fn (AuditScope $s) => $s->value, AuditScope::cases());

        if (! in_array($this->scope, $validScopes, true)) {
            return $query;
        }

        return $query->where('scope', $this->scope);
    }
}
