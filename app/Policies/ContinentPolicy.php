<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Central\Continent;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class ContinentPolicy
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
        return $user->hasPermissionTo('view continents');
    }

    public function view(User $user, Continent $continent): bool
    {
        return $user->hasPermissionTo('view continents');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create continents');
    }

    public function update(User $user, Continent $continent): bool
    {
        return $user->hasPermissionTo('edit continents');
    }

    public function delete(User $user, Continent $continent): bool
    {
        return $user->hasPermissionTo('delete continents');
    }

    public function restore(User $user, Continent $continent): bool
    {
        return $user->hasPermissionTo('restore continents');
    }
}
