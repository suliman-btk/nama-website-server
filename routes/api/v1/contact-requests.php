<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ContactRequestController;

/*
|--------------------------------------------------------------------------
| Contact Requests API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/contact-requests', [ContactRequestController::class, 'store']);

// Admin routes (protected)
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::apiResource('admin/contact-requests', ContactRequestController::class);
    Route::post('/admin/contact-requests/{contactRequest}/reply', [ContactRequestController::class, 'reply']);
});
