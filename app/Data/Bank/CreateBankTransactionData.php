<?php

namespace App\Data\Bank;

use Spatie\LaravelData\Data;

class CreateBankTransactionData extends Data
{
    public function __construct(
        public string $bankAccountId,
        public float $amount,
        public string $type, // 'DEBIT' or 'CREDIT'
        public ?string $reference = null, // e.g., Loan ID or Repayment ID
        public ?string $description = null
    ) {}
}
