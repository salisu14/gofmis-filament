<?php

namespace App\Filament\Resources\Zones\Pages;

use App\Filament\Exports\ZoneExporter;
use App\Filament\Resources\Zones\ZoneResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListZones extends ListRecords
{
    protected static string $resource = ZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            ExportAction::make()
                ->exporter(ZoneExporter::class)
                ->enableVisibleTableColumnsByDefault(),
        ];
    }
}
