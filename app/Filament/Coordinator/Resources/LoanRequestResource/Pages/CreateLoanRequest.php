<?php

// app/Filament/Coordinator/Resources/LoanRequestResource/Pages/CreateLoanRequest.php

namespace App\Filament\Coordinator\Resources\LoanRequestResource\Pages;

use App\Data\Loan\CreateWidowLoanData;
use App\Filament\Coordinator\Resources\LoanRequestResource;
use App\Services\WidowLoanService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateLoanRequest extends CreateRecord
{
    protected static string $resource = LoanRequestResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(WidowLoanService::class)->createLoan(new CreateWidowLoanData(
            widowId: $data['widow_id'],
            principalAmount: (float) $data['principal_amount'],
            durationMonths: isset($data['duration_months']) ? (int) $data['duration_months'] : null,
            purpose: $data['purpose'] ?? null,
            repaymentFrequency: $data['repayment_frequency'] ?? 'weekly',
        ));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Loan request created successfully';
    }
}
