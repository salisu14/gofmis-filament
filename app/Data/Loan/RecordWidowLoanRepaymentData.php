<?php

namespace App\Data\Loan;

use Spatie\LaravelData\Data;

class RecordWidowLoanRepaymentData extends Data
{
    public function __construct(
        public string $widowLoanId,
        public float $amount,
        public string $paidAt,
        public ?string $bankAccountId = null,
        public ?string $paymentMethod = null,
        public ?string $notes = null,
    ) {}

    public static function rules(): array
    {
        return [
            'widowLoanId' => 'required|uuid|exists:widow_loans,id',
            'amount' => 'required|numeric|min:0.01',
            'paidAt' => 'required|date',
            'bankAccountId' => 'nullable|uuid|exists:bank_accounts,id',
            'paymentMethod' => 'nullable|string',
            'notes' => 'nullable|string',
        ];
    }
}
