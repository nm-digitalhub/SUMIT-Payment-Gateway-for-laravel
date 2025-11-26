<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Pages;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Select;
use Filament\Schemas\Components\TextInput;
use Filament\Schemas\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Schemas\Schema;
use OfficeGuy\LaravelSumitGateway\Services\SettingsService;

class OfficeGuySettings extends Page
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static \UnitEnum|string|null $navigationGroup = 'SUMIT Gateway';
    protected static ?int $navigationSort = 10;

    protected string $view = 'officeguy::filament.pages.officeguy-settings';

    public ?array $data = [];

    protected SettingsService $settingsService;

    public function boot(SettingsService $settingsService): void
    {
        $this->settingsService = $settingsService;
    }

    public function mount(): void
    {
        $this->form->fill(
            $this->settingsService->getEditableSettings()
        );
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema($this->getFormSchema())
            ->statePath('data');
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make('API Credentials')
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    TextInput::make('company_id')
                        ->label('Company ID')
                        ->required()
                        ->numeric(),

                    TextInput::make('private_key')
                        ->label('Private Key')
                        ->password()
                        ->revealable()
                        ->required(),

                    TextInput::make('public_key')
                        ->label('Public Key')
                        ->required(),
                ]),

            Section::make('Environment Settings')
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    Select::make('environment')
                        ->label('Environment')
                        ->options([
                            'www' => 'Production (www)',
                            'dev' => 'Development (dev)',
                            'test' => 'Testing (test)',
                        ])
                        ->required(),

                    Select::make('pci')
                        ->label('PCI Mode')
                        ->options([
                            'no' => 'Simple (PaymentsJS)',
                            'redirect' => 'Redirect',
                            'yes' => 'Advanced (PCI-compliant)',
                        ])
                        ->required(),

                    Toggle::make('testing')
                        ->label('Testing Mode'),
                ]),

            Section::make('Payment Settings')
                ->columnSpanFull()
                ->columns(4)
                ->schema([
                    TextInput::make('max_payments')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(36),

                    Toggle::make('authorize_only'),

                    TextInput::make('authorize_added_percent')
                        ->numeric(),

                    TextInput::make('authorize_minimum_addition')
                        ->numeric(),
                ]),

            Section::make('Document Settings')
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    Toggle::make('draft_document'),
                    Toggle::make('email_document'),
                    Toggle::make('create_order_document'),
                ]),

            Section::make('Tokenization')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Toggle::make('support_tokens'),

                    Select::make('token_param')
                        ->options([
                            '2' => 'J2 Method',
                            '5' => 'J5 Method (Recommended)',
                        ]),
                ]),

            Section::make('Subscriptions')
                ->columnSpanFull()
                ->description('Configure recurring payment settings')
                ->columns(3)
                ->schema([
                    Toggle::make('subscriptions_enabled')
                        ->label('Enable Subscriptions')
                        ->default(true),

                    TextInput::make('subscriptions_default_interval')
                        ->label('Default Interval (Months)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(12)
                        ->default(1),

                    TextInput::make('subscriptions_default_cycles')
                        ->label('Default Cycles')
                        ->numeric()
                        ->placeholder('Unlimited')
                        ->helperText('Leave empty for unlimited'),

                    Toggle::make('subscriptions_allow_pause')
                        ->label('Allow Pause')
                        ->default(true),

                    Toggle::make('subscriptions_retry_failed')
                        ->label('Retry Failed Charges')
                        ->default(true),

                    TextInput::make('subscriptions_max_retries')
                        ->label('Max Retry Attempts')
                        ->numeric()
                        ->default(3),
                ]),

            Section::make('Donations')
                ->columnSpanFull()
                ->description('Configure donation handling')
                ->columns(3)
                ->schema([
                    Toggle::make('donations_enabled')
                        ->label('Enable Donations')
                        ->default(true),

                    Toggle::make('donations_allow_mixed')
                        ->label('Allow Mixed Cart')
                        ->helperText('Allow donations with regular products')
                        ->default(false),

                    Select::make('donations_document_type')
                        ->label('Document Type')
                        ->options([
                            '320' => 'Donation Receipt',
                            '1' => 'Invoice',
                        ])
                        ->default('320'),
                ]),

            Section::make('Multi-Vendor')
                ->columnSpanFull()
                ->description('Configure multi-vendor marketplace support')
                ->columns(3)
                ->schema([
                    Toggle::make('multivendor_enabled')
                        ->label('Enable Multi-Vendor')
                        ->default(false),

                    Toggle::make('multivendor_validate_credentials')
                        ->label('Validate Vendor Credentials')
                        ->default(true),

                    Toggle::make('multivendor_allow_authorize')
                        ->label('Allow Authorize Only')
                        ->helperText('Allow authorize-only for vendor payments')
                        ->default(false),
                ]),

            Section::make('Upsell / CartFlows')
                ->columnSpanFull()
                ->description('Configure upsell payment settings')
                ->columns(3)
                ->schema([
                    Toggle::make('upsell_enabled')
                        ->label('Enable Upsell')
                        ->default(true),

                    Toggle::make('upsell_require_token')
                        ->label('Require Saved Token')
                        ->default(true),

                    TextInput::make('upsell_max_per_order')
                        ->label('Max Upsells Per Order')
                        ->numeric()
                        ->default(5),
                ]),

            Section::make('Additional Features')
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    Toggle::make('bit_enabled'),
                    Toggle::make('logging'),
                    TextInput::make('log_channel'),
                ]),

            Section::make('Public Checkout Page')
                ->columnSpanFull()
                ->description('Configure the public checkout page for payment links')
                ->columns(2)
                ->schema([
                    Toggle::make('enable_public_checkout')
                        ->label('Enable Public Checkout')
                        ->helperText('Allow public access to checkout page via /officeguy/checkout/{id}')
                        ->default(false),

                    TextInput::make('public_checkout_path')
                        ->label('Checkout Path')
                        ->placeholder('checkout/{id}')
                        ->helperText('Custom path for checkout page (default: checkout/{id})')
                        ->default('checkout/{id}'),

                    TextInput::make('payable_model')
                        ->label('Payable Model Class')
                        ->placeholder('App\\Models\\Order')
                        ->helperText('Full class name of your model (e.g., App\\Models\\Order). Model can implement Payable interface OR use field mapping below.')
                        ->columnSpanFull(),
                ]),

            Section::make('Field Mapping (Optional)')
                ->columnSpanFull()
                ->description('Map your model fields to payment fields. Only fill these if your model does NOT implement the Payable interface.')
                ->collapsed()
                ->columns(3)
                ->schema([
                    TextInput::make('field_map_amount')
                        ->label('Amount Field')
                        ->placeholder('total')
                        ->helperText('Field name for payment amount'),

                    TextInput::make('field_map_currency')
                        ->label('Currency Field')
                        ->placeholder('currency')
                        ->helperText('Field name for currency (or leave empty for ILS)'),

                    TextInput::make('field_map_customer_name')
                        ->label('Customer Name Field')
                        ->placeholder('customer_name')
                        ->helperText('Field name for customer name'),

                    TextInput::make('field_map_customer_email')
                        ->label('Customer Email Field')
                        ->placeholder('email')
                        ->helperText('Field name for customer email'),

                    TextInput::make('field_map_customer_phone')
                        ->label('Customer Phone Field')
                        ->placeholder('phone')
                        ->helperText('Field name for customer phone'),

                    TextInput::make('field_map_description')
                        ->label('Description Field')
                        ->placeholder('description')
                        ->helperText('Field name for item description'),
                ]),

            Section::make('Custom Event Webhooks')
                ->columnSpanFull()
                ->description('Configure webhook URLs to receive notifications when events occur. Leave empty to disable.')
                ->collapsed()
                ->columns(2)
                ->schema([
                    TextInput::make('webhook_secret')
                        ->label('Webhook Secret')
                        ->password()
                        ->revealable()
                        ->placeholder('your-secret-key')
                        ->helperText('Secret key for webhook signature verification (X-Webhook-Signature header)')
                        ->columnSpanFull(),

                    TextInput::make('webhook_payment_completed')
                        ->label('Payment Completed URL')
                        ->url()
                        ->placeholder('https://your-app.com/webhooks/payment-completed')
                        ->helperText('Called when a payment is successfully completed'),

                    TextInput::make('webhook_payment_failed')
                        ->label('Payment Failed URL')
                        ->url()
                        ->placeholder('https://your-app.com/webhooks/payment-failed')
                        ->helperText('Called when a payment fails'),

                    TextInput::make('webhook_document_created')
                        ->label('Document Created URL')
                        ->url()
                        ->placeholder('https://your-app.com/webhooks/document-created')
                        ->helperText('Called when a document (invoice/receipt) is created'),

                    TextInput::make('webhook_subscription_created')
                        ->label('Subscription Created URL')
                        ->url()
                        ->placeholder('https://your-app.com/webhooks/subscription-created')
                        ->helperText('Called when a new subscription is created'),

                    TextInput::make('webhook_subscription_charged')
                        ->label('Subscription Charged URL')
                        ->url()
                        ->placeholder('https://your-app.com/webhooks/subscription-charged')
                        ->helperText('Called when a subscription is charged'),

                    TextInput::make('webhook_bit_payment_completed')
                        ->label('Bit Payment Completed URL')
                        ->url()
                        ->placeholder('https://your-app.com/webhooks/bit-completed')
                        ->helperText('Called when a Bit payment is completed'),

                    TextInput::make('webhook_stock_synced')
                        ->label('Stock Synced URL')
                        ->url()
                        ->placeholder('https://your-app.com/webhooks/stock-synced')
                        ->helperText('Called when stock is synchronized'),
                ]),

            Section::make('Customer Merging')
                ->columnSpanFull()
                ->description('Configure automatic customer merging with SUMIT and sync with your local customer model.')
                ->collapsed()
                ->columns(2)
                ->schema([
                    Toggle::make('merge_customers')
                        ->label('Enable Customer Merging')
                        ->helperText('When enabled, SUMIT will automatically merge customers by email/ID to prevent duplicates.')
                        ->default(false),

                    Toggle::make('customer_sync_enabled')
                        ->label('Enable Local Customer Sync')
                        ->helperText('Sync SUMIT customers with your local customer model.')
                        ->default(false),

                    TextInput::make('customer_model')
                        ->label('Customer Model Class')
                        ->placeholder('App\\Models\\User')
                        ->helperText('Full class name of your local customer/user model (e.g., App\\Models\\User or App\\Models\\Customer).')
                        ->columnSpanFull(),

                    Section::make('Customer Field Mapping')
                        ->columnSpanFull()
                        ->description('Map your model fields to SUMIT customer fields. Only fill if using local sync.')
                        ->columns(3)
                        ->schema([
                            TextInput::make('customer_field_email')
                                ->label('Email Field')
                                ->placeholder('email')
                                ->default('email')
                                ->helperText('Field name for email (unique identifier)'),

                            TextInput::make('customer_field_name')
                                ->label('Name Field')
                                ->placeholder('name')
                                ->default('name')
                                ->helperText('Field name for full name'),

                            TextInput::make('customer_field_phone')
                                ->label('Phone Field')
                                ->placeholder('phone')
                                ->helperText('Field name for phone number'),

                            TextInput::make('customer_field_first_name')
                                ->label('First Name Field')
                                ->placeholder('first_name')
                                ->helperText('Field name for first name (if separate)'),

                            TextInput::make('customer_field_last_name')
                                ->label('Last Name Field')
                                ->placeholder('last_name')
                                ->helperText('Field name for last name (if separate)'),

                            TextInput::make('customer_field_company')
                                ->label('Company Field')
                                ->placeholder('company')
                                ->helperText('Field name for company name'),

                            TextInput::make('customer_field_address')
                                ->label('Address Field')
                                ->placeholder('address')
                                ->helperText('Field name for address'),

                            TextInput::make('customer_field_city')
                                ->label('City Field')
                                ->placeholder('city')
                                ->helperText('Field name for city'),

                            TextInput::make('customer_field_sumit_id')
                                ->label('SUMIT ID Field')
                                ->placeholder('sumit_customer_id')
                                ->helperText('Field to store SUMIT customer ID (create this column in your table)'),
                        ]),
                ]),

            Section::make('Route Configuration')
                ->columnSpanFull()
                ->description('Customize all package endpoints. Changes require cache clear to take effect. Run: php artisan route:clear')
                ->collapsed()
                ->columns(2)
                ->schema([
                    TextInput::make('routes_prefix')
                        ->label('Route Prefix')
                        ->placeholder('officeguy')
                        ->default('officeguy')
                        ->helperText('Base prefix for all routes (e.g., "officeguy" → /officeguy/...)')
                        ->columnSpanFull(),

                    Section::make('Payment Callbacks')
                        ->columnSpanFull()
                        ->description('Endpoints that receive callbacks from SUMIT after payment processing')
                        ->columns(2)
                        ->schema([
                            TextInput::make('routes_card_callback')
                                ->label('Card Callback Path')
                                ->placeholder('callback/card')
                                ->default('callback/card')
                                ->helperText('Redirect return after card payment → /{prefix}/callback/card'),

                            TextInput::make('routes_bit_webhook')
                                ->label('Bit Webhook Path')
                                ->placeholder('webhook/bit')
                                ->default('webhook/bit')
                                ->helperText('Bit payment IPN webhook → /{prefix}/webhook/bit'),

                            TextInput::make('routes_sumit_webhook')
                                ->label('SUMIT Webhook Path')
                                ->placeholder('webhook/sumit')
                                ->default('webhook/sumit')
                                ->helperText('Incoming webhooks from SUMIT → /{prefix}/webhook/sumit'),
                        ]),

                    Section::make('Checkout Endpoints')
                        ->columnSpanFull()
                        ->description('Endpoints for payment processing')
                        ->columns(2)
                        ->schema([
                            Toggle::make('routes_enable_checkout_endpoint')
                                ->label('Enable Checkout Charge Endpoint')
                                ->helperText('Enable the checkout/charge endpoint for API payments')
                                ->default(false)
                                ->columnSpanFull(),

                            TextInput::make('routes_checkout_charge')
                                ->label('Checkout Charge Path')
                                ->placeholder('checkout/charge')
                                ->default('checkout/charge')
                                ->helperText('Direct charge endpoint → /{prefix}/checkout/charge'),

                            TextInput::make('routes_document_download')
                                ->label('Document Download Path')
                                ->placeholder('documents/{document}')
                                ->default('documents/{document}')
                                ->helperText('Document download → /{prefix}/documents/{id}'),
                        ]),

                    Section::make('Redirect Routes')
                        ->columnSpanFull()
                        ->description('Named routes for redirection after payment')
                        ->columns(2)
                        ->schema([
                            TextInput::make('routes_success')
                                ->label('Success Route Name')
                                ->placeholder('checkout.success')
                                ->default('checkout.success')
                                ->helperText('Named route to redirect after successful payment'),

                            TextInput::make('routes_failed')
                                ->label('Failed Route Name')
                                ->placeholder('checkout.failed')
                                ->default('checkout.failed')
                                ->helperText('Named route to redirect after failed payment'),
                        ]),
                ]),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('reset')
                ->label('Reset to Defaults')
                ->color('gray')
                ->requiresConfirmation()
                ->action(function () {
                    $this->settingsService->resetAllToDefaults();
                    $this->mount();

                    Notification::make()
                        ->title('Settings reset to defaults')
                        ->success()
                        ->send();
                }),

            \Filament\Actions\Action::make('save')
                ->label('Save Settings')
                ->color('primary')
                ->action(fn () => $this->save()),
        ];
    }

    public function save(): void
    {
        try {
            $this->settingsService->setMany($this->form->getState());

            Notification::make()
                ->title('Settings saved')
                ->body('Changes are now active')
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
