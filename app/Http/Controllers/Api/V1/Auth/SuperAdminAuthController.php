<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Exceptions\DomainException;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Resources\Central\Admin\AdminResource;
use App\Models\Central\SuperAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

final class SuperAdminAuthController extends BaseApiController
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        /** @var SuperAdmin|null $admin */
        $admin = SuperAdmin::where('email', $credentials['email'])->first();

        if (!$admin || !Hash::check($credentials['password'], $admin->password)) {
            throw new DomainException('Invalid credentials.', 401);
        }

        if (!$admin->is_active) {
            throw new DomainException('Account is disabled.', 403);
        }

        $token = $admin->createToken('sa-api-token')->accessToken;
        $refreshToken = $admin->createToken('sa-refresh-token')->accessToken;

        return $this->success([
            'token' => $token,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $admin->identifier,
                'first_name' => $admin->first_name,
                'last_name' => $admin->last_name,
                'email' => $admin->email,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->token()->revoke();

        return $this->success(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): JsonResponse
    {
        $admin = $request->user();

        return $this->success([
            'id' => $admin->identifier,
            'first_name' => $admin->first_name,
            'last_name' => $admin->last_name,
            'email' => $admin->email,
            'is_active' => $admin->is_active,
        ]);
    }
}
