<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use OfficeGuy\LaravelSumitGateway\Jobs\ProcessSumitWebhookJob;
use OfficeGuy\LaravelSumitGateway\Models\SumitWebhook;
use OfficeGuy\LaravelSumitGateway\Services\OfficeGuyApi;

/**
 * SUMIT Incoming Webhook Controller
 *
 * Handles webhooks/triggers sent FROM SUMIT to your application.
 * SUMIT can send webhooks when cards are created, updated, deleted, or archived.
 *
 * To receive webhooks from SUMIT:
 * 1. Install the required modules in SUMIT: Triggers, API, and View Management
 * 2. Create a view in SUMIT to define which cards and data to send
 * 3. Create a trigger in SUMIT with HTTP action pointing to your webhook URL
 *
 * @see https://help.sumit.co.il/he/articles/11577644-שליחת-webhook-ממערכת-סאמיט
 */
class SumitWebhookController extends Controller
{
    /**
     * Handle incoming webhook from SUMIT
     *
     * SUMIT sends webhooks in JSON or FORM format when triggers fire.
     * The system waits 10 seconds for a response, then retries up to 5 times.
     *
     * @param  string|null  $eventType  Optional event type from URL
     */
    public function handle(Request $request, ?string $eventType = null): JsonResponse
    {
        // Immediately return 200 to acknowledge receipt (SUMIT requires quick response)
        // Process asynchronously if heavy processing is needed

        try {
            // Get event type from URL parameter or try to detect from payload
            $eventType ??= $this->detectEventType($request);

            // Get payload (supports both JSON and form data)
            $payload = $this->getPayload($request);

            // Get headers for logging
            $headers = $request->headers->all();

            // Get source IP
            $sourceIp = $request->ip();
            $endpoint = $request->path();

            // Log the incoming webhook
            OfficeGuyApi::writeToLog(
                "SUMIT webhook received: {$eventType} from {$sourceIp}",
                'info'
            );

            // Create webhook record
            $webhook = SumitWebhook::createFromRequest(
                $eventType,
                $payload,
                $this->flattenHeaders($headers),
                $sourceIp,
                $endpoint
            );

            // Dispatch job for async processing (no heavy work in request)
            ProcessSumitWebhookJob::dispatch($webhook->id);

            // Return success immediately
            // Note: SUMIT expects HTTP 200 within 10 seconds
            return response()->json([
                'success' => true,
                'queued' => true,
                'message' => 'Webhook received',
                'webhook_id' => $webhook->id,
            ], 200);

        } catch (\Exception $e) {
            OfficeGuyApi::writeToLog(
                'SUMIT webhook error: ' . $e->getMessage(),
                'error'
            );

            Log::error('SUMIT webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Still return 200 to prevent SUMIT from retrying if we captured the data
            return response()->json([
                'success' => false,
                'queued' => false,
                'message' => 'Error processing webhook',
                'error' => $e->getMessage(),
            ], 200);
        }
    }

    /**
     * Handle card created webhook
     */
    public function cardCreated(Request $request): JsonResponse
    {
        return $this->handle($request, SumitWebhook::TYPE_CARD_CREATED);
    }

    /**
     * Handle card updated webhook
     */
    public function cardUpdated(Request $request): JsonResponse
    {
        return $this->handle($request, SumitWebhook::TYPE_CARD_UPDATED);
    }

    /**
     * Handle card deleted webhook
     */
    public function cardDeleted(Request $request): JsonResponse
    {
        return $this->handle($request, SumitWebhook::TYPE_CARD_DELETED);
    }

    /**
     * Handle card archived webhook
     */
    public function cardArchived(Request $request): JsonResponse
    {
        return $this->handle($request, SumitWebhook::TYPE_CARD_ARCHIVED);
    }

    /**
     * Get payload from request (supports JSON and form data)
     *
     * CRITICAL: Normalizes SUMIT CRM webhooks sent as form-data with json= parameter
     */
    private function getPayload(Request $request): array
    {
        $contentType = $request->header('Content-Type', '');

        // JSON body (standard)
        if (str_contains($contentType, 'application/json')) {
            return $request->json()->all();
        }

        // Form-data (SUMIT CRM sends: json={"Folder":...})
        $data = $request->all();

        if (isset($data['json']) && is_string($data['json'])) {
            $decoded = json_decode($data['json'], true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return $data;
    }

    /**
     * Detect event type from request
     *
     * CRITICAL: CRM webhooks are detected by Folder+Type, not event_type field
     */
    private function detectEventType(Request $request): string
    {
        // Try to detect from payload
        $payload = $this->getPayload($request);

        // CRM webhook detection (SUMIT CRM Triggers send: Folder + Type + EntityID)
        // CRITICAL: Accept 'Create', 'CreateOrUpdate', and 'Delete' (not just CreateOrUpdate!)
        if (
            isset($payload['Folder']) &&
            isset($payload['Type']) &&
            in_array($payload['Type'], ['Create', 'CreateOrUpdate', 'Delete'], true)
        ) {
            return 'crm';
        }

        if (isset($payload['event_type'])) {
            return $payload['event_type'];
        }

        if (isset($payload['EventType'])) {
            return strtolower($payload['EventType']);
        }

        if (isset($payload['action'])) {
            return match (strtolower($payload['action'])) {
                'create', 'created' => SumitWebhook::TYPE_CARD_CREATED,
                'update', 'updated' => SumitWebhook::TYPE_CARD_UPDATED,
                'delete', 'deleted' => SumitWebhook::TYPE_CARD_DELETED,
                'archive', 'archived' => SumitWebhook::TYPE_CARD_ARCHIVED,
                default => 'unknown',
            };
        }

        // Default to unknown
        return 'unknown';
    }

    /**
     * Flatten headers array (convert arrays to first value)
     */
    private function flattenHeaders(array $headers): array
    {
        $flattened = [];
        foreach ($headers as $key => $values) {
            $flattened[$key] = is_array($values) ? ($values[0] ?? null) : $values;
        }

        return $flattened;
    }
}
