<?php

namespace App\Filament\Resources\Widows\Pages;

use App\Actions\Widow\RegisterWidowAction;
use App\Data\Widow\WidowData;
use App\Filament\Resources\Widows\WidowResource;
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
        // FIX: Normalize skills to ensure it is always an array
        $skills = $data['skills'] ?? [];
        if (is_string($skills)) {
            $skills = explode(',', $skills);
        }

        // 1. Map Filament form data to the WidowData DTO.
        $widowData = new WidowData(
            deceasedId: $data['deceased_id'],
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            middleName: $data['middle_name'] ?? null,
            nin: $data['nin'] ?? null,
            address: $data['address'] ?? null,
            skills: $skills, // Pass the normalized array
            isEligible: $data['is_eligible'] ?? true,
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
