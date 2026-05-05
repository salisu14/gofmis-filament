<?php

namespace App\Data\Loan;

use Spatie\LaravelData\Data;

class CreateWidowLoanData extends Data
{
    public function __construct(
        public string $widowId,
        public float $principalAmount,
        public ?int $durationMonths = null,
        public ?string $purpose = null,
        public ?string $bankAccountId = null,
        public ?string $repaymentFrequency = 'weekly',
    ) {}

    public static function rules(): array
    {
        return [
            'widowId'            => 'required|uuid|exists:widows,id',
            'principalAmount'    => 'required|numeric|min:1',
            'durationMonths'     => 'nullable|integer|min:1',
            'purpose'            => 'nullable|string|max:255',
            'bankAccountId'      => 'nullable|uuid|exists:bank_accounts,id',
            'repaymentFrequency' => 'nullable|string|in:weekly,monthly',
        ];
    }
}
