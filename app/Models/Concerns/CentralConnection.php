<?php

declare(strict_types=1);

namespace App\Models\Concerns;

trait CentralConnection
{
    public function getConnectionName(): string
    {
        return 'central';
    }
}
