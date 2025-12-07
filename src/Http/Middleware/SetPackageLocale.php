<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Set Application Locale for Package Routes
 *
 * This middleware ensures that the locale is set correctly for all package routes,
 * especially the public checkout page. It reads from session first, then falls back
 * to the default locale.
 *
 * Priority: This middleware MUST run BEFORE the controller is executed so that
 * app()->getLocale() returns the correct value in views.
 */
class SetPackageLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get available locales from config
        $availableLocales = array_keys(config('app.available_locales', []));

        // Priority order:
        // 1. Locale from session (set by locale.change route)
        // 2. Locale from request parameter (for direct links)
        // 3. Default locale from config
        $locale = session('locale')
            ?? $request->query('locale')
            ?? $request->get('locale')
            ?? config('app.locale', 'he');

        // Validate that the locale is available
        if (!in_array($locale, $availableLocales)) {
            $locale = config('app.locale', 'he');
        }

        // Set the application locale
        app()->setLocale($locale);

        // Persist in session for future requests
        if (!session()->has('locale')) {
            session(['locale' => $locale]);
        }

        \Log::debug('📦 OfficeGuy Package - SetPackageLocale', [
            'url' => $request->fullUrl(),
            'session_locale' => session('locale'),
            'final_locale' => $locale,
            'app_locale' => app()->getLocale(),
        ]);

        return $next($request);
    }
}
