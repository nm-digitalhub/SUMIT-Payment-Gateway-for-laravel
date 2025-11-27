<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Pages;

use Filament\Schemas\Components\Section;       
  
  use Filament\Forms\Components\Select;
  use Filament\Forms\Components\TextInput;
  use Filament\Forms\Components\Toggle;
  use Filament\Notifications\Notification;
  use Filament\Pages\Page;
  use Filament\Forms\Concerns\InteractsWithForms;
  use Filament\Schemas\Schema;                 

use OfficeGuy\LaravelSumitGateway\Services\SettingsService;

class OfficeGuySettings extends Page
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string|\UnitEnum|null $navigationGroup = 'SUMIT Gateway';
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
            Section::make(__('officeguy::officeguy.settings.api_credentials'))
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    TextInput::make('company_id')
                        ->label(__('officeguy::officeguy.settings.company_id'))
                        ->required()
                        ->numeric(),

                    TextInput::make('private_key')
                        ->label(__('officeguy::officeguy.settings.private_key'))
                        ->password()
                        ->revealable()
                        ->required(),

                    TextInput::make('public_key')
                        ->label(__('officeguy::officeguy.settings.public_key'))
                        ->required(),
                ]),

            Section::make(__('officeguy::officeguy.settings.environment_settings'))
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    Select::make('environment')
                        ->label(__('officeguy::officeguy.settings.environment'))
                        ->options([
                            'www' => __('officeguy::officeguy.settings.environment_production'),
                            'dev' => __('officeguy::officeguy.settings.environment_development'),
                            'test' => __('officeguy::officeguy.settings.environment_testing'),
                        ])
                        ->required(),

                    Select::make('pci')
                        ->label(__('officeguy::officeguy.settings.pci_mode'))
                        ->options([
                            'no' => __('officeguy::officeguy.settings.pci_simple'),
                            'redirect' => __('officeguy::officeguy.settings.pci_redirect'),
                            'yes' => __('officeguy::officeguy.settings.pci_advanced'),
                        ])
                        ->required(),

                    Toggle::make('testing')
                        ->label(__('officeguy::officeguy.settings.testing_mode')),
                ]),

            Section::make(__('officeguy::officeguy.settings.payment_configuration'))
                ->columnSpanFull()
                ->columns(4)
                ->schema([
                    TextInput::make('max_payments')
                        ->label(__('officeguy::officeguy.settings.max_payments'))
                        ->helperText(__('officeguy::officeguy.settings.max_payments_help'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(36),

                    Toggle::make('authorize_only')
                        ->label(__('officeguy::officeguy.settings.authorize_only'))
                        ->helperText(__('officeguy::officeguy.settings.authorize_only_help')),

                    TextInput::make('authorize_added_percent')
                        ->label(__('officeguy::officeguy.settings.authorize_added_percent'))
                        ->helperText(__('officeguy::officeguy.settings.authorize_added_percent_help'))
                        ->numeric(),

                    TextInput::make('authorize_minimum_addition')
                        ->label(__('officeguy::officeguy.settings.authorize_minimum_addition'))
                        ->helperText(__('officeguy::officeguy.settings.authorize_minimum_addition_help'))
                        ->numeric(),
                ]),

            Section::make(__('officeguy::officeguy.settings.document_settings'))
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    Toggle::make('draft_document')
                        ->label(__('officeguy::officeguy.settings.draft_document'))
                        ->helperText(__('officeguy::officeguy.settings.draft_document_help')),
                    Toggle::make('email_document')
                        ->label(__('officeguy::officeguy.settings.email_document'))
                        ->helperText(__('officeguy::officeguy.settings.email_document_help')),
                    Toggle::make('create_order_document')
                        ->label(__('officeguy::officeguy.settings.create_order_document'))
                        ->helperText(__('officeguy::officeguy.settings.create_order_document_help')),
                ]),

            Section::make(__('officeguy::officeguy.settings.token_configuration'))
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Toggle::make('support_tokens')
                        ->label(__('officeguy::officeguy.settings.support_tokens'))
                        ->helperText(__('officeguy::officeguy.settings.support_tokens_help')),

                    Select::make('token_param')
                        ->label(__('officeguy::officeguy.settings.token_param'))
                        ->helperText(__('officeguy::officeguy.settings.token_param_help'))
                        ->options([
                            '2' => 'J2 (חד פעמי)',
                            '5' => 'J5 (רב פעמי - מומלץ)',
                        ]),
                ]),

            Section::make(__('officeguy::officeguy.settings.subscriptions'))
                ->columnSpanFull()
                ->description(__('officeguy::officeguy.settings.subscriptions'))
                ->columns(3)
                ->schema([
                    Toggle::make('subscriptions_enabled')
                        ->label(__('officeguy::officeguy.settings.subscriptions_enabled'))
                        ->default(true),

                    TextInput::make('subscriptions_default_interval')
                        ->label(__('officeguy::officeguy.settings.subscriptions_default_interval'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(12)
                        ->default(1),

                    TextInput::make('subscriptions_default_cycles')
                        ->label(__('officeguy::officeguy.settings.subscriptions_default_cycles'))
                        ->numeric()
                        ->placeholder('ללא הגבלה')
                        ->helperText('השאר ריק ללא הגבלה'),

                    Toggle::make('subscriptions_allow_pause')
                        ->label(__('officeguy::officeguy.settings.subscriptions_allow_pause'))
                        ->default(true),

                    Toggle::make('subscriptions_retry_failed')
                        ->label(__('officeguy::officeguy.settings.subscriptions_retry_failed'))
                        ->default(true),

                    TextInput::make('subscriptions_max_retries')
                        ->label(__('officeguy::officeguy.settings.subscriptions_max_retries'))
                        ->numeric()
                        ->default(3),
                ]),

            Section::make(__('officeguy::officeguy.settings.donations'))
                ->columnSpanFull()
                ->description(__('officeguy::officeguy.settings.donations'))
                ->columns(3)
                ->schema([
                    Toggle::make('donations_enabled')
                        ->label(__('officeguy::officeguy.settings.donations_enabled'))
                        ->default(true),

                    Toggle::make('donations_allow_mixed')
                        ->label(__('officeguy::officeguy.settings.donations_allow_mixed'))
                        ->helperText('אפשר תרומות יחד עם מוצרים רגילים')
                        ->default(false),

                    Select::make('donations_document_type')
                        ->label(__('officeguy::officeguy.settings.donations_default_document_type'))
                        ->options([
                            '320' => 'קבלה לתרומה',
                            '1' => 'חשבונית',
                        ])
                        ->default('320'),
                ]),

            Section::make(__('officeguy::officeguy.settings.multivendor'))
                ->columnSpanFull()
                ->description(__('officeguy::officeguy.settings.multivendor'))
                ->columns(3)
                ->schema([
                    Toggle::make('multivendor_enabled')
                        ->label(__('officeguy::officeguy.settings.multivendor_enabled'))
                        ->default(false),

                    Toggle::make('multivendor_validate_credentials')
                        ->label('Validate Vendor Credentials')
                        ->default(true),

                    Toggle::make('multivendor_allow_authorize')
                        ->label(__('officeguy::officeguy.settings.multivendor_allow_authorize'))
                        ->helperText(__('officeguy::officeguy.settings.multivendor_allow_authorize_help'))
                        ->default(false),
                ]),

            Section::make(__('officeguy::officeguy.settings.upsell'))
                ->columnSpanFull()
                ->description(__('officeguy::officeguy.settings.upsell'))
                ->columns(3)
                ->schema([
                    Toggle::make('upsell_enabled')
                        ->label(__('officeguy::officeguy.settings.upsell_enabled'))
                        ->default(true),

                    Toggle::make('upsell_require_token')
                        ->label(__('officeguy::officeguy.settings.upsell_require_token'))
                        ->default(true),

                    TextInput::make('upsell_max_per_order')
                        ->label(__('officeguy::officeguy.settings.upsell_max_per_order'))
                        ->numeric()
                        ->default(5),
                ]),

            Section::make('תכונות נוספות')
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    Toggle::make('bit_enabled')
                        ->label(__('officeguy::officeguy.settings.bit_enabled'))
                        ->helperText(__('officeguy::officeguy.settings.bit_enabled_help')),
                    Toggle::make('logging')
                        ->label(__('officeguy::officeguy.settings.logging_enabled'))
                        ->helperText(__('officeguy::officeguy.settings.logging_enabled_help')),
                    TextInput::make('log_channel')
                        ->label(__('officeguy::officeguy.settings.log_channel'))
                        ->helperText(__('officeguy::officeguy.settings.log_channel_help'))
                        ->placeholder('stack'),
                ]),

            Section::make(__('officeguy::officeguy.settings.public_checkout'))
                ->columnSpanFull()
                ->description(__('officeguy::officeguy.settings.public_checkout'))
                ->columns(2)
                ->schema([
                    Toggle::make('enable_public_checkout')
                        ->label(__('officeguy::officeguy.settings.enable_public_checkout'))
                        ->helperText(__('officeguy::officeguy.settings.enable_public_checkout_help'))
                        ->default(false),

                    TextInput::make('public_checkout_path')
                        ->label(__('officeguy::officeguy.settings.public_checkout_path'))
                        ->placeholder('checkout/{id}')
                        ->helperText('נתיב מותאם אישית לעמוד התשלום (ברירת מחדל: checkout/{id})')
                        ->default('checkout/{id}'),

                    TextInput::make('payable_model')
                        ->label(__('officeguy::officeguy.settings.payable_model'))
                        ->placeholder('App\\Models\\Order')
                        ->helperText('שם המחלקה המלא של המודל (לדוגמה: App\\Models\\Order). המודל יכול ליישם את ממשק Payable או להשתמש במיפוי שדות למטה.')
                        ->columnSpanFull(),
                ]),

            Section::make('מיפוי שדות (אופציונלי)')
                ->columnSpanFull()
                ->description('מפה את שדות המודל לשדות תשלום. מלא רק אם המודל שלך לא מיישם את ממשק Payable.')
                ->collapsed()
                ->columns(3)
                ->schema([
                    TextInput::make('field_map_amount')
                        ->label(__('officeguy::officeguy.settings.field_map_amount'))
                        ->placeholder('total')
                        ->helperText('שם שדה לסכום התשלום'),

                    TextInput::make('field_map_currency')
                        ->label(__('officeguy::officeguy.settings.field_map_currency'))
                        ->placeholder('currency')
                        ->helperText('שם שדה למטבע (או השאר ריק עבור ₪)'),

                    TextInput::make('field_map_customer_name')
                        ->label(__('officeguy::officeguy.settings.field_map_customer_name'))
                        ->placeholder('customer_name')
                        ->helperText('שם שדה לשם לקוח'),

                    TextInput::make('field_map_customer_email')
                        ->label(__('officeguy::officeguy.settings.field_map_customer_email'))
                        ->placeholder('email')
                        ->helperText('שם שדה לאימייל לקוח'),

                    TextInput::make('field_map_customer_phone')
                        ->label(__('officeguy::officeguy.settings.field_map_customer_phone'))
                        ->placeholder('phone')
                        ->helperText('שם שדה לטלפון לקוח'),

                    TextInput::make('field_map_description')
                        ->label(__('officeguy::officeguy.settings.field_map_description'))
                        ->placeholder('description')
                        ->helperText('שם שדה לתיאור פריט'),
                ]),

            Section::make(__('officeguy::officeguy.settings.custom_webhooks'))
                ->columnSpanFull()
                ->description(__('officeguy::officeguy.settings.custom_webhooks'))
                ->collapsed()
                ->columns(2)
                ->schema([
                    TextInput::make('webhook_secret')
                        ->label(__('officeguy::officeguy.settings.webhook_secret'))
                        ->password()
                        ->revealable()
                        ->placeholder('your-secret-key')
                        ->helperText('Secret key for webhook signature verification (X-Webhook-Signature header)')
                        ->columnSpanFull(),

                    TextInput::make('webhook_payment_completed')
                        ->label(__('officeguy::officeguy.settings.webhook_payment_completed'))
                        ->url()
                        ->placeholder('https://your-app.com/webhooks/payment-completed')
                        ->helperText('Called when a payment is successfully completed'),

                    TextInput::make('webhook_payment_failed')
                        ->label(__('officeguy::officeguy.settings.webhook_payment_failed'))
                        ->url()
                        ->placeholder('https://your-app.com/webhooks/payment-failed')
                        ->helperText('Called when a payment fails'),

                    TextInput::make('webhook_document_created')
                        ->label(__('officeguy::officeguy.settings.webhook_document_created'))
                        ->url()
                        ->placeholder('https://your-app.com/webhooks/document-created')
                        ->helperText('Called when a document (invoice/receipt) is created'),

                    TextInput::make('webhook_subscription_created')
                        ->label(__('officeguy::officeguy.settings.webhook_subscription_created'))
                        ->url()
                        ->placeholder('https://your-app.com/webhooks/subscription-created')
                        ->helperText('Called when a new subscription is created'),

                    TextInput::make('webhook_subscription_charged')
                        ->label(__('officeguy::officeguy.settings.webhook_subscription_charged'))
                        ->url()
                        ->placeholder('https://your-app.com/webhooks/subscription-charged')
                        ->helperText('Called when a subscription is charged'),

                    TextInput::make('webhook_bit_payment_completed')
                        ->label(__('officeguy::officeguy.settings.webhook_bit_payment_completed'))
                        ->url()
                        ->placeholder('https://your-app.com/webhooks/bit-completed')
                        ->helperText('Called when a Bit payment is completed'),

                    TextInput::make('webhook_stock_synced')
                        ->label(__('officeguy::officeguy.settings.webhook_stock_synced'))
                        ->url()
                        ->placeholder('https://your-app.com/webhooks/stock-synced')
                        ->helperText('Called when stock is synchronized'),
                ]),

            Section::make(__('officeguy::officeguy.settings.webhook_configuration'))
                ->columnSpanFull()
                ->description(__('officeguy::officeguy.settings.webhook_configuration_desc'))
                ->collapsed()
                ->columns(2)
                ->schema([
                    Toggle::make('webhook_async')
                        ->label(__('officeguy::officeguy.settings.webhook_async'))
                        ->helperText(__('officeguy::officeguy.settings.webhook_async_help'))
                        ->default(true)
                        ->columnSpanFull(),

                    TextInput::make('webhook_queue')
                        ->label(__('officeguy::officeguy.settings.webhook_queue'))
                        ->placeholder('default')
                        ->helperText(__('officeguy::officeguy.settings.webhook_queue_help'))
                        ->default('default'),

                    TextInput::make('webhook_max_tries')
                        ->label(__('officeguy::officeguy.settings.webhook_max_tries'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(10)
                        ->helperText(__('officeguy::officeguy.settings.webhook_max_tries_help'))
                        ->default(3),

                    TextInput::make('webhook_timeout')
                        ->label(__('officeguy::officeguy.settings.webhook_timeout'))
                        ->numeric()
                        ->minValue(5)
                        ->maxValue(300)
                        ->helperText(__('officeguy::officeguy.settings.webhook_timeout_help'))
                        ->default(30),

                    Toggle::make('webhook_verify_ssl')
                        ->label(__('officeguy::officeguy.settings.webhook_verify_ssl'))
                        ->helperText(__('officeguy::officeguy.settings.webhook_verify_ssl_help'))
                        ->default(true),
                ]),

            Section::make(__('officeguy::officeguy.settings.customer_management'))
                ->columnSpanFull()
                ->description(__('officeguy::officeguy.settings.customer_management_desc'))
                ->collapsed()
                ->columns(2)
                ->schema([
                    Toggle::make('customer_merging_enabled')
                        ->label(__('officeguy::officeguy.settings.customer_merging_enabled'))
                        ->helperText(__('officeguy::officeguy.settings.customer_merging_enabled_help'))
                        ->default(false)
                        ->columnSpanFull(),

                    Toggle::make('customer_local_sync_enabled')
                        ->label(__('officeguy::officeguy.settings.customer_local_sync_enabled'))
                        ->helperText(__('officeguy::officeguy.settings.customer_local_sync_enabled_help'))
                        ->default(false)
                        ->columnSpanFull(),

                    TextInput::make('customer_model_class')
                        ->label(__('officeguy::officeguy.settings.customer_model_class'))
                        ->placeholder('App\\Models\\Client')
                        ->helperText(__('officeguy::officeguy.settings.customer_model_class_help'))
                        ->columnSpanFull(),

                    Section::make('Customer Field Mapping')
                        ->columnSpanFull()
                        ->description('Map your model fields to SUMIT customer fields. Only fill if using local sync.')
                        ->columns(3)
                        ->schema([
                            TextInput::make('customer_field_email')
                                ->label(__('officeguy::officeguy.settings.customer_field_email'))
                                ->placeholder('email')
                                ->default('email')
                                ->helperText('Field name for email (unique identifier)'),

                            TextInput::make('customer_field_name')
                                ->label(__('officeguy::officeguy.settings.customer_field_name'))
                                ->placeholder('name')
                                ->default('name')
                                ->helperText('Field name for full name'),

                            TextInput::make('customer_field_phone')
                                ->label(__('officeguy::officeguy.settings.customer_field_phone'))
                                ->placeholder('phone')
                                ->helperText('Field name for phone number'),

                            TextInput::make('customer_field_first_name')
                                ->label(__('officeguy::officeguy.settings.customer_field_first_name'))
                                ->placeholder('first_name')
                                ->helperText('Field name for first name (if separate)'),

                            TextInput::make('customer_field_last_name')
                                ->label(__('officeguy::officeguy.settings.customer_field_last_name'))
                                ->placeholder('last_name')
                                ->helperText('Field name for last name (if separate)'),

                            TextInput::make('customer_field_company')
                                ->label(__('officeguy::officeguy.settings.customer_field_company'))
                                ->placeholder('company')
                                ->helperText('Field name for company name'),

                            TextInput::make('customer_field_address')
                                ->label(__('officeguy::officeguy.settings.customer_field_address'))
                                ->placeholder('address')
                                ->helperText('Field name for address'),

                            TextInput::make('customer_field_city')
                                ->label(__('officeguy::officeguy.settings.customer_field_city'))
                                ->placeholder('city')
                                ->helperText('Field name for city'),

                            TextInput::make('customer_field_sumit_id')
                                ->label(__('officeguy::officeguy.settings.customer_field_sumit_id'))
                                ->placeholder('sumit_customer_id')
                                ->helperText('Field to store SUMIT customer ID (create this column in your table)'),
                        ]),
                ]),

            Section::make(__('officeguy::officeguy.settings.route_configuration'))
                ->columnSpanFull()
                ->description(__('officeguy::officeguy.settings.route_configuration'))
                ->collapsed()
                ->columns(2)
                ->schema([
                    TextInput::make('routes_prefix')
                        ->label(__('officeguy::officeguy.settings.routes_prefix'))
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
            \OfficeGuy\LaravelSumitGateway\Filament\Actions\CreatePayableMappingAction::make(),

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

    protected function getFooterWidgets(): array
    {
        return [
            \OfficeGuy\LaravelSumitGateway\Filament\Widgets\PayableMappingsTableWidget::class,
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
