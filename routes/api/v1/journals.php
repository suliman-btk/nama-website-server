<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\JournalController;

/*
|--------------------------------------------------------------------------
| Journals API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::get('/journals', [JournalController::class, 'index']);
Route::get('/journals/{journal}', [JournalController::class, 'show']);

// Admin routes (protected)
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::apiResource('admin/journals', JournalController::class);
});
