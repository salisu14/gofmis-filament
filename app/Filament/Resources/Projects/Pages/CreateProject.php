<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProject extends CreateRecord
{
    protected static string $resource = ProjectResource::class;

//    protected function afterCreate(): void
//    {
//        // Log activity
//        activity()
//            ->performedOn($this->record)
//            ->causedBy(auth()->user())
//            ->log('project_created');
//    }
}
