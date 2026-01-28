<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Tests\Unit;

use OfficeGuy\LaravelSumitGateway\OfficeGuyServiceProvider;
use OfficeGuy\LaravelSumitGateway\Services\CustomerMergeService;
use Orchestra\Testbench\TestCase;

/**
 * Test CustomerMergeService integration with customer model resolution.
 */
class CustomerMergeServiceTest extends TestCase
{
    /**
     * Get package providers.
     */
    protected function getPackageProviders($app): array
    {
        return [
            OfficeGuyServiceProvider::class,
        ];
    }

    /**
     * Test: getModelClass() returns new config value when set
     */
    public function test_get_model_class_returns_new_config(): void
    {
        // Arrange
        config([
            'officeguy.models.customer' => 'App\\Models\\Customer',
            'officeguy.customer_model_class' => 'App\\Models\\Client',
        ]);

        $service = app(CustomerMergeService::class);

        // Act
        $result = $service->getModelClass();

        // Assert
        $this->assertEquals('App\\Models\\Customer', $result);
    }

    /**
     * Test: getModelClass() returns legacy config value when new config is null
     */
    public function test_get_model_class_falls_back_to_legacy(): void
    {
        // Arrange
        config([
            'officeguy.models.customer' => null,
            'officeguy.customer_model_class' => 'App\\Models\\Client',
        ]);

        $service = app(CustomerMergeService::class);

        // Act
        $result = $service->getModelClass();

        // Assert
        $this->assertEquals('App\\Models\\Client', $result);
    }

    /**
     * Test: getModelClass() returns null when neither config is set
     */
    public function test_get_model_class_returns_null_when_not_configured(): void
    {
        // Arrange
        config([
            'officeguy.models.customer' => null,
            'officeguy.customer_model_class' => null,
        ]);

        $service = app(CustomerMergeService::class);

        // Act
        $result = $service->getModelClass();

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test: isEnabled() uses correct setting name
     */
    public function test_is_enabled_uses_correct_setting(): void
    {
        // This test verifies that CustomerMergeService::isEnabled()
        // uses 'customer_local_sync_enabled' and not 'customer_sync_enabled'

        // Mock the database settings table to avoid database dependency
        // We'll just verify the behavior by checking what happens when disabled

        // Arrange
        config(['officeguy.customer_local_sync_enabled' => false]);

        // Create the service - it will use SettingsService internally
        // which checks config when DB table doesn't exist
        $service = app(CustomerMergeService::class);

        // Act
        $result = $service->isEnabled();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test: syncFromSumit() returns null when sync is disabled
     */
    public function test_sync_from_sumit_returns_null_when_disabled(): void
    {
        // Arrange
        config(['officeguy.customer_local_sync_enabled' => false]);

        $service = app(CustomerMergeService::class);

        // Act
        $result = $service->syncFromSumit([
            'Email' => 'test@example.com',
            'ID' => 123,
        ]);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test: syncFromSumit() returns null when model class not configured
     */
    public function test_sync_from_sumit_returns_null_when_model_not_configured(): void
    {
        // Arrange
        config([
            'officeguy.customer_local_sync_enabled' => true,
            'officeguy.models.customer' => null,
            'officeguy.customer_model_class' => null,
        ]);

        $service = app(CustomerMergeService::class);

        // Act
        $result = $service->syncFromSumit([
            'Email' => 'test@example.com',
            'ID' => 123,
        ]);

        // Assert
        $this->assertNull($result);
    }
}
