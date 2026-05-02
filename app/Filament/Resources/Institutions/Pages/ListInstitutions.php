<?php

namespace App\Filament\Resources\Institutions\Pages;

use App\Filament\Exports\InstitutionExporter;
use App\Filament\Resources\Institutions\InstitutionResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListInstitutions extends ListRecords
{
    protected static string $resource = InstitutionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            ExportAction::make()
                ->exporter(InstitutionExporter::class)
                ->enableVisibleTableColumnsByDefault(),
        ];
    }
}
