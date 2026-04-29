<?php

namespace App\Filament\Coordinator\Resources\DeceasedResource\Pages;

use App\Filament\Coordinator\Resources\DeceasedResource;
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
