<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use OfficeGuy\LaravelSumitGateway\Models\CrmFolder;
use OfficeGuy\LaravelSumitGateway\Models\CrmFolderField;

/**
 * CRM Schema Service
 *
 * Handles CRM schema operations: folders, fields, and their synchronization from SUMIT API.
 * Mirrors the pattern from PaymentService and other package services.
 */
class CrmSchemaService
{
    /**
     * List all CRM folders from SUMIT API
     *
     * Endpoint: POST /crm/schema/listfolders/
     *
     * @return array{success: bool, folders?: array, error?: string}
     */
    public static function listFolders(): array
    {
        try {
            $payload = [
                'Credentials' => PaymentService::getCredentials(),
            ];

            $response = OfficeGuyApi::post(
                $payload,
                '/crm/schema/listfolders/',
                config('officeguy.environment', 'www'),
                false
            );

            if ($response === null) {
                return [
                    'success' => false,
                    'error' => 'No response from SUMIT API',
                ];
            }

            if (($response['Status'] ?? 1) === 0) {
                return [
                    'success' => true,
                    'folders' => $response['Data']['Folders'] ?? [],
                ];
            }

            return [
                'success' => false,
                'error' => $response['UserErrorMessage'] ?? 'Failed to list folders',
            ];

        } catch (\Throwable $e) {
            OfficeGuyApi::writeToLog(
                'SUMIT CRM listFolders exception: ' . $e->getMessage(),
                'error'
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get folder schema from SUMIT API
     *
     * Endpoint: POST /crm/schema/getfolder/
     *
     * @param int $folderId SUMIT folder ID
     * @return array{success: bool, folder?: array, fields?: array, error?: string}
     */
    public static function getFolder(int $folderId): array
    {
        try {
            $payload = [
                'Credentials' => PaymentService::getCredentials(),
                'FolderID' => $folderId,
            ];

            $response = OfficeGuyApi::post(
                $payload,
                '/crm/schema/getfolder/',
                config('officeguy.environment', 'www'),
                false
            );

            if ($response === null) {
                return [
                    'success' => false,
                    'error' => 'No response from SUMIT API',
                ];
            }

            if (($response['Status'] ?? 1) === 0) {
                return [
                    'success' => true,
                    'folder' => $response['Data']['Folder'] ?? [],
                    'fields' => $response['Data']['Fields'] ?? [],
                ];
            }

            return [
                'success' => false,
                'error' => $response['UserErrorMessage'] ?? 'Failed to get folder schema',
            ];

        } catch (\Throwable $e) {
            OfficeGuyApi::writeToLog(
                'SUMIT CRM getFolder exception for folder ' . $folderId . ': ' . $e->getMessage(),
                'error'
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sync folder schema from SUMIT to local database
     *
     * Creates or updates folder with basic information from listFolders API.
     * Note: SUMIT API does not provide /crm/schema/getfolder/ endpoint,
     * so we work with limited data (ID and Name only).
     *
     * @param int $sumitFolderId SUMIT folder ID
     * @param string|null $folderName Folder name from listFolders
     * @return array{success: bool, folder?: CrmFolder, fields_synced?: int, error?: string}
     */
    public static function syncFolderSchema(int $sumitFolderId, ?string $folderName = null): array
    {
        try {
            // SUMIT API limitation: Only ID and Name are available from listFolders
            // The getFolder() endpoint returns null, so we work with what we have

            if (!$folderName) {
                // Try to pull existing name from the local DB (previous sync)
                $folderName = CrmFolder::where('sumit_folder_id', $sumitFolderId)->value('name');

                if (!$folderName) {
                    return [
                        'success' => false,
                        'error' => 'Folder name is required',
                    ];
                }
            }

            // Create or update folder with available data
            $folder = CrmFolder::updateOrCreate(
                ['sumit_folder_id' => $sumitFolderId],
                [
                    'name' => $folderName,
                    'name_plural' => $folderName, // Use same name for plural
                    'icon' => null, // Not available from API
                    'color' => null, // Not available from API
                    'entity_type' => 'contact', // Default type, cannot determine from API
                    'is_system' => false, // Default to false
                    'is_active' => true, // Default to active
                    'settings' => null, // Not available from API
                ]
            );

            OfficeGuyApi::writeToLog(
                'Synced CRM folder: ' . $folder->name . ' (SUMIT ID: ' . $sumitFolderId . ', DB ID: ' . $folder->id . ')',
                'info'
            );

            // Note: Field syncing not possible without getFolder() endpoint
            $fieldsSynced = 0;

            return [
                'success' => true,
                'folder' => $folder,
                'fields_synced' => $fieldsSynced,
            ];

        } catch (\Throwable $e) {
            OfficeGuyApi::writeToLog(
                'SUMIT CRM syncFolderSchema exception for folder ' . $sumitFolderId . ': ' . $e->getMessage(),
                'error'
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sync all folders from SUMIT to local database
     *
     * @return array{success: bool, folders_synced?: int, fields_synced?: int, error?: string}
     */
    public static function syncAllFolders(): array
    {
        try {
            // Get all folders from SUMIT
            $result = self::listFolders();

            if (!$result['success']) {
                return [
                    'success' => false,
                    'error' => $result['error'],
                ];
            }

            $foldersSynced = 0;
            $totalFieldsSynced = 0;

            foreach ($result['folders'] as $folderData) {
                $sumitFolderId = $folderData['FolderID'] ?? null;

                if (!$sumitFolderId) {
                    continue;
                }

                // Sync each folder with its fields
                $syncResult = self::syncFolderSchema($sumitFolderId);

                if ($syncResult['success']) {
                    $foldersSynced++;
                    $totalFieldsSynced += $syncResult['fields_synced'] ?? 0;
                }
            }

            OfficeGuyApi::writeToLog(
                'Successfully synced ' . $foldersSynced . ' folders with ' . $totalFieldsSynced . ' total fields',
                'info'
            );

            return [
                'success' => true,
                'folders_synced' => $foldersSynced,
                'fields_synced' => $totalFieldsSynced,
            ];

        } catch (\Throwable $e) {
            OfficeGuyApi::writeToLog(
                'SUMIT CRM syncAllFolders exception: ' . $e->getMessage(),
                'error'
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sanitize field name to snake_case
     *
     * @param string $name Field name
     * @return string Sanitized field name
     */
    private static function sanitizeFieldName(string $name): string
    {
        // Convert to snake_case
        $name = preg_replace('/[^a-zA-Z0-9]+/', '_', $name);
        $name = strtolower(trim($name, '_'));

        return $name ?: 'unknown_field';
    }

    /**
     * Map SUMIT field type to local field type
     *
     * @param string $sumitType SUMIT field type
     * @return string Local field type
     */
    private static function mapFieldType(string $sumitType): string
    {
        return match (strtolower($sumitType)) {
            'text', 'string' => 'text',
            'number', 'integer', 'decimal' => 'number',
            'email' => 'email',
            'phone', 'tel' => 'phone',
            'date', 'datetime' => 'date',
            'select', 'dropdown' => 'select',
            'multiselect' => 'multiselect',
            'boolean', 'checkbox' => 'boolean',
            'textarea' => 'text',
            default => 'text',
        };
    }
}
