<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Passport\TenantAwareAccessTokenRepository;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            AccessTokenRepositoryInterface::class,
            TenantAwareAccessTokenRepository::class,
        );
    }

    public function boot(): void
    {
        // Passport oauth_* tables live on the central DB
        Passport::useClientModel(\App\Models\Central\PassportClient::class);
        Passport::useTokenModel(\App\Models\Central\PassportToken::class);

        Passport::tokenModel()::saving(function ($token): void {
            if (function_exists('tenant') && tenant() && empty($token->tenant_id)) {
                $token->tenant_id = tenant()->id;
            }
        });
    }
}
