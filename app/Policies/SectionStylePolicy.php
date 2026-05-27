<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Central\SectionStyle;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class SectionStylePolicy
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
        return $user->hasPermissionTo('view section_styles');
    }

    public function view(User $user, SectionStyle $sectionStyle): bool
    {
        return $user->hasPermissionTo('view section_styles');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create section_styles');
    }

    public function update(User $user, SectionStyle $sectionStyle): bool
    {
        return $user->hasPermissionTo('edit section_styles');
    }

    public function delete(User $user, SectionStyle $sectionStyle): bool
    {
        return $user->hasPermissionTo('delete section_styles');
    }

    public function restore(User $user, SectionStyle $sectionStyle): bool
    {
        return $user->hasPermissionTo('restore section_styles');
    }
}
