# CLAUDE.md — officeguy/laravel-sumit-gateway

> **Source of truth**: This file was regenerated from source code inspection of v5.0.0-rc7. Do not rely on version comments inside code — read this file for accurate architecture.

## Package Identity

| Key | Value |
|-----|-------|
| Composer name | `officeguy/laravel-sumit-gateway` |
| Current version | `5.0.0-rc7` |
| PHP | `^8.2` |
| Laravel | `^12.0 \|\| ^13.0` |
| Saloon | `^4.0` |
| Namespace | `OfficeGuy\LaravelSumitGateway\` |
| Service provider | `OfficeGuyServiceProvider` |

---

## Database Tables

| Table | Model | Purpose |
|-------|-------|---------|
| `officeguy_transactions` | `OfficeGuyTransaction` | Payment transaction records |
| `officeguy_tokens` | `OfficeGuyToken` | Saved payment method tokens |
| `officeguy_documents` | `OfficeGuyDocument` | SUMIT invoices/receipts |
| `officeguy_settings` | `OfficeGuySetting` | DB-stored config (overrides `.env`) |
| `vendor_credentials` | `VendorCredential` | Multi-vendor SUMIT credentials |
| `subscriptions` | `Subscription` | Recurring billing |
| `webhook_events` | `WebhookEvent` | Outgoing webhook log |
| `sumit_incoming_webhooks` | `SumitWebhook` | Incoming SUMIT triggers |
| `payable_field_mappings` | `PayableFieldMapping` | Payable model field config |
| `document_subscription` | (pivot) | Document↔Subscription many-to-many |
| `pending_checkouts` | `PendingCheckout` | Pre-payment checkout state |
| `order_success_tokens` | `OrderSuccessToken` | Secure success page tokens |
| `order_success_access_log` | `OrderSuccessAccessLog` | Success page access audit |
| `officeguy_crm_folders` | `CrmFolder` | SUMIT CRM folder schemas |
| `officeguy_crm_folder_fields` | `CrmFolderField` | SUMIT CRM field definitions |
| `officeguy_crm_entities` | `CrmEntity` | SUMIT CRM entity records |
| `officeguy_crm_entity_fields` | `CrmEntityField` | CRM entity field values |
| `officeguy_crm_entity_relations` | `CrmEntityRelation` | CRM entity relationships |
| `officeguy_crm_activities` | `CrmActivity` | SUMIT CRM activity log |
| `officeguy_crm_views` | `CrmView` | SUMIT CRM view schemas |
| `officeguy_debt_attempts` | — | Debt collection attempt log |

---

## Key Models

### OfficeGuyDocument (`officeguy_documents`)

Stores SUMIT invoice/receipt records.

```php
// Key fillable fields:
'document_id'            // SUMIT document ID
'document_number'        // Human-readable number
'document_date'          // datetime
'order_id'               // Polymorphic FK
'order_type'             // Polymorphic type
'subscription_id'        // Legacy single subscription FK
'customer_id'            // SUMIT customer ID (NOT local user ID)
'document_type'          // '1'=Invoice, '8'=Order, 'DonationReceipt'
'is_draft'               // bool
'is_closed'              // bool
'currency'               // 'ILS', 'USD', etc.
'amount'                 // decimal:2
'description'
'document_download_url'  // Direct PDF download URL from SUMIT
'document_payment_url'   // Payment URL for open invoices
'items'                  // JSON array of line items
'raw_response'           // Full SUMIT API response array

// Relationships:
order()         // MorphTo — the payable model (order_type / order_id)
subscription()  // BelongsTo Subscription (deprecated, use subscriptions())
subscriptions() // BelongsToMany Subscription via document_subscription pivot
customer()      // BelongsTo dynamic customer model (by sumit_customer_id)
client()        // @deprecated — delegates to customer()

// Factory methods:
OfficeGuyDocument::createFromApiResponse($orderId, $response, $request, $orderType)
OfficeGuyDocument::createFromListResponse($doc, $subscriptionId)

// Helpers:
$doc->getDocumentTypeName()  // 'Invoice' / 'Order' / 'Donation Receipt' / 'Document'
$doc->isInvoice()            // document_type === '1'
$doc->isOrder()              // document_type === '8'
$doc->isDonationReceipt()    // document_type === 'DonationReceipt'
```

### OfficeGuyTransaction (`officeguy_transactions`)

```php
// Key fields (beyond the obvious):
'order_id'               // Polymorphic FK to payable
'order_type'             // Polymorphic type string
'payment_id'             // SUMIT Payment ID
'document_id'            // Linked SUMIT Document ID
'client_id'              // Local user/customer PK (resolved from ExternalIdentifier or sumit_customer_id)
'customer_id'            // Legacy SUMIT customer ID
'sumit_customer_id_used' // The actual SUMIT customer ID used
'status'                 // 'completed' | 'failed' | 'refunded'
'transaction_type'       // 'charge' | 'refund' | 'void'
'parent_transaction_id'  // FK to original transaction (for refunds)
'refund_transaction_id'  // FK to refund transaction
'sumit_entity_id'        // CRITICAL: required for webhook confirmation (ADR-004)
'is_webhook_confirmed'   // bool — confirmed via SUMIT CRM webhook
'webhook_confirmed_at'   // datetime — set by TransactionSyncListener on CRM confirmation
'confirmed_by'           // webhook source identifier (e.g. 'webhook_crm')
'source'                 // 'checkout' | 'webhook' | 'api_polling'

// Relationships:
order()             // MorphTo payable (same as order_id/order_type)
customer()          // BelongsTo dynamic customer model via client_id
client()            // @deprecated — delegates to customer()
parentTransaction() // BelongsTo self
refundTransaction() // BelongsTo self
childRefunds()      // HasMany self

// Accessor:
$tx->payable        // alias for $tx->order

// Helpers:
$tx->isRefund() / $tx->isCharge() / $tx->isCompleted() / $tx->isFailed()
$tx->isOrphan()        // true if order_id or order_type is empty
$tx->getPaymentToken() // from payment_token or raw_response
$tx->addNote(string)   // appends timestamped note

// CRITICAL: Always set sumit_entity_id when creating — required for webhook confirmation
```

---

## Service Layer

### Singletons registered in ServiceProvider

```php
SettingsService::class          // DB-first config access
OfficeGuyApi::class             // Legacy HTTP wrapper (still used internally)
PaymentService::class           // Core payment processing
TokenService::class             // Card token management
BitPaymentService::class        // Bit payment integration
DocumentService::class          // Invoice/receipt generation
StockService::class             // Product stock sync
SubscriptionService::class      // Recurring billing
DonationService::class          // Donation receipts
MultiVendorPaymentService::class // Multi-vendor split payments
UpsellService::class            // Order bumps / upsells
WebhookService::class           // Outgoing webhook dispatch
CustomerMergeService::class     // SUMIT customer sync
CheckoutViewResolver::class     // View selection (configurable callback)
SecureSuccessUrlGenerator::class // Signed success URL generation
SuccessAccessValidator::class   // 7-layer success page validation
FulfillmentDispatcher::class    // Post-payment fulfillment dispatch
ServiceDataFactory::class       // Checkout intent data assembly
TemporaryStorageService::class  // Pre-payment state storage
```

### New in v5.x

```php
IncomeItemService::class        // SUMIT accounting income items (OpenAPI)
SumitProductService::class      // CRM product sync
CheckoutIntentResolver::class   // Resolves payable from checkout intent
PackageVersionService::class    // Package version metadata
```

---

## HTTP Architecture (Saloon v4)

### SumitConnector

```php
// src/Http/Connectors/SumitConnector.php
class SumitConnector extends Connector
{
    use AlwaysThrowOnErrors;

    public ?int $tries = 3;           // Auto-retry 3 times
    public ?bool $throwOnMaxTries = true;

    // Base URL: https://api.sumit.co.il (production)
    //           http://dev.api.sumit.co.il (dev)
    // Timeout: 180s
    // Middleware: LoggingMiddleware (if officeguy.logging=true), SensitiveDataRedactor (always)
}
```

### Credentials DTO

```php
// src/Http/DTOs/CredentialsData.php
new CredentialsData(
    companyId: config('officeguy.company_id'),
    apiKey: config('officeguy.private_key')
)
// ->toArray() returns ['CompanyID' => ..., 'APIKey' => ...]
```

### Saloon Request Classes

All requests live in `src/Http/Requests/` organized by domain:
- `Auth/` — credential validation
- `Bit/` — Bit payment requests
- `Customer/` — customer CRUD
- `Document/` — document CRUD + list

---

## Configuration System

### 3-Layer Priority

1. **Database** (`officeguy_settings` table) — highest priority, overrides everything
2. **Config file** (`config/officeguy.php`) — default values
3. **`.env`** — fallback only (never use `env()` outside config files)

### Loading Mechanism

`OfficeGuyServiceProvider::loadDatabaseSettings()` runs in `boot()` and calls `OfficeGuySetting::getAllSettings()` to override the config array. This means `config('officeguy.*')` always returns the effective value.

### SettingsService

```php
use OfficeGuy\LaravelSumitGateway\Services\SettingsService;

$settings = app(SettingsService::class);
$value = $settings->get('company_id'); // DB → config → null
```

### Customer Model Resolution

3-layer priority via `app('officeguy.customer_model')`:
1. DB: `officeguy_settings.customer_model_class`
2. Config: `officeguy.models.customer`
3. Config: `officeguy.customer_model_class`
4. `null` if not configured

---

## Event Architecture

### Events (src/Events/)

| Event | When |
|-------|------|
| `PaymentCompleted` | Card/Bit payment succeeds |
| `PaymentFailed` | Payment attempt fails |
| **`PayablePaid`** | **Post-payment fulfillment hook (v5.x)** |
| `BitPaymentCompleted` | Bit-specific success |
| `DocumentCreated` | SUMIT document generated |
| `DocumentSynced` | Document synced from SUMIT API |
| `GuestUserCreated` | Auto-created guest user account |
| `SubscriptionCreated` / `Charged` / `Cancelled` / `ChargesFailed` | Subscription lifecycle |
| `UpsellPaymentCompleted` / `Failed` | Upsell outcomes |
| `MultiVendorPaymentCompleted` / `Failed` | Multi-vendor outcomes |
| `SumitWebhookReceived` | Any incoming SUMIT webhook |

### Fulfillment Architecture (Phase 4.6)

**All PayableTypes use `GenericFulfillmentHandler`**. The handler dispatches `PayablePaid`.
Host app must listen to `PayablePaid` for product-specific fulfillment:

```php
// In your EventServiceProvider:
Event::listen(
    \OfficeGuy\LaravelSumitGateway\Events\PayablePaid::class,
    \App\Listeners\HandleProductFulfillment::class
);
```

Type-specific handlers (`DigitalProductFulfillmentHandler`, `InfrastructureFulfillmentHandler`, `SubscriptionFulfillmentHandler`) are **removed**. Do not reference them.

### Listeners registered by ServiceProvider

| Listener | Trigger |
|----------|---------|
| `WebhookEventListener` | (subscriber — all webhook events) |
| `CustomerSyncListener` | `SumitWebhookReceived` |
| `RefundWebhookListener` | `SumitWebhookReceived` |
| `TransactionSyncListener` | `SumitWebhookReceived` (ADR-004: card confirmation) |
| `CrmActivitySyncListener` | `SumitWebhookReceived` |
| `DocumentSyncListener` | (subscriber — document events) |
| `AutoCreateUserListener` | `PaymentCompleted` |
| `FulfillmentListener` | `PaymentCompleted` |
| `NotifyPaymentCompletedListener` | `PaymentCompleted` |
| `NotifyPaymentFailedListener` | `PaymentFailed` |
| `NotifySubscriptionCreatedListener` | `SubscriptionCreated` |
| `NotifyDocumentCreatedListener` | `DocumentCreated` |

---

## Routes

All paths configurable via `RouteConfig` (Admin Panel or `config/officeguy.php`).
Default prefix: `officeguy`.

| Method | Path | Name | Purpose |
|--------|------|------|---------|
| `POST` | `/locale` | `officeguy.locale.change` | Language switching |
| `POST` | `/{prefix}/api/check-email` | `officeguy.api.check-email` | Email existence check |
| `GET` | `/{prefix}/{card_callback}` | `officeguy.callback.card` | Card payment return |
| `POST` | `/{prefix}/{bit_webhook}` | `officeguy.webhook.bit` | Bit IPN |
| `GET` | `/{prefix}/{document_download}` | `officeguy.document.download` | PDF download |
| `POST` | `/{prefix}/{checkout_charge}` | `officeguy.checkout.charge` | Direct charge (optional) |
| `GET/POST` | `/{prefix}/{public_checkout}` | `officeguy.public.checkout[.process]` | Public checkout |
| `GET` | `/{prefix}/success` | `officeguy.success` | Secure success (rate-limited) |
| `POST` | `/{prefix}/{sumit_webhook}` | `officeguy.webhook.sumit` | SUMIT general webhook |
| `POST` | `/{prefix}/{sumit_webhook}/card-{created\|updated\|deleted\|archived}` | — | Card-specific webhooks |
| `POST` | `/{prefix}/{sumit_webhook}/crm` | `officeguy.webhook.crm` | CRM webhook |

---

## PayableType Enum

```php
PayableType::INFRASTRUCTURE  // 'infrastructure' — requires address, ~30min fulfillment
PayableType::DIGITAL_PRODUCT // 'digital_product' — instant delivery
PayableType::SUBSCRIPTION    // 'subscription' — requires phone
PayableType::SERVICE         // 'service' — requires phone, ~24hr fulfillment
PayableType::GENERIC         // 'generic' — fallback
```

---

## SumitApiResponse Utility

Normalizes SUMIT API status — handles both legacy integer (`0`) and new OpenAPI string (`"Success (0)"`):

```php
use OfficeGuy\LaravelSumitGateway\Support\SumitApiResponse;

SumitApiResponse::isSuccess($response['Status']); // true for 0, '0', 'Success*'
```

---

## Artisan Commands

| Command | Purpose |
|---------|---------|
| `sumit:stock-sync` | Sync product stock |
| `sumit:process-recurring` | Process recurring payments |
| `sumit:sync-all-documents --days=30` | Sync documents from SUMIT |
| `crm:sync-folders` | Sync CRM folder schemas |
| `crm:sync-views` | Sync CRM view schemas |

**Scheduled automatically:**
- `sumit:sync-all-documents` — daily at 03:00
- `crm:sync-folders` — daily at 02:00
- `CheckSumitDebtJob` — daily at `officeguy.collection.schedule_time` (default 02:00)
- `sumit:stock-sync` — every 12h or daily (based on `stock_sync_freq` setting)

---

## Critical Rules

### What changed in v3.0.0+

- **Filament completely removed from core** — no `filament/filament` dependency, no Filament classes in `src/`. If you need Filament UI, use the adapter package `officeguy/laravel-sumit-gateway-filament` (separate install).
- **No `App\Models\Client` references** — customer model is fully dynamic via `app('officeguy.customer_model')`.
- **No type-specific fulfillment handlers** — use `GenericFulfillmentHandler` + listen to `PayablePaid`.

### Common pitfalls

```
❌ app(DigitalProductFulfillmentHandler::class) — class removed
❌ $transaction->client   — @deprecated, use ->customer
❌ App\Models\Client       — never assume this model exists
❌ env() in code           — only use in config files; DB overrides config
❌ $response['Status'] === 0 — use SumitApiResponse::isSuccess() instead (handles both int 0 and "Success (0)" string)
❌ $transaction->confirmed_at — column is webhook_confirmed_at (confirmed_at does not exist)
❌ Missing sumit_entity_id on OfficeGuyTransaction — webhook confirmation breaks
❌ Missing order_type in createFromApiResponse() — polymorphic link breaks
```

### Git workflow (source → vendor → host)

```bash
# 1. Edit in source repo
cd /var/www/vhosts/nm-digitalhub.com/SUMIT-Payment-Gateway-for-laravel
# ... make changes, test ...

# 2. Commit + tag
git add . && git commit -m "feat: ..."
git tag -a v5.0.0-rcN -m "Release v5.0.0-rcN"
git push origin main && git push origin v5.0.0-rcN

# 3. Update host app
cd /var/www/vhosts/kalfa.me/httpdocs  # or nm-digitalhub.com/httpdocs
composer update officeguy/laravel-sumit-gateway
```

---

## Host Integration Contract

To integrate with the package, the host app must:

1. **Listen to `PayablePaid`** for fulfillment (not type-specific handlers)
2. **Configure `officeguy.models.customer`** in config to point to your User/Account model
3. **Register `officeguy.customer_model_class`** in DB settings if dynamic resolution needed
4. **Implement `Payable` contract** on payable models
5. **Configure notification routes** (`officeguy.notification_routes.*`) for admin links in DB notifications

For event invitations / table seating integration, see `INTEGRATION_API_SURFACE.md`.

---

*Regenerated from source code: v5.0.0-rc7 | Date: 2026-04-28*
