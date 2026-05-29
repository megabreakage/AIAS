<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Preamble;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class PreamblePolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('preambles.view');
    }

    public function view(User $user, Preamble $preamble): bool
    {
        return $user->hasPermissionTo('preambles.view')
            && $user->tenant_id === $preamble->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('preambles.create');
    }

    public function update(User $user, Preamble $preamble): bool
    {
        return $user->hasPermissionTo('preambles.update')
            && $user->tenant_id === $preamble->tenant_id;
    }

    public function delete(User $user, Preamble $preamble): bool
    {
        return $user->hasPermissionTo('preambles.delete')
            && $user->tenant_id === $preamble->tenant_id;
    }

    public function restore(User $user, Preamble $preamble): bool
    {
        return $user->hasPermissionTo('preambles.restore')
            && $user->tenant_id === $preamble->tenant_id;
    }
}
