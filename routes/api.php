<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\SuperAdminAuthController;
use App\Http\Controllers\Api\V1\Central\ContinentController;
use App\Http\Controllers\Api\V1\Central\CountryController;
use App\Http\Controllers\Api\V1\Central\TenantUserController;
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

        // Tenant user management by super-admin
        Route::post('/tenants/{id}/users', [TenantUserController::class, 'store']);

        // Continent management
        Route::get('/continents', [ContinentController::class, 'index']);
        Route::post('/continents', [ContinentController::class, 'store']);
        Route::get('/continents/{identifier}', [ContinentController::class, 'show']);
        Route::put('/continents/{identifier}', [ContinentController::class, 'update']);
        Route::delete('/continents/{identifier}', [ContinentController::class, 'destroy']);
        Route::post('/continents/{identifier}/restore', [ContinentController::class, 'restore']);

        // Country management
        Route::get('/countries', [CountryController::class, 'index']);
        Route::post('/countries', [CountryController::class, 'store']);
        Route::get('/countries/{identifier}', [CountryController::class, 'show']);
        Route::put('/countries/{identifier}', [CountryController::class, 'update']);
        Route::delete('/countries/{identifier}', [CountryController::class, 'destroy']);
        Route::post('/countries/{identifier}/restore', [CountryController::class, 'restore']);
    });
});
