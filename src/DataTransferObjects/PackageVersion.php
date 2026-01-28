<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\DataTransferObjects;

/**
 * Package Version Data Transfer Object
 *
 * Represents the version status of the package.
 * Used by PackageVersionService to provide version information
 * to the About page or other consumers.
 *
 * This is READ-ONLY data - the service does not send notifications
 * or perform any actions based on version status.
 */
readonly class PackageVersion
{
    /**
     * Create a new PackageVersion instance.
     *
     * @param  string  $installed  The currently installed version (from composer.lock)
     * @param  string  $latest  The latest version available on Packagist
     * @param  bool  $outdated  Whether the installed version is outdated
     * @param  string|null  $latestUrl  URL to the latest version on Packagist
     * @param  string|null  $changelogUrl  URL to the CHANGELOG.md on GitHub
     * @param  int|null  $timestamp  Unix timestamp of when this data was fetched
     */
    public function __construct(
        public string $installed,
        public string $latest,
        public bool $outdated,
        public ?string $latestUrl = null,
        public ?string $changelogUrl = null,
        public ?int $timestamp = null
    ) {
        // Set defaults for URLs if not provided
        $this->latestUrl ??= 'https://packagist.org/packages/officeguy/laravel-sumit-gateway';
        $this->changelogUrl ??= 'https://github.com/nm-digitalhub/SUMIT-Payment-Gateway-for-laravel/blob/main/CHANGELOG.md';
        $this->timestamp ??= time();
    }

    /**
     * Create from raw array data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            installed: $data['installed'],
            latest: $data['latest'],
            outdated: $data['outdated'] ?? false,
            latestUrl: $data['latest_url'] ?? null,
            changelogUrl: $data['changelog_url'] ?? null,
            timestamp: $data['timestamp'] ?? null
        );
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'installed' => $this->installed,
            'latest' => $this->latest,
            'outdated' => $this->outdated,
            'latest_url' => $this->latestUrl,
            'changelog_url' => $this->changelogUrl,
            'timestamp' => $this->timestamp,
        ];
    }

    /**
     * Get the version comparison badge color.
     */
    public function getBadgeColor(): string
    {
        if (! $this->outdated) {
            return 'success'; // Green - up to date
        }

        // Determine severity based on version difference
        $installedParts = explode('.', $this->installed);
        $latestParts = explode('.', $this->latest);

        // Major version difference
        if (($installedParts[0] ?? 0) < ($latestParts[0] ?? 0)) {
            return 'danger'; // Red - major update available
        }

        // Minor version difference
        if (($installedParts[1] ?? 0) < ($latestParts[1] ?? 0)) {
            return 'warning'; // Orange - minor update available
        }

        // Patch version difference
        return 'info'; // Blue - patch update available
    }

    /**
     * Get human-readable status message.
     */
    public function getStatusMessage(string $locale = 'he'): string
    {
        if (! $this->outdated) {
            return $locale === 'he'
                ? 'הגרסה מעודכנת'
                : 'Up to date';
        }

        return $locale === 'he'
            ? "עדכון זמין: {$this->latest}"
            : "Update available: {$this->latest}";
    }
}
