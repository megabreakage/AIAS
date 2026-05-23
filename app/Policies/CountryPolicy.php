<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Central\Country;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class CountryPolicy
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
        return $user->hasPermissionTo('view countries');
    }

    public function view(User $user, Country $country): bool
    {
        return $user->hasPermissionTo('view countries');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create countries');
    }

    public function update(User $user, Country $country): bool
    {
        return $user->hasPermissionTo('edit countries');
    }

    public function delete(User $user, Country $country): bool
    {
        return $user->hasPermissionTo('delete countries');
    }

    public function restore(User $user, Country $country): bool
    {
        return $user->hasPermissionTo('restore countries');
    }
}
