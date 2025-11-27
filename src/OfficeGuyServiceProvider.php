<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use OfficeGuy\LaravelSumitGateway\Console\Commands\ProcessRecurringPaymentsCommand;
use OfficeGuy\LaravelSumitGateway\Console\Commands\StockSyncCommand;
use OfficeGuy\LaravelSumitGateway\Listeners\WebhookEventListener;
use OfficeGuy\LaravelSumitGateway\Services\CustomerMergeService;
use OfficeGuy\LaravelSumitGateway\Services\DonationService;
use OfficeGuy\LaravelSumitGateway\Services\MultiVendorPaymentService;
use OfficeGuy\LaravelSumitGateway\Services\Stock\StockService;
use OfficeGuy\LaravelSumitGateway\Services\SubscriptionService;
use OfficeGuy\LaravelSumitGateway\Services\UpsellService;
use OfficeGuy\LaravelSumitGateway\Services\WebhookService;

/**
 * OfficeGuy/SUMIT Gateway Service Provider
 *
 * Registers package services, configurations, routes, migrations, and views
 */
class OfficeGuyServiceProvider extends ServiceProvider
{
    /**
     * Register package services
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/officeguy.php', 'officeguy');
        $this->mergeConfigFrom(__DIR__ . '/../config/officeguy-webhooks.php', 'officeguy.webhooks');

        // Bind core services
        $this->app->singleton(\OfficeGuy\LaravelSumitGateway\Services\SettingsService::class);
        $this->app->singleton(\OfficeGuy\LaravelSumitGateway\Services\OfficeGuyApi::class);
        $this->app->singleton(\OfficeGuy\LaravelSumitGateway\Services\PaymentService::class);
        $this->app->singleton(\OfficeGuy\LaravelSumitGateway\Services\TokenService::class);
        $this->app->singleton(\OfficeGuy\LaravelSumitGateway\Services\BitPaymentService::class);
        $this->app->singleton(\OfficeGuy\LaravelSumitGateway\Services\DocumentService::class);
        $this->app->singleton(StockService::class);

        // Bind new feature services
        $this->app->singleton(SubscriptionService::class);
        $this->app->singleton(DonationService::class);
        $this->app->singleton(MultiVendorPaymentService::class);
        $this->app->singleton(UpsellService::class);
        $this->app->singleton(WebhookService::class);
        $this->app->singleton(CustomerMergeService::class);
    }

    /**
     * Bootstrap package services
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/officeguy.php' => config_path('officeguy.php'),
            __DIR__ . '/../config/officeguy-webhooks.php' => config_path('officeguy-webhooks.php'),
        ], 'officeguy-config');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../routes/officeguy.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'officeguy');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/officeguy'),
        ], 'officeguy-views');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'officeguy-migrations');

        // Load settings from database and override config
        $this->loadDatabaseSettings();

        if ($this->app->runningInConsole()) {
            $this->commands([
                StockSyncCommand::class,
                ProcessRecurringPaymentsCommand::class,
            ]);
        }

        // Register webhook event listener subscriber
        Event::subscribe(WebhookEventListener::class);

        // Register stock sync scheduler based on settings
        $this->registerStockSyncScheduler();
    }

    /**
     * Load settings from database and merge into config.
     *
     * This allows admin-panel changes to take effect immediately.
     * Loads both officeguy.* and officeguy.webhooks.* configurations.
     */
    protected function loadDatabaseSettings(): void
    {
        try {
            // Only load if table exists (prevents errors during migration)
            if (!\Illuminate\Support\Facades\Schema::hasTable('officeguy_settings')) {
                return;
            }

            $settingsService = $this->app->make(\OfficeGuy\LaravelSumitGateway\Services\SettingsService::class);
            $dbSettings = \OfficeGuy\LaravelSumitGateway\Models\OfficeGuySetting::getAllSettings();

            // Override config with database values
            foreach ($dbSettings as $key => $value) {
                config(["officeguy.{$key}" => $value]);
            }

            // Load webhook settings from database (v1.2.0+)
            // These settings are configurable via Admin Panel: /admin/office-guy-settings
            $webhookMappings = [
                'webhook_async' => 'async',
                'webhook_queue' => 'queue',
                'webhook_max_tries' => 'tries',
                'webhook_timeout' => 'timeout_in_seconds',
                'webhook_verify_ssl' => 'verify_ssl',
            ];

            foreach ($webhookMappings as $dbKey => $configKey) {
                if (isset($dbSettings[$dbKey])) {
                    $value = $dbSettings[$dbKey];

                    // Cast to appropriate types
                    if ($configKey === 'async' || $configKey === 'verify_ssl') {
                        $value = (bool) $value;
                    } elseif ($configKey === 'tries' || $configKey === 'timeout_in_seconds') {
                        $value = (int) $value;
                    }

                    config(["officeguy.webhooks.{$configKey}" => $value]);
                }
            }
        } catch (\Exception $e) {
            // Silently fail - config defaults will be used
            // This handles cases where DB isn't ready yet
        }
    }

    /**
     * Register stock sync scheduler based on settings.
     *
     * Schedules automatic stock synchronization based on the stock_sync_freq setting.
     * Options: 'none' (disabled), '12' (every 12 hours), '24' (daily)
     */
    protected function registerStockSyncScheduler(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->callAfterResolving('Illuminate\Console\Scheduling\Schedule', function ($schedule) {
            $freq = config('officeguy.stock_sync_freq', 'none');

            if ($freq === '12') {
                $schedule->command('sumit:stock-sync')->everyTwelveHours();
            } elseif ($freq === '24') {
                $schedule->command('sumit:stock-sync')->daily();
            }
            // 'none' = no scheduling
        });
    }

    public function provides(): array
    {
        return [
            \OfficeGuy\LaravelSumitGateway\Services\SettingsService::class,
            \OfficeGuy\LaravelSumitGateway\Services\OfficeGuyApi::class,
            \OfficeGuy\LaravelSumitGateway\Services\PaymentService::class,
            \OfficeGuy\LaravelSumitGateway\Services\TokenService::class,
            \OfficeGuy\LaravelSumitGateway\Services\BitPaymentService::class,
            \OfficeGuy\LaravelSumitGateway\Services\DocumentService::class,
            StockService::class,
            SubscriptionService::class,
            DonationService::class,
            MultiVendorPaymentService::class,
            UpsellService::class,
            CustomerMergeService::class,
        ];
    }
}
