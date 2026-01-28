<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\DTOs;

/**
 * Payment Data Transfer Object
 *
 * Represents payment transaction data for SUMIT API.
 * Supports 3 PCI modes: 'no' (PaymentsJS), 'yes' (Direct), 'redirect'
 */
class PaymentData
{
    public function __construct(
        public readonly float $amount,
        public readonly string $currency = 'ILS',
        public readonly int $numPayments = 1,
        public readonly ?string $orderId = null,

        // PCI Mode 'no' (PaymentsJS / Hosted Fields)
        public readonly ?string $singleUseToken = null,

        // PCI Mode 'yes' (Direct API)
        public readonly ?string $cardNumber = null,
        public readonly ?string $cvv = null,
        public readonly ?string $citizenId = null,
        public readonly ?string $expirationMonth = null,
        public readonly ?string $expirationYear = null,

        // Token-based payment (existing token)
        public readonly ?string $token = null,

        // Transaction parameters
        public readonly ?string $paramJ = null, // J2, J5, J6, etc.
        public readonly ?int $transactionType = null, // 1=charge, 2=authorize
        public readonly ?string $sumitEntityId = null, // Critical for webhook matching

        // Multi-vendor support
        public readonly ?array $vendorCredentials = null,

        // Additional metadata
        public readonly ?string $description = null,
        public readonly ?string $customerEmail = null,
        public readonly ?string $customerPhone = null,
    ) {}

    /**
     * Convert to SUMIT API request array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'Amount' => $this->amount,
            'Currency' => $this->currency,
            'NumPayments' => $this->numPayments,
        ];

        // Order ID (optional)
        if ($this->orderId) {
            $data['OrderID'] = $this->orderId;
        }

        // SUMIT Entity ID (critical for webhooks!)
        if ($this->sumitEntityId) {
            $data['SumitEntityID'] = $this->sumitEntityId;
        }

        // Transaction Type (1=charge, 2=authorize)
        if ($this->transactionType) {
            $data['TransactionType'] = $this->transactionType;
        }

        // ParamJ (J2/J5/J6 for token storage)
        if ($this->paramJ) {
            $data['ParamJ'] = $this->paramJ;
        }

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

        // Token-based payment (existing permanent token)
        if ($this->token) {
            $data['Token'] = $this->token;
            if ($this->cvv) {
                $data['CVV'] = $this->cvv; // CVV required for token payments
            }
            if ($this->citizenId) {
                $data['CitizenID'] = $this->citizenId;
            }
        }

        // Multi-vendor credentials (override default credentials)
        if ($this->vendorCredentials) {
            $data['VendorCredentials'] = $this->vendorCredentials;
        }

        // Customer metadata (optional)
        if ($this->description) {
            $data['Description'] = $this->description;
        }
        if ($this->customerEmail) {
            $data['CustomerEmail'] = $this->customerEmail;
        }
        if ($this->customerPhone) {
            $data['CustomerPhone'] = $this->customerPhone;
        }

        return $data;
    }

    /**
     * Create from array (for backward compatibility)
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            amount: $data['amount'],
            currency: $data['currency'] ?? 'ILS',
            numPayments: $data['num_payments'] ?? 1,
            orderId: $data['order_id'] ?? null,
            singleUseToken: $data['single_use_token'] ?? null,
            cardNumber: $data['card_number'] ?? null,
            cvv: $data['cvv'] ?? null,
            citizenId: $data['citizen_id'] ?? null,
            expirationMonth: $data['expiration_month'] ?? null,
            expirationYear: $data['expiration_year'] ?? null,
            token: $data['token'] ?? null,
            paramJ: $data['param_j'] ?? null,
            transactionType: $data['transaction_type'] ?? null,
            sumitEntityId: $data['sumit_entity_id'] ?? null,
            vendorCredentials: $data['vendor_credentials'] ?? null,
            description: $data['description'] ?? null,
            customerEmail: $data['customer_email'] ?? null,
            customerPhone: $data['customer_phone'] ?? null,
        );
    }
}
