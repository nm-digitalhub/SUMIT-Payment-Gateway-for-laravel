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
     * Creates or updates folder and its field definitions.
     *
     * @param int $sumitFolderId SUMIT folder ID
     * @return array{success: bool, folder?: CrmFolder, fields_synced?: int, error?: string}
     */
    public static function syncFolderSchema(int $sumitFolderId): array
    {
        try {
            // Get folder schema from SUMIT
            $result = self::getFolder($sumitFolderId);

            if (!$result['success']) {
                return [
                    'success' => false,
                    'error' => $result['error'],
                ];
            }

            $folderData = $result['folder'];
            $fieldsData = $result['fields'];

            // Create or update folder
            $folder = CrmFolder::updateOrCreate(
                ['sumit_folder_id' => $sumitFolderId],
                [
                    'name' => $folderData['Name'] ?? 'Unknown',
                    'name_plural' => $folderData['NamePlural'] ?? $folderData['Name'] ?? 'Unknown',
                    'icon' => $folderData['Icon'] ?? null,
                    'color' => $folderData['Color'] ?? null,
                    'entity_type' => strtolower($folderData['EntityType'] ?? 'contact'),
                    'is_system' => $folderData['IsSystem'] ?? false,
                    'is_active' => $folderData['IsActive'] ?? true,
                    'settings' => $folderData['Settings'] ?? null,
                ]
            );

            OfficeGuyApi::writeToLog(
                'Synced CRM folder: ' . $folder->name . ' (ID: ' . $folder->id . ')',
                'info'
            );

            // Sync fields
            $fieldsSynced = 0;
            foreach ($fieldsData as $fieldData) {
                $field = CrmFolderField::updateOrCreate(
                    [
                        'crm_folder_id' => $folder->id,
                        'sumit_field_id' => $fieldData['FieldID'] ?? null,
                    ],
                    [
                        'name' => self::sanitizeFieldName($fieldData['Name'] ?? 'unknown'),
                        'label' => $fieldData['Label'] ?? $fieldData['Name'] ?? 'Unknown',
                        'field_type' => self::mapFieldType($fieldData['Type'] ?? 'text'),
                        'is_required' => $fieldData['IsRequired'] ?? false,
                        'is_unique' => $fieldData['IsUnique'] ?? false,
                        'is_searchable' => $fieldData['IsSearchable'] ?? true,
                        'default_value' => $fieldData['DefaultValue'] ?? null,
                        'validation_rules' => $fieldData['ValidationRules'] ?? null,
                        'options' => $fieldData['Options'] ?? null,
                        'display_order' => $fieldData['DisplayOrder'] ?? 0,
                    ]
                );

                $fieldsSynced++;
            }

            OfficeGuyApi::writeToLog(
                'Synced ' . $fieldsSynced . ' fields for folder: ' . $folder->name,
                'info'
            );

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
