<?php

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * Marker trait for tenant-scoped models.
 *
 * Stancl/Tenancy v3 DatabaseTenancyBootstrapper switches the
 * default database connection to the tenant DB when tenancy is
 * initialised. Models using this trait (without an explicit
 * $connection) automatically query the tenant database.
 */
trait TenantConnection
{
    //
}
