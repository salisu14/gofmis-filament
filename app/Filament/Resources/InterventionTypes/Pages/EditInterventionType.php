<?php

namespace App\Filament\Resources\InterventionTypes\Pages;

use App\Filament\Resources\InterventionTypes\InterventionTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInterventionType extends EditRecord
{
    protected static string $resource = InterventionTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
