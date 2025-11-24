<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use OfficeGuy\LaravelSumitGateway\Services\SettingsService;

class OfficeGuySettings extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-cog-6-tooth';

protected static ?string $navigationLabel = 'Gateway Settings';

protected static string | UnitEnum | null $navigationGroup = 'SUMIT Gateway';

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

    public function form(Form $form): Form
    {
        return $form
            ->schema($this->getFormSchema())
            ->statePath('data');
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Section::make('API Credentials')
                ->description('Your SUMIT Gateway API credentials')
                ->schema([
                    Forms\TextInput::make('company_id')
                        ->label('Company ID')
                        ->required()
                        ->numeric(),
                    Forms\TextInput::make('private_key')
                        ->label('Private Key')
                        ->password()
                        ->revealable()
                        ->required(),
                    Forms\TextInput::make('public_key')
                        ->label('Public Key')
                        ->required(),
                ])
                ->columns(3),

            Forms\Section::make('Environment Settings')
                ->schema([
                    Forms\Select::make('environment')
                        ->options([
                            'www' => 'Production (www)',
                            'dev' => 'Development (dev)',
                            'test' => 'Testing (test)',
                        ])
                        ->required(),
                    Forms\Select::make('pci')
                        ->options([
                            'no' => 'Simple (PaymentsJS)',
                            'redirect' => 'Redirect',
                            'yes' => 'Advanced (PCI-compliant)',
                        ])
                        ->required(),
                    Forms\Toggle::make('testing'),
                ])
                ->columns(3),

            Forms\Section::make('Payment Settings')
                ->schema([
                    Forms\TextInput::make('max_payments')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(36),
                    Forms\Toggle::make('authorize_only'),
                    Forms\TextInput::make('authorize_added_percent')
                        ->numeric(),
                    Forms\TextInput::make('authorize_minimum_addition')
                        ->numeric(),
                ])
                ->columns(4),

            Forms\Section::make('Document Settings')
                ->schema([
                    Forms\Toggle::make('draft_document'),
                    Forms\Toggle::make('email_document'),
                    Forms\Toggle::make('create_order_document'),
                ])
                ->columns(3),

            Forms\Section::make('Tokenization')
                ->schema([
                    Forms\Toggle::make('support_tokens'),
                    Forms\Select::make('token_param')
                        ->options([
                            '2' => 'J2 Method',
                            '5' => 'J5 Method (Recommended)',
                        ])
                ])
                ->columns(2),

            Forms\Section::make('Additional Features')
                ->schema([
                    Forms\Toggle::make('bit_enabled'),
                    Forms\Toggle::make('logging'),
                    Forms\TextInput::make('log_channel'),
                ])
                ->columns(3),
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