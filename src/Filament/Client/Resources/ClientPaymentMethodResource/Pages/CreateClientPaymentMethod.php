<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientPaymentMethodResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
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
            $components[] = TextInput::make('og-ccnum')
                ->label('Card Number')
                ->required()
                ->numeric()
                ->rule('digits_between:12,19');

            $components[] = TextInput::make('og-expmonth')
                ->label('Expiry Month')
                ->required()
                ->numeric()
                ->minValue(1)
                ->maxValue(12);

            $components[] = TextInput::make('og-expyear')
                ->label('Expiry Year')
                ->required()
                ->numeric()
                ->minValue((int) date('Y'))
                ->maxValue((int) date('Y') + 20);

            $components[] = TextInput::make('og-cvv')
                ->label('CVV')
                ->password()
                ->required()
                ->rule('digits_between:3,4');

            $components[] = TextInput::make('og-citizenid')
                ->label('ID Number')
                ->required();

            // אפשרות לסמן ככרטיס ברירת מחדל – ממוקם גבוה יותר בטופס
            $components[] = Toggle::make('set_as_default')
                ->label('Set as default payment method')
                ->default(true);
        } else {
            // מצב Hosted Fields – מציגים טופס עם PaymentsJS שמייצר SingleUseToken לשדה og-token
            $components[] = Hidden::make('og-token')
                ->required();

            // Toggle קרוב לראש הטופס כדי שיהיה גלוי מיידית
            $components[] = Toggle::make('set_as_default')
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

        return $schema->schema($components)->columns(2);
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
     * אבל צריך להחזיר את ה-data כדי ש-Filament יקרא ל-handleRecordCreation
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // מחזירים את הדאטה כפי שהוא - handleRecordCreation יטפל ביצירה
        return $data;
    }

    /**
     * Override Filament's default validation to allow our custom flow
     */
    protected function getFormModel(): string
    {
        return OfficeGuyToken::class;
    }
}
