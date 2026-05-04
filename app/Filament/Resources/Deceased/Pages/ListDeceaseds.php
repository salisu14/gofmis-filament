<?php

namespace App\Filament\Resources\Deceased\Pages;

use App\Filament\Exports\DeceasedExporter;
use App\Filament\Resources\Deceased\DeceasedResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListDeceaseds extends ListRecords
{
    protected static string $resource = DeceasedResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            ExportAction::make()
                ->exporter(DeceasedExporter::class)
                ->enableVisibleTableColumnsByDefault(),
        ];
    }
}
