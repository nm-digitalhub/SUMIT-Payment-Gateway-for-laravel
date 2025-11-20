# Filament Admin Resources Documentation

This document provides detailed information about the Filament admin resources included in the SUMIT Payment Gateway package.

## Overview

The package includes three main Filament resources for managing payment gateway data:

1. **TransactionResource** - Manage payment transactions
2. **TokenResource** - Manage tokenized payment methods
3. **DocumentResource** - Manage invoices and receipts

## Installation

### 1. Register the Plugin

In your Filament panel provider (typically `app/Providers/Filament/AdminPanelProvider.php`):

```php
use OfficeGuy\LaravelSumitGateway\Filament\OfficeGuyPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->id('admin')
        ->path('admin')
        // ... other configuration
        ->plugins([
            OfficeGuyPlugin::make(),
        ]);
}
```

### 2. Navigate to Resources

After registering the plugin, you'll see a new navigation group "SUMIT Gateway" with three menu items:
- Transactions
- Payment Tokens
- Documents

## TransactionResource

### Features

- **List View**: Displays all payment transactions with pagination
- **View/Detail Page**: Shows comprehensive transaction information
- **Filters**:
  - Status (pending, completed, failed, refunded, cancelled)
  - Currency
  - Payment Method (card, bit)
  - Amount Range
  - Date Range
  - Test Mode Toggle
- **Columns**:
  - ID
  - Payment ID (searchable, copyable)
  - Order ID
  - Amount (formatted with currency)
  - Currency
  - Status (color-coded badge)
  - Payment Method
  - Card Last 4 Digits
  - Test Mode Indicator
  - Created Date
- **Actions**:
  - View Details
  - Refresh Status (placeholder for API integration)
  - Delete
- **Navigation Badge**: Shows count of pending transactions

### Detail View Sections

1. **Transaction Overview**: Payment ID, Order ID, Order Type, Document ID, Customer ID, Auth Number
2. **Payment Information**: Amount, Currency, Installments, First Payment, Subsequent Payments, Status
3. **Card Details**: Payment Method, Card Number (masked), Card Type, Expiry Date
4. **Status Information**: Status Description, Error Message
5. **Environment**: Environment (www/dev/test), Test Mode, Created/Updated timestamps
6. **Raw Data**: Complete API request and response (collapsible, copyable)

### Usage Example

```php
// The resource is automatically registered via the plugin
// No manual registration needed

// Access via navigation: Admin Panel > SUMIT Gateway > Transactions
```

## TokenResource

### Features

- **List View**: Displays all saved payment tokens
- **View/Detail Page**: Shows token and card information
- **Filters**:
  - Card Type
  - Default Status
  - Expired Tokens Toggle
- **Columns**:
  - ID
  - Owner Type (formatted class name)
  - Owner ID
  - Card Number (masked)
  - Card Type
  - Expiry Date (color-coded)
  - Default Status
  - Gateway
  - Created Date
- **Actions**:
  - View Details
  - Set as Default (only shown for non-default tokens)
  - Delete
- **Navigation Badge**: Shows count of expired tokens

### Detail View Sections

1. **Token Information**: Token value, Gateway ID, Default status
2. **Owner Information**: Owner type and ID
3. **Card Details**: Masked card number, type, expiry, citizen ID, status (active/expired)
4. **Metadata**: Additional data from API (collapsible, copyable)
5. **Timestamps**: Created, Updated, Deleted dates

### Set as Default Action

When a token is set as default:
1. All other tokens for the same owner are marked as non-default
2. The selected token is marked as default
3. A success notification is shown

### Usage Example

```php
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken;

// Get default token for a user
$defaultToken = OfficeGuyToken::getDefaultForOwner($user);

// Get all tokens for a user
$tokens = OfficeGuyToken::getForOwner($user);

// Set a token as default programmatically
$token->setAsDefault();
```

## DocumentResource

### Features

- **List View**: Displays all generated documents
- **View/Detail Page**: Shows document details
- **Filters**:
  - Document Type (Invoice, Order, Donation Receipt)
  - Currency
  - Draft Status
  - Email Status
  - Date Range
- **Columns**:
  - ID
  - Document ID (searchable, copyable)
  - Order ID
  - Document Type (color-coded badge)
  - Amount (formatted with currency)
  - Currency
  - Draft Status
  - Email Status
  - Language
  - Created Date
- **Actions**:
  - View Details
  - Download PDF (placeholder for API integration)
  - Delete
- **Navigation Badge**: Shows count of draft documents

### Detail View Sections

1. **Document Overview**: Document ID, Order ID, Order Type, Customer ID, Document Type
2. **Financial Information**: Amount, Currency, Language
3. **Document Status**: Draft status, Email sent status
4. **Description**: Document description (collapsible if empty)
5. **Raw Response**: Complete API response (collapsible, copyable)
6. **Timestamps**: Created, Updated, Deleted dates

### Document Type Mapping

- `1` → Invoice (green badge)
- `8` → Order (blue badge)
- `DonationReceipt` → Donation Receipt (yellow badge)

### Usage Example

```php
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyDocument;

// Check document type
if ($document->isInvoice()) {
    // Handle invoice
}

// Get document type name
$typeName = $document->getDocumentTypeName(); // e.g., "Invoice"
```

## Navigation Structure

```
Admin Panel
└── SUMIT Gateway (Navigation Group)
    ├── Transactions (Badge: Pending count)
    ├── Payment Tokens (Badge: Expired count)
    └── Documents (Badge: Draft count)
```

## Common Features

All resources include:

- **Search**: Quick search across key fields
- **Sorting**: Click column headers to sort
- **Bulk Actions**: Select multiple records and delete
- **Export**: Native Filament export capabilities (if enabled)
- **Pagination**: Configurable records per page
- **Responsive Design**: Mobile-friendly tables
- **Copy to Clipboard**: Click to copy IDs and tokens
- **Color-Coded Badges**: Visual indicators for status, types, etc.

## Customization

### Hiding Resources

If you want to hide specific resources, you can create your own plugin:

```php
use Filament\Contracts\Plugin;
use Filament\Panel;

class CustomOfficeGuyPlugin implements Plugin
{
    public function getId(): string
    {
        return 'custom-officeguy';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            // Register only the resources you want
            OfficeGuyTransactionResource::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
```

### Customizing Navigation

You can customize the navigation group, icons, and sort order by extending the resource classes:

```php
use OfficeGuy\LaravelSumitGateway\Filament\Resources\OfficeGuyTransactionResource as BaseTransactionResource;

class CustomTransactionResource extends BaseTransactionResource
{
    protected static ?string $navigationGroup = 'Payments';
    
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    
    protected static ?int $navigationSort = 10;
}
```

## Security Considerations

1. **Raw Data Access**: Raw request/response data is collapsible and requires explicit user action to view
2. **Sensitive Information**: Card numbers are always masked (only last 4 digits shown)
3. **Tokens**: Token values are displayed but in a non-editable format
4. **Delete Actions**: All delete actions require confirmation
5. **Permissions**: These resources respect Filament's built-in authorization system

## Future Enhancements

The following placeholder actions are included for future API integration:

1. **Transaction Refresh**: Query SUMIT API to update transaction status
2. **PDF Download**: Download document PDFs from SUMIT API
3. **Bulk Operations**: Export, refund, etc.

To implement these:

```php
// In the action callback, add actual API calls
Tables\Actions\Action::make('refresh')
    ->action(function (OfficeGuyTransaction $record) {
        // TODO: Implement actual API call
        $response = OfficeGuyApi::post([
            'PaymentID' => $record->payment_id,
        ], '/billing/payments/status/', config('officeguy.environment'));
        
        // Update transaction based on response
        // ...
    });
```

## Troubleshooting

### Resources Not Showing

1. Ensure the plugin is registered in your panel provider
2. Check that Filament is properly installed (`composer show filament/filament`)
3. Clear cache: `php artisan filament:cache-clear`

### Navigation Badge Not Updating

Badges are calculated on page load. If counts seem incorrect:
1. Check database records
2. Clear application cache: `php artisan cache:clear`
3. Refresh the browser page

### Permission Issues

If resources are not accessible:
1. Check Filament policies are properly configured
2. Ensure the authenticated user has appropriate permissions
3. Review panel middleware configuration

## Support

For issues or questions:
- GitHub Issues: https://github.com/nm-digitalhub/SUMIT-Payment-Gateway-for-laravel/issues
- SUMIT Documentation: https://help.sumit.co.il/
- API Documentation: https://app.sumit.co.il/developers/
