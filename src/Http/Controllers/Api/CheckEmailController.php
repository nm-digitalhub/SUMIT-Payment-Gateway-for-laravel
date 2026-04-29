<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * CheckEmailController
 *
 * API controller for checking if a user exists by email during checkout.
 * Used to enforce login requirement for existing customers.
 *
 * @since v1.15.0
 */
class CheckEmailController extends Controller
{
    /**
     * Check if a user exists with the given email address.
     *
     * Performs a case-insensitive email lookup to determine if a user
     * account already exists. If found, returns a login URL with return_url
     * parameter to redirect back to checkout after authentication.
     *
     * @return JsonResponse
     *
     * Response format:
     * {
     *   "exists": boolean,
     *   "login_url": string|null
     * }
     */
    public function check(Request $request): JsonResponse
    {
        // Validate email input
        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        // Normalize email (lowercase, trim whitespace)
        $email = strtolower(trim((string) $validated['email']));

        $userModel = app('officeguy.customer_model');
        if (! $userModel) {
            return response()->json(['exists' => false, 'login_url' => null]);
        }

        $exists = $userModel::whereRaw('LOWER(email) = ?', [$email])->exists();

        // Log email check for monitoring (optional, based on config)
        if (config('officeguy.logging', false)) {
            Log::channel(config('officeguy.log_channel', 'stack'))->info(
                '[SUMIT] Email existence check',
                [
                    'email' => $email,
                    'exists' => $exists,
                    'ip' => $request->ip(),
                ]
            );
        }

        // Prepare response
        $response = [
            'exists' => $exists,
            'login_url' => null,
        ];

        // If user exists, generate login URL with return_url parameter
        if ($exists) {
            // Get the referring URL (checkout page) for post-login redirect
            $returnUrl = $request->header('Referer') ?? url()->previous();

            $loginRoute = config('officeguy.routes.client_login_route', 'login');
            $response['login_url'] = route($loginRoute, array_filter([
                'return_url' => $returnUrl,
            ]));
        }

        return response()->json($response);
    }
}
