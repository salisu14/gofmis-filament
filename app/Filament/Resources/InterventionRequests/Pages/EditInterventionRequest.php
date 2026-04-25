<?php

namespace App\Filament\Resources\InterventionRequests\Pages;

use App\Filament\Resources\InterventionRequests\InterventionRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInterventionRequest extends EditRecord
{
    protected static string $resource = InterventionRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
