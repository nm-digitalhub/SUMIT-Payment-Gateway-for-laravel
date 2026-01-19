# Customer Model Configuration - Backward Compatibility

## Overview

The SUMIT Laravel Gateway package now supports both the new and legacy configuration structures for specifying the customer model class.

## Configuration Options

### New Structure (Recommended)

```php
// config/officeguy.php
return [
    'models' => [
        'customer' => \App\Models\Customer::class,
        'order' => null,
    ],
];
```

### Legacy Structure (Still Supported)

```php
// config/officeguy.php
return [
    'customer_model_class' => env('OFFICEGUY_CUSTOMER_MODEL_CLASS', 'App\\Models\\Client'),
];
```

### Environment Variable

```env
# .env
OFFICEGUY_CUSTOMER_MODEL_CLASS=App\Models\Client
```

## Resolution Priority

The package resolves the customer model class in the following order:

1. **New structure**: `config('officeguy.models.customer')`
2. **Legacy structure**: `config('officeguy.customer_model_class')`
3. **Not configured**: Returns `null`

## Migration Guide

### For New Installations

Use the new structure in your `config/officeguy.php`:

```php
'models' => [
    'customer' => \App\Models\Customer::class,
    'order' => \App\Models\Order::class,
],
```

### For Existing Installations

**No changes required!** Your existing configuration will continue to work:

```php
'customer_model_class' => env('OFFICEGUY_CUSTOMER_MODEL_CLASS', 'App\\Models\\Client'),
```

However, you can optionally migrate to the new structure:

1. Open `config/officeguy.php`
2. Locate the `customer_model_class` setting
3. Copy its value
4. Update the `models.customer` setting with that value
5. (Optional) Remove the old `customer_model_class` setting

**Before:**
```php
'customer_model_class' => 'App\\Models\\Client',
'models' => [
    'customer' => null,
],
```

**After:**
```php
'models' => [
    'customer' => \App\Models\Client::class,
],
```

## Implementation Details

The resolution logic is implemented in:

- `OfficeGuyServiceProvider::resolveCustomerModel()` - Implements the fallback logic
- `OfficeGuyServiceProvider::register()` - Binds `officeguy.customer_model` to the container
- `CustomerMergeService::getModelClass()` - Uses the container binding

## Usage in Your Code

If you need to access the customer model class in your own code:

```php
// Resolve from container (recommended)
$customerModel = app('officeguy.customer_model');

// Or use the service
$customerMerge = app(\OfficeGuy\LaravelSumitGateway\Services\CustomerMergeService::class);
$customerModel = $customerMerge->getModelClass();
```

## Error Handling

If neither configuration is set and customer sync is enabled, the system will log a warning but continue operation gracefully:

```
CustomerMergeService: Invalid or missing customer model class
```

To enable customer sync, ensure:
1. Customer model is configured (either new or legacy structure)
2. `customer_sync_enabled` is set to `true`
3. Field mappings are configured

## Related Settings

The following settings work together with customer model configuration:

```php
'customer_merging_enabled' => true,
'customer_local_sync_enabled' => true,
'customer_field_email' => 'email',
'customer_field_name' => 'name',
'customer_field_phone' => 'phone',
'customer_field_sumit_id' => 'sumit_customer_id',
```

## Support

For questions or issues, please open an issue on GitHub:
https://github.com/nm-digitalhub/SUMIT-Payment-Gateway-for-laravel/issues
