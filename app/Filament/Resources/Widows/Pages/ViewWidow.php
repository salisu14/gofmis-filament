<?php

namespace App\Filament\Resources\Widows\Pages;

use App\Filament\Resources\Widows\WidowResource;
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
