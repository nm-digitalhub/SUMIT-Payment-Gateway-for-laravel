<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use OfficeGuy\LaravelSumitGateway\Models\CrmFolder;
use OfficeGuy\LaravelSumitGateway\Support\SumitApiResponse;

/**
 * Maps commercial product plans to SUMIT CRM entities (e.g. accounting / catalog schemas).
 *
 * Uses the same endpoints as {@see CrmDataService}, but:
 * - create: calls `/crm/data/createentity/` without persisting a local {@see \OfficeGuy\LaravelSumitGateway\Models\CrmEntity}
 *   (callers such as billing apps store `sumit_entity_id` on their own models).
 * - update: calls `/crm/data/updateentity/` by SUMIT entity ID.
 */
final class SumitProductService
{
    /**
     * Create a product entity in SUMIT CRM.
     *
     * @return array{success: bool, sumit_entity_id?: int, error?: string}
     */
    public static function createProduct(
        string $name,
        string $sku,
        float $price,
        ?string $description = null,
    ): array {
        $folder = self::resolveProductFolder();

        if (! $folder['success']) {
            return [
                'success' => false,
                'error' => $folder['error'] ?? 'CRM folder not configured',
            ];
        }

        $fields = [
            'name' => $name,
            'Accounting_Name' => $name,
            'Accounting_SKU' => $sku,
            'Accounting_Price' => $price,
        ];

        if ($description !== null && trim($description) !== '') {
            $fields['Accounting_Description'] = $description;
        }

        try {
            $payload = [
                'Credentials' => PaymentService::getCredentials(),
                'Entity' => [
                    'Folder' => (string) $folder['sumit_folder_id'],
                    'Properties' => $fields,
                ],
            ];

            $response = OfficeGuyApi::post(
                $payload,
                '/crm/data/createentity/',
                config('officeguy.environment', 'www'),
                false
            );

            if ($response === null) {
                return [
                    'success' => false,
                    'error' => __('No response from SUMIT API'),
                ];
            }

            if (!SumitApiResponse::isSuccess($response['Status'] ?? null)) {
                return [
                    'success' => false,
                    'error' => (string) ($response['UserErrorMessage'] ?? 'Failed to create product entity in SUMIT'),
                ];
            }

            $sumitEntityId = $response['Data']['EntityID'] ?? null;

            if (! $sumitEntityId) {
                return [
                    'success' => false,
                    'error' => 'No entity ID returned from SUMIT',
                ];
            }

            OfficeGuyApi::writeToLog(
                'SUMIT product entity created: ' . $name . ' (SUMIT ID: ' . $sumitEntityId . ')',
                'info'
            );

            return [
                'success' => true,
                'sumit_entity_id' => (int) $sumitEntityId,
            ];
        } catch (\Throwable $e) {
            OfficeGuyApi::writeToLog(
                'SUMIT createProduct exception: ' . $e->getMessage(),
                'error'
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update an existing CRM entity by SUMIT entity ID.
     *
     * @param  array<string, mixed>  $fields
     * @return array{success: bool, error?: string}
     */
    public static function updateProduct(int $sumitEntityId, array $fields): array
    {
        if ($sumitEntityId <= 0) {
            return [
                'success' => false,
                'error' => 'Invalid SUMIT entity ID',
            ];
        }

        if ($fields === []) {
            return [
                'success' => false,
                'error' => 'No fields to update',
            ];
        }

        try {
            $payload = [
                'Credentials' => PaymentService::getCredentials(),
                'Entity' => [
                    'ID' => $sumitEntityId,
                    'Properties' => $fields,
                ],
            ];

            $response = OfficeGuyApi::post(
                $payload,
                '/crm/data/updateentity/',
                config('officeguy.environment', 'www'),
                false
            );

            if ($response === null) {
                return [
                    'success' => false,
                    'error' => __('No response from SUMIT API'),
                ];
            }

            if (!SumitApiResponse::isSuccess($response['Status'] ?? null)) {
                return [
                    'success' => false,
                    'error' => (string) ($response['UserErrorMessage'] ?? 'Failed to update product entity in SUMIT'),
                ];
            }

            OfficeGuyApi::writeToLog(
                'SUMIT product entity updated: SUMIT ID ' . $sumitEntityId . ', fields: ' . json_encode($fields),
                'info'
            );

            return ['success' => true];
        } catch (\Throwable $e) {
            OfficeGuyApi::writeToLog(
                'SUMIT updateProduct exception for entity ' . $sumitEntityId . ': ' . $e->getMessage(),
                'error'
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{success: true, sumit_folder_id: positive-int}|array{success: false, error: string}
     */
    private static function resolveProductFolder(): array
    {
        $configuredSumitId = config('officeguy.crm_products_sumit_folder_id');

        if ($configuredSumitId !== null && $configuredSumitId !== '' && (int) $configuredSumitId > 0) {
            return [
                'success' => true,
                'sumit_folder_id' => (int) $configuredSumitId,
            ];
        }

        $localFolderId = config('officeguy.crm_products_folder_id');

        if ($localFolderId !== null && $localFolderId !== '') {
            $folder = CrmFolder::query()->find((int) $localFolderId);

            if (! $folder) {
                return [
                    'success' => false,
                    'error' => 'CRM folder ID ' . (int) $localFolderId . ' not found',
                ];
            }

            if (! $folder->sumit_folder_id) {
                return [
                    'success' => false,
                    'error' => 'CRM folder is not synced with SUMIT (missing sumit_folder_id)',
                ];
            }

            return [
                'success' => true,
                'sumit_folder_id' => (int) $folder->sumit_folder_id,
            ];
        }

        return [
            'success' => false,
            'error' => 'Configure officeguy.crm_products_sumit_folder_id or officeguy.crm_products_folder_id for product sync',
        ];
    }
}
