<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Tests\Unit;

use OfficeGuy\LaravelSumitGateway\Models\OfficeGuySetting;
use OfficeGuy\LaravelSumitGateway\OfficeGuyServiceProvider;
use OfficeGuy\LaravelSumitGateway\Services\SettingsService;
use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Schema;

/**
 * Test SettingsService functionality.
 *
 * Covers:
 * - DB override priority over config
 * - Table existence handling
 * - CRUD operations
 * - Array flattening for nested settings
 * - Editable keys list includes all required fields
 */
class SettingsServiceTest extends TestCase
{
    protected SettingsService $settingsService;

    protected function getPackageProviders($app): array
    {
        return [
            OfficeGuyServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations for test database
        $this->artisan('migrate', ['--database' => 'testing']);

        $this->settingsService = app(SettingsService::class);
    }

    protected function tearDown(): void
    {
        // Clean up database
        OfficeGuySetting::query()->delete();

        parent::tearDown();
    }

    describe('getEditableKeys()', function () {
        it('includes success page settings', function () {
            $keys = $this->settingsService->getEditableKeys();

            expect($keys)
                ->toContain('success_enabled')
                ->toContain('success_token_ttl')
                ->toContain('success_rate_limit_max')
                ->toContain('success_rate_limit_decay');
        });

        it('includes route configuration settings', function () {
            $keys = $this->settingsService->getEditableKeys();

            expect($keys)
                ->toContain('routes_prefix')
                ->toContain('routes_card_callback')
                ->toContain('routes_bit_webhook')
                ->toContain('routes_sumit_webhook')
                ->toContain('routes_success')
                ->toContain('routes_failed');
        });

        it('includes collection settings', function () {
            $keys = $this->settingsService->getEditableKeys();

            expect($keys)
                ->toContain('collection.email')
                ->toContain('collection.sms')
                ->toContain('collection.schedule_time')
                ->toContain('collection.reminder_days')
                ->toContain('collection.max_attempts');
        });

        it('includes all required settings categories', function () {
            $keys = $this->settingsService->getEditableKeys();

            // API Credentials
            expect($keys)->toContain('company_id')
                ->toContain('private_key')
                ->toContain('public_key');

            // Environment
            expect($keys)->toContain('environment')
                ->toContain('pci')
                ->toContain('testing');

            // Subscriptions
            expect($keys)->toContain('subscriptions_enabled')
                ->toContain('subscriptions_default_interval');

            // Webhooks
            expect($keys)->toContain('webhook_payment_completed')
                ->toContain('webhook_secret');
        });
    });

    describe('get() method', function () {
        it('returns value from database when it exists', function () {
            // Arrange: Set value in DB
            OfficeGuySetting::set('company_id', '12345');

            // Act
            $result = $this->settingsService->get('company_id');

            // Assert
            expect($result)->toBe('12345');
        });

        it('falls back to config when DB value does not exist', function () {
            // Arrange: Set config value
            config(['officeguy.company_id' => '99999']);

            // Act: No DB value set
            $result = $this->settingsService->get('company_id');

            // Assert
            expect($result)->toBe('99999');
        });

        it('returns default value when neither DB nor config has value', function () {
            // Act
            $result = $this->settingsService->get('non_existent_key', 'my-default');

            // Assert
            expect($result)->toBe('my-default');
        });

        it('prioritizes DB over config', function () {
            // Arrange
            config(['officeguy.company_id' => 'config-value']);
            OfficeGuySetting::set('company_id', 'db-value');

            // Act
            $result = $this->settingsService->get('company_id');

            // Assert: DB value should win
            expect($result)->toBe('db-value');
        });

        it('handles nested keys correctly (e.g., collection.email)', function () {
            // Arrange
            OfficeGuySetting::set('collection.email', true);

            // Act
            $result = $this->settingsService->get('collection.email');

            // Assert
            expect($result)->toBeTrue();
        });
    });

    describe('set() method', function () {
        it('saves value to database', function () {
            // Act
            $this->settingsService->set('test_key', 'test_value');

            // Assert
            expect(OfficeGuySetting::get('test_key'))->toBe('test_value');
        });

        it('updates existing value', function () {
            // Arrange
            OfficeGuySetting::set('test_key', 'old_value');

            // Act
            $this->settingsService->set('test_key', 'new_value');

            // Assert
            expect(OfficeGuySetting::get('test_key'))->toBe('new_value');
        });

        it('throws exception when table does not exist', function () {
            // Arrange: Drop the table
            Schema::dropIfExists('officeguy_settings');

            // Expect & Act
            expect(fn () => $this->settingsService->set('key', 'value'))
                ->toThrow(\RuntimeException::class, 'Settings table does not exist');
        });
    });

    describe('setMany() method', function () {
        it('saves multiple settings at once', function () {
            // Arrange
            $settings = [
                'company_id' => '12345',
                'private_key' => 'secret-key',
                'environment' => 'test',
            ];

            // Act
            $this->settingsService->setMany($settings);

            // Assert
            expect(OfficeGuySetting::get('company_id'))->toBe('12345');
            expect(OfficeGuySetting::get('private_key'))->toBe('secret-key');
            expect(OfficeGuySetting::get('environment'))->toBe('test');
        });

        it('flattens nested arrays before saving', function () {
            // Arrange: Nested array structure
            $settings = [
                'collection' => [
                    'email' => true,
                    'sms' => false,
                ],
            ];

            // Act
            $this->settingsService->setMany($settings);

            // Assert: Should be saved as flat keys
            expect(OfficeGuySetting::get('collection.email'))->toBeTrue();
            expect(OfficeGuySetting::get('collection.sms'))->toBeFalse();
        });

        it('preserves data types correctly', function () {
            // Arrange
            $settings = [
                'success_enabled' => true,         // boolean
                'success_token_ttl' => 48,          // integer
                'company_id' => '12345',            // string
                'testing' => false,                 // boolean
            ];

            // Act
            $this->settingsService->setMany($settings);

            // Assert
            expect(OfficeGuySetting::get('success_enabled'))->toBeTrue();
            expect(OfficeGuySetting::get('success_token_ttl'))->toBe(48);
            expect(OfficeGuySetting::get('company_id'))->toBe('12345');
            expect(OfficeGuySetting::get('testing'))->toBeFalse();
        });
    });

    describe('getEditableSettings() method', function () {
        it('returns all editable settings with current values', function () {
            // Arrange: Set some values in DB
            OfficeGuySetting::set('company_id', '12345');
            OfficeGuySetting::set('success_enabled', false);
            OfficeGuySetting::set('collection.email', true);

            // Act
            $settings = $this->settingsService->getEditableSettings();

            // Assert
            expect($settings)->toHaveKey('company_id');
            expect($settings['company_id'])->toBe('12345');
            expect($settings['success_enabled'])->toBeFalse();
            expect($settings['collection']['email'])->toBeTrue();
        });

        it('merges DB overrides with config defaults', function () {
            // Arrange
            config(['officeguy.company_id' => 'config-value']);
            OfficeGuySetting::set('company_id', 'db-value');

            // Act
            $settings = $this->settingsService->getEditableSettings();

            // Assert: DB value should override config
            expect($settings['company_id'])->toBe('db-value');
        });

        it('includes config defaults for settings not in DB', function () {
            // Arrange: No DB values set
            // Config has defaults defined

            // Act
            $settings = $this->settingsService->getEditableSettings();

            // Assert: Should have all editable keys with config defaults
            expect($settings)->toHaveKeys([
                'company_id',
                'private_key',
                'public_key',
                'success_enabled',
                'routes_prefix',
            ]);
        });

        it('builds nested arrays from flat DB keys', function () {
            // Arrange: Flat keys in DB
            OfficeGuySetting::set('collection.email', true);
            OfficeGuySetting::set('collection.sms', false);
            OfficeGuySetting::set('collection.max_attempts', 5);

            // Act
            $settings = $this->settingsService->getEditableSettings();

            // Assert: Should be nested
            expect($settings['collection'])->toBeArray();
            expect($settings['collection']['email'])->toBeTrue();
            expect($settings['collection']['sms'])->toBeFalse();
            expect($settings['collection']['max_attempts'])->toBe(5);
        });
    });

    describe('has() method', function () {
        it('returns true when setting exists in database', function () {
            // Arrange
            OfficeGuySetting::set('test_key', 'test_value');

            // Act
            $result = $this->settingsService->has('test_key');

            // Assert
            expect($result)->toBeTrue();
        });

        it('returns false when setting does not exist', function () {
            // Act
            $result = $this->settingsService->has('non_existent_key');

            // Assert
            expect($result)->toBeFalse();
        });

        it('returns false when table does not exist', function () {
            // Arrange: Drop the table
            Schema::dropIfExists('officeguy_settings');

            // Act
            $result = $this->settingsService->has('any_key');

            // Assert
            expect($result)->toBeFalse();
        });
    });

    describe('remove() method', function () {
        it('deletes setting from database', function () {
            // Arrange
            OfficeGuySetting::set('test_key', 'test_value');

            // Act
            $this->settingsService->remove('test_key');

            // Assert
            expect($this->settingsService->has('test_key'))->toBeFalse();
        });

        it('falls back to config after removal', function () {
            // Arrange
            config(['officeguy.company_id' => 'config-value']);
            OfficeGuySetting::set('company_id', 'db-value');

            // Act: Remove DB value
            $this->settingsService->remove('company_id');

            // Assert: Should return config value
            expect($this->settingsService->get('company_id'))->toBe('config-value');
        });
    });

    describe('resetToDefault() method', function () {
        it('removes setting from database', function () {
            // Arrange
            OfficeGuySetting::set('test_key', 'custom_value');

            // Act
            $this->settingsService->resetToDefault('test_key');

            // Assert
            expect(OfficeGuySetting::has('test_key'))->toBeFalse();
        });

        it('causes get() to fall back to config', function () {
            // Arrange
            config(['officeguy.company_id' => 'config-default']);
            OfficeGuySetting::set('company_id', 'custom-value');

            // Act
            $this->settingsService->resetToDefault('company_id');

            // Assert
            expect($this->settingsService->get('company_id'))->toBe('config-default');
        });
    });

    describe('resetAllToDefaults() method', function () {
        it('clears all settings from database', function () {
            // Arrange: Set multiple values
            OfficeGuySetting::set('key1', 'value1');
            OfficeGuySetting::set('key2', 'value2');
            OfficeGuySetting::set('key3', 'value3');

            // Act
            $this->settingsService->resetAllToDefaults();

            // Assert
            expect(OfficeGuySetting::count())->toBe(0);
        });

        it('resets all editable settings to config defaults', function () {
            // Arrange
            config(['officeguy.company_id' => 'config-1']);
            config(['officeguy.environment' => 'config-2']);
            OfficeGuySetting::set('company_id', 'db-1');
            OfficeGuySetting::set('environment', 'db-2');

            // Act
            $this->settingsService->resetAllToDefaults();

            // Assert
            expect($this->settingsService->get('company_id'))->toBe('config-1');
            expect($this->settingsService->get('environment'))->toBe('config-2');
        });
    });

    describe('Integration: Full CRUD cycle', function () {
        it('can create, read, update, and delete settings', function () {
            // Create
            $this->settingsService->set('test_key', 'initial_value');
            expect($this->settingsService->get('test_key'))->toBe('initial_value');

            // Read
            $value = $this->settingsService->get('test_key');
            expect($value)->toBe('initial_value');

            // Update
            $this->settingsService->set('test_key', 'updated_value');
            expect($this->settingsService->get('test_key'))->toBe('updated_value');

            // Delete
            $this->settingsService->remove('test_key');
            expect($this->settingsService->has('test_key'))->toBeFalse();
        });
    });

    describe('Type Safety & Parsing', function () {
        it('handles boolean strings correctly with JSON cast', function () {
            // Simulate what happens when DB returns JSON-decoded value
            OfficeGuySetting::set('success_enabled', 'false'); // String "false"

            $result = $this->settingsService->get('success_enabled', true);

            // The model's JSON cast should convert this to boolean
            expect($result)->toBeFalse(); // Not the string "false"
        });

        it('preserves integer types through JSON cast', function () {
            OfficeGuySetting::set('success_token_ttl', '48'); // String "48"

            $result = $this->settingsService->get('success_token_ttl', 24);

            // The model's JSON cast should preserve the integer type
            expect($result)->toBeInt();
            expect($result)->toBe(48);
        });

        it('handles numeric strings correctly', function () {
            OfficeGuySetting::set('test_numeric', '123');

            $result = $this->settingsService->get('test_numeric');

            expect($result)->toBe('123'); // String preserved
        });

        it('handles zero as false correctly', function () {
            OfficeGuySetting::set('test_zero', '0');

            $result = $this->settingsService->get('test_zero');

            expect($result)->toBe('0');
        });

        it('handles empty string correctly', function () {
            OfficeGuySetting::set('test_empty', '');

            $result = $this->settingsService->get('test_empty');

            expect($result)->toBe('');
        });
    });

    describe('Legacy Config Fallback', function () {
        it('falls back to config when DB value does not exist', function () {
            // Arrange: Set config value
            config(['officeguy.success_token_ttl' => 48]);
            config(['officeguy.success_enabled' => false]);

            // Act: No DB value
            $ttl = $this->settingsService->get('success_token_ttl', 24);
            $enabled = $this->settingsService->get('success_enabled', true);

            // Assert: Should return config values
            expect($ttl)->toBe(48);
            expect($enabled)->toBeFalse();
        });

        it('prioritizes DB over config when both exist', function () {
            // Arrange
            config(['officeguy.success_token_ttl' => 48]);
            OfficeGuySetting::set('success_token_ttl', 72);

            // Act
            $result = $this->settingsService->get('success_token_ttl');

            // Assert: DB value should win
            expect($result)->toBe(72);
        });
    });
});
