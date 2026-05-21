<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Central\Country;
use App\Models\Central\SuperAdmin;

class CountryPolicy
{
    public function viewAny(SuperAdmin $user): bool
    {
        return $user->hasRole('super-admin');
    }

    public function view(SuperAdmin $user, Country $country): bool
    {
        return $user->hasRole('super-admin');
    }

    public function create(SuperAdmin $user): bool
    {
        return $user->hasRole('super-admin');
    }

    public function update(SuperAdmin $user, Country $country): bool
    {
        return $user->hasRole('super-admin');
    }

    public function delete(SuperAdmin $user, Country $country): bool
    {
        return $user->hasRole('super-admin');
    }

    public function restore(SuperAdmin $user, Country $country): bool
    {
        return $user->hasRole('super-admin');
    }
}
