<?php

namespace App\Filament\Resources\OrphanEducation\Pages;

use App\Filament\Resources\OrphanEducation\OrphanEducationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOrphanEducation extends EditRecord
{
    protected static string $resource = OrphanEducationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
