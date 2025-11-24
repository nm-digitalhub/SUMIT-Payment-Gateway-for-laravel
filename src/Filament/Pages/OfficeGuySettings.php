<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use OfficeGuy\LaravelSumitGateway\Services\SettingsService;

class OfficeGuySettings extends Page implements HasForms
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
        // Filament v4 Page: MUST use $this->form
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
            Forms\Components\Section::make('API Credentials')
                ->schema([
                    Forms\Components\TextInput::make('company_id')
                        ->label('Company ID')
                        ->required()
                        ->numeric(),
                    Forms\Components\TextInput::make('private_key')
                        ->label('Private Key')
                        ->password()
                        ->revealable()
                        ->required(),
                    Forms\Components\TextInput::make('public_key')
                        ->label('Public Key')
                        ->required(),
                ])
                ->columns(3),

            Forms\Components\Section::make('Environment Settings')
                ->schema([
                    Forms\Components\Select::make('environment')
                        ->options([
                            'www' => 'Production (www)',
                            'dev' => 'Development (dev)',
                            'test' => 'Testing (test)',
                        ])
                        ->required(),
                    Forms\Components\Select::make('pci')
                        ->options([
                            'no' => 'Simple (PaymentsJS)',
                            'redirect' => 'Redirect',
                            'yes' => 'Advanced (PCI-compliant)',
                        ])
                        ->required(),
                    Forms\Components\Toggle::make('testing'),
                ])
                ->columns(3),

            Forms\Components\Section::make('Payment Settings')
                ->schema([
                    Forms\Components\TextInput::make('max_payments')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(36),
                    Forms\Components\Toggle::make('authorize_only'),
                    Forms\Components\TextInput::make('authorize_added_percent')->numeric(),
                    Forms\Components\TextInput::make('authorize_minimum_addition')->numeric(),
                ])
                ->columns(4),

            Forms\Components\Section::make('Document Settings')
                ->schema([
                    Forms\Components\Toggle::make('draft_document'),
                    Forms\Components\Toggle::make('email_document'),
                    Forms\Components\Toggle::make('create_order_document'),
                ])
                ->columns(3),

            Forms\Components\Section::make('Tokenization')
                ->schema([
                    Forms\Components\Toggle::make('support_tokens'),
                    Forms\Components\Select::make('token_param')
                        ->options([
                            '2' => 'J2 Method',
                            '5' => 'J5 Method (Recommended)',
                        ])
                ])
                ->columns(2),

            Forms\Components\Section::make('Additional Features')
                ->schema([
                    Forms\Components\Toggle::make('bit_enabled'),
                    Forms\Components\Toggle::make('logging'),
                    Forms\Components\TextInput::make('log_channel'),
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