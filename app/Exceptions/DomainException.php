<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class DomainException extends ApiException
{
    public function __construct(string $message = 'Domain error.', int $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY)
    {
        parent::__construct($message, $statusCode, 'DOMAIN_ERROR');
    }
}
