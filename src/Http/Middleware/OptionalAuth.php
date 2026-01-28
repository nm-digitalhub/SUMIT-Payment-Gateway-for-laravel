<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional Authentication Middleware
 *
 * This middleware allows both authenticated and guest users to access routes.
 * Unlike the standard 'auth' middleware which redirects guests to login,
 * this middleware simply attempts to authenticate the user if a session exists,
 * but allows the request to continue regardless of authentication status.
 *
 * Use Case: Checkout pages where logged-in users get auto-filled forms,
 * but guests can still complete the purchase after registering/logging in.
 */
class OptionalAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Attempt to authenticate the user if a session exists
        // This will set Auth::user() if valid session is found
        // But will NOT redirect if no session exists
        if ($request->hasSession()) {
            $user = Auth::guard('web')->user();

            // Only set user if they are authenticated
            if ($user !== null) {
                Auth::guard('web')->setUser($user);
            }
        }

        // Allow the request to continue regardless of authentication status
        return $next($request);
    }
}
