<?php
// app/Filament/Coordinator/Resources/EducationRequestResource/Pages/ListEducationRequests.php

namespace App\Filament\Coordinator\Resources\EducationRequestResource\Pages;

use App\Filament\Coordinator\Resources\EducationRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEducationRequests extends ListRecords
{
    protected static string $resource = EducationRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Education Request'),
        ];
    }
}
