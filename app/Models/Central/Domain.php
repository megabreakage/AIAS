<?php

declare(strict_types=1);

namespace App\Models\Central;

use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

final class Domain extends BaseDomain
{
    protected $connection = 'central';
}
