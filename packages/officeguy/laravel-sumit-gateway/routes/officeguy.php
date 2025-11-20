<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use OfficeGuy\LaravelSumitGateway\Http\Controllers\BitWebhookController;
use OfficeGuy\LaravelSumitGateway\Http\Controllers\CardCallbackController;

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
    });
