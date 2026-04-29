<?php
// app/Filament/Coordinator/Resources/LoanRequestResource/Pages/CreateLoanRequest.php

namespace App\Filament\Coordinator\Resources\LoanRequestResource\Pages;

use App\Filament\Coordinator\Resources\LoanRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLoanRequest extends CreateRecord
{
    protected static string $resource = LoanRequestResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Loan request created successfully';
    }
}
