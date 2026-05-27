<?php

declare(strict_types=1);

use App\Exceptions\ApiException;
use App\Http\Middleware\EnsureTokenMatchesTenant;
use App\Http\Middleware\ForceJsonBody;
use App\Providers\AuthServiceProvider;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        AuthServiceProvider::class,
    ])
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            ForceJsonBody::class,
        ]);

        $middleware->alias([
            'tenant.token' => EnsureTokenMatchesTenant::class,
            'tenant.body'  => \App\Http\Middleware\InitializeTenancyByBodyParam::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ApiException $e, Request $request): JsonResponse {
            return $e->render($request);
        });

        $exceptions->render(function (ValidationException $e, Request $request): ?JsonResponse {
            if (!$request->expectsJson() && !$request->is('api/*')) {
                return null;
            }

            return new JsonResponse([
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => $e->getMessage(),
                    'details' => $e->errors(),
                ],
                'meta' => [
                    'request_id' => $request->header('X-Request-Id', (string) Str::ulid()),
                    'version' => $request->attributes->get('api_version', 'v1'),
                ],
            ], 422);
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request): ?JsonResponse {
            if (!$request->expectsJson() && !$request->is('api/*')) {
                return null;
            }

            return new JsonResponse([
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'Resource not found.',
                    'details' => null,
                ],
                'meta' => [
                    'request_id' => $request->header('X-Request-Id', (string) Str::ulid()),
                    'version' => $request->attributes->get('api_version', 'v1'),
                ],
            ], 404);
        });
    })->create();
