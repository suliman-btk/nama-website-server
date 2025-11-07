<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

/*
|--------------------------------------------------------------------------
| Authentication API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::middleware(['throttle:api-public'])->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/create-admin', [AuthController::class, 'createAdmin']);
});

// Protected routes
Route::middleware(['auth:sanctum', 'throttle:api-authenticated'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
});
