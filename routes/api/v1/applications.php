<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ApplicationController;

/*
|--------------------------------------------------------------------------
| Applications API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::middleware(['throttle:api-public'])->group(function () {
    Route::post('/applications', [ApplicationController::class, 'store']);
});

// Admin routes (protected)
Route::middleware(['auth:sanctum', 'admin', 'throttle:api-authenticated'])->group(function () {
    Route::apiResource('admin/applications', ApplicationController::class);
    Route::patch('/admin/applications/{application}/approve', [ApplicationController::class, 'approve']);
    Route::patch('/admin/applications/{application}/reject', [ApplicationController::class, 'reject']);
});
