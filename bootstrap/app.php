<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [
            SetLocale::class,
        ]);
        // Apply the "api" rate limiter (60/min) to the whole api group.
        $middleware->throttleApi();

        // Guests hitting a protected endpoint must get 401, not a redirect.
        // Without this the Authenticate middleware resolves route('login') to
        // build a redirect target — a route this API does not have — and the
        // RouteNotFoundException surfaces as a 500 for any client that did not
        // send `Accept: application/json` (i.e. a browser address bar).
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
