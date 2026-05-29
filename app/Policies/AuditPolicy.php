<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Audit;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class AuditPolicy
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
        return $user->hasPermissionTo('audits.view');
    }

    public function view(User $user, Audit $audit): bool
    {
        return $user->hasPermissionTo('audits.view')
            && $user->tenant_id === $audit->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('audits.create');
    }

    public function update(User $user, Audit $audit): bool
    {
        return $user->hasPermissionTo('audits.update')
            && $user->tenant_id === $audit->tenant_id;
    }

    public function delete(User $user, Audit $audit): bool
    {
        return $user->hasPermissionTo('audits.delete')
            && $user->tenant_id === $audit->tenant_id;
    }

    public function restore(User $user, Audit $audit): bool
    {
        return $user->hasPermissionTo('audits.restore')
            && $user->tenant_id === $audit->tenant_id;
    }
}
