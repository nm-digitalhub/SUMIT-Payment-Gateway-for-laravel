<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Pages;

use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Schemas\Schema; // ← שינוי חדש
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

    public function form(Schema $form): Schema // ← שינוי כאן
    {
        return $form
            ->schema($this->getFormSchema())
            ->statePath('data');
    }

    protected function getFormSchema(): array
    {
        return [

            Fieldset::make('API Credentials')
                ->schema([
                    Grid::make(3)->schema([
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
                ]),

            Fieldset::make('Environment Settings')
                ->schema([
                    Grid::make(3)->schema([
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
                ]),

            Fieldset::make('Payment Settings')
                ->schema([
                    Grid::make(4)->schema([
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
                ]),

            Fieldset::make('Document Settings')
                ->schema([
                    Grid::make(3)->schema([
                        Toggle::make('draft_document'),
                        Toggle::make('email_document'),
                        Toggle::make('create_order_document'),
                    ]),
                ]),

            Fieldset::make('Tokenization')
                ->schema([
                    Grid::make(2)->schema([
                        Toggle::make('support_tokens'),

                        Select::make('token_param')
                            ->options([
                                '2' => 'J2 Method',
                                '5' => 'J5 Method (Recommended)',
                            ]),
                    ]),
                ]),

            Fieldset::make('Additional Features')
                ->schema([
                    Grid::make(3)->schema([
                        Toggle::make('bit_enabled'),
                        Toggle::make('logging'),
                        TextInput::make('log_channel'),
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