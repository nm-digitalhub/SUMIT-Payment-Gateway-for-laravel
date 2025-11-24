<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway;

use Illuminate\Support\ServiceProvider;
use OfficeGuy\LaravelSumitGateway\Console\Commands\StockSyncCommand;
use OfficeGuy\LaravelSumitGateway\Services\Stock\StockService;
use OfficeGuy\LaravelSumitGateway\Settings\SumitSettings;

class OfficeGuyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Load package config
        $this->mergeConfigFrom(__DIR__ . '/../config/officeguy.php', 'officeguy');

        // Bind SumitSettings as singleton (Spatie v3 loads it automatically from migrations)
        $this->app->singleton(SumitSettings::class);

        // Bind services
        $this->app->singleton(\OfficeGuy\LaravelSumitGateway\Services\OfficeGuyApi::class);
        $this->app->singleton(\OfficeGuy\LaravelSumitGateway\Services\PaymentService::class);
        $this->app->singleton(\OfficeGuy\LaravelSumitGateway\Services\TokenService::class);
        $this->app->singleton(\OfficeGuy\LaravelSumitGateway\Services\BitPaymentService::class);
        $this->app->singleton(\OfficeGuy\LaravelSumitGateway\Services\DocumentService::class);
        $this->app->singleton(StockService::class);
    }

    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/officeguy.php' => config_path('officeguy.php'),
        ], 'officeguy-config');

        // Publish settings migrations
        $this->publishes([
            __DIR__ . '/../database/settings' => database_path('settings'),
        ], 'officeguy-settings');

        // Publish database migrations
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'officeguy-migrations');

        // Load package migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/officeguy.php');

        // Views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'officeguy');
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/officeguy'),
        ], 'officeguy-views');

        // Console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                StockSyncCommand::class,
            ]);
        }

        $this->applySettingsToConfig();
    }

    private function applySettingsToConfig(): void
    {
        // Settings might not exist before migration — prevent crash
        if (!class_exists(SumitSettings::class)) {
            return;
        }

        try {
            $settings = app(SumitSettings::class);
        } catch (\Throwable $e) {
            return;
        }

        config([
            'officeguy.company_id' => $settings->company_id,
            'officeguy.private_key' => $settings->private_key,
            'officeguy.public_key' => $settings->public_key,
            'officeguy.environment' => $settings->environment,
            'officeguy.routes' => $settings->routes,
            'officeguy.order' => $settings->order,
            'officeguy.pci' => $settings->pci,
            'officeguy.pci_mode' => $settings->pci_mode,
            'officeguy.testing' => $settings->testing,
            'officeguy.max_payments' => $settings->max_payments,
            'officegoy.min_amount_for_payments' => $settings->min_amount_for_payments,
            'officegoy.min_amount_per_payment' => $settings->min_amount_per_payment,
            'officeguy.authorize_only' => $settings->authorize_only,
            'officegoy.authorize_added_percent' => $settings->authorize_added_percent,
            'officegoy.authorize_minimum_addition' => $settings->authorize_minimum_addition,
            'officegoy.merchant_number' => $settings->merchant_number,
            'officegoy.subscriptions_merchant_number' => $settings->subscriptions_merchant_number,
            'officegoy.draft_document' => $settings->draft_document,
            'officegoy.email_document' => $settings->email_document,
            'officegoy.create_order_document' => $settings->create_order_document,
            'officegoy.automatic_languages' => $settings->automatic_languages,
            'officegoy.merge_customers' => $settings->merge_customers,
            'officegoy.support_tokens' => $settings->support_tokens,
            'officegoy.token_param' => $settings->token_param,
            'officegoy.citizen_id' => $settings->citizen_id,
            'officegoy.cvv' => $settings->cvv,
            'officegoy.four_digits_year' => $settings->four_digits_year,
            'officegoy.single_column_layout' => $settings->single_column_layout,
            'officegoy.bit_enabled' => $settings->bit_enabled,
            'officegoy.logging' => $settings->logging,
            'officegoy.log_channel' => $settings->log_channel,
            'officegoy.ssl_verify' => $settings->ssl_verify,
            'officegoy.stock_sync_freq' => $settings->stock_sync_freq,
            'officegoy.checkout_stock_sync' => $settings->checkout_stock_sync,
            'officegoy.stock' => $settings->stock,
            'officegoy.paypal_receipts' => $settings->paypal_receipts,
            'officegoy.bluesnap_receipts' => $settings->bluesnap_receipts,
            'officegoy.other_receipts' => $settings->other_receipts,
            'officegoy.supported_currencies' => $settings->supported_currencies,
        ]);
    }
}