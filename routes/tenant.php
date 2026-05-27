<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\PriorityLevelController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant API Routes
|--------------------------------------------------------------------------
| These routes are scoped to a specific tenant context and are accessible
| from tenant domains (e.g., acme.localhost).
|
*/

Route::prefix('v1')->group(function () {
    // Health check (per-tenant)
    Route::get('/health', function () {
        return response()->json([
            'data' => [
                'status' => 'ok',
                'tenant' => tenant()?->id,
            ],
            'meta' => ['version' => 'v1'],
        ]);
    });

    // Tenant user authentication (public)
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:10,1');

        Route::middleware(['auth:api', 'tenant.token'])->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    // Protected tenant routes
    Route::middleware(['auth:api', 'tenant.token'])->group(function () {
        // Priority Levels
        Route::apiResource('priority-levels', PriorityLevelController::class);
        Route::post('priority-levels/{id}/restore', [PriorityLevelController::class, 'restore'])
            ->name('priority_levels.restore');
    });
});
