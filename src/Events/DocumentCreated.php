<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Events;

class DocumentCreated
{
    public function __construct(
        public string|int $orderId,
        public string $documentId,
        public string $customerId,
        public array $response
    ) {}
}
