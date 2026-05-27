<?php

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * Marker trait for tenant-scoped models.
 *
 * Stancl/Tenancy v3 handles connection switching automatically.
 * Models extending BaseModel in a tenant migration will use the
 * correct tenant database connection without explicit configuration.
 */
trait TenantConnection
{
    //
}
