<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Exceptions\DomainException;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\Central\User\UserResource;
use App\Models\User;
use App\Repositories\Central\CentralUserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class AuthController extends BaseApiController
{
    public function __construct(protected CentralUserRepository $repository) {}

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
            throw new DomainException('Invalid credentials.', 401);
        }

        if (!$user->is_active) {
            throw new DomainException('Account is disabled.', 403);
        }

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
