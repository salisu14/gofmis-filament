<?php

namespace App\Filament\Resources\IdCardTemplates\Pages;

use App\Filament\Resources\IdCardTemplates\IdCardTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIdCardTemplates extends ListRecords
{
    protected static string $resource = IdCardTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
