<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Company;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class CompanyPolicy
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
        return $user->hasPermissionTo('companies.view');
    }

    public function view(User $user, Company $company): bool
    {
        return $user->hasPermissionTo('companies.view')
            && $user->tenant_id === $company->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('companies.create');
    }

    public function update(User $user, Company $company): bool
    {
        return $user->hasPermissionTo('companies.update')
            && $user->tenant_id === $company->tenant_id;
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->hasPermissionTo('companies.delete')
            && $user->tenant_id === $company->tenant_id;
    }

    public function restore(User $user, Company $company): bool
    {
        return $user->hasPermissionTo('companies.restore')
            && $user->tenant_id === $company->tenant_id;
    }
}
