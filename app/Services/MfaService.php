<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MfaMethod;
use App\Models\User;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

final class MfaService
{
    private const int BACKUP_CODE_COUNT = 8;

    private const int BACKUP_CODE_LENGTH = 10;

    private const int MFA_WINDOW = 1;

    private const string PENDING_MFA_CACHE_PREFIX = 'mfa_pending:';

    private const int PENDING_MFA_TTL_SECONDS = 300;

    public function __construct(private readonly Google2FA $google2fa) {}

    /**
     * Generate a new TOTP secret and return setup data.
     *
     * @return array{secret: string, qr_code_svg: string, otpauth_url: string}
     */
    public function generateSetupData(User $user): array
    {
        $secret = $this->google2fa->generateSecretKey();

        $otpauthUrl = $this->google2fa->getQRCodeUrl(
            company: config('app.name'),
            holder: $user->email,
            secret: $secret,
        );

        $qrCodeSvg = $this->renderQrCodeSvg($otpauthUrl);

        return [
            'secret' => $secret,
            'qr_code_svg' => $qrCodeSvg,
            'otpauth_url' => $otpauthUrl,
        ];
    }

    /**
     * Verify a TOTP code against the given secret (used during setup confirmation).
     */
    public function verifyCode(string $secret, string $code): bool
    {
        return (bool) $this->google2fa->verifyKey(
            secret: $secret,
            key: $code,
            window: self::MFA_WINDOW,
        );
    }

    /**
     * Verify a TOTP code for an already-enabled user.
     */
    public function verifyUserCode(User $user, string $code): bool
    {
        if (!$user->mfa_enabled || !$user->mfa_secret) {
            return false;
        }

        return $this->verifyCode(Crypt::decryptString($user->mfa_secret), $code);
    }

    /**
     * Check whether the code is a valid backup code for the user.
     * Consumes the backup code on success.
     */
    public function verifyAndConsumeBackupCode(User $user, string $code): bool
    {
        $storedCodes = $user->mfa_backup_codes ?? [];

        foreach ($storedCodes as $index => $hashedCode) {
            if (Hash::check($code, $hashedCode)) {
                unset($storedCodes[$index]);
                $user->forceFill(['mfa_backup_codes' => array_values($storedCodes)])->save();

                return true;
            }
        }

        return false;
    }

    /**
     * Verify either a TOTP code or a backup code.
     */
    public function verifyAnyCode(User $user, string $code): bool
    {
        if ($this->verifyUserCode($user, $code)) {
            return true;
        }

        return $this->verifyAndConsumeBackupCode($user, $code);
    }

    /**
     * Enable MFA for the user after confirming the TOTP code.
     * Stores the encrypted secret and returns plaintext backup codes.
     *
     * @return list<string>
     */
    public function enableMfa(User $user, string $pendingSecret, MfaMethod $method = MfaMethod::Totp): array
    {
        [$plaintextCodes, $hashedCodes] = $this->generateBackupCodes();

        $user->forceFill([
            'mfa_secret' => Crypt::encryptString($pendingSecret),
            'mfa_method' => $method->value,
            'mfa_enabled' => true,
            'mfa_confirmed_at' => now(),
            'mfa_backup_codes' => $hashedCodes,
        ])->save();

        return $plaintextCodes;
    }

    /**
     * Disable MFA for the user.
     */
    public function disableMfa(User $user): void
    {
        $user->forceFill([
            'mfa_secret' => null,
            'mfa_method' => null,
            'mfa_enabled' => false,
            'mfa_confirmed_at' => null,
            'mfa_backup_codes' => null,
        ])->save();
    }

    /**
     * Regenerate backup codes for the user.
     *
     * @return list<string>
     */
    public function regenerateBackupCodes(User $user): array
    {
        [$plaintextCodes, $hashedCodes] = $this->generateBackupCodes();

        $user->forceFill(['mfa_backup_codes' => $hashedCodes])->save();

        return $plaintextCodes;
    }

    /**
     * Update the MFA method preference.
     */
    public function updateMethod(User $user, MfaMethod $method): void
    {
        $user->forceFill(['mfa_method' => $method->value])->save();
    }

    /**
     * Store a pending MFA session and return the temporary token.
     */
    public function storePendingSession(User $user): string
    {
        $token = Str::uuid()->toString();

        Cache::put(
            self::PENDING_MFA_CACHE_PREFIX.$token,
            ['user_identifier' => $user->identifier],
            self::PENDING_MFA_TTL_SECONDS,
        );

        return $token;
    }

    /**
     * Resolve the user from a pending MFA token.
     *
     * @return array{user_identifier: string}|null
     */
    public function getPendingSession(string $token): ?array
    {
        /** @var array{user_identifier: string}|null */
        return Cache::get(self::PENDING_MFA_CACHE_PREFIX.$token);
    }

    /**
     * Consume (delete) the pending MFA session.
     */
    public function consumePendingSession(string $token): void
    {
        Cache::forget(self::PENDING_MFA_CACHE_PREFIX.$token);
    }

    /**
     * Generate plaintext backup codes and their hashed equivalents.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    private function generateBackupCodes(): array
    {
        $plaintextCodes = [];
        $hashedCodes = [];

        for ($i = 0; $i < self::BACKUP_CODE_COUNT; $i++) {
            $code = strtoupper(Str::random(self::BACKUP_CODE_LENGTH));
            $plaintextCodes[] = $code;
            $hashedCodes[] = Hash::make($code);
        }

        return [$plaintextCodes, $hashedCodes];
    }

    private function renderQrCodeSvg(string $otpauthUrl): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(
                size: 200,
                margin: 1,
                fill: Fill::uniformColor(
                    new Rgb(255, 255, 255),
                    new Rgb(0, 0, 0),
                ),
            ),
            new SvgImageBackEnd,
        );

        $writer = new Writer($renderer);

        return $writer->writeString($otpauthUrl);
    }
}
