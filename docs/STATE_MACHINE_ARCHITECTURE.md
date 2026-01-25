# State Machine & Workflow Architecture

**Package Version:** v2.4.0
**Documentation Date:** 2026-01-22
**Status:** ✅ Production Ready

---

## 📋 Overview

This document describes the **complete State Machine and Workflow architecture** for the SUMIT Payment Gateway integration. The system uses a **dual-layer architecture**:

1. **Application Layer** (`/httpdocs`) - Business State Machine (Order FSM)
2. **Package Layer** (`/SUMIT-Payment-Gateway`) - Event-Driven Execution

> **Key Principle:** State lives in the Application, Execution lives in the Package.

---

## 🏗️ Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                         APPLICATION                             │
│                     (httpdocs/app)                             │
│                                                                   │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │              ORDER STATE MACHINE                           │  │
│  │  ┌────────────┐    ┌────────────┐    ┌────────────┐     │  │
│  │  │ OrderStatus │───▶│ OrderState  │───▶│ Guards     │     │  │
│  │  │ Enum (13)  │    │ Machine     │    │ canBeX()   │     │  │
│  │  └────────────┘    └────────────┘    └────────────┘     │  │
│  │                                                             │  │
│  │  - VALID_TRANSITIONS (transition map)                       │  │
│  │  - STATUS_CATEGORIES (grouping)                            │  │
│  │  - TRANSITION_VALIDATIONS (business rules)                 │  │
│  │  - executePostTransitionActions()                          │  │
│  └───────────────────────────────────────────────────────────┘  │
│                              │                                     │
│                              │ PaymentCompleted Event             │
│                              ▼                                     │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │              AUDIT TRAIL                                   │  │
│  │  - OrderStatusAudit Model                                 │  │
│  │  - from_status, to_status                                  │  │
│  │  - user_id, context, ip_address                            │  │
│  └───────────────────────────────────────────────────────────┘  │
│                              │                                     │
│                              ▼                                     │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │              WORKFLOWS (Xentixar)                          │  │
│  │  - HasWorkflows Trait                                      │  │
│  │  - Workflow Manager v2.0                                    │  │
│  └───────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
                              │
                              │ via Event Listener
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                      PACKAGE                                    │
│              (/SUMIT-Payment-Gateway)                          │
│                                                                   │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │               EVENT-DRIVEN LAYER                           │  │
│  │  ┌────────────────┐      ┌──────────────────┐           │  │
│  │  │ Events (12+)    │─────▶│ Listeners (12+)   │           │  │
│  │  │ - PaymentCompleted│      │ - Fulfillment     │           │  │
│  │  │ - Subscription     │      │ - CustomerSync   │           │  │
│  │  │ - DocumentCreated │      │ - DocumentSync   │           │  │
│  │  └────────────────┘      └──────────────────┘           │  │
│  └────────────────────────────────────────────────────────────┘  │
│                              │                                     │
│                              ▼                                     │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │            FULFILLMENT DISPATCHER                           │  │
│  │  - Type-Based Dispatch (PayableType → Handler)              │  │
│  │  - Infrastructure → InfrastructureFulfillmentHandler          │  │
│  │  - DigitalProduct → DigitalProductFulfillmentHandler         │  │
│  │  - Subscription → SubscriptionFulfillmentHandler            │  │
│  └────────────────────────────────────────────────────────────┘  │
│                              │                                     │
│                              ▼                                     │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │            BULK ACTIONS (bytexr)                            │  │
│  │  - QueueableBulkAction                                     │  │
│  │  - Async execution with retries                             │  │
│  │  - Real-time progress tracking                              │  │
│  └────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🎯 Component Breakdown

### 1. Application Layer - Order State Machine

**File:** `/httpdocs/app/Services/Core/OrderStateMachine.php`

**Features:**
- ✅ **Validated Transitions** - `VALID_TRANSITIONS` map defines legal state changes
- ✅ **Guard Methods** - `canBeCancelled()`, `canBeRefunded()`
- ✅ **Transition Validations** - Business rules before state change
- ✅ **Audit Trail** - Every transition logged to `OrderStatusAudit`
- ✅ **Post-Transition Actions** - Jobs dispatched automatically

**Status Flow:**
```
pending → payment_processing → processing → provisioning → completed
                                                    ↓
                                                failed/cancelled
```

**Transitions Map:**
```php
const VALID_TRANSITIONS = [
    'pending' => ['payment_processing', 'cancelled', 'expired'],
    'payment_processing' => ['processing', 'failed', 'cancelled', 'requires_action'],
    'processing' => ['completed', 'failed', 'refunded', 'cancelled'],
    'provisioning' => ['completed', 'failed', 'partially_completed'],
    'completed' => ['refunded'],
    'failed' => ['pending', 'cancelled'], // Allow retry
];
```

**Example Usage:**
```php
$stateMachine = new OrderStateMachine($order);
$stateMachine->transitionTo(OrderStatus::PROCESSING, [
    'triggered_by' => 'payment_webhook',
]);
```

---

### ⚠️ IMPORTANT: Source of Truth for Status

**Principle:** Order status has **TWO complementary mechanisms** - not conflicting:

| Mechanism | Purpose | Scope |
|-----------|---------|-------|
| **OrderStateMachine** | **Business State Transitions** | Application logic |
| **spatie/laravel-model-status** | **Status Tracking** | Technical events |

**How They Work Together:**

```php
// ❌ WRONG: Direct status update (FORBIDDEN)
$order->update(['status' => OrderStatus::PROCESSING]);

// ✅ CORRECT: Through OrderStateMachine
$stateMachine = new OrderStateMachine($order);
$stateMachine->transitionTo(OrderStatus::PROCESSING);

// This internally:
// 1. Validates transition
// 2. Runs business validations
// 3. Creates audit trail
// 4. Updates status (via model-status)
// 5. Dispatches StatusUpdated event
// 6. Executes post-transition actions
```

**Why Both Exist:**

1. **OrderStateMachine** = Business Logic Layer
   - Validates IF transition is allowed
   - Runs business rules
   - Creates audit trail
   - Orchestrates post-transition actions

2. **spatie/laravel-model-status** = Technical Tracking Layer
   - Stores current status
   - Dispatches `StatusUpdated` event
   - Provides status history
   - Enables status queries

**Key Rules:**

❗ **NEVER update status directly:**
```php
// ❌ FORBIDDEN
$order->status = OrderStatus::PROCESSING;
$order->save();

// ❌ FORBIDDEN
$order->update(['status' => OrderStatus::PROCESSING]);
```

✅ **ALWAYS use OrderStateMachine:**
```php
// ✅ CORRECT
app(OrderStateMachine::class, $order)->transitionTo(OrderStatus::PROCESSING);
```

**Enforcement Recommendation:**

To prevent accidental direct updates, consider making `status` protected:

```php
// In Order Model
protected $status = OrderStatus::PENDING;

// Add mutator that enforces OrderStateMachine
public function setStatus(OrderStatus $status): void
{
    throw new \Exception(
        'Direct status updates are forbidden. ' .
        'Use OrderStateMachine::transitionTo() instead.'
    );
}
```

---

### 2. Application Layer - OrderStatus Enum

**File:** `/httpdocs/app/Enums/OrderStatus.php`

**13 States with Full Filament Integration:**

| State | Label (Hebrew) | Color | Icon |
|-------|----------------|-------|------|
| PENDING | ממתין | warning | heroicon-o-clock |
| PAYMENT_PROCESSING | מעבד תשלום | info | heroicon-o-credit-card |
| PROCESSING | בעיבוד | info | heroicon-o-arrow-path |
| AWAITING_PROVISIONING | ממתין להפעלה | warning | heroicon-o-queue-list |
| PROVISIONING_RETRYING | מנסה להפעיל שוב | warning | heroicon-o-arrow-path-rounded-square |
| PROVISIONED | הופעל | success | heroicon-o-check-badge |
| PROVISIONING_FAILED | הפעלה נכשלה | danger | heroicon-o-exclamation-circle |
| COMPLETED | הושלם | info | heroicon-o-check |
| ACTIVE | פעיל | success | heroicon-o-check-circle |
| SUSPENDED | מושהה | danger | heroicon-o-pause-circle |
| FAILED | נכשל | danger | heroicon-o-x-circle |
| CANCELLED | מבוטל | gray | heroicon-o-minus-circle |
| REFUNDED | הוחזר | danger | heroicon-o-arrow-uturn-left |

**Implements:**
- `HasLabel` - Hebrew labels
- `HasColor` - Filament color scheme
- `HasIcon` - Heroicon icons

---

### 3. Application Layer - OrderStatusAudit Model

**File:** `/httpdocs/app/Models/OrderStatusAudit.php`

**Tracks Every Status Change:**

```php
OrderStatusAudit::query()->create([
    'order_id' => $order->id,
    'from_status' => 'processing',
    'to_status' => 'completed',
    'user_id' => auth()->id(),
    'context' => ['triggered_by' => 'payment_webhook'],
    'ip_address' => request()->ip(),
    'user_agent' => request()->userAgent(),
    'created_at' => now(),
]);
```

**Query Methods:**
- `getTrailForOrder($orderId)` - Full audit trail for specific order
- `getRecentChanges($limit = 50)` - Recent status changes across all orders

---

### 4. Application Layer - Payment Event Listener

**File:** `/httpdocs/app/Listeners/TriggerProvisioningOnPaymentComplete.php`

**Listens to:** `Spatie\ModelStatus\Events\StatusUpdated`

**Workflow:**
```php
// When Order status → 'processing'
if ($event->newStatus === 'processing') {
    // Auto-transition to 'provisioning' after 10 seconds
    dispatch(new TransitionOrderStatusJob($order, 'provisioning'))
        ->delay(now()->addSeconds(10));
}
```

---

### 5. Package Layer - Events

**Location:** `/SUMIT-Payment-Gateway/src/Events/`

| Event | Purpose | Dispatched By |
|-------|---------|----------------|
| PaymentCompleted | Payment successful | PaymentService |
| PaymentFailed | Payment failed | PaymentService |
| SubscriptionCreated | New subscription | SubscriptionService |
| SubscriptionCharged | Recurring charge success | SubscriptionService |
| SubscriptionChargesFailed | Recurring charge failed | SubscriptionService |
| SubscriptionCancelled | Subscription cancelled | SubscriptionService |
| DocumentCreated | Invoice/receipt generated | DocumentService |
| BitPaymentCompleted | Bit payment success | BitPaymentService |
| SumitWebhookReceived | Incoming SUMIT webhook | SumitWebhookController |

---

### 6. Package Layer - Listeners

**Location:** `/SUMIT-Payment-Gateway/src/Listeners/`

| Listener | Event | Action |
|----------|-------|--------|
| FulfillmentListener | PaymentCompleted | Dispatches to FulfillmentDispatcher |
| CustomerSyncListener | SumitWebhookReceived | Syncs SUMIT customers |
| DocumentSyncListener | SumitWebhookReceived | Syncs documents |
| CrmActivitySyncListener | SumitWebhookReceived | Syncs CRM activities |
| AutoCreateUserListener | PaymentCompleted | Creates user for guest checkout |
| TransactionSyncListener | SumitWebhookReceived | Confirms card payments |
| RefundWebhookListener | SumitWebhookReceived | Processes refunds |
| NotifyPaymentCompletedListener | PaymentCompleted | Database notification |
| NotifyPaymentFailedListener | PaymentFailed | Database notification |
| NotifySubscriptionCreatedListener | SubscriptionCreated | Database notification |
| NotifyDocumentCreatedListener | DocumentCreated | Database notification |

---

### 7. Package Layer - Fulfillment Dispatcher

**File:** `/SUMIT-Payment-Gateway/src/Services/FulfillmentDispatcher.php`

**Type-Based Dispatch:**

```php
$dispatcher->registerMany([
    PayableType::INFRASTRUCTURE->value => InfrastructureFulfillmentHandler::class,
    PayableType::DIGITAL_PRODUCT->value => DigitalProductFulfillmentHandler::class,
    PayableType::SUBSCRIPTION->value => SubscriptionFulfillmentHandler::class,
    PayableType::GENERIC->value => GenericFulfillmentHandler::class,
]);
```

**Registration:** In `OfficeGuyServiceProvider::registerFulfillmentHandlers()`

---

### 8. Package Layer - Bulk Actions

**Package:** `bytexr/filament-queueable-bulk-actions` (v4.0)

**Jobs Created (v2.4.0):**

| Job | Purpose | Priority |
|-----|---------|----------|
| BulkSubscriptionCancelJob | Cancel subscriptions | P0 |
| BulkTokenSyncJob | Sync tokens from SUMIT | P0 |
| BulkDocumentEmailJob | Email documents | P1 |
| BulkSubscriptionChargeJob | Charge subscriptions | P1 |
| BulkPayableMappingActivateJob | Activate mappings | P1 |
| BulkPayableMappingDeactivateJob | Deactivate mappings | P1 |

**Features:**
- ✅ Async queue execution
- ✅ Real-time progress tracking
- ✅ Exponential backoff (60s, 300s, 900s)
- ✅ Per-record telemetry
- ✅ Feature flags (disabled by default)

---

## 🔗 Integration Flow (End-to-End)

### Payment → Fulfillment Flow

```
┌────────────────────────────────────────────────────────────────┐
│ 1. PAYMENT INITIATED (Application)                            │
│    User pays via SUMIT Gateway                                 │
│    → OfficeGuyTransaction created                              │
└──────────────────────────┬─────────────────────────────────────┘
                           │
                           ▼
┌────────────────────────────────────────────────────────────────┐
│ 2. SUMIT WEBHOOK RECEIVED (Package)                           │
│    SumitWebhookReceived event dispatched                      │
│    → TransactionSyncListener: confirms payment               │
└──────────────────────────┬─────────────────────────────────────┘
                           │
                           ▼
┌────────────────────────────────────────────────────────────────┐
│ 3. TRANSACTION UPDATED (Package)                              │
│    OfficeGuyTransaction status → 'completed'                 │
│    → PaymentCompleted event dispatched                      │
└──────────────────────────┬─────────────────────────────────────┘
                           │
        ┌──────────────────┴──────────────────┐
        ▼                                     ▼
┌──────────────────────┐         ┌──────────────────────┐
│ 4a. PACKAGE SIDE     │         │ 4b. APP SIDE         │
│ FulfillmentListener  │         │ TriggerProvisioning  │
│      │               │         │ OnPaymentComplete    │
│      ▼               │         │      │               │
│ FulfillmentDispatcher│         │ Listens for         │
│      │               │         │ StatusUpdated event  │
│      ▼               │         │      │               │
│ Handler invoked      │         │      ▼               │
│ (provision service)  │         │ OrderStateMachine   │
│                      │         │ processing→provisioning│
└──────────────────────┘         └──────────────────────┘
        │                                     │
        │ (Note: Package does NOT    │
        │  decide business meaning) │
        │                                     │
        └─────────────────────────────┘
```

### ⚠️ Critical Distinction: Who Decides What?

**IMPORTANT:** Package listeners **NEVER** decide business meaning.

| Layer | Responsibility | Example |
|-------|---------------|---------|
| **Application** | Decides WHEN to transition | "Payment received → move to PROCESSING" |
| **Package** | Executes AFTER decision | "Payment completed → dispatch fulfillment" |

**Package Layer Role:**
```php
// ✅ CORRECT: Package reacts to Application decision
class FulfillmentListener
{
    public function handle(PaymentCompleted $event)
    {
        // Package only executes fulfillment
        // It does NOT decide "is this order ready for fulfillment?"
        // That decision was ALREADY MADE by the Application
        $this->dispatcher->dispatch($event->payable, $event->transaction);
    }
}
```

**Application Layer Role:**
```php
// ✅ CORRECT: Application decides business meaning
class TriggerProvisioningOnPaymentComplete
{
    public function handle(StatusUpdated $event)
    {
        // Application decides: "processing means start provisioning"
        if ($event->newStatus === 'processing') {
            dispatch(new TransitionOrderStatusJob($order, 'provisioning'));
        }
    }
}
```

**Key Principle:**
- ❌ Package does NOT say "order is ready for provisioning"
- ✅ Application says "order is ready for provisioning" → Package executes provisioning
- ❌ Package does NOT validate business rules
- ✅ Application validates business rules → Package executes after validation

---

### Status Change Flow

```
┌────────────────────────────────────────────────────────────────┐
│ 1. APPLICATION DECIDES TO TRANSITION                           │
│    OrderStateMachine::transitionTo(OrderStatus::PROCESSING)    │
│    → Validates: Is transition allowed?                           │
│    → Validates: Business rules satisfied?                        │
└──────────────────────────┬─────────────────────────────────────┘
                           │
                           ▼
┌────────────────────────────────────────────────────────────────┐
│ 2. ORDER STATE MACHINE EXECUTES                               │
│    → Creates OrderStatusAudit entry (audit trail)             │
│    → Updates Order status (via model-status)                    │
│    → Dispatches StatusUpdated event                             │
│    → Executes post-transition actions                           │
└──────────────────────────┬─────────────────────────────────────┘
                           │
                           ▼
┌────────────────────────────────────────────────────────────────┐
│ 3. STATUS UPDATED EVENT TRIGGERS LISTENERS                    │
│    → TriggerProvisioningOnPaymentComplete (Application)        │
│       → Decides business meaning: "processing = start provisioning"│
│    → FulfillmentListener (Package)                             │
│       → Receives event, does NOT decide meaning                 │
│       → Executes fulfillment dispatch                             │
└──────────────────────────┬─────────────────────────────────────┘
                           │
                           ▼
┌────────────────────────────────────────────────────────────────┐
│ 4. PROVISIONING EXECUTED (Package/Handler)                     │
│    → ProvisionServiceJob or similar                           │
│    → Actual service provisioning happens                           │
└────────────────────────────────────────────────────────────────┘
```

### Status Change Flow

```
┌────────────────────────────────────────────────────────────────┐
│ 1. ORDER STATE MACHINE TRANSITION                            │
│    $stateMachine->transitionTo(OrderStatus::PROCESSING);      │
└──────────────────────────┬─────────────────────────────────────┘
                           │
                           ▼
┌────────────────────────────────────────────────────────────────┐
│ 2. TRANSITION VALIDATED                                       │
│    ✓ Check VALID_TRANSITIONS map                             │
│    ✓ Run TRANSITION_VALIDATIONS                              │
│    ✓ Create OrderStatusAudit entry                          │
└──────────────────────────┬─────────────────────────────────────┘
                           │
                           ▼
┌────────────────────────────────────────────────────────────────┐
│ 3. STATUS UPDATED                                            │
│    Order::update(['status' => OrderStatus::PROCESSING])       │
│    → Spatie\ModelStatus\Events\StatusUpdated dispatched       │
└──────────────────────────┬─────────────────────────────────────┘
                           │
                           ▼
┌────────────────────────────────────────────────────────────────┐
│ 4. POST-TRANSITION ACTIONS                                   │
│    executePostTransitionActions('processing'):               │
│    → triggerProvisioningWorkflow()                           │
│    → dispatch(TransitionOrderStatusJob($order, 'provisioning'))│
└────────────────────────────────────────────────────────────────┘
```

---

## 📦 Dependencies

### Application Dependencies

```json
{
    "spatie/laravel-model-status": "^1.18",
    "xentixar/workflow-manager": "^2.0"
}
```

### Package Dependencies

```json
{
    "bytexr/filament-queueable-bulk-actions": "^4.0",
    "saloon/laravel": "^3.14.2"
}
```

---

## 🎓 Design Patterns Used

| Pattern | Location | Purpose |
|---------|----------|---------|
| **State Machine** | OrderStateMachine | Validate and execute state transitions |
| **Event-Driven** | Events/Listeners | Decouple components |
| **Type-Based Dispatch** | FulfillmentDispatcher | Route to appropriate handler |
| **Audit Trail** | OrderStatusAudit | Compliance and debugging |
| **Observer Pattern** | StatusUpdated listener | React to state changes |
| **Command Pattern** | Jobs | Encapsulate actions |
| **Strategy Pattern** | Fulfillment handlers | Swap fulfillment logic |

---

## 🔧 Configuration

### Enable Bulk Actions

Add to `.env`:

```bash
OFFICEGUY_BULK_ACTIONS_ENABLED=true
OFFICEGUY_BULK_ACTIONS_QUEUE=officeguy-bulk-actions
```

### Enable Legacy Actions (Backward Compatibility)

```bash
OFFICEGUY_ENABLE_LEGACY_BULK_ACTIONS=true
```

---

## 📊 State Transition Matrix

```
FROM \ TO        pending  payment_processing  processing  provisioning  completed  failed  cancelled  refunded
────────────────────────────────────────────────────────────────────────────────────────
pending                 ✓           ✓               ✗            ✗           ✗       ✓       ✓        ✗
payment_processing       ✗           ✗               ✓            ✗           ✗       ✓       ✓        ✗
processing              ✗           ✗               ✗            ✓           ✓       ✓       ✓        ✓
provisioning            ✗           ✗               ✗            ✗           ✓       ✓       ✓        ✗
completed               ✗           ✗               ✗            ✗           ✗       ✗       ✗        ✓
failed                  ✓           ✗               ✗            ✗           ✗       ✗       ✓        ✗
cancelled               ✗           ✗               ✗            ✗           ✗       ✗       ✗        ✗
refunded               ✗           ✗               ✗            ✗           ✗       ✗       ✗        ✗
```

**Legend:** ✓ = Allowed, ✗ = Not Allowed

---

## 🚨 Important Architecture Decisions

### ❌ Why We DON'T Need spatie/laravel-model-states

You might be wondering: *"Shouldn't we use spatie/laravel-model-states for a proper State Machine?"*

**Answer:** NO - and here's why:

**We intentionally use a custom OrderStateMachine instead of a generic FSM package.**

**Reason 1: Domain Complexity**
```
Our domain requires:
- Complex, non-linear transitions
- External event dependencies (webhooks, async jobs)
- Tight coupling of audit, validation, and orchestration

Generic FSM packages assume:
- Simple state transitions
- Internal state management
- Decoupled concerns
```

**Reason 2: We Already Have ALL The Features**

| Feature | Generic Package | Our Implementation |
|---------|-----------------|---------------------|
| Validated Transitions | ✅ | ✅ (VALID_TRANSITIONS) |
| Guard Methods | ✅ | ✅ (canBeCancelled, etc.) |
| Transition Validation | ✅ | ✅ (validatePaymentReceived) |
| Audit Trail | ❌ (add-on) | ✅ (OrderStatusAudit) |
| Post-Transition Hooks | ✅ | ✅ (executePostTransitionActions) |
| Direct Status Updates | ✅ | ❌ (intentionally blocked) |

**Reason 3: Source of Truth Clarity**

```
With spatie/laravel-model-states:
  Model::status() → State class
  → Who updates the status? Where?
  → Multiple potential sources of truth

With our approach:
  OrderStateMachine::transitionTo()
  → Single, explicit entry point
  → Clear ownership: Application controls state
```

**Reason 4: Integration with Existing Stack**

We already have:
- ✅ `spatie/laravel-model-status` (status tracking)
- ✅ `xentixar/workflow-manager` (workflow orchestration)
- ✅ Custom `OrderStateMachine` (business state logic)

Adding another FSM package would create **confusion**, not clarity.

---

## 🚨 Important Notes

### 1. State Lives in Application

**Principle:** The Application Layer owns the **Business State Machine**.

The **Order State Machine** belongs to the **Application** (`/httpdocs`), not the package. This is intentional:

- ✅ Application owns business logic
- ✅ Application owns state transitions
- ✅ Application owns decision-making ("WHEN" to transition)
- ❌ Package does NOT manage application state
- ✅ Package only executes actions AFTER state is decided

### 2. Package is Domain-Specific (Billing/Payment)

**Principle:** The Package Layer executes **Domain Logic** but NOT **Business Decisions**.

The SUMIT Payment Gateway package is a **Domain Package** (`Billing`/`Payment` domain):

- ✅ It knows **HOW** to charge, invoice, sync
- ❌ It does NOT know **WHEN** to charge, invoice, sync
- ✅ It knows HOW to create a transaction
- ❌ It does NOT know IF a transaction should be created

**Examples:**

| Responsibility | Layer | Example |
|---------------|-------|---------|
| "Payment received → move to PROCESSING" | Application | OrderStateMachine decides |
| "Payment completed → create invoice" | Package | DocumentService executes |
| "Subscription activated → provision service" | Application | TriggerProvisioningListener decides |
| "eSIM ordered → activate SIM" | Package | DigitalProductFulfillmentHandler executes |

### 3. Events Bridge the Layers (NOT Decisions)

**Principle:** Events are a **notification mechanism**, not a **decision mechanism**.

Events are the **glue** between Application and Package:

```
Application (State Decision) → Event → Package (Execution)
```

**Critical Distinction:**

| Question | Answer | Owner |
|----------|-------|-------|
| "Is this order ready for provisioning?" | Application (StatusUpdated listener) | ✅ |
| "What does 'processing' mean?" | Application (TriggerProvisioning listener) | ✅ |
| "How do I provision this service?" | Package (Fulfillment handlers) | ✅ |
| "Should I create an invoice?" | Application (decides) | ✅ |
| "How do I create an invoice?" | Package (DocumentService) | ✅ |

**Package Listeners React to Validated Events:**

```php
// ✅ CORRECT: Package reacts to application decision
class FulfillmentListener
{
    public function handle(PaymentCompleted $event)
    {
        // PaymentCompleted means: "Application decided payment is complete"
        // Package's job: Execute fulfillment, NOT decide if fulfillment is needed
        $this->dispatcher->dispatch($event->payable, $event->transaction);
    }
}
```

**Note:** Package listeners never validate business meaning - they assume events are already validated by Application.

---

## 📝 Best Practices

### When to Add New States

1. **Application** - Add to `OrderStatus` enum
2. **Application** - Update `VALID_TRANSITIONS` in `OrderStateMachine`
3. **Application** - Add validation logic if needed
4. **Package** - Consider adding new Event if relevant

### When to Add New Transitions

1. **Application** - Update `VALID_TRANSITIONS` map
2. **Application** - Add guard method (`canBeX()`)
3. **Application** - Add validation method (`validateX()`)
4. **Test** both valid and invalid transitions

### When to Add New Events

1. **Package** - Create Event class in `src/Events/`
2. **Package** - Dispatch Event at appropriate moment
3. **Application** - Create Listener in `app/Listeners/`
4. **Application** - Register in `EventServiceProvider`

### When to Add New Bulk Actions

1. **Package** - Create Job in `src/Jobs/BulkActions/`
2. **Package** - Extend `BaseBulkActionJob`
3. **Package** - Add to Resource using `QueueableBulkAction`
4. **Package** - Add translations (he/en)

---

## 🔍 Debugging

### Check Order State

```php
$order = Order::find($id);

// Current status
echo $order->status->value; // 'processing'

// Allowed transitions
$stateMachine = new OrderStateMachine($order);
print_r($stateMachine->getAllowedTransitions());
// ['completed', 'failed', 'refunded', 'cancelled']

// Can cancel?
echo $stateMachine->canBeCancelled(); // false

// Audit trail
$audit = OrderStatusAudit::getTrailForOrder($order->id);
foreach ($audit as $entry) {
    echo "{$entry->from_status} → {$entry->to_status}\n";
}
```

### Check Bulk Action Progress

```php
use Bytexr\QueueableBulkActions\Models\BulkAction;

$bulkAction = BulkAction::find($id);

echo "Status: {$bulkAction->status}\n";
echo "Progress: {$bulkAction->processed_records}/{$bulkAction->total_records}\n";

foreach ($bulkAction->records as $record) {
    echo "Record {$record->model_id}: {$record->status}\n";
}
```

---

## 📚 Related Documentation

- **CLAUDE.md** - Main development guide
- **QUEUEABLE_BULK_ACTIONS_INTEGRATION.md** - Bulk actions setup
- **PAYABLE_FIELD_MAPPING_WIZARD.md** - Field mapping guide
- **CRM_INTEGRATION.md** - CRM sync workflows
- **INFRASTRUCTURE_FULFILLMENT.md** - Infrastructure provisioning
- **DIGITAL_PRODUCT_FULFILLMENT.md** - Digital product fulfillment

---

## 📞 Support

For questions or issues:
- **GitHub**: https://github.com/nm-digitalhub/SUMIT-Payment-Gateway-for-laravel
- **Email**: info@nm-digitalhub.com

---

**Last Updated:** 2026-01-22
**Version:** 1.0.0
