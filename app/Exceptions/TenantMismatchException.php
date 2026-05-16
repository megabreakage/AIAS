<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class TenantMismatchException extends ApiException
{
    public function __construct(string $message = 'Token does not match the current tenant.')
    {
        parent::__construct($message, Response::HTTP_FORBIDDEN, 'TENANT_MISMATCH');
    }
}
