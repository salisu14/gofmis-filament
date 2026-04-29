<?php

namespace App\Filament\Resources\OrphanClasses\Pages;

use App\Filament\Resources\OrphanClasses\OrphanClassResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditOrphanClass extends EditRecord
{
    protected static string $resource = OrphanClassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
