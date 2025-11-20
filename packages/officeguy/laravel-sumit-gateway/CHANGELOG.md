# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Complete Filament v4 Integration**
  - Admin Panel Resources:
    - TransactionResource - Full transaction management with filtering, status badges, and detailed views
    - TokenResource - Payment token management with expiry tracking and default setting
    - DocumentResource - Invoice and receipt management with type filtering
    - OfficeGuySettings Page - Read-only configuration viewer
  - Client Panel (`/client`):
    - ClientPanelProvider - Separate customer-facing panel
    - ClientTransactionResource - Customer transaction history (user-filtered)
    - ClientPaymentMethodResource - Saved payment method management
    - ClientDocumentResource - Customer invoices and receipts (user-filtered)
  - Full integration with existing models and services
  - Navigation badges for pending transactions, expired tokens, and draft documents
  - Comprehensive README documentation for all Filament resources
  - Auto-discovery of Client Panel Provider via composer

### Changed
- Upgraded from Filament v3 to Filament v4
- **Updated Filament v4 APIs:**
  - Fixed `navigationIcon` property type to `string|\BackedEnum|null` for v4 compatibility in all Resources and Pages
  - Replaced deprecated `BadgeColumn` with `TextColumn->badge()` using match expressions
  - Updated status badge color syntax to use modern match expressions
  - Verified all form components, table columns, and actions for v4 compatibility
- Updated minimum Laravel version requirement to 11.28 (required by Filament v4)
- Updated orchestra/testbench to v9.0 for Laravel 11 compatibility
- Updated PHPUnit to support both v10.0 and v11.0
- Updated README.md to reflect new version requirements and Filament resources
- Updated IMPLEMENTATION_SUMMARY.md to document implemented Filament resources
- Updated composer.json to auto-discover ClientPanelProvider

### Migration Notes
Filament resources are now **fully implemented** and production-ready:
- All admin resources automatically discovered and registered
- Client panel requires authentication and filters data per user
- Resources use Filament v4 APIs and best practices
- Settings page is read-only (settings managed via .env)

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
