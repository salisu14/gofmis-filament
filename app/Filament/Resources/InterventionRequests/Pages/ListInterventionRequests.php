<?php

namespace App\Filament\Resources\InterventionRequests\Pages;

use App\Filament\Exports\InterventionRequestExporter;
use App\Filament\Resources\InterventionRequests\InterventionRequestResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListInterventionRequests extends ListRecords
{
    protected static string $resource = InterventionRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            ExportAction::make()
                ->exporter(InterventionRequestExporter::class)
                ->enableVisibleTableColumnsByDefault(),
        ];
    }
}
