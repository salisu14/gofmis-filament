<?php

namespace App\Filament\Resources\Orphans\Pages;

use App\Actions\Orphan\RegisterOrphanAction;
use App\Data\Orphan\OrphanData;
use App\Filament\Resources\Orphans\OrphanResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateOrphan extends CreateRecord
{
    protected static string $resource = OrphanResource::class;
    /**
     * Intercepts the creation process to use the custom RegisterOrphanAction.
     * This ensures that registration numbers, educational records,
     * vocational skills, and eligibility status are all handled
     * according to the business logic defined in the Action.
     */
    protected function handleRecordCreation(array $data): Model
    {
        // 1. Map the Filament form data array to the OrphanData DTO.
        // We use named arguments to match the constructor properties of the DTO.
        $orphanData = new OrphanData(
            deceasedId: $data['deceased_id'],
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            middleName: $data['middle_name'] ?? null,
            gender: $data['gender'],
            birthDate: $data['birth_date'],
            picture: $data['picture_url'], // Passed as UploadedFile or path string
            nin: $data['nin'] ?? null,
            guardianName: $data['guardian_name'] ?? null,
            guardianPhone: $data['guardian_phone'] ?? null,
            address: $data['address'] ?? null,
            birthCertificatePath: $data['birth_certificate_path'] ?? null,
            educations: $data['educations'] ?? [],
            vocationalSkills: $data['vocationalSkills'] ?? [],
        );

        // 2. Execute the action through the service container to resolve dependencies
        // (RegistrationNumberService and OrphanEligibilityService).
        return app(RegisterOrphanAction::class)->execute($orphanData);
    }

    /**
     * Redirect back to the index after creation.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
