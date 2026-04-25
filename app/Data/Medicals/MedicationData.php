<?php

namespace App\Data\Medicals;

use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class MedicationData extends Data
{
    public function __construct(
        #[Required, StringType]
        public string $name,

        public ?string $description
    ) {}
}
