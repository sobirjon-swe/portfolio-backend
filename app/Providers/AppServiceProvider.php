<?php

namespace App\Providers;

use App\Services\TelegramNotifier;
use App\Support\ReferrerNormalizer;
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
        // Credentials are read once here rather than inside the notifier, so
        // tests can bind a differently-configured instance.
        $this->app->singleton(TelegramNotifier::class, fn (): TelegramNotifier => new TelegramNotifier(
            config('services.telegram.bot_token'),
            config('services.telegram.chat_id'),
        ));

        // Which hosts count as "our own" — and so as a direct visit rather
        // than a referral — is deployment configuration, not something the
        // normalizer should reach out and read for itself.
        $this->app->singleton(ReferrerNormalizer::class, fn (): ReferrerNormalizer => new ReferrerNormalizer(
            (array) config('analytics.own_hosts', []),
        ));
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

        // Recommendations are moderated anyway; this stops a bot filling the
        // queue faster than it can be emptied. Tighter than comments because a
        // genuine visitor writes at most one.
        RateLimiter::for('recommendations', fn (Request $request): Limit => Limit::perMinute(2)
            ->by($request->ip()));

        // Likes are a toggle, so repeated calls are legitimate — this only
        // stops someone hammering the endpoint.
        RateLimiter::for('likes', fn (Request $request): Limit => Limit::perMinute(20)
            ->by($request->ip()));
    }
}
