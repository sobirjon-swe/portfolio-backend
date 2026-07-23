<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the request locale from `?lang` or the `Accept-Language` header,
 * restricted to the supported set, and applies it for the request lifecycle.
 * Translatable models then return content in this locale (with fallback).
 */
class SetLocale
{
    private const SUPPORTED = ['en', 'uz', 'ru'];

    public function handle(Request $request, Closure $next): Response
    {
        $lang = $request->query('lang');

        if (! is_string($lang) || ! in_array($lang, self::SUPPORTED, true)) {
            $lang = $request->getPreferredLanguage(self::SUPPORTED);
        }

        app()->setLocale(
            in_array($lang, self::SUPPORTED, true) ? $lang : (string) config('app.locale')
        );

        return $next($request);
    }
}
