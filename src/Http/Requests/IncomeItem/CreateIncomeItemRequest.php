<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Requests\IncomeItem;

use OfficeGuy\LaravelSumitGateway\DataTransferObjects\IncomeItemData;
use OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

/**
 * OpenAPI: POST /accounting/incomeitems/create/
 * Request schema: Accounting_IncomeItems_Create_Request (Credentials + IncomeItem only).
 */
class CreateIncomeItemRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        protected readonly CredentialsData $credentials,
        protected readonly IncomeItemData $incomeItem,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/accounting/incomeitems/create/';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return [
            'Credentials' => $this->credentials->toArray(),
            'IncomeItem' => $this->incomeItem->toSumitArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function createDtoFromResponse(Response $response): array
    {
        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultConfig(): array
    {
        return [
            'timeout' => 60,
        ];
    }
}
