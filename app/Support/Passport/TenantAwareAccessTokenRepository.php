<?php

declare(strict_types=1);

namespace App\Support\Passport;

use Laravel\Passport\Bridge\AccessTokenRepository;
use League\OAuth2\Server\Entities\ClientEntityInterface;

final class TenantAwareAccessTokenRepository extends AccessTokenRepository
{
    public function getNewToken(
        ClientEntityInterface $clientEntity,
        array $scopes,
        ?string $userIdentifier = null
    ): TenantAwareAccessToken {
        $token           = new TenantAwareAccessToken($userIdentifier, $scopes, $clientEntity);
        $token->tenantId = function_exists('tenant') && tenant() ? tenant()->id : null;

        return $token;
    }
}
