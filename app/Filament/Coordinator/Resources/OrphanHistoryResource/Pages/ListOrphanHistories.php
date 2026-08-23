<?php

namespace App\Filament\Coordinator\Resources\OrphanHistoryResource\Pages;

use App\Filament\Coordinator\Resources\OrphanHistoryResource;
use Filament\Resources\Pages\ListRecords;

class ListOrphanHistories extends ListRecords
{
    protected static string $resource = OrphanHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
