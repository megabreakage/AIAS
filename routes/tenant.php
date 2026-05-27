<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Tenant\ChecklistTypeController;
use App\Http\Controllers\Api\V1\Tenant\PreambleController;
use App\Http\Controllers\Api\V1\Tenant\SectionStyleController;
use App\Http\Controllers\Api\V1\Tenant\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant API Routes
|--------------------------------------------------------------------------
| These routes are scoped to a specific tenant context. Tenant is identified
| via the `tenant_id` body/query parameter (the tenant's UUID identifier).
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
        // Preamble routes
        Route::get('/preambles', [PreambleController::class, 'index']);
        Route::post('/preambles', [PreambleController::class, 'store']);
        Route::get('/preambles/{identifier}', [PreambleController::class, 'show']);
        Route::put('/preambles/{identifier}', [PreambleController::class, 'update']);
        Route::delete('/preambles/{identifier}', [PreambleController::class, 'destroy']);
        Route::post('/preambles/{identifier}/restore', [PreambleController::class, 'restore']);

        // User management routes
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{identifier}', [UserController::class, 'show']);
        Route::put('/users/{identifier}', [UserController::class, 'update']);
        Route::delete('/users/{identifier}', [UserController::class, 'destroy']);
        Route::post('/users/{identifier}/restore', [UserController::class, 'restore']);

        // Checklist type routes
        Route::get('/checklist-types', [ChecklistTypeController::class, 'index']);
        Route::post('/checklist-types', [ChecklistTypeController::class, 'store']);
        Route::get('/checklist-types/{identifier}', [ChecklistTypeController::class, 'show']);
        Route::put('/checklist-types/{identifier}', [ChecklistTypeController::class, 'update']);
        Route::delete('/checklist-types/{identifier}', [ChecklistTypeController::class, 'destroy']);
        Route::post('/checklist-types/{identifier}/restore', [ChecklistTypeController::class, 'restore']);

        // Section style routes
        Route::get('/section-styles', [SectionStyleController::class, 'index']);
        Route::post('/section-styles', [SectionStyleController::class, 'store']);
        Route::get('/section-styles/{identifier}', [SectionStyleController::class, 'show']);
        Route::put('/section-styles/{identifier}', [SectionStyleController::class, 'update']);
        Route::delete('/section-styles/{identifier}', [SectionStyleController::class, 'destroy']);
        Route::post('/section-styles/{identifier}/restore', [SectionStyleController::class, 'restore']);
    });
});
