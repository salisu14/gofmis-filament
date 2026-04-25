<?php

namespace App\Data\Loan;

use Spatie\LaravelData\Data;

class UpdateWidowLoanData extends Data
{
    public function __construct(
        public float $principalAmount,
        public ?int $durationMonths = null,
        public ?string $purpose = null
    ) {}

    public static function rules(): array
    {
        return [

        ];
    }
}
