<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class UserPolicy
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
        return $user->hasPermissionTo('user.view');
    }

    public function view(User $user, User $target): bool
    {
        return $user->hasPermissionTo('user.view')
            && $user->tenant_id === $target->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('user.create');
    }

    public function update(User $user, User $target): bool
    {
        return $user->hasPermissionTo('user.edit')
            && $user->tenant_id === $target->tenant_id;
    }

    public function delete(User $user, User $target): bool
    {
        return $user->hasPermissionTo('user.delete')
            && $user->tenant_id === $target->tenant_id
            && $user->id !== $target->id;
    }

    public function restore(User $user, User $target): bool
    {
        return $user->hasPermissionTo('user.restore')
            && $user->tenant_id === $target->tenant_id;
    }
}
