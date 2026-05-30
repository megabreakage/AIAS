<?php

declare(strict_types=1);

namespace App\Repositories\Central;

use App\Models\User;
use App\Repositories\BaseRepository;
use Spatie\Permission\Models\Role;

final class CentralUserRepository extends BaseRepository
{
    protected function model(): string
    {
        return User::class;
    }

    public function readUser(string $identifier): User
    {
        /** @var User */
        return $this->query()
            ->where('identifier', $identifier)
            ->with(['roles'])
            ->firstOrFail();
    }

    /** @param array<string, mixed> $data */
    public function createUser(array $data): User
    {
        /** @var User */
        return $this->query()->create($data);
    }

    public function findByIdentifier(string $identifier): ?User
    {
        /** @var User|null */
        return $this->query()->where('identifier', $identifier)->first();
    }

    public function findByEmail(string $email): ?User
    {
        /** @var User|null */
        return $this->query()->where('email', $email)->first();
    }

    public function emailExists(string $email): bool
    {
        return $this->query()->where('email', $email)->exists();
    }

    public function usernameExists(string $username): bool
    {
        return $this->query()->where('username', $username)->exists();
    }

    public function findCentralRoleByName(string $name, string $guard = 'api'): ?Role
    {
        /** @var Role|null */
        return Role::on('central')
            ->where('name', $name)
            ->where('guard_name', $guard)
            ->first();
    }
}
