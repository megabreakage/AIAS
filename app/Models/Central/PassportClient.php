<?php

declare(strict_types=1);

namespace App\Models\Central;

use Laravel\Passport\Client;

final class PassportClient extends Client
{
    protected $connection = 'central';
}
