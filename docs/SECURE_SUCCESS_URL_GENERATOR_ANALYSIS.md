# SecureSuccessUrlGenerator Analysis

**File**: `src/Services/SecureSuccessUrlGenerator.php`
**Purpose**: Generate cryptographically secure URLs for post-payment success pages
**Security Architecture**: 7-layer defense-in-depth system
**Created**: 2025-11-27
**Package Version**: v1.1.6+

---

## Table of Contents

1. [Overview](#overview)
2. [Security Features](#security-features)
3. [7-Layer Security Architecture](#7-layer-security-architecture)
4. [Methods Analysis](#methods-analysis)
5. [Token Generation Flow](#token-generation-flow)
6. [Integration with SuccessAccessValidator](#integration-with-successaccessvalidator)
7. [Database Schema](#database-schema)
8. [Best Practices](#best-practices)
9. [Examples](#examples)
10. [Security Considerations](#security-considerations)
11. [Summary](#summary)

---

## Overview

The `SecureSuccessUrlGenerator` service is responsible for creating cryptographically secure URLs that redirect customers to success pages after completing payments. This service is a critical component of the SUMIT Payment Gateway's security infrastructure, implementing a multi-layered defense strategy to prevent unauthorized access to sensitive post-payment information.

### Core Responsibilities

- **URL Generation**: Creates time-limited, signed URLs with embedded cryptographic tokens
- **Token Management**: Generates and stores one-time-use tokens with SHA256 hashing
- **Nonce Creation**: Provides replay attack protection via cryptographic nonces
- **Database Persistence**: Records token metadata for validation and auditing
- **TTL Management**: Enforces time-to-live constraints on generated URLs

### Key Features

✅ **Cryptographically Secure**: Uses PHP's CSPRNG (`random_bytes()`)
✅ **One-Time Use**: Tokens can only be consumed once
✅ **Time-Limited**: Configurable expiration (default: 24 hours)
✅ **Replay Protection**: Unique nonces prevent replay attacks
✅ **Guest-Safe**: Works for both authenticated users and guest checkout
✅ **Audit Trail**: Full logging of token generation and consumption

---

## Security Features

### 1. HMAC Signature Validation

The service uses Laravel's built-in URL signing mechanism, which creates an HMAC-SHA256 signature of the URL parameters using the application's `APP_KEY`. This ensures the URL hasn't been tampered with.

**Implementation**:
```php
URL::temporarySignedRoute(
    'officeguy.success',
    now()->addHours($ttl),
    ['token' => $token, 'nonce' => $nonce]
);
```

**Security Benefit**: Any modification to URL parameters (token, nonce, expiration) invalidates the signature.

### 2. SHA256 Token Hashing

Tokens are **never stored in plain text** in the database. Instead, a SHA256 hash is stored, and validation is performed by hashing incoming tokens and comparing hashes.

**Implementation**:
```php
'token_hash' => hash('sha256', $token), // Database storage
```

**Security Benefit**: Even if the database is compromised, attackers cannot extract valid tokens.

### 3. Cryptographic Nonces

Each URL includes a unique nonce (64-character hex string from 32 random bytes) that must match the database record. This prevents replay attacks where an attacker tries to reuse a captured URL.

**Implementation**:
```php
protected function generateNonce(): string
{
    return bin2hex(random_bytes(32)); // 64-char hex string
}
```

**Security Benefit**: Each URL is cryptographically unique, even for the same order.

### 4. Time-to-Live (TTL) Expiration

Tokens have a configurable expiration time (default: 24 hours). The expiration timestamp is stored in the database as the **single source of truth**.

**Implementation**:
```php
'expires_at' => now()->addHours($ttlHours), // Database timestamp
```

**Security Benefit**: Limits the window of opportunity for attacks; expired tokens are rejected.

### 5. One-Time Use Enforcement

Once a token is validated and used, it is immediately marked as "consumed" in the database with IP and User-Agent tracking.

**Implementation** (in `SuccessAccessValidator`):
```php
$token->consume($ip, $userAgent); // Marks as consumed
```

**Security Benefit**: Prevents reuse of valid tokens, even within the TTL window.

### 6. CSPRNG Token Generation

Tokens are generated using PHP's `random_bytes()`, which uses a **Cryptographically Secure Pseudo-Random Number Generator** (CSPRNG). This ensures tokens are unpredictable and cannot be guessed.

**Implementation**:
```php
protected function generateToken(): string
{
    return bin2hex(random_bytes(64)); // 128-char hex string
}
```

**Security Benefit**: 2^512 possible tokens make brute force attacks computationally infeasible.

### 7. Audit Logging

All access attempts (successful and failed) are logged to the `order_success_access_logs` table with IP addresses, User-Agents, referers, and failure reasons.

**Security Benefit**: Enables forensic analysis and attack detection.

---

## 7-Layer Security Architecture

The SecureSuccessUrlGenerator works in tandem with the `SuccessAccessValidator` to implement a comprehensive 7-layer defense system:

| Layer | Component | Purpose | Validation Point |
|-------|-----------|---------|------------------|
| **1** | Rate Limiting | Prevents brute force attacks | `SuccessAccessValidator::validateRateLimit()` |
| **2** | Signed URL | HMAC signature validation | `$request->hasValidSignature()` |
| **3** | Token Existence | Token must exist in database | `OrderSuccessToken::findByToken()` |
| **4** | Token Validity | Token must not be expired | `$token->isExpired()` |
| **5** | Single Use | Token must not be consumed | `$token->isConsumed()` |
| **6** | Nonce Matching | Nonce must match database | `$token->nonce === $requestNonce` |
| **7** | Identity Proof | Guest-safe ownership validation | `validateIdentity()` |

### Defense-in-Depth Explanation

Each layer provides protection against different attack vectors:

- **Layer 1** (Rate Limiting): Prevents automated scanning/brute force
- **Layer 2** (Signed URL): Prevents URL parameter tampering
- **Layer 3** (Token Existence): Prevents fabricated tokens
- **Layer 4** (Token Validity): Prevents expired token reuse
- **Layer 5** (Single Use): Prevents valid token replay
- **Layer 6** (Nonce Matching): Prevents partial token reuse
- **Layer 7** (Identity Proof): Prevents cross-customer access

Even if one layer is bypassed, the remaining layers provide protection. An attacker must bypass **all 7 layers** simultaneously to gain unauthorized access.

---

## Methods Analysis

### `generate(Payable $payable, ?int $ttlHours = null): string`

**Purpose**: Primary method for generating secure success URLs.

**Parameters**:
- `$payable` (Payable): The payable entity (Order, Invoice, Subscription, etc.)
- `$ttlHours` (int|null): Token validity period in hours (defaults to config value)

**Return Value**: A signed URL string with embedded token and nonce.

**Process Flow**:
```
1. Generate random token (128 chars)
2. Generate nonce (64 chars)
3. Determine TTL from parameter or config
4. Create database record with SHA256 hash
5. Generate Laravel signed URL
6. Return URL string
```

**Example Usage**:
```php
$generator = app(SecureSuccessUrlGenerator::class);
$url = $generator->generate($order); // Default 24h TTL
// Returns: https://example.com/success?token=abc...&nonce=xyz...&signature=...
```

**Security Features**:
- Token is 128 characters (64 random bytes)
- Nonce is 64 characters (32 random bytes)
- Token hash (not plaintext) stored in database
- URL includes HMAC signature
- Expiration timestamp enforced

---

### `generateToken(): string`

**Purpose**: Generate cryptographically secure random token.

**Implementation**:
```php
protected function generateToken(): string
{
    return bin2hex(random_bytes(64));
}
```

**Return Value**: 128-character hexadecimal string.

**Security Analysis**:
- Uses `random_bytes(64)` (CSPRNG)
- Produces 64 bytes of random data
- Converts to hex (128 characters)
- **Entropy**: 512 bits (2^512 possible values)
- **Brute Force Resistance**: Computationally infeasible

**Why 64 bytes?**
- SHA256 hash is 32 bytes (256 bits)
- Using 64 bytes provides **double the entropy** of the hash function
- Prevents hash collision attacks

---

### `generateNonce(): string`

**Purpose**: Generate cryptographic nonce for replay attack protection.

**Implementation**:
```php
protected function generateNonce(): string
{
    return bin2hex(random_bytes(32));
}
```

**Return Value**: 64-character hexadecimal string.

**Security Analysis**:
- Uses `random_bytes(32)` (CSPRNG)
- Produces 32 bytes of random data
- **Entropy**: 256 bits (2^256 possible values)
- **Purpose**: Ensures each URL is unique, even for the same order

**Replay Attack Protection**:
- Even if a token is intercepted, the nonce ensures it can't be reused
- Each generated URL has a unique nonce-token pair
- Validator checks both token AND nonce match the database

---

### `createTokenRecord(): OrderSuccessToken`

**Purpose**: Persist token metadata to database.

**Implementation**:
```php
protected function createTokenRecord(
    Payable $payable,
    string $token,
    string $nonce,
    int $ttlHours
): OrderSuccessToken {
    return OrderSuccessToken::create([
        'payable_id' => $payable->getPayableId(),
        'payable_type' => get_class($payable),
        'token_hash' => hash('sha256', $token), // SHA256 hash
        'nonce' => $nonce,
        'expires_at' => now()->addHours($ttlHours),
    ]);
}
```

**Database Fields**:
- `payable_id`: Polymorphic ID (Order, Invoice, etc.)
- `payable_type`: Fully qualified class name
- `token_hash`: SHA256 hash (never plaintext!)
- `nonce`: Cryptographic nonce
- `expires_at`: Expiration timestamp (single source of truth)

**Security Features**:
- **Never stores plaintext tokens** (only SHA256 hash)
- Uses polymorphic relationship (supports multiple payable types)
- Expiration enforced at database level
- Indexed columns for fast lookup

---

### `regenerate(Payable $payable, ?int $ttlHours = null): string`

**Purpose**: Invalidate existing tokens and generate a new URL.

**Use Cases**:
- Customer requests new success link (email lost)
- Admin wants to resend confirmation
- Token expired but customer still needs access

**Implementation**:
```php
public function regenerate(Payable $payable, ?int $ttlHours = null): string
{
    // 1. Invalidate all existing unconsumed tokens
    OrderSuccessToken::where('payable_id', $payable->getPayableId())
        ->where('payable_type', get_class($payable))
        ->whereNull('consumed_at')
        ->update([
            'consumed_at' => now(),
            'consumed_by_ip' => request()->ip() ?? '0.0.0.0',
            'consumed_by_user_agent' => 'System: Regenerated',
        ]);

    // 2. Generate new URL
    return $this->generate($payable, $ttlHours);
}
```

**Security Features**:
- Marks old tokens as consumed (prevents reuse)
- Records regeneration in audit trail
- Creates entirely new token (not just extending TTL)

**Example Usage**:
```php
// Customer lost email with success link
$newUrl = $generator->regenerate($order);
Mail::to($customer)->send(new OrderConfirmation($newUrl));
```

---

### `generateUnsigned(Payable $payable, ?int $ttlHours = null): string`

**Purpose**: Generate unsigned URL for testing/development.

**⚠️ WARNING**: This method bypasses HMAC signature validation and should **NEVER** be used in production!

**Implementation**:
```php
public function generateUnsigned(Payable $payable, ?int $ttlHours = null): string
{
    $token = $this->generateToken();
    $nonce = $this->generateNonce();
    $ttl = $ttlHours ?? config('officeguy.success.token_ttl', 24);

    $this->createTokenRecord($payable, $token, $nonce, $ttl);

    return route('officeguy.success', [
        'token' => $token,
        'nonce' => $nonce,
    ]);
}
```

**Use Cases**:
- PHPUnit tests (where APP_KEY may not be set)
- Local development debugging
- Automated testing environments

**Security Implications**:
- URL can be tampered with (no signature)
- Still requires valid token + nonce in database
- Should be blocked in production via middleware

---

### `getDefaultTtl(): int`

**Purpose**: Retrieve configured default TTL.

**Implementation**:
```php
public function getDefaultTtl(): int
{
    return config('officeguy.success.token_ttl', 24);
}
```

**Configuration**:
```php
// config/officeguy.php
'success' => [
    'token_ttl' => env('OFFICEGUY_SUCCESS_TOKEN_TTL', 24), // hours
],
```

**Use Cases**:
- Display expiration time to customer
- Admin dashboard statistics
- Testing configuration validation

---

### `isEnabled(): bool`

**Purpose**: Check if secure success URL generation is enabled.

**Implementation**:
```php
public function isEnabled(): bool
{
    return config('officeguy.success.enabled', true);
}
```

**Configuration**:
```php
// config/officeguy.php
'success' => [
    'enabled' => env('OFFICEGUY_SUCCESS_ENABLED', true),
],
```

**Use Cases**:
- Feature flag for gradual rollout
- Emergency disable switch
- A/B testing

---

## Token Generation Flow

### Complete Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│ Payment Completed (Webhook/Callback)                            │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────────┐
│ PaymentService::handleCallback()                                │
│ - Validates payment status                                      │
│ - Creates OfficeGuyTransaction record                           │
│ - Dispatches PaymentCompleted event                             │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────────┐
│ Need to Generate Success URL?                                   │
│ - For redirect after payment                                    │
│ - For email confirmation link                                   │
│ - For SMS notification link                                     │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────────┐
│ SecureSuccessUrlGenerator::generate($order)                     │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       ├──> 1. Generate Token (128 chars)
                       │    bin2hex(random_bytes(64))
                       │
                       ├──> 2. Generate Nonce (64 chars)
                       │    bin2hex(random_bytes(32))
                       │
                       ├──> 3. Determine TTL
                       │    $ttl ?? config('officeguy.success.token_ttl', 24)
                       │
                       ├──> 4. Create Database Record
                       │    OrderSuccessToken::create([
                       │        'payable_id' => $order->id,
                       │        'payable_type' => Order::class,
                       │        'token_hash' => hash('sha256', $token),
                       │        'nonce' => $nonce,
                       │        'expires_at' => now()->addHours($ttl),
                       │    ])
                       │
                       └──> 5. Generate Signed URL
                            URL::temporarySignedRoute(
                                'officeguy.success',
                                now()->addHours($ttl),
                                ['token' => $token, 'nonce' => $nonce]
                            )
                       │
                       ▼
┌─────────────────────────────────────────────────────────────────┐
│ Signed URL Returned                                             │
│ https://app.com/success?                                        │
│   token=abc123... (128 chars)                                   │
│   &nonce=xyz789... (64 chars)                                   │
│   &expires=1234567890                                           │
│   &signature=hmac_sha256... (Laravel signature)                 │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       ├──> Use Case A: Redirect
                       │    return redirect($url);
                       │
                       ├──> Use Case B: Email
                       │    Mail::to($customer)->send(
                       │        new OrderConfirmation($url)
                       │    );
                       │
                       └──> Use Case C: SMS
                            SMS::send($customer->phone, "Success: $url");
```

---

## Integration with SuccessAccessValidator

The `SecureSuccessUrlGenerator` creates URLs that are validated by the `SuccessAccessValidator`. Here's how they work together:

### Validation Flow

```
┌─────────────────────────────────────────────────────────────────┐
│ Customer Clicks Success URL                                     │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────────┐
│ SecureSuccessController::show(Request $request)                 │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────────┐
│ SuccessAccessValidator::validate($request)                      │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       ├──> Layer 1: Rate Limiting
                       │    if (RateLimiter::tooManyAttempts()) FAIL
                       │
                       ├──> Layer 2: Signed URL
                       │    if (!$request->hasValidSignature()) FAIL
                       │
                       ├──> Layer 3: Token Existence
                       │    $token = OrderSuccessToken::findByToken($rawToken)
                       │    if (!$token) FAIL
                       │
                       ├──> Layer 4: Token Validity
                       │    if ($token->isExpired()) FAIL
                       │
                       ├──> Layer 5: Single Use
                       │    if ($token->isConsumed()) FAIL
                       │
                       ├──> Layer 6: Nonce Matching
                       │    if ($token->nonce !== $requestNonce) FAIL
                       │
                       └──> Layer 7: Identity Proof
                            if (!validateIdentity($payable, $request)) FAIL
                       │
                       ▼
┌─────────────────────────────────────────────────────────────────┐
│ ALL 7 LAYERS PASSED ✓                                           │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       ├──> 1. Log Successful Access
                       │    OrderSuccessAccessLog::logSuccessfulAccess()
                       │
                       ├──> 2. Consume Token (One-Time Use)
                       │    $token->consume($ip, $userAgent)
                       │
                       ├──> 3. Dispatch Event
                       │    event(new SuccessPageAccessed($payable, $token))
                       │
                       └──> 4. Return Success View
                            return view('officeguy::success', [
                                'payable' => $payable,
                                'token' => $token,
                            ]);
```

### ValidationResult DTO

The validator returns a `ValidationResult` DTO that encapsulates the outcome:

**Success Result**:
```php
ValidationResult {
    isValid: true,
    token: OrderSuccessToken instance,
    payable: Order instance,
    errorMessage: null,
    failures: []
}
```

**Failed Result**:
```php
ValidationResult {
    isValid: false,
    token: null,
    payable: null,
    errorMessage: "הקישור פג תוקף. נא לבקש קישור חדש.",
    failures: ['expiration']
}
```

### Usage in Controller

```php
public function show(Request $request): View|Response
{
    $result = $this->validator->validate($request);

    if ($result->isFailed()) {
        return response()->view('officeguy::errors.access-denied', [
            'error_message' => $result->getErrorMessage(),
            'failures' => $result->getFailures(),
        ], 403);
    }

    $payable = $result->getPayable();
    $token = $result->getToken();

    return view('officeguy::success', compact('payable', 'token'));
}
```

---

## Database Schema

### `order_success_tokens` Table

**Migration**: `database/migrations/xxxx_create_order_success_tokens_table.php`

```sql
CREATE TABLE order_success_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Polymorphic relationship
    payable_id BIGINT UNSIGNED NOT NULL,
    payable_type VARCHAR(255) NOT NULL,

    -- Security fields
    token_hash VARCHAR(64) NOT NULL UNIQUE, -- SHA256 hash
    nonce VARCHAR(64) NOT NULL,             -- Replay protection
    expires_at TIMESTAMP NOT NULL,          -- Single source of truth

    -- Consumption tracking
    consumed_at TIMESTAMP NULL,
    consumed_by_ip VARCHAR(45) NULL,        -- IPv4/IPv6
    consumed_by_user_agent TEXT NULL,

    -- Timestamps
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    -- Indexes
    INDEX idx_payable (payable_id, payable_type),
    INDEX idx_token_hash (token_hash),
    INDEX idx_expires_at (expires_at),
    INDEX idx_consumed_at (consumed_at)
);
```

**Index Strategy**:
- `idx_payable`: Fast lookup by payable entity
- `idx_token_hash`: Fast token validation (primary lookup)
- `idx_expires_at`: Efficient cleanup of expired tokens
- `idx_consumed_at`: Filter unconsumed tokens

### `order_success_access_logs` Table

**Migration**: `database/migrations/xxxx_create_order_success_access_logs_table.php`

```sql
CREATE TABLE order_success_access_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Payable reference (nullable for failed attempts)
    payable_id BIGINT UNSIGNED NULL,
    payable_type VARCHAR(255) NULL,

    -- Request details
    token_hash VARCHAR(64) NULL,
    nonce VARCHAR(64) NULL,

    -- Access result
    access_granted BOOLEAN NOT NULL DEFAULT 0,
    failures JSON NULL,                     -- Array of failed layers

    -- Security tracking
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    referer TEXT NULL,
    signature_valid BOOLEAN NULL,

    -- Timestamps
    attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_payable (payable_id, payable_type),
    INDEX idx_ip_address (ip_address),
    INDEX idx_access_granted (access_granted),
    INDEX idx_attempted_at (attempted_at)
);
```

**Use Cases**:
- Security auditing
- Attack detection (repeated failures from same IP)
- Customer support (verify customer accessed success page)
- Analytics (success rate, failure patterns)

---

## Best Practices

### 1. Always Use the Service (Don't Build URLs Manually)

❌ **WRONG**:
```php
// DON'T DO THIS!
$url = route('officeguy.success', [
    'token' => 'some_random_string',
    'nonce' => 'another_string',
]);
```

✅ **CORRECT**:
```php
$generator = app(SecureSuccessUrlGenerator::class);
$url = $generator->generate($order);
```

**Why**: The service ensures all security layers are properly implemented.

---

### 2. Use Dependency Injection

❌ **WRONG**:
```php
public function completePayment()
{
    $generator = new SecureSuccessUrlGenerator(); // Missing dependencies!
    $url = $generator->generate($order);
}
```

✅ **CORRECT**:
```php
public function __construct(
    protected SecureSuccessUrlGenerator $generator
) {}

public function completePayment()
{
    $url = $this->generator->generate($order);
}
```

**Why**: Proper DI allows Laravel to resolve dependencies and makes testing easier.

---

### 3. Configure TTL Based on Use Case

```php
// Email confirmation (longer TTL)
$emailUrl = $generator->generate($order, ttlHours: 72); // 3 days

// Immediate redirect (shorter TTL)
$redirectUrl = $generator->generate($order, ttlHours: 1); // 1 hour

// SMS link (medium TTL)
$smsUrl = $generator->generate($order, ttlHours: 24); // Default
```

**Guidelines**:
- **Immediate Redirects**: 1-6 hours (customer is online now)
- **Email Links**: 24-72 hours (customer may check email later)
- **SMS Links**: 12-24 hours (moderate urgency)
- **Resend Links**: 48-96 hours (customer support scenarios)

---

### 4. Use `regenerate()` for Resend Features

```php
public function resendConfirmation(Order $order)
{
    // Invalidate old tokens and create new one
    $url = $this->generator->regenerate($order);

    // Send new email
    Mail::to($order->customer)->send(
        new OrderConfirmation($url)
    );

    return response()->json([
        'message' => 'קישור חדש נשלח בהצלחה',
    ]);
}
```

**Why**: Prevents customers from having multiple valid URLs, which could confuse them or create security issues.

---

### 5. Never Store Plain Tokens in Logs

❌ **WRONG**:
```php
Log::info('Generated success URL', [
    'token' => $token, // SECURITY RISK!
    'order_id' => $order->id,
]);
```

✅ **CORRECT**:
```php
Log::info('Generated success URL', [
    'token_hash' => hash('sha256', $token), // Hash only
    'order_id' => $order->id,
    'expires_at' => now()->addHours($ttl),
]);
```

**Why**: Log files may be compromised; never log sensitive cryptographic material.

---

## Examples

### Example 1: Basic Usage (Card Payment Redirect)

```php
namespace App\Http\Controllers;

use OfficeGuy\LaravelSumitGateway\Services\SecureSuccessUrlGenerator;
use OfficeGuy\LaravelSumitGateway\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        protected SecureSuccessUrlGenerator $urlGenerator,
        protected PaymentService $paymentService
    ) {}

    public function processPayment(Request $request)
    {
        // Process payment
        $result = $this->paymentService->charge([
            'amount' => $request->amount,
            'currency' => 'ILS',
            'token' => $request->token, // From PaymentsJS SDK
        ]);

        if ($result['status'] !== 'success') {
            return back()->withErrors(['payment' => 'תשלום נכשל']);
        }

        // Get the order
        $order = Order::find($request->order_id);

        // Generate secure success URL
        $successUrl = $this->urlGenerator->generate($order);

        // Redirect customer to success page
        return redirect($successUrl);
    }
}
```

---

### Example 2: Email Confirmation Link (Webhook)

```php
namespace App\Listeners;

use OfficeGuy\LaravelSumitGateway\Events\PaymentCompleted;
use OfficeGuy\LaravelSumitGateway\Services\SecureSuccessUrlGenerator;
use App\Mail\OrderConfirmation;
use Illuminate\Support\Facades\Mail;

class SendOrderConfirmationEmail
{
    public function __construct(
        protected SecureSuccessUrlGenerator $urlGenerator
    ) {}

    public function handle(PaymentCompleted $event): void
    {
        $order = $event->payable;

        // Generate success URL with 72-hour TTL (email may be checked later)
        $successUrl = $this->urlGenerator->generate($order, ttlHours: 72);

        // Send confirmation email
        Mail::to($order->customer->email)->send(
            new OrderConfirmation($order, $successUrl)
        );
    }
}
```

---

### Example 3: Testing with Unsigned URLs

```php
namespace Tests\Feature;

use App\Models\Order;
use OfficeGuy\LaravelSumitGateway\Services\SecureSuccessUrlGenerator;
use Tests\TestCase;

class SuccessPageTest extends TestCase
{
    protected SecureSuccessUrlGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = app(SecureSuccessUrlGenerator::class);
    }

    public function test_success_page_displays_order_details()
    {
        $order = Order::factory()->create([
            'total' => 100.00,
            'status' => 'completed',
        ]);

        // Use unsigned URL for testing (bypasses signature validation)
        $url = $this->generator->generateUnsigned($order);

        // Visit success page
        $response = $this->get($url);

        $response->assertStatus(200);
        $response->assertSee($order->id);
        $response->assertSee('100.00');
        $response->assertSee('תשלום בוצע בהצלחה');
    }

    public function test_token_can_only_be_used_once()
    {
        $order = Order::factory()->create();
        $url = $this->generator->generateUnsigned($order);

        // First access succeeds
        $this->get($url)->assertStatus(200);

        // Second access fails (token consumed)
        $this->get($url)->assertStatus(403);
        $this->get($url)->assertSee('הקישור כבר נוצל');
    }
}
```

---

## Security Considerations

### Threat Model

The SecureSuccessUrlGenerator defends against the following threats:

| Threat | Attack Vector | Defense Mechanism |
|--------|---------------|-------------------|
| **URL Tampering** | Attacker modifies token/nonce parameters | HMAC signature (Layer 2) |
| **Token Guessing** | Attacker tries random tokens | 512-bit entropy (computationally infeasible) |
| **Replay Attacks** | Attacker reuses intercepted URL | One-time use + nonce (Layers 5 & 6) |
| **Brute Force** | Attacker tries many tokens | Rate limiting (Layer 1) |
| **Database Leak** | Attacker gains DB access | SHA256 hashing (tokens not recoverable) |
| **MITM Attacks** | Attacker intercepts traffic | HTTPS required, signature validation |
| **Cross-Customer Access** | Attacker guesses another customer's URL | Identity proof (Layer 7) |
| **Expired Token Reuse** | Attacker tries expired token | TTL validation (Layer 4) |

---

### Cryptographic Strength Analysis

**Token Entropy**: 512 bits (2^512 possible values)
- **Brute Force Time** (1 million attempts/second): 2^512 / 10^6 ≈ **10^147 years**
- **Comparison**: There are only 10^80 atoms in the observable universe

**Nonce Entropy**: 256 bits (2^256 possible values)
- **Collision Probability**: Negligible (birthday attack requires 2^128 operations)

**SHA256 Hash**:
- **Preimage Resistance**: Computationally infeasible to reverse
- **Collision Resistance**: No known practical attacks

**HMAC-SHA256 Signature**:
- **Key Length**: Laravel's APP_KEY (32 bytes = 256 bits)
- **Security**: No known practical attacks against HMAC-SHA256

**Conclusion**: The cryptographic primitives used provide **military-grade security** for the success URL system.

---

## Summary

### Key Takeaways

1. **Defense-in-Depth**: 7 layers of security ensure comprehensive protection
2. **Cryptographic Security**: 512-bit token entropy makes brute force infeasible
3. **One-Time Use**: Tokens are consumed after first use, preventing replay attacks
4. **Time-Limited**: Configurable TTL ensures tokens don't remain valid indefinitely
5. **Audit Trail**: Complete logging enables forensic analysis and attack detection
6. **Guest-Safe**: Works for both authenticated users and guest checkout
7. **Polymorphic**: Supports any Payable entity (Orders, Invoices, Subscriptions, etc.)

---

### When to Use This Service

✅ **Use SecureSuccessUrlGenerator when**:
- Redirecting customers after payment completion
- Sending order confirmation emails
- Sending SMS order notifications
- Implementing "resend confirmation" features
- Creating admin support tools (manual resend)

❌ **Don't use SecureSuccessUrlGenerator for**:
- Admin panel access (use Laravel auth)
- API authentication (use OAuth/API tokens)
- File downloads (use signed temporary URLs)
- Public pages (no authentication needed)

---

**Document Version**: 1.0.0
**Last Updated**: 2025-01-13
**Maintainer**: NM-DigitalHub
**Package**: officeguy/laravel-sumit-gateway v1.1.6+
