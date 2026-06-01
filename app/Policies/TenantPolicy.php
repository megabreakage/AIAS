<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Central\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class TenantPolicy
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
        return $user->hasPermissionTo('tenants.view');
    }

    public function view(User $user, Tenant $tenant): bool
    {
        // Central admins with explicit permission can view any tenant
        if ($user->hasPermissionTo('tenants.view')) {
            return true;
        }

        // Tenant users can only view their own tenant
        return $user->tenant_id === $tenant->getTenantKey();
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('tenants.create');
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $user->hasPermissionTo('tenants.edit');
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->hasPermissionTo('tenants.delete');
    }

    public function restore(User $user, Tenant $tenant): bool
    {
        return $user->hasPermissionTo('tenants.restore');
    }

    public function activate(User $user, Tenant $tenant): bool
    {
        return $user->hasPermissionTo('tenants.activate');
    }

    public function suspend(User $user, Tenant $tenant): bool
    {
        return $user->hasPermissionTo('tenants.suspend');
    }
}
