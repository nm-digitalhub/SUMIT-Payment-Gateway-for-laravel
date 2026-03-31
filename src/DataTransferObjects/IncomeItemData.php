<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\DataTransferObjects;

/**
 * OpenAPI: Accounting_Typed_IncomeItem (POST body fragment under IncomeItem).
 *
 * Keys omitted when null so the payload matches typical SUMIT examples.
 */
final readonly class IncomeItemData
{
    /**
     * @param  array<string, mixed>|null  $properties  OpenAPI: object with nullable additionalProperties
     */
    public function __construct(
        public ?int $id = null,
        public ?string $name = null,
        public ?string $description = null,
        public ?float $price = null,
        public ?string $currency = null,
        public ?float $cost = null,
        public ?string $externalIdentifier = null,
        public ?string $sku = null,
        public ?string $searchMode = null,
        public ?array $properties = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toSumitArray(): array
    {
        return array_filter(
            [
                'ID' => $this->id,
                'Name' => $this->name,
                'Description' => $this->description,
                'Price' => $this->price,
                'Currency' => $this->currency,
                'Cost' => $this->cost,
                'ExternalIdentifier' => $this->externalIdentifier,
                'SKU' => $this->sku,
                'SearchMode' => $this->searchMode,
                'Properties' => $this->properties,
            ],
            static fn (mixed $value): bool => $value !== null
        );
    }
}
