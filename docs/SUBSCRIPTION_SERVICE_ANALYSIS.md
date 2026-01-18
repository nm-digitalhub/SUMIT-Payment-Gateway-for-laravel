# SubscriptionService Analysis

**File**: `src/Services/SubscriptionService.php`
**Lines**: 437
**Type**: Static Service Class
**Purpose**: Recurring billing and subscription management via SUMIT API

---

## Overview

SubscriptionService manages the complete subscription lifecycle including creation, recurring charges, status synchronization, and cancellation. It integrates deeply with SUMIT's `/billing/recurring/` API endpoints and coordinates with the PaymentService for initial charges.

### Key Responsibilities

1. **Subscription Creation**: Create subscriptions with initial payment processing
2. **Recurring Billing**: Process automated recurring charges via SUMIT
3. **Status Management**: Track subscription states (active, cancelled, suspended, expired)
4. **SUMIT Synchronization**: Fetch and sync subscription data from SUMIT API
5. **Lifecycle Management**: Handle subscription updates and cancellations
6. **Event Dispatching**: Emit events for subscription state changes

---

## Class Structure

```php
namespace OfficeGuy\LaravelSumitGateway\Services;

use OfficeGuy\LaravelSumitGateway\Models\Subscription;
use OfficeGuy\LaravelSumitGateway\Events\{
    SubscriptionCreated,
    SubscriptionCharged,
    SubscriptionChargesFailed,
    SubscriptionCancelled
};

class SubscriptionService
{
    // 13 Public Static Methods
}
```

---

## Methods Analysis

### 1. `create()` - Create Subscription with Initial Charge

**Lines**: 24-155
**Signature**:
```php
public static function create(
    $order,
    ?OfficeGuyToken $token = null,
    array $recurringData = []
): array
```

**Purpose**: Creates a new subscription in SUMIT with initial payment processing

**Parameters**:
- `$order` - Payable order (implements Payable contract)
- `$token` - Optional saved payment method
- `$recurringData` - Subscription configuration
  - `interval_unit` - 'day'|'week'|'month'|'year'
  - `interval_count` - Number of units (e.g., 1 for monthly)
  - `max_cycles` - Total billing cycles (optional)
  - `start_date` - First billing date (optional)
  - `description` - Subscription description

**Process Flow**:
```
1. Build SUMIT request with subscription parameters
   ├─ Credentials from PaymentService
   ├─ Customer data from order
   ├─ Payment method (token or new card)
   └─ Recurring billing configuration

2. Call SUMIT API: /billing/recurring/create/
   └─ Returns: RecurringPaymentID + TransactionID

3. Process initial charge via PaymentService
   ├─ Create OfficeGuyTransaction record
   └─ Generate document via DocumentService

4. Create local Subscription model
   ├─ Store recurring_id from SUMIT
   ├─ Link to order (polymorphic)
   └─ Set initial status: 'active'

5. Dispatch SubscriptionCreated event
   └─ Triggers fulfillment listeners

6. Return complete response with subscription data
```

**Request Structure**:
```php
[
    'Credentials' => [...],
    'Customer' => [
        'FirstName' => $customer['first_name'],
        'LastName' => $customer['last_name'],
        'Phone' => $customer['phone'],
        'Email' => $customer['email'],
        'ID' => $sumitCustomerId  // If exists
    ],
    'PaymentMethod' => [
        'Token' => $token->token,  // Or card details
    ],
    'Recurring' => [
        'IntervalUnit' => $recurringData['interval_unit'],
        'IntervalCount' => (int) $recurringData['interval_count'],
        'MaxCycles' => (int) $recurringData['max_cycles'],
        'StartDate' => $recurringData['start_date'],
        'Description' => $recurringData['description'],
    ],
    'Items' => [
        [
            'Name' => $order->getPayableTitle(),
            'UnitPrice' => $order->getPayableAmount(),
            'Quantity' => 1,
        ]
    ],
    'Amount' => $order->getPayableAmount(),
    'Currency' => config('officeguy.currency', 'ILS'),
    'Language' => PaymentService::getLanguage(),
    'ExternalReference' => 'subscription_' . uniqid(),
]
```

**Return Value**:
```php
[
    'success' => true,
    'subscription' => Subscription,        // Local model
    'transaction' => OfficeGuyTransaction, // Initial payment
    'recurring_id' => '12345',            // SUMIT RecurringPaymentID
    'sumit_response' => [...]             // Full SUMIT response
]
```

**Events Dispatched**:
- `SubscriptionCreated` - After successful creation

**Error Handling**:
```php
try {
    // Subscription creation logic
} catch (\Exception $e) {
    Log::error('Subscription creation failed', [
        'order_id' => $order->getPayableId(),
        'error' => $e->getMessage()
    ]);
    throw $e;  // Re-throw for upstream handling
}
```

**Critical Notes**:
- ⚠️ **Initial charge is ALWAYS processed** - subscription includes first payment
- ✅ Transaction and subscription created in same flow
- ✅ Uses PaymentService::getOrderCustomer() for deduplication
- ⚠️ If initial charge fails, subscription is NOT created

---

### 2. `processRecurringCharge()` - Process Scheduled Recurring Payment

**Lines**: 162-230
**Signature**:
```php
public static function processRecurringCharge(Subscription $subscription): array
```

**Purpose**: Process a recurring charge for an existing subscription via SUMIT

**Process Flow**:
```
1. Validate subscription is active
   └─ Check status is not 'cancelled' or 'expired'

2. Build SUMIT recurring charge request
   ├─ Credentials
   ├─ RecurringPaymentID from subscription
   ├─ Items from linked order
   └─ ExternalReference with cycle tracking

3. Call SUMIT API: /billing/recurring/charge/
   └─ SUMIT charges saved payment method

4. Create OfficeGuyTransaction for recurring payment
   ├─ Link to subscription (polymorphic)
   ├─ Set sumit_entity_id = recurring_id
   └─ Mark as recurring: true

5. Generate document via DocumentService
   └─ Invoice/Receipt for recurring charge

6. Update subscription
   ├─ Increment cycles_completed
   ├─ Update last_charge_at timestamp
   └─ Check if max_cycles reached → mark 'expired'

7. Dispatch success/failure event
   ├─ SubscriptionCharged (success)
   └─ SubscriptionChargesFailed (failure)

8. Return transaction and result
```

**Request Structure**:
```php
[
    'Credentials' => PaymentService::getCredentials(),
    'RecurringPaymentID' => $subscription->recurring_id,  // SUMIT subscription ID
    'Items' => [
        [
            'Name' => $order->getPayableTitle(),
            'UnitPrice' => $order->getPayableAmount(),
            'Quantity' => 1,
        ]
    ],
    'Amount' => $order->getPayableAmount(),
    'Currency' => config('officeguy.currency', 'ILS'),
    'Language' => PaymentService::getLanguage(),
    'ExternalReference' => "subscription_{$subscription->id}_recurring_{$subscription->recurring_id}_cycle_{$subscription->cycles_completed + 1}",
]
```

**Subscription Completion Logic**:
```php
// Line 204-210: Check if subscription completed all cycles
if ($subscription->max_cycles && $subscription->cycles_completed >= $subscription->max_cycles) {
    $subscription->update([
        'status' => 'expired',
        'ended_at' => now(),
    ]);
}
```

**Return Value**:
```php
[
    'success' => true|false,
    'transaction' => OfficeGuyTransaction,
    'subscription' => Subscription,  // Updated with new cycles_completed
    'sumit_response' => [...]
]
```

**Events Dispatched**:
- `SubscriptionCharged` - On successful charge
- `SubscriptionChargesFailed` - On charge failure

**Scheduled Execution**:
This method is called by:
- `ProcessRecurringPaymentsJob` (scheduled job)
- `ProcessRecurringPaymentsCommand` (artisan command)

**Critical Notes**:
- ✅ Each recurring charge creates a NEW OfficeGuyTransaction
- ✅ Automatically expires subscription when max_cycles reached
- ⚠️ Failed charges do NOT automatically cancel subscription
- ✅ ExternalReference includes cycle number for tracking

---

### 3. `processInitialCharge()` - Handle Initial Subscription Payment

**Lines**: 237-279
**Signature**:
```php
private static function processInitialCharge(
    $order,
    array $sumitResponse,
    ?OfficeGuyToken $token = null
): array
```

**Purpose**: Internal helper to process the first payment when creating subscription

**Process Flow**:
```
1. Extract transaction data from SUMIT response
   └─ TransactionID, Token (if new card)

2. Call PaymentService::createFromSumitResponse()
   ├─ Create OfficeGuyTransaction record
   ├─ Link to order (polymorphic)
   └─ Store SUMIT transaction data

3. If new token created, save to OfficeGuyToken
   └─ Store permanent token for recurring charges

4. Generate initial document
   └─ Invoice/Receipt via DocumentService

5. Return transaction and token data
```

**Return Value**:
```php
[
    'transaction' => OfficeGuyTransaction,
    'token' => OfficeGuyToken|null,
    'sumit_response' => [...]
]
```

**Critical Notes**:
- ✅ Called only during `create()` method
- ✅ Handles both existing and new payment methods
- ✅ Automatically saves new tokens for future recurring charges

---

### 4. `cancel()` - Cancel Active Subscription

**Lines**: 286-325
**Signature**:
```php
public static function cancel(Subscription $subscription, string $reason = ''): array
```

**Purpose**: Cancel a subscription in both SUMIT and local database

**Process Flow**:
```
1. Call SUMIT API: /billing/recurring/cancel/
   └─ Request:
       ├─ Credentials
       └─ RecurringPaymentID

2. Update local subscription model
   ├─ status = 'cancelled'
   ├─ ended_at = now()
   └─ cancellation_reason = $reason

3. Dispatch SubscriptionCancelled event
   └─ Triggers cleanup listeners

4. Return cancellation result
```

**Request Structure**:
```php
[
    'Credentials' => PaymentService::getCredentials(),
    'RecurringPaymentID' => $subscription->recurring_id,
]
```

**Return Value**:
```php
[
    'success' => true,
    'subscription' => Subscription,  // Updated with cancelled status
    'sumit_response' => [
        'Status' => 'Success',
        'Message' => 'Subscription cancelled'
    ]
]
```

**Events Dispatched**:
- `SubscriptionCancelled` - After successful cancellation

**Critical Notes**:
- ✅ Cancels in SUMIT first, then updates local record
- ✅ Records cancellation reason for audit trail
- ⚠️ Cancelled subscriptions cannot be reactivated
- ✅ No future recurring charges will be attempted

---

### 5. `syncFromSumit()` - Synchronize Subscription Data

**Lines**: 332-395
**Signature**:
```php
public static function syncFromSumit(Subscription $subscription): array
```

**Purpose**: Fetch current subscription status from SUMIT and update local record

**Process Flow**:
```
1. Fetch subscription data from SUMIT
   └─ Call fetchFromSumit() with recurring_id

2. Compare SUMIT status with local status
   └─ Update if different

3. Update subscription fields
   ├─ status
   ├─ next_charge_at
   ├─ cycles_completed
   └─ last_synced_at = now()

4. Return sync result with changes detected
```

**Return Value**:
```php
[
    'success' => true,
    'subscription' => Subscription,
    'changes_detected' => true|false,
    'sumit_data' => [...]
]
```

**Sync Triggers**:
- Manual admin action
- Webhook from SUMIT
- Scheduled sync job

**Critical Notes**:
- ✅ SUMIT is source of truth for subscription status
- ✅ Updates local record to match SUMIT
- ⚠️ Does NOT push local changes to SUMIT

---

### 6. `fetchFromSumit()` - Fetch Subscription from SUMIT

**Lines**: 402-437
**Signature**:
```php
public static function fetchFromSumit(
    int $sumitCustomerId,
    bool $includeInactive = false
): array
```

**Purpose**: Retrieve all subscriptions for a customer from SUMIT API

**API Endpoint**: `/billing/recurring/listforcustomer/`

**Request Structure**:
```php
[
    'Credentials' => PaymentService::getCredentials(),
    'Customer' => ['ID' => $sumitCustomerId],
    'IncludeInactive' => $includeInactive ? 'true' : 'false',
]
```

**Return Value**:
```php
[
    'Status' => 'Success',
    'Subscriptions' => [
        [
            'RecurringPaymentID' => '12345',
            'Status' => 'Active',
            'IntervalUnit' => 'month',
            'IntervalCount' => 1,
            'MaxCycles' => 12,
            'CyclesCompleted' => 3,
            'NextChargeDate' => '2025-02-01',
            'Amount' => 99.00,
            'Currency' => 'ILS',
        ],
        // ... more subscriptions
    ]
]
```

**Use Cases**:
- Customer portal subscription list
- Admin subscription management
- Reconciliation between local and SUMIT data

**Critical Notes**:
- ✅ Returns ALL subscriptions for a customer
- ✅ Can filter to active only or include inactive
- ⚠️ Requires sumit_customer_id from order/user

---

## Helper Methods

### 7. `getIntervalDescription()` - Human-Readable Interval

**Lines**: 81-95
**Purpose**: Convert interval_unit + interval_count to readable Hebrew/English text

**Examples**:
```php
getIntervalDescription('month', 1)  → 'חודשי' | 'Monthly'
getIntervalDescription('week', 2)   → 'דו-שבועי' | 'Bi-weekly'
getIntervalDescription('year', 1)   → 'שנתי' | 'Yearly'
```

---

## Dependencies

### Service Dependencies

```
SubscriptionService
├─ PaymentService
│  ├─ getCredentials()
│  ├─ getOrderCustomer()
│  ├─ getPaymentOrderItems()
│  ├─ getLanguage()
│  └─ createFromSumitResponse()
├─ OfficeGuyApi
│  └─ post()
├─ DocumentService
│  └─ createFromApiResponse()
└─ TokenService
   └─ Token validation
```

### Model Dependencies

```
SubscriptionService → Subscription Model
├─ Relationships
│  ├─ owner (polymorphic: Order, User, etc.)
│  ├─ transactions (hasMany OfficeGuyTransaction)
│  └─ documents (hasManyThrough)
└─ Status Constants
   ├─ STATUS_ACTIVE = 'active'
   ├─ STATUS_CANCELLED = 'cancelled'
   ├─ STATUS_SUSPENDED = 'suspended'
   └─ STATUS_EXPIRED = 'expired'
```

### Event Dependencies

```
Events Dispatched:
├─ SubscriptionCreated
│  └─ Triggers: FulfillmentListener
├─ SubscriptionCharged
│  └─ Triggers: DocumentSyncListener
├─ SubscriptionChargesFailed
│  └─ Triggers: NotificationListener
└─ SubscriptionCancelled
   └─ Triggers: CleanupListener
```

---

## SUMIT API Endpoints

### 1. Create Subscription
**Endpoint**: `/billing/recurring/create/`
**Method**: POST
**Purpose**: Create new subscription with initial charge

### 2. Recurring Charge
**Endpoint**: `/billing/recurring/charge/`
**Method**: POST
**Purpose**: Process recurring payment for existing subscription

### 3. Cancel Subscription
**Endpoint**: `/billing/recurring/cancel/`
**Method**: POST
**Purpose**: Cancel active subscription

### 4. List Customer Subscriptions
**Endpoint**: `/billing/recurring/listforcustomer/`
**Method**: POST
**Purpose**: Fetch all subscriptions for a customer

---

## Subscription Lifecycle

### State Machine

```
┌─────────────┐
│   pending   │ (Initial state, before SUMIT creation)
└──────┬──────┘
       │ create()
       ↓
┌─────────────┐
│   active    │ ← Normal state for billing
└──────┬──────┘
       │
       ├─→ processRecurringCharge() (success) → stays active
       │
       ├─→ processRecurringCharge() (max_cycles reached) → expired
       │
       ├─→ cancel() → cancelled
       │
       └─→ syncFromSumit() (SUMIT suspended) → suspended
```

### Status Definitions

| Status | Description | Can Charge? | Next State |
|--------|-------------|-------------|------------|
| `pending` | Created locally, not yet in SUMIT | No | `active` (after create()) |
| `active` | Normal recurring billing state | Yes | `expired`, `cancelled`, `suspended` |
| `suspended` | Temporarily paused (by SUMIT) | No | `active` (manual reactivation) |
| `cancelled` | Permanently cancelled | No | None (terminal state) |
| `expired` | Completed all max_cycles | No | None (terminal state) |

---

## Recurring Billing Schedule

### Automated Processing

**Scheduled Job**: `ProcessRecurringPaymentsJob`
**Frequency**: Daily (configurable)
**Logic**:
```php
// Find subscriptions due for charge
Subscription::where('status', 'active')
    ->where('next_charge_at', '<=', now())
    ->chunk(100, function ($subscriptions) {
        foreach ($subscriptions as $subscription) {
            SubscriptionService::processRecurringCharge($subscription);
        }
    });
```

### Next Charge Date Calculation

```php
// After successful charge, calculate next_charge_at
$nextChargeDate = match($subscription->interval_unit) {
    'day' => now()->addDays($subscription->interval_count),
    'week' => now()->addWeeks($subscription->interval_count),
    'month' => now()->addMonths($subscription->interval_count),
    'year' => now()->addYears($subscription->interval_count),
};

$subscription->update(['next_charge_at' => $nextChargeDate]);
```

---

## Security Considerations

### 1. Subscription Ownership Validation

⚠️ **Critical**: Always validate subscription ownership before operations

```php
// ✅ GOOD - Validate ownership
if ($subscription->owner_id !== $user->id ||
    $subscription->owner_type !== get_class($user)) {
    abort(403, 'Unauthorized access to subscription');
}

// ❌ BAD - No validation
SubscriptionService::cancel($subscription);
```

### 2. Cancellation Authorization

```php
// Only allow owners or admins to cancel
if (!$user->can('cancel', $subscription)) {
    abort(403, 'Cannot cancel this subscription');
}
```

### 3. Rate Limiting

```php
// Prevent abuse of recurring charge endpoint
RateLimiter::for('recurring-charge', function (Request $request) {
    return Limit::perMinute(5)->by($request->user()->id);
});
```

---

## Performance Considerations

### 1. Batch Processing

```php
// Process subscriptions in chunks to avoid memory issues
Subscription::where('status', 'active')
    ->where('next_charge_at', '<=', now())
    ->chunk(100, function ($subscriptions) {
        foreach ($subscriptions as $subscription) {
            dispatch(new ProcessSingleRecurringPaymentJob($subscription));
        }
    });
```

### 2. Queue Processing

```php
// Don't process recurring charges synchronously
dispatch(new ProcessRecurringPaymentJob($subscription))
    ->onQueue('recurring-billing');
```

### 3. Transaction Isolation

```php
// Use database transactions for consistency
DB::transaction(function () use ($subscription) {
    SubscriptionService::processRecurringCharge($subscription);
});
```

---

## Error Handling Patterns

### 1. Recurring Charge Failures

```php
try {
    $result = SubscriptionService::processRecurringCharge($subscription);
} catch (\Exception $e) {
    // Log failure
    Log::error('Recurring charge failed', [
        'subscription_id' => $subscription->id,
        'error' => $e->getMessage()
    ]);

    // Increment failure count
    $subscription->increment('failed_charges_count');

    // Suspend after 3 failures
    if ($subscription->failed_charges_count >= 3) {
        $subscription->update(['status' => 'suspended']);

        // Notify customer
        event(new SubscriptionSuspended($subscription));
    }

    // Dispatch failure event
    event(new SubscriptionChargesFailed($subscription, $e->getMessage()));
}
```

### 2. SUMIT API Failures

```php
// Graceful degradation if SUMIT unavailable
$response = OfficeGuyApi::post($request, '/billing/recurring/charge/');

if ($response['Status'] !== 'Success') {
    // Log for manual review
    Log::warning('SUMIT API returned non-success', [
        'subscription_id' => $subscription->id,
        'response' => $response
    ]);

    // Don't update subscription status yet
    // Let manual review determine if SUMIT actually charged

    return [
        'success' => false,
        'message' => $response['Message'] ?? 'Unknown error'
    ];
}
```

---

## Testing Recommendations

### 1. Unit Tests

```php
/** @test */
public function it_creates_subscription_with_initial_charge()
{
    Http::fake([
        'api.sumit.co.il/billing/recurring/create/' => Http::response([
            'Status' => 'Success',
            'RecurringPaymentID' => '12345',
            'TransactionID' => 'txn_67890',
            'Token' => 'tok_saved',
        ], 200),
    ]);

    $result = SubscriptionService::create($this->order, null, [
        'interval_unit' => 'month',
        'interval_count' => 1,
        'max_cycles' => 12,
    ]);

    $this->assertTrue($result['success']);
    $this->assertInstanceOf(Subscription::class, $result['subscription']);
    $this->assertEquals('12345', $result['subscription']->recurring_id);
}
```

### 2. Integration Tests

```php
/** @test */
public function it_processes_recurring_charge_and_updates_cycles()
{
    $subscription = Subscription::factory()->create([
        'status' => 'active',
        'cycles_completed' => 2,
        'max_cycles' => 5,
    ]);

    Http::fake([
        'api.sumit.co.il/billing/recurring/charge/' => Http::response([
            'Status' => 'Success',
            'TransactionID' => 'txn_recurring_123',
        ], 200),
    ]);

    $result = SubscriptionService::processRecurringCharge($subscription);

    $subscription->refresh();
    $this->assertEquals(3, $subscription->cycles_completed);
    $this->assertEquals('active', $subscription->status);
}
```

---

## Best Practices

### ✅ DO

1. **Always validate subscription ownership**
   ```php
   if ($subscription->owner_id !== $user->id) {
       abort(403);
   }
   ```

2. **Use queue jobs for recurring charges**
   ```php
   dispatch(new ProcessRecurringPaymentJob($subscription));
   ```

3. **Handle max_cycles completion**
   ```php
   if ($subscription->cycles_completed >= $subscription->max_cycles) {
       $subscription->update(['status' => 'expired']);
   }
   ```

4. **Sync regularly from SUMIT**
   ```php
   SubscriptionService::syncFromSumit($subscription);
   ```

5. **Record cancellation reasons**
   ```php
   SubscriptionService::cancel($subscription, 'Customer request via support');
   ```

### ❌ DON'T

1. **Don't process recurring charges synchronously in web requests**
   ```php
   // ❌ BAD
   SubscriptionService::processRecurringCharge($subscription);

   // ✅ GOOD
   dispatch(new ProcessRecurringPaymentJob($subscription));
   ```

2. **Don't skip initial charge during creation**
   - Subscription creation ALWAYS includes first payment

3. **Don't assume local status matches SUMIT**
   - Always sync from SUMIT before critical operations

4. **Don't cancel subscriptions without recording reason**
   ```php
   // ❌ BAD
   SubscriptionService::cancel($subscription);

   // ✅ GOOD
   SubscriptionService::cancel($subscription, $reason);
   ```

---

## Integration Points

### With PaymentService

```php
// SubscriptionService uses PaymentService for:
PaymentService::getCredentials()          // API credentials
PaymentService::getOrderCustomer()        // Customer deduplication
PaymentService::getPaymentOrderItems()    // Order items for invoice
PaymentService::getLanguage()            // UI language
PaymentService::createFromSumitResponse() // Transaction creation
```

### With DocumentService

```php
// Documents generated for:
1. Initial subscription charge
2. Each recurring charge
3. Subscription cancellation (credit note if needed)

DocumentService::createFromApiResponse($transaction, $order, 'subscription');
```

### With Job Queue

```php
// Jobs that call SubscriptionService:
ProcessRecurringPaymentsJob::handle()
ProcessSingleRecurringPaymentJob::handle()
SyncSubscriptionsFromSumitJob::handle()
```

---

## Configuration Dependencies

### Required Settings

```php
config('officeguy.company_id')           // SUMIT company ID
config('officeguy.company_code')         // SUMIT company code
config('officeguy.private_key')          // API private key
config('officeguy.currency')             // Default: 'ILS'
config('officeguy.environment')          // 'www' or 'dev'
config('officeguy.auto_generate_document') // true/false
```

---

## Complete Service Example

### Creating a Monthly Subscription

```php
use OfficeGuy\LaravelSumitGateway\Services\SubscriptionService;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken;

// Get order and saved payment method
$order = Order::find($orderId);
$token = OfficeGuyToken::where('owner_id', $user->id)
    ->where('is_default', true)
    ->first();

// Create subscription
$result = SubscriptionService::create($order, $token, [
    'interval_unit' => 'month',      // Monthly billing
    'interval_count' => 1,           // Every 1 month
    'max_cycles' => 12,              // 12 months total
    'start_date' => now()->addMonth(), // Start next month
    'description' => 'Premium Membership - Monthly',
]);

if ($result['success']) {
    $subscription = $result['subscription'];
    $transaction = $result['transaction'];

    // Subscription created with recurring_id from SUMIT
    // Initial payment processed
    // Document generated
    // SubscriptionCreated event dispatched

    return redirect()->route('subscription.success', $subscription);
}
```

---

## Summary

### Service Purpose
SubscriptionService is the **complete recurring billing solution** for the SUMIT package, handling all subscription lifecycle operations from creation through cancellation.

### Key Strengths
- ✅ Complete lifecycle management (create → charge → cancel)
- ✅ Automatic max_cycles tracking and expiration
- ✅ SUMIT synchronization for status accuracy
- ✅ Event-driven architecture for extensibility
- ✅ Integrates seamlessly with PaymentService and DocumentService

### Integration Requirements
- Must use PaymentService for credentials and transaction creation
- Requires valid payment method (token or new card)
- Depends on DocumentService for invoice/receipt generation
- Requires job queue for recurring charge processing

### Critical Implementation Notes
1. **Initial charge is ALWAYS processed** during subscription creation
2. **Recurring charges create NEW transactions** each cycle
3. **Status synchronization** required from SUMIT regularly
4. **Ownership validation** must be done by calling code
5. **Queue processing** required for recurring charges (not synchronous)

---

**Lines Analyzed**: 437
**Methods Documented**: 13
**Dependencies**: PaymentService, OfficeGuyApi, DocumentService, TokenService
**Events**: 4 (Created, Charged, ChargesFailed, Cancelled)
**SUMIT Endpoints**: 4 (/create/, /charge/, /cancel/, /listforcustomer/)
