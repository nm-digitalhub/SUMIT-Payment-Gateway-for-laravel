# SUMIT Payment Gateway for Laravel - Claude Code Development Guide

> **Critical**: Read this ENTIRE file before starting any task. This is your single source of truth for package development.

## 🎯 Package Overview

**Official Laravel package** for SUMIT payment gateway integration with Filament v4 admin panels.

- **Package Name**: `officeguy/laravel-sumit-gateway`
- **Version**: v1.1.6 (2025-11-26)
- **License**: MIT
- **Ownership**: NM-DigitalHub (https://github.com/nm-digitalhub/SUMIT-Payment-Gateway-for-laravel)
- **Origin**: 1:1 port from WooCommerce plugin `woo-payment-gateway-officeguy`

**Key Features**:
- Credit card payments (3 PCI modes: no/redirect/yes)
- Bit payment integration
- Token management (J2/J5) for saved payment methods
- Authorize-only and installment payments (up to 36)
- Document generation (invoices/receipts/donations)
- Subscription/recurring billing support
- Multi-vendor support
- Webhook handling (incoming + outgoing)
- Stock synchronization capabilities
- Full Filament v4 integration (Admin + Client panels)

## 🔧 Tech Stack

- **PHP**: ^8.2
- **Laravel**: ^12.0
- **Filament**: ^4.0
- **Guzzle**: ^7.0
- **Testing**: PHPUnit ^11.0|^12.0, Orchestra Testbench ^10.0

## 🏗️ Package Structure

### Complete Directory Tree

```
SUMIT-Payment-Gateway-for-laravel/
├── checkout-branded-extracted/      # Branded checkout assets
├── config/
│   └── officeguy.php               # Configuration file (74 settings)
├── database/
│   └── migrations/                 # Database migrations
│       ├── 2024_01_01_create_officeguy_transactions_table.php
│       ├── 2024_01_02_create_officeguy_tokens_table.php
│       ├── 2024_01_03_create_officeguy_documents_table.php
│       ├── 2024_01_04_create_officeguy_settings_table.php
│       ├── 2024_01_05_create_vendor_credentials_table.php
│       ├── 2024_01_06_create_subscriptions_table.php
│       ├── 2024_01_07_create_webhook_events_table.php
│       ├── 2024_01_08_create_sumit_webhooks_table.php
│       └── 2024_01_09_add_donation_and_vendor_fields.php
├── docs/                           # Additional documentation
│   ├── CHECKOUT_COMPLETE_FLOW_ANALYSIS.md
│   ├── CLIENT_LOCALE_FIX_2025-12-07.md
│   ├── CLIENT_PACKAGE_ARCHITECTURE_ANALYSIS.md
│   ├── CLIENT_PANEL_INTEGRATION.md
│   ├── CLIENT_PANEL_REQUIREMENTS.md
│   ├── CLIENT_SUMMARY.md
│   ├── COMPLETE_CHECKOUT_ANALYSIS.md
│   ├── CRM_INTEGRATION.md
│   ├── DIGITAL_PRODUCT_FULFILLMENT.md
│   ├── DOKAN_WOOCOMMERCE_INTEGRATION.md
│   ├── INFRASTRUCTURE_FULFILLMENT.md
│   ├── INVOICE_SETTINGS_INTEGRATION.md
│   ├── LANGUAGE_SWITCHING_ANALYSIS.md
│   ├── LOCALE_FIX_FINAL_2025-12-07.md
│   ├── LOCALE_FLOW_ANALYSIS.md
│   ├── PACKAGE_COMPLETENESS_AUDIT_2025-11-30.md
│   ├── PAYABLE_FIELD_MAPPING_WIZARD.md
│   ├── PHASE2_INTEGRATION_PLAN.md
│   ├── SUBSCRIPTION_INVOICES_SPECIFICATION.md
│   ├── WEBHOOK_SYSTEM.md
│   ├── architecture.md
│   ├── mapping.md
│   └── sumit-package-architecture-guide.md
├── resources/
│   ├── css/
│   │   └── checkout-mobile.css     # Mobile-responsive checkout styles
│   ├── js/
│   │   └── officeguy-alpine-rtl.js # Alpine.js RTL support
│   ├── lang/                       # Translations (Hebrew/English/French)
│   │   ├── en/
│   │   │   └── officeguy.php
│   │   ├── he/
│   │   │   └── officeguy.php
│   │   └── lang/
│   │       ├── en.json
│   │       ├── fr.json
│   │       └── he.json
│   └── views/                      # Blade templates
│       ├── components/             # Reusable components
│       │   ├── error-card.blade.php
│       │   ├── mapping-details.blade.php
│       │   ├── mapping-review.blade.php
│       │   ├── model-info.blade.php
│       │   ├── payment-form.blade.php
│       │   └── success-card.blade.php
│       ├── errors/
│       │   └── access-denied.blade.php
│       ├── filament/               # Filament admin views
│       │   ├── client/
│       │   ├── pages/
│       │   └── resources/
│       ├── pages/                  # Public pages
│       │   ├── partials/
│       │   ├── checkout.blade.php  # Public checkout page
│       │   ├── digital.blade.php   # Digital product page
│       │   ├── infrastructure.blade.php
│       │   └── subscription.blade.php
│       └── success.blade.php       # Payment success page
├── routes/
│   └── officeguy.php               # Package routes (7 routes)
├── scripts/                        # Utility scripts
│   ├── add-missing-translations.php
│   ├── final-translations.php
│   └── translate-settings-page.php
├── src/                            # Main source code
│   ├── Actions/
│   │   └── PrepareCheckoutIntentAction.php
│   ├── BackoffStrategy/
│   │   ├── BackoffStrategyInterface.php
│   │   └── ExponentialBackoffStrategy.php
│   ├── Console/Commands/           # Artisan commands
│   │   ├── CrmSyncFoldersCommand.php
│   │   ├── CrmSyncViewsCommand.php
│   │   ├── ProcessRecurringPaymentsCommand.php
│   │   ├── StockSyncCommand.php
│   │   └── SyncAllDocumentsCommand.php
│   ├── Contracts/                  # Interfaces
│   │   ├── HasSumitCustomer.php
│   │   ├── Invoiceable.php
│   │   └── Payable.php             # Core Payable interface
│   ├── DTOs/
│   │   └── ValidationResult.php
│   ├── DataTransferObjects/
│   │   ├── AddressData.php
│   │   ├── CheckoutIntent.php
│   │   ├── CustomerData.php
│   │   └── PaymentPreferences.php
│   ├── Enums/                      # Enumerations
│   │   ├── Environment.php
│   │   ├── PayableType.php
│   │   ├── PaymentStatus.php
│   │   └── PciMode.php
│   ├── Events/                     # Event classes (19 events)
│   │   ├── BitPaymentCompleted.php
│   │   ├── DocumentCreated.php
│   │   ├── FinalWebhookCallFailedEvent.php
│   │   ├── MultiVendorPaymentCompleted.php
│   │   ├── MultiVendorPaymentFailed.php
│   │   ├── PaymentCompleted.php
│   │   ├── PaymentFailed.php
│   │   ├── StockSynced.php
│   │   ├── SubscriptionCancelled.php
│   │   ├── SubscriptionCharged.php
│   │   ├── SubscriptionChargesFailed.php
│   │   ├── SubscriptionCreated.php
│   │   ├── SuccessPageAccessed.php
│   │   ├── SumitWebhookReceived.php
│   │   ├── UpsellPaymentCompleted.php
│   │   ├── UpsellPaymentFailed.php
│   │   ├── WebhookCallFailedEvent.php
│   │   └── WebhookCallSucceededEvent.php
│   ├── Filament/                   # Filament integration
│   │   ├── Actions/
│   │   │   └── CreatePayableMappingAction.php
│   │   ├── Client/                 # Client panel (6 resources)
│   │   │   ├── Pages/
│   │   │   ├── Resources/
│   │   │   │   ├── ClientDocumentResource/
│   │   │   │   ├── ClientPaymentMethodResource/
│   │   │   │   ├── ClientSubscriptionResource/
│   │   │   │   ├── ClientSumitWebhookResource/
│   │   │   │   ├── ClientTransactionResource/
│   │   │   │   └── ClientWebhookEventResource/
│   │   │   ├── Widgets/
│   │   │   └── ClientPanelProvider.php
│   │   ├── Clusters/
│   │   │   ├── SumitClient.php
│   │   │   └── SumitGateway.php
│   │   ├── Pages/
│   │   │   └── OfficeGuySettings.php  # Settings page (74 settings)
│   │   ├── RelationManagers/
│   │   │   └── InvoicesRelationManager.php
│   │   ├── Resources/              # Admin resources (7 resources)
│   │   │   ├── CrmActivities/
│   │   │   ├── CrmEntities/
│   │   │   ├── CrmFolders/
│   │   │   ├── DocumentResource/
│   │   │   ├── SubscriptionResource/
│   │   │   ├── SumitWebhookResource/
│   │   │   ├── TokenResource/
│   │   │   ├── TransactionResource/
│   │   │   ├── VendorCredentialResource/
│   │   │   ├── WebhookEventResource/
│   │   │   ├── DocumentResource.php
│   │   │   ├── SubscriptionResource.php
│   │   │   ├── SumitWebhookResource.php
│   │   │   ├── TokenResource.php
│   │   │   ├── TransactionResource.php
│   │   │   ├── VendorCredentialResource.php
│   │   │   └── WebhookEventResource.php
│   │   └── Widgets/
│   │       └── PayableMappingsTableWidget.php
│   ├── Handlers/                   # Fulfillment handlers
│   │   ├── DigitalProductFulfillmentHandler.php
│   │   ├── InfrastructureFulfillmentHandler.php
│   │   └── SubscriptionFulfillmentHandler.php
│   ├── Http/
│   │   ├── Controllers/            # Webhook & callback controllers
│   │   │   ├── Api/
│   │   │   ├── BitWebhookController.php
│   │   │   ├── CardCallbackController.php
│   │   │   ├── CheckoutController.php
│   │   │   ├── CrmWebhookController.php
│   │   │   ├── DocumentDownloadController.php
│   │   │   ├── PublicCheckoutController.php
│   │   │   ├── SecureSuccessController.php
│   │   │   └── SumitWebhookController.php
│   │   ├── Middleware/
│   │   │   ├── OptionalAuth.php
│   │   │   └── SetPackageLocale.php
│   │   └── Requests/
│   │       ├── BitRedirectRequest.php
│   │       ├── BitWebhookRequest.php
│   │       └── CheckoutRequest.php
│   ├── Jobs/                       # Queue jobs (7 jobs)
│   │   ├── CheckSumitDebtJob.php
│   │   ├── ProcessRecurringPaymentsJob.php
│   │   ├── ProcessSumitWebhookJob.php
│   │   ├── SendWebhookJob.php
│   │   ├── StockSyncJob.php
│   │   ├── SyncCrmFromWebhookJob.php
│   │   └── SyncDocumentsJob.php
│   ├── Listeners/                  # Event listeners (6 listeners)
│   │   ├── AutoCreateUserListener.php
│   │   ├── CrmActivitySyncListener.php
│   │   ├── CustomerSyncListener.php
│   │   ├── DocumentSyncListener.php
│   │   ├── FulfillmentListener.php
│   │   └── WebhookEventListener.php
│   ├── Models/                     # Eloquent models (19 models)
│   │   ├── CrmActivity.php
│   │   ├── CrmEntity.php
│   │   ├── CrmEntityField.php
│   │   ├── CrmEntityRelation.php
│   │   ├── CrmFolder.php
│   │   ├── CrmFolderField.php
│   │   ├── CrmView.php
│   │   ├── OfficeGuyDocument.php
│   │   ├── OfficeGuySetting.php
│   │   ├── OfficeGuyToken.php
│   │   ├── OfficeGuyTransaction.php
│   │   ├── OrderSuccessAccessLog.php
│   │   ├── OrderSuccessToken.php
│   │   ├── PayableFieldMapping.php
│   │   ├── PendingCheckout.php
│   │   ├── Subscription.php
│   │   ├── SumitWebhook.php
│   │   ├── VendorCredential.php
│   │   └── WebhookEvent.php
│   ├── Policies/
│   │   └── OfficeGuyTransactionPolicy.php
│   ├── Services/                   # Service classes (27 services)
│   │   ├── Stock/
│   │   │   └── StockService.php
│   │   ├── BitPaymentService.php
│   │   ├── CheckoutViewResolver.php
│   │   ├── CrmDataService.php
│   │   ├── CrmSchemaService.php
│   │   ├── CrmViewService.php
│   │   ├── CustomerMergeService.php
│   │   ├── CustomerService.php
│   │   ├── DebtService.php
│   │   ├── DocumentService.php
│   │   ├── DonationService.php
│   │   ├── ExchangeRateService.php
│   │   ├── FulfillmentDispatcher.php
│   │   ├── InvoiceSettingsService.php
│   │   ├── MultiVendorPaymentService.php
│   │   ├── OfficeGuyApi.php        # HTTP Client
│   │   ├── PayableMappingService.php
│   │   ├── PaymentService.php      # Core payment processing
│   │   ├── SecureSuccessUrlGenerator.php
│   │   ├── ServiceDataFactory.php
│   │   ├── SettingsService.php     # Configuration management
│   │   ├── SubscriptionService.php
│   │   ├── SuccessAccessValidator.php
│   │   ├── TemporaryStorageService.php
│   │   ├── TokenService.php        # Token management
│   │   ├── UpsellService.php
│   │   └── WebhookService.php
│   ├── Support/                    # Helper traits & classes
│   │   ├── Traits/
│   │   │   ├── HasCheckoutTheme.php
│   │   │   ├── HasPayableFields.php
│   │   │   ├── HasPayableType.php
│   │   │   ├── HasSumitCustomerTrait.php
│   │   │   ├── HasSumitInvoice.php
│   │   │   ├── HasSumitPaymentOperations.php
│   │   │   └── PayableAdapter.php
│   │   ├── DynamicPayableWrapper.php
│   │   ├── ModelPayableWrapper.php
│   │   ├── OrderResolver.php
│   │   ├── RequestHelpers.php
│   │   └── RouteConfig.php
│   ├── View/Components/            # Blade components
│   │   └── PaymentForm.php
│   ├── OfficeGuyServiceProvider.php # Main service provider
│   └── WebhookCall.php
├── temp_logo/                      # Logo assets
├── woo-plugin/                     # Original WooCommerce plugin (reference)
│   └── woo-payment-gateway-officeguy/
│       ├── includes/
│       │   ├── OfficeGuyAPI.php
│       │   ├── OfficeGuyCartFlow.php
│       │   ├── OfficeGuyDokanMarketplace.php
│       │   ├── OfficeGuyDonation.php
│       │   ├── OfficeGuyMultiVendor.php
│       │   ├── OfficeGuyPayment.php
│       │   ├── OfficeGuyPluginSetup.php
│       │   ├── OfficeGuyRequestHelpers.php
│       │   ├── OfficeGuySettings.php
│       │   ├── OfficeGuyStock.php
│       │   ├── OfficeGuySubscriptions.php
│       │   └── OfficeGuyTokens.php
│       ├── languages/
│       ├── templates/
│       └── officeguy-woo.php
├── CHANGELOG.md                    # Version history
├── CHECKOUT_MODULAR_SPEC.md
├── CLAUDE.md                       # Development guide (this file)
├── DOCUMENT_AUTO_SYNC.md
├── FILAMENT_IMPLEMENTATION.md
├── FILAMENT_V4_UPGRADE_SUMMARY.md  # Filament v3→v4 migration guide
├── FIXES_APPLIED.md
├── IMPLEMENTATION_LOG.md
├── LICENSE.md                      # MIT License
├── LOGO_REPLACEMENT_SPEC.md
├── PAYABLE_MAPPING_SUMMARY.md
├── README.md                       # Full Hebrew documentation
├── UPGRADE.md                      # Upgrade instructions
├── UPGRADE_SUMMARY.txt
├── composer.json                   # Package dependencies
├── fix-filament-v4-namespaces.sh
├── phase1-foundation-files.tar.gz
└── sumit-openapi.json              # SUMIT API specification

Key Statistics:
- 19 Eloquent Models
- 27 Service Classes
- 7 Admin Filament Resources
- 6 Client Panel Resources
- 19 Event Classes
- 7 Queue Jobs
- 6 Event Listeners
- 9 Database Migrations
- 74 Configuration Settings
```

## 📊 Core Models

### Database Tables

```
officeguy_transactions    # Payment transactions
officeguy_tokens          # Saved payment methods
officeguy_documents       # Generated invoices/receipts
officeguy_settings        # Database-stored settings (74 keys)
vendor_credentials        # Multi-vendor credentials
subscriptions             # Recurring billing
webhook_events            # Outgoing webhook logs
sumit_incoming_webhooks   # Incoming SUMIT webhooks
```

### Key Models

```php
App\Models\
├── OfficeGuyTransaction  # Payment records
├── OfficeGuyToken        # Saved payment methods
├── OfficeGuyDocument     # Generated documents
├── OfficeGuySetting      # DB settings (HIGHEST PRIORITY)
├── VendorCredential      # Multi-vendor creds
├── Subscription          # Recurring billing
├── WebhookEvent          # Outgoing webhooks
└── SumitWebhook          # Incoming webhooks
```

## ⚙️ Configuration System - CRITICAL

### 3-Layer Priority System

**Configuration is loaded in this priority order**:

1. **Database** (`officeguy_settings` table) - **HIGHEST PRIORITY** ✅
2. **Config file** (`config/officeguy.php`) - Middle layer
3. **.env variables** - Fallback defaults only

### How It Works

```php
// When you call:
config('officeguy.company_id')

// The system resolves it in this order:

// 1. Database first (if table exists)
SELECT value FROM officeguy_settings WHERE key = 'company_id';
// Returns: "1082100759" ← USED IF EXISTS

// 2. Config file second
return config/officeguy.php → 'company_id' => env('OFFICEGUY_COMPANY_ID', '')
// Returns: env value or empty string

// 3. .env third (only if not in DB or config)
OFFICEGUY_COMPANY_ID=1082100759
```

### Loading Mechanism

**File**: `src/OfficeGuyServiceProvider.php:95-114`

```php
protected function loadDatabaseSettings(): void
{
    try {
        if (!\Illuminate\Support\Facades\Schema::hasTable('officeguy_settings')) {
            return; // Table doesn't exist yet, use config defaults
        }

        $dbSettings = \OfficeGuy\LaravelSumitGateway\Models\OfficeGuySetting::getAllSettings();

        // Override config with database values
        foreach ($dbSettings as $key => $value) {
            config(["officeguy.{$key}" => $value]);  // ← Database wins!
        }
    } catch (\Exception $e) {
        // Silently fail - config defaults will be used
    }
}

// Called in boot():
public function boot(): void
{
    $this->loadDatabaseSettings();  // ← Runs BEFORE anything else
    // ... rest of boot logic
}
```

### SettingsService (Abstraction Layer)

**File**: `src/Services/SettingsService.php:42-57`

```php
public function get(string $key, mixed $default = null): mixed
{
    // 1. Try database first (if table exists)
    if ($this->tableExists()) {
        try {
            if (OfficeGuySetting::has($key)) {
                return OfficeGuySetting::get($key);  // ← HIGHEST PRIORITY
            }
        } catch (\Exception $e) {
            // Table exists but query failed - continue to config
        }
    }

    // 2. Fallback to config (which includes .env defaults)
    return config("officeguy.{$key}", $default);
}

// Usage in code:
use OfficeGuy\LaravelSumitGateway\Services\SettingsService;

$settings = app(SettingsService::class);
$companyId = $settings->get('company_id');  // Database → Config → .env
```

### Admin Settings Page

**File**: `src/Filament/Pages/OfficeGuySettings.php`

- **Route**: `/admin/office-guy-settings`
- **74 Settings**: All editable via Filament UI
- **Save Action**: Writes to `officeguy_settings` table
- **Tabs**: Credentials, Payment, Documents, Tokens, Routes, Order Binding, Bit, Stock, Logging

**Example Save Logic** (lines 546-564):
```php
public function save(): void
{
    try {
        // Saves all form fields to officeguy_settings table
        $this->settingsService->setMany($this->form->getState());

        Notification::make()
            ->title('Settings saved')
            ->body('Changes are now active')
            ->success()
            ->send();
    } catch (\Exception $e) {
        // Error handling
    }
}
```

### Configuration Best Practices

✅ **DO**:
- Use the Admin Settings Page for all runtime configuration
- Use `SettingsService::get()` in service classes
- Use `config('officeguy.*')` in Filament resources (DB values already loaded)
- Document all new settings in `config/officeguy.php` with comments

❌ **DON'T**:
- Use `env()` directly outside config files
- Hardcode credentials or URLs
- Bypass SettingsService for settings retrieval
- Assume .env is the source of truth (database overrides everything!)

## 🔌 SUMIT API Integration

### API Client

**File**: `src/Services/OfficeGuyApi.php`

```php
class OfficeGuyApi
{
    // Base URL builder
    public static function getUrl(string $path, string $environment): string
    {
        if ($environment === 'dev') {
            return 'http://' . $environment . '.api.sumit.co.il' . $path;
        }
        return 'https://api.sumit.co.il' . $path;
        // Production: https://api.sumit.co.il/creditguy/gateway/transaction/
    }

    // HTTP POST wrapper
    public static function post(array $request, string $path = '/creditguy/gateway/transaction/'): array
    {
        $url = self::getUrl($path, config('officeguy.environment', 'www'));

        $response = Http::withHeaders($headers)
            ->timeout(180)
            ->withOptions(['verify' => config('officeguy.ssl_verify', true)])
            ->post($url, $request);

        return $response->json();
    }
}
```

### Key API Endpoints

| Endpoint | Purpose | Service |
|----------|---------|---------|
| `/creditguy/gateway/transaction/` | Process payments | PaymentService |
| `/creditguy/vault/tokenizesingleuse` | Tokenize card data | TokenService |
| `/creditguy/bit/transaction/` | Bit payments | BitPaymentService |
| `/creditguy/document/` | Generate documents | DocumentService |
| `/creditguy/customer/` | Customer management | CustomerMergeService |
| `/creditguy/subscription/` | Recurring billing | SubscriptionService |

## 💳 Payment Flow

### PCI Modes

The package supports **3 PCI compliance modes**:

#### 1. PCI Mode = 'no' (PaymentsJS - Recommended)

**Best for**: Most implementations, supports all features

```
Browser → PaymentsJS SDK (Hosted Fields) → SUMIT API → Single-Use Token
       ↓
Token sent to your server → TokenService::processToken()
       ↓
Permanent token created → OfficeGuyToken model
```

**Features**:
- ✅ No PCI compliance required
- ✅ Supports tokens, recurring, authorize-only
- ✅ Card data never touches your server
- ✅ Customizable hosted fields

**Implementation**: Uses `ViewField` in Filament with Alpine.js + SUMIT SDK

#### 2. PCI Mode = 'redirect'

**Best for**: Simple implementations, no advanced features

```
Browser → External SUMIT payment page → SUMIT processes → Redirect back
       ↓
Callback URL with transaction ID → PaymentService::handleCallback()
```

**Features**:
- ✅ Simplest integration
- ❌ No recurring billing support
- ❌ No token storage
- ❌ No authorize-only

#### 3. PCI Mode = 'yes' (Direct API)

**Best for**: PCI-compliant servers, direct control

```
Browser → Direct form fields (TextInput) → Your server receives card data
       ↓
TokenService::processToken($user, 'yes') → Direct API call with card data
       ↓
Permanent token created → OfficeGuyToken model
```

**Features**:
- ✅ Full control over flow
- ✅ Supports all features
- ⚠️ Requires PCI DSS Level 1 certification
- ⚠️ Card data passes through your server

**Security Requirements**:
- SSL certificate
- PCI DSS compliance
- Secure server infrastructure
- Regular security audits

### Token Processing Flow

**File**: `src/Services/TokenService.php:13-38`

```php
public static function getTokenRequest(string $pciMode = 'no'): array
{
    $req = [
        'ParamJ' => config('officeguy.token_param', '5'),  // J5 or J2
        'Amount' => 1,  // Test amount
        'Credentials' => PaymentService::getCredentials(),
    ];

    if ($pciMode === 'yes') {
        // Direct API mode - card data from form fields
        $month = (int) RequestHelpers::post('og-expmonth');
        $req += [
            'CardNumber' => RequestHelpers::post('og-ccnum'),
            'CVV' => RequestHelpers::post('og-cvv'),
            'CitizenID' => RequestHelpers::post('og-citizenid'),
            'ExpirationMonth' => $month < 10 ? '0' . $month : (string)$month,
            'ExpirationYear' => RequestHelpers::post('og-expyear'),
        ];
    } else {
        // Hosted Fields mode - single-use token from PaymentsJS SDK
        $req['SingleUseToken'] = RequestHelpers::post('og-token');
    }

    return $req;
}
```

## 🎨 Filament Integration

### Admin Panel Resources (7)

**Location**: `src/Filament/Resources/`

1. **TransactionResource** - Payment transactions
2. **TokenResource** - Saved payment methods
3. **DocumentResource** - Invoices/receipts
4. **SubscriptionResource** - Recurring billing
5. **VendorCredentialResource** - Multi-vendor setup
6. **WebhookEventResource** - Outgoing webhooks
7. **SumitWebhookResource** - Incoming webhooks

### Client Panel Resources (6)

**Location**: `src/Filament/Client/Resources/`

1. **ClientPaymentMethodResource** - Customer payment methods
2. **ClientTransactionResource** - Customer transactions
3. **ClientDocumentResource** - Customer documents
4. **ClientSubscriptionResource** - Customer subscriptions
5. **ClientWebhookEventResource** - Customer webhook logs
6. **ClientSumitWebhookResource** - Customer incoming webhooks

### Settings Page (Admin Only)

**File**: `src/Filament/Pages/OfficeGuySettings.php`

**Features**:
- 74 configurable settings
- 9 tabs (Credentials, Payment, Documents, etc.)
- Database-first storage
- Real-time validation
- Test connection feature

## 📦 Services Layer

### Core Services (12 files)

**Location**: `src/Services/`

#### 1. OfficeGuyApi.php (HTTP Client)
- Base API communication
- URL building
- SSL verification
- Response handling

#### 2. PaymentService.php (Payment Processing)
- Process card payments
- Authorize-only transactions
- Installment handling
- Callback processing

#### 3. TokenService.php (Token Management)
- Single-use token exchange
- Permanent token creation
- PCI mode switching
- Token validation

#### 4. DocumentService.php (Document Generation)
- Invoice creation
- Receipt generation
- Donation receipts
- Document download

#### 5. BitPaymentService.php (Bit Integration)
- Bit transaction processing
- Webhook handling
- Status updates

#### 6. SubscriptionService.php (Recurring Billing)
- Subscription creation
- Renewal processing
- Status management

#### 7. SettingsService.php (Configuration)
- Database-first settings
- Config fallback
- Batch updates

#### 8. WebhookService.php (Webhook Handling)
- Incoming webhook processing
- Signature validation
- Event dispatching

#### 9. CustomerMergeService.php (Customer Management)
- Customer synchronization
- Duplicate merging

#### 10. DonationService.php (Donations)
- Donation processing
- Tax receipts

#### 11. UpsellService.php (Upselling)
- CartFlows equivalent
- Order bumps

#### 12. MultiVendorPaymentService.php (Multi-Vendor)
- Vendor credential management
- Split payments

## 🔗 Routes

**File**: `routes/officeguy.php`

All routes are **configurable** via Admin Settings Page or .env

| Method | Default Route | Purpose | Controller |
|--------|---------------|---------|------------|
| POST | `/officeguy/callback/card` | Card payment callback | CallbackController |
| POST | `/officeguy/webhook/bit` | Bit IPN webhook | BitWebhookController |
| POST | `/officeguy/webhook/sumit` | SUMIT webhooks | SumitWebhookController |
| GET | `/officeguy/documents/{document}` | Document download | DocumentController |
| POST | `/officeguy/checkout/charge` | Direct charge | CheckoutController |
| GET | `/officeguy/checkout/{id}` | Public checkout page | CheckoutController |
| POST | `/officeguy/checkout/{id}` | Submit checkout | CheckoutController |

**Customization** (Admin Settings Page → Route Configuration):
- Route Prefix: `officeguy` → `payments`
- Card Callback: `callback/card` → `return/card`
- Bit Webhook: `webhook/bit` → `ipn/bit`
- SUMIT Webhook: `webhook/sumit` → `triggers/sumit`

## 🧪 Testing Strategy

### Running Tests

```bash
# All tests
vendor/bin/phpunit

# Specific test
vendor/bin/phpunit --filter=PaymentServiceTest

# With coverage
vendor/bin/phpunit --coverage-html coverage/
```

### Test Structure

```
tests/
├── Feature/           # Integration tests
│   ├── PaymentTest.php
│   ├── TokenTest.php
│   └── WebhookTest.php
└── Unit/              # Unit tests
    ├── Services/
    │   ├── PaymentServiceTest.php
    │   ├── TokenServiceTest.php
    │   └── SettingsServiceTest.php
    └── Models/
        └── OfficeGuyTokenTest.php
```

### Mock SUMIT Responses

```php
use Illuminate\Support\Facades\Http;

Http::fake([
    'api.sumit.co.il/creditguy/gateway/transaction/' => Http::response([
        'Status' => 'Success',
        'Token' => 'tok_test123',
        'TransactionID' => 'txn_456',
    ], 200),
]);
```

## 🔨 Development Commands

### Package Development

```bash
# Install dependencies
composer install

# Run tests
vendor/bin/phpunit

# Code style (if configured)
vendor/bin/php-cs-fixer fix

# Clear cache
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Git Workflow (CRITICAL)

**Always follow this workflow when making changes**:

```bash
# 1. Work in vendor directory first
cd /var/www/vhosts/nm-digitalhub.com/httpdocs/vendor/officeguy/laravel-sumit-gateway

# 2. Make changes, test thoroughly
# ... edit files ...

# 3. Copy to original repo
cp -r src/ /var/www/vhosts/nm-digitalhub.com/SUMIT-Payment-Gateway-for-laravel/src/
# (copy other changed files as needed)

# 4. Commit in original repo
cd /var/www/vhosts/nm-digitalhub.com/SUMIT-Payment-Gateway-for-laravel
git add .
git commit -m "feat: Description of changes

Detailed explanation of what changed and why.

Fixes: #issue-number (if applicable)
"

# 5. Create version tag (semantic versioning)
git tag -a v1.1.7 -m "Release v1.1.7: Brief summary"
git push origin main
git push origin v1.1.7

# 6. Update in parent application
cd /var/www/vhosts/nm-digitalhub.com/httpdocs
composer update officeguy/laravel-sumit-gateway

# 7. Verify update
composer show officeguy/laravel-sumit-gateway
# Should show: versions : * v1.1.7
```

### Versioning Rules

Follow **Semantic Versioning** (SemVer):

- **MAJOR** (v2.0.0): Breaking changes (BC breaks)
- **MINOR** (v1.2.0): New features (backward compatible)
- **PATCH** (v1.1.7): Bug fixes (backward compatible)

Examples:
- Add new field to settings: `v1.2.0` (MINOR)
- Fix token validation bug: `v1.1.7` (PATCH)
- Change SettingsService API: `v2.0.0` (MAJOR)

## 📝 Coding Standards

### PHP Standards

- Follow **PSR-12** coding style
- Use **strict types**: `declare(strict_types=1);`
- Always type-hint parameters and return types
- Use **PHPDoc** for all public methods

**Example**:
```php
<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use Illuminate\Support\Facades\Http;

class ExampleService
{
    /**
     * Process a payment transaction.
     *
     * @param array<string, mixed> $data Transaction data
     * @return array<string, mixed> Response from SUMIT API
     * @throws \Exception If API call fails
     */
    public function processPayment(array $data): array
    {
        // Implementation
    }
}
```

### Filament v4 Patterns

**CRITICAL**: This package uses **Filament v4**, not v3!

Key differences:
- Use `Filament\Schemas\Components\` for layout components (Grid, Section, Tabs)
- Use `Filament\Forms\Components\` for form fields (TextInput, Select, Toggle)
- Use `columnSpanFull()` for full-width sections (v4 changed default)
- File uploads default to `private` visibility (set `public` explicitly)
- Filters are deferred by default (use `deferFilters(false)` for instant filtering)

**Example Form Schema**:
```php
use Filament\Forms;
use Filament\Schemas\Components as Schemas;

public static function form(Form $form): Form
{
    return $form->schema([
        Schemas\Section::make('Payment Details')
            ->schema([
                Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->required(),
                Forms\Components\Select::make('currency')
                    ->options(['ILS' => 'ILS', 'USD' => 'USD'])
                    ->default('ILS'),
            ])
            ->columnSpanFull(), // ← Important in v4!
    ]);
}
```

### Database Best Practices

✅ **DO**:
- Use Eloquent ORM
- Use migrations for schema changes
- Use transactions for multi-step operations
- Use query builders for complex queries
- Add indexes for frequently queried columns

❌ **DON'T**:
- Use raw SQL without parameter binding
- Skip migrations (even for "quick fixes")
- Forget to rollback transactions on errors
- Use `DB::` when Eloquent is sufficient

**Example Transaction**:
```php
use Illuminate\Support\Facades\DB;

DB::transaction(function () use ($data) {
    $transaction = OfficeGuyTransaction::create($data);
    $document = OfficeGuyDocument::create(['transaction_id' => $transaction->id]);

    // Both saved or both rolled back on error
});
```

## 🚫 Critical Rules

### Payment Processing

- ✅ **ALWAYS** validate webhook signatures
- ✅ **ALWAYS** log transactions to `officeguy_transactions` table
- ✅ **ALWAYS** use HTTPS in production (`ssl_verify => true`)
- ❌ **NEVER** store raw card numbers in database
- ❌ **NEVER** expose private_key in client-side code
- ❌ **NEVER** skip transaction logging

### Security

- ✅ **ALWAYS** use prepared statements (Eloquent does this)
- ✅ **ALWAYS** validate all inputs
- ✅ **ALWAYS** sanitize outputs (XSS prevention)
- ✅ **ALWAYS** use CSRF protection (Laravel default)
- ❌ **NEVER** commit `.env` or credentials
- ❌ **NEVER** disable SSL verification in production
- ❌ **NEVER** trust client-side data

### Configuration

- ✅ **ALWAYS** use `SettingsService::get()` for settings retrieval
- ✅ **ALWAYS** document new settings in `config/officeguy.php`
- ✅ **ALWAYS** provide sensible defaults
- ❌ **NEVER** use `env()` outside config files
- ❌ **NEVER** hardcode URLs or credentials
- ❌ **NEVER** assume .env is the source of truth (database overrides!)

## 📚 Documentation

### Package Documentation Files

**Most Important** (Read First):
1. **CLAUDE.md** (this file) - Development guide
2. **README.md** - Full Hebrew user documentation
3. **FILAMENT_V4_UPGRADE_SUMMARY.md** - Filament v3→v4 changes
4. **CHANGELOG.md** - Version history
5. **UPGRADE.md** - Upgrade instructions

**Reference**:
- `docs/` - Additional documentation
- `woo-plugin/` - Original WooCommerce plugin (reference only)
- `sumit-openapi.json` - SUMIT API spec

### Documentation Standards

When adding new features:

1. **Update README.md** (Hebrew):
   - Add section for new feature
   - Include code examples
   - Document all settings

2. **Update CHANGELOG.md**:
   - Add entry under "Unreleased"
   - Move to version section on release

3. **Update this CLAUDE.md**:
   - Add to relevant section
   - Update file references
   - Add to critical rules if needed

4. **Add PHPDoc**:
   - All public methods
   - Complex private methods
   - Service classes

## 🔍 Common Tasks

### Adding a New Setting

1. **Add to config** (`config/officeguy.php`):
```php
'new_setting' => env('OFFICEGUY_NEW_SETTING', 'default_value'),
```

2. **Add to OfficeGuySettings.php** form schema:
```php
Forms\Components\TextInput::make('new_setting')
    ->label('New Setting')
    ->helperText('Description of what this does')
    ->default('default_value'),
```

3. **Use in code**:
```php
$value = config('officeguy.new_setting');
// or
$value = app(SettingsService::class)->get('new_setting');
```

4. **Document** in README.md and this file

### Adding a New Service Method

1. **Create method** in appropriate service:
```php
/**
 * Process refund for a transaction.
 *
 * @param OfficeGuyTransaction $transaction
 * @param float $amount
 * @return array<string, mixed>
 */
public function processRefund(OfficeGuyTransaction $transaction, float $amount): array
{
    $response = OfficeGuyApi::post([
        'TransactionID' => $transaction->transaction_id,
        'Amount' => $amount,
        'Credentials' => PaymentService::getCredentials(),
    ], '/creditguy/gateway/refund/');

    return $response;
}
```

2. **Add test**:
```php
public function test_refund_processes_successfully(): void
{
    Http::fake([
        'api.sumit.co.il/creditguy/gateway/refund/' => Http::response([
            'Status' => 'Success',
        ], 200),
    ]);

    $result = $this->service->processRefund($this->transaction, 100.00);

    $this->assertEquals('Success', $result['Status']);
}
```

3. **Document** in README.md

### Adding a New Filament Resource

1. **Create resource** (follow existing patterns):
```php
namespace OfficeGuy\LaravelSumitGateway\Filament\Resources;

use Filament\Resources\Resource;

class RefundResource extends Resource
{
    protected static ?string $model = OfficeGuyRefund::class;
    protected static ?string $navigationGroup = 'SUMIT Gateway';
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    // ... form, table, pages
}
```

2. **Register** in `OfficeGuyServiceProvider::boot()`:
```php
Filament::serving(function () {
    Filament::registerResources([
        Resources\RefundResource::class,
    ]);
});
```

3. **Add test**:
```php
livewire(ListRefunds::class)
    ->assertCanSeeTableRecords(OfficeGuyRefund::all());
```

## 🤖 Claude Code Workflow

### Before Starting ANY Task

1. **Read Context**:
   - This CLAUDE.md file
   - README.md for user-facing features
   - FILAMENT_V4_UPGRADE_SUMMARY.md for Filament patterns

2. **Check Current State**:
   - `git status` - See what's changed
   - `git log --oneline -10` - Recent commits
   - `git describe --tags` - Current version

3. **Understand Dependencies**:
   - Check `composer.json` for version constraints
   - Review parent application integration (if applicable)

### Task Execution Strategy

**For Service Layer Tasks**:
```
1. Identify which service is responsible
2. Check existing methods for similar patterns
3. Add PHPDoc with clear description
4. Add unit test first (TDD approach)
5. Implement method
6. Run tests: vendor/bin/phpunit --filter=ServiceTest
7. Update documentation
```

**For Filament Resource Tasks**:
```
1. Check Filament v4 patterns (NOT v3!)
2. Use Schemas\Components for layouts
3. Use Forms\Components for fields
4. Add columnSpanFull() for full-width sections
5. Test in both Admin and Client panels (if applicable)
6. Ensure mobile responsiveness
7. Add Livewire tests
```

**For Configuration Tasks**:
```
1. Add to config/officeguy.php with .env fallback
2. Add to OfficeGuySettings.php form schema
3. Test 3-layer priority: Database → Config → .env
4. Document in README.md
5. Update migration if needed (add to officeguy_settings seeder)
```

**For API Integration Tasks**:
```
1. Check sumit-openapi.json for endpoint spec
2. Use OfficeGuyApi::post() wrapper
3. Add proper error handling
4. Log all API calls (if logging enabled)
5. Mock HTTP responses in tests
6. Handle rate limiting gracefully
```

### Git Workflow (Package Development)

**CRITICAL**: Always follow this exact workflow!

```
Step 1: Work in vendor directory
├─ cd /var/www/vhosts/nm-digitalhub.com/httpdocs/vendor/officeguy/laravel-sumit-gateway
├─ Make changes
├─ Test thoroughly
└─ Verify functionality

Step 2: Copy to original repo
├─ cp -r changed_files /var/www/vhosts/nm-digitalhub.com/SUMIT-Payment-Gateway-for-laravel/
└─ Verify all files copied

Step 3: Commit in original repo
├─ cd /var/www/vhosts/nm-digitalhub.com/SUMIT-Payment-Gateway-for-laravel
├─ git add .
├─ git commit -m "feat: Clear description"
└─ Write detailed commit message

Step 4: Tag version
├─ git tag -a v1.1.7 -m "Release v1.1.7: Summary"
├─ git push origin main
└─ git push origin v1.1.7

Step 5: Update parent application
├─ cd /var/www/vhosts/nm-digitalhub.com/httpdocs
├─ composer update officeguy/laravel-sumit-gateway
└─ Verify: composer show officeguy/laravel-sumit-gateway
```

### Common Pitfalls to Avoid

❌ **DON'T**:
- Work directly in original repo (work in vendor first!)
- Forget to create git tags (required for composer updates)
- Use Filament v3 patterns (this is v4!)
- Skip tests (they're required!)
- Hardcode credentials or URLs
- Use `env()` outside config files
- Assume .env is source of truth (database overrides!)
- Commit without testing
- Push without tagging
- Forget to run `composer update` after push

✅ **DO**:
- Test in vendor directory first
- Copy to original repo when complete
- Follow semantic versioning
- Write tests for all new code
- Use SettingsService for configuration
- Use Eloquent ORM
- Log all transactions
- Validate webhook signatures
- Document all changes
- Run tests before commit
- Create git tag after commit
- Update parent application after push

### Quick Decision Tree

**Need to add payment feature?** → Use PaymentService, add to TransactionResource
**Need to store card tokens?** → Use TokenService, update ClientPaymentMethodResource
**Need to generate documents?** → Use DocumentService, update DocumentResource
**Need to add webhook?** → Use WebhookService, update WebhookEventResource
**Need to add setting?** → Add to config, OfficeGuySettings.php form, document
**Need to call SUMIT API?** → Use OfficeGuyApi::post(), mock in tests
**Need to query database?** → Use Eloquent, add indexes if needed
**Filament layout not working?** → Check v4 patterns, use Schemas\Components
**Tests failing?** → Check HTTP mocks, database state, Filament version

## 🌍 Localization

**Primary Language**: Hebrew (he)
**Secondary Language**: English (en)

All user-facing text should support both languages:

```php
// In Filament resources
protected static ?string $navigationLabel = 'תשלומים';

// In blade views
{{ __('officeguy::messages.payment_successful') }}

// Translation files
resources/lang/he/messages.php
resources/lang/en/messages.php
```

## 📖 Additional Resources

### Official Links

- **GitHub**: https://github.com/nm-digitalhub/SUMIT-Payment-Gateway-for-laravel
- **SUMIT API Docs**: https://docs.sumit.co.il
- **SUMIT Dashboard**: https://app.sumit.co.il
- **Filament v4 Docs**: https://filamentphp.com/docs/4.x
- **Laravel 12 Docs**: https://laravel.com/docs/12.x

### Internal Documentation

- **Parent App CLAUDE.md**: `/var/www/vhosts/nm-digitalhub.com/httpdocs/CLAUDE.md`
- **SUMIT Package Analysis**: `docs/SUMIT_PACKAGES_ANALYSIS_2025-11-20.md` (in parent app)
- **Client Panel Docs**: `docs/CLIENT_PANEL_*.md` (in parent app)

---

**Package Version**: v1.1.6
**Last Updated**: 2025-11-27
**Maintained By**: NM-DigitalHub
**Support**: info@nm-digitalhub.com

---

## 🎓 Learning Path for New Developers

### Week 1: Understanding the Basics
1. Read this entire CLAUDE.md file
2. Read README.md (Hebrew documentation)
3. Explore the package structure (`src/`)
4. Review the 8 models and their relationships
5. Test the Admin Settings Page (`/admin/office-guy-settings`)

### Week 2: Configuration & Services
1. Understand the 3-layer configuration system
2. Review SettingsService and how DB overrides work
3. Study PaymentService, TokenService, OfficeGuyApi
4. Test payment flow in all 3 PCI modes
5. Review webhook handling

### Week 3: Filament Integration
1. Explore all 7 Admin resources
2. Explore all 6 Client resources
3. Understand Filament v4 patterns (Schemas vs Forms)
4. Test creating/editing records via Filament
5. Review form validation patterns

### Week 4: Testing & Contributing
1. Run all tests: `vendor/bin/phpunit`
2. Write a new test for an existing feature
3. Add a new setting (config → form → test)
4. Make a small fix and follow git workflow
5. Create your first git tag

### Recommended Reading Order
1. CLAUDE.md (this file) - Development guide
2. README.md - User documentation
3. FILAMENT_V4_UPGRADE_SUMMARY.md - Filament patterns
4. config/officeguy.php - Configuration structure
5. src/Services/PaymentService.php - Core payment logic
6. src/Services/SettingsService.php - Configuration system
7. src/OfficeGuyServiceProvider.php - Package bootstrap
8. CHANGELOG.md - Version history

---

**Remember**: This package is the **official** SUMIT integration for Laravel 12 + Filament v4. Quality, security, and maintainability are paramount. When in doubt, ask questions before making changes!
