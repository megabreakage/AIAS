<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Central\CentralUserController;
use App\Http\Controllers\Api\V1\Central\ContinentController;
use App\Http\Controllers\Api\V1\Central\CountryController;
use App\Http\Controllers\Api\V1\Central\TenantUserController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\TenantController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', [HealthController::class, 'index']);

    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:10,1');

        Route::middleware('auth:api')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    Route::middleware('auth:api')->group(function () {
        Route::post('/users', [CentralUserController::class, 'store']);

        Route::get('/tenants', [TenantController::class, 'index']);
        Route::post('/tenants', [TenantController::class, 'store']);
        Route::get('/tenants/{id}', [TenantController::class, 'show']);
        Route::delete('/tenants/{id}', [TenantController::class, 'destroy']);

        Route::post('/tenants/{id}/users', [TenantUserController::class, 'store']);

        Route::get('/continents', [ContinentController::class, 'index']);
        Route::post('/continents', [ContinentController::class, 'store']);
        Route::get('/continents/{identifier}', [ContinentController::class, 'show']);
        Route::put('/continents/{identifier}', [ContinentController::class, 'update']);
        Route::delete('/continents/{identifier}', [ContinentController::class, 'destroy']);
        Route::post('/continents/{identifier}/restore', [ContinentController::class, 'restore']);

        Route::get('/countries', [CountryController::class, 'index']);
        Route::post('/countries', [CountryController::class, 'store']);
        Route::get('/countries/{identifier}', [CountryController::class, 'show']);
        Route::put('/countries/{identifier}', [CountryController::class, 'update']);
        Route::delete('/countries/{identifier}', [CountryController::class, 'destroy']);
        Route::post('/countries/{identifier}/restore', [CountryController::class, 'restore']);
    });
});
