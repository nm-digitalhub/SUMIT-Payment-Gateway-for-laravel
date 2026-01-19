<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Tests\Unit;

use OfficeGuy\LaravelSumitGateway\Tests\TestCase;
use OfficeGuy\LaravelSumitGateway\OfficeGuyServiceProvider;

/**
 * Test backward-compatible customer model resolution.
 *
 * Tests the priority sequence:
 * 1. config('officeguy.models.customer') - New structure
 * 2. config('officeguy.customer_model_class') - Legacy structure
 * 3. null - Neither configured
 */
class CustomerModelResolutionTest extends TestCase
{
    /**
     * Test that new config structure takes priority.
     */
    public function test_new_config_structure_takes_priority(): void
    {
        // Set both new and old config
        config([
            'officeguy.models.customer' => 'App\\Models\\Customer',
            'officeguy.customer_model_class' => 'App\\Models\\Client',
        ]);

        // Resolve from container
        $customerModel = app('officeguy.customer_model');

        // Should use new structure
        $this->assertEquals('App\\Models\\Customer', $customerModel);
    }

    /**
     * Test fallback to legacy config structure.
     */
    public function test_fallback_to_legacy_config_structure(): void
    {
        // Set only old config
        config([
            'officeguy.models.customer' => null,
            'officeguy.customer_model_class' => 'App\\Models\\Client',
        ]);

        // Resolve from container
        $customerModel = app('officeguy.customer_model');

        // Should use legacy structure
        $this->assertEquals('App\\Models\\Client', $customerModel);
    }

    /**
     * Test returns null when neither is configured.
     */
    public function test_returns_null_when_neither_configured(): void
    {
        // Set both to null
        config([
            'officeguy.models.customer' => null,
            'officeguy.customer_model_class' => null,
        ]);

        // Resolve from container
        $customerModel = app('officeguy.customer_model');

        // Should return null
        $this->assertNull($customerModel);
    }

    /**
     * Test new config with empty string falls back to legacy.
     */
    public function test_empty_string_in_new_config_falls_back_to_legacy(): void
    {
        // Set new to empty string, old to valid value
        config([
            'officeguy.models.customer' => '',
            'officeguy.customer_model_class' => 'App\\Models\\Client',
        ]);

        // Resolve from container
        $customerModel = app('officeguy.customer_model');

        // Should use legacy structure
        $this->assertEquals('App\\Models\\Client', $customerModel);
    }

    /**
     * Test legacy config with empty string returns null.
     */
    public function test_empty_string_in_legacy_config_returns_null(): void
    {
        // Set both to empty string
        config([
            'officeguy.models.customer' => '',
            'officeguy.customer_model_class' => '',
        ]);

        // Resolve from container
        $customerModel = app('officeguy.customer_model');

        // Should return null
        $this->assertNull($customerModel);
    }

    /**
     * Test only new config configured.
     */
    public function test_only_new_config_configured(): void
    {
        // Set only new config
        config([
            'officeguy.models.customer' => 'App\\Models\\Customer',
            'officeguy.customer_model_class' => null,
        ]);

        // Resolve from container
        $customerModel = app('officeguy.customer_model');

        // Should use new structure
        $this->assertEquals('App\\Models\\Customer', $customerModel);
    }

    /**
     * Test non-string values are rejected (new config).
     */
    public function test_non_string_values_rejected_in_new_config(): void
    {
        // Set new config to array, old to valid string
        config([
            'officeguy.models.customer' => ['not', 'a', 'string'],
            'officeguy.customer_model_class' => 'App\\Models\\Client',
        ]);

        // Resolve from container
        $customerModel = app('officeguy.customer_model');

        // Should fall back to legacy
        $this->assertEquals('App\\Models\\Client', $customerModel);
    }

    /**
     * Test non-string values are rejected (legacy config).
     */
    public function test_non_string_values_rejected_in_legacy_config(): void
    {
        // Set both to non-string values
        config([
            'officeguy.models.customer' => null,
            'officeguy.customer_model_class' => 12345,
        ]);

        // Resolve from container
        $customerModel = app('officeguy.customer_model');

        // Should return null
        $this->assertNull($customerModel);
    }

    /**
     * Test same value in both configs.
     */
    public function test_same_value_in_both_configs(): void
    {
        // Set both to same value
        $modelClass = 'App\\Models\\Customer';
        config([
            'officeguy.models.customer' => $modelClass,
            'officeguy.customer_model_class' => $modelClass,
        ]);

        // Resolve from container
        $customerModel = app('officeguy.customer_model');

        // Should work correctly
        $this->assertEquals($modelClass, $customerModel);
    }

    /**
     * Test different values in both configs (new takes priority).
     */
    public function test_different_values_new_takes_priority(): void
    {
        // Set different values
        config([
            'officeguy.models.customer' => 'App\\Models\\Customer',
            'officeguy.customer_model_class' => 'App\\Models\\Client',
        ]);

        // Resolve from container
        $customerModel = app('officeguy.customer_model');

        // New structure should win
        $this->assertEquals('App\\Models\\Customer', $customerModel);
        $this->assertNotEquals('App\\Models\\Client', $customerModel);
    }

    /**
     * Test container binding is singleton.
     */
    public function test_container_binding_resolves_consistently(): void
    {
        // Set config
        config([
            'officeguy.models.customer' => 'App\\Models\\Customer',
        ]);

        // Resolve multiple times
        $model1 = app('officeguy.customer_model');
        $model2 = app('officeguy.customer_model');
        $model3 = app('officeguy.customer_model');

        // Should return same value each time
        $this->assertEquals($model1, $model2);
        $this->assertEquals($model2, $model3);
    }

    /**
     * Test namespaced class names work correctly.
     */
    public function test_namespaced_class_names(): void
    {
        $className = 'My\\Custom\\Namespace\\Models\\Customer';
        config([
            'officeguy.models.customer' => $className,
        ]);

        $customerModel = app('officeguy.customer_model');

        $this->assertEquals($className, $customerModel);
    }

    /**
     * Test class names with double backslashes (from env files).
     */
    public function test_class_names_with_double_backslashes(): void
    {
        // Simulates how class names come from .env files
        $className = 'App\\\\Models\\\\Client';
        config([
            'officeguy.customer_model_class' => $className,
        ]);

        $customerModel = app('officeguy.customer_model');

        $this->assertEquals($className, $customerModel);
    }
}
