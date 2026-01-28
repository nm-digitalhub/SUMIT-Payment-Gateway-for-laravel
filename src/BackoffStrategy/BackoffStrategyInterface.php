<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\BackoffStrategy;

/**
 * Backoff Strategy Interface
 *
 * Defines contract for calculating retry delays.
 */
interface BackoffStrategyInterface
{
    /**
     * Calculate wait time in seconds after a failed attempt.
     *
     * @param  int  $attempt  The attempt number (1-based)
     * @return int Seconds to wait before next retry
     */
    public function waitInSecondsAfterAttempt(int $attempt): int;
}
