<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Pages;

use Filament\Forms\Components\Section;
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

    public function form(Schema $form): Schema
    {
        return $form
            ->schema($this->getFormSchema())
            ->statePath('data');
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make('API Credentials')
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
                ->columns(3)
                ->schema([
                    Toggle::make('draft_document'),
                    Toggle::make('email_document'),
                    Toggle::make('create_order_document'),
                ]),

            Section::make('Tokenization')
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
                ->columns(3)
                ->schema([
                    Toggle::make('bit_enabled'),
                    Toggle::make('logging'),
                    TextInput::make('log_channel'),
                ]),

            Section::make('Public Checkout Page')
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
                        ->helperText('Full class name of your Payable model (e.g., App\\Models\\Order, App\\Models\\Product)')
                        ->columnSpanFull(),
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
