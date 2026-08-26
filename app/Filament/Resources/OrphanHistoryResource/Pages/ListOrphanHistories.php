<?php

namespace App\Filament\Resources\OrphanHistoryResource\Pages;

use App\Filament\Resources\OrphanHistoryResource;
use Filament\Resources\Pages\ListRecords;

class ListOrphanHistories extends ListRecords
{
    protected static string $resource = OrphanHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
