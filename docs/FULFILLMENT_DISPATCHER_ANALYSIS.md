# FulfillmentDispatcher Analysis

**File**: `src/Services/FulfillmentDispatcher.php`
**Lines**: 165
**Type**: Instance Service Class (container-managed)
**Purpose**: Type-based fulfillment orchestration using Strategy Pattern

---

## Overview

FulfillmentDispatcher implements a **Type-Based Dispatch Pattern** that routes post-payment fulfillment operations to specialized handlers based on `PayableType`. This enables clean separation of concerns and extensibility without modifying core payment logic.

### Architecture Decision: Type-Based Dispatch

**Pattern Choice**: Strategy Pattern with Container Bindings

```
PayableType → Handler Mapping
├─ DIGITAL_PRODUCT → DigitalProductFulfillmentHandler
├─ SUBSCRIPTION → SubscriptionFulfillmentHandler
├─ INFRASTRUCTURE → InfrastructureFulfillmentHandler
└─ [Custom Types] → [Custom Handlers]
```

**Design Principles**:
1. **Payable only returns Type** via `getPayableType()`
2. **Dispatcher maps Type→Handler** (centralized in ServiceProvider)
3. **Optional override** via `getFulfillmentHandler()` for special cases
4. **Laravel convention** (bindings registered in ServiceProvider)

**Benefits**:
- ✅ **Centralized configuration** - all mappings in one place (ServiceProvider)
- ✅ **Type as SSOT** - Single Source of Truth for fulfillment routing
- ✅ **Testability** - easy to swap handlers in tests (mock, fake)
- ✅ **Extensibility** - add new types without modifying dispatcher
- ✅ **Laravel convention** - uses container bindings naturally

---

## Class Structure

```php
namespace OfficeGuy\LaravelSumitGateway\Services;

use OfficeGuy\LaravelSumitGateway\Contracts\Payable;
use OfficeGuy\LaravelSumitGateway\Enums\PayableType;

class FulfillmentDispatcher
{
    /** @var array<string, string> Type→Handler mappings */
    protected array $handlers = [];

    // Registration Methods
    public function register(PayableType $type, string $handlerClass): void
    public function registerMany(array $mappings): void

    // Dispatch Method (Core)
    public function dispatch(Payable $payable, OfficeGuyTransaction $transaction): void

    // Query Methods
    public function hasHandler(PayableType $type): bool
    public function getHandler(PayableType $type): ?string
    public function getHandlers(): array

    // Testing Helper
    public function clearHandlers(): void
}
```

---

## Methods Analysis

### 1. `register()` - Register Type→Handler Mapping

**Lines**: 47-55
**Signature**:
```php
public function register(PayableType $type, string $handlerClass): void
```

**Purpose**: Register a fulfillment handler for a specific PayableType

**Parameters**:
- `$type` - PayableType enum (e.g., `PayableType::DIGITAL_PRODUCT`)
- `$handlerClass` - Fully-qualified handler class name

**Process**:
```
1. Store mapping: $handlers[$type->value] = $handlerClass
2. Log registration (debug level)
```

**Implementation**:
```php
public function register(PayableType $type, string $handlerClass): void
{
    $this->handlers[$type->value] = $handlerClass;

    OfficeGuyApi::writeToLog(
        "FulfillmentDispatcher: Registered {$handlerClass} for type {$type->value}",
        'debug'
    );
}
```

**Usage in ServiceProvider**:
```php
// src/OfficeGuyServiceProvider.php
public function boot(): void
{
    $dispatcher = app(FulfillmentDispatcher::class);

    $dispatcher->register(
        PayableType::DIGITAL_PRODUCT,
        \OfficeGuy\LaravelSumitGateway\Handlers\DigitalProductFulfillmentHandler::class
    );

    $dispatcher->register(
        PayableType::SUBSCRIPTION,
        \OfficeGuy\LaravelSumitGateway\Handlers\SubscriptionFulfillmentHandler::class
    );

    $dispatcher->register(
        PayableType::INFRASTRUCTURE,
        \OfficeGuy\LaravelSumitGateway\Handlers\InfrastructureFulfillmentHandler::class
    );
}
```

**Critical Notes**:
- ✅ Called during **ServiceProvider boot()** - all mappings defined centrally
- ✅ Uses **PayableType enum** - type-safe registration
- ✅ Fully-qualified class name - no ambiguity

---

### 2. `dispatch()` - Route to Appropriate Handler

**Lines**: 69-107
**Signature**:
```php
public function dispatch(Payable $payable, OfficeGuyTransaction $transaction): void
```

**Purpose**: Dispatch fulfillment to the correct handler using 3-priority system

**Parameters**:
- `$payable` - The payable entity (Order, DigitalProduct, etc.)
- `$transaction` - The completed payment transaction

**3-Priority Dispatch System**:
```
Priority 1: Custom Handler Override (Rare)
├─ Check if Payable implements getFulfillmentHandler()
├─ Return custom handler class name
└─ Use if needs special fulfillment logic

Priority 2: Type-Based Handler (Common Path)
├─ Get PayableType from $payable->getPayableType()
├─ Lookup handler in $handlers[$type->value]
└─ Dispatch to registered handler

Priority 3: No Handler Found (Warning)
├─ Log warning about missing handler
└─ Continue without fulfillment (graceful degradation)
```

**Implementation**:
```php
public function dispatch(Payable $payable, OfficeGuyTransaction $transaction): void
{
    // Priority 1: Optional Payable-specific override (rare)
    if (method_exists($payable, 'getFulfillmentHandler')) {
        $customHandler = $payable->getFulfillmentHandler();

        if ($customHandler && class_exists($customHandler)) {
            OfficeGuyApi::writeToLog(
                "FulfillmentDispatcher: Using custom handler {$customHandler} for payable {$payable->id}",
                'info'
            );

            app($customHandler)->handle($transaction);  // ← Resolve from container
            return;
        }
    }

    // Priority 2: Type-based handler (common path)
    $type = $payable->getPayableType();

    if ($handler = $this->handlers[$type->value] ?? null) {
        OfficeGuyApi::writeToLog(
            "FulfillmentDispatcher: Dispatching to {$handler} for type {$type->value}",
            'info'
        );

        app($handler)->handle($transaction);  // ← Resolve from container
        return;
    }

    // Priority 3: No handler registered - log warning
    OfficeGuyApi::writeToLog(
        "FulfillmentDispatcher: No handler registered for type {$type->value}, payable {$payable->id}. Consider registering a handler in ServiceProvider.",
        'warning'
    );

    // Graceful degradation - continue without fulfillment
}
```

**Example Flow - Digital Product**:
```php
// 1. Payment completed for digital product
$payable = DigitalProduct::find(1);  // implements Payable
$transaction = OfficeGuyTransaction::find(123);

// 2. Dispatcher called from event listener
$dispatcher = app(FulfillmentDispatcher::class);
$dispatcher->dispatch($payable, $transaction);

// 3. Dispatcher resolves:
$type = $payable->getPayableType();  // PayableType::DIGITAL_PRODUCT

// 4. Lookup handler:
$handler = $this->handlers['digital_product'];
// → DigitalProductFulfillmentHandler::class

// 5. Resolve from container and call:
app(DigitalProductFulfillmentHandler::class)->handle($transaction);
// → Sends download link via email
```

**Example Flow - Custom Override**:
```php
// 1. Special order needs custom fulfillment
class SpecialOrder extends Order
{
    public function getFulfillmentHandler(): string
    {
        return SpecialOrderFulfillmentHandler::class;  // ← Override!
    }
}

// 2. Dispatcher called
$dispatcher->dispatch($specialOrder, $transaction);

// 3. Dispatcher detects override:
if (method_exists($payable, 'getFulfillmentHandler')) {
    $customHandler = $payable->getFulfillmentHandler();  // SpecialOrderFulfillmentHandler
    app($customHandler)->handle($transaction);  // ← Uses custom handler
    return;
}
```

**Return Value**: `void` (fires and forgets)

**Critical Notes**:
- ✅ Uses **Laravel Container** (`app($handler)`) - supports DI
- ✅ **Graceful degradation** - logs warning, continues without error
- ✅ **Override mechanism** - Payable can specify custom handler
- ⚠️ Handler resolution happens **at runtime** - class must exist

---

### 3. `hasHandler()` - Check if Type Has Handler

**Lines**: 115-118
**Signature**:
```php
public function hasHandler(PayableType $type): bool
```

**Purpose**: Check if a fulfillment handler is registered for a type

**Usage**:
```php
$dispatcher = app(FulfillmentDispatcher::class);

if ($dispatcher->hasHandler(PayableType::DIGITAL_PRODUCT)) {
    // Handler registered, fulfillment will occur
} else {
    // No handler - fulfillment will be skipped
}
```

**Use Cases**:
- Conditional logic before dispatch
- Validation in admin panel
- Testing handler registration

---

### 4. `getHandler()` - Get Handler for Type

**Lines**: 136-139
**Signature**:
```php
public function getHandler(PayableType $type): ?string
```

**Purpose**: Get the registered handler class name for a type

**Return Value**:
```php
// If handler registered:
'OfficeGuy\LaravelSumitGateway\Handlers\DigitalProductFulfillmentHandler'

// If no handler:
null
```

**Usage**:
```php
$handlerClass = $dispatcher->getHandler(PayableType::SUBSCRIPTION);

if ($handlerClass) {
    echo "Subscription fulfillment handled by: {$handlerClass}";
}
```

---

### 5. `getHandlers()` - Get All Registered Handlers

**Lines**: 125-128
**Signature**:
```php
public function getHandlers(): array
```

**Purpose**: Get all registered Type→Handler mappings (debugging, testing)

**Return Value**:
```php
[
    'digital_product' => 'OfficeGuy\LaravelSumitGateway\Handlers\DigitalProductFulfillmentHandler',
    'subscription' => 'OfficeGuy\LaravelSumitGateway\Handlers\SubscriptionFulfillmentHandler',
    'infrastructure' => 'OfficeGuy\LaravelSumitGateway\Handlers\InfrastructureFulfillmentHandler',
]
```

**Usage**:
```php
// In admin panel or debug page:
$handlers = $dispatcher->getHandlers();

foreach ($handlers as $type => $handlerClass) {
    echo "{$type} → {$handlerClass}\n";
}
```

---

### 6. `clearHandlers()` - Clear All Handlers

**Lines**: 146-149
**Signature**:
```php
public function clearHandlers(): void
```

**Purpose**: Remove all registered handlers (for testing)

**Usage in Tests**:
```php
public function setUp(): void
{
    parent::setUp();

    $dispatcher = app(FulfillmentDispatcher::class);
    $dispatcher->clearHandlers();  // ← Clean slate for test

    // Register test handlers
    $dispatcher->register(PayableType::DIGITAL_PRODUCT, MockHandler::class);
}
```

---

### 7. `registerMany()` - Batch Registration

**Lines**: 157-164
**Signature**:
```php
public function registerMany(array $mappings): void
```

**Purpose**: Register multiple Type→Handler mappings at once

**Parameters**:
- `$mappings` - Array of `[type_value => handler_class]`

**Usage**:
```php
$dispatcher->registerMany([
    'digital_product' => DigitalProductFulfillmentHandler::class,
    'subscription' => SubscriptionFulfillmentHandler::class,
    'infrastructure' => InfrastructureFulfillmentHandler::class,
]);
```

**Alternative to Individual Registration**:
```php
// Instead of:
$dispatcher->register(PayableType::DIGITAL_PRODUCT, DigitalProductFulfillmentHandler::class);
$dispatcher->register(PayableType::SUBSCRIPTION, SubscriptionFulfillmentHandler::class);
$dispatcher->register(PayableType::INFRASTRUCTURE, InfrastructureFulfillmentHandler::class);

// Use:
$dispatcher->registerMany([
    'digital_product' => DigitalProductFulfillmentHandler::class,
    'subscription' => SubscriptionFulfillmentHandler::class,
    'infrastructure' => InfrastructureFulfillmentHandler::class,
]);
```

---

## Handler Interface Contract

### Handler Implementation Pattern

All fulfillment handlers MUST implement this method signature:

```php
namespace OfficeGuy\LaravelSumitGateway\Handlers;

use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;

class DigitalProductFulfillmentHandler
{
    /**
     * Handle fulfillment for digital product
     *
     * @param OfficeGuyTransaction $transaction
     * @return void
     */
    public function handle(OfficeGuyTransaction $transaction): void
    {
        // 1. Get payable from transaction
        $payable = $transaction->payable;  // Polymorphic relationship

        // 2. Perform fulfillment logic
        $downloadLink = $payable->generateDownloadLink();

        // 3. Send to customer
        Mail::to($transaction->customer_email)
            ->send(new DigitalProductPurchasedMail($downloadLink));

        // 4. Log fulfillment
        $transaction->addNote('Digital product download link sent to customer');
    }
}
```

**Contract Requirements**:
- ✅ Public `handle(OfficeGuyTransaction $transaction)` method
- ✅ Return type: `void`
- ✅ Can use DI in constructor (resolved from container)

---

## PayableType Enum

### Definition

```php
namespace OfficeGuy\LaravelSumitGateway\Enums;

enum PayableType: string
{
    case DIGITAL_PRODUCT = 'digital_product';
    case SUBSCRIPTION = 'subscription';
    case INFRASTRUCTURE = 'infrastructure';
    case PHYSICAL_PRODUCT = 'physical_product';
    case SERVICE = 'service';
    case DONATION = 'donation';
    // ... extensible
}
```

### Payable Implementation

```php
use OfficeGuy\LaravelSumitGateway\Contracts\Payable;
use OfficeGuy\LaravelSumitGateway\Enums\PayableType;

class DigitalProduct implements Payable
{
    public function getPayableType(): PayableType
    {
        return PayableType::DIGITAL_PRODUCT;
    }

    // Optional: Custom handler override
    public function getFulfillmentHandler(): ?string
    {
        // Return null to use type-based handler
        // Return class name to override
        return null;
    }
}
```

---

## Integration Points

### 1. ServiceProvider Registration

**File**: `src/OfficeGuyServiceProvider.php`

```php
public function boot(): void
{
    // ... other boot logic

    // Register fulfillment handlers
    $this->registerFulfillmentHandlers();
}

protected function registerFulfillmentHandlers(): void
{
    $dispatcher = app(FulfillmentDispatcher::class);

    $dispatcher->registerMany([
        PayableType::DIGITAL_PRODUCT->value => DigitalProductFulfillmentHandler::class,
        PayableType::SUBSCRIPTION->value => SubscriptionFulfillmentHandler::class,
        PayableType::INFRASTRUCTURE->value => InfrastructureFulfillmentHandler::class,
    ]);
}
```

### 2. Event Listener Invocation

**File**: `src/Listeners/FulfillmentListener.php`

```php
namespace OfficeGuy\LaravelSumitGateway\Listeners;

use OfficeGuy\LaravelSumitGateway\Events\PaymentCompleted;
use OfficeGuy\LaravelSumitGateway\Services\FulfillmentDispatcher;

class FulfillmentListener
{
    protected FulfillmentDispatcher $dispatcher;

    public function __construct(FulfillmentDispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function handle(PaymentCompleted $event): void
    {
        $transaction = $event->transaction;
        $payable = $transaction->payable;  // Polymorphic relationship

        if (!$payable) {
            return;  // No payable linked to transaction
        }

        // Dispatch to appropriate handler
        $this->dispatcher->dispatch($payable, $transaction);
    }
}
```

### 3. Event Registration

**File**: `src/OfficeGuyServiceProvider.php`

```php
protected $listen = [
    PaymentCompleted::class => [
        FulfillmentListener::class,
    ],
];
```

---

## Complete Fulfillment Flow

### End-to-End Example

```
1. Customer completes payment
   └─ PaymentService::processCharge() completes

2. Transaction created and saved
   └─ OfficeGuyTransaction::create([...])

3. PaymentCompleted event dispatched
   └─ event(new PaymentCompleted($transaction))

4. FulfillmentListener::handle() called
   ├─ Get $payable from $transaction->payable
   └─ Call $dispatcher->dispatch($payable, $transaction)

5. FulfillmentDispatcher resolves handler
   ├─ Check for custom handler (Priority 1)
   ├─ Lookup type-based handler (Priority 2)
   └─ Log warning if none found (Priority 3)

6. Handler executed
   └─ app(DigitalProductFulfillmentHandler::class)->handle($transaction)

7. Handler performs fulfillment
   ├─ Generate download link
   ├─ Send email to customer
   └─ Log fulfillment in transaction notes

8. Customer receives download link
```

---

## Design Patterns Used

### 1. Strategy Pattern

**Definition**: Define a family of algorithms, encapsulate each one, and make them interchangeable.

**Implementation**:
```
Context: FulfillmentDispatcher
Strategy Interface: handle(OfficeGuyTransaction) method
Concrete Strategies:
├─ DigitalProductFulfillmentHandler
├─ SubscriptionFulfillmentHandler
└─ InfrastructureFulfillmentHandler
```

### 2. Service Locator Pattern

**Definition**: Use container to resolve handler instances at runtime

**Implementation**:
```php
app($handlerClass)->handle($transaction);  // ← Container resolution
```

### 3. Chain of Responsibility (3-Priority System)

**Definition**: Pass request through chain of handlers until one handles it

**Implementation**:
```
Priority 1: Custom handler → If exists, handle and return
Priority 2: Type-based handler → If registered, handle and return
Priority 3: No handler → Log warning, graceful degradation
```

---

## Testing Recommendations

### 1. Unit Tests

```php
use OfficeGuy\LaravelSumitGateway\Services\FulfillmentDispatcher;
use OfficeGuy\LaravelSumitGateway\Enums\PayableType;

/** @test */
public function it_registers_and_dispatches_to_correct_handler()
{
    $dispatcher = new FulfillmentDispatcher();

    $dispatcher->register(
        PayableType::DIGITAL_PRODUCT,
        MockDigitalProductHandler::class
    );

    $this->assertTrue($dispatcher->hasHandler(PayableType::DIGITAL_PRODUCT));

    $payable = $this->createPayable(PayableType::DIGITAL_PRODUCT);
    $transaction = OfficeGuyTransaction::factory()->create();

    $dispatcher->dispatch($payable, $transaction);

    // Assert handler was called
    $this->assertTrue(MockDigitalProductHandler::$wasCalled);
}
```

### 2. Integration Tests

```php
/** @test */
public function it_sends_download_link_for_digital_product()
{
    Mail::fake();

    $product = DigitalProduct::factory()->create();
    $transaction = OfficeGuyTransaction::factory()->create([
        'payable_id' => $product->id,
        'payable_type' => DigitalProduct::class,
    ]);

    event(new PaymentCompleted($transaction));

    Mail::assertSent(DigitalProductPurchasedMail::class);
}
```

---

## Best Practices

### ✅ DO

1. **Register all handlers in ServiceProvider**
   ```php
   // In ServiceProvider::boot()
   $dispatcher->registerMany([...]);
   ```

2. **Use type-based handlers (common path)**
   ```php
   public function getPayableType(): PayableType
   {
       return PayableType::DIGITAL_PRODUCT;
   }
   ```

3. **Implement standard handler interface**
   ```php
   public function handle(OfficeGuyTransaction $transaction): void
   {
       // Fulfillment logic
   }
   ```

4. **Log fulfillment in transaction notes**
   ```php
   $transaction->addNote('Digital product download link sent');
   ```

5. **Test handler registration**
   ```php
   $this->assertTrue($dispatcher->hasHandler(PayableType::DIGITAL_PRODUCT));
   ```

### ❌ DON'T

1. **Don't register handlers outside ServiceProvider**
   ```php
   // ❌ BAD - In controller or service
   $dispatcher->register(PayableType::DIGITAL_PRODUCT, Handler::class);

   // ✅ GOOD - In ServiceProvider::boot()
   protected function registerFulfillmentHandlers() { ... }
   ```

2. **Don't mix dispatch priorities**
   ```php
   // ❌ BAD - Custom handler + type don't match
   public function getFulfillmentHandler(): string
   {
       return DigitalProductHandler::class;  // But type is SUBSCRIPTION
   }
   ```

3. **Don't throw exceptions in handlers**
   ```php
   // ❌ BAD
   public function handle($transaction): void
   {
       throw new Exception('Failed to fulfill');  // Breaks payment flow
   }

   // ✅ GOOD
   public function handle($transaction): void
   {
       try {
           // Fulfillment logic
       } catch (\Exception $e) {
           Log::error('Fulfillment failed', ['error' => $e->getMessage()]);
           // Continue gracefully
       }
   }
   ```

4. **Don't assume handler exists**
   ```php
   // ❌ BAD
   $handler = $dispatcher->getHandler($type);
   app($handler)->handle($transaction);  // NullPointerException if not registered!

   // ✅ GOOD
   if ($dispatcher->hasHandler($type)) {
       $dispatcher->dispatch($payable, $transaction);
   }
   ```

---

## Extensibility

### Adding New PayableType and Handler

**Step 1: Add to PayableType Enum**
```php
// src/Enums/PayableType.php
enum PayableType: string
{
    // ... existing types
    case MEMBERSHIP = 'membership';  // ← New type
}
```

**Step 2: Create Handler**
```php
// src/Handlers/MembershipFulfillmentHandler.php
namespace OfficeGuy\LaravelSumitGateway\Handlers;

class MembershipFulfillmentHandler
{
    public function handle(OfficeGuyTransaction $transaction): void
    {
        $membership = $transaction->payable;

        // Activate membership
        $membership->activate();

        // Send welcome email
        Mail::to($transaction->customer_email)
            ->send(new MembershipActivatedMail($membership));

        $transaction->addNote('Membership activated');
    }
}
```

**Step 3: Register in ServiceProvider**
```php
// src/OfficeGuyServiceProvider.php
protected function registerFulfillmentHandlers(): void
{
    $dispatcher = app(FulfillmentDispatcher::class);

    $dispatcher->registerMany([
        // ... existing mappings
        PayableType::MEMBERSHIP->value => MembershipFulfillmentHandler::class,  // ← New mapping
    ]);
}
```

**Step 4: Implement Payable**
```php
// app/Models/Membership.php
class Membership implements Payable
{
    public function getPayableType(): PayableType
    {
        return PayableType::MEMBERSHIP;  // ← Returns new type
    }
}
```

---

## Summary

### Service Purpose
FulfillmentDispatcher provides **type-based fulfillment orchestration** using Strategy Pattern with 3-priority dispatch system and centralized handler registration.

### Key Strengths
- ✅ **Type-safe dispatch** via PayableType enum
- ✅ **Centralized configuration** in ServiceProvider
- ✅ **Extensible** - add new types without modifying dispatcher
- ✅ **Testable** - easy to mock handlers
- ✅ **Graceful degradation** - logs warning, continues without error
- ✅ **Override mechanism** - Payable can specify custom handler

### Design Patterns
- **Strategy Pattern** - Interchangeable fulfillment handlers
- **Service Locator** - Container resolution of handlers
- **Chain of Responsibility** - 3-priority dispatch system

### Critical Implementation Notes
1. **All handlers registered in ServiceProvider::boot()**
2. **Handlers resolved from container** (supports DI)
3. **Type is Single Source of Truth** for routing
4. **Custom override available** via `getFulfillmentHandler()`
5. **Graceful degradation** - no handler = log warning, continue

### Integration Points
- Invoked by **FulfillmentListener** on PaymentCompleted event
- Registered handlers in **OfficeGuyServiceProvider::boot()**
- Handlers implement standard `handle(OfficeGuyTransaction)` interface

---

**Lines Analyzed**: 165
**Methods Documented**: 7
**Design Pattern**: Strategy Pattern + Service Locator + Chain of Responsibility
**Handler Interface**: `handle(OfficeGuyTransaction $transaction): void`
**Registration**: ServiceProvider (centralized)
