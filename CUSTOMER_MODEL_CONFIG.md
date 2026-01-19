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

1. **Database** (Admin Panel editable): `officeguy_settings.customer_model_class` - **HIGHEST PRIORITY**
   - Only applies to the flat key `customer_model_class`
   - Editable via Admin Panel: `/admin/office-guy-settings` → Customer Management tab
   - Stored in `officeguy_settings` table as key-value pair
2. **New structure** (Config-only): `config('officeguy.models.customer')`
   - Nested key, NOT database-backed
   - Set in `config/officeguy.php` or via published config
3. **Legacy structure** (Config fallback): `config('officeguy.customer_model_class')`
   - Flat key with .env support: `OFFICEGUY_CUSTOMER_MODEL_CLASS`
   - Database value (if set) overrides this config value
4. **Not configured**: Returns `null`

### Database vs Config: Key Distinction

**Important**: Only the flat key `customer_model_class` can be stored in the database and edited via Admin Panel. The nested key `models.customer` remains config-only.

| Configuration Key | Database-Backed? | Admin Panel Editable? | Priority Level |
|-------------------|------------------|------------------------|----------------|
| `customer_model_class` (flat) | ✅ YES | ✅ YES | 🥇 Highest (Layer 1) |
| `models.customer` (nested) | ❌ NO | ❌ NO | 🥈 Second (Layer 2) |
| `customer_model_class` (flat, config) | 🔄 Overridden by DB | ❌ NO | 🥉 Third (Layer 3) |

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

However, you have **three migration options**:

#### Option 1: Use Admin Panel (Recommended for Runtime Changes)

1. Navigate to Admin Panel: `/admin/office-guy-settings`
2. Go to **Customer Management** tab
3. Set the **Customer Model Class** field to your model: `App\Models\Customer`
4. Click **Save**

This sets the database value which takes **highest priority** and can be changed without code deployment.

#### Option 2: Migrate to New Config Structure (Recommended for New Projects)

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

#### Option 3: Keep Legacy Structure (Fully Supported)

No changes needed. The flat key `customer_model_class` continues to work and can be overridden via Admin Panel.

## Implementation Details

The resolution logic is implemented in:

- `OfficeGuyServiceProvider::resolveCustomerModel()` - Implements the 3-layer fallback logic with database priority
- `OfficeGuyServiceProvider::register()` - Binds `officeguy.customer_model` to the container as singleton
- `CustomerMergeService::getModelClass()` - Uses the container binding
- `OfficeGuySetting` model - Handles database storage for `customer_model_class` (flat key only)
- Admin Panel Settings Page - Provides UI for editing `customer_model_class` at runtime

**Resolution Flow**:
```
1. Check officeguy_settings table for 'customer_model_class'
   ↓ (if found and is_string) → RETURN
2. Check config('officeguy.models.customer')
   ↓ (if found and is_string) → RETURN
3. Check config('officeguy.customer_model_class')
   ↓ (if found and is_string) → RETURN
4. Return null
```

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
2. `customer_local_sync_enabled` is set to `true`
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
