<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\DataTransferObjects;

use Illuminate\Http\Request;

/**
 * PaymentPreferences DTO
 *
 * Immutable value object representing customer's payment choices.
 * Includes payment method, installments, saved token selection, etc.
 *
 * CRITICAL: All properties are readonly - cannot be modified after creation.
 */
final readonly class PaymentPreferences
{
    public function __construct(
        public string $method, // 'card' | 'bit'
        public int $installments = 1,
        public ?string $tokenId = null, // Existing saved token ID (if using saved card)
        public bool $saveCard = false,
    ) {}

    /**
     * Create from HTTP request
     */
    public static function fromRequest(Request $request): self
    {
        // Extract payment_token (handle "new" as null)
        $tokenId = $request->input('payment_token');
        if (empty($tokenId) || $tokenId === 'new') {
            $tokenId = null;
        }

        return new self(
            method: $request->input('payment_method', 'card'),
            installments: max(1, (int) $request->input('payments_count', 1)),
            tokenId: $tokenId,
            saveCard: (bool) $request->input('save_card', false),
        );
    }

    /**
     * Create from array (for deserialization from DB/session)
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            method: $data['method'] ?? 'card',
            installments: $data['installments'] ?? 1,
            tokenId: $data['token_id'] ?? null,
            saveCard: $data['save_card'] ?? false,
        );
    }

    /**
     * Convert to array (for serialization to DB/session)
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'installments' => $this->installments,
            'token_id' => $this->tokenId,
            'save_card' => $this->saveCard,
        ];
    }

    /**
     * Check if customer wants to use a saved payment method
     */
    public function isUsingSavedToken(): bool
    {
        return ! in_array($this->tokenId, [null, '', '0'], true);
    }

    /**
     * Check if payment method is card (not Bit)
     */
    public function isCardPayment(): bool
    {
        return $this->method === 'card';
    }

    /**
     * Check if payment method is Bit
     */
    public function isBitPayment(): bool
    {
        return $this->method === 'bit';
    }

    /**
     * Check if using installments (more than 1 payment)
     */
    public function hasInstallments(): bool
    {
        return $this->installments > 1;
    }
}
