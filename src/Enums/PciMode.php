<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Enums;

/**
 * PCI mode enumeration
 */
enum PciMode: string
{
    case SIMPLE = 'no';        // PaymentsJS (recommended)
    case REDIRECT = 'redirect'; // External redirect
    case ADVANCED = 'yes';      // PCI compliant direct API
}
