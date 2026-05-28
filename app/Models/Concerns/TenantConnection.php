<?php

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * Trait for tenant-scoped models.
 *
 * Explicitly sets the connection to 'tenant' so queries always
 * target the tenant database, even when the default connection
 * has not been switched by the tenancy bootstrapper.
 */
trait TenantConnection
{
    public function getConnectionName(): ?string
    {
        return 'tenant';
    }
}
