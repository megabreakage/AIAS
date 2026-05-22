<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('user.view');
    }

    public function view(User $user, User $target): bool
    {
        return $user->hasPermissionTo('user.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('user.create');
    }

    public function update(User $user, User $target): bool
    {
        return $user->hasPermissionTo('user.edit');
    }

    public function delete(User $user, User $target): bool
    {
        return $user->hasPermissionTo('user.delete')
            && $user->id !== $target->id;
    }

    public function restore(User $user, User $target): bool
    {
        return $user->hasPermissionTo('user.restore');
    }
}
