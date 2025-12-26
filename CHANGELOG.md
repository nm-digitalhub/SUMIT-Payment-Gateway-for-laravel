# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [v1.20.0] - 2025-12-26

### Added
- **Transaction Linking System** - Bidirectional relationships between charge and refund transactions
  - Added `refund_transaction_id` field to link charges to their refund transactions
  - Added `parentTransaction()` relationship - refund → original charge
  - Added `refundTransaction()` relationship - charge → refund transaction
  - Added `childRefunds()` relationship - charge → all partial refunds
  - Added helper methods: `isRefund()`, `isCharge()`, `hasBeenRefunded()`
  - Added smart `getPaymentToken()` method - handles multiple token storage formats
  - Migration: `2025_12_26_000001_add_transaction_linking_fields_to_officeguy_transactions.php`
  - File: `src/Models/OfficeGuyTransaction.php`

### Changed
- **PaymentService::processRefund()** - Now creates new transaction record for refunds
  - Previously: Only updated original transaction status to 'refunded'
  - Now: Creates new `OfficeGuyTransaction` record with `transaction_type = 'refund'`
  - Links refund to original charge via `parent_transaction_id` and `refund_transaction_id`
  - Returns full refund record in response array
  - Enables transaction history tracking and reporting
  - File: `src/Services/PaymentService.php:518-605`

### Enhanced
- **TransactionResource (Filament)** - Added transaction relationship columns
  - New column: `transaction_type` with Hebrew badges (חיוב/זיכוי/בוטל)
  - New column: `parent_transaction_id` - clickable link to original charge (visible on refunds)
  - New column: `refund_transaction_id` - clickable link to refund (visible on refunded charges)
  - New column: `payment_token` - searchable, copyable, hidden by default
  - Badge colors: charge=success, refund=warning, void=danger
  - File: `src/Filament/Resources/TransactionResource.php`

- **ViewTransaction Page (Filament)** - Refund action now shows clickable link to refund transaction
  - After successful refund, notification includes "צפה בעסקת הזיכוי" button
  - Links to newly created refund transaction record
  - Original transaction automatically refreshed to show updated status
  - Removed manual status update (PaymentService handles it)
  - File: `src/Filament/Resources/TransactionResource/Pages/ViewTransaction.php:119-226`

### Fixed
- **MySQL Index Name Length** - Fixed 64-character limit violations
  - `vendor_credentials` table: Index renamed to `vendor_active_idx`
  - `subscriptions` table: Indexes renamed to `subs_status_next_idx`, `subs_subscriber_idx`
  - `officeguy_transactions` table: Foreign key renamed to `officeguy_transactions_parent_fk`
  - Prevents "Identifier name too long" errors on MySQL 5.7+
  - Files:
    - `database/migrations/2025_01_01_000005_create_vendor_credentials_table.php`
    - `database/migrations/2025_01_01_000006_create_subscriptions_table.php`
    - `database/migrations/2025_01_01_000007_add_donation_and_vendor_fields.php`

### Database Schema
- Added indexes for efficient querying:
  - `idx_transaction_type` on `officeguy_transactions.transaction_type`
  - `idx_payment_token` on `officeguy_transactions.payment_token`
- Foreign key constraints for referential integrity:
  - `refund_transaction_id` → `officeguy_transactions.id` (ON DELETE SET NULL)
  - `parent_transaction_id` → `officeguy_transactions.id` (ON DELETE SET NULL)

### Benefits
- ✅ Full transaction history tracking (charge → refund → partial refunds)
- ✅ Easy navigation between related transactions in Filament UI
- ✅ Accurate reporting and reconciliation
- ✅ Support for multiple partial refunds on same charge
- ✅ Payment token tracking across transaction lifecycle
- ✅ Backward compatible - existing transactions unaffected

## [v1.19.0] - 2025-12-XX

### Added
- **HasEloquentLineItems Trait**
  - New adaptive trait for integrating Eloquent line item relationships with SUMIT payment gateway
  - Bridges between Eloquent relationships and SUMIT API format without coupling to specific models
  - Does NOT assume table names, model names, or field structures
  - Supports multiple naming conventions:
    - `price_unit` or `unit_price` → SUMIT `unit_price`
    - `package_id` or `product_id` → SUMIT `product_id`
    - `metadata.sku` or direct `sku` field → SUMIT `sku`
  - Falls back to HasPayableFields default if no Eloquent items exist (backward compatible)
  - Usage: Override `getEloquentLineItems()` to return your specific relationship
  - Example: Order model uses `lines()` relationship (OrderLine models) → SUMIT line items
  - File: `src/Support/Traits/HasEloquentLineItems.php`
  - Benefit: Detailed SUMIT invoices with itemized breakdown instead of generic "Payment × 1"
  - Zero breaking changes: Empty relationships fall back gracefully

## [v1.18.0] - 2025-12-21

### Added
- **HasSumitPaymentOperations Trait**
  - New trait providing SUMIT payment-related helper methods for Payable models
  - Includes relationships: `officeGuyTransaction()`, `officeGuyDocument()`, `officeGuyToken()`
  - Document methods: `getDocumentUrl()`, `hasInvoiceDocument()`
  - Payment card methods: `getPaymentLast4()`, `getPaymentBrand()`, `getTransactionReference()`
  - Operation detection: `getPaymentOperation()` returns 'ChargeAndCreateToken' or 'Charge'
  - Token creation logic: `createsPaymentToken()` (override in model for custom logic)
  - Helper: `isOneTimePayment()` (inverse of createsPaymentToken)
  - File: `src/Support/Traits/HasSumitPaymentOperations.php`
  - Usage: `use HasSumitPaymentOperations;` in any Payable model
  - Reduces code duplication across models needing SUMIT payment helpers
  - Fully documented with PHPDoc blocks and usage examples

### Fixed
- **Customer Duplication Prevention** (v1.1.7)
  - Fixed SUMIT creating duplicate customers despite `merge_customers = true` setting
  - Root cause: `ExternalIdentifier` was sending `client_id` instead of `sumit_customer_id`
  - Solution: Send `Customer['ID']` directly for existing customers (WooCommerce plugin pattern)
  - For new customers: Send full Customer object with `SearchMode: 'Automatic'`
  - File: `src/Services/PaymentService.php:453-487`
  - Before: `ExternalIdentifier = 7 (client_id)` → SUMIT couldn't match → Created duplicate
  - After: `Customer['ID'] = 1095061474 (sumit_customer_id)` → SUMIT uses existing customer
  - Tested with tinker: Both SearchMode and ID-only approaches work correctly
  - See: `docs/CUSTOMER_DUPLICATION_FIX_2025-12-18.md` for detailed flow diagrams

- **Bit Webhook Confirmation** (v1.1.7)
  - BitWebhookController now marks transactions as webhook-confirmed
  - Supports Secure Success Flow Architecture (prevents race conditions)
  - Adds three fields to transaction after successful webhook:
    - `is_webhook_confirmed = true` (gatekeeper - only webhook can confirm)
    - `confirmed_at = now()` (timestamp)
    - `confirmed_by = 'webhook'` (source tracking)
  - File: `src/Http/Controllers/BitWebhookController.php:79-99`
  - Required DB fields: migration `2025_12_18_012221_add_secure_success_flow_fields.php`
  - Success page can now check `is_webhook_confirmed` to show confirmed vs pending state

### Added
- **SUMIT Customer History URL** (v1.1.7)
  - Added `sumit_history_url` field to clients table
  - Stores direct link to SUMIT customer portal with payment history
  - Portal contains: active subscriptions, invoice history (30+ docs), payment history (157 txns)
  - Migration: `2025_12_18_034425_add_sumit_history_url_to_clients_table.php`
  - Model: `app/Models/Client.php` (added to fillable)
  - Future potential: automated reconciliation, subscription tracking, payment monitoring

### Changed
- **BREAKING: Token ownership migration from User to Client** (v1.2.0)
  - `OfficeGuyToken` now uses Client as owner instead of User (business entity)
  - Client is the correct owner because it has `sumit_customer_id` and implements `HasSumitCustomer`
  - Migration required: `php artisan migrate` (migrates existing tokens from User to Client)
  - Supports B2B scenarios (multiple Users per Client)
  - Files changed:
    - `src/Filament/Client/Resources/ClientDocumentResource.php` - Uses Client ownership
    - `src/Filament/Client/Resources/ClientTransactionResource.php` - Uses Client ownership
    - `src/Filament/Client/Resources/ClientSubscriptionResource.php` - Uses Client for sync
    - `src/Filament/Client/Resources/ClientWebhookEventResource.php` - Uses Client for filtering
    - `src/Filament/Client/Resources/ClientPaymentMethodResource/Pages/CreateClientPaymentMethod.php` - Passes Client to TokenService
    - `src/Services/TokenService.php` - `syncTokenFromSumit()` now expects Client owner
    - `src/Http/Controllers/PublicCheckoutController.php` - `getSavedTokens()`, `saveCardToken()` use Client
    - `resources/views/pages/checkout.blade.php` - Updated Alpine.js function name to `checkoutForm()`

### Added
- Synced all OfficeGuy views/partials/filament files from main app (`httpdocs/vendor/officeguy/laravel-sumit-gateway`).
- Added branded `resources/css/checkout-mobile.css` (checkout CSS was not present in the package before).

### Fixed
- **Checkout form submission with saved tokens** - Fixed infinite loop preventing form submission
  - Changed `form.submit()` to `HTMLFormElement.prototype.submit.call(form)` to bypass event listeners
  - Affects both saved token usage and new card with SUMIT SDK token generation
  - `resources/views/pages/checkout.blade.php` (lines 1277, 1318)
- Client Panel Resources now correctly filter by Client instead of User
- Token sync operations now work correctly with Client ownership
- Public checkout now correctly loads saved tokens for Client (not User)

## [v1.15.0] - 2025-12-07

### Added
- **Email Existence Check During Checkout**
  - Real-time email validation to prevent duplicate user accounts
  - Blocks checkout for existing users and redirects to login
  - New API endpoint: `POST /officeguy/api/check-email`
  - Case-insensitive email lookup with 15-second timeout
  - Rate limiting: 10 requests/minute to prevent abuse
  - CSRF exempt (read-only, safe endpoint)
  - Multi-language support (Hebrew, English, French)
  - Fail-safe design: continues checkout on error
  - Files:
    - `src/Http/Controllers/Api/CheckEmailController.php` (NEW)
    - `routes/officeguy.php` (added check-email route)
    - `resources/views/pages/checkout.blade.php` (Alpine.js + UI)
    - `docs/EMAIL_USER_CHECK_SPEC.md` (specification)
  - UI Features:
    - Warning message with login button for existing users
    - Loading spinner during email verification
    - Disabled pay button when user must login
    - @blur event triggers automatic check
  - Testing:
    - Backend validated with PHP test script ✅
    - Frontend validated with Puppeteer browser automation ✅

### Added (Previous)
- **Automatic Guest User Creation (v1.14.0)**
  - New `AutoCreateUserListener` automatically creates user accounts for guest purchasers after successful payment
  - Listens to `PaymentCompleted` event and creates User + Client records
  - Generates 12-character temporary password with configurable expiry (default: 7 days)
  - Sends welcome email with login credentials and order details
  - Gracefully handles existing users (links order without creating duplicate)
  - Configurable via Admin Panel or .env:
    - `OFFICEGUY_AUTO_CREATE_GUEST_USER` (default: true)
    - `OFFICEGUY_GUEST_PASSWORD_EXPIRY_DAYS` (default: 7)
  - Files:
    - `src/Listeners/AutoCreateUserListener.php` (260 lines)
    - `app/Mail/GuestWelcomeWithPasswordMail.php` (95 lines)
    - `resources/views/emails/guest-welcome-with-password.blade.php` (364 lines)
  - Registered in: `src/OfficeGuyServiceProvider.php:128-133`
  - Config: `config/officeguy.php:108-123`

## [V1.8.3] - 2025-12-01

### Fixed
- **CRITICAL: Document Email Sending (sendByEmail)**
  - Fixed "Document not found" error when sending documents via email
  - Root cause: SUMIT API requires `DocumentType` + `DocumentNumber`, NOT `DocumentID`
  - Changed `DocumentService::sendByEmail()` signature to accept `OfficeGuyDocument` model or int
  - When int provided (legacy), fetches document model from database
  - API now sends: `DocumentType: 1, DocumentNumber: 40026` instead of `DocumentID: 1164461665`
  - File: `src/Services/DocumentService.php:924-1009`

- **Document Download - Direct SUMIT URL**
  - Changed "Download PDF" button to use direct SUMIT signed URL instead of internal route
  - Removed unnecessary redirect through local controller
  - Faster download experience - direct to PDF
  - File: `src/Filament/Resources/DocumentResource.php:165-171`

- **Route URL Double Encoding Bug**
  - Fixed double-encoded route URLs (e.g., `%22officeguy%22/%22documents%5C/28%22`)
  - Root cause: `RouteConfig::getSetting()` used `DB::table()` instead of Model's `get()`
  - Without Model, JSON cast wasn't applied, resulting in double-encoded values
  - Changed to use `OfficeGuySetting::get()` to ensure proper JSON decoding
  - File: `src/Support/RouteConfig.php:141-158`

- **Document Download Controller - Route Model Binding**
  - Switched to Laravel's route model binding for cleaner code
  - Changed from manual `where('document_id')` query to automatic model injection
  - Fixed redirect to use correct column: `document_download_url` instead of `download_url`
  - File: `src/Http/Controllers/DocumentDownloadController.php:16-44`

### Changed
- **Email Address Now Optional in DocumentResource**
  - Email field in "Resend Email" action is now optional
  - If left empty, SUMIT sends to customer's registered email automatically
  - If provided, SUMIT sends to custom email address
  - Helper text: "Leave empty to send to customer's registered email in SUMIT"
  - File: `src/Filament/Resources/DocumentResource.php:172-213`

- **DocumentService::sendByEmail() Enhanced**
  - Added `$personalMessage` parameter for custom email message
  - Added `$original` parameter (default: true) to send original document
  - Email parameter is now nullable: `?string $email = null`
  - When email is null, SUMIT uses customer's registered email from their profile
  - Backward compatible: accepts int (document_id) or OfficeGuyDocument model
  - File: `src/Services/DocumentService.php:924-1009`

### Impact
- ✅ Document email sending now works correctly
- ✅ Customers receive emails at their registered SUMIT email address by default
- ✅ Optional override to send to custom email address
- ✅ Direct PDF downloads (faster, no redirect)
- ✅ All route-based features work correctly (no more URL encoding issues)
- ✅ Backward compatible with existing code using int document_id

## [V1.8.2] - 2025-12-01

### Fixed
- **N+1 Query Optimization in getEditableSettings()**
  - Fixed second N+1 query pattern where `getEditableSettings()` called `get()` 74 times individually
  - Each `get()` call made 2 database queries (`has()` + `get()`) = 148 total queries
  - Optimized to fetch all database settings at once using `getAllSettings()` (1 query)
  - Performance improvement: **148 queries → 1 query** per page load
  - Combined with v1.8.1 fix: **222 queries → 2 queries** (111x improvement!)
  - File: `src/Services/SettingsService.php:273-301`

## [V1.8.1] - 2025-12-01

### Fixed
- **N+1 Query Performance Issue in SettingsService**
  - Fixed critical N+1 query bug where `Schema::hasTable('officeguy_settings')` was called 74 times per page load
  - Added static cache to `tableExists()` method to prevent repeated `information_schema.tables` queries
  - Resolves Sentry issue: JAVASCRIPT-REACT-T (nm-digitalhub/nm-digitalhubweb#79)
  - Performance improvement: **74 queries → 1 query** per request
  - Affects: `/admin/office-guy-settings` page load time
  - File: `src/Services/SettingsService.php:26-48`

## [V1.8.0] - 2025-12-01

### Added
- **Universal Invoice/Document Interface (Invoiceable)**
  - New `Invoiceable` interface with 10 methods for standardized invoice operations
  - `HasSumitInvoice` trait providing default implementations (138 lines)
  - Smart `getClient()` method supporting multiple relationship patterns
  - Hebrew document type names (חשבונית מס, תעודת זיכוי, etc.)
  - Currency support (ILS/USD/EUR/GBP)

- **Invoice Settings Service**
  - New `InvoiceSettingsService` with 3-layer configuration priority
  - Priority: Database (Admin Panel) → App\Settings → Config defaults
  - Settings: default currency, tax rate, payment due days
  - Added 3 settings to Admin Panel (Office Guy Settings → Document Settings)
  - Config defaults: `invoice_currency_code`, `invoice_tax_rate`, `invoice_due_days`

- **InvoicesRelationManager** (Universal Relation Manager)
  - Works with any model implementing `Invoiceable` interface (370 lines)
  - Real-time document fetching from SUMIT API
  - Auto-sync to local database for caching
  - 5 table columns: Invoice Number, Type, Date, Amount, Status
  - 4 actions: View PDF, Pay, Send Email, Create Credit/Refund
  - Dual credit mode: Credit Note (document only) vs Direct Refund (money to card)

- **Debt Service Integration**
  - New `DebtService` for customer balance/debt tracking from SUMIT
  - Customer debt history retrieval
  - Balance tracking and document fetching
  - Integration with credit/refund workflows

- **Customer Interface (HasSumitCustomer)**
  - New `HasSumitCustomer` contract for customer models
  - `HasSumitCustomerTrait` with default implementations
  - Methods: `getSumitCustomerId()`, `getSumitCustomerEmail()`, `getSumitCustomerName()`

### Enhanced
- **DocumentDownloadController Authorization**
  - Enhanced from 24 lines → 119 lines
  - Added authentication requirement
  - Document ownership verification via `documentable` relationship
  - Admin bypass support (`isAdmin()` or `hasRole('admin')`)
  - Smart owner detection across multiple relationship types
  - Fallback to SUMIT download URL if no local file
  - Detailed error messages (403, 404)

### Documentation
- Added comprehensive implementation guide (`docs/INVOICE_INTEGRATION_COMPLETE.md` - 600+ lines)
- Added debt service integration documentation
- Updated configuration documentation with 3-layer priority system
- Migration guide for existing applications

### Breaking Changes
- None - 100% backward compatible

### Migration Notes
- Existing Invoice models should implement `Invoiceable` interface
- Use `HasSumitInvoice` trait for default implementations
- Customer models should implement `HasSumitCustomer` for debt tracking
- Settings can now be configured via Admin Panel (Database priority)

### Files Added
- `src/Contracts/Invoiceable.php`
- `src/Contracts/HasSumitCustomer.php`
- `src/Support/Traits/HasSumitInvoice.php`
- `src/Support/Traits/HasSumitCustomerTrait.php`
- `src/Services/InvoiceSettingsService.php`
- `src/Services/DebtService.php`
- `src/Filament/RelationManagers/InvoicesRelationManager.php`
- `docs/DEBT_SERVICE_IMPLEMENTATION_SUMMARY.md`
- `docs/DEBT_SERVICE_INTEGRATION_PLAN.md`

### Files Modified
- `config/officeguy.php` - Added invoice settings defaults
- `src/Filament/Pages/OfficeGuySettings.php` - Added 3 invoice setting fields
- `src/Http/Controllers/DocumentDownloadController.php` - Enhanced authorization
- `src/Services/DocumentService.php` - Integration updates
- `src/Services/PaymentService.php` - Integration updates

**Total Changes**: ~2,846 insertions across 14 files

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
