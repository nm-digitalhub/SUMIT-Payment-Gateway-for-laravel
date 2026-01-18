# CheckoutIntentResolver Analysis

**File**: `src/Services/CheckoutIntentResolver.php`
**Lines**: 152
**Type**: Static Service Class
**Purpose**: Bridge between checkout context (Intent) and payment execution (ResolvedIntent)

---

## Overview

CheckoutIntentResolver implements a **Bridge Pattern** that transforms a `CheckoutIntent` (checkout context with customer/payment preferences) into a `ResolvedPaymentIntent` (payment execution configuration with all runtime decisions made).

### Key Responsibilities

1. **PCI Mode Determination**: Decide payment flow based on configuration (no/redirect/yes)
2. **Redirect URL Building**: Generate success/cancel URLs for redirect mode
3. **Token Extraction**: Extract single-use token from PaymentsJS SDK
4. **Payment Method Resolution**: Build payment method payload based on PCI mode
5. **Subscription Detection**: Determine if payment is recurring
6. **Intent Resolution**: Create fully-configured ResolvedPaymentIntent

### Design Pattern: Bridge Pattern

**Definition**: Decouple abstraction from implementation so they can vary independently

**Implementation**:
```
CheckoutIntent (Abstraction)
├─ Customer preferences
├─ Payment choices
└─ Checkout context

       ↓ (Bridge)
CheckoutIntentResolver

       ↓
ResolvedPaymentIntent (Implementation)
├─ Runtime configuration
├─ Execution parameters
└─ Ready for PaymentService
```

**Benefits**:
- ✅ Separates checkout UI logic from payment execution
- ✅ Centralizes configuration resolution
- ✅ Makes payment execution testable (inject ResolvedPaymentIntent)
- ✅ Allows checkout context to evolve independently

---

## Class Structure

```php
namespace OfficeGuy\LaravelSumitGateway\Services;

use OfficeGuy\LaravelSumitGateway\DataTransferObjects\CheckoutIntent;
use OfficeGuy\LaravelSumitGateway\DataTransferObjects\ResolvedPaymentIntent;

class CheckoutIntentResolver
{
    // Main Resolution
    public static function resolve(CheckoutIntent $intent, ?Request $request = null): ResolvedPaymentIntent

    // Helper Methods (protected static)
    protected static function buildRedirectUrls(CheckoutIntent $intent): array
    protected static function buildPaymentMethodPayload(Request $request, string $pciMode): array
    protected static function isRecurringPayment(CheckoutIntent $intent): bool
}
```

**⚠️ Note**: Static service class (no state, pure transformation)

---

## Methods Analysis

### 1. `resolve()` - Transform Intent to ResolvedIntent

**Lines**: 34-77
**Signature**:
```php
public static function resolve(CheckoutIntent $intent, ?Request $request = null): ResolvedPaymentIntent
```

**Purpose**: Main resolver method that transforms checkout intent into execution-ready payment intent

**Parameters**:
- `$intent` - CheckoutIntent with customer/payment choices
- `$request` - HTTP request (optional, auto-resolves from `request()` helper)

**Process Flow**:
```
1. Get current request
   └─ $request ?? request()

2. Determine PCI mode
   └─ config('officeguy.pci_mode') → 'no'|'redirect'|'yes'

3. Build redirect URLs (if redirect mode)
   └─ success/cancel routes

4. Extract single-use token
   └─ $request->input('og-token')

5. Get saved token ID
   └─ $intent->payment->tokenId

6. Build payment method payload
   └─ Card details if PCI='yes', empty otherwise

7. Extract customer citizen ID
   └─ $intent->customer->citizenId

8. Determine if recurring
   └─ Check PayableType === 'SUBSCRIPTION'

9. Create ResolvedPaymentIntent
   └─ All parameters resolved and ready
```

**Implementation**:
```php
public static function resolve(CheckoutIntent $intent, ?Request $request = null): ResolvedPaymentIntent
{
    // Get current request if not provided
    $request = $request ?? request();

    // 1. Determine PCI mode from configuration
    $pciMode = config('officeguy.pci_mode', 'no');
    $redirectMode = $pciMode === 'redirect';

    // 2. Build redirect URLs if in redirect mode
    $redirectUrls = null;
    if ($redirectMode) {
        $redirectUrls = self::buildRedirectUrls($intent);
    }

    // 3. Extract single-use token from request (for PaymentsJS SDK)
    $singleUseToken = $request->input('og-token');

    // 4. Get saved token ID (if customer selected saved payment method)
    $savedToken = $intent->payment->tokenId;

    // 5. Build payment method payload
    $paymentMethodPayload = self::buildPaymentMethodPayload($request, $pciMode);

    // 6. Extract customer citizen ID (CompanyNumber in SUMIT API)
    $customerCitizenId = $intent->customer->citizenId;

    // 7. Determine if recurring (subscription)
    $recurring = self::isRecurringPayment($intent);

    // 8. Create resolved intent
    return new ResolvedPaymentIntent(
        payable: $intent->payable,
        paymentsCount: $intent->payment->installments,
        recurring: $recurring,
        redirectMode: $redirectMode,
        token: $savedToken,
        paymentMethodPayload: $paymentMethodPayload,
        singleUseToken: $singleUseToken,
        customerCitizenId: $customerCitizenId,
        redirectUrls: $redirectUrls,
        pciMode: $pciMode,
    );
}
```

**Example Usage**:
```php
// 1. Build checkout intent (from form data)
$checkoutIntent = CheckoutIntent::from([
    'payable' => $order,
    'customer' => [
        'firstName' => 'יוסי',
        'lastName' => 'כהן',
        'email' => 'yossi@example.com',
        'phone' => '0501234567',
        'citizenId' => '123456789',
    ],
    'payment' => [
        'installments' => 1,
        'tokenId' => null,  // New card
    ],
]);

// 2. Resolve intent
$resolvedIntent = CheckoutIntentResolver::resolve($checkoutIntent, $request);

// 3. Execute payment
$result = PaymentService::processCharge($resolvedIntent);
```

**Return Value**:
```php
ResolvedPaymentIntent {
    payable: Order,
    paymentsCount: 1,
    recurring: false,
    redirectMode: false,
    token: null,
    paymentMethodPayload: [],
    singleUseToken: 'tok_xyz123',
    customerCitizenId: '123456789',
    redirectUrls: null,
    pciMode: 'no',
}
```

**Critical Notes**:
- ✅ **Pure transformation** - no side effects
- ✅ **Auto-resolves request** - can call without request parameter
- ✅ **Configuration-driven** - reads pci_mode from config
- ⚠️ **Single-use token from request** - must be present in $request->input('og-token')

---

### 2. `buildRedirectUrls()` - Generate Redirect URLs

**Lines**: 85-97
**Signature**:
```php
protected static function buildRedirectUrls(CheckoutIntent $intent): array
```

**Purpose**: Build success/cancel URLs for PCI redirect mode

**Return Value**:
```php
[
    'success' => 'https://site.com/officeguy/callback/card?order=123',
    'cancel' => 'https://site.com/officeguy/callback/cancel?order=123',
]
```

**Implementation**:
```php
protected static function buildRedirectUrls(CheckoutIntent $intent): array
{
    $payableId = $intent->payable->getPayableId();

    return [
        'success' => route(config('officeguy.routes.callback_success', 'officeguy.callback.card'), [
            'order' => $payableId,
        ]),
        'cancel' => route(config('officeguy.routes.callback_cancel', 'officeguy.callback.cancel'), [
            'order' => $payableId,
        ]),
    ];
}
```

**Configuration**:
```php
// config/officeguy.php
'routes' => [
    'callback_success' => 'officeguy.callback.card',
    'callback_cancel' => 'officeguy.callback.cancel',
],
```

**Route Example**:
```php
// routes/officeguy.php
Route::get('/officeguy/callback/card', [CardCallbackController::class, 'handle'])
    ->name('officeguy.callback.card');

Route::get('/officeguy/callback/cancel', [CardCallbackController::class, 'cancel'])
    ->name('officeguy.callback.cancel');
```

**When Used**:
- Only when `pci_mode = 'redirect'`
- Customer redirected to SUMIT payment page
- SUMIT redirects back to success/cancel URLs

**Critical Notes**:
- ✅ Uses **configurable route names** (customizable in Admin Settings)
- ✅ Includes **order ID** in URL parameters
- ⚠️ Routes must be registered in `routes/officeguy.php`

---

### 3. `buildPaymentMethodPayload()` - Extract Card Details

**Lines**: 110-137
**Signature**:
```php
protected static function buildPaymentMethodPayload(Request $request, string $pciMode): array
```

**Purpose**: Build payment method payload based on PCI mode

**PCI Mode Logic**:
```
PCI Mode = 'no' (PaymentsJS SDK)
└─ Return [] (empty payload)
   └─ Single-use token used instead

PCI Mode = 'redirect'
└─ Return [] (empty payload)
   └─ SUMIT handles card entry

PCI Mode = 'yes' (Direct PCI)
└─ Extract card details from request
   ├─ CardNumber: $request->input('og-ccnum')
   ├─ CVV: $request->input('og-cvv')
   ├─ ExpirationMonth: $request->input('og-expmonth')
   └─ ExpirationYear: $request->input('og-expyear')
```

**Implementation**:
```php
protected static function buildPaymentMethodPayload(Request $request, string $pciMode): array
{
    if ($pciMode !== 'yes') {
        return [];  // No payload for 'no' and 'redirect' modes
    }

    // Extract card details for direct PCI mode
    $payload = [];

    if ($request->has('og-ccnum')) {
        $payload['CardNumber'] = $request->input('og-ccnum');
    }

    if ($request->has('og-cvv')) {
        $payload['CVV'] = $request->input('og-cvv');
    }

    if ($request->has('og-expmonth')) {
        $month = (int) $request->input('og-expmonth');
        $payload['ExpirationMonth'] = $month < 10 ? '0' . $month : (string) $month;
    }

    if ($request->has('og-expyear')) {
        $payload['ExpirationYear'] = $request->input('og-expyear');
    }

    return $payload;
}
```

**Request Field Names**:
```
og-ccnum → Card number (e.g., '4111111111111111')
og-cvv → CVV (e.g., '123')
og-expmonth → Expiration month (e.g., '12')
og-expyear → Expiration year (e.g., '2025')
```

**Payload Example (PCI='yes')**:
```php
[
    'CardNumber' => '4111111111111111',
    'CVV' => '123',
    'ExpirationMonth' => '12',
    'ExpirationYear' => '2025',
]
```

**Payload Example (PCI='no' or 'redirect')**:
```php
[]  // Empty array
```

**Critical Notes**:
- ⚠️ **PCI='yes' requires PCI DSS Level 1 certification** - card data passes through server
- ✅ **PCI='no' recommended** - card data never touches server (PaymentsJS SDK)
- ✅ **Month formatting** - ensures 2-digit format (01-12)

---

### 4. `isRecurringPayment()` - Detect Subscription

**Lines**: 145-151
**Signature**:
```php
protected static function isRecurringPayment(CheckoutIntent $intent): bool
```

**Purpose**: Determine if payment is for a subscription (recurring billing)

**Implementation**:
```php
protected static function isRecurringPayment(CheckoutIntent $intent): bool
{
    // Check if PayableType is SUBSCRIPTION
    $payableType = $intent->getPayableType();

    return $payableType->value === 'SUBSCRIPTION';
}
```

**Logic**:
```
PayableType === 'SUBSCRIPTION' → true (recurring)
PayableType !== 'SUBSCRIPTION' → false (one-time)
```

**Usage in PaymentService**:
```php
if ($resolvedIntent->recurring) {
    // Use SUMIT subscription API
    $response = SubscriptionService::create($payable, $token, $data);
} else {
    // Use SUMIT one-time payment API
    $response = PaymentService::processCharge($payable, $data);
}
```

**Critical Notes**:
- ✅ Simple check based on PayableType
- ⚠️ Assumes PayableType enum has 'SUBSCRIPTION' case
- ⚠️ ~~Hardcoded string comparison~~ → Could use enum comparison instead

---

## Data Transfer Objects

### CheckoutIntent (Input)

**File**: `src/DataTransferObjects/CheckoutIntent.php`

**Properties**:
```php
class CheckoutIntent
{
    public function __construct(
        public Payable $payable,              // Order, Product, etc.
        public CustomerData $customer,        // Customer info
        public PaymentPreferences $payment,   // Installments, token
    ) {}

    public function getPayableType(): PayableType
    {
        return $this->payable->getPayableType();
    }
}
```

**CustomerData**:
```php
class CustomerData
{
    public string $firstName;
    public string $lastName;
    public string $email;
    public string $phone;
    public ?string $citizenId;  // ID number (תעודת זהות)
    public ?string $company;
    // ... etc
}
```

**PaymentPreferences**:
```php
class PaymentPreferences
{
    public int $installments;     // Number of payments (1-36)
    public ?string $tokenId;      // Saved payment method ID
}
```

---

### ResolvedPaymentIntent (Output)

**File**: `src/DataTransferObjects/ResolvedPaymentIntent.php`

**Properties**:
```php
class ResolvedPaymentIntent
{
    public function __construct(
        public Payable $payable,
        public int $paymentsCount,                // Installments
        public bool $recurring,                   // Subscription?
        public bool $redirectMode,                // PCI redirect?
        public ?string $token,                    // Saved token ID
        public array $paymentMethodPayload,       // Card details (if PCI='yes')
        public ?string $singleUseToken,          // PaymentsJS token
        public ?string $customerCitizenId,       // ID number
        public ?array $redirectUrls,             // success/cancel URLs
        public string $pciMode,                  // 'no'|'redirect'|'yes'
    ) {}
}
```

**Usage in PaymentService**:
```php
public static function processCharge(ResolvedPaymentIntent $intent): array
{
    $payload = [
        'Credentials' => self::getCredentials(),
        'Amount' => $intent->payable->getPayableAmount(),
        'Payments_Count' => $intent->paymentsCount,
    ];

    if ($intent->redirectMode) {
        $payload['RedirectURL'] = $intent->redirectUrls['success'];
        $payload['CancelRedirectURL'] = $intent->redirectUrls['cancel'];
    }

    if ($intent->singleUseToken) {
        $payload['SingleUseToken'] = $intent->singleUseToken;
    }

    if (!empty($intent->paymentMethodPayload)) {
        $payload['PaymentMethod'] = $intent->paymentMethodPayload;
    }

    // ... execute payment
}
```

---

## PCI Modes Comparison

### Mode: 'no' (PaymentsJS SDK - Recommended)

**Flow**:
```
1. Checkout form loads PaymentsJS SDK
2. Customer enters card in hosted fields
3. SDK sends card to SUMIT → returns single-use token
4. Frontend submits token to server
5. CheckoutIntentResolver extracts token from request
6. PaymentService uses token for payment
```

**ResolvedPaymentIntent**:
```php
ResolvedPaymentIntent {
    pciMode: 'no',
    redirectMode: false,
    singleUseToken: 'tok_xyz123',  // ← From PaymentsJS
    paymentMethodPayload: [],      // ← Empty
    redirectUrls: null,            // ← Not needed
}
```

**Benefits**:
- ✅ No PCI compliance required
- ✅ Card data never touches server
- ✅ Supports all features (tokens, recurring, installments)

---

### Mode: 'redirect' (External SUMIT Page)

**Flow**:
```
1. Customer clicks "Pay"
2. Redirect to SUMIT payment page
3. Customer enters card on SUMIT site
4. SUMIT processes payment
5. Redirect back to success/cancel URL
6. Server receives transaction ID in callback
```

**ResolvedPaymentIntent**:
```php
ResolvedPaymentIntent {
    pciMode: 'redirect',
    redirectMode: true,            // ← Redirect flow
    singleUseToken: null,          // ← Not used
    paymentMethodPayload: [],      // ← Empty
    redirectUrls: [                // ← Required
        'success' => 'https://site.com/callback/card?order=123',
        'cancel' => 'https://site.com/callback/cancel?order=123',
    ],
}
```

**Benefits**:
- ✅ Simplest integration
- ✅ No PCI compliance required
- ❌ No recurring billing support
- ❌ No token storage

---

### Mode: 'yes' (Direct PCI - Certified Servers)

**Flow**:
```
1. Customer enters card in form fields
2. Frontend submits card details to server
3. Server receives raw card data
4. CheckoutIntentResolver extracts card from request
5. PaymentService sends card to SUMIT
```

**ResolvedPaymentIntent**:
```php
ResolvedPaymentIntent {
    pciMode: 'yes',
    redirectMode: false,
    singleUseToken: null,          // ← Not used
    paymentMethodPayload: [        // ← Card details
        'CardNumber' => '4111111111111111',
        'CVV' => '123',
        'ExpirationMonth' => '12',
        'ExpirationYear' => '2025',
    ],
    redirectUrls: null,            // ← Not needed
}
```

**Benefits**:
- ✅ Full control over flow
- ✅ Supports all features
- ⚠️ Requires PCI DSS Level 1 certification
- ⚠️ Card data passes through server

---

## Integration Example

### Complete Checkout Flow

```php
// 1. Controller receives checkout form submission
public function processCheckout(Request $request, Order $order)
{
    // 2. Build CheckoutIntent from form data
    $checkoutIntent = CheckoutIntent::from([
        'payable' => $order,
        'customer' => [
            'firstName' => $request->input('firstName'),
            'lastName' => $request->input('lastName'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'citizenId' => $request->input('citizenId'),
        ],
        'payment' => [
            'installments' => (int) $request->input('installments', 1),
            'tokenId' => $request->input('savedTokenId'),
        ],
    ]);

    // 3. Resolve intent (bridge to payment execution)
    $resolvedIntent = CheckoutIntentResolver::resolve($checkoutIntent, $request);

    // 4. Execute payment
    if ($resolvedIntent->recurring) {
        $result = SubscriptionService::create(
            $resolvedIntent->payable,
            $resolvedIntent->token,
            $resolvedIntent
        );
    } else {
        $result = PaymentService::processCharge($resolvedIntent);
    }

    // 5. Handle result
    if ($result['success']) {
        return redirect()->route('order.success', $order);
    }

    return back()->withErrors($result['message']);
}
```

---

## Testing Recommendations

### 1. Unit Tests

```php
use OfficeGuy\LaravelSumitGateway\Services\CheckoutIntentResolver;
use OfficeGuy\LaravelSumitGateway\DataTransferObjects\CheckoutIntent;

/** @test */
public function it_resolves_intent_with_paymentjs_token()
{
    $request = Request::create('/checkout', 'POST', [
        'og-token' => 'tok_test123',
    ]);

    $checkoutIntent = CheckoutIntent::from([
        'payable' => $this->order,
        'customer' => $this->customerData,
        'payment' => ['installments' => 1, 'tokenId' => null],
    ]);

    $resolved = CheckoutIntentResolver::resolve($checkoutIntent, $request);

    $this->assertEquals('tok_test123', $resolved->singleUseToken);
    $this->assertEquals('no', $resolved->pciMode);
    $this->assertFalse($resolved->redirectMode);
}
```

### 2. PCI Mode Tests

```php
/** @test */
public function it_builds_redirect_urls_in_redirect_mode()
{
    config(['officeguy.pci_mode' => 'redirect']);

    $checkoutIntent = CheckoutIntent::from([...]);

    $resolved = CheckoutIntentResolver::resolve($checkoutIntent);

    $this->assertTrue($resolved->redirectMode);
    $this->assertNotNull($resolved->redirectUrls);
    $this->assertStringContains('/callback/card', $resolved->redirectUrls['success']);
}
```

### 3. Card Details Extraction Test

```php
/** @test */
public function it_extracts_card_details_in_pci_yes_mode()
{
    config(['officeguy.pci_mode' => 'yes']);

    $request = Request::create('/checkout', 'POST', [
        'og-ccnum' => '4111111111111111',
        'og-cvv' => '123',
        'og-expmonth' => '12',
        'og-expyear' => '2025',
    ]);

    $checkoutIntent = CheckoutIntent::from([...]);

    $resolved = CheckoutIntentResolver::resolve($checkoutIntent, $request);

    $this->assertEquals([
        'CardNumber' => '4111111111111111',
        'CVV' => '123',
        'ExpirationMonth' => '12',
        'ExpirationYear' => '2025',
    ], $resolved->paymentMethodPayload);
}
```

---

## Best Practices

### ✅ DO

1. **Always resolve intent before payment**
   ```php
   $resolvedIntent = CheckoutIntentResolver::resolve($checkoutIntent, $request);
   $result = PaymentService::processCharge($resolvedIntent);
   ```

2. **Use CheckoutIntent DTO for checkout context**
   ```php
   $checkoutIntent = CheckoutIntent::from($formData);
   ```

3. **Pass request explicitly in tests**
   ```php
   $resolved = CheckoutIntentResolver::resolve($intent, $mockRequest);
   ```

4. **Check resolved mode before execution**
   ```php
   if ($resolvedIntent->redirectMode) {
       // Handle redirect flow
   } else {
       // Handle direct flow
   }
   ```

### ❌ DON'T

1. **Don't skip resolution**
   ```php
   // ❌ BAD - Manual construction
   $payload = ['Amount' => $order->total, ...];
   PaymentService::processCharge($payload);

   // ✅ GOOD - Use resolver
   $resolvedIntent = CheckoutIntentResolver::resolve($checkoutIntent);
   PaymentService::processCharge($resolvedIntent);
   ```

2. **Don't assume PCI mode**
   ```php
   // ❌ BAD - Hardcoded assumption
   $payload['SingleUseToken'] = $request->input('og-token');

   // ✅ GOOD - Let resolver decide
   $resolved = CheckoutIntentResolver::resolve($intent, $request);
   // Resolver extracts token based on PCI mode
   ```

3. **Don't build redirect URLs manually**
   ```php
   // ❌ BAD - Manual URL building
   $successUrl = url('/callback/card?order=' . $order->id);

   // ✅ GOOD - Let resolver build URLs
   $resolved = CheckoutIntentResolver::resolve($intent);
   $successUrl = $resolved->redirectUrls['success'];
   ```

---

## Summary

### Service Purpose
CheckoutIntentResolver is a **Bridge Pattern implementation** that transforms checkout context (CheckoutIntent) into execution-ready payment configuration (ResolvedPaymentIntent).

### Key Strengths
- ✅ **Centralized configuration resolution** - all PCI mode logic in one place
- ✅ **Pure transformation** - no side effects, easy to test
- ✅ **PCI mode abstraction** - checkout code doesn't need to know PCI mode
- ✅ **Type-safe DTOs** - CheckoutIntent → ResolvedPaymentIntent
- ✅ **Request auto-resolution** - can omit request parameter

### Design Pattern
- **Bridge Pattern** - Decouples checkout abstraction from payment implementation
- **Strategy Pattern** - Different resolution strategies per PCI mode

### Critical Implementation Notes
1. **Static service** - pure transformation, no state
2. **Configuration-driven** - reads pci_mode from config
3. **Request auto-resolution** - uses `request()` helper if not provided
4. **PCI mode logic** - 'no' uses token, 'yes' uses card details, 'redirect' uses URLs
5. **Subscription detection** - checks PayableType === 'SUBSCRIPTION'

### Integration Points
- Called from **CheckoutController** before payment execution
- Produces **ResolvedPaymentIntent** consumed by **PaymentService**
- Uses **CheckoutIntent** DTO from form data
- Reads **pci_mode** from configuration

---

**Lines Analyzed**: 152
**Methods Documented**: 4
**Design Pattern**: Bridge Pattern + Strategy Pattern
**Input**: CheckoutIntent DTO
**Output**: ResolvedPaymentIntent DTO
**Purpose**: Transform checkout context into payment execution configuration
