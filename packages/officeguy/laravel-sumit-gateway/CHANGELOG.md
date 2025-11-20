# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- Upgraded from Filament v3 to Filament v4
- Updated minimum Laravel version requirement to 11.28 (required by Filament v4)
- Updated orchestra/testbench to v9.0 for Laravel 11 compatibility
- Updated PHPUnit to support both v10.0 and v11.0
- Updated README.md to reflect new version requirements
- Updated IMPLEMENTATION_SUMMARY.md documentation

### Migration Notes
This upgrade is **fully backwards compatible** for the package's current functionality:
- No breaking changes in existing code
- No Filament Resources, Pages, or components are currently implemented in the package
- The package is now ready for future Filament v4 integrations
- All existing payment processing functionality remains unchanged

### Upgrade Requirements
Projects using this package must update to:
- PHP 8.2 or higher (already required)
- Laravel 11.28 or higher (upgraded from Laravel 10.x/11.x)
- Filament 4.x (upgraded from Filament 3.x)

### For Package Users
If you are using this package in your Laravel application:

1. **Update your application's Laravel version** to 11.28 or higher
2. **Update your application's Filament version** to 4.x (if you're using Filament)
3. Run `composer update officeguy/laravel-sumit-gateway`
4. No code changes are required in your application

### For Future Development
The package is now ready for implementing Filament v4 resources:
- Admin panel resources for transactions, tokens, and documents
- Client panel resources for customer transaction history
- Filament form components for payment processing
- All new Filament features can be utilized (new directory structure, improved components, etc.)

## [1.0.0] - Initial Release

### Added
- Complete 1:1 port of SUMIT/OfficeGuy WooCommerce payment gateway plugin
- Card payment processing (PCI modes: simple, redirect, advanced)
- Bit payment support via Upay
- Token management for recurring payments
- Document generation (invoices, receipts)
- Multi-currency support (36+ currencies)
- Installment payment plans
- Comprehensive logging and error handling
- Laravel service provider with auto-discovery
- Database migrations for transactions, tokens, and documents
- HTTP controllers for payment callbacks and webhooks
- Blade component for payment form
- Full API integration with SUMIT platform
- Extensive documentation and guides
