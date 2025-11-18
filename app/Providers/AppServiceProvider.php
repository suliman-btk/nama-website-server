<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Public API rate limit: 60 requests per minute per IP
        RateLimiter::for('api-public', function (Request $request) {
            return Limit::perMinute(100)->by($request->ip());
        });

        // Authenticated API rate limit: 200 requests per minute per user
        RateLimiter::for('api-authenticated', function (Request $request) {
            return Limit::perMinute(200)->by($request->user()?->id ?: $request->ip());
        });
    }
}
