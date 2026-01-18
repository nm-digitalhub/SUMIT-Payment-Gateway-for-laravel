<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\DTOs;

/**
 * Token Data Transfer Object
 *
 * Represents token creation/exchange data for SUMIT API.
 * Used for converting single-use tokens to permanent tokens (J2/J5).
 */
class TokenData
{
    public function __construct(
        public readonly string $paramJ, // J2, J5, J6
        public readonly float $amount = 1.0, // Test amount (always 1 for tokens)

        // PCI Mode 'no' (PaymentsJS / Hosted Fields)
        public readonly ?string $singleUseToken = null,

        // PCI Mode 'yes' (Direct API)
        public readonly ?string $cardNumber = null,
        public readonly ?string $cvv = null,
        public readonly ?string $citizenId = null,
        public readonly ?string $expirationMonth = null,
        public readonly ?string $expirationYear = null,
    ) {}

    /**
     * Convert to SUMIT API request array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'ParamJ' => $this->paramJ,
            'Amount' => $this->amount,
        ];

        // PCI Mode 'no' - Single-use token from PaymentsJS
        if ($this->singleUseToken) {
            $data['SingleUseToken'] = $this->singleUseToken;
        }

        // PCI Mode 'yes' - Direct card data
        if ($this->cardNumber) {
            $data['CardNumber'] = $this->cardNumber;
            $data['CVV'] = $this->cvv;
            $data['CitizenID'] = $this->citizenId;
            $data['ExpirationMonth'] = $this->expirationMonth;
            $data['ExpirationYear'] = $this->expirationYear;
        }

        return $data;
    }

    /**
     * Create from array (for backward compatibility)
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            paramJ: $data['param_j'] ?? config('officeguy.token_param', '5'),
            amount: $data['amount'] ?? 1.0,
            singleUseToken: $data['single_use_token'] ?? null,
            cardNumber: $data['card_number'] ?? null,
            cvv: $data['cvv'] ?? null,
            citizenId: $data['citizen_id'] ?? null,
            expirationMonth: $data['expiration_month'] ?? null,
            expirationYear: $data['expiration_year'] ?? null,
        );
    }

    /**
     * Create for PCI mode 'no' (Hosted Fields)
     *
     * @param string $singleUseToken Token from PaymentsJS
     * @param string|null $paramJ J2/J5/J6 (defaults to config)
     * @return self
     */
    public static function fromSingleUseToken(string $singleUseToken, ?string $paramJ = null): self
    {
        return new self(
            paramJ: $paramJ ?? config('officeguy.token_param', '5'),
            amount: 1.0,
            singleUseToken: $singleUseToken,
        );
    }

    /**
     * Create for PCI mode 'yes' (Direct API)
     *
     * @param string $cardNumber Full card number
     * @param string $cvv CVV code
     * @param string $citizenId Israeli ID number
     * @param string $expirationMonth MM format
     * @param string $expirationYear YYYY format
     * @param string|null $paramJ J2/J5/J6 (defaults to config)
     * @return self
     */
    public static function fromCardData(
        string $cardNumber,
        string $cvv,
        string $citizenId,
        string $expirationMonth,
        string $expirationYear,
        ?string $paramJ = null
    ): self {
        return new self(
            paramJ: $paramJ ?? config('officeguy.token_param', '5'),
            amount: 1.0,
            cardNumber: $cardNumber,
            cvv: $cvv,
            citizenId: $citizenId,
            expirationMonth: $expirationMonth,
            expirationYear: $expirationYear,
        );
    }
}
