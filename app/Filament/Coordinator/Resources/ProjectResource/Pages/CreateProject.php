<?php

namespace App\Filament\Coordinator\Resources\ProjectResource\Pages;

use App\Filament\Coordinator\Resources\ProjectResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateProject extends CreateRecord
{
    protected static string $resource = ProjectResource::class;

    protected function afterCreate(): void
    {
        Notification::make()
            ->title('Project proposal submitted')
            ->body('Your project has been submitted for approval.')
            ->success()
            ->send();
    }
}
