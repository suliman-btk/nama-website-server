<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\JournalController;
use App\Http\Controllers\Api\VolunteerApplicationController;
use App\Http\Controllers\Api\ContactRequestController;
use App\Http\Controllers\Api\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::prefix('v1')->group(function () {
    // Events (public access to published only)
    Route::get('/events', [EventController::class, 'index']);
    Route::get('/events/{event}', [EventController::class, 'show']);

    // Journals (public access to published only)
    Route::get('/journals', [JournalController::class, 'index']);
    Route::get('/journals/{journal}', [JournalController::class, 'show']);

    // Volunteer applications (public submission)
    Route::post('/volunteer-applications', [VolunteerApplicationController::class, 'store']);

    // Contact requests (public submission)
    Route::post('/contact-requests', [ContactRequestController::class, 'store']);

    // Authentication
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/auth/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
});

// Admin routes (protected)
Route::prefix('v1/admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    // Events management
    Route::apiResource('events', EventController::class);
    Route::post('/events/{event}/galleries', [EventController::class, 'addGallery']);
    Route::delete('/events/{event}/galleries/{gallery}', [EventController::class, 'removeGallery']);

    // Journals management
    Route::apiResource('journals', JournalController::class);

    // Volunteer applications management
    Route::apiResource('volunteer-applications', VolunteerApplicationController::class);
    Route::patch('/volunteer-applications/{volunteerApplication}/approve', [VolunteerApplicationController::class, 'approve']);
    Route::patch('/volunteer-applications/{volunteerApplication}/reject', [VolunteerApplicationController::class, 'reject']);

    // Contact requests management
    Route::apiResource('contact-requests', ContactRequestController::class);
    Route::post('/contact-requests/{contactRequest}/reply', [ContactRequestController::class, 'reply']);
});
