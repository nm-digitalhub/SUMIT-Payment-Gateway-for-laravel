# BitPaymentService Analysis

**File**: `src/Services/BitPaymentService.php`
**Lines**: 370
**Type**: Static Service Class
**Purpose**: Bit payment processing via SUMIT redirect flow (Israeli mobile payment method)

---

## Overview

BitPaymentService handles **Bit payments** (ביט), Israel's popular mobile payment app integration. Unlike credit card payments that can be processed directly, Bit requires a **redirect flow** where the customer completes payment on Bit's mobile app, then returns via webhook (IPN).

### Key Responsibilities

1. **Payment Initiation**: Redirect customers to Bit payment page via SUMIT
2. **IPN Processing**: Handle asynchronous webhooks from SUMIT when payment completes
3. **Idempotency Protection**: Prevent duplicate processing of SUMIT retries (up to 5 times)
4. **Security Validation**: Verify order key to prevent unauthorized webhook calls
5. **Zero Amount Handling**: Create documents for free orders without Bit redirect

### Bit Payment Flow

```
1. Customer selects Bit payment method
   ↓
2. BitPaymentService::processOrder() called
   ├─ Build SUMIT request with redirect URLs
   ├─ Call SUMIT: /billing/payments/beginredirect/
   └─ Receive RedirectURL to Bit payment page
   ↓
3. Customer redirected to Bit mobile app
   ├─ Approves payment in Bit app
   └─ Bit notifies SUMIT
   ↓
4. SUMIT sends webhook to your server (IPN)
   ├─ Includes: orderId, orderKey, documentId, customerId
   └─ SUMIT retries up to 5 times if no 200 OK
   ↓
5. BitPaymentService::processWebhook() called
   ├─ Validate order key (security)
   ├─ Check idempotency (Order + Transaction)
   ├─ Update Transaction status → 'completed'
   ├─ Update Order payment_status → 'paid'
   └─ Dispatch BitPaymentCompleted event
   ↓
6. Customer returns to success page
```

---

## Class Structure

```php
namespace OfficeGuy\LaravelSumitGateway\Services;

use OfficeGuy\LaravelSumitGateway\Contracts\Payable;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;

class BitPaymentService
{
    // 4 Public/Protected Static Methods
    public static function processOrder()
    public static function processWebhook()
    protected static function buildBitPaymentRequest()
    protected static function processZeroAmountOrder()
}
```

---

## Methods Analysis

### 1. `processOrder()` - Initiate Bit Payment

**Lines**: 33-143
**Signature**:
```php
public static function processOrder(
    Payable $order,
    string $successUrl,
    string $cancelUrl,
    string $webhookUrl
): array
```

**Purpose**: Initiate Bit payment redirect flow via SUMIT

**Parameters**:
- `$order` - Payable order instance
- `$successUrl` - URL to redirect after successful payment
- `$cancelUrl` - URL to redirect if customer cancels
- `$webhookUrl` - IPN endpoint for SUMIT webhooks

**Process Flow**:
```
1. Validate Bit payments enabled
   └─ config('officeguy.bit_enabled') must be true

2. Check for zero amount order
   └─ If $order->getPayableAmount() == 0 → processZeroAmountOrder()

3. Build secure IPN URL with orderkey
   ├─ Get order ID and order key from order
   ├─ Append: ?orderid=123&orderkey=abc123
   └─ Log warning if order key missing

4. Build SUMIT request
   └─ Call buildBitPaymentRequest()

5. Call SUMIT API: /billing/payments/beginredirect/
   └─ Request redirect to Bit payment page

6. Create pending transaction
   ├─ status: 'pending'
   ├─ payment_method: 'bit'
   └─ Store raw request/response

7. Return redirect URL
   └─ Customer will be redirected to Bit app
```

**Security Feature - Order Key Validation** (Lines 58-79):
```php
// ✅ FIX #6: Build IPN URL with orderkey (security!)
$orderId = $order->getPayableId();
$orderKey = method_exists($order, 'getOrderKey') ? $order->getOrderKey() : null;

// Log warning if orderkey missing (should not happen after migrations)
if (!$orderKey) {
    OfficeGuyApi::writeToLog(
        "Warning: Order {$orderId} has no order_key - webhook validation will fail!",
        'warning'
    );
}

// Build complete IPN URL with security parameters
$ipnUrl = $webhookUrl;
if (strpos($ipnUrl, '?') === false) {
    $ipnUrl .= '?';
} else {
    $ipnUrl .= '&';
}
$ipnUrl .= 'orderid=' . urlencode((string) $orderId);
if ($orderKey) {
    $ipnUrl .= '&orderkey=' . urlencode($orderKey);
}
```

**Configuration Check** (Lines 40-50):
```php
// ✅ FIX #9: Check if Bit payments are enabled
if (!config('officeguy.bit_enabled', false)) {
    OfficeGuyApi::writeToLog(
        'Bit payment attempt rejected: Bit payments are disabled via settings',
        'warning'
    );

    return [
        'success' => false,
        'message' => __('Bit payments are currently unavailable. Please choose another payment method.'),
    ];
}
```

**Success Response** (Lines 98-116):
```php
if ($response && $response['Status'] === 0 && isset($response['Data']['RedirectURL'])) {
    // Create pending transaction
    OfficeGuyTransaction::create([
        'order_id' => $order->getPayableId(),
        'order_type' => get_class($order),
        'amount' => $order->getPayableAmount(),
        'currency' => $order->getPayableCurrency(),
        'status' => 'pending',
        'payment_method' => 'bit',
        'raw_request' => $request,
        'raw_response' => $response,
        'environment' => $environment,
        'is_test' => config('officeguy.testing', false),
    ]);

    return [
        'success' => true,
        'redirect_url' => $response['Data']['RedirectURL'],
    ];
}
```

**Return Value**:
```php
// Success case
[
    'success' => true,
    'redirect_url' => 'https://bit-payment-page.sumit.co.il/...'
]

// Failure cases
[
    'success' => false,
    'message' => 'Payment failed - [error message]'
]
```

**Critical Notes**:
- ✅ Creates **pending** transaction BEFORE redirect (webhook will complete it)
- ✅ Order key included in IPN URL for security validation
- ⚠️ If order key missing, webhook will reject payment
- ✅ Validates bit_enabled config before processing

---

### 2. `processWebhook()` - Handle Bit IPN with Idempotency

**Lines**: 233-369
**Signature**:
```php
public static function processWebhook(
    string $orderId,
    string $orderKey,
    string $documentId,
    string $customerId,
    mixed $orderModel = null
): bool
```

**Purpose**: Process SUMIT Bit payment webhook with **dual-level idempotency protection**

**Parameters**:
- `$orderId` - Order ID from webhook
- `$orderKey` - Order key for security validation
- `$documentId` - SUMIT document ID (invoice/receipt)
- `$customerId` - SUMIT customer ID
- `$orderModel` - Optional order instance (for status update)

**Critical Idempotency Pattern** (Dual-Level Protection):

```
SUMIT Retry Behavior:
└─ Retries webhook up to 5 times if no 200 OK within 10 seconds
   └─ MUST return true immediately if already processed

Protection Layers:
1️⃣ Order-Level Idempotency (PRIMARY)
   └─ Check if Order.payment_status is already 'paid'

2️⃣ Transaction-Level Idempotency (SECONDARY)
   └─ Check if OfficeGuyTransaction.status is already 'completed'
```

**Process Flow**:
```
1. Validate order key (Security - FIX #3)
   ├─ Get actual order key from Order model
   └─ If mismatch → return false (reject webhook)

2. Order-Level Idempotency Check (FIX #8)
   ├─ Check if Order.payment_status in ['completed', 'paid', 'processing']
   └─ If already paid → return true (idempotent, prevent retries)

3. Transaction-Level Idempotency Check (FIX #1)
   ├─ Find Transaction by order_id + payment_method='bit'
   ├─ If status='completed' → return true (idempotent)
   └─ If status='pending' → proceed with update

4. DB Transaction (Atomic Update)
   ├─ Update OfficeGuyTransaction
   │  ├─ status = 'completed'
   │  ├─ completed_at = now()
   │  ├─ document_id = $documentId
   │  └─ customer_id = $customerId
   │
   ├─ Update Order (FIX #7)
   │  ├─ Call markAsPaid('bit') if method exists
   │  ├─ Or update payment_status = 'paid'
   │  └─ Set status = 'processing'
   │
   └─ Add note to transaction

5. Dispatch BitPaymentCompleted event
   └─ Triggers fulfillment listeners

6. Return true (success)
```

**Security Validation** (Lines 243-256):
```php
// ✅ FIX #3: Validate order key
if ($orderModel) {
    $actualOrderKey = method_exists($orderModel, 'getOrderKey')
        ? $orderModel->getOrderKey()
        : null;

    if ($actualOrderKey && $actualOrderKey !== $orderKey) {
        OfficeGuyApi::writeToLog(
            "Bit IPN rejected: Invalid order key for order $orderId",
            'error'
        );

        return false;  // ← REJECT webhook!
    }
}
```

**Order-Level Idempotency** (Lines 258-276):
```php
// ✅ FIX #8: Order-level idempotency check (like WooCommerce)
$orderPaymentStatus = null;
if (method_exists($orderModel, 'payment_status')) {
    $orderPaymentStatus = $orderModel->payment_status;
} elseif (isset($orderModel->payment_status)) {
    $orderPaymentStatus = $orderModel->payment_status;
}

// If Order already paid, don't process again
if (in_array($orderPaymentStatus, ['completed', 'paid', 'processing'])) {
    OfficeGuyApi::writeToLog(
        "Bit IPN ignored: Order $orderId already paid (status: $orderPaymentStatus) - idempotency check",
        'debug'
    );

    return true;  // ← Return true to prevent SUMIT retries
}
```

**Transaction-Level Idempotency** (Lines 278-293):
```php
// ✅ FIX #1: Transaction-level idempotency (secondary protection)
$transaction = OfficeGuyTransaction::where('order_id', $orderId)
    ->where('payment_method', 'bit')
    ->first();

if ($transaction) {
    // If transaction already completed, this is a retry
    if ($transaction->status === 'completed') {
        OfficeGuyApi::writeToLog(
            "Bit IPN ignored: Transaction #{$transaction->id} already completed - idempotency check",
            'debug'
        );

        return true;  // Already processed, idempotent
    }
}
```

**Atomic Update in DB Transaction** (Lines 297-348):
```php
\Illuminate\Support\Facades\DB::transaction(function () use ($transaction, $documentId, $customerId, $orderModel, $orderId) {
    // Update Transaction
    $transaction->update([
        'status' => 'completed',
        'completed_at' => now(),
        'document_id' => $documentId,
        'customer_id' => $customerId,
    ]);

    // ✅ FIX #7: Update Order status (not just Transaction!)
    if ($orderModel) {
        // Check if Order has markAsPaid method (recommended pattern)
        if (method_exists($orderModel, 'markAsPaid')) {
            $orderModel->markAsPaid('bit');
        }
        // Or direct update using Eloquent
        elseif ($orderModel instanceof \Illuminate\Database\Eloquent\Model) {
            $updateData = ['paid_at' => now()];

            // Update payment_status if field exists
            if (isset($orderModel->payment_status)) {
                $updateData['payment_status'] = 'paid';
            }

            // Update status if using OrderStatus enum
            if (isset($orderModel->status)) {
                $updateData['status'] = 'processing';
            }

            $orderModel->update($updateData);
        }

        // Add note if supported
        if (method_exists($orderModel, 'addNote')) {
            $orderModel->addNote(
                "Bit payment completed successfully.\n" .
                "Document ID: {$documentId}\n" .
                "Customer ID: {$customerId}"
            );
        }
    }

    // Add note to transaction
    $transaction->addNote("Bit payment completed. Document ID: $documentId, Customer ID: $customerId");

    // Dispatch event
    event(new \OfficeGuy\LaravelSumitGateway\Events\BitPaymentCompleted(
        $orderId,
        $documentId,
        $customerId
    ));
});
```

**Return Values**:
```php
true  → Webhook processed successfully (or idempotent)
false → Validation failed or logical error
```

**Critical Implementation Notes**:

1. **MUST Return `true` for Idempotent Cases**:
   - If already processed, return `true` to tell SUMIT "success, don't retry"
   - Returning `false` will cause SUMIT to retry (up to 5 times)

2. **Security is Critical**:
   - Order key validation prevents unauthorized webhook calls
   - Without order key validation, attacker could mark any order as paid

3. **Order Update is Essential** (FIX #7):
   - Original bug: Only updated Transaction, not Order
   - Fix: Updates BOTH Transaction and Order atomically

4. **Dual-Level Idempotency** (FIX #1 + #8):
   - Order-level check is PRIMARY (WooCommerce pattern)
   - Transaction-level check is SECONDARY (safety net)

---

### 3. `buildBitPaymentRequest()` - Build SUMIT Request

**Lines**: 179-210
**Signature**:
```php
protected static function buildBitPaymentRequest(
    Payable $order,
    string $successUrl,
    string $cancelUrl,
    string $webhookUrl
): array
```

**Purpose**: Build SUMIT API request for Bit redirect payment

**Request Structure**:
```php
[
    'Credentials' => PaymentService::getCredentials(),
    'Items' => PaymentService::getPaymentOrderItems($order),
    'VATIncluded' => 'true',
    'VATRate' => PaymentService::getOrderVatRate($order),
    'Customer' => PaymentService::getOrderCustomer($order),
    'AuthoriseOnly' => config('officeguy.testing', false) ? 'true' : 'false',
    'DraftDocument' => config('officeguy.draft_document', false) ? 'true' : 'false',
    'SendDocumentByEmail' => config('officeguy.email_document', true) ? 'true' : 'false',
    'DocumentDescription' => __('Order number') . ': ' . $order->getPayableId() .
        (empty($order->getCustomerNote()) ? '' : "\r\n" . $order->getCustomerNote()),
    'Payments_Count' => 1,
    'MaximumPayments' => 1,
    'DocumentLanguage' => PaymentService::getOrderLanguage(),
    'MerchantNumber' => config('officeguy.merchant_number'),
    'RedirectURL' => $successUrl,
    'CancelRedirectURL' => $cancelUrl,
    'AutomaticallyRedirectToProviderPaymentPage' => 'UpayBit',  // ← Bit-specific!
    'IPNURL' => $webhookUrl,  // ← Includes orderid and orderkey
]
```

**Key Fields**:
- `AutomaticallyRedirectToProviderPaymentPage: 'UpayBit'` - Tells SUMIT to use Bit gateway
- `IPNURL` - Webhook endpoint (already includes orderid + orderkey parameters)
- `RedirectURL` - Where customer returns after successful payment
- `CancelRedirectURL` - Where customer returns if they cancel

**Integration with PaymentService**:
```php
'Credentials' => PaymentService::getCredentials()
'Items' => PaymentService::getPaymentOrderItems($order)
'Customer' => PaymentService::getOrderCustomer($order)
'VATRate' => PaymentService::getOrderVatRate($order)
'DocumentLanguage' => PaymentService::getOrderLanguage()
```

---

### 4. `processZeroAmountOrder()` - Handle Free Orders

**Lines**: 152-168
**Signature**:
```php
protected static function processZeroAmountOrder(Payable $order, string $successUrl): array
```

**Purpose**: Create document for zero-amount orders without Bit redirect

**Process Flow**:
```
1. Get customer data from order
   └─ PaymentService::getOrderCustomer()

2. Create document via DocumentService
   └─ DocumentService::createOrderDocument($order, $customer, null)

3. Redirect to success page
   └─ No payment required, just generate invoice
```

**Return Value**:
```php
// Success (document created or not needed)
[
    'success' => true,
    'redirect_url' => $successUrl
]

// Failure (document creation error)
[
    'success' => false,
    'message' => 'Payment failed - [error message]'
]
```

**Use Cases**:
- Free products/services
- Promotional orders (100% discount)
- Trial subscriptions with $0 initial charge

**Critical Notes**:
- ✅ Skips Bit redirect entirely
- ✅ Still creates document for accounting
- ⚠️ Returns success even if document creation fails (graceful degradation)

---

## SUMIT API Endpoint

### Bit Redirect Initiation

**Endpoint**: `/billing/payments/beginredirect/`
**Method**: POST
**Purpose**: Initiate Bit payment redirect flow

**Request Example**:
```php
[
    'Credentials' => [
        'CompanyID' => '1234567',
        'APIPrivateKey' => 'xxx',
    ],
    'Items' => [
        ['Name' => 'Product', 'UnitPrice' => 100, 'Quantity' => 1]
    ],
    'Amount' => 100,
    'Customer' => [
        'FirstName' => 'יוסי',
        'LastName' => 'כהן',
        'Email' => 'yossi@example.com',
    ],
    'RedirectURL' => 'https://site.com/success',
    'CancelRedirectURL' => 'https://site.com/cancel',
    'AutomaticallyRedirectToProviderPaymentPage' => 'UpayBit',
    'IPNURL' => 'https://site.com/webhook/bit?orderid=123&orderkey=abc',
]
```

**Response Example**:
```php
[
    'Status' => 0,  // 0 = success
    'Data' => [
        'RedirectURL' => 'https://bit-payment-page.sumit.co.il/pay/...'
    ]
]
```

---

## Dependencies

### Service Dependencies

```
BitPaymentService
├─ PaymentService
│  ├─ getCredentials()
│  ├─ getPaymentOrderItems()
│  ├─ getOrderCustomer()
│  ├─ getOrderVatRate()
│  └─ getOrderLanguage()
├─ OfficeGuyApi
│  ├─ post()
│  └─ writeToLog()
└─ DocumentService
   └─ createOrderDocument()
```

### Model Dependencies

```
BitPaymentService → OfficeGuyTransaction
├─ create() - Create pending transaction before redirect
├─ update() - Complete transaction in webhook
└─ addNote() - Add payment notes
```

### Event Dependencies

```
Event Dispatched:
└─ BitPaymentCompleted
   ├─ Payload: orderId, documentId, customerId
   └─ Triggers: FulfillmentListener, DocumentSyncListener
```

---

## Security Model

### 1. Order Key Validation

**Purpose**: Prevent unauthorized webhook calls

**Implementation**:
```php
// Generate order key (in Order model)
$order->order_key = Str::random(32);

// Include in IPN URL
$ipnUrl = $webhookUrl . '?orderid=' . $orderId . '&orderkey=' . $orderKey;

// Validate in webhook
if ($actualOrderKey !== $orderKey) {
    return false;  // Reject unauthorized webhook
}
```

**Attack Prevention**:
```
❌ Without order key:
Attacker → POST /webhook/bit?orderid=123
         → Order #123 marked as paid (unauthorized!)

✅ With order key:
Attacker → POST /webhook/bit?orderid=123
         → Rejected (no valid order key)

SUMIT    → POST /webhook/bit?orderid=123&orderkey=abc123
         → Accepted (valid order key)
```

### 2. Idempotency Protection

**Purpose**: Prevent duplicate processing of SUMIT retries

**SUMIT Retry Behavior**:
- Sends webhook up to 5 times
- Retries if no 200 OK within 10 seconds
- Stops retrying after 200 OK response

**Protection Strategy**:
```php
// 1️⃣ Check Order status first (PRIMARY)
if (in_array($order->payment_status, ['completed', 'paid'])) {
    return true;  // Already processed, tell SUMIT success
}

// 2️⃣ Check Transaction status second (SECONDARY)
if ($transaction->status === 'completed') {
    return true;  // Already processed, tell SUMIT success
}

// 3️⃣ Process payment (first time only)
DB::transaction(function () {
    $transaction->update(['status' => 'completed']);
    $order->update(['payment_status' => 'paid']);
});
```

### 3. Environment Isolation

**Configuration**:
```php
config('officeguy.bit_enabled')      // Enable/disable Bit payments
config('officeguy.testing')          // Test mode (AuthoriseOnly)
config('officeguy.environment')      // 'www' or 'dev'
```

**Test Mode Protection**:
```php
'AuthoriseOnly' => config('officeguy.testing', false) ? 'true' : 'false'

// In test mode:
// - No real charges
// - AuthoriseOnly prevents actual billing
// - Marked with is_test flag in Transaction
```

---

## Error Handling

### 1. Bit Disabled

```php
if (!config('officeguy.bit_enabled', false)) {
    return [
        'success' => false,
        'message' => __('Bit payments are currently unavailable.')
    ];
}
```

### 2. Missing Order Key

```php
if (!$orderKey) {
    OfficeGuyApi::writeToLog(
        "Warning: Order {$orderId} has no order_key - webhook validation will fail!",
        'warning'
    );
    // Continue processing but log warning
}
```

### 3. SUMIT API Failure

```php
if ($response['Status'] !== 0) {
    OfficeGuyApi::writeToLog(
        'Bit payment failed: ' . ($response['UserErrorMessage'] ?? 'Unknown error'),
        'error'
    );

    return [
        'success' => false,
        'message' => __('Payment failed') . ' - ' . $response['UserErrorMessage']
    ];
}
```

### 4. Transaction Not Found

```php
// This should NEVER happen (transaction created before redirect)
if (!$transaction) {
    OfficeGuyApi::writeToLog(
        "Bit IPN error: No transaction found for order $orderId",
        'error'
    );

    return false;  // Logical error
}
```

---

## Performance Considerations

### 1. Webhook Response Time

**Critical**: SUMIT expects 200 OK within 10 seconds

```php
// ✅ GOOD - Process in webhook (fast)
public static function processWebhook(...): bool
{
    // Lightweight checks (< 1 second)
    if (already processed) return true;

    // Atomic DB update (< 1 second)
    DB::transaction(function () {
        $transaction->update([...]);
        $order->update([...]);
    });

    return true;  // Return quickly
}

// ❌ BAD - Heavy processing in webhook
public static function processWebhook(...): bool
{
    sendEmailToCustomer();        // Slow!
    generatePDFInvoice();         // Slow!
    callExternalAPI();            // Slow!
    // SUMIT times out, retries 5 times
}
```

**Solution**: Use queued jobs for heavy work
```php
// In processWebhook():
dispatch(new SendBitPaymentEmailJob($order));
dispatch(new GenerateInvoicePdfJob($transaction));

return true;  // Return immediately
```

### 2. Race Condition Protection

**Problem**: Multiple SUMIT retries arrive simultaneously

**Solution**: Database transaction with row locking
```php
DB::transaction(function () use ($orderId) {
    // Lock row for update
    $transaction = OfficeGuyTransaction::where('order_id', $orderId)
        ->lockForUpdate()
        ->first();

    if ($transaction->status === 'completed') {
        return;  // Already processed by concurrent request
    }

    $transaction->update(['status' => 'completed']);
});
```

---

## Testing Recommendations

### 1. Unit Tests

```php
/** @test */
public function it_redirects_to_bit_payment_page()
{
    Http::fake([
        'api.sumit.co.il/billing/payments/beginredirect/' => Http::response([
            'Status' => 0,
            'Data' => ['RedirectURL' => 'https://bit.sumit.co.il/pay/123']
        ], 200),
    ]);

    $result = BitPaymentService::processOrder(
        $this->order,
        'https://site.com/success',
        'https://site.com/cancel',
        'https://site.com/webhook/bit'
    );

    $this->assertTrue($result['success']);
    $this->assertStringContains('https://bit.sumit.co.il', $result['redirect_url']);

    // Check pending transaction created
    $transaction = OfficeGuyTransaction::latest()->first();
    $this->assertEquals('pending', $transaction->status);
    $this->assertEquals('bit', $transaction->payment_method);
}
```

### 2. Webhook Idempotency Test

```php
/** @test */
public function it_handles_duplicate_webhooks_idempotently()
{
    $transaction = OfficeGuyTransaction::factory()->create([
        'status' => 'pending',
        'payment_method' => 'bit',
    ]);

    // First webhook call
    $result1 = BitPaymentService::processWebhook(
        $transaction->order_id,
        $this->order->order_key,
        'DOC123',
        'CUST456',
        $this->order
    );

    $this->assertTrue($result1);
    $transaction->refresh();
    $this->assertEquals('completed', $transaction->status);

    // Second webhook call (SUMIT retry)
    $result2 = BitPaymentService::processWebhook(
        $transaction->order_id,
        $this->order->order_key,
        'DOC123',
        'CUST456',
        $this->order
    );

    $this->assertTrue($result2);  // Still returns true
    // Transaction not updated again (idempotent)
}
```

### 3. Order Key Security Test

```php
/** @test */
public function it_rejects_webhook_with_invalid_order_key()
{
    $transaction = OfficeGuyTransaction::factory()->create([
        'status' => 'pending',
        'payment_method' => 'bit',
    ]);

    $result = BitPaymentService::processWebhook(
        $transaction->order_id,
        'WRONG_ORDER_KEY',  // Invalid order key
        'DOC123',
        'CUST456',
        $this->order
    );

    $this->assertFalse($result);  // Webhook rejected

    // Transaction remains pending
    $transaction->refresh();
    $this->assertEquals('pending', $transaction->status);
}
```

---

## Best Practices

### ✅ DO

1. **Always include order key in IPN URL**
   ```php
   $ipnUrl .= '?orderid=' . $orderId . '&orderkey=' . $orderKey;
   ```

2. **Return `true` for idempotent webhooks**
   ```php
   if ($transaction->status === 'completed') {
       return true;  // Tell SUMIT "success, don't retry"
   }
   ```

3. **Update BOTH Transaction and Order**
   ```php
   DB::transaction(function () {
       $transaction->update(['status' => 'completed']);
       $order->update(['payment_status' => 'paid']);
   });
   ```

4. **Use database transactions for atomic updates**
   ```php
   DB::transaction(function () {
       // All updates or none
   });
   ```

5. **Queue heavy work after webhook**
   ```php
   dispatch(new SendEmailJob($order));
   return true;  // Return immediately
   ```

### ❌ DON'T

1. **Don't skip order key validation**
   ```php
   // ❌ BAD - No security!
   BitPaymentService::processWebhook($orderId, '', $docId, $custId);

   // ✅ GOOD - Validate order key
   if ($actualOrderKey !== $orderKey) {
       return false;
   }
   ```

2. **Don't return `false` for idempotent cases**
   ```php
   // ❌ BAD - Causes SUMIT retries!
   if ($transaction->status === 'completed') {
       return false;
   }

   // ✅ GOOD - Tell SUMIT success
   if ($transaction->status === 'completed') {
       return true;
   }
   ```

3. **Don't process heavy work in webhook**
   ```php
   // ❌ BAD - Times out!
   public function processWebhook() {
       sendEmail();
       generatePDF();
       return true;
   }

   // ✅ GOOD - Queue heavy work
   public function processWebhook() {
       dispatch(new SendEmailJob());
       return true;
   }
   ```

4. **Don't forget to create pending transaction before redirect**
   ```php
   // ❌ BAD - No transaction exists for webhook
   return ['redirect_url' => $url];

   // ✅ GOOD - Create pending transaction first
   OfficeGuyTransaction::create(['status' => 'pending']);
   return ['redirect_url' => $url];
   ```

---

## Integration Points

### With Controller

**File**: `src/Http/Controllers/BitWebhookController.php`

```php
use OfficeGuy\LaravelSumitGateway\Services\BitPaymentService;

public function handle(Request $request)
{
    $orderId = $request->input('orderid');
    $orderKey = $request->input('orderkey');
    $documentId = $request->input('documentid');
    $customerId = $request->input('customerid');

    $order = Order::find($orderId);

    $success = BitPaymentService::processWebhook(
        $orderId,
        $orderKey,
        $documentId,
        $customerId,
        $order
    );

    return response()->json(['success' => $success], $success ? 200 : 400);
}
```

### With Checkout Flow

```php
// In checkout form:
if ($paymentMethod === 'bit') {
    $result = BitPaymentService::processOrder(
        $order,
        route('checkout.success', $order),
        route('checkout.cancel', $order),
        route('webhook.bit')
    );

    if ($result['success']) {
        return redirect($result['redirect_url']);
    }

    return back()->withErrors($result['message']);
}
```

---

## Configuration Dependencies

### Required Settings

```php
config('officeguy.bit_enabled')          // true/false - Enable Bit payments
config('officeguy.merchant_number')      // SUMIT merchant number for Bit
config('officeguy.company_id')           // SUMIT company ID
config('officeguy.private_key')          // API private key
config('officeguy.email_document')       // Send invoice by email (true/false)
config('officeguy.draft_document')       // Create draft documents (true/false)
config('officeguy.testing')              // Test mode (AuthoriseOnly)
config('officeguy.environment')          // 'www' or 'dev'
```

---

## Complete Service Example

### Initiating Bit Payment in Controller

```php
use OfficeGuy\LaravelSumitGateway\Services\BitPaymentService;

public function checkout(Request $request, Order $order)
{
    // Customer selected Bit payment method
    $result = BitPaymentService::processOrder(
        $order,
        route('order.success', $order),          // Success URL
        route('order.cancel', $order),           // Cancel URL
        route('webhook.bit')                     // Webhook URL
    );

    if ($result['success']) {
        // Redirect to Bit payment page
        return redirect($result['redirect_url']);
    }

    // Payment initiation failed
    return back()->withErrors($result['message']);
}
```

### Handling Bit Webhook in Controller

```php
use OfficeGuy\LaravelSumitGateway\Services\BitPaymentService;

public function webhook(Request $request)
{
    $orderId = $request->input('orderid');
    $orderKey = $request->input('orderkey');
    $documentId = $request->input('documentid');
    $customerId = $request->input('customerid');

    // Find order
    $order = Order::find($orderId);

    if (!$order) {
        return response()->json(['error' => 'Order not found'], 404);
    }

    // Process webhook with idempotency protection
    $success = BitPaymentService::processWebhook(
        $orderId,
        $orderKey,
        $documentId,
        $customerId,
        $order
    );

    // Return 200 OK to SUMIT (prevent retries)
    return response()->json([
        'success' => $success,
        'message' => $success ? 'Webhook processed' : 'Validation failed'
    ], $success ? 200 : 400);
}
```

---

## Summary

### Service Purpose
BitPaymentService handles **Bit mobile payment integration** via SUMIT redirect flow with robust idempotency protection and security validation.

### Key Strengths
- ✅ Dual-level idempotency (Order + Transaction)
- ✅ Order key validation for security
- ✅ Zero amount order support
- ✅ Atomic database updates
- ✅ Graceful handling of SUMIT retries (up to 5 times)

### Critical Implementation Notes
1. **Order key is MANDATORY** - Without it, webhooks can be spoofed
2. **Idempotency is CRITICAL** - SUMIT retries up to 5 times
3. **Return `true` for duplicates** - Tells SUMIT "stop retrying"
4. **Create pending transaction BEFORE redirect** - Webhook needs it
5. **Update Order AND Transaction** - Both must reflect payment status
6. **Queue heavy work** - Webhook must respond within 10 seconds

### Fixes Implemented
- **FIX #1**: Transaction-level idempotency (checks completed status)
- **FIX #3**: Order key validation (security)
- **FIX #6**: IPN URL includes orderkey parameter
- **FIX #7**: Order status update (not just Transaction)
- **FIX #8**: Order-level idempotency (primary check)
- **FIX #9**: Enforce bit_enabled setting

---

**Lines Analyzed**: 370
**Methods Documented**: 4
**Dependencies**: PaymentService, OfficeGuyApi, DocumentService
**Events**: 1 (BitPaymentCompleted)
**SUMIT Endpoints**: 1 (/billing/payments/beginredirect/)
**Security Features**: Order key validation, dual-level idempotency
