<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Tests\Unit;

use OfficeGuy\LaravelSumitGateway\OfficeGuyServiceProvider;
use Orchestra\Testbench\TestCase;

/**
 * Test customer model resolution with backward compatibility.
 *
 * Tests the priority-based fallback logic for resolving the customer model class:
 * 1. config('officeguy.models.customer') - New structure
 * 2. config('officeguy.customer_model_class') - Legacy structure
 * 3. null - Neither configured
 */
class CustomerModelResolutionTest extends TestCase
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
     * Test: New config only - should return new config value
     */
    public function test_new_config_only(): void
    {
        // Arrange
        config([
            'officeguy.models.customer' => 'App\\Models\\Customer',
            'officeguy.customer_model_class' => null,
        ]);

        // Act
        $result = app('officeguy.customer_model');

        // Assert
        $this->assertEquals('App\\Models\\Customer', $result);
    }

    /**
     * Test: Legacy config only - should return legacy config value
     */
    public function test_legacy_config_only(): void
    {
        // Arrange
        config([
            'officeguy.models.customer' => null,
            'officeguy.customer_model_class' => 'App\\Models\\Client',
        ]);

        // Act
        $result = app('officeguy.customer_model');

        // Assert
        $this->assertEquals('App\\Models\\Client', $result);
    }

    /**
     * Test: Both configs set - new config should take priority
     */
    public function test_both_configs_set_new_takes_priority(): void
    {
        // Arrange
        config([
            'officeguy.models.customer' => 'App\\Models\\Customer',
            'officeguy.customer_model_class' => 'App\\Models\\Client',
        ]);

        // Act
        $result = app('officeguy.customer_model');

        // Assert
        $this->assertEquals('App\\Models\\Customer', $result);
    }

    /**
     * Test: Neither config set - should return null
     */
    public function test_neither_config_set(): void
    {
        // Arrange
        config([
            'officeguy.models.customer' => null,
            'officeguy.customer_model_class' => null,
        ]);

        // Act
        $result = app('officeguy.customer_model');

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test: New config is empty string - should fall back to legacy
     */
    public function test_new_config_empty_string_falls_back(): void
    {
        // Arrange
        config([
            'officeguy.models.customer' => '',
            'officeguy.customer_model_class' => 'App\\Models\\Client',
        ]);

        // Act
        $result = app('officeguy.customer_model');

        // Assert
        $this->assertEquals('App\\Models\\Client', $result);
    }

    /**
     * Test: Legacy config is empty string - should return null
     */
    public function test_legacy_config_empty_string_returns_null(): void
    {
        // Arrange
        config([
            'officeguy.models.customer' => null,
            'officeguy.customer_model_class' => '',
        ]);

        // Act
        $result = app('officeguy.customer_model');

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test: New config is non-string (array) - should fall back to legacy
     */
    public function test_new_config_non_string_falls_back(): void
    {
        // Arrange
        config([
            'officeguy.models.customer' => ['invalid' => 'value'],
            'officeguy.customer_model_class' => 'App\\Models\\Client',
        ]);

        // Act
        $result = app('officeguy.customer_model');

        // Assert
        $this->assertEquals('App\\Models\\Client', $result);
    }

    /**
     * Test: Both configs are empty strings - should return null
     */
    public function test_both_configs_empty_returns_null(): void
    {
        // Arrange
        config([
            'officeguy.models.customer' => '',
            'officeguy.customer_model_class' => '',
        ]);

        // Act
        $result = app('officeguy.customer_model');

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test: Container binding is singleton - should resolve to same instance
     */
    public function test_container_binding_is_singleton(): void
    {
        // Arrange
        config([
            'officeguy.models.customer' => 'App\\Models\\Customer',
        ]);

        // Act
        $result1 = app('officeguy.customer_model');
        $result2 = app('officeguy.customer_model');

        // Assert
        $this->assertSame($result1, $result2);
    }

    /**
     * Test: Config change after first resolution - should not affect singleton
     *
     * This test verifies that once the customer model is resolved from the container,
     * subsequent config changes do not affect the resolved value (singleton behavior).
     */
    public function test_singleton_caches_first_resolution(): void
    {
        // Arrange
        config(['officeguy.models.customer' => 'App\\Models\\Customer']);

        // Act - First resolution
        $result1 = app('officeguy.customer_model');

        // Change config after first resolution
        config(['officeguy.models.customer' => 'App\\Models\\DifferentCustomer']);

        // Act - Second resolution
        $result2 = app('officeguy.customer_model');

        // Assert - Should still be the first value due to singleton
        $this->assertEquals('App\\Models\\Customer', $result1);
        $this->assertEquals('App\\Models\\Customer', $result2);
        $this->assertSame($result1, $result2);
    }
}
