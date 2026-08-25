<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Auth endpoints: 5 attempts / 15 min per IP (production only).
        // In local/testing, use loose limit so Playwright can run all tests.
        $authLimit = app()->isProduction() ? 5 : 100;
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinutes(15, $authLimit)->by($request->ip())->response(
            fn () => response()->json(['message' => 'Terlalu banyak percobaan. Coba lagi nanti.'], 429)
        )
        );

        // Mutation endpoints: 60 / min per user.
        RateLimiter::for('mutations', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?? $request->ip())
        );

        // Print-bridge endpoints: 120 / min per IP (Android bridge polls).
        RateLimiter::for('print-bridge', fn (Request $request) => Limit::perMinute(120)->by($request->ip())
        );
    }
}
