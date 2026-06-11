<?php

namespace App\Data\Bank;

use Spatie\LaravelData\Data;

class CreateBankAccountData extends Data
{
    public function __construct(
        public string $name,
        public float $initialBalance, // Maps to 'amount' in DB
        public string $userId,
        public ?string $accountNumber = null,
        public ?string $parentBankAccountId = null,
    ) {}
}
