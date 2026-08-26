<?php

// app/Filament/Coordinator/Resources/HealthcareRequestResource/Pages/CreateHealthcareRequest.php

namespace App\Filament\Coordinator\Resources\HealthcareRequestResource\Pages;

use App\Filament\Coordinator\Resources\HealthcareRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHealthcareRequest extends CreateRecord
{
    protected static string $resource = HealthcareRequestResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
