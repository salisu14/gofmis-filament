<?php

namespace App\Filament\Resources\IdCardPrintBatches\Pages;

use App\Filament\Resources\IdCardPrintBatches\IdCardPrintBatchResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditIdCardPrintBatch extends EditRecord
{
    protected static string $resource = IdCardPrintBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
