<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;

final class HealthController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->success([
            'status'    => 'ok',
            'timestamp' => now()->toISOString(),
            'version'   => config('app.version', '1.0.0'),
        ]);
    }
}
