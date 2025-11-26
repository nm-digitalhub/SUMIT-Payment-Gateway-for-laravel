<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use OfficeGuy\LaravelSumitGateway\Console\Commands\ProcessRecurringPaymentsCommand;
use OfficeGuy\LaravelSumitGateway\Console\Commands\StockSyncCommand;
use OfficeGuy\LaravelSumitGateway\Listeners\WebhookEventListener;
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
    }

    /**
     * Bootstrap package services
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/officeguy.php' => config_path('officeguy.php'),
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

        if ($this->app->runningInConsole()) {
            $this->commands([
                StockSyncCommand::class,
                ProcessRecurringPaymentsCommand::class,
            ]);
        }

        // Register webhook event listener subscriber
        Event::subscribe(WebhookEventListener::class);
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
        ];
    }
}
