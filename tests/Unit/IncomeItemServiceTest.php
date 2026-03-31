<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Tests\Unit;

use OfficeGuy\LaravelSumitGateway\DataTransferObjects\IncomeItemData;
use OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData;
use OfficeGuy\LaravelSumitGateway\Http\Requests\IncomeItem\CreateIncomeItemRequest;
use OfficeGuy\LaravelSumitGateway\Http\Requests\IncomeItem\ListIncomeItemsRequest;
use OfficeGuy\LaravelSumitGateway\OfficeGuyServiceProvider;
use OfficeGuy\LaravelSumitGateway\Services\IncomeItemService;
use Orchestra\Testbench\TestCase;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

class IncomeItemServiceTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            OfficeGuyServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        tap($app['config'], function ($config): void {
            $config->set('officeguy.company_id', 1);
            $config->set('officeguy.private_key', 'test-key');
            $config->set('officeguy.environment', 'www');
        });
    }

    protected function tearDown(): void
    {
        MockClient::destroyGlobal();

        parent::tearDown();
    }

    public function test_create_returns_entity_id_on_openapi_success_status(): void
    {
        MockClient::global([
            CreateIncomeItemRequest::class => MockResponse::make([
                'Status' => 'Success (0)',
                'Data' => ['EntityID' => 42],
            ], 200),
        ]);

        $creds = new CredentialsData(companyId: 12345678, apiKey: 'secret');
        $item = new IncomeItemData(
            name: 'My Item',
            price: 500.0,
            cost: 200.0,
        );

        $result = IncomeItemService::create($item, $creds);

        $this->assertTrue($result['success']);
        $this->assertSame(42, $result['entity_id']);
    }

    public function test_create_returns_entity_id_on_legacy_numeric_success_status(): void
    {
        MockClient::global([
            CreateIncomeItemRequest::class => MockResponse::make([
                'Status' => 0,
                'Data' => ['EntityID' => 7],
            ], 200),
        ]);

        $creds = new CredentialsData(companyId: 1, apiKey: 'k');
        $item = new IncomeItemData(name: 'X');

        $result = IncomeItemService::create($item, $creds);

        $this->assertTrue($result['success']);
        $this->assertSame(7, $result['entity_id']);
    }

    public function test_create_returns_error_on_business_failure(): void
    {
        MockClient::global([
            CreateIncomeItemRequest::class => MockResponse::make([
                'Status' => 'BusinessError (1)',
                'UserErrorMessage' => 'Duplicate SKU',
            ], 200),
        ]);

        $result = IncomeItemService::create(
            new IncomeItemData(sku: 'dup'),
            new CredentialsData(companyId: 1, apiKey: 'k'),
        );

        $this->assertFalse($result['success']);
        $this->assertSame('Duplicate SKU', $result['error']);
    }

    public function test_list_normalizes_income_items_and_has_next_page(): void
    {
        MockClient::global([
            ListIncomeItemsRequest::class => MockResponse::make([
                'Status' => 'Success (0)',
                'Data' => [
                    'IncomeItems' => [
                        ['ID' => 1, 'Name' => 'A'],
                        ['ID' => 2, 'Name' => 'B'],
                    ],
                    'HasNextPage' => true,
                ],
            ], 200),
        ]);

        $result = IncomeItemService::list(
            ['StartIndex' => 0, 'PageSize' => 10],
            new CredentialsData(companyId: 1, apiKey: 'k'),
        );

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['income_items']);
        $this->assertSame('A', $result['income_items'][0]['Name']);
        $this->assertTrue($result['has_next_page']);
    }

    public function test_create_request_body_matches_openapi_shape(): void
    {
        MockClient::global([
            CreateIncomeItemRequest::class => MockResponse::make([
                'Status' => 'Success (0)',
                'Data' => ['EntityID' => 1],
            ], 200),
        ]);

        $item = new IncomeItemData(
            name: 'Line',
            price: 10.5,
            currency: 'ILS (0)',
            properties: ['key' => 'value'],
        );

        IncomeItemService::create($item, new CredentialsData(companyId: 99, apiKey: 'api'));

        $mock = MockClient::getGlobal();
        $this->assertNotNull($mock);
        $recorded = $mock->getRecordedResponses();
        $this->assertCount(1, $recorded);

        $pending = $recorded[0]->getPendingRequest();
        /** @var CreateIncomeItemRequest $request */
        $request = $pending->getRequest();
        $this->assertInstanceOf(CreateIncomeItemRequest::class, $request);

        $body = $request->body()->all();
        $this->assertArrayHasKey('Credentials', $body);
        $this->assertSame(99, $body['Credentials']['CompanyID']);
        $this->assertSame('api', $body['Credentials']['APIKey']);
        $this->assertSame([
            'Name' => 'Line',
            'Price' => 10.5,
            'Currency' => 'ILS (0)',
            'Properties' => ['key' => 'value'],
        ], $body['IncomeItem']);
    }
}
