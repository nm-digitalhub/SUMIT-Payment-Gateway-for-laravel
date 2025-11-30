# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [V1.7.2] - 2025-11-30

### Fixed
- **Critical Fix: Subscription Sync Now Includes ALL Subscriptions**
  - Fixed `SubscriptionService::fetchFromSumit()` to correctly pass `IncludeInactive` parameter as string (`'true'`/`'false'`) instead of boolean
  - SUMIT API requires string values for boolean parameters - this was preventing inactive/paused/cancelled subscriptions from being synced
  - Now correctly syncs all subscription statuses: active, paused, cancelled, expired
  - Result: Successfully syncing 18 subscriptions (9 active + 9 paused/inactive) vs previous 9 subscriptions
  - File: `src/Services/SubscriptionService.php:339`

- **Critical Fix: Document Sync with Proper Pagination Support**
  - Fixed `DocumentService::fetchFromSumit()` to use correct SUMIT API pagination structure with `Paging` object
  - Changed from `PageNumber` to `StartIndex + PageSize` pagination (as per SUMIT API spec)
  - Fixed pagination loop to check `HasNextPage` from API response instead of document count
  - Added `array_values()` to reindex filtered results properly
  - `IncludeDrafts` is now boolean (as required by API when using Paging object)
  - Result: Successfully syncing ALL documents across multiple pages (tested with 30+ documents)
  - Files: `src/Services/DocumentService.php:280-350`

- **Critical Fix: Auto-Sync Documents in Client Subscription Page**
  - Added automatic document synchronization when viewing client subscription page
  - `ClientSubscriptionResource::getEloquentQuery()` now calls `DocumentService::syncAllForCustomer()` on page load
  - Syncs all documents for the authenticated user with 5-year lookback period
  - Ensures invoice and payment counts are accurate and up-to-date
  - Fixes issue where subscription page showed "sync 2 documents" but all 30+ documents weren't syncing
  - File: `src/Filament/Client/Resources/ClientSubscriptionResource.php:36-47`

- **Critical Fix: Multiple Subscriptions with Same Item ID**
  - Fixed `DocumentService::identifySubscriptionsInDocument()` to link documents to ALL matching subscriptions, not just the first one
  - Removed `break;` statement that prevented multiple subscriptions from being matched
  - Added Item ID verification when available for more accurate matching
  - Handles cases where multiple subscriptions share the same name and Item ID (e.g., 5 domain subscriptions for same domain)
  - Each subscription now correctly shows its portion of shared documents in pivot table
  - File: `src/Services/DocumentService.php:420-461`

## [V1.7.1] - 2025-11-30

### Fixed
- **Historical Document Sync Enhancement**
  - Extended document sync lookback period from 1 year to 5 years in `DocumentService::syncAllForCustomer()` and `syncForSubscription()`
  - Ensures historical invoices are retrieved for subscriptions created recently but with older billing history
  - Updated `SyncAllDocumentsCommand` default `--days` parameter from 30 to 1825 (5 years)
  - Files: `src/Services/DocumentService.php`, `src/Console/Commands/SyncAllDocumentsCommand.php`

## [V1.4.2] - 2025-11-30

### Added
- **Automatic Subscription Sync from SUMIT API**
  - Added `SubscriptionService::fetchFromSumit()` - Fetch subscriptions from SUMIT API by customer ID
  - Added `SubscriptionService::syncFromSumit()` - Automatically sync SUMIT subscriptions to local database
  - ClientSubscriptionResource now auto-syncs subscriptions on load using `sumit_customer_id`
  - All 9 subscription fields properly mapped from SUMIT API response
  - Metadata stored includes: item ID, SKU, description, quantity, unit price, billing dates
  - Supports all subscription statuses: active, paused, cancelled, expired, pending
  - Files: `src/Services/SubscriptionService.php`, `src/Filament/Client/Resources/ClientSubscriptionResource.php`

### Fixed
- Fixed PHP 8.4 deprecation warning in `Subscription::recordCharge()` - explicitly marked `$recurringId` parameter as nullable
- Fixed polymorphic relationship query in ClientSubscriptionResource to use `subscriber_type` and `subscriber_id`
- File: `src/Models/Subscription.php`

## [V1.4.1] - 2025-11-30

### Fixed
- Fixed Filament v4 namespace issues across all Resources (11 files)
  - **Actions**: Changed `Tables\Actions\ViewAction` to `Actions\ViewAction` (6 Client + 5 Admin Resources)
  - **Layout Components**: Changed `Forms\Components\Section` to `Schemas\Components\Section` (11 Resources)
  - Added missing `use Filament\Schemas;` and `use Filament\Actions;` imports where needed
  - Resolves "Class 'Filament\Tables\Actions\ViewAction' not found" error
  - Resolves potential "Class 'Filament\Forms\Components\Section' not found" errors
  - All Resources now fully compliant with Filament v4 namespace structure
  - Affected files:
    - Client: ClientSubscriptionResource, ClientWebhookEventResource, ClientSumitWebhookResource, ClientTransactionResource, ClientDocumentResource
    - Admin: DocumentResource, SubscriptionResource, TokenResource, TransactionResource, VendorCredentialResource, WebhookEventResource

## [V1.4.0] - 2025-11-30

### Added
- **HasPayableFields Trait** - Automatic Payable interface implementation with dynamic field mapping
  - Enables any Model to become Payable with zero configuration
  - Automatic field mapping from OfficeGuy Settings
  - Smart relationship detection (customer, user, client)
  - Automatic line items extraction from relationships
  - Fallback to config values when mapping not found
  - JSON field support (address, line_items, fees)
  - File: `src/Support/Traits/HasPayableFields.php`

- **3 New Client Panel Resources** - Complete client-facing dashboard
  - **ClientSubscriptionResource** - Customer subscriptions management
    - View active/cancelled/expired subscriptions
    - Next charge date, billing cycle, completed cycles
    - Status badges (active, pending, cancelled, failed, expired, paused)
    - Read-only access (customers cannot edit)
    - Files: `src/Filament/Client/Resources/ClientSubscriptionResource.php` + Pages

  - **ClientWebhookEventResource** - Outgoing webhook logs visibility
    - View webhooks sent to external systems
    - Status tracking (success, pending, failed)
    - Retry count and timestamps
    - Full payload inspection
    - HTTP status codes
    - Files: `src/Filament/Client/Resources/ClientWebhookEventResource.php` + Pages

  - **ClientSumitWebhookResource** - Incoming SUMIT webhooks transparency
    - View webhooks received from SUMIT
    - Signature verification status
    - Event types (transaction.completed, subscription.renewed, etc.)
    - Full payload from SUMIT
    - Processing timestamps
    - Files: `src/Filament/Client/Resources/ClientSumitWebhookResource.php` + Pages

- **Documentation**
  - Added comprehensive integration guide: `docs/INTEGRATION_GUIDE_2025-11-30.md`
  - Added package completeness audit report: `docs/PACKAGE_COMPLETENESS_AUDIT_2025-11-30.md`

### Changed
- Client Panel now features 6 complete resources (was 3):
  - Existing: ClientPaymentMethodResource, ClientTransactionResource, ClientDocumentResource
  - New: ClientSubscriptionResource, ClientWebhookEventResource, ClientSumitWebhookResource
- Improved Hebrew localization across all new resources
- Enhanced navigation grouping in Client Panel ("תשלומים" group)

### Fixed
- None (this is a feature release)

## [V1.0.6] - 2025-11-30

### Changed
- Enhanced PayableMappingsTableWidget to display both settings-based and database mappings
  - Widget now shows field mappings from `officeguy_settings` table (Settings-based)
  - Widget also shows advanced mappings from `payable_field_mappings` table (Database)
  - Added "Source" column with badge to distinguish between "הגדרות" (Settings) and "טבלה" (Database)
  - Settings-based mappings cannot be deleted or toggled (managed via Settings page)
  - Database mappings can be toggled and deleted as before
  - Added auto-refresh every 30 seconds
  - Improved empty state message

## [V1.0.5] - 2025-11-30

### Fixed
- Fixed Filament v4 Actions namespace in PayableMappingsTableWidget
  - Changed `Tables\Actions\Action` to `Actions\Action` (Filament v4 breaking change)
  - Changed `Tables\Actions\BulkAction` to `Actions\BulkAction`
  - Changed `Tables\Actions\DeleteAction` to `Actions\DeleteAction`
  - Changed `Tables\Actions\DeleteBulkAction` to `Actions\DeleteBulkAction`
  - Changed `Tables\Actions\BulkActionGroup` to `Actions\BulkActionGroup`
  - Resolves "Class 'Filament\Tables\Actions\Action' not found" error
  - All table actions now use correct Filament v4 namespace

## [V1.0.4] - 2025-11-30

### Fixed
- Fixed Livewire component registration for PayableMappingsTableWidget
  - Added explicit Livewire component registration in OfficeGuyServiceProvider
  - Widget now properly loads in OfficeGuySettings page footer
  - Resolves "Unable to find component" error when accessing /admin/office-guy-settings
  - Added registerLivewireComponents() method to service provider

## [V1.0.3] - 2025-11-26

### Fixed
- Fixed settings not persisting after save and page refresh
  - Added `getAllSettings()` alias method to `OfficeGuySetting` model
  - `SettingsService::all()` was calling non-existent `getAllSettings()` method
  - Method was actually named `allAsArray()`, causing exception and fallback to config defaults
  - Settings changes now properly save to database and persist across page refreshes

### Changed
- Updated all database migrations for Laravel 12 compatibility
  - Added `Schema::hasTable()` checks before creating tables
  - Added `Schema::hasColumn()` checks before adding columns
  - Added foreign key existence checks before creating constraints
  - Added `declare(strict_types=1)` to migrations that were missing it
  - Migrations can now safely run multiple times without errors
  - Prevents collision errors in existing installations

## [V1.0.2] - 2025-11-26

### Fixed
- Fixed `Class "Filament\Forms\Components\Section" not found` error in Filament v4
  - Updated `OfficeGuySettings.php` to use `Filament\Schemas\Components\Section`
  - Updated `ClientPaymentMethodResource.php` to use `Filament\Schemas\Components\Section`
  - Aligns with Filament v4 namespace reorganization (layout components moved to Schemas namespace)

## [V1.0.1] - 2025-11-26

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
