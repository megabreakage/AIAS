<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Central;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Central\User\RegisterCentralUserRequest;
use App\Http\Resources\Central\User\UserResource;
use App\Repositories\Central\CentralUserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

final class CentralUserController extends BaseApiController
{
    public function __construct(protected CentralUserRepository $repository) {}

    /**
     * Register a new central user with the tenant role.
     *
     * This is Step 1 of the tenant provisioning flow.
     * The created user is stored on the central database and can be referenced
     * as the owner when creating a new Tenant (Step 2).
     */
    public function store(RegisterCentralUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        Log::info('Registering central user (tenant owner)', [
            'email' => $data['email'],
            'username' => $data['username'],
        ]);

        $data['password'] = Hash::make($data['password']);
        $data['is_active'] = $data['is_active'] ?? true;

        $tenantRole = Role::on('central')
            ->where('name', 'tenant')
            ->where('guard_name', 'api')
            ->first();

        if (! $tenantRole) {
            return $this->error(
                'ROLE_NOT_FOUND',
                "Role 'tenant' is not configured on the central database. Run RolePermissionsSeeder first.",
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $user = DB::connection('central')->transaction(function () use ($data, $tenantRole) {
            $user = $this->repository->createUser($data);
            $user->assignRole($tenantRole);

            return $user;
        });

        Log::info('Central user registered', [
            'identifier' => $user->identifier,
            'email'      => $user->email,
        ]);

        return $this->success(
            UserResource::make($user->load(['roles']))->resolve(),
            Response::HTTP_CREATED,
        );
    }
}
