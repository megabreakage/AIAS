<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\MfaMethod;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Auth\Mfa\ConfirmMfaSetupRequest;
use App\Http\Requests\Auth\Mfa\DisableMfaRequest;
use App\Http\Requests\Auth\Mfa\RegenerateBackupCodesRequest;
use App\Http\Requests\Auth\Mfa\UpdateMfaMethodRequest;
use App\Http\Requests\Auth\Mfa\VerifyMfaLoginRequest;
use App\Http\Resources\Central\User\UserResource;
use App\Repositories\Central\CentralUserRepository;
use App\Services\MfaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

final class MfaController extends BaseApiController
{
    public function __construct(
        private readonly MfaService $mfaService,
        private readonly CentralUserRepository $userRepository,
    ) {}

    /**
     * GET /v1/mfa/status
     * Return the current MFA status for the authenticated user.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'mfa_enabled' => $user->mfa_enabled,
            'mfa_method' => $user->mfa_method,
            'mfa_confirmed_at' => $user->mfa_confirmed_at?->toISOString(),
            'backup_codes_remaining' => $user->mfa_enabled
                ? count($user->mfa_backup_codes ?? [])
                : null,
        ]);
    }

    /**
     * POST /v1/mfa/setup
     * Generate a TOTP secret and QR code for the authenticated user.
     * The user must confirm with a valid code before MFA is activated.
     */
    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->mfa_enabled) {
            return $this->error(
                'MFA_ALREADY_ENABLED',
                'MFA is already enabled. Disable it before setting up a new method.',
                Response::HTTP_CONFLICT,
            );
        }

        $setupData = $this->mfaService->generateSetupData($user);

        return $this->success([
            'secret' => $setupData['secret'],
            'qr_code_svg' => $setupData['qr_code_svg'],
            'otpauth_url' => $setupData['otpauth_url'],
            'message' => 'Scan the QR code with your authenticator app, then confirm with a valid 6-digit code.',
        ]);
    }

    /**
     * POST /v1/mfa/confirm
     * Confirm MFA setup by verifying the first TOTP code.
     * Activates MFA and returns one-time backup codes.
     */
    public function confirm(ConfirmMfaSetupRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if ($user->mfa_enabled) {
            return $this->error(
                'MFA_ALREADY_ENABLED',
                'MFA is already enabled.',
                Response::HTTP_CONFLICT,
            );
        }

        if (!$this->mfaService->verifyCode($data['secret'], $data['code'])) {
            return $this->error(
                'INVALID_MFA_CODE',
                'The provided TOTP code is invalid or has expired.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $backupCodes = $this->mfaService->enableMfa($user, $data['secret'], MfaMethod::Totp);

        return $this->success([
            'message' => 'MFA enabled successfully. Store these backup codes in a safe place — they will not be shown again.',
            'backup_codes' => $backupCodes,
        ]);
    }

    /**
     * POST /v1/mfa/disable
     * Disable MFA after verifying the current password and a valid MFA code.
     */
    public function disable(DisableMfaRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if (!$user->mfa_enabled) {
            return $this->error(
                'MFA_NOT_ENABLED',
                'MFA is not enabled on this account.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if (!Hash::check($data['password'], $user->password)) {
            return $this->error(
                'INVALID_PASSWORD',
                'The provided password is incorrect.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if (!$this->mfaService->verifyAnyCode($user, $data['code'])) {
            return $this->error(
                'INVALID_MFA_CODE',
                'The provided MFA code is invalid.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $this->mfaService->disableMfa($user);

        return $this->success(['message' => 'MFA disabled successfully.']);
    }

    /**
     * POST /v1/mfa/backup-codes
     * Regenerate backup codes after verifying a valid TOTP code.
     */
    public function regenerateBackupCodes(RegenerateBackupCodesRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if (!$user->mfa_enabled) {
            return $this->error(
                'MFA_NOT_ENABLED',
                'MFA must be enabled before generating backup codes.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if (!$this->mfaService->verifyUserCode($user, $data['code'])) {
            return $this->error(
                'INVALID_MFA_CODE',
                'The provided TOTP code is invalid or has expired.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $backupCodes = $this->mfaService->regenerateBackupCodes($user);

        return $this->success([
            'message' => 'Backup codes regenerated. Store these codes securely — they will not be shown again.',
            'backup_codes' => $backupCodes,
        ]);
    }

    /**
     * PUT /v1/mfa/method
     * Update the preferred MFA method.
     */
    public function updateMethod(UpdateMfaMethodRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $method = MfaMethod::from($data['method']);

        if (!$user->mfa_enabled) {
            return $this->error(
                'MFA_NOT_ENABLED',
                'MFA must be enabled before changing the method.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $this->mfaService->updateMethod($user, $method);

        return $this->success([
            'message' => "MFA method updated to {$method->label()}.",
            'method' => $method->value,
        ]);
    }

    /**
     * POST /v1/auth/mfa/verify
     * Verify an MFA code during a pending login session.
     * Issues a full Passport access token on success.
     */
    public function verifyLogin(VerifyMfaLoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $session = $this->mfaService->getPendingSession($data['mfa_token']);

        if (!$session) {
            return $this->error(
                'INVALID_MFA_TOKEN',
                'MFA session has expired or is invalid. Please log in again.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $user = $this->userRepository->findByIdentifier($session['user_identifier']);

        if (!$user) {
            return $this->error(
                'USER_NOT_FOUND',
                'Associated user account not found.',
                Response::HTTP_NOT_FOUND,
            );
        }

        if (!$this->mfaService->verifyAnyCode($user, $data['code'])) {
            return $this->error(
                'INVALID_MFA_CODE',
                'The provided MFA code is invalid or has expired.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $this->mfaService->consumePendingSession($data['mfa_token']);

        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken('api-token')->accessToken;

        return $this->success([
            'user' => UserResource::make($user->load(['roles']))->resolve(),
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }
}
