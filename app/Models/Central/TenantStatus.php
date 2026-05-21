<?php

declare(strict_types=1);

namespace App\Models\Central;

enum TenantStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
    case PendingSetup = 'pending_setup';
}
