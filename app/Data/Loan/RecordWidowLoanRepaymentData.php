<?php

namespace App\Data\Loan;

use Spatie\LaravelData\Data;

class RecordWidowLoanRepaymentData extends Data
{
    public function __construct(
        public string $widowLoanId,
        public float $amount,
        public string $paidAt,
        public ?string $paymentMethod = null,
        public ?string $notes = null,
    ) {}

    public static function rules(): array
    {
        return [
            'widowLoanId' => 'required|uuid|exists:widow_loans,id',
            'amount' => 'required|numeric|min:0.01',
            'paidAt' => 'required|date',
            'paymentMethod' => 'nullable|string',
            'notes' => 'nullable|string',
        ];
    }
}
