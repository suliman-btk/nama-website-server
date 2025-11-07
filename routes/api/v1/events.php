<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EventController;

/*
|--------------------------------------------------------------------------
| Events API Routes
|--------------------------------------------------------------------------
*/

// Public routes (with optional authentication)
Route::middleware(['throttle:api-public'])->group(function () {
    Route::get('/events', [EventController::class, 'index']);
    Route::get('/events/{event}', [EventController::class, 'show']);
});

// Admin routes (protected)
Route::middleware(['auth:sanctum', 'admin', 'throttle:api-authenticated'])->group(function () {
    Route::apiResource('admin/events', EventController::class);
    Route::post('/admin/events/{event}/galleries', [EventController::class, 'addGallery']);
    Route::delete('/admin/events/{event}/galleries/{gallery}', [EventController::class, 'removeGallery']);
    Route::patch('/admin/events/{event}/status', [EventController::class, 'updateStatus']);
});
