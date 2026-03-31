<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Tests\Unit;

use OfficeGuy\LaravelSumitGateway\DataTransferObjects\IncomeItemData;
use PHPUnit\Framework\TestCase;

class IncomeItemDataTest extends TestCase
{
    public function test_to_sumit_array_omits_null_keys(): void
    {
        $dto = new IncomeItemData(
            name: 'Only name',
            price: 0.0,
        );

        $this->assertSame(
            [
                'Name' => 'Only name',
                'Price' => 0.0,
            ],
            $dto->toSumitArray()
        );
    }

    public function test_to_sumit_array_includes_optional_openapi_fields(): void
    {
        $dto = new IncomeItemData(
            id: 5,
            name: 'Full',
            description: 'D',
            price: 1.0,
            currency: 'USD (1)',
            cost: 0.5,
            externalIdentifier: 'ext-1',
            sku: 'SKU',
            searchMode: 'SKU (4)',
            properties: ['a' => null],
        );

        $this->assertSame(
            [
                'ID' => 5,
                'Name' => 'Full',
                'Description' => 'D',
                'Price' => 1.0,
                'Currency' => 'USD (1)',
                'Cost' => 0.5,
                'ExternalIdentifier' => 'ext-1',
                'SKU' => 'SKU',
                'SearchMode' => 'SKU (4)',
                'Properties' => ['a' => null],
            ],
            $dto->toSumitArray()
        );
    }
}
