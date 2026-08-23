<?php

namespace App\Filament\Coordinator\Resources\WidowHistoryResource\Pages;

use App\Filament\Coordinator\Resources\WidowHistoryResource;
use Filament\Resources\Pages\ListRecords;

class ListWidowHistories extends ListRecords
{
    protected static string $resource = WidowHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
