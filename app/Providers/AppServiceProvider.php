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
        // Global API limiter (applied to the whole api group via throttleApi()).
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip()));

        // Tight limiter for login to blunt credential brute-force (per email + IP).
        RateLimiter::for('auth', fn (Request $request): Limit => Limit::perMinute(6)
            ->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        // Public contact form — a few submissions per minute per IP.
        RateLimiter::for('contact', fn (Request $request): Limit => Limit::perMinute(5)
            ->by($request->ip()));

        // Public analytics beacon — generous but bounded to stop flooding.
        RateLimiter::for('page-views', fn (Request $request): Limit => Limit::perMinute(30)
            ->by($request->ip()));

        // Comments are held for moderation anyway, but a tight limit keeps a
        // bot from filling the queue faster than it can be emptied.
        RateLimiter::for('comments', fn (Request $request): Limit => Limit::perMinute(3)
            ->by($request->ip()));

        // Likes are a toggle, so repeated calls are legitimate — this only
        // stops someone hammering the endpoint.
        RateLimiter::for('likes', fn (Request $request): Limit => Limit::perMinute(20)
            ->by($request->ip()));
    }
}
