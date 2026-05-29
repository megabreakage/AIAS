<?php

declare(strict_types=1);

namespace App\Enums;

enum AuditScope: string
{
    case Internal = 'internal';
    case External = 'external';
    case ServiceProvider = 'service_provider';
    case Supplier = 'supplier';
}
