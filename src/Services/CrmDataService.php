<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use OfficeGuy\LaravelSumitGateway\Models\CrmEntity;
use OfficeGuy\LaravelSumitGateway\Models\CrmFolder;

/**
 * CRM Data Service
 *
 * Handles CRUD operations for CRM entities via SUMIT API.
 * Syncs entity data between local database and SUMIT.
 */
class CrmDataService
{
    /**
     * Create new CRM entity in SUMIT and local database
     *
     * Endpoint: POST /crm/data/createentity/
     *
     * @param int $folderId Local folder ID
     * @param array $fields Entity fields data
     * @return array{success: bool, entity?: CrmEntity, sumit_entity_id?: int, error?: string}
     */
    public static function createEntity(int $folderId, array $fields): array
    {
        try {
            // Get folder
            $folder = CrmFolder::find($folderId);

            if (!$folder) {
                return [
                    'success' => false,
                    'error' => 'Folder not found',
                ];
            }

            if (!$folder->sumit_folder_id) {
                return [
                    'success' => false,
                    'error' => 'Folder not synced with SUMIT',
                ];
            }

            // Create entity in SUMIT
            $payload = [
                'Credentials' => PaymentService::getCredentials(),
                'FolderID' => $folder->sumit_folder_id,
                'Fields' => $fields,
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
                    'error' => 'No response from SUMIT API',
                ];
            }

            if (($response['Status'] ?? 1) !== 0) {
                return [
                    'success' => false,
                    'error' => $response['UserErrorMessage'] ?? 'Failed to create entity in SUMIT',
                ];
            }

            $sumitEntityId = $response['Data']['EntityID'] ?? null;

            if (!$sumitEntityId) {
                return [
                    'success' => false,
                    'error' => 'No entity ID returned from SUMIT',
                ];
            }

            // Create entity in local database
            $entity = CrmEntity::create([
                'crm_folder_id' => $folderId,
                'sumit_entity_id' => $sumitEntityId,
                'entity_type' => $folder->entity_type,
                'name' => $fields['name'] ?? 'Unknown',
                'email' => $fields['email'] ?? null,
                'phone' => $fields['phone'] ?? null,
                'mobile' => $fields['mobile'] ?? null,
                'address' => $fields['address'] ?? null,
                'city' => $fields['city'] ?? null,
                'state' => $fields['state'] ?? null,
                'postal_code' => $fields['postal_code'] ?? null,
                'country' => $fields['country'] ?? 'Israel',
                'company_name' => $fields['company_name'] ?? null,
                'tax_id' => $fields['tax_id'] ?? null,
                'status' => $fields['status'] ?? 'active',
                'source' => $fields['source'] ?? null,
                'owner_user_id' => $fields['owner_user_id'] ?? null,
                'assigned_to_user_id' => $fields['assigned_to_user_id'] ?? null,
                'sumit_customer_id' => $fields['sumit_customer_id'] ?? null,
            ]);

            // Store custom fields
            foreach ($fields as $fieldName => $fieldValue) {
                // Skip standard fields
                if (in_array($fieldName, [
                    'name', 'email', 'phone', 'mobile', 'address', 'city', 'state',
                    'postal_code', 'country', 'company_name', 'tax_id', 'status',
                    'source', 'owner_user_id', 'assigned_to_user_id', 'sumit_customer_id'
                ])) {
                    continue;
                }

                // Store as custom field
                $entity->setCustomField($fieldName, $fieldValue);
            }

            OfficeGuyApi::writeToLog(
                'Created CRM entity: ' . $entity->name . ' (ID: ' . $entity->id . ', SUMIT ID: ' . $sumitEntityId . ')',
                'info'
            );

            return [
                'success' => true,
                'entity' => $entity,
                'sumit_entity_id' => $sumitEntityId,
            ];

        } catch (\Throwable $e) {
            OfficeGuyApi::writeToLog(
                'SUMIT CRM createEntity exception: ' . $e->getMessage(),
                'error'
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get entity from SUMIT by ID
     *
     * Endpoint: POST /crm/data/getentity/
     *
     * @param int $sumitEntityId SUMIT entity ID
     * @return array{success: bool, entity?: array, error?: string}
     */
    public static function getEntity(int $sumitEntityId): array
    {
        try {
            $payload = [
                'Credentials' => PaymentService::getCredentials(),
                'EntityID' => $sumitEntityId,
            ];

            $response = OfficeGuyApi::post(
                $payload,
                '/crm/data/getentity/',
                config('officeguy.environment', 'www'),
                false
            );

            if ($response === null) {
                return [
                    'success' => false,
                    'error' => 'No response from SUMIT API',
                ];
            }

            if (($response['Status'] ?? 1) !== 0) {
                return [
                    'success' => false,
                    'error' => $response['UserErrorMessage'] ?? 'Failed to get entity from SUMIT',
                ];
            }

            return [
                'success' => true,
                'entity' => $response['Data'] ?? [],
            ];

        } catch (\Throwable $e) {
            OfficeGuyApi::writeToLog(
                'SUMIT CRM getEntity exception for entity ' . $sumitEntityId . ': ' . $e->getMessage(),
                'error'
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update entity in SUMIT and local database
     *
     * Endpoint: POST /crm/data/updateentity/
     *
     * @param int $entityId Local entity ID
     * @param array $fields Fields to update
     * @return array{success: bool, entity?: CrmEntity, error?: string}
     */
    public static function updateEntity(int $entityId, array $fields): array
    {
        try {
            // Get local entity
            $entity = CrmEntity::find($entityId);

            if (!$entity) {
                return [
                    'success' => false,
                    'error' => 'Entity not found',
                ];
            }

            if (!$entity->sumit_entity_id) {
                return [
                    'success' => false,
                    'error' => 'Entity not synced with SUMIT',
                ];
            }

            // Update entity in SUMIT
            $payload = [
                'Credentials' => PaymentService::getCredentials(),
                'EntityID' => $entity->sumit_entity_id,
                'Fields' => $fields,
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
                    'error' => 'No response from SUMIT API',
                ];
            }

            if (($response['Status'] ?? 1) !== 0) {
                return [
                    'success' => false,
                    'error' => $response['UserErrorMessage'] ?? 'Failed to update entity in SUMIT',
                ];
            }

            // Update standard fields in local entity
            $standardFields = [];
            foreach ($fields as $fieldName => $fieldValue) {
                if (in_array($fieldName, [
                    'name', 'email', 'phone', 'mobile', 'address', 'city', 'state',
                    'postal_code', 'country', 'company_name', 'tax_id', 'status',
                    'source', 'owner_user_id', 'assigned_to_user_id', 'sumit_customer_id'
                ])) {
                    $standardFields[$fieldName] = $fieldValue;
                }
            }

            if (!empty($standardFields)) {
                $entity->update($standardFields);
            }

            // Update custom fields
            foreach ($fields as $fieldName => $fieldValue) {
                if (!in_array($fieldName, array_keys($standardFields))) {
                    $entity->setCustomField($fieldName, $fieldValue);
                }
            }

            OfficeGuyApi::writeToLog(
                'Updated CRM entity: ' . $entity->name . ' (ID: ' . $entity->id . ')',
                'info'
            );

            return [
                'success' => true,
                'entity' => $entity->fresh(),
            ];

        } catch (\Throwable $e) {
            OfficeGuyApi::writeToLog(
                'SUMIT CRM updateEntity exception for entity ' . $entityId . ': ' . $e->getMessage(),
                'error'
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete entity (soft delete) in SUMIT and local database
     *
     * Endpoint: POST /crm/data/deleteentity/
     *
     * @param int $entityId Local entity ID
     * @return array{success: bool, error?: string}
     */
    public static function deleteEntity(int $entityId): array
    {
        try {
            // Get local entity
            $entity = CrmEntity::find($entityId);

            if (!$entity) {
                return [
                    'success' => false,
                    'error' => 'Entity not found',
                ];
            }

            if (!$entity->sumit_entity_id) {
                // Entity not synced, just delete locally
                $entity->delete();

                return [
                    'success' => true,
                ];
            }

            // Delete entity in SUMIT
            $payload = [
                'Credentials' => PaymentService::getCredentials(),
                'EntityID' => $entity->sumit_entity_id,
            ];

            $response = OfficeGuyApi::post(
                $payload,
                '/crm/data/deleteentity/',
                config('officeguy.environment', 'www'),
                false
            );

            if ($response === null) {
                return [
                    'success' => false,
                    'error' => 'No response from SUMIT API',
                ];
            }

            if (($response['Status'] ?? 1) !== 0) {
                return [
                    'success' => false,
                    'error' => $response['UserErrorMessage'] ?? 'Failed to delete entity in SUMIT',
                ];
            }

            // Soft delete locally
            $entity->delete();

            OfficeGuyApi::writeToLog(
                'Deleted CRM entity: ' . $entity->name . ' (ID: ' . $entity->id . ')',
                'info'
            );

            return [
                'success' => true,
            ];

        } catch (\Throwable $e) {
            OfficeGuyApi::writeToLog(
                'SUMIT CRM deleteEntity exception for entity ' . $entityId . ': ' . $e->getMessage(),
                'error'
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * List entities from SUMIT with filters
     *
     * Endpoint: POST /crm/data/listentities/
     *
     * @param int $folderId Local folder ID
     * @param array $filters Filter parameters
     * @return array{success: bool, entities?: array, total?: int, error?: string}
     */
    public static function listEntities(int $folderId, array $filters = []): array
    {
        try {
            // Get folder
            $folder = CrmFolder::find($folderId);

            if (!$folder) {
                return [
                    'success' => false,
                    'error' => 'Folder not found',
                ];
            }

            if (!$folder->sumit_folder_id) {
                return [
                    'success' => false,
                    'error' => 'Folder not synced with SUMIT',
                ];
            }

            $payload = [
                'Credentials' => PaymentService::getCredentials(),
                'FolderID' => $folder->sumit_folder_id,
                'Filters' => $filters,
            ];

            $response = OfficeGuyApi::post(
                $payload,
                '/crm/data/listentities/',
                config('officeguy.environment', 'www'),
                false
            );

            if ($response === null) {
                return [
                    'success' => false,
                    'error' => 'No response from SUMIT API',
                ];
            }

            if (($response['Status'] ?? 1) !== 0) {
                return [
                    'success' => false,
                    'error' => $response['UserErrorMessage'] ?? 'Failed to list entities from SUMIT',
                ];
            }

            return [
                'success' => true,
                'entities' => $response['Data']['Entities'] ?? [],
                'total' => $response['Data']['Total'] ?? 0,
            ];

        } catch (\Throwable $e) {
            OfficeGuyApi::writeToLog(
                'SUMIT CRM listEntities exception for folder ' . $folderId . ': ' . $e->getMessage(),
                'error'
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sync entity from SUMIT to local database
     *
     * @param int $sumitEntityId SUMIT entity ID
     * @return array{success: bool, entity?: CrmEntity, error?: string}
     */
    public static function syncEntityFromSumit(int $sumitEntityId): array
    {
        try {
            // Get entity from SUMIT
            $result = self::getEntity($sumitEntityId);

            if (!$result['success']) {
                return $result;
            }

            $entityData = $result['entity'];
            $folderId = $entityData['FolderID'] ?? null;

            if (!$folderId) {
                return [
                    'success' => false,
                    'error' => 'No folder ID in SUMIT entity data',
                ];
            }

            // Find local folder by SUMIT folder ID
            $folder = CrmFolder::where('sumit_folder_id', $folderId)->first();

            if (!$folder) {
                return [
                    'success' => false,
                    'error' => 'Folder not found in local database',
                ];
            }

            $fields = $entityData['Fields'] ?? [];

            // Create or update local entity
            $entity = CrmEntity::updateOrCreate(
                ['sumit_entity_id' => $sumitEntityId],
                [
                    'crm_folder_id' => $folder->id,
                    'entity_type' => $folder->entity_type,
                    'name' => $fields['name'] ?? 'Unknown',
                    'email' => $fields['email'] ?? null,
                    'phone' => $fields['phone'] ?? null,
                    'mobile' => $fields['mobile'] ?? null,
                    'address' => $fields['address'] ?? null,
                    'city' => $fields['city'] ?? null,
                    'state' => $fields['state'] ?? null,
                    'postal_code' => $fields['postal_code'] ?? null,
                    'country' => $fields['country'] ?? 'Israel',
                    'company_name' => $fields['company_name'] ?? null,
                    'tax_id' => $fields['tax_id'] ?? null,
                    'status' => $fields['status'] ?? 'active',
                    'source' => $fields['source'] ?? null,
                ]
            );

            // Sync custom fields
            foreach ($fields as $fieldName => $fieldValue) {
                if (!in_array($fieldName, [
                    'name', 'email', 'phone', 'mobile', 'address', 'city', 'state',
                    'postal_code', 'country', 'company_name', 'tax_id', 'status', 'source'
                ])) {
                    $entity->setCustomField($fieldName, $fieldValue);
                }
            }

            OfficeGuyApi::writeToLog(
                'Synced CRM entity from SUMIT: ' . $entity->name . ' (ID: ' . $entity->id . ')',
                'info'
            );

            return [
                'success' => true,
                'entity' => $entity,
            ];

        } catch (\Throwable $e) {
            OfficeGuyApi::writeToLog(
                'SUMIT CRM syncEntityFromSumit exception for entity ' . $sumitEntityId . ': ' . $e->getMessage(),
                'error'
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sync all entities for a folder from SUMIT
     *
     * @param int $folderId Local folder ID
     * @param array $filters Optional filters
     * @return array{success: bool, entities_synced?: int, error?: string}
     */
    public static function syncAllEntities(int $folderId, array $filters = []): array
    {
        try {
            // Get entities from SUMIT
            $result = self::listEntities($folderId, $filters);

            if (!$result['success']) {
                return $result;
            }

            $entities = $result['entities'];
            $synced = 0;

            foreach ($entities as $entityData) {
                $sumitEntityId = $entityData['EntityID'] ?? null;

                if (!$sumitEntityId) {
                    continue;
                }

                $syncResult = self::syncEntityFromSumit($sumitEntityId);

                if ($syncResult['success']) {
                    $synced++;
                }
            }

            OfficeGuyApi::writeToLog(
                'Synced ' . $synced . ' entities for folder ' . $folderId,
                'info'
            );

            return [
                'success' => true,
                'entities_synced' => $synced,
            ];

        } catch (\Throwable $e) {
            OfficeGuyApi::writeToLog(
                'SUMIT CRM syncAllEntities exception for folder ' . $folderId . ': ' . $e->getMessage(),
                'error'
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Archive entity (soft delete alternative)
     *
     * Endpoint: POST /crm/data/archiveentity/
     *
     * Archives an entity instead of permanently deleting it.
     * Archived entities can potentially be restored later.
     *
     * @param int $sumitEntityId SUMIT entity ID
     * @return array{success: bool, error?: string}
     */
    public static function archiveEntity(int $sumitEntityId): array
    {
        try {
            $payload = [
                'Credentials' => PaymentService::getCredentials(),
                'EntityID' => $sumitEntityId,
            ];

            $response = OfficeGuyApi::post(
                $payload,
                '/crm/data/archiveentity/',
                config('officeguy.environment', 'www'),
                false
            );

            if ($response === null || ($response['Status'] ?? 1) !== 0) {
                return [
                    'success' => false,
                    'error' => $response['UserErrorMessage'] ?? 'Failed to archive entity',
                ];
            }

            // Update local entity if exists
            $entity = CrmEntity::where('sumit_entity_id', $sumitEntityId)->first();
            if ($entity) {
                $entity->update(['is_active' => false]);
            }

            OfficeGuyApi::writeToLog(
                'SUMIT CRM entity archived: ' . $sumitEntityId,
                'info'
            );

            return ['success' => true];

        } catch (\Throwable $e) {
            OfficeGuyApi::writeToLog(
                'SUMIT CRM archiveEntity exception: ' . $e->getMessage(),
                'error'
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Count entity usage across the system
     *
     * Endpoint: POST /crm/data/countentityusage/
     *
     * Returns count of how many times this entity is referenced
     * in other entities, documents, or system objects.
     *
     * @param int $sumitEntityId SUMIT entity ID
     * @return array{success: bool, usage_count?: int, error?: string}
     */
    public static function countEntityUsage(int $sumitEntityId): array
    {
        try {
            $payload = [
                'Credentials' => PaymentService::getCredentials(),
                'EntityID' => $sumitEntityId,
            ];

            $response = OfficeGuyApi::post(
                $payload,
                '/crm/data/countentityusage/',
                config('officeguy.environment', 'www'),
                false
            );

            if ($response === null || ($response['Status'] ?? 1) !== 0) {
                return [
                    'success' => false,
                    'error' => $response['UserErrorMessage'] ?? 'Failed to count entity usage',
                ];
            }

            $usageCount = $response['Data']['Count'] ?? 0;

            OfficeGuyApi::writeToLog(
                "SUMIT CRM entity {$sumitEntityId} usage count: {$usageCount}",
                'info'
            );

            return [
                'success' => true,
                'usage_count' => $usageCount,
            ];

        } catch (\Throwable $e) {
            OfficeGuyApi::writeToLog(
                'SUMIT CRM countEntityUsage exception: ' . $e->getMessage(),
                'error'
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get entity HTML for printing
     *
     * Endpoint: POST /crm/data/getentityprinthtml/
     *
     * Returns formatted HTML suitable for printing a single entity.
     * Can optionally return PDF instead of HTML.
     *
     * @param int $sumitEntityId SUMIT entity ID
     * @param int $schemaId Schema/folder ID
     * @param bool $pdf Return PDF instead of HTML (default: false)
     * @return array{success: bool, html?: string, pdf?: string, error?: string}
     */
    public static function getEntityPrintHTML(int $sumitEntityId, int $schemaId, bool $pdf = false): array
    {
        try {
            $payload = [
                'Credentials' => PaymentService::getCredentials(),
                'EntityID' => $sumitEntityId,
                'SchemaID' => $schemaId,
                'PDF' => $pdf,
            ];

            $response = OfficeGuyApi::post(
                $payload,
                '/crm/data/getentityprinthtml/',
                config('officeguy.environment', 'www'),
                false
            );

            if ($response === null || ($response['Status'] ?? 1) !== 0) {
                return [
                    'success' => false,
                    'error' => $response['UserErrorMessage'] ?? 'Failed to get entity print HTML',
                ];
            }

            $result = ['success' => true];

            if ($pdf) {
                $result['pdf'] = $response['Data']['PDF'] ?? '';
            } else {
                $result['html'] = $response['Data']['HTML'] ?? '';
            }

            OfficeGuyApi::writeToLog(
                "SUMIT CRM entity {$sumitEntityId} print " . ($pdf ? 'PDF' : 'HTML') . ' retrieved',
                'info'
            );

            return $result;

        } catch (\Throwable $e) {
            OfficeGuyApi::writeToLog(
                'SUMIT CRM getEntityPrintHTML exception: ' . $e->getMessage(),
                'error'
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get entities list HTML for printing
     *
     * Endpoint: POST /crm/data/getentitieshtml/
     *
     * Returns formatted HTML suitable for printing a list of entities
     * based on a specific view. Can optionally return PDF instead of HTML.
     *
     * @param int $schemaId Schema/folder ID
     * @param int $viewId View ID for filtering/sorting
     * @param bool $pdf Return PDF instead of HTML (default: false)
     * @return array{success: bool, html?: string, pdf?: string, error?: string}
     */
    public static function getEntitiesHTML(int $schemaId, int $viewId, bool $pdf = false): array
    {
        try {
            $payload = [
                'Credentials' => PaymentService::getCredentials(),
                'SchemaID' => $schemaId,
                'ViewID' => $viewId,
                'PDF' => $pdf,
            ];

            $response = OfficeGuyApi::post(
                $payload,
                '/crm/data/getentitieshtml/',
                config('officeguy.environment', 'www'),
                false
            );

            if ($response === null || ($response['Status'] ?? 1) !== 0) {
                return [
                    'success' => false,
                    'error' => $response['UserErrorMessage'] ?? 'Failed to get entities HTML',
                ];
            }

            $result = ['success' => true];

            if ($pdf) {
                $result['pdf'] = $response['Data']['PDF'] ?? '';
            } else {
                $result['html'] = $response['Data']['HTML'] ?? '';
            }

            OfficeGuyApi::writeToLog(
                "SUMIT CRM entities list (schema: {$schemaId}, view: {$viewId}) " . ($pdf ? 'PDF' : 'HTML') . ' retrieved',
                'info'
            );

            return $result;

        } catch (\Throwable $e) {
            OfficeGuyApi::writeToLog(
                'SUMIT CRM getEntitiesHTML exception: ' . $e->getMessage(),
                'error'
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
