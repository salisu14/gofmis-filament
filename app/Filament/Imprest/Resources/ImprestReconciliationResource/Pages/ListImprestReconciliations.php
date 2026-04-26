<?php

namespace App\Filament\Imprest\Resources\ImprestReconciliationResource\Pages;

use App\Filament\Imprest\Resources\ImprestReconciliationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListImprestReconciliations extends ListRecords
{
    protected static string $resource = ImprestReconciliationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-m-plus'),
        ];
    }
}
