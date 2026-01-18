# DebtService Analysis

> **Complete technical analysis of the DebtService class**
>
> **Package**: `officeguy/laravel-sumit-gateway`
> **Version**: v1.21.4+
> **Service File**: `src/Services/DebtService.php`
> **Created**: 2026-01-13
> **Status**: Production-Ready

---

## Table of Contents

1. [Overview](#overview)
2. [Class Structure](#class-structure)
3. [Public Methods](#public-methods)
4. [SUMIT API Integration](#sumit-api-integration)
5. [Balance Calculation Logic](#balance-calculation-logic)
6. [Integration Points](#integration-points)
7. [Best Practices](#best-practices)
8. [Usage Examples](#usage-examples)
9. [Error Handling](#error-handling)
10. [Summary](#summary)

---

## Overview

### Purpose

The `DebtService` provides a comprehensive solution for managing customer debt and credit balances through the SUMIT accounting system. It enables:

- **Real-time balance retrieval** from SUMIT API
- **Detailed financial reports** with documents and payment history
- **Automated payment link generation** for debt collection
- **Multi-channel communication** (Email/SMS) for payment reminders
- **Batch balance operations** for multiple customers

### Key Features

| Feature | Description |
|---------|-------------|
| **Balance Checking** | Retrieve customer debt/credit balance in real-time |
| **Payment Links** | Generate SUMIT payment URLs for debt collection |
| **Email/SMS Integration** | Send payment links via email or SMS |
| **Batch Processing** | Get balances for multiple customers efficiently |
| **Detailed Reports** | Full financial history with documents and payments |
| **Currency Support** | ILS (₪) with formatted Hebrew labels |

### Dependencies

```php
use OfficeGuy\LaravelSumitGateway\Services\SettingsService;
use OfficeGuy\LaravelSumitGateway\Services\OfficeGuyApi;
use OfficeGuy\LaravelSumitGateway\Services\PaymentService;
use OfficeGuy\LaravelSumitGateway\Services\DocumentService;
use OfficeGuy\LaravelSumitGateway\Contracts\HasSumitCustomer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\SmsMessage; // Parent application dependency
```

---

## Class Structure

### Constructor

```php
public function __construct(
    private SettingsService $settings
) {}
```

**Dependency Injection**:
- `SettingsService` - For retrieving configuration (environment, credentials, etc.)

### Properties

| Property | Type | Visibility | Purpose |
|----------|------|------------|---------|
| `$settings` | `SettingsService` | `private` | Configuration access |

---

## Public Methods

### 1. getCustomerBalance()

**Signature**:
```php
public function getCustomerBalance(HasSumitCustomer $customer): ?array
```

**Purpose**: Retrieve customer debt/credit balance from SUMIT API.

**Parameters**:
- `$customer` - Model implementing `HasSumitCustomer` interface

**Return Value**:
```php
[
    'debt' => 150.50,              // float - positive = debt, negative = credit
    'currency' => 'ILS',           // string - currency code
    'last_updated' => '2026-01-13T10:30:00+00:00', // ISO8601 timestamp
    'formatted' => '₪150.50 (חוב)'  // string - Hebrew formatted display
]
// or null if customer has no SUMIT ID or API call fails
```

**Balance Interpretation**:
- **Positive value** → Customer owes money (חוב - debt)
- **Negative value** → Customer has credit balance (זכות - credit)
- **Zero** → Balanced (מאוזן - balanced)

**SUMIT API Endpoint**: `/accounting/documents/getdebt/`

**Key Request Parameters**:
```php
[
    'Credentials' => PaymentService::getCredentials(),
    'CustomerID' => (int) $sumitCustomerId,
    'DebitSource' => 4,  // Receipt (תשלומים שהתקבלו)
    'CreditSource' => 1, // TaxInvoice (חשבוניות מס)
    'IncludeDraftDocuments' => false,
]
```

**Critical Configuration**:
- `DebitSource: 4` → **Receipts** (payments received from customer)
- `CreditSource: 1` → **Tax Invoices** (invoices issued to customer)

This configuration ensures accurate balance calculation:
- **Invoices** increase the customer's debt (what they owe)
- **Receipts** decrease the debt (what they've paid)

**Example**:
```php
use OfficeGuy\LaravelSumitGateway\Services\DebtService;

$debtService = app(DebtService::class);
$balance = $debtService->getCustomerBalance($client);

if ($balance) {
    echo $balance['formatted']; // "₪150.50 (חוב)"

    if ($balance['debt'] > 0) {
        // Customer has debt - send reminder
    } elseif ($balance['debt'] < 0) {
        // Customer has credit - can be applied to next order
    }
}
```

---

### 2. getCustomerBalanceById()

**Signature**:
```php
public function getCustomerBalanceById(int $sumitCustomerId): ?array
```

**Purpose**: Convenience method to fetch balance by SUMIT customer ID without requiring a full Eloquent model.

**Parameters**:
- `$sumitCustomerId` - SUMIT customer ID (integer)

**Return Value**: Same as `getCustomerBalance()`

**Implementation**:
Uses an anonymous class stub implementing `HasSumitCustomer` to wrap the customer ID.

**Example**:
```php
// When you only have the SUMIT customer ID
$balance = $debtService->getCustomerBalanceById(12345);

if ($balance) {
    echo "Balance: {$balance['formatted']}";
}
```

---

### 3. createDebtPaymentDocument()

**Signature**:
```php
public function createDebtPaymentDocument(
    int $sumitCustomerId,
    float $amount,
    string $description = 'Debt Payment'
): ?string
```

**Purpose**: Create a payment document in SUMIT and return the payment URL.

**Parameters**:
- `$sumitCustomerId` - SUMIT customer ID
- `$amount` - Payment amount (ILS)
- `$description` - Optional description (default: 'Debt Payment')

**Return Value**:
- Payment URL string (e.g., `https://pay.sumit.co.il/payment/abc123`)
- `null` if document creation fails

**SUMIT API Endpoint**: `/accounting/documents/create/`

**Request Structure**:
```php
[
    'Credentials' => PaymentService::getCredentials(),
    'Items' => [
        [
            'Description' => 'Debt Payment',
            'Quantity' => 1,
            'Price' => 150.50,
            'VATRate' => 17, // from PaymentService::getOrderVatRate()
            'Currency' => 'ILS',
        ],
    ],
    'VATIncluded' => 'true',
    'Details' => [
        'CustomerID' => 12345,
        'Language' => 'he', // from PaymentService::getOrderLanguage()
        'Currency' => 'ILS',
        'Type' => DocumentService::TYPE_ORDER, // Payment order
        'Description' => 'Debt Payment',
        'SendByEmail' => [
            'Original' => 'false', // Don't send automatically
        ],
    ],
]
```

**Example**:
```php
$paymentUrl = $debtService->createDebtPaymentDocument(
    sumitCustomerId: 12345,
    amount: 150.50,
    description: 'תשלום יתרת חוב דצמבר 2025'
);

if ($paymentUrl) {
    // Send URL to customer or display it
    echo "Pay here: {$paymentUrl}";
}
```

---

### 4. sendPaymentLink()

**Signature**:
```php
public function sendPaymentLink(
    int $sumitCustomerId,
    ?string $email = null,
    ?string $phone = null,
    ?float $overrideAmount = null
): array
```

**Purpose**: Generate payment link and send it via email/SMS.

**Parameters**:
- `$sumitCustomerId` - SUMIT customer ID
- `$email` - Optional email address (sends email if provided)
- `$phone` - Optional phone number (sends SMS if provided)
- `$overrideAmount` - Optional custom amount (default: current debt balance)

**Return Value**:
```php
[
    'success' => true,
    'payment_url' => 'https://pay.sumit.co.il/payment/abc123',
    'amount' => 150.50,
]
// or
[
    'success' => false,
    'error' => 'Error description',
]
```

**Configuration Requirements**:
```php
// config/officeguy.php (or Admin Settings Page)
'collection' => [
    'email' => true,  // Enable email sending
    'sms' => false,   // Enable SMS sending
],

// .env (for SMS - parent app dependency)
SMS_DEFAULT_SENDER=ExtraMobile
```

**Email Template**:
```text
שלום,
מצורף לינק לתשלום החוב בסך ₪150.50:
https://pay.sumit.co.il/payment/abc123
```

**SMS Template**:
```text
לינק לתשלום חוב ₪150.50: https://pay.sumit.co.il/payment/abc123
```

**Example**:
```php
$result = $debtService->sendPaymentLink(
    sumitCustomerId: 12345,
    email: 'customer@example.com',
    phone: '+972501234567',
    overrideAmount: 100.00 // partial payment option
);

if ($result['success']) {
    Log::info('Payment link sent', [
        'amount' => $result['amount'],
        'url' => $result['payment_url'],
    ]);
} else {
    Log::error('Failed to send payment link', [
        'error' => $result['error'],
    ]);
}
```

---

### 5. getBalancesForCustomers()

**Signature**:
```php
public function getBalancesForCustomers($customers): array
```

**Purpose**: Batch operation to get balances for multiple customers.

**Parameters**:
- `$customers` - Laravel Collection of models implementing `HasSumitCustomer`

**Return Value**:
```php
[
    1 => [ // Customer model primary key
        'debt' => 150.50,
        'currency' => 'ILS',
        'last_updated' => '2026-01-13T10:30:00+00:00',
        'formatted' => '₪150.50 (חוב)',
    ],
    2 => [
        'debt' => -50.00,
        'currency' => 'ILS',
        'last_updated' => '2026-01-13T10:30:01+00:00',
        'formatted' => '₪50.00 (זכות)',
    ],
    // ... more customers
]
```

**Example**:
```php
use App\Models\Client;

$clients = Client::whereNotNull('sumit_customer_id')->get();
$balances = $debtService->getBalancesForCustomers($clients);

foreach ($clients as $client) {
    $balance = $balances[$client->id] ?? null;

    if ($balance) {
        echo "{$client->name}: {$balance['formatted']}\n";
    }
}
```

**Performance Considerations**:
- Each customer triggers a separate SUMIT API call
- For large datasets (100+ customers), consider queuing or chunking
- API calls are synchronous (blocking)

---

### 6. getBalanceReport()

**Signature**:
```php
public function getBalanceReport(HasSumitCustomer $customer): ?array
```

**Purpose**: Get comprehensive financial report with balance, documents, and payment history.

**Parameters**:
- `$customer` - Model implementing `HasSumitCustomer` interface

**Return Value**:
```php
[
    'documents' => [
        // Array of all customer documents from SUMIT
        [
            'Type' => 1, // Tax Invoice
            'DocumentValue' => 500.00,
            'Date' => '2025-12-01',
            // ... other document fields
        ],
        // ... more documents
    ],
    'payments' => [
        // Array of payment records from SUMIT
        [
            'Amount' => 300.00,
            'ValidPayment' => true,
            'Date' => '2025-12-15',
            // ... other payment fields
        ],
        // ... more payments
    ],
    'total_invoices' => 500.00,      // Sum of all invoices (Type=1)
    'total_payments' => 300.00,      // Sum of valid payments
    'total_credits' => 0.00,         // Sum of credit notes (Type=3)
    'balance' => 200.00,             // Current balance from SUMIT API
    'formatted_balance' => '₪200.00 (חוב)', // Hebrew formatted
    'balance_info' => [              // Full balance object
        'debt' => 200.00,
        'currency' => 'ILS',
        'last_updated' => '2026-01-13T10:30:00+00:00',
        'formatted' => '₪200.00 (חוב)',
    ],
]
// or null if customer has no SUMIT ID or API call fails
```

**Data Sources**:
1. **Balance** - `/accounting/documents/getdebt/` (most accurate)
2. **Documents** - `DocumentService::fetchFromSumit()` (last 5 years)
3. **Payments** - `/billing/payments/list/` (last 6 months)

**Document Types** (Type field):
- `1` → Tax Invoice (חשבונית מס)
- `3` → Credit Note (זיכוי)
- `4` → Receipt (קבלה)
- Other types defined in `DocumentService::TYPE_*` constants

**Example**:
```php
$report = $debtService->getBalanceReport($client);

if ($report) {
    echo "Current Balance: {$report['formatted_balance']}\n";
    echo "Total Invoices: ₪{$report['total_invoices']}\n";
    echo "Total Payments: ₪{$report['total_payments']}\n";
    echo "Total Credits: ₪{$report['total_credits']}\n";

    // Display recent documents
    foreach (array_slice($report['documents'], 0, 5) as $doc) {
        $type = match($doc['Type']) {
            1 => 'חשבונית',
            3 => 'זיכוי',
            4 => 'קבלה',
            default => 'מסמך',
        };
        echo "{$type}: ₪{$doc['DocumentValue']} - {$doc['Date']}\n";
    }
}
```

---

### 7. getPaymentHistory()

**Signature**:
```php
public function getPaymentHistory(
    HasSumitCustomer $customer,
    ?\Carbon\Carbon $dateFrom = null,
    ?\Carbon\Carbon $dateTo = null
): array
```

**Purpose**: Retrieve customer payment history from SUMIT billing API.

**Parameters**:
- `$customer` - Model implementing `HasSumitCustomer` interface
- `$dateFrom` - Start date (default: 6 months ago)
- `$dateTo` - End date (default: now)

**Return Value**:
```php
[
    [
        'CustomerID' => 12345,
        'Amount' => 300.00,
        'ValidPayment' => true,
        'Date' => '2025-12-15T10:30:00+00:00',
        'PaymentMethod' => 'CreditCard',
        // ... other payment fields from SUMIT
    ],
    // ... more payments
]
// or [] if customer has no SUMIT ID or API call fails
```

**SUMIT API Endpoint**: `/billing/payments/list/`

**Request Structure**:
```php
[
    'Credentials' => PaymentService::getCredentials(),
    'Date_From' => '2025-07-13T00:00:00+00:00', // ISO8601
    'Date_To' => '2026-01-13T23:59:59+00:00',   // ISO8601
    'Valid' => null, // Get all payments (valid and invalid)
    'StartIndex' => 0,
]
```

**Filtering**:
- API returns **all payments** for the date range
- Service filters by `CustomerID` to return only this customer's payments

**Example**:
```php
use Carbon\Carbon;

// Last 3 months
$payments = $debtService->getPaymentHistory(
    customer: $client,
    dateFrom: Carbon::now()->subMonths(3),
    dateTo: Carbon::now()
);

$validPayments = array_filter($payments, fn($p) => $p['ValidPayment'] === true);
$totalPaid = array_sum(array_column($validPayments, 'Amount'));

echo "Valid payments: " . count($validPayments) . "\n";
echo "Total paid: ₪{$totalPaid}\n";
```

---

### 8. formatBalance() (Private)

**Signature**:
```php
private function formatBalance(float $balance): string
```

**Purpose**: Format balance amount for Hebrew display.

**Parameters**:
- `$balance` - Raw balance value (positive = debt, negative = credit)

**Return Value**:
- `"₪150.50 (חוב)"` - Positive balance (debt)
- `"₪50.00 (זכות)"` - Negative balance (credit)
- `"₪0.00 (מאוזן)"` - Zero balance (balanced)

**Implementation**:
```php
if ($balance > 0) {
    return '₪' . number_format($balance, 2) . ' (חוב)';
} elseif ($balance < 0) {
    return '₪' . number_format(abs($balance), 2) . ' (זכות)';
}
return '₪0.00 (מאוזן)';
```

---

## SUMIT API Integration

### API Endpoints Used

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/accounting/documents/getdebt/` | POST | Get customer balance |
| `/accounting/documents/create/` | POST | Create payment document |
| `/billing/payments/list/` | POST | Get payment history |

### Authentication

All API calls use credentials from `PaymentService::getCredentials()`:

```php
'Credentials' => [
    'CompanyID' => config('officeguy.company_id'),
    'APIKey' => config('officeguy.api_key'),
]
```

### Environment Handling

```php
$environment = $this->settings->get('environment', 'www');

// Results in:
// 'dev' → http://dev.api.sumit.co.il
// 'www' → https://api.sumit.co.il (production)
```

### Response Format

**Success Response**:
```php
[
    'Status' => 0, // 0 = success
    'Data' => [
        'Debt' => 150.50, // or other data
        // ... endpoint-specific data
    ],
]
```

**Error Response**:
```php
[
    'Status' => 1, // non-zero = error
    'UserErrorMessage' => 'Invalid customer ID',
    'DeveloperErrorMessage' => 'Customer not found in database',
]
```

### Error Handling Strategy

1. **Try-Catch Block**: All API calls wrapped in try-catch
2. **Null Return**: Return `null` on failure (graceful degradation)
3. **Logging**: Log warnings/errors with context
4. **Status Check**: Validate `Status === 0` before processing response

```php
try {
    $response = OfficeGuyApi::post($payload, $endpoint, $environment, false);

    if (! $response || ($response['Status'] ?? null) !== 0) {
        Log::warning('SUMIT API call failed', [
            'endpoint' => $endpoint,
            'error' => $response['UserErrorMessage'] ?? 'Unknown error',
        ]);
        return null;
    }

    return $response['Data'];

} catch (Throwable $e) {
    Log::error('SUMIT API exception', [
        'endpoint' => $endpoint,
        'error' => $e->getMessage(),
    ]);
    return null;
}
```

---

## Balance Calculation Logic

### Critical Understanding

The **balance calculation** relies on correct `DebitSource` and `CreditSource` parameters:

```php
'DebitSource' => 4,  // Receipt (קבלות - תשלומים שהתקבלו)
'CreditSource' => 1, // TaxInvoice (חשבוניות מס)
```

### Why This Configuration?

| Parameter | Value | Document Type | Effect on Balance |
|-----------|-------|---------------|-------------------|
| `CreditSource` | 1 | Tax Invoice | **Increases** customer debt |
| `DebitSource` | 4 | Receipt | **Decreases** customer debt |

**Logic**:
- When you issue an **invoice** (Type 1) → Customer owes you money → Balance increases
- When customer makes a **payment** (Type 4 receipt) → Debt reduces → Balance decreases
- When you issue a **credit note** (Type 3) → Refund → Balance decreases

### Balance Formula

```
Customer Balance = Total Invoices - Total Receipts - Total Credits
```

**Example**:
- Invoice #1: ₪500 (balance = 500)
- Invoice #2: ₪300 (balance = 800)
- Receipt #1: ₪300 (balance = 500)
- Credit #1: ₪50 (balance = 450)
- Receipt #2: ₪200 (balance = 250)

Final balance: **₪250 (חוב)** - Customer owes 250

### Interpreting Results

```php
$balance = $debtService->getCustomerBalance($client);

if ($balance['debt'] > 0) {
    // Positive = Customer owes money
    echo "Customer has debt: {$balance['formatted']}";
    // Action: Send payment reminder
}

if ($balance['debt'] < 0) {
    // Negative = Customer has credit
    echo "Customer has credit: {$balance['formatted']}";
    // Action: Can be applied to next purchase
}

if ($balance['debt'] == 0) {
    // Zero = Balanced
    echo "Account is balanced: {$balance['formatted']}";
    // Action: No action needed
}
```

---

## Integration Points

### 1. Parent Application Models

**Customer Model** (example):
```php
namespace App\Models;

use OfficeGuy\LaravelSumitGateway\Contracts\HasSumitCustomer;
use OfficeGuy\LaravelSumitGateway\Support\Traits\HasSumitCustomerTrait;

class Client extends Model implements HasSumitCustomer
{
    use HasSumitCustomerTrait;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'sumit_customer_id', // SUMIT customer ID
    ];

    public function getSumitCustomerId(): ?int
    {
        return $this->sumit_customer_id;
    }
}
```

### 2. Filament Admin Integration

**Client Resource** (example):
```php
namespace App\Filament\Resources\ClientResource\Pages;

use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Components;
use OfficeGuy\LaravelSumitGateway\Services\DebtService;

class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Components\Section::make('Balance')
                ->schema([
                    Components\TextEntry::make('balance')
                        ->label('Current Balance')
                        ->state(function ($record) {
                            $balance = app(DebtService::class)
                                ->getCustomerBalance($record);
                            return $balance['formatted'] ?? 'N/A';
                        }),
                ])
                ->collapsible(),
        ]);
    }
}
```

### 3. Scheduled Jobs (Debt Collection)

**Artisan Command** (example):
```php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Client;
use OfficeGuy\LaravelSumitGateway\Services\DebtService;

class SendDebtReminders extends Command
{
    protected $signature = 'debt:send-reminders';
    protected $description = 'Send payment reminders to clients with outstanding debt';

    public function handle(DebtService $debtService)
    {
        $clients = Client::whereNotNull('sumit_customer_id')
            ->whereNotNull('email')
            ->get();

        foreach ($clients as $client) {
            $balance = $debtService->getCustomerBalance($client);

            if ($balance && $balance['debt'] > 100) {
                $result = $debtService->sendPaymentLink(
                    sumitCustomerId: $client->sumit_customer_id,
                    email: $client->email,
                    phone: $client->phone
                );

                if ($result['success']) {
                    $this->info("Sent to {$client->name} - ₪{$result['amount']}");
                }
            }
        }
    }
}
```

**Schedule** (in `app/Console/Kernel.php`):
```php
protected function schedule(Schedule $schedule)
{
    // Send debt reminders every Monday at 9 AM
    $schedule->command('debt:send-reminders')
        ->weeklyOn(1, '9:00')
        ->timezone('Asia/Jerusalem');
}
```

### 4. Livewire Component (Customer Portal)

**Component** (example):
```php
namespace App\Livewire\Customer;

use Livewire\Component;
use OfficeGuy\LaravelSumitGateway\Services\DebtService;

class MyBalance extends Component
{
    public $balance;
    public $report;

    public function mount(DebtService $debtService)
    {
        $this->balance = $debtService->getCustomerBalance(auth()->user());
        $this->report = $debtService->getBalanceReport(auth()->user());
    }

    public function render()
    {
        return view('livewire.customer.my-balance');
    }
}
```

**Blade View** (`resources/views/livewire/customer/my-balance.blade.php`):
```blade
<div class="bg-white rounded-lg shadow p-6">
    @if($balance)
        <div class="text-center mb-6">
            <h3 class="text-lg font-semibold mb-2">יתרת חשבון</h3>
            <p class="text-3xl font-bold {{ $balance['debt'] > 0 ? 'text-red-600' : 'text-green-600' }}">
                {{ $balance['formatted'] }}
            </p>
        </div>

        @if($report)
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="text-center">
                    <p class="text-sm text-gray-600">סה"כ חשבוניות</p>
                    <p class="text-xl font-semibold">₪{{ number_format($report['total_invoices'], 2) }}</p>
                </div>
                <div class="text-center">
                    <p class="text-sm text-gray-600">סה"כ תשלומים</p>
                    <p class="text-xl font-semibold">₪{{ number_format($report['total_payments'], 2) }}</p>
                </div>
                <div class="text-center">
                    <p class="text-sm text-gray-600">זיכויים</p>
                    <p class="text-xl font-semibold">₪{{ number_format($report['total_credits'], 2) }}</p>
                </div>
            </div>

            <h4 class="font-semibold mb-3">מסמכים אחרונים</h4>
            <div class="space-y-2">
                @foreach(array_slice($report['documents'], 0, 5) as $doc)
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                        <span>{{ $doc['Description'] ?? 'מסמך' }}</span>
                        <span class="font-semibold">₪{{ number_format($doc['DocumentValue'], 2) }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    @else
        <p class="text-center text-gray-500">לא נמצא מידע על יתרה</p>
    @endif
</div>
```

### 5. API Endpoint (External Integration)

**Controller** (example):
```php
namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Client;
use OfficeGuy\LaravelSumitGateway\Services\DebtService;

class ClientBalanceController extends Controller
{
    public function __construct(
        private DebtService $debtService
    ) {}

    public function show(Request $request, int $clientId): JsonResponse
    {
        $client = Client::findOrFail($clientId);

        // Authorization check
        $this->authorize('view', $client);

        $balance = $this->debtService->getCustomerBalance($client);

        if (! $balance) {
            return response()->json([
                'error' => 'Balance not available',
            ], 404);
        }

        return response()->json([
            'client_id' => $client->id,
            'sumit_customer_id' => $client->sumit_customer_id,
            'balance' => $balance,
        ]);
    }

    public function report(Request $request, int $clientId): JsonResponse
    {
        $client = Client::findOrFail($clientId);
        $this->authorize('view', $client);

        $report = $this->debtService->getBalanceReport($client);

        if (! $report) {
            return response()->json([
                'error' => 'Report not available',
            ], 404);
        }

        return response()->json($report);
    }
}
```

**Routes** (`routes/api.php`):
```php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/clients/{client}/balance', [ClientBalanceController::class, 'show']);
    Route::get('/clients/{client}/balance-report', [ClientBalanceController::class, 'report']);
});
```

---

## Best Practices

### 1. Always Check for Null Returns

```php
// ✅ DO
$balance = $debtService->getCustomerBalance($client);
if ($balance) {
    echo $balance['formatted'];
} else {
    echo 'Balance not available';
}

// ❌ DON'T
$balance = $debtService->getCustomerBalance($client);
echo $balance['formatted']; // Fatal error if null!
```

### 2. Use Queues for Batch Operations

```php
// ✅ DO - Queue for 100+ customers
use Illuminate\Support\Facades\Bus;
use App\Jobs\SendDebtReminderJob;

$clients = Client::whereNotNull('sumit_customer_id')->get();

$jobs = $clients->map(fn($client) => new SendDebtReminderJob($client));
Bus::batch($jobs)->dispatch();

// ❌ DON'T - Synchronous for many customers
$balances = $debtService->getBalancesForCustomers($clients); // Blocks!
```

### 3. Cache Balances for Read-Heavy Operations

```php
// ✅ DO
use Illuminate\Support\Facades\Cache;

$balance = Cache::remember(
    key: "client_{$client->id}_balance",
    ttl: now()->addMinutes(30),
    callback: fn() => $debtService->getCustomerBalance($client)
);

// Invalidate cache on payment
event(new PaymentCompleted($transaction));

// EventListener
class InvalidateBalanceCacheListener
{
    public function handle(PaymentCompleted $event)
    {
        Cache::forget("client_{$event->clientId}_balance");
    }
}
```

### 4. Handle API Failures Gracefully

```php
// ✅ DO
$balance = $debtService->getCustomerBalance($client);

if ($balance) {
    // Display balance
    return view('balance', compact('balance'));
} else {
    // Fallback to local records
    $localBalance = $client->transactions()->sum('amount');
    return view('balance', ['balance' => [
        'debt' => $localBalance,
        'formatted' => "₪{$localBalance} (estimated)",
    ]]);
}
```

### 5. Log Important Operations

```php
// ✅ DO
use Illuminate\Support\Facades\Log;

$result = $debtService->sendPaymentLink(
    sumitCustomerId: $client->sumit_customer_id,
    email: $client->email
);

if ($result['success']) {
    Log::info('Debt payment link sent', [
        'client_id' => $client->id,
        'sumit_customer_id' => $client->sumit_customer_id,
        'amount' => $result['amount'],
        'payment_url' => $result['payment_url'],
    ]);
} else {
    Log::error('Failed to send debt payment link', [
        'client_id' => $client->id,
        'error' => $result['error'],
    ]);
}
```

### 6. Use Type-Safe Code

```php
// ✅ DO - Type hints everywhere
public function processDebt(Client $client, DebtService $debtService): ?array
{
    return $debtService->getCustomerBalance($client);
}

// ❌ DON'T - Loose types
public function processDebt($client, $debtService)
{
    return $debtService->getCustomerBalance($client);
}
```

### 7. Validate Configuration

```php
// ✅ DO - Check email/SMS settings before sending
$emailEnabled = config('officeguy.collection.email', false);
$smsEnabled = config('officeguy.collection.sms', false);

if (! $emailEnabled && ! $smsEnabled) {
    throw new \Exception('No delivery method enabled for payment links');
}

// Then send
$result = $debtService->sendPaymentLink(...);
```

---

## Usage Examples

### Example 1: Display Balance in Admin Panel

**Filament Resource Infolist**:
```php
use Filament\Infolists\Components;
use OfficeGuy\LaravelSumitGateway\Services\DebtService;

Components\Section::make('Financial Status')
    ->schema([
        Components\TextEntry::make('sumit_balance')
            ->label('Current Balance')
            ->state(function ($record) {
                $balance = app(DebtService::class)->getCustomerBalance($record);
                return $balance['formatted'] ?? 'N/A';
            })
            ->badge()
            ->color(fn($record) => function () use ($record) {
                $balance = app(DebtService::class)->getCustomerBalance($record);
                if (! $balance) return 'gray';
                return $balance['debt'] > 0 ? 'danger' : 'success';
            }),
    ])
```

### Example 2: Automated Debt Collection Job

**Job Class**:
```php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\Client;
use OfficeGuy\LaravelSumitGateway\Services\DebtService;

class SendDebtReminderJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public function __construct(
        public Client $client
    ) {}

    public function handle(DebtService $debtService): void
    {
        $balance = $debtService->getCustomerBalance($this->client);

        if (! $balance || $balance['debt'] <= 0) {
            return; // No debt or credit balance
        }

        // Only send if debt > ₪100
        if ($balance['debt'] < 100) {
            return;
        }

        $result = $debtService->sendPaymentLink(
            sumitCustomerId: $this->client->sumit_customer_id,
            email: $this->client->email,
            phone: $this->client->phone
        );

        if ($result['success']) {
            // Log successful reminder
            $this->client->update([
                'last_debt_reminder_sent_at' => now(),
            ]);
        }
    }
}
```

### Example 3: Customer Balance Widget

**Filament Widget**:
```php
namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Client;
use OfficeGuy\LaravelSumitGateway\Services\DebtService;

class DebtOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $debtService = app(DebtService::class);

        $clients = Client::whereNotNull('sumit_customer_id')->get();
        $balances = $debtService->getBalancesForCustomers($clients);

        $totalDebt = 0;
        $totalCredit = 0;
        $clientsWithDebt = 0;

        foreach ($balances as $balance) {
            if ($balance['debt'] > 0) {
                $totalDebt += $balance['debt'];
                $clientsWithDebt++;
            } elseif ($balance['debt'] < 0) {
                $totalCredit += abs($balance['debt']);
            }
        }

        return [
            Stat::make('Total Debt', '₪' . number_format($totalDebt, 2))
                ->description("{$clientsWithDebt} clients")
                ->descriptionIcon('heroicon-o-currency-dollar')
                ->color('danger'),

            Stat::make('Total Credit', '₪' . number_format($totalCredit, 2))
                ->description('Customer credits')
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->color('success'),

            Stat::make('Clients Checked', count($balances))
                ->description('With SUMIT ID')
                ->descriptionIcon('heroicon-o-users')
                ->color('primary'),
        ];
    }
}
```

### Example 4: API Endpoint for Balance Check

**Controller**:
```php
namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Client;
use OfficeGuy\LaravelSumitGateway\Services\DebtService;

class BalanceController extends Controller
{
    public function __construct(
        private DebtService $debtService
    ) {}

    /**
     * GET /api/v1/balance
     *
     * Query params:
     * - client_id (required): Client ID
     * - detailed (optional): Include full report (true/false)
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => 'required|integer|exists:clients,id',
            'detailed' => 'boolean',
        ]);

        $client = Client::findOrFail($request->client_id);

        // Check authorization (example using Laravel Policy)
        $this->authorize('viewBalance', $client);

        if ($request->boolean('detailed')) {
            // Full report
            $report = $this->debtService->getBalanceReport($client);

            if (! $report) {
                return response()->json([
                    'error' => 'Report not available',
                    'message' => 'Customer has no SUMIT ID or API call failed',
                ], 404);
            }

            return response()->json([
                'client_id' => $client->id,
                'sumit_customer_id' => $client->sumit_customer_id,
                'report' => $report,
            ]);

        } else {
            // Simple balance
            $balance = $this->debtService->getCustomerBalance($client);

            if (! $balance) {
                return response()->json([
                    'error' => 'Balance not available',
                    'message' => 'Customer has no SUMIT ID or API call failed',
                ], 404);
            }

            return response()->json([
                'client_id' => $client->id,
                'sumit_customer_id' => $client->sumit_customer_id,
                'balance' => $balance,
            ]);
        }
    }
}
```

### Example 5: Debt Collection Dashboard

**Livewire Component**:
```php
namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Client;
use OfficeGuy\LaravelSumitGateway\Services\DebtService;
use Illuminate\Support\Facades\Cache;

class DebtCollectionDashboard extends Component
{
    use WithPagination;

    public $minDebt = 100;
    public $sendingReminder = false;

    public function sendReminders(DebtService $debtService)
    {
        $this->sendingReminder = true;

        $clients = Client::whereNotNull('sumit_customer_id')
            ->whereNotNull('email')
            ->get();

        $sent = 0;

        foreach ($clients as $client) {
            $balance = $debtService->getCustomerBalance($client);

            if ($balance && $balance['debt'] >= $this->minDebt) {
                $result = $debtService->sendPaymentLink(
                    sumitCustomerId: $client->sumit_customer_id,
                    email: $client->email,
                    phone: $client->phone
                );

                if ($result['success']) {
                    $sent++;
                }
            }
        }

        $this->sendingReminder = false;

        session()->flash('message', "Sent {$sent} payment reminders");
    }

    public function render(DebtService $debtService)
    {
        $clients = Client::whereNotNull('sumit_customer_id')
            ->paginate(50);

        // Get balances (with cache)
        $balances = [];
        foreach ($clients as $client) {
            $balances[$client->id] = Cache::remember(
                "client_{$client->id}_balance",
                now()->addMinutes(30),
                fn() => $debtService->getCustomerBalance($client)
            );
        }

        return view('livewire.admin.debt-collection-dashboard', [
            'clients' => $clients,
            'balances' => $balances,
        ]);
    }
}
```

**Blade View**:
```blade
<div>
    <div class="mb-6 flex justify-between items-center">
        <h2 class="text-2xl font-bold">Debt Collection Dashboard</h2>

        <div class="flex gap-4 items-center">
            <input type="number"
                wire:model="minDebt"
                placeholder="Min debt (₪)"
                class="border rounded px-3 py-2"
            />

            <button wire:click="sendReminders"
                wire:loading.attr="disabled"
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-50">
                <span wire:loading.remove>Send Reminders</span>
                <span wire:loading>Sending...</span>
            </button>
        </div>
    </div>

    @if(session()->has('message'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('message') }}
        </div>
    @endif

    <table class="w-full border-collapse border">
        <thead>
            <tr class="bg-gray-100">
                <th class="border p-2">Client</th>
                <th class="border p-2">Email</th>
                <th class="border p-2">Balance</th>
                <th class="border p-2">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($clients as $client)
                @php
                    $balance = $balances[$client->id] ?? null;
                @endphp
                <tr>
                    <td class="border p-2">{{ $client->name }}</td>
                    <td class="border p-2">{{ $client->email }}</td>
                    <td class="border p-2 font-mono">
                        {{ $balance['formatted'] ?? 'N/A' }}
                    </td>
                    <td class="border p-2">
                        @if($balance && $balance['debt'] > 0)
                            <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-sm">
                                Debt
                            </span>
                        @elseif($balance && $balance['debt'] < 0)
                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-sm">
                                Credit
                            </span>
                        @else
                            <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-sm">
                                Balanced
                            </span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-4">
        {{ $clients->links() }}
    </div>
</div>
```

---

## Error Handling

### Common Error Scenarios

| Scenario | Cause | Service Response | Recommended Action |
|----------|-------|------------------|-------------------|
| Customer has no SUMIT ID | `getSumitCustomerId()` returns null | Returns `null` | Create SUMIT customer first |
| SUMIT API timeout | Network issues or slow response | Returns `null` | Retry or use cached balance |
| Invalid credentials | Wrong API key or Company ID | Returns `null` | Check Admin Settings |
| Customer not found in SUMIT | Wrong customer ID | Returns `null` | Verify customer sync |
| API rate limiting | Too many requests | Returns `null` | Implement queues or delays |

### Logging Strategy

All errors are logged with context:

```php
// Warning (expected failures - e.g., customer not found)
Log::warning('SUMIT debt retrieval failed', [
    'sumit_customer_id' => $sumitCustomerId,
    'error' => $response['UserErrorMessage'] ?? 'Unknown error',
]);

// Error (unexpected failures - e.g., exceptions)
Log::error('SUMIT debt retrieval exception', [
    'sumit_customer_id' => $sumitCustomerId,
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString(),
]);
```

### Monitoring Recommendations

**1. Track API Success Rate**:
```php
// In AppServiceProvider or monitoring service
use Illuminate\Support\Facades\Event;

Event::listen('sumit.api.call', function ($endpoint, $success) {
    Metrics::increment("sumit.api.{$endpoint}." . ($success ? 'success' : 'failure'));
});
```

**2. Alert on High Failure Rate**:
```php
// Example using Laravel Horizon or custom monitoring
if (Metrics::get('sumit.api.getdebt.failure') > 10) {
    Notification::send($admins, new HighApiFailureRateNotification());
}
```

**3. Log Slow Requests**:
```php
// Add to OfficeGuyApi::post() method
$start = microtime(true);
$response = Http::post($url, $request);
$duration = microtime(true) - $start;

if ($duration > 5.0) {
    Log::warning('Slow SUMIT API call', [
        'endpoint' => $path,
        'duration' => $duration,
    ]);
}
```

---

## Summary

### Key Takeaways

1. **Purpose**: `DebtService` provides comprehensive customer balance management through SUMIT API
2. **Balance Calculation**: Uses `DebitSource: 4` (Receipts) and `CreditSource: 1` (Tax Invoices)
3. **Null Safety**: All methods return `null` on failure - always check before using results
4. **API Integration**: 3 SUMIT endpoints for balance, documents, and payments
5. **Payment Links**: Automated payment document creation and delivery via email/SMS
6. **Batch Operations**: Support for checking multiple customer balances
7. **Detailed Reports**: Full financial history with documents and payments
8. **Hebrew Formatting**: Balance amounts formatted in Hebrew (חוב/זכות/מאוזן)

### When to Use Each Method

| Method | Use Case |
|--------|----------|
| `getCustomerBalance()` | Simple balance check for UI display |
| `getCustomerBalanceById()` | Quick lookup without loading full model |
| `createDebtPaymentDocument()` | Generate payment link for manual delivery |
| `sendPaymentLink()` | Automated debt collection with email/SMS |
| `getBalancesForCustomers()` | Batch balance display in admin lists |
| `getBalanceReport()` | Detailed financial analysis with history |
| `getPaymentHistory()` | Payment tracking and reconciliation |

### Integration Checklist

- [ ] Customer model implements `HasSumitCustomer` interface
- [ ] SUMIT credentials configured in Admin Settings Page
- [ ] Email/SMS delivery configured (for payment links)
- [ ] Error logging monitored (warnings and errors)
- [ ] API calls cached for read-heavy operations
- [ ] Queues configured for batch operations
- [ ] Balance display added to customer views
- [ ] Automated debt collection scheduled (optional)

### Performance Considerations

- **API Latency**: Each balance check = 1 SUMIT API call (~500ms-2s)
- **Batch Operations**: 100 customers = 100 API calls (~50s-200s)
- **Caching**: Recommended for 30 minutes TTL on balance data
- **Queues**: Required for 100+ customers to avoid timeouts

### Security Considerations

- **Authorization**: Always check user permissions before displaying balance
- **API Credentials**: Stored securely in database (`officeguy_settings` table)
- **Logging**: Sensitive data (amounts, customer IDs) logged for audit
- **Rate Limiting**: Consider implementing client-side rate limiting for batch operations

---

## Additional Resources

### Related Documentation

- **SUMIT API Docs**: [https://docs.sumit.co.il](https://docs.sumit.co.il)
- **Package README**: `/var/www/vhosts/nm-digitalhub.com/SUMIT-Payment-Gateway-for-laravel/README.md`
- **CLAUDE.md**: Development guide for this package
- **DocumentService Analysis**: Related service for document handling

### Related Services

- **OfficeGuyApi**: HTTP client for SUMIT API calls
- **PaymentService**: Payment processing and credentials
- **DocumentService**: Invoice and receipt generation
- **CustomerMergeService**: Customer synchronization with SUMIT

### Support

For questions or issues:
- **GitHub**: [nm-digitalhub/SUMIT-Payment-Gateway-for-laravel](https://github.com/nm-digitalhub/SUMIT-Payment-Gateway-for-laravel)
- **Email**: info@nm-digitalhub.com

---

**Document Version**: 1.0
**Last Updated**: 2026-01-13
**Reviewed By**: Claude Code Assistant
**Package Version**: v1.21.4+
