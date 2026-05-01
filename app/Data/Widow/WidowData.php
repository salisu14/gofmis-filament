<?php

namespace App\Data\Widow;

use Spatie\LaravelData\Data;

class WidowData extends Data
{
    public function __construct(
        public string $deceasedId,
        public string $firstName,
        public string $lastName,
        public ?string $middleName = null,
        public ?string $nin = null,
        public ?string $address = null,

        // FIX: must be array to match model cast
        public ?array $skills = [],

        public ?bool $isEligible = true,

        // Optional (future-proof)
        public bool $isMarried = false,
    ) {}
}
