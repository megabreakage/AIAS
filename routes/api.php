<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\MfaController;
use App\Http\Controllers\Api\V1\Central\CentralUserController;
use App\Http\Controllers\Api\V1\Central\ContinentController;
use App\Http\Controllers\Api\V1\Central\CountryController;
use App\Http\Controllers\Api\V1\Central\TenantUserController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\PriorityLevelController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', [HealthController::class, 'index']);

    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:10,1');

        Route::post('/mfa/verify', [MfaController::class, 'verifyLogin'])
            ->middleware('throttle:10,1');

        Route::middleware('auth:api')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    Route::middleware('auth:api')->prefix('mfa')->group(function () {
        Route::get('/status', [MfaController::class, 'status'])->name('api.mfa.status');
        Route::post('/setup', [MfaController::class, 'setup'])->name('api.mfa.setup');
        Route::post('/confirm', [MfaController::class, 'confirm'])->name('api.mfa.confirm');
        Route::post('/disable', [MfaController::class, 'disable'])->name('api.mfa.disable');
        Route::post('/backup-codes', [MfaController::class, 'regenerateBackupCodes'])->name('api.mfa.backup-codes');
        Route::put('/method', [MfaController::class, 'updateMethod'])->name('api.mfa.method');
    });

    Route::middleware('auth:api')->group(function () {
        Route::post('/users', [CentralUserController::class, 'store']);

        Route::get('/tenants', [TenantController::class, 'index']);
        Route::post('/tenants', [TenantController::class, 'store']);
        Route::get('/tenants/{identifier}', [TenantController::class, 'show']);
        Route::put('/tenants/{identifier}', [TenantController::class, 'update']);
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

    // Tenant-scoped routes (accessible by authenticated tenant users)
    Route::middleware(['auth:api', 'tenant.token'])->group(function () {
        // Priority Levels
        Route::apiResource('priority-levels', PriorityLevelController::class);
        Route::post('priority-levels/{id}/restore', [PriorityLevelController::class, 'restore'])
            ->name('priority_levels.restore');
    });
});
