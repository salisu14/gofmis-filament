<?php

namespace App\Data\Loan;

use Spatie\LaravelData\Data;

class RecordRepaymentData extends Data
{
    public function __construct(
        public string $loanId,
        public float $amount,
        public string $paymentMethod, // 'CASH', 'BANK', etc.
        public ?string $receiptNumber = null,
        public ?string $notes = null
    ) {}
    public static function rules(): array
    {
        return [

        ];
    }
}
