<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Filters\Tenant\Users\UserFilters;
use App\Models\User;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Spatie\Permission\Models\Role;

class UserRepository extends BaseRepository
{
    protected function model(): string
    {
        return User::class;
    }

    /**
     * Browse users with filters, sorting, and pagination.
     * Scoped to the current tenant database automatically.
     */
    public function browseUsers(
        UserFilters $filters,
        int $page = 1,
        int $perPage = 15,
        ?string $sortBy = null,
        bool $sortDesc = false,
    ): LengthAwarePaginator {
        $query = $this->newQuery()->with(['createdBy', 'updatedBy', 'roles']);

        $filters->apply($query);

        $sortColumn = in_array($sortBy, ['first_name', 'last_name', 'email', 'username', 'is_active', 'created_at', 'last_login_at'], true)
            ? $sortBy
            : 'created_at';

        $query->orderBy($sortColumn, $sortDesc ? 'desc' : 'asc');

        return $query->paginate(
            perPage: min($perPage, 100),
            page: max($page, 1),
        );
    }

    /**
     * Find a user by identifier (active records only).
     *
     * @throws ModelNotFoundException
     */
    public function readUser(string $identifier): User
    {
        /** @var User */
        return $this->newQuery()
            ->where('identifier', $identifier)
            ->with(['createdBy', 'updatedBy', 'roles'])
            ->firstOrFail();
    }

    /**
     * Find a soft-deleted user by identifier (includes trashed).
     *
     * @throws ModelNotFoundException
     */
    public function readTrashedUser(string $identifier): User
    {
        /** @var User */
        return $this->newQuery()
            ->withTrashed()
            ->where('identifier', $identifier)
            ->with(['createdBy', 'updatedBy', 'roles'])
            ->firstOrFail();
    }

    /**
     * Create a new user in the current tenant database.
     *
     * @param  array<string, mixed>  $data
     */
    public function createUser(array $data): User
    {
        /** @var User */
        return $this->newQuery()->create($data);
    }

    /**
     * Update an existing user.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateUser(string $identifier, array $data): User
    {
        $user = $this->readUser($identifier);
        $user->fill($data)->save();

        return $user->fresh(['createdBy', 'updatedBy', 'roles']);
    }

    /**
     * Soft-delete a user.
     */
    public function deleteUser(string $identifier): void
    {
        $user = $this->readUser($identifier);
        $user->delete();
    }

    /**
     * Restore a soft-deleted user.
     */
    public function restoreUser(string $identifier): User
    {
        $user = $this->readTrashedUser($identifier);
        $user->restore();

        return $user->fresh(['createdBy', 'updatedBy', 'roles']);
    }

    public function emailExists(string $email): bool
    {
        return $this->newQuery()->where('email', $email)->exists();
    }

    public function usernameExists(string $username): bool
    {
        return $this->newQuery()->where('username', $username)->exists();
    }

    public function findRoleByName(string $name): ?Role
    {
        /** @var Role|null */
        return Role::where('name', $name)->where('guard_name', 'api')->first();
    }
}
