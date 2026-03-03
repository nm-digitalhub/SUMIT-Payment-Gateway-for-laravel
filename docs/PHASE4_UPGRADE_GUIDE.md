# Phase 4 — Host Boundary Elimination — Upgrade Guide

**Version:** 4.0.0  
**Breaking:** Yes. Host must configure models and listen to events.

## Summary of Changes

- **No default customer model.** `officeguy.customer_model_class` and container `officeguy.customer_model` have no fallback. Configure in config or Admin Panel.
- **No FK to host `clients` table.** Migrations use `unsignedBigInteger('client_id')` with index only. Upgrade migration drops existing FKs for existing installs.
- **Fulfillment is event-only.** The package no longer dispatches `ProcessPaidOrderJob` or references `App\Models\Order`. Listen to `PayablePaid` and run your own job.
- **Order linking** remains event-based: listen to `DocumentSynced` (see Phase 3).
- **Guest user creation** fires `GuestUserCreated`; host listens to send welcome email (no `App\Mail` reference).
- **Policy and CRM** use config/duck-typing: `officeguy.staff_model`, capability methods (`isStaff`, `isClient`, `isAdmin`, `isSuperAdmin`), no `App\Enums\UserRole`.

## Required Host Configuration

### 1. Customer model (required for customer features)

```php
// config/officeguy.php or Admin Panel → Office Guy Settings → Customer Management
'customer_model_class' => \App\Models\Client::class, // or your User/Customer model
```

Or set `OFFICEGUY_CUSTOMER_MODEL_CLASS` in `.env`.

### 2. Staff / auth model (required for CRM owner/assigned and policy)

```php
'staff_model' => \App\Models\User::class,
```

Or `OFFICEGUY_STAFF_MODEL` in `.env`. Your User model should implement capability methods: `isStaff()`, `isClient()`, `isAdmin()`, `isSuperAdmin()`, and optionally `isReseller()`.

### 3. Checkout (Phase 4.5: single show/process, host owns routing)

The package no longer defines product-type checkout routes (package/esim). Use the single `show` and `process` routes; pass the Payable via a route parameter `resolver` (callable that receives `$id` and returns a Payable instance) or configure `payable_model` in Admin Panel / settings. Host is responsible for registering any custom checkout URLs and binding the resolver.

### 4. Order model (for AutoCreateUserListener and similar)

```php
'order' => [
    'model' => \App\Models\Order::class,
],
```

Or `OFFICEGUY_ORDER_MODEL` in `.env`.

### 5. Optional: SMS model (debt collection) (for debt collection)

```php
'sms_message_model' => \App\Models\SmsMessage::class,
```

Or `OFFICEGUY_SMS_MESSAGE_MODEL`. If not set, SMS in debt collection is skipped.

## Event Listeners to Add in Host

### Fulfillment (replace ProcessPaidOrderJob dispatch)

```php
// App\Providers\EventServiceProvider or similar
use OfficeGuy\LaravelSumitGateway\Events\PayablePaid;

Event::listen(PayablePaid::class, function (PayablePaid $event) {
    $payable = $event->payable;
    if ($payable instanceof \App\Models\Order) {
        \App\Jobs\ProcessPaidOrderJob::dispatch($payable->id);
    }
});
```

### Guest welcome email (replace in-package mail)

```php
use OfficeGuy\LaravelSumitGateway\Events\GuestUserCreated;

Event::listen(GuestUserCreated::class, function (GuestUserCreated $event) {
    Mail::to($event->user->email)->queue(
        new \App\Mail\GuestWelcomeWithPasswordMail(
            $event->user,
            $event->temporaryPassword,
            $event->order
        )
    );
});
```

### Order linking (unchanged from Phase 3)

Listen to `DocumentSynced` and set `order_id` / `order_type` on the document as needed. See `docs/DOCUMENT_SYNC_ORDER_LINKING.md`.

## Migration

1. Run new migrations (including `2026_03_04_000001_drop_client_id_foreign_keys_phase4.php` if you had FKs to `clients`).
2. Set all config keys above (or env) so the package never relies on defaults for host models.
3. Register listeners for `PayablePaid` and `GuestUserCreated` (and keep `DocumentSynced` if you use it).
4. Ensure your User (or auth) model implements `isStaff()`, `isClient()`, `isAdmin()`, `isSuperAdmin()` (and optionally `isReseller()`).

## Phase 4.6 — Domain vocabulary elimination (breaking)

- **Fulfillment:** Type-specific handlers (Digital, Infrastructure, Subscription) were removed. All types use a single event-only handler that dispatches `PayablePaid`. Host must listen to `PayablePaid` for any product-specific fulfillment.
- **Checkout view:** View is no longer chosen by PayableType. Set `officeguy.checkout.view_resolver` (callable `(Request, Payable) -> ?string`). If unset, the package uses `officeguy.checkout.default_view` (single default).
- **Release:** If you relied on built-in product-specific fulfillment or type-based checkout views, treat this as a **breaking change**. Prefer **5.0.0** if that behavior was documented as stable; otherwise **4.x minor** (e.g. 4.1.0). See `docs/CORE_VS_HOST_RESPONSIBILITIES.md`.

## Backward Compatibility

- **Removed:** Default `App\Models\Client` in config and container fallbacks.
- **Removed:** Any dispatch of `App\Jobs\ProcessPaidOrderJob` or reference to `App\Models\Order` inside the package.
- **Removed:** `App\Enums\UserRole` and type-hint to `App\Models\User` in the policy.
- **Removed:** Foreign key constraints to the host `clients` table; column `client_id` remains, without FK.
- **Removed (4.6):** Type-specific fulfillment handlers; type-based checkout view selection.

Existing installs that already ran old migrations get the FK drop via the upgrade migration; new installs never create the FK.
