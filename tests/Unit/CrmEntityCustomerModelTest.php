<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OfficeGuy\LaravelSumitGateway\Models\CrmEntity;
use OfficeGuy\LaravelSumitGateway\OfficeGuyServiceProvider;
use Orchestra\Testbench\TestCase;

/**
 * Mock models for testing - must be defined before aliases
 */
class MockClientForEntity extends Model
{
    protected $table = 'clients';
}

class MockCustomerForEntity extends Model
{
    protected $table = 'customers';
}

class MockLegacyClientForEntity extends Model
{
    protected $table = 'legacy_clients';
}

class MockNewCustomerForEntity extends Model
{
    protected $table = 'new_customers';
}

// Create class aliases for App\Models namespace
if (! class_exists('App\Models\Client')) {
    class_alias(MockClientForEntity::class, 'App\Models\Client');
}
if (! class_exists('App\Models\CustomCustomer')) {
    class_alias(MockCustomerForEntity::class, 'App\Models\CustomCustomer');
}
if (! class_exists('App\Models\LegacyClient')) {
    class_alias(MockLegacyClientForEntity::class, 'App\Models\LegacyClient');
}
if (! class_exists('App\Models\NewCustomer')) {
    class_alias(MockNewCustomerForEntity::class, 'App\Models\NewCustomer');
}

/**
 * Test CrmEntity customer model resolution.
 *
 * Tests the refactored customer/client relationships that use dynamic model resolution
 * via app('officeguy.customer_model') instead of hard-coded App\Models\Client.
 *
 * Tests cover:
 * 1. customer() relationship with configured model
 * 2. customer() relationship with fallback to App\Models\Client
 * 3. client() relationship (deprecated) delegates to customer()
 * 4. Backward compatibility of client() method
 * 5. Both relationships return BelongsTo instances
 */
class CrmEntityCustomerModelTest extends TestCase
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
     * Test: customer() relationship uses configured customer model
     */
    public function test_customer_relationship_uses_configured_model(): void
    {
        // Arrange: Set up a custom customer model
        config(['officeguy.models.customer' => 'App\\Models\\CustomCustomer']);

        // Create a mock entity (not persisted)
        $entity = new CrmEntity;

        // Act: Get the customer relationship
        $relationship = $entity->customer();

        // Assert: Relationship is BelongsTo
        $this->assertInstanceOf(BelongsTo::class, $relationship);

        // Assert: Relationship resolves to a model (functional test)
        $relatedModel = $relationship->getRelated();
        $this->assertInstanceOf(Model::class, $relatedModel);

        // Assert: The relationship table matches what we expect
        $this->assertEquals('customers', $relatedModel->getTable());
    }

    /**
     * Test: customer() relationship falls back to App\Models\Client when not configured
     */
    public function test_customer_relationship_fallback_to_default(): void
    {
        // Arrange: Ensure no customer model is configured
        config(['officeguy.models.customer' => null]);
        config(['officeguy.customer_model_class' => null]);

        // Create a mock entity
        $entity = new CrmEntity;

        // Act: Get the customer relationship
        $relationship = $entity->customer();

        // Assert: Relationship is BelongsTo
        $this->assertInstanceOf(BelongsTo::class, $relationship);

        // Assert: Relationship falls back to a model
        $relatedModel = $relationship->getRelated();
        $this->assertInstanceOf(Model::class, $relatedModel);

        // Assert: The relationship table matches Client model
        $this->assertEquals('clients', $relatedModel->getTable());
    }

    /**
     * Test: customer() relationship respects legacy config structure
     */
    public function test_customer_relationship_respects_legacy_config(): void
    {
        // Arrange: Set up legacy customer model config
        config(['officeguy.models.customer' => null]);
        config(['officeguy.customer_model_class' => 'App\\Models\\LegacyClient']);

        // Create a mock entity
        $entity = new CrmEntity;

        // Act: Get the customer relationship
        $relationship = $entity->customer();

        // Assert: Relationship is BelongsTo
        $this->assertInstanceOf(BelongsTo::class, $relationship);

        // Assert: Relationship uses a model
        $relatedModel = $relationship->getRelated();
        $this->assertInstanceOf(Model::class, $relatedModel);

        // Assert: The relationship table matches legacy model
        $this->assertEquals('legacy_clients', $relatedModel->getTable());
    }

    /**
     * Test: client() (deprecated) delegates to customer() relationship
     */
    public function test_client_relationship_delegates_to_customer(): void
    {
        // Arrange: Set up a custom customer model
        config(['officeguy.models.customer' => 'App\\Models\\CustomCustomer']);

        // Create a mock entity
        $entity = new CrmEntity;

        // Act: Get both relationships
        $customerRelationship = $entity->customer();
        $clientRelationship = $entity->client();

        // Assert: Both are BelongsTo instances
        $this->assertInstanceOf(BelongsTo::class, $customerRelationship);
        $this->assertInstanceOf(BelongsTo::class, $clientRelationship);

        // Assert: Both use the same table (proving they're the same)
        $this->assertEquals(
            $customerRelationship->getRelated()->getTable(),
            $clientRelationship->getRelated()->getTable()
        );

        // Assert: Both use the configured custom model table
        $this->assertEquals('customers', $clientRelationship->getRelated()->getTable());
    }

    /**
     * Test: client() relationship maintains backward compatibility
     */
    public function test_client_relationship_backward_compatibility(): void
    {
        // Arrange: Ensure fallback to App\Models\Client
        config(['officeguy.models.customer' => null]);
        config(['officeguy.customer_model_class' => null]);

        // Create a mock entity
        $entity = new CrmEntity;

        // Act: Get the client relationship (old method)
        $relationship = $entity->client();

        // Assert: Relationship is BelongsTo
        $this->assertInstanceOf(BelongsTo::class, $relationship);

        // Assert: Relationship uses default Client model table
        $this->assertEquals('clients', $relationship->getRelated()->getTable());
    }

    /**
     * Test: customer() relationship uses correct foreign key
     */
    public function test_customer_relationship_uses_client_id_foreign_key(): void
    {
        // Arrange: Set up a custom customer model
        config(['officeguy.models.customer' => 'App\\Models\\CustomCustomer']);

        // Create a mock entity
        $entity = new CrmEntity;

        // Act: Get the customer relationship
        $relationship = $entity->customer();

        // Assert: Relationship uses 'client_id' as foreign key
        $this->assertEquals('client_id', $relationship->getForeignKeyName());
    }

    /**
     * Test: client() relationship uses correct foreign key
     */
    public function test_client_relationship_uses_client_id_foreign_key(): void
    {
        // Arrange: Ensure fallback to default
        config(['officeguy.models.customer' => null]);

        // Create a mock entity
        $entity = new CrmEntity;

        // Act: Get the client relationship
        $relationship = $entity->client();

        // Assert: Relationship uses 'client_id' as foreign key
        $this->assertEquals('client_id', $relationship->getForeignKeyName());
    }

    /**
     * Test: New config structure takes priority over legacy
     */
    public function test_new_config_takes_priority_over_legacy(): void
    {
        // Arrange: Set both new and legacy config
        config(['officeguy.models.customer' => 'App\\Models\\NewCustomer']);
        config(['officeguy.customer_model_class' => 'App\\Models\\LegacyClient']);

        // Create a mock entity
        $entity = new CrmEntity;

        // Act: Get the customer relationship
        $relationship = $entity->customer();

        // Assert: Uses new config structure (higher priority)
        $this->assertEquals('new_customers', $relationship->getRelated()->getTable());
    }

    /**
     * Test: customer() and client() relationships are functionally identical
     */
    public function test_customer_and_client_relationships_are_identical(): void
    {
        // Arrange: Set up a custom customer model
        config(['officeguy.models.customer' => 'App\\Models\\CustomCustomer']);

        // Create a mock entity
        $entity = new CrmEntity;

        // Act: Get both relationships
        $customerRel = $entity->customer();
        $clientRel = $entity->client();

        // Assert: Both return BelongsTo
        $this->assertInstanceOf(BelongsTo::class, $customerRel);
        $this->assertInstanceOf(BelongsTo::class, $clientRel);

        // Assert: Both use same table
        $this->assertEquals(
            $customerRel->getRelated()->getTable(),
            $clientRel->getRelated()->getTable()
        );

        // Assert: Both use same foreign key
        $this->assertEquals(
            $customerRel->getForeignKeyName(),
            $clientRel->getForeignKeyName()
        );

        // Assert: Both use same owner key
        $this->assertEquals(
            $customerRel->getOwnerKeyName(),
            $clientRel->getOwnerKeyName()
        );
    }

    /**
     * Test: Empty string config falls back to default
     */
    public function test_empty_string_config_falls_back_to_default(): void
    {
        // Arrange: Set empty string in new config
        config(['officeguy.models.customer' => '']);
        config(['officeguy.customer_model_class' => '']);

        // Create a mock entity
        $entity = new CrmEntity;

        // Act: Get the customer relationship
        $relationship = $entity->customer();

        // Assert: Falls back to App\Models\Client table
        $this->assertEquals('clients', $relationship->getRelated()->getTable());
    }
}
