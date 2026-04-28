<?php

namespace App\Filament\Resources\ZoneTransfers\Pages;

use App\Filament\Resources\ZoneTransfers\ZoneTransferResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditZoneTransfer extends EditRecord
{
    protected static string $resource = ZoneTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
