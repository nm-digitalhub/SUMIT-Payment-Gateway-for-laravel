<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use OfficeGuy\LaravelSumitGateway\Settings\SumitSettings;

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
        $settings = app(SumitSettings::class);
        $this->form->fill([
            'company_id' => $settings->company_id,
            'private_key' => $settings->private_key,
            'public_key' => $settings->public_key,
            'environment' => $settings->environment,
            'pci' => $settings->pci,
            'testing' => $settings->testing,
            'max_payments' => $settings->max_payments,
            'authorize_only' => $settings->authorize_only,
            'authorize_added_percent' => $settings->authorize_added_percent,
            'authorize_minimum_addition' => $settings->authorize_minimum_addition,
            'draft_document' => $settings->draft_document,
            'email_document' => $settings->email_document,
            'create_order_document' => $settings->create_order_document,
            'support_tokens' => $settings->support_tokens,
            'token_param' => $settings->token_param,
            'bit_enabled' => $settings->bit_enabled,
            'logging' => $settings->logging,
            'log_channel' => $settings->log_channel,
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
                            ->numeric()
                            ->helperText('Value stored via Spatie Settings (no .env needed)'),
                        Forms\Components\TextInput::make('private_key')
                            ->label('Private Key (API Key)')
                            ->required()
                            ->password()
                            ->revealable()
                            ->helperText('Value stored via Spatie Settings'),
                        Forms\Components\TextInput::make('public_key')
                            ->label('Public Key')
                            ->required()
                            ->helperText('Value stored via Spatie Settings'),
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
                            ->helperText('Controls API base URL'),
                        Forms\Components\Select::make('pci')
                            ->label('PCI Mode')
                            ->options([
                                'no' => 'Simple (PaymentsJS)',
                                'redirect' => 'Redirect',
                                'yes' => 'Advanced (PCI-compliant)',
                            ])
                            ->required()
                            ->helperText('Choose according to site PCI level'),
                        Forms\Components\Toggle::make('testing')
                            ->label('Testing Mode')
                            ->helperText('AuthoriseOnly defaults to true when testing'),
                    ])->columns(3),

                Forms\Components\Section::make('Payment Settings')
                    ->description('Configure payment options')
                    ->schema([
                        Forms\Components\TextInput::make('max_payments')
                            ->label('Maximum Installments')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(36)
                            ->helperText('Maximum allowed installments'),
                        Forms\Components\Toggle::make('authorize_only')
                            ->label('Authorize Only (No Capture)')
                            ->helperText('If enabled, capture handled separately'),
                        Forms\Components\TextInput::make('authorize_added_percent')
                            ->numeric()
                            ->label('Authorize Added Percent')
                            ->helperText('Optional percent to add on authorise')
                            ->minValue(0),
                        Forms\Components\TextInput::make('authorize_minimum_addition')
                            ->numeric()
                            ->label('Authorize Minimum Addition')
                            ->helperText('Absolute minimum added amount')
                            ->minValue(0),
                    ])->columns(4),

                Forms\Components\Section::make('Document Settings')
                    ->description('Invoice and receipt configuration')
                    ->schema([
                        Forms\Components\Toggle::make('draft_document')
                            ->label('Create Draft Documents')
                            ->helperText('Create drafts instead of final docs'),
                        Forms\Components\Toggle::make('email_document')
                            ->label('Email Documents to Customers')
                            ->helperText('Send document emails to customer'),
                        Forms\Components\Toggle::make('create_order_document')
                            ->label('Create Order Documents')
                            ->helperText('Create document on order completion'),
                    ])->columns(3),

                Forms\Components\Section::make('Tokenization')
                    ->description('Credit card tokenization settings')
                    ->schema([
                        Forms\Components\Toggle::make('support_tokens')
                            ->label('Support Saved Cards')
                            ->helperText('Allow saved cards/tokenization'),
                        Forms\Components\Select::make('token_param')
                            ->label('Token Method')
                            ->options([
                                '2' => 'J2 Method',
                                '5' => 'J5 Method (Recommended)',
                            ])
                            ->helperText('Choose SUMIT token variant'),
                    ])->columns(2),

                Forms\Components\Section::make('Additional Features')
                    ->description('Extra payment methods and features')
                    ->schema([
                        Forms\Components\Toggle::make('bit_enabled')
                            ->label('Enable Bit Payments')
                            ->helperText('Toggle Bit gateway availability'),
                        Forms\Components\Toggle::make('logging')
                            ->label('Enable Logging')
                            ->helperText('Use Laravel log channel defined below'),
                        Forms\Components\TextInput::make('log_channel')
                            ->label('Log Channel')
                            ->helperText('Defaults to stack'),
                    ])->columns(3),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        /** @var SumitSettings $settings */
        $settings = app(SumitSettings::class);

        foreach ($data as $key => $value) {
            $settings->$key = $value;
        }

        $settings->save();

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
