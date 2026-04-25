<?php

namespace App\Filament\Resources\InterventionTypes\Pages;

use App\Filament\Resources\InterventionTypes\InterventionTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInterventionTypes extends ListRecords
{
    protected static string $resource = InterventionTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
