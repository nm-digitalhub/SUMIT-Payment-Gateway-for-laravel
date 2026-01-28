<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use OfficeGuy\LaravelSumitGateway\DataTransferObjects\PackageVersion;

/**
 * Package Version Service
 *
 * Provides version information about the SUMIT Payment Gateway package
 * by comparing the installed version (from composer.lock) with the latest
 * version available on Packagist.
 *
 * ## Architecture
 *
 * This is a **READ-ONLY** service. It does NOT:
 * - Send notifications
 * - Interact with users or roles
 * - Perform any actions based on version status
 *
 * It only provides data that can be consumed by:
 * - About pages (Filament)
 * - CLI commands
 * - Health checks
 * - Monitoring systems
 *
 * The **Application Layer** decides what to do with this information:
 * - Display badges in UI
 * - Send notifications to admins
 * - Trigger alerts
 */
class PackageVersionService
{
    /**
     * The package name on Packagist.
     */
    protected const PACKAGE_NAME = 'officeguy/laravel-sumit-gateway';

    /**
     * Cache key for the latest version.
     */
    protected const CACHE_KEY = 'officeguy.package_latest_version';

    /**
     * Cache duration in seconds (default: 24 hours).
     */
    protected const CACHE_TTL = 86400;

    /**
     * Get the package version status.
     *
     * This method compares the installed version (from composer.lock)
     * with the latest version from Packagist API.
     *
     * Results are cached for 24 hours to avoid excessive API calls.
     */
    public function getStatus(): PackageVersion
    {
        $installed = $this->getInstalledVersion();
        $latest = $this->getLatestVersion();

        return new PackageVersion(
            installed: $installed,
            latest: $latest,
            outdated: version_compare($installed, $latest, '<'),
            latestUrl: 'https://packagist.org/packages/' . self::PACKAGE_NAME,
            changelogUrl: 'https://github.com/nm-digitalhub/SUMIT-Payment-Gateway-for-laravel/blob/main/CHANGELOG.md',
            timestamp: time()
        );
    }

    /**
     * Get the currently installed version from composer.lock.
     */
    public function getInstalledVersion(): string
    {
        // Try to read from composer.lock file
        $composerLockPath = base_path('composer.lock');

        if (! file_exists($composerLockPath)) {
            return 'unknown';
        }

        $lockContent = json_decode(file_get_contents($composerLockPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Failed to parse composer.lock', [
                'error' => json_last_error_msg(),
            ]);

            return 'unknown';
        }

        // Find the package in installed packages
        foreach ($lockContent['packages'] ?? [] as $package) {
            if ($package['name'] === self::PACKAGE_NAME) {
                // Remove 'v' prefix if present (e.g., v2.3.0 -> 2.3.0)
                return ltrim((string) $package['version'], 'v');
            }
        }

        // Not found in composer.lock - might be dev version
        return 'dev';
    }

    /**
     * Get the latest version from Packagist API.
     *
     * Results are cached for 24 hours.
     */
    public function getLatestVersion(): string
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn (): string => $this->fetchLatestVersionFromPackagist());
    }

    /**
     * Fetch the latest version from Packagist API.
     *
     * Uses the Composer v2 metadata API which is always up to date
     * and very efficient (static files).
     *
     * @see https://packagist.org/apidoc#get-package-data
     */
    protected function fetchLatestVersionFromPackagist(): string
    {
        try {
            $url = 'https://repo.packagist.org/p2/' . self::PACKAGE_NAME . '.json';

            $response = Http::timeout(10)->get($url);

            if (! $response->successful()) {
                Log::warning('Failed to fetch package version from Packagist', [
                    'status' => $response->status(),
                    'package' => self::PACKAGE_NAME,
                ]);

                return 'unknown';
            }

            $data = $response->json();

            // Extract the latest stable version
            $packages = $data['packages'] ?? [];
            $packageVersions = $packages[self::PACKAGE_NAME] ?? [];

            if (empty($packageVersions)) {
                Log::warning('Package not found on Packagist', [
                    'package' => self::PACKAGE_NAME,
                ]);

                return 'unknown';
            }

            // Filter out dev versions and get the latest stable
            $stableVersions = array_filter($packageVersions, function (array $version): bool {
                $versionString = $version['version'] ?? '';

                // Skip dev versions, aliases, and references
                if (str_contains($versionString, 'dev')) {
                    return false;
                }

                if (str_contains($versionString, 'alias')) {
                    return false;
                }

                // Only stable versions (no alpha, beta, RC)
                return ! preg_match('/-(alpha|beta|rc|pl)/i', $versionString);
            });

            if ($stableVersions === []) {
                // No stable versions found, return the first version
                $latest = $packageVersions[0]['version'] ?? 'unknown';
            } else {
                // Get the first stable version (they're sorted by release date)
                $latest = $stableVersions[0]['version'] ?? 'unknown';
            }

            // Remove 'v' prefix if present
            return ltrim($latest, 'v');

        } catch (\Exception $e) {
            Log::error('Exception while fetching package version from Packagist', [
                'error' => $e->getMessage(),
                'package' => self::PACKAGE_NAME,
            ]);

            return 'unknown';
        }
    }

    /**
     * Clear the version cache.
     *
     * Useful for testing or forcing a refresh.
     */
    public function clearCache(): bool
    {
        return Cache::forget(self::CACHE_KEY);
    }

    /**
     * Force refresh the version information.
     *
     * Clears cache and fetches fresh data from Packagist.
     */
    public function refresh(): PackageVersion
    {
        $this->clearCache();

        return $this->getStatus();
    }
}
