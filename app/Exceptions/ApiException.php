<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class ApiException extends RuntimeException
{
    public function __construct(
        string $message = 'An error occurred.',
        protected int $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR,
        protected ?string $errorCode = null,
        protected array $details = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code'    => $this->errorCode ?? 'INTERNAL_ERROR',
                'message' => $this->getMessage(),
                'details' => $this->details ?: null,
            ],
        ], $this->statusCode);
    }
}
