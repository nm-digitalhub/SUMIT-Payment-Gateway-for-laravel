<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use OfficeGuy\LaravelSumitGateway\Tests\TestCase;
use OfficeGuy\LaravelSumitGateway\Services\CustomerMergeService;
use OfficeGuy\LaravelSumitGateway\Services\SettingsService;

/**
 * Integration tests for CustomerMergeService with backward compatibility.
 */
class CustomerMergeServiceTest extends TestCase
{
    protected CustomerMergeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CustomerMergeService::class);
    }

    /**
     * Test getModelClass uses new config structure.
     */
    public function test_get_model_class_uses_new_config(): void
    {
        config([
            'officeguy.models.customer' => 'App\\Models\\Customer',
            'officeguy.customer_model_class' => null,
        ]);

        $modelClass = $this->service->getModelClass();

        $this->assertEquals('App\\Models\\Customer', $modelClass);
    }

    /**
     * Test getModelClass falls back to legacy config.
     */
    public function test_get_model_class_uses_legacy_config(): void
    {
        config([
            'officeguy.models.customer' => null,
            'officeguy.customer_model_class' => 'App\\Models\\Client',
        ]);

        $modelClass = $this->service->getModelClass();

        $this->assertEquals('App\\Models\\Client', $modelClass);
    }

    /**
     * Test getModelClass returns null when neither configured.
     */
    public function test_get_model_class_returns_null_when_not_configured(): void
    {
        config([
            'officeguy.models.customer' => null,
            'officeguy.customer_model_class' => null,
        ]);

        $modelClass = $this->service->getModelClass();

        $this->assertNull($modelClass);
    }

    /**
     * Test getModelClass with priority (new over legacy).
     */
    public function test_get_model_class_priority_new_over_legacy(): void
    {
        config([
            'officeguy.models.customer' => 'App\\Models\\Customer',
            'officeguy.customer_model_class' => 'App\\Models\\Client',
        ]);

        $modelClass = $this->service->getModelClass();

        // New config should take priority
        $this->assertEquals('App\\Models\\Customer', $modelClass);
        $this->assertNotEquals('App\\Models\\Client', $modelClass);
    }

    /**
     * Test syncFromSumit returns null when not enabled.
     */
    public function test_sync_from_sumit_returns_null_when_disabled(): void
    {
        // Ensure customer sync is disabled
        config(['officeguy.customer_sync_enabled' => false]);

        $sumitCustomer = [
            'Email' => 'test@example.com',
            'Name' => 'Test Customer',
        ];

        $result = $this->service->syncFromSumit($sumitCustomer);

        $this->assertNull($result);
    }

    /**
     * Test syncFromSumit returns null when model class not configured.
     */
    public function test_sync_from_sumit_returns_null_when_model_not_configured(): void
    {
        // Enable sync but don't configure model
        config([
            'officeguy.customer_sync_enabled' => true,
            'officeguy.models.customer' => null,
            'officeguy.customer_model_class' => null,
        ]);

        $sumitCustomer = [
            'Email' => 'test@example.com',
            'Name' => 'Test Customer',
        ];

        $result = $this->service->syncFromSumit($sumitCustomer);

        $this->assertNull($result);
    }

    /**
     * Test syncFromSumit returns null when model class doesn't exist.
     */
    public function test_sync_from_sumit_returns_null_when_model_class_not_exists(): void
    {
        // Enable sync with non-existent model
        config([
            'officeguy.customer_sync_enabled' => true,
            'officeguy.models.customer' => 'App\\Models\\NonExistentModel',
        ]);

        $sumitCustomer = [
            'Email' => 'test@example.com',
            'Name' => 'Test Customer',
        ];

        $result = $this->service->syncFromSumit($sumitCustomer);

        $this->assertNull($result);
    }

    /**
     * Test findBySumitId returns null when not enabled.
     */
    public function test_find_by_sumit_id_returns_null_when_disabled(): void
    {
        config(['officeguy.customer_sync_enabled' => false]);

        $result = $this->service->findBySumitId(123);

        $this->assertNull($result);
    }

    /**
     * Test findBySumitId returns null when model not configured.
     */
    public function test_find_by_sumit_id_returns_null_when_model_not_configured(): void
    {
        config([
            'officeguy.customer_sync_enabled' => true,
            'officeguy.models.customer' => null,
            'officeguy.customer_model_class' => null,
        ]);

        $result = $this->service->findBySumitId(123);

        $this->assertNull($result);
    }

    /**
     * Test findByEmail returns null when not enabled.
     */
    public function test_find_by_email_returns_null_when_disabled(): void
    {
        config(['officeguy.customer_sync_enabled' => false]);

        $result = $this->service->findByEmail('test@example.com');

        $this->assertNull($result);
    }

    /**
     * Test findByEmail returns null when model not configured.
     */
    public function test_find_by_email_returns_null_when_model_not_configured(): void
    {
        config([
            'officeguy.customer_sync_enabled' => true,
            'officeguy.models.customer' => null,
            'officeguy.customer_model_class' => null,
        ]);

        $result = $this->service->findByEmail('test@example.com');

        $this->assertNull($result);
    }

    /**
     * Test isEnabled respects settings.
     */
    public function test_is_enabled_respects_settings(): void
    {
        // Test disabled
        config(['officeguy.customer_sync_enabled' => false]);
        $this->assertFalse($this->service->isEnabled());

        // Test enabled
        config(['officeguy.customer_sync_enabled' => true]);
        $this->assertTrue($this->service->isEnabled());
    }

    /**
     * Test getFieldMapping returns configured mappings.
     */
    public function test_get_field_mapping_returns_configured_mappings(): void
    {
        // Set field mappings
        config([
            'officeguy.customer_field_email' => 'email',
            'officeguy.customer_field_name' => 'name',
            'officeguy.customer_field_phone' => 'phone',
            'officeguy.customer_field_sumit_id' => 'sumit_customer_id',
        ]);

        $mapping = $this->service->getFieldMapping();

        $this->assertIsArray($mapping);
        $this->assertEquals('email', $mapping['email']);
        $this->assertEquals('name', $mapping['name']);
        $this->assertEquals('phone', $mapping['phone']);
        $this->assertEquals('sumit_customer_id', $mapping['sumit_id']);
    }

    /**
     * Test service uses container binding for model resolution.
     */
    public function test_service_uses_container_binding(): void
    {
        // Set new config
        config([
            'officeguy.models.customer' => 'App\\Models\\Customer',
        ]);

        // Get model class from service
        $modelClass1 = $this->service->getModelClass();

        // Get model class from container directly
        $modelClass2 = app('officeguy.customer_model');

        // Should be the same
        $this->assertEquals($modelClass1, $modelClass2);
    }

    /**
     * Test backward compatibility: existing installations work unchanged.
     */
    public function test_backward_compatibility_legacy_config_works(): void
    {
        // Simulate existing installation with only legacy config
        config([
            'officeguy.models.customer' => null, // Not set in existing installations
            'officeguy.customer_model_class' => 'App\\Models\\Client',
        ]);

        $modelClass = $this->service->getModelClass();

        // Should work exactly as before
        $this->assertEquals('App\\Models\\Client', $modelClass);
    }

    /**
     * Test new installations can use new config structure.
     */
    public function test_new_installations_use_new_config(): void
    {
        // Simulate new installation with only new config
        config([
            'officeguy.models.customer' => 'App\\Models\\Customer',
            'officeguy.customer_model_class' => 'App\\Models\\Client', // Default from config file
        ]);

        $modelClass = $this->service->getModelClass();

        // Should use new structure
        $this->assertEquals('App\\Models\\Customer', $modelClass);
    }
}
