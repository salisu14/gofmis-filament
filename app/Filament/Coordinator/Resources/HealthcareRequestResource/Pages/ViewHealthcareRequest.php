<?php
// app/Filament/Coordinator/Resources/HealthcareRequestResource/Pages/ViewHealthcareRequest.php

namespace App\Filament\Coordinator\Resources\HealthcareRequestResource\Pages;

use App\Filament\Coordinator\Resources\HealthcareRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewHealthcareRequest extends ViewRecord
{
    protected static string $resource = HealthcareRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn($record) => $record->created_at->diffInDays(now()) <= 7),
        ];
    }
}
