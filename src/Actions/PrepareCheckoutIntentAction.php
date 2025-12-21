<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Actions;

use OfficeGuy\LaravelSumitGateway\Contracts\Payable;
use OfficeGuy\LaravelSumitGateway\DataTransferObjects\CheckoutIntent;
use OfficeGuy\LaravelSumitGateway\Http\Requests\CheckoutRequest;
use OfficeGuy\LaravelSumitGateway\Services\ServiceDataFactory;
use OfficeGuy\LaravelSumitGateway\Services\TemporaryStorageService;

/**
 * PrepareCheckoutIntentAction
 *
 * Prepares checkout intent and stores it temporarily before payment.
 *
 * Responsibilities:
 * 1. Create CheckoutIntent from validated request
 * 2. Generate service-specific data (WHOIS, cPanel, etc.)
 * 3. Store Intent + ServiceData in DB (separately!)
 *
 * CRITICAL RULES:
 * - Intent = immutable context (NOT modified after creation)
 * - ServiceData = stored separately in PendingCheckout table
 * - No business logic here (just orchestration)
 *
 * Flow:
 * Controller validates → Action prepares → Storage persists
 *
 * @package OfficeGuy\LaravelSumitGateway
 * @since 1.3.0
 */
class PrepareCheckoutIntentAction
{
    public function __construct(
        protected ServiceDataFactory $serviceDataFactory,
        protected TemporaryStorageService $temporaryStorage,
    ) {}

    /**
     * Execute the action
     *
     * ⚠️ CRITICAL: Expects CheckoutRequest (validated!) - guarantees data integrity
     *
     * @param CheckoutRequest $request Validated checkout request
     * @param Payable $payable The entity being purchased
     * @return CheckoutIntent Immutable checkout context
     */
    public function execute(CheckoutRequest $request, Payable $payable): CheckoutIntent
    {
        // 1. Create immutable Intent from validated request
        $intent = CheckoutIntent::fromRequest($request, $payable);

        // 2. Prepare service-specific data based on Intent
        $serviceData = $this->serviceDataFactory->build($intent);

        // 3. ⚠️ Intent is immutable - service data stored separately!
        // Store Intent + ServiceData in DB (not in Intent itself)
        $this->temporaryStorage->store($intent, $serviceData, $request);

        // 4. Return Intent (context only)
        return $intent;
    }
}
