<?php

namespace App\Filament\Resources\ZoneTransfers\Pages;

use App\Filament\Resources\ZoneTransfers\ZoneTransferResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListZoneTransfers extends ListRecords
{
    protected static string $resource = ZoneTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
