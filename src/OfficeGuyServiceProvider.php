<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use OfficeGuy\LaravelSumitGateway\Console\Commands\CrmSyncFoldersCommand;
use OfficeGuy\LaravelSumitGateway\Console\Commands\CrmSyncViewsCommand;
use OfficeGuy\LaravelSumitGateway\Console\Commands\ProcessRecurringPaymentsCommand;
use OfficeGuy\LaravelSumitGateway\Console\Commands\StockSyncCommand;
use OfficeGuy\LaravelSumitGateway\Console\Commands\SyncAllDocumentsCommand;
use OfficeGuy\LaravelSumitGateway\Events\SumitWebhookReceived;
use OfficeGuy\LaravelSumitGateway\Listeners\CrmActivitySyncListener;
use OfficeGuy\LaravelSumitGateway\Listeners\CustomerSyncListener;
use OfficeGuy\LaravelSumitGateway\Listeners\DocumentSyncListener;
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

        // Override middleware BEFORE routes are loaded
        // Replace 'auth' with 'optional.auth' to allow both guests and authenticated users
        $this->overrideAuthMiddleware();

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
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'officeguy');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/officeguy'),
        ], 'officeguy-views');

        $this->publishes([
            __DIR__ . '/../resources/lang' => $this->app->langPath('vendor/officeguy'),
        ], 'officeguy-lang');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'officeguy-migrations');

        // Load settings from database and override config
        $this->loadDatabaseSettings();

        // Register optional auth middleware alias
        $router = $this->app['router'];
        $router->aliasMiddleware('optional.auth', \OfficeGuy\LaravelSumitGateway\Http\Middleware\OptionalAuth::class);

        // Register commands (available in both console and web contexts)
        $this->commands([
            StockSyncCommand::class,
            ProcessRecurringPaymentsCommand::class,
            SyncAllDocumentsCommand::class,
            CrmSyncFoldersCommand::class,
            CrmSyncViewsCommand::class,
        ]);

        // Register webhook event listener subscriber
        Event::subscribe(WebhookEventListener::class);

        // Register customer sync listener (v1.2.4+)
        // Automatically syncs SUMIT customers with local customer model when webhooks are received
        Event::listen(
            SumitWebhookReceived::class,
            CustomerSyncListener::class
        );

        // CRM activities sync listener: refresh related entities when SUMIT CRM webhook arrives
        Event::listen(
            SumitWebhookReceived::class,
            CrmActivitySyncListener::class
        );

        // Register document sync listener (v1.5.0+)
        // Automatically syncs documents and subscriptions when webhooks are received
        Event::subscribe(DocumentSyncListener::class);

        // Register stock sync scheduler based on settings
        $this->registerStockSyncScheduler();

        // Register auto document sync scheduler
        $this->registerDocumentSyncScheduler();

        // Register CRM folders sync scheduler
        $this->registerCrmFoldersSyncScheduler();

        // Register debt collection scheduler
        $this->registerDebtCollectionScheduler();

        // Register Livewire components for Filament widgets
        $this->registerLivewireComponents();
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
     * Register Livewire components for Filament widgets.
     *
     * Registers package widgets with Livewire so they can be used in Filament panels.
     */
    protected function registerLivewireComponents(): void
    {
        if (!class_exists(\Livewire\Livewire::class)) {
            return;
        }

        // Register Filament widgets with explicit Livewire component names
        \Livewire\Livewire::component(
            'office-guy.laravel-sumit-gateway.filament.widgets.payable-mappings-table-widget',
            \OfficeGuy\LaravelSumitGateway\Filament\Widgets\PayableMappingsTableWidget::class
        );
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

    /**
     * Register automatic document sync scheduler.
     *
     * Schedules automatic document synchronization from SUMIT.
     * Runs daily at 3:00 AM to sync all documents and subscriptions.
     * Uses queue for background processing to avoid blocking.
     */
    protected function registerDocumentSyncScheduler(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->callAfterResolving('Illuminate\Console\Scheduling\Schedule', function ($schedule) {
            // Daily sync at 3:00 AM (low traffic time)
            $schedule->command('sumit:sync-all-documents --days=30')
                ->dailyAt('03:00')
                ->name('sumit-documents-sync')
                ->withoutOverlapping(120) // Prevent overlapping runs, timeout after 2 hours
                ->runInBackground()
                ->onFailure(function () {
                    \Log::error('SUMIT documents auto-sync failed');
                })
                ->onSuccess(function () {
                    \Log::info('SUMIT documents auto-sync completed successfully');
                });
        });
    }

    /**
     * Register automatic CRM folders sync scheduler.
     *
     * Schedules automatic CRM folders synchronization from SUMIT API.
     * Runs daily at 2:00 AM to sync all CRM folder schemas and fields.
     * CRM folders define the structure for contacts, leads, companies, deals, etc.
     */
    protected function registerCrmFoldersSyncScheduler(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->callAfterResolving('Illuminate\Console\Scheduling\Schedule', function ($schedule) {
            // Daily sync at 2:00 AM (before documents sync at 3:00 AM)
            $schedule->command('crm:sync-folders')
                ->dailyAt('02:00')
                ->name('crm-folders-sync')
                ->withoutOverlapping(60) // Prevent overlapping runs, timeout after 1 hour
                ->runInBackground()
                ->onFailure(function () {
                    \Log::error('CRM folders auto-sync failed');
                })
                ->onSuccess(function () {
                    \Log::info('CRM folders auto-sync completed successfully');
                });
        });
    }

    /**
     * Register daily debt collection check.
     */
    protected function registerDebtCollectionScheduler(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->callAfterResolving('Illuminate\Console\Scheduling\Schedule', function ($schedule) {
            $schedule->job(\OfficeGuy\LaravelSumitGateway\Jobs\CheckSumitDebtJob::class)
                ->dailyAt(config('officeguy.collection.schedule_time', '02:00'))
                ->name('sumit-debt-check')
                ->withoutOverlapping(60)
                ->onFailure(function () {
                    \Log::error('SUMIT debt auto-check failed');
                })
                ->onSuccess(function () {
                    \Log::info('SUMIT debt auto-check completed successfully');
                });
        });
    }

    /**
     * Override 'auth' middleware with 'optional.auth' in package routes configuration.
     *
     * This allows both authenticated and guest users to access checkout pages,
     * while maintaining auto-fill functionality for logged-in users.
     *
     * Called in register() BEFORE routes are loaded to ensure the override takes effect.
     */
    protected function overrideAuthMiddleware(): void
    {
        // Get current middleware configuration
        $currentMiddleware = config('officeguy.routes.middleware', ['web', 'auth']);

        // Replace 'auth' with 'optional.auth' in the middleware array
        $newMiddleware = array_map(function ($middleware) {
            return $middleware === 'auth' ? 'optional.auth' : $middleware;
        }, $currentMiddleware);

        // Set the new middleware configuration
        config(['officeguy.routes.middleware' => $newMiddleware]);
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
