<?php

declare(strict_types=1);

use OfficeGuy\LaravelSumitGateway\Settings\SumitSettings;

return [
    // Register settings classes
    'settings' => [
        SumitSettings::class,
    ],

    // Optionally override table/name per app; defaults from spatie config
];
