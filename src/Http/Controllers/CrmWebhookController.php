<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use OfficeGuy\LaravelSumitGateway\Jobs\SyncCrmFromWebhookJob;
use OfficeGuy\LaravelSumitGateway\Models\SumitWebhook;

class CrmWebhookController extends Controller
{
    /**
     * Handle CRM webhook callbacks from SUMIT.
     *
     * Expected payload (any of the following keys):
     * - EntityID | ID (int)   : SUMIT CRM entity ID
     * - FolderID | Folder (int): SUMIT folder ID (bulk sync)
     *
     * Always responds 200 quickly; heavy work is queued.
     */
    public function __invoke(Request $request): JsonResponse
    {
        // SUMIT עשויה לשלוח מזהים בשמות שונים, נאחד אותם כאן.
        $entityId = $request->integer('EntityID') ?: $request->integer('ID');
        $folderId = $request->integer('FolderID') ?: $request->integer('Folder');
        $payload = $request->all();
        $endpoint = $request->path();

        // SUMIT שולחת לפעמים מערך ממוּספר: [FolderID, EntityID, Action, Properties]
        if (!$entityId && is_array($payload)) {
            $values = array_values($payload);

            if (isset($values[0]) && is_numeric($values[0])) {
                $folderId = (int) $values[0];
            }

            if (isset($values[1]) && is_numeric($values[1])) {
                $entityId = (int) $values[1];
            }

            // אם הצעד השלישי הוא מחרוזת – נשמור כאינדיקציה לפעולה (לא חובה לסנכרון)
            if (isset($values[2]) && is_string($values[2])) {
                $payload['Action'] = $payload['Action'] ?? $values[2];
            }

            // אם הצעד הרביעי הוא מערך מאפיינים – נשמר בשם Properties
            if (isset($values[3]) && is_array($values[3])) {
                $payload['Properties'] = $payload['Properties'] ?? $values[3];
            }
        }

        try {
            // Persist the incoming webhook for audit/monitoring (non-blocking)
            $webhook = SumitWebhook::createFromRequest(
                'crm',
                $payload,
                $this->flattenHeaders($request->headers->all()),
                $request->ip(),
                $endpoint
            );

            // Always run full folder sync when folder id exists.
            if ($folderId) {
                SyncCrmFromWebhookJob::dispatch(null, $folderId, $payload);

                // Also dispatch entity-specific sync for faster freshness if entity provided.
                if ($entityId) {
                    SyncCrmFromWebhookJob::dispatch($entityId, null, $payload);
                }

                return response()
                    ->json([
                        'success' => true,
                        'queued' => true,
                        'synced' => 0,
                        'message' => 'Folder sync queued',
                        'webhook_id' => $webhook->id ?? null,
                    ])
                    ->setStatusCode(200);
            }

            if ($entityId) {
                SyncCrmFromWebhookJob::dispatch($entityId, null, $payload);

                return response()
                    ->json([
                        'success' => true,
                        'queued' => true,
                        'synced' => 0,
                        'message' => 'Entity sync queued',
                        'webhook_id' => $webhook->id ?? null,
                    ])
                    ->setStatusCode(200);
            }

            return response()
                ->json([
                    'success' => true,
                    'queued' => false,
                    'synced' => 0,
                    'message' => 'Ignored: EntityID or FolderID is required',
                    'webhook_id' => $webhook->id ?? null,
                ])
                ->setStatusCode(200);
        } catch (\Throwable $e) {
            Log::error('SUMIT CRM webhook error', [
                'message' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()
                ->json([
                    'success' => true,
                    'queued' => false,
                    'synced' => 0,
                    'message' => 'Received: queued processing unavailable',
                ])
                ->setStatusCode(200);
        }
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
