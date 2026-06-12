<?php

namespace App\Filament\Resources\IdCardPrintBatches\Pages;

use App\Filament\Resources\IdCardPrintBatches\IdCardPrintBatchResource;
use App\Filament\Resources\IdCards\IdCardResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListIdCardPrintBatches extends ListRecords
{
    protected static string $resource = IdCardPrintBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bulk_generator')
                ->label('Bulk Generate ID Cards')
                ->icon('heroicon-o-printer')
                ->color('primary')
                ->url(fn (): string => IdCardResource::getUrl('bulk-print')),
        ];
    }
}
