<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

trait RespondsWithJson
{
    protected function success(mixed $data = null, int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse([
            'data' => $data instanceof JsonResource ? $data->resolve() : $data,
            'meta' => $this->meta(),
        ], $status);
    }

    protected function paginated(LengthAwarePaginator $paginator, string $resourceClass): JsonResponse
    {
        $collection = $resourceClass::collection($paginator);
        $payload    = $collection->response()->getData(true);
        $payload['meta'] = array_merge($payload['meta'] ?? [], $this->meta());

        return new JsonResponse($payload);
    }

    protected function error(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code'    => $code,
                'message' => $message,
                'details' => $details ?: null,
            ],
            'meta' => $this->meta(),
        ], $status);
    }

    /** @return array<string, mixed> */
    private function meta(): array
    {
        return [
            'request_id' => request()->header('X-Request-Id', (string) Str::ulid()),
            'version'    => request()->attributes->get('api_version', 'v1'),
            'tenant'     => function_exists('tenant') && tenant() ? tenant()->id : null,
        ];
    }
}
