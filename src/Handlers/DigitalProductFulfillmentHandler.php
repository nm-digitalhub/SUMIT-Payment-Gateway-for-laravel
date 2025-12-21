<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Handlers;

use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;
use OfficeGuy\LaravelSumitGateway\Services\OfficeGuyApi;

/**
 * Digital Product Fulfillment Handler
 *
 * Handles post-payment fulfillment for digital products:
 * - eSIM (QR code generation, instant activation)
 * - Software licenses (license key generation, download links)
 * - Digital downloads (file access, download tokens)
 *
 * This is a REFERENCE IMPLEMENTATION.
 * In production, you should implement your own handler
 * that integrates with your digital delivery systems.
 */
class DigitalProductFulfillmentHandler
{
    /**
     * Handle digital product fulfillment
     *
     * @param OfficeGuyTransaction $transaction
     * @return void
     */
    public function handle(OfficeGuyTransaction $transaction): void
    {
        OfficeGuyApi::writeToLog(
            "DigitalProductFulfillmentHandler: Processing transaction {$transaction->id}",
            'info'
        );

        $payable = $transaction->payable;

        if (! $payable) {
            OfficeGuyApi::writeToLog(
                "DigitalProductFulfillmentHandler: No payable found for transaction {$transaction->id}",
                'warning'
            );
            return;
        }

        // Determine product type
        $productType = $this->getProductType($payable);

        OfficeGuyApi::writeToLog(
            "DigitalProductFulfillmentHandler: Product type '{$productType}' for payable {$payable->id}",
            'debug'
        );

        // Dispatch to product-specific handler
        match ($productType) {
            'esim' => $this->handleEsim($transaction, $payable),
            'software_license' => $this->handleSoftwareLicense($transaction, $payable),
            'digital_download' => $this->handleDigitalDownload($transaction, $payable),
            default => $this->handleGeneric($transaction, $payable),
        };
    }

    /**
     * Handle eSIM provisioning (INSTANT DELIVERY)
     *
     * @param OfficeGuyTransaction $transaction
     * @param mixed $payable
     * @return void
     */
    protected function handleEsim(OfficeGuyTransaction $transaction, $payable): void
    {
        OfficeGuyApi::writeToLog(
            "DigitalProductFulfillmentHandler: Processing eSIM for {$payable->name}",
            'info'
        );

        // TODO: Integrate with eSIM provider (Maya Mobile, Airalo, etc.)
        // Example steps:
        // 1. Call eSIM provider API to generate QR code
        // 2. Get ICCID + activation code
        // 3. Generate QR code image
        // 4. Send email with QR code immediately
        // 5. Update order status to 'completed'
        // 6. Send SMS with activation instructions (optional)

        // Reference implementation (placeholder):
        // $this->provisionEsimWithMayaMobile($payable, $transaction);
        // Mail::to($transaction->customer_email)->send(new EsimQrCodeMail($transaction));
        // event(new EsimProvisioned($payable, $transaction));
    }

    /**
     * Handle software license generation
     *
     * @param OfficeGuyTransaction $transaction
     * @param mixed $payable
     * @return void
     */
    protected function handleSoftwareLicense(OfficeGuyTransaction $transaction, $payable): void
    {
        OfficeGuyApi::writeToLog(
            "DigitalProductFulfillmentHandler: Processing software license for {$payable->name}",
            'info'
        );

        // TODO: Integrate with license management system
        // Example steps:
        // 1. Generate unique license key
        // 2. Store license in database with activation limits
        // 3. Create download token for software installer
        // 4. Send email with license key + download link
        // 5. Update order status

        // Reference implementation (placeholder):
        // $licenseKey = $this->generateLicenseKey($payable);
        // $downloadToken = $this->createDownloadToken($payable, $transaction);
        // Mail::to($transaction->customer_email)->send(new SoftwareLicenseMail($licenseKey, $downloadToken));
        // event(new LicenseGenerated($payable, $transaction, $licenseKey));
    }

    /**
     * Handle generic digital download
     *
     * @param OfficeGuyTransaction $transaction
     * @param mixed $payable
     * @return void
     */
    protected function handleDigitalDownload(OfficeGuyTransaction $transaction, $payable): void
    {
        OfficeGuyApi::writeToLog(
            "DigitalProductFulfillmentHandler: Processing digital download for {$payable->name}",
            'info'
        );

        // TODO: Integrate with file delivery system
        // Example steps:
        // 1. Generate secure download token (expires after N downloads or X hours)
        // 2. Create signed download URL
        // 3. Send email with download instructions
        // 4. Track download attempts
        // 5. Update order status

        // Reference implementation (placeholder):
        // $downloadUrl = $this->createSecureDownloadUrl($payable, $transaction);
        // Mail::to($transaction->customer_email)->send(new DownloadReadyMail($downloadUrl));
        // event(new DownloadTokenGenerated($payable, $transaction));
    }

    /**
     * Handle generic digital product fulfillment
     *
     * @param OfficeGuyTransaction $transaction
     * @param mixed $payable
     * @return void
     */
    protected function handleGeneric(OfficeGuyTransaction $transaction, $payable): void
    {
        OfficeGuyApi::writeToLog(
            "DigitalProductFulfillmentHandler: Processing generic digital product for {$payable->name}",
            'info'
        );

        // Generic handler - just log and notify admin
        // event(new DigitalProductFulfillmentRequested($payable, $transaction));
    }

    /**
     * Get product type from payable
     *
     * @param mixed $payable
     * @return string
     */
    protected function getProductType($payable): string
    {
        // Try to get service_type from payable
        if (property_exists($payable, 'service_type')) {
            return $payable->service_type;
        }

        // Try to infer from class name
        $className = class_basename($payable);

        return match (true) {
            str_contains($className, 'Esim') => 'esim',
            str_contains($className, 'License') => 'software_license',
            str_contains($className, 'Download') => 'digital_download',
            default => 'generic',
        };
    }
}
