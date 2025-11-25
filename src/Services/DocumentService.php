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
}
