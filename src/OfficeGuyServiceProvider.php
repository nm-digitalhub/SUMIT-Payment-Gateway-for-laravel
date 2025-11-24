<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway;

use Illuminate\Support\ServiceProvider;
use OfficeGuy\LaravelSumitGateway\Settings\SumitSettings;
use OfficeGuy\LaravelSumitGateway\Console\Commands\StockSyncCommand;
use OfficeGuy\LaravelSumitGateway\Services\Stock\StockService;

/**
 * OfficeGuy/SUMIT Gateway Service Provider
 *
 * Registers package services, configurations, routes, migrations, and views
 */
class OfficeGuyServiceProvider extends ServiceProvider
{
    /**
     * Register package services
     *
     * @return void
     */
    public function register(): void
    {
        // Merge package configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../config/officeguy.php',
            'officeguy'
        );

        // Register settings class with Spatie (adds to settings.settings array)
        $registered = config('settings.settings', []);
        if (!in_array(SumitSettings::class, $registered, true)) {
            $registered[] = SumitSettings::class;
            config(['settings.settings' => $registered]);
        }

        // Bind settings
        $this->app->singleton(SumitSettings::class);

        // Register services as singletons
        $this->app->singleton(
            \OfficeGuy\LaravelSumitGateway\Services\OfficeGuyApi::class
        );

        $this->app->singleton(
            \OfficeGuy\LaravelSumitGateway\Services\PaymentService::class
        );

        $this->app->singleton(
            \OfficeGuy\LaravelSumitGateway\Services\TokenService::class
        );

        $this->app->singleton(
            \OfficeGuy\LaravelSumitGateway\Services\BitPaymentService::class
        );

        $this->app->singleton(
            \OfficeGuy\LaravelSumitGateway\Services\DocumentService::class
        );

        $this->app->singleton(StockService::class);
    }

    /**
     * Bootstrap package services
     *
     * @return void
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../config/officeguy.php' => config_path('officeguy.php'),
        ], 'officeguy-config');

        // Publish Spatie settings migrations
        $this->publishes([
            __DIR__ . '/../database/settings' => database_path('settings'),
        ], 'officeguy-settings');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/officeguy.php');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'officeguy');

        // Publish views
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/officeguy'),
        ], 'officeguy-views');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'officeguy-migrations');

        // Register commands if running in console
        if ($this->app->runningInConsole()) {
            $this->commands([
                StockSyncCommand::class,
            ]);
        }

        // Sync settings into config so existing code paths keep working
        $this->applySettingsToConfig();
    }

    /**
     * Get the services provided by the provider
     *
     * @return array
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

    private function applySettingsToConfig(): void
    {
        if (!class_exists(SumitSettings::class)) {
            return;
        }

        /** @var SumitSettings $settings */
        $settings = app(SumitSettings::class);

        config([
            'officeguy.company_id' => $settings->company_id,
            'officeguy.private_key' => $settings->private_key,
            'officeguy.public_key' => $settings->public_key,
            'officeguy.environment' => $settings->environment,
            'officeguy.pci' => $settings->pci,
            'officeguy.pci_mode' => $settings->pci,
            'officeguy.testing' => $settings->testing,
            'officeguy.max_payments' => $settings->max_payments,
            'officeguy.min_amount_for_payments' => $settings->min_amount_for_payments,
            'officeguy.min_amount_per_payment' => $settings->min_amount_per_payment,
            'officeguy.authorize_only' => $settings->authorize_only,
            'officeguy.authorize_added_percent' => $settings->authorize_added_percent,
            'officeguy.authorize_minimum_addition' => $settings->authorize_minimum_addition,
            'officeguy.merchant_number' => $settings->merchant_number,
            'officeguy.subscriptions_merchant_number' => $settings->subscriptions_merchant_number,
            'officeguy.draft_document' => $settings->draft_document,
            'officeguy.email_document' => $settings->email_document,
            'officeguy.create_order_document' => $settings->create_order_document,
            'officeguy.automatic_languages' => $settings->automatic_languages,
            'officeguy.merge_customers' => $settings->merge_customers,
            'officeguy.support_tokens' => $settings->support_tokens,
            'officeguy.token_param' => $settings->token_param,
            'officeguy.citizen_id' => $settings->citizen_id,
            'officeguy.cvv' => $settings->cvv,
            'officeguy.four_digits_year' => $settings->four_digits_year,
            'officeguy.single_column_layout' => $settings->single_column_layout,
            'officeguy.bit_enabled' => $settings->bit_enabled,
            'officeguy.logging' => $settings->logging,
            'officeguy.log_channel' => $settings->log_channel,
            'officeguy.stock_sync_freq' => $settings->stock_sync_freq,
            'officeguy.checkout_stock_sync' => $settings->checkout_stock_sync,
            'officeguy.paypal_receipts' => $settings->paypal_receipts,
            'officeguy.bluesnap_receipts' => $settings->bluesnap_receipts,
            'officeguy.other_receipts' => $settings->other_receipts,
            'officeguy.supported_currencies' => $settings->supported_currencies,
            'officeguy.ssl_verify' => $settings->ssl_verify,
        ]);
    }
}
