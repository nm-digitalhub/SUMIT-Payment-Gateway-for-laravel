<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Support;

/**
 * Normalizes SUMIT API "wrapped" responses per OpenAPI (Teva.Common.ResponseStatus)
 * while remaining compatible with legacy numeric Status (0) from older clients.
 */
final class SumitApiResponse
{
    /**
     * OpenAPI: Teva.Common.ResponseStatus — "Success (0)", "BusinessError (1)", "TechnicalError (2)".
     * Legacy: integer 0 / 1 / 2.
     */
    public static function isSuccess(mixed $status): bool
    {
        if ($status === 0 || $status === '0') {
            return true;
        }

        if (is_string($status)) {
            return str_starts_with($status, 'Success');
        }

        return false;
    }
}
