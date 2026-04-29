<?php
// app/Filament/Coordinator/Resources/EducationRequestResource/Pages/CreateEducationRequest.php

namespace App\Filament\Coordinator\Resources\EducationRequestResource\Pages;

use App\Filament\Coordinator\Resources\EducationRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEducationRequest extends CreateRecord
{
    protected static string $resource = EducationRequestResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Education request submitted successfully';
    }
}
