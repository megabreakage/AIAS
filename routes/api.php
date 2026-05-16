<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\SuperAdminAuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\TenantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central API Routes
|--------------------------------------------------------------------------
| These routes are accessible from the central domain only.
| They handle super-admin authentication and tenant management.
|
*/

Route::prefix('v1')->group(function () {
    // Health check
    Route::get('/health', [HealthController::class, 'index']);

    // Super Admin authentication (public)
    Route::prefix('auth')->group(function () {
        Route::post('/login', [SuperAdminAuthController::class, 'login'])
            ->middleware('throttle:10,1');

        Route::middleware('auth:super_admin')->group(function () {
            Route::post('/logout', [SuperAdminAuthController::class, 'logout']);
            Route::get('/me', [SuperAdminAuthController::class, 'me']);
        });
    });

    // Tenant management (super-admin only)
    Route::middleware('auth:super_admin')->group(function () {
        Route::get('/tenants', [TenantController::class, 'index']);
        Route::post('/tenants', [TenantController::class, 'store']);
        Route::get('/tenants/{id}', [TenantController::class, 'show']);
        Route::delete('/tenants/{id}', [TenantController::class, 'destroy']);
    });
});
