<?php

namespace App\Filament\Resources\WidowLoans\Pages;

use App\Data\Loan\CreateWidowLoanData;
use App\Enums\LoanRepaymentFrequency;
use App\Filament\Resources\WidowLoans\WidowLoanResource;
use App\Services\WidowLoanService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateWidowLoan extends CreateRecord
{
    protected static string $resource = WidowLoanResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(WidowLoanService::class)->createLoan(new CreateWidowLoanData(
            widowId: $data['widow_id'],
            principalAmount: (float) $data['principal_amount'],
            durationMonths: isset($data['duration_months']) ? (int) $data['duration_months'] : null,
            purpose: $data['purpose'] ?? null,
            repaymentFrequency: ($data['repayment_frequency'] ?? null) instanceof LoanRepaymentFrequency
                ? $data['repayment_frequency']->value
                : ($data['repayment_frequency'] ?? 'weekly'),
            bankAccountId: $data['bank_account_id'] ?? null,
            disbursementBankId: $data['disbursement_bank_id'] ?? null,
            repaymentBankId: $data['repayment_bank_id'] ?? null,
            loanAgreementUrl: $data['loan_agreement_url'] ?? null,
        ));
    }
}
