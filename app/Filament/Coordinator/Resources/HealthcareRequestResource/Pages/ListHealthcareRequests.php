<?php
// app/Filament/Coordinator/Resources/HealthcareRequestResource/Pages/ListHealthcareRequests.php

namespace App\Filament\Coordinator\Resources\HealthcareRequestResource\Pages;

use App\Filament\Coordinator\Resources\HealthcareRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHealthcareRequests extends ListRecords
{
    protected static string $resource = HealthcareRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Healthcare Request'),
        ];
    }
}
