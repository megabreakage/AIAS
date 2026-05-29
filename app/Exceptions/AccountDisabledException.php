<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class AccountDisabledException extends ApiException
{
    public function __construct(string $message = 'Account is disabled.')
    {
        parent::__construct($message, Response::HTTP_FORBIDDEN, 'ACCOUNT_DISABLED');
    }
}
