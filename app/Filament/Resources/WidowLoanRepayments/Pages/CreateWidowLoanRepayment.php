<?php

namespace App\Filament\Resources\WidowLoanRepayments\Pages;

use App\Data\Loan\RecordWidowLoanRepaymentData;
use App\Filament\Resources\WidowLoanRepayments\WidowLoanRepaymentResource;
use App\Services\WidowLoanService;
use Filament\Resources\Pages\CreateRecord;

class CreateWidowLoanRepayment extends CreateRecord
{
    protected static string $resource = WidowLoanRepaymentResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        // Pass the form data directly into the service instead of default Eloquent creation
        return app(WidowLoanService::class)->recordRepayment(
            new RecordWidowLoanRepaymentData(
                widowLoanId:   $data['widow_loan_id'],
                amount:        (float) $data['amount'],
                paidAt:        $data['paid_at'],
                bankAccountId: $data['bank_account_id'] ?? null,
                paymentMethod: $data['payment_method'] ?? null,
                notes:         $data['notes'] ?? null,
            )
        );
    }
}
