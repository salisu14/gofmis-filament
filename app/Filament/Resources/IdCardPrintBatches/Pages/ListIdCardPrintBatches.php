<?php

namespace App\Filament\Resources\IdCardPrintBatches\Pages;

use App\Filament\Resources\IdCardPrintBatches\IdCardPrintBatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIdCardPrintBatches extends ListRecords
{
    protected static string $resource = IdCardPrintBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
