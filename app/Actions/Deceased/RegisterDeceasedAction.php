<?php

namespace App\Actions\Deceased;

use App\Data\Deceased\DeceasedData;
use App\Models\Deceased;
use App\Services\RegistrationNumberService;

class RegisterDeceasedAction
{
    public function __construct(
        private RegistrationNumberService $regNoService
    ) {}

    public function execute(DeceasedData $data): Deceased
    {
        return Deceased::create([
            'first_name' => $data->firstName,
            'last_name' => $data->lastName,
            'middle_name' => $data->middleName, // ✅ FIXED
            'nin' => $data->nin,
            'reg_no' => $this->regNoService->generateDeceasedRegNo(),
            'address' => $data->address,
            'vulnerability_status' => $data->vulnerabilityStatus,
            'death_cause' => $data->deathCause,
            'death_place' => $data->deathPlace,
            'occupation' => $data->occupation,
            'number_of_orphans_left' => $data->numberOfOrphansLeft,
            'number_of_widows_left' => $data->numberOfWidowsLeft,
            'guardian_name' => $data->guardianName,
            'guardian_phone' => $data->guardianPhone, // ✅ FIXED
            'has_death_cert' => $data->hasDeathCert,
            'death_cert_url' => $data->deathCertUrl,
            'age' => $data->age, // ✅ FIXED
            'zone_id' => $data->zoneId, // ✅ FIXED
            'date_registered' => now(),
        ]);
    }
}
