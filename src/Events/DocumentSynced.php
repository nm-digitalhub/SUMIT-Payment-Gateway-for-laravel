<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Events;

use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyDocument;

/**
 * Fired when a document has been synced from SUMIT and saved to officeguy_documents.
 *
 * The package does not link documents to host orders. Listen to this event in the host
 * application to perform order lookup and set order_id/order_type on the document if desired.
 *
 * @see ORDER_LINKING_EXTRACTION_DIRECTIVE.md
 */
class DocumentSynced
{
    public function __construct(
        public OfficeGuyDocument $document,
        public array $sumitPayload
    ) {}
}
