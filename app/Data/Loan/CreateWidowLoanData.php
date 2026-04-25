<?php

namespace App\Data\Loan;

use Spatie\LaravelData\Data;

class CreateWidowLoanData extends Data
{
    public function __construct(
        public string $widowId,
        public float $amount,
        public string $business,
        public ?string $description = null,
        public ?string $bankAccountId = null,
    ) {}

    public static function rules(): array
    {
        return [

        ];
    }


}
