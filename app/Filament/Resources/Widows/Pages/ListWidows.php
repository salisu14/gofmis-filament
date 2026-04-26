<?php

namespace App\Filament\Resources\Widows\Pages;

use App\Filament\Resources\Widows\WidowResource;
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
