<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Exceptions\AccountDisabledException;
use App\Exceptions\AuthenticationException;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\Central\User\UserResource;
use App\Models\Central\Tenant;
use App\Models\User;
use App\Repositories\Central\CentralUserRepository;
use App\Services\MfaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;

final class AuthController extends BaseApiController
{
    public function __construct(
        protected CentralUserRepository $repository,
        protected Tenancy $tenancy,
        protected MfaService $mfaService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = DB::connection('central')->transaction(function () use ($data): User {
            return $this->repository->createUser([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'username' => Str::slug($data['first_name'].'_'.$data['last_name'].'_'.Str::random(4)),
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'is_active' => true,
            ]);
        });

        $token = $user->createToken('api-token')->accessToken;

        return $this->success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => UserResource::make($user->load(['roles', 'permissions']))->resolve(),
        ], Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = $this->repository->findByEmail($data['email']);

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw new AuthenticationException;
        }

        if (!$user->is_active) {
            throw new AccountDisabledException;
        }

        // Auto-initialize tenancy for tenant users
        if ($user->tenant_id && !tenant()) {
            $tenant = Tenant::where('identifier', $user->tenant_id)->first();

            if ($tenant) {
                $this->tenancy->initialize($tenant);
            }
        }

        // If MFA is enabled, return a pending token instead of a full access token
        if ($user->mfa_enabled) {
            $mfaToken = $this->mfaService->storePendingSession($user);

            return $this->success([
                'mfa_required' => true,
                'mfa_token' => $mfaToken,
                'mfa_method' => $user->mfa_method,
                'message' => 'MFA verification required. Provide the code via POST /v1/auth/mfa/verify.',
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken('api-token')->accessToken;

        return $this->success([
            'user' => UserResource::make($user->load(['roles']))->resolve(),
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->token()->revoke();

        return $this->success(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['roles', 'permissions', 'tenant']);

        return $this->success(UserResource::make($user)->resolve());
    }
}
