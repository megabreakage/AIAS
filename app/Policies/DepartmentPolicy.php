<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Department;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class DepartmentPolicy
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
        return $user->hasPermissionTo('departments.view');
    }

    public function view(User $user, Department $department): bool
    {
        return $user->hasPermissionTo('departments.view')
            && $user->tenant_id === $department->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('departments.create');
    }

    public function update(User $user, Department $department): bool
    {
        return $user->hasPermissionTo('departments.update')
            && $user->tenant_id === $department->tenant_id;
    }

    public function delete(User $user, Department $department): bool
    {
        return $user->hasPermissionTo('departments.delete')
            && $user->tenant_id === $department->tenant_id;
    }

    public function restore(User $user, Department $department): bool
    {
        return $user->hasPermissionTo('departments.restore')
            && $user->tenant_id === $department->tenant_id;
    }
}
