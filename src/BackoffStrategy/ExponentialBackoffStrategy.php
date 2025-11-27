<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\BackoffStrategy;

/**
 * Exponential Backoff Strategy
 *
 * Calculates wait time between retry attempts using exponential growth.
 * Based on Spatie's implementation.
 */
class ExponentialBackoffStrategy implements BackoffStrategyInterface
{
    /**
     * Calculate wait time in seconds after a failed attempt.
     *
     * Formula: 10^(attempt) seconds
     * - Attempt 1: 10 seconds
     * - Attempt 2: 100 seconds (~1.7 minutes)
     * - Attempt 3: 1,000 seconds (~16.7 minutes)
     * - Attempt 4: 10,000 seconds (~2.8 hours)
     *
     * @param int $attempt The attempt number (1-based)
     * @return int Seconds to wait before next retry
     */
    public function waitInSecondsAfterAttempt(int $attempt): int
    {
        return (int) (10 ** $attempt);
    }
}
