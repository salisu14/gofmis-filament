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
            'nin' => $data->nin,
            'reg_no' => $this->regNoService->generateDeceasedRegNo(),
            'address' => $data->address,
            'vulnerability_status' => $data->vulnerabilityStatus,
            'death_cause' => $data->deathCause,
            'death_place' => $data->deathPlace,
            'occupation' => $data->occupation,
            'orphan_count' => $data->orphanCount,
            'widow_count' => $data->widowCount,
            'has_death_cert' => $data->hasDeathCert,
            'death_cert_url' => $data->deathCertUrl,
            'date_registered' => now(),
        ]);
    }
}
