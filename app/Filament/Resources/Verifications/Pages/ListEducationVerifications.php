<?php
// app/Filament/Admin/Resources/EducationVerificationResource/Pages/ListEducationVerifications.php

namespace App\Filament\Resources\Verifications\Pages;

use App\Filament\Resources\Verifications\EducationVerificationResource;
use Filament\Resources\Pages\ListRecords;

class ListEducationVerifications extends ListRecords
{
    protected static string $resource = EducationVerificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action — verifiers only verify, they don't create
        ];
    }
}
