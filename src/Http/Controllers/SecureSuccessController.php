<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use OfficeGuy\LaravelSumitGateway\Events\SuccessPageAccessed;
use OfficeGuy\LaravelSumitGateway\Services\SuccessAccessValidator;

/**
 * Secure Success Controller
 *
 * Displays success page after payment completion.
 * Implements 7-layer security architecture via SuccessAccessValidator.
 *
 * CRITICAL: This controller contains 0% business logic!
 * - Provisioning is done via Webhook → PaymentConfirmed event
 * - This page is ONLY for displaying success UI
 * - All validation is delegated to SuccessAccessValidator
 *
 * Security:
 * - Rate limited (via middleware)
 * - Signed URL validation
 * - One-time token validation
 * - Replay attack protection
 * - Guest-safe (works for both auth and guest checkout)
 */
class SecureSuccessController extends Controller
{
    public function __construct(
        protected SuccessAccessValidator $validator
    ) {}

    /**
     * Display success page
     *
     * Validates access using 7-layer security architecture.
     * Dispatches SuccessPageAccessed event for analytics.
     * Returns success view with payable entity.
     */
    public function show(Request $request): View | Response
    {
        // Validate access through all 7 security layers
        $result = $this->validator->validate($request);

        // If validation failed, show error
        if ($result->isFailed()) {
            Log::warning('Success page access denied', [
                'failures' => $result->getFailures(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->view('officeguy::errors.access-denied', [
                'error_message' => $result->getErrorMessage(),
                'failures' => $result->getFailures(),
            ], 403);
        }

        // Access validated! Get payable entity
        $payable = $result->getPayable();
        $token = $result->getToken();

        // Log successful access
        Log::info('Success page accessed', [
            'payable_id' => $payable->getKey(),
            'payable_type' => $payable !== null ? $payable::class : self::class,
            'ip' => $request->ip(),
        ]);

        // Dispatch event for analytics (NOT for provisioning!)
        event(new SuccessPageAccessed($payable, $token));

        // Update analytics on the payable (if method exists)
        if (method_exists($payable, 'recordSuccessPageAccess')) {
            $payable->recordSuccessPageAccess();
        }

        // Return success view with payable data
        return view('officeguy::success', [
            'payable' => $payable,
            'token' => $token,
            'order' => $payable, // Alias for backward compatibility
        ]);
    }

    /**
     * Health check endpoint
     *
     * For monitoring and testing the success page infrastructure.
     */
    public function health(): Response
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'secure-success-page',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
