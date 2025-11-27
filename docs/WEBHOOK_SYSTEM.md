# Webhook System - Complete Guide

## 🎯 Overview

The SUMIT Payment Gateway package includes a powerful **queue-based webhook system** inspired by [Spatie's laravel-webhook-server](https://github.com/spatie/laravel-webhook-server).

**Key Features:**
- ✅ **Queue Integration** - Async webhook delivery via Laravel queues
- ✅ **Automatic Retries** - Exponential backoff with configurable attempts
- ✅ **Event System** - Listen to webhook lifecycle events
- ✅ **Fluent Builder** - Clean API for webhook configuration
- ✅ **HMAC Signing** - Secure signature verification (SHA-256)
- ✅ **Database Tracking** - All webhooks logged to `webhook_events` table
- ✅ **Backward Compatible** - Legacy synchronous mode still supported

---

## 🚀 Quick Start

### Simple Usage (Async - Recommended)

```php
use OfficeGuy\LaravelSumitGateway\WebhookCall;

WebhookCall::create()
    ->event('payment_completed')
    ->url('https://example.com/webhook')
    ->payload(['order_id' => 123, 'amount' => 100.00])
    ->useSecret('your-webhook-secret')
    ->dispatch(); // Queues the webhook
```

### Synchronous Usage (Immediate)

```php
$success = WebhookCall::create()
    ->event('payment_completed')
    ->url('https://example.com/webhook')
    ->payload(['order_id' => 123])
    ->useSecret('your-webhook-secret')
    ->dispatchSync(); // Executes immediately

if ($success) {
    // Webhook delivered successfully
}
```

### Using Settings (Automatic Configuration)

```php
// Automatically reads URL and secret from Admin Panel settings
WebhookCall::create()
    ->useSettingsForEvent('payment_completed')
    ->payload(['order_id' => 123])
    ->dispatch();
```

---

## 📚 Fluent Builder API

### Core Methods

| Method | Description | Example |
|--------|-------------|---------|
| `event(string)` | Set event name | `->event('payment_completed')` |
| `url(string)` | Set webhook URL | `->url('https://example.com/webhook')` |
| `payload(array)` | Set data to send | `->payload(['key' => 'value'])` |
| `useSecret(string)` | Set signing secret | `->useSecret('secret123')` |
| `withHeaders(array)` | Add custom headers | `->withHeaders(['X-Custom' => 'value'])` |
| `meta(array)` | Attach metadata (not sent) | `->meta(['user_id' => 1])` |

### Configuration Methods

| Method | Description | Default |
|--------|-------------|---------|
| `maximumTries(int)` | Max retry attempts | 3 |
| `timeoutInSeconds(int)` | Request timeout | 30 |
| `useBackoffStrategy(string)` | Backoff class | `ExponentialBackoffStrategy` |
| `doNotSign()` | Disable signature | Enabled |
| `throwExceptionOnFailure()` | Throw on final failure | Silent delete |

### Dispatch Methods

| Method | Description | When to Use |
|--------|-------------|-------------|
| `dispatch()` | Queue async | **Recommended** - Non-blocking |
| `dispatchSync()` | Execute immediately | Testing, critical operations |
| `dispatchIf(bool)` | Conditional async | `->dispatchIf($order->isPaid())` |
| `dispatchSyncIf(bool)` | Conditional sync | `->dispatchSyncIf($needsImmediate)` |

---

## ⚙️ Configuration

### Publishing Config

```bash
php artisan vendor:publish --tag=officeguy-config
```

### Config File: `config/webhook-server.php`

```php
return [
    // Enable async queue-based delivery (recommended)
    'async' => env('WEBHOOK_ASYNC', true),

    // Queue name for webhook jobs
    'queue' => env('WEBHOOK_QUEUE', 'default'),

    // Maximum retry attempts
    'tries' => env('WEBHOOK_MAX_TRIES', 3),

    // Request timeout in seconds
    'timeout_in_seconds' => env('WEBHOOK_TIMEOUT', 30),

    // Backoff strategy class
    'backoff_strategy' => ExponentialBackoffStrategy::class,

    // Verify SSL certificates
    'verify_ssl' => env('WEBHOOK_VERIFY_SSL', true),
];
```

### Environment Variables

```env
WEBHOOK_ASYNC=true
WEBHOOK_QUEUE=webhooks
WEBHOOK_MAX_TRIES=3
WEBHOOK_TIMEOUT=30
WEBHOOK_VERIFY_SSL=true
```

---

## 🔄 Retry Logic

### Exponential Backoff Strategy

Default retry delays:

| Attempt | Delay | Formula |
|---------|-------|---------|
| 1 | Immediate | - |
| 2 | 10 seconds | 10^1 |
| 3 | 100 seconds | 10^2 |
| 4 | 1,000 seconds | 10^3 |

### Custom Backoff Strategy

```php
use OfficeGuy\LaravelSumitGateway\BackoffStrategy\BackoffStrategyInterface;

class CustomBackoffStrategy implements BackoffStrategyInterface
{
    public function waitInSecondsAfterAttempt(int $attempt): int
    {
        // Custom logic: 5, 25, 125 seconds
        return 5 ** $attempt;
    }
}

// Usage:
WebhookCall::create()
    ->useBackoffStrategy(CustomBackoffStrategy::class)
    ->dispatch();
```

---

## 📡 Events System

### Available Events

| Event | When Fired | Use Case |
|-------|------------|----------|
| `WebhookCallSucceededEvent` | 2xx response received | Metrics, logging |
| `WebhookCallFailedEvent` | Each failed attempt | Retry monitoring |
| `FinalWebhookCallFailedEvent` | All retries exhausted | Alerting, manual review |

### Event Properties

All events include:
- `uuid` - Unique identifier (consistent across retries)
- `event` - Event name (e.g., 'payment_completed')
- `url` - Webhook destination
- `payload` - Data sent
- `headers` - Request headers
- `meta` - Custom metadata
- `attempt` - Attempt number
- `statusCode` / `errorMessage` - Response details

### Listening to Events

```php
// app/Providers/EventServiceProvider.php
use OfficeGuy\LaravelSumitGateway\Events\WebhookCallSucceededEvent;
use OfficeGuy\LaravelSumitGateway\Events\FinalWebhookCallFailedEvent;

protected $listen = [
    WebhookCallSucceededEvent::class => [
        LogSuccessfulWebhook::class,
    ],

    FinalWebhookCallFailedEvent::class => [
        AlertAdminOfFailedWebhook::class,
    ],
];
```

#### Example Listener

```php
namespace App\Listeners;

use OfficeGuy\LaravelSumitGateway\Events\FinalWebhookCallFailedEvent;
use Illuminate\Support\Facades\Notification;

class AlertAdminOfFailedWebhook
{
    public function handle(FinalWebhookCallFailedEvent $event): void
    {
        Notification::route('slack', config('services.slack.webhook'))
            ->notify(new WebhookFailedNotification(
                event: $event->event,
                url: $event->url,
                attempts: $event->totalAttempts,
                error: $event->errorMessage
            ));
    }
}
```

---

## 🔐 Signature Verification

### How It Works

1. **Sender** (this package) generates HMAC-SHA256 signature:
   ```php
   $signature = hash_hmac('sha256', json_encode($payload), $secret);
   ```

2. **Header sent**:
   ```
   X-Webhook-Signature: abc123def456...
   ```

3. **Receiver** verifies signature:
   ```php
   use OfficeGuy\LaravelSumitGateway\Services\WebhookService;

   $signature = $request->header('X-Webhook-Signature');
   $payload = $request->all();
   $secret = config('officeguy.webhook_secret');

   if (WebhookService::verifySignature($signature, $payload, $secret)) {
       // Valid webhook
   } else {
       abort(403, 'Invalid signature');
   }
   ```

### Headers Sent

```
Content-Type: application/json
X-Webhook-Event: payment_completed
X-Webhook-Timestamp: 2025-11-27T12:34:56Z
X-Webhook-UUID: 550e8400-e29b-41d4-a716-446655440000
X-Webhook-Signature: abc123def456...
```

---

## 🗄️ Database Tracking

### WebhookEvent Model

All webhooks are logged to `webhook_events` table:

| Column | Description |
|--------|-------------|
| `uuid` | Unique identifier |
| `event_type` | Event name |
| `payload` | JSON data |
| `webhook_url` | Destination URL |
| `status` | pending / sent / failed |
| `http_status_code` | Response status |
| `sent_at` | Delivery timestamp |
| `retry_after` | Next retry time |
| `attempts` | Attempt count |

### Query Examples

```php
use OfficeGuy\LaravelSumitGateway\Models\WebhookEvent;

// Failed webhooks
$failed = WebhookEvent::where('status', 'failed')->get();

// By event type
$payments = WebhookEvent::where('event_type', 'payment_completed')->get();

// Ready for retry
$retryable = WebhookEvent::readyForRetry()->get();
```

---

## 🧪 Testing

### Faking Queue

```php
use Illuminate\Support\Facades\Queue;

Queue::fake();

WebhookCall::create()
    ->event('payment_completed')
    ->url('https://example.com')
    ->dispatch();

Queue::assertPushed(SendWebhookJob::class, function ($job) {
    return $job->event === 'payment_completed';
});
```

### Faking Events

```php
use Illuminate\Support\Facades\Event;

Event::fake();

WebhookCall::create()
    ->event('payment_completed')
    ->url('https://example.com')
    ->dispatchSync();

Event::assertDispatched(WebhookCallSucceededEvent::class);
```

---

## 📋 Legacy WebhookService

The original `WebhookService` class still works and now uses `WebhookCall` internally:

```php
use OfficeGuy\LaravelSumitGateway\Services\WebhookService;

$service = app(WebhookService::class);

// Async (default)
$service->send('payment_completed', ['order_id' => 123]);

// Force sync
$service->send('payment_completed', ['order_id' => 123], ['async' => false]);

// Helper methods still work
$service->sendPaymentCompleted(['order_id' => 123]);
$service->sendPaymentFailed(['order_id' => 123, 'error' => 'Card declined']);
```

---

## 🎯 Best Practices

### 1. Always Use Async Mode

```php
// ✅ Good - Non-blocking
WebhookCall::create()->event('payment_completed')->dispatch();

// ❌ Bad - Blocks request
WebhookCall::create()->event('payment_completed')->dispatchSync();
```

### 2. Use Settings for Production

```php
// ✅ Good - Centralized configuration
WebhookCall::create()->useSettingsForEvent('payment_completed');

// ❌ Bad - Hardcoded URLs
WebhookCall::create()->url('https://hardcoded.com');
```

### 3. Add Meaningful Metadata

```php
WebhookCall::create()
    ->event('payment_completed')
    ->payload(['order_id' => 123])
    ->meta([
        'user_id' => auth()->id(),
        'ip_address' => request()->ip(),
        'source' => 'checkout_page',
    ])
    ->dispatch();
```

### 4. Listen to Final Failures

```php
// Always implement alerting for permanent failures
Event::listen(FinalWebhookCallFailedEvent::class, function ($event) {
    Log::critical('Webhook permanently failed', [
        'event' => $event->event,
        'url' => $event->url,
    ]);
});
```

---

## 🔍 Monitoring

### Horizon Integration

Webhook jobs automatically include tags for Horizon:
- `sumit-webhook`
- `event:{event_name}`
- `uuid:{webhook_uuid}`

### Metrics to Track

1. **Success Rate**: `WebhookCallSucceededEvent` count / Total sent
2. **Average Attempts**: Track `attempt` in success events
3. **Failure Rate**: `FinalWebhookCallFailedEvent` count
4. **Queue Depth**: Monitor `webhooks` queue size

---

## 🆘 Troubleshooting

### Webhooks Not Firing

1. **Check queue is running**:
   ```bash
   php artisan queue:work --queue=webhooks
   ```

2. **Verify URL is configured**:
   ```php
   config('officeguy.webhook_payment_completed');
   ```

3. **Check `webhook_events` table**:
   ```sql
   SELECT * FROM webhook_events WHERE status = 'failed';
   ```

### Signature Verification Failing

1. **Ensure identical payload**:
   - Server encodes: `json_encode($payload)`
   - Must match exactly (no extra spaces/formatting)

2. **Check secret matches**:
   ```php
   config('officeguy.webhook_secret');
   ```

3. **Verify HMAC algorithm**:
   - Must use `hash_hmac('sha256', ...)`

---

## 📖 Additional Resources

- [Spatie Webhook Server (Inspiration)](https://github.com/spatie/laravel-webhook-server)
- [Laravel Queues Documentation](https://laravel.com/docs/queues)
- [HMAC Signature Verification](https://en.wikipedia.org/wiki/HMAC)

---

**Version**: 1.2.0
**Last Updated**: 2025-11-27
**Package**: officeguy/laravel-sumit-gateway
