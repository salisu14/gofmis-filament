<?php

namespace App\Filament\Resources\Deceased\Pages;

use App\Actions\Deceased\RegisterDeceasedAction;
use App\Data\Deceased\DeceasedData;
use App\Enums\VulnerabilityStatus;
use App\Filament\Resources\Deceased\DeceasedResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDeceased extends CreateRecord
{
    protected static string $resource = DeceasedResource::class;

    /**
     * Use the custom action to handle the creation logic.
     */
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        // Resolve the vulnerability status to a string if it's an enum instance
        $vulnerabilityStatus = $data['vulnerability_status'] instanceof VulnerabilityStatus
            ? $data['vulnerability_status']->value
            : (string)$data['vulnerability_status'];


        // 1. Map Filament data array to your Data Object
        $deceasedData = new DeceasedData(
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            middleName: $data['middle_name'] ?? null,
            nin: $data['nin'] ?? null,
            address: $data['address'] ?? null,
            vulnerabilityStatus: $vulnerabilityStatus,
            deathCause: $data['death_cause'] ?? null,
            deathPlace: $data['death_place'] ?? null,
            occupation: $data['occupation'] ?? null,
            numberOfOrphansLeft: $data['number_of_orphans_left'] ?? 0,
            numberOfWidowsLeft: $data['number_of_widows_left'] ?? 0,
            guardianName: $data['guardian_name'] ?? null,
            guardianPhone: $data['guardian_phone'] ?? null, // ✅ add
            hasDeathCert: $data['has_death_cert'] ?? false,
            deathCertUrl: $data['death_cert_url'] ?? null,
            age: $data['age'] ?? null, // ✅ add
            zoneId: $data['zone_id'] ?? null, // ✅ add
        );

        // 2. Resolve the action from the container and execute
        // This ensures the RegistrationNumberService is injected correctly
        return app(RegisterDeceasedAction::class)->execute($deceasedData);
    }
}
