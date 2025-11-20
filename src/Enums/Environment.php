<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Enums;

/**
 * SUMIT environment enumeration
 */
enum Environment: string
{
    case PRODUCTION = 'www';
    case DEVELOPMENT = 'dev';
    case TEST = 'test';
}
