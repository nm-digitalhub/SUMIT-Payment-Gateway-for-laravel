<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientPaymentMethodResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Forms\Components\ViewField;
use OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientPaymentMethodResource;
use OfficeGuy\LaravelSumitGateway\Services\TokenService;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken;

class CreateClientPaymentMethod extends CreateRecord
{
    protected static string $resource = ClientPaymentMethodResource::class;

    /**
     * טופס יצירת כרטיס – נפרד מה-Form של ה-Resource (שמשמש רק ל-View)
     */
    public function form(Schema $schema): Schema
    {
        $pciMode = config('officeguy.pci', config('officeguy.pci_mode', 'no'));

        $components = [];

        if ($pciMode === 'yes') {
            // מצב PCI – שולחים פרטי כרטיס מלאים לשרת (השירות מייצר טוקן)
            $components[] = Forms\Components\TextInput::make('og-ccnum')
                ->label('Card Number')
                ->required()
                ->numeric()
                ->rule('digits_between:12,19');

            $components[] = Forms\Components\TextInput::make('og-expmonth')
                ->label('Expiry Month')
                ->required()
                ->numeric()
                ->minValue(1)
                ->maxValue(12);

            $components[] = Forms\Components\TextInput::make('og-expyear')
                ->label('Expiry Year')
                ->required()
                ->numeric()
                ->minValue((int) date('Y'))
                ->maxValue((int) date('Y') + 20);

            $components[] = Forms\Components\TextInput::make('og-cvv')
                ->label('CVV')
                ->password()
                ->required()
                ->rule('digits_between:3,4');

            $components[] = Forms\Components\TextInput::make('og-citizenid')
                ->label('ID Number')
                ->required();

            // אפשרות לסמן ככרטיס ברירת מחדל – ממוקם גבוה יותר בטופס
            $components[] = Forms\Components\Toggle::make('set_as_default')
                ->label('Set as default payment method')
                ->default(true);
        } else {
            // מצב Hosted Fields – מציגים טופס עם PaymentsJS שמייצר SingleUseToken לשדה og-token
            $components[] = Forms\Components\Hidden::make('og-token')
                ->required();

            // Toggle קרוב לראש הטופס כדי שיהיה גלוי מיידית
            $components[] = Forms\Components\Toggle::make('set_as_default')
                ->label('Set as default payment method')
                ->default(true);

            $components[] = ViewField::make('card_form')
                ->label('Card Details')
                ->view('officeguy::filament.client.payment-methods.hosted-token-form', [
                    'livewireId' => $this->getId(),
                    'companyId' => config('officeguy.company_id'),
                    'publicKey' => config('officeguy.public_key'),
                ]);
        }

        return $schema->components($components)->columns(2);
    }

    /**
     * כאן אנחנו "עוקפים" את יצירת המודל הרגילה של Filament
     * ומעבירים את הנתונים ל-TokenService שמדבר עם SUMIT ומייצר טוקן.
     */
    protected function handleRecordCreation(array $data): OfficeGuyToken
    {
        $pciMode = config('officeguy.pci', config('officeguy.pci_mode', 'no'));

        // TokenService משתמש ב-RequestHelpers::post, אז נזריק את הדאטה ל-request
        request()->merge($data);

        $result = TokenService::processToken(auth()->user(), $pciMode);

        if (!($result['success'] ?? false)) {
            Notification::make()
                ->danger()
                ->title('Failed to add payment method')
                ->body($result['message'] ?? 'Unknown error')
                ->send();

            // עוצר את תהליך ה-Create של Filament
            $this->halt();
        }

        /** @var OfficeGuyToken $token */
        $token = $result['token'];

        // אם המשתמש בחר "Set as default" – נסמן את הטוקן כברירת מחדל
        if (!empty($data['set_as_default'])) {
            $token->setAsDefault();
        }

        Notification::make()
            ->success()
            ->title('Payment method added')
            ->body('Your card has been saved successfully.')
            ->send();

        return $token;
    }

    /**
     * אחרי יצירה – נחזור לרשימת אמצעי התשלום
     */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    /**
     * לא משתמשים ב-Model::create() אלא ב-handleRecordCreation בלבד
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // לא שומרים את ה-data ישירות בבסיס הנתונים (TokenService מטפל בזה)
        return [];
    }
}
