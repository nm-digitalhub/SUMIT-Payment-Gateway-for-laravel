# ServiceDataFactory - Comprehensive Analysis

**Package**: `officeguy/laravel-sumit-gateway`
**Version**: v1.2.0+
**File**: `src/Services/ServiceDataFactory.php`
**Last Updated**: 2026-01-13

---

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Methods Analysis](#methods-analysis)
4. [Factory Pattern Implementation](#factory-pattern-implementation)
5. [Service Type Detection](#service-type-detection)
6. [Data Builders](#data-builders)
7. [Dependencies](#dependencies)
8. [Integration Points](#integration-points)
9. [Configuration](#configuration)
10. [Best Practices](#best-practices)
11. [Usage Examples](#usage-examples)
12. [Future Enhancements](#future-enhancements)
13. [Summary](#summary)

---

## Overview

### Purpose

`ServiceDataFactory` is a **domain-specific data transformation service** that converts payment checkout intentions into service-specific data structures ready for external API consumption (e.g., domain registrars, hosting providers, VPS platforms).

### Core Responsibility

```
CheckoutIntent (purchase intention)
         ↓
ServiceDataFactory::build()
         ↓
Service-Specific Data (ResellerClub/cPanel/VPS API format)
```

### Key Characteristics

- **Returns plain arrays** (not DTOs) - ready for external API consumption
- **No ServiceType Enum** - service detection is dynamic from Payable entities
- **Service data stored separately** - not embedded in CheckoutIntent
- **Extensible design** - easy to add new service types (hosting, VPS, SSL)

### Critical Rules

1. ✅ Returns plain `array<string, mixed>` (not DTO)
2. ✅ Service detection is dynamic (no hardcoded Enum)
3. ✅ Service data is stored separately (not in CheckoutIntent)
4. ✅ Focused on external API integration (not internal business logic)

---

## Architecture

### Design Pattern

**Factory Pattern** with **Strategy Pattern** elements:

```
ServiceDataFactory (Factory)
    ├── detectServiceType() → Determines strategy
    └── build() → Uses match() to select builder method
        ├── buildDomainData() → Domain registration strategy
        ├── buildHostingData() → Hosting provisioning strategy
        ├── buildVpsData() → VPS configuration strategy
        └── buildSslData() → SSL certificate strategy
```

### Class Structure

```php
class ServiceDataFactory
{
    // PUBLIC API
    public function build(CheckoutIntent $intent): array

    // SERVICE DETECTION
    protected function detectServiceType($payable): string

    // DATA BUILDERS (Strategy Methods)
    protected function buildDomainData(CheckoutIntent $intent): array
    protected function buildHostingData(CheckoutIntent $intent): array
    protected function buildVpsData(CheckoutIntent $intent): array
    protected function buildSslData(CheckoutIntent $intent): array

    // HELPER METHODS (Domain-specific)
    protected function formatPhoneForWhois(string $phone): string
    protected function shouldEnablePrivacy(CheckoutIntent $intent): bool
    protected function getDefaultNameservers(): array
    protected function getDomainYears($payable): int
}
```

### Responsibilities

| Component | Responsibility | Example |
|-----------|----------------|---------|
| **build()** | Main entry point, orchestrates data creation | Returns service data for checkout |
| **detectServiceType()** | Identifies service type dynamically | 'domain', 'hosting', 'vps', 'ssl' |
| **buildXxxData()** | Creates service-specific data structures | ResellerClub WHOIS format |
| **Helper methods** | Domain-specific transformations | Phone: `0541234567` → `+972.541234567` |

---

## Methods Analysis

### 1. build() - Main Entry Point

```php
public function build(CheckoutIntent $intent): array
```

**Purpose**: Transforms checkout intent into service-specific data ready for external APIs.

**Process Flow**:
```
1. Detect service type from payable (dynamic detection)
2. Match service type to appropriate builder method
3. Return service-specific data array
4. Return empty array [] for unknown service types (safe fallback)
```

**Parameters**:
- `$intent` (CheckoutIntent): Complete checkout context including customer, payment preferences, and payable entity

**Returns**:
- `array<string, mixed>`: Service-specific data ready for external API (e.g., ResellerClub format)

**Example**:
```php
$factory = new ServiceDataFactory();
$serviceData = $factory->build($checkoutIntent);

// For DomainPackage → Returns:
[
    'registrant_contact' => [...],
    'privacy_protection' => true,
    'nameservers' => ['ns1.example.com', 'ns2.example.com'],
    'years' => 1
]
```

**Match Statement**:
```php
return match ($serviceType) {
    'domain' => $this->buildDomainData($intent),
    'hosting' => $this->buildHostingData($intent),
    'vps' => $this->buildVpsData($intent),
    'ssl' => $this->buildSslData($intent),
    default => [],  // Safe fallback - no exception thrown
};
```

---

### 2. detectServiceType() - Dynamic Service Detection

```php
protected function detectServiceType($payable): string
```

**Purpose**: Identifies service type from payable entity using a **4-level priority cascade**.

**Priority System**:

```
Priority 1: service_type property (highest)
    ↓
Priority 2: getServiceType() method
    ↓
Priority 3: Class name inference (DomainPackage → 'domain')
    ↓
Priority 4: PayableType fallback (lowest)
```

**Detection Logic**:

```php
// Priority 1: Direct property
if (property_exists($payable, 'service_type') && !empty($payable->service_type)) {
    return $payable->service_type;  // e.g., 'domain', 'hosting', 'vps'
}

// Priority 2: Method call
if (method_exists($payable, 'getServiceType')) {
    return $payable->getServiceType();  // Dynamic determination
}

// Priority 3: Class name inference
$className = class_basename($payable);
if (str_contains($className, 'Domain')) return 'domain';
if (str_contains($className, 'Hosting')) return 'hosting';
if (str_contains($className, 'Vps') || str_contains($className, 'VPS')) return 'vps';
if (str_contains($className, 'Ssl') || str_contains($className, 'SSL')) return 'ssl';

// Priority 4: PayableType fallback
return match ($payable->getPayableType()) {
    PayableType::INFRASTRUCTURE => 'domain',  // Default for infrastructure
    PayableType::DIGITAL_PRODUCT => 'digital',
    PayableType::SUBSCRIPTION => 'subscription',
    default => 'generic',
};
```

**Examples**:

| Payable | Detection Result | Method Used |
|---------|------------------|-------------|
| `DomainPackage` with `service_type = 'domain'` | `'domain'` | Priority 1 (property) |
| `HostingPackage::getServiceType()` returns `'hosting'` | `'hosting'` | Priority 2 (method) |
| `VpsPackage` (class name) | `'vps'` | Priority 3 (inference) |
| Generic payable with `PayableType::INFRASTRUCTURE` | `'domain'` | Priority 4 (fallback) |

**Why No Enum?**

- Flexibility: New service types can be added without modifying core enums
- Decoupling: Payable entities control their own service type
- Dynamic: Service type can change based on runtime conditions
- Extensibility: Third-party packages can add custom service types

---

### 3. buildDomainData() - Domain Registration Data

```php
protected function buildDomainData(CheckoutIntent $intent): array
```

**Purpose**: Prepares WHOIS contact data for domain registration APIs (ResellerClub format).

**Output Structure**:

```php
[
    'registrant_contact' => [
        'name' => 'John Doe',
        'company' => 'Acme Corp',
        'email' => 'john@example.com',
        'address1' => '123 Main St',
        'address2' => 'Suite 100',
        'city' => 'Tel Aviv',
        'state' => 'Tel Aviv District',
        'country' => 'IL',
        'zipcode' => '6801234',
        'phone' => '+972.541234567',  // ResellerClub format
    ],
    'admin_contact' => 'same_as_registrant',   // Use registrant for admin
    'tech_contact' => 'same_as_registrant',    // Use registrant for tech
    'billing_contact' => 'same_as_registrant', // Use registrant for billing
    'privacy_protection' => true,              // WHOIS privacy enabled
    'nameservers' => [
        'ns1.example.com',
        'ns2.example.com',
    ],
    'years' => 1,  // Registration period
]
```

**Data Sources**:

| Field | Source | Transformation |
|-------|--------|----------------|
| `registrant_contact.name` | `$intent->customer->name` | Direct |
| `registrant_contact.company` | `$intent->customer->company` | Nullable (`??`) |
| `registrant_contact.email` | `$intent->customer->email` | Direct |
| `registrant_contact.address1` | `$intent->customer->address->line1` | Nullable |
| `registrant_contact.city` | `$intent->customer->address->city` | Nullable |
| `registrant_contact.country` | `$intent->customer->address->country` | Default: `'IL'` |
| `registrant_contact.phone` | `$intent->customer->phone` | `formatPhoneForWhois()` |
| `privacy_protection` | Config | `shouldEnablePrivacy()` |
| `nameservers` | Config | `getDefaultNameservers()` |
| `years` | Payable | `getDomainYears()` |

**Integration Target**: ResellerClub Domain Registration API

---

### 4. buildHostingData() - Hosting Provisioning Data

```php
protected function buildHostingData(CheckoutIntent $intent): array
```

**Purpose**: Prepares cPanel/WHM provisioning data for hosting account creation.

**Status**: Placeholder (TODO implementation)

**Expected Output Structure** (when implemented):

```php
[
    'username' => 'user123',           // cPanel username
    'domain' => 'example.com',         // Primary domain
    'plan' => 'basic_hosting',         // Hosting plan
    'quota' => 10240,                  // Disk quota (MB)
    'bandwidth' => 102400,             // Bandwidth limit (MB)
    'email' => 'user@example.com',     // Contact email
    'password' => 'auto-generated',    // Auto-generated password
    'features' => [
        'ssl' => true,
        'backup' => true,
        'email_accounts' => 10,
    ],
]
```

**Integration Target**: cPanel/WHM API (future)

---

### 5. buildVpsData() - VPS Configuration Data

```php
protected function buildVpsData(CheckoutIntent $intent): array
```

**Purpose**: Prepares VPS server configuration data.

**Status**: Placeholder (TODO implementation)

**Expected Output Structure** (when implemented):

```php
[
    'hostname' => 'vps-server-01',
    'os' => 'ubuntu-22.04',
    'plan' => 'vps-standard',
    'cpu_cores' => 2,
    'ram_mb' => 4096,
    'disk_gb' => 80,
    'network' => [
        'ipv4' => 'auto',
        'ipv6' => true,
    ],
    'ssh_key' => 'ssh-rsa AAAA...',  // Customer's SSH key
]
```

**Integration Target**: VPS provider API (future)

---

### 6. buildSslData() - SSL Certificate Request Data

```php
protected function buildSslData(CheckoutIntent $intent): array
```

**Purpose**: Prepares SSL certificate request data.

**Status**: Placeholder (TODO implementation)

**Expected Output Structure** (when implemented):

```php
[
    'domain' => 'example.com',
    'type' => 'DV',  // Domain Validated
    'csr' => '-----BEGIN CERTIFICATE REQUEST-----...',
    'validation_method' => 'dns',  // dns/http/email
    'admin_email' => 'admin@example.com',
    'years' => 1,
]
```

**Integration Target**: SSL certificate provider API (future)

---

### 7. formatPhoneForWhois() - Phone Number Formatter

```php
protected function formatPhoneForWhois(string $phone): string
```

**Purpose**: Converts Israeli phone numbers to ResellerClub WHOIS format.

**Transformation**:

```
Input:  0541234567
  ↓ Remove non-numeric characters
  ↓ Remove leading zero
  ↓ Add country code prefix +972.
Output: +972.541234567
```

**Implementation**:

```php
protected function formatPhoneForWhois(string $phone): string
{
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);

    // Remove leading zero if present
    if (str_starts_with($phone, '0')) {
        $phone = substr($phone, 1);
    }

    // Format: +972.XXXXXXXXX (ResellerClub requirement)
    return '+972.' . $phone;
}
```

**Examples**:

| Input | Output |
|-------|--------|
| `0541234567` | `+972.541234567` |
| `054-123-4567` | `+972.541234567` |
| `+972-54-123-4567` | `+972.541234567` |
| `972541234567` | `+972.541234567` |

**API Requirement**: ResellerClub requires phone format `+COUNTRY_CODE.PHONE_NUMBER`

---

### 8. shouldEnablePrivacy() - Privacy Protection Check

```php
protected function shouldEnablePrivacy(CheckoutIntent $intent): bool
```

**Purpose**: Determines if WHOIS privacy protection should be enabled for domain registration.

**Logic**:

```php
return (bool) config('officeguy.domain_privacy_protection', true);
```

**Configuration Key**: `domain_privacy_protection`

**Default**: `true` (privacy enabled by default)

**Future Enhancements**:
- Per-customer preference override
- Per-domain TLD rules (some TLDs don't support privacy)
- Integration with customer privacy settings

**Example**:

```php
// config/officeguy.php
'domain_privacy_protection' => env('OFFICEGUY_DOMAIN_PRIVACY', true),

// Result
$factory->shouldEnablePrivacy($intent);  // → true
```

---

### 9. getDefaultNameservers() - Nameserver Configuration

```php
protected function getDefaultNameservers(): array
```

**Purpose**: Retrieves default nameservers from configuration.

**Configuration Key**: `default_nameservers`

**Default**:
```php
[
    'ns1.example.com',
    'ns2.example.com',
]
```

**Expected Production Format**:
```php
// config/officeguy.php
'default_nameservers' => [
    'ns1.yourdomain.com',
    'ns2.yourdomain.com',
]
```

**Future Enhancements**:
- Per-package nameserver overrides
- Dynamic nameserver selection based on hosting provider
- Validation of nameserver availability

---

### 10. getDomainYears() - Registration Period

```php
protected function getDomainYears($payable): int
```

**Purpose**: Extracts domain registration period from payable entity.

**Detection Priority**:

```
1. getYears() method → $payable->getYears()
2. years property → $payable->years
3. Default fallback → 1 year
```

**Implementation**:

```php
protected function getDomainYears($payable): int
{
    // Try method first
    if (method_exists($payable, 'getYears')) {
        return max(1, (int) $payable->getYears());  // Minimum 1 year
    }

    // Try property second
    if (property_exists($payable, 'years') && !empty($payable->years)) {
        return max(1, (int) $payable->years);  // Minimum 1 year
    }

    // Default: 1 year
    return 1;
}
```

**Safety**: Always returns minimum 1 year (even if payable has invalid value like 0 or negative)

**Examples**:

| Payable | Method/Property | Result |
|---------|----------------|--------|
| `DomainPackage` with `getYears()` returning `2` | Method | `2` |
| `DomainPackage` with `years = 3` | Property | `3` |
| `DomainPackage` with `years = 0` | Property | `1` (forced minimum) |
| Generic payable (no years field) | Fallback | `1` (default) |

---

## Factory Pattern Implementation

### Classic Factory Pattern

```
Client Code → Factory::build() → Strategy Selection → Concrete Builder
```

### Implementation Details

**Factory Method**: `build(CheckoutIntent $intent): array`

**Strategy Selection**: `detectServiceType($payable): string`

**Concrete Builders**:
- `buildDomainData()` - Domain registration strategy
- `buildHostingData()` - Hosting provisioning strategy
- `buildVpsData()` - VPS configuration strategy
- `buildSslData()` - SSL certificate strategy

### Why Factory Pattern?

1. **Encapsulation**: Service-specific logic hidden from client code
2. **Extensibility**: Easy to add new service types without modifying client code
3. **Single Responsibility**: Each builder method handles one service type
4. **Testability**: Each builder can be tested independently
5. **Maintainability**: Changes to one service type don't affect others

### Comparison with Traditional Factory

**Traditional Factory**:
```php
// Client code must know service type
$data = ServiceDataFactory::createDomainData($intent);
$data = ServiceDataFactory::createHostingData($intent);
```

**Current Implementation**:
```php
// Client code doesn't need to know service type
$data = $factory->build($intent);  // Auto-detects from payable
```

---

## Service Type Detection

### Detection Strategy

**4-Level Priority Cascade**:

```
Level 1: service_type property (explicit configuration)
    ↓ (not found)
Level 2: getServiceType() method (dynamic determination)
    ↓ (not found)
Level 3: Class name inference (convention-based)
    ↓ (not found)
Level 4: PayableType fallback (broad categorization)
```

### Level 1: Property-Based Detection

```php
// Payable model
class DomainPackage implements Payable
{
    public string $service_type = 'domain';  // ← Explicit
}

// Detection
if (property_exists($payable, 'service_type') && !empty($payable->service_type)) {
    return $payable->service_type;  // → 'domain'
}
```

**Pros**:
- ✅ Most explicit (no ambiguity)
- ✅ Fastest detection (direct property access)
- ✅ Easy to override per instance

**Cons**:
- ❌ Requires adding property to all payables
- ❌ Can become stale if not maintained

---

### Level 2: Method-Based Detection

```php
// Payable model
class DomainPackage implements Payable
{
    public function getServiceType(): string
    {
        // Dynamic determination based on attributes
        return $this->is_transfer ? 'domain_transfer' : 'domain';
    }
}

// Detection
if (method_exists($payable, 'getServiceType')) {
    return $payable->getServiceType();  // → 'domain' or 'domain_transfer'
}
```

**Pros**:
- ✅ Dynamic (can change based on runtime conditions)
- ✅ Flexible (complex logic allowed)
- ✅ Single source of truth

**Cons**:
- ❌ Slower (method call overhead)
- ❌ Requires implementing method in all payables

---

### Level 3: Class Name Inference

```php
$className = class_basename($payable);  // 'DomainPackage'

if (str_contains($className, 'Domain')) return 'domain';
if (str_contains($className, 'Hosting')) return 'hosting';
if (str_contains($className, 'Vps') || str_contains($className, 'VPS')) return 'vps';
if (str_contains($className, 'Ssl') || str_contains($className, 'SSL')) return 'ssl';
```

**Pros**:
- ✅ Convention-based (no extra code in payables)
- ✅ Works immediately for properly named classes
- ✅ Zero configuration

**Cons**:
- ❌ Fragile (class rename breaks detection)
- ❌ Ambiguous (what if class contains multiple keywords?)
- ❌ Not reliable for complex hierarchies

**Examples**:

| Class Name | Detection Result | Correct? |
|------------|------------------|----------|
| `DomainPackage` | `'domain'` | ✅ Yes |
| `HostingPackage` | `'hosting'` | ✅ Yes |
| `VpsPackage` | `'vps'` | ✅ Yes |
| `SslCertificate` | `'ssl'` | ✅ Yes |
| `DomainHostingBundle` | `'domain'` | ⚠️ Ambiguous (matches 'Domain' first) |
| `Package` | Fallback to Level 4 | ⚠️ No match |

---

### Level 4: PayableType Fallback

```php
return match ($payable->getPayableType()) {
    PayableType::INFRASTRUCTURE => 'domain',  // Default for infrastructure
    PayableType::DIGITAL_PRODUCT => 'digital',
    PayableType::SUBSCRIPTION => 'subscription',
    default => 'generic',
};
```

**Pros**:
- ✅ Always returns a value (safe fallback)
- ✅ Works for all payables (requires only Payable interface)
- ✅ Broad categorization

**Cons**:
- ❌ Least specific (all infrastructure → 'domain')
- ❌ May not match actual service type
- ❌ Requires updating match() for new PayableTypes

**Mapping**:

| PayableType | Service Type | Notes |
|-------------|-------------|-------|
| `INFRASTRUCTURE` | `'domain'` | Default assumption (may be wrong for VPS/hosting) |
| `DIGITAL_PRODUCT` | `'digital'` | E-books, software licenses |
| `SUBSCRIPTION` | `'subscription'` | Recurring services |
| Other | `'generic'` | Safe fallback |

---

### Why No ServiceType Enum?

**Decision**: No hardcoded `ServiceType` enum in the package.

**Reasons**:

1. **Flexibility**: Third-party packages can add custom service types without modifying core
2. **Extensibility**: New service types can be added via payable models
3. **Decoupling**: Payable entities control their own service type
4. **Open/Closed Principle**: Open for extension, closed for modification

**Alternative Approach** (if Enum was used):

```php
// ❌ Hardcoded Enum (inflexible)
enum ServiceType: string
{
    case DOMAIN = 'domain';
    case HOSTING = 'hosting';
    case VPS = 'vps';
    case SSL = 'ssl';
}

// Problem: Can't add 'email_hosting' or 'cdn' without modifying core package
```

**Current Approach** (string-based):

```php
// ✅ String-based (flexible)
// Third-party package can add:
class EmailHostingPackage implements Payable
{
    public string $service_type = 'email_hosting';  // ← Custom type!
}

// ServiceDataFactory can be extended:
class ExtendedServiceDataFactory extends ServiceDataFactory
{
    protected function build(CheckoutIntent $intent): array
    {
        $serviceType = $this->detectServiceType($intent->payable);

        return match ($serviceType) {
            'email_hosting' => $this->buildEmailHostingData($intent),  // ← Custom builder
            default => parent::build($intent),  // ← Fallback to core
        };
    }
}
```

---

## Data Builders

### Current Implementations

#### 1. buildDomainData() (Fully Implemented)

**Status**: ✅ Production-ready

**Target API**: ResellerClub Domain Registration

**Output Format**:
- WHOIS contact data (registrant, admin, tech, billing)
- Privacy protection settings
- Nameserver configuration
- Registration period

**Dependencies**:
- `formatPhoneForWhois()` - Phone number transformation
- `shouldEnablePrivacy()` - Privacy protection logic
- `getDefaultNameservers()` - Nameserver configuration
- `getDomainYears()` - Registration period

**Example Output**:
```php
[
    'registrant_contact' => [
        'name' => 'John Doe',
        'company' => 'Acme Corp',
        'email' => 'john@example.com',
        'address1' => '123 Main St',
        'city' => 'Tel Aviv',
        'country' => 'IL',
        'phone' => '+972.541234567',
    ],
    'admin_contact' => 'same_as_registrant',
    'privacy_protection' => true,
    'nameservers' => ['ns1.example.com', 'ns2.example.com'],
    'years' => 1,
]
```

---

#### 2. buildHostingData() (Placeholder)

**Status**: ⏳ TODO

**Target API**: cPanel/WHM API

**Planned Output**:
- cPanel username
- Primary domain
- Hosting plan
- Resource limits (disk, bandwidth)
- Email account limits
- SSL/backup features

**Required Implementation**:
```php
protected function buildHostingData(CheckoutIntent $intent): array
{
    return [
        'username' => $this->generateCpanelUsername($intent->customer),
        'domain' => $this->extractPrimaryDomain($intent->payable),
        'plan' => $this->getHostingPlan($intent->payable),
        'quota' => $this->getDiskQuota($intent->payable),
        'bandwidth' => $this->getBandwidthLimit($intent->payable),
        'email' => $intent->customer->email,
        'password' => Str::random(16),  // Auto-generated
        'features' => $this->getHostingFeatures($intent->payable),
    ];
}
```

---

#### 3. buildVpsData() (Placeholder)

**Status**: ⏳ TODO

**Target API**: VPS Provider API (DigitalOcean/Vultr/Linode)

**Planned Output**:
- Hostname
- Operating system
- CPU/RAM/Disk configuration
- Network settings (IPv4/IPv6)
- SSH keys

**Required Implementation**:
```php
protected function buildVpsData(CheckoutIntent $intent): array
{
    return [
        'hostname' => $this->generateHostname($intent->payable),
        'os' => $this->getOperatingSystem($intent->payable),
        'plan' => $this->getVpsPlan($intent->payable),
        'cpu_cores' => $this->getCpuCores($intent->payable),
        'ram_mb' => $this->getRamMb($intent->payable),
        'disk_gb' => $this->getDiskGb($intent->payable),
        'network' => [
            'ipv4' => 'auto',
            'ipv6' => $this->shouldEnableIpv6($intent),
        ],
        'ssh_key' => $this->getSshKey($intent->customer),
    ];
}
```

---

#### 4. buildSslData() (Placeholder)

**Status**: ⏳ TODO

**Target API**: SSL Certificate Provider (Let's Encrypt/Sectigo/DigiCert)

**Planned Output**:
- Domain name
- Certificate type (DV/OV/EV)
- CSR (Certificate Signing Request)
- Validation method (DNS/HTTP/Email)
- Admin contact

**Required Implementation**:
```php
protected function buildSslData(CheckoutIntent $intent): array
{
    return [
        'domain' => $this->extractDomain($intent->payable),
        'type' => $this->getCertificateType($intent->payable),  // DV/OV/EV
        'csr' => $this->generateCsr($intent->payable),
        'validation_method' => $this->getValidationMethod($intent->payable),  // dns/http/email
        'admin_email' => $intent->customer->email,
        'years' => $this->getCertificateYears($intent->payable),
    ];
}
```

---

## Dependencies

### Internal Dependencies

#### 1. CheckoutIntent DTO

**Location**: `src/DataTransferObjects/CheckoutIntent.php`

**Usage**: Main input parameter for `build()` method

**Structure**:
```php
class CheckoutIntent
{
    public function __construct(
        public readonly Payable $payable,              // Product/service being purchased
        public readonly CustomerData $customer,        // Customer information
        public readonly PaymentPreferences $preferences, // Payment settings
        public readonly ?AddressData $billingAddress,  // Billing address
        public readonly ?AddressData $shippingAddress, // Shipping address
    ) {}
}
```

**Accessed Fields**:
- `$intent->payable` → Service detection and years calculation
- `$intent->customer->name` → WHOIS registrant name
- `$intent->customer->email` → Contact email
- `$intent->customer->phone` → Phone number (formatted)
- `$intent->customer->company` → Company name (optional)
- `$intent->customer->address` → Address data (line1, city, country, etc.)

---

#### 2. PayableType Enum

**Location**: `src/Enums/PayableType.php`

**Usage**: Fallback detection in `detectServiceType()`

**Values**:
```php
enum PayableType: string
{
    case INFRASTRUCTURE = 'infrastructure';  // Domains, hosting, VPS
    case DIGITAL_PRODUCT = 'digital_product'; // E-books, software
    case SUBSCRIPTION = 'subscription';       // Recurring services
}
```

**Mapping**:
```php
PayableType::INFRASTRUCTURE → 'domain' (default)
PayableType::DIGITAL_PRODUCT → 'digital'
PayableType::SUBSCRIPTION → 'subscription'
```

---

#### 3. Payable Interface (Implicit)

**Location**: `src/Contracts/Payable.php`

**Usage**: Type hint for `$payable` parameter in detection and builder methods

**Expected Methods**:
```php
interface Payable
{
    public function getPayableType(): PayableType;  // Required by detectServiceType()
    // Optional methods:
    public function getServiceType(): ?string;       // Priority 2 detection
    public function getYears(): ?int;                // Used by getDomainYears()
}
```

---

### External Dependencies

#### 1. Laravel Configuration System

**Usage**: `config()` helper for retrieving settings

**Configuration Keys Used**:
- `officeguy.domain_privacy_protection` → WHOIS privacy setting
- `officeguy.default_nameservers` → Nameserver list

**Example**:
```php
config('officeguy.domain_privacy_protection', true);  // Default: true
config('officeguy.default_nameservers', ['ns1.example.com', 'ns2.example.com']);
```

---

#### 2. PHP Standard Library

**Functions Used**:
- `class_basename()` → Extract class name without namespace
- `str_contains()` → String pattern matching (PHP 8.0+)
- `str_starts_with()` → String prefix check (PHP 8.0+)
- `substr()` → String slicing
- `preg_replace()` → Regex replacement
- `property_exists()` → Check if property exists
- `method_exists()` → Check if method exists

---

## Integration Points

### 1. PrepareCheckoutIntentAction

**Location**: `src/Actions/PrepareCheckoutIntentAction.php`

**Integration**:
```php
class PrepareCheckoutIntentAction
{
    public function __construct(
        private ServiceDataFactory $serviceDataFactory,  // ← Injected
    ) {}

    public function execute(array $data): CheckoutIntent
    {
        $intent = new CheckoutIntent(...);

        // Build service-specific data
        $serviceData = $this->serviceDataFactory->build($intent);  // ← Called here

        // Store service data separately (not in CheckoutIntent)
        session(['checkout_service_data' => $serviceData]);

        return $intent;
    }
}
```

**Data Flow**:
```
PrepareCheckoutIntentAction
    ↓ Creates CheckoutIntent
    ↓ Calls ServiceDataFactory::build($intent)
    ↓ Stores service data in session
    ↓ Returns CheckoutIntent (without service data)
```

---

### 2. CheckoutController

**Location**: `src/Http/Controllers/CheckoutController.php`

**Integration**:
```php
class CheckoutController extends Controller
{
    public function process(CheckoutRequest $request)
    {
        $intent = app(PrepareCheckoutIntentAction::class)->execute($request->validated());

        // Retrieve service data from session
        $serviceData = session('checkout_service_data', []);  // ← Retrieved here

        // Pass to fulfillment handler
        app(FulfillmentDispatcher::class)->dispatch($intent, $serviceData);
    }
}
```

**Data Flow**:
```
CheckoutController
    ↓ Validates request
    ↓ Calls PrepareCheckoutIntentAction
    ↓ Retrieves service data from session
    ↓ Dispatches to FulfillmentDispatcher
```

---

### 3. FulfillmentDispatcher

**Location**: `src/Services/FulfillmentDispatcher.php`

**Integration**:
```php
class FulfillmentDispatcher
{
    public function dispatch(CheckoutIntent $intent, array $serviceData): void
    {
        $handler = $this->resolveHandler($intent->payable);

        // Pass service data to handler
        $handler->fulfill($intent, $serviceData);  // ← Service data used here
    }
}
```

**Data Flow**:
```
FulfillmentDispatcher
    ↓ Resolves fulfillment handler (InfrastructureFulfillmentHandler)
    ↓ Passes CheckoutIntent + service data
    ↓ Handler uses service data for API calls (ResellerClub, cPanel, etc.)
```

---

### 4. InfrastructureFulfillmentHandler

**Location**: `src/Handlers/InfrastructureFulfillmentHandler.php`

**Integration**:
```php
class InfrastructureFulfillmentHandler
{
    public function fulfill(CheckoutIntent $intent, array $serviceData): void
    {
        // Use service data for domain registration
        $response = $this->resellerClubApi->registerDomain([
            'domain' => $intent->payable->domain_name,
            'contacts' => $serviceData['registrant_contact'],  // ← From ServiceDataFactory
            'nameservers' => $serviceData['nameservers'],     // ← From ServiceDataFactory
            'privacy' => $serviceData['privacy_protection'],  // ← From ServiceDataFactory
            'years' => $serviceData['years'],                 // ← From ServiceDataFactory
        ]);
    }
}
```

**Data Flow**:
```
InfrastructureFulfillmentHandler
    ↓ Receives service data from dispatcher
    ↓ Uses service data for ResellerClub API call
    ↓ Handles API response (success/failure)
```

---

## Configuration

### Required Settings

Add to `config/officeguy.php`:

```php
return [
    // ... existing settings ...

    /*
    |--------------------------------------------------------------------------
    | Domain Registration Settings
    |--------------------------------------------------------------------------
    */

    // Enable WHOIS privacy protection by default
    'domain_privacy_protection' => env('OFFICEGUY_DOMAIN_PRIVACY', true),

    // Default nameservers for domain registration
    'default_nameservers' => [
        env('OFFICEGUY_NS1', 'ns1.example.com'),
        env('OFFICEGUY_NS2', 'ns2.example.com'),
    ],
];
```

---

### Environment Variables

Add to `.env`:

```bash
# Domain Registration
OFFICEGUY_DOMAIN_PRIVACY=true
OFFICEGUY_NS1=ns1.yourdomain.com
OFFICEGUY_NS2=ns2.yourdomain.com
```

---

### Admin Settings Page

Add to `src/Filament/Pages/OfficeGuySettings.php`:

```php
Schemas\Section::make('Domain Registration')
    ->schema([
        Forms\Components\Toggle::make('domain_privacy_protection')
            ->label('Enable WHOIS Privacy Protection')
            ->helperText('Protect customer personal information in WHOIS database')
            ->default(true),

        Forms\Components\Repeater::make('default_nameservers')
            ->label('Default Nameservers')
            ->schema([
                Forms\Components\TextInput::make('nameserver')
                    ->label('Nameserver')
                    ->required()
                    ->placeholder('ns1.example.com'),
            ])
            ->defaultItems(2)
            ->minItems(2)
            ->maxItems(4),
    ])
    ->collapsible(),
```

---

## Best Practices

### 1. Always Use Factory Pattern

✅ **DO**:
```php
$factory = app(ServiceDataFactory::class);
$serviceData = $factory->build($intent);
```

❌ **DON'T**:
```php
// Don't call builder methods directly
$serviceData = (new ServiceDataFactory())->buildDomainData($intent);
```

**Reason**: `build()` handles service type detection automatically.

---

### 2. Store Service Data Separately

✅ **DO**:
```php
$intent = new CheckoutIntent(...);
$serviceData = $factory->build($intent);

// Store separately
session(['checkout_service_data' => $serviceData]);
```

❌ **DON'T**:
```php
// Don't embed in CheckoutIntent
$intent->serviceData = $serviceData;  // CheckoutIntent is immutable!
```

**Reason**: CheckoutIntent is readonly and should not contain service-specific data.

---

### 3. Handle Empty Results Gracefully

✅ **DO**:
```php
$serviceData = $factory->build($intent);

if (empty($serviceData)) {
    // No service-specific data needed (e.g., digital products)
    return $this->processWithoutServiceData($intent);
}

return $this->processWithServiceData($intent, $serviceData);
```

❌ **DON'T**:
```php
// Don't assume service data always exists
$serviceData['registrant_contact'];  // May throw error if empty!
```

**Reason**: Not all payables require service-specific data (e.g., digital products).

---

### 4. Implement Service Type Detection

✅ **DO**:
```php
// In your Payable model
class DomainPackage implements Payable
{
    public string $service_type = 'domain';  // ← Explicit property

    // OR dynamic method
    public function getServiceType(): string
    {
        return $this->is_transfer ? 'domain_transfer' : 'domain';
    }
}
```

❌ **DON'T**:
```php
// Don't rely on class name inference only
class Package implements Payable  // ← Ambiguous name
{
    // No service_type property or getServiceType() method
}
```

**Reason**: Class name inference is fragile (fallback only).

---

### 5. Validate Service Data Before API Calls

✅ **DO**:
```php
$serviceData = $factory->build($intent);

// Validate before external API call
if (empty($serviceData['registrant_contact']['email'])) {
    throw new ValidationException('Registrant email is required');
}

$this->resellerClubApi->registerDomain($serviceData);
```

❌ **DON'T**:
```php
// Don't assume service data is valid
$this->resellerClubApi->registerDomain($serviceData);  // May fail with cryptic API error
```

**Reason**: External APIs have strict validation rules.

---

### 6. Extend Factory for Custom Service Types

✅ **DO**:
```php
// In your application
class ExtendedServiceDataFactory extends ServiceDataFactory
{
    protected function build(CheckoutIntent $intent): array
    {
        $serviceType = $this->detectServiceType($intent->payable);

        return match ($serviceType) {
            'email_hosting' => $this->buildEmailHostingData($intent),
            'cdn' => $this->buildCdnData($intent),
            default => parent::build($intent),  // ← Fallback to core
        };
    }

    protected function buildEmailHostingData(CheckoutIntent $intent): array
    {
        // Custom implementation
        return [
            'mailboxes' => 10,
            'storage_gb' => 50,
            'domain' => $intent->payable->domain,
        ];
    }
}
```

❌ **DON'T**:
```php
// Don't modify core ServiceDataFactory directly
// (violates Open/Closed Principle)
```

**Reason**: Extensibility without modifying core package.

---

## Usage Examples

### Example 1: Domain Registration

```php
use OfficeGuy\LaravelSumitGateway\Services\ServiceDataFactory;
use OfficeGuy\LaravelSumitGateway\DataTransferObjects\CheckoutIntent;
use OfficeGuy\LaravelSumitGateway\DataTransferObjects\CustomerData;
use OfficeGuy\LaravelSumitGateway\DataTransferObjects\AddressData;

// Create customer data
$customer = new CustomerData(
    name: 'John Doe',
    email: 'john@example.com',
    phone: '0541234567',
    company: 'Acme Corp',
    address: new AddressData(
        line1: '123 Main St',
        city: 'Tel Aviv',
        state: 'Tel Aviv District',
        country: 'IL',
        postalCode: '6801234',
    ),
);

// Create payable (domain package)
$domainPackage = DomainPackage::find(1);
$domainPackage->service_type = 'domain';  // Explicit service type
$domainPackage->years = 2;                // 2-year registration

// Create checkout intent
$intent = new CheckoutIntent(
    payable: $domainPackage,
    customer: $customer,
    preferences: new PaymentPreferences(...),
    billingAddress: null,
    shippingAddress: null,
);

// Build service data
$factory = app(ServiceDataFactory::class);
$serviceData = $factory->build($intent);

// Result:
/*
[
    'registrant_contact' => [
        'name' => 'John Doe',
        'company' => 'Acme Corp',
        'email' => 'john@example.com',
        'address1' => '123 Main St',
        'address2' => '',
        'city' => 'Tel Aviv',
        'state' => 'Tel Aviv District',
        'country' => 'IL',
        'zipcode' => '6801234',
        'phone' => '+972.541234567',
    ],
    'admin_contact' => 'same_as_registrant',
    'tech_contact' => 'same_as_registrant',
    'billing_contact' => 'same_as_registrant',
    'privacy_protection' => true,
    'nameservers' => [
        'ns1.example.com',
        'ns2.example.com',
    ],
    'years' => 2,
]
*/

// Use for ResellerClub API call
$response = $resellerClubApi->registerDomain([
    'domain' => $domainPackage->domain_name,
    'contacts' => $serviceData['registrant_contact'],
    'nameservers' => $serviceData['nameservers'],
    'privacy' => $serviceData['privacy_protection'],
    'years' => $serviceData['years'],
]);
```

---

### Example 2: Service Type Detection

```php
// Example 1: Property-based detection (Priority 1)
class DomainPackage implements Payable
{
    public string $service_type = 'domain';  // ← Explicit
}

$factory->detectServiceType($domainPackage);  // → 'domain'

// Example 2: Method-based detection (Priority 2)
class DomainPackage implements Payable
{
    public function getServiceType(): string
    {
        return $this->is_transfer ? 'domain_transfer' : 'domain';
    }
}

$factory->detectServiceType($domainPackage);  // → 'domain' or 'domain_transfer'

// Example 3: Class name inference (Priority 3)
class HostingPackage implements Payable
{
    // No service_type property or getServiceType() method
}

$factory->detectServiceType($hostingPackage);  // → 'hosting' (inferred from class name)

// Example 4: PayableType fallback (Priority 4)
class GenericPackage implements Payable
{
    public function getPayableType(): PayableType
    {
        return PayableType::INFRASTRUCTURE;
    }
}

$factory->detectServiceType($genericPackage);  // → 'domain' (fallback)
```

---

### Example 3: Phone Number Formatting

```php
$factory = new ServiceDataFactory();

// Israeli phone numbers
$factory->formatPhoneForWhois('0541234567');        // → '+972.541234567'
$factory->formatPhoneForWhois('054-123-4567');      // → '+972.541234567'
$factory->formatPhoneForWhois('+972-54-123-4567');  // → '+972.541234567'
$factory->formatPhoneForWhois('972541234567');      // → '+972.541234567'

// Output format: +972.XXXXXXXXX (ResellerClub requirement)
```

---

### Example 4: Empty Results (Digital Product)

```php
// Digital product (no service-specific data needed)
$digitalProduct = DigitalProduct::find(1);
$digitalProduct->service_type = 'digital';  // Not 'domain', 'hosting', etc.

$intent = new CheckoutIntent(
    payable: $digitalProduct,
    customer: $customer,
    preferences: $preferences,
);

$serviceData = $factory->build($intent);  // → [] (empty array)

// Handle gracefully
if (empty($serviceData)) {
    // No provisioning needed, just fulfill download links
    $this->fulfillDigitalProduct($intent);
}
```

---

### Example 5: Custom Service Type Extension

```php
// Step 1: Create custom payable with custom service type
class EmailHostingPackage implements Payable
{
    public string $service_type = 'email_hosting';  // Custom type
}

// Step 2: Extend ServiceDataFactory
class ExtendedServiceDataFactory extends ServiceDataFactory
{
    protected function build(CheckoutIntent $intent): array
    {
        $serviceType = $this->detectServiceType($intent->payable);

        return match ($serviceType) {
            'email_hosting' => $this->buildEmailHostingData($intent),
            default => parent::build($intent),  // Fallback to core
        };
    }

    protected function buildEmailHostingData(CheckoutIntent $intent): array
    {
        return [
            'domain' => $intent->payable->domain,
            'mailboxes' => $intent->payable->mailbox_count,
            'storage_gb' => $intent->payable->storage_limit,
            'admin_email' => $intent->customer->email,
        ];
    }
}

// Step 3: Register in service container (AppServiceProvider)
$this->app->bind(ServiceDataFactory::class, ExtendedServiceDataFactory::class);

// Step 4: Use as normal
$factory = app(ServiceDataFactory::class);  // ← Resolves to ExtendedServiceDataFactory
$serviceData = $factory->build($intent);    // → Uses custom builder
```

---

## Future Enhancements

### 1. Hosting Provisioning (buildHostingData)

**Status**: ⏳ TODO

**Implementation Plan**:

```php
protected function buildHostingData(CheckoutIntent $intent): array
{
    return [
        'username' => $this->generateCpanelUsername($intent->customer),
        'domain' => $this->extractPrimaryDomain($intent->payable),
        'plan' => $this->getHostingPlan($intent->payable),
        'quota' => $this->getDiskQuota($intent->payable),
        'bandwidth' => $this->getBandwidthLimit($intent->payable),
        'email' => $intent->customer->email,
        'password' => Str::random(16),
        'features' => [
            'ssl' => true,
            'backup' => $this->hasBackupFeature($intent->payable),
            'email_accounts' => $this->getEmailAccountLimit($intent->payable),
        ],
    ];
}
```

**Required Helper Methods**:
- `generateCpanelUsername()` - Generate unique cPanel username
- `extractPrimaryDomain()` - Get primary domain from payable
- `getHostingPlan()` - Get hosting plan identifier
- `getDiskQuota()` - Get disk quota in MB
- `getBandwidthLimit()` - Get bandwidth limit in MB
- `hasBackupFeature()` - Check if backup is included
- `getEmailAccountLimit()` - Get email account limit

**Configuration**:
```php
// config/officeguy.php
'hosting' => [
    'default_plan' => 'basic',
    'username_prefix' => 'user_',
    'default_quota_mb' => 10240,  // 10 GB
    'default_bandwidth_mb' => 102400,  // 100 GB
],
```

---

### 2. VPS Provisioning (buildVpsData)

**Status**: ⏳ TODO

**Implementation Plan**:

```php
protected function buildVpsData(CheckoutIntent $intent): array
{
    return [
        'hostname' => $this->generateHostname($intent->payable),
        'os' => $this->getOperatingSystem($intent->payable),
        'plan' => $this->getVpsPlan($intent->payable),
        'cpu_cores' => $this->getCpuCores($intent->payable),
        'ram_mb' => $this->getRamMb($intent->payable),
        'disk_gb' => $this->getDiskGb($intent->payable),
        'network' => [
            'ipv4' => 'auto',
            'ipv6' => $this->shouldEnableIpv6($intent),
        ],
        'ssh_key' => $this->getSshKey($intent->customer),
        'backup' => $this->hasVpsBackup($intent->payable),
    ];
}
```

**Required Helper Methods**:
- `generateHostname()` - Generate unique hostname
- `getOperatingSystem()` - Get OS selection (Ubuntu, CentOS, etc.)
- `getVpsPlan()` - Get VPS plan identifier
- `getCpuCores()` - Get CPU core count
- `getRamMb()` - Get RAM in MB
- `getDiskGb()` - Get disk space in GB
- `shouldEnableIpv6()` - Check if IPv6 should be enabled
- `getSshKey()` - Get customer SSH public key
- `hasVpsBackup()` - Check if backup is included

**Configuration**:
```php
// config/officeguy.php
'vps' => [
    'hostname_prefix' => 'vps-',
    'default_os' => 'ubuntu-22.04',
    'enable_ipv6' => true,
],
```

---

### 3. SSL Certificate Provisioning (buildSslData)

**Status**: ⏳ TODO

**Implementation Plan**:

```php
protected function buildSslData(CheckoutIntent $intent): array
{
    return [
        'domain' => $this->extractDomain($intent->payable),
        'type' => $this->getCertificateType($intent->payable),  // DV/OV/EV
        'csr' => $this->generateCsr($intent->payable),
        'validation_method' => $this->getValidationMethod($intent->payable),  // dns/http/email
        'admin_email' => $intent->customer->email,
        'organization' => $intent->customer->company ?? '',
        'years' => $this->getCertificateYears($intent->payable),
    ];
}
```

**Required Helper Methods**:
- `extractDomain()` - Get domain from payable
- `getCertificateType()` - Get cert type (DV/OV/EV)
- `generateCsr()` - Generate Certificate Signing Request
- `getValidationMethod()` - Get validation method (DNS/HTTP/Email)
- `getCertificateYears()` - Get certificate validity period

**Configuration**:
```php
// config/officeguy.php
'ssl' => [
    'default_type' => 'DV',  // Domain Validated
    'default_validation' => 'dns',
    'auto_renew' => true,
],
```

---

### 4. Enhanced Privacy Detection

**Current**:
```php
protected function shouldEnablePrivacy(CheckoutIntent $intent): bool
{
    return (bool) config('officeguy.domain_privacy_protection', true);
}
```

**Enhanced**:
```php
protected function shouldEnablePrivacy(CheckoutIntent $intent): bool
{
    // Check if TLD supports privacy
    $tld = $this->extractTld($intent->payable->domain_name);
    if (!$this->tldSupportsPrivacy($tld)) {
        return false;  // Some TLDs (.gov, .edu) don't support privacy
    }

    // Check customer preference
    if ($intent->preferences->privacy_protection !== null) {
        return $intent->preferences->privacy_protection;
    }

    // Check per-customer setting
    if ($intent->customer->default_privacy_protection !== null) {
        return $intent->customer->default_privacy_protection;
    }

    // Fallback to global config
    return (bool) config('officeguy.domain_privacy_protection', true);
}
```

---

### 5. Dynamic Nameserver Selection

**Current**:
```php
protected function getDefaultNameservers(): array
{
    return config('officeguy.default_nameservers', [
        'ns1.example.com',
        'ns2.example.com',
    ]);
}
```

**Enhanced**:
```php
protected function getNameservers(CheckoutIntent $intent): array
{
    // Check if payable has custom nameservers
    if (method_exists($intent->payable, 'getNameservers')) {
        $nameservers = $intent->payable->getNameservers();
        if (!empty($nameservers)) {
            return $nameservers;
        }
    }

    // Check if hosting package is bundled (use hosting nameservers)
    if ($this->hasHostingBundle($intent->payable)) {
        return $this->getHostingNameservers($intent->payable);
    }

    // Fallback to default nameservers
    return config('officeguy.default_nameservers', [
        'ns1.example.com',
        'ns2.example.com',
    ]);
}
```

---

### 6. Validation Layer

**Purpose**: Validate service data before returning from `build()`

**Implementation**:
```php
public function build(CheckoutIntent $intent): array
{
    $serviceType = $this->detectServiceType($intent->payable);

    $serviceData = match ($serviceType) {
        'domain' => $this->buildDomainData($intent),
        'hosting' => $this->buildHostingData($intent),
        'vps' => $this->buildVpsData($intent),
        'ssl' => $this->buildSslData($intent),
        default => [],
    };

    // Validate service data before returning
    $this->validateServiceData($serviceType, $serviceData);

    return $serviceData;
}

protected function validateServiceData(string $serviceType, array $data): void
{
    match ($serviceType) {
        'domain' => $this->validateDomainData($data),
        'hosting' => $this->validateHostingData($data),
        'vps' => $this->validateVpsData($data),
        'ssl' => $this->validateSslData($data),
        default => null,
    };
}

protected function validateDomainData(array $data): void
{
    if (empty($data['registrant_contact']['email'])) {
        throw new ValidationException('Registrant email is required');
    }

    if (empty($data['registrant_contact']['phone'])) {
        throw new ValidationException('Registrant phone is required');
    }

    if (count($data['nameservers']) < 2) {
        throw new ValidationException('At least 2 nameservers are required');
    }

    // ... more validations
}
```

---

## Summary

### Key Takeaways

1. **Purpose**: ServiceDataFactory transforms checkout intentions into service-specific data for external APIs
2. **Pattern**: Factory Pattern with Strategy Pattern elements (match statement)
3. **No Enum**: Service type detection is dynamic (no hardcoded ServiceType enum)
4. **Extensibility**: Easy to add new service types via extension
5. **Separation**: Service data stored separately (not in CheckoutIntent)
6. **Production-Ready**: `buildDomainData()` fully implemented, others are placeholders

---

### Current Status

| Builder Method | Status | Target API |
|----------------|--------|------------|
| `buildDomainData()` | ✅ Implemented | ResellerClub |
| `buildHostingData()` | ⏳ TODO | cPanel/WHM |
| `buildVpsData()` | ⏳ TODO | VPS Provider |
| `buildSslData()` | ⏳ TODO | SSL Provider |

---

### Integration Flow

```
PrepareCheckoutIntentAction
    ↓ Creates CheckoutIntent
    ↓ Calls ServiceDataFactory::build($intent)
    ↓ Stores service data in session
    ↓
CheckoutController
    ↓ Retrieves service data from session
    ↓
FulfillmentDispatcher
    ↓ Passes service data to handler
    ↓
InfrastructureFulfillmentHandler
    ↓ Uses service data for ResellerClub API call
```

---

### Dependencies

**Internal**:
- CheckoutIntent DTO (main input)
- PayableType Enum (fallback detection)
- Payable Interface (type hints)

**External**:
- Laravel Config System (`config()` helper)
- PHP 8.1+ (readonly properties, match expression)

---

### Configuration Keys

- `officeguy.domain_privacy_protection` - Enable WHOIS privacy (default: true)
- `officeguy.default_nameservers` - Default nameserver list

---

### Best Practices

1. ✅ Always use `build()` method (don't call builders directly)
2. ✅ Store service data separately (not in CheckoutIntent)
3. ✅ Handle empty results gracefully (not all payables need service data)
4. ✅ Implement service type detection (property > method > class name > fallback)
5. ✅ Validate service data before external API calls
6. ✅ Extend factory for custom service types (don't modify core)

---

### Future Work

1. ⏳ Implement `buildHostingData()` (cPanel provisioning)
2. ⏳ Implement `buildVpsData()` (VPS configuration)
3. ⏳ Implement `buildSslData()` (SSL certificate requests)
4. ⏳ Add validation layer for service data
5. ⏳ Enhance privacy detection (TLD support, per-customer preferences)
6. ⏳ Dynamic nameserver selection (hosting bundles, custom NS)

---

**Document Version**: 1.0
**Package Version**: v1.2.0+
**Last Updated**: 2026-01-13
**Maintained By**: NM-DigitalHub
