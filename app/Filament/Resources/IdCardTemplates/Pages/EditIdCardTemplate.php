<?php

namespace App\Filament\Resources\IdCardTemplates\Pages;

use App\Filament\Resources\IdCardTemplates\IdCardTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditIdCardTemplate extends EditRecord
{
    protected static string $resource = IdCardTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
