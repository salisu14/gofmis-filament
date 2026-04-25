<?php

namespace App\Data\Loan;

use Spatie\LaravelData\Data;

class UpdateWidowLoanData extends Data
{
    public function __construct(
        public float $amount,
        public string $business,
        public ?string $description = null
    ) {}

    public static function rules(): array
    {
        return [

        ];
    }
}
