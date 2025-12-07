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
    public ?string $singleUseToken = null;
    public bool $setAsDefault = true;

    // Result state management
    public ?string $resultStatus = null; // 'success' | 'error' | null
    public ?array $resultData = null;

    public function mount(string $ownerType, int $ownerId): void
    {
        $this->ownerType = urldecode($ownerType);
        $this->ownerId = $ownerId;

        abort_unless($this->ownerId && $this->ownerType, 404, 'Owner information is missing');
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
        // Debug logging
        \Log::info('processNewCard called', [
            'singleUseToken' => $this->singleUseToken,
            'setAsDefault' => $this->setAsDefault,
            'ownerId' => $this->ownerId,
            'ownerType' => $this->ownerType,
        ]);

        // Use Livewire properties instead of Request
        if (!$this->singleUseToken) {
            \Log::warning('Single-use token is missing');
            $this->resultStatus = 'error';
            $this->resultData = [
                'message' => 'Single-use token is required',
                'error_type' => 'validation',
            ];
            return;
        }

        $owner = $this->getOwner();
        if (!$owner) {
            \Log::warning('Owner not found', ['ownerId' => $this->ownerId, 'ownerType' => $this->ownerType]);
            $this->resultStatus = 'error';
            $this->resultData = [
                'message' => 'Owner not found',
                'error_type' => 'validation',
            ];
            return;
        }

        // Merge token into the request for TokenService to read via RequestHelpers::post()
        request()->merge([
            'og-token' => $this->singleUseToken,
        ]);
        \Log::info('Merged og-token into request', ['token' => substr($this->singleUseToken, 0, 20) . '...']);

        try {
            // Process the SingleUseToken to get permanent token
            $result = TokenService::processToken($owner, 'no');

            if (!$result['success']) {
                $this->resultStatus = 'error';
                $this->resultData = [
                    'message' => $result['message'] ?? 'Unknown error',
                    'error_type' => 'gateway',
                ];
                return;
            }

            $newToken = $result['token'];

            // Optionally set as default in SUMIT
            if ($this->setAsDefault) {
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

            // Success! Store result data
            $this->resultStatus = 'success';
            $this->resultData = [
                'token' => $newToken,
                'owner' => $owner,
                'set_as_default' => $this->setAsDefault,
            ];

        } catch (\Throwable $e) {
            \Log::error('Exception in processNewCard', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $this->resultStatus = 'error';
            $this->resultData = [
                'message' => $e->getMessage(),
                'error_type' => 'exception',
            ];
        } finally {
            // Clean up request data
            request()->offsetUnset('og-token');
        }
    }

    public function resetForm(): void
    {
        $this->resultStatus = null;
        $this->resultData = null;
        $this->singleUseToken = null;
        $this->setAsDefault = true;
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
