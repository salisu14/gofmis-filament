<?php

namespace App\Filament\Resources\Deceased\Pages;

use App\Filament\Resources\Deceased\DeceasedResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDeceased extends ViewRecord
{
    protected static string $resource = DeceasedResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
