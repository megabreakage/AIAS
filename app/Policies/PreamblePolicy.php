<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Preamble;
use App\Models\User;

class PreamblePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('preamble.view');
    }

    public function view(User $user, Preamble $preamble): bool
    {
        return $user->hasPermissionTo('preamble.view')
            && tenant()?->id === $preamble->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('preamble.create');
    }

    public function update(User $user, Preamble $preamble): bool
    {
        return $user->hasPermissionTo('preamble.update')
            && tenant()?->id === $preamble->tenant_id;
    }

    public function delete(User $user, Preamble $preamble): bool
    {
        return $user->hasPermissionTo('preamble.delete')
            && tenant()?->id === $preamble->tenant_id;
    }

    public function restore(User $user, Preamble $preamble): bool
    {
        return $user->hasPermissionTo('preamble.restore')
            && tenant()?->id === $preamble->tenant_id;
    }
}
