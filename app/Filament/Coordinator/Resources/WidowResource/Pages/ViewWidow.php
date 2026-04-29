<?php

namespace App\Filament\Coordinator\Resources\WidowResource\Pages;

use App\Filament\Coordinator\Resources\WidowResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWidow extends ViewRecord
{
    protected static string $resource = WidowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
