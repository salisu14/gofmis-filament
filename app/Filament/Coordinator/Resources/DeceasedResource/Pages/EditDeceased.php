<?php

namespace App\Filament\Coordinator\Resources\DeceasedResource\Pages;

use App\Filament\Coordinator\Resources\DeceasedResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDeceased extends EditRecord
{
    protected static string $resource = DeceasedResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
