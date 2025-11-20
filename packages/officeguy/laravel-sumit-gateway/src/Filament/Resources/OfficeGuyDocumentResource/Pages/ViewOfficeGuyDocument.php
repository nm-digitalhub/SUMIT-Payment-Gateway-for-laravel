<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\OfficeGuyDocumentResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\OfficeGuyDocumentResource;

class ViewOfficeGuyDocument extends ViewRecord
{
    protected static string $resource = OfficeGuyDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download_pdf')
                ->label('Download PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->action(function () {
                    // Placeholder for PDF download logic
                    \Filament\Notifications\Notification::make()
                        ->title('PDF download not yet implemented')
                        ->body('This would download the document PDF from SUMIT API.')
                        ->warning()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
