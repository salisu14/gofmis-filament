<?php

namespace App\Filament\Resources\Orphans\Pages;

use App\Filament\Resources\Orphans\OrphanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrphans extends ListRecords
{
    protected static string $resource = OrphanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
