# SUMIT API Endpoints Analysis

## Overview

This document provides a comprehensive analysis of the SUMIT API endpoints available in the OpenAPI specification (`sumit-openapi.json`) compared to the endpoints currently implemented in the Laravel package.

**Analysis Date:** November 2024  
**OpenAPI Version:** 3.0.4  
**Base URL:** `https://api.sumit.co.il`

---

## Summary Statistics

| Category | Total in API | Implemented | Not Implemented | Coverage |
|----------|-------------|-------------|-----------------|----------|
| **Billing (Payments)** | 13 | 6 | 7 | 46% |
| **Accounting (Documents)** | 10 | 1 | 9 | 10% |
| **Accounting (Customers)** | 4 | 0 | 4 | 0% |
| **Accounting (General)** | 6 | 0 | 6 | 0% |
| **Accounting (Income Items)** | 2 | 0 | 2 | 0% |
| **CreditGuy (Gateway/Vault)** | 8 | 3 | 5 | 38% |
| **Stock** | 1 | 1 | 0 | 100% |
| **Website (Companies/Users)** | 9 | 1 | 8 | 11% |
| **CRM** | 10 | 0 | 10 | 0% |
| **SMS** | 5 | 0 | 5 | 0% |
| **Email Subscriptions** | 2 | 0 | 2 | 0% |
| **Triggers (Webhooks)** | 2 | 0 | 2 | 0% |
| **Customer Service** | 1 | 0 | 1 | 0% |
| **Fax** | 1 | 0 | 1 | 0% |
| **Letter by Click** | 2 | 0 | 2 | 0% |
| **Scheduled Documents** | 1 | 0 | 1 | 0% |
| **TOTAL** | 77 | 12 | 65 | **16%** |

---

## Detailed Endpoint Analysis

### ✅ Implemented Endpoints

#### Billing - Payments

| Endpoint | Method | Implemented In | Description |
|----------|--------|----------------|-------------|
| `/billing/payments/charge/` | POST | `PaymentService::processCharge()` | Process card payment charge |
| `/billing/payments/beginredirect/` | POST | `PaymentService::processCharge()` (redirect mode), `BitPaymentService::processOrder()` | Begin redirect-based payment |
| `/billing/payments/multivendorcharge/` | POST | `MultiVendorPaymentService::processMultiVendorCharge()` | Multi-vendor payment splitting |

#### Billing - Recurring

| Endpoint | Method | Implemented In | Description |
|----------|--------|----------------|-------------|
| `/billing/recurring/charge/` | POST | `SubscriptionService::processRecurringCharge()`, `PaymentService::processCharge()` (recurring mode) | Process recurring subscription charge |

#### CreditGuy - Gateway & Vault

| Endpoint | Method | Implemented In | Description |
|----------|--------|----------------|-------------|
| `/creditguy/gateway/transaction/` | POST | `TokenService::processToken()` | Process gateway transaction for tokenization |
| `/creditguy/vault/tokenizesingleusejson/` | POST | `OfficeGuyApi::checkPublicCredentials()` | Tokenize single-use token (used for credential validation) |

#### Accounting - Documents

| Endpoint | Method | Implemented In | Description |
|----------|--------|----------------|-------------|
| `/accounting/documents/create/` | POST | `DocumentService::createOrderDocument()`, `DocumentService::createDocumentOnPaymentComplete()` | Create invoice/receipt document |

#### Stock

| Endpoint | Method | Implemented In | Description |
|----------|--------|----------------|-------------|
| `/stock/stock/list/` | POST | `StockService::sync()` | List stock items for synchronization |

#### Website - Companies

| Endpoint | Method | Implemented In | Description |
|----------|--------|----------------|-------------|
| `/website/companies/getdetails/` | POST | `OfficeGuyApi::checkCredentials()` | Get company details (used for credential validation) |

---

### ❌ Not Implemented Endpoints

#### 🔴 High Priority - Billing & Payments

| Endpoint | Method | Description | Suggested Priority |
|----------|--------|-------------|-------------------|
| `/billing/payments/get/` | POST | Get details of a specific payment | 🔴 High |
| `/billing/payments/list/` | POST | List payments with filters | 🔴 High |
| `/billing/recurring/cancel/` | POST | Cancel recurring payment | 🔴 High |
| `/billing/recurring/update/` | POST | Update recurring payment settings | 🔴 High |
| `/billing/recurring/listforcustomer/` | POST | List recurring payments for a customer | 🔴 High |
| `/billing/recurring/updatesettings/` | POST | Update recurring billing settings | 🟡 Medium |
| `/billing/paymentmethods/getforcustomer/` | POST | Get saved payment methods for customer | 🔴 High |
| `/billing/paymentmethods/setforcustomer/` | POST | Save payment method for customer | 🔴 High |
| `/billing/paymentmethods/remove/` | POST | Remove saved payment method | 🔴 High |
| `/billing/generalbilling/openupayterminal/` | POST | Open UPay terminal | 🟡 Medium |
| `/billing/generalbilling/setupaycredentials/` | POST | Setup UPay credentials | 🟡 Medium |

#### 🔴 High Priority - Accounting Documents

| Endpoint | Method | Description | Suggested Priority |
|----------|--------|-------------|-------------------|
| `/accounting/documents/getdetails/` | POST | Get document details | 🔴 High |
| `/accounting/documents/getpdf/` | POST | Download document as PDF | 🔴 High |
| `/accounting/documents/send/` | POST | Send document by email | 🔴 High |
| `/accounting/documents/cancel/` | POST | Cancel/void a document | 🔴 High |
| `/accounting/documents/list/` | POST | List documents with filters | 🔴 High |
| `/accounting/documents/getdebt/` | POST | Get debt for a customer/document | 🟡 Medium |
| `/accounting/documents/getdebtreport/` | POST | Get debt report | 🟡 Medium |
| `/accounting/documents/addexpense/` | POST | Add expense document | 🟡 Medium |
| `/accounting/documents/movetobooks/` | POST | Move document to accounting books | 🟢 Low |

#### 🟡 Medium Priority - Accounting Customers

| Endpoint | Method | Description | Suggested Priority |
|----------|--------|-------------|-------------------|
| `/accounting/customers/create/` | POST | Create a new customer in SUMIT | 🔴 High |
| `/accounting/customers/update/` | POST | Update customer details | 🔴 High |
| `/accounting/customers/getdetailsurl/` | POST | Get URL for customer details page | 🟡 Medium |
| `/accounting/customers/createremark/` | POST | Add a remark to customer profile | 🟢 Low |

#### 🟡 Medium Priority - Accounting General

| Endpoint | Method | Description | Suggested Priority |
|----------|--------|-------------|-------------------|
| `/accounting/general/getvatrate/` | POST | Get current VAT rate | 🟡 Medium |
| `/accounting/general/getexchangerate/` | POST | Get currency exchange rate | 🟡 Medium |
| `/accounting/general/getnextdocumentnumber/` | POST | Get next document number | 🟡 Medium |
| `/accounting/general/setnextdocumentnumber/` | POST | Set next document number | 🟢 Low |
| `/accounting/general/verifybankaccount/` | POST | Verify bank account details | 🟢 Low |
| `/accounting/general/updatesettings/` | POST | Update company settings | 🟢 Low |

#### 🟡 Medium Priority - Accounting Income Items

| Endpoint | Method | Description | Suggested Priority |
|----------|--------|-------------|-------------------|
| `/accounting/incomeitems/create/` | POST | Create an income item/product | 🟡 Medium |
| `/accounting/incomeitems/list/` | POST | List income items | 🟡 Medium |

#### 🟡 Medium Priority - CreditGuy Gateway

| Endpoint | Method | Description | Suggested Priority |
|----------|--------|-------------|-------------------|
| `/creditguy/gateway/beginredirect/` | POST | Begin redirect for payment | 🟡 Medium |
| `/creditguy/gateway/gettransaction/` | POST | Get transaction details | 🟡 Medium |
| `/creditguy/gateway/getreferencenumbers/` | POST | Get reference numbers | 🟢 Low |
| `/creditguy/billing/getstatus/` | POST | Get billing status | 🟡 Medium |
| `/creditguy/billing/process/` | POST | Process billing | 🟡 Medium |
| `/creditguy/billing/load/` | POST | Load billing data | 🟢 Low |
| `/creditguy/vault/tokenize/` | POST | Create permanent token | 🟡 Medium |
| `/creditguy/vault/tokenizesingleuse/` | POST | Create single-use token | 🟢 Low |

#### 🟢 Low Priority - Website & User Management

| Endpoint | Method | Description | Suggested Priority |
|----------|--------|-------------|-------------------|
| `/website/companies/create/` | POST | Create a new company account | 🟢 Low |
| `/website/companies/update/` | POST | Update company details | 🟢 Low |
| `/website/companies/installapplications/` | POST | Install applications on company | 🟢 Low |
| `/website/companies/listquotas/` | POST | List company quotas | 🟢 Low |
| `/website/users/create/` | POST | Create a new user | 🟢 Low |
| `/website/users/loginredirect/` | POST | Get login redirect URL | 🟢 Low |
| `/website/permissions/set/` | POST | Set user permissions | 🟢 Low |
| `/website/permissions/remove/` | POST | Remove user permissions | 🟢 Low |

#### 🟢 Low Priority - CRM

| Endpoint | Method | Description | Suggested Priority |
|----------|--------|-------------|-------------------|
| `/crm/data/createentity/` | POST | Create CRM entity | 🟢 Low |
| `/crm/data/getentity/` | POST | Get CRM entity details | 🟢 Low |
| `/crm/data/listentities/` | POST | List CRM entities | 🟢 Low |
| `/crm/data/updateentity/` | POST | Update CRM entity | 🟢 Low |
| `/crm/data/deleteentity/` | POST | Delete CRM entity | 🟢 Low |
| `/crm/data/archiveentity/` | POST | Archive CRM entity | 🟢 Low |
| `/crm/data/countentityusage/` | POST | Count entity usage | 🟢 Low |
| `/crm/data/getentitieshtml/` | POST | Get entities as HTML | 🟢 Low |
| `/crm/data/getentityprinthtml/` | POST | Get entity print HTML | 🟢 Low |
| `/crm/schema/getfolder/` | POST | Get CRM folder schema | 🟢 Low |
| `/crm/schema/listfolders/` | POST | List CRM folders | 🟢 Low |
| `/crm/views/listviews/` | POST | List CRM views | 🟢 Low |

#### 🟢 Low Priority - Communication (SMS/Email/Fax)

| Endpoint | Method | Description | Suggested Priority |
|----------|--------|-------------|-------------------|
| `/sms/sms/send/` | POST | Send SMS message | 🟢 Low |
| `/sms/sms/sendmultiple/` | POST | Send SMS to multiple recipients | 🟢 Low |
| `/sms/sms/listsenders/` | POST | List SMS sender IDs | 🟢 Low |
| `/sms/mailinglists/list/` | POST | List SMS mailing lists | 🟢 Low |
| `/sms/mailinglists/add/` | POST | Add to SMS mailing list | 🟢 Low |
| `/emailsubscriptions/mailinglists/list/` | POST | List email mailing lists | 🟢 Low |
| `/emailsubscriptions/mailinglists/add/` | POST | Add to email mailing list | 🟢 Low |
| `/fax/fax/send/` | POST | Send fax | 🟢 Low |

#### 🟢 Low Priority - Other Services

| Endpoint | Method | Description | Suggested Priority |
|----------|--------|-------------|-------------------|
| `/triggers/triggers/subscribe/` | POST | Subscribe to webhook triggers | 🟡 Medium |
| `/triggers/triggers/unsubscribe/` | POST | Unsubscribe from webhook triggers | 🟡 Medium |
| `/customerservice/tickets/create/` | POST | Create customer service ticket | 🟢 Low |
| `/letterbyclick/letterbyclick/senddocument/` | POST | Send document via postal mail | 🟢 Low |
| `/letterbyclick/letterbyclick/gettrackingcode/` | POST | Get postal tracking code | 🟢 Low |
| `/scheduleddocuments/documents/createfromdocument/` | POST | Create scheduled document | 🟢 Low |

---

## Upgrade Plan

### Phase 1: Core Billing Enhancements (High Priority) 🔴

**Estimated Timeline:** 2-3 weeks

1. **Payment Management**
   - Implement `PaymentQueryService` for `/billing/payments/get/` and `/billing/payments/list/`
   - Add methods to retrieve payment history and details

2. **Payment Methods Management**
   - Create `PaymentMethodService` for saved payment methods
   - Implement `/billing/paymentmethods/getforcustomer/`
   - Implement `/billing/paymentmethods/setforcustomer/`
   - Implement `/billing/paymentmethods/remove/`

3. **Recurring Payment Management**
   - Extend `SubscriptionService` with:
     - `cancel()` method for `/billing/recurring/cancel/`
     - `update()` method for `/billing/recurring/update/`
     - `listForCustomer()` method for `/billing/recurring/listforcustomer/`

**Files to Create/Modify:**
- `src/Services/PaymentQueryService.php` (new)
- `src/Services/PaymentMethodService.php` (new)
- `src/Services/SubscriptionService.php` (extend)

### Phase 2: Document Management (High Priority) 🔴

**Estimated Timeline:** 2 weeks

1. **Document Operations**
   - Extend `DocumentService` with:
     - `getDetails()` for `/accounting/documents/getdetails/`
     - `getPdf()` for `/accounting/documents/getpdf/`
     - `send()` for `/accounting/documents/send/`
     - `cancel()` for `/accounting/documents/cancel/`
     - `list()` for `/accounting/documents/list/`

2. **Debt Management**
   - Create `DebtService` for:
     - `/accounting/documents/getdebt/`
     - `/accounting/documents/getdebtreport/`

**Files to Create/Modify:**
- `src/Services/DocumentService.php` (extend)
- `src/Services/DebtService.php` (new)

### Phase 3: Customer Management (Medium Priority) 🟡

**Estimated Timeline:** 1-2 weeks

1. **Customer CRUD Operations**
   - Create `CustomerService` for:
     - `/accounting/customers/create/`
     - `/accounting/customers/update/`
     - `/accounting/customers/getdetailsurl/`
     - `/accounting/customers/createremark/`

**Files to Create:**
- `src/Services/CustomerService.php` (new)
- `src/Models/SumitCustomer.php` (new)

### Phase 4: Accounting Utilities (Medium Priority) 🟡

**Estimated Timeline:** 1 week

1. **Accounting General Operations**
   - Create `AccountingService` for:
     - VAT rate retrieval
     - Exchange rate lookup
     - Document number management
     - Settings management

2. **Income Items Management**
   - Create `IncomeItemService` for product/item management

**Files to Create:**
- `src/Services/AccountingService.php` (new)
- `src/Services/IncomeItemService.php` (new)

### Phase 5: Webhooks & Triggers (Medium Priority) 🟡

**Estimated Timeline:** 1 week

1. **Trigger Management**
   - Create `TriggerService` for:
     - `/triggers/triggers/subscribe/`
     - `/triggers/triggers/unsubscribe/`

**Files to Create:**
- `src/Services/TriggerService.php` (new)

### Phase 6: Communication Services (Low Priority) 🟢

**Estimated Timeline:** 2 weeks

1. **SMS Service**
   - Create `SmsService` for sending SMS messages

2. **Email Subscriptions**
   - Create `EmailSubscriptionService`

3. **Fax Service**
   - Create `FaxService` (optional)

**Files to Create:**
- `src/Services/SmsService.php` (new)
- `src/Services/EmailSubscriptionService.php` (new)
- `src/Services/FaxService.php` (new)

### Phase 7: CRM Integration (Low Priority) 🟢

**Estimated Timeline:** 2-3 weeks

1. **CRM Entity Management**
   - Create comprehensive `CrmService` for all CRM operations

**Files to Create:**
- `src/Services/CrmService.php` (new)
- `src/Models/CrmEntity.php` (new)
- `src/Models/CrmFolder.php` (new)

---

## Implementation Recommendations

### Service Pattern
All new services should follow the existing pattern:

```php
<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

class ExampleService
{
    /**
     * Example method making API call
     */
    public static function exampleMethod(array $params): array
    {
        $request = [
            'Credentials' => PaymentService::getCredentials(),
            // ... additional parameters
        ];

        $environment = config('officeguy.environment', 'www');
        $response = OfficeGuyApi::post($request, '/endpoint/path/', $environment, false);

        if ($response && ($response['Status'] ?? null) === 0) {
            return [
                'success' => true,
                'data' => $response['Data'] ?? null,
            ];
        }

        return [
            'success' => false,
            'message' => $response['UserErrorMessage'] ?? 'Unknown error',
        ];
    }
}
```

### Event Firing
All significant operations should fire events for extensibility:

```php
event(new \OfficeGuy\LaravelSumitGateway\Events\CustomerCreated($customerId, $response));
```

### Configuration
New features should be configurable via `config/officeguy.php`:

```php
'customer_sync' => [
    'enabled' => env('SUMIT_CUSTOMER_SYNC', false),
    'auto_create' => env('SUMIT_AUTO_CREATE_CUSTOMER', false),
],
```

### Models
Consider creating Eloquent models for entities that need local storage:

- `SumitCustomer` - For customer synchronization
- `SumitDocument` - For document tracking (already exists as `OfficeGuyDocument`)
- `SumitIncomeItem` - For product/item synchronization

---

## Conclusion

The current Laravel package implementation covers approximately **16%** of the available SUMIT API endpoints, focusing primarily on the core payment processing functionality. The suggested upgrade plan prioritizes features based on typical business requirements:

1. **Phase 1 & 2** - Essential for comprehensive payment and document management
2. **Phase 3 & 4** - Enables full customer and accounting integration
3. **Phase 5-7** - Advanced features for communication and CRM

The phased approach allows for incremental improvements while maintaining backward compatibility with existing implementations.
