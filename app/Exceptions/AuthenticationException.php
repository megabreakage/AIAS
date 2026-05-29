<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class AuthenticationException extends ApiException
{
    public function __construct(string $message = 'Invalid credentials.')
    {
        parent::__construct($message, Response::HTTP_UNAUTHORIZED, 'INVALID_CREDENTIALS');
    }
}
