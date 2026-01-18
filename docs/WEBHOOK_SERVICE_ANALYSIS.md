# WebhookService Analysis

**File**: `src/Services/WebhookService.php`
**Lines**: 245
**Type**: Instance Service Class (uses dependency injection)
**Purpose**: Send outgoing webhook notifications to external systems for package events

---

## Overview

WebhookService manages **outgoing webhooks** - sending HTTP notifications to external systems when important events occur (payment completed, document created, subscription charged, etc.). This allows developers to integrate with the package without writing custom event Listeners.

### Key Distinction

⚠️ **Important**: This service handles **OUTGOING webhooks** (package → external systems)

```
WebhookService (THIS FILE)
├─ Sends notifications TO external systems
├─ Triggered by package events
└─ Configurable via Admin Settings Page

vs.

BitWebhookController / SumitWebhookController
├─ Receives notifications FROM SUMIT
├─ Triggered by SUMIT events
└─ Validates incoming webhook signatures
```

### Key Responsibilities

1. **Event Notification**: Send HTTP POST requests to configured webhook URLs
2. **Async Processing**: Queue webhooks for non-blocking execution (recommended)
3. **Signature Generation**: HMAC-SHA256 signatures for security
4. **Retry Logic**: Automatic retry for failed webhooks with exponential backoff
5. **Event Logging**: Track all webhook attempts in database
6. **Multi-Event Support**: 7+ event types with dedicated methods

---

## Class Structure

```php
namespace OfficeGuy\LaravelSumitGateway\Services;

use OfficeGuy\LaravelSumitGateway\Models\WebhookEvent;

class WebhookService
{
    protected SettingsService $settings;  // ← Instance property!

    public function __construct(SettingsService $settings)
    {
        $this->settings = $settings;
    }

    // Core Methods
    public function send()
    protected function sendAsync()
    protected function sendSync()
    protected function generateSignature()
    public static function verifySignature()

    // Event-Specific Methods (7)
    public function sendPaymentCompleted()
    public function sendPaymentFailed()
    public function sendDocumentCreated()
    public function sendSubscriptionCreated()
    public function sendSubscriptionCharged()
    public function sendBitPaymentCompleted()
    public function sendStockSynced()

    // Retry Logic
    public function retryFailedEvents()
}
```

**⚠️ Important**: Unlike most other Services in the package, WebhookService is **NOT static** - it uses dependency injection and instance methods.

---

## Methods Analysis

### 1. `send()` - Main Webhook Dispatcher

**Lines**: 35-56
**Signature**:
```php
public function send(string $event, array $payload, array $options = []): bool
```

**Purpose**: Send webhook notification for any event (async or sync)

**Parameters**:
- `$event` - Event name (e.g., 'payment_completed')
- `$payload` - Data to send in webhook body
- `$options` - Additional options:
  - `async` - Send via queue (default: true)
  - `transaction_id` - Transaction ID for tracking
  - `document_id` - Document ID for tracking
  - `webhook_url` - Override URL (optional)

**Process Flow**:
```
1. Get webhook URL from settings
   └─ config key: "webhook_{$event}"
   └─ Example: webhook_payment_completed

2. Check if URL configured
   └─ If empty → log and return false

3. Determine send method
   ├─ async = true → sendAsync() (recommended)
   └─ async = false → sendSync() (legacy)

4. Return success status
```

**URL Configuration Lookup**:
```php
// Get webhook URL for event
$url = $this->settings->get("webhook_{$event}");
// Examples:
// - webhook_payment_completed
// - webhook_document_created
// - webhook_subscription_charged

if (empty($url)) {
    $this->log('info', "No webhook URL configured for event", ['event' => $event]);
    return false;
}
```

**Async vs Sync Decision**:
```php
$async = $options['async'] ?? config('officeguy.webhooks.async', true);

if ($async) {
    // New queue-based approach (recommended)
    return $this->sendAsync($event, $payload, $meta);
}

// Legacy synchronous approach
return $this->sendSync($event, $payload, $meta);
```

**Return Value**:
```php
true  → Webhook sent successfully (or queued)
false → No URL configured or send failed
```

**Usage Example**:
```php
$webhookService = app(WebhookService::class);

$webhookService->send('payment_completed', [
    'transaction_id' => 123,
    'order_id' => 456,
    'amount' => 100.00,
    'currency' => 'ILS',
], [
    'async' => true,  // Use queue
    'transaction_id' => 123,
]);
```

---

### 2. `sendAsync()` - Queue-Based Sending (Recommended)

**Lines**: 66-88
**Signature**:
```php
protected function sendAsync(string $event, array $payload, array $meta): bool
```

**Purpose**: Queue webhook for asynchronous sending via job queue

**Process Flow**:
```
1. Create WebhookCall instance
   └─ Uses WebhookCall class (Spatie webhook-client wrapper)

2. Configure webhook
   ├─ useSettingsForEvent($event) → Get URL and settings
   ├─ payload($payload) → Set webhook body
   └─ meta($meta) → Attach metadata

3. Dispatch to queue
   └─ dispatch() → Queue job for background processing

4. Log success and return true
```

**Implementation**:
```php
try {
    \OfficeGuy\LaravelSumitGateway\WebhookCall::create()
        ->useSettingsForEvent($event)  // Get webhook_payment_completed URL
        ->payload($payload)             // Data to send
        ->meta($meta)                   // Metadata (transaction_id, etc.)
        ->dispatch();                   // Queue job

    $this->log('info', "Webhook queued successfully", [
        'event' => $event,
    ]);

    return true;
} catch (\Exception $e) {
    $this->log('error', "Failed to queue webhook: {$e->getMessage()}", [
        'event' => $event,
        'exception' => $e->getMessage(),
    ]);

    return false;
}
```

**Advantages of Async**:
- ✅ Non-blocking - doesn't slow down payment processing
- ✅ Automatic retry via queue
- ✅ Exponential backoff on failures
- ✅ Can be monitored via queue dashboard

**Queue Configuration**:
```php
// config/queue.php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => null,
    ],
],
```

---

### 3. `sendSync()` - Immediate Sending (Legacy)

**Lines**: 98-114
**Signature**:
```php
protected function sendSync(string $event, array $payload, array $meta): bool
```

**Purpose**: Send webhook immediately (blocking, not recommended for production)

**Process Flow**:
```
1. Create WebhookCall instance
2. Configure webhook (same as async)
3. Execute immediately via dispatchSync()
   └─ Blocks current request until HTTP call completes
4. Return success/failure
```

**Implementation**:
```php
try {
    return \OfficeGuy\LaravelSumitGateway\WebhookCall::create()
        ->useSettingsForEvent($event)
        ->payload($payload)
        ->meta($meta)
        ->dispatchSync();  // ← Executes IMMEDIATELY (blocking)
} catch (\Exception $e) {
    $this->log('error', "Webhook error: {$e->getMessage()}", [
        'event' => $event,
        'exception' => $e->getMessage(),
    ]);

    return false;
}
```

**Disadvantages of Sync**:
- ❌ Blocking - slows down payment processing
- ❌ If webhook endpoint is slow, user waits
- ❌ If webhook fails, no automatic retry
- ❌ Single point of failure

**When to Use Sync**:
- Testing/development only
- When you need immediate confirmation
- When async queue is not available

---

### 4. `generateSignature()` - HMAC Signature Generation

**Lines**: 122-131
**Signature**:
```php
protected function generateSignature(array $payload): string
```

**Purpose**: Generate HMAC-SHA256 signature for webhook payload security

**Process Flow**:
```
1. Get webhook secret from settings
   └─ config key: webhook_secret

2. If no secret configured
   └─ Return empty string (no signature)

3. Generate HMAC-SHA256
   └─ hash_hmac('sha256', json_encode($payload), $secret)

4. Return signature
```

**Implementation**:
```php
protected function generateSignature(array $payload): string
{
    $secret = $this->settings->get('webhook_secret', '');

    if (empty($secret)) {
        return '';  // No signature if no secret
    }

    return hash_hmac('sha256', json_encode($payload), $secret);
}
```

**Security Purpose**:
```
External System (Receives Webhook):
1. Receives payload + signature in header
2. Recomputes signature using shared secret
3. Compares signatures using hash_equals()
4. If match → webhook is authentic
5. If mismatch → reject (potential attack)
```

**Webhook Header**:
```http
POST /webhook-endpoint HTTP/1.1
Host: external-system.com
Content-Type: application/json
X-Webhook-Signature: a3f8d2c1b4e6... (HMAC-SHA256)

{"transaction_id": 123, "amount": 100}
```

---

### 5. `verifySignature()` - Signature Verification

**Lines**: 140-148
**Signature**:
```php
public static function verifySignature(string $signature, array $payload, string $secret): bool
```

**Purpose**: Verify webhook signature (for receiving webhooks FROM other systems)

**Parameters**:
- `$signature` - Signature from webhook header
- `$payload` - Webhook payload body
- `$secret` - Shared secret key

**Implementation**:
```php
public static function verifySignature(string $signature, array $payload, string $secret): bool
{
    if (empty($secret) || empty($signature)) {
        return false;  // Cannot verify without secret/signature
    }

    $expectedSignature = hash_hmac('sha256', json_encode($payload), $secret);
    return hash_equals($expectedSignature, $signature);  // ← Timing-safe comparison
}
```

**Usage in Controller**:
```php
public function receiveWebhook(Request $request)
{
    $signature = $request->header('X-Webhook-Signature');
    $payload = $request->json()->all();
    $secret = config('officeguy.webhook_secret');

    if (!WebhookService::verifySignature($signature, $payload, $secret)) {
        return response()->json(['error' => 'Invalid signature'], 401);
    }

    // Process webhook...
}
```

**Security Note**:
- ✅ Uses `hash_equals()` - timing-safe comparison (prevents timing attacks)
- ❌ Never use `===` for signature comparison (vulnerable to timing attacks)

---

## Event-Specific Methods

### 6. `sendPaymentCompleted()` - Payment Success Webhook

**Lines**: 153-156
```php
public function sendPaymentCompleted(array $data, array $options = []): bool
{
    return $this->send('payment_completed', $data, $options);
}
```

**Payload Example**:
```php
[
    'transaction_id' => 123,
    'order_id' => 456,
    'amount' => 100.00,
    'currency' => 'ILS',
    'payment_method' => 'credit_card',
    'document_id' => 789,
    'customer' => [
        'id' => 999,
        'email' => 'customer@example.com',
    ],
]
```

**Triggered By**: `PaymentCompleted` event

---

### 7. `sendPaymentFailed()` - Payment Failure Webhook

**Lines**: 161-164
```php
public function sendPaymentFailed(array $data, array $options = []): bool
{
    return $this->send('payment_failed', $data, $options);
}
```

**Payload Example**:
```php
[
    'transaction_id' => 123,
    'order_id' => 456,
    'amount' => 100.00,
    'error_code' => '051',
    'error_message' => 'Insufficient funds',
]
```

**Triggered By**: `PaymentFailed` event

---

### 8. `sendDocumentCreated()` - Invoice/Receipt Created Webhook

**Lines**: 169-172
```php
public function sendDocumentCreated(array $data, array $options = []): bool
{
    return $this->send('document_created', $data, $options);
}
```

**Payload Example**:
```php
[
    'document_id' => 789,
    'document_number' => 'INV-2025-001',
    'document_type' => '1',  // Invoice
    'transaction_id' => 123,
    'order_id' => 456,
    'pdf_url' => 'https://site.com/documents/789/download',
]
```

**Triggered By**: `DocumentCreated` event

---

### 9. `sendSubscriptionCreated()` - Subscription Started Webhook

**Lines**: 177-180
```php
public function sendSubscriptionCreated(array $data, array $options = []): bool
{
    return $this->send('subscription_created', $data, $options);
}
```

**Payload Example**:
```php
[
    'subscription_id' => 111,
    'recurring_id' => '12345',  // SUMIT ID
    'order_id' => 456,
    'amount' => 99.00,
    'interval' => 'month',
    'max_cycles' => 12,
]
```

**Triggered By**: `SubscriptionCreated` event

---

### 10. `sendSubscriptionCharged()` - Recurring Payment Webhook

**Lines**: 185-188
```php
public function sendSubscriptionCharged(array $data, array $options = []): bool
{
    return $this->send('subscription_charged', $data, $options);
}
```

**Payload Example**:
```php
[
    'subscription_id' => 111,
    'transaction_id' => 124,
    'cycle_number' => 3,
    'amount' => 99.00,
    'next_charge_date' => '2025-03-01',
]
```

**Triggered By**: `SubscriptionCharged` event

---

### 11. `sendBitPaymentCompleted()` - Bit Payment Webhook

**Lines**: 193-196
```php
public function sendBitPaymentCompleted(array $data, array $options = []): bool
{
    return $this->send('bit_payment_completed', $data, $options);
}
```

**Payload Example**:
```php
[
    'order_id' => 456,
    'document_id' => 'DOC123',
    'customer_id' => 'CUST456',
    'payment_method' => 'bit',
]
```

**Triggered By**: `BitPaymentCompleted` event

---

### 12. `sendStockSynced()` - Stock Synchronization Webhook

**Lines**: 201-204
```php
public function sendStockSynced(array $data, array $options = []): bool
{
    return $this->send('stock_synced', $data, $options);
}
```

**Payload Example**:
```php
[
    'products_synced' => 42,
    'timestamp' => '2025-01-13 10:30:00',
]
```

**Triggered By**: `StockSynced` event

---

### 13. `retryFailedEvents()` - Retry Failed Webhooks

**Lines**: 212-233
**Signature**:
```php
public function retryFailedEvents(int $limit = 100): int
```

**Purpose**: Retry webhooks that failed previously with exponential backoff

**Process Flow**:
```
1. Query WebhookEvent::readyForRetry()
   └─ Finds events that failed but ready for retry

2. Limit to max number
   └─ Default: 100 events per batch

3. For each event:
   ├─ Call send() to retry webhook
   ├─ If success → markAsSent(200)
   └─ If failure → scheduleRetry() (exponential backoff)

4. Return count of processed events
```

**Implementation**:
```php
public function retryFailedEvents(int $limit = 100): int
{
    $events = WebhookEvent::readyForRetry()  // ← Model scope
        ->limit($limit)
        ->get();

    $processed = 0;

    foreach ($events as $event) {
        $success = $this->send($event->event_type, $event->payload ?? []);

        if ($success) {
            $event->markAsSent(200);  // ← Mark as successful
        } else {
            $event->scheduleRetry();  // ← Schedule next retry
        }

        $processed++;
    }

    return $processed;
}
```

**WebhookEvent Model Scopes**:
```php
// In WebhookEvent model:
public function scopeReadyForRetry($query)
{
    return $query->where('status', 'failed')
        ->where('retry_at', '<=', now())
        ->where('retry_count', '<', 5);  // Max 5 retries
}
```

**Exponential Backoff Schedule**:
```
Attempt 1: Immediate
Attempt 2: 1 minute later
Attempt 3: 5 minutes later
Attempt 4: 15 minutes later
Attempt 5: 60 minutes later
After 5 failures: Give up, mark as permanently failed
```

**Scheduled Command**:
```php
// In Console/Kernel.php
$schedule->call(function () {
    app(WebhookService::class)->retryFailedEvents();
})->everyFiveMinutes();
```

---

### 14. `log()` - Internal Logging

**Lines**: 238-244
```php
protected function log(string $level, string $message, array $context = []): void
{
    if ($this->settings->get('logging', false)) {
        $channel = $this->settings->get('log_channel', 'stack');
        Log::channel($channel)->$level("[SUMIT Webhook] {$message}", $context);
    }
}
```

**Log Levels**:
- `info` - Webhook queued, sent successfully
- `error` - Webhook failed to queue or send
- `debug` - Detailed debug information

---

## Dependencies

### Service Dependencies

```
WebhookService
├─ SettingsService (injected)
│  └─ get() - Retrieve webhook URLs and settings
├─ WebhookCall (Spatie wrapper)
│  ├─ create()
│  ├─ useSettingsForEvent()
│  ├─ payload()
│  ├─ meta()
│  ├─ dispatch()
│  └─ dispatchSync()
└─ Log Facade
   └─ Log::channel()
```

### Model Dependencies

```
WebhookService → WebhookEvent
├─ readyForRetry() scope
├─ markAsSent()
└─ scheduleRetry()
```

### Configuration Dependencies

```php
config('officeguy.webhooks.async')       // Default: true (use queue)
config('officeguy.logging')              // Enable logging
config('officeguy.log_channel')          // Log channel (stack, daily, etc.)
config('officeguy.webhook_secret')       // HMAC secret for signatures
config('officeguy.webhook_payment_completed')  // URL for payment_completed
config('officeguy.webhook_document_created')   // URL for document_created
// ... etc for each event type
```

---

## Configuration Setup

### Admin Settings Page

**Location**: `/admin/office-guy-settings` → "Webhooks" tab

**Settings Available**:
```php
// Enable/Disable
webhook_enabled → true/false

// Async vs Sync
webhooks.async → true/false (default: true)

// Security
webhook_secret → "your-secret-key"

// Event URLs (7 settings)
webhook_payment_completed → "https://external.com/webhook"
webhook_payment_failed → "https://external.com/webhook"
webhook_document_created → "https://external.com/webhook"
webhook_subscription_created → "https://external.com/webhook"
webhook_subscription_charged → "https://external.com/webhook"
webhook_bit_payment_completed → "https://external.com/webhook"
webhook_stock_synced → "https://external.com/webhook"

// Retry Settings
webhook_max_retries → 5
webhook_retry_delay → 60 (seconds)
```

---

## Integration with Event Listeners

### WebhookEventListener

**File**: `src/Listeners/WebhookEventListener.php`

```php
namespace OfficeGuy\LaravelSumitGateway\Listeners;

use OfficeGuy\LaravelSumitGateway\Services\WebhookService;

class WebhookEventListener
{
    protected WebhookService $webhookService;

    public function __construct(WebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    public function handlePaymentCompleted($event)
    {
        $this->webhookService->sendPaymentCompleted([
            'transaction_id' => $event->transaction->id,
            'order_id' => $event->order->id,
            'amount' => $event->transaction->amount,
        ]);
    }

    public function handleDocumentCreated($event)
    {
        $this->webhookService->sendDocumentCreated([
            'document_id' => $event->document->id,
            'transaction_id' => $event->document->transaction_id,
        ]);
    }
}
```

### Event Registration

**File**: `src/OfficeGuyServiceProvider.php`

```php
protected $listen = [
    PaymentCompleted::class => [
        WebhookEventListener::class . '@handlePaymentCompleted',
    ],
    DocumentCreated::class => [
        WebhookEventListener::class . '@handleDocumentCreated',
    ],
    // ... etc for all events
];
```

---

## Security Considerations

### 1. Webhook Signature Validation

**Purpose**: Prevent webhook spoofing

**Implementation**:
```php
// External system receiving webhook:
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'];
$payload = json_decode(file_get_contents('php://input'), true);
$secret = 'your-shared-secret';

if (!WebhookService::verifySignature($signature, $payload, $secret)) {
    http_response_code(401);
    exit('Invalid signature');
}

// Process webhook...
```

### 2. Webhook URL Validation

**Problem**: Users might enter invalid or malicious URLs

**Solution**: Validate URLs in settings form
```php
// In OfficeGuySettings form schema:
Forms\Components\TextInput::make('webhook_payment_completed')
    ->url()  // ← Validates URL format
    ->rules(['url', 'active_url'])  // ← Check if URL is reachable
```

### 3. Secret Key Management

**Best Practice**: Generate strong webhook secrets
```php
// Generate webhook secret (one-time):
$secret = bin2hex(random_bytes(32));  // 64-character hex string
```

**Storage**:
```php
// Store in database (via Admin Settings Page)
OfficeGuySetting::set('webhook_secret', $secret);

// Never hardcode in code or commit to git
```

---

## Performance Considerations

### 1. Always Use Async Mode

**Problem**: Sync webhooks block payment processing

```php
// ❌ BAD - Blocks user until webhook completes
$webhookService->send('payment_completed', $data, ['async' => false]);

// ✅ GOOD - Queues webhook, returns immediately
$webhookService->send('payment_completed', $data, ['async' => true]);
```

### 2. Queue Configuration

**Recommended Setup**:
```php
// config/queue.php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'webhooks',  // ← Dedicated queue for webhooks
        'retry_after' => 90,
    ],
],

// .env
QUEUE_CONNECTION=redis
```

### 3. Batch Retry Processing

```php
// In scheduled command:
$schedule->call(function () {
    // Process 100 failed webhooks every 5 minutes
    app(WebhookService::class)->retryFailedEvents(100);
})->everyFiveMinutes();
```

---

## Testing Recommendations

### 1. Unit Tests

```php
/** @test */
public function it_sends_webhook_asynchronously()
{
    Queue::fake();

    $webhookService = app(WebhookService::class);

    $result = $webhookService->send('payment_completed', [
        'transaction_id' => 123,
    ], ['async' => true]);

    $this->assertTrue($result);

    // Assert job was queued
    Queue::assertPushed(SendWebhookJob::class);
}
```

### 2. Signature Verification Test

```php
/** @test */
public function it_verifies_webhook_signature_correctly()
{
    $payload = ['transaction_id' => 123];
    $secret = 'test-secret';

    $signature = hash_hmac('sha256', json_encode($payload), $secret);

    $this->assertTrue(
        WebhookService::verifySignature($signature, $payload, $secret)
    );

    // Test with invalid signature
    $this->assertFalse(
        WebhookService::verifySignature('invalid-sig', $payload, $secret)
    );
}
```

### 3. Retry Logic Test

```php
/** @test */
public function it_retries_failed_webhooks()
{
    $event = WebhookEvent::factory()->create([
        'status' => 'failed',
        'retry_count' => 2,
        'retry_at' => now()->subMinute(),
    ]);

    Http::fake([
        'external.com/webhook' => Http::response(['success' => true], 200),
    ]);

    $webhookService = app(WebhookService::class);
    $processed = $webhookService->retryFailedEvents();

    $this->assertEquals(1, $processed);

    $event->refresh();
    $this->assertEquals('sent', $event->status);
}
```

---

## Best Practices

### ✅ DO

1. **Always use async mode in production**
   ```php
   $webhookService->send($event, $data, ['async' => true]);
   ```

2. **Generate and use webhook secrets**
   ```php
   $secret = bin2hex(random_bytes(32));
   config(['officeguy.webhook_secret' => $secret]);
   ```

3. **Verify signatures on receiving end**
   ```php
   if (!WebhookService::verifySignature($sig, $payload, $secret)) {
       abort(401);
   }
   ```

4. **Use dedicated webhook queue**
   ```php
   'queue' => 'webhooks',  // Separate from main queue
   ```

5. **Monitor failed webhooks**
   ```php
   WebhookEvent::where('status', 'failed')->count();
   ```

### ❌ DON'T

1. **Don't use sync mode in production**
   ```php
   // ❌ BAD - Blocks payment processing
   $webhookService->send($event, $data, ['async' => false]);
   ```

2. **Don't skip signature validation**
   ```php
   // ❌ BAD - No security!
   $payload = $request->json()->all();
   processWebhook($payload);  // Anyone can spoof this
   ```

3. **Don't use weak secrets**
   ```php
   // ❌ BAD
   'webhook_secret' => '12345'

   // ✅ GOOD
   'webhook_secret' => bin2hex(random_bytes(32))
   ```

4. **Don't retry forever**
   ```php
   // ✅ GOOD - Max 5 retries
   if ($event->retry_count >= 5) {
       $event->markAsPermanentlyFailed();
   }
   ```

---

## Complete Usage Example

### Setup Webhook in Admin Panel

```
1. Navigate to /admin/office-guy-settings
2. Go to "Webhooks" tab
3. Enable webhooks: ✓
4. Generate webhook secret: [Generate]
5. Set webhook URL for payment_completed: https://external.com/webhook
6. Enable async mode: ✓ (recommended)
7. Save settings
```

### Trigger Webhook in Code

```php
use OfficeGuy\LaravelSumitGateway\Services\WebhookService;

// In event listener or service:
$webhookService = app(WebhookService::class);

$webhookService->sendPaymentCompleted([
    'transaction_id' => $transaction->id,
    'order_id' => $order->id,
    'amount' => $transaction->amount,
    'currency' => $transaction->currency,
    'payment_method' => $transaction->payment_method,
    'document_id' => $document->id,
    'timestamp' => now()->toIso8601String(),
], [
    'async' => true,  // Queue for background processing
    'transaction_id' => $transaction->id,
]);
```

### Receive Webhook on External System

```php
// external-system.com/webhook
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
$payload = json_decode(file_get_contents('php://input'), true);
$secret = 'your-shared-secret';

// Verify signature
if (!hash_equals(
    hash_hmac('sha256', json_encode($payload), $secret),
    $signature
)) {
    http_response_code(401);
    exit('Invalid signature');
}

// Process webhook
$transactionId = $payload['transaction_id'];
$orderId = $payload['order_id'];

// Update your system...
updateOrderInCRM($orderId, 'paid');

// Return 200 OK
http_response_code(200);
echo json_encode(['success' => true]);
```

---

## Summary

### Service Purpose
WebhookService provides a **complete outgoing webhook system** for notifying external systems about package events without writing custom event Listeners.

### Key Strengths
- ✅ Async queue-based sending (non-blocking)
- ✅ HMAC-SHA256 signatures for security
- ✅ Automatic retry with exponential backoff
- ✅ 7+ event types with dedicated methods
- ✅ Database logging of all webhook attempts
- ✅ Configurable via Admin Settings Page

### Architecture Pattern
- **Instance Service** (NOT static like most others)
- **Dependency Injection** (SettingsService injected)
- **Facade Pattern** (WebhookCall wraps Spatie webhook-client)

### Integration Points
- WebhookEventListener dispatches webhooks for all events
- WebhookCall handles HTTP sending and retries
- WebhookEvent model tracks all webhook attempts
- Admin Settings Page configures URLs and secrets

### Critical Implementation Notes
1. **Always use async mode in production** (queue-based)
2. **Generate strong webhook secrets** (32+ bytes)
3. **Verify signatures on receiving end** (hash_equals for timing safety)
4. **Monitor failed webhooks** (retry up to 5 times)
5. **Use dedicated webhook queue** (separate from main queue)

---

**Lines Analyzed**: 245
**Methods Documented**: 14
**Dependencies**: SettingsService, WebhookCall, WebhookEvent
**Event Support**: 7 event types
**Security Features**: HMAC-SHA256 signatures, timing-safe comparison
