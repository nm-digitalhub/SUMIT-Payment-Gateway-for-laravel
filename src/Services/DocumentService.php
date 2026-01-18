<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use App\Models\Client;
use App\Models\Order;
use Carbon\Carbon;
use OfficeGuy\LaravelSumitGateway\Contracts\Payable;
use OfficeGuy\LaravelSumitGateway\Http\Connectors\SumitConnector;
use OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData;
use OfficeGuy\LaravelSumitGateway\Http\Requests\Document\GetDocumentDetailsRequest;
use OfficeGuy\LaravelSumitGateway\Http\Requests\Document\ListDocumentsRequest;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyDocument;

/**
 * Document Service
 *
 * Handles creation of invoices and receipts via SUMIT API
 * Port of document creation logic from OfficeGuyPayment.php
 */
class DocumentService
{
    /**
     * Document type constants
     */
    public const TYPE_INVOICE = '1';
    public const TYPE_ORDER = '8';
    public const TYPE_DONATION_RECEIPT = '320';
    public const TYPE_RECEIPT = '2';
    public const TYPE_CREDIT_NOTE = '3';
    /**
     * Create order document (invoice/receipt)
     *
     * Port of: CreateOrderDocument($Gateway, $Order, $Customer, $OriginalDocumentID)
     *
     * @param Payable $order Order instance
     * @param array $customer Customer data array
     * @param string|null $originalDocumentId Original document ID for credit notes
     * @param bool $isDonation Whether this is a donation document
     * @return string|null Error message, or null on success
     */
    public static function createOrderDocument(
        Payable $order,
        array $customer,
        ?string $originalDocumentId = null,
        bool $isDonation = false
    ): ?string {
        try {
            // Determine document type - use DonationReceipt for donations
            $documentType = $isDonation ? self::TYPE_DONATION_RECEIPT : self::TYPE_ORDER;

            // Auto-detect donation from order items
            if (!$isDonation) {
                try {
                    $isDonation = DonationService::containsDonation($order) && !DonationService::containsNonDonation($order);
                    if ($isDonation) {
                        $documentType = self::TYPE_DONATION_RECEIPT;
                    }
                } catch (\Throwable $e) {
                    // DonationService not available, continue with default type
                }
            }

            // Create credentials DTO
            $credentials = new CredentialsData(
                companyId: (int) config('officeguy.company_id'),
                apiKey: (string) config('officeguy.private_key')
            );

            // Build request data
            $items = PaymentService::getDocumentOrderItems($order);
            $vatRate = PaymentService::getOrderVatRate($order);
            $language = PaymentService::getOrderLanguage();
            $description = __('Order number') . ': ' . $order->getPayableId() .
                (empty($order->getCustomerNote()) ? '' : "\r\n" . $order->getCustomerNote());
            $isDraft = config('officeguy.draft_document', false) ? 'true' : 'false';

            // Instantiate connector and inline request (custom structure)
            $connector = new SumitConnector();
            $request = new class(
                $credentials,
                $items,
                $vatRate,
                $customer,
                $isDraft,
                $language,
                $order->getPayableCurrency(),
                $documentType,
                $description,
                $originalDocumentId
            ) extends \Saloon\Http\Request implements \Saloon\Contracts\Body\HasBody {
                use \Saloon\Traits\Body\HasJsonBody;

                protected \Saloon\Enums\Method $method = \Saloon\Enums\Method::POST;

                public function __construct(
                    protected readonly CredentialsData $credentials,
                    protected readonly array $items,
                    protected readonly string $vatRate,
                    protected readonly array $customer,
                    protected readonly string $isDraft,
                    protected readonly string $language,
                    protected readonly string $currency,
                    protected readonly string $documentType,
                    protected readonly string $description,
                    protected readonly ?string $originalDocumentId
                ) {}

                public function resolveEndpoint(): string
                {
                    return '/accounting/documents/create/';
                }

                protected function defaultBody(): array
                {
                    $body = [
                        'Credentials' => $this->credentials->toArray(),
                        'Items' => $this->items,
                        'VATIncluded' => 'true',
                        'VATRate' => $this->vatRate,
                        'Details' => [
                            'Customer' => $this->customer,
                            'IsDraft' => $this->isDraft,
                            'Language' => $this->language,
                            'Currency' => $this->currency,
                            'Type' => $this->documentType,
                            'Description' => $this->description,
                        ],
                    ];

                    if ($this->originalDocumentId) {
                        $body['OriginalDocumentID'] = $this->originalDocumentId;
                    }

                    return $body;
                }

                protected function defaultConfig(): array
                {
                    return ['timeout' => 180];
                }
            };

            // Send request
            $response = $connector->send($request);
            $data = $response->json();

            if ($data && $data['Status'] === 0) {
                // Success
                $documentId = $data['Data']['DocumentID'];

                // Create document record
                OfficeGuyDocument::createFromApiResponse(
                    $order->getPayableId(),
                    $data,
                    $request->body()->all(),
                    get_class($order) // CRITICAL: Links document to order via polymorphic relationship
                );

                OfficeGuyApi::writeToLog(
                    'SUMIT order document created. Document ID: ' . $documentId,
                    'info'
                );

                event(new \OfficeGuy\LaravelSumitGateway\Events\DocumentCreated(
                    $order->getPayableId(),
                    $documentId,
                    $data['Data']['CustomerID'] ?? '',
                    $data
                ));

                return null;
            }

            // Error
            $errorMessage = __('Order creation failed.') . ' - ' . ($data['UserErrorMessage'] ?? 'Unknown error');
            OfficeGuyApi::writeToLog($errorMessage, 'error');

            return $errorMessage;

        } catch (\Throwable $e) {
            $errorMessage = __('Order creation failed.') . ' - ' . $e->getMessage();
            OfficeGuyApi::writeToLog($errorMessage, 'error');
            return $errorMessage;
        }
    }

    /**
     * Create document on payment complete (for third-party gateways like PayPal)
     *
     * Port of: CreateDocumentOnPaymentCompleteInternal($OrderID, $SkipPaymentMethodValidation)
     *
     * @param Payable $order Order instance
     * @param string $paymentMethod Payment method identifier
     * @param string|null $transactionId Transaction ID from payment gateway
     * @return string|null Error message, or null on success
     */
    public static function createDocumentOnPaymentComplete(
        Payable $order,
        string $paymentMethod,
        ?string $transactionId = null
    ): ?string {
        OfficeGuyApi::writeToLog(
            'Creating document for order #' . $order->getPayableId() . ' with payment method: ' . $paymentMethod,
            'debug'
        );

        $paymentDescription = 'Laravel';

        // Determine payment description based on method
        if (in_array($paymentMethod, ['paypal', 'eh_paypal_express', 'ppec_paypal', 'ppcp-gateway'])) {
            if (config('officeguy.paypal_receipts') === 'no') {
                return null; // Skip if PayPal receipts disabled
            }

            $paymentDescription = 'PayPal';
            if ($transactionId) {
                $paymentDescription .= ' - ' . $transactionId;
            }
        } elseif ($paymentMethod === 'bluesnap') {
            if (!config('officeguy.bluesnap_receipts', false)) {
                return null; // Skip if BlueSnap receipts disabled
            }

            $paymentDescription = 'BlueSnap';
        } elseif (config('officeguy.other_receipts') === $paymentMethod) {
            $paymentDescription = $paymentMethod;
        } else {
            return null; // Skip for other payment methods
        }

        try {
            // Create credentials DTO
            $credentials = new CredentialsData(
                companyId: (int) config('officeguy.company_id'),
                apiKey: (string) config('officeguy.private_key')
            );

            // Build request data
            $items = PaymentService::getDocumentOrderItems($order);
            $vatRate = PaymentService::getOrderVatRate($order);
            $customer = PaymentService::getOrderCustomer($order);
            $isDraft = config('officeguy.draft_document', false) ? 'true' : 'false';
            $language = PaymentService::getOrderLanguage();
            $description = __('Order number') . ': ' . $order->getPayableId() .
                (empty($order->getCustomerNote()) ? '' : "\r\n" . $order->getCustomerNote());
            $sendByEmail = config('officeguy.email_document', true);

            // Instantiate connector and inline request (custom Payments structure)
            $connector = new SumitConnector();
            $request = new class(
                $credentials,
                $items,
                $vatRate,
                $customer,
                $isDraft,
                $language,
                $order->getPayableCurrency(),
                $description,
                $paymentDescription,
                $sendByEmail
            ) extends \Saloon\Http\Request implements \Saloon\Contracts\Body\HasBody {
                use \Saloon\Traits\Body\HasJsonBody;

                protected \Saloon\Enums\Method $method = \Saloon\Enums\Method::POST;

                public function __construct(
                    protected readonly CredentialsData $credentials,
                    protected readonly array $items,
                    protected readonly string $vatRate,
                    protected readonly array $customer,
                    protected readonly string $isDraft,
                    protected readonly string $language,
                    protected readonly string $currency,
                    protected readonly string $description,
                    protected readonly string $paymentDescription,
                    protected readonly bool $sendByEmail
                ) {}

                public function resolveEndpoint(): string
                {
                    return '/accounting/documents/create/';
                }

                protected function defaultBody(): array
                {
                    $body = [
                        'Credentials' => $this->credentials->toArray(),
                        'Items' => $this->items,
                        'VATIncluded' => 'true',
                        'VATRate' => $this->vatRate,
                        'Details' => [
                            'IsDraft' => $this->isDraft,
                            'Customer' => $this->customer,
                            'Language' => $this->language,
                            'Currency' => $this->currency,
                            'Description' => $this->description,
                            'Type' => '1', // Invoice type
                        ],
                        'Payments' => [
                            [
                                'Details_Other' => [
                                    'Type' => 'Laravel',
                                    'Description' => $this->paymentDescription,
                                    'DueDate' => now()->toIso8601String(),
                                ],
                            ],
                        ],
                    ];

                    if ($this->sendByEmail) {
                        $body['Details']['SendByEmail'] = [
                            'Original' => 'true',
                        ];
                    }

                    return $body;
                }

                protected function defaultConfig(): array
                {
                    return ['timeout' => 180];
                }
            };

            // Send request
            $response = $connector->send($request);
            $data = $response->json();

            if ($data && $data['Status'] === 0) {
                // Success
                $documentId = $data['Data']['DocumentID'];
                $customerId = $data['Data']['CustomerID'];

                // Create document record
                OfficeGuyDocument::createFromApiResponse(
                    $order->getPayableId(),
                    $data,
                    $request->body()->all(),
                    get_class($order) // CRITICAL: Links document to order via polymorphic relationship
                );

                OfficeGuyApi::writeToLog(
                    'SUMIT document completed. Document ID: ' . $documentId . ', Customer ID: ' . $customerId,
                    'info'
                );

                event(new \OfficeGuy\LaravelSumitGateway\Events\DocumentCreated(
                    $order->getPayableId(),
                    $documentId,
                    $customerId,
                    $data
                ));

                return null;
            }

            // Error
            $errorMessage = __('Document creation failed') . ' - ' . ($data['UserErrorMessage'] ?? 'Unknown error');
            OfficeGuyApi::writeToLog($errorMessage, 'error');

            return $errorMessage;

        } catch (\Throwable $e) {
            $errorMessage = __('Document creation failed') . ' - ' . $e->getMessage();
            OfficeGuyApi::writeToLog($errorMessage, 'error');
            return $errorMessage;
        }
    }

    /**
     * Create a donation receipt document
     *
     * @param Payable $order Order/Donation instance
     * @param array|null $customer Customer data array (optional, will be extracted from order if null)
     * @return string|null Error message, or null on success
     */
    public static function createDonationReceipt(
        Payable $order,
        ?array $customer = null
    ): ?string {
        $customer = $customer ?? PaymentService::getOrderCustomer($order);

        return self::createOrderDocument($order, $customer, null, true);
    }

    /**
     * Get document type name for display
     *
     * @param string $type Document type code
     * @return string Human-readable type name
     */
    public static function getDocumentTypeName(string $type): string
    {
        return match ($type) {
            self::TYPE_INVOICE, '1' => __('Invoice'),
            self::TYPE_RECEIPT, '2' => __('Receipt'),
            self::TYPE_CREDIT_NOTE, '3' => __('Credit Note'),
            self::TYPE_ORDER, '8' => __('Order'),
            self::TYPE_DONATION_RECEIPT, '320' => __('Donation Receipt'),
            default => __('Document'),
        };
    }

    /**
     * Check if document type is a donation receipt
     *
     * @param string $type Document type code
     * @return bool
     */
    public static function isDonationReceiptType(string $type): bool
    {
        return $type === self::TYPE_DONATION_RECEIPT || $type === '320';
    }

    /**
     * Sync documents for a given Client (by SUMIT CustomerID) and link to orders when possible.
     */
    public static function syncForClient(Client $client, ?Carbon $dateFrom = null, ?Carbon $dateTo = null): int
    {
        if (!$client->sumit_customer_id) {
            return 0;
        }

        $documents = self::fetchFromSumit((int) $client->sumit_customer_id, $dateFrom, $dateTo);
        $synced = 0;

        foreach ($documents as $doc) {
            $document = OfficeGuyDocument::updateOrCreate(
                ['document_id' => $doc['DocumentID'] ?? null],
                [
                    'document_number' => $doc['DocumentNumber'] ?? null,
                    'document_date' => $doc['Date'] ?? now(),
                    'customer_id' => $doc['CustomerID'] ?? null,
                    'document_type' => $doc['Type'] ?? self::TYPE_INVOICE,
                    'is_draft' => $doc['IsDraft'] ?? false,
                    'is_closed' => $doc['IsClosed'] ?? false,
                    'language' => $doc['Language'] ?? 'he',
                    'currency' => $doc['Currency'] ?? 'ILS',
                    'amount' => $doc['DocumentValue'] ?? 0,
                    'description' => $doc['Description'] ?? null,
                    'external_reference' => $doc['ExternalReference'] ?? null,
                    'document_download_url' => $doc['DocumentDownloadURL'] ?? null,
                    'document_payment_url' => $doc['DocumentPaymentURL'] ?? null,
                    'raw_response' => $doc,
                ]
            );

            // Link to order by external reference or document number
            $order = null;
            $ext = $doc['ExternalReference'] ?? null;
            if ($ext) {
                $order = Order::where('client_id', $client->id)
                    ->where(function ($q) use ($ext) {
                        $q->where('order_number', $ext)
                            ->orWhere('id', is_numeric($ext) ? (int) $ext : 0);
                    })
                    ->latest('id')
                    ->first();
            }

            if (!$order && !empty($doc['DocumentNumber'])) {
                $order = Order::where('client_id', $client->id)
                    ->where('order_number', $doc['DocumentNumber'])
                    ->latest('id')
                    ->first();
            }

            if ($order) {
                $document->order_id = $order->id;
                $document->order_type = Order::class;
            }

            // Fetch items/details for richer data (best effort)
            if (empty($document->items) && !empty($doc['DocumentID'])) {
                $details = self::getDocumentDetails($doc['DocumentID']);
                if ($details && isset($details['Items'])) {
                    $document->items = $details['Items'];
                }
            }

            $document->save();
            $synced++;
        }

        return $synced;
    }

    /**
     * Fetch documents from SUMIT API for a customer
     *
     * @param int $sumitCustomerId SUMIT customer ID
     * @param \Carbon\Carbon|null $dateFrom Optional start date
     * @param \Carbon\Carbon|null $dateTo Optional end date
     * @param bool $includeDrafts Include draft documents
     * @return array List of documents from SUMIT
     */
    public static function fetchFromSumit(
        int $sumitCustomerId,
        ?\Carbon\Carbon $dateFrom = null,
        ?\Carbon\Carbon $dateTo = null,
        bool $includeDrafts = false
    ): array {
        try {
            $allDocuments = [];
            $startIndex = 0;
            $pageSize = 1000;
            $hasMoreResults = true;

            // Create credentials DTO
            $credentials = new CredentialsData(
                companyId: (int) config('officeguy.company_id'),
                apiKey: (string) config('officeguy.private_key')
            );

            // Instantiate connector
            $connector = new SumitConnector();

            // Fetch all pages using pagination
            while ($hasMoreResults) {
                // Build request inline (custom pagination format)
                $request = new class(
                    $credentials,
                    $startIndex,
                    $pageSize,
                    $dateFrom,
                    $dateTo,
                    $includeDrafts
                ) extends \Saloon\Http\Request implements \Saloon\Contracts\Body\HasBody {
                    use \Saloon\Traits\Body\HasJsonBody;

                    protected \Saloon\Enums\Method $method = \Saloon\Enums\Method::POST;

                    public function __construct(
                        protected readonly CredentialsData $credentials,
                        protected readonly int $startIndex,
                        protected readonly int $pageSize,
                        protected readonly ?\Carbon\Carbon $dateFrom,
                        protected readonly ?\Carbon\Carbon $dateTo,
                        protected readonly bool $includeDrafts
                    ) {}

                    public function resolveEndpoint(): string
                    {
                        return '/accounting/documents/list/';
                    }

                    protected function defaultBody(): array
                    {
                        $body = [
                            'Credentials' => $this->credentials->toArray(),
                            'DocumentTypes' => null,
                            'DocumentNumberFrom' => null,
                            'DocumentNumberTo' => null,
                            'DateFrom' => null,
                            'DateTo' => null,
                            'IncludeDrafts' => $this->includeDrafts,
                            'Paging' => [
                                'StartIndex' => $this->startIndex,
                                'PageSize' => $this->pageSize,
                            ],
                        ];

                        if ($this->dateFrom) {
                            $body['DateFrom'] = $this->dateFrom->format('Y-m-d');
                        }

                        if ($this->dateTo) {
                            $body['DateTo'] = $this->dateTo->format('Y-m-d');
                        }

                        return $body;
                    }
                };

                // Send request
                $response = $connector->send($request);
                $data = $response->json();

                if (!$data || ($data['Status'] ?? null) !== 0) {
                    break;
                }

                $documents = $data['Data']['Documents'] ?? [];

                if (empty($documents)) {
                    break;
                }

                $allDocuments = array_merge($allDocuments, $documents);

                // Check if there are more results based on API response
                $hasMoreResults = ($data['Data']['HasNextPage'] ?? false) === true;
                $startIndex += count($documents);

                // Safety limit to prevent infinite loops (max 100,000 documents)
                if ($startIndex > 100000) {
                    break;
                }
            }

            // Filter documents by customer ID and reindex array
            $filtered = array_filter($allDocuments, function ($doc) use ($sumitCustomerId) {
                return ($doc['CustomerID'] ?? null) === $sumitCustomerId;
            });

            return array_values($filtered);

        } catch (\Throwable $e) {
            OfficeGuyApi::writeToLog(
                'Fetch documents from SUMIT failed for customer ' . $sumitCustomerId . ': ' . $e->getMessage(),
                'error'
            );
            return [];
        }
    }

    /**
     * Get full document details from SUMIT including items
     *
     * @param string|int $documentId SUMIT document ID
     * @return array|null Document details with items, or null on failure
     */
    public static function getDocumentDetails(string|int $documentId): ?array
    {
        try {
            // Create credentials DTO
            $credentials = new CredentialsData(
                companyId: (int) config('officeguy.company_id'),
                apiKey: (string) config('officeguy.private_key')
            );

            // Instantiate connector and request
            $connector = new SumitConnector();
            $request = new GetDocumentDetailsRequest(
                documentId: $documentId,
                credentials: $credentials
            );

            // Send request
            $response = $connector->send($request);
            $data = $response->json();

            if (!$data || ($data['Status'] ?? null) !== 0) {
                return null;
            }

            return $data['Data'] ?? null;

        } catch (\Throwable $e) {
            OfficeGuyApi::writeToLog(
                'Get document details failed for ID ' . $documentId . ': ' . $e->getMessage(),
                'error'
            );
            return null;
        }
    }

    /**
     * Identify all subscriptions in a document by analyzing items
     *
     * A document can contain charges for multiple subscriptions.
     * This method finds all matching subscriptions based on item names.
     *
     * @param array $fullDetails Full document details from getDocumentDetails
     * @param int $sumitCustomerId SUMIT customer ID to filter subscriptions
     * @return array Array of ['subscription' => Subscription, 'amount' => float, 'items' => array]
     */
    protected static function identifySubscriptionsInDocument(array $fullDetails, int $sumitCustomerId): array
    {
        $items = $fullDetails['Items'] ?? [];
        if (empty($items)) {
            return [];
        }

        $matches = [];

        // Get ALL subscriptions for this customer (including cancelled ones)
        // IMPORTANT: We don't filter by status here because documents can contain
        // charges for subscriptions that were later cancelled/paused
        // We need to handle polymorphic 'subscriber' relationship properly
        // Since subscriber can be User or other models, we check all possible types
        $subscriptions = \OfficeGuy\LaravelSumitGateway\Models\Subscription::query()
            ->where(function ($q) use ($sumitCustomerId) {
                // For User subscribers (most common case)
                $q->where('subscriber_type', 'App\\Models\\User')
                  ->whereIn('subscriber_id', function ($subQ) use ($sumitCustomerId) {
                      $subQ->select('id')
                           ->from('users')
                           ->where('sumit_customer_id', $sumitCustomerId);
                  });

                // TODO: Add other subscriber types if needed (e.g., 'App\Models\Client')
            })
            // NO status filter - we want ALL subscriptions (active, cancelled, paused, etc.)
            ->get();

        // For each item in the document, try to match it to a subscription
        foreach ($items as $itemData) {
            $itemName = $itemData['Item']['Name'] ?? '';
            $itemAmount = (float)($itemData['TotalPrice'] ?? 0);
            $itemId = $itemData['Item']['ID'] ?? null;

            if (empty($itemName)) {
                continue;
            }

            // Try to match item to subscriptions using multiple criteria
            $matchedSubs = [];

            foreach ($subscriptions as $subscription) {
                $nameMatch = strtolower(trim($itemName)) === strtolower(trim($subscription->name));

                // If name matches, check if Item ID also matches (for better accuracy)
                if ($nameMatch) {
                    $metadataItemId = $subscription->metadata['sumit_item_id'] ?? null;

                    // If we have an Item ID, use it for stricter matching
                    if ($itemId && $metadataItemId) {
                        if ((int)$itemId === (int)$metadataItemId) {
                            $matchedSubs[] = $subscription;
                        }
                    } else {
                        // No Item ID available, rely on name only
                        $matchedSubs[] = $subscription;
                    }
                }
            }

            // If multiple subscriptions matched the same item (e.g., 5 subscriptions for same domain),
            // add them ALL to the matches (the document belongs to all of them)
            foreach ($matchedSubs as $subscription) {
                $matches[] = [
                    'subscription' => $subscription,
                    'amount' => $itemAmount,
                    'items' => [$itemData],
                ];
            }
        }

        return $matches;
    }

    /**
     * Sync ALL documents from SUMIT for a customer
     *
     * This syncs all documents regardless of subscription matching.
     * Documents will be saved with intelligent subscription mapping when possible.
     *
     * @param int $sumitCustomerId SUMIT customer ID
     * @param \Carbon\Carbon|null $dateFrom Optional start date
     * @param \Carbon\Carbon|null $dateTo Optional end date
     * @return int Number of documents synced
     */
    public static function syncAllForCustomer(
        int $sumitCustomerId,
        ?\Carbon\Carbon $dateFrom = null,
        ?\Carbon\Carbon $dateTo = null
    ): int {
        // Default to 5 years ago to catch ALL historical documents
        // This ensures we get documents for subscriptions created recently but with older invoices
        if (!$dateFrom) {
            $dateFrom = now()->subYears(5);
        }

        if (!$dateTo) {
            $dateTo = now();
        }

        $sumitDocs = self::fetchFromSumit($sumitCustomerId, $dateFrom, $dateTo);

        $syncedCount = 0;

        foreach ($sumitDocs as $doc) {
            $documentId = $doc['DocumentID'] ?? null;
            if (!$documentId) {
                continue;
            }

            // Fetch full document details including items
            $fullDetails = self::getDocumentDetails($documentId);

            // Convert SUMIT numeric codes to readable values
            $currencyCode = $doc['Currency'] ?? 0;
            $currency = match ((int)$currencyCode) {
                0 => 'ILS',
                1 => 'USD',
                2 => 'EUR',
                default => 'ILS',
            };

            $languageCode = $doc['Language'] ?? 0;
            $language = match ((int)$languageCode) {
                0 => 'he',
                1 => 'en',
                default => 'he',
            };

            // Save document (update or create)
            $document = OfficeGuyDocument::updateOrCreate(
                [
                    'document_id' => $doc['DocumentID'],
                ],
                [
                    'document_number' => $doc['DocumentNumber'] ?? null,
                    'document_date' => isset($doc['Date']) ? \Carbon\Carbon::parse($doc['Date']) : now(),
                    'order_id' => null,
                    'order_type' => null,
                    'subscription_id' => null, // Will be set by pivot table
                    'customer_id' => $doc['CustomerID'] ?? null,
                    'document_type' => (string)($doc['Type'] ?? '1'),
                    'is_draft' => ($doc['IsDraft'] ?? false) === true || ($doc['IsDraft'] ?? false) === 'true',
                    'is_closed' => ($fullDetails['IsClosed'] ?? $doc['IsClosed'] ?? false) === true ||
                                   ($fullDetails['IsClosed'] ?? $doc['IsClosed'] ?? false) === 'true' ||
                                   ($fullDetails ? empty($fullDetails['DocumentPaymentURL']) : empty($doc['DocumentPaymentURL'])),
                    'language' => $language,
                    'currency' => $currency,
                    'amount' => $doc['DocumentValue'] ?? 0,
                    'description' => $doc['Description'] ?? null,
                    'external_reference' => $doc['ExternalReference'] ?? null,
                    'document_download_url' => $fullDetails['DocumentDownloadURL'] ?? $doc['DocumentDownloadURL'] ?? null,
                    'document_payment_url' => $fullDetails
                        ? ($fullDetails['DocumentPaymentURL'] ?? null)
                        : ($doc['DocumentPaymentURL'] ?? null),
                    'items' => $fullDetails['Items'] ?? null,
                    'raw_response' => $doc,
                ]
            );

            // Intelligent subscription mapping (many-to-many)
            if ($fullDetails && $sumitCustomerId) {
                $subscriptionsInDoc = self::identifySubscriptionsInDocument($fullDetails, $sumitCustomerId);

                // Sync to pivot table
                if (!empty($subscriptionsInDoc)) {
                    foreach ($subscriptionsInDoc as $subData) {
                        $document->subscriptions()->syncWithoutDetaching([
                            $subData['subscription']->id => [
                                'amount' => $subData['amount'],
                                'item_data' => json_encode($subData['items']),
                            ],
                        ]);
                    }

                    // Update legacy subscription_id to first matched subscription
                    if ($document->subscription_id === null) {
                        $document->update(['subscription_id' => $subscriptionsInDoc[0]['subscription']->id]);
                    }
                }
            }

            $syncedCount++;
        }

        return $syncedCount;
    }

    /**
     * Sync documents from SUMIT for a subscription
     *
     * @param \OfficeGuy\LaravelSumitGateway\Models\Subscription $subscription
     * @param \Carbon\Carbon|null $dateFrom Optional start date (default: subscription created_at)
     * @return int Number of documents synced
     */
    public static function syncForSubscription(
        $subscription,
        ?\Carbon\Carbon $dateFrom = null
    ): int {
        // Get subscriber's SUMIT customer ID
        $subscriber = $subscription->subscriber;
        $sumitCustomerId = $subscriber->sumit_customer_id ?? null;

        if (!$sumitCustomerId) {
            return 0;
        }

        // Default to 5 years ago to catch ALL historical documents
        // This ensures we get documents created before the subscription was synced to our system
        if (!$dateFrom) {
            $dateFrom = now()->subYears(5);
        }

        $sumitDocs = self::fetchFromSumit(
            (int) $sumitCustomerId,
            $dateFrom,
            now()
        );

        $syncedCount = 0;

        foreach ($sumitDocs as $doc) {
            $isMatch = false;
            $documentId = $doc['DocumentID'] ?? null;

            // Fetch full document details including items
            $fullDetails = null;
            if ($documentId) {
                $fullDetails = self::getDocumentDetails($documentId);
            }

            // Method 1: Match by ExternalReference (PRIMARY - most reliable)
            // Format: subscription_{id}_recurring_{recurring_id}
            $externalRef = $doc['ExternalReference'] ?? null;
            if ($externalRef) {
                // Check for our standard format
                $expectedRef = 'subscription_' . $subscription->id . '_recurring_' . $subscription->recurring_id;
                if ($externalRef === $expectedRef) {
                    $isMatch = true;
                }
                // Fallback: Check if it contains the recurring_id (for older documents)
                elseif ($subscription->recurring_id && str_contains($externalRef, (string) $subscription->recurring_id)) {
                    $isMatch = true;
                }
            }

            // Method 2: Match by description (EXACT match for short names, contains for longer descriptions)
            if (!$isMatch && $subscription->name) {
                $description = $doc['Description'] ?? '';
                if (!empty($description)) {
                    // For very short subscription names (≤3 chars), use exact word match to avoid false positives
                    if (mb_strlen($subscription->name) <= 3) {
                        // Match as whole word with word boundaries
                        $pattern = '/\b' . preg_quote($subscription->name, '/') . '\b/ui';
                        if (preg_match($pattern, $description)) {
                            $isMatch = true;
                        }
                    } else {
                        // For longer names, contains is safe
                        if (str_contains($description, $subscription->name)) {
                            $isMatch = true;
                        }
                    }
                }
            }

            // Method 3: Match by item name (EXACT match to avoid false positives)
            if (!$isMatch && $subscription->name && $fullDetails && isset($fullDetails['Items'])) {
                foreach ($fullDetails['Items'] as $item) {
                    $itemName = $item['Item']['Name'] ?? '';
                    // Use exact match (case-insensitive) to avoid matching "היי" to "הייי"
                    if (!empty($itemName) && strtolower(trim($itemName)) === strtolower(trim($subscription->name))) {
                        $isMatch = true;
                        break;
                    }
                }
            }

            // Method 4: Match by amount (ONLY as last resort with strict conditions)
            if (!$isMatch && $subscription->amount) {
                $docAmount = abs((float)($doc['DocumentValue'] ?? 0));
                $subAmount = (float)$subscription->amount;

                // Allow small rounding differences (1 cent)
                $amountMatches = abs($docAmount - $subAmount) < 0.01;

                if ($amountMatches) {
                    // CRITICAL: Only match by amount if:
                    // 1. Document has NO description or items (fallback only)
                    // 2. OR amount is very unique (> 100 to reduce false positives)
                    $hasNoMetadata = empty($doc['Description']) &&
                                     (!isset($fullDetails['Items']) || empty($fullDetails['Items']));
                    $isUniqueAmount = $subAmount > 100;

                    if ($hasNoMetadata || $isUniqueAmount) {
                        $isMatch = true;
                    }
                    // Otherwise: Skip this document even if amount matches
                    // (likely a false positive for common amounts like 10, 20, 30)
                }
            }

            if ($isMatch) {
                // Convert SUMIT numeric codes to readable values
                $currencyCode = $doc['Currency'] ?? 0;
                $currency = match ((int)$currencyCode) {
                    0 => 'ILS',
                    1 => 'USD',
                    2 => 'EUR',
                    default => 'ILS',
                };

                $languageCode = $doc['Language'] ?? 0;
                $language = match ((int)$languageCode) {
                    0 => 'he',
                    1 => 'en',
                    default => 'he',
                };

                $document = OfficeGuyDocument::updateOrCreate(
                    [
                        'document_id' => $doc['DocumentID'],
                    ],
                    [
                        'document_number' => $doc['DocumentNumber'] ?? null,
                        'document_date' => isset($doc['Date']) ? \Carbon\Carbon::parse($doc['Date']) : now(),
                        'order_id' => null, // No specific order for subscription documents
                        'order_type' => null,
                        'subscription_id' => $subscription->id, // Legacy: keep first/primary subscription
                        'customer_id' => $doc['CustomerID'] ?? null,
                        'document_type' => (string)($doc['Type'] ?? '1'),
                        'is_draft' => ($doc['IsDraft'] ?? false) === true || ($doc['IsDraft'] ?? false) === 'true',
                        // SUMIT logic: if DocumentPaymentURL is null = paid, if exists = unpaid
                        // Use fullDetails (getdetails) for accurate payment status
                        'is_closed' => ($fullDetails['IsClosed'] ?? $doc['IsClosed'] ?? false) === true ||
                                       ($fullDetails['IsClosed'] ?? $doc['IsClosed'] ?? false) === 'true' ||
                                       ($fullDetails ? empty($fullDetails['DocumentPaymentURL']) : empty($doc['DocumentPaymentURL'])),
                        'language' => $language,
                        'currency' => $currency,
                        'amount' => $doc['DocumentValue'] ?? 0,
                        'description' => $doc['Description'] ?? null,
                        'external_reference' => $doc['ExternalReference'] ?? null,
                        'document_download_url' => $fullDetails['DocumentDownloadURL'] ?? $doc['DocumentDownloadURL'] ?? null,
                        'document_payment_url' => $fullDetails
                            ? ($fullDetails['DocumentPaymentURL'] ?? null)
                            : ($doc['DocumentPaymentURL'] ?? null),
                        'items' => $fullDetails['Items'] ?? null,
                        'raw_response' => $doc,
                    ]
                );

                // NEW: Identify ALL subscriptions in this document (many-to-many)
                if ($fullDetails && $sumitCustomerId) {
                    $subscriptionsInDoc = self::identifySubscriptionsInDocument($fullDetails, (int)$sumitCustomerId);

                    // Sync to pivot table
                    foreach ($subscriptionsInDoc as $subData) {
                        $document->subscriptions()->syncWithoutDetaching([
                            $subData['subscription']->id => [
                                'amount' => $subData['amount'],
                                'item_data' => json_encode($subData['items']),
                            ],
                        ]);
                    }
                }

                $syncedCount++;
            }
        }

        return $syncedCount;
    }

    /**
     * Create a credit note document in SUMIT
     *
     * Creates a credit note (תעודת זיכוי) for a customer, optionally linked to an original document.
     * This is an accounting credit, NOT a refund to the payment method.
     *
     * @param \OfficeGuy\LaravelSumitGateway\Contracts\HasSumitCustomer $customer Customer instance
     * @param float $amount Credit amount
     * @param string $description Credit description (default: זיכוי)
     * @param int|null $originalDocumentId Optional original document ID to link to
     * @return array{success: bool, document_id?: int, document_number?: string, amount?: float, error?: string}
     */
    public static function createCreditNote(
        \OfficeGuy\LaravelSumitGateway\Contracts\HasSumitCustomer $customer,
        float $amount,
        string $description = 'זיכוי',
        ?int $originalDocumentId = null
    ): array {
        $sumitCustomerId = $customer->getSumitCustomerId();

        if (!$sumitCustomerId) {
            return [
                'success' => false,
                'error' => 'Customer not synced with SUMIT',
            ];
        }

        try {
            // Create credentials DTO
            $credentials = new CredentialsData(
                companyId: (int) config('officeguy.company_id'),
                apiKey: (string) config('officeguy.private_key')
            );

            // Extract request data
            $customerEmail = $customer->getSumitCustomerEmail();

            // Instantiate connector and inline request
            $connector = new SumitConnector();
            $request = new class(
                $credentials,
                $sumitCustomerId,
                $amount,
                $description,
                $customerEmail,
                $originalDocumentId
            ) extends \Saloon\Http\Request implements \Saloon\Contracts\Body\HasBody {
                use \Saloon\Traits\Body\HasJsonBody;

                protected \Saloon\Enums\Method $method = \Saloon\Enums\Method::POST;

                public function __construct(
                    protected readonly CredentialsData $credentials,
                    protected readonly string $sumitCustomerId,
                    protected readonly float $amount,
                    protected readonly string $description,
                    protected readonly string $customerEmail,
                    protected readonly ?int $originalDocumentId
                ) {}

                public function resolveEndpoint(): string
                {
                    return '/accounting/documents/create/';
                }

                protected function defaultBody(): array
                {
                    $body = [
                        'Credentials' => $this->credentials->toArray(),
                        'Details' => [
                            'Type' => 3, // CreditNote (תעודת זיכוי)
                            'Customer' => [
                                'ID' => (int) $this->sumitCustomerId,
                            ],
                            'Description' => $this->description,
                            'Currency' => 0, // ILS = 0 (NOT 1!)
                            'Language' => 0, // Hebrew
                            'SendByEmail' => [
                                'EmailAddress' => $this->customerEmail,
                                'Original' => true,
                                'SendAsPaymentRequest' => false,
                            ],
                        ],
                        'Items' => [
                            [
                                'Item' => [
                                    'Name' => $this->description,
                                ],
                                'Quantity' => 1,
                                'UnitPrice' => $this->amount,
                                'TotalPrice' => $this->amount,
                            ],
                        ],
                        'VATIncluded' => false,
                    ];

                    // Link to original document if provided
                    if ($this->originalDocumentId) {
                        $body['Details']['OriginalDocumentID'] = (int) $this->originalDocumentId;
                    }

                    return $body;
                }

                protected function defaultConfig(): array
                {
                    return ['timeout' => 180];
                }
            };

            // Send request
            $response = $connector->send($request);
            $data = $response->json();

            $status = $data['Status'] ?? null;

            if ($status === 0 || $status === '0') {
                $documentId = $data['Data']['DocumentID'] ?? null;
                $documentNumber = $data['Data']['DocumentNumber'] ?? null;

                OfficeGuyApi::writeToLog(
                    'SUMIT credit note created successfully. Document ID: ' . $documentId,
                    'info'
                );

                return [
                    'success' => true,
                    'document_id' => $documentId,
                    'document_number' => $documentNumber,
                    'amount' => $amount,
                ];
            }

            OfficeGuyApi::writeToLog(
                'SUMIT credit note creation failed: ' . ($data['UserErrorMessage'] ?? 'Unknown error'),
                'warning'
            );

            return [
                'success' => false,
                'error' => $data['UserErrorMessage'] ?? 'Unknown error',
            ];

        } catch (\Throwable $e) {
            OfficeGuyApi::writeToLog(
                'SUMIT credit note creation exception: ' . $e->getMessage(),
                'error'
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get document PDF URL from SUMIT
     *
     * @param int $documentId SUMIT document ID
     * @return array{success: bool, pdf_url?: string, error?: string}
     */
    public static function getDocumentPDF(int $documentId): array
    {
        try {
            // Create credentials DTO
            $credentials = new CredentialsData(
                companyId: (int) config('officeguy.company_id'),
                apiKey: (string) config('officeguy.private_key')
            );

            // Instantiate connector and inline request
            $connector = new SumitConnector();
            $request = new class(
                $credentials,
                $documentId
            ) extends \Saloon\Http\Request implements \Saloon\Contracts\Body\HasBody {
                use \Saloon\Traits\Body\HasJsonBody;

                protected \Saloon\Enums\Method $method = \Saloon\Enums\Method::POST;

                public function __construct(
                    protected readonly CredentialsData $credentials,
                    protected readonly int $documentId
                ) {}

                public function resolveEndpoint(): string
                {
                    return '/accounting/documents/getpdf/';
                }

                protected function defaultBody(): array
                {
                    return [
                        'Credentials' => $this->credentials->toArray(),
                        'DocumentID' => $this->documentId,
                    ];
                }

                protected function defaultConfig(): array
                {
                    return ['timeout' => 180];
                }
            };

            // Send request
            $response = $connector->send($request);
            $data = $response->json();

            if (($data['Status'] ?? null) === 0) {
                return [
                    'success' => true,
                    'pdf_url' => $data['Data']['PDFURL'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error' => $data['UserErrorMessage'] ?? 'Failed to get PDF',
            ];

        } catch (\Throwable $e) {
            OfficeGuyApi::writeToLog(
                'SUMIT get PDF exception for document ' . $documentId . ': ' . $e->getMessage(),
                'error'
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send document by email via SUMIT
     *
     * CRITICAL: SUMIT's /send/ endpoint requires DocumentType + DocumentNumber,
     * NOT DocumentID! Using DocumentID will result in "Document not found" error.
     *
     * @param int|OfficeGuyDocument $document SUMIT document ID OR OfficeGuyDocument model
     * @param string|null $email Email address to send to (null = use customer's SUMIT email)
     * @param string|null $personalMessage Optional personal message to include in email
     * @param bool $original Send original document (default: true)
     * @return array{success: bool, error?: string}
     */
    public static function sendByEmail(
        int|OfficeGuyDocument $document,
        ?string $email = null,
        ?string $personalMessage = null,
        bool $original = true
    ): array {
        try {
            // If integer provided (legacy), fetch the document model
            if (is_int($document)) {
                $documentModel = OfficeGuyDocument::where('document_id', $document)->first();
                if (!$documentModel) {
                    return [
                        'success' => false,
                        'error' => 'Document not found in local database',
                    ];
                }
                $document = $documentModel;
            }

            // Create credentials DTO
            $credentials = new CredentialsData(
                companyId: (int) config('officeguy.company_id'),
                apiKey: (string) config('officeguy.private_key')
            );

            // Instantiate connector and inline request
            $connector = new SumitConnector();
            $request = new class(
                $credentials,
                $document->document_type,
                $document->document_number,
                $original,
                $email,
                $personalMessage
            ) extends \Saloon\Http\Request implements \Saloon\Contracts\Body\HasBody {
                use \Saloon\Traits\Body\HasJsonBody;

                protected \Saloon\Enums\Method $method = \Saloon\Enums\Method::POST;

                public function __construct(
                    protected readonly CredentialsData $credentials,
                    protected readonly int $documentType,
                    protected readonly int $documentNumber,
                    protected readonly bool $original,
                    protected readonly ?string $email,
                    protected readonly ?string $personalMessage
                ) {}

                public function resolveEndpoint(): string
                {
                    return '/accounting/documents/send/';
                }

                protected function defaultBody(): array
                {
                    $body = [
                        'Credentials' => $this->credentials->toArray(),
                        'DocumentType' => $this->documentType,
                        'DocumentNumber' => $this->documentNumber,
                        'Original' => $this->original,
                    ];

                    // Add email address if provided (otherwise SUMIT uses customer's registered email)
                    if ($this->email) {
                        $body['EmailAddress'] = $this->email;
                    }

                    // Add personal message if provided
                    if ($this->personalMessage) {
                        $body['PersonalMessage'] = $this->personalMessage;
                    }

                    return $body;
                }

                protected function defaultConfig(): array
                {
                    return ['timeout' => 180];
                }
            };

            // Send request
            $response = $connector->send($request);
            $data = $response->json();

            if (($data['Status'] ?? null) === 0) {
                $logEmail = $email ?? 'customer registered email';
                OfficeGuyApi::writeToLog(
                    'SUMIT document sent by email. Document #' . $document->document_number . ' (Type: ' . $document->document_type . '), Email: ' . $logEmail,
                    'info'
                );

                return ['success' => true];
            }

            return [
                'success' => false,
                'error' => $data['UserErrorMessage'] ?? 'Failed to send email',
            ];

        } catch (\Throwable $e) {
            $docInfo = is_object($document) ? "#{$document->document_number}" : "ID {$document}";
            OfficeGuyApi::writeToLog(
                'SUMIT send email exception for document ' . $docInfo . ': ' . $e->getMessage(),
                'error'
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Cancel (delete) document in SUMIT
     *
     * Creates a cancellation credit note for the specified document.
     *
     * @param int $documentId SUMIT document ID to cancel
     * @param string $description Reason for cancellation (default: ביטול מסמך)
     * @return array{success: bool, original_document_id?: int, credit_document_id?: int, credit_document_number?: string, credit_document_url?: string, error?: string}
     */
    public static function cancelDocument(int $documentId, string $description = 'ביטול מסמך'): array
    {
        try {
            // Create credentials DTO
            $credentials = new CredentialsData(
                companyId: (int) config('officeguy.company_id'),
                apiKey: (string) config('officeguy.private_key')
            );

            // Instantiate connector and inline request
            $connector = new SumitConnector();
            $request = new class(
                $credentials,
                $documentId,
                $description
            ) extends \Saloon\Http\Request implements \Saloon\Contracts\Body\HasBody {
                use \Saloon\Traits\Body\HasJsonBody;

                protected \Saloon\Enums\Method $method = \Saloon\Enums\Method::POST;

                public function __construct(
                    protected readonly CredentialsData $credentials,
                    protected readonly int $documentId,
                    protected readonly string $description
                ) {}

                public function resolveEndpoint(): string
                {
                    return '/accounting/documents/cancel/';
                }

                protected function defaultBody(): array
                {
                    return [
                        'Credentials' => $this->credentials->toArray(),
                        'DocumentID' => $this->documentId,
                        'Description' => $this->description,
                    ];
                }

                protected function defaultConfig(): array
                {
                    return ['timeout' => 180];
                }
            };

            // Send request
            $response = $connector->send($request);
            $data = $response->json();

            if (($data['Status'] ?? null) === 0) {
                $responseData = $data['Data'] ?? [];

                OfficeGuyApi::writeToLog(
                    'SUMIT document cancelled successfully. Document ID: ' . $documentId,
                    'info'
                );

                return [
                    'success' => true,
                    'original_document_id' => $documentId,
                    'credit_document_id' => $responseData['DocumentID'] ?? null,
                    'credit_document_number' => $responseData['DocumentNumber'] ?? null,
                    'credit_document_url' => $responseData['DocumentDownloadURL'] ?? null,
                    'description' => $description,
                    'cancelled_at' => now()->toDateTimeString(),
                    'gateway_response' => $data,
                ];
            }

            return [
                'success' => false,
                'error' => $data['UserErrorMessage'] ?? 'Failed to cancel document',
                'technical_details' => $data['TechnicalErrorDetails'] ?? null,
            ];

        } catch (\Throwable $e) {
            OfficeGuyApi::writeToLog(
                'SUMIT cancel document exception for document ' . $documentId . ': ' . $e->getMessage(),
                'error'
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
