<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use OfficeGuy\LaravelSumitGateway\Contracts\Payable;
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

        $request = [
            'Credentials' => PaymentService::getCredentials(),
            'Items' => PaymentService::getDocumentOrderItems($order),
            'VATIncluded' => 'true',
            'VATRate' => PaymentService::getOrderVatRate($order),
            'Details' => [
                'Customer' => $customer,
                'IsDraft' => config('officeguy.draft_document', false) ? 'true' : 'false',
                'Language' => PaymentService::getOrderLanguage(),
                'Currency' => $order->getPayableCurrency(),
                'Type' => $documentType,
                'Description' => __('Order number') . ': ' . $order->getPayableId() .
                    (empty($order->getCustomerNote()) ? '' : "\r\n" . $order->getCustomerNote()),
            ],
        ];

        if ($originalDocumentId) {
            $request['OriginalDocumentID'] = $originalDocumentId;
        }

        $environment = config('officeguy.environment', 'www');
        $response = OfficeGuyApi::post($request, '/accounting/documents/create/', $environment, false);

        if ($response && $response['Status'] === 0) {
            // Success
            $documentId = $response['Data']['DocumentID'];

            // Create document record
            OfficeGuyDocument::createFromApiResponse(
                $order->getPayableId(),
                $response,
                $request
            );

            OfficeGuyApi::writeToLog(
                'SUMIT order document created. Document ID: ' . $documentId,
                'info'
            );

            event(new \OfficeGuy\LaravelSumitGateway\Events\DocumentCreated(
                $order->getPayableId(),
                $documentId,
                $response['Data']['CustomerID'] ?? '',
                $response
            ));

            return null;
        }

        // Error
        $errorMessage = __('Order creation failed.') . ' - ' . ($response['UserErrorMessage'] ?? 'Unknown error');
        OfficeGuyApi::writeToLog($errorMessage, 'error');

        return $errorMessage;
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

        $request = [
            'Credentials' => PaymentService::getCredentials(),
            'Items' => PaymentService::getDocumentOrderItems($order),
            'VATIncluded' => 'true',
            'VATRate' => PaymentService::getOrderVatRate($order),
            'Details' => [
                'IsDraft' => config('officeguy.draft_document', false) ? 'true' : 'false',
                'Customer' => PaymentService::getOrderCustomer($order),
                'Language' => PaymentService::getOrderLanguage(),
                'Currency' => $order->getPayableCurrency(),
                'Description' => __('Order number') . ': ' . $order->getPayableId() .
                    (empty($order->getCustomerNote()) ? '' : "\r\n" . $order->getCustomerNote()),
                'Type' => '1', // Invoice type
            ],
            'Payments' => [
                [
                    'Details_Other' => [
                        'Type' => 'Laravel',
                        'Description' => $paymentDescription,
                        'DueDate' => now()->toIso8601String(),
                    ],
                ],
            ],
        ];

        if (config('officeguy.email_document', true)) {
            $request['Details']['SendByEmail'] = [
                'Original' => 'true',
            ];
        }

        $environment = config('officeguy.environment', 'www');
        $response = OfficeGuyApi::post($request, '/accounting/documents/create/', $environment, false);

        if ($response && $response['Status'] === 0) {
            // Success
            $documentId = $response['Data']['DocumentID'];
            $customerId = $response['Data']['CustomerID'];

            // Create document record
            OfficeGuyDocument::createFromApiResponse(
                $order->getPayableId(),
                $response,
                $request
            );

            OfficeGuyApi::writeToLog(
                'SUMIT document completed. Document ID: ' . $documentId . ', Customer ID: ' . $customerId,
                'info'
            );

            event(new \OfficeGuy\LaravelSumitGateway\Events\DocumentCreated(
                $order->getPayableId(),
                $documentId,
                $customerId,
                $response
            ));

            return null;
        }

        // Error
        $errorMessage = __('Document creation failed') . ' - ' . ($response['UserErrorMessage'] ?? 'Unknown error');
        OfficeGuyApi::writeToLog($errorMessage, 'error');

        return $errorMessage;
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
        $allDocuments = [];
        $startIndex = 0;
        $pageSize = 1000;
        $hasMoreResults = true;
        $environment = config('officeguy.environment', 'www');

        // Fetch all pages using pagination
        while ($hasMoreResults) {
            $request = [
                'Credentials' => PaymentService::getCredentials(),
                'DocumentTypes' => null,
                'DocumentNumberFrom' => null,
                'DocumentNumberTo' => null,
                'DateFrom' => null,
                'DateTo' => null,
                'IncludeDrafts' => $includeDrafts,
                'Paging' => [
                    'StartIndex' => $startIndex,
                    'PageSize' => $pageSize,
                ],
            ];

            if ($dateFrom) {
                $request['DateFrom'] = $dateFrom->format('Y-m-d');
            }

            if ($dateTo) {
                $request['DateTo'] = $dateTo->format('Y-m-d');
            }

            $response = OfficeGuyApi::post(
                $request,
                '/accounting/documents/list/',
                $environment,
                false
            );

            if (!$response || ($response['Status'] ?? null) !== 0) {
                break;
            }

            $documents = $response['Data']['Documents'] ?? [];

            if (empty($documents)) {
                break;
            }

            $allDocuments = array_merge($allDocuments, $documents);

            // Check if there are more results based on API response
            $hasMoreResults = ($response['Data']['HasNextPage'] ?? false) === true;
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
    }

    /**
     * Get full document details from SUMIT including items
     *
     * @param string|int $documentId SUMIT document ID
     * @return array|null Document details with items, or null on failure
     */
    public static function getDocumentDetails(string|int $documentId): ?array
    {
        $request = [
            'Credentials' => PaymentService::getCredentials(),
            'DocumentID' => $documentId,
        ];

        $environment = config('officeguy.environment', 'www');
        $response = OfficeGuyApi::post(
            $request,
            '/accounting/documents/getdetails/',
            $environment,
            false
        );

        if (!$response || ($response['Status'] ?? null) !== 0) {
            return null;
        }

        return $response['Data'] ?? null;
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
}
