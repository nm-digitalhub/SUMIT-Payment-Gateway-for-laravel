# SuccessAccessValidator - Comprehensive Analysis

## Document Information

**Created**: 2026-01-13
**Package**: officeguy/laravel-sumit-gateway
**Version**: v1.1.6+
**File**: `src/Services/SuccessAccessValidator.php`

---

## Table of Contents

1. [Overview](#overview)
2. [7-Layer Security Architecture](#7-layer-security-architecture)
3. [Methods Reference](#methods-reference)
4. [Access Control Flow](#access-control-flow)
5. [Integration with Other Components](#integration-with-other-components)
6. [Security Best Practices](#security-best-practices)
7. [Usage Examples](#usage-examples)
8. [Testing & Debugging](#testing--debugging)
9. [Summary](#summary)

---

## Overview

### Purpose

`SuccessAccessValidator` is a critical security service that implements a **7-layer defense-in-depth architecture** for validating access to post-payment success pages. It ensures that only legitimate users with valid, one-time cryptographic tokens can view success pages after completing a payment.

### Key Responsibilities

- **Validate access requests** through 7 distinct security layers
- **Prevent unauthorized access** to payment success pages
- **Log all access attempts** (successful and failed) for security auditing
- **Support guest checkout** without requiring authentication
- **Prevent replay attacks** using cryptographic nonces
- **Rate limiting** to prevent brute force attacks

### Architecture Principles

1. **Defense in Depth**: Multiple independent security layers
2. **Zero Trust**: Every request is validated from scratch
3. **Immutable Audit Trail**: All attempts logged permanently
4. **Guest-Safe**: Works for authenticated users and guest checkout
5. **Single Use Tokens**: Tokens consumed after first successful access
6. **Cryptographic Security**: SHA256 hashing, CSPRNG random generation

---

## 7-Layer Security Architecture

### Layer 1: Rate Limiting

**Purpose**: Prevent brute force attacks by limiting requests per IP address.

**Implementation**:
```php
protected function validateRateLimit(Request $request): bool
{
    $key = 'success-access:' . $request->ip();
    $maxAttempts = config('officeguy.success.rate_limit.max_attempts', 10);
    $decayMinutes = config('officeguy.success.rate_limit.decay_minutes', 1);

    if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
        return false; // Too many attempts
    }

    RateLimiter::hit($key, $decayMinutes * 60);
    return true;
}
```

**Configuration**:
- **Max Attempts**: `config('officeguy.success.rate_limit.max_attempts', 10)` (default: 10)
- **Decay Minutes**: `config('officeguy.success.rate_limit.decay_minutes', 1)` (default: 1)

**Failure Message**: `"חריגה ממגבלת קצב. נא לנסות שנית בעוד מספר דקות."`
**Failure Code**: `rate_limit`

**Attack Vector Prevented**: Brute force token guessing attacks

---

### Layer 2: Signed URL Validation

**Purpose**: Ensure URL integrity using Laravel's HMAC signature mechanism.

**Implementation**:
```php
if (!$request->hasValidSignature()) {
    $failures[] = 'signature';
    $this->logFailedAccess(null, null, $failures, $ip, $userAgent, $referer, signatureValid: false);

    return ValidationResult::failed(
        'קישור לא תקין. ייתכן שהקישור פג תוקף.',
        $failures
    );
}
```

**How it Works**:
- URLs are generated with `URL::temporarySignedRoute()`
- Laravel adds HMAC signature to query string
- Signature validates URL has not been tampered with
- Signature includes expiration timestamp

**Failure Message**: `"קישור לא תקין. ייתכן שהקישור פג תוקף."`
**Failure Code**: `signature`

**Attack Vector Prevented**: URL tampering, parameter injection

---

### Layer 3: Token Existence

**Purpose**: Verify the token exists in the database.

**Implementation**:
```php
// Extract token from query string
$rawToken = $request->query('token');

// Hash and search database
$token = OrderSuccessToken::findByToken($rawToken);

if (!$token) {
    $failures[] = 'token';
    $tokenHash = hash('sha256', $rawToken);
    $this->logFailedAccess(null, null, $failures, $ip, $userAgent, $referer, $tokenHash, $requestNonce, true);

    return ValidationResult::failed(
        'טוקן לא תקין או כבר נוצל.',
        $failures
    );
}
```

**Database Lookup**:
```php
// In OrderSuccessToken model
public static function findByToken(string $rawToken): ?static
{
    $hash = hash('sha256', $rawToken);

    return static::where('token_hash', $hash)
        ->whereNull('consumed_at')
        ->first();
}
```

**Failure Message**: `"טוקן לא תקין או כבר נוצל."`
**Failure Code**: `token`

**Attack Vector Prevented**: Random token guessing, non-existent tokens

---

### Layer 4: Token Validity (Expiration)

**Purpose**: Ensure the token has not expired based on TTL.

**Implementation**:
```php
if ($token->isExpired()) {
    $failures[] = 'expiration';
    $this->logFailedAccess(
        $token->payable_id,
        $token->payable_type,
        $failures,
        $ip,
        $userAgent,
        $referer,
        $token->token_hash,
        $requestNonce,
        true
    );

    return ValidationResult::failed(
        'הקישור פג תוקף. נא לבקש קישור חדש.',
        $failures
    );
}
```

**Expiration Check** (in `OrderSuccessToken` model):
```php
public function isExpired(): bool
{
    return $this->expires_at->isPast();
}
```

**TTL Configuration**:
- Default: `config('officeguy.success.token_ttl', 24)` (24 hours)
- Configurable per-token via `SecureSuccessUrlGenerator::generate($payable, $ttlHours)`

**Failure Message**: `"הקישור פג תוקף. נא לבקש קישור חדש."`
**Failure Code**: `expiration`

**Attack Vector Prevented**: Use of old/stale tokens beyond intended lifetime

---

### Layer 5: Single Use (Consumption Check)

**Purpose**: Ensure the token has not been used before (one-time use only).

**Implementation**:
```php
if ($token->isConsumed()) {
    $failures[] = 'consumed';
    $this->logFailedAccess(
        $token->payable_id,
        $token->payable_type,
        $failures,
        $ip,
        $userAgent,
        $referer,
        $token->token_hash,
        $requestNonce,
        true
    );

    return ValidationResult::failed(
        'הקישור כבר נוצל. אין אפשרות להשתמש בו שוב.',
        $failures
    );
}
```

**Consumption Check** (in `OrderSuccessToken` model):
```php
public function isConsumed(): bool
{
    return !is_null($this->consumed_at);
}

// After successful validation, token is consumed:
public function consume(string $ip, string $userAgent): void
{
    $this->update([
        'consumed_at' => now(),
        'consumed_by_ip' => $ip,
        'consumed_by_user_agent' => $userAgent,
    ]);
}
```

**Failure Message**: `"הקישור כבר נוצל. אין אפשרות להשתמש בו שוב."`
**Failure Code**: `consumed`

**Attack Vector Prevented**: Token replay attacks, multiple uses of same token

---

### Layer 6: Nonce Matching

**Purpose**: Cryptographic replay protection via one-time nonce validation.

**Implementation**:
```php
$requestNonce = $request->query('nonce');

if ($token->nonce !== $requestNonce) {
    $failures[] = 'nonce';
    $this->logFailedAccess(
        $token->payable_id,
        $token->payable_type,
        $failures,
        $ip,
        $userAgent,
        $referer,
        $token->token_hash,
        $requestNonce,
        true
    );

    return ValidationResult::failed(
        'אימות נכשל. נא לבקש קישור חדש.',
        $failures
    );
}
```

**Nonce Generation** (in `SecureSuccessUrlGenerator`):
```php
protected function generateNonce(): string
{
    return bin2hex(random_bytes(32)); // 64-character hex nonce
}
```

**Security Properties**:
- **64 characters** (32 bytes) of cryptographically secure random data
- **One-time use**: Stored in database, validated once
- **Collision resistance**: Astronomically low probability of collision

**Failure Message**: `"אימות נכשל. נא לבקש קישור חדש."`
**Failure Code**: `nonce`

**Attack Vector Prevented**: Sophisticated replay attacks, token cloning

---

### Layer 7: Identity Proof (Guest-Safe)

**Purpose**: Validate cryptographic ownership without requiring authentication.

**Implementation**:
```php
protected function validateIdentity(object $payable, Request $request): bool
{
    // If user is authenticated and payable has client_id, verify ownership
    if (auth()->check() && method_exists($payable, 'getClientId')) {
        $clientId = $payable->getClientId();

        if ($clientId && $clientId !== auth()->id()) {
            return false; // Payable belongs to different user
        }
    }

    // For guests OR authenticated users with matching client_id:
    // Identity is proven cryptographically via:
    // - Signed URL (Layer 2)
    // - One-time token (Layer 3)
    // - Nonce (Layer 6)
    // This is sufficient for guest-safe validation

    return true;
}
```

**Security Rationale**:

For **authenticated users**:
- Checks if payable belongs to current user via `client_id`
- Prevents User A from accessing User B's success page

For **guest checkout**:
- Cryptographic proof via Signed URL + Token + Nonce is sufficient
- No authentication required (guest-safe)
- Possession of valid token = proof of ownership

**Failure Message**: `"אין הרשאה לצפות בדף זה."`
**Failure Code**: `identity`

**Attack Vector Prevented**: Cross-user access, unauthorized viewing of payment details

---

## Methods Reference

### Public Methods

#### `validate(Request $request): ValidationResult`

**Purpose**: Main validation method that executes all 7 security layers.

**Parameters**:
- `$request` (Request): The HTTP request containing token, nonce, and signature

**Returns**: `ValidationResult` - DTO containing validation status, token, payable entity, error message, and failures

**Behavior**:
1. Executes all 7 validation layers sequentially
2. Logs all attempts (success and failure)
3. Consumes token on success
4. Returns detailed validation result

**Usage**:
```php
$validator = app(SuccessAccessValidator::class);
$result = $validator->validate($request);

if ($result->isValid()) {
    $order = $result->getPayable();
    $token = $result->getToken();
    // Show success page
} else {
    $errorMessage = $result->getErrorMessage();
    $failures = $result->getFailures();
    // Show error page
}
```

---

#### `getRemainingAttempts(string $ip): int`

**Purpose**: Get number of remaining rate limit attempts for an IP address.

**Parameters**:
- `$ip` (string): The IP address to check

**Returns**: `int` - Number of remaining attempts before rate limit kicks in

**Usage**:
```php
$remaining = $validator->getRemainingAttempts('192.168.1.1');
// Returns: 7 (if 3 out of 10 attempts used)
```

---

#### `clearRateLimit(string $ip): void`

**Purpose**: Clear rate limit for a specific IP address (admin use only).

**Parameters**:
- `$ip` (string): The IP address to clear

**Returns**: `void`

**Usage**:
```php
// Admin action: Reset rate limit for customer
$validator->clearRateLimit('192.168.1.1');
```

**Use Cases**:
- Customer support requests
- Legitimate users accidentally rate-limited
- Testing/debugging

---

### Protected Methods

#### `validateRateLimit(Request $request): bool`

**Purpose**: Layer 1 validation - Check if IP is within rate limits.

**Returns**: `bool` - True if within limits, false if rate limited

---

#### `validateIdentity(object $payable, Request $request): bool`

**Purpose**: Layer 7 validation - Verify cryptographic ownership.

**Parameters**:
- `$payable` (object): The Payable entity (Order, Invoice, etc.)
- `$request` (Request): The HTTP request

**Returns**: `bool` - True if identity is valid, false otherwise

---

#### `logSuccessfulAccess(...): void`

**Purpose**: Log successful access to `order_success_access_log` table.

**Parameters**:
- `$payable` (object): The Payable entity
- `$tokenHash` (string): SHA256 hash of token
- `$nonce` (string): Cryptographic nonce
- `$ip` (string): Client IP address
- `$userAgent` (string|null): Client User-Agent
- `$referer` (string|null): HTTP Referer header

**Database Record Created**:
```php
[
    'payable_id' => 123,
    'payable_type' => 'App\\Models\\Order',
    'ip_address' => '192.168.1.1',
    'user_agent' => 'Mozilla/5.0...',
    'referer' => 'https://checkout.example.com',
    'is_valid' => true,
    'validation_failures' => null,
    'token_hash' => 'abc123...',
    'nonce' => 'def456...',
    'signature_valid' => true,
    'accessed_at' => '2026-01-13 14:30:00',
]
```

---

#### `logFailedAccess(...): void`

**Purpose**: Log failed access attempt to `order_success_access_log` table.

**Parameters**:
- `$payableId` (int|null): Payable ID (may be null if not found)
- `$payableType` (string|null): Payable type (may be null)
- `$failures` (array): Array of failed validation layers
- `$ip` (string): Client IP address
- `$userAgent` (string|null): Client User-Agent
- `$referer` (string|null): HTTP Referer header
- `$tokenHash` (string|null): Token hash if available
- `$nonce` (string|null): Nonce if available
- `$signatureValid` (bool): Whether URL signature was valid

**Database Record Created**:
```php
[
    'payable_id' => null,
    'payable_type' => null,
    'ip_address' => '192.168.1.1',
    'user_agent' => 'Mozilla/5.0...',
    'referer' => null,
    'is_valid' => false,
    'validation_failures' => ['signature', 'token'],
    'token_hash' => null,
    'nonce' => null,
    'signature_valid' => false,
    'accessed_at' => '2026-01-13 14:30:00',
]
```

---

## Access Control Flow

### Complete Flow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│ User clicks success URL from webhook/email                   │
│ URL: /success?token=abc...&nonce=def...&signature=xyz...     │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ SecureSuccessController::show(Request $request)             │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ SuccessAccessValidator::validate($request)                  │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ LAYER 1: Rate Limiting                                       │
│ validateRateLimit($request)                                  │
│ - Check if IP has exceeded max attempts (10/minute)         │
│ - Increment counter via RateLimiter                         │
│ ✓ PASS → Continue                                            │
│ ✗ FAIL → Return error "חריגה ממגבלת קצב"                    │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ LAYER 2: Signed URL Validation                              │
│ $request->hasValidSignature()                               │
│ - Validate Laravel HMAC signature                           │
│ - Check signature has not expired                           │
│ ✓ PASS → Continue                                            │
│ ✗ FAIL → Return error "קישור לא תקין"                       │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ LAYER 3: Token Existence                                    │
│ OrderSuccessToken::findByToken($rawToken)                   │
│ - Hash raw token with SHA256                                │
│ - Search database for matching hash                         │
│ - Ensure token is not consumed                              │
│ ✓ PASS → Continue with $token                                │
│ ✗ FAIL → Return error "טוקן לא תקין"                        │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ LAYER 4: Token Validity (Expiration)                        │
│ $token->isExpired()                                         │
│ - Check if expires_at > now()                               │
│ ✓ PASS → Continue                                            │
│ ✗ FAIL → Return error "הקישור פג תוקף"                      │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ LAYER 5: Single Use (Consumption)                           │
│ $token->isConsumed()                                        │
│ - Check if consumed_at is NULL                              │
│ ✓ PASS → Continue                                            │
│ ✗ FAIL → Return error "הקישור כבר נוצל"                     │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ LAYER 6: Nonce Matching                                     │
│ $token->nonce === $requestNonce                             │
│ - Compare database nonce with request nonce                 │
│ ✓ PASS → Continue                                            │
│ ✗ FAIL → Return error "אימות נכשל"                          │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ Load Payable Entity                                         │
│ $payable = $token->payable                                  │
│ - Load polymorphic relationship (Order, Invoice, etc.)      │
│ ✓ EXISTS → Continue                                          │
│ ✗ NOT FOUND → Return error "הזמנה לא נמצאה"                 │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ LAYER 7: Identity Proof                                     │
│ validateIdentity($payable, $request)                        │
│ - If authenticated: Check client_id ownership               │
│ - If guest: Cryptographic proof sufficient                  │
│ ✓ PASS → Continue                                            │
│ ✗ FAIL → Return error "אין הרשאה"                           │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ 🎉 ALL LAYERS PASSED!                                        │
│                                                              │
│ 1. Log successful access → order_success_access_log         │
│ 2. Consume token → $token->consume($ip, $userAgent)         │
│ 3. Return ValidationResult::success($token, $payable)       │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ SecureSuccessController::show()                             │
│ - Dispatch SuccessPageAccessed event                        │
│ - Update analytics on payable                               │
│ - Return success view with payable data                     │
└─────────────────────────────────────────────────────────────┘
```

---

## Integration with Other Components

### 1. SecureSuccessUrlGenerator

**Purpose**: Generates cryptographically secure URLs that will be validated by `SuccessAccessValidator`.

**Flow**:
```php
// Generate secure URL
$generator = app(SecureSuccessUrlGenerator::class);
$url = $generator->generate($order);

// Internally creates:
// 1. Random 128-char token (64 bytes)
// 2. Random 64-char nonce (32 bytes)
// 3. Database record with SHA256 hash
// 4. Laravel signed URL with token + nonce
```

**Database Record Created**:
```php
OrderSuccessToken::create([
    'payable_id' => $order->id,
    'payable_type' => 'App\\Models\\Order',
    'token_hash' => hash('sha256', $token), // SHA256 hash
    'nonce' => $nonce,
    'expires_at' => now()->addHours(24), // TTL
]);
```

**Relationship**: `SecureSuccessUrlGenerator` **creates** tokens that `SuccessAccessValidator` **validates**.

---

### 2. SecureSuccessController

**Purpose**: HTTP controller that uses `SuccessAccessValidator` to display success pages.

**Implementation**:
```php
class SecureSuccessController extends Controller
{
    public function __construct(
        protected SuccessAccessValidator $validator
    ) {}

    public function show(Request $request): View|Response
    {
        // Validate via SuccessAccessValidator
        $result = $this->validator->validate($request);

        if ($result->isFailed()) {
            return response()->view('officeguy::errors.access-denied', [
                'error_message' => $result->getErrorMessage(),
                'failures' => $result->getFailures(),
            ], 403);
        }

        // Success!
        $payable = $result->getPayable();
        $token = $result->getToken();

        event(new SuccessPageAccessed($payable, $token));

        return view('officeguy::success', [
            'payable' => $payable,
            'order' => $payable, // Alias
        ]);
    }
}
```

**Relationship**: `SecureSuccessController` **uses** `SuccessAccessValidator` to enforce security before displaying success page.

---

### 3. OrderSuccessToken Model

**Purpose**: Eloquent model for storing and managing one-time success tokens.

**Database Schema**:
```sql
CREATE TABLE order_success_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payable_id BIGINT UNSIGNED NOT NULL,
    payable_type VARCHAR(255) NOT NULL,
    token_hash VARCHAR(64) NOT NULL, -- SHA256 hash
    nonce VARCHAR(64) NOT NULL, -- Cryptographic nonce
    expires_at TIMESTAMP NOT NULL,
    consumed_at TIMESTAMP NULL, -- NULL = unused
    consumed_by_ip VARCHAR(45) NULL,
    consumed_by_user_agent TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    INDEX idx_token_hash (token_hash),
    INDEX idx_payable (payable_id, payable_type),
    INDEX idx_expires_at (expires_at)
);
```

**Key Methods**:
```php
// Find by raw token
public static function findByToken(string $rawToken): ?static
{
    $hash = hash('sha256', $rawToken);
    return static::where('token_hash', $hash)
        ->whereNull('consumed_at')
        ->first();
}

// Check validity
public function isValid(): bool
{
    return is_null($this->consumed_at)
        && $this->expires_at->isFuture();
}

// Consume token
public function consume(string $ip, string $userAgent): void
{
    $this->update([
        'consumed_at' => now(),
        'consumed_by_ip' => $ip,
        'consumed_by_user_agent' => $userAgent,
    ]);
}
```

**Relationship**: `SuccessAccessValidator` **queries** and **consumes** `OrderSuccessToken` records.

---

### 4. OrderSuccessAccessLog Model

**Purpose**: Audit log for all success page access attempts (valid and invalid).

**Database Schema**:
```sql
CREATE TABLE order_success_access_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payable_id BIGINT UNSIGNED NULL, -- NULL if not found
    payable_type VARCHAR(255) NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    referer TEXT NULL,
    is_valid BOOLEAN NOT NULL, -- TRUE = success, FALSE = failure
    validation_failures JSON NULL, -- ['signature', 'token', ...]
    token_hash VARCHAR(64) NULL,
    nonce VARCHAR(64) NULL,
    signature_valid BOOLEAN DEFAULT FALSE,
    accessed_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    INDEX idx_payable (payable_id, payable_type),
    INDEX idx_ip_address (ip_address),
    INDEX idx_is_valid (is_valid),
    INDEX idx_accessed_at (accessed_at)
);
```

**Key Methods**:
```php
// Log successful access
public static function logSuccessfulAccess(
    object $payable,
    string $tokenHash,
    string $nonce,
    string $ip,
    ?string $userAgent = null,
    ?string $referer = null,
    bool $signatureValid = true
): static;

// Log failed access
public static function logFailedAccess(
    ?int $payableId,
    ?string $payableType,
    array $failures,
    string $ip,
    ?string $userAgent = null,
    ?string $referer = null,
    ?string $tokenHash = null,
    ?string $nonce = null,
    bool $signatureValid = false
): static;

// Get failure description
public function getFailuresDescription(): string
{
    $descriptions = [
        'signature' => 'חתימת URL לא תקינה',
        'token' => 'Token לא תקין',
        'nonce' => 'Nonce לא תקין',
        'expiration' => 'Token פג תוקף',
        'consumed' => 'Token כבר נוצל',
        'identity' => 'זהות לא מאומתת',
        'rate_limit' => 'חריגה ממגבלת קצב',
    ];

    return collect($this->validation_failures)
        ->map(fn ($layer) => $descriptions[$layer] ?? $layer)
        ->join(', ');
}
```

**Relationship**: `SuccessAccessValidator` **creates** `OrderSuccessAccessLog` records for every validation attempt.

---

### 5. ValidationResult DTO

**Purpose**: Data Transfer Object for encapsulating validation results.

**Structure**:
```php
class ValidationResult
{
    public function __construct(
        public readonly bool $isValid,
        public readonly ?OrderSuccessToken $token = null,
        public readonly ?object $payable = null,
        public readonly ?string $errorMessage = null,
        public readonly array $failures = []
    ) {}

    // Factory methods
    public static function success(OrderSuccessToken $token, object $payable): static;
    public static function failed(string $errorMessage, array $failures = []): static;

    // Query methods
    public function isValid(): bool;
    public function isFailed(): bool;
    public function getPayable(): ?object;
    public function getToken(): ?OrderSuccessToken;
    public function getErrorMessage(): ?string;
    public function getFailures(): array;
    public function hasFailure(string $layer): bool;
    public function getFailuresAsString(): string;

    // Serialization
    public function toArray(): array;
    public function toJson(): string;
}
```

**Usage**:
```php
$result = $validator->validate($request);

if ($result->isValid()) {
    $order = $result->getPayable();
    $token = $result->getToken();
} else {
    $errorMessage = $result->getErrorMessage();
    $failures = $result->getFailures(); // ['signature', 'token']

    if ($result->hasFailure('rate_limit')) {
        // Handle rate limit specifically
    }
}
```

**Relationship**: `SuccessAccessValidator` **returns** `ValidationResult` instances from all validation methods.

---

### 6. SuccessPageAccessed Event

**Purpose**: Event dispatched when a success page is successfully accessed (after passing all 7 layers).

**Event Class**:
```php
namespace OfficeGuy\LaravelSumitGateway\Events;

class SuccessPageAccessed
{
    public function __construct(
        public readonly object $payable,
        public readonly OrderSuccessToken $token
    ) {}
}
```

**Dispatched By**: `SecureSuccessController::show()` after successful validation

**Use Cases**:
- **Analytics**: Track success page views
- **Marketing**: Trigger email campaigns
- **CRM Integration**: Update customer records
- **Metrics**: Performance monitoring

**Listeners**:
```php
// In EventServiceProvider
protected $listen = [
    SuccessPageAccessed::class => [
        UpdateAnalyticsListener::class,
        SendConfirmationEmailListener::class,
        TrackConversionListener::class,
    ],
];
```

**Relationship**: `SuccessAccessValidator` enables the event by validating access first, then `SecureSuccessController` dispatches it.

---

## Security Best Practices

### 1. Token Security

**✅ DO**:
- Always use `SecureSuccessUrlGenerator` to create tokens
- Never store plain text tokens in database (always hash with SHA256)
- Use cryptographically secure random generation (`random_bytes()`)
- Set reasonable TTL (default 24 hours)
- Consume tokens immediately after first use

**❌ DON'T**:
- Never create tokens manually
- Never send tokens in unencrypted emails (use signed URLs)
- Never reuse consumed tokens
- Never extend expired tokens (regenerate instead)

---

### 2. Rate Limiting

**Configuration**:
```php
// config/officeguy.php
'success' => [
    'rate_limit' => [
        'max_attempts' => 10, // Max attempts per decay window
        'decay_minutes' => 1, // Window duration
    ],
],
```

**Admin Tools**:
```php
// Clear rate limit for legitimate customer
$validator = app(SuccessAccessValidator::class);
$validator->clearRateLimit('192.168.1.1');

// Check remaining attempts
$remaining = $validator->getRemainingAttempts('192.168.1.1');
```

---

### 3. Audit Logging

**All access attempts are logged**:
```php
// Query access logs
$logs = OrderSuccessAccessLog::where('payable_id', $order->id)
    ->orderBy('accessed_at', 'desc')
    ->get();

// Get failed attempts for IP
$failedAttempts = OrderSuccessAccessLog::invalid()
    ->byIp('192.168.1.1')
    ->recent(24)
    ->get();

// Analyze failures
foreach ($failedAttempts as $log) {
    echo $log->getFailuresDescription();
    // Output: "חתימת URL לא תקינה, Token לא תקין"
}
```

**Monitoring Queries**:
```php
// Suspicious IPs (multiple failed attempts)
$suspiciousIps = OrderSuccessAccessLog::invalid()
    ->recent(24)
    ->select('ip_address', DB::raw('COUNT(*) as attempts'))
    ->groupBy('ip_address')
    ->having('attempts', '>', 5)
    ->get();

// Most common failures
$commonFailures = OrderSuccessAccessLog::invalid()
    ->recent(24)
    ->get()
    ->flatMap(fn($log) => $log->validation_failures)
    ->countBy()
    ->sortDesc();
```

---

### 4. Guest Checkout Security

**Guest-Safe Design**:
- No authentication required for success page access
- Cryptographic proof (Signed URL + Token + Nonce) replaces authentication
- Each layer adds defense-in-depth protection

**For Guest Orders**:
```php
// Layer 7 validation is guest-safe
protected function validateIdentity(object $payable, Request $request): bool
{
    // If authenticated, check ownership
    if (auth()->check() && method_exists($payable, 'getClientId')) {
        $clientId = $payable->getClientId();
        if ($clientId && $clientId !== auth()->id()) {
            return false; // Wrong user
        }
    }

    // For guests: Cryptographic proof is sufficient
    // Possessing valid token + nonce + signed URL = proof of ownership
    return true;
}
```

---

### 5. URL Expiration

**TTL Configuration**:
```php
// Default TTL (24 hours)
$url = $generator->generate($order);

// Custom TTL (1 hour for sensitive data)
$url = $generator->generate($order, ttlHours: 1);

// Long TTL (7 days for invoices)
$url = $generator->generate($invoice, ttlHours: 168);
```

**Expiration Handling**:
- Expired tokens return error: `"הקישור פג תוקף. נא לבקש קישור חדש."`
- Provide regeneration mechanism for legitimate users
- Log expiration failures for analytics

---

### 6. Replay Attack Prevention

**Multi-Layer Protection**:

1. **Nonce (Layer 6)**: Cryptographic one-time value
2. **Single Use (Layer 5)**: Token consumed after first use
3. **Signed URL (Layer 2)**: HMAC signature prevents tampering
4. **Expiration (Layer 4)**: Time-bound validity

**Attack Scenario**:
```
Attacker captures URL:
https://example.com/success?token=abc...&nonce=def...&signature=xyz...

Attacker tries to reuse URL:
1. Layer 2: Signature valid ✓
2. Layer 3: Token exists ✓
3. Layer 4: Not expired ✓
4. Layer 5: Token already consumed ✗ → BLOCKED!
```

---

## Usage Examples

### Example 1: Payment Flow (Complete)

```php
<?php

namespace App\Services;

use OfficeGuy\LaravelSumitGateway\Services\SecureSuccessUrlGenerator;
use App\Models\Order;

class PaymentService
{
    public function processPayment(Order $order): array
    {
        // 1. Process payment via SUMIT API
        $paymentResult = $this->chargeCard($order);

        if ($paymentResult['Status'] !== 'Success') {
            throw new \Exception('Payment failed');
        }

        // 2. Generate secure success URL
        $generator = app(SecureSuccessUrlGenerator::class);
        $successUrl = $generator->generate($order, ttlHours: 24);

        // 3. Send to webhook confirmation flow
        // (Provisioning happens via webhook, NOT via success page)

        return [
            'status' => 'success',
            'success_url' => $successUrl,
            'order_id' => $order->id,
        ];
    }
}
```

**Generated URL**:
```
https://example.com/success
    ?token=a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6...
    &nonce=a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4...
    &signature=xyz123...
    &expires=1736782800
```

**Database Records**:
```sql
-- order_success_tokens
INSERT INTO order_success_tokens (payable_id, payable_type, token_hash, nonce, expires_at)
VALUES (
    123,
    'App\\Models\\Order',
    'sha256_hash_of_token_here',
    'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4',
    '2026-01-14 14:00:00'
);
```

---

### Example 2: Success Page Access (Validated)

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use OfficeGuy\LaravelSumitGateway\Services\SuccessAccessValidator;

class OrderSuccessController extends Controller
{
    public function show(Request $request)
    {
        $validator = app(SuccessAccessValidator::class);
        $result = $validator->validate($request);

        if ($result->isFailed()) {
            // Log failure for debugging
            \Log::warning('Success page access denied', [
                'failures' => $result->getFailures(),
                'ip' => $request->ip(),
            ]);

            // Show error page
            return response()->view('errors.access-denied', [
                'error_message' => $result->getErrorMessage(),
            ], 403);
        }

        // ✅ Access validated!
        $order = $result->getPayable();
        $token = $result->getToken();

        // Show success page
        return view('orders.success', [
            'order' => $order,
            'customer' => $order->customer,
            'items' => $order->items,
        ]);
    }
}
```

**Validation Flow**:
```
Request: GET /success?token=abc...&nonce=def...&signature=xyz...

Layer 1: Rate Limit      → ✅ PASS (3/10 attempts)
Layer 2: Signed URL      → ✅ PASS (signature valid)
Layer 3: Token Existence → ✅ PASS (token found in DB)
Layer 4: Expiration      → ✅ PASS (expires_at = 2026-01-14 14:00:00, now = 2026-01-13 14:30:00)
Layer 5: Consumption     → ✅ PASS (consumed_at = NULL)
Layer 6: Nonce           → ✅ PASS (nonce matches)
Layer 7: Identity        → ✅ PASS (guest checkout, cryptographic proof valid)

Result: ValidationResult::success($token, $order)
```

---

### Example 3: Failed Access (Rate Limited)

```php
<?php

// Attacker tries 11 requests in 1 minute

Request 1-10:
Layer 1: Rate Limit → ✅ PASS
Layer 2: Signed URL → ❌ FAIL (invalid signature)
Result: ValidationResult::failed('קישור לא תקין', ['signature'])

Request 11:
Layer 1: Rate Limit → ❌ FAIL (too many attempts)
Result: ValidationResult::failed('חריגה ממגבלת קצב', ['rate_limit'])

// All 11 requests logged to order_success_access_log
```

**Database Logs**:
```sql
SELECT * FROM order_success_access_log WHERE ip_address = '192.168.1.1' ORDER BY accessed_at DESC;

| id  | ip_address    | is_valid | validation_failures | accessed_at          |
|-----|---------------|----------|---------------------|----------------------|
| 11  | 192.168.1.1   | false    | ["rate_limit"]      | 2026-01-13 14:31:00 |
| 10  | 192.168.1.1   | false    | ["signature"]       | 2026-01-13 14:30:59 |
| 9   | 192.168.1.1   | false    | ["signature"]       | 2026-01-13 14:30:58 |
| ... | ...           | ...      | ...                 | ...                  |
```

---

### Example 4: Admin Rate Limit Reset

```php
<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use OfficeGuy\LaravelSumitGateway\Services\SuccessAccessValidator;

class RateLimitController extends Controller
{
    public function reset(Request $request)
    {
        $request->validate([
            'ip_address' => 'required|ip',
        ]);

        $validator = app(SuccessAccessValidator::class);
        $validator->clearRateLimit($request->ip_address);

        return response()->json([
            'success' => true,
            'message' => 'Rate limit cleared for ' . $request->ip_address,
        ]);
    }

    public function check(Request $request)
    {
        $request->validate([
            'ip_address' => 'required|ip',
        ]);

        $validator = app(SuccessAccessValidator::class);
        $remaining = $validator->getRemainingAttempts($request->ip_address);

        return response()->json([
            'ip_address' => $request->ip_address,
            'remaining_attempts' => $remaining,
            'max_attempts' => config('officeguy.success.rate_limit.max_attempts', 10),
        ]);
    }
}
```

---

### Example 5: Regenerate Expired URL

```php
<?php

namespace App\Services;

use OfficeGuy\LaravelSumitGateway\Services\SecureSuccessUrlGenerator;
use App\Models\Order;

class OrderService
{
    public function resendSuccessLink(Order $order): string
    {
        $generator = app(SecureSuccessUrlGenerator::class);

        // Regenerate URL (invalidates old tokens)
        $newUrl = $generator->regenerate($order, ttlHours: 24);

        // Send via email
        \Mail::to($order->customer)->send(new OrderSuccessEmail($order, $newUrl));

        return $newUrl;
    }
}
```

**Behavior**:
```php
// Before regeneration
OrderSuccessToken::where('payable_id', 123)
    ->whereNull('consumed_at')
    ->get();
// Returns: [Token #1, Token #2] (old unused tokens)

// After regeneration
OrderSuccessToken::where('payable_id', 123)
    ->whereNull('consumed_at')
    ->get();
// Returns: [Token #3] (only new token)

// Old tokens marked as consumed
OrderSuccessToken::find(1)->consumed_at;
// Returns: '2026-01-13 14:30:00'

OrderSuccessToken::find(1)->consumed_by_user_agent;
// Returns: 'System: Regenerated'
```

---

### Example 6: Security Monitoring Dashboard

```php
<?php

namespace App\Http\Controllers\Admin;

use OfficeGuy\LaravelSumitGateway\Models\OrderSuccessAccessLog;
use OfficeGuy\LaravelSumitGateway\Models\OrderSuccessToken;

class SecurityDashboardController extends Controller
{
    public function index()
    {
        // Failed access attempts in last 24 hours
        $failedAttempts = OrderSuccessAccessLog::invalid()
            ->recent(24)
            ->count();

        // Suspicious IPs (>5 failed attempts)
        $suspiciousIps = OrderSuccessAccessLog::invalid()
            ->recent(24)
            ->select('ip_address', DB::raw('COUNT(*) as attempts'))
            ->groupBy('ip_address')
            ->having('attempts', '>', 5)
            ->get();

        // Most common failure types
        $failureStats = OrderSuccessAccessLog::invalid()
            ->recent(24)
            ->get()
            ->flatMap(fn($log) => $log->validation_failures)
            ->countBy()
            ->sortDesc();

        // Expired unused tokens (potential issues)
        $expiredUnused = OrderSuccessToken::expired()
            ->whereNull('consumed_at')
            ->count();

        // Success rate
        $totalAttempts = OrderSuccessAccessLog::recent(24)->count();
        $successfulAttempts = OrderSuccessAccessLog::valid()->recent(24)->count();
        $successRate = $totalAttempts > 0 ? ($successfulAttempts / $totalAttempts) * 100 : 0;

        return view('admin.security-dashboard', [
            'failed_attempts' => $failedAttempts,
            'suspicious_ips' => $suspiciousIps,
            'failure_stats' => $failureStats,
            'expired_unused' => $expiredUnused,
            'success_rate' => $successRate,
        ]);
    }
}
```

**Dashboard Output**:
```
Security Dashboard (Last 24 Hours)
═══════════════════════════════════════════════════════════

Failed Attempts: 47
Successful Attempts: 1,253
Success Rate: 96.37%

Suspicious IPs (>5 failed attempts):
┌────────────────┬──────────┐
│ IP Address     │ Attempts │
├────────────────┼──────────┤
│ 192.168.1.100  │ 23       │
│ 10.0.0.50      │ 12       │
│ 172.16.0.5     │ 8        │
└────────────────┴──────────┘

Failure Types:
┌────────────────┬───────┐
│ Type           │ Count │
├────────────────┼───────┤
│ signature      │ 25    │
│ consumed       │ 12    │
│ token          │ 8     │
│ rate_limit     │ 2     │
└────────────────┴───────┘

Expired Unused Tokens: 5 (investigate!)
```

---

## Testing & Debugging

### Unit Tests

```php
<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use OfficeGuy\LaravelSumitGateway\Services\SuccessAccessValidator;
use OfficeGuy\LaravelSumitGateway\Services\SecureSuccessUrlGenerator;
use OfficeGuy\LaravelSumitGateway\Models\OrderSuccessToken;
use App\Models\Order;

class SuccessAccessValidatorTest extends TestCase
{
    use RefreshDatabase;

    protected SuccessAccessValidator $validator;
    protected SecureSuccessUrlGenerator $generator;
    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = app(SuccessAccessValidator::class);
        $this->generator = app(SecureSuccessUrlGenerator::class);
        $this->order = Order::factory()->create();
    }

    /** @test */
    public function it_passes_all_validation_layers_for_valid_request()
    {
        // Generate valid URL
        $url = $this->generator->generate($this->order);

        // Create request from URL
        $request = Request::create($url);

        // Validate
        $result = $this->validator->validate($request);

        // Assert
        $this->assertTrue($result->isValid());
        $this->assertInstanceOf(Order::class, $result->getPayable());
        $this->assertEquals($this->order->id, $result->getPayable()->id);
    }

    /** @test */
    public function it_fails_layer_1_when_rate_limited()
    {
        $ip = '192.168.1.1';

        // Exhaust rate limit
        $maxAttempts = config('officeguy.success.rate_limit.max_attempts', 10);
        for ($i = 0; $i < $maxAttempts; $i++) {
            RateLimiter::hit('success-access:' . $ip);
        }

        // Create request
        $url = $this->generator->generate($this->order);
        $request = Request::create($url);
        $request->server->set('REMOTE_ADDR', $ip);

        // Validate
        $result = $this->validator->validate($request);

        // Assert
        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasFailure('rate_limit'));
        $this->assertEquals('חריגה ממגבלת קצב. נא לנסות שנית בעוד מספר דקות.', $result->getErrorMessage());
    }

    /** @test */
    public function it_fails_layer_2_with_invalid_signature()
    {
        // Create request with tampered signature
        $url = $this->generator->generate($this->order);
        $tamperedUrl = str_replace('signature=', 'signature=tampered', $url);

        $request = Request::create($tamperedUrl);

        // Validate
        $result = $this->validator->validate($request);

        // Assert
        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasFailure('signature'));
    }

    /** @test */
    public function it_fails_layer_3_with_nonexistent_token()
    {
        // Create signed URL but don't create database record
        $fakeToken = bin2hex(random_bytes(64));
        $nonce = bin2hex(random_bytes(32));

        $url = URL::temporarySignedRoute(
            'officeguy.success',
            now()->addHours(1),
            ['token' => $fakeToken, 'nonce' => $nonce]
        );

        $request = Request::create($url);

        // Validate
        $result = $this->validator->validate($request);

        // Assert
        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasFailure('token'));
    }

    /** @test */
    public function it_fails_layer_4_with_expired_token()
    {
        // Generate URL with 1 second TTL
        $url = $this->generator->generate($this->order, ttlHours: 0);

        // Fast-forward time
        $this->travel(2)->seconds();

        $request = Request::create($url);

        // Validate
        $result = $this->validator->validate($request);

        // Assert
        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasFailure('expiration'));
    }

    /** @test */
    public function it_fails_layer_5_with_consumed_token()
    {
        // Generate valid URL
        $url = $this->generator->generate($this->order);
        $request = Request::create($url);

        // First access (should succeed)
        $result1 = $this->validator->validate($request);
        $this->assertTrue($result1->isValid());

        // Second access (should fail - consumed)
        $result2 = $this->validator->validate($request);
        $this->assertFalse($result2->isValid());
        $this->assertTrue($result2->hasFailure('consumed'));
    }

    /** @test */
    public function it_fails_layer_6_with_mismatched_nonce()
    {
        // Generate valid URL
        $url = $this->generator->generate($this->order);

        // Tamper nonce
        $tamperedUrl = preg_replace('/nonce=([^&]+)/', 'nonce=tampered', $url);

        $request = Request::create($tamperedUrl);

        // Validate
        $result = $this->validator->validate($request);

        // Assert
        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasFailure('nonce'));
    }

    /** @test */
    public function it_logs_successful_access()
    {
        $url = $this->generator->generate($this->order);
        $request = Request::create($url);

        $this->validator->validate($request);

        // Assert log created
        $this->assertDatabaseHas('order_success_access_log', [
            'payable_id' => $this->order->id,
            'payable_type' => get_class($this->order),
            'is_valid' => true,
        ]);
    }

    /** @test */
    public function it_logs_failed_access()
    {
        // Create invalid request
        $request = Request::create('/success?token=invalid&nonce=invalid');

        $this->validator->validate($request);

        // Assert log created
        $this->assertDatabaseHas('order_success_access_log', [
            'is_valid' => false,
        ]);
    }
}
```

---

### Feature Tests

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OfficeGuy\LaravelSumitGateway\Services\SecureSuccessUrlGenerator;
use App\Models\Order;

class SuccessPageAccessTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function guest_can_access_success_page_with_valid_token()
    {
        $order = Order::factory()->create();
        $generator = app(SecureSuccessUrlGenerator::class);
        $url = $generator->generate($order);

        $response = $this->get($url);

        $response->assertStatus(200);
        $response->assertViewIs('officeguy::success');
        $response->assertViewHas('order', $order);
    }

    /** @test */
    public function authenticated_user_can_access_their_own_success_page()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['client_id' => $user->id]);

        $generator = app(SecureSuccessUrlGenerator::class);
        $url = $generator->generate($order);

        $response = $this->actingAs($user)->get($url);

        $response->assertStatus(200);
    }

    /** @test */
    public function authenticated_user_cannot_access_another_users_success_page()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $order = Order::factory()->create(['client_id' => $user2->id]);

        $generator = app(SecureSuccessUrlGenerator::class);
        $url = $generator->generate($order);

        $response = $this->actingAs($user1)->get($url);

        $response->assertStatus(403);
        $response->assertViewIs('officeguy::errors.access-denied');
    }

    /** @test */
    public function token_can_only_be_used_once()
    {
        $order = Order::factory()->create();
        $generator = app(SecureSuccessUrlGenerator::class);
        $url = $generator->generate($order);

        // First access
        $response1 = $this->get($url);
        $response1->assertStatus(200);

        // Second access
        $response2 = $this->get($url);
        $response2->assertStatus(403);
    }
}
```

---

### Debugging Tips

**1. Enable Detailed Logging**:
```php
// In SuccessAccessValidator::validate()
\Log::debug('Validation attempt', [
    'layer' => 'Layer X',
    'result' => 'pass/fail',
    'details' => [...],
]);
```

**2. Check Access Logs**:
```php
// View last 10 access attempts for order
$logs = OrderSuccessAccessLog::where('payable_id', $orderId)
    ->orderBy('accessed_at', 'desc')
    ->limit(10)
    ->get();

foreach ($logs as $log) {
    echo $log->is_valid ? '✅' : '❌';
    echo ' ' . $log->accessed_at;
    echo ' ' . $log->ip_address;
    echo ' ' . $log->getFailuresDescription();
    echo "\n";
}
```

**3. Test Rate Limiting**:
```php
$validator = app(SuccessAccessValidator::class);
$remaining = $validator->getRemainingAttempts('192.168.1.1');
echo "Remaining attempts: {$remaining}\n";
```

**4. Verify Token Generation**:
```php
$generator = app(SecureSuccessUrlGenerator::class);
$url = $generator->generate($order);

$parsedUrl = parse_url($url);
parse_str($parsedUrl['query'], $params);

echo "Token: " . $params['token'] . "\n";
echo "Nonce: " . $params['nonce'] . "\n";
echo "Signature: " . $params['signature'] . "\n";
echo "Expires: " . date('Y-m-d H:i:s', $params['expires']) . "\n";

// Check database record
$tokenHash = hash('sha256', $params['token']);
$dbToken = OrderSuccessToken::where('token_hash', $tokenHash)->first();
dd($dbToken);
```

**5. Test Individual Layers**:
```php
// Test Layer 1: Rate Limiting
$request = Request::create('/test');
$request->server->set('REMOTE_ADDR', '192.168.1.1');
$result = $validator->validateRateLimit($request);
var_dump($result); // bool

// Test Layer 2: Signed URL
$url = URL::temporarySignedRoute('officeguy.success', now()->addHour(), [
    'token' => 'test',
    'nonce' => 'test',
]);
$request = Request::create($url);
var_dump($request->hasValidSignature()); // bool

// Test Layer 3: Token Existence
$token = OrderSuccessToken::findByToken('raw_token_here');
var_dump($token); // OrderSuccessToken|null

// Test Layer 4: Expiration
var_dump($token->isExpired()); // bool

// Test Layer 5: Consumption
var_dump($token->isConsumed()); // bool

// Test Layer 6: Nonce
var_dump($token->nonce === 'provided_nonce'); // bool

// Test Layer 7: Identity
$result = $validator->validateIdentity($order, $request);
var_dump($result); // bool
```

---

## Summary

### Key Takeaways

1. **Defense in Depth**: 7 independent security layers provide robust protection
2. **Zero Trust**: Every request validated from scratch, no shortcuts
3. **Guest-Safe**: Works for authenticated users and guest checkout
4. **Single Use**: Tokens consumed after first successful access
5. **Audit Trail**: All attempts logged for security monitoring
6. **Cryptographic Security**: SHA256 hashing, CSPRNG random generation, HMAC signatures

---

### Security Layers Summary

| Layer | Purpose | Failure Code | Attack Vector Prevented |
|-------|---------|--------------|-------------------------|
| 1 | Rate Limiting | `rate_limit` | Brute force attacks |
| 2 | Signed URL | `signature` | URL tampering, parameter injection |
| 3 | Token Existence | `token` | Random token guessing |
| 4 | Token Validity | `expiration` | Use of stale/old tokens |
| 5 | Single Use | `consumed` | Token replay attacks |
| 6 | Nonce Matching | `nonce` | Sophisticated replay attacks |
| 7 | Identity Proof | `identity` | Cross-user access |

---

### Integration Points

- **SecureSuccessUrlGenerator**: Creates tokens
- **SecureSuccessController**: Uses validator
- **OrderSuccessToken**: Stores token data
- **OrderSuccessAccessLog**: Audit logging
- **ValidationResult**: Result encapsulation
- **SuccessPageAccessed**: Event dispatching

---

### Configuration

```php
// config/officeguy.php
'success' => [
    'enabled' => true,
    'token_ttl' => 24, // Hours
    'rate_limit' => [
        'max_attempts' => 10,
        'decay_minutes' => 1,
    ],
],
```

---

### Best Practices

✅ **DO**:
- Use `SecureSuccessUrlGenerator` for all token creation
- Monitor `OrderSuccessAccessLog` for suspicious activity
- Set reasonable TTL based on use case
- Clear rate limits for legitimate customers when needed
- Log all validation failures
- Test all 7 layers independently

❌ **DON'T**:
- Create tokens manually
- Store plain text tokens
- Reuse consumed tokens
- Extend expired tokens (regenerate instead)
- Skip validation layers
- Ignore failed access logs

---

**Document Version**: 1.0
**Last Updated**: 2026-01-13
**Maintainer**: NM-DigitalHub
**Package**: officeguy/laravel-sumit-gateway v1.1.6+
