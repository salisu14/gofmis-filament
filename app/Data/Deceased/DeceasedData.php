<?php

namespace App\Data\Deceased;

use Spatie\LaravelData\Data;

class DeceasedData extends Data
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?string $middleName,
        public ?string $nin,
        public ?bool $hasNin,
        public ?string $address,
        public string $vulnerabilityStatus,
        public ?string $deathCause,
        public ?string $deathPlace,
        public ?string $occupation,
        public ?int $numberOfOrphansLeft,
        public ?int $numberOfWidowsLeft,
        public ?string $guardianName,
        public ?string $guardianPhone = null,
        public ?bool $hasDeathCert = false,
        public ?string $deathCertUrl = null,
        public ?int $age = null,
        public ?string $zoneId = null,
        public ?string $dateRegistered = null,
        public ?string $dateOfBirth = null,
        public ?string $dateOfDeath = null,
    ) {}
}
