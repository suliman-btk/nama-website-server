<?php

use Illuminate\Support\Facades\Route;

// Simple health check
Route::get('/health', function () {
    return response()->json(['message' => 'API is running']);
});

Route::prefix('v1')->name('api.v1.')->group(function () {
    // Load every file under routes/api/v1/*.php
    foreach (glob(base_path('routes/api/v1/*.php')) as $file) {
        require $file;
    }

    // Health check
    Route::get('health', fn() => response()->json([
        'app' => config('app.name'),
        'version' => 'v1',
        'status' => 'ok',
    ]));

    // API fallback (404 JSON for unknown /api/v1/* routes)
    Route::fallback(function () {
        return response()->json([
            'message' => 'Not Found',
        ], 404);
    });
});
