<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\JournalController;

/*
|--------------------------------------------------------------------------
| Journals API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::middleware(['throttle:api-public'])->group(function () {
    Route::get('/journals', [JournalController::class, 'index']);
    Route::get('/journals/{journal}', [JournalController::class, 'show']);
});

// Admin routes (protected)
Route::middleware(['auth:sanctum', 'admin', 'throttle:api-authenticated'])->group(function () {
    Route::apiResource('admin/journals', JournalController::class);
});
