<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Requests\IncomeItem;

use OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

/**
 * OpenAPI: POST /accounting/incomeitems/list/
 * Request schema: Accounting_IncomeItems_List_Request (Credentials required; Paging optional).
 */
class ListIncomeItemsRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * @param  array{StartIndex?: int|null, PageSize?: int|null}|null  $paging  Core_Typed.Paging
     */
    public function __construct(
        protected readonly CredentialsData $credentials,
        protected readonly ?array $paging = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/accounting/incomeitems/list/';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        $body = [
            'Credentials' => $this->credentials->toArray(),
        ];

        if ($this->paging !== null) {
            $paging = array_filter(
                [
                    'StartIndex' => $this->paging['StartIndex'] ?? null,
                    'PageSize' => $this->paging['PageSize'] ?? null,
                ],
                static fn (mixed $v): bool => $v !== null
            );

            if ($paging !== []) {
                $body['Paging'] = $paging;
            }
        }

        return $body;
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
