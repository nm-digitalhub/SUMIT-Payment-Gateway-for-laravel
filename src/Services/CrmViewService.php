<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use OfficeGuy\LaravelSumitGateway\Models\CrmFolder;
use OfficeGuy\LaravelSumitGateway\Models\CrmView;

/**
 * CRM View Service
 *
 * Handles CRM view operations: listing saved views/filters for folders.
 * Views allow users to save custom filters, column configurations, and sorting preferences.
 */
class CrmViewService
{
    /**
     * List all views for a specific CRM folder from SUMIT API
     *
     * Endpoint: POST /crm/views/listviews/
     *
     * Returns array of views with minimal data: ID and Name only.
     * SUMIT API provides limited view data similar to the folder limitation.
     *
     * @param  int  $folderId  SUMIT folder ID
     * @return array{success: bool, views?: array, error?: string}
     */
    public static function listViews(int $folderId): array
    {
        try {
            $payload = [
                'Credentials' => PaymentService::getCredentials(),
                'FolderID' => $folderId,
            ];

            $response = OfficeGuyApi::post(
                $payload,
                '/crm/views/listviews/',
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
                    'views' => $response['Data']['Views'] ?? [],
                ];
            }

            return [
                'success' => false,
                'error' => $response['UserErrorMessage'] ?? 'Failed to list views',
            ];

        } catch (\Throwable $e) {
            OfficeGuyApi::writeToLog(
                'SUMIT CRM listViews exception for folder ' . $folderId . ': ' . $e->getMessage(),
                'error'
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sync view from SUMIT to local database
     *
     * Creates or updates a view with basic information from listViews API.
     * Note: SUMIT API provides only ID and Name for views (similar to folders limitation).
     *
     * @param  int  $sumitFolderId  SUMIT folder ID
     * @param  int  $sumitViewId  SUMIT view ID
     * @param  string  $viewName  View name from listViews
     * @return array{success: bool, view?: CrmView, error?: string}
     */
    public static function syncViewFromSumit(int $sumitFolderId, int $sumitViewId, string $viewName): array
    {
        try {
            // Get local folder
            $folder = CrmFolder::where('sumit_folder_id', $sumitFolderId)->first();

            if (! $folder) {
                return ['success' => false, 'error' => 'Folder not found locally'];
            }

            // Create or update view with available data
            $view = CrmView::updateOrCreate(
                ['sumit_view_id' => $sumitViewId],
                [
                    'crm_folder_id' => $folder->id,
                    'name' => $viewName,
                    'is_default' => false, // Cannot determine from API
                    'is_public' => true, // Default to public
                    'user_id' => null, // Public view, no specific user
                    'filters' => null, // Not available from API
                    'sort_by' => null, // Not available from API
                    'sort_direction' => 'asc', // Default
                    'columns' => null, // Not available from API
                ]
            );

            OfficeGuyApi::writeToLog(
                'Synced CRM view: ' . $view->name . ' (SUMIT ID: ' . $sumitViewId . ', Folder: ' . $folder->name . ')',
                'info'
            );

            return [
                'success' => true,
                'view' => $view,
            ];

        } catch (\Throwable $e) {
            OfficeGuyApi::writeToLog(
                'SUMIT CRM syncViewFromSumit exception: ' . $e->getMessage(),
                'error'
            );

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Sync all views for a folder from SUMIT to local database
     *
     * Fetches all views for a folder and syncs them to local database.
     *
     * @param  int  $sumitFolderId  SUMIT folder ID
     * @return array{success: bool, synced_count?: int, error?: string}
     */
    public static function syncAllViews(int $sumitFolderId): array
    {
        try {
            // Get views from SUMIT
            $result = self::listViews($sumitFolderId);

            if (! $result['success']) {
                return ['success' => false, 'error' => $result['error']];
            }

            $views = $result['views'];
            $syncedCount = 0;

            foreach ($views as $viewData) {
                $viewId = $viewData['ID'] ?? null;
                $viewName = $viewData['Name'] ?? 'Unknown View';

                if (! $viewId) {
                    continue;
                }

                $syncResult = self::syncViewFromSumit($sumitFolderId, $viewId, $viewName);

                if ($syncResult['success']) {
                    $syncedCount++;
                }
            }

            OfficeGuyApi::writeToLog(
                "Synced {$syncedCount} views for folder ID {$sumitFolderId}",
                'info'
            );

            return [
                'success' => true,
                'synced_count' => $syncedCount,
            ];

        } catch (\Throwable $e) {
            OfficeGuyApi::writeToLog(
                'SUMIT CRM syncAllViews exception: ' . $e->getMessage(),
                'error'
            );

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Sync all views for all folders from SUMIT to local database
     *
     * Iterates through all synced folders and syncs their views.
     *
     * @return array{success: bool, folders_processed?: int, total_views?: int, error?: string}
     */
    public static function syncAllFoldersViews(): array
    {
        try {
            $folders = CrmFolder::whereNotNull('sumit_folder_id')->get();
            $foldersProcessed = 0;
            $totalViews = 0;

            foreach ($folders as $folder) {
                $result = self::syncAllViews($folder->sumit_folder_id);

                if ($result['success']) {
                    $foldersProcessed++;
                    $totalViews += $result['synced_count'];
                }
            }

            OfficeGuyApi::writeToLog(
                "Synced views for {$foldersProcessed} folders, total {$totalViews} views",
                'info'
            );

            return [
                'success' => true,
                'folders_processed' => $foldersProcessed,
                'total_views' => $totalViews,
            ];

        } catch (\Throwable $e) {
            OfficeGuyApi::writeToLog(
                'SUMIT CRM syncAllFoldersViews exception: ' . $e->getMessage(),
                'error'
            );

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
