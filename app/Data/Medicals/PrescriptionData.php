<?php

namespace App\Data\Medicals;

use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class PrescriptionData extends Data
{
    public function __construct(
        #[Required]
        public string $prescribable_id, // UUID of Orphan or Widow

        #[Required]
        public string $prescribable_type, // 'App\Models\Orphan' or 'App\Models\Widow'

        #[Required, StringType]
        public string $illness,

        #[Required, Date]
        public string $prescription_date,

        #[Numeric]
        public float $lab_test_cost = 0,

        #[Numeric]
        public float $drug_cost = 0,

        public ?string $doctor_name,
        public ?string $note,

        #[Required]
        public array $medication_ids // Array of Medication UUIDs
    ) {}
}
