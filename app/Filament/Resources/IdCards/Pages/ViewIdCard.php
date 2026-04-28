<?php

namespace App\Filament\Resources\IdCards\Pages;

use App\Filament\Resources\IdCards\IdCardResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewIdCard extends ViewRecord
{
    protected static string $resource = IdCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
