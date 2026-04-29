<?php
// app/Filament/Coordinator/Resources/HealthcareRequestResource/Pages/CreateHealthcareRequest.php

namespace App\Filament\Coordinator\Resources\HealthcareRequestResource\Pages;

use App\Filament\Coordinator\Resources\HealthcareRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHealthcareRequest extends CreateRecord
{
    protected static string $resource = HealthcareRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set the polymorphic type based on patient_type selection
        $data['prescribable_type'] = $data['patient_type'] === 'orphan'
            ? \App\Models\Orphan::class
            : \App\Models\Widow::class;

        unset($data['patient_type']);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
