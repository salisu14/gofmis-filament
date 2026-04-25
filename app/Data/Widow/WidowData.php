<?php

namespace App\Data\Widow;

use Spatie\LaravelData\Data;

class WidowData extends Data
{
    public function __construct(
        public string $deceasedId, // UUID
        public string $firstName,
        public string $lastName,
        public string $nin,
        public string $address,
        public ?string $skills = null,
    ) {}
}
