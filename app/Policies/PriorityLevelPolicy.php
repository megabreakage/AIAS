<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PriorityLevel;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class PriorityLevelPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('priority-levels.view');
    }

    public function view(User $user, PriorityLevel $priorityLevel): bool
    {
        return $user->hasPermissionTo('priority-levels.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('priority-levels.create');
    }

    public function update(User $user, PriorityLevel $priorityLevel): bool
    {
        return $user->hasPermissionTo('priority-levels.edit');
    }

    public function delete(User $user, PriorityLevel $priorityLevel): bool
    {
        return $user->hasPermissionTo('priority-levels.delete');
    }

    public function restore(User $user, PriorityLevel $priorityLevel): bool
    {
        return $user->hasPermissionTo('priority-levels.delete');
    }
}
