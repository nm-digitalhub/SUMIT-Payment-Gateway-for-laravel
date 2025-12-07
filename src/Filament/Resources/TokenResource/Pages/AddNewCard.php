<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\TokenResource\Pages;

use Filament\Resources\Pages\Page;
use Filament\Notifications\Notification;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\TokenResource;
use OfficeGuy\LaravelSumitGateway\Services\TokenService;
use OfficeGuy\LaravelSumitGateway\Services\PaymentService;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken;
use Illuminate\Support\Facades\Request;

class AddNewCard extends Page
{
    protected static string $resource = TokenResource::class;

    protected string $view = 'officeguy::filament.resources.token-resource.pages.add-new-card';

    protected static ?string $title = 'Add New Payment Card';

    protected static ?string $navigationLabel = 'Add Card';

    public ?int $ownerId = null;
    public ?string $ownerType = null;

    public function mount(): void
    {
        $this->ownerId = request()->integer('owner_id');
        $this->ownerType = request()->string('owner_type')->toString();

        if (!$this->ownerId || !$this->ownerType) {
            Notification::make()
                ->title('Invalid Request')
                ->body('Owner information is missing')
                ->danger()
                ->send();

            redirect()->to(TokenResource::getUrl('index'));
        }
    }

    public function getOwner(): mixed
    {
        if (!$this->ownerType || !$this->ownerId) {
            return null;
        }

        return $this->ownerType::find($this->ownerId);
    }

    public function processNewCard(): void
    {
        $singleUseToken = Request::input('single_use_token');
        $setAsDefault = Request::boolean('set_as_default', true);

        if (!$singleUseToken) {
            Notification::make()
                ->title('Validation Error')
                ->body('Single-use token is required')
                ->danger()
                ->send();
            return;
        }

        $owner = $this->getOwner();
        if (!$owner) {
            Notification::make()
                ->title('Error')
                ->body('Owner not found')
                ->danger()
                ->send();
            return;
        }

        // Set POST data for TokenService
        $_POST['og-token'] = $singleUseToken;

        try {
            // Process the SingleUseToken to get permanent token
            $result = TokenService::processToken($owner, 'no');

            if (!$result['success']) {
                Notification::make()
                    ->title('Token Processing Failed')
                    ->body($result['message'] ?? 'Unknown error')
                    ->danger()
                    ->send();
                return;
            }

            $newToken = $result['token'];

            // Optionally set as default in SUMIT
            if ($setAsDefault) {
                $client = $owner->client ?? $owner;
                $sumitCustomerId = $client->sumit_customer_id ?? null;

                if ($sumitCustomerId) {
                    PaymentService::setPaymentMethodForCustomer(
                        $sumitCustomerId,
                        $newToken->token
                    );
                    $newToken->setAsDefault();
                }
            }

            Notification::make()
                ->title('Card Added Successfully')
                ->body('New payment method has been added')
                ->success()
                ->send();

            // Redirect to token list
            redirect()->to(TokenResource::getUrl('index'));

        } catch (\Throwable $e) {
            Notification::make()
                ->title('Processing Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            unset($_POST['og-token']);
        }
    }

    public function getPublicKey(): string
    {
        return config('officeguy.public_key', '');
    }

    public function getEnvironment(): string
    {
        return config('officeguy.environment', 'www');
    }
}
