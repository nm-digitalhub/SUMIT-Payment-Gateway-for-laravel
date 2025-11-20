# Upgrade Guide - Filament v3 to v4

This guide helps you upgrade your Laravel application from using this package with Filament v3 to Filament v4.

## Overview

The Laravel SUMIT Gateway package has been fully upgraded to support Filament v4 and Laravel 11.28+. This package includes complete Filament resource implementations for both admin and client panels.

## What Changed in Filament v4

### Package-Level Changes
- **Replaced deprecated APIs**: All Filament v3-specific APIs have been updated to v4
- **BadgeColumn → TextColumn**: Status badges now use `TextColumn::make()->badge()` with modern match expressions
- **Color syntax**: Updated to use inline color functions with match expressions
- **Form components**: All form components verified for v4 compatibility
- **Table actions**: Updated to use v4 action APIs
- **Panel configuration**: Verified middleware and discovery configurations

## Requirements

### Before Upgrade
- PHP 8.2+
- Laravel 10.x or 11.x
- Filament 3.x

### After Upgrade
- PHP 8.2+ (unchanged)
- Laravel 11.28+
- Filament 4.x

## Upgrade Steps

### Step 1: Backup Your Application
Before upgrading, ensure you have:
- A complete backup of your application
- All changes committed to version control
- A testing environment to verify the upgrade

### Step 2: Update Laravel (if needed)

If you're currently on Laravel 10.x, you need to upgrade to Laravel 11.28 or higher first.

Follow the official Laravel upgrade guide:
https://laravel.com/docs/11.x/upgrade

**Key Laravel 11 changes to be aware of:**
- New application structure (optional)
- Updated configuration file locations
- Changes to middleware
- Updated exception handling

### Step 3: Update Filament (if using)

If your application uses Filament for admin panels, upgrade Filament to v4.

Follow the official Filament upgrade guide:
https://filamentphp.com/docs/4.x/upgrade-guide

**Important Filament v4 changes:**
- PHP 8.2+ required
- Laravel 11.28+ required
- Tailwind CSS v4 (if using custom themes)
- New resource directory structure (optional)
- Various API changes in Resources, Forms, Tables, etc.

### Step 4: Update This Package

Once Laravel and Filament are upgraded, update the package:

```bash
composer update officeguy/laravel-sumit-gateway
```

### Step 5: Clear Caches

Clear all application caches:

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Step 6: Test Your Application

Thoroughly test:
- Payment processing functionality
- Card payments (all PCI modes)
- Bit payments
- Token management
- Document generation
- Webhook/callback handling

## Breaking Changes

### Package Level
The following Filament v3 APIs have been updated to v4:
- **BadgeColumn**: Replaced with `TextColumn::make()->badge()` in TransactionResource and ClientTransactionResource
- **Status colors**: Now use match expressions instead of array mappings
- **Icons**: Updated to use inline icon functions with match expressions

All other components (IconColumn, form fields, actions, filters) remain compatible with v4.

### Your Application Level
If you've implemented custom Filament resources, pages, or forms that interact with this package's models, you'll need to update them according to the Filament v4 upgrade guide.

## Common Issues

### Issue: Composer dependency conflicts
**Solution:** Ensure all your packages support Laravel 11 and Filament v4. Update or temporarily remove incompatible packages.

### Issue: Laravel 11 application structure
**Solution:** You can keep your Laravel 10 structure in Laravel 11. The new structure is optional.

### Issue: Tailwind CSS v4 changes
**Solution:** If using custom Filament themes, update your Tailwind configuration. If using default Filament themes, no action needed.

## Future Filament Integration

The package already includes complete Filament v4 resource implementations:

### Admin Panel Resources
- **TransactionResource**: Full transaction management with status filtering and detailed views
- **TokenResource**: Payment token management with expiry tracking
- **DocumentResource**: Invoice and receipt management
- **OfficeGuySettings Page**: Read-only configuration viewer

### Client Panel Resources (`/client`)
- **ClientTransactionResource**: Customer transaction history (user-filtered)
- **ClientPaymentMethodResource**: Saved payment method management
- **ClientDocumentResource**: Customer invoices and receipts (user-filtered)

All resources are production-ready and use Filament v4 APIs and best practices.

## Rollback Plan

If you need to rollback:

1. Restore your application from backup
2. Or manually downgrade:
   ```bash
   composer require "laravel/framework:^10.0" "filament/filament:^3.0"
   composer require "officeguy/laravel-sumit-gateway:1.*"
   ```

## Getting Help

If you encounter issues during the upgrade:

1. Check the official upgrade guides:
   - [Laravel 11 Upgrade Guide](https://laravel.com/docs/11.x/upgrade)
   - [Filament v4 Upgrade Guide](https://filamentphp.com/docs/4.x/upgrade-guide)

2. Review your error logs:
   ```bash
   tail -f storage/logs/laravel.log
   ```

3. Check for package compatibility issues:
   ```bash
   composer why-not laravel/framework 11.28
   composer why-not filament/filament 4.0
   ```

4. Open an issue on GitHub:
   https://github.com/nm-digitalhub/SUMIT-Payment-Gateway-for-laravel/issues

## Testing Checklist

After upgrading, verify:

- [ ] Application starts without errors
- [ ] Payment forms render correctly
- [ ] Card payments process successfully (test mode)
- [ ] Bit payments work (if enabled)
- [ ] Callbacks and webhooks are received
- [ ] Tokens can be created and managed
- [ ] Documents are generated correctly
- [ ] All existing features work as expected
- [ ] No console errors in browser
- [ ] No errors in Laravel logs

## Conclusion

This upgrade is straightforward since the package doesn't currently use Filament-specific code. The main work is upgrading your Laravel and Filament installations, after which the package will work seamlessly with the new versions.
