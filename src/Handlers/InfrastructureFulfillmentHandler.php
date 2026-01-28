<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Handlers;

use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;
use OfficeGuy\LaravelSumitGateway\Services\OfficeGuyApi;

/**
 * Infrastructure Fulfillment Handler
 *
 * Handles post-payment fulfillment for infrastructure products in the SUMIT Gateway package.
 * Dispatched by `FulfillmentDispatcher` when `PayableType::INFRASTRUCTURE` is received.
 *
 * ## Supported Service Types
 *
 * - **Domains**: WHOIS registration, DNS configuration, transfer management
 * - **Hosting**: cPanel/WHM account creation, resource limits, welcome emails
 * - **VPS**: Server provisioning, OS installation, SSH key setup
 * - **SSL Certificates**: CSR generation, CA validation, installation
 *
 * ## Architecture
 *
 * This handler is part of the **Package Layer** fulfillment system:
 *
 * ```
 * PaymentCompleted Event (from PaymentService)
 *     ↓
 * FulfillmentListener::handle()
 *     ↓
 * FulfillmentDispatcher::dispatch(payable, transaction)
 *     ↓
 * PayableType::INFRASTRUCTURE → InfrastructureFulfillmentHandler::handle()
 *     ↓
 * Service-specific handler (e.g., handleDomain(), handleHosting())
 *     ↓
 * Application Layer: ProcessPaidOrderJob (provisioning)
 * ```
 *
 * ## Integration with Application State Machine
 *
 * The **Application Layer** owns the Order State Machine. This handler:
 * - **Receives**: PaymentCompleted event (order already in 'processing' state)
 * - **Executes**: Infrastructure provisioning logic (domain, hosting, VPS)
 * - **Does NOT** manage order state (app's responsibility)
 *
 * ## Reference Implementation
 *
 * **IMPORTANT**: This is a **REFERENCE IMPLEMENTATION**. For production:
 * 1. Copy this class to your application
 * 2. Customize handlers for your provisioning systems
 * 3. Re-register in `OfficeGuyServiceProvider::registerFulfillmentHandlers()`
 *
 * ## Domain Integration (Implemented)
 *
 * Dispatches to application's `ProcessPaidOrderJob`:
 * - ResellerClub/NameSilo API integration
 * - WHOIS data submission
 * - DNS configuration
 * - Email notifications with nameserver details
 *
 * ## Hosting Integration (Implemented)
 *
 * Dispatches to application's `ProcessPaidOrderJob`:
 * - cPanel/WHM account creation via API
 * - Resource limit configuration (disk, bandwidth, databases)
 * - Email account setup
 * - Welcome email with cPanel login credentials
 *
 * ## VPS Integration (Implemented)
 *
 * Dispatches to application's `ProcessPaidOrderJob`:
 * - VPS instance creation (Virtualizor, Proxmox, etc.)
 * - OS template installation
 * - SSH key configuration
 * - Welcome email with root credentials
 *
 * ## SSL Certificate Fulfillment (TODO)
 *
 * Reference implementation steps:
 * 1. Validate domain ownership (DNS or HTTP challenge)
 * 2. Generate CSR (Certificate Signing Request)
 * 3. Request certificate from CA (Let's Encrypt, Comodo)
 * 4. Install certificate on web server
 * 5. Send certificate files to customer
 * 6. Schedule auto-renewal before expiry
 * 7. Fire `SslCertificateIssued` event
 *
 * ## Registration
 *
 * Registered in `OfficeGuyServiceProvider::registerFulfillmentHandlers()`:
 * ```php
 * $dispatcher->registerMany([
 *     PayableType::INFRASTRUCTURE->value => InfrastructureFulfillmentHandler::class,
 * ]);
 * ```
 *
 * @see \OfficeGuy\LaravelSumitGateway\Services\FulfillmentDispatcher
 * @see \OfficeGuy\LaravelSumitGateway\Listeners\FulfillmentListener
 * @see \OfficeGuy\LaravelSumitGateway\Enums\PayableType::INFRASTRUCTURE
 * @see docs/STATE_MACHINE_ARCHITECTURE.md
 */
class InfrastructureFulfillmentHandler
{
    /**
     * Handle infrastructure fulfillment
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
     * @param  mixed  $payable
     */
    protected function handleDomain(OfficeGuyTransaction $transaction, $payable): void
    {
        OfficeGuyApi::writeToLog(
            "InfrastructureFulfillmentHandler: Processing domain registration for order {$payable->id}",
            'info'
        );

        // Dispatch to application's provisioning job
        if ($payable instanceof \App\Models\Order) {
            \App\Jobs\ProcessPaidOrderJob::dispatch($payable->id);

            OfficeGuyApi::writeToLog(
                "InfrastructureFulfillmentHandler: Dispatched ProcessPaidOrderJob for domain order {$payable->id}",
                'info'
            );
        } else {
            OfficeGuyApi::writeToLog(
                'InfrastructureFulfillmentHandler: Payable is not an Order instance, skipping ProcessPaidOrderJob',
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
     * @param  mixed  $payable
     */
    protected function handleHosting(OfficeGuyTransaction $transaction, $payable): void
    {
        OfficeGuyApi::writeToLog(
            "InfrastructureFulfillmentHandler: Processing hosting provisioning for order {$payable->id}",
            'info'
        );

        // Dispatch to application's provisioning job
        if ($payable instanceof \App\Models\Order) {
            \App\Jobs\ProcessPaidOrderJob::dispatch($payable->id);

            OfficeGuyApi::writeToLog(
                "InfrastructureFulfillmentHandler: Dispatched ProcessPaidOrderJob for hosting order {$payable->id}",
                'info'
            );
        } else {
            OfficeGuyApi::writeToLog(
                'InfrastructureFulfillmentHandler: Payable is not an Order instance, skipping ProcessPaidOrderJob',
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
     * @param  mixed  $payable
     */
    protected function handleVps(OfficeGuyTransaction $transaction, $payable): void
    {
        OfficeGuyApi::writeToLog(
            "InfrastructureFulfillmentHandler: Processing VPS provisioning for order {$payable->id}",
            'info'
        );

        // Dispatch to application's provisioning job
        if ($payable instanceof \App\Models\Order) {
            \App\Jobs\ProcessPaidOrderJob::dispatch($payable->id);

            OfficeGuyApi::writeToLog(
                "InfrastructureFulfillmentHandler: Dispatched ProcessPaidOrderJob for VPS order {$payable->id}",
                'info'
            );
        } else {
            OfficeGuyApi::writeToLog(
                'InfrastructureFulfillmentHandler: Payable is not an Order instance, skipping ProcessPaidOrderJob',
                'warning'
            );
        }
    }

    /**
     * Handle SSL certificate generation
     *
     * @param  mixed  $payable
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
     * @param  mixed  $payable
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
     * @param  mixed  $payable
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
