<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway;

use Illuminate\Support\ServiceProvider;
use OfficeGuy\LaravelSumitGateway\Console\Commands\StockSyncCommand;
use OfficeGuy\LaravelSumitGateway\Services\Stock\StockService;
use OfficeGuy\LaravelSumitGateway\Settings\SumitSettings;
use Spatie\LaravelSettings\SettingsContainer;

/**
 * OfficeGuy/SUMIT Gateway Service Provider
 */
class OfficeGuyServiceProvider extends ServiceProvider
{
    /**
     * Register package services
     */
    public function register(): void
    {
        // Merge package config
        $this->mergeConfigFrom(__DIR__ . '/../config/officeguy.php', 'officeguy');

    

        // Register SumitSettings inside SettingsContainer safely
        $this->app->afterResolving(
            SettingsContainer::class,
            function (SettingsContainer $container) {
                $container->register([
                    SumitSettings::class,
                ]);
            }
        );

        // Singleton instance – OK
        $this->app->singleton(SumitSettings::class);

        // Bind services
        $this->app->singleton(\OfficeGuy\LaravelSumitGateway\Services\OfficeGuyApi::class);
        $this->app->singleton(\OfficeGuy\LaravelSumitGateway\Services\PaymentService::class);
        $this->app->singleton(\OfficeGuy\LaravelSumitGateway\Services\TokenService::class);
        $this->app->singleton(\OfficeGuy\LaravelSumitGateway\Services\BitPaymentService::class);
        $this->app->singleton(\OfficeGuy\LaravelSumitGateway\Services\DocumentService::class);
        $this->app->singleton(StockService::class);
    }

    /**
     * Bootstrap package services
     */
    public function boot(): void
    {
        // Publish configs
        $this->publishes([
            __DIR__ . '/../config/officeguy.php' => config_path('officeguy.php'),
        ], 'officeguy-config');

        // Publish settings migration
        $this->publishes([
            __DIR__ . '/../database/settings' => database_path('settings'),
        ], 'officeguy-settings');

        // Publish regular migrations
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'officeguy-migrations');

        // Views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'officeguy');
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/officeguy'),
        ], 'officeguy-views');

        // Routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/officeguy.php');

        // Load package migrations automatically
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                StockSyncCommand::class,
            ]);
        }

        // Apply settings to config (safe)
        $this->applySettingsToConfig();
    }

    /**
     * Services provided
     */
    public function provides(): array
    {
        return [
            \OfficeGuy\LaravelSumitGateway\Services\OfficeGuyApi::class,
            \OfficeGuy\LaravelSumitGateway\Services\PaymentService::class,
            \OfficeGuy\LaravelSumitGateway\Services\TokenService::class,
            \OfficeGuy\LaravelSumitGateway\Services\BitPaymentService::class,
            \OfficeGuy\LaravelSumitGateway\Services\DocumentService::class,
            SumitSettings::class,
            StockService::class,
        ];
    }

    /**
     * Inject settings into officeguy config safely
     */
    private function applySettingsToConfig(): void
    {
        // If class missing → skip safely
        if (!class_exists(SumitSettings::class)) {
            return;
        }

        // If settings DB not migrated yet → do NOT crash
        try {
            /** @var SumitSettings $settings */
            $settings = app(SumitSettings::class);
        } catch (\Throwable $e) {
            return;
        }

        // Apply dynamically loaded settings
        config([
            'officeguy.company_id' => $settings->company_id,
            'officeguy.private_key' => $settings->private_key,
            'officeguy.public_key' => $settings->public_key,
            'officeguy.environment' => $settings->environment,

            'officegoy.routes' => $settings->routes,
            'officegoy.order' => $settings->order,

            'officeguy.pci' => $settings->pci,
            'officeguy.pci_mode' => $settings->pci_mode,
            'officeguy.testing' => $settings->testing,

            'officeguy.max_payments' => $settings->max_payments,
            'officeguy.min_amount_for_payments' => $settings->min_amount_for_payments,
            'officeguy.min_amount_per_payment' => $settings->min_amount_per_payment,

            'officeguy.authorize_only' => $settings->authorize_only,
            'officeguy.authorize_added_percent' => $settings->authorize_added_percent,
            'officeguy.authorize_minimum_addition' => $settings->authorize_minimum_addition,

            'officeguy.merchant_number' => $settings->merchant_number,
            'officeguy.subscriptions_merchant_number' => $settings->subscriptions_merchant_number,

            'officegoy.draft_document' => $settings->draft_document,
            'officegoy.email_document' => $settings->email_document,
            'officegoy.create_order_document' => $settings->create_order_document,
            'officegoy.automatic_languages' => $settings->automatic_languages,
            'officegoy.merge_customers' => $settings->merge_customers,

            'officegoy.support_tokens' => $settings->support_tokens,
            'officeguy.token_param' => $settings->token_param,
            'officeguy.citizen_id' => $settings->citizen_id,
            'officegoy.cvv' => $settings->cvv,
            'officegoy.four_digits_year' => $settings->four_digits_year,
            'officegoy.single_column_layout' => $settings->single_column_layout,

            'officegoy.bit_enabled' => $settings->bit_enabled,

            'officeguy.logging' => $settings->logging,
            'officegoy.log_channel' => $settings->log_channel,
            'officeguy.ssl_verify' => $settings->ssl_verify,

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