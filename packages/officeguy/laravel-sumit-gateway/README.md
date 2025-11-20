# Laravel SUMIT Gateway

A comprehensive Laravel package for integrating SUMIT payment gateway with full Filament admin and client panel support. This package is a 1:1 port of the official SUMIT WooCommerce plugin.

## Features

- **Card Payments**: Support for PCI direct, redirect, and simple (PaymentsJS) modes
- **Bit Payments**: Israeli Bit payment method support via Upay
- **Tokenization**: Secure card storage for recurring payments
- **Document Management**: Automatic invoice and receipt creation
- **Multi-currency**: Support for 36+ currencies including ILS, USD, EUR, GBP, etc.
- **Installments**: Configurable payment plans with custom rules
- **Filament Integration**: Full admin panel and client portal resources
- **Comprehensive Logging**: Track all transactions and API calls
- **VAT Support**: Automatic tax calculation and document generation

## Requirements

- PHP 8.2 or higher
- Laravel 11.28 or higher
- Filament 4.x
- A SUMIT account with API credentials

## Installation

### 1. Install via Composer

```bash
composer require officeguy/laravel-sumit-gateway
```

### 2. Publish Configuration

```bash
php artisan vendor:publish --tag=officeguy-config
```

### 3. Configure Environment Variables

Add the following to your `.env` file:

```env
# SUMIT Credentials (Required)
OFFICEGUY_COMPANY_ID=your_company_id
OFFICEGUY_PRIVATE_KEY=your_private_key
OFFICEGUY_PUBLIC_KEY=your_public_key

# Environment (www for production, dev or test for sandbox)
OFFICEGUY_ENVIRONMENT=www

# PCI Mode (no=simple, redirect, yes=advanced/PCI-compliant)
OFFICEGUY_PCI_MODE=no

# Payment Settings
OFFICEGUY_TESTING=false
OFFICEGUY_MAX_PAYMENTS=12
OFFICEGUY_AUTHORIZE_ONLY=false

# Document Settings
OFFICEGUY_DRAFT_DOCUMENT=false
OFFICEGUY_EMAIL_DOCUMENT=true
OFFICEGUY_CREATE_ORDER_DOCUMENT=false

# Tokenization
OFFICEGUY_SUPPORT_TOKENS=true
OFFICEGUY_TOKEN_PARAM=5

# Bit Payments
OFFICEGUY_BIT_ENABLED=false

# Logging
OFFICEGUY_LOGGING=true
```

### 4. Run Migrations

```bash
php artisan migrate
```

### 5. Publish Views (Optional)

```bash
php artisan vendor:publish --tag=officeguy-views
```

## Usage

### Implementing the Payable Contract

Your Order model must implement the `Payable` contract:

```php
use OfficeGuy\LaravelSumitGateway\Contracts\Payable;

class Order extends Model implements Payable
{
    public function getPayableId(): string|int
    {
        return $this->id;
    }

    public function getPayableAmount(): float
    {
        return $this->total;
    }

    public function getPayableCurrency(): string
    {
        return $this->currency ?? 'ILS';
    }

    public function getCustomerEmail(): ?string
    {
        return $this->customer->email;
    }

    public function getCustomerPhone(): ?string
    {
        return $this->customer->phone;
    }

    public function getCustomerName(): string
    {
        return $this->customer->name;
    }

    public function getCustomerAddress(): ?array
    {
        return [
            'address' => $this->billing_address,
            'city' => $this->billing_city,
            'state' => $this->billing_state,
            'country' => $this->billing_country,
            'zip_code' => $this->billing_zip,
        ];
    }

    public function getCustomerCompany(): ?string
    {
        return $this->customer->company;
    }

    public function getCustomerId(): string|int|null
    {
        return $this->customer_id;
    }

    public function getLineItems(): array
    {
        return $this->items->map(function ($item) {
            return [
                'name' => $item->product_name,
                'sku' => $item->product_sku,
                'quantity' => $item->quantity,
                'unit_price' => $item->price,
                'product_id' => $item->product_id,
                'variation_id' => $item->variation_id,
            ];
        })->toArray();
    }

    public function getShippingAmount(): float
    {
        return $this->shipping_total;
    }

    public function getShippingMethod(): ?string
    {
        return $this->shipping_method;
    }

    public function getFees(): array
    {
        return $this->fees->map(function ($fee) {
            return [
                'name' => $fee->name,
                'amount' => $fee->amount,
            ];
        })->toArray();
    }

    public function getVatRate(): ?float
    {
        return $this->tax_rate;
    }

    public function isTaxEnabled(): bool
    {
        return config('app.tax_enabled', true);
    }

    public function getCustomerNote(): ?string
    {
        return $this->customer_note;
    }
}
```

### Processing a Payment

```php
use OfficeGuy\LaravelSumitGateway\Services\PaymentService;
use OfficeGuy\LaravelSumitGateway\Services\OfficeGuyApi;

// Your order instance
$order = Order::find(1);

// Build payment items
$items = PaymentService::getPaymentOrderItems($order);

// Build customer data
$customer = PaymentService::getOrderCustomer($order);

// Make payment request
$request = [
    'Credentials' => PaymentService::getCredentials(),
    'Items' => $items,
    'Customer' => $customer,
    'VATIncluded' => 'true',
    'VATRate' => PaymentService::getOrderVatRate($order),
    // ... additional fields
];

$response = OfficeGuyApi::post(
    $request,
    '/billing/payments/charge/',
    config('officeguy.environment'),
    true
);
```

### Creating a Document

```php
use OfficeGuy\LaravelSumitGateway\Services\DocumentService;

$order = Order::find(1);
$customer = PaymentService::getOrderCustomer($order);

$result = DocumentService::createOrderDocument($order, $customer, null);

if ($result === null) {
    // Success
} else {
    // Error: $result contains error message
}
```

### Working with Tokens

```php
use OfficeGuy\LaravelSumitGateway\Services\TokenService;

$user = auth()->user();

// Process tokenization
$result = TokenService::processToken($user, 'no');

if ($result['success']) {
    $token = $result['token'];
    // Token saved successfully
} else {
    $errorMessage = $result['message'];
}

// Get user's saved tokens
$tokens = OfficeGuyToken::getForOwner($user);

// Get default token
$defaultToken = OfficeGuyToken::getDefaultForOwner($user);
```

## Filament Integration

The package includes ready-to-use Filament resources for both admin and client panels.

### Admin Resources

- `OfficeGuyTransactionResource` - View and manage all transactions
- `OfficeGuyTokenResource` - Manage customer payment tokens
- `OfficeGuyDocumentResource` - View generated documents
- `OfficeGuySettingsPage` - Configure gateway settings

### Client Panel Resources

- `ClientTransactionResource` - Customer's transaction history
- `ClientPaymentMethodResource` - Manage saved payment methods

## API Endpoints

The package registers the following routes:

- `GET /officeguy/callback/card` - Card payment callback handler
- `POST /officeguy/webhook/bit` - Bit payment webhook handler

## Configuration

All configuration options are available in `config/officeguy.php`:

- **Environment Settings**: Production/Development/Test modes
- **Credentials**: Company ID, Private Key, Public Key
- **PCI Modes**: Simple, Redirect, or Advanced
- **Payment Options**: Installments, authorize-only, minimum amounts
- **Document Settings**: Draft mode, auto-email, languages
- **Tokenization**: Token storage and J5/J2 methods
- **Logging**: Enable/disable comprehensive logging

## Testing

Run the test suite:

```bash
composer test
```

## Security

If you discover any security-related issues, please email security@sumit.co.il instead of using the issue tracker.

## Credits

- SUMIT Team
- Original WooCommerce plugin developers

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Support

For support, please visit:
- [SUMIT Documentation](https://help.sumit.co.il/)
- [API Documentation](https://app.sumit.co.il/developers/)
- [GitHub Issues](https://github.com/nm-digitalhub/SUMIT-Payment-Gateway-for-laravel/issues)
