<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use OfficeGuy\LaravelSumitGateway\Contracts\HasSumitCustomer;
use OfficeGuy\LaravelSumitGateway\Support\Traits\HasSumitCustomerTrait;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;
use App\Models\SmsMessage;
use OfficeGuy\LaravelSumitGateway\Services\DocumentService;
use OfficeGuy\LaravelSumitGateway\Services\PaymentService;

/**
 * Service for managing customer debt and credit balance in SUMIT
 *
 * This service provides methods to:
 * - Retrieve customer balance (debt/credit)
 * - Get detailed balance reports
 * - Retrieve payment history
 * - Calculate balances for multiple customers
 *
 * Example usage:
 * ```php
 * $debtService = app(DebtService::class);
 * $balance = $debtService->getCustomerBalance($client);
 *
 * if ($balance) {
 *     echo $balance['formatted']; // "₪150.50 (חוב)" or "₪50.00 (זכות)"
 * }
 * ```
 */
class DebtService
{
    public function __construct(
        private SettingsService $settings
    ) {}

    /**
     * Get customer debt/credit balance from SUMIT
     *
     * IMPORTANT: Correct balance calculation uses:
     * - DebitSource: 4 (Receipt - payments received)
     * - CreditSource: 1 (TaxInvoice - invoices issued)
     *
     * Result interpretation:
     * - Positive value = Customer owes money (חוב)
     * - Negative value = Customer has credit balance (זכות)
     * - Zero = Balanced (מאוזן)
     *
     * @param HasSumitCustomer $customer The customer to check balance for
     * @return array{debt: float, currency: string, last_updated: string, formatted: string}|null
     *         Returns null if customer has no SUMIT ID or if API call fails
     */
    public function getCustomerBalance(HasSumitCustomer $customer): ?array
    {
        $sumitCustomerId = $customer->getSumitCustomerId();

        if (! $sumitCustomerId) {
            return null;
        }

        try {
            // Create credentials DTO
            $credentials = new \OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData(
                companyId: (int) config('officeguy.company_id'),
                apiKey: (string) config('officeguy.private_key')
            );

            // Extract request parameters
            $customerId = (int) $sumitCustomerId;
            $debitSource = 4; // Receipt (קבלות - תשלומים שהתקבלו)
            $creditSource = 1; // TaxInvoice (חשבוניות מס)
            $includeDraftDocuments = false;

            // Instantiate connector and inline request
            $connector = new \OfficeGuy\LaravelSumitGateway\Http\Connectors\SumitConnector();
            $request = new class(
                $credentials,
                $customerId,
                $debitSource,
                $creditSource,
                $includeDraftDocuments
            ) extends \Saloon\Http\Request implements \Saloon\Contracts\Body\HasBody {
                use \Saloon\Traits\Body\HasJsonBody;

                protected \Saloon\Enums\Method $method = \Saloon\Enums\Method::POST;

                public function __construct(
                    protected readonly \OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData $credentials,
                    protected readonly int $customerId,
                    protected readonly int $debitSource,
                    protected readonly int $creditSource,
                    protected readonly bool $includeDraftDocuments
                ) {}

                public function resolveEndpoint(): string
                {
                    return '/accounting/documents/getdebt/';
                }

                protected function defaultBody(): array
                {
                    return [
                        'Credentials' => $this->credentials->toArray(),
                        'CustomerID' => $this->customerId,
                        'DebitSource' => $this->debitSource,
                        'CreditSource' => $this->creditSource,
                        'IncludeDraftDocuments' => $this->includeDraftDocuments,
                    ];
                }

                protected function defaultConfig(): array
                {
                    return ['timeout' => 60];
                }
            };

            // Send request
            $saloonResponse = $connector->send($request);
            $response = $saloonResponse->json();

            if (! $response || ($response['Status'] ?? null) !== 0) {
                Log::warning('SUMIT debt retrieval failed', [
                    'sumit_customer_id' => $sumitCustomerId,
                    'error' => $response['UserErrorMessage'] ?? 'Unknown error',
                ]);

                return null;
            }

            $debt = (float) ($response['Data']['Debt'] ?? 0);

            return [
                'debt' => $debt,
                'currency' => 'ILS',
                'last_updated' => now()->toIso8601String(),
                'formatted' => $this->formatBalance($debt),
            ];

        } catch (Throwable $e) {
            Log::error('SUMIT debt retrieval exception', [
                'sumit_customer_id' => $sumitCustomerId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Convenience helper: fetch balance by SUMIT customer ID without requiring a full model.
     */
    public function getCustomerBalanceById(int $sumitCustomerId): ?array
    {
        $stub = new class($sumitCustomerId) implements HasSumitCustomer {
            use HasSumitCustomerTrait;

            public function __construct(private int $id) {}

            public function getSumitCustomerId(): ?int
            {
                return $this->id;
            }
        };

        return $this->getCustomerBalance($stub);
    }

    /**
     * Create a payment document for the current debt and return payment URL.
     */
    public function createDebtPaymentDocument(int $sumitCustomerId, float $amount, string $description = 'Debt Payment'): ?string
    {
        try {
            // Create credentials DTO
            $credentials = new \OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData(
                companyId: (int) config('officeguy.company_id'),
                apiKey: (string) config('officeguy.private_key')
            );

            // Extract request data
            $items = [
                [
                    'Description' => $description,
                    'Quantity' => 1,
                    'Price' => $amount,
                    'VATRate' => PaymentService::getOrderVatRate(null),
                    'Currency' => PaymentService::getOrderCurrency(null),
                ],
            ];

            $details = [
                'CustomerID' => $sumitCustomerId,
                'Language' => PaymentService::getOrderLanguage(),
                'Currency' => PaymentService::getOrderCurrency(null),
                'Type' => DocumentService::TYPE_ORDER,
                'Description' => $description,
                'SendByEmail' => [
                    'Original' => 'false',
                ],
            ];

            $vatIncluded = 'true';

            // Instantiate connector and inline request
            $connector = new \OfficeGuy\LaravelSumitGateway\Http\Connectors\SumitConnector();
            $request = new class(
                $credentials,
                $items,
                $details,
                $vatIncluded
            ) extends \Saloon\Http\Request implements \Saloon\Contracts\Body\HasBody {
                use \Saloon\Traits\Body\HasJsonBody;

                protected \Saloon\Enums\Method $method = \Saloon\Enums\Method::POST;

                public function __construct(
                    protected readonly \OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData $credentials,
                    protected readonly array $items,
                    protected readonly array $details,
                    protected readonly string $vatIncluded
                ) {}

                public function resolveEndpoint(): string
                {
                    return '/accounting/documents/create/';
                }

                protected function defaultBody(): array
                {
                    return [
                        'Credentials' => $this->credentials->toArray(),
                        'Items' => $this->items,
                        'VATIncluded' => $this->vatIncluded,
                        'Details' => $this->details,
                    ];
                }

                protected function defaultConfig(): array
                {
                    return ['timeout' => 60];
                }
            };

            // Send request
            $saloonResponse = $connector->send($request);
            $response = $saloonResponse->json();

            if ($response && ($response['Status'] ?? 1) === 0) {
                return $response['Data']['DocumentPaymentURL'] ?? null;
            }

            Log::warning('Failed to create debt payment document', [
                'customer_id' => $sumitCustomerId,
                'error' => $response['UserErrorMessage'] ?? 'Unknown error',
            ]);

            return null;

        } catch (Throwable $e) {
            Log::error('Debt payment document creation exception', [
                'customer_id' => $sumitCustomerId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Send payment link by email/SMS using current debt amount.
     */
    public function sendPaymentLink(
        int $sumitCustomerId,
        ?string $email = null,
        ?string $phone = null,
        ?float $overrideAmount = null
    ): array {
        $balance = $this->getCustomerBalanceById($sumitCustomerId);

        if (! $balance || ($balance['debt'] ?? 0) <= 0) {
            return ['success' => false, 'error' => 'No positive debt to collect'];
        }

        $amount = $overrideAmount ?? (float) $balance['debt'];

        $paymentUrl = $this->createDebtPaymentDocument($sumitCustomerId, $amount);

        if (! $paymentUrl) {
            return ['success' => false, 'error' => 'Failed to generate payment link'];
        }

        $emailEnabled = config('officeguy.collection.email', true);
        $smsEnabled = config('officeguy.collection.sms', false);

        if ($email && $emailEnabled) {
            Mail::raw("שלום,\nמצורף לינק לתשלום החוב בסך ₪{$amount}:\n{$paymentUrl}", function ($message) use ($email) {
                $message->to($email)->subject('לינק לתשלום חוב');
            });
        }

        if ($phone && $smsEnabled) {
            $sms = SmsMessage::createOutbound([
                'destination' => $phone,
                'sender' => config('sms.default_sender', 'ExtraMobile'),
                'message' => "לינק לתשלום חוב ₪{$amount}: {$paymentUrl}",
            ]);
            $sms->sendViaExm();
        }

        return [
            'success' => true,
            'payment_url' => $paymentUrl,
            'amount' => $amount,
        ];
    }

    /**
     * Format balance amount for display
     *
     * @param float $balance The balance amount (positive = debt, negative = credit)
     * @return string Formatted balance string in Hebrew
     */
    private function formatBalance(float $balance): string
    {
        if ($balance > 0) {
            return '₪'.number_format($balance, 2).' (חוב)';
        } elseif ($balance < 0) {
            return '₪'.number_format(abs($balance), 2).' (זכות)';
        }

        return '₪0.00 (מאוזן)';
    }

    /**
     * Get balance for multiple customers
     *
     * Useful for batch operations, such as displaying balances in a customer list
     *
     * @param \Illuminate\Support\Collection<HasSumitCustomer> $customers
     * @return array<int, array> Array keyed by customer ID with balance data
     */
    public function getBalancesForCustomers($customers): array
    {
        $balances = [];

        foreach ($customers as $customer) {
            $sumitCustomerId = $customer->getSumitCustomerId();

            if ($sumitCustomerId) {
                $balance = $this->getCustomerBalance($customer);

                if ($balance) {
                    // Use the model's primary key as array key
                    $balances[$customer->getKey()] = $balance;
                }
            }
        }

        return $balances;
    }

    /**
     * Get detailed balance report with documents and payments
     *
     * Provides comprehensive information about customer's financial status:
     * - Current balance (from SUMIT's getdebt API)
     * - List of all documents (invoices, receipts, credits)
     * - Payment history
     * - Totals breakdown
     *
     * @param HasSumitCustomer $customer The customer to get report for
     * @return array{
     *     documents: array,
     *     payments: array,
     *     total_invoices: float,
     *     total_payments: float,
     *     total_credits: float,
     *     balance: float,
     *     formatted_balance: string,
     *     balance_info: array
     * }|null Returns null if customer has no SUMIT ID or if API call fails
     */
    public function getBalanceReport(HasSumitCustomer $customer): ?array
    {
        $sumitCustomerId = $customer->getSumitCustomerId();

        if (! $sumitCustomerId) {
            return null;
        }

        try {
            // Get accurate balance using SUMIT's getdebt API
            $balanceInfo = $this->getCustomerBalance($customer);

            if (! $balanceInfo) {
                return null;
            }

            // Get documents list (using DocumentService)
            $documents = DocumentService::fetchFromSumit(
                $sumitCustomerId,
                now()->subYears(5),
                now()->addYear()
            );

            // Get payments history
            $payments = $this->getPaymentHistory($customer);

            // Calculate document totals for informational purposes
            $totalInvoices = 0;
            $totalCredits = 0;

            foreach ($documents as $doc) {
                $docType = (int) ($doc['Type'] ?? 0);
                $docValue = (float) ($doc['DocumentValue'] ?? 0);

                if ($docType === 3) {
                    // Credit note
                    $totalCredits += $docValue;
                } elseif ($docType === 1) {
                    // Invoice
                    $totalInvoices += $docValue;
                }
            }

            // Calculate total valid payments
            $totalValidPayments = 0;
            foreach ($payments as $payment) {
                if (($payment['ValidPayment'] ?? false) === true) {
                    $totalValidPayments += (float) ($payment['Amount'] ?? 0);
                }
            }

            return [
                'documents' => $documents,
                'payments' => $payments,
                'total_invoices' => $totalInvoices,
                'total_payments' => $totalValidPayments,
                'total_credits' => $totalCredits,
                // Use SUMIT's calculated balance (most accurate)
                'balance' => $balanceInfo['debt'],
                'formatted_balance' => $balanceInfo['formatted'],
                'balance_info' => $balanceInfo,
            ];

        } catch (Throwable $e) {
            Log::error('SUMIT balance report exception', [
                'sumit_customer_id' => $sumitCustomerId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get customer payment history from SUMIT billing API
     *
     * Retrieves payments from the last 6 months by default
     *
     * @param HasSumitCustomer $customer The customer to get payments for
     * @param \Carbon\Carbon|null $dateFrom Start date (default: 6 months ago)
     * @param \Carbon\Carbon|null $dateTo End date (default: now)
     * @return array List of payment records from SUMIT
     */
    public function getPaymentHistory(
        HasSumitCustomer $customer,
        ?\Carbon\Carbon $dateFrom = null,
        ?\Carbon\Carbon $dateTo = null
    ): array {
        $sumitCustomerId = $customer->getSumitCustomerId();

        if (! $sumitCustomerId) {
            return [];
        }

        try {
            // Default to last 6 months
            if (! $dateFrom) {
                $dateFrom = now()->subMonths(6)->startOfDay();
            }

            if (! $dateTo) {
                $dateTo = now()->endOfDay();
            }

            // Create credentials DTO
            $credentials = new \OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData(
                companyId: (int) config('officeguy.company_id'),
                apiKey: (string) config('officeguy.private_key')
            );

            // Extract request parameters
            $dateFromStr = $dateFrom->toIso8601String();
            $dateToStr = $dateTo->toIso8601String();
            $valid = null; // Get all payments (valid and invalid)
            $startIndex = 0;

            // Instantiate connector and inline request
            $connector = new \OfficeGuy\LaravelSumitGateway\Http\Connectors\SumitConnector();
            $request = new class(
                $credentials,
                $dateFromStr,
                $dateToStr,
                $valid,
                $startIndex
            ) extends \Saloon\Http\Request implements \Saloon\Contracts\Body\HasBody {
                use \Saloon\Traits\Body\HasJsonBody;

                protected \Saloon\Enums\Method $method = \Saloon\Enums\Method::POST;

                public function __construct(
                    protected readonly \OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData $credentials,
                    protected readonly string $dateFrom,
                    protected readonly string $dateTo,
                    protected readonly ?int $valid,
                    protected readonly int $startIndex
                ) {}

                public function resolveEndpoint(): string
                {
                    return '/billing/payments/list/';
                }

                protected function defaultBody(): array
                {
                    return [
                        'Credentials' => $this->credentials->toArray(),
                        'Date_From' => $this->dateFrom,
                        'Date_To' => $this->dateTo,
                        'Valid' => $this->valid,
                        'StartIndex' => $this->startIndex,
                    ];
                }

                protected function defaultConfig(): array
                {
                    return ['timeout' => 180];
                }
            };

            // Send request
            $saloonResponse = $connector->send($request);
            $response = $saloonResponse->json();

            if (! $response || ($response['Status'] ?? null) !== 0) {
                return [];
            }

            $allPayments = $response['Data']['Payments'] ?? [];

            // Filter only this customer's payments
            $customerPayments = array_filter($allPayments, function ($payment) use ($sumitCustomerId) {
                return ((int) ($payment['CustomerID'] ?? 0)) === $sumitCustomerId;
            });

            return array_values($customerPayments);

        } catch (Throwable $e) {
            Log::error('SUMIT payments list exception', [
                'sumit_customer_id' => $sumitCustomerId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
