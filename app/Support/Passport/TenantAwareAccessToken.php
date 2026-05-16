<?php

declare(strict_types=1);

namespace App\Support\Passport;

use Laravel\Passport\Bridge\AccessToken;

final class TenantAwareAccessToken extends AccessToken
{
    public ?string $tenantId = null;
}
