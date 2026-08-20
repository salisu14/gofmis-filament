<?php

// app/Filament/Coordinator/Resources/EducationRequestResource/Pages/CreateEducationRequest.php

namespace App\Filament\Coordinator\Resources\EducationRequestResource\Pages;

use App\Filament\Coordinator\Resources\EducationRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEducationRequest extends CreateRecord
{
    protected static string $resource = EducationRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v) && ! in_array($k, ['supporting_documents', 'items', 'verification_documents'], true)) {
                $data[$k] = reset($v);
            }
        }

        $data['status'] = 'pending';
        $data['verification_status'] = 'pending';

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Education request submitted successfully';
    }
}
