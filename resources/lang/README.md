# SUMIT Payment Gateway - Translation Files

This directory contains translation files for the SUMIT Payment Gateway package.

## Available Languages

- **Hebrew (עברית)**: `he/officeguy.php` - Primary language
- **English**: `en/officeguy.php` - Secondary language

## Usage

### In Filament Resources

```php
use Filament\Resources\Resource;

class TransactionResource extends Resource
{
    // Use translation keys for navigation
    protected static ?string $navigationLabel = __('officeguy::officeguy.nav.transactions');
    protected static ?string $navigationGroup = __('officeguy::officeguy.nav.group');

    // Use in form schemas
    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('amount')
                ->label(__('officeguy::officeguy.fields.amount')),

            Select::make('status')
                ->label(__('officeguy::officeguy.fields.status'))
                ->options([
                    'pending' => __('officeguy::officeguy.status.pending'),
                    'completed' => __('officeguy::officeguy.status.completed'),
                    'failed' => __('officeguy::officeguy.status.failed'),
                ]),
        ]);
    }
}
```

### In Blade Views

```blade
{{-- Display translated messages --}}
<div class="alert alert-success">
    {{ __('officeguy::officeguy.messages.payment_success') }}
</div>

{{-- Display field labels --}}
<label>{{ __('officeguy::officeguy.fields.amount') }}</label>

{{-- Display status labels --}}
<span class="badge">
    {{ __('officeguy::officeguy.status.completed') }}
</span>
```

### In Controllers/Services

```php
use Filament\Notifications\Notification;

class PaymentService
{
    public function processPayment($data)
    {
        // ... payment logic

        // Success notification
        Notification::make()
            ->title(__('officeguy::officeguy.messages.payment_success'))
            ->success()
            ->send();

        // Or error notification
        Notification::make()
            ->title(__('officeguy::officeguy.messages.payment_failed'))
            ->danger()
            ->send();
    }
}
```

### Settings Page Usage

The `OfficeGuySettings.php` page can use translations like this:

```php
Section::make(__('officeguy::officeguy.settings.api_credentials'))
    ->schema([
        TextInput::make('company_id')
            ->label(__('officeguy::officeguy.settings.company_id')),

        TextInput::make('private_key')
            ->label(__('officeguy::officeguy.settings.private_key'))
            ->helperText(__('officeguy::officeguy.settings.private_key_help')),
    ]),
```

## Translation Structure

### Main Categories

1. **nav** - Navigation labels (sidebar, menu items)
2. **settings** - Settings page labels and descriptions
3. **messages** - Success/error messages and notifications
4. **status** - Status labels (pending, completed, failed, etc.)
5. **fields** - Common field labels
6. **actions** - Button and action labels
7. **validation** - Validation error messages

### Translation Key Naming Convention

- Use descriptive, hierarchical keys
- Format: `category.subcategory.key`
- Examples:
  - `nav.transactions` - Navigation label
  - `settings.api_credentials` - Settings section
  - `messages.payment_success` - Success message
  - `fields.amount` - Field label
  - `actions.save` - Button text

## Publishing Translations

Users can publish the translation files to customize them:

```bash
php artisan vendor:publish --tag=officeguy-lang
```

This will copy the files to:
```
lang/vendor/officeguy/
├── he/
│   └── officeguy.php
└── en/
    └── officeguy.php
```

Published files take priority over package translations.

## Adding New Translations

### Step 1: Add to Translation Files

Add the key to both `he/officeguy.php` and `en/officeguy.php`:

```php
// he/officeguy.php
'messages' => [
    'refund_success' => 'ההחזר בוצע בהצלחה',
],

// en/officeguy.php
'messages' => [
    'refund_success' => 'Refund processed successfully',
],
```

### Step 2: Use in Code

```php
Notification::make()
    ->title(__('officeguy::officeguy.messages.refund_success'))
    ->success()
    ->send();
```

## Language Detection

Laravel automatically detects the application language based on:

1. User's session locale
2. Application default locale (`config('app.locale')`)
3. Fallback locale (`config('app.fallback_locale')`)

To change the active language:

```php
// In AppServiceProvider or middleware
app()->setLocale('he'); // Hebrew
app()->setLocale('en'); // English
```

## Best Practices

1. **Always use translation keys** - Never hardcode text in Filament resources or views
2. **Provide both languages** - Ensure all keys exist in both Hebrew and English
3. **Use descriptive keys** - Make keys self-explanatory
4. **Keep structure consistent** - Follow the existing hierarchy
5. **Add help text** - Provide `_help` suffixes for field descriptions

## RTL Support

Hebrew is RTL (right-to-left). Ensure your views support RTL:

```blade
<div dir="{{ app()->getLocale() === 'he' ? 'rtl' : 'ltr' }}">
    {{ __('officeguy::officeguy.messages.payment_success') }}
</div>
```

Filament automatically handles RTL for Hebrew locales.

## Translation Coverage

Current coverage includes:

- ✅ Navigation labels (Admin & Client panels)
- ✅ Settings page (74 settings with descriptions)
- ✅ Payment messages & notifications
- ✅ Field labels (transactions, tokens, documents)
- ✅ Status labels (pending, completed, failed, etc.)
- ✅ Action buttons (save, cancel, refund, etc.)
- ✅ Validation messages
- ✅ Webhook configuration (v1.2.0+)

## Need Help?

For questions or to request additional translations:
- **Email**: info@nm-digitalhub.com
- **GitHub**: https://github.com/nm-digitalhub/SUMIT-Payment-Gateway-for-laravel

---

**Last Updated**: 2025-11-27
**Package Version**: v1.2.3+
