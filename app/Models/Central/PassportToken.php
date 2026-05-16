<?php

declare(strict_types=1);

namespace App\Models\Central;

use Laravel\Passport\Token;

final class PassportToken extends Token
{
    protected $connection = 'central';
}
