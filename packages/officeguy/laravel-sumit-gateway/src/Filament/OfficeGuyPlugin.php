<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\OfficeGuyTransactionResource;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\OfficeGuyTokenResource;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\OfficeGuyDocumentResource;

class OfficeGuyPlugin implements Plugin
{
    public function getId(): string
    {
        return 'officeguy-gateway';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                OfficeGuyTransactionResource::class,
                OfficeGuyTokenResource::class,
                OfficeGuyDocumentResource::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        return filament(app(static::class)->getId());
    }
}
