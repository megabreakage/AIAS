<?php

declare(strict_types=1);

namespace App\Repositories\Central;

use App\Models\Central\CentralUser;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class CentralUserRepository extends BaseRepository
{
    protected function model(): string
    {
        return CentralUser::class;
    }

    /**
     * Find a central user by identifier.
     *
     * @throws ModelNotFoundException
     */
    public function readUser(string $identifier): CentralUser
    {
        /** @var CentralUser */
        return CentralUser::on('central')
            ->where('identifier', $identifier)
            ->with(['roles'])
            ->firstOrFail();
    }

    /**
     * Create a new central user.
     *
     * @param  array<string, mixed>  $data
     */
    public function createUser(array $data): CentralUser
    {
        /** @var CentralUser */
        return CentralUser::on('central')->create($data);
    }

    /**
     * Check whether a central user with given email exists.
     */
    public function emailExists(string $email): bool
    {
        return CentralUser::on('central')->where('email', $email)->exists();
    }

    /**
     * Check whether a central user with given username exists.
     */
    public function usernameExists(string $username): bool
    {
        return CentralUser::on('central')->where('username', $username)->exists();
    }
}
