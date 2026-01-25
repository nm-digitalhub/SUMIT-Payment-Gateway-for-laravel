<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use OfficeGuy\LaravelSumitGateway\Http\Controllers\Api\CheckEmailController;
use OfficeGuy\LaravelSumitGateway\Http\Controllers\BitWebhookController;
use OfficeGuy\LaravelSumitGateway\Http\Controllers\CardCallbackController;
use OfficeGuy\LaravelSumitGateway\Http\Controllers\CrmWebhookController;
use OfficeGuy\LaravelSumitGateway\Http\Controllers\CheckoutController;
use OfficeGuy\LaravelSumitGateway\Http\Controllers\DocumentDownloadController;
use OfficeGuy\LaravelSumitGateway\Http\Controllers\PublicCheckoutController;
use OfficeGuy\LaravelSumitGateway\Http\Controllers\SecureSuccessController;
use OfficeGuy\LaravelSumitGateway\Http\Controllers\SumitWebhookController;
use OfficeGuy\LaravelSumitGateway\Support\RouteConfig;

/*
|--------------------------------------------------------------------------
| OfficeGuy/SUMIT Gateway Routes
|--------------------------------------------------------------------------
|
| These routes handle callbacks and webhooks from the SUMIT payment gateway.
| All paths can be customized via Admin Panel (Gateway Settings > Route Configuration)
| or via config/officeguy.php
|
| After changing routes in Admin Panel, run: php artisan route:clear
|
*/

$prefix = RouteConfig::getPrefix();
$middleware = RouteConfig::getMiddleware();

/*
|--------------------------------------------------------------------------
| Language/Locale Switching (No Prefix - Global)
|--------------------------------------------------------------------------
|
| Handle language switching for the checkout page.
| This route must be OUTSIDE the prefix so it works from any page.
| Stores selected locale in session and redirects back.
|
*/
Route::middleware('web')->post('locale', function () {
    $locale = request('locale');
    $availableLocales = array_keys(config('app.available_locales', []));

    \Log::info('🌍 OfficeGuy Package - Locale Change Request', [
        'requested_locale' => $locale,
        'available_locales' => $availableLocales,
        'session_before' => session('locale'),
        'app_locale_before' => app()->getLocale(),
        'referer' => request()->header('Referer'),
    ]);

    if (in_array($locale, $availableLocales)) {
        session(['locale' => $locale]);
        app()->setLocale($locale);

        \Log::info('✅ OfficeGuy Package - Locale Changed Successfully', [
            'new_locale' => $locale,
            'session_after' => session('locale'),
            'app_locale_after' => app()->getLocale(),
        ]);
    } else {
        \Log::warning('❌ OfficeGuy Package - Invalid Locale Requested', [
            'requested' => $locale,
            'available' => $availableLocales,
        ]);
    }

    if (request()->expectsJson()) {
        return response()->json(['success' => true, 'locale' => $locale]);
    }

    return back();
})->name('officeguy.locale.change');

/*
|--------------------------------------------------------------------------
| Email Check API (v1.15.0+)
|--------------------------------------------------------------------------
|
| Check if a user exists by email during checkout.
| Used to enforce login requirement for existing customers.
| Rate limited to prevent abuse (10 requests/minute).
| CSRF exempt because it's called from public checkout page.
|
*/
Route::middleware(['throttle:10,1'])
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
    ->post($prefix . '/api/check-email', [CheckEmailController::class, 'check'])
    ->name('officeguy.api.check-email');

Route::prefix($prefix)
    ->middleware(array_merge($middleware, ['officeguy.locale']))
    ->group(function () {
        // Card payment callback (redirect return)
        Route::get(
            RouteConfig::getCardCallbackPath(),
            [CardCallbackController::class, 'handle']
        )->name('officeguy.callback.card');

        // Bit payment webhook (IPN)
        Route::post(
            RouteConfig::getBitWebhookPath(),
            [BitWebhookController::class, 'handle']
        )->name('officeguy.webhook.bit');

        // Document download
        Route::get(
            RouteConfig::getDocumentDownloadPath(),
            [DocumentDownloadController::class, 'download']
        )->name('officeguy.document.download');

        // Optional checkout charge endpoint (configurable via Admin Panel)
        if (RouteConfig::isCheckoutEndpointEnabled()) {
            Route::post(
                RouteConfig::getCheckoutChargePath(),
                [CheckoutController::class, 'charge']
            )->name('officeguy.checkout.charge');
        }

        // Public checkout page - routes are always registered but controller checks if enabled
        // Can be enabled via Admin Panel (Settings) or config/env

        // Generic checkout (uses payable_model_class from Admin Panel)
        Route::get(
            RouteConfig::getPublicCheckoutPath(),
            [PublicCheckoutController::class, 'show']
        )->name('officeguy.public.checkout');

        Route::post(
            RouteConfig::getPublicCheckoutPath(),
            [PublicCheckoutController::class, 'process']
        )->name('officeguy.public.checkout.process');

        // Specific model checkouts (bypasses Admin Panel setting)
        // Package checkout (hosting/domain/SSL)
        Route::get(
            'checkout/package/{id}',
            [PublicCheckoutController::class, 'showPackage']
        )->name('officeguy.public.checkout.package');

        Route::post(
            'checkout/package/{id}',
            [PublicCheckoutController::class, 'processPackage']
        )->name('officeguy.public.checkout.package.process');

        // eSIM checkout
        Route::get(
            'checkout/esim/{id}',
            [PublicCheckoutController::class, 'showEsim']
        )->name('officeguy.public.checkout.esim');

        Route::post(
            'checkout/esim/{id}',
            [PublicCheckoutController::class, 'processEsim']
        )->name('officeguy.public.checkout.esim.process');

        /*
        |--------------------------------------------------------------------------
        | Secure Success Page (v2.0.0+)
        |--------------------------------------------------------------------------
        |
        | Cryptographically secure success page with 7-layer validation.
        | Uses token-first routing (token in query string, not URL path).
        | Rate limited to prevent brute force attacks (10 requests/minute).
        |
        | Security Layers:
        | 1. Rate Limiting (middleware)
        | 2. Signed URL (Laravel HMAC)
        | 3. Token Existence (database lookup)
        | 4. Token Validity (not expired)
        | 5. Single Use (not consumed)
        | 6. Nonce Matching (replay protection)
        | 7. Identity Proof (guest-safe cryptographic ownership)
        |
        | URL Format: /officeguy/success?token=...&nonce=...&signature=...
        |
        */
        Route::get(
            'success',
            [SecureSuccessController::class, 'show']
        )
            ->middleware(['throttle:10,1'])
            ->name('officeguy.success');

        /*
        |--------------------------------------------------------------------------
        | Incoming Webhooks from SUMIT (Triggers)
        |--------------------------------------------------------------------------
        |
        | These routes receive webhooks/triggers sent FROM SUMIT when cards are
        | created, updated, deleted, or archived in the SUMIT system.
        |
        | To use these, configure a trigger in SUMIT with HTTP action pointing to:
        | - General: POST /{prefix}/webhook/sumit
        | - Specific: POST /{prefix}/webhook/sumit/{event_type}
        |
        | Supported event_types: card_created, card_updated, card_deleted, card_archived
        |
        | @see https://help.sumit.co.il/he/articles/11577644-שליחת-webhook-ממערכת-סאמיט
        |
        */
        
        $sumitWebhookPath = RouteConfig::getSumitWebhookPath();
        
        // General webhook endpoint (auto-detects event type from payload)
        Route::post(
            $sumitWebhookPath,
            [SumitWebhookController::class, 'handle']
        )->name('officeguy.webhook.sumit');

        // Specific event type endpoints
        Route::post(
            $sumitWebhookPath . '/card-created',
            [SumitWebhookController::class, 'cardCreated']
        )->name('officeguy.webhook.sumit.card_created');

        Route::post(
            $sumitWebhookPath . '/card-updated',
            [SumitWebhookController::class, 'cardUpdated']
        )->name('officeguy.webhook.sumit.card_updated');

        Route::post(
            $sumitWebhookPath . '/card-deleted',
            [SumitWebhookController::class, 'cardDeleted']
        )->name('officeguy.webhook.sumit.card_deleted');

        Route::post(
            $sumitWebhookPath . '/card-archived',
            [SumitWebhookController::class, 'cardArchived']
        )->name('officeguy.webhook.sumit.card_archived');

        // CRM webhook (entity/folder updates)
        Route::post(
            $sumitWebhookPath . '/crm',
            CrmWebhookController::class
        )->name('officeguy.webhook.crm');
    });
