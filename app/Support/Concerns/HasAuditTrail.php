<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use OwenIt\Auditing\Auditable;

trait HasAuditTrail
{
    use Auditable;
}
