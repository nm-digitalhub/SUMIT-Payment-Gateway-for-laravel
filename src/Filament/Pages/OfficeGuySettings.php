<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;

class OfficeGuySettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'officeguy::filament.pages.officeguy-settings';

    protected static ?string $navigationLabel = 'Gateway Settings';

    protected static string|\UnitEnum|null $navigationGroup = 'SUMIT Gateway';

    protected static ?int $navigationSort = 10;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'company_id' => config('officeguy.company_id'),
            'private_key' => config('officeguy.private_key'),
            'public_key' => config('officeguy.public_key'),
            'environment' => config('officeguy.environment', 'www'),
            'pci_mode' => config('officeguy.pci_mode', 'no'),
            'testing' => config('officeguy.testing', false),
            'max_payments' => config('officeguy.max_payments', 1),
            'authorize_only' => config('officeguy.authorize_only', false),
            'draft_document' => config('officeguy.draft_document', false),
            'email_document' => config('officeguy.email_document', true),
            'create_order_document' => config('officeguy.create_order_document', false),
            'support_tokens' => config('officeguy.support_tokens', true),
            'token_param' => config('officeguy.token_param', '5'),
            'bit_enabled' => config('officeguy.bit_enabled', false),
            'logging' => config('officeguy.logging', true),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('API Credentials')
                    ->description('Your SUMIT Gateway API credentials')
                    ->schema([
                        Forms\Components\TextInput::make('company_id')
                            ->label('Company ID')
                            ->required()
                            ->disabled()
                            ->helperText('Set via OFFICEGUY_COMPANY_ID environment variable'),
                        Forms\Components\TextInput::make('private_key')
                            ->label('Private Key (API Key)')
                            ->required()
                            ->disabled()
                            ->password()
                            ->revealable()
                            ->helperText('Set via OFFICEGUY_PRIVATE_KEY environment variable'),
                        Forms\Components\TextInput::make('public_key')
                            ->label('Public Key')
                            ->required()
                            ->disabled()
                            ->helperText('Set via OFFICEGUY_PUBLIC_KEY environment variable'),
                    ])->columns(3),

                Forms\Components\Section::make('Environment Settings')
                    ->description('Gateway environment configuration')
                    ->schema([
                        Forms\Components\Select::make('environment')
                            ->label('Environment')
                            ->options([
                                'www' => 'Production (www)',
                                'dev' => 'Development (dev)',
                                'test' => 'Testing (test)',
                            ])
                            ->required()
                            ->disabled()
                            ->helperText('Set via OFFICEGUY_ENVIRONMENT environment variable'),
                        Forms\Components\Select::make('pci_mode')
                            ->label('PCI Mode')
                            ->options([
                                'no' => 'Simple (PaymentsJS)',
                                'redirect' => 'Redirect',
                                'yes' => 'Advanced (PCI-compliant)',
                            ])
                            ->required()
                            ->disabled()
                            ->helperText('Set via OFFICEGUY_PCI_MODE environment variable'),
                        Forms\Components\Toggle::make('testing')
                            ->label('Testing Mode')
                            ->disabled()
                            ->helperText('Set via OFFICEGUY_TESTING environment variable'),
                    ])->columns(3),

                Forms\Components\Section::make('Payment Settings')
                    ->description('Configure payment options')
                    ->schema([
                        Forms\Components\TextInput::make('max_payments')
                            ->label('Maximum Installments')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(36)
                            ->disabled()
                            ->helperText('Set via OFFICEGUY_MAX_PAYMENTS environment variable'),
                        Forms\Components\Toggle::make('authorize_only')
                            ->label('Authorize Only (No Capture)')
                            ->disabled()
                            ->helperText('Set via OFFICEGUY_AUTHORIZE_ONLY environment variable'),
                    ])->columns(2),

                Forms\Components\Section::make('Document Settings')
                    ->description('Invoice and receipt configuration')
                    ->schema([
                        Forms\Components\Toggle::make('draft_document')
                            ->label('Create Draft Documents')
                            ->disabled()
                            ->helperText('Set via OFFICEGUY_DRAFT_DOCUMENT environment variable'),
                        Forms\Components\Toggle::make('email_document')
                            ->label('Email Documents to Customers')
                            ->disabled()
                            ->helperText('Set via OFFICEGUY_EMAIL_DOCUMENT environment variable'),
                        Forms\Components\Toggle::make('create_order_document')
                            ->label('Create Order Documents')
                            ->disabled()
                            ->helperText('Set via OFFICEGUY_CREATE_ORDER_DOCUMENT environment variable'),
                    ])->columns(3),

                Forms\Components\Section::make('Tokenization')
                    ->description('Credit card tokenization settings')
                    ->schema([
                        Forms\Components\Toggle::make('support_tokens')
                            ->label('Support Saved Cards')
                            ->disabled()
                            ->helperText('Set via OFFICEGUY_SUPPORT_TOKENS environment variable'),
                        Forms\Components\Select::make('token_param')
                            ->label('Token Method')
                            ->options([
                                '2' => 'J2 Method',
                                '5' => 'J5 Method (Recommended)',
                            ])
                            ->disabled()
                            ->helperText('Set via OFFICEGUY_TOKEN_PARAM environment variable'),
                    ])->columns(2),

                Forms\Components\Section::make('Additional Features')
                    ->description('Extra payment methods and features')
                    ->schema([
                        Forms\Components\Toggle::make('bit_enabled')
                            ->label('Enable Bit Payments')
                            ->disabled()
                            ->helperText('Set via OFFICEGUY_BIT_ENABLED environment variable'),
                        Forms\Components\Toggle::make('logging')
                            ->label('Enable Logging')
                            ->disabled()
                            ->helperText('Set via OFFICEGUY_LOGGING environment variable'),
                    ])->columns(2),

                Forms\Components\Section::make('Important Notice')
                    ->description('All settings are read from environment variables (.env file) and cannot be modified through this interface. This page is for viewing current configuration only.')
                    ->schema([
                        Forms\Components\Placeholder::make('notice')
                            ->content('To modify these settings, update your .env file and restart your application.')
                            ->extraAttributes(['class' => 'text-sm text-gray-600']),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
