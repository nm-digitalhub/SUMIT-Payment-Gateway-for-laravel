# SUMIT Payment Gateway - Test Suite

## Overview

This directory contains the automated test suite for the Laravel SUMIT Payment Gateway package, validating backward-compatible customer model resolution and integration with the service container.

## Test Structure

```
tests/
├── TestCase.php                              # Base test case with Orchestra Testbench setup
├── Unit/                                     # Unit tests (isolated component testing)
│   └── CustomerModelResolutionTest.php      # 13 tests for container binding resolution
└── Feature/                                  # Integration tests (service-level testing)
    └── CustomerMergeServiceTest.php         # 16 tests for CustomerMergeService integration
```

## Running Tests

### All Tests
```bash
vendor/bin/phpunit
```

### With Detailed Output
```bash
vendor/bin/phpunit --testdox
```

### Specific Test Suite
```bash
# Unit tests only
vendor/bin/phpunit tests/Unit

# Feature tests only
vendor/bin/phpunit tests/Feature
```

### Specific Test Class
```bash
vendor/bin/phpunit tests/Unit/CustomerModelResolutionTest.php
```

### Specific Test Method
```bash
vendor/bin/phpunit --filter test_new_config_structure_takes_priority
```

## Test Coverage

### Unit Tests (13 tests)
**File:** `tests/Unit/CustomerModelResolutionTest.php`

Tests the `resolveCustomerModel()` method and container binding:
- Priority sequence validation (new → legacy → null)
- Empty string handling
- Non-string value rejection
- Container binding consistency
- Namespace and backslash handling

### Integration Tests (16 tests)
**File:** `tests/Feature/CustomerMergeServiceTest.php`

Tests the `CustomerMergeService` integration:
- Config resolution through service
- Graceful null handling when disabled
- Model class validation
- Field mapping configuration
- Backward compatibility scenarios

## Test Results

```
PHPUnit 12.5.6 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.3.6
Configuration: phpunit.xml

.............................                                     29 / 29 (100%)

Time: 00:00.509, Memory: 42.50 MB

OK (29 tests, 37 assertions)
```

## Writing New Tests

### Unit Test Template
```php
<?php

namespace OfficeGuy\LaravelSumitGateway\Tests\Unit;

use OfficeGuy\LaravelSumitGateway\Tests\TestCase;

class MyFeatureTest extends TestCase
{
    public function test_my_feature_works(): void
    {
        // Arrange
        config(['officeguy.some_setting' => 'value']);

        // Act
        $result = app('officeguy.some_binding');

        // Assert
        $this->assertEquals('expected', $result);
    }
}
```

### Feature Test Template
```php
<?php

namespace OfficeGuy\LaravelSumitGateway\Tests\Feature;

use OfficeGuy\LaravelSumitGateway\Tests\TestCase;
use OfficeGuy\LaravelSumitGateway\Services\MyService;

class MyServiceTest extends TestCase
{
    protected MyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MyService::class);
    }

    public function test_service_method_returns_expected_value(): void
    {
        // Test implementation
    }
}
```

## Configuration

### phpunit.xml
- **Bootstrap:** `vendor/autoload.php`
- **Database:** SQLite in-memory (`:memory:`)
- **Environment:** `APP_ENV=testing`
- **Cache/Session:** Array drivers (fast, stateless)

### TestCase.php
- Extends Orchestra Testbench
- Registers `OfficeGuyServiceProvider`
- Sets up SQLite test database
- Provides clean Laravel environment for each test

## Best Practices

1. **Use Descriptive Test Names:** Follow `test_method_scenario_expected_result` pattern
2. **Isolate Tests:** Each test should be independent and not rely on test order
3. **Mock External Dependencies:** Don't make real API calls to SUMIT in tests
4. **Test Edge Cases:** Empty strings, null values, invalid types
5. **Test Backward Compatibility:** Ensure legacy configurations still work
6. **Arrange-Act-Assert:** Structure tests with clear setup, execution, and validation

## Continuous Integration

These tests are designed to run in CI/CD pipelines:
- Fast execution (~0.5 seconds for 29 tests)
- No external dependencies required
- In-memory database (no setup/teardown)
- Consistent results across environments

## Related Documentation

- **CUSTOMER_MODEL_CONFIG.md** - Configuration guide for customer model resolution
- **IMPLEMENTATION_VALIDATION.md** - Implementation validation report
- **CLAUDE.md** - Development guide and package architecture

## Troubleshooting

### "Class not found" errors
Run `composer install` to ensure all dependencies are installed.

### "Database not found" errors
Check that `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:` are set in phpunit.xml.

### Tests fail after config changes
Clear Laravel cache: `php artisan config:clear`

## Contributing

When adding new features:
1. Write tests first (TDD approach)
2. Ensure all existing tests pass
3. Add new tests for new functionality
4. Update this README if adding new test categories
5. Run full test suite before committing

## Support

For questions or issues:
- GitHub: https://github.com/nm-digitalhub/SUMIT-Payment-Gateway-for-laravel/issues
- Email: info@nm-digitalhub.com
