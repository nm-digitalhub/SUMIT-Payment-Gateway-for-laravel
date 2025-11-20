# WooCommerce to Laravel Mapping

This document provides a comprehensive mapping between the original WooCommerce SUMIT plugin and the Laravel package implementation.

## File Structure Mapping

| WooCommerce Plugin | Laravel Package | Notes |
|-------------------|-----------------|-------|
| `includes/OfficeGuyAPI.php` | `src/Services/OfficeGuyApi.php` | API communication layer |
| `includes/OfficeGuyPayment.php` | `src/Services/PaymentService.php` + `src/Services/DocumentService.php` | Split into focused services |
| `includes/OfficeGuyTokens.php` | `src/Services/TokenService.php` | Card tokenization |
| `includes/officeguy_woocommerce_gateway.php` | `src/Http/Controllers/CardCallbackController.php` | Card gateway logic |
| `includes/officeguybit_woocommerce_gateway.php` | `src/Services/BitPaymentService.php` + `src/Http/Controllers/BitWebhookController.php` | Bit payment handling |
| `includes/OfficeGuySettings.php` | `config/officeguy.php` | Configuration settings |
| `includes/OfficeGuyRequestHelpers.php` | `src/Support/RequestHelpers.php` | Request utilities |
| N/A | `src/Contracts/Payable.php` | Laravel abstraction for orders |
| N/A | `src/Models/*` | Eloquent models for data persistence |
| N/A | `database/migrations/*` | Database schema |

## Class and Method Mapping

### OfficeGuyAPI.php → OfficeGuyApi.php

| WooCommerce Method | Laravel Method | Changes |
|-------------------|----------------|---------|
| `GetURL($Path, $Environment)` | `getUrl(string $path, string $environment)` | Camel case, type hints |
| `Post($Request, $Path, $Environment, $SendClientIP)` | `post(array $request, string $path, string $environment, bool $sendClientIp)` | Type hints, strict types |
| `PostRaw(...)` | `postRaw(...)` | Using Laravel HTTP client instead of wp_remote_post |
| `CheckCredentials($CompanyID, $APIKey)` | `checkCredentials(int $companyId, string $apiKey)` | Type hints |
| `CheckPublicCredentials(...)` | `checkPublicCredentials(...)` | Type hints |
| `WriteToLog($Text, $Type)` | `writeToLog(string $text, string $type)` | Using Laravel Log facade |

### OfficeGuyPayment.php → PaymentService.php

| WooCommerce Method | Laravel Method | Changes |
|-------------------|----------------|---------|
| `GetCredentials($Gateway)` | `getCredentials()` | Reads from config instead of gateway settings |
| `GetMaximumPayments($Gateway, $OrderValue)` | `getMaximumPayments(float $orderValue)` | Reads from config |
| `GetOrderVatRate($Order)` | `getOrderVatRate(Payable $order)` | Uses Payable contract |
| `GetOrderLanguage($Gateway)` | `getOrderLanguage()` | Uses app locale |
| `GetOrderCustomer($Gateway, $Order)` | `getOrderCustomer(Payable $order, ?string $citizenId)` | Uses Payable contract |
| `GetPaymentOrderItems($Order)` | `getPaymentOrderItems(Payable $order)` | Uses Payable contract |
| `GetDocumentOrderItems($Order)` | `getDocumentOrderItems(Payable $order)` | Uses Payable contract |
| `IsCurrencySupported()` | `isCurrencySupported(string $currency)` | Type hints |
| `ProcessOrder(...)` | Moved to controller/service layer | More modular approach |

### OfficeGuyPayment.php → DocumentService.php

| WooCommerce Method | Laravel Method | Changes |
|-------------------|----------------|---------|
| `CreateOrderDocument(...)` | `createOrderDocument(...)` | Uses Payable contract |
| `CreateDocumentOnPaymentCompleteInternal(...)` | `createDocumentOnPaymentComplete(...)` | Simplified parameters |

### OfficeGuyTokens.php → TokenService.php

| WooCommerce Method | Laravel Method | Changes |
|-------------------|----------------|---------|
| `GetTokenRequest($Gateway)` | `getTokenRequest(string $pciMode)` | Simplified parameters |
| `GetTokenFromResponse($Gateway, $Response)` | `getTokenFromResponse(mixed $owner, array $response, string $gatewayId)` | Returns OfficeGuyToken model |
| `ProcessToken($Gateway)` | `processToken(mixed $owner, string $pciMode)` | Returns array with success/token/message |
| `SaveTokenToOrder($Order, $Token)` | `saveTokenToOrder(mixed $order, OfficeGuyToken $token)` | Type hints |

### Bit Gateway → BitPaymentService.php

| WooCommerce Method | Laravel Method | Changes |
|-------------------|----------------|---------|
| `ProcessBitOrder($Gateway, $Order)` | `processOrder(Payable $order, ...)` | Uses Payable contract |
| `ProcessIPN()` (static) | `processWebhook(...)` | Moved to service, improved parameters |

### OfficeGuySettings.php → config/officeguy.php

All settings have been converted to a Laravel configuration file with environment variable support.

| WooCommerce Setting | Laravel Config Key | ENV Variable |
|--------------------|-------------------|--------------|
| `environment` | `officeguy.environment` | `OFFICEGUY_ENVIRONMENT` |
| `companyid` | `officeguy.company_id` | `OFFICEGUY_COMPANY_ID` |
| `privatekey` | `officeguy.private_key` | `OFFICEGUY_PRIVATE_KEY` |
| `publickey` | `officeguy.public_key` | `OFFICEGUY_PUBLIC_KEY` |
| `pci` | `officeguy.pci` | `OFFICEGUY_PCI_MODE` |
| `testing` | `officeguy.testing` | `OFFICEGUY_TESTING` |
| `maxpayments` | `officeguy.max_payments` | `OFFICEGUY_MAX_PAYMENTS` |
| `support_tokens` | `officeguy.support_tokens` | `OFFICEGUY_SUPPORT_TOKENS` |
| `draftdocument` | `officeguy.draft_document` | `OFFICEGUY_DRAFT_DOCUMENT` |
| `emaildocument` | `officeguy.email_document` | `OFFICEGUY_EMAIL_DOCUMENT` |
| And more... | See `config/officeguy.php` | Full mapping available |

### OfficeGuyRequestHelpers.php → RequestHelpers.php

| WooCommerce Method | Laravel Method | Changes |
|-------------------|----------------|---------|
| `Get($Name)` | `get(string $name, mixed $default)` | Uses Laravel request() helper |
| `Post($Name)` | `post(string $name, mixed $default)` | Uses Laravel request() helper |

## Data Storage Mapping

### WooCommerce Meta Fields → Laravel Database

| WooCommerce Meta | Laravel Table | Laravel Column | Notes |
|-----------------|---------------|----------------|-------|
| `_og_token` | `officeguy_tokens` | `token` | Stored as model with relationships |
| Order meta for payment details | `officeguy_transactions` | Various columns | Dedicated transaction table |
| `OfficeGuyDocumentID` | `officeguy_documents` | `document_id` | Dedicated documents table |
| `OfficeGuyCustomerID` | `officeguy_transactions` | `customer_id` | Stored in transaction |
| `OfficeGuyAuthNumber` | `officeguy_transactions` | `auth_number` | Dedicated column |

## Conceptual Differences

### 1. Order Abstraction

**WooCommerce**: Direct dependency on `WC_Order` object
**Laravel**: `Payable` contract interface for flexibility

### 2. Configuration

**WooCommerce**: Settings stored in WordPress options table, accessed via `$Gateway->settings`
**Laravel**: Configuration file with environment variables

### 3. Hooks and Filters

**WooCommerce**: WordPress actions and filters (e.g., `apply_filters`, `do_action`)
**Laravel**: Laravel events and service container (planned for future implementation)

### 4. User Authentication

**WooCommerce**: `get_current_user_id()`, `is_user_logged_in()`
**Laravel**: `auth()->user()`, `auth()->check()`

### 5. Logging

**WooCommerce**: `wc_get_logger()` and WooCommerce logging system
**Laravel**: Laravel Log facade with configurable channels

### 6. HTTP Requests

**WooCommerce**: `wp_remote_post()`, `wp_remote_retrieve_body()`
**Laravel**: Laravel HTTP client (`Http::post()`)

### 7. Localization

**WooCommerce**: `__()`, `_e()` with textdomain
**Laravel**: `__()` with Laravel translation system

### 8. Database Access

**WooCommerce**: WordPress post meta and custom tables
**Laravel**: Eloquent ORM with dedicated models and migrations

## API Endpoint Mapping

| WooCommerce | Laravel | Purpose |
|------------|---------|---------|
| `?wc-api=WC_OfficeGuy` | `/officeguy/callback/card` | Card payment callback |
| `?wc-api=officeguybit_woocommerce_gateway` | `/officeguy/webhook/bit` | Bit payment webhook |

## Not Yet Implemented

The following WooCommerce plugin features are not yet ported but are documented for future implementation:

1. **OfficeGuySubscriptions.php** - Recurring payment logic (planned)
2. **OfficeGuyStock.php** - Stock synchronization (planned)
3. **OfficeGuyMultiVendor.php** - Multi-vendor support (planned)
4. **OfficeGuyDonation.php** - Donation receipt logic (planned)
5. **CartFlows integration** - Specific to WooCommerce ecosystem (N/A)
6. **Marketplace integrations** (Dokan, WCFM, WC Vendors) - Marketplace-specific (N/A)

## Testing Considerations

When testing the Laravel package against the WooCommerce plugin:

1. **API Requests**: Both should send identical JSON payloads to SUMIT
2. **Response Handling**: Both should handle API responses identically
3. **Document Creation**: Both should create the same document types
4. **Token Storage**: Both should store and retrieve tokens correctly
5. **Error Handling**: Error messages should be consistent
6. **Status Mapping**: Payment statuses should map consistently

## Migration Path

For migrating from WooCommerce to Laravel:

1. Export transaction data from WooCommerce
2. Map order data to Laravel `Payable` implementation
3. Migrate customer tokens (card details are tokenized)
4. Update webhook URLs in SUMIT dashboard
5. Test in development environment before production switch
