<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Filters\Tenant\Users\UserFilters;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Tenant\Users\CreateUserRequest;
use App\Http\Requests\Tenant\Users\UpdateUserRequest;
use App\Http\Resources\Tenant\User\UserResource;
use App\Models\User;
use App\Repositories\Tenant\UserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class UserController extends BaseApiController
{
    public function __construct(protected UserRepository $repository) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $filters = UserFilters::fromRequest($request);

        $users = $this->repository->browseUsers(
            filters: $filters,
            page: $request->integer('page', 1),
            perPage: $request->integer('per_page', 15),
            sortBy: $request->input('sort_by'),
            sortDesc: $request->input('sort_order') === 'desc',
        );

        return $this->paginated($users, UserResource::class);
    }

    public function store(CreateUserRequest $request): JsonResponse
    {
        Gate::authorize('create', User::class);

        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $data['is_active'] = $data['is_active'] ?? true;

        Log::info('Creating user', ['email' => $data['email'], 'tenant' => tenant()?->id]);

        $user = DB::transaction(function () use ($data): User {
            return $this->repository->createUser($data);
        });

        Log::info('User created', ['identifier' => $user->identifier]);

        return $this->success(
            UserResource::make($user->load(['createdBy', 'updatedBy', 'roles']))->resolve(),
            Response::HTTP_CREATED,
        );
    }

    public function show(string $identifier): JsonResponse
    {
        $user = $this->repository->readUser($identifier);

        Gate::authorize('view', $user);

        return $this->success(UserResource::make($user)->resolve());
    }

    public function update(UpdateUserRequest $request, string $identifier): JsonResponse
    {
        $user = $this->repository->readUser($identifier);

        Gate::authorize('update', $user);

        $data = $request->validated();

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        Log::info('Updating user', ['identifier' => $identifier]);

        $user = DB::transaction(function () use ($identifier, $data): User {
            return $this->repository->updateUser($identifier, $data);
        });

        Log::info('User updated', ['identifier' => $user->identifier]);

        return $this->success(UserResource::make($user)->resolve());
    }

    public function destroy(string $identifier): JsonResponse
    {
        $user = $this->repository->readUser($identifier);

        Gate::authorize('delete', $user);

        Log::info('Deleting user', ['identifier' => $identifier]);

        DB::transaction(function () use ($identifier): void {
            $this->repository->deleteUser($identifier);
        });

        Log::info('User deleted', ['identifier' => $identifier]);

        return $this->success(null, Response::HTTP_NO_CONTENT);
    }

    public function restore(string $identifier): JsonResponse
    {
        $user = $this->repository->readTrashedUser($identifier);

        Gate::authorize('restore', $user);

        Log::info('Restoring user', ['identifier' => $identifier]);

        $user = DB::transaction(function () use ($identifier): User {
            return $this->repository->restoreUser($identifier);
        });

        Log::info('User restored', ['identifier' => $user->identifier]);

        return $this->success(UserResource::make($user)->resolve());
    }
}
