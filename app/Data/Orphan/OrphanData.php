<?php

namespace App\Data\Orphan;

use Spatie\LaravelData\Data;


use Illuminate\Http\UploadedFile;

class OrphanData extends Data
{
    public function __construct(
        public string $deceasedId, // UUID
        public string $firstName,
        public string $lastName,
        public string $gender, // 'MALE' or 'FEMALE'
        public string $birthDate, // Y-m-d format
        public UploadedFile $picture, // Required Image
        public ?string $nin = null,
        public ?string $guardianName = null,
        public ?string $guardianPhone = null,
        public ?string $address = null,
        public ?string $birthCertificatePath = null,
        // Optional Education IDs if created beforehand
        public ?string $westernEducationId = null,
        public ?string $islamiyyaEducationId = null,
    ) {}
}
