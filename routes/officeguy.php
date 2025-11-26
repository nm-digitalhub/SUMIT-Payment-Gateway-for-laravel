<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use OfficeGuy\LaravelSumitGateway\Http\Controllers\BitWebhookController;
use OfficeGuy\LaravelSumitGateway\Http\Controllers\CardCallbackController;
use OfficeGuy\LaravelSumitGateway\Http\Controllers\CheckoutController;
use OfficeGuy\LaravelSumitGateway\Http\Controllers\DocumentDownloadController;
use OfficeGuy\LaravelSumitGateway\Http\Controllers\PublicCheckoutController;

/*
|--------------------------------------------------------------------------
| OfficeGuy/SUMIT Gateway Routes
|--------------------------------------------------------------------------
|
| These routes handle callbacks and webhooks from the SUMIT payment gateway
|
*/

$prefix = config('officeguy.routes.prefix', 'officeguy');
$middleware = config('officeguy.routes.middleware', ['web']);

Route::prefix($prefix)
    ->middleware($middleware)
    ->group(function () {
        // Card payment callback (redirect return)
        Route::get(
            config('officeguy.routes.card_callback', 'callback/card'),
            [CardCallbackController::class, 'handle']
        )->name('officeguy.callback.card');

        // Bit payment webhook (IPN)
        Route::post(
            config('officeguy.routes.bit_webhook', 'webhook/bit'),
            [BitWebhookController::class, 'handle']
        )->name('officeguy.webhook.bit');

        Route::get('documents/{document}', [DocumentDownloadController::class, 'download'])
            ->name('officeguy.document.download');

        // Optional checkout charge endpoint (disabled by default)
        if (config('officeguy.routes.enable_checkout_endpoint', false)) {
            Route::post(
                config('officeguy.routes.checkout_charge', 'checkout/charge'),
                [CheckoutController::class, 'charge']
            )->name('officeguy.checkout.charge');
        }

        // Public checkout page - routes are always registered but controller checks if enabled
        // Can be enabled via Admin Panel (Settings) or config/env
        Route::get(
            config('officeguy.routes.public_checkout', 'checkout/{id}'),
            [PublicCheckoutController::class, 'show']
        )->name('officeguy.public.checkout');

        Route::post(
            config('officeguy.routes.public_checkout', 'checkout/{id}'),
            [PublicCheckoutController::class, 'process']
        )->name('officeguy.public.checkout.process');
    });
