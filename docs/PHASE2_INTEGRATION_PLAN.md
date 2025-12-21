# Phase 2 - Integration Plan

> **Created**: 2025-12-18
> **Target**: Integrate CheckoutRequest + PrepareCheckoutIntentAction into PublicCheckoutController
> **Risk Level**: LOW (incremental changes, backward compatible)

---

## 🔍 Current State Analysis

### PublicCheckoutController::process() Flow

```
Line 145-154  → Debug logging
Line 156-165  → Feature check + Payable resolution
Line 170-206  → ❌ Inline validation (TO REPLACE)
Line 208-265  → Guest user creation (KEEP for now)
Line 267-286  → Payment token validation
Line 288-291  → Bit payment routing
Line 293-347  → Profile update logic (KEEP for now)
Line 350      → Card payment routing
```

---

## 🎯 Integration Strategy

### Step 1: Replace Inline Validation (SAFE)

**Current:**
```php
public function process(Request $request, string|int $id)
{
    // ... feature check, payable resolution ...

    $user = auth()->user();
    $client = $user?->client;

    $rules = [
        'customer_name' => 'required|string|max:255',
        // ... 20+ validation rules inline ...
    ];

    // Conditional validation based on client profile
    if (empty($client?->client_address)) {
        $rules['customer_address'] = 'required|string|max:255';
    }
    // ...

    $validated = $request->validate($rules);
}
```

**After:**
```php
public function process(CheckoutRequest $request, string|int $id)
{
    // ... feature check, payable resolution ...

    // ✅ Set payable for conditional validation
    $request->setPayable($payable);

    // ✅ Validation already done by CheckoutRequest!
    // No need for inline $rules anymore
}
```

**Changes:**
- Line 143: `Request $request` → `CheckoutRequest $request`
- After payable resolution: Add `$request->setPayable($payable)`
- Lines 167-206: **DELETE** (validation rules moved to CheckoutRequest)

**Risk:** LOW - setPayable() is clean (no merge, no input modification)

---

### Step 2: Add PrepareCheckoutIntentAction (NEW CODE)

**⚠️ CRITICAL: Intent creation AFTER guest user + profile update!**

**Why:** Guest user creation and profile update can modify customer data (name, phone, address).
If we create Intent before these steps, the Intent might have incomplete data.

**Correct order:**
```php
public function process(CheckoutRequest $request, string|int $id)
{
    // 1. Feature check, payable resolution
    // ... existing code ...

    // 2. Set payable for conditional validation
    $request->setPayable($payable);

    // 3. Guest user creation (unchanged)
    $user = auth()->user();
    if (!$user && $request->filled('password')) {
        // ... create user ...
    }

    // 4. Profile update (unchanged)
    $client = $user?->client;
    if ($client) {
        // ... update client profile ...
    }

    // 5. ✅ NEW: Prepare checkout intent + service data
    // NOW customer data is complete (after guest/profile updates)
    $intent = app(PrepareCheckoutIntentAction::class)->execute($request, $payable);

    // 6. Payment routing (use validated() not input()!)
    $validated = $request->validated();

    if ($validated['payment_method'] === 'bit') {
        return $this->processBitPayment($payable, $validated);
    }

    return $this->processCardPayment($payable, $validated, $paymentsCount, $request);
}
```

**What it does:**
1. Creates immutable CheckoutIntent from validated request (AFTER guest/profile updates)
2. Generates service-specific data (WHOIS, etc.)
3. Stores Intent + ServiceData in DB (separately!)

**Risk:** LOW - Intent is created at the right time, no data loss

---

### Step 3: Update CheckoutRequest to Handle Payable (CRITICAL)

**Problem:** CheckoutRequest needs `$payable` to determine address requirements

**Solution:** Inject payable before validation

```php
public function process(CheckoutRequest $request, string|int $id)
{
    // ... feature check ...

    $payable = $this->resolvePayable($request, $id);

    if (!$payable) {
        abort(404, __('Order not found'));
    }

    // ✅ Inject payable into request for validation
    $request->merge(['_payable' => $payable]);

    // ✅ Now validation can access payable via getPayable()
    // (Already implemented in CheckoutRequest::getPayable())

    // ✅ Prepare intent (after validation)
    $intent = app(PrepareCheckoutIntentAction::class)->execute($request, $payable);

    // ... rest of flow ...
}
```

**Risk:** LOW - `_payable` is internal, won't conflict with form data

---

## 📝 Implementation Checklist

### Phase 2.1: Minimal Integration (THIS PHASE)

- [ ] Change method signature: `Request` → `CheckoutRequest`
- [ ] Inject `$payable` into request: `$request->merge(['_payable' => $payable])`
- [ ] Delete inline validation rules (lines 167-206)
- [ ] Add `PrepareCheckoutIntentAction` call after payable resolution
- [ ] Keep guest user creation (unchanged)
- [ ] Keep profile update (unchanged)
- [ ] Keep payment routing (unchanged)

**Expected Result:**
- ✅ Validation moved to CheckoutRequest
- ✅ Intent + ServiceData stored in DB before payment
- ✅ No behavior change (backward compatible)
- ✅ All existing tests pass

### Phase 2.2: Guest User Action (LATER - Phase 3)

- [ ] Extract guest user creation to `CreateGuestUserAction`
- [ ] Replace lines 208-265 with Action call

### Phase 2.3: Profile Update Action (LATER - Phase 3)

- [ ] Extract profile update to `UpdateClientProfileAction`
- [ ] Replace lines 293-347 with Action call

### Phase 2.4: Payment Flow Integration (LATER - Phase 3)

- [ ] Pass `$intent` to `processCardPayment()`
- [ ] Pass `$intent` to `processBitPayment()`
- [ ] Use Intent data instead of `$validated` array

---

## 🔧 Code Changes (Phase 2.1)

### File: `PublicCheckoutController.php`

**Imports to add:**
```php
use OfficeGuy\LaravelSumitGateway\Http\Requests\CheckoutRequest;
use OfficeGuy\LaravelSumitGateway\Actions\PrepareCheckoutIntentAction;
```

**Method signature:**
```php
// Before:
public function process(Request $request, string|int $id)

// After:
public function process(CheckoutRequest $request, string|int $id)
```

**After payable resolution (line ~165):**
```php
// ✅ NEW: Inject payable for conditional validation
$request->merge(['_payable' => $payable]);

// ✅ NEW: Prepare checkout intent + service data
// This stores Intent + ServiceData in DB before payment
$intent = app(PrepareCheckoutIntentAction::class)->execute($request, $payable);
```

**Delete lines 167-206:**
```php
// ❌ DELETE THIS BLOCK (validation now in CheckoutRequest)
$user = auth()->user();
$client = $user?->client;

$rules = [
    'customer_name' => 'required|string|max:255',
    // ... all validation rules ...
];

// Require address fields if missing in profile
if (empty($client?->client_address)) {
    $rules['customer_address'] = 'required|string|max:255';
}
// ...

$validated = $request->validate($rules);
```

**Replace with:**
```php
// ✅ Validation already done by CheckoutRequest type-hint
// Access validated data via: $request->validated()

$user = auth()->user();
$client = $user?->client;
```

**Keep lines 208-347 unchanged:**
```php
// Guest user creation (unchanged)
if (!$user && !empty($request->input('password'))) {
    // ... existing code ...
}

// Profile update (unchanged)
if ($client) {
    // ... existing code ...
}
```

---

## ✅ Testing Checklist (Before Commit)

- [ ] Syntax check: `php -l src/Http/Controllers/PublicCheckoutController.php`
- [ ] Checkout page loads (no errors)
- [ ] Form validation works (required fields)
- [ ] Guest user registration works
- [ ] Logged-in user checkout works
- [ ] Bit payment works
- [ ] Card payment works
- [ ] PendingCheckout record created in DB
- [ ] Service data stored correctly
- [ ] No regressions in existing features

---

## 🎯 Success Criteria

**Phase 2.1 Complete When:**
1. ✅ CheckoutRequest used instead of inline validation
2. ✅ PrepareCheckoutIntentAction called before payment
3. ✅ Intent + ServiceData stored in DB
4. ✅ All existing functionality works
5. ✅ No breaking changes
6. ✅ Tests pass

**Commit Message:**
```
feat: integrate CheckoutRequest + PrepareCheckoutIntentAction

- Replaced inline validation with CheckoutRequest (FormRequest)
- Added PrepareCheckoutIntentAction to store Intent + ServiceData
- Intent + ServiceData now stored in DB before payment processing
- No behavior change (backward compatible)
- Guest user creation and profile update unchanged (Phase 3)

Ref: docs/PHASE2_INTEGRATION_PLAN.md
```

---

## 🚧 Known Issues / Edge Cases

1. **Session expiration during redirect**: Covered by DB storage
2. **Webhook callback**: Can retrieve from DB (not session-dependent)
3. **Guest user + saved card**: Works (user created before Intent preparation)
4. **Profile missing address**: Handled by CheckoutRequest conditional validation

---

## 📌 Notes for Phase 3

**What's NOT in this phase:**
- CreateGuestUserAction (keep inline for now)
- UpdateClientProfileAction (keep inline for now)
- Passing Intent to payment methods (keep $validated array)

**Why deferred:**
- Lower risk - one change at a time
- Easier to test and debug
- Can be done after Phase 2.1 is proven stable
