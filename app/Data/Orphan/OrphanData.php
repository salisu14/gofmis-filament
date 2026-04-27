<?php

namespace App\Data\Orphan;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;

class OrphanData extends Data
{
    public function __construct(
        // Core
        public string       $deceasedId,
        public string       $firstName,
        public string       $lastName,
        public ?string      $middleName,
        public string       $gender,
        public string       $birthDate,
        public UploadedFile $picture,

        // Optional
        public ?string      $nin = null,
        public ?string      $guardianName = null,
        public ?string      $guardianPhone = null,
        public ?string      $address = null,
        public bool         $hasBirthCert = false,
        public ?string      $birthCertificatePath = null,

        /*
        |--------------------------------------------------------------------------
        | EDUCATION (NEW)
        |--------------------------------------------------------------------------
        | Multiple institutions per orphan
        | Example:
        | [
        |   {
        |     "institution_id": "uuid",
        |     "level": "primary",
        |     "class_level": "Primary 3",
        |     "school_fee": 50000,
        |     "fee_frequency": "termly",
        |     "is_current": true,
        |     "started_at": "2024-01-01"
        |   }
        | ]
        */
        public ?array       $educations = [],

        /*
        |--------------------------------------------------------------------------
        | VOCATIONAL SKILLS (INDEPENDENT)
        |--------------------------------------------------------------------------
        | Example:
        | [
        |   { "id": "uuid", "specify": "Tailoring - Female dresses" },
        |   { "id": "uuid", "specify": null }
        | ]
        */
        public ?array       $vocationalSkills = [],
    )
    {
    }
}
