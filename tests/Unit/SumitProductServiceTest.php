<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Tests\Unit;

use Illuminate\Support\Facades\Http;
use OfficeGuy\LaravelSumitGateway\OfficeGuyServiceProvider;
use OfficeGuy\LaravelSumitGateway\Services\SumitProductService;
use Orchestra\Testbench\TestCase;

class SumitProductServiceTest extends TestCase
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
            $config->set('officeguy.crm_products_sumit_folder_id', null);
            $config->set('officeguy.crm_products_folder_id', null);
        });
    }

    public function test_create_product_fails_when_folder_not_configured(): void
    {
        $result = SumitProductService::createProduct('Plan A', 'SKU-1', 9.99, 'Desc');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Configure officeguy', (string) $result['error']);
    }

    public function test_update_product_rejects_invalid_entity_id(): void
    {
        $result = SumitProductService::updateProduct(0, ['Accounting_Price' => 10]);

        $this->assertFalse($result['success']);
    }

    public function test_create_product_posts_to_sumit_when_folder_configured(): void
    {
        config([
            'officeguy.crm_products_sumit_folder_id' => 5551212,
        ]);

        Http::fake([
            'https://api.sumit.co.il/crm/data/createentity/' => Http::response([
                'Status' => 0,
                'Data' => ['EntityID' => 42],
            ], 200),
        ]);

        $result = SumitProductService::createProduct('Plan B', 'SKU-2', 12.5, 'Hello');

        $this->assertTrue($result['success']);
        $this->assertSame(42, $result['sumit_entity_id']);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            return $request->url() === 'https://api.sumit.co.il/crm/data/createentity/'
                && ($request['FolderID'] ?? null) === 5551212
                && ($request['Fields']['Accounting_Name'] ?? null) === 'Plan B'
                && ($request['Fields']['Accounting_SKU'] ?? null) === 'SKU-2'
                && ($request['Fields']['Accounting_Price'] ?? null) === 12.5;
        });
    }

    public function test_update_product_posts_fields_to_sumit(): void
    {
        Http::fake([
            'https://api.sumit.co.il/crm/data/updateentity/' => Http::response([
                'Status' => 0,
                'Data' => [],
            ], 200),
        ]);

        $result = SumitProductService::updateProduct(99, ['Accounting_Price' => 20]);

        $this->assertTrue($result['success']);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            return $request->url() === 'https://api.sumit.co.il/crm/data/updateentity/'
                && ($request['EntityID'] ?? null) === 99
                && ($request['Fields']['Accounting_Price'] ?? null) === 20;
        });
    }
}
