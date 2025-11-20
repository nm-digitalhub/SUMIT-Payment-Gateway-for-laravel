# Filament Resources - SUMIT Payment Gateway

This directory contains all Filament v4 resources for the Laravel SUMIT Gateway package.

## Admin Panel Resources

The following admin resources are available for managing the SUMIT payment gateway:

### 1. TransactionResource
**Path:** `src/Filament/Resources/TransactionResource.php`

Manages all payment transactions processed through SUMIT.

**Features:**
- View all transactions with detailed information
- Filter by status, currency, amount range, and test mode
- Display transaction status with color-coded badges
- View raw request/response data
- Show installment details
- Navigation badge showing pending transactions count

**Pages:**
- List: Browse all transactions
- View: See detailed transaction information

**Actions:**
- View Document: Navigate to associated document (if exists)
- Refresh Status: Update transaction status from API (placeholder)

---

### 2. TokenResource
**Path:** `src/Filament/Resources/TokenResource.php`

Manages saved payment tokens (credit cards) for recurring payments.

**Features:**
- View all saved payment tokens
- Display card information (last 4 digits, expiry date)
- Set tokens as default for users
- Delete expired or unwanted tokens
- Filter by default status and card type
- Navigation badge showing expired tokens count

**Pages:**
- List: Browse all saved tokens
- View: See detailed token information

**Actions:**
- Set as Default: Mark token as default payment method
- Delete: Remove token from system

---

### 3. DocumentResource
**Path:** `src/Filament/Resources/DocumentResource.php`

Manages invoices, receipts, and other documents created via SUMIT.

**Features:**
- View all generated documents
- Filter by document type, draft status, and email status
- Display document type with color-coded badges (Invoice, Order, Donation Receipt)
- View financial details and raw API response
- Navigation badge showing draft documents count

**Pages:**
- List: Browse all documents
- View: See detailed document information

---

### 4. OfficeGuySettings
**Path:** `src/Filament/Pages/OfficeGuySettings.php`

Configuration page for viewing SUMIT gateway settings.

**Features:**
- View all gateway configuration settings
- Display API credentials (read-only)
- Show environment settings (Production/Dev/Test)
- View payment options (installments, authorize-only)
- Display document settings
- Show tokenization configuration
- View additional features (Bit payments, logging)

**Note:** All settings are read from environment variables and cannot be modified through the UI. This is a read-only configuration viewer.

---

## Client Panel Resources

The client panel provides a customer-facing interface for managing their own transactions and payment methods.

### Panel Configuration
**Path:** `src/Filament/Client/ClientPanelProvider.php`

- Panel ID: `client`
- URL Path: `/client`
- Authentication: Required
- Primary Color: Sky Blue (#0ea5e9)

---

### 1. ClientTransactionResource
**Path:** `src/Filament/Client/Resources/ClientTransactionResource.php`

Customer view of their own transactions.

**Features:**
- View only authenticated user's transactions
- Display transaction history with status badges
- Show payment details and installments
- Filter transactions by status
- No create/edit/delete permissions (read-only)

**Pages:**
- List: Browse user's transactions
- View: See detailed transaction information

---

### 2. ClientPaymentMethodResource
**Path:** `src/Filament/Client/Resources/ClientPaymentMethodResource.php`

Customer management of saved payment methods.

**Features:**
- View only authenticated user's saved cards
- Display card information (masked number, expiry date)
- Set default payment method
- Delete saved payment methods
- Show expiry status with warnings
- Navigation badge for expired cards
- Empty state message for users with no saved cards

**Pages:**
- List: Browse user's saved payment methods
- View: See detailed payment method information

**Actions:**
- Set as Default: Mark card as default payment method
- Delete: Remove saved payment method (with confirmation)

---

## Installation & Setup

### 1. Register Resources in Service Provider

Update your `OfficeGuyServiceProvider.php`:

```php
use Filament\Facades\Filament;

public function boot(): void
{
    // ... existing code ...
    
    // Register Admin Resources
    Filament::serving(function () {
        Filament::registerResources([
            \OfficeGuy\LaravelSumitGateway\Filament\Resources\TransactionResource::class,
            \OfficeGuy\LaravelSumitGateway\Filament\Resources\TokenResource::class,
            \OfficeGuy\LaravelSumitGateway\Filament\Resources\DocumentResource::class,
        ]);
        
        Filament::registerPages([
            \OfficeGuy\LaravelSumitGateway\Filament\Pages\OfficeGuySettings::class,
        ]);
    });
}
```

### 2. Register Client Panel Provider

Add to `config/app.php`:

```php
'providers' => [
    // ... other providers ...
    \OfficeGuy\LaravelSumitGateway\Filament\Client\ClientPanelProvider::class,
],
```

Or use auto-discovery by adding to `composer.json`:

```json
{
    "extra": {
        "laravel": {
            "providers": [
                "OfficeGuy\\LaravelSumitGateway\\OfficeGuyServiceProvider",
                "OfficeGuy\\LaravelSumitGateway\\Filament\\Client\\ClientPanelProvider"
            ]
        }
    }
}
```

### 3. Publish Views (if needed)

```bash
php artisan vendor:publish --tag=officeguy-views
```

---

## URLs

### Admin Panel
- Transactions: `/admin/transactions`
- Tokens: `/admin/tokens`
- Documents: `/admin/documents`
- Settings: `/admin/officeguy-settings`

### Client Panel
- Transactions: `/client/client-transactions`
- Payment Methods: `/client/client-payment-methods`

---

## Permissions

All resources use Filament's built-in authorization. You can customize permissions by implementing policies for each model:

```php
// app/Policies/OfficeGuyTransactionPolicy.php
public function viewAny(User $user): bool
{
    return $user->hasPermissionTo('view_transactions');
}
```

---

## Customization

### Changing Navigation Groups

Edit the resource files and modify:

```php
protected static ?string $navigationGroup = 'Your Group Name';
```

### Customizing Colors

In each resource, you can customize badge colors:

```php
->colors([
    'success' => 'completed',
    'warning' => 'pending',
    'danger' => 'failed',
])
```

### Adding Custom Actions

Example of adding a custom action:

```php
Tables\Actions\Action::make('custom_action')
    ->label('Custom Action')
    ->icon('heroicon-o-sparkles')
    ->requiresConfirmation()
    ->action(function ($record) {
        // Your custom logic here
    }),
```

---

## Integration with SUMIT API

The resources are designed to integrate with the existing SUMIT services:

- **PaymentService**: For processing payments
- **TokenService**: For managing tokenized cards
- **DocumentService**: For creating documents
- **OfficeGuyApi**: For API communication

Example of calling the API from an action:

```php
use OfficeGuy\LaravelSumitGateway\Services\PaymentService;
use OfficeGuy\LaravelSumitGateway\Services\OfficeGuyApi;

Tables\Actions\Action::make('refresh_status')
    ->action(function ($record) {
        $request = [
            'Credentials' => PaymentService::getCredentials(),
            'PaymentID' => $record->payment_id,
        ];
        
        $response = OfficeGuyApi::post(
            $request,
            '/billing/payments/status/',
            config('officeguy.environment'),
            true
        );
        
        // Update record based on response
        $record->update(['status' => $response['Status']]);
    }),
```

---

## Future Enhancements

Planned features for future releases:

1. **Stock Sync Widget**: Display inventory synchronization status
2. **Subscription Management**: Handle recurring subscriptions
3. **Refund Processing**: Process refunds directly from admin panel
4. **Bulk Actions**: Process multiple transactions at once
5. **Advanced Reporting**: Generate payment reports and analytics
6. **Document Preview**: View/download PDF documents
7. **Webhook Log Viewer**: View webhook/callback logs

---

## Troubleshooting

### Resources Not Showing

1. Clear Filament cache: `php artisan filament:cache-clear`
2. Clear application cache: `php artisan cache:clear`
3. Ensure resources are properly registered in service provider

### Authorization Issues

Make sure your User model implements the `FilamentUser` contract and has the necessary permissions.

### Navigation Icons Not Displaying

Ensure `blade-ui-kit/blade-heroicons` is installed:

```bash
composer require blade-ui-kit/blade-heroicons
```

---

## Support

For issues related to Filament resources:
1. Check the Filament documentation: https://filamentphp.com/docs
2. Review the SUMIT API documentation
3. Open an issue on GitHub

---

## License

These Filament resources are part of the Laravel SUMIT Gateway package and are licensed under the MIT License.
