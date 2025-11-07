<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ContactRequestController;

/*
|--------------------------------------------------------------------------
| Contact Requests API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::middleware(['throttle:api-public'])->group(function () {
    Route::post('/contact-requests', [ContactRequestController::class, 'store']);
});

// Admin routes (protected)
Route::middleware(['auth:sanctum', 'admin', 'throttle:api-authenticated'])->group(function () {
    Route::apiResource('admin/contact-requests', ContactRequestController::class);
    Route::post('/admin/contact-requests/{contactRequest}/reply', [ContactRequestController::class, 'reply']);
});
