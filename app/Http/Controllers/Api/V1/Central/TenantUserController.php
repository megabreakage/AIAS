<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Central;

use App\Exceptions\ApiException;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Central\User\CreateTenantUserRequest;
use App\Http\Resources\Tenant\User\UserResource;
use App\Models\User;
use App\Repositories\Central\TenantRepository;
use App\Repositories\Tenant\UserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class TenantUserController extends BaseApiController
{
    public function __construct(
        protected UserRepository $repository,
        protected TenantRepository $tenantRepository,
    ) {}

    public function store(CreateTenantUserRequest $request, string $id): JsonResponse
    {
        Gate::authorize('create', User::class);

        $tenant = $this->tenantRepository->findById($id);

        if (!$tenant) {
            return $this->error(
                'TENANT_NOT_FOUND',
                "Tenant '{$id}' not found.",
                Response::HTTP_NOT_FOUND,
            );
        }

        $data = $request->validated();
        $role = $data['role'];
        unset($data['role']);

        $data['password'] = Hash::make($data['password']);
        $data['is_active'] = $data['is_active'] ?? true;

        Log::info('Creating tenant user', [
            'tenant' => $id,
            'email' => $data['email'],
            'role' => $role,
        ]);

        try {
            $user = $tenant->run(function () use ($data, $role): User {
                if ($this->repository->emailExists($data['email'])) {
                    throw new ApiException(
                        'A user with this email already exists in this tenant.',
                        Response::HTTP_UNPROCESSABLE_ENTITY,
                        'EMAIL_TAKEN',
                    );
                }

                if ($this->repository->usernameExists($data['username'])) {
                    throw new ApiException(
                        'This username is already taken in this tenant.',
                        Response::HTTP_UNPROCESSABLE_ENTITY,
                        'USERNAME_TAKEN',
                    );
                }

                $tenantRole = $this->repository->findRoleByName($role);

                if (!$tenantRole) {
                    throw new ApiException(
                        "Role '{$role}' is not configured for this tenant.",
                        Response::HTTP_UNPROCESSABLE_ENTITY,
                        'ROLE_NOT_FOUND',
                    );
                }

                $user = DB::transaction(function () use ($data, $tenantRole): User {
                    $newUser = $this->repository->createUser($data);
                    $newUser->assignRole($tenantRole);

                    return $newUser;
                });

                return $user->load(['roles']);
            });

            Log::info('Tenant user created', [
                'identifier' => $user->identifier,
                'tenant' => $id,
                'role' => $role,
            ]);

            return $this->success(UserResource::make($user)->resolve(), Response::HTTP_CREATED);

        } catch (ApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Failed to create tenant user', [
                'tenant' => $id,
                'email' => $data['email'],
                'error' => $e->getMessage(),
            ]);

            return $this->error(
                'USER_CREATION_FAILED',
                'Failed to create user: '.$e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }
}
