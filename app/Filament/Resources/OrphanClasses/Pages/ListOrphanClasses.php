<?php

namespace App\Filament\Resources\OrphanClasses\Pages;

use App\Filament\Resources\OrphanClasses\OrphanClassResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrphanClasses extends ListRecords
{
    protected static string $resource = OrphanClassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
