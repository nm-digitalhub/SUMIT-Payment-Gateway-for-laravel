# Upgrade Guide: Filament v3 to v4

## Overview

This package has been upgraded to support Filament v4, which requires some changes to your application's dependencies and configuration.

## What Changed

### Dependency Updates

The following dependencies have been updated:

- **Filament**: `^3.0` → `^4.0`
- **Laravel**: `^10.0|^11.0` → `^11.28`
- **Orchestra Testbench**: `^8.0|^9.0` → `^9.0` (for testing)
- **PHPUnit**: `^10.0` → `^10.0|^11.0` (for testing)

### Why Laravel 11.28+?

Filament v4 requires Laravel 11.28 or higher. This is a hard requirement from FilamentPHP to ensure compatibility with the new features and architecture introduced in v4.

## Impact on Your Application

### No Breaking Changes in This Package

**Important:** This package does not currently implement any Filament resources, pages, panels, or widgets. Therefore, the upgrade from Filament v3 to v4 only affects the dependency versions.

If you have been using this package, you will **not** need to change any of your existing code that uses the payment gateway functionality.

### Future Filament Resources

When Filament admin resources and client panels are added to this package in the future, they will be built using Filament v4 architecture from the start. This means:

- New Schema architecture for forms and tables
- Improved performance with partial rendering
- Modern component structure
- Better TypeScript support

## Upgrade Steps

### 1. Check Your Application Requirements

Before upgrading, ensure your application meets these requirements:

- PHP 8.2 or higher ✅
- Laravel 11.28 or higher ⚠️

### 2. Upgrade Laravel (if needed)

If you're currently on Laravel 10.x, you'll need to upgrade to Laravel 11.28+:

```bash
# Review Laravel 11 upgrade guide
https://laravel.com/docs/11.x/upgrade

# Update your composer.json
composer require laravel/framework:^11.28
```

### 3. Update This Package

Update the package to the latest version:

```bash
composer update officeguy/laravel-sumit-gateway
```

### 4. Update Filament (if you're using it)

If your application uses Filament directly, you'll need to upgrade it as well:

```bash
# Install the Filament upgrade helper
composer require filament/upgrade:^4.0 -W --dev

# Run the automated upgrade script
vendor/bin/filament-v4

# Follow the prompts and review changes
```

For detailed Filament upgrade instructions, see: https://filamentphp.com/docs/4.x/upgrade-guide

### 5. Test Your Application

After upgrading:

1. Clear all caches:
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan view:clear
   php artisan route:clear
   ```

2. Run your test suite:
   ```bash
   php artisan test
   ```

3. Test payment processing functionality:
   - Card payments
   - Bit payments
   - Token management
   - Document generation

## Package-Specific Notes

### What's Not Affected

The following package features continue to work exactly as before:

- ✅ Payment processing (card and Bit)
- ✅ Tokenization
- ✅ Document management
- ✅ API communication with SUMIT
- ✅ Callback and webhook handling
- ✅ Database models and migrations
- ✅ Service classes
- ✅ Configuration
- ✅ Blade components

### What's New

- 🆕 Ready for Filament v4 resources (when implemented)
- 🆕 Compatible with Laravel 11.28+
- 🆕 Modern dependency stack

## Future Development

### Planned Filament Resources (v4)

When Filament admin resources are implemented, they will include:

**Admin Panel:**
- Transaction management resource
- Token management resource
- Document management resource
- Settings page

**Client Portal:**
- Payment method management
- Transaction history
- Invoice downloads

All of these will be built using Filament v4's new architecture.

## Troubleshooting

### Composer Dependency Conflicts

If you encounter dependency conflicts during upgrade:

1. Review your `composer.json` for packages that require Laravel 10.x
2. Update those packages to Laravel 11-compatible versions
3. If a package doesn't support Laravel 11, look for alternatives

### Testing Issues

If tests fail after upgrade:

1. Update `orchestra/testbench` to `^9.0`
2. Review test setup for Laravel 11 compatibility
3. Update any deprecated testing methods

## Getting Help

- **Package Issues**: https://github.com/nm-digitalhub/SUMIT-Payment-Gateway-for-laravel/issues
- **Filament v4 Docs**: https://filamentphp.com/docs/4.x
- **Laravel 11 Docs**: https://laravel.com/docs/11.x

## Rollback

If you need to rollback to the previous version:

```bash
# In your application's composer.json, specify the older version
"officeguy/laravel-sumit-gateway": "^1.0"  # or previous version tag

# Then run
composer update officeguy/laravel-sumit-gateway
```

Note: You'll also need to ensure your Laravel version is compatible with the older package version.

## Summary

This is a straightforward upgrade that primarily affects dependency versions. Since no Filament resources are implemented yet in this package, your existing payment gateway functionality will continue to work without any code changes on your part.

The upgrade positions the package for future Filament v4 resource development while maintaining all existing functionality.
