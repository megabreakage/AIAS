<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Central;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Central\User\CreateTenantUserRequest;
use App\Http\Resources\Tenant\User\UserResource;
use App\Models\Central\Tenant;
use App\Models\User;
use App\Repositories\Tenant\UserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

final class TenantUserController extends BaseApiController
{
    public function __construct(protected UserRepository $repository) {}

    public function store(CreateTenantUserRequest $request, string $tenantId): JsonResponse
    {
        $tenant = Tenant::on('central')->find($tenantId);

        if (!$tenant) {
            return $this->error(
                'TENANT_NOT_FOUND',
                "Tenant '{$tenantId}' not found.",
                Response::HTTP_NOT_FOUND,
            );
        }

        $data = $request->validated();
        $role = $data['role'];
        unset($data['role']);

        $data['password'] = Hash::make($data['password']);
        $data['is_active'] = $data['is_active'] ?? true;

        Log::info('SuperAdmin creating tenant user', [
            'tenant' => $tenantId,
            'email' => $data['email'],
            'role' => $role,
        ]);

        try {
            tenancy()->initialize($tenant);

            // Validate uniqueness within tenant DB
            if (User::where('email', $data['email'])->exists()) {
                tenancy()->end();

                return $this->error(
                    'EMAIL_TAKEN',
                    'A user with this email already exists in this tenant.',
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            if (User::where('username', $data['username'])->exists()) {
                tenancy()->end();

                return $this->error(
                    'USERNAME_TAKEN',
                    'This username is already taken in this tenant.',
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            $tenantRole = Role::where('name', $role)->where('guard_name', 'api')->first();

            if (!$tenantRole) {
                tenancy()->end();

                return $this->error(
                    'ROLE_NOT_FOUND',
                    "Role '{$role}' is not configured for this tenant.",
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            $user = DB::transaction(function () use ($data, $tenantRole): User {
                $user = $this->repository->createUser($data);
                $user->assignRole($tenantRole);

                return $user;
            });

            Log::info('Tenant user created by SuperAdmin', [
                'identifier' => $user->identifier,
                'tenant' => $tenantId,
                'role' => $role,
            ]);

            tenancy()->end();

            return $this->success(
                UserResource::make($user->load(['roles']))->resolve(),
                Response::HTTP_CREATED,
            );
        } catch (\Throwable $e) {
            tenancy()->end();

            Log::error('Failed to create tenant user', [
                'tenant' => $tenantId,
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
