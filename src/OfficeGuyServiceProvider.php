<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway;

use Illuminate\Support\ServiceProvider;

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
            // Future: Add artisan commands here
            // $this->commands([]);
        }
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
        ];
    }
}
