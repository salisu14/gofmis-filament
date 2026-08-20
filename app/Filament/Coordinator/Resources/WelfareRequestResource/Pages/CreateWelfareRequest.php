<?php

// app/Filament\Coordinator\Resources\WelfareRequestResource/Pages/CreateWelfareRequest.php

namespace App\Filament\Coordinator\Resources\WelfareRequestResource\Pages;

use App\Filament\Coordinator\Resources\WelfareRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWelfareRequest extends CreateRecord
{
    protected static string $resource = WelfareRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['suggested_by'] = auth()->id();
        $data['status'] = \App\Enums\BeneficiaryStatus::PENDING->value;
        $data['collection_status'] = \App\Enums\CollectionStatus::NOT_COLLECTED->value;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Welfare request submitted successfully';
    }
}
