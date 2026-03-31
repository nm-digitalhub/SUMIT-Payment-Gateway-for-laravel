<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use OfficeGuy\LaravelSumitGateway\DataTransferObjects\IncomeItemData;
use OfficeGuy\LaravelSumitGateway\Http\Connectors\SumitConnector;
use OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData;
use OfficeGuy\LaravelSumitGateway\Http\Requests\IncomeItem\CreateIncomeItemRequest;
use OfficeGuy\LaravelSumitGateway\Http\Requests\IncomeItem\ListIncomeItemsRequest;
use OfficeGuy\LaravelSumitGateway\Support\SumitApiResponse;

/**
 * Accounting income items (catalog line items) via SUMIT OpenAPI.
 *
 * @see https://api.sumit.co.il — POST /accounting/incomeitems/create/ and /list/
 */
final class IncomeItemService
{
    /**
     * @return array{
     *     success: true,
     *     entity_id: positive-int,
     *     raw: array<string, mixed>
     * }|array{
     *     success: false,
     *     error: string,
     *     raw?: array<string, mixed>
     * }
     */
    public static function create(IncomeItemData $incomeItem, ?CredentialsData $credentials = null): array
    {
        $credentials ??= self::credentialsFromConfig();

        try {
            $connector = new SumitConnector;
            $request = new CreateIncomeItemRequest($credentials, $incomeItem);
            $response = $connector->send($request);
            /** @var array<string, mixed> $payload */
            $payload = $response->json() ?? [];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        if (! SumitApiResponse::isSuccess($payload['Status'] ?? null)) {
            return [
                'success' => false,
                'error' => (string) ($payload['UserErrorMessage'] ?? 'Failed to create income item in SUMIT.'),
                'raw' => $payload,
            ];
        }

        $data = $payload['Data'] ?? null;
        $entityId = is_array($data) ? ($data['EntityID'] ?? null) : null;

        if (! is_numeric($entityId) || (int) $entityId < 1) {
            return [
                'success' => false,
                'error' => 'No EntityID returned from SUMIT income item create response.',
                'raw' => $payload,
            ];
        }

        return [
            'success' => true,
            'entity_id' => (int) $entityId,
            'raw' => $payload,
        ];
    }

    /**
     * @param  array{StartIndex?: int|null, PageSize?: int|null}|null  $paging
     * @return array{
     *     success: true,
     *     income_items: list<array<string, mixed>>,
     *     has_next_page: bool,
     *     raw: array<string, mixed>
     * }|array{
     *     success: false,
     *     error: string,
     *     raw?: array<string, mixed>
     * }
     */
    public static function list(?array $paging = null, ?CredentialsData $credentials = null): array
    {
        $credentials ??= self::credentialsFromConfig();

        try {
            $connector = new SumitConnector;
            $request = new ListIncomeItemsRequest($credentials, $paging);
            $response = $connector->send($request);
            /** @var array<string, mixed> $payload */
            $payload = $response->json() ?? [];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        if (! SumitApiResponse::isSuccess($payload['Status'] ?? null)) {
            return [
                'success' => false,
                'error' => (string) ($payload['UserErrorMessage'] ?? 'Failed to list income items from SUMIT.'),
                'raw' => $payload,
            ];
        }

        $data = $payload['Data'] ?? null;
        if (! is_array($data)) {
            return [
                'success' => false,
                'error' => 'Invalid Data payload in SUMIT income items list response.',
                'raw' => $payload,
            ];
        }

        $items = $data['IncomeItems'] ?? [];
        if (! is_array($items)) {
            $items = [];
        }

        /** @var list<array<string, mixed>> $normalized */
        $normalized = array_values(array_filter($items, 'is_array'));

        return [
            'success' => true,
            'income_items' => $normalized,
            'has_next_page' => (bool) ($data['HasNextPage'] ?? false),
            'raw' => $payload,
        ];
    }

    private static function credentialsFromConfig(): CredentialsData
    {
        $creds = PaymentService::getCredentials();

        return new CredentialsData(
            companyId: (int) $creds['CompanyID'],
            apiKey: (string) $creds['APIKey'],
        );
    }
}
