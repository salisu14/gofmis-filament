<?php

namespace App\Rules\Imprest;

use App\Models\ImprestFund;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SufficientFundBalance implements ValidationRule
{
    public function __construct(private readonly int $fundId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $fund = ImprestFund::find($this->fundId);

        if (!$fund) {
            $fail('Fund not found.');
            return;
        }

        if ($fund->current_balance < $value) {
            $fail("Insufficient balance. Available: {$fund->current_balance}");
        }
    }
}
