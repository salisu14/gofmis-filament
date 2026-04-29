<?php

namespace App\Filament\Coordinator\Resources\WidowResource\Pages;

use App\Actions\Widow\RegisterWidowAction;
use App\Data\Widow\WidowData;
use App\Filament\Coordinator\Resources\WidowResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateWidow extends CreateRecord
{
    protected static string $resource = WidowResource::class;

    /**
     * Intercepts the creation process to use the custom RegisterWidowAction.
     * This ensures that registration numbers, sequence counting,
     * and deceased-head updates are handled by the action logic.
     */
    protected function handleRecordCreation(array $data): Model
    {
        // 1. Map Filament form data to the WidowData DTO.
        $widowData = new WidowData(
            deceasedId: $data['deceased_id'],
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            middleName: $data['middle_name'] ?: null,
            nin: $data['nin'] ?? null,
            address: $data['address'] ?? null,
            skills: $data['skills'] ?? [],
            isMarried: $data['is_married'] ?? false,
        );

        // 2. Execute the action through the container to resolve dependencies.
        // The action handles the registration number and deceased-head counters.
        return app(RegisterWidowAction::class)->execute($widowData);
    }

    /**
     * Redirect back to the index after creation.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
