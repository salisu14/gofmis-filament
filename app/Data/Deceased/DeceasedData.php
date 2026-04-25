<?php

namespace App\Data\Deceased;

use Spatie\LaravelData\Data;

class DeceasedData  extends Data
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $nin,
        public string $address,
        public string $vulnerabilityStatus, // 'A', 'B', or 'C'
        public string $deathCause,
        public string $deathPlace,
        public ?string $occupation = null,
        public ?int $orphanCount = 0,
        public ?int $widowCount = 0,
        public ?bool $hasDeathCert = false,
        public ?string $deathCertUrl = null,
    ) {}
}
