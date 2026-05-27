<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Checklist;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class ChecklistPolicy
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
        return $user->hasPermissionTo('checklists.view');
    }

    public function view(User $user, Checklist $checklist): bool
    {
        return $user->hasPermissionTo('checklists.view')
            && $user->tenant_id === $checklist->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('checklists.create');
    }

    public function update(User $user, Checklist $checklist): bool
    {
        return $user->hasPermissionTo('checklists.update')
            && $user->tenant_id === $checklist->tenant_id;
    }

    public function delete(User $user, Checklist $checklist): bool
    {
        return $user->hasPermissionTo('checklists.delete')
            && $user->tenant_id === $checklist->tenant_id;
    }

    public function restore(User $user, Checklist $checklist): bool
    {
        return $user->hasPermissionTo('checklists.restore')
            && $user->tenant_id === $checklist->tenant_id;
    }
}
