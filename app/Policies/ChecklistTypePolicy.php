<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\ChecklistType;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class ChecklistTypePolicy
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
        return $user->hasPermissionTo('checklist-types.view');
    }

    public function view(User $user, ChecklistType $checklistType): bool
    {
        return $user->hasPermissionTo('checklist-types.view')
            && $user->tenant_id === $checklistType->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('checklist-types.create');
    }

    public function update(User $user, ChecklistType $checklistType): bool
    {
        return $user->hasPermissionTo('checklist-types.update')
            && $user->tenant_id === $checklistType->tenant_id;
    }

    public function delete(User $user, ChecklistType $checklistType): bool
    {
        return $user->hasPermissionTo('checklist-types.delete')
            && $user->tenant_id === $checklistType->tenant_id;
    }

    public function restore(User $user, ChecklistType $checklistType): bool
    {
        return $user->hasPermissionTo('checklist-types.restore')
            && $user->tenant_id === $checklistType->tenant_id;
    }
}
