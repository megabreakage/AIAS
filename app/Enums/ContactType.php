<?php

declare(strict_types=1);

namespace App\Enums;

enum ContactType: string
{
    case Primary = 'primary';
    case Secondary = 'secondary';
    case Billing = 'billing';
    case Technical = 'technical';
}
