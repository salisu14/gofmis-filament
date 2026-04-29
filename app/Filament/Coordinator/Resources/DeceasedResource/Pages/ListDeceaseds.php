<?php

namespace App\Filament\Coordinator\Resources\DeceasedResource\Pages;

use App\Filament\Coordinator\Resources\DeceasedResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDeceaseds extends ListRecords
{
    protected static string $resource = DeceasedResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
