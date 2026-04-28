<?php

namespace App\Filament\Resources\IdCards\Pages;

use App\Filament\Resources\IdCards\IdCardResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditIdCard extends EditRecord
{
    protected static string $resource = IdCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
