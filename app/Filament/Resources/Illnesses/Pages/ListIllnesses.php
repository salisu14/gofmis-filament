<?php

namespace App\Filament\Resources\Illnesses\Pages;

use App\Filament\Resources\Illnesses\IllnessResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIllnesses extends ListRecords
{
    protected static string $resource = IllnessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
