<?php

namespace App\Filament\Resources\OutOfPocketExpenditures\Pages;

use App\Filament\Resources\OutOfPocketExpenditures\OutOfPocketExpenditureResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOutOfPocketExpenditures extends ListRecords
{
    protected static string $resource = OutOfPocketExpenditureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
