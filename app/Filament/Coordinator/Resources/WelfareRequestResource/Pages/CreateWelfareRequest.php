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
        $packageId = $data['welfare_package_id'] ?? null;
        $deceasedId = $data['deceased_id'] ?? null;

        if (is_array($packageId)) {
            $packageId = reset($packageId);
        }
        if (is_array($deceasedId)) {
            $deceasedId = reset($deceasedId);
        }

        if ($packageId && $deceasedId) {
            $exists = \App\Models\WelfareBeneficiary::where('welfare_package_id', $packageId)
                ->where('deceased_id', $deceasedId)
                ->exists();

            if ($exists) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'deceased_id' => 'This family already has a welfare request/allocation for the selected package.',
                ]);
            }
        }

        $data['suggested_by'] = auth()->id();
        $data['status'] = \App\Enums\BeneficiaryStatus::PENDING->value;
        $data['collection_status'] = \App\Enums\CollectionStatus::NOT_COLLECTED->value;

        return $data;
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $packageId = $data['welfare_package_id'] ?? null;
        $deceasedId = $data['deceased_id'] ?? null;

        if (is_array($packageId)) {
            $packageId = reset($packageId);
        }
        if (is_array($deceasedId)) {
            $deceasedId = reset($deceasedId);
        }

        if (! $packageId || ! $deceasedId) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'deceased_id' => 'A welfare package and family head are required.',
            ]);
        }

        try {
            return app(\App\Services\Welfare\WelfareNominationService::class)
                ->nominateSingle($packageId, $deceasedId, auth()->user());
        } catch (\RuntimeException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'deceased_id' => $e->getMessage(),
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Welfare nomination submitted successfully';
    }
}
