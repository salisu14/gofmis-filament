<?php

namespace App\Filament\Coordinator\Resources\OrphanResource\Pages;

use App\Filament\Coordinator\Resources\OrphanResource;
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
