<?php

namespace App\Filament\Coordinator\Resources\WidowResource\Pages;

use App\Filament\Coordinator\Resources\WidowResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWidows extends ListRecords
{
    protected static string $resource = WidowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
