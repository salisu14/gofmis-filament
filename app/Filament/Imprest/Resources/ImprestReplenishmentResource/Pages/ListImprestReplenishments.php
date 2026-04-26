<?php

namespace App\Filament\Imprest\Resources\ImprestReplenishmentResource\Pages;

use App\Filament\Imprest\Resources\ImprestReplenishmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListImprestReplenishments extends ListRecords
{
    protected static string $resource = ImprestReplenishmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-m-plus'),
        ];
    }
}
