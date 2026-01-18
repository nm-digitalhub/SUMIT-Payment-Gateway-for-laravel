<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\DTOs;

/**
 * Document Data Transfer Object
 *
 * Represents invoice/receipt document data for SUMIT API.
 *
 * Document Types:
 * - 1: Invoice (חשבונית מס)
 * - 2: Receipt (קבלה)
 * - 3: Credit Note (מסמך זיכוי)
 * - 320: Donation Receipt (קבלה על תרומה)
 *
 * Required Fields:
 * - Type: Document type (1/2/3/320)
 * - Amount: Total amount
 * - Currency: ILS, USD, EUR
 * - Customer: Name, email, phone, address
 * - Items: Product/service items with prices
 */
class DocumentData
{
    public function __construct(
        public readonly int $type, // Document type: 1=Invoice, 2=Receipt, 3=Credit, 320=Donation
        public readonly float $amount, // Total amount
        public readonly string $currency = 'ILS',

        // Customer information
        public readonly string $customerName = '',
        public readonly string $customerEmail = '',
        public readonly ?string $customerPhone = null,
        public readonly ?string $customerAddress = null,
        public readonly ?string $customerCity = null,
        public readonly ?string $customerZip = null,
        public readonly ?string $citizenId = null, // Israeli ID for tax purposes

        // Document items
        public readonly array $items = [], // [['name' => '', 'price' => 0, 'quantity' => 1, 'vat' => 17]]

        // Optional metadata
        public readonly ?string $orderId = null, // Reference to order
        public readonly ?string $transactionId = null, // Reference to payment transaction
        public readonly ?string $notes = null, // Internal notes
        public readonly ?string $customerNotes = null, // Notes visible to customer

        // Company information (optional overrides)
        public readonly ?string $companyName = null,
        public readonly ?string $companyAddress = null,
        public readonly ?string $companyPhone = null,
        public readonly ?string $companyEmail = null,

        // Tax settings
        public readonly ?bool $includeVat = null, // Include VAT in document
        public readonly ?float $vatRate = null, // VAT rate (default: 17%)

        // Donation-specific (Type=320)
        public readonly ?string $donationPurpose = null,
        public readonly ?string $donationRecipient = null,
    ) {}

    /**
     * Convert to SUMIT API request array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'Type' => $this->type,
            'Amount' => $this->amount,
            'Currency' => $this->currency,
            'CustomerName' => $this->customerName,
            'CustomerEmail' => $this->customerEmail,
        ];

        // Optional customer fields
        if ($this->customerPhone) {
            $data['CustomerPhone'] = $this->customerPhone;
        }
        if ($this->customerAddress) {
            $data['CustomerAddress'] = $this->customerAddress;
        }
        if ($this->customerCity) {
            $data['CustomerCity'] = $this->customerCity;
        }
        if ($this->customerZip) {
            $data['CustomerZip'] = $this->customerZip;
        }
        if ($this->citizenId) {
            $data['CitizenID'] = $this->citizenId;
        }

        // Document items
        if (!empty($this->items)) {
            $data['Items'] = $this->items;
        }

        // References
        if ($this->orderId) {
            $data['OrderID'] = $this->orderId;
        }
        if ($this->transactionId) {
            $data['TransactionID'] = $this->transactionId;
        }

        // Notes
        if ($this->notes) {
            $data['Notes'] = $this->notes;
        }
        if ($this->customerNotes) {
            $data['CustomerNotes'] = $this->customerNotes;
        }

        // Company overrides
        if ($this->companyName) {
            $data['CompanyName'] = $this->companyName;
        }
        if ($this->companyAddress) {
            $data['CompanyAddress'] = $this->companyAddress;
        }
        if ($this->companyPhone) {
            $data['CompanyPhone'] = $this->companyPhone;
        }
        if ($this->companyEmail) {
            $data['CompanyEmail'] = $this->companyEmail;
        }

        // Tax settings
        if ($this->includeVat !== null) {
            $data['IncludeVAT'] = $this->includeVat;
        }
        if ($this->vatRate !== null) {
            $data['VATRate'] = $this->vatRate;
        }

        // Donation-specific
        if ($this->type === 320) {
            if ($this->donationPurpose) {
                $data['DonationPurpose'] = $this->donationPurpose;
            }
            if ($this->donationRecipient) {
                $data['DonationRecipient'] = $this->donationRecipient;
            }
        }

        return $data;
    }

    /**
     * Create from array
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'],
            amount: $data['amount'],
            currency: $data['currency'] ?? 'ILS',
            customerName: $data['customer_name'] ?? '',
            customerEmail: $data['customer_email'] ?? '',
            customerPhone: $data['customer_phone'] ?? null,
            customerAddress: $data['customer_address'] ?? null,
            customerCity: $data['customer_city'] ?? null,
            customerZip: $data['customer_zip'] ?? null,
            citizenId: $data['citizen_id'] ?? null,
            items: $data['items'] ?? [],
            orderId: $data['order_id'] ?? null,
            transactionId: $data['transaction_id'] ?? null,
            notes: $data['notes'] ?? null,
            customerNotes: $data['customer_notes'] ?? null,
            companyName: $data['company_name'] ?? null,
            companyAddress: $data['company_address'] ?? null,
            companyPhone: $data['company_phone'] ?? null,
            companyEmail: $data['company_email'] ?? null,
            includeVat: $data['include_vat'] ?? null,
            vatRate: $data['vat_rate'] ?? null,
            donationPurpose: $data['donation_purpose'] ?? null,
            donationRecipient: $data['donation_recipient'] ?? null,
        );
    }
}
