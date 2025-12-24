# Phase 3 — PaymentService Execution-Only (Executed Changes)

## Scope (Actual Changes)
- ✅ PaymentService refactored to **execution-only** (no RequestHelpers, no PCI decision, no PaymentMethod building)
- ✅ Resolver extended to provide redirectMode (pci redirect + bit) and redirect URLs
- ✅ PublicCheckoutController now uses `processResolvedIntent()` for card flow
- ✅ SubscriptionService now passes `paymentMethodPayload`

> Note: Controller changes were required to preserve behavior after removing PaymentService’s request-based logic.

---

## Key Outcomes
- `PaymentService` **no longer accesses Request** or decides PCI mode.
- `PaymentService` **accepts PaymentMethod payload** built upstream.
- `PaymentService::processResolvedIntent()` now uses **redirect URLs** + **payment method payload** from `ResolvedPaymentIntent`.

---

## File-by-File Changes

### 1) PaymentService — Execution Only
**File:** `vendor/officeguy/laravel-sumit-gateway/src/Services/PaymentService.php`

**Removed**
- `RequestHelpers` import (no request access)
- PCI decision logic (`config('officeguy.pci')`)
- PaymentMethod building (SingleUseToken / PCI / Token)

**Updated method signatures**
- `buildChargeRequest()` now accepts:
  - `paymentMethodPayload` (?array)
  - `customerCitizenId` (?string)
- `processCharge()` now accepts:
  - `paymentMethodPayload` (?array)
  - `customerCitizenId` (?string)

**New behavior**
- If not redirect and `paymentMethodPayload` exists → set `PaymentMethod` directly
- Customer data now uses `customerCitizenId` param (no request lookup)

**processResolvedIntent()**
- Builds `$extra` from `redirectUrls` when `redirectMode` is true
- Passes `paymentMethodPayload` + `customerCitizenId`

---

### 2) ResolvedPaymentIntent — Execution Payload Extensions
**File:** `vendor/officeguy/laravel-sumit-gateway/src/DataTransferObjects/ResolvedPaymentIntent.php`

**Added fields**
- `pciMode`
- `paymentMethodPayload`
- `redirectUrls`
- `customerCitizenId`

---

### 3) CheckoutIntentResolver — Redirect + Payload Decisions
**File:** `vendor/officeguy/laravel-sumit-gateway/src/Services/CheckoutIntentResolver.php`

**Changes**
- `redirectMode` now true for:
  - Bit payments
  - PCI redirect (`pciMode === 'redirect'`)
- `redirectUrls` built when `redirectMode` is true
- `customerCitizenId` resolved from:
  - `payment.ogCitizenId` → `payment.citizenId` → `customer.citizenId`

---

### 4) PublicCheckoutController — Card Flow Uses Resolved Intent
**File:** `vendor/officeguy/laravel-sumit-gateway/src/Http/Controllers/PublicCheckoutController.php`

**Changes**
- After `PrepareCheckoutIntentAction`, now resolves intent:
  - `CheckoutIntentResolver::resolve($intent)`
- `processCardPayment()` now calls:
  - `PaymentService::processResolvedIntent($resolvedIntent)`
- Redirect handling now uses:
  - `$resolvedIntent->redirectMode`

---

### 5) SubscriptionService — Supplies PaymentMethod Payload
**File:** `vendor/officeguy/laravel-sumit-gateway/src/Services/SubscriptionService.php`

**Changes**
- Builds `paymentMethodPayload` from token:
  - `TokenService::getPaymentMethodFromToken($token)`
- Passes it into `PaymentService::processCharge()`

---

## Manual Diff (Phase 3 — package is not a git repo)
```diff
// ResolvedPaymentIntent
- public ?OfficeGuyToken $token,
+ public ?OfficeGuyToken $token,
+ public string $pciMode,
+ public ?array $paymentMethodPayload,
+ public ?array $redirectUrls,
+ public ?string $customerCitizenId,
+ public string $environment,
+ public string $locale,
```

```diff
// CheckoutIntentResolver
- $redirectMode = $intent->isBitPayment();
+ $redirectMode = $intent->isBitPayment() || $pciMode === 'redirect';
+ $redirectUrls = self::buildRedirectUrls($payable, $redirectMode);
+ $customerCitizenId = $intent->payment->ogCitizenId
+     ?? $intent->payment->citizenId
+     ?? $intent->customer->citizenId;
```

```diff
// PaymentService
- public static function buildChargeRequest(..., array $extra = []): array
+ public static function buildChargeRequest(..., array $extra = [], ?array $paymentMethodPayload = null, ?string $customerCitizenId = null): array

- 'Customer' => self::getOrderCustomer($order),
+ 'Customer' => self::getOrderCustomer($order, $customerCitizenId),

- if (!$redirectMode && $paymentMethodPayload !== null) {
-     $request['PaymentMethod'] = $paymentMethodPayload;
- }
+ if (!$redirectMode && $paymentMethodPayload !== null) {
+     $request['PaymentMethod'] = $paymentMethodPayload;
+ }

- public static function processCharge(..., array $extra = []): array
+ public static function processCharge(..., array $extra = [], ?array $paymentMethodPayload = null, ?string $customerCitizenId = null): array

+ public static function processResolvedIntent(ResolvedPaymentIntent $intent): array
+ {
+     $extra = [];
+     if ($intent->redirectMode && $intent->redirectUrls) {
+         $extra['RedirectURL'] = $intent->redirectUrls['success'] ?? null;
+         $extra['CancelRedirectURL'] = $intent->redirectUrls['cancel'] ?? null;
+     }
+     return self::processCharge(..., $extra, $intent->paymentMethodPayload, $intent->customerCitizenId);
+ }
```

```diff
// PublicCheckoutController
- $intent = app(PrepareCheckoutIntentAction::class)->execute($request, $payable);
- return $this->processCardPayment($payable, $validated, $paymentsCount, $request);
+ $intent = app(PrepareCheckoutIntentAction::class)->execute($request, $payable);
+ $resolvedIntent = CheckoutIntentResolver::resolve($intent);
+ return $this->processCardPayment($payable, $validated, $paymentsCount, $request, $resolvedIntent);

- $result = PaymentService::processCharge(...);
+ $result = PaymentService::processResolvedIntent($resolvedIntent);

- if ($redirectMode && isset($result['redirect_url'])) { ... }
+ if ($resolvedIntent->redirectMode && isset($result['redirect_url'])) { ... }
```

```diff
// SubscriptionService
- $result = PaymentService::processCharge(..., token: $token);
+ $paymentMethodPayload = $token ? TokenService::getPaymentMethodFromToken($token) : null;
+ $result = PaymentService::processCharge(..., token: $token, paymentMethodPayload: $paymentMethodPayload);
```

## Compliance Checklist (Phase 3)
- [x] PaymentService does NOT call `RequestHelpers`
- [x] PaymentService does NOT decide PCI mode
- [x] PaymentService does NOT build PaymentMethod
- [x] PaymentService consumes data from `ResolvedPaymentIntent`

---

## QA
- `vendor/bin/pint --dirty` executed and passed.

---

## Status
Phase 3 complete. Ready for review / approval before Phase 4.
