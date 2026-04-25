<?php

namespace App\Filament\Resources\OrphanEducation\Pages;

use App\Filament\Resources\OrphanEducation\OrphanEducationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOrphanEducation extends ViewRecord
{
    protected static string $resource = OrphanEducationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
