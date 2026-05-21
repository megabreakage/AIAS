<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Central\Continent;
use App\Models\Central\SuperAdmin;

class ContinentPolicy
{
    public function viewAny(SuperAdmin $user): bool
    {
        return $user->hasRole('super-admin');
    }

    public function view(SuperAdmin $user, Continent $continent): bool
    {
        return $user->hasRole('super-admin');
    }

    public function create(SuperAdmin $user): bool
    {
        return $user->hasRole('super-admin');
    }

    public function update(SuperAdmin $user, Continent $continent): bool
    {
        return $user->hasRole('super-admin');
    }

    public function delete(SuperAdmin $user, Continent $continent): bool
    {
        return $user->hasRole('super-admin');
    }

    public function restore(SuperAdmin $user, Continent $continent): bool
    {
        return $user->hasRole('super-admin');
    }
}
