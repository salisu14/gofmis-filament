<?php
// app/Filament\Coordinator\Resources\EducationRequestResource/Pages/ViewEducationRequest.php

namespace App\Filament\Coordinator\Resources\EducationRequestResource\Pages;

use App\Filament\Coordinator\Resources\EducationRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewEducationRequest extends ViewRecord
{
    protected static string $resource = EducationRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn($record) => $record->status === 'pending'),
        ];
    }
}
