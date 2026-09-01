<?php

namespace App\Filament\Resources\WidowLoans\Pages;

use App\Filament\Exports\WidowLoanExporter;
use App\Filament\Resources\WidowLoans\WidowLoanResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListWidowLoans extends ListRecords
{
    protected static string $resource = WidowLoanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            ExportAction::make()->visible(fn () => ! auth()->user()?->isDemoObserver())
                ->exporter(WidowLoanExporter::class)
                ->enableVisibleTableColumnsByDefault(),
        ];
    }
}
