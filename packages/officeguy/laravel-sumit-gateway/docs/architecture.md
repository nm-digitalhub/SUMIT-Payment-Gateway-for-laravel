# Architecture Documentation

This document describes the architecture of the Laravel SUMIT Gateway package.

## High-Level Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                      Laravel Application                         │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │                    Your Order Model                        │  │
│  │              (Implements Payable Contract)                 │  │
│  └───────────────────────────────────────────────────────────┘  │
│                              │                                   │
│                              ▼                                   │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │            SUMIT Gateway Package                          │  │
│  │                                                            │  │
│  │  ┌─────────────┐  ┌──────────────┐  ┌────────────────┐  │  │
│  │  │  Services   │  │ Controllers  │  │    Models      │  │  │
│  │  ├─────────────┤  ├──────────────┤  ├────────────────┤  │  │
│  │  │ Payment     │  │ CardCallback │  │ Transaction    │  │  │
│  │  │ Token       │  │ BitWebhook   │  │ Token          │  │  │
│  │  │ Bit         │  └──────────────┘  │ Document       │  │  │
│  │  │ Document    │                    └────────────────┘  │  │
│  │  │ OfficeGuyApi│                                        │  │
│  │  └─────────────┘                                        │  │
│  └───────────────────────────────────────────────────────────┘  │
│                              │                                   │
│                              ▼                                   │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │                    Database                                │  │
│  │  ┌──────────────┐  ┌─────────────┐  ┌─────────────────┐  │  │
│  │  │officeguy_    │  │officeguy_   │  │officeguy_       │  │  │
│  │  │transactions  │  │tokens       │  │documents        │  │  │
│  │  └──────────────┘  └─────────────┘  └─────────────────┘  │  │
│  └───────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
                    ┌──────────────────┐
                    │   SUMIT API      │
                    │  (api.sumit.co.il)│
                    └──────────────────┘
```

## Component Architecture

### 1. Contracts Layer

**Purpose**: Define interfaces for loose coupling

**Components**:
- `Payable` - Interface for any billable entity (orders, invoices, etc.)

**Why**: Allows the package to work with any order/invoice implementation, not just a specific one.

### 2. Services Layer

**Purpose**: Business logic and API communication

**Components**:

#### OfficeGuyApi
- Handles all HTTP communication with SUMIT API
- Manages URL building for different environments
- Handles request/response logging
- Error handling and retries

#### PaymentService
- Payment calculation and validation
- Order items formatting
- Customer data formatting
- Currency and VAT handling
- Maximum installments logic

#### TokenService
- Credit card tokenization
- Token storage and retrieval
- Payment method building from tokens

#### BitPaymentService
- Bit payment processing
- Redirect URL management
- Webhook verification and processing

#### DocumentService
- Invoice/receipt creation
- Document email sending
- Third-party payment document handling

**Design Pattern**: Service-oriented architecture with static methods for easy access

### 3. Models Layer

**Purpose**: Data persistence and retrieval

**Components**:

#### OfficeGuyTransaction
- Stores payment transaction details
- Tracks payment status lifecycle
- Polymorphic relationship to orders
- Helper methods for status updates

#### OfficeGuyToken
- Stores tokenized credit cards
- Polymorphic owner relationship (User, Customer, etc.)
- Default token management
- Expiration checking

#### OfficeGuyDocument
- Stores invoice and receipt information
- Links to transactions and orders
- Document type identification

**Design Pattern**: Eloquent ORM with soft deletes and polymorphic relationships

### 4. HTTP Layer

**Purpose**: Handle incoming requests from SUMIT

**Components**:

#### CardCallbackController
- Processes redirect returns from card payments
- Retrieves payment status from SUMIT
- Updates transaction records
- Redirects user to success/failure pages

#### BitWebhookController
- Receives server-to-server notifications
- Validates webhook authenticity
- Updates payment status
- Returns appropriate HTTP responses

### 5. Support Layer

**Purpose**: Utility classes and helpers

**Components**:

#### RequestHelpers
- Request data access utilities
- POST/GET parameter retrieval

#### Enums
- `PaymentStatus` - Transaction status values
- `Environment` - SUMIT environment modes
- `PciMode` - PCI compliance levels

## Data Flow

### Card Payment Flow (Redirect Mode)

```
1. User initiates payment
   └─> Application creates order (implements Payable)
       └─> PaymentService.getPaymentOrderItems(order)
           └─> BitPaymentService.processOrder() OR
               Build payment request manually
               └─> OfficeGuyApi.post('/billing/payments/beginredirect/')
                   └─> SUMIT returns redirect URL
                       └─> User redirected to SUMIT
                           └─> User completes payment
                               └─> SUMIT redirects back
                                   └─> CardCallbackController.handle()
                                       └─> OfficeGuyApi.post('/billing/payments/get/')
                                           └─> Update OfficeGuyTransaction
                                               └─> Redirect to success/failure
```

### Card Payment Flow (Simple/PCI Mode)

```
1. User enters card details
   └─> Frontend tokenizes card (PaymentsJS)
       └─> POST single-use token to backend
           └─> TokenService.getPaymentMethodPCI() OR use token
               └─> OfficeGuyApi.post('/billing/payments/charge/')
                   └─> SUMIT processes immediately
                       └─> Create OfficeGuyTransaction
                           └─> Create OfficeGuyToken (if requested)
                               └─> Create OfficeGuyDocument (if configured)
                                   └─> Return success/failure
```

### Bit Payment Flow

```
1. User selects Bit payment
   └─> BitPaymentService.processOrder()
       └─> OfficeGuyApi.post('/billing/payments/beginredirect/')
           └─> SUMIT returns redirect URL
               └─> User redirected to Bit app
                   └─> User approves payment
                       └─> SUMIT calls webhook
                           └─> BitWebhookController.handle()
                               └─> BitPaymentService.processWebhook()
                                   └─> Update OfficeGuyTransaction
                                       └─> Application notified (event)
```

### Token Creation Flow

```
1. User saves payment method
   └─> TokenService.processToken(user)
       └─> TokenService.getTokenRequest()
           └─> OfficeGuyApi.post('/creditguy/gateway/transaction/')
               └─> SUMIT returns token
                   └─> TokenService.getTokenFromResponse()
                       └─> Create OfficeGuyToken
                           └─> Link to user (polymorphic)
                               └─> Set as default (optional)
```

### Document Creation Flow

```
1. Payment completed
   └─> DocumentService.createOrderDocument(order)
       └─> PaymentService.getDocumentOrderItems(order)
           └─> OfficeGuyApi.post('/accounting/documents/create/')
               └─> SUMIT creates invoice/receipt
                   └─> Create OfficeGuyDocument
                       └─> Email document (if configured)
```

## Database Schema

### officeguy_transactions

Stores all payment transaction attempts and results.

**Key Columns**:
- `order_id`, `order_type` - Polymorphic relation to order
- `payment_id` - SUMIT payment identifier
- `document_id` - Related invoice/receipt
- `status` - Transaction status (pending, completed, failed, refunded)
- `payment_method` - card or bit
- `raw_request`, `raw_response` - Full API payload for debugging

**Indexes**:
- `order_id` - Fast order lookup
- `payment_id` - SUMIT reference lookup
- `status` - Status filtering

### officeguy_tokens

Stores tokenized credit cards for recurring payments.

**Key Columns**:
- `owner_type`, `owner_id` - Polymorphic relation to owner (User, Customer)
- `token` - SUMIT card token (unique)
- `last_four` - Last 4 digits for display
- `is_default` - Default payment method flag
- `expiry_month`, `expiry_year` - Card expiration

**Indexes**:
- `token` - Unique constraint
- `owner_type`, `owner_id`, `is_default` - Default token lookup

### officeguy_documents

Stores created invoices and receipts.

**Key Columns**:
- `document_id` - SUMIT document identifier (unique)
- `order_id`, `order_type` - Polymorphic relation to order
- `document_type` - 1=invoice, 8=order, DonationReceipt
- `is_draft` - Draft document flag
- `emailed` - Document sent flag

**Indexes**:
- `document_id` - Unique constraint
- `order_id` - Order lookup

## Configuration Design

The package uses Laravel's configuration system with environment variable support.

**Hierarchy**:
1. Environment variables (`.env`)
2. Configuration file (`config/officeguy.php`)
3. Default values

**Benefits**:
- Easy environment-specific configuration
- Version-controlled defaults
- Runtime overrides possible

## Security Considerations

### 1. PCI Compliance

- **Simple Mode (Recommended)**: Card data never touches server (PaymentsJS)
- **Redirect Mode**: No card data on server
- **Advanced Mode**: Server handles card data (requires PCI compliance)

### 2. Webhook Security

- Order key verification for Bit webhooks
- Transaction status checking (no duplicate processing)
- Comprehensive logging for audit trails

### 3. Token Security

- Tokens stored with user association
- Polymorphic ownership prevents token stealing
- Soft deletes for audit trail

### 4. API Credentials

- Stored in environment variables
- Never logged or exposed in responses
- Separate public/private keys

## Extension Points

### 1. Custom Order Models

Implement `Payable` contract on any model:
```php
class Invoice implements Payable { /* ... */ }
class Subscription implements Payable { /* ... */ }
```

### 2. Custom Events

Package can dispatch Laravel events:
```php
event(new PaymentCompleted($transaction));
event(new DocumentCreated($document));
```

### 3. Custom Middleware

Add custom middleware to routes:
```php
'routes' => [
    'middleware' => ['web', 'custom-middleware'],
],
```

### 4. Custom Logging

Configure custom log channel:
```php
'log_channel' => 'sumit-payments',
```

## Performance Considerations

### 1. Database Queries

- Indexed columns for fast lookups
- Polymorphic relations for flexibility
- Soft deletes for data retention

### 2. API Calls

- Timeout configuration (180 seconds default)
- Request/response logging (configurable)
- Single-use HTTP client

### 3. Caching

- Configuration cached by Laravel
- No query caching (real-time payment data)

## Testing Strategy

### 1. Unit Tests

- Service method logic
- Helper functions
- Data transformations

### 2. Integration Tests

- Database operations
- Model factories
- Service integration

### 3. Feature Tests

- HTTP endpoints
- Controller logic
- Full payment flows

### 4. Mock API Tests

- Mock SUMIT responses
- Test error handling
- Test edge cases

## Deployment Considerations

### 1. Environment Setup

- Set all required environment variables
- Configure logging channel
- Set up database

### 2. Migration Strategy

- Run migrations
- Publish configuration
- Publish views (if customizing)

### 3. Monitoring

- Monitor transaction success rates
- Track API response times
- Monitor webhook delivery

### 4. Backup

- Regular database backups
- Transaction log retention
- Document archival

## Future Enhancements

### Planned Features

1. **Subscription Service** - Recurring payment processing
2. **Stock Service** - Inventory synchronization
3. **Refund Service** - Payment refund processing
4. **Filament Resources** - Admin and client panel integration
5. **Events System** - Laravel event dispatching
6. **Artisan Commands** - CLI utilities
7. **Queue Jobs** - Async document creation
8. **Notifications** - Payment status notifications

### Possible Extensions

1. **Multi-currency pricing**
2. **Payment plan builder**
3. **Fraud detection hooks**
4. **Payment analytics dashboard**
5. **Custom document templates**
