# Integration API Surface (5.0.0-rc1)

Public events and config keys for integrating the package in a **non-commerce** host (e.g. event invitations & table seating) without modifying core, adding domain assumptions, or reintroducing host coupling.

---

## Public events

Host applications listen to these events to implement fulfillment, order linking, and onboarding. The package dispatches them; it does **not** call host models, jobs, or enums.

| Event | Payload | Host responsibility |
|-------|---------|----------------------|
| **PayablePaid** | `OfficeGuyTransaction $transaction`, `Payable $payable` | Run fulfillment (e.g. confirm invitation, assign seat, send confirmation). Package never dispatches host jobs. |
| **DocumentSynced** | `OfficeGuyDocument $document`, `array $sumitPayload` | Optionally link document to host “order” (e.g. reservation); set `order_id` / `order_type` on document if desired. Package does not perform order lookup. |
| **GuestUserCreated** | `object $user`, `string $temporaryPassword`, `object $order` | Send welcome email or perform onboarding. Package does not send mail. |
| **PaymentCompleted** | Transaction + order IDs, payment details | Optional: sync state, notify user. |
| **PaymentFailed** | Transaction + failure info | Optional: notify user, retry logic. |
| **DocumentCreated** | `OfficeGuyDocument`, payload | Optional: store reference, notify. |
| **SubscriptionCreated** | `Subscription $subscription` | Optional: sync to host subscription state. |
| **SubscriptionCharged** / **SubscriptionChargesFailed** | Subscription + payment/failure | Optional: update host state. |
| **SubscriptionCancelled** | `Subscription`, reason | Optional: update host state. |
| **SuccessPageAccessed** | Payable, token | Optional: analytics, post-payment logic. |
| **SumitWebhookReceived** | `SumitWebhook` | Optional: custom webhook handling. |
| **BitPaymentCompleted** | Bit payment payload | Optional: sync Bit-specific state. |
| **MultiVendorPaymentCompleted** / **MultiVendorPaymentFailed** | Order ID, results | Optional: multi-vendor handling. |
| **UpsellPaymentCompleted** / **UpsellPaymentFailed** | Payable, transaction | Optional: upsell flow. |
| **WebhookCallSucceededEvent** / **WebhookCallFailedEvent** / **FinalWebhookCallFailedEvent** | Webhook call details | Optional: logging, retries. |
| **StockSynced** | Updated/skipped counts, data | Optional: sync host inventory. |

**Primary integration events for a minimal host:** `PayablePaid`, `DocumentSynced`, and (if using guest user creation) `GuestUserCreated`.

---

## Config keys required for host

No fallback to `App` namespace. No default model assumptions. If a key is not set, the package skips the related operation or uses a single default (e.g. one checkout view).

| Config key | Required | Purpose |
|-------------|----------|---------|
| **customer_model_class** / **models.customer** | Yes, for customer features | Eloquent model class for “customer” (e.g. `Invitee`, `User`). Resolved via DB → `officeguy.models.customer` → `officeguy.customer_model_class` → container `officeguy.customer_model`. If null: customer resolution skipped or returns null. |
| **staff_model** | Yes, for CRM owner/assigned & policy | Eloquent model class for staff/auth (e.g. `User`). Used by `CrmEntity::owner()`, `CrmEntity::assigned()`, `OfficeGuyTransactionPolicy`. If null: relations return empty; policy uses capability checks on passed user. |
| **order.model** | Yes, for AutoCreateUserListener / order resolution | Eloquent model class for “order” (e.g. `Reservation`, `Booking`). If null: AutoCreateUserListener and order-based flows skip. |
| **order.resolver** | No | Callable `(string|int $orderId): ?Payable`. Overrides order.model resolution when set. |
| **checkout.view_resolver** | No | Callable `(Request $request, Payable $payable): ?string` (view name). If set and returns existing view, that view is used. |
| **checkout.default_view** | No | Default checkout view when view_resolver is null or returns null. Default: `officeguy::pages.checkout`. |
| **sms_message_model** | No | Class name for SMS in debt collection (DebtService). If null: SMS step skipped. |
| **guest_user_model** | No | Auth model for guest user creation. Falls back to `staff_model` when null. |
| **guest_user_role** | No | Role value (string) for new guest users. No enum; host defines values. |
| **company_id**, **private_key**, **public_key** | Yes, for SUMIT API | SUMIT credentials. |

**Container:** The package also binds `officeguy.customer_model` (resolved from DB/config). Use that where code expects the customer model instance/class; do not rely on `App\Models\*`.

---

## Confirmation

- **No fallback to App namespace:** No references to `App\Models`, `App\Jobs`, or `App\Enums` in package core (verified in Phase 4.7).
- **No default model assumptions:** `customer_model_class`, `staff_model`, `order.model` have no default to `App\Models\Client` or similar; when not set, the package skips or uses null.

---

## Freeze rule (integration validation)

**No core changes during integration validation unless a hard bug is discovered.**

- Do not modify package `src/`, `config/`, or `routes/` for integration testing.
- Do not add back domain assumptions (e.g. esim, package, digital) or host coupling (e.g. `App\Models`, `constrained('clients')`).
- If a blocking bug is found in core, fix only that bug and document it; do not expand scope.

This branch (`release/5.0.0-rc1`) is intended for validating that the package works in a real non-commerce system (e.g. event invitations & table seating) under the above rule.
