<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Handlers;

use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;
use OfficeGuy\LaravelSumitGateway\Services\OfficeGuyApi;

/**
 * Infrastructure Fulfillment Handler
 *
 * Handles post-payment fulfillment for infrastructure products:
 * - Domains (WHOIS registration, DNS setup)
 * - Hosting (cPanel provisioning, welcome email)
 * - VPS (server provisioning, root access)
 * - SSL (certificate generation, installation)
 *
 * This is a REFERENCE IMPLEMENTATION.
 * In production, you should implement your own handler
 * that integrates with your provisioning systems.
 */
class InfrastructureFulfillmentHandler
{
    /**
     * Handle infrastructure fulfillment
     *
     * @param OfficeGuyTransaction $transaction
     * @return void
     */
    public function handle(OfficeGuyTransaction $transaction): void
    {
        OfficeGuyApi::writeToLog(
            "InfrastructureFulfillmentHandler: Processing transaction {$transaction->id}",
            'info'
        );

        $payable = $transaction->payable;

        if (! $payable) {
            OfficeGuyApi::writeToLog(
                "InfrastructureFulfillmentHandler: No payable found for transaction {$transaction->id}",
                'warning'
            );
            return;
        }

        // Determine service type
        $serviceType = $this->getServiceType($payable);

        OfficeGuyApi::writeToLog(
            "InfrastructureFulfillmentHandler: Service type '{$serviceType}' for payable {$payable->id}",
            'debug'
        );

        // Dispatch to service-specific handler
        match ($serviceType) {
            'domain' => $this->handleDomain($transaction, $payable),
            'hosting' => $this->handleHosting($transaction, $payable),
            'vps' => $this->handleVps($transaction, $payable),
            'ssl' => $this->handleSsl($transaction, $payable),
            default => $this->handleGeneric($transaction, $payable),
        };
    }

    /**
     * Handle domain registration
     *
     * Dispatches to app-specific ProcessPaidOrderJob which handles:
     * - ResellerClub/NameSilo API integration
     * - WHOIS data submission
     * - DNS configuration
     * - Email notifications
     *
     * @param OfficeGuyTransaction $transaction
     * @param mixed $payable
     * @return void
     */
    protected function handleDomain(OfficeGuyTransaction $transaction, $payable): void
    {
        OfficeGuyApi::writeToLog(
            "InfrastructureFulfillmentHandler: Processing domain registration for order {$payable->id}",
            'info'
        );

        // Dispatch to application's provisioning job
        if ($payable instanceof \App\Models\Order) {
            \App\Jobs\ProcessPaidOrderJob::dispatch($payable);

            OfficeGuyApi::writeToLog(
                "InfrastructureFulfillmentHandler: Dispatched ProcessPaidOrderJob for domain order {$payable->id}",
                'info'
            );
        } else {
            OfficeGuyApi::writeToLog(
                "InfrastructureFulfillmentHandler: Payable is not an Order instance, skipping ProcessPaidOrderJob",
                'warning'
            );
        }
    }

    /**
     * Handle hosting provisioning
     *
     * Dispatches to app-specific ProcessPaidOrderJob which handles:
     * - cPanel/WHM account creation
     * - Resource limit configuration
     * - Email setup
     * - Welcome email with credentials
     *
     * @param OfficeGuyTransaction $transaction
     * @param mixed $payable
     * @return void
     */
    protected function handleHosting(OfficeGuyTransaction $transaction, $payable): void
    {
        OfficeGuyApi::writeToLog(
            "InfrastructureFulfillmentHandler: Processing hosting provisioning for order {$payable->id}",
            'info'
        );

        // Dispatch to application's provisioning job
        if ($payable instanceof \App\Models\Order) {
            \App\Jobs\ProcessPaidOrderJob::dispatch($payable);

            OfficeGuyApi::writeToLog(
                "InfrastructureFulfillmentHandler: Dispatched ProcessPaidOrderJob for hosting order {$payable->id}",
                'info'
            );
        } else {
            OfficeGuyApi::writeToLog(
                "InfrastructureFulfillmentHandler: Payable is not an Order instance, skipping ProcessPaidOrderJob",
                'warning'
            );
        }
    }

    /**
     * Handle VPS provisioning
     *
     * Dispatches to app-specific ProcessPaidOrderJob which handles:
     * - VPS instance creation
     * - OS/resource configuration
     * - SSH key setup
     * - Welcome email with credentials
     *
     * @param OfficeGuyTransaction $transaction
     * @param mixed $payable
     * @return void
     */
    protected function handleVps(OfficeGuyTransaction $transaction, $payable): void
    {
        OfficeGuyApi::writeToLog(
            "InfrastructureFulfillmentHandler: Processing VPS provisioning for order {$payable->id}",
            'info'
        );

        // Dispatch to application's provisioning job
        if ($payable instanceof \App\Models\Order) {
            \App\Jobs\ProcessPaidOrderJob::dispatch($payable);

            OfficeGuyApi::writeToLog(
                "InfrastructureFulfillmentHandler: Dispatched ProcessPaidOrderJob for VPS order {$payable->id}",
                'info'
            );
        } else {
            OfficeGuyApi::writeToLog(
                "InfrastructureFulfillmentHandler: Payable is not an Order instance, skipping ProcessPaidOrderJob",
                'warning'
            );
        }
    }

    /**
     * Handle SSL certificate generation
     *
     * @param OfficeGuyTransaction $transaction
     * @param mixed $payable
     * @return void
     */
    protected function handleSsl(OfficeGuyTransaction $transaction, $payable): void
    {
        OfficeGuyApi::writeToLog(
            "InfrastructureFulfillmentHandler: Processing SSL certificate for {$payable->name}",
            'info'
        );

        // TODO: Integrate with SSL provider (Let's Encrypt, Comodo, etc.)
        // Example steps:
        // 1. Validate domain ownership (DNS/HTTP challenge)
        // 2. Generate CSR (Certificate Signing Request)
        // 3. Request certificate from CA
        // 4. Install certificate on server
        // 5. Send certificate files to customer
        // 6. Update order status

        // Reference implementation (placeholder):
        // $this->generateSslCertificate($payable, $transaction);
        // event(new SslCertificateIssued($payable, $transaction));
    }

    /**
     * Handle generic infrastructure fulfillment
     *
     * @param OfficeGuyTransaction $transaction
     * @param mixed $payable
     * @return void
     */
    protected function handleGeneric(OfficeGuyTransaction $transaction, $payable): void
    {
        OfficeGuyApi::writeToLog(
            "InfrastructureFulfillmentHandler: Processing generic infrastructure for {$payable->name}",
            'info'
        );

        // Generic handler - just log and notify admin
        // event(new InfrastructureFulfillmentRequested($payable, $transaction));
    }

    /**
     * Get service type from payable
     *
     * @param mixed $payable
     * @return string
     */
    protected function getServiceType($payable): string
    {
        // Try to get service_type from payable
        if (property_exists($payable, 'service_type')) {
            return $payable->service_type;
        }

        // Try to infer from class name
        $className = class_basename($payable);

        return match (true) {
            str_contains($className, 'Domain') => 'domain',
            str_contains($className, 'Hosting') => 'hosting',
            str_contains($className, 'Vps') => 'vps',
            str_contains($className, 'Ssl') => 'ssl',
            default => 'generic',
        };
    }
}
