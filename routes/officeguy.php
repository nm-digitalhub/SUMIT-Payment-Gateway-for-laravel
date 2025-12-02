<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use OfficeGuy\LaravelSumitGateway\Http\Controllers\BitWebhookController;
use OfficeGuy\LaravelSumitGateway\Http\Controllers\CardCallbackController;
use OfficeGuy\LaravelSumitGateway\Http\Controllers\CrmWebhookController;
use OfficeGuy\LaravelSumitGateway\Http\Controllers\CheckoutController;
use OfficeGuy\LaravelSumitGateway\Http\Controllers\DocumentDownloadController;
use OfficeGuy\LaravelSumitGateway\Http\Controllers\PublicCheckoutController;
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

Route::prefix($prefix)
    ->middleware($middleware)
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
        Route::get(
            RouteConfig::getPublicCheckoutPath(),
            [PublicCheckoutController::class, 'show']
        )->name('officeguy.public.checkout');

        Route::post(
            RouteConfig::getPublicCheckoutPath(),
            [PublicCheckoutController::class, 'process']
        )->name('officeguy.public.checkout.process');

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
