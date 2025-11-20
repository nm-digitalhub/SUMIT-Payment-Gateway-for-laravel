<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Enums;

/**
 * Payment status enumeration
 */
enum PaymentStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case REFUNDED = 'refunded';
    case CANCELLED = 'cancelled';
}
